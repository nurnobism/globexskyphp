<?php
require_once __DIR__ . '/../../includes/middleware.php';
$db = getDB();

$id = (int)(get('id', 0));
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare(
    "SELECT bp.*, u.name AS author_name
     FROM blog_posts bp
     LEFT JOIN users u ON u.id = bp.author_id
     WHERE bp.id = ? AND bp.status = 'published'"
);
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Post Not Found';
    include __DIR__ . '/../../includes/header.php';
    echo '<div class="container py-5 text-center">
            <i class="bi bi-journal-x display-3 text-muted mb-3 d-block"></i>
            <h2>Post Not Found</h2>
            <p class="text-muted">This blog post does not exist or has been removed.</p>
            <a href="index.php" class="btn btn-primary">Back to Blog</a>
          </div>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch comments
$cStmt = $db->prepare(
    "SELECT bc.*, u.name AS commenter_name
     FROM blog_comments bc
     LEFT JOIN users u ON u.id = bc.user_id
     WHERE bc.post_id = ? AND bc.status = 'approved'
     ORDER BY bc.created_at ASC"
);
$cStmt->execute([$id]);
$comments = $cStmt->fetchAll();

// Prev / Next posts
$prevStmt = $db->prepare("SELECT id, title FROM blog_posts WHERE status='published' AND created_at < ? ORDER BY created_at DESC LIMIT 1");
$prevStmt->execute([$post['created_at']]);
$prevPost = $prevStmt->fetch();

$nextStmt = $db->prepare("SELECT id, title FROM blog_posts WHERE status='published' AND created_at > ? ORDER BY created_at ASC LIMIT 1");
$nextStmt->execute([$post['created_at']]);
$nextPost = $nextStmt->fetch();

$isLoggedIn = isset($_SESSION['user_id']);
$pageTitle  = $post['title'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Blog</a></li>
                    <?php if (!empty($post['category'])): ?>
                    <li class="breadcrumb-item">
                        <a href="index.php?category=<?= urlencode($post['category']) ?>" class="text-capitalize">
                            <?= e($post['category']) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active text-truncate" style="max-width:200px;"><?= e($post['title']) ?></li>
                </ol>
            </nav>

            <!-- Featured Image Placeholder -->
            <div class="ratio ratio-16x9 rounded-3 overflow-hidden bg-light mb-4 shadow-sm">
                <?php if (!empty($post['featured_image'])): ?>
                    <img src="<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>"
                         class="w-100 h-100 object-fit-cover">
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center"
                         style="background:linear-gradient(135deg,#e9ecef,#dee2e6);">
                        <i class="bi bi-image display-1 text-muted"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Post Meta -->
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3 small text-muted">
                <?php if (!empty($post['category'])): ?>
                    <span class="badge bg-primary bg-opacity-10 text-primary text-capitalize fs-6">
                        <?= e($post['category']) ?>
                    </span>
                <?php endif; ?>
                <span><i class="bi bi-person-circle me-1"></i><?= e($post['author_name'] ?? 'GlobexSky') ?></span>
                <span><i class="bi bi-calendar3 me-1"></i><?= !empty($post['created_at']) ? date('F j, Y', strtotime($post['created_at'])) : '' ?></span>
                <span><i class="bi bi-chat-text me-1"></i><?= count($comments) ?> comment<?= count($comments) !== 1 ? 's' : '' ?></span>
            </div>

            <!-- Title -->
            <h1 class="display-6 fw-bold mb-3"><?= e($post['title']) ?></h1>

            <!-- Tags -->
            <?php if (!empty($post['tags'])): ?>
            <div class="d-flex flex-wrap gap-1 mb-4">
                <?php foreach (array_filter(array_map('trim', explode(',', $post['tags']))) as $tag): ?>
                    <span class="badge bg-light text-muted border">
                        <i class="bi bi-hash"></i><?= e($tag) ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Article Content -->
            <article class="blog-content lh-lg mb-5">
                <?= $post['content'] ?>
            </article>

            <hr class="my-5">

            <!-- Comments Section -->
            <section id="comments" class="mb-5">
                <h4 class="fw-bold mb-4">
                    <i class="bi bi-chat-square-text me-2 text-primary"></i>
                    Comments (<?= count($comments) ?>)
                </h4>

                <?php if (empty($comments)): ?>
                    <div class="text-center text-muted py-4 bg-light rounded-3">
                        <i class="bi bi-chat-dots display-4 d-block mb-2"></i>
                        <p class="mb-0">No comments yet. Be the first to comment!</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3 mb-4">
                        <?php foreach ($comments as $comment): ?>
                        <div class="card border-0 bg-light shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                                         style="width:38px;height:38px;font-size:.9rem;">
                                        <?= strtoupper(substr($comment['commenter_name'] ?? 'A', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold small"><?= e($comment['commenter_name'] ?? 'Anonymous') ?></div>
                                        <div class="text-muted" style="font-size:.75rem;">
                                            <?= !empty($comment['created_at']) ? date('M j, Y \a\t g:i a', strtotime($comment['created_at'])) : '' ?>
                                        </div>
                                    </div>
                                </div>
                                <p class="mb-0 text-dark"><?= e($comment['content'] ?? $comment['body'] ?? '') ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Add Comment -->
                <?php if ($isLoggedIn): ?>
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <span class="fw-semibold"><i class="bi bi-pencil-square me-2 text-primary"></i>Leave a Comment</span>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="/api/blog.php">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="add_comment">
                            <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                            <div class="mb-3">
                                <label for="comment-content" class="form-label fw-semibold">Your Comment</label>
                                <textarea id="comment-content" name="content" class="form-control" rows="4"
                                          placeholder="Share your thoughts…" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i> Post Comment
                            </button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-light border text-center">
                    <i class="bi bi-lock me-2 text-muted"></i>
                    <a href="/pages/auth/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">Log in</a>
                    to leave a comment.
                </div>
                <?php endif; ?>
            </section>

            <!-- Prev / Next Navigation -->
            <nav class="d-flex justify-content-between gap-3 pt-4 border-top">
                <div class="flex-grow-1" style="max-width:45%;">
                    <?php if ($prevPost): ?>
                    <a href="post.php?id=<?= (int)$prevPost['id'] ?>" class="text-decoration-none">
                        <div class="small text-muted mb-1"><i class="bi bi-arrow-left me-1"></i>Previous Post</div>
                        <div class="fw-semibold text-dark text-truncate"><?= e($prevPost['title']) ?></div>
                    </a>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1 text-end" style="max-width:45%;">
                    <?php if ($nextPost): ?>
                    <a href="post.php?id=<?= (int)$nextPost['id'] ?>" class="text-decoration-none">
                        <div class="small text-muted mb-1">Next Post <i class="bi bi-arrow-right ms-1"></i></div>
                        <div class="fw-semibold text-dark text-truncate"><?= e($nextPost['title']) ?></div>
                    </a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </div>
</div>

<style>
.blog-content p { margin-bottom: 1.25rem; }
.blog-content h2, .blog-content h3 { margin-top: 2rem; margin-bottom: 1rem; font-weight: 700; }
.blog-content img { max-width: 100%; border-radius: .5rem; }
.blog-content blockquote { border-left: 4px solid #0d6efd; padding-left: 1rem; color: #6c757d; font-style: italic; }
.blog-content pre { background: #f8f9fa; border-radius: .5rem; padding: 1rem; overflow-x: auto; }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
