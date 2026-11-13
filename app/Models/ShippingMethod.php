<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'cost',
        'free_shipping_above',
        'provider',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'free_shipping_above' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function costFor(float $subtotal): float
    {
        if ($this->free_shipping_above !== null && $subtotal >= (float) $this->free_shipping_above) {
            return 0.0;
        }

        return (float) $this->cost;
    }
}
