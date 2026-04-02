<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();

$search = get('search', '');
$filterStatus = get('status', '');

$sql = "SELECT * FROM shipping_routes WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (origin LIKE ? OR destination LIKE ? OR carrier LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}
if ($filterStatus !== '') {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY created_at DESC";

$page = max(1, (int)get('page', 1));
$routes = [];
$totalPages = 1;

try {
    $result = paginate($db, $sql, $params, $page);
    $routes = $result['data'];
    $totalPages = $result['pages'];
} catch (\Exception $e) {
    $routes = [];
}

$pageTitle = 'Shipping Routes';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-signpost-split me-2"></i><?= e($pageTitle) ?></h1>
            <p class="text-muted mb-0">Manage shipping routes and carriers</p>
        </div>
        <div>
            <a href="/pages/logistics/index.php" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
            <?php if (isAdmin()): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRouteModal"><i class="bi bi-plus-circle me-1"></i>Add Route</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="search" class="form-label">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Origin, destination, or carrier..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= $filterStatus === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary me-2"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="/pages/logistics/routes.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Routes Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Origin</th>
                            <th>Destination</th>
                            <th>Carrier</th>
                            <th class="text-center">Est. Days</th>
                            <th>Cost</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($routes)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-signpost-split display-6 d-block mb-2"></i>
                                No shipping routes found.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($routes as $route):
                                $statusMap = [
                                    'active'    => 'success',
                                    'inactive'  => 'secondary',
                                    'suspended' => 'danger',
                                ];
                                $badgeClass = $statusMap[$route['status']] ?? 'secondary';
                            ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-geo-alt text-primary me-1"></i>
                                        <span class="fw-semibold"><?= e($route['origin']) ?></span>
                                    </td>
                                    <td>
                                        <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                        <?= e($route['destination']) ?>
                                    </td>
                                    <td><?= e($route['carrier']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark"><?= e($route['estimated_days'] ?? '—') ?> days</span>
                                    </td>
                                    <td class="fw-semibold"><?= isset($route['cost']) ? formatMoney($route['cost']) : '—' ?></td>
                                    <td><span class="badge bg-<?= $badgeClass ?>"><?= e(ucfirst($route['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (isAdmin()): ?>
<!-- Add Route Modal -->
<div class="modal fade" id="addRouteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Shipping Route</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/api/logistics.php?action=add_route">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Origin</label>
                        <input type="text" class="form-control" name="origin" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Destination</label>
                        <input type="text" class="form-control" name="destination" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Carrier</label>
                        <input type="text" class="form-control" name="carrier" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Estimated Days</label>
                            <input type="number" class="form-control" name="estimated_days" min="1" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Cost</label>
                            <input type="number" class="form-control" name="cost" step="0.01" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Add Route</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
