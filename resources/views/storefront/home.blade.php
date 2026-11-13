@extends('layouts.storefront')

@section('title', 'Home')

@section('content')
    <section class="relative overflow-hidden rounded-3xl bg-linear-to-br from-brand-500 via-brand-600 to-brand-700 mb-4">
        <div class="absolute -top-10 -right-10 w-56 h-56 rounded-full bg-sun-400/30"></div>
        <div class="absolute bottom-0 left-1/3 w-32 h-32 rounded-full bg-leaf-400/20"></div>
        <div class="absolute top-1/2 right-1/4 w-16 h-16 rounded-full bg-white/10"></div>
        <div class="absolute bottom-10 right-10 w-10 h-10 rounded-full bg-bubble-300/40"></div>

        <div class="relative px-6 py-14 sm:px-12 sm:py-20 max-w-xl">
            <span class="inline-block bg-white/15 text-white text-xs font-bold tracking-wide uppercase px-3 py-1 rounded-full mb-4">38+ Years of Play</span>
            <h1 class="font-display text-3xl sm:text-5xl font-extrabold text-white leading-tight mb-4">
                Toys that spark imagination
            </h1>
            <p class="text-white/85 text-base sm:text-lg mb-8">
                From building blocks to board games, discover toys the whole family will love — trusted by parents across India.
            </p>
            @php $firstCategory = $categories->first(); @endphp
            <a href="{{ $firstCategory ? route('categories.show', $firstCategory) : '#featured' }}"
               class="inline-flex items-center gap-2 bg-white text-brand-600 font-bold px-6 py-3 rounded-full hover:bg-sun-300 hover:text-ink-900 transition-colors">
                Shop Now →
            </a>
        </div>
    </section>

    <section class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-14">
        @foreach ([['38+', 'Years Experience'], ['30+', 'Countries'], ['4000+', 'Retail Outlets'], ['3', 'Manufacturing Facilities']] as [$stat, $label])
            <div class="bg-white rounded-2xl border border-black/5 py-5 px-3 text-center">
                <p class="font-display text-2xl font-extrabold text-brand-600">{{ $stat }}</p>
                <p class="text-xs text-ink-900/60 font-medium mt-1">{{ $label }}</p>
            </div>
        @endforeach
    </section>

    @if ($banners->isNotEmpty())
        <section class="mb-14 grid gap-4 {{ $banners->count() > 1 ? 'md:grid-cols-2' : '' }}">
            @foreach ($banners as $banner)
                <a href="{{ $banner->link ?? '#' }}" class="block rounded-2xl overflow-hidden bg-sky-tint-300 relative h-48 md:h-64">
                    @if ($banner->image && Storage::disk('public')->exists($banner->image))
                        <img src="{{ Storage::url($banner->image) }}" alt="{{ $banner->title }}" class="w-full h-full object-cover">
                    @endif
                    <div class="absolute inset-0 flex flex-col justify-center p-6 bg-black/20">
                        <h2 class="text-white text-2xl font-bold">{{ $banner->title }}</h2>
                        @if ($banner->subtitle)
                            <p class="text-white/90 mt-1">{{ $banner->subtitle }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </section>
    @endif

    @if ($categories->isNotEmpty())
        <section class="mb-14">
            <div class="flex items-baseline justify-between mb-5">
                <h2 class="font-display text-xl font-bold text-ink-900">Shop by Category</h2>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @php $accents = ['bg-sun-300/40 text-sun-500', 'bg-leaf-400/30 text-leaf-400', 'bg-sky-tint-300/60 text-sky-500', 'bg-bubble-300/30 text-bubble-400']; @endphp
                @foreach ($categories as $i => $category)
                    <a href="{{ route('categories.show', $category) }}" class="rounded-2xl border border-black/5 bg-white p-6 text-center hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
                        <div class="w-12 h-12 mx-auto rounded-full flex items-center justify-center font-display font-bold text-lg mb-3 {{ $accents[$i % count($accents)] }}">
                            {{ Str::substr($category->name, 0, 1) }}
                        </div>
                        <span class="font-semibold text-sm text-ink-900">{{ $category->name }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($featuredProducts->isNotEmpty())
        <section>
            <div class="flex items-baseline justify-between mb-5">
                <h2 class="font-display text-xl font-bold text-ink-900">Best Sellers</h2>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach ($featuredProducts as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif
@endsection
