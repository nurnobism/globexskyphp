<?php
/**
 * pages/cms/index.php — CMS Pages List (Admin only)
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

if (!isAdmin()) {
    flashMessage('danger', 'Access denied.');
    redirect('/');
}

$db   = getDB();
$page = max(1, (int)get('page', 1));

$sql    = 'SELECT bp.*, u.first_name, u.last_name
           FROM blog_posts bp
           JOIN users u ON u.id = bp.author_id
           ORDER BY bp.created_at DESC';
$result = paginate($db, $sql, [], $page, 20);
$posts  = $result['data'] ?? [];

$pageTitle = 'CMS — Content Pages';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-file-richtext text-primary me-2"></i>Content Pages</h3>
            <p class="text-muted small mb-0">Manage CMS blog posts and static pages</p>
        </div>
        <a href="/pages/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Admin Dashboard
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($posts)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-file-earmark-text display-3"></i>
                <p class="mt-3">No content pages found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Status</th>
                            <th>Views</th>
                            <th>Published</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($posts as $post): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e(mb_strimwidth($post['title'], 0, 60, '…')) ?></div>
                            <small class="text-muted"><?= e($post['slug']) ?></small>
                        </td>
                        <td class="small"><?= e($post['first_name'] . ' ' . $post['last_name']) ?></td>
                        <td>
                            <?php
                            $sCls = ['published' => 'bg-success', 'draft' => 'bg-warning text-dark', 'archived' => 'bg-secondary'];
                            $cls  = $sCls[$post['status']] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?= $cls ?>"><?= ucfirst(e($post['status'])) ?></span>
                        </td>
                        <td class="small"><?= number_format((int)($post['view_count'] ?? 0)) ?></td>
                        <td class="small text-muted">
                            <?= !empty($post['published_at']) ? date('M j, Y', strtotime($post['published_at'])) : '—' ?>
                        </td>
                        <td>
                            <a href="/pages/cms/edit.php?id=<?= (int)$post['id'] ?>"
                               class="btn btn-sm btn-outline-primary me-1">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <?php if (!empty($post['slug'])): ?>
                            <a href="/pages/blog/post.php?slug=<?= urlencode($post['slug']) ?>"
                               target="_blank" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if (($result['total_pages'] ?? 1) > 1): ?>
            <div class="p-3">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <?php for ($p = 1; $p <= $result['total_pages']; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
