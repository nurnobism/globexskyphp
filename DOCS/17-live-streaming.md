# GlobexSky — Live Streaming System

## Overview

GlobexSky suppliers can broadcast live video sessions to showcase products, conduct factory tours, host flash sales, and interact with buyers in real time. The streaming system is designed to work in China (avoiding blocked Western services) and scale from small product demos to large virtual trade show booths.

---

## 2-Layer Streaming Architecture

| Layer | Technology | Use Case | China Compatible |
|-------|-----------|----------|-----------------|
| **Primary** | PeerJS (WebRTC P2P) | Small streams, < 10 viewers | ✅ Yes |
| **Secondary** | Jitsi Meet (self-hosted) | Larger streams, 10–500 viewers | ✅ Yes |

### Why Not…

| Service | Reason Not Used |
|---------|----------------|
| YouTube Live | ❌ Blocked in China |
| Google Meet | ❌ Blocked in China |
| Zoom | ❌ Blocked in China |
| Twitch | ❌ Blocked in China |
| Facebook Live | ❌ Blocked in China |
| AWS Media Services | ❌ AWS CDN partially blocked in China |

---

## Layer 1 — PeerJS (WebRTC P2P)

| Property | Detail |
|----------|--------|
| Protocol | WebRTC (direct peer-to-peer) |
| Library | PeerJS (simplifies WebRTC signaling) |
| Media server | None required (P2P direct) |
| Best for | < 10 viewers |
| Latency | Very low (< 500ms) |
| Server role | PeerJS signaling server only (very lightweight) |
| Hosting | Node.js on Namecheap |

**Limitation**: P2P does not scale beyond ~10 viewers due to bandwidth on the streamer's connection. For larger audiences, system auto-prompts switch to Jitsi.

---

## Layer 2 — Jitsi Meet (Self-Hosted)

| Property | Detail |
|----------|--------|
| Type | Open-source video conferencing |
| Hosting | Self-hosted server (VPS or Namecheap) |
| Viewer limit | 500+ (with adequate server) |
| China compatible | ✅ Yes (not blocked, no Google dependencies) |
| Recording | Built-in (Jibri component) |
| Features | Screen share, hand raise, Q&A, reactions |

---

## Live Stream Features

| Feature | PeerJS | Jitsi |
|---------|--------|-------|
| Live video | ✅ | ✅ |
| Live chat (Q&A) | ✅ | ✅ |
| Screen share | ✅ | ✅ |
| Session recording | ❌ | ✅ |
| Schedule stream | ✅ | ✅ |
| Viewer count | ✅ | ✅ |
| Reactions (👍❤️🔥) | ✅ | ✅ |
| Product links overlay | ✅ | ✅ |
| Buy Now popup | ✅ | ✅ |
| Multi-camera | ❌ (future OBS) | ✅ |
| Bandwidth auto-adjust | ✅ (WebRTC built-in) | ✅ |

---

## Stream Types

| Type | Description | Typical Use |
|------|-------------|------------|
| **Product Launch** | Showcase new product with live demo | New arrivals, promotions |
| **Factory Tour** | Walk through production facility | Build trust with B2B buyers |
| **Trade Show Booth** | Virtual booth during a trade event | B2B lead generation |
| **Supplier Meeting** | 1-on-1 or small group with buyers | RFQ negotiation |
| **Training Session** | Teach buyers how to use products | After-sales support |
| **Flash Sale** | Timed sale with live host | Drive urgency, high conversion |

---

## Plan Limits

| Feature | Free | Pro | Enterprise |
|---------|------|-----|-----------|
| Live streams per week | ❌ | 2 | Unlimited |
| Stream duration | — | Up to 1 hour | Up to 8 hours |
| Viewer limit | — | 500 | Unlimited |
| Session recording | — | ✅ | ✅ |
| Trade show streams | ❌ | ❌ | ✅ |

---

## Supplier Streaming Dashboard

| Feature | Detail |
|---------|--------|
| Schedule a stream | Set date, time, title, description, product links |
| Start stream | One-click start (PeerJS first, switch to Jitsi if > 10 viewers) |
| Manage scheduled streams | Edit, cancel, rescheduled |
| Past recordings | View and share recordings (Jitsi only) |
| Viewer analytics | Peak viewers, average duration, engagement rate |
| Earnings from stream | Track sales attributed to live sessions |

---

## Buyer Stream Experience

| Feature | Detail |
|---------|--------|
| Stream discovery | Homepage "Live Now" section, category stream list |
| Watch page | Video + live chat + product panel |
| Live chat | Real-time Q&A alongside the stream |
| Product panel | Products shown during stream with "Add to Cart" button |
| Buy Now popup | Supplier can trigger pop-up on a featured product |
| Reactions | Tap reaction buttons during stream |
| Replay | Watch recording after stream ends (Jitsi) |
| Notify me | Subscribe to supplier stream notifications |

---

## Technical Flow

```
Supplier clicks "Go Live"
        │
        ▼
PeerJS: request camera/mic permission
        │
        ▼
PeerJS signaling server creates session ID
        │
        ▼
Viewers join via session ID (direct P2P connection)
        │
        ├─ If viewers < 10 → stay on PeerJS
        │
        └─ If viewers ≥ 10 → prompt: "Switch to Jitsi for better experience"
                │
                ▼
           Jitsi room created, all participants redirected
```

---

## Database Schema

```sql
live_streams (
    id, supplier_id, title, description,
    scheduled_at, started_at, ended_at,
    stream_type, platform (peerjs/jitsi),
    status (scheduled/live/ended/cancelled),
    viewer_peak, recording_url, created_at
)

stream_products (
    id, stream_id, product_id, display_order, added_at
)

stream_viewers (
    id, stream_id, user_id, joined_at, left_at
)
```
