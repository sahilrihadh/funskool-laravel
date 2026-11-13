<?php

namespace App\Http\Controllers\Channel;

use App\Http\Resources\BrandResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\Channel\ChannelProductResource;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\ShippingMethodResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class CatalogController extends ChannelController
{
    public function store(Request $request)
    {
        return response()->json(['data' => [
            'channel' => $this->channel($request)->only(['name', 'slug', 'type']),
            'store_name' => Setting::get('store_name'),
            'currency_code' => Setting::get('currency_code', 'INR'),
            'currency_symbol' => Setting::get('currency_symbol', '₹'),
            'tax_percent' => (float) Setting::get('tax_percent', 0),
            'prices_include_tax' => false,
            'return_window_days' => (int) Setting::get('return_window_days', 7),
            'contact' => [
                'email' => Setting::get('store_email'),
                'phone' => Setting::get('store_phone'),
                'address' => Setting::get('store_address'),
            ],
        ]]);
    }

    public function categories()
    {
        return CategoryResource::collection(
            Category::query()->where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    public function brands()
    {
        return BrandResource::collection(Brand::query()->where('is_active', true)->orderBy('name')->get());
    }

    /**
     * Filters: search, category (slug), brand (slug), sku[] , featured,
     * updated_since (ISO date — for incremental sync), include_inactive, per_page (≤100).
     */
    public function products(Request $request)
    {
        $request->validate([
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sku' => ['nullable', 'array'],
        ]);

        $products = Product::query()
            ->unless($request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$request->string('search').'%')
                ->orWhere('sku', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('category'), fn ($query) => $query->whereRelation('category', 'slug', $request->string('category')))
            ->when($request->filled('brand'), fn ($query) => $query->whereRelation('brand', 'slug', $request->string('brand')))
            ->when($request->filled('sku'), fn ($query) => $query->whereIn('sku', (array) $request->input('sku')))
            ->when($request->boolean('featured'), fn ($query) => $query->where('is_featured', true))
            ->when($request->filled('updated_since'), fn ($query) => $query->where('updated_at', '>=', $request->date('updated_since')))
            ->with(['category', 'brand', 'images', 'variants'])
            ->orderBy('id')
            ->paginate($request->integer('per_page', 50));

        return ChannelProductResource::collection($products);
    }

    public function product(string $sku)
    {
        $product = Product::query()
            ->where('sku', $sku)
            ->orWhere('slug', $sku)
            ->with(['category', 'brand', 'images', 'variants'])
            ->firstOrFail();

        return new ChannelProductResource($product);
    }

    /** Lightweight stock lookup: ?sku[]=A&sku[]=B (product or variant SKUs). */
    public function inventory(Request $request)
    {
        $request->validate(['sku' => ['required', 'array', 'max:200'], 'sku.*' => ['string']]);
        $skus = (array) $request->input('sku');

        $products = Product::query()->whereIn('sku', $skus)->get(['sku', 'stock_quantity', 'is_active'])
            ->map(fn ($product) => ['sku' => $product->sku, 'stock_quantity' => (int) $product->stock_quantity, 'in_stock' => $product->is_active && $product->stock_quantity > 0]);

        $variants = ProductVariant::query()->whereIn('sku', $skus)->get(['sku', 'stock_quantity', 'is_active'])
            ->map(fn ($variant) => ['sku' => $variant->sku, 'stock_quantity' => (int) $variant->stock_quantity, 'in_stock' => $variant->is_active && $variant->stock_quantity > 0]);

        return response()->json(['data' => $products->concat($variants)->values()]);
    }

    public function shippingMethods()
    {
        return ShippingMethodResource::collection(
            ShippingMethod::query()->where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    /** Only methods that can actually be used (online gateways need configured keys). */
    public function paymentMethods(PaymentService $payments)
    {
        $methods = PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get()
            ->filter(fn (PaymentMethod $method) => ! isset(PaymentService::GATEWAYS[$method->code]) || $payments->gateway($method->code)->isConfigured());

        return PaymentMethodResource::collection($methods->values());
    }

    public function pages()
    {
        return response()->json(['data' => Page::query()->published()->orderBy('sort_order')->get(['title', 'slug', 'show_in_footer', 'updated_at'])]);
    }

    public function page(string $slug)
    {
        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $page->only(['title', 'slug', 'content', 'meta_title', 'meta_description', 'updated_at'])]);
    }
}
