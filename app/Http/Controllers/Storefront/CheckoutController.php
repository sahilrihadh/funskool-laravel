<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\ShippingMethod;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected OrderService $orderService,
        protected PaymentService $paymentService,
    ) {}

    public function index()
    {
        $user = Auth::user();
        $cart = $this->cartService->getOrCreateCart($user);
        $cart->load(['items.product', 'items.variant']);

        return view('storefront.checkout', [
            'cart' => $cart,
            'addresses' => $user->addresses,
            'shippingMethods' => ShippingMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get()
                ->filter(fn (PaymentMethod $method) => ! isset(PaymentService::GATEWAYS[$method->code]) || $this->paymentService->gateway($method->code)->isConfigured()),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
            'shipping_method_id' => ['required', 'exists:shipping_methods,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'coupon_code' => ['nullable', 'string'],
            'customer_note' => ['nullable', 'string'],
            'checkout_token' => ['nullable', 'string', 'max:64'],
        ]);

        $address = collect($data)->only(['full_name', 'phone', 'line1', 'line2', 'city', 'state', 'postal_code', 'country'])->all();

        $order = $this->orderService->checkout(Auth::user(), [
            'shipping_address' => $address,
            'billing_address' => $address,
            'shipping_method_id' => $data['shipping_method_id'],
            'payment_method_id' => $data['payment_method_id'],
            'coupon_code' => $data['coupon_code'] ?? null,
            'customer_note' => $data['customer_note'] ?? null,
            // One token per checkout page view: a double-click or refresh can't create two orders.
            'idempotency_key' => $data['checkout_token'] ?? null,
        ]);

        if ($this->paymentService->needsOnlinePayment($order)) {
            return redirect()->to(PaymentController::startUrl($order));
        }

        return redirect()->route('checkout.confirmation', $order);
    }

    public function confirmation(Order $order)
    {
        abort_unless($order->user_id === Auth::id(), 403);

        return view('storefront.checkout-confirmation', [
            'order' => $order->load('items'),
            'payUrl' => $this->paymentService->needsOnlinePayment($order) ? PaymentController::startUrl($order) : null,
        ]);
    }
}
