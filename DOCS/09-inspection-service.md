# GlobexSky — Inspection Service

## Overview

GlobexSky offers a third-party quality inspection marketplace. Buyers can hire GlobexSky-verified inspectors to visit supplier factories and report on product quality before shipment. This reduces risk for international B2B buyers purchasing large orders without visiting the factory themselves.

---

## Inspection Flow

```
Buyer submits inspection request (order ID + inspection type)
        │
        ▼
Platform receives request + payment (Stripe)
        │
        ▼
GlobexSky assigns available inspector near factory location
        │
        ▼
Inspector notified (email + in-app)
        │
        ▼
Inspector contacts supplier to schedule factory visit
        │
        ▼
Factory visit conducted (photos, samples, checklists)
        │
        ▼
Inspector submits report via Inspector Dashboard
        │
        ▼
Buyer notified: Report available for review
        │
        ▼
Buyer reviews report + makes decision:
  ├─ ✅ Approve → order proceeds to shipment
  ├─ ⚠️ Conditional Approve → supplier must fix issues, re-inspect
  └─ ❌ Reject → request refund or cancel order
```

---

## Inspection Types & Pricing

| Type | Timing | Price | Duration | What's Covered |
|------|--------|-------|----------|---------------|
| **Pre-Production Check** | Before manufacturing starts | $50 | 2–4 hours | Raw materials, factory capacity, sample approval |
| **During Production Check** | At 30–50% completion | $150 | 4–8 hours | In-progress quality, conformance to specs, defect rate |
| **Pre-Shipment Check** | After 80%+ production complete | $200 | 4–8 hours | Final product quality, packaging, labelling, quantity count |
| **Full Inspection Package** | All 3 stages combined | $300 | Multiple visits | Complete coverage from raw materials to ship-ready |

---

## Inspector Onboarding

| Step | Detail |
|------|--------|
| Registration | Apply as inspector (professional background required) |
| Credential check | GlobexSky reviews qualifications + experience |
| Region assignment | Inspector registered for specific country/cities |
| Activation | Admin activates inspector account |
| Training | Onboarding material + report format guidelines |

---

## Inspector Dashboard

| Section | Features |
|---------|---------|
| **My Assignments** | Current and past inspection jobs |
| **Calendar** | Schedule factory visit dates |
| **Report Builder** | Structured form with sections per inspection type |
| **Photo Upload** | Upload up to 50 photos per report (required) |
| **Video Upload** | Optional video clips (max 5 min, ≤200MB) |
| **Defect Logger** | Log defects by type, severity, count |
| **Checklist** | Pre-defined AQL (Acceptable Quality Level) checklist |
| **Expenses** | Log travel/accommodation (reimbursed by platform) |
| **Earnings** | View earnings per inspection, payout history |

### Report Sections

1. **Order & Product Summary** — SKU, quantity, buyer PO number
2. **Factory Overview** — location, capacity, cleanliness, safety
3. **Raw Materials Check** — source, quality, conformance to specs
4. **Production Process** — observed steps, equipment, worker count
5. **Product Sampling** — AQL sampling plan, pass/fail count
6. **Defect Analysis** — critical / major / minor defect breakdown
7. **Packaging & Labelling** — carton marking, barcodes, language check
8. **Inspector Verdict** — Pass / Conditional Pass / Fail
9. **Photos Gallery** — labelled photos for each section

---

## Buyer Dashboard (Inspection)

| Section | Features |
|---------|---------|
| **Request Inspection** | Select order, inspection type, notes |
| **Track Status** | Pending Assignment → Scheduled → In Progress → Complete |
| **View Reports** | Read inspection report online |
| **Download Report** | PDF export of full report |
| **Dispute** | Raise dispute if inspector was negligent |
| **History** | All past inspections with results |

---

## Supplier View

- Supplier receives notification when an inspection is requested on their products.
- Supplier must provide factory address and contact person to coordinate visit.
- Supplier cannot see the report before it is released to the buyer.
- Supplier can respond/comment on the report after buyer reviews it.

---

## Platform Operations

| Task | Who | Frequency |
|------|-----|-----------|
| Assign inspector | Admin / Auto-assign | On request received |
| Quality check on report | Admin spot-check | 10% of reports |
| Handle inspector disputes | Admin | On complaint |
| Pay inspector | Platform | Weekly batch |
| Refund buyer if inspection fails | Admin | On request + review |

---

## Inspector Payout

| Item | Detail |
|------|--------|
| Inspection fee (gross) | $50 / $150 / $200 / $300 |
| Platform cut | 20% |
| Inspector payout | 80% of fee + approved expenses |
| Payout schedule | Weekly |
| Min payout | $50 |

---

## Future Enhancements

- AI-powered defect detection from uploaded photos (Phase 8)
- Integration with SGS, Bureau Veritas, or TÜV inspector networks
- Buyer insurance option (if inspection passes but product later fails)
- On-site live streaming of inspection to buyer (Phase 7)
