<?php

namespace App\Filament\Resources\SupportTickets\Tables;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Filament\Support\Avatar;
use App\Models\SupportTicket;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Mirrors TailAdmin's Support Tickets list. */
class SupportTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Support Tickets')
            ->description('Your most recent support tickets list')
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('Ticket ID')
                    ->formatStateUsing(fn ($state) => "#{$state}")
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('name')
                    ->label('Requested By')
                    ->formatStateUsing(fn ($state, SupportTicket $record) => Avatar::html($record->name, $record->email))
                    ->html()
                    ->searchable(['name', 'email']),

                TextColumn::make('subject')
                    ->limit(48)
                    ->tooltip(fn (SupportTicket $record) => $record->subject)
                    ->description(fn (SupportTicket $record) => $record->categoryLabel())
                    ->searchable(),

                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn ($state) => TicketPriority::tryFrom($state)?->getLabel() ?? $state)
                    ->color(fn ($state) => TicketPriority::tryFrom($state)?->getColor() ?? 'gray')
                    ->toggleable(),

                TextColumn::make('assignee.name')
                    ->label('Assigned')
                    ->placeholder('Unassigned')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Create Date')
                    ->date('d M, Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => TicketStatus::labelFor($state))
                    ->color(fn ($state) => TicketStatus::colorFor($state)),
            ])
            ->filters([
                SelectFilter::make('category')->options(SupportTicket::CATEGORIES),
                SelectFilter::make('priority')->options(TicketPriority::options()),
                SelectFilter::make('assigned_to')->label('Assigned to')->relationship('assignee', 'name'),
                SelectFilter::make('sales_channel_id')->label('Website')->relationship('salesChannel', 'name'),
            ])
            ->recordActions([
                ViewAction::make()->label('Reply')->icon('heroicon-o-chat-bubble-left-right'),
            ])
            ->recordUrl(fn (SupportTicket $record) => SupportTicketResource::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc');
    }
}
