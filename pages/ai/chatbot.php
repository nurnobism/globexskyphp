<?php
/**
 * pages/ai/chatbot.php — AI Chat Assistant (Phase 8)
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$userId = (int)$_SESSION['user_id'];
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
#chat-container { display:flex; height:calc(100vh - 140px); }
#chat-sidebar { width:260px; flex-shrink:0; border-right:1px solid #dee2e6; overflow-y:auto; }
#chat-main { flex:1; display:flex; flex-direction:column; overflow:hidden; }
#messages-area { flex:1; overflow-y:auto; padding:1rem; background:#f8f9fa; }
.msg-user   { display:flex; justify-content:flex-end; margin-bottom:1rem; }
.msg-ai     { display:flex; justify-content:flex-start; margin-bottom:1rem; }
.bubble-user{ background:#0d6efd; color:#fff; padding:.75rem 1rem; border-radius:18px 18px 4px 18px; max-width:70%; }
.bubble-ai  { background:#fff; color:#212529; padding:.75rem 1rem; border-radius:18px 18px 18px 4px; max-width:70%; box-shadow:0 1px 3px rgba(0,0,0,.1); }
#typing-indicator { display:none; padding:.5rem 1rem; }
.typing-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#999; margin:0 2px; animation:bounce .9s infinite; }
.typing-dot:nth-child(2){animation-delay:.2s;} .typing-dot:nth-child(3){animation-delay:.4s;}
@keyframes bounce { 0%,60%,100%{transform:translateY(0)} 30%{transform:translateY(-6px)} }
.conv-item { cursor:pointer; padding:.5rem .75rem; border-radius:6px; margin:.15rem .5rem; font-size:.85rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.conv-item:hover,.conv-item.active { background:#e9ecef; }
</style>

<div class="container-fluid p-0">
<div id="chat-container">
    <!-- Sidebar -->
    <div id="chat-sidebar" class="bg-white">
        <div class="p-3 border-bottom">
            <button class="btn btn-primary btn-sm w-100" id="new-chat-btn"><i class="bi bi-plus-lg me-1"></i>New Chat</button>
        </div>
        <div class="p-2">
            <input type="text" class="form-control form-control-sm" placeholder="Search conversations..." id="conv-search">
        </div>
        <div id="conversations-list" class="py-1"></div>
    </div>

    <!-- Main Chat -->
    <div id="chat-main">
        <div class="border-bottom bg-white px-3 py-2 d-flex align-items-center">
            <i class="bi bi-robot text-primary me-2"></i>
            <span id="chat-title" class="fw-semibold">GlobexBot</span>
            <div class="ms-auto">
                <select class="form-select form-select-sm" id="context-selector" style="width:160px;">
                    <option value="general">General</option>
                    <option value="product">Product Help</option>
                    <option value="order">Order Support</option>
                    <option value="sourcing">Sourcing</option>
                    <option value="analytics">Analytics</option>
                </select>
            </div>
        </div>

        <div id="messages-area">
            <div class="text-center py-5 text-muted" id="welcome-msg">
                <i class="bi bi-robot fs-1 text-primary"></i>
                <h5 class="mt-3">Hi! I'm GlobexBot</h5>
                <p>Your AI assistant for GlobexSky marketplace.<br>Ask me anything about products, orders, sourcing, or analytics!</p>
            </div>
        </div>

        <div id="typing-indicator">
            <div class="bubble-ai d-inline-block">
                <span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>
            </div>
        </div>

        <div class="border-top bg-white p-3">
            <div class="d-flex gap-2">
                <textarea id="message-input" class="form-control" rows="1" placeholder="Type your message... (Enter to send, Shift+Enter for new line)" style="resize:none;max-height:120px;"></textarea>
                <button class="btn btn-primary" id="send-btn" style="white-space:nowrap;"><i class="bi bi-send"></i></button>
            </div>
            <div class="d-flex justify-content-between mt-1">
                <small class="text-muted" id="token-info">Ready</small>
                <small class="text-muted" id="conv-id-display"></small>
            </div>
        </div>
    </div>
</div>
</div>

<script src="/assets/js/ai-chat.js"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
