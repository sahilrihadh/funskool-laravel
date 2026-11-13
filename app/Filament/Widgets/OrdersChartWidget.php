<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ResolvesDashboardFilters;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;

class OrdersChartWidget extends ChartWidget
{
    use ResolvesDashboardFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission('reports.view');
    }

    public function getHeading(): string
    {
        return 'Sales & orders — '.$this->periodLabel();
    }

    public function getDescription(): ?string
    {
        return 'Order totals by day placed (excl. cancelled). Times in '.config('app.timezone').', amounts in ₹.';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        [$start, $end] = $this->period();
        $byMonth = $start->diffInDays($end) > 120;
        $format = $byMonth ? '%Y-%m' : '%Y-%m-%d';
        $bucket = $this->ordersQuery()->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('{$format}', created_at)"
            : "DATE_FORMAT(created_at, '{$format}')";

        $rows = $this->ordersQuery()
            ->selectRaw("{$bucket} as bucket, COUNT(*) as orders, SUM(total) as revenue")
            ->whereBetween('created_at', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $buckets = collect(CarbonPeriod::create($start, $byMonth ? '1 month' : '1 day', $end))
            ->map(fn ($date) => $date->format($byMonth ? 'Y-m' : 'Y-m-d'))
            ->unique()
            ->values();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₹)',
                    'data' => $buckets->map(fn ($key) => round((float) ($rows[$key]->revenue ?? 0), 2))->all(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Orders',
                    'data' => $buckets->map(fn ($key) => (int) ($rows[$key]->orders ?? 0))->all(),
                    'borderColor' => '#3b82f6',
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $buckets->map(fn ($key) => $byMonth ? date('M Y', strtotime($key.'-01')) : date('M j', strtotime($key)))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['position' => 'left', 'beginAtZero' => true],
                'y1' => ['position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false], 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
