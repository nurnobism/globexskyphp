<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();
$idsParam = get('ids', '');
$ids = array_filter(array_map('intval', explode(',', $idsParam)));

$suppliers = [];
if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT s.*, (SELECT COUNT(*) FROM products p WHERE p.supplier_id = s.id) AS product_count FROM suppliers s WHERE s.id IN ($placeholders)");
    $stmt->execute($ids);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$pageTitle = 'Compare Suppliers';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="index.php" class="btn btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 mb-0"><i class="bi bi-layout-three-columns me-2"></i>Compare Suppliers</h1>
    </div>

    <?php if (empty($suppliers)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>No suppliers selected for comparison. Please go back to the
            <a href="index.php">supplier directory</a> and select suppliers to compare.
            <br><small class="text-muted">Usage: compare.php?ids=1,2,3</small>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="bg-light" style="min-width:180px;">Criteria</th>
                            <?php foreach ($suppliers as $supplier): ?>
                                <th class="text-center" style="min-width:200px;">
                                    <?= e($supplier['company_name'] ?? '') ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-semibold bg-light">Rating</td>
                            <?php foreach ($suppliers as $supplier): ?>
                                <td class="text-center"><?= starRating($supplier['rating'] ?? 0) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td class="fw-semibold bg-light">Country</td>
                            <?php foreach ($suppliers as $supplier): ?>
                                <td class="text-center">
                                    <i class="bi bi-geo-alt me-1"></i><?= e($supplier['country'] ?? '—') ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td class="fw-semibold bg-light">Verified</td>
                            <?php foreach ($suppliers as $supplier): ?>
                                <td class="text-center">
                                    <?php if (!empty($supplier['is_verified'])): ?>
                                        <span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i>Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td class="fw-semibold bg-light">Response Rate</td>
                            <?php foreach ($suppliers as $supplier): ?>
                                <td class="text-center"><?= e($supplier['response_rate'] ?? '—') ?>%</td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td class="fw-semibold bg-light">Delivery Time</td>
                            <?php foreach ($suppliers as $supplier): ?>
                                <td class="text-center"><?= e($supplier['delivery_time'] ?? '—') ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td class="fw-semibold bg-light">Min Order</td>
                            <?php foreach ($suppliers as $supplier): ?>
                                <td class="text-center"><?= e($supplier['min_order'] ?? '—') ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td class="fw-semibold bg-light">Certifications</td>
                            <?php foreach ($suppliers as $supplier): ?>
                                <td class="text-center">
                                    <?php
                                    $certs = !empty($supplier['certifications']) ? explode(',', $supplier['certifications']) : [];
                                    if (!empty($certs)):
                                        foreach ($certs as $cert): ?>
                                            <span class="badge bg-info me-1 mb-1"><?= e(trim($cert)) ?></span>
                                        <?php endforeach;
                                    else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td class="fw-semibold bg-light">Product Count</td>
                            <?php foreach ($suppliers as $supplier): ?>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?= (int) ($supplier['product_count'] ?? 0) ?></span>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td class="fw-semibold bg-light">Member Since</td>
                            <?php foreach ($suppliers as $supplier): ?>
                                <td class="text-center"><?= formatDate($supplier['created_at'] ?? '') ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-center mt-3">
            <?php foreach ($suppliers as $supplier): ?>
                <a href="detail.php?id=<?= (int) $supplier['id'] ?>" class="btn btn-outline-primary btn-sm me-2">
                    View <?= e($supplier['company_name'] ?? '') ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
