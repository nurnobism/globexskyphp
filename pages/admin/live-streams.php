<?php
/**
 * pages/admin/live-streams.php — Admin Live Stream Management
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['admin', 'super_admin']);

$db = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $sub = $_POST['_sub'] ?? '';
    $id  = (int)($_POST['stream_id'] ?? 0);

    if ($sub === 'end' && $id) {
        $db->prepare("UPDATE live_streams SET status = 'ended', ended_at = NOW() WHERE id = ?")->execute([$id]);
        flashMessage('success', 'Stream ended.');
    } elseif ($sub === 'feature' && $id) {
        $db->prepare("UPDATE live_streams SET is_featured = 1 - is_featured WHERE id = ?")->execute([$id]);
    } elseif ($sub === 'cancel' && $id) {
        $db->prepare("UPDATE live_streams SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        flashMessage('warning', 'Stream cancelled.');
    }
    redirect('/pages/admin/live-streams.php');
}

try {
    $liveCount   = (int)$db->query('SELECT COUNT(*) FROM live_streams WHERE status = "live"')->fetchColumn();
    $totalToday  = (int)$db->query("SELECT COUNT(*) FROM live_streams WHERE DATE(started_at) = CURDATE()")->fetchColumn();
    $totalViewers = (int)$db->query('SELECT COALESCE(SUM(total_viewers), 0) FROM live_streams WHERE status = "live"')->fetchColumn();
    $totalRevenue = (float)$db->query('SELECT COALESCE(SUM(revenue_during_stream), 0) FROM live_streams')->fetchColumn();

    $streamsStmt = $db->query(
        'SELECT ls.*, u.first_name, u.last_name, u.email
         FROM live_streams ls
         LEFT JOIN users u ON u.id = ls.streamer_id
         ORDER BY ls.created_at DESC
         LIMIT 100'
    );
    $streams = $streamsStmt->fetchAll(PDO::FETCH_ASSOC);
    $dbOk    = true;
} catch (PDOException $e) {
    $dbOk = false;
    $liveCount = $totalToday = $totalViewers = $totalRevenue = 0;
    $streams = [];
}

$pageTitle = 'Live Stream Management';
require_once __DIR__ . '/../../includes/header.php';

$statusColors = ['live' => 'danger', 'scheduled' => 'warning', 'ended' => 'secondary', 'cancelled' => 'dark'];
?>

<div class="container-fluid py-4">
    <h1 class="h3 fw-bold mb-4"><i class="bi bi-broadcast text-danger"></i> Live Stream Management</h1>

    <?php if (!$dbOk): ?>
        <div class="alert alert-warning">Live stream tables not initialized. Run schema_v4.sql first.</div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-1 fw-bold text-danger"><?= $liveCount ?></div>
                <div class="text-muted small">Streams Live Now</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-1 fw-bold text-primary"><?= $totalToday ?></div>
                <div class="text-muted small">Streams Today</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-1 fw-bold text-info"><?= number_format($totalViewers) ?></div>
                <div class="text-muted small">Viewers (Live Now)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-1 fw-bold text-success"><?= money($totalRevenue) ?></div>
                <div class="text-muted small">Total Revenue Generated</div>
            </div>
        </div>
    </div>

    <!-- Streams Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-camera-video"></i> All Streams</span>
            <div class="d-flex gap-2">
                <a href="?status=live" class="btn btn-sm btn-outline-danger">Live</a>
                <a href="?status=scheduled" class="btn btn-sm btn-outline-warning">Scheduled</a>
                <a href="?status=ended" class="btn btn-sm btn-outline-secondary">Ended</a>
                <a href="?" class="btn btn-sm btn-outline-primary">All</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Streamer</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Viewers</th>
                        <th>Revenue</th>
                        <th>Started</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $filterStatus = $_GET['status'] ?? '';
                    $filtered = $filterStatus ? array_filter($streams, fn($s) => $s['status'] === $filterStatus) : $streams;
                    if ($filtered):
                        foreach ($filtered as $stream):
                            $sc = $statusColors[$stream['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td>
                            <a href="<?= APP_URL ?>/pages/live/watch.php?id=<?= $stream['id'] ?>" class="text-decoration-none fw-semibold">
                                <?= e(mb_strimwidth($stream['title'], 0, 40, '...')) ?>
                            </a>
                            <?php if ($stream['is_featured']): ?><span class="badge bg-warning text-dark ms-1">★ Featured</span><?php endif; ?>
                        </td>
                        <td class="small"><?= e($stream['first_name'] . ' ' . $stream['last_name']) ?></td>
                        <td><span class="badge bg-light text-dark border small"><?= e($stream['category']) ?></span></td>
                        <td><span class="badge bg-<?= $sc ?>"><?= ucfirst($stream['status']) ?></span></td>
                        <td><?= number_format((int)$stream['peak_viewers']) ?></td>
                        <td><?= money((float)$stream['revenue_during_stream']) ?></td>
                        <td class="small text-muted"><?= $stream['started_at'] ? date('M d, H:i', strtotime($stream['started_at'])) : (date('M d, H:i', strtotime($stream['scheduled_at'] ?? 'now'))) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if ($stream['status'] === 'live'): ?>
                                <form method="POST" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="_sub" value="end">
                                    <input type="hidden" name="stream_id" value="<?= $stream['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-danger btn-sm" onclick="return confirm('End this stream?')">End</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="_sub" value="feature">
                                    <input type="hidden" name="stream_id" value="<?= $stream['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-warning btn-sm">
                                        <?= $stream['is_featured'] ? 'Unfeature' : 'Feature' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php
                        endforeach;
                    else:
                    ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No streams found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
