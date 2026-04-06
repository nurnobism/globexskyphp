# GlobexSky — Complete 53-PR Development Master Plan

> **Definitive breakdown of every PR needed to take GlobexSky from current state to production launch.**
> Each PR is scoped to be independently mergeable, CI-passing, and conflict-free when merged in order.

---

## Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Merged to main |
| 🔄 | PR open / in progress |
| ⏳ | Not started |
| 📦 | Schema migration included |
| 🧪 | Smoke tests updated |
| 🔒 | Security-sensitive |

---

## Phase 1 — Foundation ✅ COMPLETE

> Already merged. Delivered: project scaffolding, 100+ DB tables (`schema.sql` + `schema_v2.sql`), auth system, RBAC (8 roles), CSRF/XSS/SQLi protection, admin panel scaffold, 116+ page stubs, 46+ API stubs, 7 languages, CI/CD, smoke tests, Docker dev environment.

| PR# | Title | Status |
|-----|-------|--------|
| 1 | Foundation: scaffolding, auth, RBAC, schema, CI/CD | ✅ Merged |

---

## Phase 2 — Products, Cart & Checkout (PRs 2–7)

> **Goal:** Full e-commerce flow — product CRUD with variations, cart, checkout, Stripe payments, order management.

| PR# | Title | Files | Schema | Status |
|-----|-------|-------|--------|--------|
| 2 | **Product CRUD — Basic Info & Media Upload** | `api/products.php` (create, update, delete, get, list), `pages/supplier/products/` (add.php, edit.php, list.php), `includes/products.php` (helper), `pages/products/` (browse, detail) | — | 🔄 |
| 3 | **Product Variations & SKU Matrix** | `api/products.php` (variations actions), `pages/supplier/products/variations.php`, `includes/variations.php` (auto-generate SKU matrix, per-SKU price/stock/image/weight) | — | ⏳ |
| 4 | **Category System — 3-Level Hierarchical** | `api/categories.php`, `pages/admin/categories/`, `includes/categories.php` (tree builder, breadcrumb), category filter on browse page | — | ⏳ |
| 5 | **Cart & Wishlist** | `api/cart.php` (add, update, remove, get), `pages/cart.php`, `includes/cart.php` (session + DB merge on login, multi-supplier grouping), `api/wishlist.php`, `pages/account/wishlist.php` | — | ⏳ |
| 6 | **Checkout & Stripe Payment** | `api/checkout.php`, `pages/checkout.php`, `pages/checkout/confirmation.php`, `includes/checkout.php` (order creation, stock deduction), `api/stripe-webhook.php`, Stripe PaymentIntent + 3DS | 📦 `schema_v3.sql` (if needed) | ⏳ |
| 7 | **Order Management — Buyer & Supplier Views** | `pages/account/orders/` (list, detail, tracking), `pages/supplier/orders/` (list, detail, status update), `api/orders.php` (status transitions: pending → processing → shipped → delivered), invoice PDF (TCPDF), bulk status update | — | ⏳ |

---

## Phase 3 — Commission, Plans & Financial Engine (PRs 8–13)

> **Goal:** Revenue system — tiered commission, supplier plans (Free/Pro/Enterprise), payouts, tax, coupons.

| PR# | Title | Files | Schema | Status |
|-----|-------|-------|--------|--------|
| 8 | **Commission Engine — Tiered Rates + Category Overrides** | `includes/commission.php` (calculate: GMV tiers Starter 12%/Growth 10%/Scale 8%/Enterprise 6%, category overrides, plan discounts Free 0%/Pro -15%/Enterprise -30%), `api/admin/commissions.php`, `pages/admin/pricing/commissions.php` | 📦 `schema_v3.sql` (commission_logs) | 🔄 |
| 9 | **Supplier Plans — Free / Pro / Enterprise** | `api/plans.php` (subscribe, upgrade, downgrade, cancel), `pages/supplier/plans.php`, `includes/plans.php` (plan limits enforcement, proration), Stripe Subscription integration, duration discounts (Quarterly 10%, Semi-Annual 15%, Annual 25%) | 📦 plan_subscriptions table | ⏳ |
| 10 | **Add-On Purchases & Invoice System** | `api/addons.php`, `pages/supplier/billing/` (invoices, add-ons), add-on catalog (extra product slots $0.50, images $0.10, boost $5, featured listing $25/week, livestream $10, API overage $1/1K, translation $2/product/language), auto-generated PDF invoices | — | ⏳ |
| 11 | **Payout System — Supplier Withdrawals** | `api/payouts.php`, `pages/supplier/earnings/` (overview, withdrawal, history), `pages/admin/finance/payouts.php` (approve/reject queue), 7-day hold post-delivery, min $50 withdrawal, methods: bank (free), PayPal ($1), Wise ($2) | — | ⏳ |
| 12 | **Tax Calculation Engine** | `includes/tax.php` (mode: fixed/per-country/VAT, default rate, country rates JSON, VAT ID validation), `pages/admin/tax/`, tax line item on checkout, tax report for admin | — | ⏳ |
| 13 | **Coupon & Promotion System** | `api/coupons.php`, `pages/admin/coupons/` (create, manage, analytics), `includes/coupons.php` (types: percentage/fixed/free-shipping, min order, max uses, date range, per-user limit, category/product scope), apply at checkout | — | ⏳ |

---

## Phase 4 — Logistics & Shipping (PRs 14–17)

> **Goal:** Parcel service, carry service, carrier management, tracking.

| PR# | Title | Files | Schema | Status |
|-----|-------|-------|--------|--------|
| 14 | **Shipping Templates & Rate Engine** | `includes/shipping.php` (rate calculation, free shipping threshold, per-zone rates), `pages/supplier/shipping/` (templates: Free 1, Pro 5, Enterprise unlimited), `api/shipping.php`, origin country config, processing time (1–30 days) | 📦 `schema_v4.sql` | 🔄 |
| 15 | **Parcel Service — Carrier Matching & Tracking** | `api/parcel.php`, `pages/admin/carriers/` (manage, zones, rates), `pages/account/tracking.php`, `includes/parcel.php` (carrier matching algorithm, tracking event log), carrier registration flow | — | ⏳ |
| 16 | **Carry Service — Traveler Delivery** | `api/carry.php`, `pages/carry/` (list trips, post trip, match, accept), `includes/carry.php` (trip matching, delivery confirmation), `pages/carrier/dashboard.php`, 15% platform commission on delivery fee | — | ⏳ |
| 17 | **Unified Tracking & Delivery Confirmation** | `pages/account/orders/tracking.php` (unified parcel + carry view), delivery confirmation flow (buyer confirms → 7-day hold starts → payout eligible), auto-confirm after 14 days if no action | — | ⏳ |

---

## Phase 5 — Real-Time Communication (PRs 18–23)

> **Goal:** Socket.io chat, webmail, notification engine, email system.

| PR# | Title | Files | Schema | Status |
|-----|-------|-------|--------|--------|
| 18 | **Socket.io Server & Chat Infrastructure** | `nodejs/server.js` (Socket.io on port 3001, JWT auth, room management), `nodejs/package.json`, `api/chat.php` (10 actions: send, history, rooms, members, read receipts, typing, search, file upload, delete, report), `includes/chat.php` | 📦 `schema_v5.sql` (chat_room_members, chat_read_receipts, chat_files + 8 more tables) | ⏳ |
| 19 | **Chat UI — Inbox, Conversation, Contacts** | `pages/messages/inbox.php`, `pages/messages/conversation.php`, `pages/messages/contacts.php`, real-time typing indicators, read receipts, emoji reactions, file sharing (JPG/PNG/PDF/DOCX/XLSX/ZIP, max 10MB), message search | — | ⏳ |
| 20 | **Webmail System — Internal Messaging** | `api/webmail.php` (16 actions: compose, reply, forward, list, get, delete, move, label, star, mark-read, attachments, contacts, search, drafts, trash, bulk), `pages/webmail/` (inbox, compose, read, sent, drafts, trash, contacts), rich text editor, up to 10 attachments (10MB each, 25MB/thread) | — | ⏳ |
| 21 | **Notification Engine — In-App + Email** | `includes/notifications.php` (engine: 50+ event types for buyer/supplier/admin, channel routing: online→toast, >5min→badge+email, >1h→contextual email, critical→always email), `api/notifications.php`, `pages/notifications/` (list, preferences), badge + dropdown + toast UI, AJAX polling (30s) | — | ⏳ |
| 22 | **Email Templates & PHPMailer Integration** | `includes/mailer.php` (PHPMailer wrapper), `templates/emails/` (order-placed, order-shipped, payout-processed, password-changed, kyc-approved + 10 more), SMTP config from platform_config (AES-256 encrypted), test email from admin | — | ⏳ |
| 23 | **Notification Preferences & System Messages** | `pages/notifications/preferences.php`, `pages/supplier/settings/notifications.php`, `pages/admin/settings/notifications.php`, per-user per-event toggles (in_app/email/sms), security & payment events non-disableable, system message templates (order status, KYC, payout, dispute, inspection) | — | ⏳ |

---

## Phase 6 — Dropshipping Marketplace (PRs 24–27)

> **Goal:** Dropship import, markup, auto-routing, white-label, payment split.

| PR# | Title | Files | Schema | Status |
|-----|-------|-------|--------|--------|
| 24 | **Dropship Catalog & Import Engine** | `includes/dropshipping.php` (import, sync, catalog browse, plan limit check: Free=blocked, Pro=100, Enterprise=unlimited), `api/dropshipping.php` (import, list, sync, remove, catalog), `pages/dropshipping/catalog.php`, `pages/dropshipping/my-imports.php`, cannot import own products | 📦 `schema_v7.sql` (dropship_items, dropship_markups, dropship_orders, dropship_storefronts) | ⏳ |
| 25 | **Dropship Markup & Storefront** | `pages/dropshipping/storefront.php` (customizable template store), `includes/dropshipping.php` (markup engine: 5–300% range), white-label shipping (supplier ships with dropshipper branding), auto-price sync when supplier changes base price | — | ⏳ |
| 26 | **Dropship Order Routing & Payment Split** | `includes/dropship-payment.php` (payment split: 3% platform fee from both sides, 7-day hold post-delivery), auto-route orders to original supplier, `pages/supplier/dropshipping/` (analytics, enable/disable per product) | — | ⏳ |
| 27 | **Dropship Earnings & Supplier Analytics** | `pages/dropshipping/earnings.php`, `pages/supplier/dropshipping/analytics.php`, earnings dashboard (gross markup, platform fee deduction, net), supplier view of who's dropshipping their products, downgrade handling (existing imports stay, new blocked) | — | ⏳ |

---

## Phase 7 — API Platform & Live Streaming (PRs 28–32)

> **Goal:** Public API with keys/rate-limits/webhooks, PeerJS + Jitsi live streaming.

| PR# | Title | Files | Schema | Status |
|-----|-------|-------|--------|--------|
| 28 | **API Platform — Keys, Auth & Rate Limiting** | `api/platform/` (RESTful endpoints for products, orders, inventory, shipping), `includes/api-auth.php` (API key generation, JWT for API, rate limiting: Free 1K/mo, Pro 100K, Enterprise unlimited), `pages/supplier/api/` (key management, usage stats) | 📦 `schema_v8.sql` (api_keys, api_usage_logs, api_webhooks) | ⏳ |
| 29 | **API Webhooks & Documentation** | `includes/webhooks.php` (event dispatch: order.created, order.shipped, product.updated, payment.received, inventory.low), `pages/supplier/api/webhooks.php` (configure endpoints, view logs, test), `pages/api/docs.php` (interactive API documentation) | — | ⏳ |
| 30 | **PeerJS Live Streaming — P2P Video** | `nodejs/peerjs-server.js` (signaling server on port 3001), `api/livestream.php` (schedule, start, end, viewers, products), `pages/livestream/watch.php`, `pages/supplier/livestream/` (go-live, schedule, manage), PeerJS WebRTC for <10 viewers, plan limits: Free ❌, Pro 2/week (1h), Enterprise unlimited (8h) | — | ⏳ |
| 31 | **Jitsi Meet Integration — Scalable Streaming** | Jitsi room creation when viewers ≥10 (auto-switch from PeerJS), recording support, `pages/livestream/watch.php` (unified player), screen share, reactions, product overlay + Buy Now popup, bandwidth auto-adjust | — | ⏳ |
| 32 | **Livestream Schedule, Recordings & Analytics** | `pages/supplier/livestream/schedule.php`, `pages/supplier/livestream/recordings.php`, `pages/supplier/livestream/analytics.php` (peak viewers, engagement, attributed sales), homepage "Live Now" section, buyer notifications for scheduled streams, stream types: product launch, factory tour, trade show, flash sale | — | ⏳ |

---

## Phase 8 — AI / DeepSeek Integration (PRs 33–36)

> **Goal:** AI-powered search, chatbot, recommendations, translation, demand forecasting, smart sourcing.

| PR# | Title | Files | Schema | Status |
|-----|-------|-------|--------|--------|
| 33 | **AI Search — Semantic + Full-Text Hybrid** | `includes/ai-search.php` (DeepSeek embeddings + MySQL FULLTEXT hybrid ranking), `api/search.php` (semantic mode), search results page with AI relevance, feature flag `ai_search`, rate limit 100 calls/min, ~$0.001/call | 📦 `schema_v9.sql` (ai_conversations, ai_messages, ai_search_logs) | ⏳ |
| 34 | **AI Chatbot — Customer Support Assistant** | `includes/ai-chatbot.php` (DeepSeek conversation, context-aware: order history, product catalog, policies), `api/ai-chat.php`, `pages/support/ai-chat.php`, conversation history, escalate to human, feature flag `ai_chatbot` | — | ⏳ |
| 35 | **AI Recommendations & Demand Forecasting** | `includes/ai-recommendations.php` (related products, "customers also bought", personalized homepage, trending prediction), `includes/ai-forecasting.php` (category demand prediction, reorder suggestions), `api/recommendations.php`, feature flags `ai_recommendations` | — | ⏳ |
| 36 | **AI Translation & Smart Sourcing** | `includes/ai-translation.php` (DeepSeek translate product listings, 7 languages, $2/product/language add-on), `includes/ai-sourcing.php` (natural language requirement → supplier matching, auto-RFQ to top 5, price prediction, supplier scoring), `api/ai-sourcing.php`, `pages/sourcing/` | — | ⏳ |

---

## Phase 9 — Advanced Admin & KYC (PRs 37–41)

> **Goal:** KYC verification (L0–L4), inspection service, fraud prevention, disputes, trade shows.

| PR# | Title | Files | Schema | Status |
|-----|-------|-------|--------|--------|
| 37 | **KYC System — Levels L0 to L4** | `api/kyc.php` (submit, review, approve, reject), `pages/supplier/kyc/` (upload documents, status), `pages/admin/kyc/` (review queue, document viewer, approve/reject with reason), L0 browse-only → L1 email+phone (10 products) → L2 business license (full) → L3 video call (verified badge) → L4 auto (100+ orders, 4.5+ rating, gold badge, lower commission) | 📦 `schema_v5_kyc.sql` (kyc_submissions, kyc_documents, kyc_verification_logs) | ⏳ |
| 38 | **Inspection Service — Request, Assign, Report** | `api/inspection.php`, `pages/inspection/` (request, track, view report, dispute), `pages/admin/inspection/` (manage, assign inspector, review), `pages/inspector/` (dashboard, assignments, report builder), types: Pre-Production $50, During $150, Pre-Shipment $200, Full Package $300, inspector payout 80% + expenses, platform keeps 20% | — | ⏳ |
| 39 | **Buyer Fraud Prevention — 6-Layer System** | `includes/fraud-prevention.php` (device fingerprinting max 5 devices, IP analysis VPN/Tor/GeoIP, refund abuse >15%/30d flag, order velocity 10/hr 50/day, payment fraud Stripe Radar + 3DS >$100 + BIN check, KYC-based limits), risk score 0–100 (Low auto, Medium manual 1h, High hold), `pages/admin/fraud/` (flagged orders, IP blocklist, suspicious accounts) | 📦 `schema_v10.sql` (fraud_audit_log, device_fingerprints, ip_blocklist) | ⏳ |
| 40 | **Dispute Resolution System** | `api/disputes.php`, `pages/account/disputes/` (open, evidence upload, track), `pages/admin/disputes/` (mediation tools, resolution), timeline: Day 0 buyer opens (within 15d) → Day 3 supplier responds → Day 6 mediation → Day 11 decision → Day 14 executed (refund/partial/replace/reject), dispute types: not-as-described, not-received, damaged, wrong-item, quantity-short, quality-below-spec | — | ⏳ |
| 41 | **Trade Shows & Advanced Admin Tools** | `pages/admin/trade-shows/` (manage events, booth rental $99–$999), `api/trade-shows.php`, show types (Category 3d, Regional 5d, Annual Global 7d, Flash 24h), 3D lobby, booth booking, appointment system, business card exchange, `pages/admin/analytics/` (platform GMV, users, conversion, activity logs) | 📦 `schema_v11.sql` (trade_shows, trade_show_booths, trade_show_appointments) | ⏳ |

---

## Phase 10 — Internationalization, PWA & SEO (PRs 42–45)

> **Goal:** 50 languages, multi-currency, PWA offline, push notifications, full SEO.

| PR# | Title | Files | Schema | Status |
|-----|-------|-------|--------|--------|
| 42 | **Multi-Language — 50 Languages + RTL Support** | `languages/` (50 JSON/PHP locale files), language switcher component, RTL CSS for Arabic/Hebrew/Farsi/Urdu, admin language management page, translation fallback chain (user locale → EN), per-product multi-language editing (Enterprise) | — | ⏳ |
| 43 | **Multi-Currency — Live Rates + Display** | `includes/currency.php` (exchange rate API integration, auto-update cron), currency switcher, per-product display in user's currency, checkout in seller's currency with conversion notice, 1.5% conversion fee, admin rate management, supported: USD/EUR/GBP/CNY/JPY/AED + 20 more | — | ⏳ |
| 44 | **PWA — Offline Support + Push Notifications** | `manifest.json`, `service-worker.js` (cache-first for static, network-first for API), offline browse/cart, background sync for pending actions, push notification opt-in, `includes/push.php` (Web Push API, no Firebase — self-hosted VAPID), install prompt | — | ⏳ |
| 45 | **SEO — Sitemap, Meta, Schema.org, OG Tags** | `includes/seo.php` (auto-generate meta title/description/canonical per page), `sitemap.xml` generator (products, categories, suppliers, pages), `robots.txt`, JSON-LD structured data (Product, Organization, BreadcrumbList, FAQPage), Open Graph + Twitter Card tags, `pages/admin/seo/` (per-page meta editor), Baidu sitemap submission for China | — | ⏳ |

---

## Phase 11 — Security Hardening & Performance (PRs 46–49)

> **Goal:** OWASP Top 10 compliance, rate limiting, query optimization, asset optimization.

| PR# | Title | Files | Schema | Status |
|-----|-------|-------|--------|--------|
| 46 | **Security Headers & Session Hardening** | `includes/security-headers.php` (CSP with nonce, X-Content-Type-Options, X-Frame-Options DENY, HSTS, Referrer-Policy, Permissions-Policy), `includes/session-security.php` (fingerprinting: User-Agent + IP first 2 octets, idle timeout, regeneration on privilege change), `.htaccess` hardening (block includes/, eval, base64_decode) | — | 🔄 |
| 47 | **Rate Limiting & Input Validation** | `includes/rate-limiter.php` (DB-backed, UNIQUE index on rate_key: login 5/15min, password-reset 3/60min, registration 3/60min, API 60/1min, pages 120/1min), `includes/security-validator.php` (sanitizeString, sanitizeEmail, sanitizeInt, sanitizeUrl, sanitizeFilename, validatePassword, validatePhone, validateFileUpload, detectXss, detectSqlInjection, escapeForJs) | 📦 rate_limits table | 🔄 |
| 48 | **Query Optimizer & Slow Query Logging** | `includes/query-optimizer.php` (per-query µs timing, slow query log threshold 100ms, EXPLAIN helper, index suggestions, 7-day auto-cleanup), recommended indexes: products(status, category, created_at), orders(buyer_id, status), notifications(user_id, is_read), `pages/admin/performance/` (slow query dashboard) | 📦 query_log table | ⏳ |
| 49 | **Asset Optimization & Caching** | `includes/asset-optimizer.php` (CSS/JS minify, cache-busting ?v=filemtime, preload critical, lazy-load images, inline critical CSS), `config/opcache.php` (.user.ini: 128MB, 10K files, 60s revalidate), `.htaccess` mod_expires (CSS/JS 1mo, images 6mo), Gzip mod_deflate, WebP auto-conversion via GD | — | ⏳ |

---

## Phase 12 — Production Deployment (PRs 50–53)

> **Goal:** Go live on Namecheap shared hosting with all systems operational.

| PR# | Title | Files | Schema | Status |
|-----|-------|-------|--------|--------|
| 50 | **Deployment Scripts & Environment Setup** | `deploy/setup.sh` (automated deployment), `deploy/production.env.template`, `deploy/database-setup.sh` (import all 13 schema files in order), `deploy/cron-setup.md`, `deploy/nodejs-setup.md` (PM2 config), `deploy/ssl-checklist.md`, file permissions (uploads 750, .env not web-accessible) | — | ⏳ |
| 51 | **Health Check & Admin Go-Live Panel** | `deploy/health-check.php` (every 5min: DB, SMTP, Stripe, DeepSeek, disk, memory, file perms, SSL days, session handler, cron jobs → 🟢🟡🔴), `pages/admin/go-live.php` (test → go-live button: switches Stripe test→live, debug→off, logs→error-only, platform_status=live), 24h rollback available | — | ⏳ |
| 52 | **Cloudflare, DNS & SSL Configuration** | Cloudflare Free tier setup (140+ PoPs, DDoS, Auto Minify, Brotli, cache rules), DNS A/CNAME records, AutoSSL (Let's Encrypt), HSTS preload submission, cache bypass rules for API/auth endpoints, China-accessible CDN (cdnjs.cloudflare.com → jsDelivr → self-hosted fallback), Bunny Fonts for Google Fonts replacement | — | ⏳ |
| 53 | **Production Smoke Tests & Monitoring** | E2E validation: register → login → browse → cart → checkout → payment → tracking, password reset email flow, KYC upload, Socket.io connection, API endpoints + real key, `deploy/rollback.sh`, UptimeRobot setup, error log monitoring, securityheaders.com check, browser console zero errors, no APP_DEBUG traces | 🧪 | ⏳ |

---

## Database Migration Order

All schema files must be imported in this exact order (CI and production):

```
1.  database/schema.sql          — Core 100+ tables
2.  database/schema_v2.sql       — Phase 1 additions
3.  database/schema_v3.sql       — Commission & checkout tables
4.  database/schema_v4.sql       — Shipping & logistics tables
5.  database/schema_v5.sql       — Chat & messaging tables (11 tables)
6.  database/schema_v7.sql       — Dropshipping tables
7.  database/schema_v8.sql       — API platform tables
8.  database/schema_v5_kyc.sql   — KYC tables + system_settings extensions
9.  database/schema_v9.sql       — AI tables
10. database/schema_v10.sql      — Fraud prevention tables
11. database/schema_v11.sql      — Trade shows & advanced admin tables
12. database/seed.sql            — Demo/seed data
```

> **Note:** There is no `schema_v6.sql` — dropshipping tables are in `schema_v7.sql`. The `schema_v5_kyc.sql` must come after `schema_v5.sql` because it extends `system_settings` with ALTER TABLE.

---

## Feature Toggle Mapping

Each major feature is gated by a toggle in the `feature_toggles` table. PRs must check `isFeatureEnabled($key)` before activating new functionality.

| Toggle Key | Default | Activated By PR# |
|------------|---------|------------------|
| `user_registration` | ON | PR 1 ✅ |
| `supplier_registration` | ON | PR 1 ✅ |
| `carrier_registration` | ON | PR 15 |
| `product_listing` | ON | PR 2 |
| `cart_checkout` | ON | PR 5–6 |
| `stripe_payment` | ON | PR 6 |
| `cod_payment` | OFF | PR 6 |
| `bank_transfer` | ON | PR 6 |
| `dropshipping` | OFF | PR 24 |
| `live_streaming` | OFF | PR 30 |
| `real_time_chat` | OFF | PR 18 |
| `ai_search` | OFF | PR 33 |
| `ai_chatbot` | OFF | PR 34 |
| `ai_recommendations` | OFF | PR 35 |
| `inspection_service` | OFF | PR 38 |
| `carry_service` | OFF | PR 16 |
| `parcel_service` | OFF | PR 15 |
| `api_platform` | OFF | PR 28 |
| `trade_shows` | OFF | PR 41 |
| `vr_showroom` | OFF | Future |
| `loyalty_program` | OFF | Future |
| `webmail` | OFF | PR 20 |
| `multi_language` | ON | PR 42 |
| `multi_currency` | ON | PR 43 |
| `maintenance_mode` | OFF | PR 51 |
| `gdpr_compliance` | ON | PR 1 ✅ |
| `email_verification` | ON | PR 1 ✅ |
| `sms_verification` | OFF | Future |

---

## Revenue Streams by PR

| Revenue Stream | Formula / Rate | Implemented In |
|----------------|---------------|----------------|
| **Transaction Commission** | GMV tier × (1 - plan_discount); Starter 12%, Growth 10%, Scale 8%, Enterprise 6%; category overrides; Pro -15%, Enterprise -30% | PR 8 |
| **Supplier Plans** | Free $0, Pro $299/mo, Enterprise $999/mo; duration discounts up to 25% | PR 9 |
| **Plan Add-Ons** | Product slots $0.50, images $0.10, boost $5, featured $25/wk, livestream $10, API $1/1K, translation $2/product/lang | PR 10 |
| **Dropship Markup Fee** | 3% platform fee (DROPSHIP_FEE_RATE = 0.03); markup range 5–300% | PR 26 |
| **Carry Service Commission** | 15% of delivery fee | PR 16 |
| **Inspection Fees** | Pre-Production $50, During $150, Pre-Shipment $200, Full $300; platform keeps 20% | PR 38 |
| **API Access Plans** | Free 1K/mo, Pro $49 (100K), Enterprise $199 (unlimited) | PR 28 |
| **Trade Show Booths** | $99–$999 per booth; 100% to platform | PR 41 |

---

## User Role Coverage by PR

| Role | Key PRs |
|------|---------|
| **Buyer** | PR 2 (browse), 5 (cart/wishlist), 6 (checkout), 7 (orders), 17 (tracking), 19 (chat), 40 (disputes) |
| **Supplier** | PR 2–3 (products), 7 (order mgmt), 8 (commission), 9 (plans), 11 (payouts), 14 (shipping), 25 (dropship), 30 (livestream), 37 (KYC) |
| **Admin** | PR 4 (categories), 8 (commission config), 11 (payout approval), 12 (tax), 13 (coupons), 37 (KYC review), 39 (fraud), 40 (disputes), 41 (trade shows), 51 (go-live) |
| **Carrier** | PR 15 (parcel), 16 (carry service) |
| **Inspector** | PR 38 (inspection assignments, reports) |
| **Dropshipper** | PR 24–27 (full dropship flow) |
| **API User** | PR 28–29 (API keys, endpoints, webhooks) |

---

## China GFW Compatibility

All PRs must use China-compatible alternatives (no Google services):

| Blocked Service | Replacement | PR# Where Used |
|----------------|-------------|----------------|
| Google reCAPTCHA | hCaptcha | PR 1 ✅ |
| Google Maps | OpenStreetMap + Leaflet.js | PR 15 (carrier tracking) |
| Google Fonts | Self-hosted + Bunny Fonts | PR 1 ✅ |
| Google Analytics | Umami (self-hosted) | PR 45 |
| YouTube Live | PeerJS + Jitsi Meet | PR 30–31 |
| Zoom | Jitsi Meet | PR 31 |
| Google OAuth | WeChat OAuth + email/password | PR 1 ✅ |
| Gmail SMTP | PHPMailer + custom SMTP (Mailgun) | PR 22 |
| Google Translate | DeepSeek AI translation | PR 36 |
| AWS/GCP CDN | Cloudflare Free + self-hosted | PR 52 |

---

## Acceptance Criteria (Every PR)

1. ✅ All PHP files pass `php -l` syntax check
2. ✅ `composer install` succeeds
3. ✅ All schema migrations import without error (in order)
4. ✅ `php tests/smoke.php` passes
5. ✅ No `APP_DEBUG` traces in output
6. ✅ Feature gated by `isFeatureEnabled()` where applicable
7. ✅ RBAC enforced via `requireRole()` / `requireAuth()` on all new pages/APIs
8. ✅ CSRF tokens on all forms
9. ✅ PDO prepared statements on all queries (no string interpolation in SQL)
10. ✅ Output escaped with `e()` / `htmlspecialchars()`
11. ✅ INT UNSIGNED for all IDs and foreign keys (MySQL FK compatibility)
12. ✅ COLLATE utf8mb4_unicode_ci on all new tables

---

## Summary

| Category | Count |
|----------|-------|
| Total PRs | 53 |
| Phases | 12 |
| Schema migrations | 12 files |
| Feature toggles | 28 |
| Revenue streams | 8 |
| User roles | 8 |
| Pages | 116+ |
| API endpoints | 46+ |
| DB tables | 100+ |
| Languages | 7 → 50 |
| Target launch | August 2026 |

---

*This plan is the single source of truth for GlobexSky development sequencing. Each PR should reference this document by PR number.*
