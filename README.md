# GlobexSky

**GlobexSky** is a Global B2B Trade Platform built with PHP 8.x, MySQL, Bootstrap 5, Socket.io, and Node.js. It provides a comprehensive suite of tools for international trade, dropshipping, supplier management, and buyer-seller communication.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.x (PDO, OOP) |
| Database | MySQL 8.x |
| Frontend | Bootstrap 5, Vanilla JS |
| Real-time | Socket.io, Node.js |
| Payments | Stripe |
| Email | PHPMailer (SMTP) |
| AI | DeepSeek API |
| PWA | Service Worker, Web App Manifest |

---

## Features

- 🛒 **Products & Catalog** — Multi-category product listings with image upload and WebP conversion
- 📦 **Orders & Cart** — Full checkout flow with Stripe payment integration
- 🚚 **Dropshipping** — Supplier import, product sync, earnings split, cron-based automation
- 🔌 **REST API Platform** — API key authentication, SDK (PHP/Python/JS), rate limiting
- 🤖 **AI Search & Content** — DeepSeek-powered product search and content generation
- 💬 **Real-time Chat** — Socket.io + JWT authentication, private messaging
- 📹 **Live Streaming** — WebRTC/PeerJS peer-to-peer streaming
- 🪪 **KYC Verification** — Document upload and admin review workflow
- 🌐 **i18n / Multi-language** — English, Spanish, and more via `lang/` files
- 📱 **PWA** — Installable progressive web app with offline support
- 📍 **Parcel Tracking** — Shipment status API integration
- 🔐 **Security** — CSRF, XSS (CSP nonce), bcrypt, rate limiting, session hardening, security headers

---

## Quick Setup

```bash
# 1. Clone the repository
git clone https://github.com/nurnobism/globexskyphp.git
cd globexskyphp

# 2. Copy and configure environment variables
cp .env.example .env
# Edit .env with your real credentials (DB, Mail, Stripe, etc.)

# 3. Install PHP dependencies
composer install

# 4. Import the database schema
mysql -u your_user -p your_database < database/schema.sql
mysql -u your_user -p your_database < database/schema_v2.sql
mysql -u your_user -p your_database < database/schema_v3.sql
mysql -u your_user -p your_database < database/schema_v4.sql
mysql -u your_user -p your_database < database/schema_v5.sql
mysql -u your_user -p your_database < database/schema_v5_kyc.sql
mysql -u your_user -p your_database < database/schema_v7.sql
mysql -u your_user -p your_database < database/schema_v8.sql
mysql -u your_user -p your_database < database/schema_v9.sql
mysql -u your_user -p your_database < database/schema_v10.sql
mysql -u your_user -p your_database < database/schema_v11.sql

# 5. Install Node.js dependencies and start the real-time server
cd nodejs
npm install
node server.js
```

---

## Environment Variables Reference

| Variable | Description |
|---|---|
| `APP_ENV` | `development` or `production` |
| `APP_DEBUG` | `true` (dev only) or `false` (production) |
| `DB_HOST` | MySQL host (default: `localhost`) |
| `DB_NAME` | MySQL database name |
| `DB_USER` | MySQL username |
| `DB_PASS` | MySQL password |
| `ADMIN_EMAIL` | Default admin email |
| `ADMIN_PASSWORD` | Admin initial password — **change before first run** |
| `MAIL_HOST` | SMTP host |
| `MAIL_PORT` | SMTP port (default: `587`) |
| `MAIL_USERNAME` | SMTP username |
| `MAIL_PASSWORD` | SMTP password |
| `STRIPE_PUBLISHABLE_KEY` | Stripe publishable key |
| `STRIPE_SECRET_KEY` | Stripe secret key |
| `STRIPE_WEBHOOK_SECRET` | Stripe webhook signing secret |
| `JWT_SECRET` | JWT signing secret for Node.js server |
| `INTERNAL_API_KEY` | Internal API key for PHP → Node.js communication |
| `CORS_ORIGIN` | Allowed CORS origin (set to your domain in production) |
| `DEEPSEEK_API_KEY` | DeepSeek AI API key |
| `EXCHANGE_RATE_API_KEY` | Exchange rate API key |

---

## Folder Structure

```
globexskyphp/
├── api/            # REST API endpoints
├── assets/         # CSS, JS, images
├── config/         # Database, mail, app configuration
├── cron/           # Scheduled tasks (dropship sync, etc.)
├── database/       # SQL schema migrations
├── DOCS/           # Project documentation (23+ docs)
├── includes/       # Core PHP includes (auth, helpers, modules)
├── lang/           # i18n translation files
├── nodejs/         # Node.js real-time server (Socket.io)
├── pages/          # Page templates
├── project-anyyesd-1/  # Scratch/idea notes (not production code)
├── sdk/            # API SDKs (PHP, Python, JS)
├── templates/      # Email and HTML templates
├── tests/          # Smoke tests
├── .env.example    # Environment variable reference
└── index.php       # Application entry point
```

> **Note:** The `project-anyyesd-1/` folder contains early-stage idea notes and is not part of the production application.

---

## Deployment

### Prerequisites

| Requirement | Version | Notes |
|---|---|---|
| PHP | >= 8.0 | With extensions: pdo, pdo_mysql, mbstring, json, curl, gd, openssl, fileinfo |
| MySQL / MariaDB | 8.0+ | Database created in cPanel → MySQL Databases |
| Composer | latest | For PHP dependency management |
| Node.js | 18+ LTS | Optional — for real-time chat / video features |
| Apache | 2.4+ | mod_rewrite must be enabled |

**Namecheap cPanel Details:**
- cPanel URL: `https://premium116.web-hosting.com:2083`
- Home directory: `/home/bidybxoc/`
- Project root: `/home/bidybxoc/globexsky.com/`

---

### Quick Start — One-Click Setup

```bash
# 1. Upload files to server via cPanel → File Manager or Git
# 2. Open cPanel → Terminal and run:
cd ~/globexsky.com
bash deploy/setup.sh
```

The setup script will:
- ✅ Check PHP version and required extensions
- ✅ Create `.env` from the production template
- ✅ Test MySQL connection
- ✅ Set correct file permissions (755 dirs / 644 files / 640 .env)
- ✅ Create required upload/storage directories
- ✅ Install Composer dependencies
- ✅ Import the database schema
- ✅ Create the admin user (if not exists)
- ✅ Run the health check

---

### Manual Setup Steps

#### 1. Environment Configuration

```bash
cp deploy/production.env.template .env
nano .env   # Fill in all <FILL_IN> placeholders
```

Key values to set (find in Namecheap cPanel → MySQL Databases):
```env
DB_HOST=localhost
DB_NAME=bidybxoc_globexsky
DB_USER=bidybxoc_globexsky
DB_PASS=your_db_password

APP_URL=https://globexsky.com
APP_ENV=production
APP_DEBUG=false
```

#### 2. Install PHP Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

#### 3. Database Setup

```bash
bash deploy/database-setup.sh
```

Or manually via cPanel → phpMyAdmin, import in this order:
```
database/schema.sql
database/schema_v2.sql
database/schema_v3.sql
database/schema_v4.sql
database/schema_v5.sql
database/schema_v7.sql
database/schema_v8.sql
database/schema_v5_kyc.sql
database/schema_v9.sql
database/schema_v10.sql
database/schema_v11.sql
database/seed.sql
```

#### 4. Create Admin Account

Set `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env`, then run:
```bash
bash deploy/setup.sh    # Admin creation is part of setup
```

Or via MySQL:
```sql
INSERT INTO users (email, password_hash, name, role, admin_role, status, is_verified, is_active, created_at, updated_at)
VALUES ('admin@globexsky.com', '<bcrypt_hash>', 'Admin', 'admin', 'super_admin', 'active', 1, 1, NOW(), NOW());
```

Login at: `https://globexsky.com/pages/auth/login.php`

---

### Cron Jobs Setup

Set up cron jobs in **cPanel → Advanced → Cron Jobs**.
See [`deploy/cron-setup.md`](deploy/cron-setup.md) for exact commands and schedules.

| Job | Schedule |
|---|---|
| Session cleanup | Daily 1 AM |
| Exchange rate update | Every 6 hours |
| Email queue | Every 5 minutes |
| Subscription checks | Daily 2 AM |
| KYC reminders | Daily 10 AM |
| Analytics aggregation | Daily 3 AM |
| Cache cleanup | Every 6 hours |
| Database backup | Daily 4 AM |

---

### Node.js Real-Time Features

The real-time server (`nodejs/server.js`) powers chat and video calls.

For **Namecheap shared hosting**:
1. cPanel → **Software** → **Setup Node.js App**
2. Set Application root to `public_html/nodejs`
3. Set Startup file to `server.js`
4. Add environment variables (see `.env`)

For production with high traffic, consider a separate VPS for the Node.js server.
See [`deploy/nodejs-setup.md`](deploy/nodejs-setup.md) for full instructions.

---

### SSL Setup

1. cPanel → **Security** → **SSL/TLS Status** → **Run AutoSSL**
2. Force HTTPS is already enabled in `.htaccess`
3. Verify at: `https://www.ssllabs.com/ssltest/`

See [`deploy/ssl-checklist.md`](deploy/ssl-checklist.md) for the full security checklist.

---

### Health Check

Verify your deployment is healthy:

```bash
# CLI check
php deploy/health-check.php

# Web check (set HEALTH_CHECK_KEY in .env first)
curl "https://globexsky.com/deploy/health-check.php?key=YOUR_KEY"

# JSON API
curl "https://globexsky.com/deploy/health-check.php?key=YOUR_KEY&format=json"
```

---

### Rollback Procedure

If something goes wrong after deployment:

```bash
# Show recent commits and roll back
bash deploy/rollback.sh

# Roll back to a specific commit
bash deploy/rollback.sh abc1234
```

The rollback script will:
1. Create a database backup
2. Create a code snapshot
3. Restore code files from the target commit
4. Reinstall dependencies

---

### Deploy Folder Reference

| File | Purpose |
|---|---|
| [`deploy/setup.sh`](deploy/setup.sh) | One-click automated deployment script |
| [`deploy/production.env.template`](deploy/production.env.template) | Production `.env` template with Namecheap hints |
| [`deploy/database-setup.sh`](deploy/database-setup.sh) | Database schema import in correct order |
| [`deploy/cron-setup.md`](deploy/cron-setup.md) | Cron job configuration for cPanel |
| [`deploy/nodejs-setup.md`](deploy/nodejs-setup.md) | Node.js / Socket.io / PeerJS server setup |
| [`deploy/ssl-checklist.md`](deploy/ssl-checklist.md) | SSL verification and security headers checklist |
| [`deploy/health-check.php`](deploy/health-check.php) | Post-deploy verification script (HTML + JSON) |
| [`deploy/rollback.sh`](deploy/rollback.sh) | Git-based rollback with automatic DB backup |

---

### Troubleshooting

| Problem | Solution |
|---|---|
| "Invalid email or password" on admin login | Verify `role='admin'` and `admin_role='super_admin'` in users table |
| White page / PHP errors | Set `APP_DEBUG=true` temporarily, check `storage/logs/` |
| Database connection failed | Verify DB credentials in `.env` — Namecheap prefixes db names with cPanel username |
| File upload fails | Run `chmod 755 uploads/` and subdirectories |
| Cron jobs not running | Verify PHP path with `which php` in cPanel Terminal |
| Node.js not connecting | Check `.htaccess` ProxyPass rules; confirm mod_proxy is enabled |
| SSL redirect loop | Ensure `APP_URL=https://...` in `.env` |
| Composer not found | Install via: `curl -sS https://getcomposer.org/installer \| php` |

**Namecheap-Specific Tips:**
- MySQL host is almost always `localhost` (not a remote IP)
- Database and user names are prefixed with your cPanel username (e.g., `bidybxoc_dbname`)
- PHP binary path: `/usr/local/bin/php` or check with `which php`
- Error logs location: cPanel → **Metrics** → **Errors**
- File Manager path: `/home/bidybxoc/globexsky.com/`

---

## Demo Data

The repository ships with a ready-to-run seed file that populates the database with realistic demo data so the homepage, product listing pages, and counters look alive immediately after deployment.

### What gets seeded

| Section | Records | Details |
|---|---|---|
| **Categories** | 10 | Electronics, Machinery, Apparel & Fashion, Home & Garden, Food & Beverage, Chemicals, Automotive, Health & Beauty, Sports & Outdoors, Industrial |
| **Users** | 6 | 1 admin + 3 suppliers + 2 buyers — password **Demo@2026** for all |
| **Suppliers** | 3 | TechVision Electronics (China), Global Machinery Corp (Germany), FashionHub International (Bangladesh) |
| **Products** | 30 | Spread across 6 categories; ~8 featured; realistic prices, MOQs, ratings |
| **Reviews** | 20 | Mix of 4 & 5-star reviews linked to products and buyers |
| **RFQs** | 5 | Mix of open/closed statuses |
| **Orders** | 8 | Mix of pending / confirmed / shipped / delivered |
| **Order Items** | 9 | Linked to the orders above |
| **Wishlist Items** | 5 | Linked to buyer accounts |
| **Cart Items** | 3 | Linked to buyer accounts |

### Demo login credentials

| Role | Email | Password |
|---|---|---|
| Admin | admin@globexsky.com | Demo@2026 |
| Supplier | supplier1@demo.com | Demo@2026 |
| Supplier | supplier2@demo.com | Demo@2026 |
| Supplier | supplier3@demo.com | Demo@2026 |
| Buyer | buyer1@demo.com | Demo@2026 |
| Buyer | buyer2@demo.com | Demo@2026 |

### How to seed

**Option 1 — Shell script** (recommended):

```bash
# Uses DB_USER=root and DB_NAME=globexsky_db by default
bash database/run_seed.sh

# Override with your own credentials:
DB_USER=bidybxoc_globexsky DB_NAME=bidybxoc_globexsky bash database/run_seed.sh
```

**Option 2 — Direct MySQL command**:

```bash
mysql -u <db_user> -p <db_name> < database/seed_demo_data.sql
```

**Option 3 — phpMyAdmin**: import `database/seed_demo_data.sql` via the Import tab.

> The seed file uses `INSERT IGNORE` so it is safe to run multiple times without creating duplicates. All demo rows use IDs ≥ 101 to avoid collisions with existing data.

---

## License

[MIT](LICENSE) — placeholder, update before release.
