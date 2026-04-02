<?php
/**
 * pages/livestream/index.php — Browse Live Streams
 */
require_once __DIR__ . '/../../includes/middleware.php';

$db     = getDB();
$status = in_array($_GET['status'] ?? '', ['live','upcoming','past']) ? $_GET['status'] : '';

$where  = ['1=1'];
$params = [];
if ($status) {
    $where[]  = 'ls.status = ?';
    $params[] = $status;
}

$stmt = $db->prepare(
    "SELECT ls.*, u.name seller_name, u.company_name
     FROM livestreams ls
     LEFT JOIN users u ON u.id = ls.seller_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY FIELD(ls.status,'live','upcoming','past'), ls.scheduled_at ASC
     LIMIT 48"
);
$stmt->execute($params);
$streams = $stmt->fetchAll();

$pageTitle = 'Live Streams';
include __DIR__ . '/../../includes/header.php';
?>

<style>
    .stream-card:hover { transform: translateY(-4px); transition: .2s; }
    .badge-live  { background: #dc3545; animation: pulse 1.5s infinite; }
    .badge-upcoming { background: #FF6B35; }
    .badge-past  { background: #6c757d; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.6} }
    .thumb-placeholder { background: linear-gradient(135deg,#1B2A4A 0%,#2d4a7a 100%); height:180px; }
    .filter-btn.active { background:#FF6B35; border-color:#FF6B35; color:#fff; }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1" style="color:#1B2A4A">
                <i class="bi bi-broadcast me-2" style="color:#FF6B35"></i>Live Streams
            </h2>
            <p class="text-muted mb-0">Watch suppliers showcase products live — ask questions in real time</p>
        </div>
        <?php if (isLoggedIn()): ?>
            <a href="/pages/livestream/schedule.php" class="btn btn-sm fw-semibold text-white"
               style="background:#FF6B35;border-color:#FF6B35">
                <i class="bi bi-camera-video me-1"></i>Go Live
            </a>
        <?php endif; ?>
    </div>

    <!-- Filter Tabs -->
    <div class="mb-4">
        <div class="btn-group" role="group">
            <a href="?status=" class="btn btn-outline-secondary btn-sm filter-btn <?= !$status ? 'active' : '' ?>">
                All
            </a>
            <a href="?status=live" class="btn btn-outline-secondary btn-sm filter-btn <?= $status==='live' ? 'active' : '' ?>">
                <i class="bi bi-circle-fill me-1 text-danger" style="font-size:.5rem"></i>Live Now
            </a>
            <a href="?status=upcoming" class="btn btn-outline-secondary btn-sm filter-btn <?= $status==='upcoming' ? 'active' : '' ?>">
                <i class="bi bi-calendar-event me-1"></i>Upcoming
            </a>
            <a href="?status=past" class="btn btn-outline-secondary btn-sm filter-btn <?= $status==='past' ? 'active' : '' ?>">
                <i class="bi bi-clock-history me-1"></i>Past
            </a>
        </div>
    </div>

    <!-- Stream Grid -->
    <?php if (empty($streams)): ?>
        <div class="text-center py-5">
            <i class="bi bi-camera-video-off display-3 text-muted"></i>
            <p class="mt-3 text-muted">No streams found. <?= !isLoggedIn() ? '<a href="/pages/auth/login.php">Log in</a> to schedule one.' : '' ?></p>
        </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($streams as $s): ?>
        <div class="col-sm-6 col-lg-4 col-xl-3">
            <div class="card h-100 border-0 shadow-sm stream-card overflow-hidden">
                <!-- Thumbnail -->
                <div class="thumb-placeholder position-relative d-flex align-items-center justify-content-center">
                    <?php if (!empty($s['thumbnail'])): ?>
                        <img src="<?= e($s['thumbnail']) ?>" class="w-100 h-100 object-fit-cover position-absolute top-0 start-0" alt="">
                    <?php else: ?>
                        <i class="bi bi-play-circle-fill text-white opacity-50" style="font-size:3rem"></i>
                    <?php endif; ?>
                    <!-- Status Badge -->
                    <span class="badge position-absolute top-0 start-0 m-2 badge-<?= e($s['status']) ?>">
                        <?php if ($s['status'] === 'live'): ?>
                            <i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>LIVE
                        <?php elseif ($s['status'] === 'upcoming'): ?>
                            <i class="bi bi-calendar-check me-1"></i>UPCOMING
                        <?php else: ?>
                            <i class="bi bi-archive me-1"></i>PAST
                        <?php endif; ?>
                    </span>
                    <!-- Viewer Count -->
                    <?php if ($s['status'] === 'live'): ?>
                    <span class="badge bg-dark bg-opacity-75 position-absolute top-0 end-0 m-2">
                        <i class="bi bi-eye me-1"></i><?= number_format((int)$s['viewer_count']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <!-- Body -->
                <div class="card-body d-flex flex-column">
                    <h6 class="fw-bold mb-1 text-truncate" title="<?= e($s['title']) ?>">
                        <?= e($s['title']) ?>
                    </h6>
                    <p class="text-muted small mb-1">
                        <i class="bi bi-building me-1"></i><?= e($s['company_name'] ?: $s['seller_name']) ?>
                    </p>
                    <p class="text-muted small mb-3">
                        <i class="bi bi-clock me-1"></i>
                        <?= date('M j, Y · g:i A', strtotime($s['scheduled_at'])) ?>
                    </p>
                    <?php if (!empty($s['category'])): ?>
                        <span class="badge bg-light text-dark border mb-2 align-self-start">
                            <?= e($s['category']) ?>
                        </span>
                    <?php endif; ?>
                    <div class="mt-auto">
                        <?php if ($s['status'] === 'live'): ?>
                            <a href="/pages/livestream/watch.php?stream_id=<?= (int)$s['id'] ?>"
                               class="btn btn-sm w-100 text-white fw-semibold" style="background:#dc3545;border-color:#dc3545">
                                <i class="bi bi-broadcast me-1"></i>Watch Now
                            </a>
                        <?php elseif ($s['status'] === 'upcoming'): ?>
                            <a href="/pages/livestream/watch.php?stream_id=<?= (int)$s['id'] ?>"
                               class="btn btn-sm w-100 fw-semibold" style="background:#FF6B35;border-color:#FF6B35;color:#fff">
                                <i class="bi bi-bell me-1"></i>Set Reminder
                            </a>
                        <?php else: ?>
                            <a href="/pages/livestream/watch.php?stream_id=<?= (int)$s['id'] ?>"
                               class="btn btn-sm btn-outline-secondary w-100">
                                <i class="bi bi-play-circle me-1"></i>Watch Replay
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
