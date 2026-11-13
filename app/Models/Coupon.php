<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use LogsActivity;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'per_customer_limit',
        'first_order_only',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'first_order_only' => 'boolean',
    ];

    public function isValidFor(float $subtotal): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        if ($this->min_order_amount !== null && $subtotal < (float) $this->min_order_amount) {
            return false;
        }

        return true;
    }

    /**
     * Customer-specific rules (per-customer limit, first order only). Cancelled
     * orders don't count against the limit.
     */
    public function isAvailableTo(?User $user, ?string $email = null): bool
    {
        if ($this->per_customer_limit === null && ! $this->first_order_only) {
            return true;
        }

        if (! $user && ! $email) {
            return ! $this->first_order_only;
        }

        $previousOrders = Order::query()
            ->where(fn ($query) => $query
                ->when($user, fn ($query) => $query->where('user_id', $user->id))
                ->when($email, fn ($query) => $query->orWhere('customer_email', $email)))
            ->where('status', '!=', 'cancelled');

        if ($this->first_order_only && (clone $previousOrders)->exists()) {
            return false;
        }

        if ($this->per_customer_limit !== null
            && (clone $previousOrders)->where('coupon_id', $this->id)->count() >= $this->per_customer_limit) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $subtotal): float
    {
        $discount = $this->type === 'percent'
            ? $subtotal * ((float) $this->value / 100)
            : (float) $this->value;

        if ($this->max_discount_amount !== null) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }

        return min($discount, $subtotal);
    }
}
