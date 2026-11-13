<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\WebhookDelivery;
use App\Services\ChannelWebhookService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsStore;
use Tests\TestCase;

class ChannelApiTest extends TestCase
{
    use BuildsStore, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Http::fake(['partner.test/*' => Http::response(['ok' => true])]);
    }

    protected function orderPayload(string $sku, array $overrides = []): array
    {
        return [
            'external_reference' => 'WC-1001',
            'customer' => ['name' => 'Asha Rao', 'email' => 'asha@example.com', 'phone' => '9876543210'],
            'shipping_address' => $this->address(),
            'items' => [['sku' => $sku, 'quantity' => 2]],
            'shipping_method_id' => $this->shippingMethod()->id,
            'payment_method_code' => $this->paymentMethod('cod')->code,
            ...$overrides,
        ];
    }

    public function test_requests_without_a_valid_key_are_rejected(): void
    {
        $this->getJson('/api/channel/v1/products')->assertUnauthorized();
        $this->withToken('fsk_nope.wrong')->getJson('/api/channel/v1/products')->assertUnauthorized();

        [$channel, $key] = $this->channel(['is_active' => false]);
        $this->withToken($key)->getJson('/api/channel/v1/products')->assertUnauthorized();
    }

    public function test_catalog_and_inventory_sync(): void
    {
        [, $key] = $this->channel();
        $product = $this->product(['stock_quantity' => 4]);
        $this->product(['is_active' => false]);

        $this->withToken($key)->getJson('/api/channel/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', $product->sku);

        $this->withHeader('X-Api-Key', $key)->getJson('/api/channel/v1/inventory?sku[]='.$product->sku)
            ->assertOk()
            ->assertJsonPath('data.0.stock_quantity', 4);
    }

    public function test_quote_returns_server_side_totals(): void
    {
        [, $key] = $this->channel();
        $product = $this->product(['regular_price' => 400]);

        $this->withToken($key)->postJson('/api/channel/v1/quote', [
            'items' => [['sku' => $product->sku, 'quantity' => 3]],
            'shipping_method_id' => $this->shippingMethod()->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 1200)
            ->assertJsonPath('data.shipping_amount', 50)
            ->assertJsonPath('data.total', 1250);
    }

    public function test_order_creation_is_idempotent_and_links_the_customer(): void
    {
        [$channel, $key] = $this->channel();
        $product = $this->product(['stock_quantity' => 5]);
        $payload = $this->orderPayload($product->sku);

        $this->withToken($key)->withHeader('Idempotency-Key', 'abc')->postJson('/api/channel/v1/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.external_reference', 'WC-1001')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment.requires_action', false);

        $this->withToken($key)->withHeader('Idempotency-Key', 'abc')->postJson('/api/channel/v1/orders', $payload)
            ->assertOk();

        $order = Order::query()->sole();
        $this->assertTrue($order->salesChannel->is($channel));
        $this->assertSame('asha@example.com', $order->user->email);
        $this->assertSame(3, $product->fresh()->stock_quantity);

        $this->withToken($key)->getJson('/api/channel/v1/orders/WC-1001')->assertOk()->assertJsonPath('data.order_number', $order->order_number);
    }

    public function test_externally_paid_order_must_match_the_server_total(): void
    {
        [, $key] = $this->channel();
        $product = $this->product(['regular_price' => 500]);

        $this->withToken($key)->postJson('/api/channel/v1/orders', $this->orderPayload($product->sku, [
            'payment' => ['status' => 'paid', 'gateway' => 'woo-razorpay', 'reference' => 'pay_1', 'amount' => 10],
        ]))->assertUnprocessable()->assertJsonValidationErrors('payment.amount');

        $this->withToken($key)->postJson('/api/channel/v1/orders', $this->orderPayload($product->sku, [
            'payment' => ['status' => 'paid', 'gateway' => 'woo-razorpay', 'reference' => 'pay_1', 'amount' => 1050],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_order_events_are_delivered_as_signed_webhooks(): void
    {
        [$channel, $key] = $this->channel();
        $product = $this->product();

        $this->withToken($key)->postJson('/api/channel/v1/orders', $this->orderPayload($product->sku))->assertCreated();
        app(OrderService::class)->updateStatus(Order::query()->sole(), 'confirmed');

        $delivery = WebhookDelivery::query()->where('event', 'order.created')->sole();
        $this->assertSame('delivered', $delivery->status);
        $this->assertTrue(WebhookDelivery::query()->where('event', 'order.status_changed')->exists());

        Http::assertSent(function (Request $request) use ($channel) {
            return $request->hasHeader('X-Webhook-Event', 'order.created')
                && $request->header('X-Webhook-Signature')[0] === ChannelWebhookService::sign($request->body(), $channel->webhook_secret);
        });
    }

    public function test_a_channel_can_only_see_its_own_orders(): void
    {
        [, $keyA] = $this->channel(['name' => 'A']);
        [, $keyB] = $this->channel(['name' => 'B']);
        $product = $this->product();

        $this->withToken($keyA)->postJson('/api/channel/v1/orders', $this->orderPayload($product->sku))->assertCreated();

        $this->withToken($keyB)->getJson('/api/channel/v1/orders/WC-1001')->assertNotFound();
        $this->withToken($keyB)->getJson('/api/channel/v1/orders')->assertJsonCount(0, 'data');
    }
}
