# GlobexSky Documentation Index

Welcome to the GlobexSky project documentation. This folder contains comprehensive specifications, architecture decisions, and planning documents for the GlobexSky B2B/B2C e-commerce platform.

## 📚 Document Index

| # | File | Description |
|---|------|-------------|
| — | [README.md](README.md) | This index file |
| 01 | [01-project-overview.md](01-project-overview.md) | Full project overview, tech stack, hosting, current state, gap analysis |
| 02 | [02-pricing-commission.md](02-pricing-commission.md) | 6 revenue streams, commission tiers, platform fees, competitor comparison |
| 03 | [03-dropshipping-flow.md](03-dropshipping-flow.md) | End-to-end dropshipping workflow, plan requirements |
| 04 | [04-supplier-system.md](04-supplier-system.md) | Supplier lifecycle, KYC levels, profile, dashboard |
| 05 | [05-free-vs-premium.md](05-free-vs-premium.md) | Feature comparison table: Free vs Pro vs Enterprise |
| 06 | [06-premium-package.md](06-premium-package.md) | Pricing structure, duration discounts, add-ons, service charges |
| 07 | [07-product-upload.md](07-product-upload.md) | Taobao-style product upload: fields, media, variations, SKU matrix |
| 08 | [08-webmail-system.md](08-webmail-system.md) | Internal messaging system, access rules, notifications, implementation |
| 09 | [09-inspection-service.md](09-inspection-service.md) | Quality inspection flow, types, inspector & buyer dashboards |
| 10 | [10-buyer-fraud-prevention.md](10-buyer-fraud-prevention.md) | Fraud prevention: device fingerprinting, IP analysis, risk scoring |
| 11 | [11-admin-config.md](11-admin-config.md) | Admin configuration system, health checks, Go Live process |
| 12 | [12-feature-toggle.md](12-feature-toggle.md) | 28 feature toggles, implementation, audit log |
| 13 | [13-platform-commission.md](13-platform-commission.md) | Detailed commission formula, tiers, category overrides, plan discounts |
| 14 | [14-notification-system.md](14-notification-system.md) | Multi-channel notifications, events by role, online/offline rules |
| 15 | [15-supplier-policies.md](15-supplier-policies.md) | Supplier rules, rating system, disputes, payouts, termination |
| 16 | [16-realtime-chat.md](16-realtime-chat.md) | 3-layer chat architecture: Socket.io + Pusher + AJAX fallback |
| 17 | [17-live-streaming.md](17-live-streaming.md) | Video streaming: PeerJS WebRTC + Jitsi Meet, China-safe |
| 18 | [18-feature-spec-500.md](18-feature-spec-500.md) | Complete 500+ feature specification: frontend, supplier, admin |
| 19 | [19-advanced-features.md](19-advanced-features.md) | Alibaba-like: Trade Shows, Trade Finance, VR Showroom, Loyalty, AI Sourcing |
| 20 | [20-hosting-environment.md](20-hosting-environment.md) | Namecheap shared hosting specs, Node.js, file structure, environment |
| 21 | [21-china-gfw.md](21-china-gfw.md) | Great Firewall considerations, blocked services, China-safe alternatives |
| 22 | [22-self-hosted-alternatives.md](22-self-hosted-alternatives.md) | Free self-hosted alternatives to Google/paid services |
| 23 | [23-security-audit.md](23-security-audit.md) | Security audit results, OWASP Top 10 compliance, headers, rate limiting |
| 24 | [24-performance-optimization.md](24-performance-optimization.md) | OPcache, query optimizer, asset optimization, caching strategy |
| 25 | [25-complete-53-pr-plan.md](25-complete-53-pr-plan.md) | **Complete 53-PR development master plan** — definitive PR breakdown |
| — | [PRODUCTION-CHECKLIST.md](PRODUCTION-CHECKLIST.md) | Production deployment checklist (critical/high/medium/final) |
| — | [ROADMAP.md](ROADMAP.md) | 12-phase development roadmap with status |

## 🔗 Quick Links

- **Current Phase:** Phase 1 ✅ Complete | Phase 2-4 🔄 In Progress
- **Tech Stack:** PHP 8.x · MySQL · Bootstrap 5 · Apache · Node.js · DeepSeek AI
- **Hosting:** Namecheap Shared Hosting · cPanel · AutoSSL
- **Repo:** [nurnobism/globexskyphp](https://github.com/nurnobism/globexskyphp)

## 📌 Notes

- All documentation is **specification only** — it describes intended behaviour and design decisions.
- Implementation status per feature is tracked in [ROADMAP.md](ROADMAP.md).
- Do **not** modify any source code from this folder.
