/**
 * assets/js/ai-chat.js — AI Chat UI Controller (Phase 8)
 */
(function() {
    'use strict';

    let currentConversationId = null;
    let isTyping = false;

    const messagesArea       = document.getElementById('messages-area');
    const messageInput       = document.getElementById('message-input');
    const sendBtn            = document.getElementById('send-btn');
    const conversationsList  = document.getElementById('conversations-list');
    const newChatBtn         = document.getElementById('new-chat-btn');
    const contextSelector    = document.getElementById('context-selector');
    const tokenInfo          = document.getElementById('token-info');
    const convIdDisplay      = document.getElementById('conv-id-display');

    if (!messagesArea) return;

    // ── Load Conversations ────────────────────────────────────────
    function loadConversations() {
        fetch('/api/ai/chatbot.php?action=conversations')
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                conversationsList.innerHTML = '';
                if (!d.data.length) {
                    conversationsList.innerHTML = '<div class="text-muted text-center py-3 small">No conversations yet</div>';
                    return;
                }
                d.data.forEach(conv => {
                    const div = document.createElement('div');
                    div.className = 'conv-item' + (conv.id == currentConversationId ? ' active' : '');
                    div.textContent = conv.title || 'New conversation';
                    div.title = conv.title || '';
                    div.dataset.convId = conv.id;
                    div.addEventListener('click', () => loadConversation(conv.id, conv.title));
                    conversationsList.appendChild(div);
                });
            }).catch(() => {});
    }

    // ── Load a specific conversation ──────────────────────────────
    function loadConversation(convId, title) {
        currentConversationId = convId;
        document.getElementById('welcome-msg') && (document.getElementById('welcome-msg').style.display = 'none');
        messagesArea.innerHTML = '';
        if (convIdDisplay) convIdDisplay.textContent = 'Conv #' + convId;
        document.getElementById('chat-title') && (document.getElementById('chat-title').textContent = title || 'GlobexBot');
        document.querySelectorAll('.conv-item').forEach(el => el.classList.toggle('active', el.dataset.convId == convId));

        fetch('/api/ai/chatbot.php?action=messages&conversation_id=' + convId)
            .then(r => r.json())
            .then(d => {
                if (d.success && d.data.messages) {
                    d.data.messages.forEach(msg => appendMessage(msg.role, msg.content));
                    scrollToBottom();
                }
            }).catch(() => {});
    }

    // ── Append a message bubble ───────────────────────────────────
    function appendMessage(role, content) {
        const welcome = document.getElementById('welcome-msg');
        if (welcome) welcome.style.display = 'none';

        const div = document.createElement('div');
        div.className = role === 'user' ? 'msg-user' : 'msg-ai';
        const bubble = document.createElement('div');
        bubble.className = role === 'user' ? 'bubble-user' : 'bubble-ai';
        bubble.innerHTML = escapeHtml(content).replace(/\n/g, '<br>');
        div.appendChild(bubble);
        messagesArea.appendChild(div);
    }

    // ── Typewriter effect ─────────────────────────────────────────
    function typewriterAppend(text, callback) {
        const div = document.createElement('div');
        div.className = 'msg-ai';
        const bubble = document.createElement('div');
        bubble.className = 'bubble-ai';
        div.appendChild(bubble);
        messagesArea.appendChild(div);

        let i = 0;
        function typeChar() {
            if (i < text.length) {
                bubble.innerHTML = escapeHtml(text.substring(0, i + 1)).replace(/\n/g, '<br>');
                i++;
                scrollToBottom();
                setTimeout(typeChar, 8);
            } else {
                if (callback) callback();
            }
        }
        typeChar();
    }

    // ── Send a message ────────────────────────────────────────────
    function sendMessage() {
        const text = messageInput.value.trim();
        if (!text || isTyping) return;

        appendMessage('user', text);
        messageInput.value = '';
        messageInput.style.height = 'auto';
        scrollToBottom();
        showTyping(true);
        isTyping = true;
        if (sendBtn) { sendBtn.disabled = true; }

        const payload = {
            action: 'send',
            message: text,
            conversation_id: currentConversationId || 0,
            context: contextSelector ? contextSelector.value : 'general',
        };

        fetch('/api/ai/chatbot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(d => {
            showTyping(false);
            isTyping = false;
            if (sendBtn) sendBtn.disabled = false;

            if (d.success) {
                currentConversationId = d.data.conversation_id;
                if (convIdDisplay) convIdDisplay.textContent = 'Conv #' + d.data.conversation_id;
                typewriterAppend(d.data.response);
                loadConversations();
            } else {
                appendMessage('assistant', 'Sorry, I encountered an issue: ' + (d.error || 'Unknown error'));
                scrollToBottom();
            }
        })
        .catch(() => {
            showTyping(false);
            isTyping = false;
            if (sendBtn) sendBtn.disabled = false;
            appendMessage('assistant', 'Connection error. Please check your internet and try again.');
            scrollToBottom();
        });
    }

    function showTyping(show) {
        const indicator = document.getElementById('typing-indicator');
        if (indicator) indicator.style.display = show ? 'block' : 'none';
        if (show) scrollToBottom();
    }

    function scrollToBottom() {
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ── Event Listeners ───────────────────────────────────────────
    if (sendBtn) sendBtn.addEventListener('click', sendMessage);

    if (messageInput) {
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
    }

    if (newChatBtn) {
        newChatBtn.addEventListener('click', () => {
            currentConversationId = null;
            messagesArea.innerHTML = '';
            const welcome = document.getElementById('welcome-msg');
            if (welcome) { welcome.style.display = ''; messagesArea.appendChild(welcome); }
            if (convIdDisplay) convIdDisplay.textContent = '';
            document.getElementById('chat-title') && (document.getElementById('chat-title').textContent = 'GlobexBot');
            document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
        });
    }

    if (document.getElementById('conv-search')) {
        document.getElementById('conv-search').addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.conv-item').forEach(el => {
                el.style.display = el.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    loadConversations();
})();
