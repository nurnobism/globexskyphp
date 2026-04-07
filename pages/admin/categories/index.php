<?php
/**
 * pages/admin/categories/index.php — Admin Category Tree View
 * PR #4: 3-Level Hierarchical Category System
 */

require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();
require_once __DIR__ . '/../../../includes/categories.php';

$pageTitle = 'Admin — Category Management';

$q    = get('q', '');
$tree = $q ? [] : getCategoryTree();
$searchResults = $q ? searchCategories($q) : [];

include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-diagram-3-fill text-primary me-2"></i>Category Management</h3>
        <div class="d-flex gap-2">
            <a href="/pages/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Dashboard
            </a>
            <a href="/pages/admin/categories/add.php" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Add Root Category
            </a>
        </div>
    </div>

    <!-- Search -->
    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-5">
            <div class="input-group">
                <input type="text" name="q" class="form-control" placeholder="Search categories..." value="<?= e($q) ?>">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                <?php if ($q): ?>
                <a href="?" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if ($q): ?>
    <!-- Search results -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">
            Search results for "<?= e($q) ?>" (<?= count($searchResults) ?> found)
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>ID</th><th>Name</th><th>Level</th><th>Slug</th><th>Commission</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($searchResults as $cat): ?>
                <tr>
                    <td><?= $cat['id'] ?></td>
                    <td>
                        <?= str_repeat('<span class="text-muted">— </span>', max(0, (int)$cat['level'] - 1)) ?>
                        <?php if ($cat['icon']): ?><i class="bi <?= e($cat['icon']) ?> me-1 text-primary"></i><?php endif; ?>
                        <?= e($cat['name']) ?>
                    </td>
                    <td><span class="badge bg-<?= ['','primary','info','secondary'][(int)$cat['level']] ?? 'secondary' ?>">L<?= $cat['level'] ?></span></td>
                    <td><code><?= e($cat['slug']) ?></code></td>
                    <td><?= $cat['commission_rate'] !== null ? e($cat['commission_rate']) . '%' : '—' ?></td>
                    <td>
                        <?php if ($cat['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/pages/admin/categories/edit.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if ((int)$cat['level'] < 3): ?>
                        <a href="/pages/admin/categories/add.php?parent_id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-success">+ Child</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($searchResults)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No categories found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <!-- Category Tree -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-diagram-3 me-2"></i>Category Tree (3 Levels)</span>
            <small class="text-muted"><?= count($tree) ?> root categories</small>
        </div>
        <div class="card-body p-0">
            <?php if (empty($tree)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-folder-x fs-1 d-block mb-2"></i>
                No categories yet. <a href="/pages/admin/categories/add.php">Add the first one</a>.
            </div>
            <?php else: ?>
            <div class="accordion accordion-flush" id="categoryTree">
                <?php foreach ($tree as $l1): ?>
                <div class="accordion-item border-bottom">
                    <div class="accordion-header">
                        <button class="accordion-button collapsed bg-white fw-semibold py-2" type="button"
                                data-bs-toggle="collapse" data-bs-target="#cat-<?= $l1['id'] ?>">
                            <?php if ($l1['icon']): ?>
                            <i class="bi <?= e($l1['icon']) ?> me-2 text-primary fs-5"></i>
                            <?php endif; ?>
                            <?= e($l1['name']) ?>
                            <span class="badge bg-primary ms-2 small"><?= count($l1['children']) ?> sub</span>
                            <?php if ($l1['commission_rate'] !== null): ?>
                            <span class="badge bg-warning text-dark ms-1 small"><?= e($l1['commission_rate']) ?>% commission</span>
                            <?php endif; ?>
                            <?php if (!$l1['is_active']): ?>
                            <span class="badge bg-secondary ms-1 small">Inactive</span>
                            <?php endif; ?>
                        </button>
                    </div>
                    <div id="cat-<?= $l1['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#categoryTree">
                        <div class="accordion-body p-0">
                            <!-- L1 actions -->
                            <div class="px-4 py-2 bg-light d-flex gap-2 border-bottom">
                                <a href="/pages/admin/categories/edit.php?id=<?= $l1['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </a>
                                <a href="/pages/admin/categories/add.php?parent_id=<?= $l1['id'] ?>" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-plus me-1"></i>Add Sub-Category
                                </a>
                                <button class="btn btn-sm btn-outline-danger ms-auto btn-delete-cat"
                                        data-id="<?= $l1['id'] ?>" data-name="<?= e($l1['name']) ?>">
                                    <i class="bi bi-trash me-1"></i>Delete
                                </button>
                            </div>

                            <!-- Level 2 -->
                            <?php if (!empty($l1['children'])): ?>
                            <div class="ps-4">
                                <?php foreach ($l1['children'] as $l2): ?>
                                <div class="border-bottom py-2 px-3">
                                    <div class="d-flex align-items-start gap-2">
                                        <div class="flex-grow-1">
                                            <span class="text-muted me-1">↳</span>
                                            <?php if ($l2['icon']): ?>
                                            <i class="bi <?= e($l2['icon']) ?> me-1 text-info"></i>
                                            <?php endif; ?>
                                            <strong><?= e($l2['name']) ?></strong>
                                            <?php if ($l2['commission_rate'] !== null): ?>
                                            <span class="badge bg-warning text-dark ms-1 small"><?= e($l2['commission_rate']) ?>%</span>
                                            <?php endif; ?>
                                            <?php if (!$l2['is_active']): ?>
                                            <span class="badge bg-secondary ms-1 small">Inactive</span>
                                            <?php endif; ?>

                                            <!-- Level 3 chips -->
                                            <?php if (!empty($l2['children'])): ?>
                                            <div class="mt-1 ps-3">
                                                <?php foreach ($l2['children'] as $l3): ?>
                                                <span class="badge bg-light text-dark border me-1 mb-1 fw-normal">
                                                    <?= e($l3['name']) ?>
                                                    <?php if (!$l3['is_active']): ?>
                                                    <span class="text-muted small">(inactive)</span>
                                                    <?php endif; ?>
                                                </span>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex gap-1 flex-shrink-0">
                                            <a href="/pages/admin/categories/edit.php?id=<?= $l2['id'] ?>" class="btn btn-xs btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="/pages/admin/categories/add.php?parent_id=<?= $l2['id'] ?>" class="btn btn-xs btn-outline-success" title="Add child">
                                                <i class="bi bi-plus"></i>
                                            </a>
                                            <button class="btn btn-xs btn-outline-danger btn-delete-cat"
                                                    data-id="<?= $l2['id'] ?>" data-name="<?= e($l2['name']) ?>" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="ps-5 py-3 text-muted small">No sub-categories yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Delete Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteCatName"></strong>?</p>
                <p class="text-muted small">This will soft-delete the category. If products are assigned or children exist, deletion will be blocked.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<style>
.btn-xs { padding: .15rem .4rem; font-size: .75rem; }
</style>
<script>
(function () {
    const csrfToken = <?= json_encode(csrfToken()) ?>;
    let deleteCatId = null;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));

    document.querySelectorAll('.btn-delete-cat').forEach(btn => {
        btn.addEventListener('click', () => {
            deleteCatId = btn.dataset.id;
            document.getElementById('deleteCatName').textContent = btn.dataset.name;
            modal.show();
        });
    });

    document.getElementById('confirmDelete').addEventListener('click', () => {
        if (!deleteCatId) return;
        const fd = new FormData();
        fd.append('id', deleteCatId);
        fd.append('_csrf_token', csrfToken);
        fetch('/api/categories.php?action=delete', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                modal.hide();
                if (res.success) {
                    location.reload();
                } else {
                    alert('Cannot delete: ' + res.message);
                }
            });
    });
})();
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
