/**
 * GlobexSky — AI Chatbot Frontend (assets/js/ai-chatbot.js)
 *
 * Full-page chatbot interface:
 * - AJAX message send/receive
 * - Lightweight markdown rendering
 * - Typing indicator
 * - Conversation switching
 * - Token counter
 * - Error handling with retry
 */
(function () {
    'use strict';

    /* ── Config ──────────────────────────────────────────────── */
    const API     = '/api/ai.php';
    const MAX_LEN = 500;

    /* ── DOM refs ────────────────────────────────────────────── */
    const msgList       = document.getElementById('chatMessages');
    const inputEl       = document.getElementById('chatInput');
    const sendBtn       = document.getElementById('chatSend');
    const newChatBtn    = document.getElementById('newChatBtn');
    const convList      = document.getElementById('convList');
    const contextSelect = document.getElementById('contextType');
    const tokenDisplay  = document.getElementById('tokenDisplay');
    const charCount     = document.getElementById('charCount');

    if (!msgList || !inputEl || !sendBtn) return;

    let currentConversationId = null;
    let isSending             = false;

    /* ── Markdown renderer (minimal) ────────────────────────── */
    function renderMd(text) {
        return text
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/`(.+?)`/g, '<code>$1</code>')
            .replace(/^#{1,3} (.+)$/gm, '<strong>$1</strong>')
            .replace(/^[-*] (.+)$/gm, '• $1')
            .replace(/\n/g, '<br>');
    }

    /* ── Message renderer ────────────────────────────────────── */
    function appendMessage(role, content, animate) {
        const wrap = document.createElement('div');
        wrap.className = 'msg-wrap ' + (role === 'user' ? 'user' : '');

        const avatar = document.createElement('div');
        avatar.className = 'avatar ' + (role === 'user' ? 'avatar-user' : 'avatar-bot');
        avatar.innerHTML = role === 'user' ? '<i class="bi bi-person-fill"></i>' : '<i class="bi bi-robot"></i>';

        const bubble = document.createElement('div');
        bubble.className = 'msg-bubble ' + (role === 'user' ? 'msg-user' : 'msg-assistant');
        bubble.innerHTML = role === 'assistant' ? renderMd(content) : escHtml(content);

        if (animate) {
            bubble.style.opacity = '0';
            bubble.style.transform = 'translateY(8px)';
            bubble.style.transition = 'opacity .2s, transform .2s';
            requestAnimationFrame(function () {
                bubble.style.opacity = '1';
                bubble.style.transform = 'translateY(0)';
            });
        }

        wrap.appendChild(avatar);
        wrap.appendChild(bubble);
        msgList.appendChild(wrap);
        scrollBottom();
        return bubble;
    }

    /* ── Typing indicator ────────────────────────────────────── */
    function showTyping() {
        const wrap = document.createElement('div');
        wrap.id = 'typingIndicator';
        wrap.className = 'msg-wrap';
        wrap.innerHTML = '<div class="avatar avatar-bot"><i class="bi bi-robot"></i></div>'
            + '<div class="msg-bubble msg-assistant" style="padding:.5rem .8rem">'
            + '<span class="gs-typing-dot"></span><span class="gs-typing-dot"></span><span class="gs-typing-dot"></span>'
            + '</div>';
        msgList.appendChild(wrap);
        scrollBottom();
    }

    function hideTyping() {
        const el = document.getElementById('typingIndicator');
        if (el) el.remove();
    }

    function scrollBottom() {
        msgList.scrollTop = msgList.scrollHeight;
    }

    function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    /* ── Send message ────────────────────────────────────────── */
    async function sendMessage(retries) {
        if (isSending) return;
        const msg = inputEl.value.trim();
        if (!msg) return;

        isSending = true;
        sendBtn.disabled = true;
        inputEl.value = '';
        if (charCount) charCount.textContent = '0/' + MAX_LEN;

        appendMessage('user', msg, false);
        showTyping();

        const body = new URLSearchParams({
            message:         msg,
            conversation_id: currentConversationId || '',
            context_type:    contextSelect ? contextSelect.value : 'general',
        });

        try {
            const resp = await fetch(API + '?action=chat_send', {
                method: 'POST',
                body,
            });

            hideTyping();

            if (!resp.ok) {
                throw new Error('HTTP ' + resp.status);
            }

            const data = await resp.json();

            if (data.success) {
                currentConversationId = data.conversation_id;
                appendMessage('assistant', data.message, true);
                loadConversations();
                loadUsage();
            } else {
                appendMessage('assistant', '⚠️ ' + (data.error || 'AI is unavailable right now. Please try again.'), true);
            }
        } catch (err) {
            hideTyping();
            if (retries > 0) {
                setTimeout(function () { sendMessage(retries - 1); }, 2000);
                return;
            }
            appendMessage('assistant', '⚠️ Could not connect to AI. Please check your connection.', true);
        } finally {
            isSending = false;
            sendBtn.disabled = false;
            inputEl.focus();
        }
    }

    /* ── Load conversation list ──────────────────────────────── */
    async function loadConversations() {
        if (!convList) return;
        try {
            const resp = await fetch(API + '?action=chat_conversations');
            const data = await resp.json();
            if (!data.success) return;

            convList.innerHTML = '';
            (data.conversations || []).forEach(function (c) {
                const li = document.createElement('a');
                li.href = '#';
                li.className = 'list-group-item list-group-item-action py-2 px-3 small'
                             + (c.id === currentConversationId ? ' active' : '');
                li.innerHTML = '<div class="fw-semibold text-truncate" style="max-width:180px">'
                             + escHtml(c.title || 'Conversation') + '</div>'
                             + '<div class="text-muted" style="font-size:.72rem">'
                             + c.context_type + ' · ' + c.messages_count + ' msgs</div>';
                li.addEventListener('click', function (e) {
                    e.preventDefault();
                    loadConversation(c.id);
                });
                convList.appendChild(li);
            });
        } catch (e) { /* silent */ }
    }

    /* ── Load a specific conversation ───────────────────────── */
    async function loadConversation(convId) {
        currentConversationId = convId;
        msgList.innerHTML = '';

        try {
            const resp = await fetch(API + '?action=chat_history&conversation_id=' + convId);
            const data = await resp.json();
            if (data.success) {
                (data.messages || []).forEach(function (m) {
                    appendMessage(m.role, m.content, false);
                });
            }
        } catch (e) { /* silent */ }

        // Highlight active in sidebar
        document.querySelectorAll('#convList a').forEach(function (el) {
            el.classList.remove('active');
        });
        const active = convList ? convList.querySelector('[data-id="' + convId + '"]') : null;
        if (active) active.classList.add('active');
    }

    /* ── Load usage display ──────────────────────────────────── */
    async function loadUsage() {
        if (!tokenDisplay) return;
        try {
            const resp = await fetch(API + '?action=usage_stats&period=today');
            const data = await resp.json();
            if (data.success && data.budget) {
                const b = data.budget;
                tokenDisplay.textContent = b.used.toLocaleString() + ' / ' + b.limit.toLocaleString() + ' tokens today';
            }
        } catch (e) { /* silent */ }
    }

    /* ── Event listeners ─────────────────────────────────────── */
    sendBtn.addEventListener('click', function () { sendMessage(2); });

    inputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage(2);
        }
    });

    inputEl.addEventListener('input', function () {
        if (charCount) charCount.textContent = this.value.length + '/' + MAX_LEN;
    });

    if (newChatBtn) {
        newChatBtn.addEventListener('click', function () {
            currentConversationId = null;
            msgList.innerHTML = '';
            appendMessage('assistant', '👋 Hi! I\'m GlobexBot. How can I help you today?', false);
        });
    }

    // Quick action buttons
    document.querySelectorAll('.quick-action-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            inputEl.value = this.dataset.msg || this.textContent;
            sendMessage(2);
        });
    });

    /* ── Init ────────────────────────────────────────────────── */
    loadConversations();
    loadUsage();
}());
