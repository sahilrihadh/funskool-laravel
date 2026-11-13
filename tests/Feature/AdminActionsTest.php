<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\SalesChannels\Pages\CreateSalesChannel;
use App\Filament\Widgets\LatestOrdersWidget;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\NeedsActionWidget;
use App\Filament\Widgets\OrdersChartWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\TopProductsWidget;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\SalesChannel;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\BuildsStore;
use Tests\TestCase;

class AdminActionsTest extends TestCase
{
    use BuildsStore, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->actingAs($this->staff('admin'));
    }

    protected function order(): Order
    {
        return app(OrderService::class)->placeOrder(
            [['product' => $this->product(['regular_price' => 250]), 'quantity' => 2]],
            ['user' => $this->customer(), 'shipping_address' => $this->address(), 'payment_method_id' => $this->paymentMethod('cod')->id],
        );
    }

    public function test_dashboard_widgets_render_with_data_and_filters(): void
    {
        $this->order();
        $this->product(['stock_quantity' => 1]);

        foreach ([NeedsActionWidget::class, StatsOverviewWidget::class, OrdersChartWidget::class, TopProductsWidget::class, LowStockWidget::class, LatestOrdersWidget::class] as $widget) {
            Livewire::test($widget, ['pageFilters' => ['period' => '7', 'channel' => null]])->assertOk();
        }

        Livewire::test(StatsOverviewWidget::class, ['pageFilters' => ['period' => '365', 'channel' => 'storefront']])->assertSee('Net sales');
        Livewire::test(OrdersChartWidget::class, ['pageFilters' => ['period' => '365']])->assertOk();
    }

    public function test_order_page_actions_confirm_pay_and_ship(): void
    {
        $order = $this->order();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('updateStatus', ['status' => 'confirmed', 'note' => 'Called customer'])
            ->assertHasNoActionErrors()
            ->callAction('recordPayment', ['amount' => (float) $order->total, 'reference' => 'COD-1'])
            ->callAction('createShipment', ['carrier' => 'manual', 'courier_name' => 'Delhivery', 'tracking_number' => 'DL123']);

        $order->refresh();
        $this->assertSame('shipped', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('DL123', $order->shipments()->sole()->tracking_number);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('markDelivered')
            ->callAction('refund', ['amount' => 100, 'reason' => 'Goodwill', 'via_gateway' => false]);

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('partially_refunded', $order->payment_status);
    }

    public function test_manual_order_uses_checkout_pricing(): void
    {
        $product = $this->product(['regular_price' => 300, 'stock_quantity' => 5]);
        $customer = $this->customer();

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'user_id' => $customer->id,
                'shipping_method_id' => $this->shippingMethod()->id,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'shipping_address' => $this->address(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::query()->sole();
        $this->assertEquals(650, (float) $order->total);
        $this->assertSame(3, $product->fresh()->stock_quantity);
    }

    public function test_connecting_a_website_issues_a_hashed_api_key(): void
    {
        Livewire::test(CreateSalesChannel::class)
            ->fillForm(['name' => 'Woo Store', 'type' => 'woocommerce', 'webhook_events' => ['order.created']])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $channel = SalesChannel::query()->sole();
        $this->assertNotNull($channel->api_key_hash);
        $this->assertSame(64, strlen($channel->api_key_hash));
        $this->assertNotNull($channel->webhook_secret);
    }

    public function test_adjust_stock_writes_ledger_and_audit(): void
    {
        $product = $this->product(['stock_quantity' => 3]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction('adjustStock', ['mode' => 'add', 'quantity' => 7, 'reason' => 'restock']);

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(7, $product->stockMovements()->sole()->quantity_change);
        $this->assertTrue(ActivityLog::query()->where('subject_type', $product->getMorphClass())->exists());
    }
}
