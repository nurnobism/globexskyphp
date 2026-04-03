# GlobexSky — Buyer Fraud Prevention System

## Overview

GlobexSky employs a multi-layer fraud prevention system to protect against fraudulent buyers, account abuse, payment fraud, and refund scams while minimising friction for legitimate customers.

---

## Layer 1 — Device Fingerprinting

| Rule | Detail |
|------|--------|
| Max devices per account | 5 devices |
| Fingerprint method | Browser fingerprint (canvas, fonts, WebGL, plugins, timezone) |
| Storage | `buyer_devices` table with device hash |
| On new device login | Send email alert to buyer |
| Trigger review | 5+ devices in 30 days → flag for manual review |
| Block condition | Known fraudster fingerprint (admin block list) |

---

## Layer 2 — IP Analysis

| Check | Threshold / Action |
|-------|-------------------|
| VPN / Proxy detection | Flag order as medium risk if VPN detected |
| Tor exit node | Block order automatically |
| GeoIP vs billing address | Mismatch > 500km → increase risk score |
| IP velocity | Same IP, 5+ accounts → flag |
| Country block list | Admin-configurable list of blocked countries |
| IP velocity (orders) | 10+ orders from same IP in 1 hour → manual hold |

---

## Layer 3 — Refund Abuse Detection

| Metric | Threshold | Action |
|--------|-----------|--------|
| Refund rate (30 days) | > 15% | Flag account |
| Refund rate (lifetime) | > 25% | Flag account |
| First warning | Triggered at first flag | In-app + email warning |
| Restricted | Second flag | Limited to pre-approved orders |
| Suspended | Third flag | Account suspended, orders held |

### Refund Abuse Calculation

```
refund_rate_30d = refunded_orders / total_orders (last 30 days)
refund_rate_lifetime = total_refunded / total_orders (all time)
```

---

## Layer 4 — Order Velocity Limits

| Limit | Default | Admin Configurable |
|-------|---------|-------------------|
| Max orders per hour | 10 | Yes |
| Max orders per day | 50 | Yes |
| Max spend (unverified buyers) | $500 | Yes |
| Max spend (L1 KYC buyers) | $5,000 | Yes |
| Max spend (L2+ KYC buyers) | Unlimited | — |

Orders exceeding velocity limits are:
1. Held for manual admin review, OR
2. Require additional verification (email OTP / phone OTP)

---

## Layer 5 — Payment Fraud Prevention

| Method | Detail |
|--------|--------|
| Stripe Radar | Machine-learning fraud detection (built-in) |
| 3D Secure (3DS) | Required for orders > $100 |
| AVS (Address Verification) | Billing address must match card issuer records |
| CVV verification | Required on all card transactions |
| Disposable email blocking | Regex + known domains blocked at registration |
| Card BIN check | Flag prepaid / virtual cards for manual review |
| Chargeback tracking | 3+ chargebacks → account review |

---

## Layer 6 — KYC Incentive for Buyers

Buyers are incentivised (not required) to complete KYC:

| KYC Level | Benefit |
|-----------|---------|
| L0 (none) | $500/day spend limit, slower refunds (7 days) |
| L1 (email + phone) | $5,000/day limit, 5-day refunds, KYC badge |
| L2 (ID verified) | Unlimited spend, 3-day refunds, "Verified Buyer" badge, priority support |

---

## Risk Score System

Each order is assigned a risk score 0–100 at the point of checkout:

| Score Range | Label | Action |
|-------------|-------|--------|
| 0 – 30 | Low Risk | Auto-approve, process immediately |
| 31 – 60 | Standard Risk | Normal processing, Stripe handles |
| 61 – 80 | Medium Risk | Manual review within 1 hour |
| 81 – 100 | High Risk | Order held, buyer contacted, may cancel |

### Risk Score Inputs

| Factor | Max Points |
|--------|-----------|
| New account (< 7 days) | +10 |
| First order ever | +5 |
| VPN detected | +15 |
| GeoIP mismatch | +10 |
| Disposable email | +20 |
| Order > $1,000 | +10 |
| Order velocity flag | +20 |
| Prepaid / virtual card | +15 |
| Refund rate > 15% | +20 |
| Positive review history | −10 |
| L2 KYC verified | −15 |
| 3+ successful orders | −10 |

---

## Admin Fraud Dashboard

| Feature | Detail |
|---------|--------|
| Flagged orders | List with risk scores and flags |
| Blocked IPs | Manage block list |
| Suspicious accounts | Accounts flagged for review |
| Chargeback log | Stripe chargeback history |
| Risk score history | Per-buyer score over time |
| Manual approve/reject | Admin can override any held order |

---

## Audit Log

All fraud-related actions are logged in `fraud_audit_log`:
- Account flags
- Order holds and releases
- IP blocks
- Manual admin overrides
- KYC verification events
