<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db = getDB();

// Stats
$modelCount  = (int)$db->query("SELECT COUNT(*) FROM ai_models WHERE is_active = 1")->fetchColumn();
$logCount    = (int)$db->query("SELECT COUNT(*) FROM ai_usage_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$totalTokens = (int)$db->query("SELECT COALESCE(SUM(tokens_used),0) FROM ai_usage_logs WHERE MONTH(created_at) = MONTH(NOW())")->fetchColumn();
$trainCount  = (int)$db->query("SELECT COUNT(*) FROM ai_training_data")->fetchColumn();

$pageTitle = 'AI Management';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-robot text-primary me-2"></i>AI Management Dashboard</h3>
        <a href="/pages/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Admin Dashboard
        </a>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <?php $cards = [
            ['Active Models',       $modelCount,                   'cpu',               'primary'],
            ['Requests Today',      number_format($logCount),      'activity',          'success'],
            ['Tokens This Month',   number_format($totalTokens),   'file-binary',       'warning'],
            ['Training Datasets',   $trainCount,                   'database-fill',     'info'],
        ]; ?>
        <?php foreach ($cards as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-<?= $color ?> bg-opacity-10 p-3">
                        <i class="bi bi-<?= $icon ?> text-<?= $color ?> fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?= $value ?></h5>
                        <small class="text-muted"><?= $label ?></small>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Quick Links -->
    <div class="row g-3">
        <?php $links = [
            ['/pages/admin/ai/models.php',   'cpu',          'primary',   'AI Models',         'Manage AI model configurations'],
            ['/pages/admin/ai/training.php',  'database-fill','success',   'Training Data',     'Manage training datasets'],
            ['/pages/admin/ai/logs.php',      'activity',     'warning',   'Usage Logs',        'View AI usage analytics'],
            ['/pages/admin/ai/settings.php',  'gear-fill',    'secondary', 'AI Settings',       'Configure AI features'],
        ]; ?>
        <?php foreach ($links as [$url, $icon, $color, $title, $desc]): ?>
        <div class="col-md-3">
            <a href="<?= $url ?>" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body text-center py-4">
                    <i class="bi bi-<?= $icon ?> text-<?= $color ?> display-5"></i>
                    <h6 class="mt-2 fw-bold text-dark"><?= $title ?></h6>
                    <small class="text-muted"><?= $desc ?></small>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
