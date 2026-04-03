<?php
/**
 * pages/api/logs.php — API Request Logs
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db     = getDB();
$userId = $_SESSION['user_id'];

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$filterStatus   = $_GET['status'] ?? '';
$filterEndpoint = trim($_GET['endpoint'] ?? '');
$filterFrom     = $_GET['from'] ?? '';
$filterTo       = $_GET['to'] ?? '';

try {
    $where    = ['ak.user_id = ?'];
    $bindings = [$userId];

    if ($filterStatus) {
        if ($filterStatus === '2xx') {
            $where[] = 'arl.response_code BETWEEN 200 AND 299';
        } elseif ($filterStatus === '4xx') {
            $where[] = 'arl.response_code BETWEEN 400 AND 499';
        } elseif ($filterStatus === '5xx') {
            $where[] = 'arl.response_code >= 500';
        }
    }
    if ($filterEndpoint) {
        $where[]    = 'arl.endpoint LIKE ?';
        $bindings[] = '%' . $filterEndpoint . '%';
    }
    if ($filterFrom) {
        $where[]    = 'arl.created_at >= ?';
        $bindings[] = $filterFrom . ' 00:00:00';
    }
    if ($filterTo) {
        $where[]    = 'arl.created_at <= ?';
        $bindings[] = $filterTo . ' 23:59:59';
    }

    $whereStr  = 'WHERE ' . implode(' AND ', $where);
    $countStmt = $db->prepare("SELECT COUNT(*) FROM api_request_logs arl JOIN api_keys ak ON ak.id = arl.api_key_id $whereStr");
    $countStmt->execute($bindings);
    $total     = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($total / $perPage);

    $stmt = $db->prepare(
        "SELECT arl.id, arl.method, arl.endpoint, arl.response_code, arl.response_time_ms,
                arl.ip_address, arl.created_at, ak.name AS key_name, ak.key_prefix
         FROM api_request_logs arl
         JOIN api_keys ak ON ak.id = arl.api_key_id
         $whereStr
         ORDER BY arl.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute(array_merge($bindings, [$perPage, $offset]));
    $logs  = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dbOk  = true;
} catch (PDOException $e) {
    $logs = [];
    $total = $totalPages = 0;
    $dbOk  = false;
}

$pageTitle = 'API Request Logs';
require_once __DIR__ . '/../../includes/header.php';

function statusBadge(int $code): string {
    if ($code < 300) return '<span class="badge bg-success">' . $code . '</span>';
    if ($code < 400) return '<span class="badge bg-info">' . $code . '</span>';
    if ($code < 500) return '<span class="badge bg-warning text-dark">' . $code . '</span>';
    return '<span class="badge bg-danger">' . $code . '</span>';
}
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
                    <a href="<?= APP_URL ?>/pages/api/logs.php" class="list-group-item list-group-item-action active"><i class="bi bi-list-ul"></i> Request Logs</a>
                    <a href="<?= APP_URL ?>/pages/api/usage.php" class="list-group-item list-group-item-action"><i class="bi bi-bar-chart"></i> Usage Analytics</a>
                    <a href="<?= APP_URL ?>/pages/api/webhooks.php" class="list-group-item list-group-item-action"><i class="bi bi-arrow-repeat"></i> Webhooks</a>
                </div>
            </div>
        </div>

        <div class="col-lg-10 col-md-9">
            <h1 class="h3 fw-bold mb-4"><i class="bi bi-list-ul text-primary"></i> API Request Logs</h1>

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <form class="row g-2 align-items-end" method="GET">
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="2xx" <?= $filterStatus === '2xx' ? 'selected' : '' ?>>2xx Success</option>
                                <option value="4xx" <?= $filterStatus === '4xx' ? 'selected' : '' ?>>4xx Client Error</option>
                                <option value="5xx" <?= $filterStatus === '5xx' ? 'selected' : '' ?>>5xx Server Error</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Endpoint</label>
                            <input type="text" name="endpoint" class="form-control form-control-sm" placeholder="e.g. products/list" value="<?= e($filterEndpoint) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">From</label>
                            <input type="date" name="from" class="form-control form-control-sm" value="<?= e($filterFrom) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">To</label>
                            <input type="date" name="to" class="form-control form-control-sm" value="<?= e($filterTo) ?>">
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                            <a href="?" class="btn btn-sm btn-outline-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!$dbOk): ?>
                <div class="alert alert-warning">API tables not initialized.</div>
            <?php else: ?>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Showing <?= $total ?> requests</span>
                    <small class="text-muted">Page <?= $page ?> of <?= max(1, $totalPages) ?></small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Method</th>
                                <th>Endpoint</th>
                                <th>Status</th>
                                <th>Time (ms)</th>
                                <th>IP</th>
                                <th>API Key</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logs): ?>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="small text-muted"><?= date('M d H:i:s', strtotime($log['created_at'])) ?></td>
                                    <td>
                                        <?php
                                        $mc = ['GET'=>'success','POST'=>'primary','PUT'=>'warning','DELETE'=>'danger'];
                                        $bc = $mc[$log['method']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $bc ?>"><?= e($log['method']) ?></span>
                                    </td>
                                    <td class="small font-monospace"><?= e($log['endpoint']) ?></td>
                                    <td><?= statusBadge((int)$log['response_code']) ?></td>
                                    <td class="small <?= (int)$log['response_time_ms'] > 1000 ? 'text-danger' : '' ?>">
                                        <?= (int)$log['response_time_ms'] ?>ms
                                    </td>
                                    <td class="small text-muted"><?= e($log['ip_address']) ?></td>
                                    <td><code class="small"><?= e($log['key_prefix']) ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No API requests logged yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-white">
                    <nav>
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&status=<?= e($filterStatus) ?>&endpoint=<?= e($filterEndpoint) ?>&from=<?= e($filterFrom) ?>&to=<?= e($filterTo) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
