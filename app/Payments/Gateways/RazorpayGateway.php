<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Payments\InvalidWebhookSignature;
use App\Payments\PaymentCheckout;
use App\Payments\PaymentGateway;
use App\Payments\PaymentResult;
use App\Payments\RefundResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Razorpay via Payment Links (hosted page — UPI, cards, netbanking, wallets).
 * Docs: https://razorpay.com/docs/api/payments/payment-links/
 */
class RazorpayGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'razorpay';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'));
    }

    public function createCheckout(Order $order, string $returnUrl, string $cancelUrl): PaymentCheckout
    {
        $response = $this->client()->post('/payment_links', [
            'amount' => (int) round((float) $order->total * 100),
            'currency' => $order->currency ?: 'INR',
            // Razorpay requires a unique reference per link, so retries get a suffix.
            'reference_id' => Str::limit($order->order_number, 34, '').'-'.Str::upper(Str::random(5)),
            'description' => "Order {$order->order_number}",
            'customer' => array_filter([
                'name' => $order->contactName(),
                'email' => $order->contactEmail(),
                'contact' => $order->contactPhone(),
            ]),
            'notify' => ['sms' => false, 'email' => false],
            'reminder_enable' => false,
            'notes' => ['order_number' => $order->order_number],
            'callback_url' => $returnUrl,
            'callback_method' => 'get',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Razorpay: '.($response->json('error.description') ?? 'could not create payment link'));
        }

        return new PaymentCheckout($response->json('short_url'), $response->json('id'), $response->json());
    }

    public function verifyReturn(Order $order, PaymentTransaction $pending, Request $request): ?PaymentResult
    {
        $linkId = (string) $request->query('razorpay_payment_link_id');

        if ($linkId === '' || $linkId !== $pending->reference) {
            return null;
        }

        $payload = implode('|', [
            $linkId,
            $request->query('razorpay_payment_link_reference_id'),
            $request->query('razorpay_payment_link_status'),
            $request->query('razorpay_payment_id'),
        ]);

        $signatureValid = hash_equals(
            hash_hmac('sha256', $payload, (string) config('services.razorpay.key_secret')),
            (string) $request->query('razorpay_signature'),
        );

        // Signature proves the redirect came from Razorpay; the API fetch is the source of truth.
        $link = $this->client()->get("/payment_links/{$linkId}");

        if (! $signatureValid || $link->failed()) {
            return new PaymentResult(PaymentResult::PENDING, $order->order_number, $linkId);
        }

        return $this->resultFromLink($link->json(), $order->order_number);
    }

    public function parseWebhook(Request $request): ?PaymentResult
    {
        $secret = (string) config('services.razorpay.webhook_secret');
        $signature = (string) $request->header('X-Razorpay-Signature');

        if ($secret === '' || ! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            throw new InvalidWebhookSignature('Invalid Razorpay webhook signature.');
        }

        $event = $request->json()->all();
        $type = $event['event'] ?? null;
        $link = data_get($event, 'payload.payment_link.entity');

        if (! $link || ! in_array($type, ['payment_link.paid', 'payment_link.expired', 'payment_link.cancelled'], true)) {
            return null;
        }

        $payment = data_get($event, 'payload.payment.entity', []);

        return new PaymentResult(
            status: $type === 'payment_link.paid' ? PaymentResult::PAID : PaymentResult::FAILED,
            orderNumber: data_get($link, 'notes.order_number'),
            reference: $link['id'] ?? null,
            paymentId: $payment['id'] ?? null,
            amount: isset($link['amount_paid']) ? $link['amount_paid'] / 100 : null,
            payload: $event,
            eventId: $request->header('X-Razorpay-Event-Id') ?: sha1($request->getContent()),
            eventType: $type,
        );
    }

    public function refund(PaymentTransaction $payment, float $amount, ?string $reason = null): RefundResult
    {
        if (! $payment->gateway_payment_id) {
            return new RefundResult(false, null, 'failed', error: 'No Razorpay payment id recorded for this transaction.');
        }

        $response = $this->client(retry: false)->post("/payments/{$payment->gateway_payment_id}/refund", [
            'amount' => (int) round($amount * 100),
            'notes' => array_filter(['reason' => $reason, 'order_number' => $payment->order->order_number]),
        ]);

        if ($response->failed()) {
            return new RefundResult(false, null, 'failed', $response->json() ?? [], $response->json('error.description') ?? 'Refund failed');
        }

        return new RefundResult(true, $response->json('id'), $response->json('status', 'pending'), $response->json());
    }

    protected function resultFromLink(array $link, string $orderNumber): PaymentResult
    {
        $status = match ($link['status'] ?? null) {
            'paid' => PaymentResult::PAID,
            'expired', 'cancelled' => PaymentResult::FAILED,
            default => PaymentResult::PENDING,
        };

        return new PaymentResult(
            status: $status,
            orderNumber: $orderNumber,
            reference: $link['id'] ?? null,
            paymentId: data_get($link, 'payments.0.payment_id'),
            amount: isset($link['amount_paid']) ? $link['amount_paid'] / 100 : null,
            payload: $link,
        );
    }

    /** Refunds are never retried automatically — a timed-out retry could refund twice. */
    protected function client(bool $retry = true): PendingRequest
    {
        return Http::baseUrl(config('services.razorpay.base_url'))
            ->withBasicAuth((string) config('services.razorpay.key_id'), (string) config('services.razorpay.key_secret'))
            ->acceptJson()
            ->timeout(20)
            ->when($retry, fn (PendingRequest $request) => $request->retry(2, 500, throw: false));
    }
}
