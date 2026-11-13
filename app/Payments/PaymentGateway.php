<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

/**
 * A hosted-checkout payment provider. Card data never touches this app —
 * customers are redirected to the provider's page, and payment is only
 * trusted after a server-side status check or a signature-verified webhook.
 */
interface PaymentGateway
{
    public function code(): string;

    public function isConfigured(): bool;

    public function createCheckout(Order $order, string $returnUrl, string $cancelUrl): PaymentCheckout;

    /** Server-side verification when the customer is redirected back. */
    public function verifyReturn(Order $order, PaymentTransaction $pending, Request $request): ?PaymentResult;

    /**
     * Returns null for events this app doesn't act on.
     *
     * @throws InvalidWebhookSignature
     */
    public function parseWebhook(Request $request): ?PaymentResult;

    public function refund(PaymentTransaction $payment, float $amount, ?string $reason = null): RefundResult;
}
