<?php

namespace App\Filament\Resources\SalesChannels\Pages;

use App\Filament\Resources\SalesChannels\SalesChannelResource;
use App\Models\SalesChannel;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesChannel extends CreateRecord
{
    protected static string $resource = SalesChannelResource::class;

    protected function afterCreate(): void
    {
        /** @var SalesChannel $channel */
        $channel = $this->getRecord();

        $key = $channel->rotateApiKey();
        $secret = $channel->rotateWebhookSecret();

        Notification::make()
            ->title('Website connected — copy these now')
            ->body("API key: {$key}\n\nWebhook secret: {$secret}\n\nThe API key is shown only once. Store both in the connected site's server-side environment variables.")
            ->persistent()
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
