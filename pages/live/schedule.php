<?php
/**
 * pages/live/schedule.php — Stream Schedule
 */
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();

try {
    $stmt = $db->query(
        'SELECT ls.id, ls.title, ls.category, ls.description, ls.scheduled_at, ls.thumbnail_url,
                u.first_name, u.last_name, u.avatar_url
         FROM live_streams ls
         LEFT JOIN users u ON u.id = ls.streamer_id
         WHERE ls.status = "scheduled" AND ls.scheduled_at > NOW()
         ORDER BY ls.scheduled_at ASC
         LIMIT 50'
    );
    $upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dbOk     = true;
} catch (PDOException $e) {
    $upcoming = [];
    $dbOk     = false;
}

$categories = ['product_showcase' => 'Product Showcase', 'unboxing' => 'Unboxing', 'tutorial' => 'Tutorial', 'flash_sale' => 'Flash Sale', 'qa' => 'Q&A', 'general' => 'General'];
$catColors  = ['product_showcase'=>'primary','unboxing'=>'info','tutorial'=>'success','flash_sale'=>'danger','qa'=>'warning','general'=>'secondary'];

$pageTitle = 'Stream Schedule';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold"><i class="bi bi-calendar-event text-primary"></i> Stream Schedule</h1>
            <p class="text-muted mb-0">Upcoming live streams — set a reminder</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/pages/live/index.php" class="btn btn-outline-secondary">
                <i class="bi bi-broadcast-pin"></i> Live Now
            </a>
            <?php if (isLoggedIn() && isSupplier()): ?>
            <a href="<?= APP_URL ?>/pages/live/stream.php" class="btn btn-danger">
                <i class="bi bi-calendar-plus"></i> Schedule Stream
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$dbOk): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Live stream tables not yet initialized.</div>
    <?php elseif (!$upcoming): ?>
        <div class="alert alert-info">
            <i class="bi bi-calendar-x"></i> No streams scheduled yet.
            <?php if (isLoggedIn() && isSupplier()): ?>
                <a href="<?= APP_URL ?>/pages/live/stream.php" class="alert-link">Schedule the first one!</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($upcoming as $stream): ?>
            <?php
            $dt  = new DateTime($stream['scheduled_at']);
            $now = new DateTime();
            $diff = $now->diff($dt);
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <?php if ($stream['thumbnail_url']): ?>
                        <img src="<?= e($stream['thumbnail_url']) ?>" class="card-img-top" style="height:160px;object-fit:cover" alt="">
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="mb-2">
                            <?php
                            $cat   = $stream['category'];
                            $color = $catColors[$cat] ?? 'secondary';
                            $label = $categories[$cat] ?? $cat;
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= e($label) ?></span>
                        </div>
                        <h5 class="card-title fw-bold"><?= e($stream['title']) ?></h5>
                        <?php if ($stream['description']): ?>
                            <p class="card-text text-muted small"><?= e(mb_strimwidth($stream['description'], 0, 100, '...')) ?></p>
                        <?php endif; ?>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <?php if ($stream['avatar_url']): ?>
                                <img src="<?= e($stream['avatar_url']) ?>" class="rounded-circle" width="28" height="28" alt="">
                            <?php endif; ?>
                            <small class="text-muted"><?= e($stream['first_name'] . ' ' . $stream['last_name']) ?></small>
                        </div>
                        <div class="bg-light rounded p-2 text-center">
                            <div class="text-primary fw-bold"><?= $dt->format('l, M d, Y') ?></div>
                            <div class="fs-5 fw-bold"><?= $dt->format('H:i') ?> UTC</div>
                            <div class="small text-muted">
                                <?php if ($diff->days > 0): ?>
                                    In <?= $diff->days ?> day<?= $diff->days > 1 ? 's' : '' ?>
                                <?php elseif ($diff->h > 0): ?>
                                    In <?= $diff->h ?> hour<?= $diff->h > 1 ? 's' : '' ?>
                                <?php else: ?>
                                    Starting soon!
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
