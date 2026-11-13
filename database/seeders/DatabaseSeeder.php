<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@funskool.test'],
            [
                'name' => 'Store Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $customer = User::query()->updateOrCreate(
            ['email' => 'customer@funskool.test'],
            [
                'name' => 'Demo Customer',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '9876543210',
                'email_verified_at' => now(),
            ]
        );

        $customer->addresses()->updateOrCreate(
            ['label' => 'Home'],
            [
                'full_name' => 'Demo Customer',
                'phone' => '9876543210',
                'line1' => '221B Baker Street',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'postal_code' => '400001',
                'country' => 'India',
                'type' => 'shipping',
                'is_default' => true,
            ]
        );

        ShippingMethod::query()->firstOrCreate(
            ['name' => 'Standard Shipping'],
            ['description' => 'Delivered in 5-7 business days.', 'cost' => 49, 'free_shipping_above' => 999, 'sort_order' => 1]
        );

        ShippingMethod::query()->firstOrCreate(
            ['name' => 'Express Shipping'],
            ['description' => 'Delivered in 1-2 business days.', 'cost' => 149, 'sort_order' => 2]
        );

        PaymentMethod::query()->firstOrCreate(
            ['code' => 'cod'],
            ['name' => 'Cash on Delivery', 'instructions' => 'Pay in cash when your order is delivered.', 'sort_order' => 1]
        );

        PaymentMethod::query()->firstOrCreate(
            ['code' => 'bank_transfer'],
            ['name' => 'Bank Transfer', 'instructions' => 'Transfer the total amount to our bank account and share the reference number.', 'sort_order' => 2]
        );

        Coupon::query()->firstOrCreate(
            ['code' => 'WELCOME10'],
            [
                'type' => 'percent',
                'value' => 10,
                'min_order_amount' => 500,
                'max_discount_amount' => 200,
                'usage_limit' => 100,
                'is_active' => true,
            ]
        );

        $this->call(OperationsSeeder::class);

        $this->seedCatalog($customer);
    }

    protected function seedCatalog(User $customer): void
    {
        $items = json_decode(file_get_contents(__DIR__.'/data/funskool_products.json'), true);

        $categoryDescriptions = [
            'Building Blocks & Construction' => 'Bricks, blocks and build-your-own sets for young engineers.',
            'Board Games & Puzzles' => 'Classic family board games, card games and strategy puzzles.',
            'Puzzles & STEM' => 'Wooden puzzles, science kits and brain-teasers that make learning fun.',
            'Remote Control & Vehicles' => 'RC cars and vehicles built for high-speed play.',
            'Action Figures & Characters' => 'Everyone\'s favourite characters, ready for adventure.',
            'Preschool & Infant Toys' => 'Soft, safe and sensory toys for the youngest players.',
            'Arts & Crafts' => 'DIY kits, painting sets and craft activities for budding artists.',
            'Dolls & Collectibles' => 'Dolls, coin banks and collectible character merchandise.',
            'Play Sets & Pretend Play' => 'Kitchens, shops and role-play sets for imaginative fun.',
        ];

        $categories = collect($categoryDescriptions)->map(
            fn (string $description, string $name) => Category::query()->firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true]
            )
        );

        $brandNames = collect($items)->pluck('brand')->unique();
        $brands = $brandNames->mapWithKeys(fn (string $name) => [
            $name => Brand::query()->firstOrCreate(['name' => $name]),
        ]);

        foreach ($items as $index => $item) {
            $sku = $item['sku'] !== '' ? $item['sku'] : 'FSK-'.strtoupper(Str::substr(Str::slug($item['slug']), 0, 12)).'-'.($index + 1);

            $product = Product::query()->updateOrCreate(
                ['sku' => $sku],
                [
                    'category_id' => $categories[$item['category']]->id,
                    'brand_id' => $brands[$item['brand']]->id,
                    'name' => $item['name'],
                    'short_description' => Str::limit($item['short_description'], 300),
                    'description' => $item['short_description']
                        ? '<p>'.nl2br(e($item['short_description'])).'</p>'
                        : null,
                    'regular_price' => $item['regular_price'],
                    'sale_price' => $item['sale_price'],
                    'image' => $item['images'][0] ?? null,
                    'stock_quantity' => $item['is_featured'] ? random_int(3, 15) : random_int(15, 80),
                    'low_stock_threshold' => 5,
                    'is_active' => true,
                    'is_featured' => $item['is_featured'],
                ]
            );

            if (! $product->wasRecentlyCreated) {
                continue;
            }

            foreach (array_slice($item['images'], 1) as $sortOrder => $imagePath) {
                $product->images()->create([
                    'image_path' => $imagePath,
                    'sort_order' => $sortOrder,
                ]);
            }

            $product->marketplaceLinks()->createMany([
                ['platform' => 'Funskool India', 'url' => $item['source_url'], 'sort_order' => 1],
                ['platform' => 'Amazon', 'url' => 'https://www.amazon.in/s?k='.urlencode($item['name']), 'sort_order' => 2],
                ['platform' => 'Flipkart', 'url' => 'https://www.flipkart.com/search?q='.urlencode($item['name']), 'sort_order' => 3],
            ]);

            if ($item['is_featured']) {
                $product->reviews()->create([
                    'user_id' => $customer->id,
                    'rating' => random_int(4, 5),
                    'title' => 'Great buy!',
                    'comment' => 'My kids love this — good quality and true to the pictures.',
                    'is_approved' => true,
                ]);
            }
        }
    }
}
