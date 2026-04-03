# GlobexSky — Self-Hosted & Free Alternatives

## Overview

GlobexSky is designed to minimise dependency on paid third-party SaaS services. This document lists every external service that could incur cost or cause access issues (especially in China), and the free or self-hosted alternative used.

---

## Complete Replacement Table

| Category | Paid / Blocked Service | GlobexSky Alternative | Cost | Notes |
|----------|----------------------|----------------------|------|-------|
| **CAPTCHA** | Google reCAPTCHA | **hCaptcha** | Free | Privacy-first, China-accessible |
| **Maps** | Google Maps | **OpenStreetMap + Leaflet.js** | Free | Open-source, no API key needed |
| **Fonts CDN** | Google Fonts | **Self-hosted + Bunny Fonts** | Free | Download fonts, self-host in `/assets/fonts/` |
| **Analytics** | Google Analytics | **Umami** (self-hosted) | Free | Privacy-first, GDPR compliant |
| **Video CDN** | YouTube Embed | **Jitsi Meet** / direct HTML5 video | Free | No YouTube dependency |
| **Streaming** | Zoom, YouTube Live | **PeerJS (WebRTC) + Jitsi Meet** | Free | China-accessible |
| **Email (transactional)** | SendGrid, Mailchimp | **PHPMailer + own SMTP** | Free* | *SMTP provider may have cost |
| **Real-time Chat** | Intercom, Zendesk | **Socket.io (Node.js)** | Free | Self-hosted on Namecheap |
| **Search** | Algolia, Elasticsearch | **MySQL FULLTEXT + DeepSeek** | Free** | **DeepSeek ~$0.001/call |
| **AI Translation** | Google Translate API | **DeepSeek API** | ~$0.001/call | Much cheaper than Google |
| **CDN** | AWS CloudFront | **Cloudflare Free Tier** | Free | 100GB/month free bandwidth |
| **Object Storage** | AWS S3 | **Local storage + Cloudflare R2** | Free*** | ***R2: first 10GB free |
| **Authentication** | Auth0, Firebase Auth | **Custom PHP session auth** | Free | Built in, no third party |
| **Push Notifications** | Firebase FCM | **PWA service worker** | Free | Phase 10, no Google dependency |
| **Automation / Workflows** | Zapier, Make | **n8n** (self-hosted) | Free | Open-source automation |
| **Monitoring (uptime)** | Pingdom, Datadog | **UptimeRobot** | Free | 50 monitors free tier |
| **Error tracking** | Sentry | **Custom PHP error logging** | Free | Write to DB or log file |
| **A/B Testing** | Optimizely, VWO | **Feature toggle system** | Free | Built-in feature flags |
| **Forms / Surveys** | Typeform, Google Forms | **Custom PHP forms** | Free | No third-party dependency |
| **OAuth (Google)** | Google OAuth | **WeChat OAuth** + email/password | Free | WeChat for China users |
| **Video hosting** | Vimeo, Wistia | **Jitsi recordings** / HTML5 video | Free | Store recordings on server |
| **SSL** | Let's Encrypt (direct) | **AutoSSL via Namecheap cPanel** | Free | Auto-renew built in |
| **2FA** | Authy, Duo | **TOTP (PHP TOTP library)** | Free | RFC 6238 compatible |
| **Image processing** | Cloudinary | **PHP GD library** | Free | Resize, WebP, thumbnails |
| **PDF generation** | DocRaptor | **FPDF / TCPDF** | Free | Generate invoices in PHP |
| **Cron jobs** | EasyCron | **cPanel Cron Jobs** | Free | Namecheap cPanel built-in |
| **Search engine** | Elasticsearch | **MySQL FULLTEXT index** | Free | Sufficient for current scale |
| **Rate limiting** | Redis + middleware | **PHP session + DB rate limiter** | Free | Simple, no Redis needed |

---

## Cost Summary

| Service | Monthly Cost |
|---------|-------------|
| Namecheap hosting | ~$10–25/month |
| Domain | ~$10/year |
| DeepSeek AI (at scale) | ~$1–50/month (usage-based) |
| Stripe fees | 2.9% + $0.30 per transaction (transaction cost, not platform cost) |
| Cloudflare R2 (if using) | $0.015/GB after 10GB free |
| Everything else | **$0** |

**Total platform infrastructure cost at early stage: ~$10–25/month** (hosting only).

---

## hCaptcha Setup

```html
<!-- In forms requiring CAPTCHA -->
<div class="h-captcha" data-sitekey="YOUR_SITE_KEY"></div>
<script src="https://js.hcaptcha.com/1/api.js" async defer></script>
```

```php
// Server-side verify
$response = file_get_contents(
    'https://hcaptcha.com/siteverify',
    false,
    stream_context_create(['http' => [
        'method' => 'POST',
        'content' => http_build_query([
            'secret' => HCAPTCHA_SECRET,
            'response' => $_POST['h-captcha-response']
        ])
    ]])
);
$result = json_decode($response);
if (!$result->success) { /* reject */ }
```

---

## OpenStreetMap + Leaflet.js Setup

```html
<!-- No API key needed -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

<div id="map" style="height: 400px;"></div>
<script>
  var map = L.map('map').setView([31.2304, 121.4737], 13); // Shanghai default
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);
</script>
```

---

## Umami Analytics Setup

Umami is a self-hosted, privacy-first, GDPR-compliant analytics tool:

```html
<!-- Replace Google Analytics script with: -->
<script async defer
  data-website-id="YOUR_UMAMI_ID"
  src="https://YOUR_UMAMI_HOST/umami.js">
</script>
```

- Hosted on own server or free tier at umami.is
- No cookies, no personal data collection
- China-accessible (self-hosted)

---

## Summary Philosophy

> **"If it can be done in PHP with standard libraries or a free open-source tool, use that. Only pay for what cannot be avoided."**

The only unavoidable costs are:
1. Hosting (Namecheap)
2. Stripe transaction fees (% of revenue, not fixed cost)
3. DeepSeek AI calls (fraction of a cent per call)

All other SaaS tools have been replaced with self-hosted or free-tier alternatives.
