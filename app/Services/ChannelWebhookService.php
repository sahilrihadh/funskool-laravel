<?php

namespace App\Services;

use App\Jobs\DeliverChannelWebhook;
use App\Models\SalesChannel;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;

/**
 * Outbound webhooks to connected websites. Each delivery is signed with the
 * channel's webhook secret:
 *   X-Webhook-Signature: sha256=<hex hmac of raw body>
 * Order events go only to the channel that placed the order; catalogue and
 * stock events go to every channel subscribed to them.
 */
class ChannelWebhookService
{
    public function dispatch(string $event, array $data, ?SalesChannel $only = null): void
    {
        $channels = $only
            ? collect([$only])
            : SalesChannel::query()->where('is_active', true)->whereNotNull('webhook_url')->get();

        foreach ($channels as $channel) {
            if ($channel->subscribesTo($event)) {
                $this->queue($channel, $event, $data);
            }
        }
    }

    public function queue(SalesChannel $channel, string $event, array $data): WebhookDelivery
    {
        $uuid = (string) Str::uuid();

        $delivery = $channel->webhookDeliveries()->create([
            'uuid' => $uuid,
            'event' => $event,
            'payload' => [
                'id' => $uuid,
                'event' => $event,
                'created_at' => now()->toIso8601String(),
                'data' => $data,
            ],
        ]);

        DeliverChannelWebhook::dispatch($delivery);

        return $delivery;
    }

    public static function sign(string $body, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $body, $secret);
    }
}
