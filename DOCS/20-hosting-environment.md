# GlobexSky — Hosting Environment

## Overview

GlobexSky is hosted on **Namecheap Shared Hosting** (Stellar Business or equivalent plan) with cPanel management. This provides a cost-effective environment with all required capabilities: PHP 8.x, MySQL, Node.js, email hosting, and AutoSSL.

---

## Namecheap Shared Hosting Specifications

| Feature | Specification |
|---------|--------------|
| Storage | Unmetered SSD |
| Bandwidth | Unmetered |
| Domains | Unlimited |
| Email accounts | Unlimited |
| MySQL databases | Unlimited |
| PHP version | 8.x (configurable via cPanel) |
| Node.js | Available via cPanel Node.js Selector (v14–v20) |
| SSL | AutoSSL (Let's Encrypt, auto-renew) |
| Backup | Weekly automated via cPanel |
| cPanel version | Latest |
| Softaculous | Available (WordPress, etc.) |

---

## Node.js on Namecheap (Confirmed Available)

Namecheap Shared Hosting supports Node.js via cPanel Node.js Selector:

| Feature | Detail |
|---------|--------|
| Versions available | Node.js 14, 16, 18, 20 |
| Custom port | Yes (unique port per app) |
| Process manager | PM2-like via cPanel |
| Start/stop/restart | Via cPanel UI or command line |
| Application root | Separate from `public_html` |

### Socket.io + PeerJS Server

Both Socket.io (real-time chat) and PeerJS (WebRTC signaling) run as Node.js processes:

| Service | Port | Purpose |
|---------|------|---------|
| Socket.io server | 3000 | Real-time chat, notifications |
| PeerJS signaling | 3001 | WebRTC peer discovery for streaming |

Apache proxies requests from the public domain to these ports via `.htaccess` and Apache `ProxyPass`.

---

## File Structure

```
/home/{cpanel_user}/
├── public_html/            ← PHP application root (domain root)
│   ├── index.php
│   ├── .htaccess
│   ├── api/
│   ├── assets/
│   ├── includes/
│   ├── pages/
│   ├── storage/            ← user uploads (not web-accessible directly)
│   └── ...
│
├── nodejs_app/             ← Node.js real-time server
│   ├── server.js           ← Socket.io entry point
│   ├── peerjs-server.js    ← PeerJS signaling server
│   ├── package.json
│   └── node_modules/
│
└── logs/                   ← PHP error logs, access logs
```

---

## Environment Configuration

### `.env` File (Development)

Located at `/home/{user}/.env` (outside `public_html` for security):

```env
APP_ENV=production
APP_URL=https://globexsky.com
APP_DEBUG=false

DB_HOST=localhost
DB_NAME=globexsky_db
DB_USER=globexsky_user
DB_PASS=secret

STRIPE_PK=pk_live_...
STRIPE_SK=sk_live_...
STRIPE_WEBHOOK=whsec_...

SMTP_HOST=smtp.mailgun.org
SMTP_PORT=587
SMTP_USER=noreply@globexsky.com
SMTP_PASS=secret

DEEPSEEK_API_KEY=sk-...
```

> In production, sensitive values are stored encrypted in the database (`platform_config` table) and the `.env` file is used only for bootstrap. See [11-admin-config.md](11-admin-config.md).

---

## Performance Optimisation

| Optimisation | Method |
|-------------|--------|
| PHP OPcache | Enabled via cPanel PHP settings |
| MySQL query cache | Enabled in MySQL config |
| Gzip compression | Apache `mod_deflate` via `.htaccess` |
| Browser caching | `Cache-Control` headers via `.htaccess` |
| Image optimisation | WebP conversion on upload (PHP GD library) |
| Thumbnail generation | Multiple sizes generated on upload |
| CDN (optional) | Cloudflare free tier for static assets |

### `.htaccess` Performance Settings

```apache
# Gzip
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript
</IfModule>

# Browser Cache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

---

## Cloudflare CDN (Optional)

| Feature | Cloudflare Free Tier |
|---------|---------------------|
| CDN | ✅ (140+ edge locations) |
| DDoS protection | ✅ Basic |
| SSL | ✅ (Full / Full Strict with Namecheap AutoSSL) |
| China accessible | ✅ (Cloudflare is not blocked in China) |
| Cost | Free |
| Asset caching | ✅ CSS, JS, images cached at edge |

### Cloudflare DNS Setup

```
A record: @ → Namecheap server IP (proxied via Cloudflare)
CNAME: www → globexsky.com (proxied)
A record: socket → Namecheap server IP (DNS only, not proxied — WebSocket requirement)
```

---

## Database

| Item | Detail |
|------|--------|
| Engine | MySQL 8.x |
| Connection | PDO with prepared statements |
| Charset | utf8mb4 (full Unicode + emoji support) |
| Collation | utf8mb4_unicode_ci |
| Connection pool | Persistent connections via PDO |
| Backup | Weekly cPanel backup + manual export before deploys |

---

## Deployment Process

```bash
# 1. Pull latest from GitHub (via cPanel Git or SSH)
cd ~/public_html && git pull origin main

# 2. Run any pending schema migrations
mysql -u user -p globexsky_db < database/migrations/latest.sql

# 3. Clear PHP OPcache (via cPanel or PHP function)
# 4. Restart Node.js apps via cPanel Node.js Selector
```
