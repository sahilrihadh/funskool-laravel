<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\ShipmentCreated;
use App\Models\Order;
use App\Models\Shipment;
use App\Shipping\ShiprocketClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShippingService
{
    public function __construct(
        protected OrderService $orders,
        protected ShiprocketClient $shiprocket,
    ) {}

    /** @return array<string, string> */
    public function availableCarriers(): array
    {
        return array_filter([
            'manual' => 'Manual — own courier / enter tracking yourself',
            'shiprocket' => $this->shiprocket->isConfigured() ? 'Shiprocket — create shipment, AWB & label automatically' : null,
        ]);
    }

    public function createManual(Order $order, string $courierName, ?string $trackingNumber, ?string $trackingUrl): Shipment
    {
        return $this->ship($order, [
            'carrier' => 'manual',
            'courier_name' => $courierName,
            'tracking_number' => $trackingNumber,
            'tracking_url' => $trackingUrl,
        ]);
    }

    public function createViaShiprocket(Order $order): Shipment
    {
        $this->assertShippable($order);

        $result = $this->shiprocket->createShipment($order);

        return $this->ship($order, [
            ...$result,
            'carrier' => 'shiprocket',
            'tracking_url' => $result['tracking_number'] ? $this->shiprocket->trackingUrl($result['tracking_number']) : null,
        ]);
    }

    public function markDelivered(Shipment $shipment): void
    {
        $this->applyStatus($shipment, 'delivered', 'Delivered');
    }

    /** Applies a carrier tracking update (webhook or polling). */
    public function applyStatus(Shipment $shipment, string $status, ?string $event = null): void
    {
        if ($shipment->status === $status) {
            return;
        }

        DB::transaction(function () use ($shipment, $status, $event) {
            $shipment->update([
                'status' => $status,
                'last_event' => $event,
                'delivered_at' => $status === 'delivered' ? now() : $shipment->delivered_at,
            ]);

            $order = $shipment->order;

            if ($status === 'delivered' && $order->status === OrderStatus::Shipped->value) {
                $this->orders->updateStatus($order, OrderStatus::Delivered->value, $event);
            }

            if ($status === 'rto' && $order->status === OrderStatus::Shipped->value) {
                $this->orders->updateStatus($order, OrderStatus::Returned->value, 'Returned to origin by courier.');
            }
        });
    }

    /** Polls Shiprocket for shipments still in transit (scheduled hourly). */
    public function syncTracking(): int
    {
        if (! $this->shiprocket->isConfigured()) {
            return 0;
        }

        $updated = 0;

        Shipment::query()
            ->where('carrier', 'shiprocket')
            ->whereNotNull('tracking_number')
            ->whereIn('status', ['created', 'in_transit', 'out_for_delivery'])
            ->each(function (Shipment $shipment) use (&$updated) {
                $tracking = $this->shiprocket->track($shipment->tracking_number);
                $current = data_get($tracking, 'shipment_track.0.current_status');
                $status = ShiprocketClient::mapStatus($current);

                if ($status && $status !== $shipment->status) {
                    $this->applyStatus($shipment, $status, $current);
                    $updated++;
                }
            });

        return $updated;
    }

    protected function ship(Order $order, array $attributes): Shipment
    {
        $this->assertShippable($order);

        $shipment = DB::transaction(function () use ($order, $attributes) {
            $shipment = $order->shipments()->create([
                ...$attributes,
                'status' => 'in_transit',
                'shipped_at' => now(),
            ]);

            if ($order->status !== OrderStatus::Shipped->value) {
                $note = trim(($attributes['courier_name'] ?? $attributes['carrier']).' '.($attributes['tracking_number'] ?? ''));
                $this->orders->updateStatus($order, OrderStatus::Shipped->value, "Shipped: {$note}");
            }

            return $shipment;
        });

        ShipmentCreated::dispatch($shipment);

        return $shipment;
    }

    protected function assertShippable(Order $order): void
    {
        $status = $order->statusEnum();

        if (! $status || (! $status->canTransitionTo(OrderStatus::Shipped))) {
            throw ValidationException::withMessages([
                'order' => "Orders in \"{$status?->getLabel()}\" status can't be shipped. Confirm the order first.",
            ]);
        }
    }
}
