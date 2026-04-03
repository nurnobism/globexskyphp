# GlobexSky — Platform Commission (Detailed)

## Overview

GlobexSky earns a transaction commission on every completed order. The commission system uses a tiered rate based on the supplier's cumulative GMV (Gross Merchandise Value), with category-specific overrides and plan discounts applied on top.

---

## Commission Formula

```
Step 1: Look up tier rate based on supplier's cumulative GMV
Step 2: If category has an override, use category rate instead of tier rate
Step 3: Apply plan discount: effective_rate = base_rate × (1 - plan_discount)
Step 4: commission = order_subtotal × effective_rate
```

### Example Calculations

**Example A — Free plan supplier, Electronics, $500 order:**
- GMV: $2,500 → Growth tier → 10%
- Category override: Electronics → 8%
- Plan discount: Free → 0%
- **Commission = $500 × 8% = $40**

**Example B — Pro plan supplier, Fashion, $1,000 order:**
- GMV: $8,000 → Growth tier → 10%
- Category override: Fashion → 15%
- Plan discount: Pro → 15% off
- Effective rate: 15% × 0.85 = 12.75%
- **Commission = $1,000 × 12.75% = $127.50**

**Example C — Enterprise plan supplier, Raw Materials, $10,000 order:**
- GMV: $55,000 → Enterprise tier → 6%
- Category override: Raw Materials → 5%
- Plan discount: Enterprise → 30% off
- Effective rate: 5% × 0.70 = 3.5%
- **Commission = $10,000 × 3.5% = $350**

---

## GMV Tiers

| Tier | GMV Range | Base Commission Rate |
|------|-----------|---------------------|
| Starter | $0 – $1,000 | 12% |
| Growth | $1,001 – $10,000 | 10% |
| Scale | $10,001 – $50,000 | 8% |
| Enterprise | $50,001 and above | 6% |

GMV is cumulative lifetime sales on the platform (completed orders only, net of refunds).

---

## Category Commission Overrides

| Category | Override Rate | Reason |
|----------|-------------|--------|
| Electronics | 8% | High-value, competitive market |
| Fashion & Apparel | 15% | High margin, high return rate |
| Industrial Equipment | 6% | Low margin, B2B focus |
| Raw Materials | 5% | Commodity, attract suppliers |
| Food & Agriculture | 10% | Perishable, regulated |
| Documents & Services | 3% | Digital goods, low overhead |
| Home & Living | 12% | Mid-margin consumer goods |
| Health & Beauty | 14% | High margin, regulated |
| Other / Uncategorised | 10% | Default fallback |

Category overrides **replace** (not add to) the tier rate.

---

## Supplier Plan Discounts

| Plan | Monthly Fee | Commission Discount |
|------|-------------|---------------------|
| Free | $0 | 0% (no discount) |
| Pro | $299/mo | 15% off effective rate |
| Enterprise | $999/mo | 30% off effective rate |

Discounts are permanent for the duration the supplier holds the plan. Downgrade removes discount at cycle end.

---

## Commission Log

Every commission is recorded in `commission_logs`:

```sql
commission_logs (
    id, order_id, supplier_id,
    order_subtotal, tier_rate, category_rate,
    plan_discount, effective_rate, commission_amount,
    status (pending/settled/refunded),
    created_at, settled_at
)
```

---

## Commission Settlement

| Phase | Timing | Event |
|-------|--------|-------|
| Commission calculated | At order placement | Stored as `pending` |
| Commission held | During delivery hold | 7 days after delivery |
| Commission settled | After hold period | Moved to platform revenue |
| Commission reversed | If refund approved | Partial or full reversal |

---

## Admin Commission Dashboard

| Widget | Data |
|--------|------|
| Total commission today | Sum of settled commissions |
| Total commission this month | Monthly chart |
| Top commission sources | By supplier, by category |
| Pending commission | In hold period |
| Refunded commission | Reversed this month |
| Commission by plan | Free / Pro / Enterprise breakdown |

---

## Competitor Comparison

| Platform | Commission Range | Notes |
|----------|-----------------|-------|
| **GlobexSky** | 3.5–12% (effective with plan) | Tiered, category, plan discounts |
| Alibaba | 0–3% | B2B focus, mainly membership revenue |
| Amazon | 6–20% | Category-based, no plan discount |
| AliExpress | 5–8% | Consumer focus |
| eBay | 3–12.9% | Final value fee, category-based |
| Etsy | 6.5% + listing fee | Handmade/vintage focus |
| Shopify | 0% (own store) | Revenue from subscriptions |
| Wish | 15% | Higher due to trust issues |

GlobexSky's effective rates for Enterprise plan suppliers are **competitive with or below Alibaba** while providing B2C marketplace features Alibaba lacks.

---

## Admin Configuration

Admins can adjust commission rates from:  
`/admin/pricing/commissions`

| Configurable | Change Type |
|-------------|------------|
| Tier rates (4 tiers) | Percentage, admin-editable |
| Category overrides | Per-category, add/remove/edit |
| Plan discounts | Per plan, percentage |
| Effective from date | Changes apply to future orders only |

All changes are logged with admin ID, timestamp, and old/new values.
