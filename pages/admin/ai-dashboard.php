<?php
/**
 * pages/admin/ai-dashboard.php — Admin AI Dashboard
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();
requireRole(['admin', 'super_admin']);
require_once __DIR__ . '/../../includes/ai-engine.php';

$pageTitle   = 'AI Dashboard — Admin';
$currentUser = getCurrentUser();

// Load usage stats
$usageToday = getAiUsageStats(null, 'today');
$usageWeek  = getAiUsageStats(null, 'week');
$usageMonth = getAiUsageStats(null, 'month');

// Load AI config
$configs = [];
try {
    $stmt = getDB()->prepare('SELECT config_key, config_value, description FROM ai_config ORDER BY config_key');
    $stmt->execute();
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// High-risk fraud count
require_once __DIR__ . '/../../includes/ai-fraud.php';
$highRiskOrders = getHighRiskOrders(5);

$fraudStats = [];
try {
    $stmt = getDB()->prepare(
        "SELECT
           COUNT(*) AS total,
           SUM(risk_level = 'critical') AS critical,
           SUM(risk_level = 'high') AS high,
           SUM(admin_decision = 'pending') AS pending
         FROM ai_fraud_logs"
    );
    $stmt->execute();
    $fraudStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { /* ignore */ }

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 fw-bold mb-0"><i class="bi bi-robot text-primary me-2"></i>AI Dashboard</h1>
        <a href="/api/ai.php?action=health" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-activity me-1"></i>API Health Check
        </a>
    </div>

    <!-- Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Tokens Today</div>
                            <div class="h4 fw-bold mb-0"><?= number_format((int)($usageToday['tokens_input'] ?? 0) + (int)($usageToday['tokens_output'] ?? 0)) ?></div>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded p-2">
                            <i class="bi bi-cpu text-primary fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">Month: <?= number_format((int)($usageMonth['tokens_input'] ?? 0) + (int)($usageMonth['tokens_output'] ?? 0)) ?></small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">AI Requests Today</div>
                            <div class="h4 fw-bold mb-0"><?= number_format((int)($usageToday['requests'] ?? 0)) ?></div>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded p-2">
                            <i class="bi bi-lightning text-success fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">Week: <?= number_format((int)($usageWeek['requests'] ?? 0)) ?></small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Est. Cost Today</div>
                            <div class="h4 fw-bold mb-0">$<?= number_format((float)($usageToday['cost_usd'] ?? 0), 4) ?></div>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded p-2">
                            <i class="bi bi-currency-dollar text-warning fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">Month: $<?= number_format((float)($usageMonth['cost_usd'] ?? 0), 4) ?></small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Fraud Alerts</div>
                            <div class="h4 fw-bold mb-0 text-danger"><?= (int)($fraudStats['pending'] ?? 0) ?></div>
                        </div>
                        <div class="bg-danger bg-opacity-10 rounded p-2">
                            <i class="bi bi-shield-exclamation text-danger fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        Critical: <?= (int)($fraudStats['critical'] ?? 0) ?> |
                        High: <?= (int)($fraudStats['high'] ?? 0) ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Fraud Alerts -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-shield-exclamation text-danger me-2"></i>High-Risk Fraud Alerts</span>
                    <a href="<?= APP_URL ?>/pages/admin/ai-fraud.php" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($highRiskOrders)): ?>
                        <div class="text-center text-muted py-4"><i class="bi bi-check-circle text-success fs-3 d-block mb-2"></i>No high-risk alerts</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light"><tr><th>Order/Event</th><th>Risk</th><th>Score</th><th>Action</th></tr></thead>
                                <tbody>
                                <?php foreach ($highRiskOrders as $r): ?>
                                    <tr>
                                        <td class="small"><?= $r['order_id'] ? '#' . $r['order_id'] : 'User #' . $r['user_id'] ?></td>
                                        <td><span class="badge bg-<?= $r['risk_level'] === 'critical' ? 'danger' : 'warning' ?>"><?= ucfirst($r['risk_level']) ?></span></td>
                                        <td><?= $r['risk_score'] ?>/100</td>
                                        <td><a href="<?= APP_URL ?>/pages/admin/ai-fraud.php?id=<?= $r['id'] ?>" class="btn btn-xs btn-outline-secondary btn-sm">Review</a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- AI Feature Toggles -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-toggles me-2"></i>Feature Toggles</div>
                <div class="card-body">
                    <?php
                    $toggles = [
                        'ai_enabled'                 => 'Global AI',
                        'ai_chatbot_enabled'         => 'Chatbot',
                        'ai_recommendations_enabled' => 'Recommendations',
                        'ai_fraud_enabled'           => 'Fraud Detection',
                        'ai_search_enabled'          => 'Smart Search',
                        'ai_content_enabled'         => 'Content Generation',
                    ];
                    $configMap = [];
                    foreach ($configs as $c) {
                        $configMap[$c['config_key']] = $c['config_value'];
                    }
                    foreach ($toggles as $key => $label):
                        $isOn = ($configMap[$key] ?? '1') === '1';
                    ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-semibold"><?= $label ?></span>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input ai-toggle" type="checkbox"
                                   data-key="<?= $key ?>"
                                   <?= $isOn ? 'checked' : '' ?>>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <hr>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Update API Key</label>
                        <div class="input-group input-group-sm">
                            <input type="password" id="apiKeyInput" class="form-control" placeholder="sk-...">
                            <button class="btn btn-outline-primary" onclick="updateApiKey()">Save</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.ai-toggle').forEach(function (el) {
    el.addEventListener('change', async function () {
        const key   = this.dataset.key;
        const value = this.checked ? '1' : '0';
        const body  = new URLSearchParams({ key, value });
        const resp  = await fetch('/api/ai.php?action=admin_config', { method: 'POST', body });
        const data  = await resp.json();
        if (!data.success) { this.checked = !this.checked; alert('Failed to update config'); }
    });
});

async function updateApiKey() {
    const val = document.getElementById('apiKeyInput').value.trim();
    if (!val) return;
    const body = new URLSearchParams({ key: 'deepseek_api_key', value: val });
    const resp = await fetch('/api/ai.php?action=admin_config', { method: 'POST', body });
    const data = await resp.json();
    if (data.success) {
        alert('API key updated successfully.');
        document.getElementById('apiKeyInput').value = '';
    } else {
        alert('Failed to update API key.');
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
