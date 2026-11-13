# Channel API v1 — connecting an external website

Use this API to run any storefront (WooCommerce, Shopify, a Next.js/React site, a mobile app, a marketplace bridge) off this dashboard. The dashboard stays the source of truth for **catalogue, prices, stock, orders, payments, shipping and notifications**. Your site displays products and sends orders.

```
Your website  ──(REST, API key)──▶  /api/channel/v1/*  ──▶  Orders, stock, payments, shipping
     ▲                                                              │
     └────────────(signed webhooks: order / shipment / stock)───────┘
```

## 1. Get credentials

Admin → **Integrations → Connected Websites → Connect a website**. You get:

| Value | Where it goes | Notes |
|---|---|---|
| **API key** `fsk_xxxxxxxx.<secret>` | your site's **server-side** env | Shown once. Only its SHA-256 hash is stored. Rotate it from the same screen. |
| **Webhook secret** `whsec_…` | your site's server-side env | Used to verify our webhook signatures. |

> Never put the API key in browser JavaScript or a mobile app bundle. Call this API from your backend (PHP, Node, Next.js route handlers, etc.).

## 2. Authentication & conventions

```
Authorization: Bearer fsk_xxxxxxxx.<secret>      (or  X-Api-Key: <key>)
Accept: application/json
Content-Type: application/json
```

- Base URL: `https://YOUR-DOMAIN/api/channel/v1`
- Rate limit: 300 requests/minute per channel (HTTP 429 when exceeded).
- Errors: `401` bad/inactive key · `404` not found / not your order · `422` validation (`{ "message", "errors": { field: [..] } }`).
- Money is decimal in the store currency (`GET /store` → `currency_code`, INR by default). Prices are **tax-exclusive**; tax is added at the order level.
- Lists are paginated: `?page=2&per_page=50` (max 100) and return `data`, `links` and `meta`.

## 3. Endpoints

### Store & catalogue (read)

| Method | Path | Purpose |
|---|---|---|
| GET | `/store` | Store name, currency, tax %, return window, contact info |
| GET | `/categories` · `/brands` | Active categories / brands |
| GET | `/products` | Filters: `search`, `category` (slug), `brand` (slug), `sku[]`, `featured=1`, `updated_since=2026-10-01T00:00:00Z` (incremental sync), `include_inactive=1` |
| GET | `/products/{sku-or-slug}` | One product with images and variants |
| GET | `/inventory?sku[]=A&sku[]=B` | Stock for product **or variant** SKUs (max 200) |
| GET | `/shipping-methods` | Active shipping options (use the `id` when ordering) |
| GET | `/payment-methods` | Usable payment methods (online gateways only appear once their keys are configured) |
| GET | `/pages` · `/pages/{slug}` | Published policy / content pages (HTML) |

### Cart pricing

`POST /quote` returns authoritative totals. Call it before showing a cart or checkout total.

```json
{
  "items": [{ "sku": "FS-1001", "quantity": 2 }],
  "coupon_code": "WELCOME10",
  "shipping_method_id": 1,
  "customer_email": "asha@example.com"
}
```
→ `{ "data": { "items": [...], "subtotal", "discount_amount", "shipping_amount", "tax_amount", "total", "coupon_code" } }`

### Orders

| Method | Path | Purpose |
|---|---|---|
| POST | `/orders` | Place an order |
| GET | `/orders` | Your channel's orders. Filters: `external_reference`, `status`, `updated_since` |
| GET | `/orders/{order_number-or-external_reference}` | One order |
| POST | `/orders/{order_number}/cancel` | Cancel before it's packed (`{ "reason": "…" }`). Stock is restored |

A channel only ever sees **its own** orders.

### Support tickets (your contact form → our helpdesk)

| Method | Path | Purpose |
|---|---|---|
| POST | `/support-tickets` | `{ name, email, phone?, subject, category?, order_number?, message }` → ticket + `customer_url` (a private page where the customer can read replies and answer) |
| GET | `/support-tickets/{ticket_number}` | Status and public thread (internal staff notes are never included) |
| POST | `/support-tickets/{ticket_number}/messages` | `{ body }`: add a customer reply. Reopens a solved ticket |

`category` is one of `order, delivery, returns, payment, product, account, general`. Staff replies are emailed to the customer by this dashboard.

#### `POST /orders`

Send an **`Idempotency-Key`** header (your own order id works well). Retrying with the same key, or the same `external_reference`, returns the original order (HTTP 200) instead of creating a duplicate. A new order returns HTTP 201.

```json
{
  "external_reference": "WC-1001",
  "customer": { "name": "Asha Rao", "email": "asha@example.com", "phone": "9876543210" },
  "shipping_address": {
    "full_name": "Asha Rao", "phone": "9876543210",
    "line1": "12 MG Road", "line2": null,
    "city": "Bengaluru", "state": "Karnataka", "postal_code": "560001", "country": "India"
  },
  "billing_address": null,
  "items": [{ "sku": "FS-1001", "quantity": 2 }],
  "shipping_method_id": 1,
  "payment_method_code": "razorpay",
  "coupon_code": null,
  "customer_note": "Gift wrap please",
  "return_url": "https://shop.example.com/checkout/thank-you"
}
```

- **SKUs**: send the variant SKU for products with variants (a parent SKU is rejected with 422).
- **Stock** is checked and reserved atomically. Out-of-stock lines return 422 `stock`.
- **Customer**: linked to an existing account by email, or a customer account is created (they can claim it with "Forgot password").

**Payment — choose one mode:**

1. **We collect the payment** (Razorpay/Stripe configured here). Set `payment_method_code` to `razorpay` or `stripe`. The response contains:
   ```json
   "payment": { "requires_action": true, "payment_url": "https://YOUR-DOMAIN/pay/ORD-…?signature=…" }
   ```
   Redirect the customer to `payment_url` (signed, valid 48h). After paying, cancelling or failing, they come back to your `return_url` with `?order=ORD-…&payment_status=paid|pending|failed|cancelled`. **Don't trust that query string.** Wait for the `order.payment_changed` webhook, or call `GET /orders/{number}`.
2. **You already collected the payment** (e.g. your WooCommerce Razorpay plugin). Add:
   ```json
   "payment": { "status": "paid", "gateway": "woo-razorpay", "reference": "pay_ABC123", "amount": 1050.00 }
   ```
   `amount` must equal our computed total (use `/quote`), or the order is rejected with 422. The order is created as `confirmed` / `paid`.

Cash on delivery: `"payment_method_code": "cod"`. No payment step; staff record the payment on delivery.

#### Order object (also the webhook payload)

```json
{
  "order_number": "ORD-20261024-AB12CD",
  "external_reference": "WC-1001",
  "status": "pending | confirmed | processing | packed | shipped | delivered | on_hold | cancelled | return_requested | returned | refunded",
  "payment_status": "pending | authorized | paid | failed | partially_refunded | refunded | disputed",
  "currency": "INR",
  "subtotal": 1000.0, "discount_amount": 0.0, "shipping_amount": 50.0, "tax_amount": 0.0, "total": 1050.0, "refunded_amount": 0.0,
  "customer": { "name": "…", "email": "…", "phone": "…" },
  "shipping_address": { }, "billing_address": { },
  "payment_method": "razorpay", "shipping_method": "Standard Shipping",
  "items": [{ "sku": "FS-1001", "name": "…", "variant": null, "quantity": 2, "price": 500.0, "total": 1000.0 }],
  "shipments": [{ "carrier": "Delhivery", "tracking_number": "…", "tracking_url": "…", "status": "in_transit", "shipped_at": "…", "delivered_at": null }],
  "payment": { "requires_action": false, "payment_url": null },
  "created_at": "2026-10-24T10:00:00+05:30", "updated_at": "…"
}
```

Order status and payment status are **separate** fields. An order can be shipped while payment is pending (COD), or delivered while a refund is in progress.

## 4. Webhooks (dashboard → your site)

Set the webhook URL and events on the connected-website screen.

| Event | Sent to | `data` |
|---|---|---|
| `order.created` | the channel that placed the order | `{ order }` |
| `order.status_changed` | same | `{ order, previous_status }` |
| `order.payment_changed` | same | `{ order, previous_payment_status }` |
| `shipment.created` | same | `{ order }` (see `order.shipments`) |
| `product.updated` | all subscribed channels | `{ product }` |
| `inventory.updated` | all subscribed channels | `{ sku, product_sku, variant_id, previous_quantity, stock_quantity, in_stock }` |
| `ping` | the channel | sent by "Send test webhook" |

Request:
```
POST <your webhook url>
Content-Type: application/json
X-Webhook-Event: order.status_changed
X-Webhook-Id: 7d0c…   (unique per delivery — use it to ignore duplicates)
X-Webhook-Signature: sha256=<hex HMAC-SHA256 of the raw body, keyed with your webhook secret>

{ "id": "7d0c…", "event": "order.status_changed", "created_at": "…", "data": { … } }
```

Respond with any **2xx** within 15 seconds. Anything else is retried after 10s, 1m, 5m, 15m and 1h (6 attempts), then marked failed. Every attempt is visible, and can be retried, in the delivery log on the connected-website screen. Deliveries can arrive more than once and out of order. Use `X-Webhook-Id` for de-duplication and `updated_at` to discard stale updates.

### Verifying the signature

PHP:
```php
$body = file_get_contents('php://input');
$expected = 'sha256='.hash_hmac('sha256', $body, getenv('FUNSKOOL_WEBHOOK_SECRET'));
if (! hash_equals($expected, $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '')) {
    http_response_code(401); exit;
}
$event = json_decode($body, true);
```

Node / Next.js:
```js
import crypto from 'node:crypto';

export async function POST(req) {
  const body = await req.text(); // raw body — don't re-serialise JSON
  const expected = 'sha256=' + crypto.createHmac('sha256', process.env.FUNSKOOL_WEBHOOK_SECRET).update(body).digest('hex');
  const given = req.headers.get('x-webhook-signature') ?? '';
  if (given.length !== expected.length || !crypto.timingSafeEqual(Buffer.from(given), Buffer.from(expected))) {
    return new Response('bad signature', { status: 401 });
  }
  const event = JSON.parse(body);
  // handle event.event / event.data …
  return new Response('ok');
}
```

## 5. Typical integration recipes

**Headless storefront (Next.js etc.)**: sync products with `GET /products?updated_since=…` (or listen to `product.updated` / `inventory.updated`), price carts with `/quote`, place orders with `payment_method_code: "razorpay"`, then redirect to `payment_url`.

**WooCommerce / Shopify as the shop front**: on "order paid" in your platform, call `POST /orders` with `payment.status = "paid"` and `external_reference` = your order id. Then push `shipment.created` / `order.status_changed` back into your platform from our webhooks. Keep stock in sync from `inventory.updated`.

**ERP / accounting**: page through `GET /orders?updated_since=…` on a schedule.

## 6. Quick test with curl

```bash
KEY="fsk_xxxxxxxx.yyyy"
BASE="http://localhost:8000/api/channel/v1"

curl -s -H "Authorization: Bearer $KEY" "$BASE/store"
curl -s -H "Authorization: Bearer $KEY" "$BASE/products?per_page=5"
curl -s -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
     -d '{"items":[{"sku":"YOUR-SKU","quantity":1}],"shipping_method_id":1}' "$BASE/quote"
```
