<?php
/**
 * pages/api/index.php — API Developer Dashboard
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db     = getDB();
$userId = $_SESSION['user_id'];

try {
    // API key count
    $keysStmt = $db->prepare('SELECT COUNT(*) FROM api_keys WHERE user_id = ? AND is_active = 1');
    $keysStmt->execute([$userId]);
    $activeKeys = (int)$keysStmt->fetchColumn();

    // Requests today
    $todayStmt = $db->prepare(
        'SELECT COALESCE(SUM(requests_today), 0) FROM api_keys WHERE user_id = ? AND is_active = 1'
    );
    $todayStmt->execute([$userId]);
    $requestsToday = (int)$todayStmt->fetchColumn();

    // Requests this month
    $monthStmt = $db->prepare(
        'SELECT COALESCE(SUM(requests_month), 0) FROM api_keys WHERE user_id = ? AND is_active = 1'
    );
    $monthStmt->execute([$userId]);
    $requestsMonth = (int)$monthStmt->fetchColumn();

    // Success rate
    $srStmt = $db->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN response_code < 400 THEN 1 ELSE 0 END) AS success
         FROM api_request_logs arl
         JOIN api_keys ak ON ak.id = arl.api_key_id
         WHERE ak.user_id = ? AND arl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
    );
    $srStmt->execute([$userId]);
    $srRow       = $srStmt->fetch(PDO::FETCH_ASSOC);
    $successRate = $srRow['total'] > 0 ? round(($srRow['success'] / $srRow['total']) * 100, 1) : 100;

    // Avg response time
    $rtStmt = $db->prepare(
        'SELECT COALESCE(AVG(arl.response_time_ms), 0)
         FROM api_request_logs arl
         JOIN api_keys ak ON ak.id = arl.api_key_id
         WHERE ak.user_id = ? AND arl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
    );
    $rtStmt->execute([$userId]);
    $avgResponseMs = (int)$rtStmt->fetchColumn();

    // Recent keys
    $keysListStmt = $db->prepare(
        'SELECT id, name, key_prefix, environment, is_active, last_used_at, requests_today, created_at
         FROM api_keys WHERE user_id = ? ORDER BY created_at DESC LIMIT 5'
    );
    $keysListStmt->execute([$userId]);
    $recentKeys = $keysListStmt->fetchAll(PDO::FETCH_ASSOC);

    // Last 7 days usage chart data
    $chartStmt = $db->prepare(
        "SELECT DATE(arl.created_at) AS day, COUNT(*) AS requests
         FROM api_request_logs arl
         JOIN api_keys ak ON ak.id = arl.api_key_id
         WHERE ak.user_id = ? AND arl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY DATE(arl.created_at)
         ORDER BY day ASC"
    );
    $chartStmt->execute([$userId]);
    $chartData = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

    $dbOk = true;
} catch (PDOException $e) {
    $dbOk = false;
    $activeKeys = $requestsToday = $requestsMonth = 0;
    $successRate = 100;
    $avgResponseMs = 0;
    $recentKeys = [];
    $chartData  = [];
}

$pageTitle = 'API Dashboard';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-2 col-md-3">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white"><i class="bi bi-code-slash"></i> API Platform</div>
                <div class="list-group list-group-flush">
                    <a href="<?= APP_URL ?>/pages/api/index.php" class="list-group-item list-group-item-action active">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a href="<?= APP_URL ?>/pages/api/keys.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-key"></i> API Keys
                    </a>
                    <a href="<?= APP_URL ?>/pages/api/docs.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-book"></i> Documentation
                    </a>
                    <a href="<?= APP_URL ?>/pages/api/logs.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-list-ul"></i> Request Logs
                    </a>
                    <a href="<?= APP_URL ?>/pages/api/usage.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-bar-chart"></i> Usage Analytics
                    </a>
                    <a href="<?= APP_URL ?>/pages/api/webhooks.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-arrow-repeat"></i> Webhooks
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-10 col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 fw-bold mb-0"><i class="bi bi-speedometer2 text-primary"></i> API Dashboard</h1>
                <a href="<?= APP_URL ?>/pages/api/keys.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Create API Key
                </a>
            </div>

            <?php if (!$dbOk): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> API tables not yet initialized. Please run database migrations.
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center p-3">
                        <div class="text-primary fs-1 fw-bold"><?= $activeKeys ?></div>
                        <div class="text-muted small">Active API Keys</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center p-3">
                        <div class="text-success fs-1 fw-bold"><?= number_format($requestsToday) ?></div>
                        <div class="text-muted small">Requests Today</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center p-3">
                        <div class="text-info fs-1 fw-bold"><?= $successRate ?>%</div>
                        <div class="text-muted small">Success Rate (30d)</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center p-3">
                        <div class="text-warning fs-1 fw-bold"><?= $avgResponseMs ?>ms</div>
                        <div class="text-muted small">Avg Response Time</div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <!-- API Keys Summary -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-key"></i> Your API Keys</span>
                            <a href="<?= APP_URL ?>/pages/api/keys.php" class="btn btn-sm btn-outline-primary">Manage All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr><th>Name</th><th>Key Prefix</th><th>Env</th><th>Req Today</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php if ($recentKeys): ?>
                                        <?php foreach ($recentKeys as $key): ?>
                                        <tr>
                                            <td><?= e($key['name']) ?></td>
                                            <td><code class="small"><?= e($key['key_prefix']) ?></code></td>
                                            <td><span class="badge bg-<?= $key['environment'] === 'live' ? 'success' : 'secondary' ?>"><?= e($key['environment']) ?></span></td>
                                            <td><?= (int)$key['requests_today'] ?></td>
                                            <td>
                                                <?php if ($key['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">No API keys yet. <a href="<?= APP_URL ?>/pages/api/keys.php">Create one</a>.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Quick Start -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white fw-semibold"><i class="bi bi-rocket-takeoff"></i> Quick Start</div>
                        <div class="card-body small">
                            <p class="mb-2"><strong>Base URL:</strong></p>
                            <code class="d-block bg-light p-2 rounded mb-3"><?= APP_URL ?>/api/v1/gateway.php</code>

                            <p class="mb-2"><strong>Authentication:</strong></p>
                            <pre class="bg-dark text-success p-2 rounded small" style="font-size:0.75rem">X-API-Key: gsk_live_xxxxxxxx</pre>

                            <p class="mb-2"><strong>Example request:</strong></p>
                            <pre class="bg-dark text-success p-2 rounded small" style="font-size:0.75rem">curl "<?= APP_URL ?>/api/v1/gateway.php?resource=products&action=list" \
  -H "X-API-Key: YOUR_KEY"</pre>

                            <a href="<?= APP_URL ?>/pages/api/docs.php" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="bi bi-book"></i> Full Documentation
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
