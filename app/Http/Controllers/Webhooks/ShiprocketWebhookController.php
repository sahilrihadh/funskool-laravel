<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\WebhookEvent;
use App\Services\ShippingService;
use App\Shipping\ShiprocketClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;

/**
 * Shiprocket tracking webhook. Configure it in Shiprocket → Settings → API →
 * Webhooks with the same token as SHIPROCKET_WEBHOOK_TOKEN (sent as x-api-key).
 */
class ShiprocketWebhookController extends Controller
{
    public function __invoke(Request $request, ShippingService $shipping)
    {
        $token = (string) config('services.shiprocket.webhook_token');

        if ($token === '' || ! hash_equals($token, (string) $request->header('x-api-key'))) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        $awb = (string) $request->input('awb');
        $current = (string) $request->input('current_status');

        try {
            WebhookEvent::query()->create([
                'provider' => 'shiprocket',
                'event_id' => sha1($awb.'|'.$current.'|'.$request->input('current_timestamp')),
                'type' => $current,
                'payload' => $request->all(),
                'processed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['received' => true]);
        }

        $shipment = $awb !== '' ? Shipment::query()->where('tracking_number', $awb)->first() : null;
        $status = ShiprocketClient::mapStatus($current);

        if ($shipment && $status) {
            $shipping->applyStatus($shipment, $status, $current);
        }

        return response()->json(['received' => true]);
    }
}
