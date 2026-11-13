@extends('layouts.storefront')

@section('title', 'Wishlist')

@section('content')
    <h1 class="font-display text-2xl sm:text-3xl font-bold mb-6 text-ink-900">Wishlist</h1>

    @if ($wishlist->isEmpty())
        <p class="text-ink-900/60">Your wishlist is empty.</p>
    @else
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ($wishlist as $entry)
                <x-product-card :product="$entry->product" />
            @endforeach
        </div>
    @endif
@endsection
