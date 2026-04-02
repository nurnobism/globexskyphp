<?php
/**
 * pages/cms/edit.php — Edit CMS Page (Admin only)
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

if (!isAdmin()) {
    flashMessage('danger', 'Access denied.');
    redirect('/');
}

$db = getDB();
$id = (int)get('id', 0);

if (!$id) {
    flashMessage('danger', 'Post ID is required.');
    redirect('/pages/cms/index.php');
}

$stmt = $db->prepare('SELECT * FROM blog_posts WHERE id = ?');
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    flashMessage('danger', 'Content page not found.');
    redirect('/pages/cms/index.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        flashMessage('danger', 'Invalid CSRF token.');
        redirect('/pages/cms/edit.php?id=' . $id);
    }

    $title       = trim(post('title', ''));
    $slug        = trim(post('slug', ''));
    $content     = post('content', '');
    $excerpt     = trim(post('excerpt', ''));
    $status      = post('status', 'draft');
    $publishedAt = $status === 'published' ? ($post['published_at'] ?: date('Y-m-d H:i:s')) : null;

    if (!$title || !$slug) {
        flashMessage('danger', 'Title and slug are required.');
        redirect('/pages/cms/edit.php?id=' . $id);
    }

    $db->prepare(
        'UPDATE blog_posts SET title=?, slug=?, content=?, excerpt=?, status=?, published_at=?, updated_at=NOW() WHERE id=?'
    )->execute([$title, $slug, $content, $excerpt, $status, $publishedAt, $id]);

    flashMessage('success', 'Content page updated successfully.');
    redirect('/pages/cms/index.php');
}

$pageTitle = 'Edit: ' . $post['title'];
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/pages/admin/dashboard.php">Admin</a></li>
            <li class="breadcrumb-item"><a href="/pages/cms/index.php">CMS</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-pencil-square text-primary me-2"></i>Edit Content Page
            </h5>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="_csrf_token" value="<?= e(csrfToken()) ?>">

                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control"
                               value="<?= e($post['title']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                            <option value="archived" <?= $post['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text text-muted">/blog/</span>
                        <input type="text" name="slug" class="form-control"
                               value="<?= e($post['slug']) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Excerpt</label>
                    <textarea name="excerpt" class="form-control" rows="2"
                              placeholder="Short summary…"><?= e($post['excerpt'] ?? '') ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Content</label>
                    <textarea name="content" class="form-control" rows="16"
                              style="font-family:monospace"><?= e($post['content'] ?? '') ?></textarea>
                    <div class="form-text">HTML content is supported.</div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                    <a href="/pages/cms/index.php" class="btn btn-outline-secondary">Cancel</a>
                    <?php if (!empty($post['slug'])): ?>
                    <a href="/pages/blog/post.php?slug=<?= urlencode($post['slug']) ?>"
                       target="_blank" class="btn btn-outline-info ms-auto">
                        <i class="bi bi-eye me-1"></i>Preview
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
