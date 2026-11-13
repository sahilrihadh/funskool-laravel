<?php

use App\Models\WebhookDelivery;
use App\Models\WebhookEvent;
use App\Services\ShippingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('shipments:sync', function (ShippingService $shipping) {
    $this->info('Updated '.$shipping->syncTracking().' shipment(s) from the courier.');
})->purpose('Poll the courier API for tracking updates on in-transit shipments');

Artisan::command('webhooks:prune {--days=60}', function () {
    $cutoff = now()->subDays((int) $this->option('days'));
    $deliveries = WebhookDelivery::query()->where('status', 'delivered')->where('created_at', '<', $cutoff)->delete();
    $events = WebhookEvent::query()->whereNotNull('processed_at')->where('created_at', '<', $cutoff)->delete();
    $this->info("Pruned {$deliveries} delivery log(s) and {$events} inbound event(s).");
})->purpose('Delete old successful webhook logs');

Schedule::command('shipments:sync')->hourly()->withoutOverlapping();
Schedule::command('webhooks:prune')->daily();
