<?php

namespace App\Filament\Resources\ReturnRequests\Schemas;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\ReturnRequest;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReturnRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Return')
                    ->schema([
                        TextEntry::make('order.order_number')->label('Order')
                            ->url(fn (ReturnRequest $record) => OrderResource::getUrl('view', ['record' => $record->order_id])),
                        TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => ReturnRequest::STATUSES[$state] ?? $state),
                        TextEntry::make('reason')->formatStateUsing(fn ($state) => ReturnRequest::REASONS[$state] ?? $state),
                        TextEntry::make('created_at')->label('Requested')->dateTime(),
                        TextEntry::make('items')->label('Items')
                            ->state(function (ReturnRequest $record) {
                                $items = $record->order->items->keyBy('id');

                                return collect($record->items)->map(fn ($line) => ($items[$line['order_item_id']]->product_name ?? 'Item').' × '.$line['quantity'])->implode(', ');
                            })
                            ->columnSpanFull(),
                        TextEntry::make('value')->label('Value of returned items')->state(fn (ReturnRequest $record) => $record->itemsValue())->money('INR'),
                        TextEntry::make('refund_amount')->money('INR')->placeholder('—'),
                        TextEntry::make('restocked')->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                        TextEntry::make('customer_note')->placeholder('—')->columnSpanFull(),
                    ])
                    ->columns(4),

                Textarea::make('admin_note')
                    ->label('Note to customer / internal')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
