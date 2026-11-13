# Funskool Admin — E-commerce Platform

A Laravel 13 + Filament 5 e-commerce platform: a full admin panel, a working
Blade storefront, and a token-based JSON API for a future React/Next
frontend. This README is your guide to what was built, how to run it, and
how to extend it.

> **New in the operations release:** payment gateways (Razorpay/Stripe), Shiprocket shipping,
> full order lifecycle with returns & refunds, email/SMS notifications, staff roles + MFA,
> audit log, stock ledger, CMS policy pages, a filterable dashboard with a light/dark toggle,
> and a **Channel API** that connects any external website to this dashboard.
>
> - What's covered vs. the requirements spec: [`docs/FEATURE-GAP-ANALYSIS.md`](docs/FEATURE-GAP-ANALYSIS.md)
> - Credentials you need to supply: [`required-resource.txt`](required-resource.txt)
> - Connecting another website: [`docs/CHANNEL_API.md`](docs/CHANNEL_API.md)

## Contents

- [Quick Start](#quick-start)
- [What Was Built](#what-was-built)
- [Branding & Demo Catalog](#branding--demo-catalog)
- [Architecture](#architecture)
- [Admin Panel Guide](#admin-panel-guide)
- ["Also Available On" (external marketplace links)](#also-available-on-external-marketplace-links)
- [Storefront Guide](#storefront-guide)
- [API Guide](#api-guide)
- [Payments — what's there, how to add a real gateway](#payments)
- [Shipping — what's there, how to add a real carrier](#shipping)
- [Intentionally Simplified / Next Steps](#intentionally-simplified--next-steps)
- [Useful Commands](#useful-commands)

## Quick Start

```bash
composer install
cp .env.example .env   # if you don't already have one; set DB_* credentials
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

npm install
npm run build           # or `npm run dev` while working on storefront styles

php artisan serve
php artisan queue:work      # in a second terminal: emails, SMS and webhooks are queued
```

(`composer dev` runs the server, queue worker, logs and Vite together.)

In production you also need the scheduler (Shiprocket tracking sync, log pruning):
`* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`

Upgrading an existing database: `php artisan migrate && php artisan db:seed --class=OperationsSeeder`
(adds the Razorpay/Stripe payment methods as inactive, draft policy pages, and default settings).

Seeded logins (see `database/seeders/DatabaseSeeder.php`):

| Role     | Email                     | Password   |
|----------|---------------------------|------------|
| Admin    | admin@funskool.test       | password   |
| Customer | customer@funskool.test    | password   |

- Admin panel: `http://localhost:8000/admin`
- Storefront: `http://localhost:8000/`
- API base: `http://localhost:8000/api`

**Change the seeded passwords (and remove the seeder's demo accounts) before deploying anywhere real.**

## What Was Built

**Admin panel (Filament, `/admin`)**
- Catalog: Brands, Categories (nested), Products — with tabs for pricing/inventory,
  a gallery of images, variants (Size/Color-style), "Also Available On" marketplace
  links, and SEO fields.
- Sales: Orders (view/edit/create manual orders, status timeline, "Update Status"
  action), Coupons.
- Customers: single Users resource (admins + customers, filterable by role), with
  an Addresses relation manager.
- Reviews: moderation queue (approve/reject).
- Configuration: Shipping Methods, Payment Methods (see [Payments](#payments) /
  [Shipping](#shipping)), Banners, and a Store Settings page (currency, tax %,
  contact info, social links).
- Dashboard: revenue/orders/customers/low-stock stats, an orders chart, and a
  latest-orders table.

**Storefront (Blade + Tailwind, functional MVP)**
- Home (banners, categories, featured products), category listing, product detail
  (gallery, variant picker, reviews, related products, marketplace links), cart,
  checkout, order history, wishlist, saved addresses, and register/login.
- Requires a customer account to use the cart/checkout (no guest checkout —
  see [Next Steps](#intentionally-simplified--next-steps)).

**API (`/api`, Sanctum token auth)**
- Same commerce operations as the storefront, for a React/Next (or any) client:
  auth, categories, products, cart, checkout, orders, wishlist, addresses,
  reviews, shipping/payment method lists.

**Product "Also Available On"** — see its own section below; this is the
external-marketplace-links feature you asked for specifically.

## Branding & Demo Catalog

Colors, logo, and the seeded product catalog were sourced from
[funskoolindia.com](https://funskoolindia.com/) so the demo looks and feels
like a real Funskool storefront, not a generic placeholder shop.

- **Colors**: sampled the live site's CSS and the official logo SVG. Primary
  brand red is `#ED1D24` (from the logo), with a yellow/gold, leaf-green,
  sky-blue and pink used as accents — matching the playful multi-color
  look of the real site. All defined as Tailwind v4 theme tokens in
  `resources/css/app.css` (`brand-*`, `sun-*`, `leaf-*`, `sky-*`, `bubble-*`,
  `ink-900`) — change the hex values there to re-theme the whole storefront
  in one place.
- **Logo**: the real Funskool logo (`public/images/funskool-logo.svg`,
  downloaded from the live site) is used in the storefront header/footer and
  auth pages. Swap that file to re-brand.
- **Font**: added `Baloo 2` (a rounded, toy-brand-appropriate display font,
  self-hosted via the existing Bunny Fonts Vite integration) for headings —
  see `--font-display` in `app.css` and the `font-display` utility class used
  throughout the storefront views.
- **Catalog**: `database/seeders/data/funskool_products.json` holds 33 real
  products (names, SKUs, prices, marketing copy) pulled from Funskool
  India's public WooCommerce Store API
  (`funskoolindia.com/wp-json/wc/store/v1/products`), bucketed into 9
  categories and 6 brands (Funskool house brand, Paw Patrol, Hello Kitty,
  Volt Rush, Blazetrix, Puzzle Pop). Product photos were downloaded and
  resized into `storage/app/public/products/catalog/`. `DatabaseSeeder`
  reads that JSON and creates the products/images/marketplace links — the
  "Also Available On" links even point to the real `funskoolindia.com`
  product pages plus generic Amazon/Flipkart searches, so the feature has
  real data to demo against out of the box.

**Before using this in production**: this is demo/dev data for showing the
platform working end-to-end. Replace `funskool_products.json` and the seeded
images with your actual product catalog and licensed photography, and
confirm you have the right to publicly use these specific product photos
(they were pulled from Funskool's own live site, which is fine for an
internal Funskool demo/dev environment, but check with your team before any
public-facing deployment).

## Architecture

- **Models**: `app/Models/*` — standard Eloquent, nothing exotic. `User.role`
  (`admin`/`customer`) drives both Filament panel access (`canAccessPanel()`)
  and storefront/API access.
- **Business logic lives in services, not controllers**: `App\Services\CartService`
  and `App\Services\OrderService` are used by *both* the Blade `CheckoutController`
  and the API `CheckoutController`, so cart/checkout behavior never diverges
  between the two frontends. Extend these, not the controllers, when you change
  checkout logic.
- **Filament resources**: `app/Filament/Resources/*`, one folder per resource,
  following the existing `Resource / Schemas / Tables / Pages` split that was
  already established in the starter `Products`/`Categories` resources.
- **Order status history** is appended automatically by a model hook in
  `App\Models\Order::booted()` whenever `status` changes (including on
  creation) — don't update `orders.status` directly via a raw query if you want
  the timeline to stay accurate.
- **Settings** are a simple `key` → `value` table (`App\Models\Setting`), not a
  package, with a cached `Setting::get()/set()`/`getMany()`/`setMany()` API.
  Good enough for a single-store admin; if you need per-tenant or typed
  settings later, swap this out.

## Admin Panel Guide

Log in at `/admin`. Navigation is grouped as **Catalog**, **Sales**,
**Customers**, **Configuration**, plus **Settings** and the **Dashboard**.

- **Products**: the "Also Available On" tab, the Variants tab, and the Images
  gallery tab are all repeaters backed by real relationships
  (`marketplaceLinks`, `variants`, `images`) — add/remove/reorder rows and hit
  "Save changes".
- **Orders**: creating an order from the admin (`New Manual Order`) lets you
  pick a customer and add line items directly — useful for phone/offline
  orders. It decrements stock and computes totals the same way checkout does,
  minus coupon support (coupons are storefront/API-only for now). Viewing an
  order shows the full summary, items, and status timeline, with an
  "Update Status" action that logs a timeline entry.
- **Reviews**: new reviews are unapproved by default; approve them here to
  make them public on the product page/API.
- **Settings**: store name/logo/contact info, currency code/symbol, and a
  single global tax percentage (`Setting::get('tax_percent')`), applied to
  every order at checkout. There's no per-category or per-region tax yet.

## "Also Available On" (external marketplace links)

This is the feature you asked for specifically: on each product, admins can
add links to where else the product is sold (Amazon, Flipkart, Meesho,
Myntra, or "Other" + a custom platform note), each with its own toggle and
sort order.

- **Admin**: Products → edit a product → **Also Available On** tab.
- **Data**: `product_marketplace_links` table / `App\Models\ProductMarketplaceLink`,
  `hasMany` on `Product` as `marketplaceLinks()`.
- **Storefront**: rendered as pill links (opens in a new tab) on the product
  detail page, just under the Add to Cart button.
- **API**: included as `marketplace_links` in the product payload
  (`GET /api/products/{slug}`).

## Storefront Guide

Plain Blade + Tailwind v4 (via the Vite setup that was already scaffolded in
this project) — no separate JS framework, no client-side state. It's meant as
a working reference implementation and a fallback storefront, not the final
production frontend if you build the React/Next app mentioned in your prompt.

- Layout: `resources/views/layouts/storefront.blade.php`.
- Pages: `resources/views/storefront/*`.
- Auth is hand-rolled (`App\Http\Controllers\Auth\AuthController` +
  `resources/views/auth/*`) rather than Breeze/Fortify, to keep the dependency
  footprint small and share the `role` column cleanly with the admin panel.
  Swap in Breeze/Fortify later if you want password resets, email
  verification, etc. — this controller is intentionally minimal.
- Styling: edit `resources/css/app.css` (Tailwind entry) and re-run
  `npm run build` (or `npm run dev` while iterating).

## API Guide

Base URL: `/api`. Auth via Laravel Sanctum **personal access tokens** (`Authorization: Bearer <token>`), not cookies — safe for a separate React/Next app on another origin as long as you send the header.

| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| POST | `/register` | – | returns `{ user, token }` |
| POST | `/login` | – | returns `{ user, token }` |
| POST | `/logout` | ✓ | revokes the current token |
| GET | `/me` | ✓ | current user |
| GET | `/categories`, `/categories/{slug}` | – | |
| GET | `/products` | – | filters: `category`, `featured`, `search`, `per_page` |
| GET | `/products/{slug}` | – | includes images, variants, marketplace links, approved reviews |
| GET | `/products/{slug}/reviews` | – | |
| POST | `/products/{slug}/reviews` | ✓ | one review per user per product (upsert) |
| GET | `/shipping-methods`, `/payment-methods` | – | active only |
| GET/POST/PATCH/DELETE | `/cart`, `/cart/{id}` | ✓ | |
| POST | `/checkout` | ✓ | send `Idempotency-Key`; response has `payment.payment_url` for online gateways |
| GET | `/orders`, `/orders/{id}` | ✓ | includes shipments & returns |
| POST | `/orders/{id}/cancel` | ✓ | before the order is packed |
| POST | `/orders/{id}/returns` | ✓ | `{ reason, items: { "<order_item_id>": qty } }` |
| GET | `/orders/{id}/payment-link` | ✓ | signed hosted-payment URL if unpaid |
| POST | `/forgot-password`, `/reset-password` | – | rate-limited |
| GET | `/pages`, `/pages/{slug}` | – | published policy/content pages |
| GET/POST | `/support-tickets` | ✓ | list / open (`subject, category?, order_id?, message`) |
| GET | `/support-tickets/{ticket_number}` | ✓ | thread without internal notes |
| POST | `/support-tickets/{ticket_number}/messages` | ✓ | `{ body }`; reopens a solved ticket |
| GET/POST/DELETE | `/wishlist`, `/wishlist/{product}` | ✓ | |
| GET/POST/DELETE | `/addresses`, `/addresses/{id}` | ✓ | |

All responses go through `App\Http\Resources\*` so the JSON shape is stable
regardless of what the Eloquent models look like internally — extend those
classes (not raw `toArray()` calls in controllers) if you need more fields
exposed to the frontend.

## Payments

**Razorpay (primary) and Stripe are wired up as hosted checkouts.** Card data never touches
this app. Add the keys from `required-resource.txt` to `.env`, then activate the payment method
in Admin → Configuration → Payment Methods. The method's form shows whether the keys were found
and which webhook URL to register.

- Flow: order is created → customer is redirected to the gateway → on return the status is
  **re-fetched from the gateway API** (the browser redirect is never trusted) → the signed
  webhook confirms it. Both paths go through `PaymentService::apply()`, which is idempotent.
  Webhook events are de-duplicated by `(provider, event_id)`.
- If the paid amount doesn't match the order total, the order is put **On Hold** for review.
- Failed or cancelled payments keep the order; customers get a "Pay now" retry (signed link, 48h).
- Refunds: Admin order → More → Refund (needs the `orders.refund` permission). They go through
  the gateway, or are recorded as manual (COD / bank transfer). Partial refunds are supported.
- COD / bank transfer: staff use **Record Payment** on the order.
- Code: `app/Payments/*`, `app/Services/PaymentService.php`. To add another gateway, implement
  `App\Payments\PaymentGateway` and register it in `PaymentService::GATEWAYS`.

<details><summary>Original note (before gateways were added)</summary>


- `payment_methods` table / `PaymentMethodResource` in the admin: admins can
  add/enable methods (seeded with Cash on Delivery and Bank Transfer), with a
  `config` JSON column reserved for future gateway credentials. In the admin
  form it's shown as a disabled "Gateway Credentials — Coming Soon" section so
  you can see the shape without it doing anything yet.
- Every `Order` has its own `payment_status` (`pending/paid/failed/refunded`)
  independent of the overall `status` — that's the field a real gateway
  webhook would update.

**To add a real gateway (e.g. Razorpay/Stripe) later:**
1. Add the SDK via composer, add real fields to `PaymentMethod.config` (or a
   dedicated settings page) for API keys.
2. In `App\Services\OrderService::checkout()`, after creating the `Order`,
   branch on `$paymentMethod->code` to create a payment intent/order with the
   gateway, and store its reference on the order (add a column if needed).
3. Add a webhook route/controller that verifies the gateway's signature and
   updates `order->payment_status` (and `status` if you auto-progress orders
   on payment).
4. Un-disable the `config` KeyValue field in
   `app/Filament/Resources/PaymentMethods/Schemas/PaymentMethodForm.php`.

</details>

## Shipping

**Shipments are created per order: Admin order → Ship.** Either:
- **Manual**: enter the courier, tracking number and tracking URL; or
- **Shiprocket** (when `SHIPROCKET_*` is set): creates the Shiprocket order, assigns an AWB,
  generates the label and stores the tracking link. Tracking updates arrive by webhook
  (`/api/webhooks/shipping/shiprocket`) and by hourly polling (`php artisan shipments:sync`).
  Delivered → order becomes Delivered; RTO → Returned.

Customers get a shipped email/SMS with the tracking link. Checkout shipping *rates* are still the
flat-rate methods described below (zones and weight-based rates are listed as next steps).

<details><summary>Rate calculation details</summary>


- `shipping_methods` table: admin-managed flat-rate options (name, cost,
  optional free-shipping threshold, active/inactive) — seeded with Standard
  and Express. There's a `provider` field reserved for a future carrier key
  (e.g. `shiprocket`, `delhivery`) — informational only right now.
- `ShippingMethod::costFor($subtotal)` is where the actual cost calculation
  happens (currently: flat cost, or free above a threshold).

**Live courier rates at checkout (future):** replace/extend `costFor()` to call the
carrier's rate API (using `Product.weight`).

</details>

## Orders, Returns & Refunds

- Order status (`App\Enums\OrderStatus`) and payment status (`App\Enums\PaymentStatus`) are
  separate fields. Allowed status transitions are enforced in `OrderService::updateStatus()`.
- Cancelling puts stock back (stock ledger, reason "cancellation"). Cancelled-but-paid orders
  show under Orders → **Refund Due**.
- Customers can cancel until the order is packed, and can request a return within
  `return_window_days` (Settings) after delivery. Staff handle returns under Sales → Returns:
  approve/reject → mark received (optionally restock) → refund.
- Every stock change is written to Inventory → Stock Movements.

## Staff Roles, MFA & Audit

- Roles: `admin` (owner, full access), `manager`, `catalog_manager`, `fulfillment`, `support`,
  `marketing`, `finance`, `analyst` (read-only). Permissions for each role are in
  `config/permissions.php` and are enforced on every Filament resource through
  `App\Filament\Concerns\ChecksPermissions`.
- MFA: each staff member can enable an authenticator app under their profile.
  `ADMIN_REQUIRE_MFA=true` makes it mandatory.
- Administration → Audit Log records changes to key records, refunds, exports, settings changes,
  API-key rotations and staff sign-ins. It is read-only, and secrets are never logged.
- Light/dark mode: the sun/moon button in the top bar (Light/Dark/System is also in the user menu).

## Admin Design (TailAdmin)

The admin panel follows the [TailAdmin](https://tailadmin.com) design system:

- **Layout:** a full-height 290px sidebar with the logo. Navigation groups are parent items with
  icons and collapsible sub-menus, under MENU / SUPPORT / OTHERS headings. The header has the
  collapse button and ⌘K search on the left, and the theme toggle, notification bell and
  avatar + name menu on the right. It collapses to icons on desktop and slides in on mobile.
- **Tokens:** TailAdmin's exact palette (brand `#465FFF`, grays, success, error, warning,
  blue-light) in `App\Filament\Support\TailAdminPalette`, the Outfit font, rounded-2xl cards
  with 1px borders, and pill badges.
- **Tables:** "Data Table 3" style. Avatar + name + email cells (`App\Filament\Support\Avatar`),
  a toolbar with search, filters and Download, and square pagination.
- **Files:** `resources/css/filament/admin/theme.css` (registered with `->viteTheme()`), plus
  `resources/views/filament/components/*` and one overridden Filament view,
  `resources/views/vendor/filament-panels/components/user-menu.blade.php`, which adds the name
  and chevron to the header user button. Re-check that file after Filament upgrades.
- Run **`npm run build`** after changing the theme (or keep `npm run dev` running).
- Avatars are generated locally as initials, so staff names are no longer sent to ui-avatars.com.

## Support Tickets

- Customers open tickets from **`/contact`** (anyone), **My Support Tickets** (signed in), the
  customer API (`/api/support-tickets`), or a connected website (`/api/channel/v1/support-tickets`).
  Guests follow and reply to their ticket through the private link in their confirmation email.
- Admin → **Support → Ticket List** follows TailAdmin's Support Tickets page: Total / Pending /
  Solved cards, All / Pending / Solved / Assigned to me / Unassigned tabs, and category, priority,
  assignee and website filters. Opening a ticket shows the **Ticket Reply** page: the conversation
  thread, a reply box with attachments and In-Progress / Solved / On-Hold status, internal notes,
  a Ticket Details panel, and a "2 of 120" pager.
- Staff replies email the customer. Internal notes are never shown to customers, by email, or
  through any API. A customer reply reopens a solved ticket.
- New tickets and customer replies notify the assignee, or everyone with `support.view`, through
  the header bell. Settings → "Support ticket alert email" can also notify a shared inbox.
- Attachments (images/PDF/text, max 5 MB, 3 per message) are stored on the private `local` disk
  and served only to staff, the ticket owner, or a guest holding the ticket link.

## Connecting Other Websites (Channel API)

Admin → Integrations → **Connected Websites** issues an API key and webhook secret for each
external site (WooCommerce, Shopify, a Next.js storefront, a mobile app…). The site can then
sync products and stock, price carts server-side, place orders (paid by them, or paid through
our gateway via a `payment_url`), and receive signed webhooks for order, shipment, product and
stock events. Webhook deliveries are retried with backoff and listed in a delivery log with a
manual retry button. Full reference: [`docs/CHANNEL_API.md`](docs/CHANNEL_API.md).

## Intentionally Simplified / Next Steps

Documented here so nothing looks like an oversight:

See [`docs/FEATURE-GAP-ANALYSIS.md`](docs/FEATURE-GAP-ANALYSIS.md) for the full, current list.
Highlights:

- **No guest checkout on the Blade storefront.** Cart and checkout require an account.
  Guest orders *are* supported through the Channel API (customers are matched or created by
  email). Adding it here means a session-based cart that merges into the user's cart on login.
- **Product variants are simplified** — `product_variants.attributes` is a
  free-form JSON map (`{"Size":"M"}`) rather than a normalized
  attributes/values system. Fine for most catalogs; if you need
  attribute-driven filtering/faceted search, you'll want a relational
  attributes table instead.
- **Shipping rates are flat per method** (with a free-shipping threshold). There are no
  zones or weight tables yet.
- **Single global tax %.** Per-category GST/HSN rates need a decision with your CA first.
- **No product search engine** — `search` on `/api/products` is a plain
  `LIKE` query. Swap in Laravel Scout + Meilisearch/Algolia if the catalog
  grows.
- **Tests**: `php artisan test` runs 42 feature tests covering checkout, overselling,
  idempotency, the order lifecycle, returns, refunds, the channel API, payment webhooks,
  permissions, support tickets, and admin pages and actions.

## Useful Commands

```bash
php artisan migrate:fresh --seed   # reset the DB back to demo data
php artisan tinker                 # poke at models directly
npm run dev                        # Tailwind/Vite watch mode for storefront work
php artisan route:list --path=admin
php artisan route:list --path=api
```
