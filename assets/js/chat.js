/**
 * chat.js - Real-Time Chat for GlobexSky B2B Platform
 * Depends on window.CHAT_CONFIG = { userId, appUrl, csrf, activeRoom?, page? }
 *
 * Supports layouts:
 *   - inbox        (pages/messages/inbox.php)        - sidebar + panel
 *   - conversation (pages/messages/conversation.php) - panel only, room pre-opened
 *   - legacy       (pages/messages/index.php)        - backward compat
 */
(function () {
    'use strict';

    var cfg     = window.CHAT_CONFIG || {};
    var APP_URL = cfg.appUrl || '';
    var SELF_ID = cfg.userId || 0;
    var CSRF    = cfg.csrf   || '';

    var currentRoomId = cfg.activeRoom || null;
    var pollTimer     = null;
    var typingTimer   = null;
    var lastMessageId = 0;
    var isTyping      = false;
    var currentTab    = 'all';
    var allRooms      = [];
    var pendingFiles  = [];

    // Emoji set (surrogate pairs / direct 4-digit escapes for ES5 compatibility)
    var EMOJI_SET = [
        '\uD83D\uDE0A','\uD83D\uDE02','\uD83D\uDE0D','\uD83E\uDD70','\uD83D\uDE0E',
        '\uD83D\uDE22','\uD83D\uDE21','\uD83D\uDC4D','\uD83D\uDC4E','\uD83D\uDC4F',
        '\uD83D\uDE4F','\uD83D\uDCAA','\uD83C\uDF89','\u2705','\u274C','\u26A1',
        '\uD83D\uDD25','\uD83D\uDCAF','\uD83D\uDE80','\uD83D\uDCBC','\uD83D\uDCE6',
        '\uD83E\uDD1D','\uD83D\uDCB0','\uD83D\uDCC8','\uD83D\uDCE7','\uD83D\uDCDE',
        '\uD83C\uDF0D','\uD83D\uDD50','\uD83D\uDCAC','\uD83D\uDCDD','\u2B50','\uD83C\uDFC6'
    ];

    // DOM refs
    var convList      = document.getElementById('convList')   || document.getElementById('roomList');
    var convSearch    = document.getElementById('convSearch') || document.getElementById('roomSearch');
    var chatMessages  = document.getElementById('chatMessages');
    var messageInput  = document.getElementById('messageInput');
    var sendBtn       = document.getElementById('sendBtn');
    var emptyState    = document.getElementById('emptyState');
    var activeChat    = document.getElementById('activeChat');
    var chatHeaderName   = document.getElementById('chatHeaderName');
    var chatHeaderAvatar = document.getElementById('chatHeaderAvatar');
    var chatHeaderStatus = document.getElementById('chatHeaderStatus');
    var typingIndicator  = document.getElementById('typingIndicator');
    var typingName       = document.getElementById('typingName');
    var messagesLoading  = document.getElementById('messagesLoading');
    var fileAttach       = document.getElementById('fileAttach');
    var attachPreview    = document.getElementById('attachPreview');

    // Init
    function init() {
        buildEmojiPicker();
        loadRooms();
        bindEvents();
        if (currentRoomId) {
            openRoom(currentRoomId);
        }
    }

    // Load conversation list
    function loadRooms() {
        fetch(APP_URL + '/api/chat.php?action=get_rooms', {
            headers: { 'X-CSRF-Token': CSRF }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            allRooms = data.data || data.rooms || [];
            renderConvList(allRooms);
        })
        .catch(function() {
            if (convList) {
                convList.innerHTML =
                    '<div class="text-center py-4 text-danger small">' +
                    '<i class="bi bi-exclamation-circle me-1"></i>Failed to load conversations.</div>';
            }
        });
    }

    function renderConvList(rooms) {
        if (!convList) return;
        ['convLoading', 'roomListLoading'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.remove();
        });
        if (rooms.length === 0) {
            convList.innerHTML =
                '<div class="text-center py-5 text-muted">' +
                '<i class="bi bi-chat-square display-4 opacity-25"></i>' +
                '<p class="small mt-2">No conversations yet.</p>' +
                '<a href="' + APP_URL + '/pages/messages/new.php" class="btn btn-sm btn-primary mt-1">' +
                '<i class="bi bi-pencil-square me-1"></i>Start a chat</a></div>';
            return;
        }
        convList.innerHTML = '';
        rooms.forEach(function(room) {
            convList.appendChild(buildConvItem(room));
        });
    }

    function buildConvItem(room) {
        var div = document.createElement('div');
        div.className = 'conv-item' + (room.id == currentRoomId ? ' active' : '');
        div.dataset.roomId = room.id;

        var name    = room.name || 'Chat';
        var preview = truncate(room.last_message_preview || room.last_message || 'No messages yet.', 48);
        var time    = room.last_message_at ? timeAgo(room.last_message_at) : '';
        var unread  = parseInt(room.unread_count || 0, 10);
        var initial = name.charAt(0).toUpperCase();
        var typeLabel = (room.type && room.type !== 'direct')
            ? '<span class="badge bg-warning text-dark ms-1" style="font-size:.6rem;">' + escHtml(room.type) + '</span>'
            : '';

        div.innerHTML =
            '<div class="chat-avatar" style="width:44px;height:44px;">' +
              escHtml(initial) +
            '</div>' +
            '<div class="flex-grow-1 overflow-hidden">' +
              '<div class="d-flex justify-content-between align-items-start">' +
                '<span class="fw-semibold text-truncate small">' + escHtml(name) + typeLabel + '</span>' +
                '<span class="text-muted ms-1" style="font-size:.68rem;white-space:nowrap;">' + escHtml(time) + '</span>' +
              '</div>' +
              '<div class="d-flex justify-content-between align-items-center">' +
                '<span class="text-muted text-truncate" style="font-size:.78rem;">' + escHtml(preview) + '</span>' +
                (unread > 0 ? '<span class="unread-badge ms-1">' + unread + '</span>' : '') +
              '</div>' +
            '</div>';

        div.addEventListener('click', function() { openRoom(room.id); });
        return div;
    }

    // Open a room
    function openRoom(roomId) {
        currentRoomId = roomId;
        lastMessageId = 0;

        document.querySelectorAll('.conv-item, .room-item').forEach(function(el) {
            el.classList.toggle('active', el.dataset.roomId == roomId);
        });

        if (emptyState) emptyState.classList.add('d-none');
        if (activeChat) activeChat.classList.remove('d-none');

        if (chatMessages) {
            chatMessages.querySelectorAll('.msg-row, [data-msg-id], .date-separator').forEach(function(m) {
                m.remove();
            });
        }
        if (messagesLoading) messagesLoading.style.display = 'block';

        var sidebar = document.getElementById('chatSidebar');
        if (sidebar && window.innerWidth < 768) {
            sidebar.classList.add('chat-sidebar-mobile-hidden');
        }

        fetch(APP_URL + '/api/chat.php?action=get_room&room_id=' + roomId, {
            headers: { 'X-CSRF-Token': CSRF }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) { updateChatHeader(data.data || data.room || {}); })
        .catch(function() {});

        loadMessages(roomId, true);
        stopPolling();
        pollTimer = setInterval(function() { pollMessages(roomId); }, 3000);

        var newUrl = APP_URL + '/pages/messages/conversation.php?room_id=' + roomId;
        if (window.history && window.history.pushState) {
            window.history.pushState({ roomId: roomId }, '', newUrl);
        }
    }

    function updateChatHeader(room) {
        var name = room.name || 'Chat';
        if (chatHeaderName)   chatHeaderName.textContent  = name;
        if (chatHeaderAvatar) chatHeaderAvatar.textContent = name.charAt(0).toUpperCase();

        var statusDot  = chatHeaderStatus ? chatHeaderStatus.querySelector('.online-dot') : null;
        var statusText = document.getElementById('chatHeaderStatusText');
        if (statusDot) {
            statusDot.classList.toggle('offline', !room.is_online);
            statusDot.classList.toggle('pulse',    !!room.is_online);
        }
        if (statusText) statusText.textContent = room.is_online ? 'Online' : 'Offline';

        var vpl = document.getElementById('viewProfileLink');
        if (vpl && room.other_user_id) {
            vpl.href = APP_URL + '/pages/account/profile.php?id=' + room.other_user_id;
        }
    }

    // Load / poll messages
    function loadMessages(roomId, initial) {
        var url = (lastMessageId > 0)
            ? APP_URL + '/api/chat.php?action=get_new&room_id=' + roomId + '&last_id=' + lastMessageId
            : APP_URL + '/api/chat.php?action=get_messages&room_id=' + roomId;

        fetch(url, { headers: { 'X-CSRF-Token': CSRF } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (messagesLoading) messagesLoading.style.display = 'none';
            var messages = data.data || data.messages || [];
            if (messages.length > 0) {
                var prevDate = null;
                messages.forEach(function(m) {
                    var msgDate = m.created_at ? m.created_at.substring(0, 10) : null;
                    if (msgDate && msgDate !== prevDate) {
                        appendDateSeparator(msgDate);
                        prevDate = msgDate;
                    }
                    appendMessage(m, false);
                });
                lastMessageId = parseInt(messages[messages.length - 1].id, 10);
                if (initial) scrollToBottom();
            }
            if (data.typing_user) { showTyping(data.typing_user); } else { hideTyping(); }
        })
        .catch(function() { if (messagesLoading) messagesLoading.style.display = 'none'; });
    }

    function pollMessages(roomId) {
        if (roomId !== currentRoomId) return;
        loadMessages(roomId, false);
    }

    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    // Date separator
    function appendDateSeparator(dateStr) {
        if (!chatMessages) return;
        var div = document.createElement('div');
        div.className = 'date-separator';
        div.innerHTML = '<span>' + escHtml(formatDateLabel(dateStr)) + '</span>';
        chatMessages.appendChild(div);
    }

    function formatDateLabel(dateStr) {
        var today = new Date();
        var d     = new Date(dateStr);
        if (isNaN(d)) return dateStr;
        var todayStr     = today.toISOString().substring(0, 10);
        var yesterdayStr = new Date(today - 86400000).toISOString().substring(0, 10);
        if (dateStr === todayStr)     return 'Today';
        if (dateStr === yesterdayStr) return 'Yesterday';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    // Render message bubble
    function appendMessage(msg, skipScroll) {
        if (!chatMessages) return;
        var body     = msg.message || msg.body || '';
        var senderId = parseInt(msg.sender_id || msg.user_id || 0, 10);
        var isSent   = senderId === SELF_ID;
        var time     = msg.created_at ? timeAgo(msg.created_at) : '';
        var isRead   = !!msg.is_read;
        var msgType  = msg.type || 'text';

        var wrap = document.createElement('div');
        wrap.className = 'msg-row ' + (isSent ? 'sent' : 'received');
        wrap.dataset.msgId = msg.id;

        var contentHtml = '';
        if (msgType === 'image' && msg.file_url) {
            contentHtml =
                '<a href="' + escHtml(msg.file_url) + '" target="_blank" rel="noopener">' +
                '<img src="' + escHtml(msg.file_url) + '" alt="image"' +
                ' style="max-width:200px;max-height:200px;border-radius:.375rem;display:block;"></a>';
        } else if (msgType === 'file' && msg.file_url) {
            contentHtml =
                '<a href="' + escHtml(msg.file_url) + '" class="msg-file" target="_blank" rel="noopener">' +
                '<i class="bi bi-file-earmark me-1"></i>' + escHtml(msg.file_name || 'Attachment') + '</a>';
        } else {
            contentHtml = escHtml(body);
        }

        var senderLabel = (!isSent && msg.sender_name)
            ? '<div class="msg-sender-name">' + escHtml(msg.sender_name) + '</div>' : '';

        wrap.innerHTML =
            '<div>' +
              senderLabel +
              '<div class="msg-bubble ' + (isSent ? 'sent' : 'received') + '">' + contentHtml + '</div>' +
              '<div class="msg-meta ' + (isSent ? 'sent' : 'received') + '">' +
                escHtml(time) +
                (isSent ? ' <i class="bi bi-check2' + (isRead ? '-all msg-check read' : ' msg-check') + '"></i>' : '') +
              '</div>' +
            '</div>';

        chatMessages.appendChild(wrap);
        if (!skipScroll) scrollToBottom();
    }

    function scrollToBottom() {
        if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Re-render attach preview badges with correct indices
    function renderAttachPreviews() {
        if (!attachPreview) return;
        attachPreview.innerHTML = '';
        pendingFiles.forEach(function(f, i) {
            var badge = document.createElement('span');
            badge.className = 'badge bg-secondary d-inline-flex align-items-center gap-1 me-1 mb-1';
            badge.innerHTML =
                '<i class="bi bi-paperclip"></i>' + escHtml(truncate(f.name, 22)) +
                '<button type="button" class="btn-close btn-close-white ms-1"' +
                ' style="font-size:.5rem;" data-idx="' + i + '"></button>';
            badge.querySelector('button').addEventListener('click', function() {
                pendingFiles.splice(parseInt(this.dataset.idx, 10), 1);
                renderAttachPreviews(); // re-render with updated indices
            });
            attachPreview.appendChild(badge);
        });
    }

    // Send message
    function sendMessage() {
        if (!currentRoomId) return;
        var body = messageInput ? messageInput.value.trim() : '';
        if (!body && pendingFiles.length === 0) return;

        if (messageInput) { messageInput.value = ''; messageInput.style.height = 'auto'; }
        if (sendBtn) sendBtn.disabled = true;
        stopTypingSignal();

        if (pendingFiles.length > 0) {
            uploadAndSendFiles(body);
        } else {
            sendTextMessage(body);
        }
    }

    function sendTextMessage(body) {
        var fd = new FormData();
        fd.append('_csrf_token', CSRF);
        fd.append('room_id',    currentRoomId);
        fd.append('message',    body);
        fd.append('type',       'text');

        fetch(APP_URL + '/api/chat.php?action=send', {
            method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (sendBtn) sendBtn.disabled = false;
            var msg = data.data || data.message || null;
            if (msg) {
                appendMessage(msg, false);
                updateConvPreview(currentRoomId, body);
            }
        })
        .catch(function() { if (sendBtn) sendBtn.disabled = false; });
    }

    function uploadAndSendFiles(caption) {
        var file = pendingFiles.shift();
        if (!file) {
            if (caption) sendTextMessage(caption);
            if (sendBtn) sendBtn.disabled = false;
            if (attachPreview) attachPreview.innerHTML = '';
            return;
        }
        var fd = new FormData();
        fd.append('file', file);
        fetch(APP_URL + '/api/chat.php?action=upload_file', {
            method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.file_url) {
                var fd2 = new FormData();
                fd2.append('_csrf_token', CSRF);
                fd2.append('room_id',    currentRoomId);
                fd2.append('message',    caption || '');
                fd2.append('type',       (data.mime_type && data.mime_type.startsWith('image/')) ? 'image' : 'file');
                fd2.append('file_url',   data.file_url);
                fd2.append('file_name',  data.file_name || file.name);
                fd2.append('file_size',  data.file_size || file.size);
                return fetch(APP_URL + '/api/chat.php?action=send', {
                    method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: fd2
                })
                .then(function(r2) { return r2.json(); })
                .then(function(d2) {
                    var m = d2.data || d2.message || null;
                    if (m) appendMessage(m, false);
                    uploadAndSendFiles('');
                });
            }
            uploadAndSendFiles(caption);
        })
        .catch(function() { uploadAndSendFiles(caption); });
    }

    function updateConvPreview(roomId, body) {
        var item = convList ? convList.querySelector('[data-room-id="' + roomId + '"]') : null;
        if (!item) return;
        var preview = item.querySelector('.text-muted.text-truncate');
        if (preview) preview.textContent = truncate(body, 48);
        var timeEl = item.querySelector('[style*="white-space"]');
        if (timeEl) timeEl.textContent = 'Just now';
    }

    // Typing indicator
    function onTyping() {
        if (!isTyping) { isTyping = true; sendTypingSignal(true); }
        clearTimeout(typingTimer);
        typingTimer = setTimeout(stopTypingSignal, 2500);
    }

    function stopTypingSignal() {
        if (isTyping) { isTyping = false; sendTypingSignal(false); }
        clearTimeout(typingTimer);
    }

    function sendTypingSignal(typing) {
        if (!currentRoomId) return;
        var fd = new FormData();
        fd.append('_csrf_token', CSRF);
        fd.append('room_id', currentRoomId);
        fd.append('typing', typing ? '1' : '0');
        fetch(APP_URL + '/api/chat.php?action=typing', {
            method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: fd
        }).catch(function() {});
    }

    function showTyping(userName) {
        if (typingIndicator) typingIndicator.style.display = 'block';
        if (typingName) typingName.textContent = escHtml(userName) + ' is typing\u2026';
    }

    function hideTyping() {
        if (typingIndicator) typingIndicator.style.display = 'none';
    }

    // Tab / filter
    function applyTab() {
        var filtered = allRooms.slice();
        if (currentTab !== 'all') {
            filtered = filtered.filter(function(r) {
                var t = r.type || 'direct';
                if (currentTab === 'direct')  return t === 'direct';
                if (currentTab === 'group')   return t === 'group';
                if (currentTab === 'support') return t === 'support';
                if (currentTab === 'unread')  return parseInt(r.unread_count || 0, 10) > 0;
                if (currentTab === 'orders')  return t === 'order';
                return true;
            });
        }
        var q = convSearch ? convSearch.value.trim().toLowerCase() : '';
        if (q) {
            filtered = filtered.filter(function(r) {
                return (r.name || '').toLowerCase().indexOf(q) !== -1 ||
                       (r.last_message_preview || r.last_message || '').toLowerCase().indexOf(q) !== -1;
            });
        }
        renderConvList(filtered);
        if (currentRoomId) {
            var ai = convList ? convList.querySelector('[data-room-id="' + currentRoomId + '"]') : null;
            if (ai) ai.classList.add('active');
        }
    }

    // Emoji picker
    function buildEmojiPicker() {
        var picker = document.getElementById('emojiPicker');
        if (!picker) return;
        EMOJI_SET.forEach(function(em) {
            var btn = document.createElement('span');
            btn.className = 'emoji-btn-item';
            btn.textContent = em;
            btn.addEventListener('click', function() {
                if (messageInput) {
                    var pos = messageInput.selectionStart || messageInput.value.length;
                    messageInput.value =
                        messageInput.value.substring(0, pos) + em + messageInput.value.substring(pos);
                    messageInput.focus();
                    messageInput.selectionStart = messageInput.selectionEnd = pos + em.length;
                }
                picker.classList.add('d-none');
            });
            picker.appendChild(btn);
        });
    }

    // Events
    function bindEvents() {
        if (sendBtn) sendBtn.addEventListener('click', sendMessage);

        if (messageInput) {
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
            });
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                onTyping();
            });
        }

        if (convSearch) convSearch.addEventListener('input', applyTab);

        // Inbox tabs (new layout)
        document.querySelectorAll('.conv-tab').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.conv-tab').forEach(function(b) {
                    b.classList.remove('btn-primary', 'active');
                    b.classList.add('btn-outline-secondary');
                });
                this.classList.remove('btn-outline-secondary');
                this.classList.add('btn-primary', 'active');
                currentTab = this.dataset.tab || 'all';
                applyTab();
            });
        });

        // Legacy filter buttons (index.php)
        document.querySelectorAll('.filter-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(function(b) {
                    b.classList.remove('btn-primary', 'active');
                    b.classList.add('btn-outline-secondary');
                });
                this.classList.remove('btn-outline-secondary');
                this.classList.add('btn-primary', 'active');
                currentTab = this.dataset.filter || 'all';
                applyTab();
            });
        });

        var attachBtn = document.getElementById('attachBtn');
        if (attachBtn && fileAttach) {
            attachBtn.addEventListener('click', function() { fileAttach.click(); });
        }

        if (fileAttach) {
            fileAttach.addEventListener('change', function() {
                pendingFiles = Array.from(this.files);
                renderAttachPreviews();
            });
        }

        // Emoji toggle (new layout: emojiToggle + emojiPicker)
        var emojiToggle = document.getElementById('emojiToggle');
        var emojiPicker = document.getElementById('emojiPicker');
        if (emojiToggle && emojiPicker) {
            emojiToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                emojiPicker.classList.toggle('d-none');
            });
            document.addEventListener('click', function(e) {
                if (!emojiPicker.contains(e.target) && e.target !== emojiToggle) {
                    emojiPicker.classList.add('d-none');
                }
            });
        }

        // Legacy emoji button (index.php / conversation.php)
        var emojiBtn = document.getElementById('emojiBtn');
        if (emojiBtn) {
            emojiBtn.addEventListener('click', function() {
                var em = EMOJI_SET[Math.floor(Math.random() * EMOJI_SET.length)];
                if (messageInput) { messageInput.value += em; messageInput.focus(); }
            });
        }

        // Mobile back button
        var backBtn = document.getElementById('backToListBtn');
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                var sidebar = document.getElementById('chatSidebar');
                if (sidebar) sidebar.classList.remove('chat-sidebar-mobile-hidden');
                stopPolling();
            });
        }

        var muteLink = document.getElementById('muteLink');
        if (muteLink) {
            muteLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (!currentRoomId) return;
                var fd = new FormData();
                fd.append('_csrf_token', CSRF);
                fd.append('room_id', currentRoomId);
                fetch(APP_URL + '/api/chat.php?action=mute_room', {
                    method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: fd
                }).catch(function() {});
            });
        }

        var deleteChatLink = document.getElementById('deleteChatLink');
        if (deleteChatLink) {
            deleteChatLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (!currentRoomId) return;
                if (!confirm('Delete this conversation? This cannot be undone.')) return;
                var fd = new FormData();
                fd.append('_csrf_token', CSRF);
                fd.append('room_id', currentRoomId);
                fetch(APP_URL + '/api/chat.php?action=delete_room', {
                    method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: fd
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        window.location.href = APP_URL + '/pages/messages/inbox.php';
                    }
                })
                .catch(function() {});
            });
        }

        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.roomId) openRoom(e.state.roomId);
        });
    }

    // Utilities
    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    function truncate(str, len) {
        return str.length > len ? str.substring(0, len) + '\u2026' : str;
    }

    function timeAgo(dateStr) {
        var now  = new Date();
        var then = new Date(dateStr);
        if (isNaN(then)) return dateStr;
        var diff = Math.floor((now - then) / 1000);
        if (diff < 60)    return 'Just now';
        if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return then.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    // Bootstrap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
