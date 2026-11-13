<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    use LogsActivity;

    public const STATUSES = [
        'created' => 'Created',
        'in_transit' => 'In Transit',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
        'failed' => 'Delivery Failed',
        'rto' => 'Returned to Origin',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'order_id',
        'carrier',
        'courier_name',
        'tracking_number',
        'tracking_url',
        'provider_order_id',
        'provider_shipment_id',
        'label_url',
        'status',
        'last_event',
        'shipped_at',
        'delivered_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
