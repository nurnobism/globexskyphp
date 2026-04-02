<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$userId = $_SESSION['user_id'] ?? 0;

$stmt = $db->prepare("SELECT t.* FROM teams t JOIN team_members tm ON t.id = tm.team_id WHERE tm.user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

$members = [];
$isOwner = false;
if ($team) {
    $stmt = $db->prepare("SELECT tm.*, u.name, u.email FROM team_members tm JOIN users u ON tm.user_id = u.id WHERE tm.team_id = ? ORDER BY tm.role ASC, u.name ASC");
    $stmt->execute([$team['id']]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $db->prepare("SELECT role FROM team_members WHERE team_id = ? AND user_id = ?");
    $stmt->execute([$team['id'], $userId]);
    $myRole = $stmt->fetchColumn();
    $isOwner = $myRole === 'owner';
}

$roleBadges = [
    'owner'  => 'bg-danger',
    'admin'  => 'bg-warning text-dark',
    'editor' => 'bg-info',
    'member' => 'bg-primary',
    'viewer' => 'bg-secondary',
];

$pageTitle = 'Team Management';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-people me-2"></i>Team Management</h1>
        <div class="d-flex gap-2">
            <a href="roles.php" class="btn btn-outline-secondary">
                <i class="bi bi-shield-lock me-1"></i>Roles
            </a>
            <a href="invite.php" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i>Invite Member
            </a>
        </div>
    </div>

    <?php if (!$team): ?>
        <div class="text-center py-5">
            <i class="bi bi-people display-1 text-muted"></i>
            <h5 class="mt-3">No Team Found</h5>
            <p class="text-muted">You are not part of any team yet.</p>
        </div>
    <?php else: ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-1"><?= e($team['name'] ?? 'My Team') ?></h4>
                        <p class="text-muted mb-0">
                            <?php if (!empty($team['description'])): ?>
                                <?= e($team['description']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="text-muted small">
                            <div><i class="bi bi-people me-1"></i><?= count($members) ?> member<?= count($members) !== 1 ? 's' : '' ?></div>
                            <div><i class="bi bi-calendar me-1"></i>Created <?= formatDate($team['created_at'] ?? '') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Members</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <?php if ($isOwner): ?>
                                <th></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td class="fw-semibold">
                                    <i class="bi bi-person-circle me-1 text-muted"></i>
                                    <?= e($member['name'] ?? '') ?>
                                </td>
                                <td><?= e($member['email'] ?? '') ?></td>
                                <td>
                                    <?php $badge = $roleBadges[$member['role'] ?? ''] ?? 'bg-secondary'; ?>
                                    <span class="badge <?= $badge ?>"><?= e(ucfirst($member['role'] ?? 'member')) ?></span>
                                </td>
                                <td>
                                    <?php if (($member['status'] ?? 'active') === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif (($member['status'] ?? '') === 'invited'): ?>
                                        <span class="badge bg-warning text-dark">Invited</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= e(ucfirst($member['status'] ?? '')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= formatDate($member['created_at'] ?? '') ?></td>
                                <?php if ($isOwner): ?>
                                    <td>
                                        <?php if (($member['role'] ?? '') !== 'owner'): ?>
                                            <form method="post" action="../../api/teams.php?action=remove_member"
                                                  onsubmit="return confirm('Remove this team member?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="team_id" value="<?= (int) $team['id'] ?>">
                                                <input type="hidden" name="user_id" value="<?= (int) ($member['user_id'] ?? 0) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                                    <i class="bi bi-person-x"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
