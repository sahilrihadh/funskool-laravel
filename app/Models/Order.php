<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPaymentStatusChanged;
use App\Events\OrderStatusChanged;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use LogsActivity;

    protected $fillable = [
        'order_number',
        'user_id',
        'sales_channel_id',
        'external_reference',
        'idempotency_key',
        'customer_name',
        'customer_email',
        'customer_phone',
        'currency',
        'status',
        'payment_status',
        'payment_method_id',
        'shipping_method_id',
        'coupon_id',
        'subtotal',
        'discount_amount',
        'shipping_amount',
        'tax_amount',
        'total',
        'refunded_amount',
        'shipping_address',
        'billing_address',
        'customer_note',
        'admin_note',
        'return_url',
        'paid_at',
        'cancelled_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected array $hiddenFromActivityLog = ['shipping_address', 'billing_address', 'idempotency_key'];

    /** Free-text note for the next status-history row (set by OrderService). */
    public ?string $pendingStatusNote = null;

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            $order->statusHistories()->create([
                'status' => $order->status,
                'user_id' => auth()->id(),
                'note' => $order->pendingStatusNote,
            ]);
        });

        static::updated(function (Order $order) {
            if ($order->wasChanged('status')) {
                $order->statusHistories()->create([
                    'status' => $order->status,
                    'user_id' => auth()->id(),
                    'note' => $order->pendingStatusNote,
                ]);

                OrderStatusChanged::dispatch($order, (string) $order->getOriginal('status'), $order->status);
            }

            if ($order->wasChanged('payment_status')) {
                OrderPaymentStatusChanged::dispatch($order, (string) $order->getOriginal('payment_status'), $order->payment_status);
            }

            $order->pendingStatusNote = null;
        });
    }

    public function statusEnum(): ?OrderStatus
    {
        return OrderStatus::tryFrom($this->status);
    }

    public function isPaid(): bool
    {
        return in_array($this->payment_status, PaymentStatus::captured(), true);
    }

    public function refundableAmount(): float
    {
        return max(0, round((float) $this->total - (float) $this->refunded_amount, 2));
    }

    /** Email/phone/name to notify — the order snapshot first, then the account. */
    public function contactEmail(): ?string
    {
        return $this->customer_email ?: $this->user?->email;
    }

    public function contactPhone(): ?string
    {
        return $this->customer_phone ?: ($this->shipping_address['phone'] ?? $this->user?->phone);
    }

    public function contactName(): string
    {
        return $this->customer_name ?: ($this->shipping_address['full_name'] ?? $this->user?->name ?? 'Customer');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->latest('id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class)->latest('id');
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class)->latest('id');
    }
}
