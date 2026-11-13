<?php

namespace App\Filament\Resources\ReturnRequests\Tables;

use App\Models\ReturnRequest;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReturnRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('order.order_number')->label('Order')->searchable()->weight('bold'),
                TextColumn::make('order.customer_name')->label('Customer')->searchable(),
                TextColumn::make('reason')->formatStateUsing(fn ($state) => ReturnRequest::REASONS[$state] ?? $state),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => ReturnRequest::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'requested' => 'warning',
                        'approved', 'received' => 'info',
                        'refunded' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->label('Requested')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ReturnRequest::STATUSES),
            ])
            ->recordActions([
                EditAction::make()->label('Review'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
