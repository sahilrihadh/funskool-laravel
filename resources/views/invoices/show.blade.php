@php
    $setting = fn ($key, $default = null) => \App\Models\Setting::get($key, $default);
    $symbol = $setting('currency_symbol', '₹');
    $money = fn ($value) => $symbol.number_format((float) $value, 2);
    $address = fn (?array $a) => collect([$a['full_name'] ?? null, $a['line1'] ?? null, $a['line2'] ?? null, trim(($a['city'] ?? '').' '.($a['postal_code'] ?? '')), $a['state'] ?? null, $a['country'] ?? null, $a['phone'] ?? null])->filter()->implode('<br>');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $packingSlip ? 'Packing Slip' : 'Invoice' }} {{ $order->order_number }}</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color: #1f2937; margin: 0; padding: 32px; background: #fff; font-size: 14px; }
        .wrap { max-width: 800px; margin: 0 auto; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #111; padding-bottom: 16px; }
        h1 { margin: 0; font-size: 24px; letter-spacing: .02em; }
        .muted { color: #6b7280; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 24px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; color: #6b7280; }
        td.num, th.num { text-align: right; }
        .totals { margin-left: auto; width: 280px; margin-top: 16px; }
        .totals div { display: flex; justify-content: space-between; padding: 4px 0; }
        .totals .grand { font-weight: 700; font-size: 16px; border-top: 2px solid #111; margin-top: 6px; padding-top: 8px; }
        .print { margin-bottom: 16px; }
        @media print { .print { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="print"><button onclick="window.print()">Print / Save as PDF</button></div>
    <header>
        <div>
            <h1>{{ $setting('store_name', config('app.name')) }}</h1>
            <div class="muted">
                {{ $setting('store_address') }}<br>
                {{ $setting('store_email') }} {{ $setting('store_phone') ? '· '.$setting('store_phone') : '' }}
                @if ($setting('tax_registration_number'))<br>GSTIN / Tax ID: {{ $setting('tax_registration_number') }}@endif
            </div>
        </div>
        <div style="text-align:right">
            <h1>{{ $packingSlip ? 'PACKING SLIP' : 'TAX INVOICE' }}</h1>
            <div class="muted">
                Order {{ $order->order_number }}<br>
                Date {{ $order->created_at->format('d M Y') }}<br>
                @unless ($packingSlip) Payment: {{ $order->paymentMethod?->name ?? '—' }} ({{ \App\Enums\PaymentStatus::labelFor($order->payment_status) }}) @endunless
            </div>
        </div>
    </header>

    <div class="grid">
        <div><strong>Ship to</strong><br>{!! $address($order->shipping_address) !!}</div>
        @unless ($packingSlip)
            <div><strong>Bill to</strong><br>{!! $address($order->billing_address ?? $order->shipping_address) !!}<br>{{ $order->contactEmail() }}</div>
        @endunless
    </div>

    <table>
        <thead>
        <tr>
            <th>Item</th><th>SKU</th><th class="num">Qty</th>
            @unless ($packingSlip)<th class="num">Price</th><th class="num">Total</th>@endunless
            @if ($packingSlip)<th class="num">Packed ✓</th>@endif
        </tr>
        </thead>
        <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->product_name }} @if($item->variant_label)<br><span class="muted">{{ $item->variant_label }}</span>@endif</td>
                <td>{{ $item->sku }}</td>
                <td class="num">{{ $item->quantity }}</td>
                @unless ($packingSlip)
                    <td class="num">{{ $money($item->price) }}</td>
                    <td class="num">{{ $money($item->total) }}</td>
                @endunless
                @if ($packingSlip)<td class="num">☐</td>@endif
            </tr>
        @endforeach
        </tbody>
    </table>

    @unless ($packingSlip)
        <div class="totals">
            <div><span>Subtotal</span><span>{{ $money($order->subtotal) }}</span></div>
            @if ((float) $order->discount_amount > 0)<div><span>Discount</span><span>-{{ $money($order->discount_amount) }}</span></div>@endif
            <div><span>Shipping</span><span>{{ $money($order->shipping_amount) }}</span></div>
            <div><span>Tax ({{ (float) $setting('tax_percent', 0) }}%)</span><span>{{ $money($order->tax_amount) }}</span></div>
            <div class="grand"><span>Total</span><span>{{ $money($order->total) }}</span></div>
            @if ((float) $order->refunded_amount > 0)<div><span>Refunded</span><span>-{{ $money($order->refunded_amount) }}</span></div>@endif
        </div>
    @endunless

    @if ($order->customer_note)
        <p><strong>Customer note:</strong> {{ $order->customer_note }}</p>
    @endif
    <p class="muted" style="margin-top:32px">This is a computer-generated document. {{ $setting('invoice_footer') }}</p>
</div>
</body>
</html>
