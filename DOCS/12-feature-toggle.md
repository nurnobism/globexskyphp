# GlobexSky — Feature Toggle System

## Overview

GlobexSky includes a centrally managed feature toggle system. Any major feature can be switched ON or OFF from the admin panel without code changes or redeployment. This allows gradual rollout, maintenance windows, and regional control.

---

## 28 Feature Toggles

| # | Toggle Key | Default | Description |
|---|-----------|---------|-------------|
| 1 | `user_registration` | ON | Allow new buyer accounts |
| 2 | `supplier_registration` | ON | Allow new supplier applications |
| 3 | `carrier_registration` | ON | Allow new carrier sign-ups |
| 4 | `product_listing` | ON | Allow suppliers to publish products |
| 5 | `cart_checkout` | ON | Allow buyers to complete checkout |
| 6 | `stripe_payment` | ON | Stripe card payments |
| 7 | `cod_payment` | OFF | Cash on delivery payments |
| 8 | `bank_transfer` | ON | Bank transfer payment option |
| 9 | `dropshipping` | OFF | Dropshipping marketplace and imports |
| 10 | `live_streaming` | OFF | Live stream sessions (supplier) |
| 11 | `real_time_chat` | OFF | Socket.io real-time chat |
| 12 | `ai_search` | OFF | DeepSeek AI-powered search |
| 13 | `ai_chatbot` | OFF | AI customer support chatbot |
| 14 | `ai_recommendations` | OFF | AI product recommendations |
| 15 | `inspection_service` | OFF | Quality inspection marketplace |
| 16 | `carry_service` | OFF | Carry service for logistics |
| 17 | `parcel_service` | OFF | Parcel shipping service |
| 18 | `api_platform` | OFF | Third-party API access |
| 19 | `trade_shows` | OFF | Virtual trade show platform |
| 20 | `vr_showroom` | OFF | VR/AR product showroom |
| 21 | `loyalty_program` | OFF | Buyer loyalty points system |
| 22 | `webmail` | OFF | Internal messaging system |
| 23 | `multi_language` | ON | Multi-language UI support |
| 24 | `multi_currency` | ON | Multi-currency display and conversion |
| 25 | `maintenance_mode` | OFF | Show maintenance page to all users |
| 26 | `gdpr_compliance` | ON | Cookie consent + GDPR features |
| 27 | `email_verification` | ON | Require email verify on registration |
| 28 | `sms_verification` | OFF | SMS OTP for phone verification |

---

## Database Schema

```sql
feature_toggles (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    toggle_key    VARCHAR(64) UNIQUE NOT NULL,
    is_enabled    TINYINT(1) DEFAULT 0,
    description   TEXT,
    modified_by   INT,         -- admin user ID
    modified_at   DATETIME,
    created_at    DATETIME
)
```

---

## PHP Helper Function

```php
// includes/feature_toggles.php

function isFeatureEnabled(string $key): bool {
    static $cache = null;

    if ($cache === null) {
        $pdo = getPDO();
        $stmt = $pdo->query("SELECT toggle_key, is_enabled FROM feature_toggles");
        $cache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    return isset($cache[$key]) && (bool)$cache[$key];
}
```

Usage in any PHP file:

```php
if (!isFeatureEnabled('dropshipping')) {
    http_response_code(404);
    include 'pages/errors/feature-disabled.php';
    exit;
}
```

---

## Admin Toggle UI

The admin panel `/admin/settings/feature-toggles` shows:

| Column | Content |
|--------|---------|
| Feature name | Human-readable label |
| Status | Green ON / Red OFF toggle switch |
| Last changed | Timestamp |
| Changed by | Admin username |
| Description | What the feature controls |

Changes take effect immediately (cache cleared on save).

---

## Maintenance Mode

The `maintenance_mode` toggle is special:

- When ON, all frontend pages show a maintenance page.
- Admins and super admins can still access the admin panel.
- Bypassed by IP whitelist (admin-configurable).
- Returns HTTP 503 to search engines.

---

## Audit Log

Every toggle change is recorded in `feature_toggle_audit`:

```sql
feature_toggle_audit (
    id, toggle_key, old_value, new_value,
    changed_by (admin user ID),
    changed_at DATETIME,
    ip_address, user_agent
)
```

---

## Toggle Dependencies

Some toggles have implied dependencies:

| Toggle | Requires |
|--------|----------|
| `ai_search` | DeepSeek API key configured |
| `ai_chatbot` | DeepSeek API key configured |
| `real_time_chat` | Node.js server running |
| `live_streaming` | Node.js + PeerJS server running |
| `stripe_payment` | Stripe keys configured |
| `sms_verification` | SMS provider configured |
| `dropshipping` | `product_listing` must also be ON |
| `carry_service` | `carrier_registration` should be ON |

If a dependency is not met, the admin panel shows a warning but does not block the toggle.

---

## Future Enhancements

- **Role-based toggles**: Enable feature for specific user roles only (e.g., Pro suppliers only).
- **Scheduled toggles**: Auto-enable/disable at a specific datetime (e.g., flash sale).
- **A/B testing**: Enable feature for 50% of users for testing.
- **Region toggles**: Enable feature only for specific countries.
