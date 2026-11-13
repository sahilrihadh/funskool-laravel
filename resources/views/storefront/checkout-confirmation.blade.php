@extends('layouts.storefront')

@section('title', 'Order Confirmed')

@section('content')
    <div class="max-w-xl mx-auto text-center py-12">
        <div class="w-16 h-16 mx-auto rounded-full bg-leaf-400/20 flex items-center justify-center text-3xl mb-4">🎉</div>
        <h1 class="font-display text-2xl font-bold text-ink-900">Thank you for your order!</h1>
        <p class="text-ink-900/60 mt-2">Your order <strong class="text-ink-900">{{ $order->order_number }}</strong> has been placed.</p>

        @if ($order->isPaid())
            <p class="mt-3 inline-block rounded-full bg-leaf-400/20 px-4 py-1 text-sm font-semibold text-cocoa-600">Payment received</p>
        @elseif ($payUrl)
            <div class="mt-6 rounded-2xl border border-sun-400/50 bg-sun-400/10 p-4 text-sm text-ink-900/80">
                @if (session('payment_result') === 'cancelled')
                    Payment was cancelled — your order is saved.
                @elseif (session('payment_result') === 'failed' || session('payment_result') === 'error')
                    The payment didn't go through — your order is saved and you can try again.
                @else
                    Your order is awaiting payment. If you've just paid, it can take a minute to confirm.
                @endif
                <a href="{{ $payUrl }}" class="mt-3 block w-full rounded-full bg-brand-500 px-6 py-2.5 text-center font-bold text-white hover:bg-brand-600">Pay now</a>
            </div>
        @else
            <p class="mt-3 text-sm text-ink-900/60">Payment: {{ $order->paymentMethod?->name ?? '—' }} ({{ \App\Enums\PaymentStatus::labelFor($order->payment_status) }})</p>
        @endif

        <div class="mt-8 bg-white border border-black/5 rounded-2xl p-5 text-left">
            @foreach ($order->items as $item)
                <div class="flex justify-between text-sm py-1 text-ink-900/70">
                    <span>{{ $item->product_name }} × {{ $item->quantity }}</span>
                    <span>₹{{ number_format($item->total, 2) }}</span>
                </div>
            @endforeach
            <div class="flex justify-between font-bold text-ink-900 border-t border-black/5 pt-2 mt-2">
                <span>Total</span>
                <span>₹{{ number_format($order->total, 2) }}</span>
            </div>
        </div>

        <a href="{{ route('orders.show', $order) }}" class="inline-block mt-6 text-brand-600 font-bold">View Order Details</a>
    </div>
@endsection
