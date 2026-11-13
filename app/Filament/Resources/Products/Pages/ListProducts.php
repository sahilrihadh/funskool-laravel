<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\CsvExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** Called by the table's "Download" action — exports exactly what the current filters/tabs show. */
    public function downloadCsv()
    {
        abort_unless(auth()->user()?->hasPermission('reports.view'), 403);

        return CsvExport::download('products', $this->getFilteredSortedTableQuery()->with(['category', 'brand']), [
            'SKU' => fn ($p) => $p->sku,
            'Name' => fn ($p) => $p->name,
            'Category' => fn ($p) => $p->category?->name,
            'Brand' => fn ($p) => $p->brand?->name,
            'Regular price' => fn ($p) => $p->regular_price,
            'Sale price' => fn ($p) => $p->sale_price,
            'Stock' => fn ($p) => $p->stock_quantity,
            'Low-stock threshold' => fn ($p) => $p->low_stock_threshold,
            'Active' => fn ($p) => $p->is_active ? 'yes' : 'no',
            'Updated' => fn ($p) => $p->updated_at?->toDateTimeString(),
        ]);
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'active' => Tab::make('Active')->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true)),
            'low_stock' => Tab::make('Low stock')
                ->badge(fn () => ProductResource::getModel()::query()->where('is_active', true)->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold')),
            'out_of_stock' => Tab::make('Out of stock')->modifyQueryUsing(fn (Builder $query) => $query->where('stock_quantity', '<=', 0)),
            'inactive' => Tab::make('Draft / inactive')->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', false)),
        ];
    }
}
