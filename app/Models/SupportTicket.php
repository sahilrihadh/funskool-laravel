<?php

namespace App\Models;

use App\Enums\TicketStatus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    use LogsActivity;

    public const CATEGORIES = [
        'order' => 'Order Issue',
        'delivery' => 'Delivery & Tracking',
        'returns' => 'Returns & Refunds',
        'payment' => 'Payment',
        'product' => 'Product Question',
        'account' => 'Account',
        'general' => 'General Support',
    ];

    protected $fillable = [
        'ticket_number',
        'user_id',
        'order_id',
        'sales_channel_id',
        'assigned_to',
        'name',
        'email',
        'phone',
        'subject',
        'category',
        'priority',
        'status',
        'source',
        'first_response_at',
        'last_customer_reply_at',
        'last_staff_reply_at',
        'solved_at',
    ];

    protected $hidden = ['access_token'];

    protected array $hiddenFromActivityLog = ['access_token'];

    protected $casts = [
        'first_response_at' => 'datetime',
        'last_customer_reply_at' => 'datetime',
        'last_staff_reply_at' => 'datetime',
        'solved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket) {
            $ticket->access_token ??= Str::random(48);

            if (! $ticket->ticket_number) {
                do {
                    $number = (string) random_int(100000, 999999);
                } while (static::query()->where('ticket_number', $number)->exists());

                $ticket->ticket_number = $number;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ticket_number';
    }

    public function isResolved(): bool
    {
        return in_array($this->status, TicketStatus::resolved(), true);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function verifyToken(?string $token): bool
    {
        return $token !== null && hash_equals($this->access_token, $token);
    }

    /** Link a guest can open without signing in (emailed to them). */
    public function guestUrl(): string
    {
        return route('support.guest', ['ticket' => $this->ticket_number, 'token' => $this->access_token]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public function publicMessages(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }
}
