<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\WebhookEvent;
use App\Payments\Gateways\RazorpayGateway;
use App\Payments\Gateways\StripeGateway;
use App\Payments\InvalidWebhookSignature;
use App\Payments\PaymentGateway;
use App\Payments\PaymentResult;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /** @var array<string, class-string<PaymentGateway>> payment_methods.code => driver */
    public const GATEWAYS = [
        'razorpay' => RazorpayGateway::class,
        'stripe' => StripeGateway::class,
    ];

    /** Offline methods: no redirect, staff record the payment manually. */
    public const OFFLINE = ['cod', 'bank_transfer'];

    public function gateway(?string $code): ?PaymentGateway
    {
        $class = self::GATEWAYS[$code] ?? null;

        return $class ? app($class) : null;
    }

    /** Online gateway for this order, if it should be paid by redirect. */
    public function gatewayFor(Order $order): ?PaymentGateway
    {
        $gateway = $this->gateway($order->paymentMethod?->code);

        return $gateway?->isConfigured() ? $gateway : null;
    }

    public function needsOnlinePayment(Order $order): bool
    {
        return ! $order->isPaid()
            && $order->status !== OrderStatus::Cancelled->value
            && $this->gatewayFor($order) !== null;
    }

    /** Creates a hosted checkout and returns the URL to send the customer to. */
    public function startCheckout(Order $order): string
    {
        $gateway = $this->gatewayFor($order);

        if (! $gateway || ! $this->needsOnlinePayment($order)) {
            throw ValidationException::withMessages(['payment' => 'This order does not need an online payment.']);
        }

        $checkout = $gateway->createCheckout(
            $order,
            route('payments.return', ['gateway' => $gateway->code(), 'order' => $order->order_number]),
            route('payments.cancelled', ['order' => $order->order_number]),
        );

        $order->transactions()->create([
            'gateway' => $gateway->code(),
            'type' => 'payment',
            'reference' => $checkout->reference,
            'amount' => $order->total,
            'currency' => $order->currency,
            'status' => 'pending',
            'payload' => $checkout->payload,
        ]);

        return $checkout->redirectUrl;
    }

    /** Customer came back from the gateway. Status is re-checked server-side. */
    public function handleReturn(string $gatewayCode, Order $order, Request $request): ?PaymentResult
    {
        $gateway = $this->gateway($gatewayCode);
        $pending = $order->transactions()->where('gateway', $gatewayCode)->where('type', 'payment')->first();

        if (! $gateway || ! $pending) {
            return null;
        }

        $result = $gateway->verifyReturn($order, $pending, $request);

        if ($result) {
            $this->apply($order, $gatewayCode, $result);
        }

        return $result;
    }

    /**
     * Processes a provider webhook once per event id.
     *
     * @throws InvalidWebhookSignature
     */
    public function handleWebhook(string $gatewayCode, Request $request): void
    {
        $result = $this->gateway($gatewayCode)?->parseWebhook($request);

        if (! $result) {
            return;
        }

        try {
            $event = WebhookEvent::query()->create([
                'provider' => $gatewayCode,
                'event_id' => $result->eventId,
                'type' => $result->eventType,
                'payload' => $result->payload,
            ]);
        } catch (UniqueConstraintViolationException) {
            return; // Already received — providers retry deliveries.
        }

        $order = $result->orderNumber ? Order::query()->where('order_number', $result->orderNumber)->first() : null;

        try {
            if ($order) {
                $this->apply($order, $gatewayCode, $result);
            }
        } catch (\Throwable $e) {
            $event->delete(); // let the provider's retry process it again

            throw $e;
        }

        $event->update(['processed_at' => now()]);
    }

    /**
     * Applies a verified gateway result. Safe to call repeatedly with the same
     * result (return redirect + webhook both arrive).
     */
    public function apply(Order $order, string $gatewayCode, PaymentResult $result): void
    {
        DB::transaction(function () use ($order, $gatewayCode, $result) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            $transaction = $order->transactions()
                ->where('gateway', $gatewayCode)
                ->where('type', 'payment')
                ->when($result->reference, fn ($query) => $query->where('reference', $result->reference))
                ->first();

            if ($result->status === PaymentResult::PENDING) {
                return;
            }

            if ($result->status === PaymentResult::FAILED) {
                $transaction?->update(['status' => 'failed']);

                if ($order->payment_status === PaymentStatus::Pending->value) {
                    $order->update(['payment_status' => PaymentStatus::Failed->value]);
                }

                return;
            }

            if ($order->isPaid() || $order->payment_status === PaymentStatus::Refunded->value) {
                $transaction?->update(['status' => 'succeeded', 'gateway_payment_id' => $result->paymentId ?? $transaction->gateway_payment_id]);

                return;
            }

            $attributes = [
                'status' => 'succeeded',
                'gateway_payment_id' => $result->paymentId,
                'amount' => $result->amount ?? $order->total,
                'payload' => $result->payload,
            ];

            $transaction
                ? $transaction->update($attributes)
                : $order->transactions()->create([...$attributes, 'gateway' => $gatewayCode, 'type' => 'payment', 'reference' => $result->reference, 'currency' => $order->currency]);

            $amountMatches = $result->amount === null || abs($result->amount - (float) $order->total) < 0.01;

            $order->pendingStatusNote = $amountMatches
                ? "Payment received via {$gatewayCode}."
                : "Payment amount mismatch: received {$result->amount}, expected {$order->total}. Review before fulfilling.";

            $order->forceFill([
                'payment_status' => PaymentStatus::Paid->value,
                'paid_at' => now(),
                'status' => match (true) {
                    ! $amountMatches => OrderStatus::OnHold->value,
                    $order->status === OrderStatus::Pending->value => OrderStatus::Confirmed->value,
                    default => $order->status,
                },
            ])->save();
        });
    }

    /** COD collected / bank transfer received — recorded by staff. */
    public function recordManualPayment(Order $order, float $amount, ?string $reference = null, ?string $note = null): PaymentTransaction
    {
        if ($order->isPaid()) {
            throw ValidationException::withMessages(['amount' => 'This order is already marked as paid.']);
        }

        return DB::transaction(function () use ($order, $amount, $reference, $note) {
            $transaction = $order->transactions()->create([
                'gateway' => $order->paymentMethod?->code ?? 'manual',
                'type' => 'payment',
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $order->currency,
                'status' => 'succeeded',
                'reason' => $note,
                'user_id' => auth()->id(),
            ]);

            $order->pendingStatusNote = trim('Payment recorded manually. '.($reference ? "Ref: {$reference}. " : '').($note ?? '')) ?: null;
            $order->forceFill([
                'payment_status' => PaymentStatus::Paid->value,
                'paid_at' => now(),
                'status' => $order->status === OrderStatus::Pending->value ? OrderStatus::Confirmed->value : $order->status,
            ])->save();

            return $transaction;
        });
    }

    /**
     * Refunds through the original gateway when possible, otherwise records a
     * manual refund (e.g. COD refunded by bank transfer).
     */
    public function refund(Order $order, float $amount, string $reason, bool $viaGateway = true): PaymentTransaction
    {
        $amount = round($amount, 2);

        if ($amount <= 0 || $amount > $order->refundableAmount()) {
            throw ValidationException::withMessages(['amount' => 'Refund must be between 0 and '.number_format($order->refundableAmount(), 2).'.']);
        }

        if (! $order->isPaid()) {
            throw ValidationException::withMessages(['amount' => 'Only paid orders can be refunded.']);
        }

        $payment = $order->transactions()->where('type', 'payment')->where('status', 'succeeded')->first();
        $gateway = $viaGateway ? $this->gateway($payment?->gateway) : null;

        $refund = ['reference' => null, 'status' => 'succeeded', 'payload' => null, 'gateway' => 'manual'];

        if ($gateway && $payment) {
            $result = $gateway->refund($payment, $amount, $reason);

            if (! $result->accepted) {
                throw ValidationException::withMessages(['amount' => "Gateway refused the refund: {$result->error}"]);
            }

            $refund = ['reference' => $result->reference, 'status' => $result->status, 'payload' => $result->payload, 'gateway' => $gateway->code()];
        }

        return DB::transaction(function () use ($order, $amount, $reason, $refund) {
            $transaction = $order->transactions()->create([
                ...$refund,
                'type' => 'refund',
                'amount' => $amount,
                'currency' => $order->currency,
                'reason' => $reason,
                'user_id' => auth()->id(),
            ]);

            $refunded = round((float) $order->refunded_amount + $amount, 2);
            $fullyRefunded = $refunded >= (float) $order->total - 0.01;

            $order->pendingStatusNote = "Refunded {$amount}: {$reason}";
            $order->forceFill([
                'refunded_amount' => $refunded,
                'payment_status' => $fullyRefunded ? PaymentStatus::Refunded->value : PaymentStatus::PartiallyRefunded->value,
                'status' => $fullyRefunded && in_array($order->status, [OrderStatus::Delivered->value, OrderStatus::Returned->value, OrderStatus::ReturnRequested->value], true)
                    ? OrderStatus::Refunded->value
                    : $order->status,
            ])->save();

            ActivityLog::record('refund', $order, ['amount' => $amount, 'reason' => $reason, 'gateway' => $refund['gateway']]);

            return $transaction;
        });
    }
}
