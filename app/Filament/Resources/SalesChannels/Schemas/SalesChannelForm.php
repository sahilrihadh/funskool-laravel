<?php

namespace App\Filament\Resources\SalesChannels\Schemas;

use App\Models\SalesChannel;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalesChannelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Website')
                    ->description('Any external site or app that sells your catalogue: WooCommerce, Shopify, a Next.js storefront, a mobile app, a marketplace bridge…')
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        Select::make('type')->options(SalesChannel::TYPES)->default('headless')->required(),
                        TextInput::make('website_url')->url()->maxLength(255)->columnSpanFull(),
                        Toggle::make('send_customer_notifications')
                            ->label('Send order emails / SMS to customers from this dashboard')
                            ->helperText('Turn off if the connected site already sends its own order notifications.')
                            ->default(true)
                            ->columnSpanFull(),
                        Toggle::make('is_active')->default(true)->helperText('Inactive channels get 401 on every API call.'),
                    ])
                    ->columns(2),

                Section::make('Webhooks (this dashboard → your website)')
                    ->description('We POST JSON to this URL when the selected events happen, signed with the webhook secret (header X-Webhook-Signature: sha256=HMAC).')
                    ->schema([
                        TextInput::make('webhook_url')->url()->maxLength(255)->placeholder('https://your-site.com/webhooks/funskool')->columnSpanFull(),
                        CheckboxList::make('webhook_events')
                            ->options(SalesChannel::EVENTS)
                            ->default(array_keys(SalesChannel::EVENTS))
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('API Access')
                    ->visibleOn('edit')
                    ->schema([
                        TextEntry::make('api_base')
                            ->label('API base URL')
                            ->state(fn () => url('/api/channel/v1'))
                            ->copyable(),
                        TextEntry::make('api_key_prefix')
                            ->label('API key')
                            ->state(fn (?SalesChannel $record) => $record?->api_key_prefix ? $record->api_key_prefix.'.••••••••' : 'Not issued yet — use "Generate API key" above')
                            ->helperText('Send as "Authorization: Bearer <key>". Only server-side — never in browser JavaScript or app bundles.'),
                        TextEntry::make('last_used_at')->label('Last API call')->state(fn (?SalesChannel $record) => $record?->last_used_at?->diffForHumans() ?? 'Never'),
                        TextEntry::make('docs')->label('Docs')->state('docs/CHANNEL_API.md in the project repository'),
                    ])
                    ->columns(2),
            ]);
    }
}
