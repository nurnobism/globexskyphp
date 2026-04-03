<?php
/**
 * pages/ai/index.php — AI Dashboard (Phase 8)
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// Stats
$stats = ['conversations' => 0, 'recommendations' => 0, 'fraud_alerts' => 0, 'content_generated' => 0, 'search_enhancements' => 0, 'tokens_today' => 0, 'cost_today' => 0.0];
try {
    $stats['conversations']      = (int)$db->prepare("SELECT COUNT(*) FROM ai_conversations WHERE user_id = ?")->execute([$userId]) ? (int)$db->query("SELECT COUNT(*) FROM ai_conversations WHERE user_id = $userId")->fetchColumn() : 0;
    $stats['recommendations']    = (int)$db->query("SELECT COUNT(*) FROM ai_recommendations WHERE user_id = $userId")->fetchColumn();
    $stats['content_generated']  = (int)$db->query("SELECT COUNT(*) FROM ai_content_generations WHERE user_id = $userId")->fetchColumn();
    $stats['search_enhancements']= (int)$db->query("SELECT COUNT(*) FROM ai_search_logs WHERE user_id = $userId")->fetchColumn();
    $row = $db->query("SELECT COALESCE(SUM(total_tokens),0) AS tk, COALESCE(SUM(cost_usd),0) AS cost FROM ai_usage WHERE user_id = $userId AND DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC);
    $stats['tokens_today'] = (int)($row['tk'] ?? 0);
    $stats['cost_today']   = (float)($row['cost'] ?? 0);
} catch (PDOException $e) {}

// Recent activity
$recentActivity = [];
try {
    $recentActivity = $db->query("SELECT feature, status, total_tokens, cost_usd, created_at FROM ai_usage WHERE user_id = $userId ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Usage chart data (last 30 days)
$chartLabels = [];
$chartData   = [];
try {
    $stmt = $db->query("SELECT DATE(created_at) AS d, SUM(total_tokens) AS t FROM ai_usage WHERE user_id = $userId AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY d ASC");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $chartLabels[] = $r['d'];
        $chartData[]   = (int)$r['t'];
    }
} catch (PDOException $e) {}

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-robot fs-2 text-primary me-3"></i>
        <div>
            <h1 class="h3 mb-0">AI Dashboard</h1>
            <p class="text-muted mb-0">DeepSeek-powered intelligence for your marketplace</p>
        </div>
        <div class="ms-auto">
            <span id="ai-status-badge" class="badge bg-secondary">Checking...</span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="bi bi-chat-dots text-primary fs-3"></i>
                    <div class="h4 mt-2 mb-0"><?= number_format($stats['conversations']) ?></div>
                    <small class="text-muted">Conversations</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="bi bi-stars text-warning fs-3"></i>
                    <div class="h4 mt-2 mb-0"><?= number_format($stats['recommendations']) ?></div>
                    <small class="text-muted">Recommendations</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="bi bi-shield-exclamation text-danger fs-3"></i>
                    <div class="h4 mt-2 mb-0"><?= number_format($stats['fraud_alerts']) ?></div>
                    <small class="text-muted">Fraud Alerts</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="bi bi-file-earmark-text text-success fs-3"></i>
                    <div class="h4 mt-2 mb-0"><?= number_format($stats['content_generated']) ?></div>
                    <small class="text-muted">Content Generated</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="bi bi-search text-info fs-3"></i>
                    <div class="h4 mt-2 mb-0"><?= number_format($stats['search_enhancements']) ?></div>
                    <small class="text-muted">AI Searches</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="bi bi-lightning-charge text-purple fs-3"></i>
                    <div class="h4 mt-2 mb-0"><?= number_format($stats['tokens_today']) ?></div>
                    <small class="text-muted">Tokens Today</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">Quick Actions</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="/pages/ai/chatbot.php" class="btn btn-primary"><i class="bi bi-chat-dots me-2"></i>Start AI Chat</a>
                        <a href="/pages/ai/content-generator.php" class="btn btn-success"><i class="bi bi-file-earmark-plus me-2"></i>Generate Content</a>
                        <a href="/pages/ai/analytics.php" class="btn btn-info text-white"><i class="bi bi-graph-up me-2"></i>Analyze Sales</a>
                        <a href="/pages/ai/recommendations.php" class="btn btn-warning"><i class="bi bi-stars me-2"></i>View Recommendations</a>
                        <a href="/pages/ai/search.php" class="btn btn-secondary"><i class="bi bi-search me-2"></i>AI Search</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Token Usage Chart -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h5 class="mb-0">Token Usage — Last 30 Days</h5>
                </div>
                <div class="card-body">
                    <canvas id="usageChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h5 class="mb-0">Recent AI Activity</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentActivity)): ?>
                        <div class="text-center py-4 text-muted"><i class="bi bi-robot fs-2 d-block mb-2"></i>No AI activity yet</div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentActivity as $act): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2">
                            <div>
                                <span class="badge bg-light text-dark me-2"><?= e($act['feature']) ?></span>
                                <small class="text-muted"><?= date('M j, g:i a', strtotime($act['created_at'])) ?></small>
                            </div>
                            <small class="text-muted"><?= number_format((int)$act['total_tokens']) ?> tok</small>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Token usage chart
const ctx = document.getElementById('usageChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Tokens Used',
            data: <?= json_encode($chartData) ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13,110,253,0.08)',
            fill: true,
            tension: 0.4,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});

// Check AI status
fetch('/api/ai/analytics.php?action=health')
    .then(r => r.json())
    .then(d => {
        const badge = document.getElementById('ai-status-badge');
        if (d.success) {
            const s = d.data?.status || 'unknown';
            const colors = { healthy: 'success', degraded: 'warning', critical: 'danger', unknown: 'secondary' };
            badge.className = 'badge bg-' + (colors[s] || 'secondary');
            badge.textContent = 'AI ' + s.charAt(0).toUpperCase() + s.slice(1);
        } else {
            badge.className = 'badge bg-secondary'; badge.textContent = 'AI Available';
        }
    }).catch(() => {
        const badge = document.getElementById('ai-status-badge');
        badge.className = 'badge bg-secondary'; badge.textContent = 'AI Available';
    });
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
