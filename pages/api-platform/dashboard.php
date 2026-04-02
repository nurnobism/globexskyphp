<?php
/**
 * pages/api-platform/dashboard.php — API Usage Dashboard
 */

require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db     = getDB();
$userId = $_SESSION['user_id'];

// Fetch summary stats (last 30 days)
$totStmt = $db->prepare('SELECT COUNT(*) total,
    SUM(CASE WHEN status_code < 400 THEN 1 ELSE 0 END) success,
    SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) errors
    FROM api_requests WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
$totStmt->execute([$userId]);
$totals = $totStmt->fetch();
$totalCalls  = (int)($totals['total']  ?? 0);
$successRate = $totalCalls > 0 ? round(($totals['success'] / $totalCalls) * 100, 1) : 100;

// Top endpoints
$epStmt = $db->prepare('SELECT endpoint, COUNT(*) calls FROM api_requests
    WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY endpoint ORDER BY calls DESC LIMIT 5');
$epStmt->execute([$userId]);
$topEndpoints = $epStmt->fetchAll();

// Recent requests
$recStmt = $db->prepare('SELECT method, endpoint, status_code, response_time_ms, created_at
    FROM api_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
$recStmt->execute([$userId]);
$recentRequests = $recStmt->fetchAll();

// Active API key for display
$keyStmt = $db->prepare('SELECT id, name, key_prefix, last_used_at FROM api_keys
    WHERE user_id = ? AND status = "active" ORDER BY created_at DESC LIMIT 1');
$keyStmt->execute([$userId]);
$activeKey = $keyStmt->fetch();

$pageTitle = 'API Dashboard';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4 class="fw-bold mb-0"><i class="bi bi-speedometer2 me-2" style="color:#FF6B35;"></i>API Dashboard</h4>
        <div class="d-flex gap-2">
            <a href="/pages/api-platform/keys.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-key me-1"></i>Manage Keys</a>
            <a href="/pages/api-platform/docs.php" class="btn btn-sm text-white" style="background:#1B2A4A;"><i class="bi bi-book me-1"></i>Docs</a>
        </div>
    </div>

    <!-- Stats row -->
    <div class="row g-3 mb-4">
        <?php $stats = [
            ['label'=>'Total API Calls (30d)', 'value'=>number_format($totalCalls), 'icon'=>'bi-activity',   'color'=>'#FF6B35'],
            ['label'=>'Success Rate',          'value'=>$successRate . '%',          'icon'=>'bi-check-circle','color'=>'#198754'],
            ['label'=>'Error Calls',           'value'=>number_format($totals['errors'] ?? 0), 'icon'=>'bi-exclamation-triangle','color'=>'#dc3545'],
            ['label'=>'Active Keys',           'value'=>$activeKey ? '1+' : '0',    'icon'=>'bi-key',         'color'=>'#1B2A4A'],
        ];
        foreach ($stats as $s): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:48px;height:48px;background:<?= $s['color'] ?>1a;">
                        <i class="<?= $s['icon'] ?> fs-4" style="color:<?= $s['color'] ?>;"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-5"><?= $s['value'] ?></div>
                        <div class="text-muted small"><?= $s['label'] ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <!-- Chart placeholder + API key -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">API Calls Over Time</span>
                    <select class="form-select form-select-sm w-auto" id="periodSelect">
                        <option value="7d">Last 7 days</option>
                        <option value="30d" selected>Last 30 days</option>
                    </select>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center" style="min-height:220px;background:#f8f9fa;">
                    <div class="text-center text-muted">
                        <i class="bi bi-bar-chart display-3"></i>
                        <p class="mt-2">Chart renders here via Chart.js</p>
                        <small>Load <code>/api/api-platform.php?action=usage_stats&period=30d</code></small>
                    </div>
                </div>
            </div>

            <!-- Recent requests -->
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-semibold">Recent Requests</div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Method</th><th>Endpoint</th><th>Status</th><th>Time (ms)</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentRequests)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No requests yet.</td></tr>
                            <?php else: ?>
                            <?php foreach ($recentRequests as $r):
                                $sc = (int)$r['status_code'];
                                $bc = $sc < 400 ? 'success' : ($sc < 500 ? 'warning' : 'danger');
                            ?>
                            <tr>
                                <td><span class="badge bg-primary"><?= e($r['method']) ?></span></td>
                                <td><code><?= e($r['endpoint']) ?></code></td>
                                <td><span class="badge bg-<?= $bc ?>"><?= $sc ?></span></td>
                                <td><?= number_format($r['response_time_ms']) ?></td>
                                <td class="text-muted small"><?= formatDateTime($r['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Side: active key + top endpoints -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header fw-semibold" style="background:#1B2A4A;color:#fff;">Your API Key</div>
                <div class="card-body">
                    <?php if ($activeKey): ?>
                    <p class="text-muted small mb-1"><?= e($activeKey['name']) ?></p>
                    <div class="input-group">
                        <input type="text" id="keyDisplay" class="form-control font-monospace"
                               value="<?= e($activeKey['key_prefix']) ?>••••••••••••••••••••" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyKey()" title="Copy">
                            <i class="bi bi-clipboard" id="copyIcon"></i>
                        </button>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Last used: <?= $activeKey['last_used_at'] ? formatDate($activeKey['last_used_at']) : 'Never' ?></p>
                    <?php else: ?>
                    <p class="text-muted small">No active keys.</p>
                    <a href="/pages/api-platform/keys.php" class="btn btn-sm text-white" style="background:#FF6B35;">
                        <i class="bi bi-plus me-1"></i>Create Key
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header fw-semibold">Top Endpoints</div>
                <ul class="list-group list-group-flush">
                    <?php if (empty($topEndpoints)): ?>
                    <li class="list-group-item text-muted text-center small py-3">No data yet.</li>
                    <?php else: foreach ($topEndpoints as $ep): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <code class="small"><?= e($ep['endpoint']) ?></code>
                        <span class="badge rounded-pill" style="background:#FF6B35;"><?= number_format($ep['calls']) ?></span>
                    </li>
                    <?php endforeach; endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<script>
function copyKey() {
    const el = document.getElementById('keyDisplay');
    navigator.clipboard.writeText(el.value).then(() => {
        const icon = document.getElementById('copyIcon');
        icon.className = 'bi bi-clipboard-check text-success';
        setTimeout(() => icon.className = 'bi bi-clipboard', 2000);
    });
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
