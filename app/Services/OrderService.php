<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        protected CartService $cartService,
        protected InventoryService $inventory,
    ) {}

    /**
     * Storefront / customer-API checkout from the signed-in user's cart.
     *
     * @param  array{shipping_address: array, billing_address?: array|null, shipping_method_id: int, payment_method_id: int, coupon_code?: string|null, customer_note?: string|null, idempotency_key?: string|null}  $data
     */
    public function checkout(User $user, array $data): Order
    {
        $cart = $this->cartService->getOrCreateCart($user);
        $cart->loadMissing(['items.product', 'items.variant']);

        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        // Prices are re-read from the catalogue; if any changed since the item
        // was added, refresh the cart and ask the customer to review it first.
        $changed = $cart->items->filter(function ($item) {
            $current = $this->currentPrice($item->product, $item->variant);

            if (abs($current - (float) $item->price) < 0.005) {
                return false;
            }

            $item->update(['price' => $current]);

            return true;
        });

        if ($changed->isNotEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Prices changed for '.$changed->map(fn ($item) => $item->product->name)->implode(', ').'. Please review your cart and place the order again.',
            ]);
        }

        $lines = $cart->items->map(fn ($item) => [
            'product' => $item->product,
            'variant' => $item->variant,
            'quantity' => $item->quantity,
        ])->all();

        $order = $this->placeOrder($lines, [
            ...$data,
            'user' => $user,
            'idempotency_key' => isset($data['idempotency_key']) ? "user:{$user->id}:{$data['idempotency_key']}" : null,
        ]);

        $cart->items()->delete();

        return $order;
    }

    /**
     * Server-side pricing for a set of lines. Never trusts client totals.
     *
     * @param  array<int, array{product: Product, variant?: ?ProductVariant, quantity: int}>  $lines
     * @return array{lines: Collection, subtotal: float, discount: float, shipping: float, tax: float, total: float, coupon: ?Coupon, shipping_method: ?ShippingMethod}
     */
    public function quote(array $lines, ?string $couponCode = null, ?int $shippingMethodId = null, ?User $user = null, ?string $email = null): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'At least one item is required.']);
        }

        $resolved = collect($lines)->map(function (array $line) {
            $product = $line['product'];
            $variant = $line['variant'] ?? null;
            $quantity = (int) $line['quantity'];

            if (! $product->is_active || ($variant && ! $variant->is_active)) {
                throw ValidationException::withMessages(['items' => "{$product->name} is no longer available."]);
            }

            if ($variant && $variant->product_id !== $product->id) {
                throw ValidationException::withMessages(['items' => "Invalid variant for {$product->name}."]);
            }

            if ($quantity < 1) {
                throw ValidationException::withMessages(['items' => 'Quantity must be at least 1.']);
            }

            if ($this->inventory->available($product, $variant) < $quantity) {
                throw ValidationException::withMessages(['stock' => "Not enough stock for {$product->name}."]);
            }

            $price = $this->currentPrice($product, $variant);

            return [
                'product' => $product,
                'variant' => $variant,
                'quantity' => $quantity,
                'price' => $price,
                'total' => round($price * $quantity, 2),
            ];
        });

        $subtotal = round((float) $resolved->sum('total'), 2);

        $coupon = null;
        $discount = 0.0;

        if (filled($couponCode)) {
            $coupon = Coupon::query()->where('code', strtoupper(trim($couponCode)))->first();

            if (! $coupon || ! $coupon->isValidFor($subtotal)) {
                throw ValidationException::withMessages(['coupon_code' => 'This coupon is not valid, has expired, or the order is below its minimum amount.']);
            }

            if (! $coupon->isAvailableTo($user, $email)) {
                throw ValidationException::withMessages(['coupon_code' => 'This coupon has already been used the maximum number of times for this customer.']);
            }

            $discount = round($coupon->calculateDiscount($subtotal), 2);
        }

        $shippingMethod = $shippingMethodId
            ? ShippingMethod::query()->where('is_active', true)->find($shippingMethodId)
            : null;

        if ($shippingMethodId && ! $shippingMethod) {
            throw ValidationException::withMessages(['shipping_method_id' => 'This shipping method is not available.']);
        }

        $shipping = round($shippingMethod?->costFor($subtotal - $discount) ?? 0.0, 2);
        $taxPercent = (float) Setting::get('tax_percent', 0);
        $tax = round(($subtotal - $discount) * ($taxPercent / 100), 2);

        return [
            'lines' => $resolved,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'tax' => $tax,
            'total' => round($subtotal - $discount + $shipping + $tax, 2),
            'coupon' => $coupon,
            'shipping_method' => $shippingMethod,
        ];
    }

    /**
     * Creates an order from explicit lines. Used by cart checkout, admin manual
     * orders and the Channel API.
     *
     * @param  array<int, array{product: Product, variant?: ?ProductVariant, quantity: int}>  $lines
     * @param  array{user: User, sales_channel?: ?SalesChannel, shipping_method_id?: ?int, payment_method_id?: ?int, coupon_code?: ?string, shipping_address: array, billing_address?: ?array, customer_note?: ?string, admin_note?: ?string, idempotency_key?: ?string, external_reference?: ?string, return_url?: ?string, customer?: array, payment_status?: string, status?: string}  $data
     */
    public function placeOrder(array $lines, array $data): Order
    {
        if (! empty($data['idempotency_key']) && $existing = Order::query()->where('idempotency_key', $data['idempotency_key'])->first()) {
            return $existing->load('items');
        }

        /** @var User $user */
        $user = $data['user'];
        $customer = $data['customer'] ?? [];
        $email = $customer['email'] ?? $user->email;

        $quote = $this->quote($lines, $data['coupon_code'] ?? null, $data['shipping_method_id'] ?? null, $user, $email);

        $paymentMethod = ! empty($data['payment_method_id'])
            ? PaymentMethod::query()->where('is_active', true)->find($data['payment_method_id'])
            : null;

        if (! empty($data['payment_method_id']) && ! $paymentMethod) {
            throw ValidationException::withMessages(['payment_method_id' => 'This payment method is not available.']);
        }

        try {
            $order = DB::transaction(function () use ($quote, $data, $user, $customer, $paymentMethod) {
                $order = Order::query()->create([
                    'order_number' => $this->generateOrderNumber(),
                    'user_id' => $user->id,
                    'sales_channel_id' => ($data['sales_channel'] ?? null)?->id,
                    'external_reference' => $data['external_reference'] ?? null,
                    'idempotency_key' => $data['idempotency_key'] ?? null,
                    'customer_name' => $customer['name'] ?? $data['shipping_address']['full_name'] ?? $user->name,
                    'customer_email' => $customer['email'] ?? $user->email,
                    'customer_phone' => $customer['phone'] ?? $data['shipping_address']['phone'] ?? $user->phone,
                    'currency' => Setting::get('currency_code', 'INR'),
                    'status' => $data['status'] ?? OrderStatus::Pending->value,
                    'payment_status' => $data['payment_status'] ?? PaymentStatus::Pending->value,
                    'payment_method_id' => $paymentMethod?->id,
                    'shipping_method_id' => $quote['shipping_method']?->id,
                    'coupon_id' => $quote['coupon']?->id,
                    'subtotal' => $quote['subtotal'],
                    'discount_amount' => $quote['discount'],
                    'shipping_amount' => $quote['shipping'],
                    'tax_amount' => $quote['tax'],
                    'total' => $quote['total'],
                    'shipping_address' => $data['shipping_address'],
                    'billing_address' => $data['billing_address'] ?? $data['shipping_address'],
                    'customer_note' => $data['customer_note'] ?? null,
                    'admin_note' => $data['admin_note'] ?? null,
                    'return_url' => $data['return_url'] ?? null,
                    'paid_at' => ($data['payment_status'] ?? null) === PaymentStatus::Paid->value ? now() : null,
                ]);

                foreach ($quote['lines'] as $line) {
                    $order->items()->create([
                        'product_id' => $line['product']->id,
                        'product_variant_id' => $line['variant']?->id,
                        'product_name' => $line['product']->name,
                        'sku' => $line['variant']->sku ?? $line['product']->sku,
                        'variant_label' => $line['variant']?->label,
                        'price' => $line['price'],
                        'quantity' => $line['quantity'],
                        'total' => $line['total'],
                    ]);

                    $this->inventory->deduct($line['product'], $line['variant'], $line['quantity'], 'order', $order);
                }

                $quote['coupon']?->increment('used_count');

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent duplicate submit with the same idempotency key won the race.
            if (! empty($data['idempotency_key']) && $existing = Order::query()->where('idempotency_key', $data['idempotency_key'])->first()) {
                return $existing->load('items');
            }

            throw $e;
        }

        OrderPlaced::dispatch($order);

        return $order->load('items');
    }

    /**
     * @throws ValidationException when the transition is not allowed
     */
    public function updateStatus(Order $order, string $status, ?string $note = null, bool $force = false): Order
    {
        $target = OrderStatus::from($status);
        $current = $order->statusEnum();

        if ($current === $target) {
            if ($note) {
                $order->statusHistories()->create(['status' => $order->status, 'note' => $note, 'user_id' => auth()->id()]);
            }

            return $order;
        }

        if (! $force && $current && ! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "An order cannot move from \"{$current->getLabel()}\" to \"{$target->getLabel()}\".",
            ]);
        }

        if ($target === OrderStatus::Cancelled) {
            return $this->cancel($order, $note);
        }

        $order->pendingStatusNote = $note;
        $order->update(['status' => $target->value]);

        if ($target === OrderStatus::Delivered) {
            $order->shipments()->whereNull('delivered_at')->update(['status' => 'delivered', 'delivered_at' => now()]);
        }

        return $order->fresh();
    }

    /**
     * Cancels the order and puts its stock back. Captured payments are not
     * refunded automatically — the order is flagged for a staff refund.
     */
    public function cancel(Order $order, ?string $reason = null, bool $byCustomer = false): Order
    {
        if ($order->status === OrderStatus::Cancelled->value) {
            return $order;
        }

        if ($byCustomer && ! $order->statusEnum()?->isCancellableByCustomer()) {
            throw ValidationException::withMessages(['order' => 'This order can no longer be cancelled. Please request a return once it arrives.']);
        }

        DB::transaction(function () use ($order, $reason, $byCustomer) {
            $order->loadMissing('items.product', 'items.variant');

            foreach ($order->items as $item) {
                if ($item->product) {
                    $this->inventory->add($item->product, $item->variant, $item->quantity, 'cancellation', $order);
                }
            }

            if ($order->coupon_id) {
                $order->coupon()->where('used_count', '>', 0)->decrement('used_count');
            }

            $note = trim(($byCustomer ? 'Cancelled by customer. ' : '').($reason ?? ''));

            if ($order->isPaid()) {
                $note .= ' Payment was captured — refund required.';
            }

            $order->pendingStatusNote = trim($note) ?: null;
            $order->update([
                'status' => OrderStatus::Cancelled->value,
                'cancelled_at' => now(),
            ]);
        });

        return $order->fresh();
    }

    public function currentPrice(Product $product, ?ProductVariant $variant = null): float
    {
        return round($variant ? $variant->effective_price : $product->price, 2);
    }

    protected function generateOrderNumber(): string
    {
        $prefix = Setting::get('order_number_prefix', 'ORD');

        do {
            $number = $prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
