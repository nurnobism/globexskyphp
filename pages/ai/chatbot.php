<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$currentUser = getCurrentUser();
$pageTitle   = 'GlobexBot — AI Chatbot';
include __DIR__ . '/../../includes/header.php';
?>

<style>
    :root { --brand-orange: #FF6B35; --brand-dark: #1B2A4A; }
    body { background: #f4f6fb; }
    .chat-wrapper   { height: calc(100vh - 160px); min-height: 520px; display: flex; flex-direction: column; }
    .chat-header    { background: linear-gradient(135deg, var(--brand-dark), #2d4070);
                      border-radius: 16px 16px 0 0; padding: 1rem 1.5rem; }
    .chat-messages  { flex: 1; overflow-y: auto; padding: 1.25rem; background: #f9fafb;
                      scroll-behavior: smooth; }
    .chat-footer    { background: #fff; border-top: 1px solid #e9ecef; border-radius: 0 0 16px 16px;
                      padding: 1rem 1.25rem; }
    .msg-bubble     { max-width: 75%; border-radius: 18px; padding: .7rem 1.1rem;
                      font-size: .93rem; line-height: 1.5; word-break: break-word; }
    .msg-user       { background: var(--brand-orange); color: #fff; border-bottom-right-radius: 4px; }
    .msg-assistant  { background: #fff; color: #212529; border: 1px solid #e9ecef;
                      border-bottom-left-radius: 4px; box-shadow: 0 2px 6px rgba(0,0,0,.06); }
    .msg-wrap       { display: flex; align-items: flex-end; gap: .5rem; margin-bottom: 1rem; }
    .msg-wrap.user  { flex-direction: row-reverse; }
    .avatar         { width: 34px; height: 34px; border-radius: 50%; object-fit: cover;
                      flex-shrink: 0; display:flex; align-items:center; justify-content:center; }
    .avatar-bot     { background: var(--brand-dark); color: #fff; font-size: .85rem; }
    .avatar-user    { background: var(--brand-orange); color: #fff; font-size: .85rem; }
    .typing-dot     { width: 8px; height: 8px; border-radius: 50%; background: #adb5bd;
                      display: inline-block; animation: typing .9s infinite; }
    .typing-dot:nth-child(2) { animation-delay: .15s; }
    .typing-dot:nth-child(3) { animation-delay: .3s; }
    @keyframes typing { 0%,80%,100%{transform:scale(0.8);opacity:.5} 40%{transform:scale(1.1);opacity:1} }
    .quick-reply    { border-radius: 50px; font-size: .82rem; border: 1.5px solid var(--brand-orange);
                      color: var(--brand-orange); padding: .25rem .75rem; cursor: pointer;
                      transition: all .2s; background: #fff; }
    .quick-reply:hover { background: var(--brand-orange); color: #fff; }
    .chat-input     { border-radius: 50px; padding: .65rem 1.25rem; border: 1.5px solid #dee2e6;
                      font-size: .95rem; transition: border-color .2s; }
    .chat-input:focus { border-color: var(--brand-orange); box-shadow: 0 0 0 3px rgba(255,107,53,.12); }
    .send-btn       { border-radius: 50px; min-width: 80px; }
    .sidebar-card   { border-radius: 14px; }
</style>

<div class="container-fluid py-3" style="max-width:1400px;">
    <div class="row g-3">

        <!-- Sidebar -->
        <div class="col-lg-3 d-none d-lg-block">
            <div class="card sidebar-card border-0 shadow-sm mb-3">
                <div class="card-body p-3">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history text-warning me-2"></i>Recent Conversations</h6>
                    <div id="conversationList">
                        <div class="text-muted small text-center py-2">Loading…</div>
                    </div>
                    <button class="btn btn-outline-primary btn-sm w-100 mt-2 rounded-pill" id="newChatBtn">
                        <i class="bi bi-plus-lg me-1"></i> New Chat
                    </button>
                </div>
            </div>
            <div class="card sidebar-card border-0 shadow-sm">
                <div class="card-body p-3">
                    <h6 class="fw-bold mb-3"><i class="bi bi-lightbulb text-warning me-2"></i>Suggested Topics</h6>
                    <div class="d-flex flex-column gap-2">
                        <?php
                        $topics = [
                            ['How do I track my order?',    'bi-truck'],
                            ['Find suppliers for textiles', 'bi-search'],
                            ['What certifications do I need?','bi-patch-check'],
                            ['How does RFQ work?',          'bi-file-text'],
                            ['Bulk order discounts',        'bi-tags'],
                        ];
                        foreach ($topics as [$t, $i]):
                        ?>
                        <button class="btn btn-light btn-sm text-start quick-topic rounded-3"
                                data-msg="<?= e($t) ?>">
                            <i class="bi <?= $i ?> text-warning me-2"></i><?= e($t) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Window -->
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm chat-wrapper">
                <!-- Header -->
                <div class="chat-header d-flex align-items-center gap-3">
                    <div class="avatar avatar-bot flex-shrink-0">
                        <i class="bi bi-robot"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-white fw-bold">GlobexBot</div>
                        <div class="text-white-75 small"><span class="badge bg-success" style="font-size:.65rem;">●</span> Online · Powered by DeepSeek AI</div>
                    </div>
                    <div class="ms-auto d-flex gap-2">
                        <button class="btn btn-outline-light btn-sm rounded-pill" id="clearChatBtn" title="Clear conversation">
                            <i class="bi bi-trash3"></i>
                        </button>
                        <a href="<?= APP_URL ?>/pages/ai/index.php" class="btn btn-outline-light btn-sm rounded-pill">
                            <i class="bi bi-grid"></i>
                        </a>
                    </div>
                </div>

                <!-- Messages -->
                <div class="chat-messages" id="chatMessages">
                    <!-- Welcome message -->
                    <div class="msg-wrap" id="welcomeMsg">
                        <div class="avatar avatar-bot"><i class="bi bi-robot"></i></div>
                        <div>
                            <div class="msg-bubble msg-assistant">
                                👋 Hi<?= $currentUser ? ', ' . e($currentUser['first_name']) : '' ?>! I'm <strong>GlobexBot</strong>, your AI assistant for GlobexSky.<br><br>
                                I can help you find products, track orders, connect with suppliers, and answer any trade questions. What can I do for you today?
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <span class="quick-reply" data-msg="Show me trending products">🔥 Trending products</span>
                                <span class="quick-reply" data-msg="How do I place a bulk order?">📦 Bulk orders</span>
                                <span class="quick-reply" data-msg="What payment methods do you accept?">💳 Payments</span>
                                <span class="quick-reply" data-msg="Help me find a supplier">🏭 Find supplier</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="chat-footer">
                    <form id="chatForm" class="d-flex align-items-center gap-2">
                        <?= csrfField() ?>
                        <input type="text" id="chatInput" class="form-control chat-input flex-grow-1"
                               placeholder="Type your message…" autocomplete="off" maxlength="2000">
                        <button type="submit" class="btn btn-primary send-btn"
                                style="background:var(--brand-orange);border-color:var(--brand-orange);">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </form>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small class="text-muted">Press <kbd>Enter</kbd> to send · <kbd>Shift+Enter</kbd> for new line</small>
                        <small class="text-muted" id="charCount">0 / 2000</small>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
const CHATBOT_URL   = '<?= APP_URL ?>/api/ai/chatbot.php';
const CSRF_TOKEN    = '<?= csrfToken() ?>';
let conversationId  = localStorage.getItem('globex_conv_id') || '';
let messageHistory  = [];

// Load stored history from localStorage
function loadLocalHistory() {
    try {
        const stored = JSON.parse(localStorage.getItem('globex_chat_' + conversationId) || '[]');
        if (stored.length > 0) {
            // Restore messages (skip welcome)
            stored.forEach(m => appendMessage(m.role, m.content, false));
        }
    } catch (e) {}
}

function scrollToBottom() {
    const el = document.getElementById('chatMessages');
    el.scrollTop = el.scrollHeight;
}

function appendMessage(role, content, save = true) {
    const isUser = role === 'user';
    const ts     = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    const avatarHtml = isUser
        ? `<div class="avatar avatar-user"><i class="bi bi-person-fill"></i></div>`
        : `<div class="avatar avatar-bot"><i class="bi bi-robot"></i></div>`;

    // Convert markdown-ish to HTML
    const formatted = content
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');

    const msgHtml = `
        <div class="msg-wrap ${isUser ? 'user' : ''}">
            ${isUser ? '' : avatarHtml}
            <div>
                <div class="msg-bubble ${isUser ? 'msg-user' : 'msg-assistant'}">${formatted}</div>
                <div class="text-muted" style="font-size:.72rem;margin-top:.25rem;${isUser ? 'text-align:right' : ''}">${ts}</div>
            </div>
            ${isUser ? avatarHtml : ''}
        </div>`;

    document.getElementById('chatMessages').insertAdjacentHTML('beforeend', msgHtml);
    scrollToBottom();

    if (save) {
        messageHistory.push({role, content});
        if (conversationId) {
            localStorage.setItem('globex_chat_' + conversationId, JSON.stringify(messageHistory));
        }
    }
}

function showTyping() {
    const html = `<div class="msg-wrap" id="typingIndicator">
        <div class="avatar avatar-bot"><i class="bi bi-robot"></i></div>
        <div class="msg-bubble msg-assistant">
            <span class="typing-dot"></span>
            <span class="typing-dot"></span>
            <span class="typing-dot"></span>
        </div>
    </div>`;
    document.getElementById('chatMessages').insertAdjacentHTML('beforeend', html);
    scrollToBottom();
}

function hideTyping() {
    const el = document.getElementById('typingIndicator');
    if (el) el.remove();
}

async function sendMessage(message) {
    if (!message.trim()) return;
    appendMessage('user', message);

    const input = document.getElementById('chatInput');
    input.value = '';
    input.disabled = true;
    document.querySelector('#chatForm button[type=submit]').disabled = true;
    showTyping();

    try {
        const body = new URLSearchParams({
            message,
            conversation_id: conversationId,
            _csrf_token: CSRF_TOKEN
        });

        const res  = await fetch(CHATBOT_URL, {method:'POST', body});
        const data = await res.json();
        hideTyping();

        if (data.success) {
            conversationId = data.conversation_id;
            localStorage.setItem('globex_conv_id', conversationId);
            appendMessage('assistant', data.response);
            updateConversationList();
        } else {
            appendMessage('assistant', '⚠️ Sorry, I encountered an issue: ' + (data.message || 'Unknown error'));
        }
    } catch (err) {
        hideTyping();
        appendMessage('assistant', '⚠️ Connection error. Please check your connection and try again.');
    } finally {
        input.disabled = false;
        document.querySelector('#chatForm button[type=submit]').disabled = false;
        input.focus();
    }
}

// Form submit
document.getElementById('chatForm').addEventListener('submit', e => {
    e.preventDefault();
    sendMessage(document.getElementById('chatInput').value.trim());
});

// Character counter
document.getElementById('chatInput').addEventListener('input', function () {
    document.getElementById('charCount').textContent = `${this.value.length} / 2000`;
});

// Quick replies
document.addEventListener('click', e => {
    const el = e.target.closest('.quick-reply, .quick-topic');
    if (el) sendMessage(el.dataset.msg);
});

// Clear chat
document.getElementById('clearChatBtn').addEventListener('click', () => {
    if (!confirm('Clear this conversation?')) return;
    conversationId = '';
    messageHistory = [];
    localStorage.removeItem('globex_conv_id');
    const container = document.getElementById('chatMessages');
    // Keep only welcome message
    const msgs = container.querySelectorAll('.msg-wrap:not(#welcomeMsg)');
    msgs.forEach(m => m.remove());
    conversationId = '';
    localStorage.setItem('globex_conv_id', '');
});

// New chat
document.getElementById('newChatBtn').addEventListener('click', () => {
    conversationId = '';
    messageHistory = [];
    localStorage.removeItem('globex_conv_id');
    const container = document.getElementById('chatMessages');
    const msgs = container.querySelectorAll('.msg-wrap:not(#welcomeMsg)');
    msgs.forEach(m => m.remove());
});

function updateConversationList() {
    const list = document.getElementById('conversationList');
    if (!conversationId) { list.innerHTML = '<div class="text-muted small text-center py-2">No conversations yet</div>'; return; }
    const preview = messageHistory.filter(m => m.role === 'user').slice(-1)[0]?.content || 'Conversation';
    const shortened = preview.length > 40 ? preview.substring(0, 40) + '…' : preview;
    list.innerHTML = `<div class="small p-2 rounded-3 bg-light text-truncate" style="cursor:pointer;">
        <i class="bi bi-chat-dots text-warning me-1"></i>${shortened}
    </div>`;
}

// Initialise
if (conversationId) loadLocalHistory();
updateConversationList();
scrollToBottom();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
