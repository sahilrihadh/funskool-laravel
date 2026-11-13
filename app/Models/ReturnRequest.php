<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    use LogsActivity;

    public const STATUSES = [
        'requested' => 'Requested',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'received' => 'Received',
        'refunded' => 'Refunded',
    ];

    public const REASONS = [
        'damaged' => 'Arrived damaged',
        'defective' => 'Defective / not working',
        'wrong_item' => 'Wrong item received',
        'missing_parts' => 'Missing parts',
        'not_as_described' => 'Not as described',
        'changed_mind' => 'No longer needed',
        'other' => 'Other',
    ];

    protected $fillable = [
        'order_id',
        'user_id',
        'status',
        'reason',
        'items',
        'customer_note',
        'admin_note',
        'refund_amount',
        'restocked',
        'resolved_at',
    ];

    protected $casts = [
        'items' => 'array',
        'refund_amount' => 'decimal:2',
        'restocked' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Value of the returned lines, at the price the customer paid. */
    public function itemsValue(): float
    {
        $orderItems = $this->order->items->keyBy('id');

        return round(collect($this->items)->sum(function (array $line) use ($orderItems) {
            $item = $orderItems->get($line['order_item_id']);

            return $item ? (float) $item->price * (int) $line['quantity'] : 0;
        }), 2);
    }
}
