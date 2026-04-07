<?php
/**
 * pages/admin/categories/add.php — Add Category Form
 * PR #4: 3-Level Hierarchical Category System
 */

require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();
require_once __DIR__ . '/../../../includes/categories.php';

$parentId  = get('parent_id', '') !== '' ? (int)get('parent_id', 0) : null;
$parent    = $parentId ? getCategory($parentId) : null;

// Determine what level we're creating
$newLevel  = $parent ? (int)$parent['level'] + 1 : 1;

if ($newLevel > 3) {
    redirect('/pages/admin/categories/index.php');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        try {
            $id = createCategory($_POST);
            redirect('/pages/admin/categories/index.php?created=' . $id);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

// Level 1 categories for parent dropdown (L2 form needs to pick an L1 parent)
$rootCategories = getRootCategories();
// Level 2 categories for parent dropdown (L3 form picks an L2)
$level2Categories = $parentId && $parent && (int)$parent['level'] === 1 ? getChildren($parentId) : [];

$pageTitle = 'Admin — Add Category (Level ' . $newLevel . ')';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4" style="max-width:720px">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold">
            <i class="bi bi-folder-plus text-primary me-2"></i>
            Add Category
            <span class="badge bg-<?= ['','primary','info','secondary'][$newLevel] ?? 'secondary' ?> ms-2">Level <?= $newLevel ?></span>
        </h3>
        <a href="/pages/admin/categories/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    <?php if ($parent): ?>
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/pages/admin/categories/index.php">Categories</a></li>
            <?php foreach (getCategoryBreadcrumb($parent['id']) as $crumb): ?>
            <li class="breadcrumb-item"><?= e($crumb['name']) ?></li>
            <?php endforeach; ?>
            <li class="breadcrumb-item active">New</li>
        </ol>
    </nav>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" id="addCategoryForm">
                <?= csrfField() ?>
                <input type="hidden" name="parent_id" value="<?= $parentId !== null ? $parentId : '' ?>">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="catName" class="form-control"
                           value="<?= e($_POST['name'] ?? '') ?>" required
                           placeholder="e.g. Smartphones">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Slug</label>
                    <input type="text" name="slug" id="catSlug" class="form-control"
                           value="<?= e($_POST['slug'] ?? '') ?>"
                           placeholder="auto-generated from name">
                    <div class="form-text">Leave blank to auto-generate from the name.</div>
                </div>

                <?php if ($newLevel === 1): ?>
                <!-- For L1, no parent — already set as NULL by hidden field -->
                <?php elseif ($newLevel === 2): ?>
                <!-- Parent = one of the root categories -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Parent Category (Level 1) <span class="text-danger">*</span></label>
                    <select name="parent_id" class="form-select" required>
                        <option value="">— Select Level 1 parent —</option>
                        <?php foreach ($rootCategories as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= (string)($parentId ?? '') === (string)$r['id'] ? 'selected' : '' ?>>
                            <?= e($r['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <!-- L3: parent must be an L2 category -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Parent Category (Level 2) <span class="text-danger">*</span></label>
                    <select name="parent_id" class="form-select" required>
                        <option value="">— Select Level 2 parent —</option>
                        <?php
                        $db = getDB();
                        $l2s = $db->query('SELECT id, name FROM categories WHERE level = 2 AND is_active = 1 ORDER BY name ASC')->fetchAll();
                        foreach ($l2s as $l2):
                        ?>
                        <option value="<?= $l2['id'] ?>" <?= (string)($parentId ?? '') === (string)$l2['id'] ? 'selected' : '' ?>>
                            <?= e($l2['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Icon Class</label>
                        <div class="input-group">
                            <span class="input-group-text" id="iconPreview"><i class="bi bi-tag"></i></span>
                            <input type="text" name="icon" id="catIcon" class="form-control"
                                   value="<?= e($_POST['icon'] ?? '') ?>"
                                   placeholder="bi-laptop (Bootstrap Icons)">
                        </div>
                        <div class="form-text">Bootstrap Icons class, e.g. <code>bi-laptop</code></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" min="0"
                               value="<?= e($_POST['sort_order'] ?? '0') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="is_active" class="form-select">
                            <option value="1" <?= ($_POST['is_active'] ?? '1') === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= ($_POST['is_active'] ?? '1') === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <?php if ($newLevel === 1): ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Commission Rate Override (%)</label>
                    <div class="input-group" style="max-width:200px">
                        <input type="number" name="commission_rate" class="form-control" min="0" max="100" step="0.01"
                               value="<?= e($_POST['commission_rate'] ?? '') ?>"
                               placeholder="e.g. 8.00">
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Leave blank to use platform default.</div>
                </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="3"
                              placeholder="Optional category description"><?= e($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Create Category
                    </button>
                    <a href="/pages/admin/categories/index.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-generate slug from name
document.getElementById('catName').addEventListener('input', function () {
    const slugField = document.getElementById('catSlug');
    if (slugField.dataset.manual) return;
    slugField.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
});
document.getElementById('catSlug').addEventListener('input', function () {
    this.dataset.manual = '1';
});

// Live icon preview
document.getElementById('catIcon').addEventListener('input', function () {
    const preview = document.getElementById('iconPreview').querySelector('i');
    preview.className = 'bi ' + this.value.trim();
});
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
