<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db   = getDB();
$page = max(1, (int)get('page', 1));
$q    = trim(get('q', ''));
$country = get('country', '');

$where  = ['s.verified = 1'];
$params = [];
if ($q)       { $where[] = '(s.company_name LIKE ? OR s.description LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($country) { $where[] = 's.country = ?'; $params[] = $country; }

$sql    = 'SELECT s.*, u.email FROM suppliers s JOIN users u ON u.id=s.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY s.rating DESC, s.total_orders DESC';
$result = paginate($db, $sql, $params, $page);
$suppliers = $result['data'];

$countries = $db->query('SELECT DISTINCT country FROM suppliers WHERE country IS NOT NULL AND country != "" ORDER BY country')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Supplier Directory';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-building text-primary me-2"></i>Supplier Directory</h3>
            <small class="text-muted"><?= $result['total'] ?> verified suppliers</small>
        </div>
        <?php if (isLoggedIn() && isSupplier()): ?>
        <a href="/pages/supplier/profile.php?edit=1" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Edit My Profile
        </a>
        <?php endif; ?>
    </div>

    <!-- Search & Filter -->
    <form method="GET" class="card border-0 shadow-sm p-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold small">Search Suppliers</label>
                <input type="text" name="q" class="form-control" placeholder="Company name or keyword..." value="<?= e($q) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Country</label>
                <select name="country" class="form-select">
                    <option value="">All Countries</option>
                    <?php foreach ($countries as $c): ?>
                    <option value="<?= e($c) ?>" <?= $country === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Search</button>
            </div>
            <?php if ($q || $country): ?>
            <div class="col-md-2">
                <a href="/pages/supplier/index.php" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($suppliers)): ?>
    <div class="text-center py-5">
        <i class="bi bi-building-x display-1 text-muted"></i>
        <h5 class="mt-3">No suppliers found</h5>
        <?php if ($q): ?><a href="/pages/supplier/index.php" class="btn btn-outline-primary mt-2">View All Suppliers</a><?php endif; ?>
    </div>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($suppliers as $s): ?>
        <div class="col">
            <div class="card h-100 border-0 shadow-sm supplier-card">
                <?php if ($s['banner']): ?>
                <img src="<?= e(APP_URL . '/' . $s['banner']) ?>" class="card-img-top" style="height:100px;object-fit:cover" alt="">
                <?php else: ?>
                <div style="height:60px;background:linear-gradient(135deg,#0d6efd,#6610f2)"></div>
                <?php endif; ?>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3" style="margin-top:-30px">
                        <img src="<?= $s['logo'] ? e(APP_URL.'/'.$s['logo']) : 'https://ui-avatars.com/api/?name=' . urlencode($s['company_name']) . '&background=0d6efd&color=fff&size=60' ?>"
                             class="rounded border bg-white p-1" width="60" height="60" style="object-fit:contain">
                        <div>
                            <h6 class="fw-bold mb-0"><?= e($s['company_name']) ?></h6>
                            <?php if ($s['verified']): ?><span class="badge bg-success small"><i class="bi bi-check-circle me-1"></i>Verified</span><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($s['description']): ?>
                    <p class="text-muted small mb-2"><?= e(mb_strimwidth($s['description'], 0, 100, '…')) ?></p>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-2 small text-muted mb-3">
                        <?php if ($s['country']): ?><span><i class="bi bi-geo-alt"></i> <?= e($s['city'] ? $s['city'].', ' : '') . e($s['country']) ?></span><?php endif; ?>
                        <?php if ($s['rating'] > 0): ?><span class="text-warning">★ <?= number_format($s['rating'],1) ?></span><?php endif; ?>
                        <?php if ($s['total_products'] > 0): ?><span><i class="bi bi-box"></i> <?= $s['total_products'] ?> products</span><?php endif; ?>
                        <?php if ($s['response_time']): ?><span><i class="bi bi-clock"></i> <?= e($s['response_time']) ?></span><?php endif; ?>
                    </div>
                    <a href="/pages/supplier/profile.php?slug=<?= urlencode($s['slug'] ?? '') ?>" class="btn btn-sm btn-outline-primary w-100">
                        View Profile <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($result['pages'] > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&country=<?= urlencode($country) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
