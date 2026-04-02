<?php
/**
 * pages/inspection/report.php — Inspection Report View
 */

require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db   = getDB();
$id   = (int)get('id', 0);
if (!$id) { redirect('/pages/inspection/tracking.php'); }

$stmt = $db->prepare('SELECT i.*, u.name buyer_name FROM inspections i
    LEFT JOIN users u ON u.id = i.buyer_id
    WHERE i.id = ? AND (i.buyer_id = ? OR ? = "admin")');
$stmt->execute([$id, $_SESSION['user_id'], $_SESSION['role'] ?? '']);
$inspection = $stmt->fetch();
if (!$inspection) { redirect('/pages/inspection/tracking.php'); }

$rStmt = $db->prepare('SELECT * FROM inspection_reports WHERE inspection_id = ?');
$rStmt->execute([$id]);
$report = $rStmt->fetch();

$checklist = $report ? json_decode($report['checklist'] ?? '[]', true) : [];
$score     = $report ? (int)$report['overall_score'] : 0;
$result    = $report['result'] ?? 'pending';

$pageTitle = 'Inspection Report — ' . ($inspection['reference_no'] ?? '');
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <a href="/pages/inspection/tracking.php?inspection_id=<?= $id ?>" class="text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i>Back to Tracking
        </a>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Print Report
        </button>
    </div>

    <!-- Header card -->
    <div class="card border-0 shadow-sm mb-4" style="border-top:4px solid #FF6B35!important;">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="fw-bold mb-1">Quality Inspection Report</h4>
                    <p class="text-muted mb-0">Reference: <strong><?= e($inspection['reference_no']) ?></strong></p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <?php if ($result === 'pass'): ?>
                    <span class="badge bg-success fs-5 px-4 py-2"><i class="bi bi-check-circle me-1"></i>PASS</span>
                    <?php elseif ($result === 'fail'): ?>
                    <span class="badge bg-danger fs-5 px-4 py-2"><i class="bi bi-x-circle me-1"></i>FAIL</span>
                    <?php else: ?>
                    <span class="badge bg-secondary fs-5 px-4 py-2">PENDING</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Inspection details -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header fw-semibold" style="background:#1B2A4A;color:#fff;">Inspection Details</div>
                <div class="card-body">
                    <?php $details = [
                        'Supplier'        => $inspection['supplier_name'],
                        'Product'         => $inspection['product_name'],
                        'Quantity'        => number_format($inspection['quantity']) . ' units',
                        'Type'            => ucwords(str_replace('_', ' ', $inspection['inspection_type'])),
                        'Inspection Date' => formatDate($inspection['inspection_date']),
                        'Factory'         => $inspection['factory_address'],
                        'Buyer'           => $inspection['buyer_name'],
                        'Submitted'       => formatDate($inspection['created_at']),
                    ];
                    foreach ($details as $label => $val): ?>
                    <div class="row py-2 border-bottom">
                        <div class="col-5 text-muted small"><?= $label ?></div>
                        <div class="col-7 fw-semibold small"><?= e((string)$val) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($report): ?>
                    <div class="mt-3 text-center">
                        <div class="fw-bold text-muted small mb-1">Overall Score</div>
                        <div class="display-4 fw-bold" style="color:#FF6B35;"><?= $score ?><small class="fs-5 text-muted">/100</small></div>
                        <div class="progress mt-2" style="height:10px;">
                            <div class="progress-bar" style="width:<?= $score ?>%;background:#FF6B35;"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Checklist -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header fw-semibold" style="background:#1B2A4A;color:#fff;">Checklist Items</div>
                <div class="card-body p-0">
                    <?php if (empty($checklist)): ?>
                    <p class="text-muted text-center py-4">No checklist data available.</p>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($checklist as $item): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?= e($item['label'] ?? '') ?></span>
                            <?php if (($item['status'] ?? '') === 'pass'): ?>
                            <span class="badge bg-success"><i class="bi bi-check-lg"></i> Pass</span>
                            <?php elseif (($item['status'] ?? '') === 'fail'): ?>
                            <span class="badge bg-danger"><i class="bi bi-x-lg"></i> Fail</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">N/A</span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Photo placeholders -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header fw-semibold" style="background:#1B2A4A;color:#fff;">Inspection Photos</div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="col-6 col-md-3">
                            <div class="rounded d-flex align-items-center justify-content-center bg-light"
                                 style="height:90px;border:2px dashed #dee2e6;">
                                <i class="bi bi-image text-muted fs-3"></i>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Photos will be attached upon inspector submission.</p>
                </div>
            </div>

            <!-- Recommendations -->
            <?php if ($report && $report['recommendations']): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-semibold" style="background:#1B2A4A;color:#fff;">Recommendations</div>
                <div class="card-body">
                    <p class="mb-0"><?= nl2br(e($report['recommendations'])) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
