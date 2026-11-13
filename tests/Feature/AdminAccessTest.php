<?php

namespace Tests\Feature;

use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsStore;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use BuildsStore, RefreshDatabase;

    public function test_every_admin_page_renders_for_the_owner(): void
    {
        Notification::fake();
        $admin = $this->staff('admin');
        $order = app(OrderService::class)->placeOrder(
            [['product' => $this->product(), 'quantity' => 1]],
            ['user' => $this->customer(), 'shipping_address' => $this->address(), 'payment_method_id' => $this->paymentMethod()->id],
        );
        [$channel] = $this->channel();

        $pages = [
            '/admin', '/admin/orders', "/admin/orders/{$order->id}", "/admin/orders/{$order->id}/edit", '/admin/orders/create',
            '/admin/products', "/admin/products/{$order->items->first()->product_id}/edit", '/admin/categories', '/admin/brands',
            '/admin/users', '/admin/coupons', '/admin/banners', '/admin/reviews', '/admin/return-requests',
            '/admin/stock-movements', '/admin/pages', '/admin/pages/create', '/admin/sales-channels', "/admin/sales-channels/{$channel->id}/edit",
            '/admin/sales-channels/create', '/admin/integrations-status', '/admin/manage-settings', '/admin/payment-methods',
            '/admin/shipping-methods', '/admin/activity-logs', '/admin/profile', "/orders/{$order->id}/invoice",
        ];

        foreach ($pages as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
    }

    public function test_customers_can_not_enter_the_admin_panel(): void
    {
        $this->actingAs($this->customer())->get('/admin')->assertForbidden();
    }

    public function test_roles_only_reach_their_areas(): void
    {
        $marketing = $this->staff('marketing');
        $this->actingAs($marketing)->get('/admin/coupons')->assertOk();
        $this->actingAs($marketing)->get('/admin/orders')->assertForbidden();
        $this->actingAs($marketing)->get('/admin/sales-channels')->assertForbidden();

        $fulfillment = $this->staff('fulfillment');
        $this->actingAs($fulfillment)->get('/admin/orders')->assertOk();
        $this->actingAs($fulfillment)->get('/admin/products')->assertOk();
        $this->actingAs($fulfillment)->get('/admin/products/create')->assertForbidden();
        $this->actingAs($fulfillment)->get('/admin/activity-logs')->assertForbidden();

        $analyst = $this->staff('analyst');
        $this->actingAs($analyst)->get('/admin/orders')->assertOk();
        $this->actingAs($analyst)->get('/admin/orders/create')->assertForbidden();
    }

    public function test_support_staff_can_not_edit_staff_accounts(): void
    {
        $support = $this->staff('support');
        $owner = $this->staff('admin');

        $this->actingAs($support)->get("/admin/users/{$owner->id}/edit")->assertForbidden();
        $this->actingAs($support)->get("/admin/users/{$this->customer()->id}/edit")->assertOk();
    }

    public function test_customers_can_only_open_their_own_invoice(): void
    {
        Notification::fake();
        $order = app(OrderService::class)->placeOrder(
            [['product' => $this->product(), 'quantity' => 1]],
            ['user' => $this->customer(), 'shipping_address' => $this->address()],
        );

        $this->actingAs($order->user)->get(route('orders.invoice', $order))->assertOk();
        $this->actingAs($this->customer())->get(route('orders.invoice', $order))->assertForbidden();
    }
}
