<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ResolvesDashboardFilters;
use App\Models\OrderItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopProductsWidget extends BaseWidget
{
    use ResolvesDashboardFilters;

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission('reports.view');
    }

    public function table(Table $table): Table
    {
        [$start, $end] = $this->period();

        return $table
            ->heading('Top products — '.$this->periodLabel())
            // Aggregated in a subquery so the table's own "ORDER BY id" is valid under MySQL ONLY_FULL_GROUP_BY.
            ->query(
                OrderItem::query()
                    ->fromSub(
                        OrderItem::query()
                            ->selectRaw('MIN(order_items.id) as id, product_id, product_name, SUM(quantity) as units, SUM(order_items.total) as revenue')
                            ->whereHas('order', fn (Builder $query) => $query
                                ->whereIn('id', $this->ordersQuery()->select('id'))
                                ->whereBetween('created_at', [$start, $end])
                                ->where('status', '!=', 'cancelled'))
                            ->groupBy('product_id', 'product_name'),
                        'order_items',
                    )
                    ->orderByDesc('revenue')
            )
            ->columns([
                TextColumn::make('product_name')->label('Product')->limit(40),
                TextColumn::make('units')->numeric(),
                TextColumn::make('revenue')->money('INR'),
            ])
            ->paginated([5])
            ->defaultPaginationPageOption(5);
    }
}
