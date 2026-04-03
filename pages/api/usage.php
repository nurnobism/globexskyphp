<?php
/**
 * pages/api/usage.php — API Usage Analytics
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db     = getDB();
$userId = $_SESSION['user_id'];

try {
    // Daily usage last 30 days
    $dailyStmt = $db->prepare(
        "SELECT DATE(arl.created_at) AS day, COUNT(*) AS requests,
                SUM(CASE WHEN arl.response_code < 400 THEN 1 ELSE 0 END) AS success,
                AVG(arl.response_time_ms) AS avg_ms
         FROM api_request_logs arl
         JOIN api_keys ak ON ak.id = arl.api_key_id
         WHERE ak.user_id = ? AND arl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY DATE(arl.created_at)
         ORDER BY day ASC"
    );
    $dailyStmt->execute([$userId]);
    $daily = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Top endpoints
    $topStmt = $db->prepare(
        "SELECT arl.endpoint, COUNT(*) AS requests,
                SUM(CASE WHEN arl.response_code < 400 THEN 1 ELSE 0 END) AS success
         FROM api_request_logs arl
         JOIN api_keys ak ON ak.id = arl.api_key_id
         WHERE ak.user_id = ? AND arl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY arl.endpoint
         ORDER BY requests DESC
         LIMIT 10"
    );
    $topStmt->execute([$userId]);
    $topEndpoints = $topStmt->fetchAll(PDO::FETCH_ASSOC);

    // Status code distribution
    $scStmt = $db->prepare(
        "SELECT
            SUM(CASE WHEN arl.response_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) AS s2xx,
            SUM(CASE WHEN arl.response_code BETWEEN 400 AND 499 THEN 1 ELSE 0 END) AS s4xx,
            SUM(CASE WHEN arl.response_code >= 500 THEN 1 ELSE 0 END) AS s5xx,
            COUNT(*) AS total
         FROM api_request_logs arl
         JOIN api_keys ak ON ak.id = arl.api_key_id
         WHERE ak.user_id = ? AND arl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $scStmt->execute([$userId]);
    $statusDist = $scStmt->fetch(PDO::FETCH_ASSOC);

    // Rate limit usage
    $rlStmt = $db->prepare(
        'SELECT COALESCE(SUM(requests_today), 0) AS today,
                COALESCE(SUM(requests_month), 0) AS month,
                COALESCE(SUM(rate_limit_per_day), 0) AS limit_day
         FROM api_keys WHERE user_id = ? AND is_active = 1'
    );
    $rlStmt->execute([$userId]);
    $rlData = $rlStmt->fetch(PDO::FETCH_ASSOC);

    $dbOk = true;
} catch (PDOException $e) {
    $dbOk = false;
    $daily = $topEndpoints = [];
    $statusDist = ['s2xx' => 0, 's4xx' => 0, 's5xx' => 0, 'total' => 0];
    $rlData = ['today' => 0, 'month' => 0, 'limit_day' => 100];
}

$pageTitle = 'API Usage Analytics';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-2 col-md-3">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white"><i class="bi bi-code-slash"></i> API Platform</div>
                <div class="list-group list-group-flush">
                    <a href="<?= APP_URL ?>/pages/api/index.php" class="list-group-item list-group-item-action"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    <a href="<?= APP_URL ?>/pages/api/keys.php" class="list-group-item list-group-item-action"><i class="bi bi-key"></i> API Keys</a>
                    <a href="<?= APP_URL ?>/pages/api/docs.php" class="list-group-item list-group-item-action"><i class="bi bi-book"></i> Documentation</a>
                    <a href="<?= APP_URL ?>/pages/api/logs.php" class="list-group-item list-group-item-action"><i class="bi bi-list-ul"></i> Request Logs</a>
                    <a href="<?= APP_URL ?>/pages/api/usage.php" class="list-group-item list-group-item-action active"><i class="bi bi-bar-chart"></i> Usage Analytics</a>
                    <a href="<?= APP_URL ?>/pages/api/webhooks.php" class="list-group-item list-group-item-action"><i class="bi bi-arrow-repeat"></i> Webhooks</a>
                </div>
            </div>
        </div>

        <div class="col-lg-10 col-md-9">
            <h1 class="h3 fw-bold mb-4"><i class="bi bi-bar-chart text-primary"></i> Usage Analytics</h1>

            <?php if (!$dbOk): ?>
                <div class="alert alert-warning">API tables not initialized.</div>
            <?php else: ?>

            <!-- Rate limit progress -->
            <?php
            $limitDay = (int)$rlData['limit_day'];
            $usedDay  = (int)$rlData['today'];
            $pct      = $limitDay > 0 ? min(100, round($usedDay / $limitDay * 100)) : 0;
            $barColor = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success');
            ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-speedometer"></i> Daily Rate Limit Usage</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-1">
                        <span><?= number_format($usedDay) ?> used</span>
                        <span><?= $limitDay > 0 ? number_format($limitDay) . ' limit' : 'Unlimited' ?></span>
                    </div>
                    <div class="progress" style="height:12px">
                        <div class="progress-bar bg-<?= $barColor ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                    <?php if ($pct >= 80): ?>
                        <div class="alert alert-warning mt-2 mb-0 p-2 small">
                            <i class="bi bi-exclamation-triangle"></i> You've used <?= $pct ?>% of your daily limit.
                            <a href="<?= APP_URL ?>/pages/supplier/plan-upgrade.php">Upgrade your plan</a> to increase limits.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <!-- Status distribution -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white fw-semibold"><i class="bi bi-pie-chart"></i> Status Codes (30d)</div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-success">2xx Success</span><strong><?= number_format((int)$statusDist['s2xx']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-warning">4xx Client Errors</span><strong><?= number_format((int)$statusDist['s4xx']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-danger">5xx Server Errors</span><strong><?= number_format((int)$statusDist['s5xx']) ?></strong>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span>Total</span><strong><?= number_format((int)$statusDist['total']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top endpoints -->
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white fw-semibold"><i class="bi bi-list-ol"></i> Top Endpoints (30d)</div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light"><tr><th>Endpoint</th><th>Requests</th><th>Success Rate</th></tr></thead>
                                <tbody>
                                    <?php if ($topEndpoints): ?>
                                        <?php foreach ($topEndpoints as $ep): ?>
                                        <tr>
                                            <td class="small font-monospace"><?= e($ep['endpoint']) ?></td>
                                            <td><?= number_format((int)$ep['requests']) ?></td>
                                            <td>
                                                <?php $sr = $ep['requests'] > 0 ? round($ep['success'] / $ep['requests'] * 100, 1) : 0; ?>
                                                <span class="badge bg-<?= $sr >= 95 ? 'success' : ($sr >= 80 ? 'warning' : 'danger') ?>">
                                                    <?= $sr ?>%
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center text-muted py-3">No data yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily usage chart data (simple text table) -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-graph-up"></i> Daily Requests (Last 30 Days)</div>
                <?php if ($daily): ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Requests</th><th>Success</th><th>Avg Response</th></tr></thead>
                        <tbody>
                            <?php foreach ($daily as $d): ?>
                            <tr>
                                <td><?= e($d['day']) ?></td>
                                <td><?= number_format((int)$d['requests']) ?></td>
                                <td><?= number_format((int)$d['success']) ?></td>
                                <td><?= round((float)$d['avg_ms']) ?>ms</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="card-body text-center text-muted py-4">No requests in the last 30 days.</div>
                <?php endif; ?>
            </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
