require('dotenv').config();
const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const cors = require('cors');
const jwt = require('jsonwebtoken');
const mysql = require('mysql2/promise');

const PORT = process.env.PORT || 3001;
const CORS_ORIGIN = process.env.CORS_ORIGIN || '*';
if (CORS_ORIGIN === '*') {
    console.warn('[security] WARNING: CORS_ORIGIN is set to "*". Restrict this in production.');
}
const SHUTDOWN_TIMEOUT_MS = parseInt(process.env.SHUTDOWN_TIMEOUT_MS || '5000', 10);

// Require JWT_SECRET and INTERNAL_API_KEY to be explicitly configured
const JWT_SECRET = process.env.JWT_SECRET;
const INTERNAL_API_KEY = process.env.INTERNAL_API_KEY;

if (!JWT_SECRET) {
    console.error('[startup] FATAL: JWT_SECRET environment variable is required.');
    process.exit(1);
}
if (!INTERNAL_API_KEY) {
    console.error('[startup] FATAL: INTERNAL_API_KEY environment variable is required.');
    process.exit(1);
}

// --- Database pool ---
const db = mysql.createPool({
    host: process.env.DB_HOST || 'localhost',
    database: process.env.DB_NAME || 'globexsky',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASS || '',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0,
});

// --- Express app ---
const app = express();

// CORS_ORIGIN is a server-controlled environment variable, not user input.
// When set to '*', we intentionally reject all cross-origin requests to avoid
// the credentials+wildcard footgun; operators must set a specific origin in production.
const corsOrigins = CORS_ORIGIN === '*'
    ? false // disables CORS entirely when wildcard is set; warning was emitted at startup
    : CORS_ORIGIN.split(',').map(o => o.trim()).filter(Boolean);

app.use(cors({
    origin: corsOrigins,
    methods: ['GET', 'POST'],
    credentials: true,
}));
app.use(express.json());

// Internal API key middleware
function requireInternalKey(req, res, next) {
    const authHeader = req.headers['authorization'] || '';
    if (authHeader !== `Bearer ${INTERNAL_API_KEY}`) {
        return res.status(401).json({ error: 'Unauthorized' });
    }
    next();
}

// Health check
app.get('/', (_req, res) => res.json({ status: 'GlobexSky Realtime OK' }));
app.get('/health', (_req, res) => res.json({ status: 'ok', uptime: process.uptime() }));

// Online status check — lets PHP query whether a user is online
app.get('/online-status/:userId', requireInternalKey, (req, res) => {
    const uid = parseInt(req.params.userId, 10);
    if (!Number.isFinite(uid)) {
        return res.status(400).json({ error: 'Invalid userId' });
    }
    const sockets = userSockets.get(uid);
    const isOnline = !!(sockets && sockets.size > 0);
    res.json({ userId: uid, isOnline, connectedSockets: isOnline ? sockets.size : 0 });
});

// Push a notification to a specific user
app.post('/internal/notify', requireInternalKey, (req, res) => {
    const { targetUserId, notification } = req.body || {};
    const uid = parseInt(targetUserId, 10);
    if (!Number.isFinite(uid) || !notification) {
        return res.status(400).json({ error: 'Valid targetUserId (integer) and notification required' });
    }
    const delivered = pushNotificationToUser(uid, notification);
    io.to(`user:${uid}`).emit('notification', notification);
    res.json({ success: true, delivered });
});

// PHP callback: new chat message — broadcast to conversation room
app.post('/internal/chat-message', requireInternalKey, (req, res) => {
    const { conversationId, senderId, messageId, content, type } = req.body || {};
    if (!conversationId) {
        return res.status(400).json({ error: 'conversationId required' });
    }
    const payload = {
        id: messageId,
        conversationId,
        senderId,
        content,
        type: type || 'text',
        sentAt: new Date().toISOString(),
    };
    io.to(`conversation:${conversationId}`).emit('new_message', payload);
    res.json({ success: true });
});

// Admin broadcast to all connected clients
app.post('/internal/broadcast', requireInternalKey, (req, res) => {
    const { event, data, scope } = req.body || {};
    if (!event) return res.status(400).json({ error: 'event required' });
    // scope='all' broadcasts to every connected socket; default is admin room only
    if (scope === 'all') {
        io.emit(event, data || {});
    } else {
        io.to('admin:broadcast').emit(event, data || {});
    }
    res.json({ success: true });
});

// --- HTTP server ---
const server = http.createServer(app);

// --- Socket.io ---
const io = new Server(server, {
    cors: {
        origin: corsOrigins || false,
        methods: ['GET', 'POST'],
        credentials: true,
    },
    pingTimeout: 60000,
    pingInterval: 25000,
});

// In-memory map: userId -> Set of socketIds (a user may have multiple tabs open)
const userSockets = new Map();

// ── Phase 7: Live Streaming In-Memory State ──────────────────────────────────
const activeStreams = new Map();   // streamId -> { streamerId, title, viewers: Set, pinnedProduct, startedAt }
const streamBans   = new Map();   // streamId -> Set of banned userIds

function streamRoom(streamId) {
    return `stream_${streamId}`;
}

function handleViewerLeave(socket, streamId) {
    const room = streamRoom(streamId);
    const stream = activeStreams.get(streamId);
    if (stream) {
        stream.viewers.delete(socket.id);
        const count = stream.viewers.size;
        io.to(room).emit('stream_viewer_count', { streamId, count });
    }
    socket.leave(room);
}

// ── Helpers ──────────────────────────────────────────────────────────────────

async function setUserOnline(userId, socketId) {
    try {
        await db.execute(
            `INSERT INTO user_online_status (user_id, is_online, last_seen, socket_id)
             VALUES (?, 1, NOW(), ?)
             ON DUPLICATE KEY UPDATE is_online = 1, last_seen = NOW(), socket_id = ?`,
            [userId, socketId, socketId]
        );
    } catch (err) {
        console.error('[DB] setUserOnline error:', err.message);
    }
}

async function setUserOffline(userId) {
    try {
        await db.execute(
            `UPDATE user_online_status
             SET is_online = 0, last_seen = NOW(), socket_id = NULL
             WHERE user_id = ?`,
            [userId]
        );
    } catch (err) {
        console.error('[DB] setUserOffline error:', err.message);
    }
}

async function saveMessage(conversationId, senderId, content, type, extra) {
    const { attachmentsJson = null } = extra || {};
    const truncated = String(content || '').substring(0, 5000);
    const [result] = await db.execute(
        `INSERT INTO messages
            (conversation_id, sender_id, content, type, attachments_json, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())`,
        [conversationId, senderId, truncated || null, type || 'text', attachmentsJson]
    );
    const msgId = result.insertId;
    await db.execute(
        `UPDATE conversations
         SET last_message_id = ?, last_message_at = NOW(), updated_at = NOW()
         WHERE id = ?`,
        [msgId, conversationId]
    );
    return msgId;
}

async function markMessageRead(messageId, userId) {
    try {
        await db.execute(
            `INSERT IGNORE INTO message_reads (message_id, user_id, read_at)
             VALUES (?, ?, NOW())`,
            [messageId, userId]
        );
        // Also bump conversation_participants last_read_at
        await db.execute(
            `UPDATE conversation_participants SET last_read_at = NOW()
             WHERE conversation_id = (SELECT conversation_id FROM messages WHERE id = ?)
               AND user_id = ?`,
            [messageId, userId]
        );
    } catch (err) {
        console.error('[DB] markMessageRead error:', err.message);
    }
}

async function isConversationParticipant(conversationId, userId) {
    const [rows] = await db.execute(
        'SELECT id FROM conversation_participants WHERE conversation_id = ? AND user_id = ?',
        [conversationId, userId]
    );
    return rows.length > 0;
}

function broadcastOnlineStatus(userId, isOnline) {
    // Emit both event names for compatibility
    io.emit('online_status', { userId, isOnline, lastSeen: new Date().toISOString() });
    io.emit('user_status',   { userId, isOnline, lastSeen: new Date().toISOString() });
}

function pushNotificationToUser(targetUserId, notification) {
    const sockets = userSockets.get(targetUserId);
    if (sockets && sockets.size > 0) {
        sockets.forEach((sid) => {
            io.to(sid).emit('notification', notification);
        });
        return true;
    }
    return false;
}

// ── JWT Middleware ────────────────────────────────────────────────────────────

io.use((socket, next) => {
    const token =
        socket.handshake.auth?.token ||
        socket.handshake.headers?.authorization?.replace('Bearer ', '');

    if (!token) {
        return next(new Error('Authentication token required'));
    }

    try {
        const decoded = jwt.verify(token, JWT_SECRET);
        // JWT must include `user_id` (integer) as the canonical user identifier
        const userId = parseInt(decoded.user_id, 10);
        if (!Number.isFinite(userId)) {
            return next(new Error('Invalid token payload: user_id must be an integer'));
        }
        socket.userId = userId;
        socket.userRole = decoded.role || 'buyer';
        socket.userName = decoded.name || decoded.username || '';
        next();
    } catch (err) {
        next(new Error('Invalid or expired token'));
    }
});

// ── Connection handler ────────────────────────────────────────────────────────

io.on('connection', async (socket) => {
    const userId = socket.userId;
    console.log(`[connect] user=${userId} socket=${socket.id}`);

    // Track socket
    if (!userSockets.has(userId)) {
        userSockets.set(userId, new Set());
    }
    userSockets.get(userId).add(socket.id);

    // Mark online in DB and broadcast
    await setUserOnline(userId, socket.id);
    broadcastOnlineStatus(userId, true);

    // Auto-join the user's personal notification room
    socket.join(`user:${userId}`);

    // Admins also join the broadcast channel
    if (socket.userRole === 'admin') {
        socket.join('admin:broadcast');
    }

    // ── join_conversation ──────────────────────────────────────────────────────
    socket.on('join_conversation', async ({ conversationId }, ack) => {
        if (!conversationId) return ack && ack({ error: 'conversationId required' });
        try {
            const [rows] = await db.execute(
                'SELECT id FROM conversation_participants WHERE conversation_id = ? AND user_id = ?',
                [conversationId, userId]
            );
            if (!rows.length) return ack && ack({ error: 'Not a participant of this conversation' });
            socket.join(`conversation:${conversationId}`);
            console.log(`[join_conversation] user=${userId} conversation=${conversationId}`);
            ack && ack({ success: true, conversationId });
        } catch (err) {
            console.error('[join_conversation] error:', err.message);
            ack && ack({ error: 'Server error' });
        }
    });

    // ── leave_conversation ─────────────────────────────────────────────────────
    socket.on('leave_conversation', ({ conversationId }, ack) => {
        if (!conversationId) return ack && ack({ error: 'conversationId required' });
        socket.leave(`conversation:${conversationId}`);
        console.log(`[leave_conversation] user=${userId} conversation=${conversationId}`);
        ack && ack({ success: true });
    });

    // ── online_status — query whether a user is online ─────────────────────────
    socket.on('online_status', ({ targetUserId }, ack) => {
        const uid = parseInt(targetUserId, 10);
        if (!Number.isFinite(uid)) return ack && ack({ error: 'targetUserId required' });
        const sockets = userSockets.get(uid);
        const isOnline = !!(sockets && sockets.size > 0);
        ack && ack({ userId: uid, isOnline });
    });

    // ── new_notification — push to a specific user (admin only) ───────────────
    socket.on('new_notification', (data, ack) => {
        if (socket.userRole !== 'admin') return ack && ack({ error: 'Unauthorized' });
        const { targetUserId, notification } = data || {};
        const uid = parseInt(targetUserId, 10);
        if (!Number.isFinite(uid) || !notification) {
            return ack && ack({ error: 'Valid targetUserId (integer) and notification required' });
        }
        const delivered = pushNotificationToUser(uid, notification);
        io.to(`user:${uid}`).emit('new_notification', notification);
        ack && ack({ success: true, delivered });
    });

    // ── join_room (legacy alias for join_conversation) ────────────────────────
    socket.on('join_room', async ({ roomId }, ack) => {
        if (!roomId) return ack && ack({ error: 'roomId required' });
        try {
            const allowed = await isConversationParticipant(roomId, userId);
            if (!allowed) return ack && ack({ error: 'Not a participant of this room' });
            socket.join(`conversation:${roomId}`);
            console.log(`[join_room] user=${userId} room=${roomId}`);
            ack && ack({ success: true, roomId });
        } catch (err) {
            console.error('[join_room] error:', err.message);
            ack && ack({ error: 'Server error' });
        }
    });

    // ── leave_room (legacy alias) ──────────────────────────────────────────────
    socket.on('leave_room', ({ roomId }, ack) => {
        if (!roomId) return ack && ack({ error: 'roomId required' });
        socket.leave(`conversation:${roomId}`);
        console.log(`[leave_room] user=${userId} room=${roomId}`);
        ack && ack({ success: true });
    });

    // ── send_message ───────────────────────────────────────────────────────────
    socket.on('send_message', async (data, ack) => {
        // Accept both conversationId and legacy roomId
        const cid = parseInt(data && (data.conversationId || data.roomId), 10);
        const content = (data && (data.content || data.message)) || '';
        const type = (data && data.type) || 'text';
        const attachments = (data && data.attachments) || null;

        if (!Number.isFinite(cid)) return ack && ack({ error: 'conversationId required' });
        if (!content && !attachments) return ack && ack({ error: 'content required' });

        try {
            const allowed = await isConversationParticipant(cid, userId);
            if (!allowed) return ack && ack({ error: 'Not a participant of this conversation' });

            const attachmentsJson = attachments ? JSON.stringify(attachments) : null;
            const messageId = await saveMessage(cid, userId, content, type, { attachmentsJson });

            const payload = {
                id: messageId,
                conversationId: cid,
                senderId: userId,
                senderName: socket.userName,
                content,
                type,
                attachments: attachments || null,
                createdAt: new Date().toISOString(),
            };

            // Broadcast to everyone in the conversation room
            io.to(`conversation:${cid}`).emit('new_message', payload);

            ack && ack({ success: true, messageId });
        } catch (err) {
            console.error('[send_message] error:', err.message);
            ack && ack({ error: 'Failed to send message' });
        }
    });

    // ── typing_start ───────────────────────────────────────────────────────────
    socket.on('typing_start', ({ conversationId, roomId }) => {
        const cid = parseInt(conversationId || roomId, 10);
        if (!Number.isFinite(cid)) return;
        socket.to(`conversation:${cid}`).emit('user_typing', {
            conversationId: cid,
            userId,
            userName: socket.userName,
            isTyping: true,
        });
    });

    // ── typing_stop ────────────────────────────────────────────────────────────
    socket.on('typing_stop', ({ conversationId, roomId }) => {
        const cid = parseInt(conversationId || roomId, 10);
        if (!Number.isFinite(cid)) return;
        socket.to(`conversation:${cid}`).emit('user_typing', {
            conversationId: cid,
            userId,
            userName: socket.userName,
            isTyping: false,
        });
    });

    // ── read_receipt ───────────────────────────────────────────────────────────
    socket.on('read_receipt', async ({ messageId, conversationId, roomId }, ack) => {
        if (!messageId) return ack && ack({ error: 'messageId required' });
        const cid = parseInt(conversationId || roomId, 10);

        try {
            await markMessageRead(messageId, userId);

            if (Number.isFinite(cid)) {
                socket.to(`conversation:${cid}`).emit('message_read', {
                    messageId,
                    conversationId: cid,
                    userId,
                    readAt: new Date().toISOString(),
                });
            }

            ack && ack({ success: true });
        } catch (err) {
            console.error('[read_receipt] error:', err.message);
            ack && ack({ error: 'Failed to record read receipt' });
        }
    });


    // ── push_notification (admin-only socket event) ────────────────────────────
    socket.on('push_notification', (data, ack) => {
        if (socket.userRole !== 'admin') {
            return ack && ack({ error: 'Unauthorized' });
        }
        const { targetUserId, notification } = data || {};
        const uid = parseInt(targetUserId, 10);
        if (!Number.isFinite(uid) || !notification) {
            return ack && ack({ error: 'Valid targetUserId (integer) and notification required' });
        }
        const delivered = pushNotificationToUser(uid, notification);
        ack && ack({ success: true, delivered });
    });

    // ── Phase 7: Live Streaming Events ─────────────────────────────────────────

    socket.on('stream_start', (data) => {
        const { streamId, title, category } = data || {};
        if (!streamId) return;

        const room = streamRoom(streamId);
        socket.join(room);

        activeStreams.set(streamId, {
            streamerId: userId,
            title: title || 'Live Stream',
            category: category || 'general',
            viewers: new Set(),
            pinnedProduct: null,
            startedAt: Date.now(),
            streamerSocketId: socket.id
        });
        streamBans.set(streamId, new Set());

        io.emit('stream_started', {
            streamId,
            streamerId: userId,
            title,
            category,
            startedAt: new Date().toISOString()
        });
        console.log(`[stream_start] stream=${streamId} user=${userId}`);
    });

    socket.on('stream_end', (data) => {
        const { streamId } = data || {};
        if (!streamId) return;

        const stream = activeStreams.get(streamId);
        if (stream && stream.streamerId === userId) {
            const room = streamRoom(streamId);
            io.to(room).emit('stream_ended', {
                streamId,
                duration: Date.now() - stream.startedAt,
                peakViewers: stream.viewers.size
            });
            activeStreams.delete(streamId);
            streamBans.delete(streamId);
            console.log(`[stream_end] stream=${streamId}`);
        }
    });

    socket.on('stream_join', (data) => {
        const { streamId } = data || {};
        if (!streamId) return;

        const bans = streamBans.get(streamId);
        if (bans && bans.has(userId)) {
            socket.emit('stream_error', { message: 'You are banned from this stream.' });
            return;
        }

        const room = streamRoom(streamId);
        const stream = activeStreams.get(streamId);

        socket.join(room);
        socket.streamId = streamId;

        if (stream) {
            stream.viewers.add(socket.id);
            const count = stream.viewers.size;
            io.to(room).emit('stream_viewer_count', { streamId, count });

            if (stream.pinnedProduct) {
                socket.emit('stream_product_pin', stream.pinnedProduct);
            }
        }
        console.log(`[stream_join] user=${userId} stream=${streamId}`);
    });

    socket.on('stream_leave', (data) => {
        const { streamId } = data || {};
        if (streamId) handleViewerLeave(socket, streamId);
    });

    socket.on('stream_chat', (data) => {
        const { streamId, message, type } = data || {};
        if (!streamId || !message) return;

        const bans = streamBans.get(streamId);
        if (bans && bans.has(userId)) return;

        const room = streamRoom(streamId);
        io.to(room).emit('stream_chat_message', {
            id: require('crypto').randomUUID(),
            streamId,
            userId,
            username: socket.userName || 'Anonymous',
            message: String(message).substring(0, 500),
            type: type || 'message',
            timestamp: new Date().toISOString()
        });
    });

    socket.on('stream_reaction', (data) => {
        const { streamId, emoji } = data || {};
        if (!streamId || !emoji) return;

        const room = streamRoom(streamId);
        io.to(room).emit('stream_reaction_broadcast', {
            streamId,
            userId,
            emoji,
            id: require('crypto').randomUUID()
        });
    });

    socket.on('stream_product_pin', (data) => {
        const { streamId, product } = data || {};
        if (!streamId || !product) return;

        const stream = activeStreams.get(streamId);
        if (stream && stream.streamerId === userId) {
            stream.pinnedProduct = product;
            const room = streamRoom(streamId);
            io.to(room).emit('stream_product_pin', { streamId, product });
        }
    });

    socket.on('stream_product_unpin', (data) => {
        const { streamId } = data || {};
        if (!streamId) return;

        const stream = activeStreams.get(streamId);
        if (stream && stream.streamerId === userId) {
            stream.pinnedProduct = null;
            const room = streamRoom(streamId);
            io.to(room).emit('stream_product_unpin', { streamId });
        }
    });

    socket.on('stream_question', (data) => {
        const { streamId, question } = data || {};
        if (!streamId || !question) return;

        const stream = activeStreams.get(streamId);
        if (!stream) return;

        const room = streamRoom(streamId);
        const questionData = {
            id: require('crypto').randomUUID(),
            streamId,
            userId,
            username: socket.userName || 'Anonymous',
            question: String(question).substring(0, 500),
            timestamp: new Date().toISOString()
        };
        io.to(stream.streamerSocketId).emit('stream_question_received', questionData);
        io.to(room).emit('stream_question_broadcast', questionData);
    });

    socket.on('stream_ban', (data) => {
        const { streamId, targetUserId } = data || {};
        if (!streamId || !targetUserId) return;

        const stream = activeStreams.get(streamId);
        if (stream && stream.streamerId === userId) {
            let bans = streamBans.get(streamId);
            if (!bans) {
                bans = new Set();
                streamBans.set(streamId, bans);
            }
            bans.add(targetUserId);
            const room = streamRoom(streamId);
            io.to(room).emit('stream_user_banned', { streamId, userId: targetUserId });
            console.log(`[stream_ban] user=${targetUserId} from stream=${streamId}`);
        }
    });

    // WebRTC signaling
    socket.on('webrtc_offer', (data) => {
        const { streamId, offer, targetId } = data || {};
        if (targetId) {
            io.to(targetId).emit('webrtc_offer', { offer, senderId: socket.id, streamId });
        } else if (streamId) {
            socket.to(streamRoom(streamId)).emit('webrtc_offer', { offer, senderId: socket.id, streamId });
        }
    });

    socket.on('webrtc_answer', (data) => {
        const { targetId, answer, streamId } = data || {};
        if (targetId) {
            io.to(targetId).emit('webrtc_answer', { answer, senderId: socket.id, streamId });
        }
    });

    socket.on('webrtc_ice_candidate', (data) => {
        const { targetId, candidate, streamId } = data || {};
        if (targetId) {
            io.to(targetId).emit('webrtc_ice_candidate', { candidate, senderId: socket.id, streamId });
        } else if (streamId) {
            socket.to(streamRoom(streamId)).emit('webrtc_ice_candidate', { candidate, senderId: socket.id, streamId });
        }
    });

    // ── disconnect ─────────────────────────────────────────────────────────────
    socket.on('disconnect', async (reason) => {
        console.log(`[disconnect] user=${userId} socket=${socket.id} reason=${reason}`);

        // Clean up streaming state
        if (socket.streamId) {
            handleViewerLeave(socket, socket.streamId);
        }

        const sockets = userSockets.get(userId);
        if (sockets) {
            sockets.delete(socket.id);
            if (sockets.size === 0) {
                userSockets.delete(userId);
                // Only mark offline when the last socket closes
                await setUserOffline(userId);
                broadcastOnlineStatus(userId, false);
            }
        }
    });

    socket.on('error', (err) => {
        console.error(`[socket error] user=${userId}:`, err.message);
    });
});

// ── Phase 7: Periodic viewer count broadcast ─────────────────────────────────
setInterval(() => {
    for (const [streamId, stream] of activeStreams.entries()) {
        const room = streamRoom(streamId);
        const count = stream.viewers.size;
        io.to(room).emit('stream_viewer_count', { streamId, count });
    }
}, 10000);

// ── Graceful shutdown ─────────────────────────────────────────────────────────

async function shutdown(signal) {
    console.log(`\n[shutdown] Received ${signal}. Closing server…`);
    io.close(() => console.log('[shutdown] Socket.io closed'));
    server.close(async () => {
        await db.end();
        console.log('[shutdown] DB pool closed. Bye!');
        process.exit(0);
    });
    setTimeout(() => process.exit(1), SHUTDOWN_TIMEOUT_MS);
}

process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));

// ── Start ─────────────────────────────────────────────────────────────────────

server.listen(PORT, () => {
    console.log(`[GlobexSky Realtime] Listening on port ${PORT}`);
});
