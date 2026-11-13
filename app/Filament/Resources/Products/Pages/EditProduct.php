<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('adjustStock')
                ->label('Adjust Stock')
                ->icon('heroicon-o-adjustments-vertical')
                ->visible(fn () => auth()->user()?->hasPermission('inventory.manage'))
                ->schema([
                    Select::make('product_variant_id')
                        ->label('Variant')
                        ->placeholder('Product (no variant)')
                        ->options(fn () => $this->getRecord()->variants->mapWithKeys(fn (ProductVariant $variant) => [$variant->id => "{$variant->label} — {$variant->sku} (stock {$variant->stock_quantity})"]))
                        ->visible(fn () => $this->getRecord()->variants->isNotEmpty()),
                    Radio::make('mode')
                        ->options(['add' => 'Add stock', 'remove' => 'Remove stock', 'set' => 'Set exact level (stocktake)'])
                        ->default('add')
                        ->inline()
                        ->required(),
                    TextInput::make('quantity')->numeric()->minValue(0)->required(),
                    Select::make('reason')
                        ->options(collect(StockMovement::REASONS)->except(['order', 'cancellation', 'return']))
                        ->default('restock')
                        ->required(),
                    TextInput::make('note')->maxLength(255),
                ])
                ->action(function (array $data, InventoryService $inventory): void {
                    /** @var Product $product */
                    $product = $this->getRecord();
                    $variant = ! empty($data['product_variant_id']) ? $product->variants()->find($data['product_variant_id']) : null;
                    $quantity = (int) $data['quantity'];

                    match ($data['mode']) {
                        'add' => $inventory->add($product, $variant, $quantity, $data['reason'], null, $data['note'] ?? null),
                        'remove' => $inventory->deduct($product, $variant, $quantity, $data['reason'], null, $data['note'] ?? null),
                        'set' => $inventory->setLevel($product, $variant, $quantity, $data['reason'], $data['note'] ?? null),
                    };

                    $this->refreshFormData(['stock_quantity']);

                    Notification::make()->title('Stock updated')->success()->send();
                }),

            DeleteAction::make(),
        ];
    }
}
