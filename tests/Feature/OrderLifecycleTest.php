<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ReturnService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsStore;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use BuildsStore, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    protected function placeOrder(int $quantity = 2): Order
    {
        $product = $this->product(['regular_price' => 300, 'stock_quantity' => 10]);

        return app(OrderService::class)->placeOrder(
            [['product' => $product, 'quantity' => $quantity]],
            ['user' => $this->customer(), 'shipping_address' => $this->address(), 'payment_method_id' => $this->paymentMethod('cod')->id],
        );
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $order = $this->placeOrder();

        $this->expectException(ValidationException::class);
        app(OrderService::class)->updateStatus($order, 'delivered');
    }

    public function test_cancel_restores_stock_and_flags_paid_orders_for_refund(): void
    {
        $order = $this->placeOrder(3);
        $product = $order->items->first()->product;
        $this->assertSame(7, $product->fresh()->stock_quantity);

        app(PaymentService::class)->recordManualPayment($order, (float) $order->total, 'UTR123');
        $this->assertSame('confirmed', $order->fresh()->status);

        $cancelled = app(OrderService::class)->cancel($order->fresh(), 'Customer called');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertStringContainsString('refund required', $cancelled->statusHistories->last()->note);
    }

    public function test_customer_can_not_cancel_after_shipping(): void
    {
        $order = $this->placeOrder();
        app(OrderService::class)->updateStatus($order, 'confirmed');
        app(ShippingService::class)->createManual($order->fresh(), 'Delhivery', 'AWB1', null);

        $this->actingAs($order->user)
            ->post(route('orders.cancel', $order))
            ->assertSessionHasErrors('order');

        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_full_return_flow_restocks_and_refunds(): void
    {
        $order = $this->placeOrder(2);
        $product = $order->items->first()->product;
        $payments = app(PaymentService::class);
        $orders = app(OrderService::class);

        $payments->recordManualPayment($order, (float) $order->total);
        app(ShippingService::class)->createManual($order->fresh(), 'Blue Dart', 'BD1', 'https://track.test/BD1');
        $orders->updateStatus($order->fresh(), 'delivered');

        $returns = app(ReturnService::class);
        $item = $order->items->first();
        $return = $returns->request($order->fresh(), $order->user, [$item->id => 1], 'damaged');

        $this->assertSame('return_requested', $order->fresh()->status);

        $returns->receive($return, restock: true);
        $this->assertSame('returned', $order->fresh()->status);
        $this->assertSame(9, $product->fresh()->stock_quantity);

        $returns->refund($return->fresh(), 300, viaGateway: false);

        $order->refresh();
        $this->assertSame('partially_refunded', $order->payment_status);
        $this->assertEquals(300, (float) $order->refunded_amount);
        $this->assertSame(1, $order->transactions()->where('type', 'refund')->count());
    }

    public function test_refund_can_not_exceed_amount_paid(): void
    {
        $order = $this->placeOrder();
        app(PaymentService::class)->recordManualPayment($order, (float) $order->total);

        $this->expectException(ValidationException::class);
        app(PaymentService::class)->refund($order->fresh(), (float) $order->total + 1, 'too much', viaGateway: false);
    }
}
