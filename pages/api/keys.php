<?php
/**
 * pages/api/keys.php — API Key Management
 */
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/api-auth.php';
require_once __DIR__ . '/../../includes/api-response.php';
requireAuth();

$db     = getDB();
$userId = $_SESSION['user_id'];
$msg    = '';
$newKey = null;
$errors = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $sub = $_POST['_sub'] ?? '';

    if ($sub === 'create') {
        $name        = trim($_POST['name'] ?? '');
        $environment = in_array($_POST['environment'] ?? '', ['live', 'test'], true) ? $_POST['environment'] : 'live';
        $permissions = $_POST['permissions'] ?? [];
        $ipWhitelist = trim($_POST['ip_whitelist'] ?? '');

        if (!$name) {
            $errors[] = 'Key name is required.';
        } else {
            try {
                $result = generateApiKey($userId, $name, $environment, $permissions, $ipWhitelist);
                $newKey = $result['key'];   // Shown ONCE
                $msg    = 'success';
            } catch (PDOException $e) {
                $errors[] = 'Failed to create key.';
            }
        }
    } elseif ($sub === 'revoke') {
        $keyId = (int)($_POST['key_id'] ?? 0);
        if (revokeApiKey($keyId, $userId)) {
            flashMessage('success', 'API key revoked.');
        }
        redirect('/pages/api/keys.php');
    } elseif ($sub === 'rotate') {
        $keyId  = (int)($_POST['key_id'] ?? 0);
        $result = rotateApiKey($keyId, $userId);
        if ($result) {
            $newKey = $result['key'];
            $msg    = 'rotated';
        }
    }
}

// Fetch keys
try {
    $stmt = $db->prepare(
        'SELECT id, name, key_prefix, environment, permissions, ip_whitelist,
                is_active, last_used_at, requests_today, rate_limit_per_day, created_at, revoked_at
         FROM api_keys WHERE user_id = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$userId]);
    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $keys = [];
}

$allResources = ['products', 'orders', 'cart', 'reviews', 'shipping', 'dropship', 'webhooks', 'users'];

$pageTitle = 'API Keys';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-2 col-md-3">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white"><i class="bi bi-code-slash"></i> API Platform</div>
                <div class="list-group list-group-flush">
                    <a href="<?= APP_URL ?>/pages/api/index.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a href="<?= APP_URL ?>/pages/api/keys.php" class="list-group-item list-group-item-action active">
                        <i class="bi bi-key"></i> API Keys
                    </a>
                    <a href="<?= APP_URL ?>/pages/api/docs.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-book"></i> Documentation
                    </a>
                    <a href="<?= APP_URL ?>/pages/api/logs.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-list-ul"></i> Request Logs
                    </a>
                    <a href="<?= APP_URL ?>/pages/api/usage.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-bar-chart"></i> Usage Analytics
                    </a>
                    <a href="<?= APP_URL ?>/pages/api/webhooks.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-arrow-repeat"></i> Webhooks
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-10 col-md-9">
            <h1 class="h3 fw-bold mb-4"><i class="bi bi-key text-primary"></i> API Keys</h1>

            <?php if ($newKey): ?>
                <div class="alert alert-success border-0 shadow-sm">
                    <h5 class="fw-bold"><i class="bi bi-check-circle"></i> Key <?= $msg === 'rotated' ? 'Rotated' : 'Created' ?> Successfully!</h5>
                    <p class="mb-2">⚠️ <strong>Copy this key now — it will never be shown again.</strong></p>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace" id="newKeyDisplay" value="<?= e($newKey) ?>" readonly>
                        <button class="btn btn-outline-success" onclick="navigator.clipboard.writeText('<?= e($newKey) ?>');this.textContent='Copied!'">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger"><?= e($err) ?></div>
            <?php endforeach; ?>

            <!-- Create Key Form -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold" data-bs-toggle="collapse" data-bs-target="#createKeyForm" style="cursor:pointer">
                    <i class="bi bi-plus-circle text-primary"></i> Create New API Key
                </div>
                <div class="collapse" id="createKeyForm">
                    <div class="card-body">
                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="_sub" value="create">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Key Name / Label <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" placeholder="e.g. My Shopify Store" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Environment</label>
                                    <select name="environment" class="form-select">
                                        <option value="live">Live</option>
                                        <option value="test">Test</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fw-semibold">IP Whitelist (optional)</label>
                                    <input type="text" name="ip_whitelist" class="form-control" placeholder="192.168.1.1, 10.0.0.1">
                                    <div class="form-text">Comma-separated IPs. Leave blank to allow all.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Permissions</label>
                                    <div class="row g-2">
                                        <?php foreach ($allResources as $res): ?>
                                        <div class="col-md-3 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="permissions[]"
                                                       id="perm_<?= $res ?>" value="<?= $res ?>" checked>
                                                <label class="form-check-label" for="perm_<?= $res ?>">
                                                    <?= ucfirst($res) ?>
                                                </label>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-key"></i> Generate API Key
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Keys Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Your API Keys</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Key Prefix</th>
                                <th>Environment</th>
                                <th>Req Today</th>
                                <th>Rate Limit/Day</th>
                                <th>Last Used</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($keys): ?>
                                <?php foreach ($keys as $key): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($key['name']) ?></td>
                                    <td><code class="small"><?= e($key['key_prefix']) ?></code></td>
                                    <td>
                                        <span class="badge bg-<?= $key['environment'] === 'live' ? 'success' : 'secondary' ?>">
                                            <?= e($key['environment']) ?>
                                        </span>
                                    </td>
                                    <td><?= (int)$key['requests_today'] ?></td>
                                    <td><?= $key['rate_limit_per_day'] ? number_format($key['rate_limit_per_day']) : '∞' ?></td>
                                    <td class="small text-muted"><?= $key['last_used_at'] ? date('M d, H:i', strtotime($key['last_used_at'])) : 'Never' ?></td>
                                    <td>
                                        <?php if ($key['is_active'] && !$key['revoked_at']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($key['revoked_at'] && strtotime($key['revoked_at']) > time()): ?>
                                            <span class="badge bg-warning text-dark">Grace Period</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Revoked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($key['is_active']): ?>
                                            <form method="POST" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="_sub" value="rotate">
                                                <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                                                <button type="submit" class="btn btn-xs btn-outline-warning btn-sm"
                                                        onclick="return confirm('Rotate this key? The old key will expire in 24 hours.')">
                                                    <i class="bi bi-arrow-clockwise"></i> Rotate
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="_sub" value="revoke">
                                                <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                                                <button type="submit" class="btn btn-xs btn-outline-danger btn-sm"
                                                        onclick="return confirm('Revoke this key? This cannot be undone.')">
                                                    <i class="bi bi-trash"></i> Revoke
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No API keys yet. Create one above.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
