<?php

namespace App\Http\Controllers\Channel;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Resources\Channel\ChannelOrderResource;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends ChannelController
{
    public function __construct(protected OrderService $orders) {}

    /** Authoritative server-side pricing for a cart — call before showing totals / placing an order. */
    public function quote(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.sku' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'shipping_method_id' => ['nullable', 'integer'],
            'customer_email' => ['nullable', 'email'],
        ]);

        $user = isset($data['customer_email']) ? User::query()->where('email', $data['customer_email'])->first() : null;
        $quote = $this->orders->quote($this->resolveLines($data['items']), $data['coupon_code'] ?? null, $data['shipping_method_id'] ?? null, $user, $data['customer_email'] ?? null);

        return response()->json(['data' => [
            'items' => $quote['lines']->map(fn ($line) => [
                'sku' => $line['variant']->sku ?? $line['product']->sku,
                'name' => $line['product']->name,
                'variant' => $line['variant']?->label,
                'quantity' => $line['quantity'],
                'price' => $line['price'],
                'total' => $line['total'],
            ])->values(),
            'subtotal' => $quote['subtotal'],
            'discount_amount' => $quote['discount'],
            'shipping_amount' => $quote['shipping'],
            'tax_amount' => $quote['tax'],
            'total' => $quote['total'],
            'coupon_code' => $quote['coupon']?->code,
        ]]);
    }

    public function index(Request $request)
    {
        $request->validate(['updated_since' => ['nullable', 'date'], 'per_page' => ['nullable', 'integer', 'max:100']]);

        $orders = $this->channel($request)->orders()
            ->when($request->filled('external_reference'), fn ($query) => $query->where('external_reference', $request->string('external_reference')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('updated_since'), fn ($query) => $query->where('updated_at', '>=', $request->date('updated_since')))
            ->latest('id')
            ->paginate($request->integer('per_page', 25));

        return ChannelOrderResource::collection($orders);
    }

    public function show(Request $request, string $orderNumber)
    {
        return new ChannelOrderResource($this->findOrder($request, $orderNumber));
    }

    /**
     * Places an order. Send an "Idempotency-Key" header (e.g. your own order id)
     * so network retries never create duplicates.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'external_reference' => ['nullable', 'string', 'max:100'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.email' => ['required', 'email', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:20'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.full_name' => ['required', 'string', 'max:255'],
            'shipping_address.phone' => ['required', 'string', 'max:20'],
            'shipping_address.line1' => ['required', 'string', 'max:255'],
            'shipping_address.line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:100'],
            'shipping_address.state' => ['required', 'string', 'max:100'],
            'shipping_address.postal_code' => ['required', 'string', 'max:20'],
            'shipping_address.country' => ['required', 'string', 'max:100'],
            'billing_address' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.sku' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'shipping_method_id' => ['nullable', 'integer'],
            'payment_method_id' => ['nullable', 'integer'],
            'payment_method_code' => ['nullable', 'string'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'return_url' => ['nullable', 'url', 'max:500'],
            // Supply this when YOUR site already collected the payment.
            'payment' => ['nullable', 'array'],
            'payment.status' => ['required_with:payment', 'in:paid,pending'],
            'payment.gateway' => ['required_with:payment', 'string', 'max:30'],
            'payment.reference' => ['required_if:payment.status,paid', 'nullable', 'string', 'max:255'],
            'payment.amount' => ['required_if:payment.status,paid', 'nullable', 'numeric', 'min:0'],
        ]);

        $channel = $this->channel($request);
        $idempotencyKey = $request->header('Idempotency-Key') ?: ($data['external_reference'] ?? null);

        if (! empty($data['external_reference'])
            && $existing = $channel->orders()->where('external_reference', $data['external_reference'])->first()) {
            return (new ChannelOrderResource($existing))->response()->setStatusCode(200);
        }

        $paymentMethodId = $data['payment_method_id']
            ?? (isset($data['payment_method_code']) ? PaymentMethod::query()->where('code', $data['payment_method_code'])->value('id') : null);

        $paidExternally = ($data['payment']['status'] ?? null) === 'paid';

        if ($paidExternally) {
            $quote = $this->orders->quote($this->resolveLines($data['items']), $data['coupon_code'] ?? null, $data['shipping_method_id'] ?? null, null, $data['customer']['email']);

            if (abs($quote['total'] - (float) $data['payment']['amount']) >= 0.01) {
                throw ValidationException::withMessages([
                    'payment.amount' => "Paid amount {$data['payment']['amount']} does not match the order total {$quote['total']}. Use POST /quote to get authoritative totals.",
                ]);
            }
        }

        $order = DB::transaction(function () use ($data, $channel, $idempotencyKey, $paymentMethodId, $paidExternally) {
            $order = $this->orders->placeOrder($this->resolveLines($data['items']), [
                'user' => $this->resolveCustomer($data['customer']),
                'sales_channel' => $channel,
                'customer' => $data['customer'],
                'external_reference' => $data['external_reference'] ?? null,
                'idempotency_key' => $idempotencyKey ? "channel:{$channel->id}:{$idempotencyKey}" : null,
                'shipping_address' => $data['shipping_address'],
                'billing_address' => $data['billing_address'] ?? null,
                'shipping_method_id' => $data['shipping_method_id'] ?? null,
                'payment_method_id' => $paymentMethodId,
                'coupon_code' => $data['coupon_code'] ?? null,
                'customer_note' => $data['customer_note'] ?? null,
                'return_url' => $data['return_url'] ?? null,
                'status' => $paidExternally ? OrderStatus::Confirmed->value : OrderStatus::Pending->value,
                'payment_status' => $paidExternally ? PaymentStatus::Paid->value : PaymentStatus::Pending->value,
            ]);

            if ($paidExternally && $order->wasRecentlyCreated) {
                $order->transactions()->create([
                    'gateway' => Str::limit('ext:'.$data['payment']['gateway'], 30, ''),
                    'type' => 'payment',
                    'reference' => $data['payment']['reference'],
                    'amount' => $data['payment']['amount'],
                    'currency' => $order->currency,
                    'status' => 'succeeded',
                    'reason' => "Collected by {$channel->name}",
                ]);
            }

            return $order;
        });

        return (new ChannelOrderResource($order))->response()->setStatusCode($order->wasRecentlyCreated ? 201 : 200);
    }

    public function cancel(Request $request, string $orderNumber)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $order = $this->findOrder($request, $orderNumber);

        $order = $this->orders->cancel($order, 'Cancelled via '.$this->channel($request)->name.'. '.($data['reason'] ?? ''), byCustomer: true);

        return new ChannelOrderResource($order);
    }

    protected function findOrder(Request $request, string $orderNumber): Order
    {
        return $this->channel($request)->orders()
            ->where(fn ($query) => $query->where('order_number', $orderNumber)->orWhere('external_reference', $orderNumber))
            ->firstOrFail();
    }

    /**
     * Resolves SKUs to products/variants. Variant SKUs win; a product SKU on a
     * product that has variants is rejected (the variant must be chosen).
     */
    protected function resolveLines(array $items): array
    {
        return collect($items)->map(function (array $item, int $index) {
            $variant = ProductVariant::query()->with('product')->where('sku', $item['sku'])->first();
            $product = $variant?->product ?? Product::query()->where('sku', $item['sku'])->first();

            if (! $product) {
                throw ValidationException::withMessages(["items.{$index}.sku" => "Unknown SKU \"{$item['sku']}\"."]);
            }

            if (! $variant && $product->variants()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(["items.{$index}.sku" => "SKU \"{$item['sku']}\" has variants — send a variant SKU."]);
            }

            return ['product' => $product, 'variant' => $variant, 'quantity' => (int) $item['quantity']];
        })->all();
    }

    /** Links the order to an existing customer account, or creates one they can claim via "forgot password". */
    protected function resolveCustomer(array $customer): User
    {
        return User::query()->firstOrCreate(
            ['email' => strtolower($customer['email'])],
            [
                'name' => $customer['name'],
                'phone' => $customer['phone'] ?? null,
                'password' => Str::password(32),
                'role' => 'customer',
            ],
        );
    }
}
