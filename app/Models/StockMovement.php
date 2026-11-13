<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    public const UPDATED_AT = null;

    public const REASONS = [
        'order' => 'Sold (order placed)',
        'cancellation' => 'Order cancelled',
        'return' => 'Customer return restocked',
        'restock' => 'Supplier restock',
        'adjustment' => 'Manual adjustment',
        'damaged' => 'Damaged / write-off',
        'stocktake' => 'Stocktake correction',
    ];

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'quantity_change',
        'quantity_after',
        'reason',
        'reference_type',
        'reference_id',
        'user_id',
        'note',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
