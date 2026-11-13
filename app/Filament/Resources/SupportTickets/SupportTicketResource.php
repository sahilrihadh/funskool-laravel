<?php

namespace App\Filament\Resources\SupportTickets;

use App\Enums\TicketStatus;
use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Resources\SupportTickets\Pages\CreateSupportTicket;
use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Filament\Resources\SupportTickets\Schemas\SupportTicketForm;
use App\Filament\Resources\SupportTickets\Tables\SupportTicketsTable;
use App\Models\SupportTicket;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SupportTicketResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionArea = 'support';

    protected static ?string $model = SupportTicket::class;

    protected static ?string $navigationLabel = 'Ticket List';

    protected static ?string $modelLabel = 'support ticket';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Support';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) SupportTicket::query()->where('status', TicketStatus::Open->value)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return "#{$record->ticket_number} — {$record->subject}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['ticket_number', 'subject', 'email', 'name'];
    }

    public static function form(Schema $schema): Schema
    {
        return SupportTicketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportTicketsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportTickets::route('/'),
            'create' => CreateSupportTicket::route('/create'),
            'view' => ViewSupportTicket::route('/{record}'),
        ];
    }
}
