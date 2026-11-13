<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer & Fulfillment')
                    ->description(fn (string $operation) => $operation === 'edit'
                        ? 'Totals, items, status and payment can only change through the actions on the order page, so every change is recalculated and audited.'
                        : 'Prices, stock, coupon, shipping and tax are calculated by the same rules as online checkout.')
                    ->schema([
                        Select::make('user_id')
                            ->label('Customer')
                            ->relationship('user', 'name', fn ($query) => $query->where('role', 'customer'))
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->email})")
                            ->searchable(['name', 'email', 'phone'])
                            ->required()
                            ->disabledOn('edit'),

                        Select::make('shipping_method_id')
                            ->label('Shipping Method')
                            ->relationship('shippingMethod', 'name', fn ($query) => $query->where('is_active', true))
                            ->preload()
                            ->disabledOn('edit'),

                        Select::make('payment_method_id')
                            ->label('Payment Method')
                            ->relationship('paymentMethod', 'name', fn ($query) => $query->where('is_active', true))
                            ->preload()
                            ->disabledOn('edit'),

                        TextInput::make('coupon_code')
                            ->label('Coupon Code')
                            ->maxLength(50)
                            ->visibleOn('create')
                            ->dehydrated(),
                    ])
                    ->columns(2),

                Section::make('Items')
                    ->visibleOn('create')
                    ->schema([
                        Repeater::make('items')
                            ->label('')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->options(fn () => Product::query()->where('is_active', true)->orderBy('name')->get()
                                        ->mapWithKeys(fn (Product $product) => [$product->id => "{$product->name} — {$product->sku} (stock {$product->stock_quantity})"]))
                                    ->searchable()
                                    ->live()
                                    ->required()
                                    ->columnSpan(2),

                                Select::make('product_variant_id')
                                    ->label('Variant')
                                    ->options(fn (Get $get) => ProductVariant::query()->where('product_id', $get('product_id'))->where('is_active', true)->get()
                                        ->mapWithKeys(fn (ProductVariant $variant) => [$variant->id => "{$variant->label} (stock {$variant->stock_quantity})"]))
                                    ->visible(fn (Get $get) => $get('product_id') && ProductVariant::query()->where('product_id', $get('product_id'))->where('is_active', true)->exists())
                                    ->required(fn (Get $get) => $get('product_id') && ProductVariant::query()->where('product_id', $get('product_id'))->where('is_active', true)->exists()),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required(),
                            ])
                            ->columns(4)
                            ->addActionLabel('Add item')
                            ->required(),
                    ]),

                Section::make('Shipping Address')
                    ->schema([
                        TextInput::make('shipping_address.full_name')->label('Full Name')->required(),
                        TextInput::make('shipping_address.phone')->label('Phone')->required(),
                        TextInput::make('shipping_address.line1')->label('Address Line 1')->required()->columnSpanFull(),
                        TextInput::make('shipping_address.line2')->label('Address Line 2')->columnSpanFull(),
                        TextInput::make('shipping_address.city')->label('City')->required(),
                        TextInput::make('shipping_address.state')->label('State')->required(),
                        TextInput::make('shipping_address.postal_code')->label('Postal Code')->required(),
                        TextInput::make('shipping_address.country')->label('Country')->default('India')->required(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('customer_note')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit')
                            ->columnSpanFull(),

                        Textarea::make('admin_note')
                            ->label('Internal note (never shown to the customer)')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
