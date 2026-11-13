@extends('layouts.storefront')

@section('title', 'Checkout')

@section('content')
    <h1 class="font-display text-2xl sm:text-3xl font-bold mb-6 text-ink-900">Checkout</h1>

    @if ($cart->items->isEmpty())
        <p class="text-ink-900/60">Your cart is empty. <a href="{{ route('home') }}" class="text-brand-600 font-semibold">Continue shopping</a>.</p>
    @else
        <form method="POST" action="{{ route('checkout.store') }}" class="grid md:grid-cols-3 gap-8" onsubmit="this.querySelector('[type=submit]').disabled = true">
            @csrf
            <input type="hidden" name="checkout_token" value="{{ old('checkout_token', (string) \Illuminate\Support\Str::uuid()) }}">

            <div class="md:col-span-2 space-y-6">
                <div class="bg-white border border-black/5 rounded-2xl p-5">
                    <h2 class="font-display font-bold text-ink-900 mb-4">Shipping Address</h2>

                    @if ($addresses->isNotEmpty())
                        <div class="mb-4 space-y-2">
                            @foreach ($addresses as $address)
                                <label class="flex items-start gap-2 text-sm border border-black/10 rounded-xl p-3 cursor-pointer hover:border-brand-300">
                                    <input type="radio" name="saved_address" value="{{ $address->id }}" class="accent-brand-500 mt-0.5"
                                           data-full-name="{{ $address->full_name }}" data-phone="{{ $address->phone }}"
                                           data-line1="{{ $address->line1 }}" data-line2="{{ $address->line2 }}"
                                           data-city="{{ $address->city }}" data-state="{{ $address->state }}"
                                           data-postal-code="{{ $address->postal_code }}" data-country="{{ $address->country }}"
                                           onclick="fillAddress(this)">
                                    <span class="text-ink-900/80">{{ $address->full_name }}, {{ $address->line1 }}, {{ $address->city }}, {{ $address->state }} {{ $address->postal_code }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" name="full_name" placeholder="Full Name" required value="{{ old('full_name') }}" class="border border-black/10 rounded-lg px-3 py-2 text-sm col-span-2">
                        <input type="text" name="phone" placeholder="Phone" required value="{{ old('phone') }}" class="border border-black/10 rounded-lg px-3 py-2 text-sm col-span-2">
                        <input type="text" name="line1" placeholder="Address Line 1" required value="{{ old('line1') }}" class="border border-black/10 rounded-lg px-3 py-2 text-sm col-span-2">
                        <input type="text" name="line2" placeholder="Address Line 2" value="{{ old('line2') }}" class="border border-black/10 rounded-lg px-3 py-2 text-sm col-span-2">
                        <input type="text" name="city" placeholder="City" required value="{{ old('city') }}" class="border border-black/10 rounded-lg px-3 py-2 text-sm">
                        <input type="text" name="state" placeholder="State" required value="{{ old('state') }}" class="border border-black/10 rounded-lg px-3 py-2 text-sm">
                        <input type="text" name="postal_code" placeholder="Postal Code" required value="{{ old('postal_code') }}" class="border border-black/10 rounded-lg px-3 py-2 text-sm">
                        <input type="text" name="country" placeholder="Country" required value="{{ old('country', 'India') }}" class="border border-black/10 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                <div class="bg-white border border-black/5 rounded-2xl p-5">
                    <h2 class="font-display font-bold text-ink-900 mb-4">Shipping Method</h2>
                    <div class="space-y-2">
                        @foreach ($shippingMethods as $method)
                            <label class="flex items-center justify-between border border-black/10 rounded-xl p-3 text-sm cursor-pointer hover:border-brand-300">
                                <span class="text-ink-900/80">
                                    <input type="radio" name="shipping_method_id" value="{{ $method->id }}" required class="accent-brand-500">
                                    {{ $method->name }}
                                </span>
                                <span class="font-semibold text-ink-900">₹{{ number_format($method->cost, 2) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="bg-white border border-black/5 rounded-2xl p-5">
                    <h2 class="font-display font-bold text-ink-900 mb-4">Payment Method</h2>
                    <div class="space-y-2">
                        @foreach ($paymentMethods as $method)
                            <label class="flex flex-col border border-black/10 rounded-xl p-3 text-sm cursor-pointer hover:border-brand-300">
                                <span class="text-ink-900/80"><input type="radio" name="payment_method_id" value="{{ $method->id }}" required class="accent-brand-500"> {{ $method->name }}</span>
                                @if ($method->instructions)
                                    <span class="text-ink-900/50 text-xs mt-1 ml-5">{{ $method->instructions }}</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="bg-white border border-black/5 rounded-2xl p-5 h-fit">
                <h2 class="font-display font-bold text-ink-900 mb-4">Order Summary</h2>
                <div class="space-y-2 text-sm mb-4">
                    @foreach ($cart->items as $item)
                        <div class="flex justify-between text-ink-900/70">
                            <span>{{ $item->product->name }} × {{ $item->quantity }}</span>
                            <span>₹{{ number_format($item->total, 2) }}</span>
                        </div>
                    @endforeach
                </div>

                <input type="text" name="coupon_code" placeholder="Coupon code" class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm mb-4">

                <div class="flex justify-between font-bold text-ink-900 border-t border-black/5 pt-3">
                    <span>Subtotal</span>
                    <span>₹{{ number_format($cart->subtotal, 2) }}</span>
                </div>

                <button type="submit" class="mt-4 w-full bg-brand-500 hover:bg-brand-600 text-white font-bold px-6 py-2.5 rounded-full transition-colors">
                    Place Order
                </button>
            </div>
        </form>

        <script>
            function fillAddress(input) {
                const form = input.closest('form');
                form.querySelector('[name=full_name]').value = input.dataset.fullName || '';
                form.querySelector('[name=phone]').value = input.dataset.phone || '';
                form.querySelector('[name=line1]').value = input.dataset.line1 || '';
                form.querySelector('[name=line2]').value = input.dataset.line2 || '';
                form.querySelector('[name=city]').value = input.dataset.city || '';
                form.querySelector('[name=state]').value = input.dataset.state || '';
                form.querySelector('[name=postal_code]').value = input.dataset.postalCode || '';
                form.querySelector('[name=country]').value = input.dataset.country || '';
            }
        </script>
    @endif
@endsection
