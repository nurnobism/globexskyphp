<?php
/**
 * pages/live/vod.php — Video On Demand (Past Streams)
 */
require_once __DIR__ . '/../../includes/middleware.php';

$db       = getDB();
$streamId = (int)($_GET['id'] ?? 0);

// Single VOD view
if ($streamId) {
    try {
        $stmt = $db->prepare(
            'SELECT ls.*, u.first_name, u.last_name, u.avatar_url
             FROM live_streams ls
             LEFT JOIN users u ON u.id = ls.streamer_id
             WHERE ls.id = ? AND ls.status = "ended"'
        );
        $stmt->execute([$streamId]);
        $stream = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stream = null;
    }
    if (!$stream) {
        redirect('/pages/live/vod.php');
    }

    // Get stream products
    try {
        $prodStmt = $db->prepare(
            'SELECT sp.*, p.name, p.price, p.thumbnail_url
             FROM stream_products sp
             LEFT JOIN products p ON p.id = sp.product_id
             WHERE sp.stream_id = ? ORDER BY sp.sort_order'
        );
        $prodStmt->execute([$streamId]);
        $streamProducts = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $streamProducts = [];
    }

    $pageTitle = e($stream['title']) . ' — Replay';
    require_once __DIR__ . '/../../includes/header.php';
    ?>

    <div class="container py-4">
        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Video Player -->
                <div class="bg-black rounded-3 mb-3 position-relative" style="padding-top:56.25%">
                    <?php if ($stream['vod_url']): ?>
                        <video src="<?= e($stream['vod_url']) ?>"
                               class="position-absolute top-0 start-0 w-100 h-100 rounded-3"
                               controls style="background:#000"></video>
                    <?php else: ?>
                        <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center text-white">
                            <div class="text-center">
                                <i class="bi bi-camera-video-off fs-1 d-block mb-2 text-muted"></i>
                                <p>Recording not available</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Stream info -->
                <h1 class="h5 fw-bold"><?= e($stream['title']) ?></h1>
                <div class="d-flex align-items-center gap-3 mb-3 text-muted small">
                    <span><i class="bi bi-person"></i> <?= e($stream['first_name'] . ' ' . $stream['last_name']) ?></span>
                    <span><i class="bi bi-eye"></i> <?= number_format((int)$stream['peak_viewers']) ?> peak viewers</span>
                    <span><i class="bi bi-clock"></i> <?= gmdate('H:i:s', (int)$stream['duration_seconds']) ?></span>
                    <?php if ($stream['ended_at']): ?>
                        <span><i class="bi bi-calendar"></i> <?= date('M d, Y', strtotime($stream['ended_at'])) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($stream['description']): ?>
                    <p class="text-muted"><?= e($stream['description']) ?></p>
                <?php endif; ?>

                <!-- Stats -->
                <div class="row g-3 mb-3">
                    <div class="col-3 text-center">
                        <div class="fw-bold fs-5"><?= number_format((int)$stream['total_viewers']) ?></div>
                        <div class="text-muted small">Total Viewers</div>
                    </div>
                    <div class="col-3 text-center">
                        <div class="fw-bold fs-5"><?= number_format((int)$stream['peak_viewers']) ?></div>
                        <div class="text-muted small">Peak Viewers</div>
                    </div>
                    <div class="col-3 text-center">
                        <div class="fw-bold fs-5"><?= number_format((int)$stream['total_messages']) ?></div>
                        <div class="text-muted small">Chat Messages</div>
                    </div>
                    <div class="col-3 text-center">
                        <div class="fw-bold fs-5"><?= number_format((int)$stream['orders_during_stream']) ?></div>
                        <div class="text-muted small">Orders</div>
                    </div>
                </div>
            </div>

            <!-- Products sidebar -->
            <div class="col-lg-4">
                <?php if ($streamProducts): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold">
                        <i class="bi bi-box-seam"></i> Products in This Stream
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($streamProducts as $sp): ?>
                        <div class="d-flex align-items-center gap-3 p-3 border-bottom">
                            <?php if ($sp['thumbnail_url']): ?>
                                <img src="<?= e($sp['thumbnail_url']) ?>" width="60" height="60" style="object-fit:cover;border-radius:4px" alt="">
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small"><?= e($sp['name']) ?></div>
                                <div class="text-primary small fw-bold"><?= money($sp['price']) ?></div>
                            </div>
                            <a href="<?= APP_URL ?>/pages/product/detail.php?id=<?= (int)$sp['product_id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// VOD list page
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

try {
    $countStmt = $db->query("SELECT COUNT(*) FROM live_streams WHERE status = 'ended' AND is_vod_available = 1");
    $total     = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare(
        'SELECT ls.id, ls.title, ls.category, ls.thumbnail_url, ls.duration_seconds,
                ls.peak_viewers, ls.total_viewers, ls.ended_at,
                u.first_name, u.last_name
         FROM live_streams ls
         LEFT JOIN users u ON u.id = ls.streamer_id
         WHERE ls.status = "ended" AND ls.is_vod_available = 1
         ORDER BY ls.ended_at DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->execute([$perPage, $offset]);
    $vods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dbOk = true;
} catch (PDOException $e) {
    $vods = [];
    $total = 0;
    $dbOk  = false;
}

$totalPages = (int)ceil($total / $perPage);
$pageTitle  = 'Past Streams — Replays';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <h1 class="h3 fw-bold mb-4"><i class="bi bi-camera-video-fill text-secondary"></i> Past Streams</h1>

    <?php if (!$dbOk): ?>
        <div class="alert alert-warning">Live stream tables not initialized.</div>
    <?php elseif (!$vods): ?>
        <div class="alert alert-info">No recordings available yet.</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($vods as $vod): ?>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="position-relative">
                        <img src="<?= $vod['thumbnail_url'] ? e($vod['thumbnail_url']) : 'https://via.placeholder.com/400x225/2c2c2c/ffffff?text=VOD' ?>"
                             class="card-img-top" style="height:150px;object-fit:cover" alt="">
                        <span class="position-absolute bottom-0 end-0 badge bg-dark m-2">
                            <?= gmdate('H:i:s', (int)$vod['duration_seconds']) ?>
                        </span>
                    </div>
                    <div class="card-body p-2">
                        <h6 class="fw-semibold" style="font-size:0.85rem;line-height:1.3">
                            <a href="?id=<?= $vod['id'] ?>" class="text-decoration-none text-dark">
                                <?= e(mb_strimwidth($vod['title'], 0, 50, '...')) ?>
                            </a>
                        </h6>
                        <div class="text-muted small">
                            <?= e($vod['first_name'] . ' ' . $vod['last_name']) ?>
                        </div>
                        <div class="text-muted small">
                            <i class="bi bi-eye"></i> <?= number_format((int)$vod['peak_viewers']) ?> peak
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
