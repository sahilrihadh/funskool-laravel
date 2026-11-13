<?php

namespace App\Listeners;

use App\Events\StockLevelChanged;
use App\Models\Setting;
use App\Notifications\StaffAlertNotification;
use App\Services\StaffNotifier;
use Illuminate\Support\Facades\Notification;

class SendLowStockAlert
{
    public function __construct(protected StaffNotifier $staff) {}

    /** Alerts once, when stock crosses the threshold — not on every sale below it. */
    public function handle(StockLevelChanged $event): void
    {
        $threshold = (int) $event->product->low_stock_threshold;

        if (! ($event->previous > $threshold && $event->current <= $threshold)) {
            return;
        }

        $name = $event->product->name.($event->variant ? " ({$event->variant->label})" : '');

        $this->staff->notify(
            'inventory.view',
            $event->current === 0 ? "Out of stock: {$name}" : "Low stock: {$name}",
            "{$event->current} left (threshold {$threshold})",
            url("/admin/products/{$event->product->id}/edit"),
            'heroicon-o-archive-box-x-mark',
            'danger',
        );

        $email = Setting::get('low_stock_alert_email') ?: Setting::get('order_alert_email');

        if (! $email) {
            return;
        }

        Notification::route('mail', $email)->notify(new StaffAlertNotification(
            $event->current === 0 ? "Out of stock: {$name}" : "Low stock: {$name}",
            ["{$name} now has {$event->current} unit(s) left (threshold {$threshold}).", 'SKU: '.($event->variant?->sku ?? $event->product->sku)],
            url("/admin/products/{$event->product->id}/edit"),
        ));
    }
}
