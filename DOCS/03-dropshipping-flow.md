# GlobexSky — Dropshipping Flow

## Overview

GlobexSky's dropshipping system allows registered dropshippers to import supplier products into their own storefront, set a custom markup, and sell to end customers — all without holding inventory. The platform routes orders automatically to the original supplier for fulfillment with white-label shipping.

---

## End-to-End Flow

```
Supplier lists product (allow_dropshipping = true)
        │
        ▼
Dropshipper browses "Dropship Marketplace"
        │
        ▼
Dropshipper imports product to their store
        │
        ▼
Dropshipper sets markup (5% – 300%)
        │
        ▼
Customer visits dropshipper's storefront and buys
        │
        ▼
Platform receives payment (Stripe / COD / bank)
        │
        ▼
Platform auto-routes order to original supplier
        │
        ▼
Supplier fulfills and ships (white-label: dropshipper's brand)
        │
        ▼
Payment split:
  ├─ Supplier receives: wholesale price − platform 3% fee
  └─ Dropshipper receives: markup amount − 3% platform fee
```

---

## Detailed Step Breakdown

### Step 1 — Supplier Lists Product for Dropshipping

- Supplier sets `allow_dropshipping = true` on their product listing.
- Supplier sets the **wholesale price** (what dropshippers pay).
- Supplier sets **min and max markup** boundaries (optional).
- Supplier can set dropshipping-specific images or descriptions.

### Step 2 — Dropshipper Imports Product

- Dropshipper browses the Dropship Marketplace filtered by category, price, rating, or supplier.
- One-click **Import** button copies product to dropshipper's store.
- Imported product is linked to original supplier product via `dropship_items` table.
- Import limits enforced by plan:
  - Free: ❌ Not allowed
  - Pro: 100 imports maximum
  - Enterprise: Unlimited imports

### Step 3 — Dropshipper Sets Markup

- Dropshipper sets retail price as either:
  - A **percentage markup** (e.g., +30%) over the wholesale price, or
  - A **fixed price** above the cost floor.
- System enforces min 5% / max 300% markup.
- Dropshipper can edit title, description, and images for their store listing.

### Step 4 — Customer Buys from Dropshipper Store

- Customer lands on dropshipper's storefront (custom domain or GlobexSky sub-page).
- Product page shows **retail price** (wholesale + markup).
- No indication to the customer that this is a dropship product.
- Customer adds to cart, checks out via Stripe / COD / bank transfer.

### Step 5 — Platform Processes Payment

- GlobexSky holds the full payment.
- Calculates split:
  - Wholesale cost → reserved for supplier
  - Markup profit → reserved for dropshipper
  - 3% platform fee deducted from both
- Order placed in `orders` table with `source = 'dropship'`.

### Step 6 — Auto-Route to Supplier

- Platform sends a **supplier order notification** with shipping label details.
- Supplier receives order as if it were a direct order, but with dropshipper's brand label.
- Supplier ships directly to the end customer.
- Tracking number fed back into platform → customer sees tracking.

### Step 7 — White-Label Shipping

- Shipping labels use the **dropshipper's brand name** (or GlobexSky default if not configured).
- Invoice / packing slip: dropshipper's logo.
- No reference to the original supplier on any physical material.
- Configurable in dropshipper settings.

### Step 8 — Payment Split & Payout

- After order delivered + hold period (7 days):
  - Supplier payout: wholesale price − 3% platform fee
  - Dropshipper payout: markup profit − 3% platform fee
- Both credited to their respective earnings wallets.
- Withdrawal requested manually (min $50).

---

## Dropshipper Dashboard Features

| Section | Features |
|---------|---------|
| **Import Store** | Browse marketplace, filter, preview, import |
| **My Products** | Manage imported products, edit markup, pause/activate |
| **Orders** | Track dropship orders, supplier status, tracking numbers |
| **Earnings** | Balance, per-order breakdown, pending/paid history |
| **Storefront** | Customize store name, logo, banner (Pro+) |
| **Analytics** | Top products, conversion, revenue chart |
| **Settings** | Brand name for labels, return address, notification prefs |

---

## Plan Requirements

| Feature | Free | Pro | Enterprise |
|---------|------|-----|-----------|
| Dropshipping access | ❌ | ✅ | ✅ |
| Max imports | 0 | 100 | Unlimited |
| Custom storefront | ❌ | Template | Custom domain |
| API access for imports | ❌ | ❌ | ✅ |
| White-label labels | ❌ | ✅ | ✅ |
| Analytics | ❌ | Basic | Advanced |

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `dropship_items` | Links dropshipper's product to original supplier product |
| `dropship_markups` | Stores per-product markup configuration |
| `dropship_orders` | Tracks auto-routed orders |
| `dropship_storefronts` | Dropshipper brand/store configuration |

---

## Business Rules

- A dropshipper cannot import their own products.
- If a supplier removes `allow_dropshipping`, existing imports are not removed but new sales are blocked.
- If supplier's plan downgrades, imports linked to that supplier's products remain active.
- Disputes on dropship orders are mediated by GlobexSky (buyer ↔ platform ↔ supplier chain).
- Returns on dropship orders must be approved by both supplier and platform before refund.
