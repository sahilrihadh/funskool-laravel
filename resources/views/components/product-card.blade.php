@props(['product'])

<a href="{{ route('products.show', $product) }}" class="group block bg-white rounded-2xl border border-black/5 overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
    <div class="aspect-square bg-sky-tint-100 flex items-center justify-center overflow-hidden relative">
        @if ($product->sale_price)
            <span class="absolute top-2 left-2 z-10 bg-brand-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                -{{ round((1 - $product->sale_price / $product->regular_price) * 100) }}%
            </span>
        @endif
        @if ($product->image)
            <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200">
        @else
            <span class="text-ink-900/20 text-sm">No image</span>
        @endif
    </div>
    <div class="p-4">
        <p class="text-xs font-semibold text-sky-500 uppercase tracking-wide">{{ $product->brand?->name ?? $product->category?->name }}</p>
        <h3 class="font-semibold text-ink-900 mt-1 line-clamp-2 leading-snug">{{ $product->name }}</h3>
        <div class="mt-2 flex items-center gap-2">
            <span class="font-bold text-brand-600">₹{{ number_format($product->price, 2) }}</span>
            @if ($product->sale_price)
                <span class="text-sm text-ink-900/40 line-through">₹{{ number_format($product->regular_price, 2) }}</span>
            @endif
        </div>
    </div>
</a>
