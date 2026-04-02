<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db = getDB();

// Load current AI settings
$settingsStmt = $db->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key LIKE 'ai_%'");
$settings = [];
foreach ($settingsStmt->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$pageTitle = 'AI Feature Settings';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-gear-fill text-secondary me-2"></i>AI Feature Settings</h3>
        <a href="/pages/admin/ai/index.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <form method="POST" action="/api/admin.php?action=update_ai_settings">
                <?= csrfField() ?>

                <!-- Feature Toggles -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-toggles me-2"></i>Feature Toggles</h6>
                    </div>
                    <div class="card-body">
                        <?php $features = [
                            ['ai_chatbot_enabled',        'AI Chatbot',              'Enable the AI chatbot for buyers and suppliers'],
                            ['ai_recommendations_enabled','Product Recommendations', 'AI-powered product recommendations'],
                            ['ai_search_enabled',         'AI Search',               'Enable semantic/AI-powered search'],
                            ['ai_fraud_detection_enabled','Fraud Detection',         'AI fraud detection for orders and payments'],
                            ['ai_insights_enabled',       'AI Insights',             'AI analytics and business insights'],
                            ['ai_translation_enabled',    'Auto Translation',        'AI-powered content translation'],
                        ]; ?>
                        <?php foreach ($features as [$key, $label, $desc]): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <div class="fw-semibold"><?= $label ?></div>
                                <small class="text-muted"><?= $desc ?></small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="<?= $key ?>" id="<?= $key ?>"
                                       <?= ($settings[$key] ?? '1') === '1' ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Model Defaults -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-sliders me-2"></i>Default Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Default AI Provider</label>
                                <select name="ai_default_provider" class="form-select">
                                    <?php foreach (['deepseek'=>'DeepSeek','openai'=>'OpenAI','anthropic'=>'Anthropic'] as $val=>$label): ?>
                                    <option value="<?= $val ?>" <?= ($settings['ai_default_provider'] ?? 'deepseek') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Max Tokens per Request</label>
                                <input type="number" name="ai_max_tokens" class="form-control" min="256" max="32000"
                                       value="<?= e($settings['ai_max_tokens'] ?? '2048') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Daily Request Limit per User</label>
                                <input type="number" name="ai_daily_limit" class="form-control" min="1"
                                       value="<?= e($settings['ai_daily_limit'] ?? '100') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Monthly Budget (USD)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="ai_monthly_budget" class="form-control" min="0" step="0.01"
                                           value="<?= e($settings['ai_monthly_budget'] ?? '500') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save me-1"></i> Save Settings
                </button>
            </form>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>Quick Actions</h6>
                    <div class="d-grid gap-2">
                        <a href="/pages/admin/ai/models.php" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-cpu me-2"></i>Manage Models
                        </a>
                        <a href="/pages/admin/ai/training.php" class="btn btn-outline-success btn-sm text-start">
                            <i class="bi bi-database me-2"></i>Training Data
                        </a>
                        <a href="/pages/admin/ai/logs.php" class="btn btn-outline-warning btn-sm text-start">
                            <i class="bi bi-activity me-2"></i>View Logs
                        </a>
                        <a href="/pages/ai/chatbot.php" class="btn btn-outline-secondary btn-sm text-start" target="_blank">
                            <i class="bi bi-chat-dots me-2"></i>Test Chatbot
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
