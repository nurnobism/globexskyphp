<?php
/**
 * pages/api/webhooks.php — Webhook Management Page
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db     = getDB();
$userId = $_SESSION['user_id'];

const ALL_EVENTS = [
    'order.created', 'order.updated', 'order.shipped', 'order.delivered', 'order.cancelled',
    'product.created', 'product.updated', 'product.deleted', 'product.stock_low',
    'payment.completed', 'payment.failed', 'payment.refunded',
    'user.registered', 'user.updated',
    'review.created',
    'dropship.order_created', 'dropship.order_shipped',
];

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $sub = $_POST['_sub'] ?? '';

    if ($sub === 'create') {
        $url    = trim($_POST['url'] ?? '');
        $events = $_POST['events'] ?? [];
        if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
            $errors[] = 'Webhook URL must be a valid HTTPS URL.';
        }
        if (!$events) {
            $errors[] = 'Select at least one event.';
        }
        if (!$errors) {
            $secret = bin2hex(random_bytes(24));
            $db->prepare(
                'INSERT INTO webhooks (user_id, url, secret, events, is_active) VALUES (?, ?, ?, ?, 1)'
            )->execute([$userId, $url, $secret, json_encode($events)]);
            $success = 'Webhook created! Secret: <code>' . htmlspecialchars($secret, ENT_QUOTES) . '</code> — store it securely.';
        }
    } elseif ($sub === 'delete') {
        $id = (int)($_POST['hook_id'] ?? 0);
        $db->prepare('DELETE FROM webhooks WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
        flashMessage('success', 'Webhook deleted.');
        redirect('/pages/api/webhooks.php');
    } elseif ($sub === 'toggle') {
        $id = (int)($_POST['hook_id'] ?? 0);
        $db->prepare('UPDATE webhooks SET is_active = 1 - is_active WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
        redirect('/pages/api/webhooks.php');
    }
}

try {
    $stmt = $db->prepare(
        'SELECT id, url, events, is_active, last_triggered_at, success_count, failure_count, created_at
         FROM webhooks WHERE user_id = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$userId]);
    $hooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dbOk  = true;
} catch (PDOException $e) {
    $hooks = [];
    $dbOk  = false;
}

$pageTitle = 'Webhooks';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-2 col-md-3">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white"><i class="bi bi-code-slash"></i> API Platform</div>
                <div class="list-group list-group-flush">
                    <a href="<?= APP_URL ?>/pages/api/index.php" class="list-group-item list-group-item-action"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    <a href="<?= APP_URL ?>/pages/api/keys.php" class="list-group-item list-group-item-action"><i class="bi bi-key"></i> API Keys</a>
                    <a href="<?= APP_URL ?>/pages/api/docs.php" class="list-group-item list-group-item-action"><i class="bi bi-book"></i> Documentation</a>
                    <a href="<?= APP_URL ?>/pages/api/logs.php" class="list-group-item list-group-item-action"><i class="bi bi-list-ul"></i> Request Logs</a>
                    <a href="<?= APP_URL ?>/pages/api/usage.php" class="list-group-item list-group-item-action"><i class="bi bi-bar-chart"></i> Usage Analytics</a>
                    <a href="<?= APP_URL ?>/pages/api/webhooks.php" class="list-group-item list-group-item-action active"><i class="bi bi-arrow-repeat"></i> Webhooks</a>
                </div>
            </div>
        </div>

        <div class="col-lg-10 col-md-9">
            <h1 class="h3 fw-bold mb-4"><i class="bi bi-arrow-repeat text-primary"></i> Webhooks</h1>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger"><?= e($err) ?></div>
            <?php endforeach; ?>

            <!-- Create Webhook -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold" data-bs-toggle="collapse" data-bs-target="#createWebhookForm" style="cursor:pointer">
                    <i class="bi bi-plus-circle text-primary"></i> Register New Webhook
                </div>
                <div class="collapse" id="createWebhookForm">
                    <div class="card-body">
                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="_sub" value="create">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Endpoint URL (HTTPS required) <span class="text-danger">*</span></label>
                                    <input type="url" name="url" class="form-control" placeholder="https://yourdomain.com/webhook" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Subscribe to Events <span class="text-danger">*</span></label>
                                    <div class="row g-2">
                                        <?php foreach (ALL_EVENTS as $event): ?>
                                        <div class="col-md-4 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="events[]"
                                                       id="ev_<?= str_replace('.', '_', $event) ?>" value="<?= $event ?>">
                                                <label class="form-check-label small" for="ev_<?= str_replace('.', '_', $event) ?>">
                                                    <code><?= e($event) ?></code>
                                                </label>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus-lg"></i> Register Webhook
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Webhooks list -->
            <?php if (!$dbOk): ?>
                <div class="alert alert-warning">Webhook tables not initialized.</div>
            <?php elseif ($hooks): ?>
                <?php foreach ($hooks as $hook): ?>
                <?php $events = json_decode($hook['events'], true) ?? []; ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <code class="small"><?= e($hook['url']) ?></code>
                            <?php if ($hook['is_active']): ?>
                                <span class="badge bg-success ms-2">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary ms-2">Paused</span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="_sub" value="toggle">
                                <input type="hidden" name="hook_id" value="<?= $hook['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <?= $hook['is_active'] ? 'Pause' : 'Activate' ?>
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="_sub" value="delete">
                                <input type="hidden" name="hook_id" value="<?= $hook['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete this webhook?')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body py-2">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($events as $ev): ?>
                                        <span class="badge bg-light text-dark border"><code class="small"><?= e($ev) ?></code></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-end small text-muted">
                                <div>✅ <?= (int)$hook['success_count'] ?> success / ❌ <?= (int)$hook['failure_count'] ?> failed</div>
                                <?php if ($hook['last_triggered_at']): ?>
                                    <div>Last triggered: <?= date('M d, H:i', strtotime($hook['last_triggered_at'])) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">No webhooks registered yet. Create one above.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
