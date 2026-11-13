<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * "admin" keeps its original meaning (full access / owner) so existing
 * accounts keep working. Permissions per role live in config/permissions.php.
 */
enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Manager = 'manager';
    case CatalogManager = 'catalog_manager';
    case Fulfillment = 'fulfillment';
    case Support = 'support';
    case Marketing = 'marketing';
    case Finance = 'finance';
    case Analyst = 'analyst';
    case Customer = 'customer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Owner / Super Admin',
            self::Manager => 'Store Manager',
            self::CatalogManager => 'Catalogue Manager',
            self::Analyst => 'Read-only Analyst',
            default => ucfirst($this->value),
        };
    }

    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }

    public static function staffValues(): array
    {
        return collect(self::cases())->filter->isStaff()->map->value->values()->all();
    }
}
