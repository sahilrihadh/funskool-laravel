<?php

namespace App\Listeners;

use App\Events\OrderPaymentStatusChanged;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Events\ProductChanged;
use App\Events\ShipmentCreated;
use App\Events\StockLevelChanged;
use App\Http\Resources\Channel\ChannelOrderResource;
use App\Http\Resources\Channel\ChannelProductResource;
use App\Models\Order;
use App\Services\ChannelWebhookService;

/** Fans domain events out to connected websites as signed webhooks. */
class DispatchChannelWebhooks
{
    public function __construct(protected ChannelWebhookService $webhooks) {}

    public function handleOrderPlaced(OrderPlaced $event): void
    {
        $this->forOrder('order.created', $event->order);
    }

    public function handleOrderStatusChanged(OrderStatusChanged $event): void
    {
        $this->forOrder('order.status_changed', $event->order, ['previous_status' => $event->from]);
    }

    public function handleOrderPaymentStatusChanged(OrderPaymentStatusChanged $event): void
    {
        $this->forOrder('order.payment_changed', $event->order, ['previous_payment_status' => $event->from]);
    }

    public function handleShipmentCreated(ShipmentCreated $event): void
    {
        $this->forOrder('shipment.created', $event->shipment->order);
    }

    public function handleProductChanged(ProductChanged $event): void
    {
        $product = $event->product->fresh(['category', 'brand', 'images', 'variants']);

        if ($product) {
            $this->webhooks->dispatch('product.updated', ['product' => (new ChannelProductResource($product))->resolve()]);
        }
    }

    public function handleStockLevelChanged(StockLevelChanged $event): void
    {
        $this->webhooks->dispatch('inventory.updated', [
            'sku' => $event->variant?->sku ?? $event->product->sku,
            'product_sku' => $event->product->sku,
            'variant_id' => $event->variant?->id,
            'previous_quantity' => $event->previous,
            'stock_quantity' => $event->current,
            'in_stock' => $event->current > 0,
        ]);
    }

    protected function forOrder(string $event, Order $order, array $extra = []): void
    {
        if (! $order->salesChannel) {
            return;
        }

        $this->webhooks->dispatch($event, [
            ...$extra,
            'order' => (new ChannelOrderResource($order->fresh()))->resolve(),
        ], $order->salesChannel);
    }
}
