# GlobexSky — Product Upload System (Taobao-Style)

## Overview

GlobexSky's product upload system is modelled on Taobao/Alibaba's detailed listing approach: hierarchical categories, rich media, tiered pricing, a variation matrix that auto-generates SKUs, and shipping configuration. All in a single multi-step form.

---

## Upload Form Steps

1. **Basic Information** — title, category, brand, descriptions
2. **Media** — images, gallery, video, size chart
3. **Pricing** — base price, sale price, currency, MOQ, tiered pricing
4. **Variations** — define types → generate SKU matrix → customize per SKU
5. **Shipping** — weight, dimensions, category, processing time, origin
6. **Settings** — visibility, dropshipping, wholesale, return policy

---

## Step 1 — Basic Information

| Field | Details |
|-------|---------|
| Product Title | 10–150 characters, required |
| Category | Hierarchical 3-level (e.g., Electronics → Phones → Smartphones) |
| Sub-category | Auto-populated from category |
| Brand | Text input with autocomplete |
| Short Description | 50–300 characters, shown in listing card |
| Full Description | Rich text editor (bold, tables, images, HTML) |
| Key Features | Bullet points (up to 10) |
| Tags | Up to 10 tags for search |
| Product Condition | New / Used / Refurbished |

---

## Step 2 — Media

| Media Type | Requirement | Plan Limit |
|-----------|------------|-----------|
| Main image | Min 800×800px, JPG/PNG, ≤5MB | All plans |
| Gallery images | Min 600×600px, ≤5MB each | Free: 3, Pro: 10, Enterprise: 20 |
| Product video | MP4, max 2 min, ≤100MB | Free: ❌, Pro: 1, Enterprise: 3 |
| Size chart | JPG/PNG or PDF | Pro+ only |
| 360° images | Series of images for 3D spin | Enterprise+ |

---

## Step 3 — Pricing

| Field | Details |
|-------|---------|
| Base price | Required, minimum $0.01 |
| Sale price | Optional, must be < base price |
| Currency | USD (displayed, converted at checkout) |
| MOQ | Minimum order quantity (default: 1) |
| Max order quantity | Optional limit per buyer per order |

### Tiered Pricing Table

Suppliers can define quantity-based price breaks:

| Min Qty | Max Qty | Unit Price |
|---------|---------|-----------|
| 1 | 9 | $10.00 |
| 10 | 49 | $9.00 |
| 50 | 199 | $8.00 |
| 200 | — | $7.00 (negotiable) |

Up to 10 tiers supported. Automatically applied at checkout.

---

## Step 4 — Variations

### 4a — Define Variation Types

Supplier defines up to 3 variation dimensions:

| Variation Type | Example Values |
|---------------|---------------|
| Color | Red, Blue, Black, White |
| Size | S, M, L, XL, XXL |
| Material | Cotton, Polyester, Silk |

### 4b — Auto-Generate SKU Matrix

System generates all combinations automatically:

| Color | Size | SKU Auto-Generated |
|-------|------|--------------------|
| Red | S | PRD-001-RED-S |
| Red | M | PRD-001-RED-M |
| Red | L | PRD-001-RED-L |
| Blue | S | PRD-001-BLU-S |
| ... | ... | ... |

### 4c — Customize Per-SKU

For each generated SKU, supplier can set:

| Field | Default | Customizable |
|-------|---------|-------------|
| Price | Base price | Yes |
| Stock quantity | 0 | Yes |
| SKU code | Auto-generated | Yes (override) |
| Image | Main image | Yes (color-specific) |
| Weight | Product weight | Yes |
| Active | Yes | Yes |

---

## Step 5 — Shipping

| Field | Details |
|-------|---------|
| Weight | In kg, required for shipping calculation |
| Dimensions | Length × Width × Height in cm |
| Shipping category | Standard / Fragile / Oversized / Dangerous |
| Processing time | 1–30 days (shown to buyer) |
| Origin country | Country products ship from |
| Shipping templates | Saved rate cards (Free: 1, Pro: 5, Enterprise: Unlimited) |

### Shipping Rate Template

| Zone | Method | Price | Est. Days |
|------|--------|-------|----------|
| Domestic | Standard | $2.00 | 3–5 |
| Asia | Economy | $8.00 | 10–15 |
| Europe | Standard | $15.00 | 7–14 |
| USA | Express | $25.00 | 5–7 |

---

## Step 6 — Settings

| Setting | Options |
|---------|---------|
| Status | Draft / Active / Inactive |
| Dropshipping | Enable / Disable |
| Wholesale | Enable / Disable |
| Return policy | Template or custom text |
| Pre-order | Enable with estimated ship date |
| NSFW / Age-restricted | Flag (triggers admin review) |
| Search index | Include / Exclude |

---

## Product Status Flow

```
Draft → Active (published, visible to buyers)
Active → Inactive (hidden, not deleted)
Active → Pending Review (if admin review triggered)
Pending Review → Active (approved) / Rejected (with reason)
```

---

## Bulk Upload

Pro and Enterprise suppliers can bulk upload products via:
- **CSV template** (downloadable from dashboard)
- **Excel (.xlsx)** spreadsheet
- **API** (Enterprise only)

Required CSV columns: title, category_id, price, stock, description (others optional).  
Up to 500 products per upload for Pro, unlimited for Enterprise.

---

## After Upload

- Product images optimised automatically (WebP conversion, thumbnail generation).
- Product goes to `Active` status immediately (unless admin review required).
- Admin review triggered for: price > $10,000, flagged keywords, NSFW flag.
- Supplier notified by email + in-app when product is live or rejected.
