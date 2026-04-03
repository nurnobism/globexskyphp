require('dotenv').config();
const http = require('http');
const { Server } = require('socket.io');
const jwt = require('jsonwebtoken');
const mysql = require('mysql2/promise');
const cors = require('cors');

const PORT = process.env.PORT || 3001;
const CORS_ORIGIN = process.env.CORS_ORIGIN || '*';
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

// --- HTTP server ---
// All request handling is consolidated here to avoid double-execution.
const server = http.createServer((req, res) => {
    if (req.method === 'POST' && req.url === '/internal/notify') {
        const authHeader = req.headers['authorization'] || '';
        if (authHeader !== `Bearer ${INTERNAL_API_KEY}`) {
            res.writeHead(401, { 'Content-Type': 'application/json' });
            return res.end(JSON.stringify({ error: 'Unauthorized' }));
        }

        let body = '';
        req.on('data', (chunk) => { body += chunk; });
        req.on('end', () => {
            try {
                const { targetUserId, notification } = JSON.parse(body);
                const uid = parseInt(targetUserId, 10);
                if (!Number.isFinite(uid) || !notification) {
                    res.writeHead(400, { 'Content-Type': 'application/json' });
                    return res.end(JSON.stringify({ error: 'Valid targetUserId (integer) and notification required' }));
                }
                const delivered = pushNotificationToUser(uid, notification);
                io.to(`user:${uid}`).emit('notification', notification);
                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ success: true, delivered }));
            } catch (e) {
                res.writeHead(400, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ error: 'Invalid JSON' }));
            }
        });
        return;
    }

    // Health-check for all other requests
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ status: 'GlobexSky Realtime OK' }));
});

// --- Socket.io ---
const io = new Server(server, {
    cors: {
        origin: CORS_ORIGIN,
        methods: ['GET', 'POST'],
        credentials: true,
    },
    pingTimeout: 60000,
    pingInterval: 25000,
});

// In-memory map: userId -> Set of socketIds (a user may have multiple tabs open)
const userSockets = new Map();

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

async function saveMessage(roomId, senderId, message, type, extra) {
    const { fileUrl = null, fileName = null, fileSize = null, replyToId = null } = extra || {};
    const [result] = await db.execute(
        `INSERT INTO chat_messages
            (room_id, sender_id, message, type, file_url, file_name, file_size, reply_to_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [roomId, senderId, message, type || 'text', fileUrl, fileName, fileSize, replyToId]
    );
    // Update room's last-message preview
    const preview = (message || fileName || '').substring(0, 120);
    await db.execute(
        `UPDATE chat_rooms
         SET last_message_at = NOW(), last_message_preview = ?
         WHERE id = ?`,
        [preview, roomId]
    );
    return result.insertId;
}

async function markMessageRead(messageId, userId) {
    try {
        await db.execute(
            `INSERT IGNORE INTO message_read_receipts (message_id, user_id, read_at)
             VALUES (?, ?, NOW())`,
            [messageId, userId]
        );
        // Also bump participant last_read_at
        await db.execute(
            `UPDATE chat_participants SET last_read_at = NOW()
             WHERE room_id = (SELECT room_id FROM chat_messages WHERE id = ?)
               AND user_id = ?`,
            [messageId, userId]
        );
    } catch (err) {
        console.error('[DB] markMessageRead error:', err.message);
    }
}

async function isRoomParticipant(roomId, userId) {
    const [rows] = await db.execute(
        'SELECT id FROM chat_participants WHERE room_id = ? AND user_id = ?',
        [roomId, userId]
    );
    return rows.length > 0;
}

function broadcastOnlineStatus(userId, isOnline) {
    io.emit('user_status', { userId, isOnline, lastSeen: new Date().toISOString() });
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

    // ── join_room ──────────────────────────────────────────────────────────────
    socket.on('join_room', async ({ roomId }, ack) => {
        if (!roomId) return ack && ack({ error: 'roomId required' });

        try {
            const allowed = await isRoomParticipant(roomId, userId);
            if (!allowed) return ack && ack({ error: 'Not a participant of this room' });

            socket.join(`room:${roomId}`);
            console.log(`[join_room] user=${userId} room=${roomId}`);
            ack && ack({ success: true, roomId });
        } catch (err) {
            console.error('[join_room] error:', err.message);
            ack && ack({ error: 'Server error' });
        }
    });

    // ── leave_room ─────────────────────────────────────────────────────────────
    socket.on('leave_room', ({ roomId }, ack) => {
        if (!roomId) return ack && ack({ error: 'roomId required' });
        socket.leave(`room:${roomId}`);
        console.log(`[leave_room] user=${userId} room=${roomId}`);
        ack && ack({ success: true });
    });

    // ── send_message ───────────────────────────────────────────────────────────
    socket.on('send_message', async (data, ack) => {
        const { roomId, message, type = 'text', fileUrl, fileName, fileSize, replyToId } = data || {};

        if (!roomId) return ack && ack({ error: 'roomId required' });
        if (!message && !fileUrl) return ack && ack({ error: 'message or fileUrl required' });

        try {
            const allowed = await isRoomParticipant(roomId, userId);
            if (!allowed) return ack && ack({ error: 'Not a participant of this room' });

            const messageId = await saveMessage(roomId, userId, message, type, {
                fileUrl,
                fileName,
                fileSize,
                replyToId,
            });

            const payload = {
                id: messageId,
                roomId,
                senderId: userId,
                senderName: socket.userName,
                message,
                type,
                fileUrl: fileUrl || null,
                fileName: fileName || null,
                fileSize: fileSize || null,
                replyToId: replyToId || null,
                createdAt: new Date().toISOString(),
            };

            // Broadcast to everyone in the room (including sender for multi-tab sync)
            io.to(`room:${roomId}`).emit('new_message', payload);

            ack && ack({ success: true, messageId });
        } catch (err) {
            console.error('[send_message] error:', err.message);
            ack && ack({ error: 'Failed to send message' });
        }
    });

    // ── typing_start ───────────────────────────────────────────────────────────
    socket.on('typing_start', ({ roomId }) => {
        if (!roomId) return;
        socket.to(`room:${roomId}`).emit('user_typing', {
            roomId,
            userId,
            userName: socket.userName,
            isTyping: true,
        });
    });

    // ── typing_stop ────────────────────────────────────────────────────────────
    socket.on('typing_stop', ({ roomId }) => {
        if (!roomId) return;
        socket.to(`room:${roomId}`).emit('user_typing', {
            roomId,
            userId,
            userName: socket.userName,
            isTyping: false,
        });
    });

    // ── read_receipt ───────────────────────────────────────────────────────────
    socket.on('read_receipt', async ({ messageId, roomId }, ack) => {
        if (!messageId) return ack && ack({ error: 'messageId required' });

        try {
            await markMessageRead(messageId, userId);

            // Notify others in the room about the read receipt
            if (roomId) {
                socket.to(`room:${roomId}`).emit('message_read', {
                    messageId,
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

    // ── disconnect ─────────────────────────────────────────────────────────────
    socket.on('disconnect', async (reason) => {
        console.log(`[disconnect] user=${userId} socket=${socket.id} reason=${reason}`);

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
