<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Solved = 'solved';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::InProgress => 'In Progress',
            self::OnHold => 'On Hold',
            default => ucfirst($this->value),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::InProgress => 'warning',
            self::OnHold => 'gray',
            self::Solved => 'success',
            self::Closed => 'gray',
        };
    }

    /** "Pending" in the ticket list = anything still needing work. */
    public static function pending(): array
    {
        return [self::Open->value, self::InProgress->value, self::OnHold->value];
    }

    public static function resolved(): array
    {
        return [self::Solved->value, self::Closed->value];
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }

    public static function labelFor(?string $state): string
    {
        return self::tryFrom((string) $state)?->getLabel() ?? (string) $state;
    }

    public static function colorFor(?string $state): string
    {
        return self::tryFrom((string) $state)?->getColor() ?? 'gray';
    }
}
