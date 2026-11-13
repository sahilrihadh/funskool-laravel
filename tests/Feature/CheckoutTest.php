<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\StockMovement;
use App\Notifications\OrderPlacedNotification;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsStore;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use BuildsStore, RefreshDatabase;

    public function test_storefront_checkout_creates_order_deducts_stock_and_notifies(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $product = $this->product(['regular_price' => 500, 'stock_quantity' => 5]);
        $shipping = $this->shippingMethod();
        $payment = $this->paymentMethod('cod');

        app(CartService::class)->addItem($customer, $product, null, 2);

        $response = $this->actingAs($customer)->post(route('checkout.store'), [
            ...$this->address(),
            'shipping_method_id' => $shipping->id,
            'payment_method_id' => $payment->id,
            'checkout_token' => 'token-1',
        ]);

        $order = Order::query()->sole();
        $response->assertRedirect(route('checkout.confirmation', $order));

        $this->assertSame('pending', $order->status);
        $this->assertEquals(1000, (float) $order->subtotal);
        $this->assertEquals(1050, (float) $order->total);
        $this->assertSame($customer->email, $order->customer_email);
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertSame(-2, StockMovement::query()->sole()->quantity_change);
        $this->assertSame(0, $customer->cart->items()->count());

        Notification::assertSentOnDemand(OrderPlacedNotification::class);
    }

    public function test_repeated_submit_with_same_checkout_token_creates_one_order(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $data = [
            'shipping_address' => $this->address(),
            'shipping_method_id' => $this->shippingMethod()->id,
            'payment_method_id' => $this->paymentMethod()->id,
            'idempotency_key' => 'same-key',
        ];

        app(CartService::class)->addItem($customer, $product, null, 1);
        $first = app(OrderService::class)->checkout($customer, $data);

        app(CartService::class)->addItem($customer, $product, null, 1);
        $second = app(OrderService::class)->checkout($customer, $data);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Order::query()->count());
    }

    public function test_checkout_stops_when_a_price_changed_since_it_was_added_to_the_cart(): void
    {
        $customer = $this->customer();
        $product = $this->product(['regular_price' => 500]);
        app(CartService::class)->addItem($customer, $product, null, 1);

        $product->update(['regular_price' => 650]);

        try {
            app(OrderService::class)->checkout($customer, [
                'shipping_address' => $this->address(),
                'shipping_method_id' => $this->shippingMethod()->id,
                'payment_method_id' => $this->paymentMethod()->id,
            ]);
            $this->fail('Expected the checkout to be blocked.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cart', $e->errors());
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertEquals(650, (float) $customer->cart->items()->first()->price);
    }

    public function test_stock_can_not_be_oversold(): void
    {
        $product = $this->product(['stock_quantity' => 1]);
        $orders = app(OrderService::class);
        $base = ['shipping_address' => $this->address()];

        $orders->placeOrder([['product' => $product, 'quantity' => 1]], [...$base, 'user' => $this->customer()]);

        $this->expectException(ValidationException::class);
        $orders->placeOrder([['product' => $product->fresh(), 'quantity' => 1]], [...$base, 'user' => $this->customer()]);
    }

    public function test_per_customer_coupon_limit_is_enforced(): void
    {
        $coupon = Coupon::query()->create(['code' => 'ONCE', 'type' => 'fixed', 'value' => 50, 'per_customer_limit' => 1, 'is_active' => true]);
        $customer = $this->customer();
        $orders = app(OrderService::class);
        $order = $orders->placeOrder([['product' => $this->product(), 'quantity' => 1]], ['user' => $customer, 'coupon_code' => 'once', 'shipping_address' => $this->address()]);

        $this->assertEquals(50, (float) $order->discount_amount);
        $this->assertTrue($order->coupon->is($coupon));

        $this->expectException(ValidationException::class);
        $orders->placeOrder([['product' => $this->product(), 'quantity' => 1]], ['user' => $customer, 'coupon_code' => 'ONCE', 'shipping_address' => $this->address()]);
    }
}
