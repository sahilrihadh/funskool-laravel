@extends('layouts.storefront')

@section('title', $category->name)

@section('content')
    <nav class="text-sm text-ink-900/50 mb-4">
        <a href="{{ route('home') }}" class="hover:text-brand-600">Home</a> / <span class="text-ink-900">{{ $category->name }}</span>
    </nav>

    <h1 class="font-display text-2xl sm:text-3xl font-bold mb-2 text-ink-900">{{ $category->name }}</h1>
    @if ($category->description)
        <p class="text-ink-900/60 mb-8">{{ $category->description }}</p>
    @endif

    @if ($products->isEmpty())
        <p class="text-ink-900/50">No products in this category yet.</p>
    @else
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>

        <div class="mt-8">
            {{ $products->links() }}
        </div>
    @endif
@endsection
