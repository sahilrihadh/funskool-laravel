<?php

namespace App\Shipping;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Shiprocket API client: create order → assign AWB → label → track.
 * Docs: https://apidocs.shiprocket.in/
 */
class ShiprocketClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.shiprocket.email')) && filled(config('services.shiprocket.password'));
    }

    /**
     * @return array{provider_order_id: string, provider_shipment_id: string, tracking_number: ?string, courier_name: ?string, label_url: ?string}
     */
    public function createShipment(Order $order): array
    {
        $order->loadMissing('items.product', 'paymentMethod');
        $address = $order->shipping_address ?? [];
        $dimensions = config('services.shiprocket.default_dimensions');
        $weight = max(0.1, (float) $order->items->sum(fn ($item) => ((float) ($item->product?->weight ?? 0.5)) * $item->quantity));

        $created = $this->client()->post('/orders/create/adhoc', [
            'order_id' => $order->order_number,
            'order_date' => $order->created_at->format('Y-m-d H:i'),
            'pickup_location' => config('services.shiprocket.pickup_location'),
            'billing_customer_name' => $address['full_name'] ?? $order->contactName(),
            'billing_last_name' => '',
            'billing_address' => $address['line1'] ?? '',
            'billing_address_2' => $address['line2'] ?? '',
            'billing_city' => $address['city'] ?? '',
            'billing_pincode' => $address['postal_code'] ?? '',
            'billing_state' => $address['state'] ?? '',
            'billing_country' => $address['country'] ?? 'India',
            'billing_email' => $order->contactEmail(),
            'billing_phone' => $address['phone'] ?? $order->contactPhone(),
            'shipping_is_billing' => true,
            'order_items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'sku' => $item->sku ?: "ITEM-{$item->id}",
                'units' => $item->quantity,
                'selling_price' => (float) $item->price,
            ])->all(),
            'payment_method' => $order->isPaid() ? 'Prepaid' : 'COD',
            'shipping_charges' => (float) $order->shipping_amount,
            'total_discount' => (float) $order->discount_amount,
            'sub_total' => (float) $order->total,
            'length' => $dimensions['length'],
            'breadth' => $dimensions['breadth'],
            'height' => $dimensions['height'],
            'weight' => round($weight, 2),
        ]);

        if ($created->failed() || ! $created->json('shipment_id')) {
            throw new RuntimeException('Shiprocket: '.($created->json('message') ?? 'could not create the shipment order'));
        }

        $shipmentId = (string) $created->json('shipment_id');
        $awb = $this->client()->post('/courier/assign/awb', ['shipment_id' => $shipmentId]);
        $label = $this->client()->post('/courier/generate/label', ['shipment_id' => [$shipmentId]]);

        return [
            'provider_order_id' => (string) $created->json('order_id'),
            'provider_shipment_id' => $shipmentId,
            'tracking_number' => $awb->json('response.data.awb_code'),
            'courier_name' => $awb->json('response.data.courier_name'),
            'label_url' => $label->json('label_url'),
        ];
    }

    public function track(string $awb): ?array
    {
        $response = $this->client()->get("/courier/track/awb/{$awb}");

        return $response->successful() ? $response->json('tracking_data') : null;
    }

    public function trackingUrl(string $awb): string
    {
        return "https://shiprocket.co/tracking/{$awb}";
    }

    /** Maps a Shiprocket "current_status" string to a Shipment status. */
    public static function mapStatus(?string $status): ?string
    {
        $status = strtoupper(trim((string) $status));

        return match (true) {
            $status === '' => null,
            $status === 'DELIVERED' => 'delivered',
            str_contains($status, 'OUT FOR DELIVERY') => 'out_for_delivery',
            str_starts_with($status, 'RTO') => 'rto',
            str_contains($status, 'CANCEL') => 'cancelled',
            str_contains($status, 'UNDELIVERED'), str_contains($status, 'LOST'), str_contains($status, 'DAMAGED') => 'failed',
            default => 'in_transit',
        };
    }

    protected function token(): string
    {
        // Shiprocket tokens last 10 days; refresh a day early.
        return Cache::remember('shiprocket.token', now()->addDays(9), function () {
            $response = Http::baseUrl(config('services.shiprocket.base_url'))
                ->acceptJson()
                ->timeout(20)
                ->post('/auth/login', [
                    'email' => config('services.shiprocket.email'),
                    'password' => config('services.shiprocket.password'),
                ]);

            if ($response->failed() || ! $response->json('token')) {
                throw new RuntimeException('Shiprocket: authentication failed — check SHIPROCKET_EMAIL / SHIPROCKET_PASSWORD.');
            }

            return $response->json('token');
        });
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(config('services.shiprocket.base_url'))
            ->withToken($this->token())
            ->acceptJson()
            ->timeout(30);
    }
}
