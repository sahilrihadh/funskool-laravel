<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $products = $request->user()->wishlist()->with('product')->latest()->get()->pluck('product');

        return ProductResource::collection($products);
    }

    public function store(Request $request, Product $product)
    {
        $request->user()->wishlist()->firstOrCreate(['product_id' => $product->id]);

        return response()->json(['message' => 'Added to wishlist.']);
    }

    public function destroy(Request $request, Product $product)
    {
        $request->user()->wishlist()->where('product_id', $product->id)->delete();

        return response()->json(['message' => 'Removed from wishlist.']);
    }
}
