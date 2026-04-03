<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['label_action'] ?? '';

    if ($action === 'create' && !empty(trim($_POST['name'] ?? ''))) {
        $name  = trim($_POST['name']);
        $color = $_POST['color'] ?? '#6c757d';
        $db->prepare('INSERT IGNORE INTO webmail_labels (user_id, name, color) VALUES (?,?,?)')
           ->execute([$userId, $name, $color]);
    }
    if ($action === 'update' && !empty($_POST['label_id'])) {
        $labelId = (int)$_POST['label_id'];
        $name    = trim($_POST['name'] ?? '');
        $color   = $_POST['color'] ?? '#6c757d';
        if ($name !== '') {
            $db->prepare('UPDATE webmail_labels SET name=?, color=? WHERE id=? AND user_id=?')
               ->execute([$name, $color, $labelId, $userId]);
        }
    }
    if ($action === 'delete' && !empty($_POST['label_id'])) {
        $labelId = (int)$_POST['label_id'];
        $db->prepare('DELETE FROM webmail_labels WHERE id=? AND user_id=?')
           ->execute([$labelId, $userId]);
    }

    header('Location: labels.php');
    exit;
}

$stmt = $db->prepare('SELECT * FROM webmail_labels WHERE user_id=? ORDER BY sort_order, name');
$stmt->execute([$userId]);
$labels = $stmt->fetchAll();

$pageTitle = 'Manage Labels';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <a href="compose.php" class="btn btn-primary w-100 mb-3"><i class="bi bi-pencil-square me-1"></i> Compose</a>
            <div class="list-group list-group-flush mb-3">
                <a href="inbox.php" class="list-group-item list-group-item-action"><i class="bi bi-inbox me-2"></i>Inbox</a>
                <a href="sent.php" class="list-group-item list-group-item-action"><i class="bi bi-send me-2"></i>Sent</a>
                <a href="drafts.php" class="list-group-item list-group-item-action"><i class="bi bi-file-earmark me-2"></i>Drafts</a>
                <a href="trash.php" class="list-group-item list-group-item-action"><i class="bi bi-trash me-2"></i>Trash</a>
            </div>
        </div>

        <div class="col-md-9 col-lg-10">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-tags me-2"></i>Manage Labels</h5>
                </div>
                <div class="card-body">
                    <!-- Create new label -->
                    <form method="POST" class="row g-2 align-items-end mb-4">
                        <?= csrfField() ?>
                        <input type="hidden" name="label_action" value="create">
                        <div class="col-md-5">
                            <label class="form-label">Label Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g., Important" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Color</label>
                            <input type="color" name="color" class="form-control form-control-color" value="#0d6efd">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Create Label</button>
                        </div>
                    </form>

                    <?php if (empty($labels)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-tags display-4 text-muted"></i>
                            <p class="text-muted mt-2">No labels yet. Create one above.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Color</th>
                                        <th>Name</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($labels as $lbl): ?>
                                        <tr>
                                            <td><span class="badge rounded-pill" style="background:<?= e($lbl['color']) ?>">&nbsp;&nbsp;&nbsp;</span></td>
                                            <td><?= e($lbl['name']) ?></td>
                                            <td><small class="text-muted"><?= date('M j, Y', strtotime($lbl['created_at'])) ?></small></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editLabel<?= $lbl['id'] ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this label?')">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="label_action" value="delete">
                                                    <button type="submit" name="label_id" value="<?= (int)$lbl['id'] ?>" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>

                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editLabel<?= $lbl['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="label_action" value="update">
                                                        <input type="hidden" name="label_id" value="<?= (int)$lbl['id'] ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Edit Label</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Name</label>
                                                                <input type="text" name="name" class="form-control" value="<?= e($lbl['name']) ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Color</label>
                                                                <input type="color" name="color" class="form-control form-control-color" value="<?= e($lbl['color']) ?>">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
