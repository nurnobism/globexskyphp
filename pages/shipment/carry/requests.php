<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Browse open requests (not by current user)
$openRequests = [];
try {
    $filterFrom = trim($_GET['from_city'] ?? '');
    $filterTo   = trim($_GET['to_city'] ?? '');

    $sql    = "SELECT cr.*, u.first_name, u.last_name
               FROM carry_requests cr
               JOIN users u ON u.id = cr.sender_id
               WHERE cr.status = 'open' AND cr.sender_id != ?";
    $params = [$userId];

    if ($filterFrom !== '') {
        $sql      .= " AND cr.from_city LIKE ?";
        $params[]  = '%' . $filterFrom . '%';
    }
    if ($filterTo !== '') {
        $sql      .= " AND cr.to_city LIKE ?";
        $params[]  = '%' . $filterTo . '%';
    }

    $sql .= " ORDER BY cr.created_at DESC LIMIT 50";

    $stmt        = $db->prepare($sql);
    $stmt->execute($params);
    $openRequests = $stmt->fetchAll();
} catch (Exception $e) {
    $openRequests = [];
}

$categoryColors = [
    'document'    => 'primary',
    'electronics' => 'info',
    'clothing'    => 'success',
    'food'        => 'warning',
    'medicine'    => 'danger',
    'other'       => 'secondary',
];

$countries = [
    'United States', 'United Kingdom', 'Canada', 'Australia', 'Germany',
    'France', 'Italy', 'Spain', 'China', 'Japan', 'India', 'Brazil',
    'Mexico', 'South Africa', 'Nigeria', 'Kenya', 'UAE', 'Saudi Arabia',
    'Singapore', 'Malaysia', 'Indonesia', 'Pakistan', 'Bangladesh',
    'Egypt', 'Turkey', 'Argentina', 'Colombia', 'Chile', 'Ghana', 'Ethiopia',
];

$pageTitle = 'Carry Requests';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-inbox-fill text-primary me-2"></i>Carry Requests</h3>
        <a href="/pages/shipment/carry/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="requestTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= (!isset($_GET['tab']) || $_GET['tab'] === 'browse') ? 'active' : '' ?>"
                    id="browse-tab" data-bs-toggle="tab" data-bs-target="#browsePanel"
                    type="button" role="tab">
                <i class="bi bi-search me-1"></i> Browse Requests
                <?php if (!empty($openRequests)): ?>
                <span class="badge bg-primary ms-1"><?= count($openRequests) ?></span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= (isset($_GET['tab']) && $_GET['tab'] === 'post') ? 'active' : '' ?>"
                    id="post-tab" data-bs-toggle="tab" data-bs-target="#postPanel"
                    type="button" role="tab">
                <i class="bi bi-plus-circle me-1"></i> Post a Request
            </button>
        </li>
    </ul>

    <div class="tab-content" id="requestTabsContent">

        <!-- Browse Tab -->
        <div class="tab-pane fade <?= (!isset($_GET['tab']) || $_GET['tab'] === 'browse') ? 'show active' : '' ?>"
             id="browsePanel" role="tabpanel">

            <!-- Route Filter -->
            <form method="GET" class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">From City</label>
                            <input type="text" name="from_city" class="form-control"
                                   placeholder="e.g., Lagos"
                                   value="<?= e($filterFrom ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">To City</label>
                            <input type="text" name="to_city" class="form-control"
                                   placeholder="e.g., London"
                                   value="<?= e($filterTo ?? '') ?>">
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <input type="hidden" name="tab" value="browse">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="bi bi-funnel me-1"></i> Filter
                            </button>
                            <a href="?tab=browse" class="btn btn-outline-secondary">Clear</a>
                        </div>
                    </div>
                </div>
            </form>

            <?php if (empty($openRequests)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox text-muted display-3"></i>
                <h5 class="mt-3 text-muted">No open requests found</h5>
                <p class="text-muted">Try adjusting your filters or check back later.</p>
            </div>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($openRequests as $req): ?>
                <?php $catColor = $categoryColors[$req['category']] ?? 'secondary'; ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="fw-bold mb-0"><?= e($req['title']) ?></h6>
                                <span class="badge bg-<?= $catColor ?> text-capitalize">
                                    <?= e($req['category']) ?>
                                </span>
                            </div>
                            <p class="text-muted small mb-2">
                                <i class="bi bi-person me-1"></i>
                                <?= e($req['first_name'] . ' ' . $req['last_name']) ?>
                            </p>
                            <p class="small mb-2">
                                <i class="bi bi-geo-alt text-primary me-1"></i>
                                <strong><?= e($req['from_city']) ?></strong>, <?= e($req['from_country_name'] ?? $req['from_country']) ?>
                                <i class="bi bi-arrow-right text-muted mx-1"></i>
                                <strong><?= e($req['to_city']) ?></strong>, <?= e($req['to_country_name'] ?? $req['to_country']) ?>
                            </p>
                            <div class="d-flex gap-3 small text-muted mb-3">
                                <span><i class="bi bi-box me-1"></i><?= number_format((float)$req['weight_kg'], 1) ?> kg</span>
                                <span><i class="bi bi-currency-dollar me-1"></i>Budget: <?= formatMoney((float)$req['budget']) ?></span>
                            </div>
                            <?php if (!empty($req['preferred_date_from'])): ?>
                            <p class="small text-muted mb-3">
                                <i class="bi bi-calendar me-1"></i>
                                <?= formatDate($req['preferred_date_from']) ?>
                                <?php if (!empty($req['preferred_date_to'])): ?>
                                – <?= formatDate($req['preferred_date_to']) ?>
                                <?php endif; ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($req['description'])): ?>
                            <p class="small text-muted mb-3"><?= e(mb_substr($req['description'], 0, 100)) ?><?= mb_strlen($req['description']) > 100 ? '…' : '' ?></p>
                            <?php endif; ?>
                            <form method="POST" action="/api/carry.php?action=accept_request">
                                <?= csrfField() ?>
                                <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm w-100">
                                    <i class="bi bi-check-circle me-1"></i> Accept Request
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Post Request Tab -->
        <div class="tab-pane fade <?= (isset($_GET['tab']) && $_GET['tab'] === 'post') ? 'show active' : '' ?>"
             id="postPanel" role="tabpanel">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold">Post a New Carry Request</h6>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="/api/carry.php?action=post_request">
                                <?= csrfField() ?>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Title *</label>
                                        <input type="text" name="title" class="form-control" required
                                               placeholder="e.g., Carry documents from London to Lagos">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Category *</label>
                                        <select name="category" class="form-select" required>
                                            <option value="">Select category...</option>
                                            <option value="document">📄 Document</option>
                                            <option value="electronics">💻 Electronics</option>
                                            <option value="clothing">👕 Clothing</option>
                                            <option value="food">🍱 Food</option>
                                            <option value="medicine">💊 Medicine</option>
                                            <option value="other">📦 Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Weight (kg) *</label>
                                        <input type="number" name="weight_kg" class="form-control" required
                                               min="0.01" step="0.01" placeholder="e.g., 2.5">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">From City *</label>
                                        <input type="text" name="from_city" class="form-control" required placeholder="e.g., London">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">From Country *</label>
                                        <select name="from_country" class="form-select" required>
                                            <option value="">Select country...</option>
                                            <?php foreach ($countries as $country): ?>
                                            <option value="<?= e($country) ?>"><?= e($country) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">To City *</label>
                                        <input type="text" name="to_city" class="form-control" required placeholder="e.g., Lagos">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">To Country *</label>
                                        <select name="to_country" class="form-select" required>
                                            <option value="">Select country...</option>
                                            <?php foreach ($countries as $country): ?>
                                            <option value="<?= e($country) ?>"><?= e($country) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Preferred Date From</label>
                                        <input type="date" name="preferred_date_from" class="form-control"
                                               min="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Preferred Date To</label>
                                        <input type="date" name="preferred_date_to" class="form-control"
                                               min="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Budget (USD) *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="budget" class="form-control" required
                                                   min="0.01" step="0.01" placeholder="e.g., 20.00">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Special Handling</label>
                                        <input type="text" name="special_handling" class="form-control"
                                               placeholder="e.g., Fragile, Keep upright">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Description</label>
                                        <textarea name="description" class="form-control" rows="3"
                                            placeholder="Describe the items and any additional details..."></textarea>
                                    </div>
                                </div>
                                <div class="mt-4 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="bi bi-send me-1"></i> Post Request
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary">Clear</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
