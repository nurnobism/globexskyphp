# GlobexSky — Project Overview

## 1. What Is GlobexSky?

GlobexSky is a **full-stack B2B/B2C e-commerce platform** inspired by Alibaba, built to connect global buyers, suppliers, dropshippers, and carriers. It supports multi-language storefronts, tiered supplier plans, dropshipping automation, a carry/parcel logistics service, live streaming, AI-powered search, and an inspection marketplace.

---

## 2. Technology Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP 8.x (procedural + OOP, PDO, custom MVC-lite) |
| **Database** | MySQL 8.x — 100+ tables |
| **Frontend** | Bootstrap 5, vanilla JS, jQuery |
| **Web Server** | Apache (mod_rewrite, .htaccess) |
| **Real-Time** | Node.js (Socket.io for chat, PeerJS for WebRTC streaming) |
| **AI** | DeepSeek API (search, chatbot, translation, recommendations) |
| **Payments** | Stripe (cards, subscriptions), COD, bank transfer |
| **Email** | PHPMailer + SMTP (configurable: Gmail, Mailgun, etc.) |
| **Auth** | Custom PHP session-based, CSRF tokens, bcrypt passwords |
| **Containerisation** | Docker + docker-compose (dev environment) |
| **CI/CD** | GitHub Actions (lint, schema import, smoke tests) |

---

## 3. Hosting Environment

| Item | Detail |
|------|--------|
| **Provider** | Namecheap Shared Hosting |
| **Control Panel** | cPanel |
| **SSL** | AutoSSL (Let's Encrypt via cPanel) |
| **Storage** | Unmetered SSD |
| **PHP** | 8.x via cPanel PHP Selector |
| **Node.js** | Available via cPanel Node.js Selector (v14–v20) |
| **MySQL** | Unlimited databases |
| **Email** | Unlimited email accounts |
| **Backup** | Weekly automated (cPanel) |
| **CDN** | Cloudflare (optional free tier) |

---

## 4. Scale & Scope

| Metric | Count |
|--------|-------|
| Pages / views | 116+ |
| API endpoints | 46+ |
| Database tables | 100+ |
| Supported languages | 7 (EN, ZH, AR, FR, ES, DE, JA) |
| User roles | 8 |
| Feature toggles | 28 |

---

## 5. User Roles (RBAC)

| Role | Description |
|------|-------------|
| `super_admin` | Full platform control |
| `admin` | Day-to-day operations |
| `supplier` | Lists products, manages orders, earns |
| `dropshipper` | Imports supplier products, sets markup |
| `buyer` | Browses, orders, reviews |
| `carrier` | Accepts carry/parcel jobs |
| `inspector` | Conducts factory quality inspections |
| `api_user` | Third-party API access |

---

## 6. What Is Functional (Implemented)

- ✅ Authentication (register, login, logout, email verify, password reset)
- ✅ RBAC — 8 roles with middleware enforcement (`requireRole()`, `requireAuth()`)
- ✅ Admin panel (users, products, orders, settings, logs)
- ✅ CSRF token generation & validation on all forms
- ✅ XSS prevention (output escaping throughout)
- ✅ Session management (regeneration, timeouts, secure cookies)
- ✅ PDO prepared statements on all DB queries
- ✅ Docker dev environment (`docker-compose up`)
- ✅ CI/CD pipeline (GitHub Actions: lint + schema + smoke tests)
- ✅ Database schema — 100+ tables defined
- ✅ Middleware pipeline (`middleware.php`)
- ✅ UI scaffolding (Bootstrap 5 layouts, sidebar, nav)

---

## 7. What Is Scaffold Only (UI exists, logic not wired)

- ⚠️ Products — CRUD pages exist, DB logic partial
- ⚠️ Cart — session cart exists, checkout flow incomplete
- ⚠️ Orders — pages exist, status flow not complete
- ⚠️ Supplier dashboard — UI exists, earnings/payouts not wired
- ⚠️ Dropshipping — import flow not implemented
- ⚠️ Live Streaming — pages exist, WebRTC not connected
- ⚠️ AI features — pages exist, DeepSeek API not called
- ⚠️ Inspection service — forms exist, assignment logic missing
- ⚠️ API platform — keys exist, rate limiting not enforced
- ⚠️ Carry/Parcel — pages exist, matching logic not implemented
- ⚠️ Trade shows — pages scaffolded only
- ⚠️ VR Showroom — page exists, no 3D assets
- ⚠️ Admin pricing/settings — forms exist, values not enforced in checkout

---

## 8. Not Yet Implemented

- ❌ KYC verification flow (document upload → admin review → approve/reject)
- ❌ Stripe real subscription billing (plan upgrade payments)
- ❌ Real-time Chat (Socket.io server not deployed)
- ❌ Live Streaming (PeerJS/Jitsi not configured)
- ❌ Webmail (internal message system — tables exist, UI not built)
- ❌ SMTP configuration UI in admin panel
- ❌ Actual DeepSeek AI API calls
- ❌ Feature toggle enforcement (toggles exist in DB, not checked in code)
- ❌ Commission calculation on real orders
- ❌ Supplier payout processing

---

## 9. Development Phases

See [ROADMAP.md](ROADMAP.md) for the full 12-phase plan.

| Phase | Focus | Status |
|-------|-------|--------|
| 1 | Foundation, Auth, RBAC, DB, UI | ✅ Done |
| 2 | Products, Cart, Checkout, Stripe | 🔄 In Progress |
| 3 | Commission, Plans, Pricing, Payout | 🔄 In Progress |
| 4 | Parcel, Carry, Tracking, Logistics | 🔄 In Progress |
| 5 | Socket.io Chat, Notifications | ⏳ Planned |
| 6 | Dropshipping Engine | ⏳ Planned |
| 7 | API Platform, Live Streaming | ⏳ Planned |
| 8 | AI (DeepSeek) | ⏳ Planned |
| 9 | Webmail, KYC, Advanced Admin | ⏳ Planned |
| 10 | 50 Languages, Currencies, PWA, SEO | ⏳ Planned |
| 11 | Security Audit, Performance, Testing | ⏳ Planned |
| 12 | Deploy to Namecheap, Go Live 🚀 | ⏳ Planned |
