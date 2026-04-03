<?php
/**
 * pages/ai/recommendations.php — AI Product Recommendations (Phase 8)
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$userId = (int)$_SESSION['user_id'];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-stars fs-2 text-warning me-3"></i>
        <div>
            <h1 class="h3 mb-0">Product Recommendations</h1>
            <p class="text-muted mb-0">AI-curated picks personalized just for you</p>
        </div>
        <div class="ms-auto">
            <button class="btn btn-outline-primary btn-sm" id="refresh-btn">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div class="row g-3 mb-4" id="rec-metrics">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h5 mb-0 text-primary" id="total-recs">—</div>
                <small class="text-muted">Total Recommendations</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h5 mb-0 text-success" id="ctr-stat">—</div>
                <small class="text-muted">Click-through Rate</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h5 mb-0 text-warning" id="conv-stat">—</div>
                <small class="text-muted">Conversion Rate</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h5 mb-0 text-info" id="score-stat">—</div>
                <small class="text-muted">Avg Match Score</small>
            </div>
        </div>
    </div>

    <!-- For You Section -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-3 d-flex align-items-center">
            <i class="bi bi-person-check text-primary me-2"></i>
            <h5 class="mb-0">Recommended For You</h5>
            <span class="badge bg-primary ms-2">AI Personalized</span>
        </div>
        <div class="card-body">
            <div class="row g-3" id="personalized-grid">
                <div class="col-12 text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>

    <!-- Trending Section -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-3 d-flex align-items-center">
            <i class="bi bi-graph-up-arrow text-warning me-2"></i>
            <h5 class="mb-0">Trending on GlobexSky</h5>
        </div>
        <div class="card-body">
            <div class="row g-3" id="trending-grid">
                <div class="col-12 text-center py-4"><div class="spinner-border text-warning"></div></div>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/ai-recommendations.js"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
