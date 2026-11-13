<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    public function index()
    {
        return view('storefront.wishlist', [
            'wishlist' => Auth::user()->wishlist()->with('product')->latest()->get(),
        ]);
    }

    public function store(Product $product)
    {
        Auth::user()->wishlist()->firstOrCreate(['product_id' => $product->id]);

        return back()->with('success', 'Added to wishlist.');
    }

    public function destroy(Product $product)
    {
        Auth::user()->wishlist()->where('product_id', $product->id)->delete();

        return back()->with('success', 'Removed from wishlist.');
    }
}
