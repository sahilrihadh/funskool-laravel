<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function __invoke(Product $product)
    {
        $product->load(['images', 'variants', 'marketplaceLinks' => fn ($query) => $query->where('is_active', true), 'brand', 'category', 'approvedReviews.user']);

        $relatedProducts = Product::query()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->take(4)
            ->get();

        $isWishlisted = Auth::check()
            ? Auth::user()->wishlist()->where('product_id', $product->id)->exists()
            : false;

        return view('storefront.product', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'isWishlisted' => $isWishlisted,
        ]);
    }
}
