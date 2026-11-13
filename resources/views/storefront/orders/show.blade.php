@extends('layouts.storefront')

@section('title', $order->order_number)

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1 text-ink-900">{{ $order->order_number }}</h1>
            <p class="text-ink-900/50">Placed on {{ $order->created_at->format('M j, Y') }}</p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <span class="rounded-full bg-black/5 px-3 py-1 font-semibold text-ink-900">{{ \App\Enums\OrderStatus::labelFor($order->status) }}</span>
            <span class="rounded-full bg-black/5 px-3 py-1 text-ink-900/70">Payment: {{ \App\Enums\PaymentStatus::labelFor($order->payment_status) }}</span>
            <a href="{{ route('orders.invoice', $order) }}" target="_blank" class="rounded-full border border-black/10 px-3 py-1 font-semibold text-brand-600 hover:bg-brand-50">Invoice</a>
            <a href="{{ route('support.contact', ['order' => $order->id]) }}" class="rounded-full border border-black/10 px-3 py-1 font-semibold text-brand-600 hover:bg-brand-50">Get help</a>
        </div>
    </div>

    @if ($payUrl)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-sun-400/50 bg-sun-400/10 p-4 text-sm text-ink-900/80">
            <span>This order is awaiting online payment.</span>
            <a href="{{ $payUrl }}" class="rounded-full bg-brand-500 px-5 py-2 font-bold text-white hover:bg-brand-600">Pay now</a>
        </div>
    @endif

    <div class="grid md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white border border-black/5 rounded-2xl p-5">
                <h2 class="font-display font-bold text-ink-900 mb-4">Items</h2>
                @foreach ($order->items as $item)
                    <div class="flex justify-between text-sm py-2 border-b border-black/5 last:border-0 text-ink-900/70">
                        <span>{{ $item->product_name }} @if($item->variant_label) ({{ $item->variant_label }}) @endif × {{ $item->quantity }}</span>
                        <span>₹{{ number_format($item->total, 2) }}</span>
                    </div>
                @endforeach
            </div>

            @if ($order->shipments->isNotEmpty())
                <div class="bg-white border border-black/5 rounded-2xl p-5">
                    <h2 class="font-display font-bold text-ink-900 mb-4">Shipment Tracking</h2>
                    @foreach ($order->shipments as $shipment)
                        <div class="flex flex-wrap justify-between gap-2 text-sm py-2 border-b border-black/5 last:border-0">
                            <div class="text-ink-900/70">
                                <strong class="text-ink-900">{{ $shipment->courier_name ?? ucfirst($shipment->carrier) }}</strong>
                                @if ($shipment->tracking_number) · {{ $shipment->tracking_number }} @endif
                                <span class="block text-ink-900/50">{{ \App\Models\Shipment::STATUSES[$shipment->status] ?? $shipment->status }} @if($shipment->last_event) — {{ $shipment->last_event }} @endif</span>
                            </div>
                            @if ($shipment->tracking_url)
                                <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener" class="font-semibold text-brand-600">Track parcel →</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="bg-white border border-black/5 rounded-2xl p-5">
                <h2 class="font-display font-bold text-ink-900 mb-4">Status Timeline</h2>
                <ul class="space-y-2 text-sm">
                    @foreach ($order->statusHistories as $history)
                        <li class="flex justify-between gap-4">
                            <span>
                                <span class="font-medium text-ink-900">{{ \App\Enums\OrderStatus::labelFor($history->status) }}</span>
                                @if ($history->note)<span class="block text-ink-900/50">{{ $history->note }}</span>@endif
                            </span>
                            <span class="text-ink-900/40 shrink-0">{{ $history->created_at->format('M j, Y g:i A') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if ($order->returnRequests->isNotEmpty())
                <div class="bg-white border border-black/5 rounded-2xl p-5">
                    <h2 class="font-display font-bold text-ink-900 mb-4">Returns</h2>
                    @foreach ($order->returnRequests as $return)
                        <div class="text-sm py-2 border-b border-black/5 last:border-0 text-ink-900/70">
                            <strong class="text-ink-900">Return #{{ $return->id }}</strong> — {{ \App\Models\ReturnRequest::STATUSES[$return->status] ?? $return->status }}
                            <span class="block text-ink-900/50">{{ $returnReasons[$return->reason] ?? $return->reason }} · {{ $return->created_at->format('M j, Y') }}</span>
                            @if ($return->admin_note)<span class="block">{{ $return->admin_note }}</span>@endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($canReturn && array_sum($returnable) > 0)
                <form method="POST" action="{{ route('orders.return', $order) }}" class="bg-white border border-black/5 rounded-2xl p-5 space-y-4">
                    @csrf
                    <div>
                        <h2 class="font-display font-bold text-ink-900">Request a Return</h2>
                        <p class="text-sm text-ink-900/50">Available until {{ $returnDeadline->format('M j, Y') }}.</p>
                    </div>
                    @foreach ($order->items as $item)
                        @if (($returnable[$item->id] ?? 0) > 0)
                            <label class="flex items-center justify-between gap-3 text-sm text-ink-900/70">
                                <span>{{ $item->product_name }} @if($item->variant_label) ({{ $item->variant_label }}) @endif</span>
                                <select name="items[{{ $item->id }}]" class="border border-black/10 rounded-lg px-2 py-1">
                                    @for ($q = 0; $q <= $returnable[$item->id]; $q++)
                                        <option value="{{ $q }}">{{ $q }}</option>
                                    @endfor
                                </select>
                            </label>
                        @endif
                    @endforeach
                    <select name="reason" required class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
                        <option value="">Reason for return…</option>
                        @foreach ($returnReasons as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <textarea name="customer_note" rows="2" placeholder="Anything we should know? (optional)" class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm"></textarea>
                    <button class="rounded-full bg-ink-900 px-5 py-2 text-sm font-bold text-white">Submit return request</button>
                </form>
            @endif
        </div>

        <div class="space-y-6">
            <div class="bg-white border border-black/5 rounded-2xl p-5 h-fit">
                <h2 class="font-display font-bold text-ink-900 mb-4">Summary</h2>
                <div class="text-sm space-y-1 text-ink-900/70">
                    <div class="flex justify-between"><span>Subtotal</span><span>₹{{ number_format($order->subtotal, 2) }}</span></div>
                    <div class="flex justify-between"><span>Discount</span><span>-₹{{ number_format($order->discount_amount, 2) }}</span></div>
                    <div class="flex justify-between"><span>Shipping</span><span>₹{{ number_format($order->shipping_amount, 2) }}</span></div>
                    <div class="flex justify-between"><span>Tax</span><span>₹{{ number_format($order->tax_amount, 2) }}</span></div>
                    <div class="flex justify-between font-bold text-ink-900 border-t border-black/5 pt-2 mt-2"><span>Total</span><span>₹{{ number_format($order->total, 2) }}</span></div>
                    @if ((float) $order->refunded_amount > 0)
                        <div class="flex justify-between text-cocoa-600"><span>Refunded</span><span>₹{{ number_format($order->refunded_amount, 2) }}</span></div>
                    @endif
                </div>

                <div class="mt-4 text-sm text-ink-900/60">
                    <p><strong class="text-ink-900">Payment:</strong> {{ $order->paymentMethod?->name }}</p>
                    <p><strong class="text-ink-900">Shipping:</strong> {{ $order->shippingMethod?->name }}</p>
                </div>
            </div>

            @if ($order->statusEnum()?->isCancellableByCustomer())
                <form method="POST" action="{{ route('orders.cancel', $order) }}" class="bg-white border border-black/5 rounded-2xl p-5 space-y-3"
                      onsubmit="return confirm('Cancel this order?')">
                    @csrf
                    <h2 class="font-display font-bold text-ink-900">Cancel Order</h2>
                    <p class="text-sm text-ink-900/50">You can cancel until the order is packed.@if($order->isPaid()) Your payment will be refunded to the original method.@endif</p>
                    <input name="reason" placeholder="Reason (optional)" class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
                    <button class="w-full rounded-full border border-brand-500 px-5 py-2 text-sm font-bold text-brand-600 hover:bg-brand-50">Cancel order</button>
                </form>
            @endif
        </div>
    </div>
@endsection
