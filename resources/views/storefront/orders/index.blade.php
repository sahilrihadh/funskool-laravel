@extends('layouts.storefront')

@section('title', 'My Orders')

@section('content')
    <h1 class="font-display text-2xl sm:text-3xl font-bold mb-6 text-ink-900">My Orders</h1>

    @if ($orders->isEmpty())
        <p class="text-ink-900/60">You haven't placed any orders yet.</p>
    @else
        <div class="bg-white border border-black/5 rounded-2xl divide-y divide-black/5 overflow-hidden">
            @foreach ($orders as $order)
                <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between p-4 hover:bg-sky-tint-100 transition-colors">
                    <div>
                        <p class="font-semibold text-ink-900">{{ $order->order_number }}</p>
                        <p class="text-sm text-ink-900/50">{{ $order->created_at->format('M j, Y') }}</p>
                    </div>
                    <span class="text-xs font-semibold px-3 py-1 rounded-full bg-sky-tint-100 text-sky-500 capitalize">{{ $order->status }}</span>
                    <span class="font-bold text-ink-900">₹{{ number_format($order->total, 2) }}</span>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
@endsection
