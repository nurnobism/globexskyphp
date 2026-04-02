<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();

$isSubscribed = false;
if (isLoggedIn()) {
    $stmt = $db->prepare("SELECT * FROM newsletter_subscribers WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $isSubscribed = (bool)$stmt->fetch();
}

$newsletters = [];
try {
    $newsletters = $db->query("
        SELECT * FROM newsletters WHERE status = 'sent' ORDER BY sent_at DESC LIMIT 10
    ")->fetchAll();
} catch (\Exception $e) {
    $newsletters = [];
}

$pageTitle = 'Newsletter';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-envelope-open me-2"></i><?= e($pageTitle) ?></h1>
            <p class="text-muted mb-0">Stay updated with the latest news and offers</p>
        </div>
        <a href="/pages/newsletter/archive.php" class="btn btn-outline-primary"><i class="bi bi-archive me-1"></i>Full Archive</a>
    </div>

    <?php if (isLoggedIn()): ?>
        <!-- Subscription Status -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">
                            <?php if ($isSubscribed): ?>
                                <i class="bi bi-check-circle-fill text-success me-2"></i>You're subscribed!
                            <?php else: ?>
                                <i class="bi bi-envelope me-2"></i>Subscribe to our newsletter
                            <?php endif; ?>
                        </h5>
                        <p class="text-muted mb-0">
                            <?= $isSubscribed ? 'You\'ll receive our latest updates and offers.' : 'Get the latest news delivered to your inbox.' ?>
                        </p>
                    </div>
                    <form method="post" action="/api/newsletter.php?action=<?= $isSubscribed ? 'unsubscribe' : 'subscribe' ?>">
                        <?= csrfField() ?>
                        <?php if ($isSubscribed): ?>
                            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Unsubscribe from the newsletter?')">
                                <i class="bi bi-bell-slash me-1"></i>Unsubscribe
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-bell me-1"></i>Subscribe
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Public Subscribe Form -->
        <div class="card border-0 shadow-sm mb-4 bg-primary bg-opacity-10">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-1"><i class="bi bi-envelope-heart me-2"></i>Subscribe to Our Newsletter</h5>
                        <p class="text-muted mb-0">Enter your email to receive updates and exclusive offers.</p>
                    </div>
                    <div class="col-md-6">
                        <form method="post" action="/api/newsletter.php?action=subscribe_email" class="d-flex gap-2">
                            <?= csrfField() ?>
                            <input type="email" class="form-control" name="email" placeholder="your@email.com" required>
                            <button type="submit" class="btn btn-primary text-nowrap"><i class="bi bi-send me-1"></i>Subscribe</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recent Newsletters -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0"><i class="bi bi-newspaper me-2"></i>Recent Newsletters</h5>
        </div>
        <ul class="list-group list-group-flush">
            <?php if (empty($newsletters)): ?>
                <li class="list-group-item text-center text-muted py-5">
                    <i class="bi bi-envelope-open display-4 d-block mb-2"></i>
                    No newsletters published yet. Check back soon!
                </li>
            <?php else: ?>
                <?php foreach ($newsletters as $nl): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">
                                    <i class="bi bi-envelope-fill text-primary me-2"></i>
                                    <?= e($nl['title']) ?>
                                </h6>
                                <small class="text-muted">
                                    <i class="bi bi-calendar3 me-1"></i><?= formatDate($nl['sent_at'] ?? $nl['created_at']) ?>
                                </small>
                            </div>
                            <a href="/pages/newsletter/archive.php#newsletter-<?= (int)$nl['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-book me-1"></i>Read
                            </a>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
