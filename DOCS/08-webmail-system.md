# GlobexSky — Webmail (Internal Messaging) System

## Overview

GlobexSky includes an internal webmail-style messaging system. All platform users — buyers, suppliers, admins, inspectors, carriers — can communicate within the platform without exposing personal email addresses. This is different from the real-time chat system; webmail is asynchronous, thread-based, and supports attachments.

---

## Features

| Feature | Detail |
|---------|--------|
| Inbox / Sent / Drafts / Trash | Standard folder structure |
| Compose | Rich text editor (bold, italic, bullet lists, links) |
| Attachments | Up to 10MB per message, 25MB per thread total |
| Contacts | Auto-populated from platform connections (buyers you've transacted with, etc.) |
| Search | Full-text search across subject and body |
| Labels / Tags | Custom labels (e.g., "Urgent", "Contract", "RFQ") |
| Read Receipts | Sender can see when message was read |
| Threads | Messages grouped by conversation thread |
| Pagination | 50 messages per page |
| Mark as unread | Manually mark read messages as unread |
| Star / Flag | Star important messages |
| Bulk actions | Delete, move, mark read/unread on multiple messages |

---

## Access Rules by Role

| Role | Can Send To | Can Receive From | Notes |
|------|------------|-----------------|-------|
| Buyer | Supplier, Support | Supplier, Admin | Cannot message other buyers |
| Supplier | Buyer, Admin | Buyer, Admin, Inspector | Can message buyers they've transacted with |
| Admin | All roles | All roles | Full access |
| Inspector | Supplier, Admin | Supplier, Admin | Related to assigned inspections only |
| Carrier | Buyer (re: delivery), Admin | Buyer, Admin | Delivery-related messages |
| Dropshipper | Supplier (inquiry), Admin | Supplier, Admin | Inquiry messages |
| API User | Admin only | Admin | Technical support |

---

## Notification Integration

| Event | In-App Badge | Email | Push (Future) |
|-------|-------------|-------|--------------|
| New message received | ✅ | ✅ (if offline) | ✅ |
| Message reply | ✅ | ✅ (if offline) | ✅ |
| Attachment received | ✅ | ✅ (if offline) | ✅ |
| Message marked urgent | ✅ | ✅ (always) | ✅ |

Badge count shown in top navigation bar (unread count).

---

## Attachment Rules

| Plan (Supplier) | Max Attachment Size | Max per Thread |
|----------------|--------------------|--------------:|
| All plans (buyer/supplier/other) | 10 MB per file | 25 MB total |
| Allowed formats | JPG, PNG, PDF, DOCX, XLSX, ZIP | — |
| Prohibited | EXE, PHP, SH, BAT, JS | — |

---

## Database Schema

```sql
messages (
    id, thread_id, sender_id, sender_role,
    subject, body (TEXT), created_at, is_draft
)

message_threads (
    id, subject, created_by, created_at, updated_at
)

message_recipients (
    id, message_id, recipient_id, recipient_role,
    folder (inbox/sent/trash), is_read, read_at,
    is_starred, label, deleted_at
)

message_attachments (
    id, message_id, filename, stored_filename,
    file_size, mime_type, uploaded_at
)
```

---

## Technical Implementation

### Backend
- Messages stored in MySQL (`messages`, `message_threads`, `message_recipients`, `message_attachments`).
- PHP handles compose, send, reply, forward, delete, search.
- File uploads stored in `/storage/messages/attachments/` with randomised filenames.
- Attachment access controlled by auth check (only sender and recipient).

### Frontend
- Custom UI built with Bootstrap 5.
- Inbox loads via AJAX (no full page reload).
- Compose window: modal dialog with rich text editor (Quill.js or TinyMCE).
- File attachment: drag-and-drop or file picker, with upload progress bar.

### Real-Time (Future)
- When Socket.io (Phase 5) is implemented, new message notifications will arrive in real-time without page refresh.
- Until then: AJAX polling every 30 seconds for new message badge count.

---

## System Message Types

Besides user-to-user messages, the system generates automated messages in the inbox:

| Event | Auto-Message Sender | Recipient |
|-------|-------------------|-----------|
| Order placed | System | Supplier |
| Order status change | System | Buyer |
| KYC approved/rejected | Admin | Supplier |
| Payout processed | System | Supplier |
| Dispute opened | System | Both parties |
| Inspection report ready | Inspector | Buyer |

These appear as system messages (non-replyable) in the recipient's inbox.

---

## Search Functionality

- Full-text search on subject and body.
- Filter by: folder, sender, date range, label, has attachment.
- Results highlighted with search term.
- Search index updated on message insert.
