/**
 * chat.js — Real-Time Chat for GlobexSky B2B Platform
 * Depends on window.CHAT_CONFIG = { userId, appUrl, csrf, activeRoom? }
 */
(function () {
    'use strict';

    const cfg = window.CHAT_CONFIG || {};
    const APP_URL = cfg.appUrl || '';
    const SELF_ID = cfg.userId || 0;
    const CSRF    = cfg.csrf   || '';

    let currentRoomId  = cfg.activeRoom || null;
    let pollTimer      = null;
    let typingTimer    = null;
    let lastMessageId  = 0;
    let isTyping       = false;
    let currentFilter  = 'all';
    let allRooms       = [];

    // ── DOM refs ──────────────────────────────────────────────────────────────
    const roomList        = document.getElementById('roomList');
    const roomSearch      = document.getElementById('roomSearch');
    const chatMessages    = document.getElementById('chatMessages');
    const messageInput    = document.getElementById('messageInput');
    const sendBtn         = document.getElementById('sendBtn');
    const emptyState      = document.getElementById('emptyState');
    const activeChat      = document.getElementById('activeChat');
    const chatHeaderName  = document.getElementById('chatHeaderName');
    const chatHeaderAvatar= document.getElementById('chatHeaderAvatar');
    const chatHeaderStatus= document.getElementById('chatHeaderStatus');
    const typingIndicator = document.getElementById('typingIndicator');
    const typingName      = document.getElementById('typingName');
    const messagesLoading = document.getElementById('messagesLoading');
    const fileAttach      = document.getElementById('fileAttach');
    const attachPreview   = document.getElementById('attachPreview');

    // ── Init ──────────────────────────────────────────────────────────────────
    function init() {
        loadRooms();
        bindEvents();

        if (currentRoomId) {
            openRoom(currentRoomId);
        }
    }

    // ── Load room list ────────────────────────────────────────────────────────
    function loadRooms() {
        fetch(APP_URL + '/api/chat.php?action=get_rooms', {
            headers: { 'X-CSRF-Token': CSRF }
        })
        .then(r => r.json())
        .then(data => {
            allRooms = data.rooms || [];
            renderRooms(allRooms);
        })
        .catch(() => {
            if (roomList) {
                roomList.innerHTML =
                    '<div class="text-center py-4 text-danger small">' +
                    '<i class="bi bi-exclamation-circle me-1"></i>Failed to load rooms.</div>';
            }
        });
    }

    function renderRooms(rooms) {
        if (!roomList) return;
        const loading = document.getElementById('roomListLoading');
        if (loading) loading.remove();

        if (rooms.length === 0) {
            roomList.innerHTML =
                '<div class="text-center py-5 text-muted">' +
                '<i class="bi bi-chat-square display-4 opacity-25"></i>' +
                '<p class="small mt-2">No conversations yet.</p>' +
                '<a href="' + APP_URL + '/pages/messages/compose.php" class="btn btn-sm btn-primary mt-1">' +
                '<i class="bi bi-pencil-square me-1"></i>Start a chat</a></div>';
            return;
        }

        roomList.innerHTML = '';
        rooms.forEach(room => {
            const item = buildRoomItem(room);
            roomList.appendChild(item);
        });
    }

    function buildRoomItem(room) {
        const div = document.createElement('div');
        div.className = 'room-item p-3 d-flex gap-2 align-items-start' +
                        (room.id == currentRoomId ? ' active' : '');
        div.dataset.roomId = room.id;

        const name     = escHtml(room.name || 'Chat');
        const preview  = escHtml(truncate(room.last_message || 'No messages yet.', 45));
        const time     = room.last_message_at ? timeAgo(room.last_message_at) : '';
        const unread   = parseInt(room.unread_count || 0, 10);
        const initial  = name.charAt(0).toUpperCase();
        const isOrder  = room.type === 'order';

        div.innerHTML =
            '<div class="avatar-circle">' + initial + '</div>' +
            '<div class="flex-grow-1 overflow-hidden">' +
              '<div class="d-flex justify-content-between align-items-start">' +
                '<span class="fw-semibold text-truncate small">' + name + '</span>' +
                '<span class="text-muted" style="font-size:.7rem;white-space:nowrap;">' + time + '</span>' +
              '</div>' +
              '<div class="d-flex justify-content-between align-items-center">' +
                '<span class="text-muted text-truncate" style="font-size:.78rem;">' + preview + '</span>' +
                (unread > 0
                    ? '<span class="badge rounded-pill bg-primary ms-1">' + unread + '</span>'
                    : '') +
              '</div>' +
              (isOrder ? '<span class="badge bg-warning text-dark mt-1" style="font-size:.65rem;">Order</span>' : '') +
            '</div>';

        div.addEventListener('click', () => openRoom(room.id));
        return div;
    }

    // ── Open a room ───────────────────────────────────────────────────────────
    function openRoom(roomId) {
        currentRoomId = roomId;
        lastMessageId = 0;

        // Highlight active room
        document.querySelectorAll('.room-item').forEach(el => {
            el.classList.toggle('active', el.dataset.roomId == roomId);
        });

        if (emptyState)   emptyState.classList.add('d-none');
        if (activeChat)   activeChat.classList.remove('d-none');
        if (chatMessages) chatMessages.innerHTML = '';
        if (messagesLoading) {
            messagesLoading.style.display = 'block';
        }

        // Load room details
        fetch(APP_URL + '/api/chat.php?action=get_room&room_id=' + roomId, {
            headers: { 'X-CSRF-Token': CSRF }
        })
        .then(r => r.json())
        .then(data => {
            if (data.room) updateChatHeader(data.room);
        })
        .catch(() => {});

        // Load messages
        loadMessages(roomId, true);

        // Start polling
        stopPolling();
        pollTimer = setInterval(() => pollMessages(roomId), 3000);

        // Update URL without reload
        const newUrl = APP_URL + '/pages/messages/conversation.php?room_id=' + roomId;
        if (window.history && window.history.pushState) {
            window.history.pushState({ roomId }, '', newUrl);
        }
    }

    function updateChatHeader(room) {
        if (chatHeaderName)   chatHeaderName.textContent  = room.name || 'Chat';
        if (chatHeaderAvatar) chatHeaderAvatar.textContent = (room.name || '?').charAt(0).toUpperCase();

        const viewProfileLink = document.getElementById('viewProfileLink');
        if (viewProfileLink && room.other_user_id) {
            viewProfileLink.href =
                APP_URL + '/pages/account/profile.php?id=' + room.other_user_id;
        }
    }

    // ── Load / poll messages ──────────────────────────────────────────────────
    function loadMessages(roomId, initial) {
        const url = APP_URL + '/api/chat.php?action=get_messages&room_id=' + roomId +
                    (lastMessageId ? '&after_id=' + lastMessageId : '');

        fetch(url, { headers: { 'X-CSRF-Token': CSRF } })
        .then(r => r.json())
        .then(data => {
            if (messagesLoading) messagesLoading.style.display = 'none';

            const messages = data.messages || [];
            if (messages.length > 0) {
                messages.forEach(m => appendMessage(m, initial && chatMessages.children.length === 0));
                lastMessageId = messages[messages.length - 1].id;
                if (initial) scrollToBottom();
            }

            if (data.typing_user) {
                showTyping(data.typing_user);
            } else {
                hideTyping();
            }
        })
        .catch(() => {
            if (messagesLoading) messagesLoading.style.display = 'none';
        });
    }

    function pollMessages(roomId) {
        if (roomId !== currentRoomId) return;
        loadMessages(roomId, false);
        // Also refresh room list occasionally to update previews / unread counts
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    // ── Render a message bubble ───────────────────────────────────────────────
    function appendMessage(msg, skipScroll) {
        const isSent = parseInt(msg.sender_id, 10) === SELF_ID;
        const wrap   = document.createElement('div');
        wrap.className = 'd-flex mb-2 ' + (isSent ? 'justify-content-end' : 'justify-content-start');
        wrap.dataset.msgId = msg.id;

        const time  = msg.created_at ? timeAgo(msg.created_at) : '';
        const text  = escHtml(msg.body || '');
        const label = isSent ? '' :
            '<div class="small text-muted mb-1 ms-1">' + escHtml(msg.sender_name || '') + '</div>';

        wrap.innerHTML =
            '<div>' +
              label +
              '<div class="msg-bubble ' + (isSent ? 'sent' : 'received') + ' px-3 py-2">' +
                text +
              '</div>' +
              '<div class="text-muted mt-1 ' + (isSent ? 'text-end' : '') + '" style="font-size:.68rem;">' +
                time +
                (isSent
                    ? ' <i class="bi bi-check2' + (msg.is_read ? '-all text-info' : '') + '"></i>'
                    : '') +
              '</div>' +
            '</div>';

        if (chatMessages) {
            chatMessages.appendChild(wrap);
            if (!skipScroll) scrollToBottom();
        }
    }

    function scrollToBottom() {
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    // ── Send message ──────────────────────────────────────────────────────────
    function sendMessage() {
        if (!currentRoomId) return;
        const body = (messageInput ? messageInput.value.trim() : '');
        if (!body) return;

        if (messageInput) messageInput.value = '';
        if (sendBtn) sendBtn.disabled = true;
        stopTypingSignal();

        fetch(APP_URL + '/api/chat.php?action=send_message', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF
            },
            body: JSON.stringify({ room_id: currentRoomId, body, _csrf_token: CSRF })
        })
        .then(r => r.json())
        .then(data => {
            if (sendBtn) sendBtn.disabled = false;
            if (data.message) {
                appendMessage(data.message, false);
                updateRoomPreview(currentRoomId, body);
            }
        })
        .catch(() => {
            if (sendBtn) sendBtn.disabled = false;
        });
    }

    function updateRoomPreview(roomId, body) {
        const item = roomList ? roomList.querySelector('[data-room-id="' + roomId + '"]') : null;
        if (!item) return;
        const preview = item.querySelector('.text-muted.text-truncate');
        if (preview) preview.textContent = truncate(body, 45);
        const timeEl = item.querySelector('.text-muted[style]');
        if (timeEl) timeEl.textContent = 'Just now';
    }

    // ── Typing indicator ──────────────────────────────────────────────────────
    function onTyping() {
        if (!isTyping) {
            isTyping = true;
            sendTypingSignal(true);
        }
        clearTimeout(typingTimer);
        typingTimer = setTimeout(stopTypingSignal, 2500);
    }

    function stopTypingSignal() {
        if (isTyping) {
            isTyping = false;
            sendTypingSignal(false);
        }
        clearTimeout(typingTimer);
    }

    function sendTypingSignal(typing) {
        if (!currentRoomId) return;
        fetch(APP_URL + '/api/chat.php?action=typing', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body:    JSON.stringify({ room_id: currentRoomId, typing, _csrf_token: CSRF })
        }).catch(() => {});
    }

    function showTyping(userName) {
        if (typingIndicator) typingIndicator.style.display = 'flex';
        if (typingName) typingName.textContent = escHtml(userName) + ' is typing…';
    }

    function hideTyping() {
        if (typingIndicator) typingIndicator.style.display = 'none';
    }

    // ── Room search + filter ──────────────────────────────────────────────────
    function applyFilter() {
        let filtered = allRooms.slice();

        if (currentFilter === 'unread') {
            filtered = filtered.filter(r => parseInt(r.unread_count || 0, 10) > 0);
        } else if (currentFilter === 'orders') {
            filtered = filtered.filter(r => r.type === 'order');
        }

        const q = roomSearch ? roomSearch.value.trim().toLowerCase() : '';
        if (q) {
            filtered = filtered.filter(r =>
                (r.name || '').toLowerCase().includes(q) ||
                (r.last_message || '').toLowerCase().includes(q)
            );
        }

        renderRooms(filtered);

        // Re-highlight active room
        if (currentRoomId) {
            const activeItem = roomList
                ? roomList.querySelector('[data-room-id="' + currentRoomId + '"]')
                : null;
            if (activeItem) activeItem.classList.add('active');
        }
    }

    // ── Bind events ───────────────────────────────────────────────────────────
    function bindEvents() {
        // Send on button click
        if (sendBtn) {
            sendBtn.addEventListener('click', sendMessage);
        }

        // Send on Enter (Shift+Enter = newline)
        if (messageInput) {
            messageInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            // Auto-resize textarea
            messageInput.addEventListener('input', function () {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                onTyping();
            });
        }

        // Room search
        if (roomSearch) {
            roomSearch.addEventListener('input', applyFilter);
        }

        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('btn-primary', 'active');
                    b.classList.add('btn-outline-secondary');
                });
                this.classList.remove('btn-outline-secondary');
                this.classList.add('btn-primary', 'active');
                currentFilter = this.dataset.filter || 'all';
                applyFilter();
            });
        });

        // File attach preview
        if (fileAttach) {
            fileAttach.addEventListener('change', function () {
                if (!attachPreview) return;
                attachPreview.innerHTML = '';
                Array.from(this.files).forEach(f => {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-secondary d-flex align-items-center gap-1';
                    badge.innerHTML =
                        '<i class="bi bi-paperclip"></i>' + escHtml(truncate(f.name, 20)) +
                        ' <button type="button" class="btn-close btn-close-white ms-1" style="font-size:.55rem;"></button>';
                    attachPreview.appendChild(badge);
                });
            });
        }

        // Emoji button (placeholder — integrates with picker library if available)
        const emojiBtn = document.getElementById('emojiBtn');
        if (emojiBtn) {
            emojiBtn.addEventListener('click', function () {
                const emojis = ['😊','👍','🎉','✅','🚀','💼','📦','🤝'];
                const rand   = emojis[Math.floor(Math.random() * emojis.length)];
                if (messageInput) {
                    messageInput.value += rand;
                    messageInput.focus();
                }
            });
        }

        // Delete chat link
        const deleteChatLink = document.getElementById('deleteChatLink');
        if (deleteChatLink) {
            deleteChatLink.addEventListener('click', function (e) {
                e.preventDefault();
                if (!currentRoomId) return;
                if (!confirm('Delete this conversation? This cannot be undone.')) return;

                fetch(APP_URL + '/api/chat.php?action=delete_room', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body:    JSON.stringify({ room_id: currentRoomId, _csrf_token: CSRF })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = APP_URL + '/pages/messages/index.php';
                    }
                })
                .catch(() => {});
            });
        }

        // Browser back/forward
        window.addEventListener('popstate', function (e) {
            if (e.state && e.state.roomId) {
                openRoom(e.state.roomId);
            }
        });
    }

    // ── Utilities ─────────────────────────────────────────────────────────────
    function escHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    function truncate(str, len) {
        return str.length > len ? str.substring(0, len) + '…' : str;
    }

    function timeAgo(dateStr) {
        const now  = new Date();
        const then = new Date(dateStr);
        if (isNaN(then)) return dateStr;
        const diff = Math.floor((now - then) / 1000);

        if (diff < 60)   return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';

        const d = then;
        return (d.getMonth() + 1) + '/' + d.getDate() + '/' + d.getFullYear();
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
