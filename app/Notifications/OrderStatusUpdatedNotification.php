<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;

class OrderStatusUpdatedNotification extends OrderNotification
{
    /** Statuses worth telling the customer about (shipped has its own message). */
    public const NOTIFY = ['confirmed', 'delivered', 'cancelled', 'returned', 'refunded'];

    public function __construct(Order $order, public string $status)
    {
        parent::__construct($order);
    }

    protected function subject(): string
    {
        return "Your order {$this->order->order_number} is ".strtolower(OrderStatus::labelFor($this->status));
    }

    protected function lines(): array
    {
        return [match ($this->status) {
            'confirmed' => "Good news — your order is confirmed and we're getting it ready.",
            'delivered' => 'Your order has been delivered. We hope you love it!',
            'cancelled' => 'Your order has been cancelled.'.($this->order->isPaid() ? ' Any amount paid will be refunded to the original payment method.' : ''),
            'returned' => "We've received your return.",
            'refunded' => 'Your refund has been processed.',
            default => 'Your order status is now: '.OrderStatus::labelFor($this->status).'.',
        }];
    }

    protected function smsTemplate(): string
    {
        return 'order_status';
    }
}
