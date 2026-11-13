<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Shipment;
use App\Sms\SmsMessage;

class ShipmentDispatchedNotification extends OrderNotification
{
    public function __construct(Order $order, public Shipment $shipment)
    {
        parent::__construct($order);
    }

    protected function subject(): string
    {
        return "Your order {$this->order->order_number} has shipped";
    }

    protected function lines(): array
    {
        return array_filter([
            'Your order is on its way'.($this->shipment->courier_name ? " with {$this->shipment->courier_name}" : '').'.',
            $this->shipment->tracking_number ? "Tracking number: {$this->shipment->tracking_number}" : null,
            $this->shipment->tracking_url ? "Track it here: {$this->shipment->tracking_url}" : null,
        ]);
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return new SmsMessage(
            text: $this->storeName().": Order {$this->order->order_number} has shipped.".($this->shipment->tracking_url ? " Track: {$this->shipment->tracking_url}" : ''),
            template: 'order_shipped',
            variables: [
                'var1' => $this->order->order_number,
                'var2' => (string) ($this->shipment->tracking_number ?? ''),
                'var3' => (string) ($this->shipment->tracking_url ?? ''),
            ],
        );
    }

    protected function smsTemplate(): string
    {
        return 'order_shipped';
    }
}
