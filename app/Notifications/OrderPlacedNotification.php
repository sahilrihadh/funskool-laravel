<?php

namespace App\Notifications;

class OrderPlacedNotification extends OrderNotification
{
    protected function subject(): string
    {
        return "We've received your order {$this->order->order_number}";
    }

    protected function lines(): array
    {
        $this->order->loadMissing('items');

        return [
            'Thank you! Your order has been received'.($this->order->isPaid() ? ' and paid.' : '.'),
            ...$this->order->items->map(fn ($item) => "• {$item->product_name}".($item->variant_label ? " ({$item->variant_label})" : '')." × {$item->quantity} — ".$this->money($item->total))->all(),
            "We'll let you know as soon as it ships.",
        ];
    }

    protected function smsTemplate(): string
    {
        return 'order_placed';
    }
}
