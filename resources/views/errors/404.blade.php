@extends('layouts.storefront')

@section('title', 'Page Not Found')

@section('content')
    <div class="max-w-lg mx-auto text-center py-16">
        <p class="font-display text-6xl font-bold text-brand-500">404</p>
        <h1 class="font-display text-2xl font-bold text-ink-900 mt-2">We couldn't find that page</h1>
        <p class="text-ink-900/60 mt-2">It may have moved, or the product is no longer available.</p>
        <div class="mt-6 flex flex-wrap justify-center gap-3">
            <a href="{{ route('home') }}" class="rounded-full bg-brand-500 px-6 py-2.5 font-bold text-white hover:bg-brand-600">Go to homepage</a>
            <a href="{{ route('orders.index') }}" class="rounded-full border border-black/10 px-6 py-2.5 font-bold text-ink-900">My orders</a>
        </div>
    </div>
@endsection
