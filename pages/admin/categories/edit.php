<?php
/**
 * pages/admin/categories/edit.php — Edit Category Form
 * PR #4: 3-Level Hierarchical Category System
 */

require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();
require_once __DIR__ . '/../../../includes/categories.php';

$id  = (int)get('id', 0);
if (!$id) redirect('/pages/admin/categories/index.php');

$cat = getCategory($id);
if (!$cat) redirect('/pages/admin/categories/index.php');

$breadcrumb    = getCategoryBreadcrumb($id);
$productCount  = getProductCount($id, true);
$error         = '';
$successMsg    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        try {
            updateCategory($id, $_POST);
            $successMsg = 'Category updated successfully.';
            $cat = getCategory($id); // refresh
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

// Build parent options for the level of this category
$parentOptions = [];
$currentLevel  = (int)$cat['level'];
if ($currentLevel === 2) {
    $parentOptions = getRootCategories();
} elseif ($currentLevel === 3) {
    $db = getDB();
    $stmt = $db->query('SELECT id, name FROM categories WHERE level = 2 AND is_active = 1 ORDER BY name ASC');
    $parentOptions = $stmt->fetchAll();
}

$pageTitle = 'Admin — Edit Category: ' . $cat['name'];
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4" style="max-width:720px">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold">
            <i class="bi bi-pencil-square text-primary me-2"></i>
            Edit Category
            <span class="badge bg-<?= ['','primary','info','secondary'][$currentLevel] ?? 'secondary' ?> ms-2">Level <?= $currentLevel ?></span>
        </h3>
        <a href="/pages/admin/categories/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/pages/admin/categories/index.php">Categories</a></li>
            <?php foreach ($breadcrumb as $i => $crumb): ?>
            <li class="breadcrumb-item <?= $i === count($breadcrumb) - 1 ? 'active' : '' ?>">
                <?php if ($i < count($breadcrumb) - 1): ?>
                <a href="/pages/admin/categories/edit.php?id=<?= $crumb['id'] ?>"><?= e($crumb['name']) ?></a>
                <?php else: ?>
                <?= e($crumb['name']) ?>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ol>
    </nav>

    <!-- Stats row -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-primary"><?= $productCount ?></div>
                <div class="text-muted small">Products (incl. subcategories)</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-info"><?= $currentLevel ?></div>
                <div class="text-muted small">Hierarchy Level</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold <?= $cat['is_active'] ? 'text-success' : 'text-secondary' ?>">
                    <?= $cat['is_active'] ? 'Active' : 'Inactive' ?>
                </div>
                <div class="text-muted small">Status</div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($successMsg): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= e($successMsg) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST">
                <?= csrfField() ?>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="catName" class="form-control"
                           value="<?= e($_POST['name'] ?? $cat['name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Slug</label>
                    <input type="text" name="slug" id="catSlug" class="form-control"
                           value="<?= e($_POST['slug'] ?? $cat['slug']) ?>">
                </div>

                <?php if (!empty($parentOptions)): ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        Parent Category (Level <?= $currentLevel - 1 ?>) <span class="text-danger">*</span>
                    </label>
                    <select name="parent_id" class="form-select" required>
                        <option value="">— Select parent —</option>
                        <?php foreach ($parentOptions as $po): ?>
                        <option value="<?= $po['id'] ?>"
                                <?= (int)$cat['parent_id'] === (int)$po['id'] ? 'selected' : '' ?>>
                            <?= e($po['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Changing parent cannot create a Level 4+ category.</div>
                </div>
                <?php endif; ?>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Icon Class</label>
                        <div class="input-group">
                            <span class="input-group-text" id="iconPreview">
                                <i class="bi <?= e($cat['icon'] ?? 'bi-tag') ?>"></i>
                            </span>
                            <input type="text" name="icon" id="catIcon" class="form-control"
                                   value="<?= e($_POST['icon'] ?? $cat['icon'] ?? '') ?>"
                                   placeholder="bi-laptop">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" min="0"
                               value="<?= e($_POST['sort_order'] ?? $cat['sort_order']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="is_active" class="form-select">
                            <option value="1" <?= (int)($cat['is_active']) ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= !(int)($cat['is_active']) ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Commission Rate Override (%)</label>
                    <div class="input-group" style="max-width:200px">
                        <input type="number" name="commission_rate" class="form-control" min="0" max="100" step="0.01"
                               value="<?= e($_POST['commission_rate'] ?? ($cat['commission_rate'] ?? '')) ?>"
                               placeholder="Platform default">
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Leave blank to use platform default.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= e($_POST['description'] ?? $cat['description'] ?? '') ?></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Save Changes
                    </button>
                    <a href="/pages/admin/categories/index.php" class="btn btn-outline-secondary">Cancel</a>
                    <?php if ($currentLevel < 3): ?>
                    <a href="/pages/admin/categories/add.php?parent_id=<?= $id ?>" class="btn btn-outline-success ms-auto">
                        <i class="bi bi-plus me-1"></i>Add Sub-Category
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('catIcon').addEventListener('input', function () {
    const preview = document.getElementById('iconPreview').querySelector('i');
    preview.className = 'bi ' + this.value.trim();
});
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
