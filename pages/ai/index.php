<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// Quick stats
try {
    $stmt = $db->query("SELECT COUNT(*) FROM admin_logs WHERE action='ai_search'");
    $aiQueries = (int)$stmt->fetchColumn();

    $chatConvos = 0;
    try {
        $c = $db->query("SELECT COUNT(DISTINCT conversation_id) FROM ai_chat_history");
        $chatConvos = (int)$c->fetchColumn();
    } catch (PDOException $e) {}

    $fraudAlerts = 0;
    try {
        $f = $db->query("SELECT COUNT(*) FROM fraud_flags WHERE risk_level IN ('high','critical') AND status='open'");
        $fraudAlerts = (int)$f->fetchColumn();
    } catch (PDOException $e) {}

    $recsServed = 0;
    try {
        $r = $db->query("SELECT COUNT(*) FROM admin_logs WHERE action='ai_recommendations'");
        $recsServed = (int)$r->fetchColumn();
    } catch (PDOException $e) {}
} catch (PDOException $e) {
    $aiQueries = $chatConvos = $fraudAlerts = $recsServed = 0;
}

$isAdmin   = isAdmin();
$pageTitle = 'AI Hub';
include __DIR__ . '/../../includes/header.php';
?>

<style>
    :root { --bs-primary: #FF6B35; }
    .ai-hero        { background: linear-gradient(135deg, #1B2A4A 0%, #2d4070 50%, #FF6B35 100%); }
    .feature-card   { border: none; border-radius: 16px; transition: transform .25s, box-shadow .25s; }
    .feature-card:hover { transform: translateY(-6px); box-shadow: 0 12px 32px rgba(0,0,0,.15); }
    .stat-card      { border-radius: 16px; border: none; }
    .stat-icon      { width: 56px; height: 56px; border-radius: 14px; display:flex; align-items:center; justify-content:center; font-size:1.6rem; }
    .badge-ai       { background: linear-gradient(135deg,#FF6B35,#ff8f65); color:#fff; font-size:.7rem; padding:.25em .55em; border-radius:8px; }
</style>

<!-- Hero Banner -->
<div class="ai-hero text-white py-5">
    <div class="container py-3">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <span class="badge-ai mb-3 d-inline-block">Powered by DeepSeek AI</span>
                <h1 class="display-5 fw-bold mb-3">AI Hub</h1>
                <p class="lead text-white-75 mb-4">
                    Supercharge your GlobexSky experience with intelligent search, personalised recommendations,
                    real-time fraud protection, and deep business analytics — all powered by AI.
                </p>
                <a href="<?= APP_URL ?>/pages/ai/search.php" class="btn btn-warning btn-lg fw-bold me-2 px-4">
                    <i class="bi bi-search me-1"></i> Try AI Search
                </a>
                <a href="<?= APP_URL ?>/pages/ai/chatbot.php" class="btn btn-outline-light btn-lg px-4">
                    <i class="bi bi-chat-dots me-1"></i> Open Chatbot
                </a>
            </div>
            <div class="col-lg-5 d-none d-lg-flex justify-content-center">
                <div class="text-center" style="font-size:9rem;opacity:.25;">
                    <i class="bi bi-cpu-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">

    <!-- Stats Row -->
    <div class="row g-3 mb-5">
        <?php
        $statCards = [
            ['Total AI Queries',          $aiQueries,   'search',       'rgba(255,107,53,.15)',  '#FF6B35'],
            ['Chatbot Conversations',      $chatConvos,  'chat-dots-fill','rgba(27,42,74,.1)',    '#1B2A4A'],
            ['High-Risk Fraud Alerts',     $fraudAlerts, 'shield-exclamation','rgba(220,53,69,.1)','#dc3545'],
            ['Recommendations Served',     $recsServed,  'stars',        'rgba(255,193,7,.15)',   '#ffc107'],
        ];
        foreach ($statCards as [$label, $value, $icon, $bg, $color]):
        ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3 p-4">
                    <div class="stat-icon" style="background:<?= $bg ?>; color:<?= $color ?>;">
                        <i class="bi bi-<?= $icon ?>"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bold lh-1"><?= number_format($value) ?></div>
                        <div class="text-muted small"><?= e($label) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Feature Cards -->
    <h4 class="fw-bold mb-4"><i class="bi bi-grid-fill text-warning me-2"></i>AI Features</h4>
    <div class="row g-4 mb-5">

        <!-- Smart Search -->
        <div class="col-md-6 col-xl-4">
            <div class="card feature-card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon" style="background:rgba(255,107,53,.12);color:#FF6B35;">
                            <i class="bi bi-search-heart-fill"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">Smart Search</h5>
                            <small class="text-muted">Natural language · Voice · Image · Barcode</small>
                        </div>
                    </div>
                    <p class="text-muted small mb-4">
                        Search products using plain English, upload an image, scan a barcode, or speak your query.
                        AI extracts filters automatically for instant, relevant results.
                    </p>
                    <a href="<?= APP_URL ?>/pages/ai/search.php" class="btn btn-primary w-100">
                        <i class="bi bi-arrow-right-circle me-1"></i> Open Smart Search
                    </a>
                </div>
            </div>
        </div>

        <!-- AI Chatbot -->
        <div class="col-md-6 col-xl-4">
            <div class="card feature-card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon" style="background:rgba(27,42,74,.1);color:#1B2A4A;">
                            <i class="bi bi-robot"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">GlobexBot</h5>
                            <small class="text-muted">24/7 AI assistant</small>
                        </div>
                    </div>
                    <p class="text-muted small mb-4">
                        Ask anything about products, orders, suppliers, or trade regulations.
                        GlobexBot remembers your conversation context and gives instant answers.
                    </p>
                    <a href="<?= APP_URL ?>/pages/ai/chatbot.php" class="btn btn-dark w-100">
                        <i class="bi bi-chat-dots me-1"></i> Start Chatting
                    </a>
                </div>
            </div>
        </div>

        <!-- Recommendations -->
        <div class="col-md-6 col-xl-4">
            <div class="card feature-card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon" style="background:rgba(255,193,7,.15);color:#d49e00;">
                            <i class="bi bi-stars"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">For You</h5>
                            <small class="text-muted">Personalised recommendations</small>
                        </div>
                    </div>
                    <p class="text-muted small mb-4">
                        AI analyses your order history and browsing patterns to surface the products
                        most likely to match your needs.
                    </p>
                    <a href="<?= APP_URL ?>/pages/ai/recommendations.php" class="btn btn-warning w-100 text-dark fw-bold">
                        <i class="bi bi-lightning-fill me-1"></i> See Recommendations
                    </a>
                </div>
            </div>
        </div>

        <?php if ($isAdmin): ?>

        <!-- Fraud Detection -->
        <div class="col-md-6 col-xl-4">
            <div class="card feature-card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon" style="background:rgba(220,53,69,.1);color:#dc3545;">
                            <i class="bi bi-shield-shaded"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">Fraud Detection</h5>
                            <small class="text-muted">Admin · Real-time risk scoring</small>
                        </div>
                    </div>
                    <p class="text-muted small mb-4">
                        AI evaluates every transaction for fraud indicators and assigns a risk score,
                        helping you act before losses occur.
                    </p>
                    <a href="<?= APP_URL ?>/pages/ai/fraud-detection.php" class="btn btn-danger w-100">
                        <i class="bi bi-shield-check me-1"></i> View Fraud Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Business Insights -->
        <div class="col-md-6 col-xl-4">
            <div class="card feature-card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon" style="background:rgba(25,135,84,.1);color:#198754;">
                            <i class="bi bi-lightbulb-fill"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">Business Insights</h5>
                            <small class="text-muted">Admin · Trends & predictions</small>
                        </div>
                    </div>
                    <p class="text-muted small mb-4">
                        Get AI-generated summaries of sales trends, revenue predictions,
                        and prioritised growth recommendations tailored to your data.
                    </p>
                    <a href="<?= APP_URL ?>/pages/ai/insights.php" class="btn btn-success w-100">
                        <i class="bi bi-graph-up-arrow me-1"></i> View Insights
                    </a>
                </div>
            </div>
        </div>

        <!-- AI Analytics -->
        <div class="col-md-6 col-xl-4">
            <div class="card feature-card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon" style="background:rgba(13,110,253,.1);color:#0d6efd;">
                            <i class="bi bi-bar-chart-fill"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">AI Analytics</h5>
                            <small class="text-muted">Admin · Ask your data</small>
                        </div>
                    </div>
                    <p class="text-muted small mb-4">
                        Ask questions in plain English and get instant AI explanations of your charts,
                        trends, and KPIs — no SQL needed.
                    </p>
                    <a href="<?= APP_URL ?>/pages/ai/analytics.php" class="btn btn-primary w-100">
                        <i class="bi bi-chat-square-text me-1"></i> Explore Analytics
                    </a>
                </div>
            </div>
        </div>

        <?php endif; ?>

    </div>

    <!-- Quick Tips -->
    <div class="card border-0 rounded-4" style="background:linear-gradient(135deg,#1B2A4A,#2d4070);">
        <div class="card-body p-4 p-lg-5 text-white">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h5 class="fw-bold mb-2"><i class="bi bi-info-circle me-2 text-warning"></i>How AI Powers GlobexSky</h5>
                    <p class="text-white-75 mb-0">
                        Our AI features use <strong>DeepSeek</strong> — a state-of-the-art large language model.
                        Your data is processed securely and is never used to train external models.
                        AI suggestions are advisory; always verify before taking business decisions.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <a href="<?= APP_URL ?>/pages/ai/chatbot.php" class="btn btn-warning fw-bold px-4">
                        <i class="bi bi-robot me-1"></i> Ask GlobexBot
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
