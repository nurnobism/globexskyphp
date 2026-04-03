# GlobexSky — Advanced Features (Alibaba-Scale)

## Overview

Beyond standard e-commerce, GlobexSky includes Alibaba-level B2B features for large-scale global trade: virtual trade shows, trade finance, VR showrooms, a loyalty program, and AI-powered smart sourcing.

---

## 1. Virtual Trade Shows

### Concept

A digital B2B exhibition hall where suppliers rent virtual booths and buyers attend to discover products, watch live demos, book appointments, and collect leads.

### Features

| Feature | Detail |
|---------|--------|
| Virtual hall | 3D lobby with booth sections by category |
| Booth rental | Suppliers book and customise their booth |
| 3D layout | Visitors can navigate the hall (WebGL/Three.js) |
| Live demo | Supplier streams live from their booth |
| Appointment booking | Buyers book 1-on-1 slots with supplier |
| Lead collection | Visitors can "drop a business card" (share contact info) |
| Business cards | Digital card exchange with one-click |
| Event calendar | Scheduled events during the show |
| Product showcase | Featured products in the booth |
| Badge scanning | Visitor badge with QR code for lead tracking |

### Trade Show Types

| Type | Duration | Audience |
|------|----------|---------|
| Category Show | 3 days | Buyers interested in a specific category |
| Regional Show | 5 days | Buyers from a specific region/country |
| Annual Global | 7 days | All buyers and suppliers |
| Flash Show | 24 hours | New arrivals or clearance |

### Revenue Model

- Supplier booth rental: $99–$999 depending on booth size and duration.
- GlobexSky takes 100% of booth rental fee.
- During the show, all orders go through normal commission structure.

---

## 2. Trade Finance

### Products Offered

| Product | Detail |
|---------|--------|
| Letter of Credit (LC) | Traditional B2B payment security |
| Trade Credit | Buy now, pay in 30 / 60 / 90 days |
| Escrow | Funds held until delivery confirmed |
| Trade Insurance | Product and payment risk coverage |
| Invoice Factoring | Supplier sells invoice to get early payout |
| Purchase Order (PO) Financing | Finance up to 80% of a PO |
| Multi-currency settlement | Pay in any major currency |

### Trade Credit Terms

| Term | Fee |
|------|-----|
| Net 30 | 1.5% |
| Net 60 | 2.5% |
| Net 90 | 3.5% |

### Escrow Flow

```
Buyer sends funds to GlobexSky escrow
        │
Supplier ships goods
        │
Buyer confirms receipt and quality
        │
Funds released to supplier (minus commission)
        │
If dispute: held pending mediation
```

### Eligibility

- Requires L2+ KYC for buyers.
- Requires L2+ KYC for suppliers.
- Enterprise supplier plan required for LC and Invoice Factoring.

---

## 3. VR Showroom

### Concept

An immersive virtual product showroom accessible via web browser (WebXR) or mobile device (gyroscope-based navigation).

### Features

| Feature | Detail |
|---------|--------|
| 360° product images | Equirectangular photos of product in environment |
| AR try-on | Mobile AR for fashion, accessories, home décor |
| Virtual factory tour | 360° walkthrough of supplier's facility |
| 3D product models | GLB/GLTF format, interactive rotation |
| WebXR | Works in Chrome/Firefox with VR headset |
| Mobile gyroscope | Phone tilt to navigate on mobile |
| Product hotspots | Click points in 360° view to see product details |
| Multi-room showroom | Multiple themed rooms (e.g., by product type) |

### Technical Stack

| Component | Technology |
|-----------|-----------|
| 360° viewer | A-Frame (WebXR framework) |
| 3D models | Three.js + GLTF format |
| AR layer | AR.js (marker-based) |
| Mobile | Responsive with DeviceOrientation API |

### Plan Requirement

VR Showroom access is **Enterprise plan only**.

---

## 4. Loyalty Program

### Overview

Buyers earn points for purchases, reviews, referrals, and account activities. Points can be redeemed for discounts.

### Earning Points

| Action | Points Earned |
|--------|--------------|
| Purchase | 1 point per $1 spent |
| Write a product review | 50 points |
| Refer a new buyer | 500 points |
| Complete KYC L1 | 100 points |
| Complete KYC L2 | 300 points |
| Account birthday | 200 points |
| Flash sale purchase | 2x points |

### Tiers

| Tier | Points Required | Benefits |
|------|----------------|---------|
| Bronze | 0 | Standard |
| Silver | 1,000 | 5% extra loyalty discount |
| Gold | 5,000 | 10% extra discount + free standard shipping |
| Platinum | 20,000 | 15% extra discount + priority support + free express shipping |

### Redemption

| Rate | Detail |
|------|--------|
| 100 points = $1 | Applied as cart discount |
| Min redemption | 100 points |
| Max per order | 50% of order value |
| Expiry | 12 months from earning date |

### Referral System

- Referrer gets 500 points when referred friend completes first order.
- Referred friend gets a $10 welcome coupon.
- Tracked via unique referral link (`/ref/{code}`).

---

## 5. AI Smart Sourcing (DeepSeek)

### Overview

An AI-powered sourcing assistant that helps buyers find the best suppliers for their requirements using natural language input.

### Features

| Feature | Detail |
|---------|--------|
| Requirement matching | Buyer describes product needs in natural language; AI matches to supplier capabilities |
| Auto-RFQ | AI generates and sends RFQ to top 5 matched suppliers |
| Price prediction | AI predicts fair market price range for a sourcing request |
| Supplier scoring | AI scores suppliers by quality, delivery, reviews, KYC level |
| Demand forecasting | Supplier analytics: predict next month's demand by category |
| Reorder suggestions | Buyer's past orders + inventory signals → suggested reorders |

### Technical Implementation

| Component | Detail |
|-----------|--------|
| AI provider | DeepSeek Chat API |
| Embedding | DeepSeek embeddings for semantic search |
| Search index | MySQL FULLTEXT + DeepSeek semantic ranking |
| Cost | ~$0.001 per API call |
| Rate limiting | 100 calls/min (configurable) |
| Toggle | Controlled by `ai_search` and `ai_recommendations` feature flags |
