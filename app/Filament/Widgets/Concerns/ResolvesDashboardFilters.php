<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Order;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Period + channel filters shared by the dashboard widgets (times in the app timezone). */
trait ResolvesDashboardFilters
{
    use InteractsWithPageFilters;

    /** @return array{0: Carbon, 1: Carbon} */
    protected function period(): array
    {
        $period = $this->pageFilters['period'] ?? '30';
        $end = now()->endOfDay();

        $start = $period === 'today' ? now()->startOfDay() : now()->subDays((int) $period - 1)->startOfDay();

        return [$start, $end];
    }

    /** @return array{0: Carbon, 1: Carbon} the same-length range immediately before */
    protected function previousPeriod(): array
    {
        [$start, $end] = $this->period();
        $days = $start->diffInDays($end) + 1;

        return [$start->copy()->subDays((int) round($days)), $start->copy()->subSecond()];
    }

    protected function periodLabel(): string
    {
        return match ($this->pageFilters['period'] ?? '30') {
            'today' => 'today',
            '365' => 'last 12 months',
            default => 'last '.($this->pageFilters['period'] ?? '30').' days',
        };
    }

    protected function ordersQuery(): Builder
    {
        $channel = $this->pageFilters['channel'] ?? null;

        return Order::query()
            ->when($channel === 'storefront', fn (Builder $query) => $query->whereNull('sales_channel_id'))
            ->when($channel && $channel !== 'storefront', fn (Builder $query) => $query->where('sales_channel_id', $channel));
    }
}
