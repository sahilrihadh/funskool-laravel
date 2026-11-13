<?php

namespace App\Services;

use App\Events\StockLevelChanged;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every stock change goes through here so it is atomic (no overselling under
 * concurrent checkouts) and recorded in the stock_movements ledger.
 */
class InventoryService
{
    public function available(Product $product, ?ProductVariant $variant = null): int
    {
        return (int) ($variant ? $variant->stock_quantity : $product->stock_quantity);
    }

    /**
     * Removes stock, failing if there isn't enough. The conditional UPDATE is
     * the concurrency guard: two checkouts racing for the last unit can't both win.
     */
    public function deduct(Product $product, ?ProductVariant $variant, int $quantity, string $reason = 'order', ?Model $reference = null, ?string $note = null): void
    {
        $quantity = abs($quantity);
        $query = $variant
            ? ProductVariant::query()->whereKey($variant->id)
            : Product::query()->whereKey($product->id);

        $affected = $query->where('stock_quantity', '>=', $quantity)->decrement('stock_quantity', $quantity);

        if ($affected === 0) {
            $name = $product->name.($variant ? " ({$variant->label})" : '');

            throw ValidationException::withMessages([
                'stock' => "Not enough stock for {$name}.",
            ]);
        }

        $this->record($product, $variant, -$quantity, $reason, $reference, $note);
    }

    public function add(Product $product, ?ProductVariant $variant, int $quantity, string $reason, ?Model $reference = null, ?string $note = null): void
    {
        $quantity = abs($quantity);

        ($variant ?? $product)->newQuery()->whereKey(($variant ?? $product)->getKey())->increment('stock_quantity', $quantity);

        $this->record($product, $variant, $quantity, $reason, $reference, $note);
    }

    /** Sets an absolute level (stocktake / manual correction). */
    public function setLevel(Product $product, ?ProductVariant $variant, int $newLevel, string $reason = 'stocktake', ?string $note = null): void
    {
        DB::transaction(function () use ($product, $variant, $newLevel, $reason, $note) {
            $target = ($variant ?? $product)->newQuery()->lockForUpdate()->findOrFail(($variant ?? $product)->getKey());
            $difference = max(0, $newLevel) - (int) $target->stock_quantity;

            if ($difference === 0) {
                return;
            }

            $target->newQuery()->whereKey($target->getKey())->update(['stock_quantity' => max(0, $newLevel)]);
            $this->record($product, $variant, $difference, $reason, null, $note);
        });
    }

    protected function record(Product $product, ?ProductVariant $variant, int $change, string $reason, ?Model $reference, ?string $note): void
    {
        $current = (int) ($variant
            ? ProductVariant::query()->whereKey($variant->id)->value('stock_quantity')
            : Product::query()->whereKey($product->id)->value('stock_quantity'));

        StockMovement::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'quantity_change' => $change,
            'quantity_after' => $current,
            'reason' => $reason,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => auth()->id(),
            'note' => $note,
        ]);

        ($variant ?? $product)->setAttribute('stock_quantity', $current)->syncOriginalAttribute('stock_quantity');

        StockLevelChanged::dispatch($product, $variant, $current - $change, $current);
    }
}
