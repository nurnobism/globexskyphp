# GlobexSky — Supplier System Architecture

## Overview

The supplier system handles the full lifecycle of a business joining GlobexSky as a product lister, from initial registration through KYC verification, daily operations, and plan management.

---

## 1. Supplier Account Lifecycle

```
Registration (email + password + business name)
        │
        ▼
Email Verification (link sent via SMTP)
        │
        ▼
Basic Profile Setup (company info, contact, country)
        │
        ▼
KYC Submission (L1 → L2 → L3 as needed)
        │
        ▼
Admin Review & Approval
        │
        ▼
Active Supplier (can list products, receive orders)
        │
        ▼
Plan Selection (Free → Pro → Enterprise)
```

---

## 2. KYC Levels

| Level | Name | Requirements | Benefits |
|-------|------|-------------|---------|
| **L0** | Unverified | Account created | Browse only, cannot list |
| **L1** | Basic | Email verified + phone verified | Can list up to 10 products |
| **L2** | Business | Business license + government ID | Full product listing, orders |
| **L3** | Premium | Video call with GlobexSky team + factory photos | Verified badge, inspection access, RFQ priority |
| **L4** | Gold | Earned: 100+ orders AND 4.5+ average rating | Gold badge, featured placement, lower commission |

### KYC Document Requirements by Level

| Level | Required Documents |
|-------|-------------------|
| L1 | Phone number (OTP verified) |
| L2 | Business registration certificate, Owner/Director government ID, Business address proof |
| L3 | Video verification call (30 min), Factory/warehouse photos (min 10), 3 trade references |
| L4 | Automatically awarded based on performance metrics |

### KYC Review Process

1. Supplier uploads documents via secure portal.
2. Admin receives notification in admin panel.
3. Admin reviews within 3–5 business days.
4. Decision: Approved / Rejected (with reason) / Request more info.
5. Supplier notified by email and in-app notification.

---

## 3. Supplier Profile Fields

### Company Information
- Company legal name
- Trading name / brand name
- Business type (Manufacturer / Trading Company / Wholesaler / Retailer)
- Year established
- Number of employees
- Annual revenue range
- Factory size (m²)
- Certifications (ISO, CE, FDA, etc.)
- Company description
- Website URL

### Products & Capabilities
- Main product categories (multi-select)
- Primary materials
- Production capacity
- Min order quantity (MOQ)
- Lead time range
- Export markets

### Contact Information
- Primary contact name & title
- Business phone
- WhatsApp / WeChat
- Business email
- Business address (street, city, country, postal code)

### Media
- Company logo
- Banner image
- Factory photos (up to 20)
- Company intro video (Pro+)
- Trade show participation certificates

---

## 4. Supplier Dashboard Sections

| Section | Features |
|---------|---------|
| **Overview** | Welcome card, key stats (products, orders, revenue today/week/month), quick actions |
| **Products** | List, create, edit, delete, bulk upload, stock management, variation management |
| **Orders** | Incoming orders, status update (processing → shipped → delivered), invoice download |
| **Earnings** | Gross revenue, platform commission deducted, net earnings, withdrawal balance |
| **Analytics** | Sales chart, top products, traffic sources, conversion rate, buyer demographics |
| **Reviews** | Customer reviews, reply to reviews, report inappropriate reviews |
| **Messages** | Buyer inquiries, RFQ responses, order-specific chat, support tickets |
| **Plans** | Current plan, usage vs limits, upgrade button, billing history |
| **Livestream** | Schedule, start, manage recordings (Pro+) |
| **Dropshipping** | Enable/disable dropshipping per product, view dropshipper sales |
| **Trade Shows** | Register for virtual trade shows (Enterprise+) |
| **Settings** | Store settings, shipping templates, return policy, notification preferences |

---

## 5. Supplier Store Page (Public)

The supplier's public storefront is accessible at:  
`/supplier/{supplier_slug}` or custom domain (Enterprise)

| Section | Content |
|---------|---------|
| Header | Logo, banner, company name, KYC badge, rating |
| Stats | Years in business, products listed, orders completed, response rate |
| Products | Grid of active products with search/filter |
| About | Company description, certifications, factory photos |
| Reviews | Aggregated buyer reviews with replies |
| Trade Shows | Upcoming/past show participation |
| Contact | Inquiry form, WhatsApp/WeChat link |

---

## 6. Supplier Rating & Badges

| Badge | Criteria | Display |
|-------|---------|---------|
| ✓ Email Verified | L1 KYC | Small tick |
| 🏢 Business Verified | L2 KYC | Blue badge |
| ⭐ Premium Verified | L3 KYC | Gold star badge |
| 🥇 Gold Supplier | L4 earned | Gold crown badge |
| 💎 Pro Plan | Active Pro subscription | Pro ribbon |
| 💼 Enterprise | Active Enterprise subscription | Enterprise ribbon |

---

## 7. Supplier Performance Metrics

| Metric | Target (Good Standing) |
|--------|----------------------|
| On-time shipment rate | > 95% |
| Response time to inquiries | < 24 hours |
| Dispute rate | < 3% of orders |
| Return/refund rate | < 5% of orders |
| Average rating | ≥ 4.0 / 5.0 |

Suppliers falling below thresholds receive warnings, then probation, then suspension.

---

## 8. Supplier Commission & Payout

| Item | Detail |
|------|--------|
| Commission deducted | Per order, based on tier + category + plan |
| Payout hold | 7 days after order delivery confirmed |
| Min withdrawal | $50 |
| Payout methods | Bank transfer, PayPal, Wise |
| Processing time | 3–5 business days |
| Disputed orders | Frozen until resolution |

See [02-pricing-commission.md](02-pricing-commission.md) for rate details.  
See [15-supplier-policies.md](15-supplier-policies.md) for policy rules.
