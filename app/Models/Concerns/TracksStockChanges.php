<?php

namespace App\Models\Concerns;

use App\Events\StockLevelChanged;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;

/**
 * Records stock edits made through forms (e.g. the product edit page) in the
 * ledger. InventoryService writes with query-builder updates, so its changes
 * don't pass through here and are not double-counted.
 */
trait TracksStockChanges
{
    public static function bootTracksStockChanges(): void
    {
        static::updated(function (Product|ProductVariant $model) {
            if (! $model->wasChanged('stock_quantity')) {
                return;
            }

            $previous = (int) $model->getOriginal('stock_quantity');
            $current = (int) $model->stock_quantity;
            $product = $model instanceof ProductVariant ? $model->product : $model;
            $variant = $model instanceof ProductVariant ? $model : null;

            StockMovement::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity_change' => $current - $previous,
                'quantity_after' => $current,
                'reason' => 'adjustment',
                'user_id' => auth()->id(),
                'note' => 'Edited on the product form',
            ]);

            StockLevelChanged::dispatch($product, $variant, $previous, $current);
        });
    }
}
