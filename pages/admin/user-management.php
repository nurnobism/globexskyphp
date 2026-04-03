<?php
/**
 * pages/admin/user-management.php — Enhanced User Management (Phase 9)
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['admin', 'super_admin']);

$db   = getDB();
$page = max(1, (int)($_GET['page'] ?? 1));
$role = $_GET['role'] ?? '';
$kyc  = $_GET['kyc']  ?? '';
$q    = $_GET['q']    ?? '';

$where  = ['1=1'];
$params = [];
if ($role) { $where[] = 'u.role=?'; $params[] = $role; }
if ($q)    { $where[] = '(u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)'; $params = array_merge($params, ["%$q%","%$q%","%$q%"]); }

$sql = 'SELECT u.*, COALESCE(kl.current_level, 0) as kyc_level
        FROM users u
        LEFT JOIN kyc_levels kl ON kl.user_id = u.id
        WHERE ' . implode(' AND ', $where);
if ($kyc !== '') { $sql .= ' HAVING kyc_level=?'; $params[] = (int)$kyc; }
$sql .= ' ORDER BY u.created_at DESC';

try {
    $result = paginate($db, $sql, $params, $page);
    $users  = $result['data'];
    $pagination = $result;
} catch (PDOException $e) {
    $users = []; $pagination = ['total' => 0, 'pages' => 1];
}

// CSV export — must run before any output (headers not yet sent)
if (isset($_GET['export']) && $_GET['export'] === 'csv' && isAdmin()) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_export.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Email','First Name','Last Name','Role','KYC Level','Created At']);
    foreach ($users as $u) {
        fputcsv($out, [$u['id'],$u['email'],$u['first_name']??'',$u['last_name']??'',$u['role'],$u['kyc_level'],$u['created_at']]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Admin — Enhanced User Management';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-people-fill text-primary me-2"></i>User Management</h3>
        <div class="d-flex gap-2">
            <a href="/pages/admin/users.php" class="btn btn-outline-secondary btn-sm">Standard View</a>
            <a href="/pages/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Dashboard
            </a>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-3">
            <input type="text" name="q" class="form-control" placeholder="Search email/name..." value="<?= e($q) ?>">
        </div>
        <div class="col-md-2">
            <select name="role" class="form-select">
                <option value="">All Roles</option>
                <?php foreach (['buyer','supplier','admin','super_admin'] as $r): ?>
                    <option value="<?= $r ?>" <?= $role===$r?'selected':'' ?>><?= ucwords(str_replace('_',' ',$r)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="kyc" class="form-select">
                <option value="">All KYC Levels</option>
                <?php for ($l=0; $l<=4; $l++): ?>
                    <option value="<?= $l ?>" <?= $kyc==="$l"?'selected':'' ?>>L<?= $l ?> — <?= ['Unverified','Basic','Business','Premium','Gold'][$l] ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-search me-1"></i>Filter
            </button>
        </div>
        <div class="col-md-3 text-end">
            <a href="?export=csv&role=<?= urlencode($role) ?>&q=<?= urlencode($q) ?>" class="btn btn-outline-success btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
        </div>
    </form>

    <!-- Users Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th><th>Name</th><th>Email</th><th>Role</th>
                            <th>KYC Level</th><th>Status</th><th>Joined</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= e(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?></td>
                            <td><?= e($u['email']) ?></td>
                            <td><span class="badge bg-secondary"><?= e($u['role']) ?></span></td>
                            <td>
                                <?php $kl = (int)$u['kyc_level']; $colors = ['secondary','info','primary','success','warning']; ?>
                                <span class="badge bg-<?= $colors[$kl] ?? 'secondary' ?>">L<?= $kl ?></span>
                            </td>
                            <td>
                                <span class="badge bg-<?= ($u['is_active'] ?? 1) ? 'success' : 'danger' ?>">
                                    <?= ($u['is_active'] ?? 1) ? 'Active' : 'Suspended' ?>
                                </span>
                            </td>
                            <td><small><?= e(date('M j, Y', strtotime($u['created_at']))) ?></small></td>
                            <td>
                                <a href="/pages/admin/users.php?view=<?= $u['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if (($pagination['pages'] ?? 1) > 1): ?>
    <nav class="mt-3">
        <ul class="pagination justify-content-center">
            <?php for ($i=1; $i<=$pagination['pages']; $i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>&role=<?= urlencode($role) ?>&q=<?= urlencode($q) ?>&kyc=<?= urlencode($kyc) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
