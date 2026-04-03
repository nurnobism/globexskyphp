/**
 * GlobexSky Socket.io Client Wrapper
 *
 * Manages Socket.io connection with auto-reconnect
 * and AJAX polling fallback.
 */
const GlobexSocket = (function() {
    'use strict';

    let socket = null;
    let connected = false;
    let retries = 0;
    const MAX_RETRIES = 3;
    let fallbackMode = false;
    let pollingIntervals = [];
    const eventHandlers = {};

    function init(config) {
        config = config || {};
        const serverUrl = config.serverUrl || '';
        const token     = config.token || '';

        if (!serverUrl || typeof io === 'undefined') {
            console.log('[GlobexSocket] Socket.io not available, using AJAX fallback');
            enableFallback(config);
            return;
        }

        try {
            socket = io(serverUrl, {
                auth: { token: token },
                reconnection: true,
                reconnectionAttempts: MAX_RETRIES,
                reconnectionDelay: 2000,
                timeout: 10000,
                transports: ['websocket', 'polling']
            });

            socket.on('connect', function() {
                connected = true;
                retries = 0;
                console.log('[GlobexSocket] Connected:', socket.id);
                trigger('connected', { socketId: socket.id });
            });

            socket.on('disconnect', function(reason) {
                connected = false;
                console.log('[GlobexSocket] Disconnected:', reason);
                trigger('disconnected', { reason: reason });
            });

            socket.on('connect_error', function(err) {
                retries++;
                console.warn('[GlobexSocket] Connection error (' + retries + '/' + MAX_RETRIES + '):', err.message);
                if (retries >= MAX_RETRIES) {
                    console.log('[GlobexSocket] Max retries reached, switching to AJAX fallback');
                    socket.disconnect();
                    enableFallback(config);
                }
            });

            // Chat events
            socket.on('new_message', function(data) { trigger('new_message', data); });
            socket.on('typing_start', function(data) { trigger('typing_start', data); });
            socket.on('typing_stop', function(data) { trigger('typing_stop', data); });
            socket.on('read_receipt', function(data) { trigger('read_receipt', data); });
            socket.on('user_online', function(data) { trigger('user_online', data); });
            socket.on('user_offline', function(data) { trigger('user_offline', data); });

            // Notification events
            socket.on('notification', function(data) { trigger('notification', data); });
            socket.on('unread_count', function(data) { trigger('unread_count', data); });

        } catch (e) {
            console.error('[GlobexSocket] Init error:', e);
            enableFallback(config);
        }
    }

    function enableFallback(config) {
        fallbackMode = true;
        trigger('fallback_mode', {});
    }

    function on(event, handler) {
        if (!eventHandlers[event]) eventHandlers[event] = [];
        eventHandlers[event].push(handler);
    }

    function off(event, handler) {
        if (!eventHandlers[event]) return;
        if (handler) {
            eventHandlers[event] = eventHandlers[event].filter(function(h) { return h !== handler; });
        } else {
            delete eventHandlers[event];
        }
    }

    function trigger(event, data) {
        (eventHandlers[event] || []).forEach(function(handler) {
            try { handler(data); } catch (e) { console.error('[GlobexSocket] Handler error:', e); }
        });
    }

    function emit(event, data) {
        if (socket && connected) {
            socket.emit(event, data);
            return true;
        }
        return false;
    }

    function joinRoom(roomId) {
        emit('join_room', { room_id: roomId });
    }

    function leaveRoom(roomId) {
        emit('leave_room', { room_id: roomId });
    }

    function sendMessage(roomId, message, type, fileUrl, fileName, fileSize) {
        return emit('send_message', {
            room_id: roomId,
            message: message,
            type: type || 'text',
            file_url: fileUrl || null,
            file_name: fileName || null,
            file_size: fileSize || null
        });
    }

    function startTyping(roomId) { emit('typing_start', { room_id: roomId }); }
    function stopTyping(roomId) { emit('typing_stop', { room_id: roomId }); }
    function markRead(roomId, messageId) { emit('read_receipt', { room_id: roomId, message_id: messageId }); }

    function isConnected() { return connected; }
    function isFallback() { return fallbackMode; }

    function destroy() {
        pollingIntervals.forEach(function(id) { clearInterval(id); });
        pollingIntervals = [];
        if (socket) { socket.disconnect(); socket = null; }
        connected = false;
    }

    return {
        init: init,
        on: on,
        off: off,
        emit: emit,
        joinRoom: joinRoom,
        leaveRoom: leaveRoom,
        sendMessage: sendMessage,
        startTyping: startTyping,
        stopTyping: stopTyping,
        markRead: markRead,
        isConnected: isConnected,
        isFallback: isFallback,
        destroy: destroy
    };
})();
