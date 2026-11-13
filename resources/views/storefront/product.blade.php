@extends('layouts.storefront')

@section('title', $product->name)

@section('content')
    <nav class="text-sm text-ink-900/50 mb-6">
        <a href="{{ route('home') }}" class="hover:text-brand-600">Home</a>
        @if ($product->category)
            / <a href="{{ route('categories.show', $product->category) }}" class="hover:text-brand-600">{{ $product->category->name }}</a>
        @endif
        / <span class="text-ink-900">{{ $product->name }}</span>
    </nav>

    <div class="grid md:grid-cols-2 gap-10">
        <div>
            <div class="aspect-square bg-white border border-black/5 rounded-2xl overflow-hidden flex items-center justify-center">
                @if ($product->image)
                    <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                @else
                    <span class="text-ink-900/20">No image</span>
                @endif
            </div>

            @if ($product->images->isNotEmpty())
                <div class="grid grid-cols-4 gap-2 mt-2">
                    @foreach ($product->images as $image)
                        <div class="aspect-square bg-white border border-black/5 rounded-xl overflow-hidden">
                            <img src="{{ Storage::url($image->image_path) }}" class="w-full h-full object-cover">
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            @if ($product->brand)
                <p class="text-xs font-semibold text-sky-500 uppercase tracking-wide">{{ $product->brand->name }}</p>
            @endif
            <h1 class="font-display text-2xl sm:text-3xl font-bold mt-1 text-ink-900">{{ $product->name }}</h1>

            @if ($product->approvedReviews->isNotEmpty())
                <p class="text-sm text-sun-500 mt-2">{{ str_repeat('★', round($product->average_rating)) }}{{ str_repeat('☆', 5 - round($product->average_rating)) }}
                    <span class="text-ink-900/50">({{ $product->approvedReviews->count() }} reviews)</span>
                </p>
            @endif

            <div class="mt-4 flex items-center gap-3">
                <span class="font-display text-2xl sm:text-3xl font-extrabold text-brand-600">₹{{ number_format($product->price, 2) }}</span>
                @if ($product->sale_price)
                    <span class="text-ink-900/40 line-through">₹{{ number_format($product->regular_price, 2) }}</span>
                    <span class="bg-brand-50 text-brand-600 text-xs font-bold px-2 py-1 rounded-full">
                        Save {{ round((1 - $product->sale_price / $product->regular_price) * 100) }}%
                    </span>
                @endif
            </div>

            <p class="text-ink-900/70 mt-4 leading-relaxed">{{ $product->short_description }}</p>

            <p class="mt-4 text-sm font-semibold {{ $product->stock_quantity > 0 ? 'text-leaf-400' : 'text-brand-600' }}">
                {{ $product->stock_quantity > 0 ? '● In stock' : '● Out of stock' }}
            </p>

            <form method="POST" action="{{ route('cart.store') }}" class="mt-6 flex flex-wrap items-center gap-3">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                @if ($product->variants->isNotEmpty())
                    <select name="product_variant_id" class="border border-black/10 rounded-full px-4 py-2.5 text-sm font-medium">
                        @foreach ($product->variants as $variant)
                            <option value="{{ $variant->id }}" @disabled($variant->stock_quantity < 1)>
                                {{ $variant->label ?: $variant->sku }} @if ($variant->stock_quantity < 1) (out of stock) @endif
                            </option>
                        @endforeach
                    </select>
                @endif

                <input type="number" name="quantity" value="1" min="1" class="w-20 border border-black/10 rounded-full px-4 py-2.5 text-sm font-medium text-center">

                <button type="submit" @disabled($product->stock_quantity < 1) class="bg-brand-500 hover:bg-brand-600 disabled:opacity-50 text-white font-bold px-8 py-2.5 rounded-full transition-colors">
                    Add to Cart
                </button>
            </form>

            @auth
                <form method="POST" action="{{ route($isWishlisted ? 'wishlist.destroy' : 'wishlist.store', $product) }}" class="mt-3">
                    @csrf
                    @if ($isWishlisted) @method('DELETE') @endif
                    <button type="submit" class="text-sm font-medium text-ink-900/50 hover:text-bubble-400 transition-colors">
                        {{ $isWishlisted ? '♥ Remove from wishlist' : '♡ Add to wishlist' }}
                    </button>
                </form>
            @endauth

            @if ($product->marketplaceLinks->isNotEmpty())
                <div class="mt-8 border-t border-black/5 pt-6">
                    <h2 class="font-display font-bold text-ink-900 mb-3">Also Available On</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($product->marketplaceLinks as $link)
                            <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer nofollow"
                               class="inline-flex items-center gap-1 border border-black/10 rounded-full px-4 py-1.5 text-sm font-medium hover:border-brand-500 hover:text-brand-600 transition-colors">
                                {{ $link->platform }} ↗
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($product->description)
                <div class="mt-8 border-t border-black/5 pt-6 prose prose-sm max-w-none text-ink-900/70">
                    {!! $product->description !!}
                </div>
            @endif
        </div>
    </div>

    <div class="mt-16 border-t border-black/5 pt-10">
        <h2 class="font-display text-xl font-bold text-ink-900 mb-5">Reviews</h2>

        @forelse ($product->approvedReviews as $review)
            <div class="border-b border-black/5 py-4">
                <p class="text-sun-500 text-sm">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</p>
                @if ($review->title)
                    <p class="font-semibold mt-1 text-ink-900">{{ $review->title }}</p>
                @endif
                <p class="text-ink-900/60 text-sm mt-1">{{ $review->comment }}</p>
                <p class="text-ink-900/40 text-xs mt-1">{{ $review->user->name }} &middot; {{ $review->created_at->format('M j, Y') }}</p>
            </div>
        @empty
            <p class="text-ink-900/50 text-sm">No reviews yet.</p>
        @endforelse

        @auth
            <form method="POST" action="{{ route('reviews.store', $product) }}" class="mt-6 max-w-lg space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-semibold mb-1 text-ink-900">Rating</label>
                    <select name="rating" class="border border-black/10 rounded-lg px-3 py-2 text-sm w-full">
                        @for ($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}">{{ $i }} star{{ $i > 1 ? 's' : '' }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1 text-ink-900">Title</label>
                    <input type="text" name="title" class="border border-black/10 rounded-lg px-3 py-2 text-sm w-full">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1 text-ink-900">Comment</label>
                    <textarea name="comment" rows="3" class="border border-black/10 rounded-lg px-3 py-2 text-sm w-full"></textarea>
                </div>
                <button type="submit" class="bg-ink-900 text-white text-sm font-bold px-6 py-2.5 rounded-full hover:bg-ink-900/80 transition-colors">Submit Review</button>
            </form>
        @else
            <p class="text-sm text-ink-900/50 mt-4"><a href="{{ route('login') }}" class="text-brand-600 font-semibold">Sign in</a> to write a review.</p>
        @endauth
    </div>

    @if ($relatedProducts->isNotEmpty())
        <div class="mt-16 border-t border-black/5 pt-10">
            <h2 class="font-display text-xl font-bold text-ink-900 mb-5">Related Products</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach ($relatedProducts as $related)
                    <x-product-card :product="$related" />
                @endforeach
            </div>
        </div>
    @endif
@endsection
