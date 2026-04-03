<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['admin', 'super_admin']);
require_once __DIR__ . '/../../includes/admin_permissions.php';

$db = getDB();

// Filters
$page         = max(1, (int) get('page', 1));
$filterAdmin  = (int) get('admin_id', 0);
$filterAction = get('action_type', '');
$filterTarget = get('target_type', '');
$dateFrom     = get('date_from', '');
$dateTo       = get('date_to', '');

$where  = ['1=1'];
$params = [];

if ($filterAdmin > 0) {
    $where[]  = 'aal.admin_id = ?';
    $params[] = $filterAdmin;
}
if ($filterAction) {
    $where[]  = 'aal.action LIKE ?';
    $params[] = '%' . $filterAction . '%';
}
if ($filterTarget) {
    $where[]  = 'aal.target_type = ?';
    $params[] = $filterTarget;
}
if ($dateFrom) {
    $where[]  = 'aal.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[]  = 'aal.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

$whereClause = implode(' AND ', $where);

$sql = "SELECT aal.id, aal.action, aal.target_type, aal.target_id,
               aal.ip_address, aal.created_at, aal.details,
               u.email AS admin_email,
               CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS admin_name
        FROM admin_audit_log aal
        LEFT JOIN users u ON u.id = aal.admin_id
        WHERE {$whereClause}
        ORDER BY aal.created_at DESC";

$result  = paginate($db, $sql, $params, $page, 20);
$entries = $result['data'];

// Load admin users for the filter dropdown
try {
    $adminsStmt = $db->query(
        "SELECT id, email, CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) AS full_name
         FROM users
         WHERE role IN ('admin','super_admin')
         ORDER BY email ASC"
    );
    $admins = $adminsStmt->fetchAll();
} catch (PDOException $e) {
    $admins = [];
}

// Collect distinct target types for filter dropdown
try {
    $targetTypesStmt = $db->query("SELECT DISTINCT target_type FROM admin_audit_log WHERE target_type IS NOT NULL ORDER BY target_type ASC");
    $targetTypes     = array_column($targetTypesStmt->fetchAll(), 'target_type');
} catch (PDOException $e) {
    $targetTypes = [];
}

$pageTitle = 'Admin Audit Log';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-journal-text text-primary me-2"></i>Admin Audit Log</h3>
        <a href="/pages/admin/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="row g-2 mb-4 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted mb-1">Admin</label>
            <select name="admin_id" class="form-select">
                <option value="">All Admins</option>
                <?php foreach ($admins as $adm): ?>
                <option value="<?= (int) $adm['id'] ?>" <?= $filterAdmin === (int) $adm['id'] ? 'selected' : '' ?>>
                    <?= e(trim($adm['full_name']) ?: $adm['email']) ?> (<?= e($adm['email']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted mb-1">Action Type</label>
            <input type="text" name="action_type" class="form-control" placeholder="e.g. approve, reject..." value="<?= e($filterAction) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted mb-1">Target Type</label>
            <select name="target_type" class="form-select">
                <option value="">All Targets</option>
                <?php foreach ($targetTypes as $tt): ?>
                <option value="<?= e($tt) ?>" <?= $filterTarget === $tt ? 'selected' : '' ?>>
                    <?= e(ucfirst(str_replace('_', ' ', $tt))) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted mb-1">Date From</label>
            <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted mb-1">Date To</label>
            <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-md-auto">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search me-1"></i>Filter
            </button>
            <?php if ($filterAdmin || $filterAction || $filterTarget || $dateFrom || $dateTo): ?>
            <a href="?" class="btn btn-outline-secondary ms-1">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <span class="small text-muted"><?= $result['total'] ?> log entr<?= $result['total'] !== 1 ? 'ies' : 'y' ?> found</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Target Type</th>
                        <th>Target ID</th>
                        <th>IP Address</th>
                        <th>Date / Time</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $entry):
                    $displayAdmin = trim($entry['admin_name']) ?: ($entry['admin_email'] ?? '—');
                ?>
                <tr>
                    <td class="text-muted"><?= (int) $entry['id'] ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($displayAdmin) ?></div>
                        <?php if (!empty($entry['admin_email']) && trim($entry['admin_name'])): ?>
                        <div class="text-muted"><?= e($entry['admin_email']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-secondary fw-normal"><?= e($entry['action']) ?></span>
                    </td>
                    <td>
                        <?php if (!empty($entry['target_type'])): ?>
                        <span class="text-muted"><?= e(ucfirst(str_replace('_', ' ', $entry['target_type']))) ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($entry['target_id'])): ?>
                        <span class="badge bg-light text-dark border">#<?= (int) $entry['target_id'] ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?= e($entry['ip_address'] ?? '—') ?></td>
                    <td class="text-nowrap"><?= formatDateTime($entry['created_at']) ?></td>
                    <td>
                        <?php if (!empty($entry['details'])): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="popover"
                                data-bs-trigger="hover focus"
                                data-bs-content="<?= e($entry['details']) ?>"
                                title="Details">
                            <i class="bi bi-info-circle"></i>
                        </button>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($entries)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-journal-x fs-3 d-block mb-2"></i>
                        No audit log entries found.
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($result['pages'] > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php if ($result['current'] > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?= $result['current'] - 1 ?>&admin_id=<?= $filterAdmin ?>&action_type=<?= urlencode($filterAction) ?>&target_type=<?= urlencode($filterTarget) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>
            <?php for ($i = max(1, $result['current'] - 2); $i <= min($result['pages'], $result['current'] + 2); $i++): ?>
            <li class="page-item <?= $i === $result['current'] ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&admin_id=<?= $filterAdmin ?>&action_type=<?= urlencode($filterAction) ?>&target_type=<?= urlencode($filterTarget) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">
                    <?= $i ?>
                </a>
            </li>
            <?php endfor; ?>
            <?php if ($result['current'] < $result['pages']): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?= $result['current'] + 1 ?>&admin_id=<?= $filterAdmin ?>&action_type=<?= urlencode($filterAction) ?>&target_type=<?= urlencode($filterTarget) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Initialise Bootstrap popovers
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
        new bootstrap.Popover(el);
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
