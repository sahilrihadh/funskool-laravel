<?php

namespace App\Filament\Resources\StockMovements;

use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\StockMovement;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Inventory ledger — every stock change with reason, actor and reference. */
class StockMovementResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionArea = 'inventory';

    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationLabel = 'Stock Movements';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Inventory';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('product.name')->searchable()->description(fn ($record) => $record->variant?->label),
                TextColumn::make('product.sku')->label('SKU')->getStateUsing(fn ($record) => $record->variant?->sku ?? $record->product?->sku)->searchable(['sku']),
                TextColumn::make('quantity_change')->label('Change')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "+{$state}" : (string) $state)
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->weight('bold'),
                TextColumn::make('quantity_after')->label('Stock after'),
                TextColumn::make('reason')->badge()->formatStateUsing(fn ($state) => StockMovement::REASONS[$state] ?? $state),
                TextColumn::make('reference_type')->label('Reference')
                    ->formatStateUsing(fn ($state, $record) => $state ? class_basename($state).' #'.$record->reference_id : null)
                    ->placeholder('—'),
                TextColumn::make('user.name')->label('By')->placeholder('System / checkout'),
                TextColumn::make('note')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('reason')->options(StockMovement::REASONS),
                SelectFilter::make('product_id')->label('Product')->relationship('product', 'name')->searchable(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
        ];
    }
}
