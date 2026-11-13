<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestOrdersWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission('orders.view');
    }

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->with('salesChannel')->latest()->limit(8))
            ->heading('Latest Orders')
            ->columns([
                TextColumn::make('order_number')->label('Order #'),
                TextColumn::make('customer_name')->label('Customer')->getStateUsing(fn (Order $record) => $record->contactName()),
                TextColumn::make('salesChannel.name')->label('Channel')->placeholder('Storefront'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => OrderStatus::labelFor($state))
                    ->color(fn ($state) => OrderStatus::colorFor($state)),
                TextColumn::make('payment_status')->label('Payment')->badge()
                    ->formatStateUsing(fn ($state) => PaymentStatus::labelFor($state))
                    ->color(fn ($state) => PaymentStatus::colorFor($state)),
                TextColumn::make('total')->money('INR'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordUrl(fn (Order $record) => route('filament.admin.resources.orders.view', $record))
            ->paginated(false);
    }
}
