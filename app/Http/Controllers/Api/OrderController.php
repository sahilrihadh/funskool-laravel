<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\PaymentController;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ReturnService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return OrderResource::collection(
            $request->user()->orders()->latest()->paginate(10)
        );
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return new OrderResource($order->load(['items', 'statusHistories', 'shipments', 'returnRequests']));
    }

    public function cancel(Request $request, Order $order, OrderService $orders)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return new OrderResource($orders->cancel($order, $data['reason'] ?? null, byCustomer: true)->load('items'));
    }

    /** Body: { reason, items: { "<order_item_id>": quantity }, customer_note? } */
    public function requestReturn(Request $request, Order $order, ReturnService $returns)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'reason' => ['required', 'string'],
            'items' => ['required', 'array'],
            'items.*' => ['integer', 'min:0'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $return = $returns->request($order, $request->user(), $data['items'], $data['reason'], $data['customer_note'] ?? null);

        return response()->json(['data' => $return->only(['id', 'status', 'reason', 'items', 'created_at'])], 201);
    }

    /** Signed hosted-payment link for an unpaid online-payment order. */
    public function paymentLink(Request $request, Order $order, PaymentService $payments)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return response()->json(['data' => [
            'requires_action' => $needs = $payments->needsOnlinePayment($order),
            'payment_url' => $needs ? PaymentController::startUrl($order) : null,
        ]]);
    }
}
