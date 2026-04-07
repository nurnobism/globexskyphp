<?php
/**
 * pages/carry/index.php — Browse Carry Trips (Public Marketplace) — PR #16
 */
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/carry.php';

$filters = [
    'origin'        => trim($_GET['origin'] ?? ''),
    'destination'   => trim($_GET['destination'] ?? ''),
    'date_from'     => trim($_GET['date_from'] ?? ''),
    'max_weight'    => (float)($_GET['weight'] ?? 0),
    'price_min'     => (float)($_GET['price_min'] ?? 0),
    'price_max'     => (float)($_GET['price_max'] ?? 0),
    'verified_only' => !empty($_GET['verified_only']),
    'min_rating'    => (float)($_GET['min_rating'] ?? 0),
    'sort'          => trim($_GET['sort'] ?? 'date_asc'),
];

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$result  = getTrips($filters, $page, $perPage);
$trips   = $result['trips'];
$total   = $result['total'];
$pages   = $result['pages'];

$pageTitle = 'Browse Carry Trips';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-airplane text-primary me-2"></i>Carry Service — Browse Trips</h3>
        <?php if (isLoggedIn()): ?>
            <a href="/pages/carrier/trips/index.php" class="btn btn-outline-primary">
                <i class="bi bi-plus-circle me-1"></i> Post a Trip
            </a>
        <?php endif; ?>
    </div>

    <!-- Search Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">From (City/Country)</label>
                    <input type="text" name="origin" class="form-control" placeholder="e.g. Dubai" value="<?= htmlspecialchars($filters['origin']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">To (City/Country)</label>
                    <input type="text" name="destination" class="form-control" placeholder="e.g. Dhaka" value="<?= htmlspecialchars($filters['destination']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Departure Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Package Weight (kg)</label>
                    <input type="number" name="weight" class="form-control" placeholder="kg" min="0" step="0.1" value="<?= $filters['max_weight'] > 0 ? $filters['max_weight'] : '' ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i> Search Trips
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <!-- Filters Sidebar -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-funnel me-1"></i> Filters
                </div>
                <div class="card-body">
                    <form method="GET" id="filterForm">
                        <input type="hidden" name="origin" value="<?= htmlspecialchars($filters['origin']) ?>">
                        <input type="hidden" name="destination" value="<?= htmlspecialchars($filters['destination']) ?>">
                        <input type="hidden" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>">
                        <input type="hidden" name="weight" value="<?= $filters['max_weight'] > 0 ? $filters['max_weight'] : '' ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Sort By</label>
                            <select name="sort" class="form-select form-select-sm" onchange="document.getElementById('filterForm').submit()">
                                <option value="date_asc" <?= $filters['sort'] === 'date_asc' ? 'selected' : '' ?>>Date (Earliest)</option>
                                <option value="date_desc" <?= $filters['sort'] === 'date_desc' ? 'selected' : '' ?>>Date (Latest)</option>
                                <option value="price_asc" <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Price (Low to High)</option>
                                <option value="price_desc" <?= $filters['sort'] === 'price_desc' ? 'selected' : '' ?>>Price (High to Low)</option>
                                <option value="rating" <?= $filters['sort'] === 'rating' ? 'selected' : '' ?>>Top Rated</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Min Price/kg ($)</label>
                            <input type="number" name="price_min" class="form-control form-control-sm" min="0" step="0.5" value="<?= $filters['price_min'] > 0 ? $filters['price_min'] : '' ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Max Price/kg ($)</label>
                            <input type="number" name="price_max" class="form-control form-control-sm" min="0" step="0.5" value="<?= $filters['price_max'] > 0 ? $filters['price_max'] : '' ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Min Carrier Rating</label>
                            <select name="min_rating" class="form-select form-select-sm">
                                <option value="0">Any Rating</option>
                                <option value="3" <?= $filters['min_rating'] == 3 ? 'selected' : '' ?>>3+ Stars</option>
                                <option value="4" <?= $filters['min_rating'] == 4 ? 'selected' : '' ?>>4+ Stars</option>
                                <option value="4.5" <?= $filters['min_rating'] == 4.5 ? 'selected' : '' ?>>4.5+ Stars</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="verified_only" value="1" id="verifiedOnly" class="form-check-input"
                                    <?= $filters['verified_only'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="verifiedOnly">
                                    <i class="bi bi-patch-check-fill text-success"></i> Verified Carriers Only
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm w-100">Apply Filters</button>
                        <a href="/pages/carry/index.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">Clear All</a>
                    </form>
                </div>
            </div>
        </div>

        <!-- Trip Cards Grid -->
        <div class="col-md-9">
            <?php if ($total > 0): ?>
                <p class="text-muted small mb-3"><?= number_format($total) ?> trip<?= $total !== 1 ? 's' : '' ?> found</p>
                <div class="row g-4">
                    <?php foreach ($trips as $trip): ?>
                        <div class="col-sm-6 col-xl-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <!-- Carrier Info -->
                                    <div class="d-flex align-items-center mb-3">
                                        <?php if (!empty($trip['avatar'])): ?>
                                            <img src="<?= htmlspecialchars($trip['avatar']) ?>" class="rounded-circle me-2" width="40" height="40" alt="Carrier" style="object-fit:cover">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;font-size:1rem">
                                                <?= strtoupper(substr($trip['first_name'] ?? 'C', 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-semibold small">
                                                <?= htmlspecialchars(($trip['first_name'] ?? '') . ' ' . ($trip['last_name'] ?? '')) ?>
                                                <?php if ($trip['carrier_verified']): ?>
                                                    <i class="bi bi-patch-check-fill text-success ms-1" title="Verified"></i>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($trip['carrier_rating'] > 0): ?>
                                                <div class="text-warning small">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi bi-star<?= $i <= round($trip['carrier_rating']) ? '-fill' : '' ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="text-muted">(<?= number_format($trip['carrier_rating'], 1) ?>)</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Route -->
                                    <div class="mb-2">
                                        <span class="badge bg-light text-dark">
                                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($trip['origin_city']) ?>, <?= htmlspecialchars($trip['origin_country']) ?>
                                        </span>
                                        <i class="bi bi-arrow-right text-muted mx-1"></i>
                                        <span class="badge bg-light text-dark">
                                            <i class="bi bi-geo-alt-fill text-danger"></i> <?= htmlspecialchars($trip['destination_city']) ?>, <?= htmlspecialchars($trip['destination_country']) ?>
                                        </span>
                                    </div>

                                    <!-- Date & Capacity -->
                                    <div class="text-muted small mb-2">
                                        <i class="bi bi-calendar3 me-1"></i> <?= date('M d, Y', strtotime($trip['departure_date'])) ?>
                                        &nbsp;&mdash;&nbsp;
                                        <i class="bi bi-box-seam me-1"></i> Up to <?= number_format($trip['max_weight_kg'], 1) ?> kg
                                    </div>

                                    <!-- Pricing -->
                                    <div class="mb-2">
                                        <?php if ($trip['flat_rate']): ?>
                                            <span class="badge bg-primary fs-6">$<?= number_format($trip['flat_rate'], 2) ?> flat rate</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary fs-6">$<?= number_format($trip['price_per_kg'], 2) ?>/kg</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($trip['available_space_description'])): ?>
                                        <p class="text-muted small mb-2"><?= htmlspecialchars(mb_strimwidth($trip['available_space_description'], 0, 80, '…')) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-white border-0 pt-0">
                                    <a href="/pages/carry/trip-detail.php?id=<?= (int)$trip['id'] ?>" class="btn btn-primary btn-sm w-100">
                                        <i class="bi bi-send me-1"></i> Request Carry
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($p = 1; $p <= $pages; $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-airplane display-1 text-muted"></i>
                    <h5 class="mt-3 text-muted">No trips found</h5>
                    <p class="text-muted">Try adjusting your search or filters, or check back later.</p>
                    <?php if (isLoggedIn()): ?>
                        <a href="/pages/carrier/trips/index.php" class="btn btn-primary mt-2">Post Your Trip</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
