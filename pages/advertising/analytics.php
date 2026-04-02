<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();
$db = getDB();

$userId = $_SESSION['user_id'];

$stmt = $db->prepare('SELECT * FROM advertising_campaigns WHERE user_id=? ORDER BY created_at DESC');
$stmt->execute([$userId]);
$campaigns = $stmt->fetchAll();

$pageTitle = 'Ad Analytics';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 fw-bold"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Ad Analytics</h1>
            <p class="text-muted mb-0">Performance overview for your advertising campaigns</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Campaigns
        </a>
    </div>

    <!-- KPI Cards -->
    <div class="row g-4 mb-4">
        <!-- Total Impressions -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center gap-3 p-4">
                    <div class="rounded-3 bg-primary bg-opacity-10 p-3">
                        <i class="bi bi-eye fs-3 text-primary"></i>
                    </div>
                    <div>
                        <div class="text-muted small mb-1">Total Impressions</div>
                        <div class="h4 mb-0 fw-bold">—</div>
                        <div class="text-muted" style="font-size:.75rem;">Live data via API</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Clicks -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center gap-3 p-4">
                    <div class="rounded-3 bg-success bg-opacity-10 p-3">
                        <i class="bi bi-cursor fs-3 text-success"></i>
                    </div>
                    <div>
                        <div class="text-muted small mb-1">Total Clicks</div>
                        <div class="h4 mb-0 fw-bold">—</div>
                        <div class="text-muted" style="font-size:.75rem;">Live data via API</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Avg CTR -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center gap-3 p-4">
                    <div class="rounded-3 bg-warning bg-opacity-10 p-3">
                        <i class="bi bi-percent fs-3 text-warning"></i>
                    </div>
                    <div>
                        <div class="text-muted small mb-1">Avg CTR</div>
                        <div class="h4 mb-0 fw-bold">—</div>
                        <div class="text-muted" style="font-size:.75rem;">Live data via API</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Spend -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center gap-3 p-4">
                    <div class="rounded-3 bg-danger bg-opacity-10 p-3">
                        <i class="bi bi-currency-dollar fs-3 text-danger"></i>
                    </div>
                    <div>
                        <div class="text-muted small mb-1">Total Spend</div>
                        <div class="h4 mb-0 fw-bold">—</div>
                        <div class="text-muted" style="font-size:.75rem;">Live data via API</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Chart Placeholder -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="bi bi-graph-up me-2 text-primary"></i>Performance Over Time</span>
            <span class="badge bg-light text-muted border">Last 30 days</span>
        </div>
        <div class="card-body d-flex flex-column align-items-center justify-content-center py-5" style="min-height:220px;">
            <i class="bi bi-bar-chart-line display-3 text-muted mb-3"></i>
            <p class="text-muted mb-0">Chart loads here</p>
            <small class="text-muted">Connect your analytics API to display live performance data.</small>
        </div>
    </div>

    <!-- Campaigns Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <span class="fw-semibold"><i class="bi bi-table me-2 text-secondary"></i>Campaign Breakdown</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($campaigns)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-megaphone display-4 text-muted d-block mb-3"></i>
                    <p class="text-muted mb-0">No campaigns to display.</p>
                    <a href="create.php" class="btn btn-sm btn-primary mt-3">
                        <i class="bi bi-plus-circle me-1"></i> Create Campaign
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Campaign</th>
                                <th>Impressions</th>
                                <th>Clicks</th>
                                <th>CTR</th>
                                <th>Spend</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campaigns as $campaign):
                                $impressions = (int)($campaign['impressions'] ?? 0);
                                $clicks      = (int)($campaign['clicks'] ?? 0);
                                $ctr         = $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0;
                                $status      = $campaign['status'] ?? 'draft';
                                $statusMap   = ['active' => 'success', 'paused' => 'warning', 'ended' => 'secondary', 'draft' => 'info'];
                                $badgeClass  = $statusMap[$status] ?? 'secondary';
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold"><?= e($campaign['title'] ?? $campaign['name'] ?? '') ?></div>
                                    <?php if (!empty($campaign['type'])): ?>
                                        <small class="text-muted"><?= e(ucfirst($campaign['type'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($impressions) ?></td>
                                <td><?= number_format($clicks) ?></td>
                                <td><?= $ctr ?>%</td>
                                <td><?= formatMoney($campaign['spend'] ?? $campaign['budget'] ?? 0) ?></td>
                                <td>
                                    <span class="badge bg-<?= $badgeClass ?> text-capitalize"><?= e($status) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
