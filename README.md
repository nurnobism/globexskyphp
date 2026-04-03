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

### Quick Deploy (3 Steps)

```bash
# 1. Run the automated setup script
bash deploy/setup.sh

# 2. Fill in all values in .env
#    (use deploy/production.env.template as a reference)
nano .env

# 3. Import the database
bash deploy/database-setup.sh
```

### Production Checklist

| Step | Command / Location |
|---|---|
| 1. Environment file | `cp deploy/production.env.template .env` then fill in values |
| 2. PHP dependencies | `composer install --no-dev --optimize-autoloader` |
| 3. Database import | `bash deploy/database-setup.sh` |
| 4. Cron jobs | See [`deploy/cron-setup.md`](deploy/cron-setup.md) |
| 5. Node.js server | See [`deploy/nodejs-setup.md`](deploy/nodejs-setup.md) |
| 6. SSL & security | See [`deploy/ssl-checklist.md`](deploy/ssl-checklist.md) |
| 7. Verify deployment | `php deploy/health-check.php` |
| 8. Rollback if needed | `bash deploy/rollback.sh <commit-sha>` |

### Deploy Folder Reference

| File | Purpose |
|---|---|
| [`deploy/setup.sh`](deploy/setup.sh) | Automated one-click deployment script |
| [`deploy/production.env.template`](deploy/production.env.template) | Production `.env` template with Namecheap hints |
| [`deploy/database-setup.sh`](deploy/database-setup.sh) | Database schema import in correct order |
| [`deploy/cron-setup.md`](deploy/cron-setup.md) | Cron job configuration for cPanel |
| [`deploy/nodejs-setup.md`](deploy/nodejs-setup.md) | Node.js / Socket.io server setup |
| [`deploy/ssl-checklist.md`](deploy/ssl-checklist.md) | SSL verification and security headers |
| [`deploy/health-check.php`](deploy/health-check.php) | Post-deploy verification script |
| [`deploy/rollback.sh`](deploy/rollback.sh) | Git-based rollback with DB backup |

See also [`DOCS/PRODUCTION-CHECKLIST.md`](DOCS/PRODUCTION-CHECKLIST.md) for the full production checklist.

---

## License

[MIT](LICENSE) — placeholder, update before release.
