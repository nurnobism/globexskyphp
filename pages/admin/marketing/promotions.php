<?php
/**
 * pages/admin/marketing/promotions.php — Admin Promotions Manager (PR #13)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/coupons.php';
requireAdmin();

$db   = getDB();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$total  = 0;
$promos = [];
try {
    $total = (int)$db->query('SELECT COUNT(*) FROM promotions')->fetchColumn();
    $stmt = $db->prepare('SELECT * FROM promotions ORDER BY created_at DESC LIMIT ? OFFSET ?');
    $stmt->execute([$perPage, ($page - 1) * $perPage]);
    $promos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* ignore */ }

$totalPages = (int)ceil($total / $perPage);

$pageTitle = 'Promotions Manager';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-fire text-danger me-2"></i>Promotions Manager</h3>
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#createPromoModal">
            <i class="bi bi-plus-lg me-1"></i>New Promotion
        </button>
    </div>

    <!-- Promotions List -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Discount</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th>Featured</th>
                        <th>Views</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($promos)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No promotions found. Create your first one!</td></tr>
                <?php else: ?>
                <?php foreach ($promos as $p): ?>
                <?php
                    $now = time();
                    $start = strtotime($p['start_date']);
                    $end   = strtotime($p['end_date']);
                    if (!$p['is_active']) {
                        $badge = '<span class="badge bg-secondary">Inactive</span>';
                    } elseif ($start > $now) {
                        $badge = '<span class="badge bg-info">Upcoming</span>';
                    } elseif ($end < $now) {
                        $badge = '<span class="badge bg-danger">Ended</span>';
                    } else {
                        $badge = '<span class="badge bg-success">Live</span>';
                    }
                    $discLabel = $p['discount_type'] === 'percentage'
                        ? number_format((float)$p['discount_value'], 0) . '% off'
                        : '$' . number_format((float)$p['discount_value'], 2) . ' off';
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($p['name']) ?></div>
                        <small class="text-muted"><?= e(mb_strimwidth($p['description'] ?? '', 0, 50, '…')) ?></small>
                    </td>
                    <td><span class="badge bg-warning text-dark"><?= $discLabel ?></span></td>
                    <td style="font-size:.8rem">
                        <?= date('M j, Y', strtotime($p['start_date'])) ?>
                        <br>→ <?= date('M j, Y', strtotime($p['end_date'])) ?>
                    </td>
                    <td><?= $badge ?></td>
                    <td><?= $p['is_featured'] ? '<i class="bi bi-star-fill text-warning"></i>' : '<i class="bi bi-star text-muted"></i>' ?></td>
                    <td><?= number_format((int)$p['views_count']) ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="editPromo(<?= (int)$p['id'] ?>)" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deletePromo(<?= (int)$p['id'] ?>)" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <small class="text-muted">Showing <?= count($promos) ?> of <?= $total ?></small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Promotion Modal -->
<div class="modal fade" id="createPromoModal" tabindex="-1" aria-labelledby="createPromoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createPromoModalLabel"><i class="bi bi-fire text-danger me-2"></i>Create Promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="promoForm">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" id="promoId" name="promotion_id" value="">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Name *</label>
                            <input type="text" name="name" id="promoName" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Discount Type</label>
                            <select name="discount_type" id="promoDiscountType" class="form-select">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount ($)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Discount Value</label>
                            <input type="number" name="discount_value" id="promoDiscountValue" class="form-control" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Start Date *</label>
                            <input type="datetime-local" name="start_date" id="promoStartDate" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">End Date *</label>
                            <input type="datetime-local" name="end_date" id="promoEndDate" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" id="promoDescription" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Banner Image URL</label>
                            <input type="url" name="banner_image" id="promoBanner" class="form-control" placeholder="https://…">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_featured" id="promoFeatured" value="1">
                                <label class="form-check-label" for="promoFeatured">Featured on Homepage</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="savePromo()">
                    <i class="bi bi-check-lg me-1"></i>Save Promotion
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;

function savePromo() {
    const form = document.getElementById('promoForm');
    const fd   = new FormData(form);
    const id   = document.getElementById('promoId').value;
    const action = id ? 'update' : 'create';
    if (!document.getElementById('promoFeatured').checked) fd.set('is_featured', '0');

    fetch('/api/promotions.php?action=' + action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
            else alert(d.message || 'Error saving promotion');
        });
}

function editPromo(id) {
    fetch('/api/promotions.php?action=list&per_page=100')
        .then(r => r.json())
        .then(d => {
            const promo = (d.data || []).find(p => p.id == id);
            if (!promo) return;
            document.getElementById('promoId').value          = promo.id;
            document.getElementById('promoName').value        = promo.name;
            document.getElementById('promoDiscountType').value= promo.discount_type;
            document.getElementById('promoDiscountValue').value= promo.discount_value;
            document.getElementById('promoStartDate').value   = promo.start_date.replace(' ', 'T').substring(0,16);
            document.getElementById('promoEndDate').value     = promo.end_date.replace(' ', 'T').substring(0,16);
            document.getElementById('promoDescription').value = promo.description || '';
            document.getElementById('promoBanner').value      = promo.banner_image || '';
            document.getElementById('promoFeatured').checked  = !!promo.is_featured;
            document.getElementById('createPromoModalLabel').textContent = '✏️ Edit Promotion';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('createPromoModal')).show();
        });
}

function deletePromo(id) {
    if (!confirm('Delete this promotion? This cannot be undone.')) return;
    fetch('/api/promotions.php?action=delete', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'promotion_id=' + id + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN),
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.message); });
}
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
