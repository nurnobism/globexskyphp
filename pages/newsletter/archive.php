<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();

$newsletters = [];
try {
    $newsletters = $db->query("
        SELECT * FROM newsletters WHERE status = 'sent' ORDER BY sent_at DESC
    ")->fetchAll();
} catch (\Exception $e) {
    $newsletters = [];
}

$pageTitle = 'Newsletter Archive';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-archive me-2"></i><?= e($pageTitle) ?></h1>
            <p class="text-muted mb-0">Browse all past newsletters</p>
        </div>
        <a href="/pages/newsletter/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Newsletter</a>
    </div>

    <?php if (empty($newsletters)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-archive display-1 text-muted"></i>
                <p class="text-muted mt-3 mb-0">No archived newsletters yet. Check back later!</p>
            </div>
        </div>
    <?php else: ?>
        <div class="accordion" id="newsletterArchive">
            <?php foreach ($newsletters as $i => $nl): ?>
                <div class="accordion-item border-0 shadow-sm mb-3 rounded" id="newsletter-<?= (int)$nl['id'] ?>">
                    <h2 class="accordion-header">
                        <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?> rounded" type="button" data-bs-toggle="collapse" data-bs-target="#nl-content-<?= (int)$nl['id'] ?>">
                            <div class="me-3">
                                <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex p-2">
                                    <i class="bi bi-envelope-fill text-primary"></i>
                                </div>
                            </div>
                            <div>
                                <strong><?= e($nl['title']) ?></strong>
                                <br>
                                <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= formatDate($nl['sent_at'] ?? $nl['created_at']) ?></small>
                            </div>
                        </button>
                    </h2>
                    <div id="nl-content-<?= (int)$nl['id'] ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#newsletterArchive">
                        <div class="accordion-body">
                            <?php if (!empty($nl['excerpt'])): ?>
                                <p class="lead text-muted"><?= e($nl['excerpt']) ?></p>
                                <hr>
                            <?php endif; ?>
                            <div class="newsletter-content">
                                <?= nl2br(e($nl['content'] ?? 'Content not available.')) ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
