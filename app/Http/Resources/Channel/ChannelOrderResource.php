<?php

namespace App\Http\Resources\Channel;

use App\Http\Controllers\Storefront\PaymentController;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class ChannelOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['items', 'shipments', 'paymentMethod', 'shippingMethod']);

        return [
            'order_number' => $this->order_number,
            'external_reference' => $this->external_reference,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'currency' => $this->currency,
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'shipping_amount' => (float) $this->shipping_amount,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,
            'refunded_amount' => (float) $this->refunded_amount,
            'customer' => [
                'name' => $this->customer_name,
                'email' => $this->customer_email,
                'phone' => $this->customer_phone,
            ],
            'shipping_address' => $this->shipping_address,
            'billing_address' => $this->billing_address,
            'payment_method' => $this->paymentMethod?->code,
            'shipping_method' => $this->shippingMethod?->name,
            'items' => $this->items->map(fn ($item) => [
                'sku' => $item->sku,
                'name' => $item->product_name,
                'variant' => $item->variant_label,
                'quantity' => $item->quantity,
                'price' => (float) $item->price,
                'total' => (float) $item->total,
            ]),
            'shipments' => $this->shipments->map(fn ($shipment) => [
                'carrier' => $shipment->courier_name ?? $shipment->carrier,
                'tracking_number' => $shipment->tracking_number,
                'tracking_url' => $shipment->tracking_url,
                'status' => $shipment->status,
                'shipped_at' => $shipment->shipped_at?->toIso8601String(),
                'delivered_at' => $shipment->delivered_at?->toIso8601String(),
            ]),
            // When requires_action is true, redirect the customer to payment_url (signed, valid 48h).
            'payment' => [
                'requires_action' => $needsPayment = app(PaymentService::class)->needsOnlinePayment($this->resource),
                'payment_url' => $needsPayment ? PaymentController::startUrl($this->resource) : null,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
