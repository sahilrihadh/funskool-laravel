<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\PaymentController;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    /** Send an "Idempotency-Key" header so retries never create duplicate orders. */
    public function store(Request $request, PaymentService $payments)
    {
        $data = $request->validate([
            'shipping_address' => ['required', 'array'],
            'shipping_address.full_name' => ['required', 'string'],
            'shipping_address.phone' => ['required', 'string'],
            'shipping_address.line1' => ['required', 'string'],
            'shipping_address.city' => ['required', 'string'],
            'shipping_address.state' => ['required', 'string'],
            'shipping_address.postal_code' => ['required', 'string'],
            'shipping_address.country' => ['required', 'string'],
            'billing_address' => ['nullable', 'array'],
            'shipping_method_id' => ['required', 'exists:shipping_methods,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'coupon_code' => ['nullable', 'string'],
            'customer_note' => ['nullable', 'string'],
        ]);

        $order = $this->orderService->checkout($request->user(), [
            ...$data,
            'idempotency_key' => $request->header('Idempotency-Key'),
        ]);

        $needsPayment = $payments->needsOnlinePayment($order);

        return (new OrderResource($order->load('items')))->additional(['payment' => [
            'requires_action' => $needsPayment,
            'payment_url' => $needsPayment ? PaymentController::startUrl($order) : null,
        ]]);
    }
}
