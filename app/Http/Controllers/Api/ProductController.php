<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->where('is_active', true)
            ->when($request->filled('category'), fn ($query) => $query->whereRelation('category', 'slug', $request->string('category')))
            ->when($request->filled('featured'), fn ($query) => $query->where('is_featured', true))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->with(['category', 'brand'])
            ->withCount('approvedReviews')
            ->paginate($request->integer('per_page', 12));

        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        $product->load([
            'category',
            'brand',
            'images',
            'variants' => fn ($query) => $query->where('is_active', true),
            'marketplaceLinks' => fn ($query) => $query->where('is_active', true),
            'approvedReviews.user',
        ]);

        return new ProductResource($product);
    }
}
