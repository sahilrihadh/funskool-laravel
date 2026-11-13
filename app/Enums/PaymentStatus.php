<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Paid = 'paid';
    case Failed = 'failed';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Disputed = 'disputed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PartiallyRefunded => 'Partially Refunded',
            default => ucfirst($this->value),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Authorized => 'info',
            self::Failed, self::Disputed => 'danger',
            self::Refunded, self::PartiallyRefunded => 'warning',
            self::Pending => 'gray',
        };
    }

    /** Money has been captured (and not fully returned). */
    public static function captured(): array
    {
        return [self::Paid->value, self::PartiallyRefunded->value];
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
