<?php

namespace App\Notifications;

class PaymentReceivedNotification extends OrderNotification
{
    protected function subject(): string
    {
        return "Payment received for order {$this->order->order_number}";
    }

    protected function lines(): array
    {
        return ["We've received your payment of ".$this->money($this->order->total).'. Your order is now being processed.'];
    }

    protected function smsTemplate(): string
    {
        return 'payment_received';
    }
}
