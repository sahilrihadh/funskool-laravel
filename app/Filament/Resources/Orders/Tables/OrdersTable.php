<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Support\Avatar;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->where('order_number', 'like', "%{$search}%")
                        ->orWhere('external_reference', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhereHas('shipments', fn (Builder $query) => $query->where('tracking_number', 'like', "%{$search}%"))
                        ->orWhereHas('transactions', fn (Builder $query) => $query->where('gateway_payment_id', 'like', "%{$search}%")->orWhere('reference', 'like', "%{$search}%")))
                    ->description(fn ($record) => $record->external_reference)
                    ->weight('bold'),

                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->getStateUsing(fn ($record) => $record->contactName())
                    ->formatStateUsing(fn ($state, $record) => Avatar::html($state, $record->contactEmail()))
                    ->html()
                    ->searchable(['customer_name']),

                TextColumn::make('salesChannel.name')
                    ->label('Channel')
                    ->placeholder('Storefront')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderStatus::labelFor($state))
                    ->color(fn (string $state) => OrderStatus::colorFor($state)),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => PaymentStatus::labelFor($state))
                    ->color(fn (string $state) => PaymentStatus::colorFor($state)),

                TextColumn::make('paymentMethod.name')
                    ->label('Method')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Placed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderStatus::options())
                    ->multiple(),

                SelectFilter::make('payment_status')
                    ->options(PaymentStatus::options())
                    ->multiple(),

                SelectFilter::make('sales_channel_id')
                    ->label('Channel')
                    ->relationship('salesChannel', 'name'),

                SelectFilter::make('payment_method_id')
                    ->label('Payment method')
                    ->relationship('paymentMethod', 'name'),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->headerActions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->outlined()
                    ->visible(fn () => auth()->user()?->hasPermission('reports.view'))
                    ->action(fn ($livewire) => $livewire->downloadCsv()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
