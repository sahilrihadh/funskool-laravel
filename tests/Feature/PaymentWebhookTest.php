<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\WebhookEvent;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsStore;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use BuildsStore, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        config([
            'services.razorpay.key_id' => 'rzp_test_key',
            'services.razorpay.key_secret' => 'rzp_secret',
            'services.razorpay.webhook_secret' => 'rzp_whsec',
            'services.stripe.secret' => 'sk_test_x',
            'services.stripe.webhook_secret' => 'whsec_stripe',
        ]);
    }

    protected function order(string $gateway): Order
    {
        return app(OrderService::class)->placeOrder(
            [['product' => $this->product(['regular_price' => 1000]), 'quantity' => 1]],
            ['user' => $this->customer(), 'shipping_address' => $this->address(), 'payment_method_id' => $this->paymentMethod($gateway)->id],
        );
    }

    protected function razorpayWebhook(Order $order, string $eventId, ?string $signature = null, ?float $amountPaid = null)
    {
        $body = json_encode([
            'event' => 'payment_link.paid',
            'payload' => [
                'payment_link' => ['entity' => ['id' => 'plink_1', 'amount_paid' => (int) (($amountPaid ?? $order->total) * 100), 'notes' => ['order_number' => $order->order_number]]],
                'payment' => ['entity' => ['id' => 'pay_1']],
            ],
        ]);

        return $this->call('POST', '/api/webhooks/payments/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, 'rzp_whsec'),
            'HTTP_X_RAZORPAY_EVENT_ID' => $eventId,
        ], $body);
    }

    public function test_razorpay_checkout_redirects_to_the_hosted_page(): void
    {
        Http::fake(['api.razorpay.com/v1/payment_links' => Http::response(['id' => 'plink_1', 'short_url' => 'https://rzp.io/i/abc'])]);
        $order = $this->order('razorpay');

        $this->assertTrue(app(PaymentService::class)->needsOnlinePayment($order));
        $this->assertSame('https://rzp.io/i/abc', app(PaymentService::class)->startCheckout($order));
        $this->assertSame('plink_1', $order->transactions()->sole()->reference);
    }

    public function test_signed_razorpay_webhook_marks_the_order_paid_once(): void
    {
        $order = $this->order('razorpay');

        $this->razorpayWebhook($order, 'evt_1')->assertOk();
        $this->razorpayWebhook($order, 'evt_1')->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
        $this->assertSame(1, $order->transactions()->count());
        $this->assertSame(1, WebhookEvent::query()->count());
    }

    public function test_razorpay_webhook_with_a_bad_signature_is_rejected(): void
    {
        $order = $this->order('razorpay');

        $this->razorpayWebhook($order, 'evt_2', 'forged')->assertStatus(400);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_razorpay_return_is_verified_with_the_api_not_trusted_from_the_browser(): void
    {
        Http::fake([
            'api.razorpay.com/v1/payment_links' => Http::response(['id' => 'plink_9', 'short_url' => 'https://rzp.io/i/x']),
            'api.razorpay.com/v1/payment_links/plink_9' => Http::response(['id' => 'plink_9', 'status' => 'created']),
        ]);
        $order = $this->order('razorpay');
        app(PaymentService::class)->startCheckout($order);

        $params = ['razorpay_payment_link_id' => 'plink_9', 'razorpay_payment_link_reference_id' => 'r', 'razorpay_payment_link_status' => 'paid', 'razorpay_payment_id' => 'pay_9'];
        $params['razorpay_signature'] = hash_hmac('sha256', implode('|', $params), 'rzp_secret');

        $this->actingAs($order->user)->get(route('payments.return', ['gateway' => 'razorpay', 'order' => $order->order_number]).'?'.http_build_query($params));

        // The redirect claims "paid", but the API says the link is still unpaid.
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_stripe_webhook_signature_and_replay_window(): void
    {
        $order = $this->order('stripe');
        $body = json_encode([
            'id' => 'evt_s1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_1', 'amount_total' => 105000, 'metadata' => ['order_number' => $order->order_number]]],
        ]);

        $send = fn (int $timestamp) => $this->call('POST', '/api/webhooks/payments/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$body}", 'whsec_stripe'),
        ], $body);

        $send(time() - 3600)->assertStatus(400);
        $this->assertSame('pending', $order->fresh()->payment_status);

        $send(time())->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('pi_1', $order->transactions()->sole()->gateway_payment_id);
    }

    public function test_amount_mismatch_puts_the_order_on_hold(): void
    {
        $order = $this->order('razorpay');

        $this->razorpayWebhook($order, 'evt_3', amountPaid: 10)->assertOk();

        $this->assertSame('on_hold', $order->fresh()->status);
    }
}
