<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\SupportTicket;
use App\Models\WebhookDelivery;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Work queues — every card links to the list that clears it. */
class NeedsActionWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected ?string $heading = 'Needs attention';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission('orders.view');
    }

    protected function getStats(): array
    {
        $orders = fn (string $tab) => route('filament.admin.resources.orders.index', ['tab' => $tab]);

        $newOrders = Order::query()->where('status', OrderStatus::Pending->value)->count();
        $toFulfil = Order::query()->whereIn('status', OrderStatus::toFulfil())->count();
        $returns = ReturnRequest::query()->whereIn('status', ['requested', 'approved', 'received'])->count();
        $refundDue = Order::query()->where('status', OrderStatus::Cancelled->value)->whereIn('payment_status', PaymentStatus::captured())->whereColumn('refunded_amount', '<', 'total')->count();
        $onHold = Order::query()->where('status', OrderStatus::OnHold->value)->count();
        $lowStock = Product::query()->where('is_active', true)->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count()
            + ProductVariant::query()->where('is_active', true)->where('stock_quantity', '<=', 0)->count();
        $failedWebhooks = WebhookDelivery::query()->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count();

        $card = fn (string $label, int $value, string $url, string $hint, string $alertColor = 'warning') => Stat::make($label, $value)
            ->description($hint)
            ->color($value > 0 ? $alertColor : 'success')
            ->url($url);

        return array_filter([
            $card('New orders', $newOrders, $orders('new'), 'Awaiting confirmation / payment'),
            $card('To fulfil', $toFulfil, $orders('to_fulfil'), 'Confirmed, not yet shipped'),
            $card('Open returns', $returns, route('filament.admin.resources.return-requests.index'), 'Requested, approved or received'),
            $card('Refunds due', $refundDue, $orders('refund_due'), 'Cancelled after payment', 'danger'),
            $onHold ? $card('On hold', $onHold, $orders('on_hold'), 'Payment mismatch or manual hold', 'danger') : null,
            auth()->user()->hasPermission('support.view')
                ? $card('Open tickets', SupportTicket::query()->where('status', TicketStatus::Open->value)->count(), route('filament.admin.resources.support-tickets.index', ['tab' => 'pending']), 'Waiting for a staff reply')
                : null,
            auth()->user()->hasPermission('inventory.view')
                ? $card('Low / out of stock', $lowStock, route('filament.admin.resources.products.index', ['tab' => 'low_stock']), 'At or below threshold', 'danger')
                : null,
            $failedWebhooks && auth()->user()->hasPermission('integrations.view')
                ? $card('Failed webhooks', $failedWebhooks, route('filament.admin.resources.sales-channels.index'), 'Last 7 days — connected sites may be out of sync', 'danger')
                : null,
        ]);
    }
}
