<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\ReturnRequests\ReturnRequestResource;
use App\Models\ReturnRequest;
use App\Models\Shipment;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Summary')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('order_number')->label('Order #')->weight('bold'),
                                TextEntry::make('customer_name')->label('Customer')
                                    ->getStateUsing(fn ($record) => $record->contactName())
                                    ->helperText(fn ($record) => trim($record->contactEmail().' '.$record->contactPhone())),
                                TextEntry::make('status')->badge()
                                    ->formatStateUsing(fn ($state) => OrderStatus::labelFor($state))
                                    ->color(fn ($state) => OrderStatus::colorFor($state)),
                                TextEntry::make('payment_status')->label('Payment')->badge()
                                    ->formatStateUsing(fn ($state) => PaymentStatus::labelFor($state))
                                    ->color(fn ($state) => PaymentStatus::colorFor($state)),
                            ]),
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('subtotal')->money('INR'),
                                TextEntry::make('discount_amount')->label('Discount')->money('INR'),
                                TextEntry::make('shipping_amount')->label('Shipping')->money('INR'),
                                TextEntry::make('tax_amount')->label('Tax')->money('INR'),
                            ]),
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total')->money('INR')->size('lg')->weight('bold'),
                                TextEntry::make('refunded_amount')->label('Refunded')->money('INR')
                                    ->visible(fn ($record) => (float) $record->refunded_amount > 0),
                                TextEntry::make('salesChannel.name')->label('Sales Channel')->placeholder('Storefront / admin')
                                    ->badge()->color('info'),
                                TextEntry::make('external_reference')->label('External Order Ref')->placeholder('—')->copyable(),
                            ]),
                    ]),

                Grid::make(2)
                    ->schema([
                        Section::make('Shipping Address')
                            ->schema([
                                TextEntry::make('shipping_address.full_name')->label('Name'),
                                TextEntry::make('shipping_address.phone')->label('Phone'),
                                TextEntry::make('shipping_address.line1')->label('Address')
                                    ->formatStateUsing(fn ($record) => collect([
                                        $record->shipping_address['line1'] ?? null,
                                        $record->shipping_address['line2'] ?? null,
                                        $record->shipping_address['city'] ?? null,
                                        $record->shipping_address['state'] ?? null,
                                        $record->shipping_address['postal_code'] ?? null,
                                        $record->shipping_address['country'] ?? null,
                                    ])->filter()->implode(', ')),
                            ]),

                        Section::make('Fulfillment')
                            ->schema([
                                TextEntry::make('paymentMethod.name')->label('Payment Method')->placeholder('—'),
                                TextEntry::make('shippingMethod.name')->label('Shipping Method')->placeholder('—'),
                                TextEntry::make('customer_note')->placeholder('—')->columnSpanFull(),
                                TextEntry::make('admin_note')->placeholder('—')->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('product_name')->label('Product')->columnSpan(2),
                                        TextEntry::make('sku')->label('SKU'),
                                        TextEntry::make('quantity'),
                                        TextEntry::make('total')->money('INR'),
                                    ]),
                            ]),
                    ]),

                Section::make('Shipments')
                    ->visible(fn ($record) => $record->shipments->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('shipments')
                            ->label('')
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('courier_name')->label('Courier')
                                            ->getStateUsing(fn ($record) => $record->courier_name ?? ucfirst($record->carrier)),
                                        TextEntry::make('tracking_number')->placeholder('—')->copyable()
                                            ->url(fn ($record) => $record->tracking_url, shouldOpenInNewTab: true),
                                        TextEntry::make('status')->badge()
                                            ->formatStateUsing(fn ($state) => Shipment::STATUSES[$state] ?? $state),
                                        TextEntry::make('shipped_at')->dateTime(),
                                        TextEntry::make('label_url')->label('Label')->placeholder('—')
                                            ->formatStateUsing(fn () => 'Download')
                                            ->url(fn ($record) => $record->label_url, shouldOpenInNewTab: true),
                                    ]),
                            ]),
                    ]),

                Section::make('Payments & Refunds')
                    ->visible(fn ($record) => $record->transactions->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('transactions')
                            ->label('')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextEntry::make('type')->badge()->color(fn ($state) => $state === 'refund' ? 'warning' : 'success'),
                                        TextEntry::make('gateway'),
                                        TextEntry::make('amount')->money('INR'),
                                        TextEntry::make('status')->badge(),
                                        TextEntry::make('gateway_payment_id')->label('Reference')
                                            ->getStateUsing(fn ($record) => $record->gateway_payment_id ?? $record->reference)->placeholder('—')->copyable(),
                                        TextEntry::make('created_at')->dateTime()
                                            ->helperText(fn ($record) => $record->reason),
                                    ]),
                            ]),
                    ]),

                Section::make('Returns')
                    ->visible(fn ($record) => $record->returnRequests->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('returnRequests')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('id')->label('Return')->formatStateUsing(fn ($state) => "#{$state}")
                                            ->url(fn ($record) => ReturnRequestResource::getUrl('edit', ['record' => $record])),
                                        TextEntry::make('status')->badge()
                                            ->formatStateUsing(fn ($state) => ReturnRequest::STATUSES[$state] ?? $state),
                                        TextEntry::make('reason')->formatStateUsing(fn ($state) => ReturnRequest::REASONS[$state] ?? $state),
                                        TextEntry::make('created_at')->dateTime(),
                                    ]),
                            ]),
                    ]),

                Section::make('Status Timeline')
                    ->schema([
                        RepeatableEntry::make('statusHistories')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('status')->badge()
                                            ->formatStateUsing(fn ($state) => OrderStatus::labelFor($state))
                                            ->color(fn ($state) => OrderStatus::colorFor($state)),
                                        TextEntry::make('note')->placeholder('—')
                                            ->helperText(fn ($record) => $record->is_customer_visible ? null : 'Internal only'),
                                        TextEntry::make('user.name')->label('By')->placeholder('System / customer'),
                                        TextEntry::make('created_at')->dateTime(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
