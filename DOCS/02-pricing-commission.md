# GlobexSky — Pricing & Commission System

GlobexSky has **6 distinct revenue streams**. Each is independently configurable by the admin.

---

## Revenue Stream 1 — Transaction Commission

Every completed order generates a commission for GlobexSky.

### Tiered Rates (by cumulative GMV)

| Tier | GMV Range | Base Rate |
|------|-----------|-----------|
| Starter | $0 – $1,000 | 12% |
| Growth | $1,001 – $10,000 | 10% |
| Scale | $10,001 – $50,000 | 8% |
| Enterprise | $50,001+ | 6% |

### Category Overrides

| Category | Rate |
|----------|------|
| Electronics | 8% |
| Fashion & Apparel | 15% |
| Industrial Equipment | 6% |
| Raw Materials | 5% |
| Food & Agriculture | 10% |
| Documents & Services | 3% |
| Home & Living | 12% |
| Health & Beauty | 14% |

### Plan Discounts

| Supplier Plan | Commission Discount |
|--------------|---------------------|
| Free | 0% |
| Pro | 15% off base rate |
| Enterprise | 30% off base rate |

### Commission Formula

```
effective_rate = tier_rate
if category_override exists: effective_rate = category_override
effective_rate = effective_rate × (1 - plan_discount)
commission = order_subtotal × effective_rate
```

**Example:**  
A Pro supplier sells $500 of Electronics:  
`effective_rate = 8% × (1 - 0.15) = 6.8%`  
`commission = $500 × 6.8% = $34`

---

## Revenue Stream 2 — Supplier Subscription Plans

| Plan | Price | Billing |
|------|-------|---------|
| Free | $0 | — |
| Pro | $299/month | Monthly / Quarterly / Semi-Annual / Annual |
| Enterprise | $999/month | Monthly / Quarterly / Semi-Annual / Annual |

Duration discounts applied to Pro and Enterprise:

| Duration | Discount | Pro/mo | Enterprise/mo |
|----------|----------|--------|---------------|
| Monthly | 0% | $299 | $999 |
| Quarterly | 10% | $269 | $899 |
| Semi-Annual | 15% | $254 | $849 |
| Annual | 25% | $224 | $749 |

---

## Revenue Stream 3 — Dropshipping Markup Platform Fee

| Fee Type | Rate |
|----------|------|
| Platform fee on dropship orders | 3% of order value |
| Min dropshipper markup | 5% |
| Max dropshipper markup | 300% |

The platform fee is charged to the dropshipper on top of the supplier wholesale price.

---

## Revenue Stream 4 — Carry Service Commission

| Fee Type | Rate |
|----------|------|
| Platform commission on carry jobs | 15% of delivery fee |

Carriers set their delivery fee; GlobexSky takes 15%.

---

## Revenue Stream 5 — Inspection Fees

| Inspection Type | Fee |
|----------------|-----|
| Pre-Production Check | $50 |
| During Production Check | $150 |
| Pre-Shipment Check | $200 |
| Full Inspection Package | $300 |

The fee is paid by the buyer requesting the inspection. GlobexSky retains the platform portion and pays the assigned inspector.

---

## Revenue Stream 6 — API Access Plans

### Bundled API (with Supplier Plans)

| Plan | API Access |
|------|-----------|
| Free | No API access |
| Pro | Basic API (read-only, limited endpoints) |
| Enterprise | Full API (read + write, all endpoints) |

### Standalone API Plans

| Plan | Monthly Fee | Calls/Month |
|------|-------------|-------------|
| Free | $0 | 1,000 |
| Pro | $49 | 100,000 |
| Enterprise | $199 | Unlimited |

### Overage Pricing

| Tier | Per Extra Call |
|------|---------------|
| After free quota | $0.001/call |
| Bulk (>1M/mo) | Negotiated |

---

## Competitor Comparison

| Platform | Commission | Subscription | Dropship Fee | Notes |
|----------|------------|--------------|--------------|-------|
| **GlobexSky** | 6–12% | Free / $299 / $999 | 3% + markup | Full B2B+B2C |
| Alibaba | 0–3% | $2,999+/year | N/A | B2B only |
| Amazon | 6–20% | $39.99/mo (FBA) | N/A | B2C marketplace |
| Shopify | 0% (own store) | $29–$299/mo | App costs | Store builder |
| AliExpress | 5–8% | Free | N/A | Consumer-focus |
| eBay | 3–12.9% | Optional store | N/A | Auction + fixed |
| Etsy | 6.5% + $0.20 listing | $10/mo (optional) | N/A | Handmade focus |

---

## Revenue Summary (Monthly Projections at Scale)

| Stream | Low | Mid | High |
|--------|-----|-----|------|
| Transaction Commissions | $5,000 | $50,000 | $500,000 |
| Supplier Subscriptions | $1,000 | $15,000 | $150,000 |
| Dropshipping Fees | $500 | $5,000 | $50,000 |
| Carry Commission | $200 | $2,000 | $20,000 |
| Inspection Fees | $500 | $5,000 | $25,000 |
| API Access | $200 | $2,000 | $20,000 |
| **Total** | **$7,400** | **$79,000** | **$765,000** |
