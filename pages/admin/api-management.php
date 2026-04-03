<?php
/**
 * pages/admin/api-management.php — Admin API Management
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['admin', 'super_admin']);

$db = getDB();

try {
    $totalKeys    = (int)$db->query('SELECT COUNT(*) FROM api_keys')->fetchColumn();
    $activeKeys   = (int)$db->query('SELECT COUNT(*) FROM api_keys WHERE is_active = 1')->fetchColumn();
    $requestsToday = (int)$db->query('SELECT COALESCE(SUM(requests_today), 0) FROM api_keys')->fetchColumn();

    $errorStmt = $db->query(
        "SELECT COUNT(*) FROM api_request_logs WHERE response_code >= 400 AND created_at >= CURDATE()"
    );
    $errorsToday  = (int)$errorStmt->fetchColumn();
    $totalToday   = (int)$db->query("SELECT COUNT(*) FROM api_request_logs WHERE created_at >= CURDATE()")->fetchColumn();
    $errorRate    = $totalToday > 0 ? round($errorsToday / $totalToday * 100, 1) : 0;

    // Top consumers
    $topStmt = $db->query(
        'SELECT ak.name, ak.key_prefix, ak.requests_today, ak.requests_month,
                u.email, u.first_name, u.last_name
         FROM api_keys ak
         JOIN users u ON u.id = ak.user_id
         WHERE ak.is_active = 1
         ORDER BY ak.requests_today DESC
         LIMIT 10'
    );
    $topConsumers = $topStmt->fetchAll(PDO::FETCH_ASSOC);

    // All keys
    $keysStmt = $db->query(
        'SELECT ak.*, u.email, u.first_name, u.last_name
         FROM api_keys ak
         JOIN users u ON u.id = ak.user_id
         ORDER BY ak.created_at DESC
         LIMIT 50'
    );
    $allKeys = $keysStmt->fetchAll(PDO::FETCH_ASSOC);

    $dbOk = true;
} catch (PDOException $e) {
    $dbOk = false;
    $totalKeys = $activeKeys = $requestsToday = $errorsToday = $errorRate = 0;
    $topConsumers = $allKeys = [];
}

// Handle revoke action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_key'])) {
    validateCsrf();
    $keyId = (int)$_POST['revoke_key'];
    try {
        $db->prepare('UPDATE api_keys SET is_active = 0, revoked_at = NOW() WHERE id = ?')->execute([$keyId]);
        flashMessage('success', 'API key revoked.');
    } catch (PDOException $e) {
        flashMessage('danger', 'Failed to revoke key.');
    }
    redirect('/pages/admin/api-management.php');
}

$pageTitle = 'API Management';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <h1 class="h3 fw-bold mb-4"><i class="bi bi-code-slash text-primary"></i> API Management</h1>

    <?php if (!$dbOk): ?>
        <div class="alert alert-warning">API tables not initialized. Run schema_v4.sql first.</div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-1 fw-bold text-primary"><?= $totalKeys ?></div>
                <div class="text-muted small">Total API Keys</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-1 fw-bold text-success"><?= $activeKeys ?></div>
                <div class="text-muted small">Active Keys</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-1 fw-bold text-info"><?= number_format($requestsToday) ?></div>
                <div class="text-muted small">Requests Today</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-1 fw-bold text-<?= $errorRate > 5 ? 'danger' : 'success' ?>"><?= $errorRate ?>%</div>
                <div class="text-muted small">Error Rate (Today)</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Top Consumers -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-trophy"></i> Top API Consumers (Today)</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>User</th><th>Key</th><th>Req Today</th></tr></thead>
                        <tbody>
                            <?php if ($topConsumers): ?>
                                <?php foreach ($topConsumers as $c): ?>
                                <tr>
                                    <td class="small"><?= e($c['first_name'] . ' ' . $c['last_name']) ?><br>
                                        <span class="text-muted"><?= e($c['email']) ?></span></td>
                                    <td><code class="small"><?= e($c['key_prefix']) ?></code></td>
                                    <td><?= number_format((int)$c['requests_today']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">No data.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- All Keys -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-key"></i> All API Keys</div>
                <div class="table-responsive" style="max-height:400px;overflow-y:auto">
                    <table class="table table-sm mb-0">
                        <thead class="table-light sticky-top"><tr><th>User</th><th>Name</th><th>Prefix</th><th>Env</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($allKeys as $key): ?>
                            <tr>
                                <td class="small"><?= e($key['email']) ?></td>
                                <td class="small"><?= e($key['name']) ?></td>
                                <td><code class="small"><?= e($key['key_prefix']) ?></code></td>
                                <td><span class="badge bg-<?= $key['environment'] === 'live' ? 'success' : 'secondary' ?>"><?= $key['environment'] ?></span></td>
                                <td>
                                    <?php if ($key['is_active'] && !$key['revoked_at']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Revoked</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($key['is_active']): ?>
                                    <form method="POST" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="revoke_key" value="<?= $key['id'] ?>">
                                        <button type="submit" class="btn btn-xs btn-outline-danger btn-sm"
                                                onclick="return confirm('Revoke key?')">Revoke</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
