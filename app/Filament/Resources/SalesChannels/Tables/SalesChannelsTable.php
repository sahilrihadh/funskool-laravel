<?php

namespace App\Filament\Resources\SalesChannels\Tables;

use App\Models\SalesChannel;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalesChannelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold')->description(fn ($record) => $record->website_url),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => SalesChannel::TYPES[$state] ?? $state),
                TextColumn::make('orders_count')->counts('orders')->label('Orders'),
                TextColumn::make('failed_deliveries')
                    ->label('Failed webhooks')
                    ->getStateUsing(fn ($record) => $record->webhookDeliveries()->where('status', 'failed')->count())
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('last_used_at')->label('Last API call')->since()->placeholder('Never'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
