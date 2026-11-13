<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission('inventory.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low stock')
            ->query(Product::query()->where('is_active', true)->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->orderBy('stock_quantity'))
            ->columns([
                TextColumn::make('name')->limit(35),
                TextColumn::make('sku'),
                TextColumn::make('stock_quantity')->label('Stock')->badge()->color(fn ($state) => $state <= 0 ? 'danger' : 'warning'),
            ])
            ->recordActions([
                Action::make('edit')->icon('heroicon-o-pencil-square')->url(fn (Product $record) => route('filament.admin.resources.products.edit', $record)),
            ])
            ->emptyStateHeading('Nothing is running low')
            ->paginated([5])
            ->defaultPaginationPageOption(5);
    }
}
