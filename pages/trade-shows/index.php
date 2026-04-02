<?php
/**
 * pages/trade-shows/index.php — Trade Show Directory
 */
require_once __DIR__ . '/../../includes/middleware.php';

$db   = getDB();
$type = in_array($_GET['type'] ?? '', ['virtual','physical']) ? $_GET['type'] : '';
$when = in_array($_GET['when'] ?? '', ['this_week','this_month','future']) ? $_GET['when'] : '';

$where  = ['ts.status != "cancelled"'];
$params = [];

if ($type) {
    $where[]  = 'ts.type = ?';
    $params[] = $type;
}
switch ($when) {
    case 'this_week':
        $where[] = 'ts.start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
        break;
    case 'this_month':
        $where[] = 'MONTH(ts.start_date) = MONTH(CURDATE()) AND YEAR(ts.start_date) = YEAR(CURDATE())';
        break;
    case 'future':
        $where[] = 'ts.start_date >= CURDATE()';
        break;
}

$stmt = $db->prepare(
    "SELECT ts.*, COUNT(b.id) AS booth_count
     FROM trade_shows ts
     LEFT JOIN trade_show_booths b ON b.show_id = ts.id
     WHERE " . implode(' AND ', $where) . "
     GROUP BY ts.id
     ORDER BY ts.start_date ASC
     LIMIT 48"
);
$stmt->execute($params);
$shows = $stmt->fetchAll();

$pageTitle = 'Trade Shows & Events';
include __DIR__ . '/../../includes/header.php';
?>

<style>
    .show-card { transition:.2s; }
    .show-card:hover { transform:translateY(-4px); box-shadow:0 8px 24px rgba(0,0,0,.12)!important; }
    .badge-virtual  { background:#0ea5e9; }
    .badge-physical { background:#10b981; }
    .filter-btn.active { background:#FF6B35; border-color:#FF6B35; color:#fff; }
    .hero-strip { background:linear-gradient(135deg,#1B2A4A 0%,#2d4a7a 100%); }
</style>

<!-- Hero Strip -->
<div class="hero-strip text-white py-5 mb-5">
    <div class="container text-center">
        <h1 class="fw-bold mb-2">
            <i class="bi bi-buildings me-2" style="color:#FF6B35"></i>Trade Shows &amp; Virtual Events
        </h1>
        <p class="lead mb-0 opacity-75">Connect with global suppliers at live &amp; virtual exhibitions</p>
    </div>
</div>

<div class="container pb-5">

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4 p-3">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label fw-semibold small mb-1">Event Type</label><br>
                <div class="btn-group btn-group-sm" role="group">
                    <a href="?type=&when=<?= e($when) ?>"
                       class="btn btn-outline-secondary filter-btn <?= !$type ? 'active' : '' ?>">All</a>
                    <a href="?type=virtual&when=<?= e($when) ?>"
                       class="btn btn-outline-secondary filter-btn <?= $type==='virtual' ? 'active' : '' ?>">
                       <i class="bi bi-laptop me-1"></i>Virtual
                    </a>
                    <a href="?type=physical&when=<?= e($when) ?>"
                       class="btn btn-outline-secondary filter-btn <?= $type==='physical' ? 'active' : '' ?>">
                       <i class="bi bi-geo-alt me-1"></i>Physical
                    </a>
                </div>
            </div>
            <div>
                <label class="form-label fw-semibold small mb-1">When</label><br>
                <div class="btn-group btn-group-sm" role="group">
                    <a href="?type=<?= e($type) ?>&when="
                       class="btn btn-outline-secondary filter-btn <?= !$when ? 'active' : '' ?>">Anytime</a>
                    <a href="?type=<?= e($type) ?>&when=this_week"
                       class="btn btn-outline-secondary filter-btn <?= $when==='this_week' ? 'active' : '' ?>">This Week</a>
                    <a href="?type=<?= e($type) ?>&when=this_month"
                       class="btn btn-outline-secondary filter-btn <?= $when==='this_month' ? 'active' : '' ?>">This Month</a>
                    <a href="?type=<?= e($type) ?>&when=future"
                       class="btn btn-outline-secondary filter-btn <?= $when==='future' ? 'active' : '' ?>">Upcoming</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Count -->
    <p class="text-muted small mb-3">
        Showing <strong><?= count($shows) ?></strong> event<?= count($shows) !== 1 ? 's' : '' ?>
    </p>

    <!-- Grid -->
    <?php if (empty($shows)): ?>
        <div class="text-center py-5">
            <i class="bi bi-calendar-x display-3 text-muted"></i>
            <p class="mt-3 text-muted">No events match your filters. Try removing some filters above.</p>
        </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($shows as $show): ?>
        <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="card h-100 border-0 shadow-sm show-card overflow-hidden">

                <!-- Banner placeholder -->
                <div class="position-relative" style="height:140px;background:linear-gradient(135deg,#1B2A4A,#2d4a7a)">
                    <?php if (!empty($show['banner'])): ?>
                        <img src="<?= e($show['banner']) ?>" class="w-100 h-100 object-fit-cover" alt="">
                    <?php else: ?>
                        <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                            <i class="bi bi-buildings text-white opacity-50" style="font-size:3rem"></i>
                        </div>
                    <?php endif; ?>
                    <span class="badge position-absolute top-0 start-0 m-2 badge-<?= e($show['type'] ?? 'virtual') ?>">
                        <?php if (($show['type'] ?? '') === 'physical'): ?>
                            <i class="bi bi-geo-alt-fill me-1"></i>Physical
                        <?php else: ?>
                            <i class="bi bi-laptop me-1"></i>Virtual
                        <?php endif; ?>
                    </span>
                </div>

                <div class="card-body d-flex flex-column">
                    <?php if (!empty($show['category'])): ?>
                        <span class="badge bg-light text-dark border mb-2 align-self-start small">
                            <?= e($show['category']) ?>
                        </span>
                    <?php endif; ?>
                    <h6 class="fw-bold mb-1"><?= e($show['name']) ?></h6>

                    <p class="text-muted small mb-1">
                        <i class="bi bi-calendar3 me-1"></i>
                        <?= date('M j', strtotime($show['start_date'])) ?>
                        <?php if (!empty($show['end_date']) && $show['end_date'] !== $show['start_date']): ?>
                            – <?= date('M j, Y', strtotime($show['end_date'])) ?>
                        <?php else: ?>
                            <?= date(', Y', strtotime($show['start_date'])) ?>
                        <?php endif; ?>
                    </p>

                    <p class="text-muted small mb-1">
                        <i class="bi bi-geo-alt me-1"></i>
                        <?= e(!empty($show['location']) ? $show['location'] : 'Online') ?>
                    </p>

                    <p class="text-muted small mb-3">
                        <i class="bi bi-shop me-1"></i>
                        <?= number_format((int)$show['booth_count']) ?> booth<?= $show['booth_count'] != 1 ? 's' : '' ?>
                    </p>

                    <?php if (!empty($show['short_desc'])): ?>
                        <p class="text-muted small mb-3 flex-grow-1"><?= e(mb_strimwidth($show['short_desc'], 0, 90, '…')) ?></p>
                    <?php endif; ?>

                    <a href="/pages/trade-shows/booth.php?show_id=<?= (int)$show['id'] ?>"
                       class="btn btn-sm fw-semibold text-white mt-auto"
                       style="background:#FF6B35;border-color:#FF6B35">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Register / View
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
