<?php

namespace App\Filament\Pages;

use App\Models\PaymentMethod;
use App\Models\SalesChannel;
use App\Sms\SmsSender;
use Filament\Pages\Page;

/**
 * Read-only health view of every third-party credential the store needs.
 * Shows only whether each env var is set — never its value.
 */
class IntegrationsStatus extends Page
{
    protected static ?string $navigationLabel = 'Integration Status';

    protected static ?string $title = 'Integration Status';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.integrations-status';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('integrations.view') || (bool) auth()->user()?->hasPermission('settings.view');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Integrations';
    }

    protected function getViewData(): array
    {
        // Read through config (not env()) so this stays correct after `php artisan config:cache`.
        $map = [
            'RAZORPAY_KEY_ID' => 'services.razorpay.key_id',
            'RAZORPAY_KEY_SECRET' => 'services.razorpay.key_secret',
            'RAZORPAY_WEBHOOK_SECRET' => 'services.razorpay.webhook_secret',
            'STRIPE_KEY' => 'services.stripe.key',
            'STRIPE_SECRET' => 'services.stripe.secret',
            'STRIPE_WEBHOOK_SECRET' => 'services.stripe.webhook_secret',
            'SHIPROCKET_EMAIL' => 'services.shiprocket.email',
            'SHIPROCKET_PASSWORD' => 'services.shiprocket.password',
            'SHIPROCKET_PICKUP_LOCATION' => 'services.shiprocket.pickup_location',
            'SHIPROCKET_WEBHOOK_TOKEN' => 'services.shiprocket.webhook_token',
            'MAIL_FROM_ADDRESS' => 'mail.from.address',
            'MAIL_HOST' => 'mail.mailers.smtp.host',
            'MAIL_USERNAME' => 'mail.mailers.smtp.username',
            'MAIL_PASSWORD' => 'mail.mailers.smtp.password',
            'AWS_ACCESS_KEY_ID' => 'services.ses.key',
            'AWS_SECRET_ACCESS_KEY' => 'services.ses.secret',
            'POSTMARK_API_KEY' => 'services.postmark.key',
            'RESEND_API_KEY' => 'services.resend.key',
            'TWILIO_SID' => 'services.twilio.sid',
            'TWILIO_AUTH_TOKEN' => 'services.twilio.token',
            'TWILIO_FROM' => 'services.twilio.from',
            'MSG91_AUTH_KEY' => 'services.msg91.auth_key',
            'MSG91_SENDER_ID' => 'services.msg91.sender_id',
            'MSG91_TEMPLATE_ORDER_PLACED' => 'services.msg91.templates.order_placed',
            'MSG91_TEMPLATE_ORDER_STATUS' => 'services.msg91.templates.order_status',
            'MSG91_TEMPLATE_ORDER_SHIPPED' => 'services.msg91.templates.order_shipped',
            'MSG91_TEMPLATE_PAYMENT_RECEIVED' => 'services.msg91.templates.payment_received',
        ];
        $env = fn (string ...$keys) => collect($keys)->mapWithKeys(fn ($key) => [$key => filled(config($map[$key]))])->all();
        $mailFromIsDefault = config('mail.from.address') === 'hello@example.com';
        $activeCodes = PaymentMethod::query()->where('is_active', true)->pluck('code')->all();

        return [
            'groups' => [
                [
                    'name' => 'Razorpay (UPI, cards, netbanking, wallets — India)',
                    'required' => true,
                    'keys' => $env('RAZORPAY_KEY_ID', 'RAZORPAY_KEY_SECRET', 'RAZORPAY_WEBHOOK_SECRET'),
                    'note' => in_array('razorpay', $activeCodes, true) ? 'Payment method "razorpay" is active.' : 'Activate the "Razorpay" payment method under Configuration → Payment Methods.',
                    'webhook' => route('webhooks.payments', 'razorpay'),
                    'webhook_hint' => 'Razorpay Dashboard → Settings → Webhooks → event: payment_link.paid, payment_link.expired, payment_link.cancelled',
                ],
                [
                    'name' => 'Stripe (international cards / wallets) — optional',
                    'required' => false,
                    'keys' => $env('STRIPE_KEY', 'STRIPE_SECRET', 'STRIPE_WEBHOOK_SECRET'),
                    'note' => in_array('stripe', $activeCodes, true) ? 'Payment method "stripe" is active.' : 'Optional second gateway.',
                    'webhook' => route('webhooks.payments', 'stripe'),
                    'webhook_hint' => 'Stripe Dashboard → Developers → Webhooks → events: checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, checkout.session.expired',
                ],
                [
                    'name' => 'Shiprocket (courier aggregator: AWB, labels, tracking)',
                    'required' => true,
                    'keys' => $env('SHIPROCKET_EMAIL', 'SHIPROCKET_PASSWORD', 'SHIPROCKET_PICKUP_LOCATION', 'SHIPROCKET_WEBHOOK_TOKEN'),
                    'note' => 'Without it, ship orders manually by entering the courier + tracking number.',
                    'webhook' => route('webhooks.shiprocket'),
                    'webhook_hint' => 'Shiprocket → Settings → API → Webhooks; set the token to SHIPROCKET_WEBHOOK_TOKEN',
                ],
                [
                    'name' => 'Email delivery (order confirmations, password resets)',
                    'required' => true,
                    'keys' => [
                        'MAIL_MAILER = '.config('mail.default') => config('mail.default') !== 'log',
                        'MAIL_FROM_ADDRESS' => ! $mailFromIsDefault && filled(config('mail.from.address')),
                        ...(config('mail.default') === 'smtp' ? $env('MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD') : []),
                        ...(config('mail.default') === 'ses' ? $env('AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY') : []),
                        ...(config('mail.default') === 'postmark' ? $env('POSTMARK_API_KEY') : []),
                        ...(config('mail.default') === 'resend' ? $env('RESEND_API_KEY') : []),
                    ],
                    'note' => config('mail.default') === 'log' ? 'Emails are only written to the log right now.' : 'Remember SPF/DKIM/DMARC DNS records for the sender domain.',
                ],
                [
                    'name' => 'SMS (order & shipping updates)',
                    'required' => false,
                    'keys' => [
                        'SMS_DRIVER = '.config('services.sms.driver') => config('services.sms.driver') !== 'log',
                        ...(config('services.sms.driver') === 'twilio' ? $env('TWILIO_SID', 'TWILIO_AUTH_TOKEN', 'TWILIO_FROM') : $env('MSG91_AUTH_KEY', 'MSG91_SENDER_ID', 'MSG91_TEMPLATE_ORDER_PLACED', 'MSG91_TEMPLATE_ORDER_STATUS', 'MSG91_TEMPLATE_ORDER_SHIPPED', 'MSG91_TEMPLATE_PAYMENT_RECEIVED')),
                    ],
                    'note' => app(SmsSender::class)->isConfigured() ? 'SMS sending is live.' : 'SMS are only written to the log right now. India requires DLT-registered templates.',
                ],
                [
                    'name' => 'Queue worker & scheduler (emails, webhooks, tracking sync)',
                    'required' => true,
                    'keys' => ['QUEUE_CONNECTION = '.config('queue.default') => config('queue.default') !== 'sync'],
                    'note' => 'Run "php artisan queue:work" under a process manager and "* * * * * php artisan schedule:run" in cron.',
                ],
            ],
            'channels' => SalesChannel::query()->withCount(['webhookDeliveries as failed_count' => fn ($query) => $query->where('status', 'failed')])->get(),
        ];
    }
}
