<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Payments\InvalidWebhookSignature;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentService $payments)
    {
        abort_unless(isset(PaymentService::GATEWAYS[$gateway]), 404);

        try {
            $payments->handleWebhook($gateway, $request);
        } catch (InvalidWebhookSignature $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json(['received' => true]);
    }
}
