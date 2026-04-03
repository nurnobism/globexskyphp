# GlobexSky — Real-Time Chat System

## Overview

GlobexSky's chat system uses a **3-layer architecture** to guarantee message delivery in all network conditions — including restrictive environments like mainland China.

---

## Architecture Overview

```
Layer 1 (Primary):   Socket.io (Node.js)  — WebSocket real-time
Layer 2 (Secondary): Pusher              — SaaS fallback
Layer 3 (Tertiary):  AJAX polling (5s)   — Universal fallback
```

The client automatically falls back to the next layer if the current layer is unavailable.

---

## Layer 1 — Socket.io (Primary)

| Property | Detail |
|----------|--------|
| Server | Node.js with Socket.io library |
| Protocol | WebSocket (TCP, persistent connection) |
| Hosting | Namecheap Node.js via cPanel Selector |
| Port | Custom port (e.g., 3000) proxied by Apache |
| Authentication | JWT token issued at login, verified by Node.js server |
| Features | Real-time, typing indicator, read receipts, online status |
| Delivery | Instant (< 100ms) |

### Socket Events

| Event | Direction | Payload |
|-------|-----------|---------|
| `message:send` | Client → Server | `{room_id, body, type}` |
| `message:receive` | Server → Client | `{message_id, sender, body, timestamp}` |
| `typing:start` | Client → Server | `{room_id}` |
| `typing:stop` | Client → Server | `{room_id}` |
| `typing:indicator` | Server → Client | `{room_id, user_id, username}` |
| `message:read` | Client → Server | `{message_id}` |
| `message:read_receipt` | Server → Client | `{message_id, read_by, read_at}` |
| `user:online` | Server → Client | `{user_id}` |
| `user:offline` | Server → Client | `{user_id}` |
| `room:join` | Client → Server | `{room_id}` |
| `room:leave` | Client → Server | `{room_id}` |

---

## Layer 2 — Pusher (Secondary)

| Property | Detail |
|----------|--------|
| Provider | Pusher.com (SaaS) |
| Free tier | 200,000 messages/day, 100 concurrent connections |
| Activation | Auto-switch if Socket.io server unreachable |
| Latency | ~200–500ms |
| Features | Real-time, no typing indicator in free tier |

Pusher is configured as a standby channel via the Pusher PHP SDK and JavaScript SDK.

---

## Layer 3 — AJAX Polling (Tertiary)

| Property | Detail |
|----------|--------|
| Method | `setInterval` polling every 5 seconds |
| Endpoint | `GET /api/chat/poll?room_id={id}&since={timestamp}` |
| Activation | Auto-switch if both Socket.io and Pusher fail |
| Latency | Up to 5 seconds |
| Features | Messages only (no real-time indicators) |

---

## Chat Features

| Feature | Socket.io | Pusher | AJAX |
|---------|----------|--------|------|
| 1-to-1 messaging | ✅ | ✅ | ✅ |
| Group chat | ✅ | ✅ | ✅ |
| File sharing | ✅ | ✅ | ✅ |
| Emoji reactions | ✅ | ✅ | ✅ |
| Typing indicator | ✅ | ❌ | ❌ |
| Read receipts | ✅ | ✅ | ✅ |
| Message search | ✅ | ✅ | ✅ |
| Chat history | ✅ | ✅ | ✅ |
| Online status | ✅ | ❌ | ❌ |
| Reply to message | ✅ | ✅ | ✅ |
| Forward message | ✅ | ✅ | ✅ |

---

## Chat Room Types

| Room Type | Participants | Purpose |
|-----------|------------|---------|
| `order` | Buyer + Supplier | Discuss a specific order |
| `inquiry` | Buyer + Supplier | Pre-purchase product inquiry |
| `support` | User + Admin | Platform support ticket |
| `rfq` | Buyer + Supplier(s) | Request for Quote negotiation |
| `group` | Multiple | Internal admin team or supplier group |

Room IDs are deterministic:
- Order chat: `order_{order_id}`
- Inquiry: `inquiry_{product_id}_{buyer_id}`
- Support: `support_{ticket_id}`

---

## Database Schema

```sql
chat_rooms (
    id, type (order/inquiry/support/rfq/group),
    reference_id, name, created_at
)

chat_room_members (
    id, room_id, user_id, user_role, joined_at, left_at
)

chat_messages (
    id, room_id, sender_id, sender_role,
    message_type (text/image/file/system),
    body TEXT, file_url, file_name, file_size,
    reply_to_id, is_deleted, created_at
)

chat_read_receipts (
    id, message_id, user_id, read_at
)
```

---

## File Sharing Rules

| Rule | Limit |
|------|-------|
| Max file size | 10 MB |
| Allowed types | JPG, PNG, PDF, DOCX, MP4 (short), ZIP |
| Prohibited | EXE, PHP, SH, BAT |
| Storage | `/storage/chat/` with UUID filenames |
| Access | Auth-gated download URL |

---

## Security

- All WebSocket connections authenticated via JWT (signed with app secret).
- Messages sanitised server-side before storage and broadcast.
- Rate limiting: max 60 messages/minute per user.
- Users can only join rooms they are participants of.
- Admin can monitor and delete any chat room.

---

## Implementation Timeline

| Phase | Feature |
|-------|---------|
| Phase 1 (Done) | DB schema created, UI scaffolded |
| Phase 5 | Socket.io server deployed, real-time chat live |
| Phase 5 | Pusher fallback configured |
| Phase 5 | AJAX polling tertiary fallback |
| Phase 9 | Chat moderation tools for admin |
