<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Enums\TicketStatus;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Filament\Resources\SupportTickets\Widgets\TicketStatsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSupportTickets extends ListRecords
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Log a ticket'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [TicketStatsWidget::class];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', TicketStatus::pending())),
            'solved' => Tab::make('Solved')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', TicketStatus::resolved())),
            'mine' => Tab::make('Assigned to me')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('assigned_to', auth()->id())),
            'unassigned' => Tab::make('Unassigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('assigned_to')->whereIn('status', TicketStatus::pending())),
        ];
    }
}
