# GlobexSky — Performance Optimization Report (Phase 11)

**Date:** August 2026  
**Platform:** PHP 8.x / MySQL 8.0 / Apache (Namecheap shared hosting)

---

## 1. OPcache Configuration

### Recommended `.user.ini` (place in document root)

```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.validate_timestamps=1
opcache.fast_shutdown=1
opcache.save_comments=1
opcache.max_wasted_percentage=10
```

### Helper functions (`config/opcache.php`)

| Function | Purpose |
|---|---|
| `getOpcacheConfig()` | Returns the recommended settings as an array |
| `getOpcacheStatus()` | Returns current memory/stats from `opcache_get_status()` |
| `clearOpcacheIfNeeded()` | Resets cache when config files change (uses sentinel file) |

### Expected improvements

- **First-byte time:** 30–60% reduction on repeated PHP file loads
- **CPU usage:** Significant reduction — no re-parsing/compiling PHP on each request
- **Memory:** 128 MB OPcache shared pool covers all ~150 PHP files comfortably

---

## 2. Database Query Optimization

### QueryOptimizer class (`includes/query-optimizer.php`)

Wraps PDO to provide:
- **Per-query timing** — microsecond-precision `microtime()` around every `execute()`
- **Slow query log** — writes to `query_log` table when `execution_time_ms >= threshold` (default 100 ms)
- **EXPLAIN helper** — `explain(string $sql)` runs `EXPLAIN` on any SELECT
- **Index suggestions** — `suggestIndexes()` analyses `query_log` for repeated slow queries and suggests `INDEX` additions
- **Auto-cleanup** — deletes `query_log` rows older than 7 days

### DB table: `query_log`

```sql
CREATE TABLE `query_log` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `query_hash`        CHAR(64)     NOT NULL,
    `query_text`        TEXT         NOT NULL,
    `execution_time_ms` FLOAT        NOT NULL,
    `row_count`         INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_execution_time` (`execution_time_ms`),
    INDEX `idx_created_at`     (`created_at`)
);
```

### Manual index recommendations

Run `EXPLAIN` on the following common query patterns:
- `products` table: add composite index `(status, category_id, created_at)`
- `orders` table: add index `(buyer_id, status, created_at)` and `(supplier_id, status)`
- `ai_messages` table: add index `(conversation_id, created_at)`
- `notifications` table: add index `(user_id, is_read, created_at)`

---

## 3. Asset Optimization

### AssetOptimizer class (`includes/asset-optimizer.php`)

| Method | Description |
|---|---|
| `cssMinify(string $css)` | Remove comments + collapse whitespace |
| `jsMinify(string $js)` | Remove comments + collapse whitespace (heuristic) |
| `generateAssetUrl(string $path)` | Append `?v={filemtime}` for cache busting |
| `getAssetVersion(string $path)` | Returns `filemtime` or `Ymd` fallback |
| `preloadCriticalAssets(array $assets)` | Generates `<link rel="preload">` tags |
| `lazyLoadImage(string $src, ...)` | Generates `<img loading="lazy" decoding="async">` |
| `inlineCriticalCss(string $cssFile)` | Reads, minifies and inlines CSS in `<style>` tag |

### Cache-busting strategy

Every asset URL is versioned with a filemtime-based query parameter:
```
/assets/css/app.css?v=1714000000
```
When a file changes, `filemtime` changes, forcing browsers to re-fetch. Browser caches are set to `1 month` (Apache `mod_expires`).

### Lazy loading

All below-fold images should use `AssetOptimizer::lazyLoadImage()` or manually add `loading="lazy"` to `<img>` tags. This defers image loading until the user scrolls, reducing initial page weight.

---

## 4. Caching Strategy

### L1: OPcache (bytecode cache)
- All PHP compiled files cached in shared memory
- No configuration needed at runtime — set in `.user.ini`

### L2: MySQL query cache (application-level)
- The `QueryOptimizer` class can be used to cache frequent read queries in `$_SESSION` or a flat file cache
- For expensive dashboard queries, store result in a JSON file with TTL:

```php
$cacheFile = sys_get_temp_dir() . '/globexsky_dashboard_cache.json';
$ttl       = 300; // 5 minutes
if (!is_file($cacheFile) || (time() - filemtime($cacheFile)) > $ttl) {
    $data = // ... expensive query ...
    file_put_contents($cacheFile, json_encode($data), LOCK_EX);
} else {
    $data = json_decode(file_get_contents($cacheFile), true);
}
```

### L3: Browser cache
Already configured in `.htaccess` via `mod_expires`:
- CSS / JS: `access plus 1 month`
- Images: `access plus 6 months`

---

## 5. CDN Integration Notes (Cloudflare)

1. Point nameservers to Cloudflare (free plan sufficient)
2. Enable **Auto Minify** (HTML/CSS/JS) in Speed → Optimization
3. Enable **Brotli** compression
4. Set **Cache Level: Standard** — static assets (`.css`, `.js`, images) cached at edge
5. Add **Page Rule**: `*.globexsky.com/api/*` → Cache Level: Bypass (API responses must not be cached)
6. Enable **Rocket Loader** only after testing — it async-loads JS and can interfere with inline scripts

### Headers to add for Cloudflare

```
Cache-Control: public, max-age=2592000    (for static assets)
Cache-Control: no-store, no-cache         (for API / auth pages — already set by applySecurityHeaders())
```

---

## 6. Benchmark Recommendations

### Tools

| Tool | Purpose | Command |
|---|---|---|
| `ab` (Apache Bench) | Simple throughput test | `ab -n 1000 -c 50 https://globexsky.com/` |
| `k6` | Scripted load test | `k6 run load-test.js` |
| Google Lighthouse | Frontend perf score | Chrome DevTools → Lighthouse |
| WebPageTest | Full waterfall analysis | https://www.webpagetest.org |

### Target scores (Lighthouse)

| Metric | Target |
|---|---|
| Performance | ≥ 85 |
| Accessibility | ≥ 90 |
| Best Practices | ≥ 95 |
| SEO | ≥ 90 |
| First Contentful Paint | < 1.5 s |
| Time to Interactive | < 3.5 s |
| Cumulative Layout Shift | < 0.1 |

---

## 7. Shared Hosting Limitations & Workarounds

| Limitation | Workaround |
|---|---|
| No Redis / Memcached | File-based or MySQL-based caching (see section 4) |
| No root / `php.ini` write access | Use `.user.ini` in document root for OPcache / session settings |
| Shared MySQL server | Add `INDEX` on all FK + WHERE columns; use `LIMIT` on all queries |
| No Node.js persistent process | Socket.io fallback: use AJAX polling from `includes/notifications.php` |
| Limited RAM per process | Tune `memory_limit=256M` in `.user.ini`; avoid loading all rows into RAM |
| No background jobs / cron by default | Add cron via cPanel → Cron Jobs (`php /path/to/cron/cleanup.php`) |

---

## 8. Recommended Cron Jobs

Add via cPanel → Cron Jobs:

```bash
# Every 5 minutes — process notification queue
*/5 * * * * php /home/yourusername/public_html/cron/notifications.php

# Daily at 2 AM — clean expired sessions / rate limits / query log
0 2 * * * php /home/yourusername/public_html/cron/cleanup.php

# Weekly Sunday 3 AM — full DB backup
0 3 * * 0 mysqldump -u USER -pPASS DB_NAME > /home/yourusername/backups/weekly_$(date +\%F).sql
```
