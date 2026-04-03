# GlobexSky — Cron Jobs Setup (Namecheap cPanel)

## How to Add Cron Jobs in cPanel

1. Log in to **Namecheap cPanel** → `https://premium116.web-hosting.com:2083`
2. Navigate to **Advanced** → **Cron Jobs**
3. Under **Add New Cron Job**, set the schedule fields and paste the command
4. Click **Add New Cron Job**

> **Replace `bidybxoc`** with your actual cPanel username in all commands below.  
> **Verify your PHP path** by running in cPanel → Terminal: `which php`  
> Common paths: `/usr/local/bin/php` or `/usr/bin/php`

Create the logs directory first (run once in cPanel Terminal):
```bash
mkdir -p /home/bidybxoc/logs && chmod 750 /home/bidybxoc/logs
```

---

## Required Cron Jobs

### 1. Session Cleanup — Daily at 1 AM

Removes expired PHP sessions and database session records.

| Field    | Value |
|----------|-------|
| Minute   | `0`   |
| Hour     | `1`   |
| Day      | `*`   |
| Month    | `*`   |
| Weekday  | `*`   |

**Cron expression:** `0 1 * * *`

**Command:**
```
/usr/local/bin/php -r "session_save_path('/home/bidybxoc/globexsky.com/storage/sessions'); session_start(); session_gc();" >> /home/bidybxoc/logs/cron-session.log 2>&1
```

---

### 2. Exchange Rate Update — Every 6 Hours

Fetches the latest currency exchange rates from the API.

| Field    | Value |
|----------|-------|
| Minute   | `30`  |
| Hour     | `*/6` |
| Day      | `*`   |
| Month    | `*`   |
| Weekday  | `*`   |

**Cron expression:** `30 */6 * * *`

**Command:**
```
/usr/local/bin/php /home/bidybxoc/globexsky.com/cron/update-exchange-rates.php >> /home/bidybxoc/logs/cron-exchange.log 2>&1
```

---

### 3. Email Queue Processing — Every 5 Minutes

Sends queued transactional emails (order confirmations, KYC notifications, etc.).

| Field    | Value  |
|----------|--------|
| Minute   | `*/5`  |
| Hour     | `*`    |
| Day      | `*`    |
| Month    | `*`    |
| Weekday  | `*`    |

**Cron expression:** `*/5 * * * *`

**Command:**
```
/usr/local/bin/php /home/bidybxoc/globexsky.com/cron/process-email-queue.php >> /home/bidybxoc/logs/cron-email.log 2>&1
```

---

### 4. Subscription & Payment Checks — Daily at 2 AM

Checks for expired subscriptions, failed payments, and sends renewal reminders.

| Field    | Value |
|----------|-------|
| Minute   | `0`   |
| Hour     | `2`   |
| Day      | `*`   |
| Month    | `*`   |
| Weekday  | `*`   |

**Cron expression:** `0 2 * * *`

**Command:**
```
/usr/local/bin/php /home/bidybxoc/globexsky.com/cron/check-subscriptions.php >> /home/bidybxoc/logs/cron-subscriptions.log 2>&1
```

---

### 5. KYC Verification Reminders — Daily at 10 AM

Sends reminders to users with pending or incomplete KYC verification.

| Field    | Value |
|----------|-------|
| Minute   | `0`   |
| Hour     | `10`  |
| Day      | `*`   |
| Month    | `*`   |
| Weekday  | `*`   |

**Cron expression:** `0 10 * * *`

**Command:**
```
/usr/local/bin/php /home/bidybxoc/globexsky.com/cron/kyc-reminders.php >> /home/bidybxoc/logs/cron-kyc.log 2>&1
```

---

### 6. Analytics & Stats Aggregation — Daily at 3 AM

Aggregates daily sales, visitor, and performance statistics.

| Field    | Value |
|----------|-------|
| Minute   | `0`   |
| Hour     | `3`   |
| Day      | `*`   |
| Month    | `*`   |
| Weekday  | `*`   |

**Cron expression:** `0 3 * * *`

**Command:**
```
/usr/local/bin/php /home/bidybxoc/globexsky.com/cron/aggregate-analytics.php >> /home/bidybxoc/logs/cron-analytics.log 2>&1
```

---

### 7. Cache Cleanup — Every 6 Hours

Removes stale cache files from the `storage/cache/` directory.

| Field    | Value |
|----------|-------|
| Minute   | `0`   |
| Hour     | `*/6` |
| Day      | `*`   |
| Month    | `*`   |
| Weekday  | `*`   |

**Cron expression:** `0 */6 * * *`

**Command:**
```
/usr/local/bin/php -r "array_map('unlink', glob('/home/bidybxoc/globexsky.com/storage/cache/*.cache'));" >> /home/bidybxoc/logs/cron-cache.log 2>&1
```

---

### 8. Database Backup — Daily at 4 AM

Creates a compressed MySQL dump and stores it in `~/backups/`.

| Field    | Value |
|----------|-------|
| Minute   | `0`   |
| Hour     | `4`   |
| Day      | `*`   |
| Month    | `*`   |
| Weekday  | `*`   |

**Cron expression:** `0 4 * * *`

**Command (using `~/.my.cnf` for credentials — recommended):**
```
mkdir -p /home/bidybxoc/backups && mysqldump bidybxoc_globexsky | gzip > /home/bidybxoc/backups/db_$(date +\%Y\%m\%d).sql.gz && find /home/bidybxoc/backups -name "db_*.sql.gz" -mtime +30 -delete >> /home/bidybxoc/logs/cron-backup.log 2>&1
```

**Setup `~/.my.cnf` first** (run once in cPanel Terminal):
```bash
cat > ~/.my.cnf << 'EOF'
[client]
host=localhost
user=bidybxoc_globexsky
password=YOUR_DB_PASSWORD
EOF
chmod 600 ~/.my.cnf
```

> **Security Note:** Using `~/.my.cnf` keeps credentials out of cron commands and
> process listings. The `chmod 600` ensures only your account can read this file.
> Never put plaintext passwords in cron commands.

---

### 9. Dropship Product Sync — Every 6 Hours

Syncs supplier catalog changes into the GlobexSky product database.

| Field    | Value |
|----------|-------|
| Minute   | `0`   |
| Hour     | `*/6` |
| Day      | `*`   |
| Month    | `*`   |
| Weekday  | `*`   |

**Cron expression:** `0 */6 * * *`

**Command:**
```
/usr/local/bin/php /home/bidybxoc/globexsky.com/cron/sync-dropship-products.php >> /home/bidybxoc/logs/cron-dropship.log 2>&1
```

---

### 10. Webhook Processing — Every 5 Minutes

Processes queued Stripe and external webhooks.

| Field    | Value  |
|----------|--------|
| Minute   | `*/5`  |
| Hour     | `*`    |
| Day      | `*`    |
| Month    | `*`    |
| Weekday  | `*`    |

**Cron expression:** `*/5 * * * *`

**Command:**
```
/usr/local/bin/php /home/bidybxoc/globexsky.com/cron/process-webhooks.php >> /home/bidybxoc/logs/cron-webhooks.log 2>&1
```

---

## Complete Cron Job Summary Table

| # | Job | Schedule | Expression |
|---|-----|----------|------------|
| 1 | Session cleanup | Daily 1 AM | `0 1 * * *` |
| 2 | Exchange rate update | Every 6h | `30 */6 * * *` |
| 3 | Email queue | Every 5 min | `*/5 * * * *` |
| 4 | Subscription checks | Daily 2 AM | `0 2 * * *` |
| 5 | KYC reminders | Daily 10 AM | `0 10 * * *` |
| 6 | Analytics aggregation | Daily 3 AM | `0 3 * * *` |
| 7 | Cache cleanup | Every 6h | `0 */6 * * *` |
| 8 | Database backup | Daily 4 AM | `0 4 * * *` |
| 9 | Dropship sync | Every 6h | `0 */6 * * *` |
| 10 | Webhook processing | Every 5 min | `*/5 * * * *` |

---

## Notes

- Replace `bidybxoc` with your actual cPanel username
- Create `~/logs/` directory before adding cron jobs:
  ```bash
  mkdir -p ~/logs && chmod 750 ~/logs
  ```
- To verify PHP path on Namecheap, run in cPanel → Terminal:
  ```bash
  which php
  # or
  ls /usr/local/bin/php*
  ```
- Check cron job logs for debugging:
  ```bash
  tail -50 ~/logs/cron-email.log
  ```
- All cron scripts load `.env` via `config/database.php` — ensure `.env` is in the project root
- Namecheap cPanel sends cron output emails by default — configure **From Email** in cPanel → Cron Jobs to avoid spam
