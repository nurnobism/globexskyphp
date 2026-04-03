<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireRole(['admin', 'super_admin']);
require_once __DIR__ . '/../../../includes/kyc.php';

$db = getDB();

// Stats
try {
    $totalPending   = (int) $db->query("SELECT COUNT(*) FROM kyc_submissions WHERE status = 'pending'")->fetchColumn();
    $approvedToday  = (int) $db->query("SELECT COUNT(*) FROM kyc_submissions WHERE status = 'approved' AND DATE(reviewed_at) = CURDATE()")->fetchColumn();
    $underReview    = (int) $db->query("SELECT COUNT(*) FROM kyc_submissions WHERE status = 'under_review'")->fetchColumn();
    $totalReviewed  = (int) $db->query("SELECT COUNT(*) FROM kyc_submissions WHERE status IN ('approved','rejected')")->fetchColumn();
    $totalRejected  = (int) $db->query("SELECT COUNT(*) FROM kyc_submissions WHERE status = 'rejected'")->fetchColumn();
    $rejectionRate  = $totalReviewed > 0 ? round(($totalRejected / $totalReviewed) * 100, 1) : 0;
} catch (PDOException $e) {
    $totalPending = $approvedToday = $underReview = $totalRejected = $totalReviewed = 0;
    $rejectionRate = 0;
}

// Filters
$page      = max(1, (int) get('page', 1));
$filterStatus = get('status', '');
$search       = get('q', '');
$dateFrom     = get('date_from', '');
$dateTo       = get('date_to', '');

$where  = ['1=1'];
$params = [];

if ($filterStatus) {
    $where[]  = 'ks.status = ?';
    $params[] = $filterStatus;
}
if ($search) {
    $where[]  = '(u.email LIKE ? OR ks.business_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
    $s = "%{$search}%";
    $params   = array_merge($params, [$s, $s, $s, $s]);
}
if ($dateFrom) {
    $where[]  = 'ks.submitted_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[]  = 'ks.submitted_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

$whereClause = implode(' AND ', $where);
$sql = "SELECT ks.id, ks.business_name, ks.business_type, ks.country, ks.status,
               ks.submitted_at, ks.reviewed_at,
               u.id AS user_id, u.email,
               CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS user_name
        FROM kyc_submissions ks
        JOIN users u ON u.id = ks.user_id
        WHERE {$whereClause}
        ORDER BY ks.submitted_at DESC";

$result      = paginate($db, $sql, $params, $page, 20);
$submissions = $result['data'];

$statusColors = [
    'pending'      => 'warning',
    'under_review' => 'info',
    'approved'     => 'success',
    'rejected'     => 'danger',
    'expired'      => 'secondary',
];

$pageTitle = 'KYC Management';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-shield-check text-primary me-2"></i>KYC Management</h3>
        <a href="/pages/admin/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <?php $cards = [
            ['Pending Review',   $totalPending,  'hourglass-split', 'warning'],
            ['Approved Today',   $approvedToday, 'patch-check-fill','success'],
            ['Under Review',     $underReview,   'search',          'info'],
            ['Rejection Rate',   $rejectionRate . '%', 'x-circle', 'danger'],
        ]; ?>
        <?php foreach ($cards as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-<?= $color ?> bg-opacity-10 p-3">
                        <i class="bi bi-<?= $icon ?> text-<?= $color ?> fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?= $value ?></h5>
                        <small class="text-muted"><?= $label ?></small>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="row g-2 mb-4 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted mb-1">Search</label>
            <input type="text" name="q" class="form-control" placeholder="User, email, business..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted mb-1">Status</label>
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <?php foreach (['pending', 'under_review', 'approved', 'rejected', 'expired'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
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
            <?php if ($search || $filterStatus || $dateFrom || $dateTo): ?>
            <a href="?" class="btn btn-outline-secondary ms-1">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <span class="small text-muted"><?= $result['total'] ?> submission<?= $result['total'] !== 1 ? 's' : '' ?> found</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Business Name</th>
                        <th>Type</th>
                        <th>Country</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($submissions as $sub):
                    $badgeColor = $statusColors[$sub['status']] ?? 'secondary';
                    $displayName = trim($sub['user_name']) ?: $sub['email'];
                ?>
                <tr>
                    <td class="text-muted">#<?= (int) $sub['id'] ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($displayName) ?></div>
                        <div class="text-muted"><?= e($sub['email']) ?></div>
                    </td>
                    <td><?= e($sub['business_name']) ?></td>
                    <td><?= e(ucfirst(str_replace('_', ' ', $sub['business_type']))) ?></td>
                    <td><?= e($sub['country']) ?></td>
                    <td>
                        <span class="badge bg-<?= $badgeColor ?>">
                            <?= e(ucfirst(str_replace('_', ' ', $sub['status']))) ?>
                        </span>
                    </td>
                    <td><?= formatDate($sub['submitted_at']) ?></td>
                    <td>
                        <a href="/pages/admin/kyc/review.php?id=<?= (int) $sub['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i>Review
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($submissions)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No KYC submissions found.
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
                <a class="page-link" href="?page=<?= $result['current'] - 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>
            <?php for ($i = max(1, $result['current'] - 2); $i <= min($result['pages'], $result['current'] + 2); $i++): ?>
            <li class="page-item <?= $i === $result['current'] ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">
                    <?= $i ?>
                </a>
            </li>
            <?php endfor; ?>
            <?php if ($result['current'] < $result['pages']): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?= $result['current'] + 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
