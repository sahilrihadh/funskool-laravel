<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index()
    {
        return view('storefront.orders.index', [
            'orders' => Auth::user()->orders()->latest()->paginate(10),
        ]);
    }

    public function show(Order $order, ReturnService $returns, PaymentService $payments)
    {
        abort_unless($order->user_id === Auth::id(), 403);

        return view('storefront.orders.show', [
            'order' => $order->load(['items', 'shippingMethod', 'paymentMethod', 'shipments', 'returnRequests',
                'statusHistories' => fn ($query) => $query->where('is_customer_visible', true)]),
            'canReturn' => $returns->isEligible($order),
            'returnDeadline' => $returns->returnDeadline($order),
            'returnable' => $returns->returnableQuantities($order),
            'returnReasons' => ReturnRequest::REASONS,
            'payUrl' => $payments->needsOnlinePayment($order) ? PaymentController::startUrl($order) : null,
        ]);
    }

    public function cancel(Request $request, Order $order, OrderService $orders)
    {
        abort_unless($order->user_id === Auth::id(), 403);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $orders->cancel($order, $data['reason'] ?? null, byCustomer: true);

        return back()->with('success', 'Your order has been cancelled.');
    }

    public function requestReturn(Request $request, Order $order, ReturnService $returns)
    {
        abort_unless($order->user_id === Auth::id(), 403);

        $data = $request->validate([
            'reason' => ['required', 'string'],
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $returns->request($order, Auth::user(), $data['items'], $data['reason'], $data['customer_note'] ?? null);

        return back()->with('success', "Return requested. We'll email you the next steps.");
    }

    /** Printable invoice or packing slip — the customer's own order, or staff with orders access. */
    public function invoice(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($order->user_id === $user->id || $user->hasPermission('orders.view'), 403);

        return view('invoices.show', [
            'order' => $order->load(['items', 'paymentMethod', 'shippingMethod']),
            'packingSlip' => $request->query('type') === 'packing-slip' && $user->isStaff(),
        ]);
    }
}
