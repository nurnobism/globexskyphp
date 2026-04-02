<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireRole(['admin', 'super_admin']);

$db     = getDB();
$page   = max(1, (int)get('page', 1));
$role   = get('role', '');
$status = get('status', '');
$q      = get('q', '');
$where  = ['1=1']; $params = [];
if ($role)   { $where[] = 'role=?';   $params[] = $role; }
if ($status) { $where[] = 'status=?'; $params[] = $status; }
if ($q)      { $where[] = '(email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR name LIKE ?)'; $params = array_merge($params, ["%$q%","%$q%","%$q%","%$q%"]); }
$sql    = 'SELECT id,email,name,first_name,last_name,role,status,is_active,is_verified,created_at FROM users WHERE ' . implode(' AND ',$where) . ' ORDER BY created_at DESC';
$result = paginate($db, $sql, $params, $page);
$users  = $result['data'];

$pageTitle = 'Admin — User Management';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-people-fill text-primary me-2"></i>User Management</h3>
        <a href="/pages/admin/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    </div>

    <!-- Filter -->
    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-3"><input type="text" name="q" class="form-control" placeholder="Search email, name..." value="<?= e($q) ?>"></div>
        <div class="col-md-2">
            <select name="role" class="form-select">
                <option value="">All Roles</option>
                <?php foreach (['buyer','supplier','carrier','admin','super_admin','inspector','support'] as $r): ?>
                <option value="<?= $r ?>" <?= $role===$r?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$r)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <?php foreach (['active','suspended','banned','pending'] as $s): ?>
                <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-primary">Search</button></div>
        <?php if ($q||$role||$status): ?><div class="col-auto"><a href="?" class="btn btn-outline-secondary">Clear</a></div><?php endif; ?>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-2 d-flex justify-content-between">
            <span class="small text-muted"><?= $result['total'] ?> users found</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $displayName = !empty($u['name']) ? $u['name']
                        : trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                    $roleColors = ['super_admin'=>'danger','admin'=>'danger','supplier'=>'primary','carrier'=>'success','buyer'=>'secondary'];
                    $rc = $roleColors[$u['role']] ?? 'secondary';
                    $userStatus = $u['status'] ?? ($u['is_active'] ? 'active' : 'inactive');
                    $statusColors = ['active'=>'success','suspended'=>'warning','banned'=>'danger','pending'=>'secondary'];
                    $sc = $statusColors[$userStatus] ?? 'secondary';
                ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= e($displayName ?: '—') ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="badge bg-<?= $rc ?>"><?= e(str_replace('_',' ',$u['role'])) ?></span></td>
                    <td>
                        <span class="badge bg-<?= $sc ?>"><?= e(ucfirst($userStatus)) ?></span>
                        <?php if ($u['is_verified']): ?><span class="badge bg-info ms-1"><i class="bi bi-check-circle"></i> Verified</span><?php endif; ?>
                    </td>
                    <td><?= formatDate($u['created_at']) ?></td>
                    <td class="d-flex gap-1 flex-wrap">
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <form method="POST" action="/api/admin.php?action=toggle_user" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-<?= $u['is_active'] ? 'outline-danger' : 'outline-success' ?>"
                                    onclick="return confirm('<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this user?')">
                                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No users found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($result['pages'] > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i=1; $i<=$result['pages']; $i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&role=<?= e($role) ?>&status=<?= e($status) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
