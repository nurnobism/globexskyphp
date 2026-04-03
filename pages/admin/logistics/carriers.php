<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db               = getDB();
$filterStatus     = get('verification_status', '');
$page             = max(1, (int)get('page', 1));
$limit            = 50;
$offset           = ($page - 1) * $limit;

$carriers = [];
$total    = 0;

try {
    $where  = ['1=1'];
    $params = [];
    if ($filterStatus) {
        $where[] = 'c.status = ?';
        $params[] = $filterStatus;
    }
    $whereStr = implode(' AND ', $where);

    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM carriers c WHERE $whereStr"
    );
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT c.*, u.first_name, u.last_name, u.email, u.phone AS user_phone, u.is_verified
         FROM carriers c
         LEFT JOIN users u ON u.id = c.user_id
         WHERE $whereStr
         ORDER BY c.created_at DESC
         LIMIT $limit OFFSET $offset"
    );
    $stmt->execute($params);
    $carriers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tables may not exist yet
}

$statusColors = [
    'pending'   => 'warning',
    'active'    => 'success',
    'suspended' => 'danger',
    'rejected'  => 'secondary',
];

$allStatuses = ['pending', 'active', 'suspended', 'rejected'];
$pages       = $limit > 0 && $total > 0 ? (int)ceil($total / $limit) : 1;

$pageTitle = 'Carrier Management';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-person-badge text-primary me-2"></i>Carrier Management</h3>
        <a href="/pages/admin/logistics/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Overview
        </a>
    </div>

    <!-- Quick Nav -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="/pages/admin/logistics/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Overview</a>
        <a href="/pages/admin/logistics/parcels.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-seam me-1"></i>Parcels</a>
        <a href="/pages/admin/logistics/carriers.php" class="btn btn-primary btn-sm"><i class="bi bi-person-badge me-1"></i>Carriers</a>
        <a href="/pages/admin/logistics/carry-requests.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left-right me-1"></i>Carry Requests</a>
        <a href="/pages/admin/logistics/rates.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-currency-dollar me-1"></i>Rates</a>
    </div>

    <!-- Status Filters -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="?" class="btn btn-sm <?= $filterStatus === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
        <?php foreach ($allStatuses as $s): ?>
        <a href="?verification_status=<?= $s ?>"
           class="btn btn-sm <?= $filterStatus === $s ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= ucfirst($s) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-2">
            <span class="small text-muted"><?= $total ?> carrier<?= $total !== 1 ? 's' : '' ?> found</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>ID Type</th>
                        <th>Nationality</th>
                        <th>Verified</th>
                        <th>Rating</th>
                        <th>Trips</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($carriers)): ?>
                <tr><td colspan="11" class="text-center text-muted py-4">No carriers found.</td></tr>
                <?php endif; ?>
                <?php foreach ($carriers as $c):
                    $displayName = !empty($c['full_name'])
                        ? $c['full_name']
                        : trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                    $phone = $c['phone'] ?? $c['user_phone'] ?? '—';
                ?>
                <tr>
                    <td><?= (int)$c['id'] ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($displayName ?: '—') ?></div>
                        <?php if (!empty($c['email'])): ?>
                        <small class="text-muted"><?= e($c['email']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e($phone) ?></td>
                    <td><?= e($c['passport_number'] ?? '—') ?></td>
                    <td><?= e($c['nationality'] ?? '—') ?></td>
                    <td>
                        <?php if (!empty($c['is_verified'])): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Yes</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $rating = isset($c['rating']) ? (float)$c['rating'] : 0;
                        $stars  = round($rating);
                        ?>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star<?= $i <= $stars ? '-fill text-warning' : ' text-muted' ?>"></i>
                        <?php endfor; ?>
                        <small class="ms-1"><?= number_format($rating, 1) ?></small>
                    </td>
                    <td><?= (int)($c['total_trips'] ?? 0) ?></td>
                    <td>
                        <span class="badge bg-<?= $statusColors[$c['status']] ?? 'secondary' ?>">
                            <?= e(ucfirst($c['status'] ?? '')) ?>
                        </span>
                    </td>
                    <td class="text-nowrap"><?= formatDate($c['created_at']) ?></td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <?php if (($c['status'] ?? '') !== 'active'): ?>
                            <form method="POST" action="/api/carry.php?action=approve_carrier" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="carrier_id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success"
                                        onclick="return confirm('Approve this carrier?')">
                                    Approve
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if (($c['status'] ?? '') !== 'rejected'): ?>
                            <form method="POST" action="/api/carry.php?action=reject_carrier" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="carrier_id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Reject this carrier?')">
                                    Reject
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if (($c['status'] ?? '') === 'active'): ?>
                            <form method="POST" action="/api/carry.php?action=suspend_carrier" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="carrier_id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning"
                                        onclick="return confirm('Suspend this carrier?')">
                                    Suspend
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
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
                <a class="page-link" href="?page=<?= $i ?>&verification_status=<?= e($filterStatus) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
