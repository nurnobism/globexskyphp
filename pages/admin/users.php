<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAdmin();

$db    = getDB();
$page  = max(1, (int)get('page', 1));
$role  = get('role', '');
$q     = get('q', '');
$where = ['1=1']; $params = [];
if ($role) { $where[] = 'role=?'; $params[] = $role; }
if ($q)    { $where[] = '(email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)'; $params = array_merge($params, ["%$q%","%$q%","%$q%"]); }
$sql    = 'SELECT id,email,first_name,last_name,role,is_active,is_verified,created_at FROM users WHERE ' . implode(' AND ',$where) . ' ORDER BY created_at DESC';
$result = paginate($db, $sql, $params, $page);
$users  = $result['data'];

$pageTitle = 'Admin — Users';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-people-fill text-primary me-2"></i>User Management</h3>
        <a href="/pages/admin/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    </div>

    <!-- Filter -->
    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-4"><input type="text" name="q" class="form-control" placeholder="Search email, name..." value="<?= e($q) ?>"></div>
        <div class="col-md-3">
            <select name="role" class="form-select">
                <option value="">All Roles</option>
                <option value="buyer" <?= $role==='buyer'?'selected':'' ?>>Buyers</option>
                <option value="supplier" <?= $role==='supplier'?'selected':'' ?>>Suppliers</option>
                <option value="admin" <?= $role==='admin'?'selected':'' ?>>Admins</option>
            </select>
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-primary">Search</button></div>
        <?php if ($q||$role): ?><div class="col-auto"><a href="?" class="btn btn-outline-secondary">Clear</a></div><?php endif; ?>
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
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="badge bg-<?= $u['role']==='admin'?'danger':($u['role']==='supplier'?'primary':'secondary') ?>"><?= $u['role'] ?></span></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inactive</span>
                        <?php endif; ?>
                        <?php if ($u['is_verified']): ?><span class="badge bg-info ms-1">Verified</span><?php endif; ?>
                    </td>
                    <td><?= formatDate($u['created_at']) ?></td>
                    <td>
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
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($result['pages'] > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i=1; $i<=$result['pages']; $i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&role=<?= e($role) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
