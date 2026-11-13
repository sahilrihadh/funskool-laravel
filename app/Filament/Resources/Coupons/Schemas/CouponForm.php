<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->formatStateUsing(fn (?string $state) => $state ? strtoupper($state) : $state)
                    ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper($state) : $state),

                Select::make('type')
                    ->options([
                        'fixed' => 'Fixed Amount (₹)',
                        'percent' => 'Percentage (%)',
                    ])
                    ->required()
                    ->default('percent'),

                TextInput::make('value')
                    ->required()
                    ->numeric(),

                TextInput::make('min_order_amount')
                    ->label('Minimum Order Amount (₹)')
                    ->numeric(),

                TextInput::make('max_discount_amount')
                    ->label('Maximum Discount Amount (₹)')
                    ->numeric()
                    ->helperText('Only applies to percentage coupons.'),

                TextInput::make('usage_limit')
                    ->label('Total Usage Limit')
                    ->numeric()
                    ->helperText('Leave empty for unlimited use.'),

                TextInput::make('per_customer_limit')
                    ->label('Uses per Customer')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Leave empty for no per-customer limit.'),

                Toggle::make('first_order_only')
                    ->label("Customer's first order only")
                    ->inline(false),

                DateTimePicker::make('starts_at'),

                DateTimePicker::make('expires_at'),

                Toggle::make('is_active')
                    ->default(true),
            ])
            ->columns(2);
    }
}
