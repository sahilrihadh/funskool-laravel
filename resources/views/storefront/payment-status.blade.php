@extends('layouts.storefront')

@section('title', 'Payment Status')

@section('content')
    <div class="max-w-md mx-auto text-center py-12">
        <h1 class="font-display text-2xl font-bold text-ink-900">
            @switch($status)
                @case('paid') Payment received @break
                @case('cancelled') Payment cancelled @break
                @case('failed') @case('error') Payment didn't go through @break
                @default Payment pending
            @endswitch
        </h1>
        <p class="text-ink-900/60 mt-2">Order <strong class="text-ink-900">{{ $order->order_number }}</strong> · ₹{{ number_format($order->total, 2) }}</p>
        @if ($status === 'pending')
            <p class="text-sm text-ink-900/50 mt-2">If you completed the payment, it may take a minute to confirm.</p>
        @endif
        @if ($retryUrl)
            <a href="{{ $retryUrl }}" class="inline-block mt-6 rounded-full bg-brand-500 px-6 py-2.5 font-bold text-white hover:bg-brand-600">Try payment again</a>
        @endif
    </div>
@endsection
