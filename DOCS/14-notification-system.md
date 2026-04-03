# GlobexSky — Notification System

## Overview

GlobexSky uses a multi-channel notification system to keep all platform users informed of relevant events. Notifications are always delivered in-app, with email as a configurable fallback for offline users. Push notifications (mobile) and SMS are planned for future phases.

---

## Notification Channels

| Channel | Status | Trigger |
|---------|--------|---------|
| **In-App** | ✅ Always | Every event (badge + toast popup) |
| **Email** | ✅ Configurable | When user is offline or event is critical |
| **Push (Browser/Mobile)** | ⏳ Future (Phase 10) | PWA service worker |
| **SMS** | ⏳ Future (Phase 9) | Critical events only (if SMS configured) |

---

## Online vs Offline Behaviour

| User State | Notification Delivery |
|-----------|----------------------|
| Online (active in last 5 min) | In-app toast popup only |
| Offline (>5 min inactive) | In-app badge queued + email sent |
| Offline (>1 hour) | In-app badge + email (with full context) |
| Critical event (payment, security) | Always send email regardless of online status |

---

## Buyer Notification Events

| Event | In-App | Email | Priority |
|-------|--------|-------|----------|
| Order placed successfully | ✅ | ✅ | High |
| Order status changed | ✅ | ✅ | High |
| Payment confirmed | ✅ | ✅ | High |
| Payment failed | ✅ | ✅ | Critical |
| Shipment tracking updated | ✅ | ✅ | Normal |
| Order delivered | ✅ | ✅ | High |
| Review reminder (3 days after delivery) | ✅ | ✅ | Low |
| Wishlist item price dropped | ✅ | ✅ | Normal |
| Coupon received / expiring | ✅ | ✅ | Low |
| New message received | ✅ | ✅ (if offline) | Normal |
| Dispute status update | ✅ | ✅ | High |
| New device login detected | ✅ | ✅ | Critical |
| Password changed | ✅ | ✅ | Critical |
| Account suspended / warning | ✅ | ✅ | Critical |
| Refund approved | ✅ | ✅ | High |
| Inspection report ready | ✅ | ✅ | High |

---

## Supplier Notification Events

| Event | In-App | Email | Priority |
|-------|--------|-------|----------|
| New order received | ✅ | ✅ | Critical |
| Order cancellation request | ✅ | ✅ | High |
| Product approved / rejected | ✅ | ✅ | High |
| New buyer review | ✅ | ✅ | Normal |
| Payout processed | ✅ | ✅ | High |
| Payout rejected | ✅ | ✅ | High |
| Plan renewal upcoming (7 days) | ✅ | ✅ | Normal |
| Plan payment failed | ✅ | ✅ | Critical |
| Low stock alert | ✅ | ✅ | Normal |
| New buyer inquiry | ✅ | ✅ (if offline) | Normal |
| New RFQ (Request for Quote) | ✅ | ✅ | Normal |
| Commission report ready | ✅ | ✅ | Low |
| KYC status updated | ✅ | ✅ | High |
| Inspection requested on your product | ✅ | ✅ | High |

---

## Admin Notification Events

| Event | In-App | Email | Priority |
|-------|--------|-------|----------|
| New user registration | ✅ | — | Low |
| New supplier application | ✅ | ✅ | Normal |
| Product pending review | ✅ | — | Normal |
| KYC documents submitted | ✅ | ✅ | Normal |
| Payout requested | ✅ | ✅ | High |
| Dispute opened | ✅ | ✅ | High |
| Health check failure | ✅ | ✅ | Critical |
| Revenue milestone reached | ✅ | ✅ | Low |
| Fraud flag triggered | ✅ | ✅ | High |
| Chargeback received | ✅ | ✅ | Critical |
| SSL certificate expiring | ✅ | ✅ | High |

---

## Database Schema

```sql
notifications (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    user_id      INT NOT NULL,
    user_role    ENUM('buyer','supplier','admin','carrier','inspector'),
    type         VARCHAR(64),  -- e.g., 'order_placed', 'payout_ready'
    title        VARCHAR(255),
    body         TEXT,
    link         VARCHAR(512), -- deep link URL
    is_read      TINYINT(1) DEFAULT 0,
    read_at      DATETIME,
    created_at   DATETIME
)

notification_preferences (
    id, user_id, event_type,
    in_app_enabled TINYINT(1) DEFAULT 1,
    email_enabled  TINYINT(1) DEFAULT 1,
    sms_enabled    TINYINT(1) DEFAULT 0
)
```

---

## In-App Notification UI

| Element | Detail |
|---------|--------|
| Badge | Red circle with unread count on bell icon (top nav) |
| Dropdown | Click bell → shows latest 10 notifications |
| Toast | Bottom-right popup for real-time events (3 sec auto-dismiss) |
| Notification page | `/notifications` — full history with filters |
| Mark all read | One-click button |
| Deep links | Each notification links to relevant page |

---

## Email Notification Templates

Email notifications use HTML templates stored in `/templates/email/`:

| Template | Event |
|----------|-------|
| `order-placed.html` | Buyer order confirmation |
| `order-shipped.html` | Shipment notification with tracking |
| `order-delivered.html` | Delivery confirmation + review request |
| `new-order-supplier.html` | Supplier new order alert |
| `payout-processed.html` | Payout confirmation |
| `password-changed.html` | Security alert |
| `kyc-approved.html` | KYC verification result |
| `health-alert.html` | Admin system health failure |

---

## Notification Preferences

Users can manage their notification preferences at:
- Buyer: `/account/notifications`
- Supplier: `/supplier/settings/notifications`
- Admin: `/admin/settings/notifications`

Some notifications (security, payment) cannot be disabled.

---

## Implementation Notes

- Phase 1: In-app notifications with DB + AJAX polling (badge count every 30s).
- Phase 5: Socket.io real-time notifications (instant delivery when user is online).
- Phase 10: PWA push notifications (service worker).
