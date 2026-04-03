# GlobexSky — Complete Feature Specification (500+)

## Overview

This document enumerates all planned features across the three major stakeholder interfaces: Buyer Frontend, Supplier Portal, and Admin Panel.

---

## A. Buyer Frontend Features

### A1. Homepage

| # | Feature |
|---|---------|
| 1 | Hero banner with call to action (configurable via CMS) |
| 2 | Category navigation bar (hierarchical) |
| 3 | Trending products section |
| 4 | Flash deals countdown timer section |
| 5 | Live streams "Live Now" section |
| 6 | AI-powered personalized product recommendations |
| 7 | Featured suppliers section |
| 8 | New arrivals section |
| 9 | Recently viewed products |
| 10 | Multi-language support toggle |
| 11 | Currency switcher |
| 12 | Search bar with autocomplete |

### A2. Product Discovery & Search

| # | Feature |
|---|---------|
| 13 | Full-text search with MySQL FULLTEXT |
| 14 | AI-enhanced semantic search (DeepSeek) |
| 15 | Filter by: category, price range, rating, country, MOQ, shipping |
| 16 | Sort by: price, rating, orders, newest, relevance |
| 17 | Faceted search (multiple filters simultaneously) |
| 18 | Save search query |
| 19 | Search history |
| 20 | Visual search (image-based, future) |

### A3. Product Detail Page

| # | Feature |
|---|---------|
| 21 | High-res image gallery with zoom |
| 22 | Product video player |
| 23 | 360° spin view (Enterprise products) |
| 24 | Variation selector (color, size, material) |
| 25 | Per-variation image switching |
| 26 | Tiered pricing table (MOQ discounts) |
| 27 | Stock indicator |
| 28 | Add to cart button |
| 29 | Add to wishlist |
| 30 | Buy Now button |
| 31 | Compare product (vs up to 4 products) |
| 32 | Q&A section (buyer asks, supplier answers) |
| 33 | Verified buyer reviews with photos |
| 34 | Supplier info panel |
| 35 | Related products |
| 36 | Size chart lightbox |
| 37 | Estimated delivery time |
| 38 | Shipping cost calculator |
| 39 | Return policy display |
| 40 | Share product (social / copy link) |

### A4. Cart & Checkout

| # | Feature |
|---|---------|
| 41 | Session cart + DB cart (merged on login) |
| 42 | Multi-supplier cart (separate shipments) |
| 43 | Variation-aware cart items |
| 44 | Quantity updater |
| 45 | Remove item |
| 46 | Save for later |
| 47 | Coupon code input |
| 48 | Multi-shipping address support |
| 49 | Shipping method selection per supplier |
| 50 | Order summary with itemised breakdown |
| 51 | Tax calculation at checkout |
| 52 | Stripe card payment |
| 53 | COD (Cash on Delivery) — toggle |
| 54 | Bank transfer payment |
| 55 | Payment method save |
| 56 | 3D Secure authentication |
| 57 | Order confirmation page |
| 58 | Order confirmation email |

### A5. Order Management

| # | Feature |
|---|---------|
| 59 | Order history list |
| 60 | Order detail view |
| 61 | Real-time order tracking |
| 62 | Unified tracking (parcel + carry) |
| 63 | Cancel order (within window) |
| 64 | Return/refund request |
| 65 | Upload return evidence (photos) |
| 66 | Write product review |
| 67 | Write supplier review |
| 68 | Open dispute |
| 69 | Download invoice as PDF |
| 70 | Reorder button |
| 71 | Request quality inspection |

### A6. Buyer Account

| # | Feature |
|---|---------|
| 72 | Profile settings (name, avatar, bio) |
| 73 | Address book (multiple shipping addresses) |
| 74 | Saved payment methods |
| 75 | Wishlist management |
| 76 | Product review history |
| 77 | Messages / webmail inbox |
| 78 | Notification preferences |
| 79 | Security settings (password, 2FA) |
| 80 | Login history |
| 81 | Connected devices |
| 82 | GDPR data export |
| 83 | Account deletion request |
| 84 | KYC verification flow |
| 85 | Loyalty points balance |
| 86 | Coupon wallet |
| 87 | Language preference |
| 88 | Currency preference |

---

## B. Supplier Portal Features (50+)

| # | Feature |
|---|---------|
| 101 | Supplier registration and email verification |
| 102 | Business profile setup |
| 103 | KYC document upload |
| 104 | Dashboard overview (stats cards) |
| 105 | Product CRUD (create, edit, delete, clone) |
| 106 | Bulk product upload (CSV/Excel) |
| 107 | Product variation manager |
| 108 | Inventory management |
| 109 | Stock alert configuration |
| 110 | Product categories browser |
| 111 | Order list with filters |
| 112 | Order detail and status update |
| 113 | Print order invoice |
| 114 | Bulk order processing |
| 115 | Earnings dashboard (gross, commission, net) |
| 116 | Payout request |
| 117 | Payout history |
| 118 | Commission breakdown per order |
| 119 | Plan management (current plan, usage) |
| 120 | Upgrade/downgrade plan |
| 121 | Billing history |
| 122 | Supplier public store customisation |
| 123 | Shipping template management |
| 124 | Return policy editor |
| 125 | Buyer inquiry management |
| 126 | RFQ (Request for Quote) management |
| 127 | Live stream scheduling and management |
| 128 | Stream recording library |
| 129 | Dropshipping enable/disable per product |
| 130 | Dropshipping analytics |
| 131 | Product review management |
| 132 | Reply to buyer reviews |
| 133 | Analytics: sales chart |
| 134 | Analytics: top products |
| 135 | Analytics: buyer demographics |
| 136 | Analytics: traffic sources |
| 137 | API key management |
| 138 | API usage statistics |
| 139 | Webhook configuration |
| 140 | Product export (CSV) |
| 141 | Order export (CSV) |
| 142 | Tax invoice download |
| 143 | Notification preferences |
| 144 | Trade show registration |
| 145 | VR showroom management |
| 146 | Featured listing purchase |
| 147 | Product boost purchase |
| 148 | Flash sale participation |
| 149 | Multi-language product editing |
| 150 | Account settings |

---

## C. Admin Panel Features (100+)

| # | Feature |
|---|---------|
| 201 | Admin dashboard (platform overview) |
| 202 | User management (all roles) |
| 203 | User search, filter, sort |
| 204 | User detail view + edit |
| 205 | User suspend / unsuspend |
| 206 | KYC review queue |
| 207 | KYC approve / reject with reason |
| 208 | Product management list |
| 209 | Product approval queue |
| 210 | Product edit (admin override) |
| 211 | Product remove |
| 212 | Order management |
| 213 | Order status override |
| 214 | Order refund processing |
| 215 | Finance dashboard |
| 216 | Revenue by period chart |
| 217 | Commission log |
| 218 | Payout request management |
| 219 | Payout approve / reject |
| 220 | Invoice list |
| 221 | Commission tier configuration |
| 222 | Category commission override |
| 223 | Plan management (create, edit, deactivate) |
| 224 | Category management (hierarchical) |
| 225 | CMS page management |
| 226 | Email template management |
| 227 | Feature toggle panel |
| 228 | Admin configuration panel (10 categories) |
| 229 | Health check dashboard |
| 230 | Go Live switch |
| 231 | Inspection management |
| 232 | Inspector assignment |
| 233 | Inspection report review |
| 234 | Dispute management |
| 235 | Dispute mediation tools |
| 236 | Carrier management |
| 237 | Shipping zone management |
| 238 | Tax rate management |
| 239 | Coupon management |
| 240 | Platform analytics (GMV, users, conversion) |
| 241 | Activity logs |
| 242 | Security audit log |
| 243 | Health check history |
| 244 | Database backup / restore |
| 245 | Notification broadcast (to all users / segment) |
| 246 | API usage monitoring |
| 247 | Rate limit management |
| 248 | SEO meta management (per page) |
| 249 | Sitemap generator |
| 250 | Maintenance mode control |
| 251 | Fraud flag review |
| 252 | IP block list management |
| 253 | Admin role management |
| 254 | Admin 2FA enforcement |
| 255 | System cron job status |
| 256 | Error log viewer |
| 257 | Trade show management |
| 258 | VR showroom moderation |
| 259 | Language management |
| 260 | Currency rate management |
| 261 | Loyalty program configuration |
| 262 | Referral program management |
| 263 | A/B test toggle (future) |
| 264 | Feature flag scheduling (future) |
| 265 | Region-based feature toggles (future) |
