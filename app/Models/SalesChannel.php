<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * An external website (WooCommerce, Shopify, a Next.js storefront, a mobile
 * app...) connected to this dashboard through the Channel API.
 */
class SalesChannel extends Model
{
    use HasSlug, LogsActivity;

    public const EVENTS = [
        'order.created' => 'Order created',
        'order.status_changed' => 'Order status changed',
        'order.payment_changed' => 'Order payment status changed',
        'shipment.created' => 'Shipment created / tracking added',
        'product.updated' => 'Product created or updated',
        'inventory.updated' => 'Stock level changed',
    ];

    public const TYPES = [
        'headless' => 'Headless / custom storefront',
        'woocommerce' => 'WooCommerce',
        'shopify' => 'Shopify',
        'mobile_app' => 'Mobile app',
        'marketplace' => 'Marketplace / other',
    ];

    protected $fillable = [
        'name',
        'slug',
        'type',
        'website_url',
        'webhook_url',
        'webhook_events',
        'send_customer_notifications',
        'is_active',
    ];

    protected $hidden = [
        'api_key_hash',
        'webhook_secret',
    ];

    protected $casts = [
        'webhook_events' => 'array',
        'webhook_secret' => 'encrypted',
        'send_customer_notifications' => 'boolean',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Issues a new API key, invalidating the previous one. The plain key is
     * returned once and never stored — only its SHA-256 hash.
     */
    public function rotateApiKey(): string
    {
        $prefix = 'fsk_'.Str::lower(Str::random(8));
        $secret = Str::random(40);

        $this->forceFill([
            'api_key_prefix' => $prefix,
            'api_key_hash' => hash('sha256', $secret),
        ])->save();

        return "{$prefix}.{$secret}";
    }

    public function rotateWebhookSecret(): string
    {
        $secret = 'whsec_'.Str::random(40);
        $this->forceFill(['webhook_secret' => $secret])->save();

        return $secret;
    }

    public static function findByApiKey(?string $key): ?self
    {
        if (! $key || ! str_contains($key, '.')) {
            return null;
        }

        [$prefix, $secret] = explode('.', $key, 2);

        $channel = static::query()->where('api_key_prefix', $prefix)->first();

        if (! $channel || ! $channel->api_key_hash || ! hash_equals($channel->api_key_hash, hash('sha256', $secret))) {
            return null;
        }

        return $channel;
    }

    public function subscribesTo(string $event): bool
    {
        return $this->is_active
            && filled($this->webhook_url)
            && in_array($event, $this->webhook_events ?? [], true);
    }
}
