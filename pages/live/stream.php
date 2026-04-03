<?php
/**
 * pages/live/stream.php — Go Live (Streamer Page)
 * Only for verified suppliers.
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db     = getDB();
$userId = $_SESSION['user_id'];

// Get supplier's products for stream setup
try {
    $suppStmt = $db->prepare('SELECT id FROM suppliers WHERE user_id = ?');
    $suppStmt->execute([$userId]);
    $supplierId = $suppStmt->fetchColumn();

    $products = [];
    if ($supplierId) {
        $prodStmt = $db->prepare(
            'SELECT id, name, price, thumbnail_url FROM products WHERE supplier_id = ? AND status = "active" ORDER BY name LIMIT 50'
        );
        $prodStmt->execute([$supplierId]);
        $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $products = [];
    $supplierId = null;
}

// Get active stream for this supplier
try {
    $activeStmt = $db->prepare(
        'SELECT * FROM live_streams WHERE streamer_id = ? AND status = "live" LIMIT 1'
    );
    $activeStmt->execute([$userId]);
    $activeStream = $activeStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $activeStream = null;
}

$pageTitle = 'Go Live';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <h1 class="h3 fw-bold mb-4"><i class="bi bi-broadcast-pin text-danger"></i> Go Live</h1>

    <?php if ($activeStream): ?>
    <!-- Active Stream Controls -->
    <div class="alert alert-danger border-0">
        <strong>🔴 You are currently LIVE!</strong> Stream: "<?= e($activeStream['title']) ?>"
        <a href="?stream_id=<?= $activeStream['id'] ?>" class="alert-link ms-2">Manage Stream →</a>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Pre-stream Setup -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-sliders"></i> Stream Setup
                </div>
                <div class="card-body">
                    <form id="startStreamForm" action="<?= APP_URL ?>/api/live.php" method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="start">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Stream Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. New Summer Collection Showcase" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Tell viewers what this stream is about..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="category" class="form-select">
                                <option value="product_showcase">Product Showcase</option>
                                <option value="unboxing">Unboxing</option>
                                <option value="tutorial">Tutorial</option>
                                <option value="flash_sale">Flash Sale</option>
                                <option value="qa">Q&amp;A</option>
                                <option value="general">General</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Products to Showcase</label>
                            <div style="max-height:200px;overflow-y:auto" class="border rounded p-2">
                                <?php if ($products): ?>
                                    <?php foreach ($products as $p): ?>
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" name="product_ids[]"
                                               id="prod_<?= $p['id'] ?>" value="<?= $p['id'] ?>">
                                        <label class="form-check-label small" for="prod_<?= $p['id'] ?>">
                                            <?= e($p['name']) ?> — <?= money($p['price']) ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted small mb-0">No products available. <a href="<?= APP_URL ?>/pages/supplier/product-add.php">Add products first</a>.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="button" id="startStreamBtn" class="btn btn-danger btn-lg">
                                <i class="bi bi-broadcast"></i> Start Streaming
                            </button>
                        </div>
                    </form>

                    <hr>

                    <h6 class="fw-semibold">Schedule for Later</h6>
                    <form action="<?= APP_URL ?>/api/live.php" method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="schedule">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <input type="text" name="title" class="form-control form-control-sm" placeholder="Stream title" required>
                            </div>
                            <div class="col-md-6">
                                <input type="datetime-local" name="scheduled_at" class="form-control form-control-sm" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-primary mt-2">
                            <i class="bi bi-calendar-plus"></i> Schedule Stream
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Camera Preview -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-camera-video"></i> Camera Preview
                </div>
                <div class="card-body p-0">
                    <div class="bg-black position-relative" style="padding-top:56.25%">
                        <video id="localVideo" class="position-absolute top-0 start-0 w-100 h-100 rounded-bottom"
                               autoplay muted playsinline style="object-fit:cover"></video>
                        <div id="noCameraMsg" class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center text-white">
                            <div class="text-center">
                                <i class="bi bi-camera-video-off fs-1 d-block mb-2"></i>
                                <p>Camera not started</p>
                                <button id="enableCamera" class="btn btn-outline-light btn-sm">
                                    <i class="bi bi-camera-video"></i> Enable Camera
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 border-top d-flex gap-2 justify-content-center">
                        <button id="toggleMic" class="btn btn-outline-secondary btn-sm" disabled>
                            <i class="bi bi-mic"></i> Mic On
                        </button>
                        <button id="toggleCam" class="btn btn-outline-secondary btn-sm" disabled>
                            <i class="bi bi-camera-video"></i> Cam On
                        </button>
                        <button id="toggleScreen" class="btn btn-outline-secondary btn-sm" disabled>
                            <i class="bi bi-display"></i> Screen Share
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stream Status (shown when live) -->
            <div id="streamStatusCard" class="card border-0 shadow-sm mt-3 d-none">
                <div class="card-header bg-danger text-white fw-semibold">
                    <i class="bi bi-broadcast"></i> Live Now
                </div>
                <div class="card-body">
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="fw-bold fs-4" id="liveViewerCount">0</div>
                            <div class="text-muted small">Viewers</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold fs-4" id="liveDuration">00:00</div>
                            <div class="text-muted small">Duration</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold fs-4" id="liveChatCount">0</div>
                            <div class="text-muted small">Messages</div>
                        </div>
                    </div>
                    <div class="d-grid mt-3">
                        <button id="endStreamBtn" class="btn btn-outline-danger"
                                onclick="return confirm('End your live stream?')">
                            <i class="bi bi-stop-circle"></i> End Stream
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/live-stream.js"></script>
<script>
    const IS_STREAMER = true;
    const IS_VIEWER   = false;
    const API_BASE    = '<?= APP_URL ?>/api';
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
