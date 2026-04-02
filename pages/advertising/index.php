<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();
$db = getDB();

$userId = $_SESSION['user_id'];
$q = trim($_GET['q'] ?? '');

$stmt = $db->prepare('SELECT * FROM advertising_campaigns WHERE user_id=? ORDER BY created_at DESC');
$stmt->execute([$userId]);
$campaigns = $stmt->fetchAll();

if ($q !== '') {
    $campaigns = array_filter($campaigns, fn($c) => stripos($c['title'], $q) !== false);
}

$pageTitle = 'Advertising Campaigns';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 fw-bold"><i class="bi bi-megaphone me-2 text-primary"></i>Advertising Campaigns</h1>
            <p class="text-muted mb-0">Manage and monitor your ad campaigns</p>
        </div>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Create Campaign
        </a>
    </div>

    <!-- Search Filter -->
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">Search Campaigns</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control border-start-0" placeholder="Search by campaign name…" value="<?= e($q) ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                    <?php if ($q !== ''): ?>
                        <a href="index.php" class="btn btn-outline-secondary ms-1">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Campaigns Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
            <span class="fw-semibold">Campaigns</span>
            <span class="badge bg-secondary"><?= count($campaigns) ?> total</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($campaigns)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-megaphone display-4 text-muted d-block mb-3"></i>
                    <h5 class="text-muted">No campaigns found</h5>
                    <p class="text-muted small mb-3">
                        <?= $q !== '' ? 'Try a different search term.' : 'Get started by creating your first advertising campaign.' ?>
                    </p>
                    <?php if ($q === ''): ?>
                        <a href="create.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle me-1"></i> Create Campaign
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Campaign Name</th>
                                <th>Budget</th>
                                <th>Impressions</th>
                                <th>Clicks</th>
                                <th>CTR</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campaigns as $campaign):
                                $impressions = (int)($campaign['impressions'] ?? 0);
                                $clicks      = (int)($campaign['clicks'] ?? 0);
                                $ctr         = $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0;
                                $status      = $campaign['status'] ?? 'draft';
                                $statusMap   = [
                                    'active' => 'success',
                                    'paused' => 'warning',
                                    'ended'  => 'secondary',
                                    'draft'  => 'info',
                                ];
                                $badgeClass = $statusMap[$status] ?? 'secondary';
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold"><?= e($campaign['title'] ?? $campaign['name'] ?? '') ?></div>
                                    <?php if (!empty($campaign['type'])): ?>
                                        <small class="text-muted"><?= e(ucfirst($campaign['type'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatMoney($campaign['budget'] ?? 0) ?></td>
                                <td><?= number_format($impressions) ?></td>
                                <td><?= number_format($clicks) ?></td>
                                <td><?= $ctr ?>%</td>
                                <td>
                                    <span class="badge bg-<?= $badgeClass ?> text-capitalize"><?= e($status) ?></span>
                                </td>
                                <td><?= !empty($campaign['start_date']) ? date('M j, Y', strtotime($campaign['start_date'])) : '—' ?></td>
                                <td><?= !empty($campaign['end_date']) ? date('M j, Y', strtotime($campaign['end_date'])) : '—' ?></td>
                                <td class="text-end pe-4">
                                    <a href="analytics.php?id=<?= (int)$campaign['id'] ?>" class="btn btn-sm btn-outline-secondary me-1" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="create.php?id=<?= (int)$campaign['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
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
