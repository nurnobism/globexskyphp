# GlobexSky — Development Roadmap

## Overview

GlobexSky is developed in 12 structured phases. Each phase builds on the previous, enabling serial merging of feature branches without conflicts. The goal is a fully live B2B/B2C platform by August 2026.

---

## Phase 1 — Foundation ✅ DONE (April 2026)

**Status:** Complete — merged to main

### Included:

- ✅ Project scaffolding (folder structure, autoloading, middleware pipeline)
- ✅ Database schema — 100+ tables defined (`schema.sql` + `schema_v2.sql`)
- ✅ Authentication system (register, login, logout, email verify, password reset)
- ✅ RBAC system — 8 roles with `requireRole()`, `requireAuth()` middleware
- ✅ Session management (regeneration, timeouts, secure cookies)
- ✅ CSRF token generation and validation on all forms
- ✅ XSS prevention (output escaping throughout templates)
- ✅ PDO prepared statements on all DB queries
- ✅ Admin panel scaffolding (sidebar, navigation, user management)
- ✅ Bootstrap 5 UI layouts (buyer, supplier, admin templates)
- ✅ Docker dev environment (`docker-compose.yml`, `Dockerfile`)
- ✅ GitHub Actions CI/CD (PHP lint + schema import + smoke tests)
- ✅ Page scaffolding for 116+ pages (UI exists, backend wired for auth pages)
- ✅ 46+ API endpoint stubs
- ✅ Multi-language file structure (7 languages: EN, ZH, AR, FR, ES, DE, JA)
- ✅ Feature toggle DB table and PHP helper stub
- ✅ Admin configuration DB table structure
- ✅ Smoke test suite (`tests/smoke.php`)

---

## Phase 2 — Products, Cart, Checkout, Stripe 🔄 In Progress

**Status:** PR open, pending merge

### Included:

- 🔄 Product CRUD (create, edit, delete, list) — full backend wired
- 🔄 Product variations (types → SKU matrix → per-SKU customisation)
- 🔄 Product gallery (multiple images, plan limits enforced)
- 🔄 Product categories (hierarchical, 3 levels)
- 🔄 Shopping cart (session + DB, merged on login)
- 🔄 Cart variations-aware
- 🔄 Checkout flow (address → shipping → payment → confirm)
- 🔄 Stripe payment integration (test mode → live mode)
- 🔄 Order creation on successful payment
- 🔄 Order confirmation email via SMTP
- 🔄 Basic order management (buyer view: list, detail, cancel)
- 🔄 COD and bank transfer as alternative payment options
- 🔄 Coupon code field at checkout (stub)

---

## Phase 3 — Commission, Plans, Pricing, Payout 🔄 In Progress

**Status:** PR open, pending merge after Phase 2

### Included:

- 🔄 Commission engine (tiered rates, category override, plan discount)
- 🔄 Commission log table (`commission_logs`) — auto-populate on order
- 🔄 Supplier plan management (Free / Pro / Enterprise)
- 🔄 Plan feature limits enforcement (`includes/plan_limits.php`)
- 🔄 Stripe subscription billing for Pro/Enterprise plans
- 🔄 Duration discounts (monthly / quarterly / semi-annual / annual)
- 🔄 Admin pricing configuration (functional CRUD for rates)
- 🔄 Admin finance dashboard (revenue chart, commission logs)
- 🔄 Supplier earnings dashboard
- 🔄 Payout request system (Bank / PayPal / Wise)
- 🔄 Admin payout approval / rejection
- 🔄 Coupon engine (`includes/coupon_engine.php`)
- 🔄 Tax engine (`includes/tax_engine.php`)
- 🔄 Central price engine (`includes/price_engine.php`)
- 🔄 8 new DB tables (commission_logs, plan_subscriptions, etc.)

---

## Phase 4 — Parcel, Carry Service, Tracking, Logistics 🔄 In Progress

**Status:** PR open, pending merge after Phase 3

### Included:

- 🔄 Parcel service (create shipment, tracking, calculator, addresses)
- 🔄 Carry service (register carrier, manage trips, accept requests)
- 🔄 Carrier matching algorithm (route-based)
- 🔄 Unified order tracking page (parcel + carry)
- 🔄 Admin logistics dashboard (overview, carriers, shipments, rates)
- 🔄 Carry/parcel rate configuration (admin)
- 🔄 10 new DB tables (shipments, tracking_events, carriers, trips, etc.)
- 🔄 Carrier earnings and payout
- 🔄 15% platform commission on carry jobs
- 🔄 Shipment status notifications (buyer + carrier)

---

## Phase 5 — Real-Time Chat, Notifications ⏳ Planned (May 2026)

### Included:

- ⏳ Socket.io Node.js server deployment
- ⏳ Real-time chat (buyer ↔ supplier, buyer ↔ support)
- ⏳ Chat room types (order, inquiry, support, RFQ)
- ⏳ Typing indicator, read receipts, online status
- ⏳ File sharing in chat (images, PDFs)
- ⏳ Pusher fallback integration
- ⏳ AJAX polling tertiary fallback
- ⏳ Real-time notification delivery (Socket.io)
- ⏳ In-app toast notifications (live push)
- ⏳ Notification preferences per user
- ⏳ Email notification templates (all event types)
- ⏳ Notification badge count (real-time update)

---

## Phase 6 — Dropshipping Engine ⏳ Planned (May 2026)

### Included:

- ⏳ Dropship marketplace (supplier products with `allow_dropshipping=true`)
- ⏳ One-click product import for dropshippers
- ⏳ Markup configuration (percentage or fixed price)
- ⏳ Plan limits enforcement (Free=❌, Pro=100, Enterprise=unlimited)
- ⏳ Auto-route order to original supplier on purchase
- ⏳ White-label shipping label generation
- ⏳ Payment split logic (platform → supplier + dropshipper)
- ⏳ Dropshipper dashboard (imports, orders, earnings, storefront)
- ⏳ 3% platform fee on dropship orders
- ⏳ DB tables: dropship_items, dropship_orders, dropship_storefronts

---

## Phase 7 — API Platform, Live Streaming ⏳ Planned (June 2026)

### Included:

- ⏳ API key generation and management
- ⏳ API rate limiting (per plan: Free/Pro/Enterprise)
- ⏳ API endpoints (products, orders, inventory, webhooks)
- ⏳ API usage logging and analytics
- ⏳ Webhook system (notify external systems on events)
- ⏳ PeerJS WebRTC streaming server setup
- ⏳ Jitsi Meet self-hosted deployment
- ⏳ Live stream scheduling, start, manage
- ⏳ Buyer stream watch page (video + chat + products)
- ⏳ Product overlay during stream (Buy Now popup)
- ⏳ Stream plan limits enforcement
- ⏳ Stream recording library

---

## Phase 8 — AI Features (DeepSeek) ⏳ Planned (June 2026)

### Included:

- ⏳ DeepSeek API integration (`includes/ai.php`)
- ⏳ AI-powered search (semantic product search)
- ⏳ AI chatbot (buyer support assistant)
- ⏳ AI product recommendations (personalised)
- ⏳ AI translation (product listings → multiple languages)
- ⏳ AI demand forecasting (supplier analytics)
- ⏳ AI smart sourcing (requirement → supplier matching)
- ⏳ AI price prediction for RFQ
- ⏳ AI feature toggles respected (`ai_search`, `ai_chatbot`, etc.)
- ⏳ DeepSeek API cost tracking in admin dashboard

---

## Phase 9 — Webmail, KYC, Advanced Admin ⏳ Planned (July 2026)

### Included:

- ⏳ Internal webmail system (inbox, compose, threads, attachments)
- ⏳ Access rules by role
- ⏳ System-generated messages in inbox
- ⏳ KYC flow: L1 → L2 → L3 document upload
- ⏳ Admin KYC review queue (approve / reject / request more info)
- ⏳ KYC status update notifications
- ⏳ Advanced admin: fraud flag review dashboard
- ⏳ IP block list management
- ⏳ Risk score history per buyer
- ⏳ Supplier rating system (weighted by recency)
- ⏳ Dispute mediation tools (admin)
- ⏳ Trade show management (admin)
- ⏳ Inspection service full implementation

---

## Phase 10 — Languages, Currencies, PWA, SEO ⏳ Planned (July 2026)

### Included:

- ⏳ 50 languages support (translation files)
- ⏳ Multi-currency display (live exchange rates)
- ⏳ Currency conversion in checkout
- ⏳ PWA manifest + service worker (offline support)
- ⏳ Push notification via PWA service worker
- ⏳ SEO meta management per page (admin CMS)
- ⏳ Sitemap generator (XML)
- ⏳ Canonical URLs
- ⏳ Open Graph tags for social sharing
- ⏳ Structured data (JSON-LD) for products
- ⏳ Baidu sitemap submission
- ⏳ RTL layout support (Arabic, Persian, Hebrew)
- ⏳ Loyalty program full implementation
- ⏳ Referral system

---

## Phase 11 — Security Audit, Performance, Testing ⏳ Planned (Aug 2026)

### Included:

- ⏳ Full security audit (OWASP Top 10 review)
- ⏳ Penetration testing (SQL injection, XSS, CSRF, session hijacking)
- ⏳ Performance testing (load test with Apache JMeter or k6)
- ⏳ PHP OPcache optimisation
- ⏳ Database query optimisation (EXPLAIN analysis, indexes)
- ⏳ Image optimisation audit
- ⏳ Comprehensive unit tests (PHPUnit)
- ⏳ Integration tests (key user flows)
- ⏳ Browser compatibility testing (Chrome, Firefox, Safari, Edge)
- ⏳ Mobile responsiveness audit
- ⏳ Accessibility audit (WCAG 2.1 AA)
- ⏳ GDPR compliance review (cookie consent, data export, deletion)

---

## Phase 12 — Deploy to Namecheap, Go Live 🚀 ⏳ Planned (Aug 2026)

### Included:

- ⏳ Production environment setup on Namecheap
- ⏳ Domain and DNS configuration
- ⏳ AutoSSL (Let's Encrypt) setup
- ⏳ Production `.env` configuration
- ⏳ Database migration to production
- ⏳ Node.js server deployment (Socket.io + PeerJS)
- ⏳ Cloudflare CDN setup (optional)
- ⏳ Stripe live keys configured
- ⏳ Health check: all green ✅
- ⏳ Admin Go Live switch activated
- ⏳ Smoke tests on production
- ⏳ Monitoring setup (UptimeRobot)
- ⏳ Rollback plan documented
- 🚀 **GlobexSky is LIVE!**

---

## Summary Timeline

| Phase | Month | Status |
|-------|-------|--------|
| Phase 1 | April 2026 | ✅ Done |
| Phase 2 | April 2026 | 🔄 In Progress |
| Phase 3 | April 2026 | 🔄 In Progress |
| Phase 4 | April 2026 | 🔄 In Progress |
| Phase 5 | May 2026 | ⏳ Planned |
| Phase 6 | May 2026 | ⏳ Planned |
| Phase 7 | June 2026 | ⏳ Planned |
| Phase 8 | June 2026 | ⏳ Planned |
| Phase 9 | July 2026 | ⏳ Planned |
| Phase 10 | July 2026 | ⏳ Planned |
| Phase 11 | August 2026 | ⏳ Planned |
| Phase 12 | August 2026 | ⏳ Planned / 🚀 Launch |
