<?php
/**
 * pages/livestream/schedule.php — Schedule a Livestream
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();

// Load supplier's active products for the feature picker
$pStmt = $db->prepare(
    "SELECT id, name, thumbnail FROM products WHERE supplier_id = (
         SELECT id FROM suppliers WHERE user_id = ? LIMIT 1
     ) AND status = 'active' ORDER BY name ASC LIMIT 100"
);
$pStmt->execute([$_SESSION['user_id']]);
$myProducts = $pStmt->fetchAll();

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $scheduledAt = trim($_POST['scheduled_at'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $streamUrl   = trim($_POST['stream_url'] ?? '');
        $productIds  = array_filter(array_map('intval', $_POST['product_ids'] ?? []));

        if (!$title)       $errors[] = 'Stream title is required.';
        if (!$scheduledAt) $errors[] = 'Scheduled date/time is required.';
        if ($scheduledAt && strtotime($scheduledAt) < time()) {
            $errors[] = 'Scheduled time must be in the future.';
        }

        if (!$errors) {
            $ins = $db->prepare(
                "INSERT INTO livestreams (seller_id, title, description, scheduled_at, category, stream_url, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'upcoming', NOW())"
            );
            $ins->execute([$_SESSION['user_id'], $title, $description, $scheduledAt, $category, $streamUrl]);
            $newId = (int)$db->lastInsertId();

            if ($productIds) {
                $pip = $db->prepare('INSERT IGNORE INTO livestream_products (stream_id, product_id) VALUES (?, ?)');
                foreach ($productIds as $pid) {
                    $pip->execute([$newId, $pid]);
                }
            }

            $success = true;
        }
    }
}

$categories = ['Electronics','Machinery','Apparel','Home & Garden','Food & Beverage',
                'Chemicals','Automotive','Health & Beauty','Office Supplies','Other'];

$pageTitle = 'Schedule a Livestream';
include __DIR__ . '/../../includes/header.php';
?>

<style>
    .section-title { color:#1B2A4A; font-weight:700; }
    .product-pick-item { cursor:pointer; border:2px solid transparent; transition:.15s; }
    .product-pick-item:hover { border-color:#FF6B35; }
    .product-pick-item.selected { border-color:#FF6B35; background:#fff5f0; }
</style>

<div class="container py-5" style="max-width:820px">
    <h2 class="section-title mb-1"><i class="bi bi-camera-video me-2" style="color:#FF6B35"></i>Schedule a Livestream</h2>
    <p class="text-muted mb-4">Set up your broadcast and let buyers tune in live.</p>

    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>Your livestream has been scheduled! <a href="/pages/livestream/index.php">View all streams →</a></div>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="schedule-form" novalidate>
        <?= csrfField() ?>

        <!-- Basic Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header fw-semibold" style="background:#1B2A4A;color:#fff">
                <i class="bi bi-info-circle me-2"></i>Stream Details
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Stream Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="160"
                           placeholder="e.g. Summer Collection Launch 2025"
                           value="<?= e($_POST['title'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="3"
                              placeholder="What will you showcase? Any special offers?"><?= e($_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Scheduled Date &amp; Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="scheduled_at" class="form-control"
                               min="<?= date('Y-m-d\TH:i') ?>"
                               value="<?= e($_POST['scheduled_at'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Category</label>
                        <select name="category" class="form-select">
                            <option value="">— Select category —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat) ?>" <?= (($_POST['category'] ?? '') === $cat) ? 'selected' : '' ?>>
                                    <?= e($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stream URL -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header fw-semibold" style="background:#1B2A4A;color:#fff">
                <i class="bi bi-link-45deg me-2"></i>Stream Source (Optional)
            </div>
            <div class="card-body">
                <label class="form-label fw-semibold">Embed URL</label>
                <input type="url" name="stream_url" class="form-control"
                       placeholder="https://www.youtube.com/embed/VIDEO_ID or RTMP embed URL"
                       value="<?= e($_POST['stream_url'] ?? '') ?>">
                <div class="form-text">Paste a YouTube Live, Vimeo, or custom RTMP embed URL. Leave blank to add later.</div>
            </div>
        </div>

        <!-- Featured Products -->
        <?php if ($myProducts): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header fw-semibold" style="background:#1B2A4A;color:#fff">
                <i class="bi bi-bag-heart me-2"></i>Feature Products in Stream
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Select products to display to viewers during the stream.</p>
                <div class="row g-2" id="product-picker">
                    <?php foreach ($myProducts as $p):
                        $checked = in_array($p['id'], (array)($_POST['product_ids'] ?? []));
                    ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <label class="product-pick-item d-block card p-2 mb-0 rounded <?= $checked ? 'selected' : '' ?>">
                            <input type="checkbox" name="product_ids[]" value="<?= (int)$p['id'] ?>"
                                   class="d-none product-cb" <?= $checked ? 'checked' : '' ?>>
                            <div class="bg-light rounded mb-1" style="height:70px;overflow:hidden">
                                <?php if (!empty($p['thumbnail'])): ?>
                                    <img src="<?= e($p['thumbnail']) ?>" class="w-100 h-100 object-fit-cover" alt="">
                                <?php else: ?>
                                    <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                                        <i class="bi bi-box-seam text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <p class="small mb-0 text-truncate fw-semibold"><?= e($p['name']) ?></p>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="d-flex gap-2 justify-content-end">
            <a href="/pages/livestream/index.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn text-white fw-semibold px-4" style="background:#FF6B35;border-color:#FF6B35">
                <i class="bi bi-calendar-check me-2"></i>Schedule Stream
            </button>
        </div>
    </form>
</div>

<script>
document.querySelectorAll('.product-pick-item').forEach(function (label) {
    label.addEventListener('click', function () {
        const cb = label.querySelector('.product-cb');
        label.classList.toggle('selected', cb.checked);
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
