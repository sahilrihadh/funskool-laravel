<?php

namespace App\Filament\Resources\ShippingMethods\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShippingMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                Textarea::make('description')->columnSpanFull(),

                TextInput::make('cost')
                    ->label('Cost (₹)')
                    ->numeric()
                    ->prefix('₹')
                    ->required()
                    ->default(0),

                TextInput::make('free_shipping_above')
                    ->label('Free Shipping Above (₹)')
                    ->numeric()
                    ->prefix('₹')
                    ->helperText('Leave empty to never offer free shipping for this method.'),

                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_active')->default(true),

                Section::make('Carrier Integration')
                    ->description('Checkout rates come from the cost / free-shipping fields above. Shipments are created per order (Orders → Ship): manually, or via Shiprocket when SHIPROCKET_* keys are set in .env.')
                    ->schema([
                        TextInput::make('provider')
                            ->label('Provider Key')
                            ->helperText('Optional label, e.g. "shiprocket" — used for reporting only.')
                            ->maxLength(100),
                    ])
                    ->collapsed(),
            ])
            ->columns(2);
    }
}
