<?php

namespace App\Filament\Resources\SalesChannels\Pages;

use App\Filament\Resources\SalesChannels\SalesChannelResource;
use App\Models\ActivityLog;
use App\Models\SalesChannel;
use App\Services\ChannelWebhookService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSalesChannel extends EditRecord
{
    protected static string $resource = SalesChannelResource::class;

    protected function channel(): SalesChannel
    {
        return $this->getRecord();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rotateKey')
                ->label(fn () => $this->channel()->api_key_prefix ? 'Rotate API key' : 'Generate API key')
                ->icon('heroicon-o-key')
                ->requiresConfirmation()
                ->modalDescription('The current key stops working immediately. Update the connected site straight after.')
                ->action(function () {
                    $key = $this->channel()->rotateApiKey();
                    ActivityLog::record('api_key_rotated', $this->channel());

                    Notification::make()
                        ->title('New API key — copy it now')
                        ->body("{$key}\n\nThis is the only time it will be shown.")
                        ->persistent()
                        ->success()
                        ->send();
                }),

            Action::make('rotateSecret')
                ->label('Reveal / rotate webhook secret')
                ->icon('heroicon-o-shield-check')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Issues a new signing secret. The connected site must verify signatures with the new value.')
                ->action(function () {
                    $secret = $this->channel()->rotateWebhookSecret();
                    ActivityLog::record('webhook_secret_rotated', $this->channel());

                    Notification::make()->title('Webhook signing secret')->body($secret)->persistent()->success()->send();
                }),

            Action::make('testWebhook')
                ->label('Send test webhook')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->disabled(fn () => blank($this->channel()->webhook_url))
                ->action(function (ChannelWebhookService $webhooks) {
                    $webhooks->queue($this->channel(), 'ping', ['message' => 'Test webhook from '.config('app.name')]);

                    Notification::make()->title('Test webhook queued')->body('See the delivery log below for the response.')->success()->send();
                }),

            DeleteAction::make(),
        ];
    }
}
