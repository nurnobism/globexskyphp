<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db           = getDB();
$filterStatus = get('status', '');
$page         = max(1, (int)get('page', 1));
$limit        = 50;
$offset       = ($page - 1) * $limit;

$requests = [];
$total    = 0;

try {
    $where  = ['1=1'];
    $params = [];
    if ($filterStatus) {
        $where[] = 'cr.status = ?';
        $params[] = $filterStatus;
    }
    $whereStr = implode(' AND ', $where);

    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM carry_requests cr WHERE $whereStr"
    );
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT cr.*, u.first_name, u.last_name, u.email
         FROM carry_requests cr
         LEFT JOIN users u ON u.id = cr.sender_id
         WHERE $whereStr
         ORDER BY cr.created_at DESC
         LIMIT $limit OFFSET $offset"
    );
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tables may not exist yet
}

$statusColors = [
    'open'        => 'primary',
    'matched'     => 'info',
    'in_progress' => 'info',
    'delivered'   => 'success',
    'completed'   => 'success',
    'cancelled'   => 'secondary',
    'disputed'    => 'danger',
];

$allStatuses = ['open', 'matched', 'in_progress', 'delivered', 'completed', 'cancelled', 'disputed'];
$pages       = $limit > 0 && $total > 0 ? (int)ceil($total / $limit) : 1;

$pageTitle = 'Carry Requests';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-arrow-left-right text-primary me-2"></i>Carry Requests</h3>
        <a href="/pages/admin/logistics/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Overview
        </a>
    </div>

    <!-- Quick Nav -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="/pages/admin/logistics/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Overview</a>
        <a href="/pages/admin/logistics/parcels.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-seam me-1"></i>Parcels</a>
        <a href="/pages/admin/logistics/carriers.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-badge me-1"></i>Carriers</a>
        <a href="/pages/admin/logistics/carry-requests.php" class="btn btn-primary btn-sm"><i class="bi bi-arrow-left-right me-1"></i>Carry Requests</a>
        <a href="/pages/admin/logistics/rates.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-currency-dollar me-1"></i>Rates</a>
    </div>

    <!-- Status Filters -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="?" class="btn btn-sm <?= $filterStatus === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
        <?php foreach ($allStatuses as $s): ?>
        <a href="?status=<?= $s ?>"
           class="btn btn-sm <?= $filterStatus === $s ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= ucfirst(str_replace('_', ' ', $s)) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-2">
            <span class="small text-muted"><?= $total ?> request<?= $total !== 1 ? 's' : '' ?> found</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Route</th>
                        <th>Category</th>
                        <th>Weight</th>
                        <th>Budget</th>
                        <th>Sender</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($requests)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No carry requests found.</td></tr>
                <?php endif; ?>
                <?php foreach ($requests as $r):
                    $senderName = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($r['title']) ?></div>
                        <?php if (!empty($r['description'])): ?>
                        <small class="text-muted"><?= e(mb_substr($r['description'], 0, 60)) ?>…</small>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <?= e($r['from_city']) ?> (<?= e($r['from_country']) ?>)
                        <i class="bi bi-arrow-right"></i>
                        <?= e($r['to_city']) ?> (<?= e($r['to_country']) ?>)
                    </td>
                    <td><?= e(ucfirst($r['category'] ?? '—')) ?></td>
                    <td><?= isset($r['weight_kg']) ? e($r['weight_kg']) . ' kg' : '—' ?></td>
                    <td>
                        <?php if (isset($r['budget'])): ?>
                            <?= formatMoney($r['budget']) ?> <?= e($r['currency'] ?? 'USD') ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <div><?= e($senderName ?: '—') ?></div>
                        <?php if (!empty($r['email'])): ?>
                        <small class="text-muted"><?= e($r['email']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= $statusColors[$r['status']] ?? 'secondary' ?>">
                            <?= e(ucfirst(str_replace('_', ' ', $r['status']))) ?>
                        </span>
                    </td>
                    <td class="text-nowrap"><?= formatDate($r['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&status=<?= e($filterStatus) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
