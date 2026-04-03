<?php
/**
 * includes/ai-widget.php — Floating AI Chat Widget
 *
 * Renders a small floating chat bubble in the bottom-right corner
 * (Intercom/Zendesk style). Includes a mini chatbot popup window.
 * Only shown when ai_chatbot_enabled = true.
 *
 * Include this file once in footer.php or any layout template.
 */

// Only render if the AI chatbot feature is enabled
if (!function_exists('isAiEnabled') || !isAiEnabled('chatbot')) {
    return;
}
?>
<!-- AI Chat Widget -->
<div id="gs-ai-widget" class="gs-ai-widget" aria-label="AI Chat Assistant" role="complementary">
    <!-- Trigger button -->
    <button id="gsChatTrigger"
            class="gs-chat-trigger btn btn-primary rounded-circle shadow-lg"
            type="button"
            aria-label="Open AI chat"
            title="Chat with GlobexBot AI">
        <i class="bi bi-robot fs-5" id="gsChatIcon"></i>
        <span class="gs-badge d-none" id="gsChatBadge">1</span>
    </button>

    <!-- Mini chat window -->
    <div id="gsChatWindow" class="gs-chat-window shadow-lg d-none" role="dialog" aria-label="AI Chat">
        <div class="gs-chat-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <div class="gs-bot-avatar"><i class="bi bi-robot"></i></div>
                <div>
                    <div class="fw-semibold small">GlobexBot AI</div>
                    <div class="text-white-50" style="font-size:.72rem">Powered by DeepSeek</div>
                </div>
            </div>
            <div class="d-flex gap-1">
                <a href="<?= defined('APP_URL') ? APP_URL : '' ?>/pages/ai/chatbot.php"
                   class="btn btn-sm btn-outline-light py-0 px-2"
                   title="Open full chat" target="_blank">
                    <i class="bi bi-arrows-fullscreen" style="font-size:.75rem"></i>
                </a>
                <button id="gsChatClose" class="btn btn-sm btn-outline-light py-0 px-2" type="button" aria-label="Close">
                    <i class="bi bi-x-lg" style="font-size:.75rem"></i>
                </button>
            </div>
        </div>

        <div id="gsChatMessages" class="gs-chat-messages">
            <!-- Welcome message -->
            <div class="gs-msg gs-msg-bot">
                <div class="gs-msg-bubble">
                    👋 Hi! I'm GlobexBot. How can I help you today?
                </div>
            </div>
        </div>

        <div id="gsQuickReplies" class="gs-quick-replies px-2 pb-1">
            <button class="btn btn-outline-secondary btn-sm gs-quick-btn" type="button" data-msg="Find products for me">Find products</button>
            <button class="btn btn-outline-secondary btn-sm gs-quick-btn" type="button" data-msg="How do I track my order?">Track order</button>
            <button class="btn btn-outline-secondary btn-sm gs-quick-btn" type="button" data-msg="I need sourcing help">Sourcing help</button>
        </div>

        <div class="gs-chat-footer d-flex gap-2">
            <input id="gsChatInput"
                   type="text"
                   class="form-control form-control-sm"
                   placeholder="Type a message..."
                   maxlength="500"
                   autocomplete="off">
            <button id="gsChatSend" class="btn btn-primary btn-sm px-3" type="button" aria-label="Send">
                <i class="bi bi-send-fill"></i>
            </button>
        </div>
    </div>
</div>

<style>
.gs-ai-widget {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    font-family: inherit;
}
.gs-chat-trigger {
    width: 56px;
    height: 56px;
    border-radius: 50% !important;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: transform .2s;
}
.gs-chat-trigger:hover { transform: scale(1.08); }
.gs-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    background: #dc3545;
    color: #fff;
    font-size: .65rem;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.gs-chat-window {
    position: absolute;
    bottom: 68px;
    right: 0;
    width: 340px;
    max-height: 480px;
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    display: flex !important;
    flex-direction: column;
    animation: slideUp .2s ease;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
.gs-chat-header {
    background: linear-gradient(135deg, #1B2A4A, #2d4070);
    color: #fff;
    padding: .75rem 1rem;
    flex-shrink: 0;
}
.gs-bot-avatar {
    width: 32px;
    height: 32px;
    background: rgba(255,255,255,.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}
.gs-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: .75rem;
    background: #f9fafb;
    display: flex;
    flex-direction: column;
    gap: .5rem;
}
.gs-msg { display: flex; }
.gs-msg-bot { justify-content: flex-start; }
.gs-msg-user { justify-content: flex-end; }
.gs-msg-bubble {
    max-width: 80%;
    padding: .5rem .8rem;
    border-radius: 16px;
    font-size: .87rem;
    line-height: 1.4;
    word-break: break-word;
}
.gs-msg-bot .gs-msg-bubble  { background: #fff; border: 1px solid #e9ecef; border-bottom-left-radius: 4px; }
.gs-msg-user .gs-msg-bubble { background: #FF6B35; color: #fff; border-bottom-right-radius: 4px; }
.gs-typing-dot {
    display: inline-block;
    width: 6px; height: 6px;
    background: #adb5bd;
    border-radius: 50%;
    animation: bounce .8s infinite alternate;
}
.gs-typing-dot:nth-child(2) { animation-delay: .15s; }
.gs-typing-dot:nth-child(3) { animation-delay: .3s; }
@keyframes bounce {
    from { transform: translateY(0); }
    to   { transform: translateY(-5px); }
}
.gs-quick-replies {
    display: flex;
    flex-wrap: wrap;
    gap: .25rem;
    background: #f9fafb;
    flex-shrink: 0;
}
.gs-quick-btn { font-size: .75rem !important; padding: .2rem .5rem !important; }
.gs-chat-footer {
    padding: .6rem .75rem;
    border-top: 1px solid #e9ecef;
    background: #fff;
    flex-shrink: 0;
}
@media (max-width: 400px) {
    .gs-chat-window { width: calc(100vw - 32px); right: -8px; }
}
</style>

<script src="<?= defined('APP_URL') ? APP_URL : '' ?>/assets/js/chatbot.js" defer></script>
