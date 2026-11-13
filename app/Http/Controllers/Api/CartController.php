<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(protected CartService $cartService) {}

    public function index(Request $request)
    {
        $cart = $this->cartService->getOrCreateCart($request->user());
        $cart->load(['items.product', 'items.variant']);

        return new CartResource($cart);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $variant = isset($data['product_variant_id']) ? ProductVariant::find($data['product_variant_id']) : null;

        $this->cartService->addItem($request->user(), $product, $variant, $data['quantity']);

        $cart = $this->cartService->getOrCreateCart($request->user());
        $cart->load(['items.product', 'items.variant']);

        return new CartResource($cart);
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0']]);

        $this->cartService->updateQuantity($request->user(), $cartItem, $data['quantity']);

        $cart = $this->cartService->getOrCreateCart($request->user());
        $cart->load(['items.product', 'items.variant']);

        return new CartResource($cart);
    }

    public function destroy(Request $request, CartItem $cartItem)
    {
        $this->cartService->removeItem($request->user(), $cartItem);

        return response()->json(['message' => 'Removed from cart.']);
    }
}
