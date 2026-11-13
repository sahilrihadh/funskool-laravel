<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Fulfilment lifecycle of an order. Payment state is tracked separately in
 * PaymentStatus — an order can be packed while payment is pending (COD), or
 * delivered while a refund is still processing.
 */
enum OrderStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';
    case ReturnRequested = 'return_requested';
    case Returned = 'returned';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::OnHold => 'On Hold',
            self::ReturnRequested => 'Return Requested',
            default => ucfirst($this->value),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending, self::OnHold => 'gray',
            self::Confirmed, self::Processing, self::Packed => 'warning',
            self::Shipped => 'info',
            self::Delivered => 'success',
            self::Cancelled, self::Refunded => 'danger',
            self::ReturnRequested, self::Returned => 'warning',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Processing, self::OnHold, self::Cancelled],
            self::Confirmed => [self::Processing, self::Packed, self::Shipped, self::OnHold, self::Cancelled],
            self::Processing => [self::Packed, self::Shipped, self::OnHold, self::Cancelled],
            self::Packed => [self::Shipped, self::OnHold, self::Cancelled],
            self::OnHold => [self::Pending, self::Confirmed, self::Processing, self::Cancelled],
            self::Shipped => [self::Delivered, self::Returned],
            self::Delivered => [self::ReturnRequested, self::Returned, self::Refunded],
            self::ReturnRequested => [self::Returned, self::Delivered, self::Refunded],
            self::Returned => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return $status === $this || in_array($status, $this->allowedTransitions(), true);
    }

    /** Customer may cancel before the parcel leaves the warehouse. */
    public function isCancellableByCustomer(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Processing], true);
    }

    /** Statuses that still need warehouse work. */
    public static function toFulfil(): array
    {
        return [self::Confirmed->value, self::Processing->value, self::Packed->value];
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }

    public static function colorFor(?string $state): string
    {
        return self::tryFrom((string) $state)?->getColor() ?? 'gray';
    }

    public static function labelFor(?string $state): string
    {
        return self::tryFrom((string) $state)?->getLabel() ?? (string) $state;
    }
}
