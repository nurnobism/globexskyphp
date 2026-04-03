# GlobexSky — Security Audit Report (Phase 11)

**Date:** August 2026  
**Scope:** Full codebase — PHP 8.x / MySQL / Bootstrap 5 / Apache (Namecheap shared hosting)  
**Auditor:** Internal (Phase 11 implementation pass)

---

## 1. Current Security Posture Assessment

| Category | Before Phase 11 | After Phase 11 |
|---|---|---|
| Authentication | ✅ `password_hash`/`password_verify`, sessions | ✅ + `SecureSession` hardening |
| CSRF Protection | ✅ Tokens in forms | ✅ + `SecurityValidator::validateCsrfToken()` |
| SQL Injection | ✅ PDO prepared statements | ✅ + `SecurityValidator::detectSqlInjection()` heuristic |
| XSS | ✅ `e()` / `htmlspecialchars` | ✅ + CSP with nonce, `detectXss()` |
| Security Headers | ⚠️ Partial (`X-Content-Type-Options`, `X-Frame-Options`) | ✅ Full suite |
| Rate Limiting | ❌ None | ✅ `RateLimiter` class (DB-backed) |
| Input Validation | ⚠️ Ad-hoc | ✅ `SecurityValidator` class |
| Session Security | ⚠️ Basic | ✅ `SecureSession` + fingerprinting |
| File Upload Validation | ⚠️ Partial | ✅ MIME-type check + size limit |
| `.htaccess` hardening | ⚠️ Partial | ✅ Exploit blocking, hotlink protection |
| OPcache | ❌ Not configured | ✅ `config/opcache.php` |
| Query monitoring | ❌ None | ✅ `QueryOptimizer` class |

---

## 2. Vulnerabilities Found and Fixed

### 2.1 Missing Content-Security-Policy
**Risk:** Medium — no CSP allowed inline scripts, leaving XSS attack surface larger.  
**Fix:** `includes/security-headers.php` → `applySecurityHeaders()` sets strict CSP with per-request nonce. CDN allowances for Bootstrap/fonts are explicit.

### 2.2 No Rate Limiting on Login / Registration / Password Reset
**Risk:** High — brute-force attacks on login with no throttle.  
**Fix:** `includes/rate-limiter.php` → `RateLimiter` class. Default limits:
- Login: 5 / 15 min per IP
- Password reset: 3 / 60 min per email
- Registration: 3 / 60 min per IP
- API: 60 / 1 min per API key
- General pages: 120 / 1 min per IP

### 2.3 Session Fixation Risk
**Risk:** Medium — session ID not regenerated on privilege changes consistently.  
**Fix:** `includes/session-security.php` → `SecureSession::regenerate()` must be called after every login / privilege elevation. `start()` also enables `use_strict_mode`.

### 2.4 No Browser Fingerprint Binding
**Risk:** Low-Medium — stolen session cookies could be replayed from a different browser.  
**Fix:** `SecureSession::setFingerprint()` / `checkFingerprint()` bind the session to a hash of User-Agent + first 2 IP octets (resilient to carrier-grade NAT).

### 2.5 Insufficient Input Sanitisation Coverage
**Risk:** Medium — some API endpoints relying on ad-hoc `trim()` / `strip_tags()`.  
**Fix:** `SecurityValidator` provides `sanitizeString()`, `sanitizeEmail()`, `sanitizeUrl()`, `sanitizeInt()`, `sanitizeFilename()` for consistent use across all new endpoints.

### 2.6 `.htaccess` Permitted Direct Access to `includes/`
**Risk:** Medium — PHP files in `includes/` were accessible directly via URL, potentially leaking error messages.  
**Fix:** Added `RewriteRule ^includes/ - [F,L]` to `.htaccess`.

### 2.7 Common Exploit Patterns Not Blocked at Web-Server Level
**Risk:** Low — some scanners send `eval(`, `base64_decode` in query strings.  
**Fix:** Added mod_rewrite rules to return 403 on known exploit patterns.

---

## 3. Security Headers Implementation

| Header | Value | Purpose |
|---|---|---|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self' 'nonce-{n}' cdn.jsdelivr.net; ...` | Prevents XSS, data injection |
| `X-Content-Type-Options` | `nosniff` | Prevents MIME sniffing |
| `X-Frame-Options` | `DENY` | Prevents clickjacking |
| `X-XSS-Protection` | `1; mode=block` | Legacy XSS filter (IE/Chrome <78) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Limits referer leakage |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` | Disables sensitive browser APIs |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` | Enforces HTTPS |

All headers are set by `applySecurityHeaders()` in `includes/security-headers.php`.  
A subset is also set in `.htaccess` as an Apache-level fallback.

---

## 4. Rate Limiting Configuration

The `RateLimiter` class uses the MySQL `rate_limits` table with a UNIQUE index on `rate_key`. No Redis or Memcached required — suitable for Namecheap shared hosting.

**Usage example:**
```php
$limiter = new RateLimiter(getDB());
$key     = 'login:' . $_SERVER['REMOTE_ADDR'];
if ($limiter->tooManyAttempts($key, 5, 15)) {
    http_response_code(429);
    die(json_encode(['error' => 'Too many login attempts. Try again in 15 minutes.']));
}
$limiter->hit($key);
// ... attempt login ...
if ($success) {
    $limiter->clear($key);
}
```

---

## 5. Session Hardening Details

- **Session name:** `GLOBEXSKY_SID` (custom, obscures technology stack)
- **Cookie flags:** `HttpOnly=true`, `Secure=true` (HTTPS), `SameSite=Strict`
- **Idle timeout:** 3600 seconds (1 hour)
- **ID regeneration:** Every 5 minutes automatically + immediately on login
- **Fingerprint:** SHA-256 of User-Agent + first 2 IP octets, stored in `$_SESSION['_fingerprint']`
- **Strict mode:** `session.use_strict_mode=1` prevents session adoption from unrecognised IDs

---

## 6. Input Validation Coverage

`SecurityValidator` static methods are available globally after `require_once 'includes/security-validator.php'`.

| Method | What it validates |
|---|---|
| `sanitizeString()` | Strips HTML tags, trims, HTML-encodes |
| `sanitizeEmail()` | RFC-compliant email sanitisation |
| `sanitizeInt()` | Forces integer cast |
| `sanitizeUrl()` | Strips illegal URL characters |
| `sanitizeFilename()` | Removes path traversal, special chars |
| `validateEmail()` | RFC 5321/5322 check via `filter_var` |
| `validatePassword()` | Min 8 chars, upper/lower/digit/special |
| `validatePhone()` | E.164-ish, 7–15 digits |
| `validateFileUpload()` | MIME type (via `finfo`), size limit |
| `validateCsrfToken()` | Timing-safe `hash_equals` comparison |
| `escapeForJs()` | Safe for JS string embedding |
| `escapeForAttribute()` | Safe for HTML attribute values |
| `detectXss()` | Heuristic pattern matching (10 patterns) |
| `detectSqlInjection()` | Heuristic pattern matching (11 patterns) |

---

## 7. OWASP Top 10 Compliance Checklist

| # | OWASP Risk | Status | Notes |
|---|---|---|---|
| A01 | Broken Access Control | ✅ | RBAC via `auth_guard.php` + `requireRole()` |
| A02 | Cryptographic Failures | ✅ | `password_hash()` with `PASSWORD_DEFAULT`, HTTPS/HSTS |
| A03 | Injection | ✅ | PDO prepared statements everywhere + SQLi detection helper |
| A04 | Insecure Design | ✅ | Rate limiting, session fingerprinting, CSRF tokens |
| A05 | Security Misconfiguration | ✅ | Security headers, `.htaccess` hardening |
| A06 | Vulnerable Components | ⚠️ | Composer deps should be audited with `composer audit` |
| A07 | ID & Auth Failures | ✅ | Rate limiting on login, session hardening, secure cookies |
| A08 | Software & Data Integrity | ⚠️ | No SRI on external CDN assets yet |
| A09 | Logging & Monitoring | ⚠️ | Query log implemented; application-level security events not yet centralised |
| A10 | SSRF | ✅ | No user-controlled URLs used in server-side HTTP calls |

---

## 8. Recommended Future Improvements

1. **Subresource Integrity (SRI)** — Add `integrity` attributes to Bootstrap CDN `<link>` / `<script>` tags.
2. **Centralised security event log** — Log login failures, rate-limit hits, CSRF violations to a `security_events` table.
3. **`composer audit`** in CI — Add step to check for known CVEs in PHP dependencies.
4. **File upload antivirus** — Integrate ClamAV (if host supports it) or a cloud virus-scanning API.
5. **GDPR cookie consent banner** — Required before setting non-essential cookies.
6. **Content-Security-Policy Report-Only mode** — Enable `report-uri` / `report-to` to catch violations without blocking.

---

## 9. Penetration Testing Recommendations

- **Tools:** OWASP ZAP (free), Burp Suite Community, sqlmap (on staging only)
- **Scope:** All 46+ API endpoints, login/register/password-reset, file-upload endpoints, admin panel
- **Priority attacks to test:**
  - SQL injection via all `?id=` / filter parameters
  - XSS via product names, descriptions, search queries
  - CSRF on state-changing POST endpoints
  - Session fixation / hijacking
  - Brute-force login (verify rate limiter triggers at 5 attempts)
  - Path traversal in file download endpoints
  - Insecure direct object reference (IDOR) on order/product IDs
