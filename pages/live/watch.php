<?php
/**
 * pages/live/watch.php — Watch a Live Stream
 */
require_once __DIR__ . '/../../includes/middleware.php';

$db       = getDB();
$streamId = (int)($_GET['id'] ?? 0);

if (!$streamId) {
    redirect('/pages/live/index.php');
}

try {
    $stmt = $db->prepare(
        'SELECT ls.*, u.first_name, u.last_name, u.avatar_url, u.id AS uid
         FROM live_streams ls
         LEFT JOIN users u ON u.id = ls.streamer_id
         WHERE ls.id = ?'
    );
    $stmt->execute([$streamId]);
    $stream = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stream = null;
}

if (!$stream) {
    redirect('/pages/live/index.php');
}

// Get pinned product
try {
    $ppStmt = $db->prepare(
        'SELECT sp.*, p.name AS product_name, p.price, p.sale_price, p.thumbnail_url
         FROM stream_products sp
         LEFT JOIN products p ON p.id = sp.product_id
         WHERE sp.stream_id = ? AND sp.is_pinned = 1
         LIMIT 1'
    );
    $ppStmt->execute([$streamId]);
    $pinnedProduct = $ppStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pinnedProduct = null;
}

// Get chat history (last 50 messages)
try {
    $chatStmt = $db->prepare(
        'SELECT sc.message, sc.type, sc.created_at, sc.is_highlighted,
                u.first_name, u.last_name, u.avatar_url
         FROM stream_chat sc
         LEFT JOIN users u ON u.id = sc.user_id
         WHERE sc.stream_id = ? AND sc.is_deleted = 0
         ORDER BY sc.created_at ASC
         LIMIT 50'
    );
    $chatStmt->execute([$streamId]);
    $chatMessages = $chatStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $chatMessages = [];
}

// Log viewer join
if (isLoggedIn()) {
    try {
        $db->prepare(
            'INSERT INTO stream_viewers (stream_id, user_id, joined_at) VALUES (?, ?, NOW())'
        )->execute([$streamId, $_SESSION['user_id']]);
    } catch (PDOException $e) { /* ignore */ }
}

$pageTitle = e($stream['title']) . ' — Live';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-3">
    <div class="row g-3">
        <!-- Video Player Area -->
        <div class="col-lg-8">
            <!-- Video Player -->
            <div class="bg-black rounded-3 position-relative mb-3" style="padding-top:56.25%">
                <div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center text-white" id="videoContainer">
                    <?php if ($stream['status'] === 'live'): ?>
                        <!-- WebRTC Player -->
                        <video id="remoteVideo" class="w-100 h-100 rounded-3" autoplay playsinline style="object-fit:contain;background:#000"></video>
                        <div id="noStreamMsg" class="text-center" style="display:none">
                            <i class="bi bi-broadcast fs-1 text-danger mb-2 d-block"></i>
                            <p>Connecting to stream...</p>
                        </div>
                    <?php elseif ($stream['status'] === 'ended' && $stream['is_vod_available']): ?>
                        <video src="<?= e($stream['vod_url']) ?>" class="w-100 h-100 rounded-3" controls style="object-fit:contain;background:#000"></video>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <i class="bi bi-camera-video-off fs-1 mb-3 d-block text-muted"></i>
                            <p class="lead">Stream is not currently live.</p>
                            <p class="text-muted">Status: <?= ucfirst($stream['status']) ?></p>
                            <a href="<?= APP_URL ?>/pages/live/index.php" class="btn btn-outline-light mt-2">Browse Live Streams</a>
                        </div>
                    <?php endif; ?>

                    <!-- Overlays -->
                    <?php if ($stream['status'] === 'live'): ?>
                    <div class="position-absolute top-0 start-0 p-2">
                        <span class="badge bg-danger"><i class="bi bi-circle-fill"></i> LIVE</span>
                    </div>
                    <div class="position-absolute top-0 end-0 p-2">
                        <span class="badge bg-dark" id="viewerCount">
                            <i class="bi bi-eye"></i> <span id="viewerCountNum"><?= (int)$stream['peak_viewers'] ?></span>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stream Info -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h1 class="h5 fw-bold mb-2"><?= e($stream['title']) ?></h1>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($stream['avatar_url']): ?>
                                <img src="<?= e($stream['avatar_url']) ?>" class="rounded-circle" width="36" height="36" alt="">
                            <?php else: ?>
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white" style="width:36px;height:36px">
                                    <?= strtoupper(substr($stream['first_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold small"><?= e($stream['first_name'] . ' ' . $stream['last_name']) ?></div>
                            </div>
                        </div>
                        <?php if (isLoggedIn()): ?>
                        <form action="<?= APP_URL ?>/api/live.php" method="POST" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="follow_streamer">
                            <input type="hidden" name="streamer_id" value="<?= (int)$stream['uid'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-person-plus"></i> Follow
                            </button>
                        </form>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-secondary" onclick="navigator.share?.({title:'<?= e($stream['title']) ?>',url:location.href})">
                            <i class="bi bi-share"></i> Share
                        </button>
                    </div>
                    <?php if ($stream['description']): ?>
                        <p class="text-muted small mb-0"><?= e($stream['description']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pinned Product -->
            <?php if ($pinnedProduct): ?>
            <div class="card border-warning border-2 shadow-sm">
                <div class="card-header bg-warning text-dark fw-semibold">
                    <i class="bi bi-pin-fill"></i> Featured Product
                </div>
                <div class="card-body">
                    <div class="d-flex gap-3 align-items-center">
                        <?php if ($pinnedProduct['thumbnail_url']): ?>
                            <img src="<?= e($pinnedProduct['thumbnail_url']) ?>" width="80" height="80" style="object-fit:cover;border-radius:8px" alt="">
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1"><?= e($pinnedProduct['product_name']) ?></h6>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($pinnedProduct['special_price']): ?>
                                    <span class="text-danger fw-bold fs-5"><?= money($pinnedProduct['special_price']) ?></span>
                                    <span class="text-muted text-decoration-line-through"><?= money($pinnedProduct['price']) ?></span>
                                <?php else: ?>
                                    <span class="fw-bold fs-5"><?= money($pinnedProduct['sale_price'] ?: $pinnedProduct['price']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex flex-column gap-2">
                            <form action="<?= APP_URL ?>/api/cart.php" method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?= (int)$pinnedProduct['product_id'] ?>">
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-cart-plus"></i> Add to Cart
                                </button>
                            </form>
                            <a href="<?= APP_URL ?>/pages/product/detail.php?id=<?= (int)$pinnedProduct['product_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Live Chat Sidebar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100 d-flex flex-column">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-chat-dots"></i> Live Chat</span>
                    <?php if ($stream['status'] === 'live'): ?>
                        <span class="badge bg-danger"><i class="bi bi-circle-fill"></i> Live</span>
                    <?php endif; ?>
                </div>

                <!-- Chat Messages -->
                <div id="chatMessages" class="p-3 flex-grow-1 overflow-auto" style="height:400px">
                    <?php foreach ($chatMessages as $msg): ?>
                    <div class="mb-2 <?= $msg['is_highlighted'] ? 'bg-warning-subtle rounded p-1' : '' ?>">
                        <strong class="small"><?= e($msg['first_name'] . ' ' . $msg['last_name']) ?></strong>
                        <?php if ($msg['type'] === 'reaction'): ?>
                            <span class="fs-5"><?= e($msg['message']) ?></span>
                        <?php elseif ($msg['type'] === 'question'): ?>
                            <span class="badge bg-info text-dark">Q</span>
                            <span class="small"><?= e($msg['message']) ?></span>
                        <?php else: ?>
                            <span class="small text-muted"><?= e($msg['message']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reaction Bar -->
                <div class="px-3 py-2 border-top d-flex gap-2">
                    <?php foreach (['❤️', '🔥', '👏', '😮', '🎉'] as $emoji): ?>
                        <button class="btn btn-sm btn-light reaction-btn" data-emoji="<?= $emoji ?>"
                                data-stream="<?= $streamId ?>"
                                <?= !isLoggedIn() ? 'disabled title="Login to react"' : '' ?>>
                            <?= $emoji ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Chat Input -->
                <?php if (isLoggedIn() && $stream['status'] === 'live'): ?>
                <div class="p-2 border-top">
                    <div class="input-group">
                        <input type="text" id="chatInput" class="form-control form-control-sm" placeholder="Say something...">
                        <button class="btn btn-sm btn-primary" id="sendChat" data-stream="<?= $streamId ?>">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                </div>
                <?php elseif (!isLoggedIn()): ?>
                <div class="p-3 border-top text-center">
                    <a href="<?= APP_URL ?>/pages/auth/login.php" class="btn btn-sm btn-outline-primary">Login to chat</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/live-stream.js"></script>
<script>
    const STREAM_ID  = <?= $streamId ?>;
    const IS_LIVE    = <?= $stream['status'] === 'live' ? 'true' : 'false' ?>;
    const IS_VIEWER  = true;
    const IS_LOGGED_IN = <?= isLoggedIn() ? 'true' : 'false' ?>;
    const API_BASE   = '<?= APP_URL ?>/api';
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
