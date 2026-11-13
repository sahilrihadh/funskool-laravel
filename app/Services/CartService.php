<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function getOrCreateCart(User $user): Cart
    {
        return Cart::query()->firstOrCreate(['user_id' => $user->id]);
    }

    public function addItem(User $user, Product $product, ?ProductVariant $variant, int $quantity): CartItem
    {
        $quantity = max(1, $quantity);
        $availableStock = $variant ? $variant->stock_quantity : $product->stock_quantity;

        if ($availableStock < $quantity) {
            throw ValidationException::withMessages(['quantity' => 'Not enough stock available.']);
        }

        $cart = $this->getOrCreateCart($user);

        $item = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->first();

        $price = $variant ? $variant->effective_price : $product->price;

        if ($item) {
            $item->update([
                'quantity' => $item->quantity + $quantity,
                'price' => $price,
            ]);

            return $item->fresh();
        }

        return $cart->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'quantity' => $quantity,
            'price' => $price,
        ]);
    }

    public function updateQuantity(User $user, CartItem $item, int $quantity): CartItem
    {
        $this->assertOwnership($user, $item);

        if ($quantity < 1) {
            $item->delete();

            return $item;
        }

        $availableStock = $item->variant ? $item->variant->stock_quantity : $item->product->stock_quantity;

        if ($availableStock < $quantity) {
            throw ValidationException::withMessages(['quantity' => 'Not enough stock available.']);
        }

        $item->update(['quantity' => $quantity]);

        return $item->fresh();
    }

    public function removeItem(User $user, CartItem $item): void
    {
        $this->assertOwnership($user, $item);
        $item->delete();
    }

    public function clear(User $user): void
    {
        $this->getOrCreateCart($user)->items()->delete();
    }

    protected function assertOwnership(User $user, CartItem $item): void
    {
        if ($item->cart->user_id !== $user->id) {
            throw ValidationException::withMessages(['item' => 'This cart item does not belong to you.']);
        }
    }
}
