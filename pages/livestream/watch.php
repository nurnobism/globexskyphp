<?php
/**
 * pages/livestream/watch.php — Watch a Livestream
 */
require_once __DIR__ . '/../../includes/middleware.php';

$streamId = (int)($_GET['stream_id'] ?? 0);
if (!$streamId) {
    header('Location: /pages/livestream/index.php');
    exit;
}

$db   = getDB();
$stmt = $db->prepare(
    "SELECT ls.*, u.name seller_name, u.company_name, u.avatar
     FROM livestreams ls
     LEFT JOIN users u ON u.id = ls.seller_id
     WHERE ls.id = ? LIMIT 1"
);
$stmt->execute([$streamId]);
$stream = $stmt->fetch();
if (!$stream) {
    header('Location: /pages/livestream/index.php');
    exit;
}

// Increment viewer count for live streams
if ($stream['status'] === 'live') {
    $db->prepare('UPDATE livestreams SET viewer_count = viewer_count + 1 WHERE id = ?')->execute([$streamId]);
}

// Featured products
$pStmt = $db->prepare(
    "SELECT p.id, p.name, p.slug, p.price, p.thumbnail, p.short_desc, p.currency
     FROM livestream_products lp
     JOIN products p ON p.id = lp.product_id
     WHERE lp.stream_id = ? ORDER BY lp.sort_order ASC"
);
$pStmt->execute([$streamId]);
$products = $pStmt->fetchAll();

// Recent chat (last 50 messages)
$cStmt = $db->prepare(
    "SELECT lc.id, lc.message, lc.created_at, u.name username
     FROM livestream_chat lc
     LEFT JOIN users u ON u.id = lc.user_id
     WHERE lc.stream_id = ? ORDER BY lc.id ASC LIMIT 50"
);
$cStmt->execute([$streamId]);
$chatMessages = $cStmt->fetchAll();
$lastMsgId = !empty($chatMessages) ? (int)end($chatMessages)['id'] : 0;

$pageTitle = e($stream['title']);
include __DIR__ . '/../../includes/header.php';
?>

<style>
    #video-frame { background:#0d1117; min-height:380px; border-radius:.5rem; }
    #chat-box { height:400px; overflow-y:auto; }
    .chat-msg { border-bottom:1px solid #f0f0f0; }
    .chat-msg:last-child { border:0; }
    .product-stream-card:hover { box-shadow:0 4px 16px rgba(255,107,53,.25); }
</style>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">

        <!-- ── Left: Video + Products ───────────────────────────────────────── -->
        <div class="col-lg-8">

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="/pages/livestream/index.php">Live Streams</a></li>
                    <li class="breadcrumb-item active"><?= e($stream['title']) ?></li>
                </ol>
            </nav>

            <!-- Video Player -->
            <div id="video-frame" class="d-flex align-items-center justify-content-center position-relative mb-3">
                <?php if (!empty($stream['stream_url'])): ?>
                    <iframe src="<?= e($stream['stream_url']) ?>"
                            class="w-100 rounded" style="min-height:380px;border:0"
                            allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
                <?php elseif ($stream['status'] === 'upcoming'): ?>
                    <div class="text-center text-white p-4">
                        <i class="bi bi-clock display-3 mb-3 d-block" style="color:#FF6B35"></i>
                        <h5>Stream starts <?= date('M j \a\t g:i A', strtotime($stream['scheduled_at'])) ?></h5>
                        <p class="text-secondary small">Refresh the page when it goes live.</p>
                    </div>
                <?php else: ?>
                    <div class="text-center text-white p-4">
                        <i class="bi bi-camera-video-off display-3 mb-3 d-block text-secondary"></i>
                        <p class="text-secondary">No stream available.</p>
                    </div>
                <?php endif; ?>

                <?php if ($stream['status'] === 'live'): ?>
                    <span class="badge bg-danger position-absolute top-0 start-0 m-2">
                        <i class="bi bi-circle-fill me-1" style="font-size:.4rem"></i>LIVE &nbsp;
                        <i class="bi bi-eye me-1"></i><?= number_format((int)$stream['viewer_count']) ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Stream Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:48px;height:48px;background:#1B2A4A!important">
                            <i class="bi bi-building text-white"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-1 fw-bold"><?= e($stream['title']) ?></h5>
                            <p class="mb-1 text-muted small">
                                <strong><?= e($stream['company_name'] ?: $stream['seller_name']) ?></strong>
                                &nbsp;·&nbsp;
                                <i class="bi bi-clock me-1"></i><?= date('M j, Y · g:i A', strtotime($stream['scheduled_at'])) ?>
                                <?php if (!empty($stream['category'])): ?>
                                    &nbsp;·&nbsp;<span class="badge bg-light text-dark border"><?= e($stream['category']) ?></span>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($stream['description'])): ?>
                                <p class="text-muted small mb-0"><?= nl2br(e($stream['description'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Featured Products -->
            <?php if ($products): ?>
            <h6 class="fw-bold mb-3" style="color:#1B2A4A">
                <i class="bi bi-bag-heart me-2" style="color:#FF6B35"></i>Featured Products
            </h6>
            <div class="row g-3">
                <?php foreach ($products as $p): ?>
                <div class="col-6 col-md-4 col-xl-3">
                    <div class="card border-0 shadow-sm h-100 product-stream-card">
                        <div class="bg-light" style="height:120px;overflow:hidden">
                            <?php if (!empty($p['thumbnail'])): ?>
                                <img src="<?= e($p['thumbnail']) ?>" class="w-100 h-100 object-fit-cover" alt="">
                            <?php else: ?>
                                <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                                    <i class="bi bi-box-seam text-muted" style="font-size:2rem"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-2">
                            <p class="small fw-semibold mb-1 text-truncate"><?= e($p['name']) ?></p>
                            <p class="small fw-bold mb-2" style="color:#FF6B35">
                                <?= formatMoney((float)$p['price'], $p['currency'] ?? 'USD') ?>
                            </p>
                            <a href="/pages/product/detail.php?slug=<?= e($p['slug']) ?>"
                               class="btn btn-sm w-100 text-white" style="background:#1B2A4A;font-size:.75rem">
                                View Product
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Right: Chat ──────────────────────────────────────────────────── -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100" style="min-height:520px">
                <div class="card-header text-white fw-semibold" style="background:#1B2A4A">
                    <i class="bi bi-chat-dots me-2"></i>Live Chat
                </div>

                <!-- Messages -->
                <div id="chat-box" class="card-body p-2">
                    <?php if (empty($chatMessages)): ?>
                        <p class="text-muted small text-center mt-4" id="empty-chat">Be the first to say hi! 👋</p>
                    <?php else: ?>
                        <?php foreach ($chatMessages as $msg): ?>
                        <div class="chat-msg py-2 px-1">
                            <span class="fw-semibold small" style="color:#FF6B35"><?= e($msg['username'] ?? 'Guest') ?></span>
                            <span class="text-muted" style="font-size:.7rem"> · <?= date('g:i A', strtotime($msg['created_at'])) ?></span>
                            <p class="mb-0 small"><?= e($msg['message']) ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Input -->
                <div class="card-footer p-2">
                    <?php if (isLoggedIn()): ?>
                    <form id="chat-form" class="d-flex gap-2">
                        <?= csrfField() ?>
                        <input type="hidden" name="stream_id" value="<?= $streamId ?>">
                        <input type="hidden" name="action" value="chat">
                        <input type="text" id="chat-input" name="message" class="form-control form-control-sm"
                               placeholder="Say something…" maxlength="500" autocomplete="off" required>
                        <button type="submit" class="btn btn-sm text-white px-3" style="background:#FF6B35;white-space:nowrap">
                            <i class="bi bi-send"></i>
                        </button>
                    </form>
                    <?php else: ?>
                    <p class="text-muted small text-center mb-0">
                        <a href="/pages/auth/login.php">Log in</a> to join the chat
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /row -->
</div>

<script>
(function () {
    'use strict';
    let lastId = <?= $lastMsgId ?>;
    const streamId = <?= $streamId ?>;
    const isLive   = <?= json_encode($stream['status'] === 'live') ?>;
    const chatBox  = document.getElementById('chat-box');
    const chatForm = document.getElementById('chat-form');

    function appendMessage(msg) {
        const empty = document.getElementById('empty-chat');
        if (empty) empty.remove();

        const div = document.createElement('div');
        div.className = 'chat-msg py-2 px-1';
        div.innerHTML =
            `<span class="fw-semibold small" style="color:#FF6B35">${escHtml(msg.username || 'Guest')}</span>` +
            `<span class="text-muted" style="font-size:.7rem"> · ${formatTime(msg.created_at)}</span>` +
            `<p class="mb-0 small">${escHtml(msg.message)}</p>`;
        chatBox.appendChild(div);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function formatTime(ts) {
        const d = new Date(ts.replace(' ', 'T'));
        return d.toLocaleTimeString([], {hour:'numeric',minute:'2-digit'});
    }

    function pollChat() {
        fetch(`/api/livestream.php?action=chat&stream_id=${streamId}&after=${lastId}`)
            .then(r => r.json())
            .then(data => {
                (data.messages || []).forEach(msg => {
                    appendMessage(msg);
                    lastId = Math.max(lastId, msg.id);
                });
            })
            .catch(() => {});
    }

    // Poll every 3 s for live streams, every 10 s otherwise
    if (isLive) {
        setInterval(pollChat, 3000);
    } else {
        setInterval(pollChat, 10000);
    }

    // Scroll to bottom on load
    chatBox.scrollTop = chatBox.scrollHeight;

    // Submit chat message
    if (chatForm) {
        chatForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const data = new FormData(chatForm);
            fetch('/api/livestream.php', { method: 'POST', body: data })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        document.getElementById('chat-input').value = '';
                        pollChat();
                    }
                })
                .catch(() => {});
        });
    }
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
