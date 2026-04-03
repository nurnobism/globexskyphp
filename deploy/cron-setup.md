# GlobexSky — Cron Jobs Setup (Namecheap cPanel)

## How to Add Cron Jobs in cPanel

1. Log in to **Namecheap cPanel**
2. Navigate to **Advanced** → **Cron Jobs**
3. For each job below, paste the command and select the schedule

---

## Required Cron Jobs

### 1. Dropship Product Sync — Every 6 Hours

Syncs supplier catalog changes into the GlobexSky product database.

| Field    | Value |
|----------|-------|
| Minute   | `0`   |
| Hour     | `*/6` |
| Day      | `*`   |
| Month    | `*`   |
| Weekday  | `*`   |

**Command:**
```
/usr/local/bin/php /home/your_cpanel_user/public_html/cron/sync-dropship-products.php >> /home/your_cpanel_user/logs/cron-dropship.log 2>&1
```

---

### 2. Rate Limit Reset — Daily at Midnight

Resets per-IP / per-user API rate limit counters.

| Field    | Value |
|----------|-------|
| Minute   | `0`   |
| Hour     | `0`   |
| Day      | `*`   |
| Month    | `*`   |
| Weekday  | `*`   |

**Command:**
```
/usr/local/bin/php /home/your_cpanel_user/public_html/cron/process-webhooks.php reset_rate_limits >> /home/your_cpanel_user/logs/cron-ratelimit.log 2>&1
```

> **Note:** If there is a dedicated rate-limit reset script, replace the path above.

---

### 3. AI Recommendations Update — Daily at 2 AM

Re-generates AI-powered product recommendations.

| Field    | Value |
|----------|-------|
| Minute   | `0`   |
| Hour     | `2`   |
| Day      | `*`   |
| Month    | `*`   |
| Weekday  | `*`   |

**Command:**
```
/usr/local/bin/php /home/your_cpanel_user/public_html/cron/ai-recommendations.php >> /home/your_cpanel_user/logs/cron-ai.log 2>&1
```

---

### 4. AI Fraud Scan — Daily at 3 AM

Scans recent orders and signups for fraud signals.

| Field    | Value |
|----------|-------|
| Minute   | `0`   |
| Hour     | `3`   |
| Day      | `*`   |
| Month    | `*`   |
| Weekday  | `*`   |

**Command:**
```
/usr/local/bin/php /home/your_cpanel_user/public_html/cron/ai-fraud-scan.php >> /home/your_cpanel_user/logs/cron-fraud.log 2>&1
```

---

### 5. Webhook Processing — Every 5 Minutes

Processes queued Stripe and external webhooks.

| Field    | Value  |
|----------|--------|
| Minute   | `*/5`  |
| Hour     | `*`    |
| Day      | `*`    |
| Month    | `*`    |
| Weekday  | `*`    |

**Command:**
```
/usr/local/bin/php /home/your_cpanel_user/public_html/cron/process-webhooks.php >> /home/your_cpanel_user/logs/cron-webhooks.log 2>&1
```

---

### 6. Session Cleanup — Daily at 4 AM

Removes expired sessions from the database / session storage.

| Field    | Value |
|----------|-------|
| Minute   | `0`   |
| Hour     | `4`   |
| Day      | `*`   |
| Month    | `*`   |
| Weekday  | `*`   |

**Command:**
```
/usr/local/bin/php -r "session_start(); session_gc();" >> /home/your_cpanel_user/logs/cron-session.log 2>&1
```

---

## Notes

- Replace `your_cpanel_user` with your actual cPanel username.
- Create the `logs/` directory in your home folder if it does not exist:
  ```bash
  mkdir -p ~/logs
  chmod 750 ~/logs
  ```
- To find the correct PHP binary path on Namecheap, run in **cPanel → Terminal**:
  ```bash
  which php
  ```
  Common paths: `/usr/local/bin/php` or `/usr/bin/php`
- Cron output is redirected to log files for debugging. Check them if a job is not working.
- All cron scripts source the `.env` file via `config/database.php` — ensure `.env` is in the project root.
