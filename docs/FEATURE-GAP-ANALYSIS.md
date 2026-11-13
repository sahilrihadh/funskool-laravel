# Feature gap analysis — spec vs. this codebase

Checked against the "E-commerce Website — Complete Feature & Requirements Specification" (24 sections), as of 24 Oct 2026 (updated for the support module and admin redesign).

| Mark | Meaning |
|---|---|
| ✅ | Existed before this round |
| 🆕 | Built since the first review (covered by `php artisan test`: 42 tests) |
| 🔑 | Code is done; add credentials from `required-resource.txt` to switch it on |
| 🟡 | Partly done (the gap is noted) |
| ⏳ | Not built yet; suggested phase in brackets |
| ➖ | Business/legal decision or content, not code |

**Bottom line:** the Phase-1 launch essentials from §24 are now in place: catalogue, cart/checkout, payment gateway, shipping, order lifecycle, returns/refunds, notifications, roles, audit, CMS policies, reporting basics and the external-website connector. The largest remaining gaps are **guest checkout on the built-in Blade storefront**, **storefront filters/sorting/search quality**, **shipping zones/weight-based rates**, **multi-warehouse**, and the optional business-model modules (§23).

---

## 1. Website-wide experience
| Item | Status |
|---|---|
| Brand styles, responsive layout, header/nav/footer | ✅ |
| Loading/empty/error states | 🟡 empty states on main pages. 🆕 custom 404 page |
| Announcement bar, mega menu, currency/language selector | ⏳ [P2] (single market, INR) |
| Search field | ✅ basic (`LIKE`); suggestions/recent searches ⏳ [P2] |
| Cookie/privacy preference controls | ⏳ needed once analytics/ads pixels are added |
| Maintenance page | ✅ Laravel `php artisan down` |
| Contact details / service hours | ✅ contact in footer from Settings. 🆕 policy pages in footer |

## 2. Homepage
| Item | Status |
|---|---|
| Hero banners, categories, featured products, footer | ✅ |
| Admin reorder / enable / disable banners | ✅ |
| Banner scheduling (start/end), mobile image variants, preview | ⏳ [P2] |
| Bestsellers, recently viewed, reviews/testimonials blocks, newsletter signup | ⏳ [P2] |

## 3. Listing, search & discovery
| Item | Status |
|---|---|
| Category pages + product grid + pagination | ✅ |
| Filters (price/brand/attributes), sort options, active filter chips | ⏳ [P2]. Needs normalised attributes (variants are free-form JSON today) |
| Typo-tolerant search, synonyms, search analytics | ⏳ [P2] Laravel Scout + Meilisearch |
| Unpublished products hidden from search/API | ✅ (`is_active`) |
| Product cards with price/sale/stock | ✅ |

## 4. Product detail page
| Item | Status |
|---|---|
| Gallery, description, variants, price/sale, stock, reviews, related, "Also available on" | ✅ |
| Variant price / stock / SKU | ✅ |
| Server-side check of variant/stock at checkout | 🆕 |
| Pincode delivery checker, Q&A, structured data (JSON-LD), backorder/preorder | ⏳ [P2] |

## 5. Cart & checkout
| Item | Status |
|---|---|
| Add/update/remove, stock validation, coupon | ✅ |
| **Re-validate price/stock before order creation** (never trust client prices) | 🆕 cart prices refreshed; customer asked to review if any changed |
| **Atomic stock deduction (no overselling)** | 🆕 `InventoryService` conditional UPDATE |
| **Duplicate-submit / idempotency protection** | 🆕 checkout token (web), `Idempotency-Key` (API) |
| **Online payment via hosted gateway; paid only after server-side verification** | 🆕🔑 Razorpay + Stripe |
| Payment failure / cancel / pending → order kept, "Pay now" retry | 🆕 |
| Per-customer & first-order coupon rules | 🆕 |
| **Guest checkout on the Blade storefront** | ⏳ [P1.5]. Guest orders already work through the Channel API (headless sites) |
| Separate billing address UI, gift message | ⏳ [P2] |
| Free-shipping threshold | ✅ |

## 6. Customer account
| Item | Status |
|---|---|
| Register / login / logout, address book, order history, wishlist, reviews | ✅ |
| **Password reset** (web + API) | 🆕 |
| Profile edit + change password (signs out other devices) | 🆕 |
| **Separate marketing consent with timestamp** (opt-in, unchecked by default) | 🆕 |
| Order timeline, shipment tracking links, invoice download | 🆕 |
| **Cancel order (before packing)** | 🆕 web + API |
| **Request a return (item/qty/reason, within return window)** | 🆕 web + API |
| Email verification, social login, account self-deletion, reorder, store credit | ⏳ [P2]. Deletion requests currently go via support email |

## 7. Order, payment, shipping, returns
| Item | Status |
|---|---|
| **Separate order status vs payment status** (Appendix B) | 🆕 11 order statuses, 7 payment statuses, enforced transitions |
| Human-readable order number + immutable item/price/address snapshot | ✅ (configurable prefix 🆕) |
| Status history with actor & note; internal-only notes | 🆕 |
| Payment transactions, gateway refs, refunds ledger | 🆕 |
| **Webhook signature verification + idempotent processing** | 🆕 (Razorpay HMAC; Stripe signed with 5-minute replay window) |
| Paid-amount ≠ order total → order put **On Hold** | 🆕 |
| COD / bank transfer "Record payment" | 🆕 |
| Full and partial refunds (gateway or manual) with permission | 🆕 |
| Shipments: carrier, AWB, tracking URL, label; split shipments | 🆕🔑 manual + Shiprocket |
| Tracking webhooks + hourly polling; auto-delivered; RTO → Returned | 🆕🔑 |
| Returns: request → approve/reject → receive (optional restock) → refund | 🆕 |
| Packing slip / invoice print | 🆕 |
| Shipping zones / weight-dimension rate tables / pickup | ⏳ [P2]. Flat rate + free threshold today |
| Fraud/risk review queue, chargeback workflow | 🟡 "Disputed" payment status + manual On Hold; no automated scoring |
| Customs / HS codes | ➖ only for international shipping |

## 8. Support, content & trust pages
| Item | Status |
|---|---|
| About, Contact, Shipping, Returns, Privacy, Terms, Payment & Security | 🆕 seeded as **drafts**. ➖ write and legally review the text, then publish |
| CMS editor, footer links, SEO meta per page | 🆕 |
| Order tracking | 🆕 in the account (public "track by order no. + email" page ⏳) |
| **Contact form + support tickets** (ticket ID, category, priority, status, owner, reply history, internal notes, attachments, SLA "first reply" time) | 🆕 storefront `/contact`, customer account, guest email links, customer API, Channel API |
| Spam protection on the contact form | 🆕 rate limit + honeypot (CAPTCHA ⏳) |
| Order lookup from a ticket, macros/canned replies, SLA escalation rules | 🟡 linked order shown on the ticket; macros/escalation ⏳ [P2] |
| Chat / WhatsApp | ⏳ [P2] |

## 9. Admin dashboard & navigation
| Item | Status |
|---|---|
| **Net sales, gross, orders, AOV, refunds with stated definitions + previous-period comparison** | 🆕 |
| **Date-range & sales-channel filters** | 🆕 |
| **"Needs attention" queues** (new, to fulfil, returns, refunds due, on hold, low stock, failed webhooks), each clickable | 🆕 |
| Sales chart (revenue + orders), top products, low stock, latest orders | 🆕 |
| Role-aware widgets | 🆕 |
| **Light / dark theme switch** | 🆕 one-click sun/moon button in the top bar (plus Light/Dark/System in the user menu) |
| Sidebar grouped per Appendix A | 🆕 Sales · Catalog · Inventory · Customers · Marketing · Content · Support · Integrations · Configuration · Administration |
| **Admin UI in the TailAdmin design** (full-height sidebar with icon groups and MENU / SUPPORT / OTHERS headings, ⌘K header search, round theme and notification buttons, avatar + name user menu, Outfit font, brand #465FFF, rounded-2xl cards, "Data Table 3" tables with a Download button) | 🆕 `resources/css/filament/admin/theme.css` |
| In-panel notification bell (new orders, new tickets / replies, low stock) | 🆕 |
| Search/filter/sort on all lists, workflow tabs on Orders/Products/Users | 🆕 |
| CSV export (orders, products), streamed and audited, formula-injection safe | 🆕 |
| CSV **import** with field mapping | ⏳ [P2] |
| Saved views, bulk status update with preview | ⏳ [P2] |

## 10. Product & catalogue management
| Item | Status |
|---|---|
| Products, categories (nested), brands, images, variants, SEO, marketplace links | ✅ |
| Draft/active status | ✅ (`is_active`); scheduled publishing ⏳ |
| Cost price/margin, related/upsell pickers, bundles, digital goods | ⏳ [P2/P3] |
| Change history | 🆕 audit log on every product/variant change |
| Slug-change redirects | ⏳ [P2] |

## 11. Inventory & pricing
| Item | Status |
|---|---|
| Stock per product/variant, low-stock threshold | ✅ |
| **Stock movement ledger** (reason, actor, reference, balance after) | 🆕 includes edits made on the product form |
| **Adjust stock action** (add / remove / stocktake) with reason | 🆕 |
| Low-stock email alert (once, when crossing the threshold) | 🆕 |
| Overselling prevention | 🆕 |
| Multi-warehouse, reserved vs on-hand, backorders, suppliers/POs | ⏳ [P3] |
| Price history / scheduled sale dates / tier pricing | ⏳ [P2]. Price changes are in the audit log |

## 12. Order management (admin)
| Item | Status |
|---|---|
| Search by order no., external ref, email, phone, tracking no., gateway payment id | 🆕 |
| Filters: status, payment status, channel, payment method, date | 🆕 |
| Actions: change status (valid steps only), Ship, Mark delivered, Record payment, Refund, Set payment status (disputes), Copy payment link, Cancel (restocks), Print invoice/packing slip | 🆕 |
| Manual phone orders: same pricing/stock/coupon rules as checkout, with variants | 🆕 |
| Order edits limited to address + internal note (money changes only through audited actions) | 🆕 |

## 13. Customers & CRM
| Item | Status |
|---|---|
| Customer list, addresses, order count | ✅ |
| Customers / Staff tabs, marketing-consent column | 🆕 |
| Guest/channel buyers linked to a customer record by email | 🆕 |
| Segments, tags, LTV, duplicate merge, consent history log | ⏳ [P2] |
| Staff never see passwords or card data | ✅ (card data never touches the app) |

## 14. Promotions
| Item | Status |
|---|---|
| Fixed/percent coupons, min spend, max discount, total limit, dates | ✅ |
| Per-customer limit, first-order-only | 🆕 |
| Automatic discounts, BOGO, category/product inclusion rules, gift cards, abandoned-cart emails, loyalty | ⏳ [P2/P3] |

## 15. CMS
| Item | Status |
|---|---|
| Pages: rich text (sanitised on output), slug, draft/published, scheduled publish, SEO, footer toggle, reorder | 🆕 |
| Banners | ✅ |
| Blog, version history/rollback, media library, redirects manager | ⏳ [P2] |

## 16. Shipping, tax & payment configuration
| Item | Status |
|---|---|
| Shipping methods (flat + free threshold) | ✅ |
| Payment methods. Gateway status and webhook URL shown in the form; secrets only in `.env` | 🆕 |
| **Integration Status page** (which credentials are set, webhook URLs to register) | 🆕 |
| Single global tax % | ✅ + GSTIN on invoices 🆕. ➖ per-category GST/HSN rates need a decision with your CA ⏳ |
| Sandbox vs live separation | 🔑 via `.env` per environment |

## 17. Reports & analytics
| Item | Status |
|---|---|
| Dashboard KPIs, chart, top products, CSV exports | 🆕 |
| Dedicated report pages (by category/brand/region, cohorts, tax export, settlement reconciliation) | ⏳ [P2] |
| GA4 / Meta pixel with consent | ⏳ [P2] |

## 18. Staff, roles, permissions & audit
| Item | Status |
|---|---|
| **Roles**: Owner, Store Manager, Catalogue Manager, Fulfilment, Support, Marketing, Finance, Read-only Analyst | 🆕 (`config/permissions.php`) |
| Least-privilege view/manage per area; refunds, exports and staff management are separate permissions | 🆕 |
| Only staff managers can edit staff; nobody can delete themselves | 🆕 |
| **MFA** (authenticator app + recovery codes), optional or forced (`ADMIN_REQUIRE_MFA`) | 🆕 |
| **Audit log**: CRUD on key records, refunds, exports, settings changes, API key rotation, staff logins/failed logins. Read-only, never logs secrets | 🆕 |
| Admin password reset | 🆕 |

## 19. Technical, security, privacy & accessibility
| Item | Status |
|---|---|
| Laravel 13 / Filament 5 / PHP 8.3+, migrations, queue | ✅ |
| CSRF, ORM queries, Blade escaping, hashed passwords | ✅ |
| **Rate limits** on login/register/reset/checkout/channel API | 🆕 |
| **Security headers** (nosniff, frame options, referrer policy, HSTS on https) + `noindex` on admin/account/cart/checkout/API | 🆕 |
| Webhook signatures + idempotency; encrypted webhook secrets & MFA secrets at rest; hashed API keys | 🆕 |
| sitemap.xml | 🆕 |
| Automated tests | 🆕 42 feature tests (checkout, oversell, idempotency, lifecycle, returns, refunds, channel API, webhooks, permissions, support tickets, admin pages and actions) |
| Monitoring/error tracking, backups & restore drill, CI pipeline, dependency scanning | ⏳ ➖ hosting setup (see `required-resource.txt` §6–7) |
| Accessibility audit (screen reader, contrast) | ⏳ not yet audited |
| Data-deletion workflow, retention periods | ⏳ / ➖ define policy first |

## 20. Integrations & automation
| Item | Status |
|---|---|
| Payment gateways | 🆕🔑 Razorpay, Stripe |
| Courier | 🆕🔑 Shiprocket |
| Email (order placed/confirmed/shipped/delivered/cancelled/refunded, payment received, password reset) | 🆕🔑 SMTP/SES/Postmark/Resend |
| SMS | 🆕🔑 MSG91 (DLT Flow templates) / Twilio |
| Staff alerts: new order, low stock | 🆕 |
| **Connect any external website** (REST API + signed webhooks + delivery log/retry) | 🆕 see `docs/CHANNEL_API.md` |
| Queue retries with backoff, failed-delivery visibility | 🆕 |
| Scheduled jobs: tracking sync (hourly), webhook log pruning (daily) | 🆕 (needs cron) |
| WhatsApp, accounting/ERP, marketplace feeds, helpdesk | ⏳ [P2/P3] |

## 21. Data model
All core entities from the spec exist except supplier/purchase order, gift card/loyalty, blog, redirect, media library and multi-location inventory. Money uses `decimal(10,2)`. Statuses use string enums with transition rules. Order snapshots are immutable. Webhooks are de-duplicated by unique `(provider, event_id)`.

## 22. QA & launch checklist
Automated coverage is listed under §19. Still needed before going live (➖): final prices, stock, weights and images; published policies; live gateway and courier credentials tested end to end in sandbox; sender domain DNS (SPF/DKIM/DMARC); backups and restore test; MFA on staff accounts; remove the demo `admin@funskool.test` / `customer@funskool.test` accounts.

## 23. Optional modules
Marketplace, B2B/wholesale, subscriptions, digital goods, international, POS: ⏳ [P3], none started. The Channel API covers "sell the same catalogue on several websites/apps".

## 24. Rollout status
- **Phase 1 (launch essentials):** done, apart from guest checkout on the Blade storefront and storefront filters.
- **Phase 2:** advanced search/filters, abandoned cart, segments, CSV import, report pages, shipping zones, support macros/escalation.
- **Phase 3:** loyalty, multi-warehouse/purchasing, B2B/marketplace/subscriptions/international.
