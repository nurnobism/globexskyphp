<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db = getDB();

// Daily usage stats (last 7 days)
$dailyStats = $db->query("SELECT DATE(created_at) AS date, COUNT(*) AS requests, COALESCE(SUM(tokens_used),0) AS tokens FROM ai_usage_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY date ASC")->fetchAll();

// Recent logs
$logs = $db->query("SELECT al.*, u.email, m.name AS model_name FROM ai_usage_logs al LEFT JOIN users u ON al.user_id = u.id LEFT JOIN ai_models m ON al.model_id = m.id ORDER BY al.created_at DESC LIMIT 100")->fetchAll();

$pageTitle = 'AI Usage Logs';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-activity text-warning me-2"></i>AI Usage Logs</h3>
        <a href="/pages/admin/ai/index.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <!-- 7-day stats -->
    <?php if (!empty($dailyStats)): ?>
    <div class="row g-3 mb-4">
        <?php foreach ($dailyStats as $stat): ?>
        <div class="col">
            <div class="card border-0 shadow-sm text-center py-3">
                <h6 class="text-muted small"><?= date('M d', strtotime($stat['date'])) ?></h6>
                <h5 class="fw-bold"><?= number_format($stat['requests']) ?></h5>
                <small class="text-muted"><?= number_format($stat['tokens']) ?> tokens</small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Logs Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th>Date</th><th>User</th><th>Model</th><th>Feature</th><th>Tokens</th><th>Cost</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No AI usage logs yet.</td></tr>
                <?php else: ?>
                <?php foreach ($logs as $l): ?>
                <tr>
                    <td><?= formatDate($l['created_at']) ?></td>
                    <td><?= e($l['email'] ?? 'Guest') ?></td>
                    <td><?= e($l['model_name'] ?? '—') ?></td>
                    <td><span class="badge bg-light text-dark border"><?= e($l['feature'] ?? 'general') ?></span></td>
                    <td><?= number_format((int)($l['tokens_used'] ?? 0)) ?></td>
                    <td>$<?= number_format((float)($l['cost'] ?? 0), 6) ?></td>
                    <td><span class="badge bg-<?= ($l['status'] ?? 'success') === 'success' ? 'success' : 'danger' ?>"><?= ucfirst($l['status'] ?? 'success') ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
