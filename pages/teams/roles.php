<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$userId = $_SESSION['user_id'] ?? 0;

$stmt = $db->prepare("SELECT t.id FROM teams t JOIN team_members tm ON t.id = tm.team_id WHERE tm.user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teamId = $stmt->fetchColumn();

$roles = [
    [
        'name'        => 'Owner',
        'description' => 'Full control over the team, billing, and all settings. Can transfer ownership.',
        'permissions' => ['All Permissions', 'Billing', 'Team Deletion', 'Ownership Transfer'],
        'badge'       => 'bg-danger',
        'count'       => 0,
    ],
    [
        'name'        => 'Admin',
        'description' => 'Full access to all features except team ownership and billing.',
        'permissions' => ['Manage Members', 'Manage Products', 'Manage Orders', 'View Reports', 'Edit Settings'],
        'badge'       => 'bg-warning text-dark',
        'count'       => 0,
    ],
    [
        'name'        => 'Editor',
        'description' => 'Can create and edit products, manage inventory, and process orders.',
        'permissions' => ['Edit Products', 'Manage Inventory', 'Process Orders', 'View Reports'],
        'badge'       => 'bg-info',
        'count'       => 0,
    ],
    [
        'name'        => 'Member',
        'description' => 'Can create orders, view products, and manage their own profile.',
        'permissions' => ['Create Orders', 'View Products', 'Edit Profile', 'View Dashboard'],
        'badge'       => 'bg-primary',
        'count'       => 0,
    ],
    [
        'name'        => 'Viewer',
        'description' => 'Read-only access to view products, orders, and reports.',
        'permissions' => ['View Products', 'View Orders', 'View Reports'],
        'badge'       => 'bg-secondary',
        'count'       => 0,
    ],
];

if ($teamId) {
    foreach ($roles as &$role) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND role = ?");
        $stmt->execute([$teamId, strtolower($role['name'])]);
        $role['count'] = (int) $stmt->fetchColumn();
    }
    unset($role);
}

$pageTitle = 'Team Roles';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="index.php" class="btn btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 mb-0"><i class="bi bi-shield-lock me-2"></i>Team Roles &amp; Permissions</h1>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Role</th>
                        <th>Description</th>
                        <th>Permissions</th>
                        <th class="text-center">Members</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td>
                                <span class="badge <?= $role['badge'] ?> fs-6">
                                    <?= e($role['name']) ?>
                                </span>
                            </td>
                            <td class="text-muted small"><?= e($role['description']) ?></td>
                            <td>
                                <?php foreach ($role['permissions'] as $perm): ?>
                                    <span class="badge bg-light text-dark border me-1 mb-1">
                                        <?= e($perm) ?>
                                    </span>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-dark"><?= $role['count'] ?></span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Edit role">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-muted small mt-3">
        <i class="bi bi-info-circle me-1"></i>Role definitions are system-wide defaults. Contact support for custom role configurations.
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
