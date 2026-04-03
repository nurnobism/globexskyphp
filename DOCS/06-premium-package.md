# GlobexSky — Premium Package Pricing Structure

## Base Prices

| Plan | Monthly Price |
|------|--------------|
| Free | $0 |
| Pro | $299/month |
| Enterprise | $999/month |

---

## Duration Discounts

Suppliers can save by committing to longer billing periods.

| Duration | Discount | Pro/month | Pro Total | Enterprise/month | Enterprise Total |
|----------|----------|-----------|-----------|-----------------|-----------------|
| Monthly | 0% | $299 | $299 | $999 | $999 |
| Quarterly (3 mo) | 10% | $269 | $807 | $899 | $2,697 |
| Semi-Annual (6 mo) | 15% | $254 | $1,524 | $849 | $5,094 |
| Annual (12 mo) | 25% | $224 | $2,688 | $749 | $8,988 |

> Discounts are applied to the per-month rate. Full payment is charged upfront for non-monthly periods.

---

## Add-On Products & Services

These are optional extras purchasable on any plan:

| Add-On | Unit | Price |
|--------|------|-------|
| Extra product slots | per product/month | $0.50 |
| Extra image slots | per image/month | $0.10 |
| Product boost (search priority) | per boost | $5.00 |
| Featured listing | per week | $25.00 |
| Extra live stream session | per session | $10.00 |
| API call overage | per 1,000 calls | $1.00 ($0.001/call) |
| Translation | per product × per language | $2.00 |

---

## Service Charges (Platform Operational Fees)

These charges are applied automatically on relevant transactions:

| Service | Rate |
|---------|------|
| Stripe payment processing | 2.9% + $0.30 per transaction |
| Currency conversion fee | 1.5% of transaction value |
| Withdrawal fee (Bank transfer) | $0 (free) |
| Withdrawal fee (PayPal) | $1.00 flat |
| Withdrawal fee (Wise) | $2.00 flat |

---

## Plan Upgrade / Downgrade Rules

### Upgrade (Free → Pro, Free → Enterprise, Pro → Enterprise)
- Effective immediately upon successful Stripe payment.
- Prorated credit applied if upgrading mid-cycle.

### Downgrade (Enterprise → Pro, Enterprise/Pro → Free)
- Scheduled at end of current billing cycle.
- Features restricted at cycle end.
- No refund for remaining days.
- Data retained for 90 days after downgrade.

---

## Auto-Renewal

- All plans auto-renew by default.
- Supplier can cancel auto-renewal from the Plans page.
- Cancellation takes effect at end of current period.
- Stripe subscription is cancelled on platform side; no more charges.

---

## Failed Payment Handling

| Event | Action |
|-------|--------|
| Payment fails (first attempt) | Retry after 3 days |
| Payment fails (second attempt) | Retry after 7 days + email warning |
| Payment fails (third attempt) | Plan reverted to Free, features suspended |
| Features restored | Immediately upon successful payment |

---

## Trial Period (Future Feature)

> Not yet implemented — planned for Phase 9.

| Plan | Trial Period | Credit Card Required |
|------|-------------|---------------------|
| Pro | 14 days | Yes (not charged during trial) |
| Enterprise | 30 days | Yes (not charged during trial) |

---

## Invoices & Billing

- Automatic invoice generated on each payment.
- Downloadable as PDF from supplier billing dashboard.
- Invoice includes: plan, period, amount, tax, Stripe transaction ID.
- Tax applied based on supplier's country (VAT/GST where applicable).
