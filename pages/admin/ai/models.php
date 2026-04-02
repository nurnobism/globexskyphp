<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db = getDB();
$models = $db->query("SELECT * FROM ai_models ORDER BY is_active DESC, name ASC")->fetchAll();

$pageTitle = 'AI Models';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-cpu text-primary me-2"></i>AI Models</h3>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModelModal">
                <i class="bi bi-plus-circle me-1"></i> Add Model
            </button>
            <a href="/pages/admin/ai/index.php" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <div class="row g-3">
        <?php if (empty($models)): ?>
        <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">No AI models configured yet.</div></div></div>
        <?php else: ?>
        <?php foreach ($models as $m): ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="fw-bold mb-0"><?= e($m['name']) ?></h6>
                        <span class="badge bg-<?= $m['is_active'] ? 'success' : 'secondary' ?>">
                            <?= $m['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    <p class="text-muted small mb-2"><?= e($m['provider'] ?? '') ?> — <?= e($m['model_id'] ?? '') ?></p>
                    <p class="text-muted small mb-3"><?= e($m['description'] ?? '') ?></p>
                    <div class="row g-2 text-center small mb-3">
                        <div class="col-6">
                            <div class="bg-light rounded p-2">
                                <strong><?= number_format((float)($m['cost_per_token'] ?? 0), 6) ?></strong>
                                <div class="text-muted">Cost/token</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light rounded p-2">
                                <strong><?= number_format((int)($m['max_tokens'] ?? 0)) ?></strong>
                                <div class="text-muted">Max tokens</div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="POST" action="/api/admin.php?action=toggle_ai_model">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                            <button class="btn btn-sm btn-outline-<?= $m['is_active'] ? 'secondary' : 'success' ?>">
                                <?= $m['is_active'] ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="addModelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add AI Model</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/admin.php?action=add_ai_model">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Display Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g., GPT-4 Turbo">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Provider *</label>
                            <select name="provider" class="form-select" required>
                                <option value="openai">OpenAI</option>
                                <option value="deepseek">DeepSeek</option>
                                <option value="anthropic">Anthropic</option>
                                <option value="google">Google (Gemini)</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Model ID *</label>
                            <input type="text" name="model_id" class="form-control" required placeholder="e.g., gpt-4-turbo">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">API Key</label>
                            <input type="password" name="api_key" class="form-control" placeholder="Leave blank to use default">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Max Tokens</label>
                            <input type="number" name="max_tokens" class="form-control" min="1" value="4096">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cost per Token ($)</label>
                            <input type="number" name="cost_per_token" class="form-control" min="0" step="0.000001" value="0.00002">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Model</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
