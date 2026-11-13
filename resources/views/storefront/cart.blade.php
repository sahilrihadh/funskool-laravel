@extends('layouts.storefront')

@section('title', 'Your Cart')

@section('content')
    <h1 class="font-display text-2xl sm:text-3xl font-bold mb-6 text-ink-900">Your Cart</h1>

    @guest
        <p class="text-ink-900/60">Please <a href="{{ route('login') }}" class="text-brand-600 font-semibold">sign in</a> to view your cart.</p>
    @else
        @if (! $cart || $cart->items->isEmpty())
            <p class="text-ink-900/60">Your cart is empty. <a href="{{ route('home') }}" class="text-brand-600 font-semibold">Continue shopping</a>.</p>
        @else
            <div class="space-y-4">
                @foreach ($cart->items as $item)
                    <div class="flex items-center gap-4 bg-white border border-black/5 rounded-2xl p-4">
                        <div class="w-16 h-16 bg-sky-tint-100 rounded-xl overflow-hidden flex items-center justify-center shrink-0">
                            @if ($item->product->image)
                                <img src="{{ Storage::url($item->product->image) }}" class="w-full h-full object-cover">
                            @endif
                        </div>
                        <div class="flex-1">
                            <a href="{{ route('products.show', $item->product) }}" class="font-semibold text-ink-900 hover:text-brand-600">{{ $item->product->name }}</a>
                            @if ($item->variant)
                                <p class="text-sm text-ink-900/50">{{ $item->variant->label }}</p>
                            @endif
                            <p class="text-sm text-ink-900/50">₹{{ number_format($item->price, 2) }} each</p>
                        </div>

                        <form method="POST" action="{{ route('cart.update', $item) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <input type="number" name="quantity" value="{{ $item->quantity }}" min="0" class="w-16 border border-black/10 rounded-full px-2 py-1 text-sm text-center">
                            <button type="submit" class="text-sm font-semibold text-sky-500 hover:text-sky-500/70">Update</button>
                        </form>

                        <p class="w-24 text-right font-bold text-ink-900">₹{{ number_format($item->total, 2) }}</p>

                        <form method="POST" action="{{ route('cart.destroy', $item) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-semibold text-brand-600 hover:text-brand-700">Remove</button>
                        </form>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex justify-end">
                <div class="w-full md:w-80 bg-white border border-black/5 rounded-2xl p-5">
                    <div class="flex justify-between font-bold text-lg text-ink-900">
                        <span>Subtotal</span>
                        <span>₹{{ number_format($cart->subtotal, 2) }}</span>
                    </div>
                    <a href="{{ route('checkout.index') }}" class="mt-4 block text-center bg-brand-500 hover:bg-brand-600 text-white font-bold px-6 py-2.5 rounded-full transition-colors">
                        Proceed to Checkout
                    </a>
                </div>
            </div>
        @endif
    @endguest
@endsection
