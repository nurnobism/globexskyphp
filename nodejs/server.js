/**
 * GlobexSky Node.js Streaming & Real-time Server
 *
 * Extends the existing server with live streaming events:
 * - Stream start/end/join/leave
 * - Live chat during streams
 * - Emoji reactions
 * - Product pin/unpin during stream
 * - Viewer questions
 * - Viewer count updates
 * - Stream moderation (bans)
 *
 * Uses Socket.io for real-time communication.
 * Uses PeerJS for WebRTC signaling.
 */

const http = require('http');
const { Server } = require('socket.io');
const crypto = require('crypto');

const PORT = process.env.PORT || 3001;

const server = http.createServer((req, res) => {
    // Basic health check endpoint
    if (req.url === '/health') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ status: 'ok', service: 'globexsky-streaming', uptime: process.uptime() }));
        return;
    }
    res.writeHead(404);
    res.end('Not found');
});

const io = new Server(server, {
    cors: {
        origin: '*',
        methods: ['GET', 'POST']
    }
});

// In-memory state for active streams
const activeStreams = new Map();   // streamId -> { streamerId, title, viewers: Set, pinnedProduct, startedAt }
const streamBans   = new Map();   // streamId -> Set of banned userIds
const viewerCounts = new Map();   // streamId -> count

/**
 * Generate a unique room name for a stream
 */
function streamRoom(streamId) {
    return `stream_${streamId}`;
}

io.on('connection', (socket) => {
    console.log(`Client connected: ${socket.id}`);

    // ── stream_start ─────────────────────────────────────────
    socket.on('stream_start', (data) => {
        const { streamId, streamerId, title, category } = data;
        if (!streamId || !streamerId) return;

        const room = streamRoom(streamId);
        socket.join(room);

        activeStreams.set(streamId, {
            streamerId,
            title: title || 'Live Stream',
            category: category || 'general',
            viewers: new Set(),
            pinnedProduct: null,
            startedAt: Date.now(),
            streamerSocketId: socket.id
        });
        viewerCounts.set(streamId, 0);
        streamBans.set(streamId, new Set());

        // Broadcast that a new stream started
        io.emit('stream_started', {
            streamId,
            streamerId,
            title,
            category,
            startedAt: new Date().toISOString()
        });

        console.log(`Stream ${streamId} started by user ${streamerId}`);
    });

    // ── stream_end ───────────────────────────────────────────
    socket.on('stream_end', (data) => {
        const { streamId, streamerId } = data;
        if (!streamId) return;

        const stream = activeStreams.get(streamId);
        if (stream && stream.streamerId === streamerId) {
            const room = streamRoom(streamId);

            // Notify all viewers
            io.to(room).emit('stream_ended', {
                streamId,
                duration: Date.now() - stream.startedAt,
                peakViewers: viewerCounts.get(streamId) || 0
            });

            // Clean up
            activeStreams.delete(streamId);
            viewerCounts.delete(streamId);
            streamBans.delete(streamId);

            console.log(`Stream ${streamId} ended`);
        }
    });

    // ── stream_join ──────────────────────────────────────────
    socket.on('stream_join', (data) => {
        const { streamId, userId, username } = data;
        if (!streamId) return;

        const room = streamRoom(streamId);
        const stream = activeStreams.get(streamId);

        // Check if banned
        const bans = streamBans.get(streamId);
        if (bans && bans.has(userId)) {
            socket.emit('stream_error', { message: 'You are banned from this stream.' });
            return;
        }

        socket.join(room);
        socket.streamId = streamId;
        socket.userId = userId;
        socket.username = username || 'Anonymous';

        if (stream) {
            stream.viewers.add(socket.id);
            const count = stream.viewers.size;
            viewerCounts.set(streamId, count);

            // Notify streamer
            io.to(room).emit('stream_viewer_count', { streamId, count });

            // Send current pinned product to new viewer
            if (stream.pinnedProduct) {
                socket.emit('stream_product_pin', stream.pinnedProduct);
            }
        }

        console.log(`Viewer ${userId || socket.id} joined stream ${streamId}`);
    });

    // ── stream_leave ─────────────────────────────────────────
    socket.on('stream_leave', (data) => {
        const { streamId } = data;
        if (!streamId) return;

        handleViewerLeave(socket, streamId);
    });

    // ── stream_chat ──────────────────────────────────────────
    socket.on('stream_chat', (data) => {
        const { streamId, userId, username, message, type } = data;
        if (!streamId || !message) return;

        // Check if banned
        const bans = streamBans.get(streamId);
        if (bans && bans.has(userId)) return;

        const room = streamRoom(streamId);
        const chatMessage = {
            id: crypto.randomUUID(),
            streamId,
            userId,
            username: username || 'Anonymous',
            message: message.substring(0, 500),  // Limit message length
            type: type || 'message',
            timestamp: new Date().toISOString()
        };

        io.to(room).emit('stream_chat_message', chatMessage);
    });

    // ── stream_reaction ──────────────────────────────────────
    socket.on('stream_reaction', (data) => {
        const { streamId, userId, emoji } = data;
        if (!streamId || !emoji) return;

        const room = streamRoom(streamId);
        io.to(room).emit('stream_reaction_broadcast', {
            streamId,
            userId,
            emoji,
            id: crypto.randomUUID()
        });
    });

    // ── stream_product_pin ───────────────────────────────────
    socket.on('stream_product_pin', (data) => {
        const { streamId, streamerId, product } = data;
        if (!streamId || !product) return;

        const stream = activeStreams.get(streamId);
        if (stream && stream.streamerId === streamerId) {
            stream.pinnedProduct = product;

            const room = streamRoom(streamId);
            io.to(room).emit('stream_product_pin', {
                streamId,
                product
            });

            console.log(`Product pinned in stream ${streamId}: ${product.name}`);
        }
    });

    // ── stream_product_unpin ─────────────────────────────────
    socket.on('stream_product_unpin', (data) => {
        const { streamId, streamerId } = data;
        if (!streamId) return;

        const stream = activeStreams.get(streamId);
        if (stream && stream.streamerId === streamerId) {
            stream.pinnedProduct = null;

            const room = streamRoom(streamId);
            io.to(room).emit('stream_product_unpin', { streamId });
        }
    });

    // ── stream_question ──────────────────────────────────────
    socket.on('stream_question', (data) => {
        const { streamId, userId, username, question } = data;
        if (!streamId || !question) return;

        const stream = activeStreams.get(streamId);
        if (!stream) return;

        const room = streamRoom(streamId);
        const questionData = {
            id: crypto.randomUUID(),
            streamId,
            userId,
            username: username || 'Anonymous',
            question: question.substring(0, 500),
            timestamp: new Date().toISOString()
        };

        // Send to streamer
        io.to(stream.streamerSocketId).emit('stream_question_received', questionData);
        // Also broadcast to all viewers
        io.to(room).emit('stream_question_broadcast', questionData);
    });

    // ── stream_ban ───────────────────────────────────────────
    socket.on('stream_ban', (data) => {
        const { streamId, streamerId, targetUserId } = data;
        if (!streamId || !targetUserId) return;

        const stream = activeStreams.get(streamId);
        if (stream && stream.streamerId === streamerId) {
            let bans = streamBans.get(streamId);
            if (!bans) {
                bans = new Set();
                streamBans.set(streamId, bans);
            }
            bans.add(targetUserId);

            // Notify the banned user
            const room = streamRoom(streamId);
            io.to(room).emit('stream_user_banned', { streamId, userId: targetUserId });

            console.log(`User ${targetUserId} banned from stream ${streamId}`);
        }
    });

    // ── WebRTC signaling ─────────────────────────────────────
    socket.on('webrtc_offer', (data) => {
        const { streamId, offer, targetId } = data;
        if (targetId) {
            io.to(targetId).emit('webrtc_offer', { offer, senderId: socket.id, streamId });
        } else {
            const room = streamRoom(streamId);
            socket.to(room).emit('webrtc_offer', { offer, senderId: socket.id, streamId });
        }
    });

    socket.on('webrtc_answer', (data) => {
        const { targetId, answer, streamId } = data;
        if (targetId) {
            io.to(targetId).emit('webrtc_answer', { answer, senderId: socket.id, streamId });
        }
    });

    socket.on('webrtc_ice_candidate', (data) => {
        const { targetId, candidate, streamId } = data;
        if (targetId) {
            io.to(targetId).emit('webrtc_ice_candidate', { candidate, senderId: socket.id, streamId });
        } else {
            const room = streamRoom(streamId);
            socket.to(room).emit('webrtc_ice_candidate', { candidate, senderId: socket.id, streamId });
        }
    });

    // ── Disconnect ───────────────────────────────────────────
    socket.on('disconnect', () => {
        if (socket.streamId) {
            handleViewerLeave(socket, socket.streamId);
        }
        console.log(`Client disconnected: ${socket.id}`);
    });
});

/**
 * Handle a viewer leaving a stream
 */
function handleViewerLeave(socket, streamId) {
    const room = streamRoom(streamId);
    const stream = activeStreams.get(streamId);

    if (stream) {
        stream.viewers.delete(socket.id);
        const count = stream.viewers.size;
        viewerCounts.set(streamId, count);

        io.to(room).emit('stream_viewer_count', { streamId, count });
    }

    socket.leave(room);
}

// ── Periodic viewer count broadcast ──────────────────────────
setInterval(() => {
    for (const [streamId, stream] of activeStreams.entries()) {
        const room = streamRoom(streamId);
        const count = stream.viewers.size;
        io.to(room).emit('stream_viewer_count', { streamId, count });
    }
}, 10000);  // Every 10 seconds

server.listen(PORT, () => {
    console.log(`GlobexSky Streaming Server running on port ${PORT}`);
});
