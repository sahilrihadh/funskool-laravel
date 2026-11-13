<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\ChannelWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DeliverChannelWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public function __construct(public WebhookDelivery $delivery) {}

    /** Exponential-ish backoff: 10s, 1m, 5m, 15m, 1h. */
    public function backoff(): array
    {
        return [10, 60, 300, 900, 3600];
    }

    public function handle(): void
    {
        $channel = $this->delivery->salesChannel;

        if (! $channel?->is_active || ! $channel->webhook_url) {
            $this->delivery->update(['status' => 'skipped']);

            return;
        }

        $body = json_encode($this->delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->delivery->increment('attempts');

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => config('app.name').'-Webhooks/1.0',
                'X-Webhook-Event' => $this->delivery->event,
                'X-Webhook-Id' => $this->delivery->uuid,
                'X-Webhook-Signature' => ChannelWebhookService::sign($body, (string) $channel->webhook_secret),
            ])
                ->timeout(15)
                ->withBody($body, 'application/json')
                ->post($channel->webhook_url);
        } catch (Throwable $e) {
            $this->delivery->update(['status' => 'retrying', 'response_body' => Str::limit($e->getMessage(), 1000)]);

            throw $e;
        }

        $this->delivery->update([
            'status' => $response->successful() ? 'delivered' : 'retrying',
            'response_code' => $response->status(),
            'response_body' => Str::limit($response->body(), 2000),
            'delivered_at' => $response->successful() ? now() : null,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Webhook endpoint responded {$response->status()}");
        }
    }

    public function failed(?Throwable $e): void
    {
        $this->delivery->update(['status' => 'failed']);
    }
}
