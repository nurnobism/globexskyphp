# GlobexSky — Production Deployment Checklist

Use this checklist every time you deploy GlobexSky to a production environment.
Check off each item before going live.

---

## 🔴 Critical — Must Complete Before Go-Live

### Application Configuration
- [ ] Copy `.env.example` to `.env` and fill in **all** values with real credentials
- [ ] Set `APP_DEBUG=false` — prevents stack traces from leaking to end users
- [ ] Set `APP_ENV=production` — enables production-mode behaviour
- [ ] Change `ADMIN_PASSWORD` from any default value to a strong, unique password (min 12 chars, mixed case + numbers + symbols)

### Database
- [ ] Create a dedicated MySQL database and user with least-privilege permissions (`SELECT, INSERT, UPDATE, DELETE` only — no `DROP`, `ALTER`, `FILE`)
- [ ] Set `DB_NAME`, `DB_USER`, `DB_PASS` in `.env` to the real production credentials
- [ ] Import all schema migrations in order:
  ```bash
  mysql -u user -p dbname < database/schema.sql
  mysql -u user -p dbname < database/schema_v2.sql
  mysql -u user -p dbname < database/schema_v3.sql
  mysql -u user -p dbname < database/schema_v4.sql
  mysql -u user -p dbname < database/schema_v5.sql
  mysql -u user -p dbname < database/schema_v5_kyc.sql
  mysql -u user -p dbname < database/schema_v7.sql
  mysql -u user -p dbname < database/schema_v8.sql
  mysql -u user -p dbname < database/schema_v9.sql
  mysql -u user -p dbname < database/schema_v10.sql
  mysql -u user -p dbname < database/schema_v11.sql
  ```
- [ ] Verify the admin account exists and the default password has been updated

### Payment Gateway (Stripe)
- [ ] Replace `STRIPE_PUBLISHABLE_KEY` with the **live** key (`pk_live_...`)
- [ ] Replace `STRIPE_SECRET_KEY` with the **live** key (`sk_live_...`)
- [ ] Set `STRIPE_WEBHOOK_SECRET` to the live webhook signing secret from the Stripe Dashboard
- [ ] Register the production webhook endpoint in Stripe Dashboard (e.g. `https://yourdomain.com/api/stripe-webhook.php`)

### Email (SMTP)
- [ ] Set `MAIL_HOST` to your production SMTP server
- [ ] Set `MAIL_USERNAME` and `MAIL_PASSWORD` with real SMTP credentials
- [ ] Set `MAIL_FROM_EMAIL` to the verified sender address
- [ ] Send a test email and confirm delivery

---

## 🟠 High Priority

### Node.js Real-time Server (Socket.io)
- [ ] Set a strong random `JWT_SECRET` (min 32 random characters)
- [ ] Set a strong random `INTERNAL_API_KEY` for PHP → Node.js communication
- [ ] Set `CORS_ORIGIN` to your specific domain (e.g. `https://yourdomain.com`) — **never leave as `*` in production**
- [ ] Start the server with a process manager (PM2 recommended):
  ```bash
  npm install -g pm2
  pm2 start nodejs/server.js --name globexsky-realtime
  pm2 save
  pm2 startup
  ```

### SSL / HTTPS
- [ ] Verify SSL certificate is installed and auto-renews (Namecheap AutoSSL or Let's Encrypt)
- [ ] Confirm all HTTP requests are redirected to HTTPS (`.htaccess` `RewriteRule` is in place)
- [ ] Check HSTS header is active in `security-headers.php`

### File Permissions
- [ ] Set `uploads/` directory permissions to `750` (owner read/write/execute, group read/execute)
- [ ] Set `uploads/kyc/` to `750` — KYC documents must not be publicly browseable
- [ ] Verify `.env` file is **not** web-accessible (`.htaccess` rule blocks it)
- [ ] Verify `config/` directory is **not** web-accessible

### Cron Jobs
- [ ] Schedule dropshipping product sync:
  ```
  0 */6 * * * php /path/to/globexskyphp/cron/sync-dropship-products.php
  ```
- [ ] Schedule any rate-limit reset or cleanup cron jobs as required

---

## 🟡 Medium Priority

### AI Features
- [ ] Set `DEEPSEEK_API_KEY` for AI search and content generation
- [ ] Test AI search returns results and fails gracefully if the API is unavailable

### Currency & Localization
- [ ] Set `EXCHANGE_RATE_API_KEY` for live currency conversion
- [ ] Verify `DEFAULT_LANGUAGE` and `DEFAULT_CURRENCY` are correct for your market

### Analytics & Tracking
- [ ] Set `GOOGLE_ANALYTICS_ID` (if applicable) in `.env` or the admin system settings
- [ ] Verify the sitemap is accessible at `/sitemap.xml`

### PWA
- [ ] Confirm `manifest.json` has correct `start_url` and `scope` for your domain
- [ ] Confirm all PWA icons exist in `assets/icons/`
- [ ] Test service worker caching (`sw.js`) on the production domain

---

## 🔵 Final Verification

- [ ] Run a full end-to-end test: Register → Login → Browse Products → Add to Cart → Checkout → Payment → Order Tracking
- [ ] Test password reset email flow
- [ ] Test KYC document upload
- [ ] Test real-time chat (Socket.io connection)
- [ ] Test API endpoints with a real API key
- [ ] Review browser console for JS errors
- [ ] Run a security header check at [securityheaders.com](https://securityheaders.com)
- [ ] Check error logs are writing to the correct location and not exposed publicly
- [ ] Confirm no `APP_DEBUG=true` traces appear in the browser

---

## 📝 Post-Launch

- [ ] Monitor error logs daily for the first week
- [ ] Set up uptime monitoring (e.g. UptimeRobot)
- [ ] Rotate `JWT_SECRET` and `INTERNAL_API_KEY` periodically
- [ ] Review and update Stripe webhook event types as needed
- [ ] Plan regular database backups (daily recommended)
