<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use App\Services\PaymentService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),

                TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->live(onBlur: true)
                    ->helperText('Online gateways: "razorpay", "stripe". Offline: "cod", "bank_transfer" (staff record the payment).')
                    ->maxLength(50),

                Textarea::make('instructions')
                    ->helperText('Shown to the customer at checkout, e.g. bank account details for Bank Transfer.')
                    ->columnSpanFull(),

                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_active')->default(true),

                Section::make('Gateway Connection')
                    ->description('API keys are read from the server .env file (see required-resource.txt) — never stored here. A gateway is only offered at checkout when this method is active AND its keys are set.')
                    ->visible(fn (Get $get) => isset(PaymentService::GATEWAYS[$get('code')]))
                    ->schema([
                        TextEntry::make('gateway_status')
                            ->label('Status')
                            ->state(fn (Get $get) => app(PaymentService::class)->gateway($get('code'))?->isConfigured()
                                ? 'Connected — keys found in .env'
                                : 'Not connected — add the '.strtoupper($get('code')).'_* keys to .env')
                            ->badge()
                            ->color(fn (Get $get) => app(PaymentService::class)->gateway($get('code'))?->isConfigured() ? 'success' : 'danger'),
                        TextEntry::make('webhook_url')
                            ->label('Webhook URL to register with the gateway')
                            ->state(fn (Get $get) => route('webhooks.payments', $get('code')))
                            ->copyable(),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
