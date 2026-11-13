<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function __construct(protected CartService $cartService) {}

    public function index()
    {
        if (! Auth::check()) {
            return view('storefront.cart', ['cart' => null]);
        }

        $cart = $this->cartService->getOrCreateCart(Auth::user());
        $cart->load(['items.product', 'items.variant']);

        return view('storefront.cart', ['cart' => $cart]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if (! Auth::check()) {
            return redirect()->route('login')->with('info', 'Please sign in to add items to your cart.');
        }

        $product = Product::findOrFail($data['product_id']);
        $variant = isset($data['product_variant_id']) ? ProductVariant::find($data['product_variant_id']) : null;

        $this->cartService->addItem(Auth::user(), $product, $variant, $data['quantity']);

        return redirect()->route('cart.index')->with('success', 'Added to cart.');
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0']]);

        $this->cartService->updateQuantity(Auth::user(), $cartItem, $data['quantity']);

        return redirect()->route('cart.index');
    }

    public function destroy(CartItem $cartItem)
    {
        $this->cartService->removeItem(Auth::user(), $cartItem);

        return redirect()->route('cart.index');
    }
}
