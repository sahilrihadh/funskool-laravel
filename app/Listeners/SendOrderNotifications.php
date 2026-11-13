<?php

namespace App\Listeners;

use App\Enums\PaymentStatus;
use App\Events\OrderPaymentStatusChanged;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Events\ShipmentCreated;
use App\Models\Order;
use App\Models\Setting;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\ShipmentDispatchedNotification;
use App\Notifications\StaffAlertNotification;
use App\Services\StaffNotifier;
use Illuminate\Support\Facades\Notification;

/** Customer + staff messages for order lifecycle events (one message per change). */
class SendOrderNotifications
{
    public function __construct(protected StaffNotifier $staff) {}

    public function handleOrderPlaced(OrderPlaced $event): void
    {
        $order = $event->order;

        if ($this->notifiesCustomer($order)) {
            OrderPlacedNotification::sendFor($order);
        }

        $this->staff->notify(
            'orders.view',
            "New order {$order->order_number}",
            $order->contactName().' · '.Setting::get('currency_symbol', '₹').number_format((float) $order->total, 2).($order->salesChannel ? " · {$order->salesChannel->name}" : ''),
            url("/admin/orders/{$order->id}"),
            'heroicon-o-shopping-bag',
            'success',
        );

        if ($email = Setting::get('order_alert_email')) {
            Notification::route('mail', $email)->notify(new StaffAlertNotification(
                "New order {$order->order_number} — ".Setting::get('currency_symbol', '₹').number_format((float) $order->total, 2),
                [
                    "Customer: {$order->contactName()} ({$order->contactEmail()})",
                    'Channel: '.($order->salesChannel?->name ?? 'Storefront / admin'),
                    'Payment: '.($order->paymentMethod?->name ?? '—').' ('.$order->payment_status.')',
                ],
                url("/admin/orders/{$order->id}"),
            ));
        }
    }

    public function handleOrderStatusChanged(OrderStatusChanged $event): void
    {
        if (in_array($event->to, OrderStatusUpdatedNotification::NOTIFY, true) && $this->notifiesCustomer($event->order)) {
            OrderStatusUpdatedNotification::sendFor($event->order, $event->to);
        }
    }

    public function handleOrderPaymentStatusChanged(OrderPaymentStatusChanged $event): void
    {
        // Only for payments completed after the order was placed (gateway redirect / manual record).
        if ($event->to === PaymentStatus::Paid->value && $event->from !== $event->to && $this->notifiesCustomer($event->order)) {
            PaymentReceivedNotification::sendFor($event->order);
        }
    }

    public function handleShipmentCreated(ShipmentCreated $event): void
    {
        $order = $event->shipment->order;

        if ($this->notifiesCustomer($order)) {
            ShipmentDispatchedNotification::sendFor($order, $event->shipment);
        }
    }

    protected function notifiesCustomer(Order $order): bool
    {
        return $order->salesChannel?->send_customer_notifications ?? true;
    }
}
