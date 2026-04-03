# GlobexSky — China Great Firewall (GFW) Considerations

## Overview

GlobexSky targets global buyers and suppliers including users in mainland China. Many popular Western services are blocked by China's Great Firewall (GFW). This document outlines what is blocked and the China-compatible alternatives used throughout the platform.

---

## Blocked Services in China

| Service | Category | Status in China |
|---------|----------|----------------|
| Google Search | Search | ❌ Blocked |
| Google Maps | Maps | ❌ Blocked |
| Google Fonts | Fonts CDN | ❌ Blocked |
| Google Analytics | Analytics | ❌ Blocked |
| Google reCAPTCHA | CAPTCHA | ❌ Blocked |
| Google OAuth / Sign-in | Authentication | ❌ Blocked |
| YouTube | Video | ❌ Blocked |
| YouTube Live | Streaming | ❌ Blocked |
| Facebook | Social | ❌ Blocked |
| Facebook Login | Authentication | ❌ Blocked |
| Instagram | Social | ❌ Blocked |
| WhatsApp | Messaging | ❌ Blocked |
| Telegram | Messaging | ❌ Blocked |
| Dropbox | Storage | ❌ Blocked |
| Zoom | Video calls | ❌ Blocked |
| Twitch | Streaming | ❌ Blocked |
| AWS CloudFront CDN | CDN | ⚠️ Partially blocked |
| Google Cloud CDN | CDN | ❌ Blocked |
| Twitter / X | Social | ❌ Blocked |
| Gmail (direct SMTP) | Email | ❌ Blocked |

---

## GlobexSky China-Safe Replacements

| Blocked Service | GlobexSky Replacement | Notes |
|----------------|----------------------|-------|
| Google reCAPTCHA | **hCaptcha** | Privacy-focused, China-accessible |
| Google Maps | **OpenStreetMap + Leaflet.js** | Open source, no Google dependency |
| Google Fonts | **Self-hosted fonts + Bunny Fonts CDN** | Bunny CDN is accessible in China |
| Google Analytics | **Umami** (self-hosted) | Open-source, privacy-first |
| YouTube / YouTube Live | **PeerJS (WebRTC) + Jitsi Meet** | Both work in China |
| Zoom | **Jitsi Meet** | Open-source, China-accessible |
| Google OAuth | **WeChat Login (OAuth 2.0)** | WeChat is dominant in China |
| Gmail SMTP | **PHPMailer + Mailgun/SMTP** | Platform's own webmail system |
| Google Translate | **DeepSeek AI translation** | Chinese AI, works in China |
| AWS/GCP CDN | **Cloudflare CDN / cdnjs.cloudflare.com / self-hosted** | Cloudflare accessible in China |
| WhatsApp | **In-platform chat (Socket.io)** | Real-time chat built in |
| Dropbox | **Local storage + Cloudflare R2** | R2 accessible in China |

---

## China Mode Feature Toggle

GlobexSky has a **China Mode** toggle in the admin panel (`china_mode` in `platform_config`).

When China Mode is **ON**, the following switches happen automatically:

| Setting | Standard Mode | China Mode |
|---------|-------------|-----------|
| CAPTCHA | Google reCAPTCHA | hCaptcha |
| Maps | Google Maps (if configured) | OpenStreetMap + Leaflet |
| Fonts CDN | Google Fonts | Self-hosted + Bunny Fonts |
| Analytics | Google Analytics | Umami |
| Video streaming | Any provider | PeerJS + Jitsi only |
| CDN for static assets | Any CDN | Cloudflare / self-hosted |
| OAuth login options | Google + WeChat | WeChat only (Google hidden) |
| Payment methods | All | Alipay / WeChat Pay (future) |

The China Mode switch is applied via PHP configuration check at page render time, so no code changes are needed.

---

## CDN Strategy

### Standard Global Mode

Load order for libraries:
1. cdnjs.cloudflare.com (accessible globally including China)
2. jsDelivr (accessible globally)
3. Self-hosted fallback

### China-Specific Libraries

All Bootstrap, jQuery, Font Awesome, and other UI libraries are loaded from:
- **cdnjs.cloudflare.com** (first choice — accessible in China)
- Self-hosted copy in `/assets/vendor/` (ultimate fallback)

### Fonts

```html
<!-- Standard mode: Google Fonts (blocked in China) -->
<link href="https://fonts.googleapis.com/css2?family=Inter" rel="stylesheet">

<!-- China mode: Self-hosted -->
<link href="/assets/fonts/inter.css" rel="stylesheet">

<!-- China mode alternative: Bunny Fonts -->
<link href="https://fonts.bunny.net/css?family=inter" rel="stylesheet">
```

---

## Chinese Payment Methods (Future)

| Method | Status | Notes |
|--------|--------|-------|
| Alipay | ⏳ Planned (Phase 12) | Dominant in China |
| WeChat Pay | ⏳ Planned (Phase 12) | Second-largest in China |
| UnionPay | ⏳ Planned | State card network |

---

## SEO for Chinese Market

| Strategy | Detail |
|----------|--------|
| Baidu sitemap | Submit `sitemap.xml` to Baidu Webmaster Tools |
| Baidu Analytics | Add Baidu Tongji script for Chinese traffic (optional) |
| ICP Licence | Required to host a website accessible in China (.cn domain) |
| Language | Simplified Chinese (`zh-Hans`) and Traditional Chinese (`zh-Hant`) |

> Note: GlobexSky targets Chinese buyers/suppliers trading internationally, not a China-hosted platform. An ICP licence is required only if hosting from Chinese servers, which is not the current plan.

---

## Testing China Accessibility

Tools to verify China accessibility:

| Tool | URL | Purpose |
|------|-----|---------|
| Comparitech GFW Test | Available online | Check if domain/URL is blocked |
| 17ce.com | `17ce.com` | Multi-region speed test from China |
| ICP備案 check | miit.gov.cn | Verify ICP licence status |
