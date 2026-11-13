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
use RuntimeException;

/**
 * Stripe Checkout (hosted page) for international cards / wallets.
 * Docs: https://docs.stripe.com/api/checkout/sessions
 */
class StripeGateway implements PaymentGateway
{
    /** Seconds a webhook timestamp may drift before it is rejected (replay protection). */
    protected const SIGNATURE_TOLERANCE = 300;

    public function code(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    public function createCheckout(Order $order, string $returnUrl, string $cancelUrl): PaymentCheckout
    {
        $separator = str_contains($returnUrl, '?') ? '&' : '?';

        $response = $this->client()->post('/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => $order->order_number,
            'customer_email' => $order->contactEmail(),
            'success_url' => $returnUrl.$separator.'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => ['order_number' => $order->order_number],
            'payment_intent_data' => ['metadata' => ['order_number' => $order->order_number]],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($order->currency ?: 'INR'),
                    'unit_amount' => (int) round((float) $order->total * 100),
                    'product_data' => ['name' => "Order {$order->order_number}"],
                ],
            ]],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Stripe: '.($response->json('error.message') ?? 'could not create checkout session'));
        }

        return new PaymentCheckout($response->json('url'), $response->json('id'), $response->json());
    }

    public function verifyReturn(Order $order, PaymentTransaction $pending, Request $request): ?PaymentResult
    {
        $sessionId = (string) $request->query('session_id');

        if ($sessionId === '' || $sessionId !== $pending->reference) {
            return null;
        }

        $session = $this->client()->get("/checkout/sessions/{$sessionId}");

        if ($session->failed()) {
            return new PaymentResult(PaymentResult::PENDING, $order->order_number, $sessionId);
        }

        return $this->resultFromSession($session->json());
    }

    public function parseWebhook(Request $request): ?PaymentResult
    {
        if (! $this->hasValidSignature($request)) {
            throw new InvalidWebhookSignature('Invalid Stripe webhook signature.');
        }

        $event = $request->json()->all();
        $type = $event['type'] ?? null;
        $session = data_get($event, 'data.object', []);

        $status = match ($type) {
            'checkout.session.completed' => ($session['payment_status'] ?? null) === 'paid' ? PaymentResult::PAID : PaymentResult::PENDING,
            'checkout.session.async_payment_succeeded' => PaymentResult::PAID,
            'checkout.session.async_payment_failed', 'checkout.session.expired' => PaymentResult::FAILED,
            default => null,
        };

        if ($status === null) {
            return null;
        }

        $result = $this->resultFromSession($session);

        return new PaymentResult(
            status: $status,
            orderNumber: $result->orderNumber,
            reference: $result->reference,
            paymentId: $result->paymentId,
            amount: $result->amount,
            payload: $event,
            eventId: $event['id'] ?? sha1($request->getContent()),
            eventType: $type,
        );
    }

    public function refund(PaymentTransaction $payment, float $amount, ?string $reason = null): RefundResult
    {
        if (! $payment->gateway_payment_id) {
            return new RefundResult(false, null, 'failed', error: 'No Stripe payment intent recorded for this transaction.');
        }

        $response = $this->client(retry: false)->post('/refunds', [
            'payment_intent' => $payment->gateway_payment_id,
            'amount' => (int) round($amount * 100),
            'metadata' => array_filter(['reason' => $reason, 'order_number' => $payment->order->order_number]),
        ]);

        if ($response->failed()) {
            return new RefundResult(false, null, 'failed', $response->json() ?? [], $response->json('error.message') ?? 'Refund failed');
        }

        return new RefundResult(true, $response->json('id'), $response->json('status', 'pending'), $response->json());
    }

    protected function hasValidSignature(Request $request): bool
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $header = (string) $request->header('Stripe-Signature');

        if ($secret === '' || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if (! $timestamp || abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, (string) $signature)) {
                return true;
            }
        }

        return false;
    }

    protected function resultFromSession(array $session): PaymentResult
    {
        $status = match (true) {
            ($session['payment_status'] ?? null) === 'paid' => PaymentResult::PAID,
            ($session['status'] ?? null) === 'expired' => PaymentResult::FAILED,
            default => PaymentResult::PENDING,
        };

        return new PaymentResult(
            status: $status,
            orderNumber: data_get($session, 'metadata.order_number') ?? ($session['client_reference_id'] ?? null),
            reference: $session['id'] ?? null,
            paymentId: is_array($session['payment_intent'] ?? null) ? $session['payment_intent']['id'] : ($session['payment_intent'] ?? null),
            amount: isset($session['amount_total']) ? $session['amount_total'] / 100 : null,
            payload: $session,
        );
    }

    /** Refunds are never retried automatically — a timed-out retry could refund twice. */
    protected function client(bool $retry = true): PendingRequest
    {
        return Http::baseUrl(config('services.stripe.base_url'))
            ->withToken((string) config('services.stripe.secret'))
            ->asForm()
            ->acceptJson()
            ->timeout(20)
            ->when($retry, fn (PendingRequest $request) => $request->retry(2, 500, throw: false));
    }
}
