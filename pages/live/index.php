<?php
/**
 * pages/live/index.php — Live Streams Hub
 */
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();

try {
    $liveStmt = $db->query(
        'SELECT ls.id, ls.title, ls.category, ls.thumbnail_url, ls.started_at,
                ls.peak_viewers, ls.total_viewers, ls.total_messages,
                u.first_name, u.last_name, u.avatar_url
         FROM live_streams ls
         LEFT JOIN users u ON u.id = ls.streamer_id
         WHERE ls.status = "live"
         ORDER BY ls.peak_viewers DESC
         LIMIT 12'
    );
    $liveStreams = $liveStmt->fetchAll(PDO::FETCH_ASSOC);

    $upcomingStmt = $db->query(
        'SELECT ls.id, ls.title, ls.category, ls.thumbnail_url, ls.scheduled_at,
                u.first_name, u.last_name
         FROM live_streams ls
         LEFT JOIN users u ON u.id = ls.streamer_id
         WHERE ls.status = "scheduled" AND ls.scheduled_at > NOW()
         ORDER BY ls.scheduled_at ASC
         LIMIT 6'
    );
    $upcoming = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);

    $vodStmt = $db->query(
        'SELECT ls.id, ls.title, ls.category, ls.thumbnail_url, ls.duration_seconds,
                ls.peak_viewers, ls.ended_at,
                u.first_name, u.last_name
         FROM live_streams ls
         LEFT JOIN users u ON u.id = ls.streamer_id
         WHERE ls.status = "ended" AND ls.is_vod_available = 1
         ORDER BY ls.ended_at DESC
         LIMIT 6'
    );
    $vods  = $vodStmt->fetchAll(PDO::FETCH_ASSOC);
    $dbOk  = true;
} catch (PDOException $e) {
    $liveStreams = $upcoming = $vods = [];
    $dbOk = false;
}

$categories = ['product_showcase' => 'Product Showcase', 'unboxing' => 'Unboxing', 'tutorial' => 'Tutorial', 'flash_sale' => 'Flash Sale', 'qa' => 'Q&A', 'general' => 'General'];

$pageTitle = 'Live Streams';
require_once __DIR__ . '/../../includes/header.php';

function categoryBadge(string $cat): string {
    $colors = ['product_showcase'=>'primary','unboxing'=>'info','tutorial'=>'success','flash_sale'=>'danger','qa'=>'warning','general'=>'secondary'];
    $labels = ['product_showcase'=>'Product Showcase','unboxing'=>'Unboxing','tutorial'=>'Tutorial','flash_sale'=>'Flash Sale','qa'=>'Q&A','general'=>'General'];
    $c = $colors[$cat] ?? 'secondary';
    $l = $labels[$cat] ?? $cat;
    return "<span class=\"badge bg-$c\">$l</span>";
}

function formatDuration(int $secs): string {
    $h = intdiv($secs, 3600);
    $m = intdiv($secs % 3600, 60);
    $s = $secs % 60;
    return $h ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
}
?>

<div class="container py-4">
    <!-- Hero -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold"><span class="text-danger">🔴</span> Live Streams</h1>
            <p class="text-muted mb-0">Watch live product showcases, unboxings, tutorials and more</p>
        </div>
        <?php if (isLoggedIn() && isSupplier()): ?>
            <a href="<?= APP_URL ?>/pages/live/stream.php" class="btn btn-danger">
                <i class="bi bi-broadcast"></i> Go Live
            </a>
        <?php endif; ?>
    </div>

    <!-- Category filter -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="?" class="btn btn-sm <?= !isset($_GET['cat']) ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
        <?php foreach ($categories as $key => $label): ?>
            <a href="?cat=<?= $key ?>" class="btn btn-sm <?= ($_GET['cat'] ?? '') === $key ? 'btn-primary' : 'btn-outline-primary' ?>">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$dbOk): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Live stream tables not yet initialized.</div>
    <?php endif; ?>

    <!-- Live Now -->
    <?php if ($liveStreams): ?>
    <section class="mb-5">
        <h2 class="h5 fw-bold mb-3"><span class="text-danger">●</span> Live Now (<?= count($liveStreams) ?>)</h2>
        <div class="row g-3">
            <?php foreach ($liveStreams as $stream): ?>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="position-relative">
                        <img src="<?= $stream['thumbnail_url'] ? e($stream['thumbnail_url']) : 'https://via.placeholder.com/400x225/1a1a2e/ffffff?text=LIVE' ?>"
                             class="card-img-top" alt="<?= e($stream['title']) ?>" style="height:160px;object-fit:cover">
                        <span class="position-absolute top-0 start-0 badge bg-danger m-2">🔴 LIVE</span>
                        <span class="position-absolute bottom-0 end-0 badge bg-dark m-2">
                            <i class="bi bi-eye"></i> <?= number_format((int)$stream['peak_viewers']) ?>
                        </span>
                    </div>
                    <div class="card-body p-2">
                        <div class="mb-1"><?= categoryBadge($stream['category']) ?></div>
                        <h6 class="mb-1 fw-semibold" style="font-size:0.85rem;line-height:1.3">
                            <a href="<?= APP_URL ?>/pages/live/watch.php?id=<?= $stream['id'] ?>" class="text-decoration-none text-dark">
                                <?= e(mb_strimwidth($stream['title'], 0, 50, '...')) ?>
                            </a>
                        </h6>
                        <div class="text-muted small">
                            <i class="bi bi-person"></i> <?= e($stream['first_name'] . ' ' . $stream['last_name']) ?>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent p-2">
                        <a href="<?= APP_URL ?>/pages/live/watch.php?id=<?= $stream['id'] ?>" class="btn btn-sm btn-danger w-100">
                            <i class="bi bi-play-fill"></i> Watch Live
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php else: ?>
    <div class="alert alert-info mb-4">
        <i class="bi bi-camera-video"></i> No streams are live right now.
        <?php if (isLoggedIn() && isSupplier()): ?>
            <a href="<?= APP_URL ?>/pages/live/stream.php" class="alert-link">Start the first one!</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Upcoming -->
    <?php if ($upcoming): ?>
    <section class="mb-5">
        <h2 class="h5 fw-bold mb-3"><i class="bi bi-calendar-event text-primary"></i> Upcoming Streams</h2>
        <div class="row g-3">
            <?php foreach ($upcoming as $stream): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="mb-2"><?= categoryBadge($stream['category']) ?></div>
                        <h6 class="fw-semibold"><?= e($stream['title']) ?></h6>
                        <p class="text-muted small mb-1">
                            <i class="bi bi-person"></i> <?= e($stream['first_name'] . ' ' . $stream['last_name']) ?>
                        </p>
                        <p class="text-primary small mb-0">
                            <i class="bi bi-clock"></i>
                            <?= date('M d, Y \a\t H:i', strtotime($stream['scheduled_at'])) ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Past Streams (VODs) -->
    <?php if ($vods): ?>
    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 fw-bold mb-0"><i class="bi bi-camera-video-fill text-secondary"></i> Past Streams</h2>
            <a href="<?= APP_URL ?>/pages/live/vod.php" class="btn btn-sm btn-outline-secondary">View All</a>
        </div>
        <div class="row g-3">
            <?php foreach ($vods as $vod): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="position-relative">
                        <img src="<?= $vod['thumbnail_url'] ? e($vod['thumbnail_url']) : 'https://via.placeholder.com/400x225/2c2c2c/ffffff?text=VOD' ?>"
                             class="card-img-top" alt="<?= e($vod['title']) ?>" style="height:140px;object-fit:cover">
                        <span class="position-absolute bottom-0 end-0 badge bg-dark m-2">
                            <?= formatDuration((int)$vod['duration_seconds']) ?>
                        </span>
                    </div>
                    <div class="card-body p-2">
                        <h6 class="mb-1" style="font-size:0.85rem">
                            <a href="<?= APP_URL ?>/pages/live/vod.php?id=<?= $vod['id'] ?>" class="text-decoration-none text-dark">
                                <?= e(mb_strimwidth($vod['title'], 0, 50, '...')) ?>
                            </a>
                        </h6>
                        <div class="text-muted small">
                            <?= e($vod['first_name'] . ' ' . $vod['last_name']) ?> ·
                            <?= number_format((int)$vod['peak_viewers']) ?> viewers
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
