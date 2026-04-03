<?php
/**
 * pages/admin/ai-fraud.php — Admin Fraud Review Queue
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();
requireRole(['admin', 'super_admin']);
require_once __DIR__ . '/../../includes/ai-engine.php';
require_once __DIR__ . '/../../includes/ai-fraud.php';

$pageTitle   = 'Fraud Review — Admin AI';
$currentUser = getCurrentUser();

// Filters
$filterRisk     = $_GET['risk_level']     ?? '';
$filterEvent    = $_GET['event_type']     ?? '';
$filterDecision = $_GET['admin_decision'] ?? 'pending';

$filters = array_filter([
    'risk_level'     => $filterRisk,
    'event_type'     => $filterEvent,
    'admin_decision' => $filterDecision,
]);

$dashboard = getFraudDashboard($filters);
$items     = $dashboard['items'] ?? [];
$stats     = $dashboard['stats'] ?? [];

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <h1 class="h3 fw-bold mb-0"><i class="bi bi-shield-exclamation text-danger me-2"></i>Fraud Review Queue</h1>
        <a href="<?= APP_URL ?>/pages/admin/ai-dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>AI Dashboard
        </a>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <?php
        $statCards = [
            ['label' => 'Total Flagged',    'value' => $stats['total']        ?? 0, 'icon' => 'flag',              'color' => 'secondary'],
            ['label' => 'Critical',         'value' => $stats['critical_count'] ?? 0, 'icon' => 'exclamation-octagon', 'color' => 'danger'],
            ['label' => 'High Risk',        'value' => $stats['high_count']    ?? 0, 'icon' => 'exclamation-triangle', 'color' => 'warning'],
            ['label' => 'Pending Review',   'value' => $stats['pending_count'] ?? 0, 'icon' => 'hourglass-split',  'color' => 'info'],
        ];
        foreach ($statCards as $card):
        ?>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small"><?= $card['label'] ?></div>
                        <div class="h4 fw-bold mb-0"><?= number_format((int)$card['value']) ?></div>
                    </div>
                    <i class="bi bi-<?= $card['icon'] ?> text-<?= $card['color'] ?> fs-3"></i>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-sm-3">
                    <label class="form-label small fw-semibold">Risk Level</label>
                    <select name="risk_level" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach (['low', 'medium', 'high', 'critical'] as $lvl): ?>
                            <option value="<?= $lvl ?>" <?= $filterRisk === $lvl ? 'selected' : '' ?>><?= ucfirst($lvl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-3">
                    <label class="form-label small fw-semibold">Event Type</label>
                    <select name="event_type" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach (['order', 'login', 'registration', 'payment', 'refund', 'review'] as $et): ?>
                            <option value="<?= $et ?>" <?= $filterEvent === $et ? 'selected' : '' ?>><?= ucfirst($et) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-3">
                    <label class="form-label small fw-semibold">Decision Status</label>
                    <select name="admin_decision" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach (['pending', 'approved', 'rejected', 'escalated'] as $d): ?>
                            <option value="<?= $d ?>" <?= $filterDecision === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-3">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Items Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($items)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle text-success display-4"></i>
                    <p class="mt-3 text-muted">No flagged items found for the selected filters.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>User / Order</th>
                            <th>Risk Level</th>
                            <th>Score</th>
                            <th>AI Rec.</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="small">#<?= (int)$item['id'] ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($item['event_type']) ?></span></td>
                            <td class="small">
                                User #<?= (int)$item['user_id'] ?>
                                <?= $item['order_id'] ? '<br><small>Order #' . (int)$item['order_id'] . '</small>' : '' ?>
                            </td>
                            <td>
                                <?php
                                $lvlColor = match ($item['risk_level']) {
                                    'critical' => 'danger',
                                    'high'     => 'warning',
                                    'medium'   => 'info',
                                    default    => 'success',
                                };
                                ?>
                                <span class="badge bg-<?= $lvlColor ?>"><?= ucfirst($item['risk_level']) ?></span>
                            </td>
                            <td>
                                <div class="progress" style="height:6px;width:80px">
                                    <div class="progress-bar bg-<?= $lvlColor ?>"
                                         style="width:<?= $item['risk_score'] ?>%"></div>
                                </div>
                                <small><?= $item['risk_score'] ?>/100</small>
                            </td>
                            <td><span class="badge bg-outline-secondary border"><?= htmlspecialchars($item['ai_recommendation']) ?></span></td>
                            <td>
                                <?php
                                $decColor = match ($item['admin_decision']) {
                                    'approved'  => 'success',
                                    'rejected'  => 'danger',
                                    'escalated' => 'warning',
                                    default     => 'secondary',
                                };
                                ?>
                                <span class="badge bg-<?= $decColor ?>"><?= ucfirst($item['admin_decision']) ?></span>
                            </td>
                            <td class="small"><?= date('M d, H:i', strtotime($item['created_at'])) ?></td>
                            <td>
                                <?php if ($item['admin_decision'] === 'pending'): ?>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-xs btn-success btn-sm resolve-btn"
                                            data-id="<?= $item['id'] ?>"
                                            data-action="approved">✓</button>
                                    <button class="btn btn-xs btn-danger btn-sm resolve-btn"
                                            data-id="<?= $item['id'] ?>"
                                            data-action="rejected">✗</button>
                                    <button class="btn btn-xs btn-warning btn-sm resolve-btn"
                                            data-id="<?= $item['id'] ?>"
                                            data-action="escalated">⬆</button>
                                </div>
                                <?php else: ?>
                                <span class="text-muted small">Resolved</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.resolve-btn').forEach(function (btn) {
    btn.addEventListener('click', async function () {
        const logId    = this.dataset.id;
        const decision = this.dataset.action;
        const notes    = prompt('Add notes (optional):') || '';

        const body = new URLSearchParams({ log_id: logId, decision, notes });
        const resp = await fetch('/api/ai.php?action=fraud_resolve', { method: 'POST', body });
        const data = await resp.json();

        if (data.success) {
            this.closest('tr').querySelector('td:nth-last-child(1)').innerHTML = '<span class="text-muted small">Resolved</span>';
            this.closest('tr').querySelector('td:nth-last-child(3)').innerHTML = '<span class="badge bg-' +
                (decision === 'approved' ? 'success' : decision === 'rejected' ? 'danger' : 'warning') + '">' +
                decision.charAt(0).toUpperCase() + decision.slice(1) + '</span>';
        } else {
            alert('Failed to resolve case.');
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
