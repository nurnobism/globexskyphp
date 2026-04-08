<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$pageTitle     = 'Messages — Inbox';
$currentUserId = (int)$_SESSION['user_id'];
include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/chat.css">

<div class="container-fluid py-3">
    <div class="card shadow-sm border-0 chat-layout">
        <div class="row g-0 h-100">

            <!-- ── Left Sidebar ──────────────────────────────────── -->
            <div class="col-md-4 col-lg-3 chat-sidebar h-100" id="chatSidebar">

                <div class="chat-sidebar-header">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-chat-dots text-primary me-2"></i>Messages
                        </h6>
                        <a href="<?= APP_URL ?>/pages/messages/new.php"
                           class="btn btn-sm btn-primary" title="New Message">
                            <i class="bi bi-pencil-square"></i>
                        </a>
                    </div>

                    <!-- Search -->
                    <input type="text" id="convSearch" class="form-control form-control-sm mb-2"
                           placeholder="Search conversations…" autocomplete="off">

                    <!-- Tabs -->
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <button class="btn btn-primary conv-tab active" data-tab="all">All</button>
                        <button class="btn btn-outline-secondary conv-tab" data-tab="direct">Direct</button>
                        <button class="btn btn-outline-secondary conv-tab" data-tab="group">Groups</button>
                        <button class="btn btn-outline-secondary conv-tab" data-tab="support">Support</button>
                    </div>
                </div>

                <!-- Conversation list -->
                <div class="conv-list" id="convList">
                    <div class="text-center py-4 text-muted" id="convLoading">
                        <div class="spinner-border spinner-border-sm"></div>
                        <p class="small mt-2">Loading…</p>
                    </div>
                </div>
            </div>

            <!-- ── Right: Chat Panel ─────────────────────────────── -->
            <div class="col-md-8 col-lg-9 chat-panel h-100" id="chatPanel">

                <!-- Empty state -->
                <div id="emptyState" class="chat-empty-state">
                    <i class="bi bi-chat-square-dots display-1 text-primary opacity-25"></i>
                    <h5 class="mt-3 text-muted">Select a conversation</h5>
                    <p class="small mb-3">Choose from the sidebar or start a new chat.</p>
                    <a href="<?= APP_URL ?>/pages/messages/new.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-pencil-square me-1"></i>New Message
                    </a>
                </div>

                <!-- Active conversation (shown when room selected) -->
                <div id="activeChat" class="d-none d-flex flex-column h-100">

                    <!-- Header -->
                    <div class="chat-panel-header">
                        <!-- Mobile back button -->
                        <button class="btn btn-sm btn-link text-muted d-md-none p-0 me-1"
                                id="backToListBtn" title="Back">
                            <i class="bi bi-arrow-left fs-5"></i>
                        </button>

                        <div class="chat-avatar" id="chatHeaderAvatar">?</div>

                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-semibold text-truncate" id="chatHeaderName">—</div>
                            <div class="small" id="chatHeaderStatus">
                                <span class="online-dot me-1 offline"></span>
                                <span id="chatHeaderStatusText">Offline</span>
                            </div>
                        </div>

                        <div class="d-flex gap-1 ms-2">
                            <a href="<?= APP_URL ?>/pages/messages/contacts.php"
                               class="btn btn-sm btn-outline-secondary" title="Contacts">
                                <i class="bi bi-people"></i>
                            </a>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="dropdown" title="More options">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" id="viewProfileLink">
                                        <i class="bi bi-person me-2"></i>View Profile
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" id="muteLink">
                                        <i class="bi bi-bell-slash me-2"></i>Mute Notifications
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" id="deleteChatLink">
                                        <i class="bi bi-trash me-2"></i>Delete Chat
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <div class="text-center text-muted small py-3"
                             id="messagesLoading" style="display:none;">
                            <div class="spinner-border spinner-border-sm"></div> Loading…
                        </div>
                        <div id="loadMoreBtn" class="text-center py-2" style="display:none;">
                            <button class="btn btn-sm btn-outline-secondary" id="loadOlderBtn">
                                Load older messages
                            </button>
                        </div>
                    </div>

                    <!-- Typing indicator -->
                    <div id="typingIndicator" style="display:none;">
                        <div class="d-flex align-items-center gap-2">
                            <div class="typing-dots"><span></span><span></span><span></span></div>
                            <small class="text-muted" id="typingName">Someone is typing…</small>
                        </div>
                    </div>

                    <!-- Input area -->
                    <div class="chat-input-area">
                        <div class="chat-input-toolbar">
                            <!-- Attach file -->
                            <button class="btn btn-outline-secondary btn-sm"
                                    id="attachBtn" title="Attach file">
                                <i class="bi bi-paperclip"></i>
                            </button>
                            <input type="file" id="fileAttach" class="d-none" multiple
                                   accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">

                            <!-- Text input -->
                            <textarea id="messageInput" class="form-control form-control-sm flex-grow-1"
                                      rows="1"
                                      placeholder="Type a message… (Enter to send, Shift+Enter for newline)"></textarea>

                            <!-- Emoji -->
                            <div class="position-relative">
                                <button class="btn btn-outline-secondary btn-sm" id="emojiToggle"
                                        title="Emoji">
                                    <i class="bi bi-emoji-smile"></i>
                                </button>
                                <div class="emoji-picker d-none" id="emojiPicker"></div>
                            </div>

                            <!-- Send -->
                            <button class="btn btn-primary btn-sm px-3" id="sendBtn"
                                    title="Send (Enter)">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </div>

                        <!-- Attach preview -->
                        <div class="attach-preview-row" id="attachPreview"></div>
                    </div>
                </div><!-- /activeChat -->

            </div><!-- /chat-panel -->
        </div>
    </div>
</div>

<script>
window.CHAT_CONFIG = {
    userId: <?= $currentUserId ?>,
    appUrl: <?= json_encode(APP_URL) ?>,
    csrf:   <?= json_encode(csrfToken()) ?>,
    page:   'inbox'
};
</script>
<script src="<?= APP_URL ?>/assets/js/chat.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
