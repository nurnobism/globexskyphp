# GlobexSky — Admin Configuration System (Test → Go Live)

## Overview

The admin configuration system allows the platform owner to configure all third-party integrations and system settings through the admin panel. Configurations are stored encrypted in the database (not in `.env` files in production), enabling a controlled Test → Go Live switch.

---

## 10 Configuration Categories

### 1. Database & Core
| Setting | Example |
|---------|---------|
| DB host, port, name, user, password | Encrypted |
| App name, URL, timezone | `GlobexSky`, `https://globexsky.com` |
| Debug mode | `true` (test) / `false` (production) |
| Error logging level | `verbose` (test) / `error-only` (production) |

### 2. Payment (Stripe)
| Setting | Example |
|---------|---------|
| Stripe publishable key | `pk_test_...` / `pk_live_...` |
| Stripe secret key | `sk_test_...` / `sk_live_...` |
| Stripe webhook secret | `whsec_...` |
| Currency | `USD` |
| Mode | `test` / `live` |

### 3. Email / SMTP
| Setting | Example |
|---------|---------|
| SMTP host | `smtp.mailgun.org` / `smtp.gmail.com` |
| SMTP port | `587` |
| SMTP username | `noreply@globexsky.com` |
| SMTP password | Encrypted |
| From name | `GlobexSky` |
| From email | `noreply@globexsky.com` |
| Encryption | `TLS` |

### 4. AI (DeepSeek)
| Setting | Example |
|---------|---------|
| DeepSeek API key | `sk-...` (encrypted) |
| Model | `deepseek-chat` |
| Max tokens per request | `2000` |
| AI search enabled | `true` / `false` |
| AI chatbot enabled | `true` / `false` |
| AI translation enabled | `true` / `false` |

### 5. Storage
| Setting | Example |
|---------|---------|
| Storage driver | `local` / `cloudflare_r2` |
| Upload path (local) | `/home/user/public_html/storage/` |
| Cloudflare R2 bucket | `globexsky-media` |
| Max upload size | `10MB` |
| Allowed file types | `jpg,png,pdf,mp4` |

### 6. Security
| Setting | Example |
|---------|---------|
| CSRF token lifetime | `3600` seconds |
| Session lifetime | `7200` seconds |
| Max login attempts | `5` |
| Login lockout duration | `900` seconds (15 min) |
| 2FA enabled | `true` / `false` |
| hCaptcha site key | `...` |
| hCaptcha secret key | `...` (encrypted) |

### 7. Social / OAuth
| Setting | Example |
|---------|---------|
| Google OAuth client ID | `...` |
| Google OAuth client secret | `...` (encrypted) |
| WeChat App ID | `...` |
| WeChat App Secret | `...` (encrypted) |
| Facebook App ID | `...` |
| Login methods enabled | `email,google,wechat` |

### 8. Shipping
| Setting | Example |
|---------|---------|
| Default shipping origin country | `CN` |
| Shipping rate source | `manual` / `easypost` |
| EasyPost API key | `...` (encrypted) |
| Free shipping threshold | `$100` |
| Estimated delivery days | Configurable per zone |

### 9. Tax
| Setting | Example |
|---------|---------|
| Tax calculation mode | `automatic` / `manual` |
| Default tax rate | `0%` |
| Tax inclusive pricing | `false` |
| Country tax rates | JSON table (VAT/GST by country) |
| Tax registration number | e.g., EU VAT ID |

### 10. Localisation
| Setting | Example |
|---------|---------|
| Default language | `en` |
| Available languages | `en,zh,ar,fr,es,de,ja` |
| Default currency | `USD` |
| Date format | `Y-m-d` |
| Number format | `1,234.56` |
| RTL languages | `ar` |

---

## Health Check System

An automated health check runs every 5 minutes (via cron) and verifies all critical system components.

### Health Check Components

| Component | Check | Status Indicators |
|-----------|-------|------------------|
| Database | Connect + `SELECT 1` query | 🟢 / 🟡 / 🔴 |
| SMTP | Test connection to SMTP server | 🟢 / 🟡 / 🔴 |
| Stripe | Retrieve account via API | 🟢 / 🟡 / 🔴 |
| DeepSeek AI | Ping API endpoint | 🟢 / 🟡 / 🔴 |
| Disk space | Check available storage | 🟢 (>20%) / 🟡 (10–20%) / 🔴 (<10%) |
| Memory | PHP memory usage | 🟢 (<70%) / 🟡 (70–90%) / 🔴 (>90%) |
| File permissions | Check writable directories | 🟢 / 🔴 |
| SSL certificate | Days until expiry | 🟢 (>30d) / 🟡 (7–30d) / 🔴 (<7d) |
| Session handler | Test session read/write | 🟢 / 🔴 |
| Cron jobs | Last run timestamps | 🟢 (<10min) / 🟡 (10–60min) / 🔴 (>60min) |

### Health Status Colours

| Colour | Meaning |
|--------|---------|
| 🟢 Green | All checks passing |
| 🟡 Yellow | Warning — non-critical issue |
| 🔴 Red | Critical failure — action required |

---

## Test → Go Live Process

### Step 1: Configure in Test Mode
- All settings saved with test credentials (Stripe test keys, etc.)
- Test transactions visible in dashboard

### Step 2: Run Health Check
- Admin clicks "Run Health Check" in admin panel
- System checks all 10 components
- Results displayed as green/yellow/red

### Step 3: All Green
- All components show 🟢 green
- "Go Live" button becomes active (previously greyed out)

### Step 4: Go Live Button
- Admin clicks "Go Live"
- Confirmation dialog with checklist
- System switches:
  - Stripe test → live keys
  - Debug mode → off
  - Error logging → error-only
  - Sets `platform_status = live` in config table

### Step 5: Production Live
- Platform is now accepting real payments
- Monitoring continues every 5 minutes
- Admin dashboard shows "LIVE" badge

### Rollback
- "Rollback to Test" button available for 24 hours after Go Live
- Switches back to test credentials
- All live transactions preserved (not reversed)

---

## Config Storage

```sql
platform_config (
    id, category, key, value (TEXT encrypted),
    is_secret (bool), last_modified, modified_by
)
```

- Sensitive values (API keys, passwords) stored AES-256 encrypted.
- Decrypted in PHP only at point of use.
- Config values cached in PHP session for performance.
- Audit log: every config change records who changed what and when.
