<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PaymentMethod;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Operational defaults: online gateway methods (inactive until keys are in
 * .env), policy page DRAFTS, and settings. Safe to re-run; never overwrites
 * existing values. Run alone with: php artisan db:seed --class=OperationsSeeder
 */
class OperationsSeeder extends Seeder
{
    public function run(): void
    {
        PaymentMethod::query()->firstOrCreate(
            ['code' => 'razorpay'],
            ['name' => 'UPI / Cards / Netbanking (Razorpay)', 'instructions' => "You'll be redirected to Razorpay's secure page to pay.", 'sort_order' => 0, 'is_active' => false]
        );

        PaymentMethod::query()->firstOrCreate(
            ['code' => 'stripe'],
            ['name' => 'International Card (Stripe)', 'instructions' => "You'll be redirected to Stripe's secure page to pay.", 'sort_order' => 3, 'is_active' => false]
        );

        foreach ([
            'order_number_prefix' => 'ORD',
            'return_window_days' => 7,
            'currency_code' => 'INR',
            'currency_symbol' => '₹',
        ] as $key => $value) {
            if (Setting::get($key) === null) {
                Setting::set($key, $value);
            }
        }

        // Drafts only — every policy must be written/approved for your business
        // (and reviewed by a legal adviser) before it is published.
        $todo = '<p><strong>[DRAFT — replace with your approved policy text before publishing.]</strong></p>';

        $pages = [
            'About Us' => '<h2>Our story</h2><p>Tell customers who you are.</p>',
            'Contact Us' => '<h2>Customer support</h2><p>Email, phone, hours of service and registered address.</p>',
            'Shipping & Delivery Policy' => '<h2>Where we ship</h2><p>Serviceable areas.</p><h2>Delivery times & charges</h2><p>Dispatch time, delivery estimates, free-shipping threshold.</p><h2>Failed deliveries</h2><p>Address issues, re-delivery, return to origin.</p>',
            'Returns, Exchanges & Refunds' => '<h2>Return window</h2><p>Number of days after delivery, item condition, exclusions.</p><h2>How to request a return</h2><p>From My Orders → order → Request a Return.</p><h2>Refunds</h2><p>Method and timeline for refunds, shipping-fee treatment, COD refunds.</p>',
            'Privacy Policy' => '<h2>What we collect</h2><p>Account, order and payment data (card data is handled by our payment provider, never stored by us).</p><h2>How we use it</h2><p>Order fulfilment, support, and marketing only with consent.</p><h2>Your rights</h2><p>Access, correction and deletion requests.</p>',
            'Terms & Conditions' => '<h2>Terms of sale</h2><p>Pricing, availability, order acceptance, cancellations, liability, governing law.</p>',
            'Payment & Security' => '<h2>Accepted payment methods</h2><p>UPI, cards, netbanking, wallets, COD.</p><h2>Security</h2><p>Payments are processed on our gateway partner\'s PCI-DSS compliant pages.</p>',
        ];

        $sort = 0;

        foreach ($pages as $title => $body) {
            Page::query()->firstOrCreate(['title' => $title], [
                'content' => $todo.$body,
                'status' => 'draft',
                'show_in_footer' => true,
                'sort_order' => $sort++,
            ]);
        }
    }
}
