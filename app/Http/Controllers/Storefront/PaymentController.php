<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Hosted-checkout redirects. Works for storefront orders and for orders
 * placed by connected websites (which get a signed "payment_url").
 */
class PaymentController extends Controller
{
    public function __construct(protected PaymentService $payments) {}

    public static function startUrl(Order $order): string
    {
        return URL::temporarySignedRoute('payments.start', now()->addDays(2), ['order' => $order->order_number]);
    }

    /** Signed link → gateway's hosted page. */
    public function start(Order $order)
    {
        if (! $this->payments->needsOnlinePayment($order)) {
            return $this->finish($order, $order->isPaid() ? 'paid' : 'not_required');
        }

        try {
            return redirect()->away($this->payments->startCheckout($order));
        } catch (Throwable $e) {
            Log::error("Payment start failed for {$order->order_number}: {$e->getMessage()}");

            return $this->finish($order, 'error');
        }
    }

    /** Gateway redirected the customer back — verify server-side before trusting it. */
    public function return(Request $request, string $gateway, Order $order)
    {
        $result = $this->payments->handleReturn($gateway, $order, $request);
        $order->refresh();

        return $this->finish($order, $order->isPaid() ? 'paid' : ($result?->status ?? 'pending'));
    }

    public function cancelled(Order $order)
    {
        return $this->finish($order, 'cancelled');
    }

    protected function finish(Order $order, string $status)
    {
        if ($order->return_url) {
            $separator = str_contains($order->return_url, '?') ? '&' : '?';

            return redirect()->away($order->return_url.$separator.http_build_query([
                'order' => $order->order_number,
                'payment_status' => $status,
            ]));
        }

        if (Auth::id() === $order->user_id) {
            return redirect()->route('checkout.confirmation', $order)->with('payment_result', $status);
        }

        return view('storefront.payment-status', [
            'order' => $order,
            'status' => $status,
            'retryUrl' => $this->payments->needsOnlinePayment($order) ? self::startUrl($order) : null,
        ]);
    }
}
