<?php

namespace App\Filament\Resources\SalesChannels\RelationManagers;

use App\Jobs\DeliverChannelWebhook;
use App\Models\WebhookDelivery;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WebhookDeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'webhookDeliveries';

    protected static ?string $title = 'Webhook delivery log';

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('event'),
            TextEntry::make('status')->badge(),
            TextEntry::make('response_code')->placeholder('—'),
            TextEntry::make('attempts'),
            TextEntry::make('payload')->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))->columnSpanFull()->fontFamily('mono'),
            TextEntry::make('response_body')->placeholder('—')->columnSpanFull()->fontFamily('mono'),
        ])->columns(4);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event')->badge(),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    'delivered' => 'success',
                    'failed' => 'danger',
                    'retrying' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('response_code')->placeholder('—'),
                TextColumn::make('attempts'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'retrying' => 'Retrying', 'delivered' => 'Delivered', 'failed' => 'Failed']),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('retry')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (WebhookDelivery $record) => $record->status !== 'delivered')
                    ->action(function (WebhookDelivery $record) {
                        $record->update(['status' => 'pending']);
                        DeliverChannelWebhook::dispatch($record);
                    }),
            ])
            ->defaultSort('id', 'desc');
    }
}
