<?php
require_once __DIR__ . '/../../includes/middleware.php';
$db = getDB();

$page     = max(1, (int)(get('page', 1)));
$category = get('category', '');
$perPage  = 9;
$offset   = ($page - 1) * $perPage;

// Fetch categories
$categories = $db->query("SELECT DISTINCT category FROM blog_posts WHERE status='published' AND category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Count total
$countSql    = "SELECT COUNT(*) FROM blog_posts WHERE status='published'" . ($category !== '' ? " AND category=?" : '');
$countStmt   = $db->prepare($countSql);
$countStmt->execute($category !== '' ? [$category] : []);
$totalPosts  = (int)$countStmt->fetchColumn();
$totalPages  = max(1, (int)ceil($totalPosts / $perPage));

// Fetch posts
$sql  = "SELECT bp.*, u.name AS author_name FROM blog_posts bp LEFT JOIN users u ON u.id = bp.author_id WHERE bp.status='published'";
$params = [];
if ($category !== '') {
    $sql .= ' AND bp.category = ?';
    $params[] = $category;
}
$sql .= ' ORDER BY bp.created_at DESC LIMIT ? OFFSET ?';
$params[] = $perPage;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$pageTitle = 'Blog';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-5">
    <!-- Page Header -->
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold mb-2">GlobexSky Blog</h1>
        <p class="text-muted lead mb-0">Insights, guides and news for B2B trade professionals</p>
    </div>

    <!-- Category Filter -->
    <?php if (!empty($categories)): ?>
    <div class="d-flex flex-wrap gap-2 justify-content-center mb-5">
        <a href="index.php" class="btn btn-sm <?= $category === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            All Posts
        </a>
        <?php foreach ($categories as $cat): ?>
        <a href="index.php?category=<?= urlencode($cat) ?><?= $page > 1 ? '&page=' . $page : '' ?>"
           class="btn btn-sm <?= $category === $cat ? 'btn-primary' : 'btn-outline-secondary' ?> text-capitalize">
            <?= e($cat) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Blog Grid -->
    <?php if (empty($posts)): ?>
        <div class="text-center py-5">
            <i class="bi bi-journal-text display-4 text-muted d-block mb-3"></i>
            <h5 class="text-muted">No posts yet</h5>
            <p class="text-muted small">Check back soon for new content.</p>
        </div>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">
        <?php foreach ($posts as $post): ?>
        <div class="col">
            <div class="card h-100 shadow-sm border-0 hover-shadow">
                <!-- Featured Image Placeholder -->
                <div class="ratio ratio-16x9 bg-light rounded-top overflow-hidden">
                    <?php if (!empty($post['featured_image'])): ?>
                        <img src="<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>"
                             class="w-100 h-100 object-fit-cover">
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center bg-gradient text-white-50"
                             style="background: linear-gradient(135deg,#e9ecef,#dee2e6);">
                            <i class="bi bi-image fs-1 text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-body d-flex flex-column p-4">
                    <!-- Category Badge -->
                    <?php if (!empty($post['category'])): ?>
                        <div class="mb-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary text-capitalize">
                                <?= e($post['category']) ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Title -->
                    <h5 class="card-title fw-bold mb-2">
                        <a href="post.php?id=<?= (int)$post['id'] ?>" class="text-decoration-none text-dark stretched-link">
                            <?= e($post['title']) ?>
                        </a>
                    </h5>

                    <!-- Excerpt -->
                    <?php $excerpt = $post['excerpt'] ?? (strlen($post['content'] ?? '') > 140 ? substr(strip_tags($post['content']), 0, 140) . '…' : strip_tags($post['content'] ?? '')); ?>
                    <p class="card-text text-muted small flex-grow-1"><?= e($excerpt) ?></p>

                    <!-- Meta -->
                    <div class="d-flex align-items-center justify-content-between mt-3 pt-3 border-top small text-muted">
                        <span><i class="bi bi-person-circle me-1"></i><?= e($post['author_name'] ?? 'GlobexSky') ?></span>
                        <span><i class="bi bi-calendar3 me-1"></i><?= !empty($post['created_at']) ? date('M j, Y', strtotime($post['created_at'])) : '' ?></span>
                    </div>
                </div>

                <div class="card-footer bg-white border-top-0 px-4 pb-4">
                    <a href="post.php?id=<?= (int)$post['id'] ?>" class="btn btn-sm btn-outline-primary w-100 position-relative">
                        Read More <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Blog pagination" class="d-flex justify-content-center">
        <ul class="pagination">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="index.php?page=<?= $page - 1 ?><?= $category !== '' ? '&category=' . urlencode($category) : '' ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="index.php?page=<?= $p ?><?= $category !== '' ? '&category=' . urlencode($category) : '' ?>">
                    <?= $p ?>
                </a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="index.php?page=<?= $page + 1 ?><?= $category !== '' ? '&category=' . urlencode($category) : '' ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.hover-shadow { transition: box-shadow .2s; }
.hover-shadow:hover { box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.12) !important; }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
