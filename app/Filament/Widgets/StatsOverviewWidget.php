<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ResolvesDashboardFilters;
use App\Models\PaymentTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Sales KPIs. Definitions (also shown on hover):
 *  - Orders: placed in the period, excluding cancelled.
 *  - Gross sales: item subtotals of those orders, before discounts.
 *  - Net sales: gross − discounts − refunds issued in the period.
 *  - AOV: order totals (incl. shipping & tax) ÷ orders.
 */
class StatsOverviewWidget extends BaseWidget
{
    use ResolvesDashboardFilters;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission('reports.view');
    }

    protected function metrics(Carbon $start, Carbon $end): array
    {
        $orders = $this->ordersQuery()->whereBetween('created_at', [$start, $end])->where('status', '!=', 'cancelled');

        $count = (clone $orders)->count();
        $gross = (float) (clone $orders)->sum('subtotal');
        $discounts = (float) (clone $orders)->sum('discount_amount');
        $totals = (float) (clone $orders)->sum('total');

        $refunds = (float) PaymentTransaction::query()
            ->where('type', 'refund')
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('order_id', $this->ordersQuery()->select('id'))
            ->sum('amount');

        return [
            'orders' => $count,
            'gross' => $gross,
            'net' => $gross - $discounts - $refunds,
            'aov' => $count ? $totals / $count : 0,
            'refunds' => $refunds,
        ];
    }

    protected function getStats(): array
    {
        $current = $this->metrics(...$this->period());
        $previous = $this->metrics(...$this->previousPeriod());

        $money = fn (float $value) => '₹'.number_format($value, 0);

        $trend = function (string $key, bool $higherIsBetter = true) use ($current, $previous): array {
            if (! $previous[$key]) {
                return [null, 'gray', null];
            }

            $change = ($current[$key] - $previous[$key]) / $previous[$key] * 100;
            $good = $higherIsBetter ? $change >= 0 : $change <= 0;

            return [
                ($change >= 0 ? '+' : '').number_format($change, 1).'% vs previous period',
                $good ? 'success' : 'danger',
                $change >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down',
            ];
        };

        $stat = function (string $label, string $value, string $key, string $definition, bool $higherIsBetter = true) use ($trend) {
            [$description, $color, $icon] = $trend($key, $higherIsBetter);

            return Stat::make($label, $value)
                ->description($description ?? $definition)
                ->descriptionIcon($icon)
                ->color($color)
                ->extraAttributes(['title' => $definition]);
        };

        return [
            $stat('Net sales', $money($current['net']), 'net', 'Gross − discounts − refunds issued, '.$this->periodLabel())
                ->url(route('filament.admin.resources.orders.index')),
            $stat('Gross sales', $money($current['gross']), 'gross', 'Item subtotals before discounts (excl. cancelled)'),
            $stat('Orders', number_format($current['orders']), 'orders', 'Placed '.$this->periodLabel().', excl. cancelled')
                ->url(route('filament.admin.resources.orders.index')),
            $stat('Avg. order value', $money($current['aov']), 'aov', 'Order totals incl. shipping & tax ÷ orders'),
            $stat('Refunds', $money($current['refunds']), 'refunds', 'Refunds issued '.$this->periodLabel(), higherIsBetter: false),
        ];
    }
}
