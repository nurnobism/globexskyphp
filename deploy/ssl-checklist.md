# GlobexSky — SSL & Security Checklist (Namecheap cPanel)

Use this checklist after deploying to production to verify SSL and security headers are
correctly configured.

---

## 1. Enable AutoSSL (Free SSL via Namecheap / Let's Encrypt)

- [ ] Log in to **cPanel** → **Security** → **SSL/TLS Status**
- [ ] All domains/sub-domains in the list show a **green padlock** ✅
- [ ] If any domain shows "Needs Renewal" or a red icon, click **Run AutoSSL**
- [ ] Wait 5–10 minutes and refresh the page

**Verify from the browser:**
- [ ] Navigate to `https://yourdomain.com` — no browser warning
- [ ] Padlock icon is shown in the address bar
- [ ] Click the padlock → Certificate is valid and issued by **cPanel, Inc. / Sectigo**

---

## 2. Force HTTPS in `.htaccess`

Uncomment these lines in `.htaccess` (already present, just uncomment):

```apache
# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

- [ ] After uncommenting, test: `http://yourdomain.com` → should redirect to `https://`
- [ ] Check redirect with curl:
  ```bash
  curl -I http://yourdomain.com
  # Expect: 301 Moved Permanently + Location: https://...
  ```

---

## 3. Verify HSTS Header

HSTS (HTTP Strict Transport Security) tells browsers to always use HTTPS.

- [ ] Add to `.htaccess` inside `<IfModule mod_headers.c>`:
  ```apache
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
  ```
- [ ] Verify with curl:
  ```bash
  curl -I https://yourdomain.com
  # Look for: Strict-Transport-Security: max-age=31536000; includeSubDomains
  ```
- [ ] Check with online tool: https://hstspreload.org/

> ⚠️ Only add `preload` to HSTS after you are 100% sure HTTPS is stable. Preloading is
> very difficult to reverse.

---

## 4. Check Security Headers

Run the following to verify all security headers are present:

```bash
curl -I https://yourdomain.com
```

Expected headers (all should be present):

| Header | Expected Value |
|---|---|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` or `DENY` |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` |

- [ ] Verify with online tool: https://securityheaders.com/
- [ ] Target grade: **A** or **A+**

---

## 5. Mixed Content Check

Mixed content occurs when an HTTPS page loads HTTP resources (images, scripts, etc.).

- [ ] Open `https://yourdomain.com` in Chrome
- [ ] Open **DevTools** (F12) → **Console** tab
- [ ] Look for any `Mixed Content` warnings → fix by updating resource URLs to HTTPS
- [ ] Check with online tool: https://www.whynopadlock.com/

---

## 6. SSL Labs Grade Check

- [ ] Go to: https://www.ssllabs.com/ssltest/
- [ ] Enter your domain and run the test
- [ ] Target grade: **A** or **A+**
- [ ] Fix any issues flagged (weak ciphers, etc.) — for Namecheap shared hosting,
  cipher configuration is managed by the host.

---

## 7. Content Security Policy (CSP)

The PHP security headers already set a CSP nonce-based policy. Verify it works:

- [ ] Open `https://yourdomain.com` → DevTools → Console: no CSP violation errors
- [ ] Check `Content-Security-Policy` header is present in curl output

---

## 8. Cookie Security

- [ ] Verify session cookies have `Secure` and `HttpOnly` flags:
  ```bash
  curl -c /tmp/cookies.txt -I https://yourdomain.com/
  cat /tmp/cookies.txt
  # globexsky_session should show: Secure, HttpOnly
  ```

---

## 9. Sensitive File Access

- [ ] Confirm `.env` is NOT accessible from the browser:
  ```bash
  curl -o /dev/null -s -w "%{http_code}" https://yourdomain.com/.env
  # Expected: 403 or 404
  ```
- [ ] Confirm `composer.json` is not accessible:
  ```bash
  curl -o /dev/null -s -w "%{http_code}" https://yourdomain.com/composer.json
  # Expected: 403 or 404
  ```
- [ ] Confirm `config/` directory is not listable:
  ```bash
  curl -o /dev/null -s -w "%{http_code}" https://yourdomain.com/config/
  # Expected: 403
  ```

---

## 10. Final Sign-Off

- [ ] All checklist items above are ✅
- [ ] `deploy/health-check.php` runs with all green checks
- [ ] No browser security warnings
- [ ] Stripe test payment succeeded with live keys
