<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Inbound provider webhooks (payment gateways, couriers). The unique
 * (provider, event_id) index makes processing idempotent.
 */
class WebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_id',
        'type',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
