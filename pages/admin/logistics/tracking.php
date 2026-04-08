<?php
/**
 * pages/admin/logistics/tracking.php — Admin: Tracking Dashboard (PR #15)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/tracking.php';
requireAdmin();

$db = getDB();

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = ['active' => 0, 'delivered_today' => 0, 'in_transit' => 0, 'exceptions' => 0];

try {
    $statStmt = $db->query(
        "SELECT status, COUNT(*) AS cnt FROM shipments GROUP BY status"
    );
    foreach ($statStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $s = $row['status'];
        $c = (int)$row['cnt'];
        if (in_array($s, ['label_created', 'picked_up', 'in_transit', 'out_for_delivery'], true)) {
            $stats['active'] += $c;
        }
        if ($s === 'in_transit' || $s === 'out_for_delivery') {
            $stats['in_transit'] += $c;
        }
        if ($s === 'exception') {
            $stats['exceptions'] += $c;
        }
    }
    $deliveredStmt = $db->query(
        "SELECT COUNT(*) FROM shipments WHERE status='delivered' AND DATE(actual_delivery) = CURDATE()"
    );
    $stats['delivered_today'] = (int)$deliveredStmt->fetchColumn();
} catch (PDOException $e) {
    // Table may not exist yet
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filterStatus   = trim($_GET['status']   ?? '');
$filterCarrier  = trim($_GET['carrier']  ?? '');
$filterSupplier = trim($_GET['supplier'] ?? '');
$filterFrom     = trim($_GET['date_from'] ?? '');
$filterTo       = trim($_GET['date_to']   ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 30;

$filters = array_filter([
    'status'    => $filterStatus,
    'carrier'   => $filterCarrier,
    'supplier'  => $filterSupplier,
    'date_from' => $filterFrom,
    'date_to'   => $filterTo,
]);

// If filtering by supplier name, resolve to supplier_id first
$supplierFilter = [];
if ($filterSupplier !== '') {
    try {
        $sStmt = $db->prepare(
            "SELECT id FROM users WHERE CONCAT(first_name,' ',last_name) LIKE ? OR email LIKE ? LIMIT 50"
        );
        $like = '%' . $filterSupplier . '%';
        $sStmt->execute([$like, $like]);
        $supplierFilter = $sStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) { /* ignore */ }
}

// Use _getShipmentsFiltered via public helpers
$shipmentData = ['shipments' => [], 'total' => 0, 'pages' => 1];
try {
    $where  = [];
    $params = [];

    if ($filterStatus !== '') {
        $where[]  = 's.status = ?';
        $params[] = $filterStatus;
    }
    if ($filterCarrier !== '') {
        $where[]  = 's.carrier_code = ?';
        $params[] = $filterCarrier;
    }
    if (!empty($supplierFilter)) {
        $ph      = implode(',', array_fill(0, count($supplierFilter), '?'));
        $where[] = "s.supplier_id IN ($ph)";
        $params  = array_merge($params, $supplierFilter);
    }
    if ($filterFrom !== '') {
        $where[]  = 'DATE(s.shipped_date) >= ?';
        $params[] = $filterFrom;
    }
    if ($filterTo !== '') {
        $where[]  = 'DATE(s.shipped_date) <= ?';
        $params[] = $filterTo;
    }

    $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $db->prepare("SELECT COUNT(*) FROM shipments s $whereStr");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $offset   = ($page - 1) * $perPage;
    $dataStmt = $db->prepare(
        "SELECT s.*,
                o.order_number,
                b.first_name AS buyer_first, b.last_name AS buyer_last, b.email AS buyer_email,
                sup.first_name AS supplier_first, sup.last_name AS supplier_last
         FROM shipments s
         LEFT JOIN orders o   ON o.id   = s.order_id
         LEFT JOIN users  b   ON b.id   = o.buyer_id
         LEFT JOIN users  sup ON sup.id = s.supplier_id
         $whereStr
         ORDER BY s.created_at DESC
         LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
    );
    $dataStmt->execute($params);
    $shipmentData = [
        'shipments' => $dataStmt->fetchAll(PDO::FETCH_ASSOC),
        'total'     => $total,
        'pages'     => (int)ceil($total / $perPage),
    ];
} catch (PDOException $e) {
    // Tables not yet created
}

$carriers  = getCarriers();
$pageTitle = 'Tracking Dashboard';
include __DIR__ . '/../../../includes/header.php';

$statCards = [
    ['label' => 'Active Shipments',  'value' => $stats['active'],         'color' => 'primary', 'icon' => 'truck'],
    ['label' => 'In Transit',        'value' => $stats['in_transit'],      'color' => 'warning', 'icon' => 'box-seam'],
    ['label' => 'Delivered Today',   'value' => $stats['delivered_today'], 'color' => 'success', 'icon' => 'house-check'],
    ['label' => 'Exceptions',        'value' => $stats['exceptions'],      'color' => 'danger',  'icon' => 'exclamation-triangle'],
];
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4 class="fw-bold mb-0"><i class="bi bi-truck me-2"></i>Tracking Dashboard</h4>
        <form method="POST" action="/api/tracking.php?action=refresh" id="refresh-all-form">
            <?= csrfField() ?>
            <button type="button" class="btn btn-outline-primary btn-sm" id="refresh-all-btn">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh All Active
            </button>
        </form>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <?php foreach ($statCards as $card): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-<?= $card['color'] ?> bg-opacity-10 d-flex align-items-center justify-content-center"
                         style="width:52px;height:52px;flex-shrink:0">
                        <i class="bi bi-<?= $card['icon'] ?> text-<?= $card['color'] ?> fs-4"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bold"><?= number_format($card['value']) ?></div>
                        <div class="text-muted small"><?= e($card['label']) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Exception Alerts -->
    <?php
    $exceptions = array_filter($shipmentData['shipments'], fn($s) => $s['status'] === 'exception');
    if (!empty($exceptions)):
    ?>
    <div class="alert alert-danger d-flex align-items-start gap-2 mb-4">
        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 mt-1"></i>
        <div>
            <strong><?= count($exceptions) ?> shipment<?= count($exceptions) > 1 ? 's have' : ' has' ?> delivery exceptions!</strong>
            <ul class="mb-0 mt-1 small">
                <?php foreach ($exceptions as $ex): ?>
                <li>
                    Order #<?= e($ex['order_number'] ?? $ex['order_id']) ?> —
                    <?= e($ex['carrier_name'] ?: $ex['carrier_code']) ?>
                    (<?= e($ex['tracking_number']) ?>)
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-sm-6 col-md-2">
                    <label class="form-label small fw-semibold mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <?php
                        $allStatuses = ['label_created','picked_up','in_transit','out_for_delivery','delivered','exception','returned','unknown'];
                        foreach ($allStatuses as $st):
                        ?>
                        <option value="<?= e($st) ?>" <?= $filterStatus === $st ? 'selected' : '' ?>>
                            <?= e(getStatusLabel($st)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label small fw-semibold mb-1">Carrier</label>
                    <select name="carrier" class="form-select form-select-sm">
                        <option value="">All Carriers</option>
                        <?php foreach ($carriers as $c): ?>
                        <option value="<?= e($c['code']) ?>" <?= $filterCarrier === $c['code'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label small fw-semibold mb-1">Supplier</label>
                    <input type="text" name="supplier" class="form-control form-control-sm"
                           placeholder="Name or email" value="<?= e($filterSupplier) ?>">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label small fw-semibold mb-1">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filterFrom) ?>">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label small fw-semibold mb-1">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filterTo) ?>">
                </div>
                <div class="col-sm-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                    <a href="?" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Shipments Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <span class="small text-muted">
                    <?= number_format($shipmentData['total']) ?> shipment<?= $shipmentData['total'] !== 1 ? 's' : '' ?>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Order #</th>
                            <th>Supplier</th>
                            <th>Buyer</th>
                            <th>Carrier</th>
                            <th>Tracking #</th>
                            <th>Status</th>
                            <th>Shipped</th>
                            <th>Est. Delivery</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shipmentData['shipments'])): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No shipments found.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($shipmentData['shipments'] as $s): ?>
                        <tr class="<?= $s['status'] === 'exception' ? 'table-danger' : '' ?>">
                            <td>
                                <?php if (!empty($s['order_number'])): ?>
                                <a href="/pages/admin/orders/detail.php?order_id=<?= (int)$s['order_id'] ?>" class="text-decoration-none">
                                    #<?= e($s['order_number']) ?>
                                </a>
                                <?php else: ?>
                                #<?= (int)$s['order_id'] ?>
                                <?php endif; ?>
                            </td>
                            <td><?= e(trim(($s['supplier_first'] ?? '') . ' ' . ($s['supplier_last'] ?? ''))) ?: '—' ?></td>
                            <td><?= e(trim(($s['buyer_first'] ?? '') . ' ' . ($s['buyer_last'] ?? ''))) ?: '—' ?></td>
                            <td><?= e($s['carrier_name'] ?: $s['carrier_code']) ?></td>
                            <td class="font-monospace small"><?= e($s['tracking_number']) ?></td>
                            <td>
                                <span class="badge bg-<?= getStatusColor($s['status']) ?>">
                                    <?= e(getStatusLabel($s['status'])) ?>
                                </span>
                            </td>
                            <td class="small"><?= $s['shipped_date'] ? e(date('M j, Y', strtotime($s['shipped_date']))) : '—' ?></td>
                            <td class="small"><?= $s['estimated_delivery'] ? e(date('M j, Y', strtotime($s['estimated_delivery']))) : '—' ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php $tUrl = getCarrierTrackingUrl($s['carrier_code'] ?? '', $s['tracking_number'] ?? '', $s['tracking_url'] ?? ''); ?>
                                    <?php if ($tUrl): ?>
                                    <a href="<?= e($tUrl) ?>" target="_blank" rel="noopener noreferrer"
                                       class="btn btn-outline-secondary btn-sm" title="Track on carrier site">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-primary btn-sm refresh-btn"
                                            data-id="<?= (int)$s['id'] ?>" title="Refresh tracking">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($shipmentData['pages'] > 1): ?>
            <div class="px-3 py-2 border-top d-flex justify-content-center">
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($p = 1; $p <= $shipmentData['pages']; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>">
                                <?= $p ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Individual shipment refresh
document.querySelectorAll('.refresh-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id   = btn.getAttribute('data-id');
        var icon = btn.querySelector('i');
        icon.classList.add('spin');
        btn.disabled = true;

        var fd = new FormData();
        fd.append('shipment_id', id);
        fd.append('csrf_token', document.querySelector('[name=csrf_token]')?.value || '');

        fetch('/api/tracking.php?action=refresh', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                icon.classList.remove('spin');
                btn.disabled = false;
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Refresh failed');
                }
            })
            .catch(function() { icon.classList.remove('spin'); btn.disabled = false; });
    });
});

// Refresh all active shipments
document.getElementById('refresh-all-btn')?.addEventListener('click', function() {
    if (!confirm('Refresh tracking for all active shipments? This may take a moment.')) return;
    this.disabled = true;
    this.textContent = 'Refreshing…';

    // Call the cron endpoint on the server-side (admin only)
    fetch('/api/tracking.php?action=refresh_all', { method: 'POST',
        body: new URLSearchParams({ csrf_token: document.querySelector('[name=csrf_token]')?.value || '' })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        alert('Done. Refreshed: ' + (data.refreshed ?? 0) + ', Errors: ' + (data.errors ?? 0));
        location.reload();
    })
    .catch(function() { location.reload(); });
});
</script>

<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.spin { display: inline-block; animation: spin 1s linear infinite; }
</style>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
