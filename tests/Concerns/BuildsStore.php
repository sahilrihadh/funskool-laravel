<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Support\Str;

trait BuildsStore
{
    protected function customer(array $attributes = []): User
    {
        return User::factory()->create(['role' => 'customer', 'phone' => '9876543210', ...$attributes]);
    }

    protected function staff(string $role = 'admin'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    protected function product(array $attributes = []): Product
    {
        $category = Category::query()->firstOrCreate(['name' => 'Toys'], ['is_active' => true]);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Test Toy '.Str::random(4),
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'regular_price' => 500,
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    protected function shippingMethod(array $attributes = []): ShippingMethod
    {
        return ShippingMethod::query()->create(['name' => 'Standard', 'cost' => 50, 'free_shipping_above' => 2000, 'is_active' => true, ...$attributes]);
    }

    protected function paymentMethod(string $code = 'cod'): PaymentMethod
    {
        return PaymentMethod::query()->firstOrCreate(['code' => $code], ['name' => strtoupper($code), 'is_active' => true]);
    }

    protected function address(): array
    {
        return [
            'full_name' => 'Asha Rao',
            'phone' => '9876543210',
            'line1' => '12 MG Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'postal_code' => '560001',
            'country' => 'India',
        ];
    }

    /** @return array{0: SalesChannel, 1: string} channel + plain API key */
    protected function channel(array $attributes = []): array
    {
        $channel = SalesChannel::query()->create([
            'name' => 'Partner Shop',
            'type' => 'woocommerce',
            'webhook_url' => 'https://partner.test/webhooks',
            'webhook_events' => array_keys(SalesChannel::EVENTS),
            'is_active' => true,
            ...$attributes,
        ]);

        $key = $channel->rotateApiKey();
        $channel->rotateWebhookSecret();

        return [$channel->fresh(), $key];
    }
}
