<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db = getDB();
$currentUserId = (int)$_SESSION['user_id'];

$roomId = (int)get('room_id', 0);

if ($roomId <= 0) {
    header('Location: ' . APP_URL . '/pages/messages/index.php');
    exit;
}

// Verify current user is a participant in this room
$stmt = $db->prepare(
    'SELECT r.*, rp.user_id AS participant_id
       FROM chat_rooms r
       JOIN chat_room_participants rp ON rp.room_id = r.id
      WHERE r.id = ? AND rp.user_id = ?
      LIMIT 1'
);
$stmt->execute([$roomId, $currentUserId]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    header('Location: ' . APP_URL . '/pages/messages/index.php');
    exit;
}

$pageTitle = 'Conversation';
include __DIR__ . '/../../includes/header.php';
?>

<style>
.chat-layout {
    height: calc(100vh - 180px);
    min-height: 500px;
}
.sidebar-panel {
    border-right: 1px solid #dee2e6;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}
.room-list {
    overflow-y: auto;
    flex: 1;
}
.room-item {
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.15s;
}
.room-item:hover, .room-item.active {
    background: #e8f0fe;
}
.room-item .avatar-circle {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #6c757d;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1rem;
    flex-shrink: 0;
}
.chat-panel {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    background: #f8f9fa;
}
.msg-bubble {
    max-width: 65%;
    word-break: break-word;
}
.msg-bubble.sent {
    background: #0d6efd;
    color: #fff;
    border-radius: 18px 18px 4px 18px;
}
.msg-bubble.received {
    background: #fff;
    color: #212529;
    border-radius: 18px 18px 18px 4px;
    border: 1px solid #dee2e6;
}
.chat-input-area {
    border-top: 1px solid #dee2e6;
    padding: 0.75rem;
    background: #fff;
}
.online-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #198754;
    display: inline-block;
    border: 2px solid #fff;
}
.typing-indicator span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #6c757d;
    display: inline-block;
    animation: bounce 1.3s infinite ease-in-out;
}
.typing-indicator span:nth-child(2) { animation-delay: 0.15s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.3s; }
@keyframes bounce {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-5px); }
}
</style>

<div class="container-fluid py-3">
    <div class="card shadow-sm border-0 chat-layout">
        <div class="row g-0 h-100">

            <!-- Sidebar -->
            <div class="col-md-4 col-lg-3 sidebar-panel h-100">
                <div class="p-3 border-bottom bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0">
                            <a href="<?= APP_URL ?>/pages/messages/index.php"
                               class="text-decoration-none text-dark">
                                <i class="bi bi-arrow-left me-1"></i>Messages
                            </a>
                        </h6>
                        <a href="<?= APP_URL ?>/pages/messages/compose.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-pencil-square"></i>
                        </a>
                    </div>
                    <input type="text" id="roomSearch" class="form-control form-control-sm"
                           placeholder="Search conversations…">
                    <div class="d-flex gap-1 mt-2">
                        <button class="btn btn-sm btn-primary filter-btn active" data-filter="all">All</button>
                        <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="unread">Unread</button>
                        <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="orders">Orders</button>
                    </div>
                </div>
                <div class="room-list" id="roomList">
                    <div class="text-center py-4 text-muted" id="roomListLoading">
                        <div class="spinner-border spinner-border-sm"></div>
                        <p class="small mt-2">Loading…</p>
                    </div>
                </div>
            </div>

            <!-- Chat panel -->
            <div class="col-md-8 col-lg-9 chat-panel h-100" id="chatPanel">
                <div id="emptyState" class="d-none d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                    <i class="bi bi-chat-square-dots display-1 text-primary opacity-25"></i>
                    <h5 class="mt-3">Select a conversation</h5>
                </div>

                <div id="activeChat" class="d-flex flex-column h-100">
                    <!-- Chat header -->
                    <div class="p-3 border-bottom bg-white d-flex align-items-center gap-3">
                        <div id="chatHeaderAvatar"
                             style="width:40px;height:40px;border-radius:50%;background:#6c757d;
                                    color:#fff;display:flex;align-items:center;justify-content:center;
                                    font-weight:600;flex-shrink:0;">
                            ?
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold" id="chatHeaderName">Loading…</div>
                            <small class="text-muted" id="chatHeaderStatus">
                                <span class="online-dot me-1"></span>Online
                            </small>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-secondary" title="Search in chat">
                                <i class="bi bi-search"></i>
                            </button>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" id="viewProfileLink">
                                        <i class="bi bi-person me-2"></i>View Profile
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" id="deleteChatLink">
                                        <i class="bi bi-trash me-2"></i>Delete Chat
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Messages area -->
                    <div class="chat-messages" id="chatMessages">
                        <div class="text-center text-muted small py-3" id="messagesLoading">
                            <div class="spinner-border spinner-border-sm"></div> Loading messages…
                        </div>
                    </div>

                    <!-- Typing indicator -->
                    <div class="px-3 pb-1" id="typingIndicator" style="display:none;">
                        <div class="d-flex align-items-center gap-2">
                            <div class="typing-indicator d-flex gap-1 p-2 bg-white border rounded-pill">
                                <span></span><span></span><span></span>
                            </div>
                            <small class="text-muted" id="typingName">Someone is typing…</small>
                        </div>
                    </div>

                    <!-- Input area -->
                    <div class="chat-input-area">
                        <div class="d-flex align-items-end gap-2">
                            <button class="btn btn-outline-secondary btn-sm" title="Attach file"
                                    onclick="document.getElementById('fileAttach').click()">
                                <i class="bi bi-paperclip"></i>
                            </button>
                            <input type="file" id="fileAttach" class="d-none" multiple>
                            <textarea id="messageInput" class="form-control form-control-sm"
                                      rows="1" placeholder="Type a message…"
                                      style="resize:none;max-height:120px;overflow-y:auto;"></textarea>
                            <button class="btn btn-outline-secondary btn-sm" id="emojiBtn" title="Emoji">
                                <i class="bi bi-emoji-smile"></i>
                            </button>
                            <button class="btn btn-primary btn-sm px-3" id="sendBtn" title="Send (Enter)">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </div>
                        <div class="d-flex flex-wrap gap-1 mt-1" id="attachPreview"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
window.CHAT_CONFIG = {
    userId:     <?= $currentUserId ?>,
    appUrl:     <?= json_encode(APP_URL) ?>,
    csrf:       <?= json_encode(csrfToken()) ?>,
    activeRoom: <?= $roomId ?>
};
</script>
<script src="<?= APP_URL ?>/assets/js/chat.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
