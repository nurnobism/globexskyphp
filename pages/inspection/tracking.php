<?php
/**
 * pages/inspection/tracking.php — Inspection Status Timeline
 */

require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db           = getDB();
$inspectionId = (int)get('inspection_id', 0);
$inspection   = null;
$timeline     = [];

$steps = [
    'requested'          => ['label' => 'Requested',          'icon' => 'bi-file-earmark-plus'],
    'scheduled'          => ['label' => 'Scheduled',           'icon' => 'bi-calendar-check'],
    'inspector_assigned' => ['label' => 'Inspector Assigned',  'icon' => 'bi-person-badge'],
    'in_progress'        => ['label' => 'In Progress',         'icon' => 'bi-clipboard2-pulse'],
    'report_ready'       => ['label' => 'Report Ready',        'icon' => 'bi-file-earmark-text'],
    'completed'          => ['label' => 'Completed',           'icon' => 'bi-patch-check-fill'],
];
$stepKeys = array_keys($steps);

if ($inspectionId) {
    $stmt = $db->prepare('SELECT i.*, u.name buyer_name FROM inspections i
        LEFT JOIN users u ON u.id = i.buyer_id
        WHERE i.id = ? AND (i.buyer_id = ? OR ? = "admin")');
    $stmt->execute([$inspectionId, $_SESSION['user_id'], $_SESSION['role'] ?? '']);
    $inspection = $stmt->fetch();

    if ($inspection) {
        $tStmt = $db->prepare('SELECT * FROM inspection_timeline WHERE inspection_id = ? ORDER BY created_at ASC');
        $tStmt->execute([$inspectionId]);
        $timeline = $tStmt->fetchAll();
    }
}

// User's inspections list
$listStmt = $db->prepare('SELECT * FROM inspections WHERE buyer_id = ? ORDER BY created_at DESC LIMIT 20');
$listStmt->execute([$_SESSION['user_id']]);
$myInspections = $listStmt->fetchAll();

$pageTitle = 'Inspection Tracking';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-search me-2" style="color:#FF6B35;"></i>Inspection Tracking</h3>
        <a href="/pages/inspection/request.php" class="btn text-white" style="background:#FF6B35;">
            <i class="bi bi-plus-lg me-1"></i>New Request
        </a>
    </div>
    <div class="row g-4">
        <!-- Sidebar: list -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-semibold" style="background:#1B2A4A;color:#fff;">My Inspections</div>
                <div class="list-group list-group-flush" style="max-height:520px;overflow-y:auto;">
                    <?php if (empty($myInspections)): ?>
                    <div class="list-group-item text-muted text-center py-4">No inspections yet.</div>
                    <?php else: ?>
                    <?php foreach ($myInspections as $row):
                        $active = $inspectionId === (int)$row['id'];
                        $badges = ['requested'=>'secondary','scheduled'=>'info','inspector_assigned'=>'primary','in_progress'=>'warning','report_ready'=>'success','completed'=>'success','cancelled'=>'danger'];
                        $b = $badges[$row['status']] ?? 'secondary';
                    ?>
                    <a href="?inspection_id=<?= $row['id'] ?>"
                       class="list-group-item list-group-item-action <?= $active ? 'active' : '' ?>">
                        <div class="d-flex justify-content-between">
                            <strong><?= e($row['reference_no']) ?></strong>
                            <span class="badge bg-<?= $b ?>"><?= ucwords(str_replace('_',' ',$row['status'])) ?></span>
                        </div>
                        <small class="d-block text-<?= $active ? 'white' : 'muted' ?>"><?= e($row['product_name']) ?></small>
                        <small class="text-<?= $active ? 'white-50' : 'muted' ?>"><?= formatDate($row['created_at']) ?></small>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main: timeline -->
        <div class="col-lg-8">
            <?php if (!$inspection): ?>
            <div class="card border-0 shadow-sm text-center py-5">
                <i class="bi bi-search display-3 text-muted"></i>
                <h5 class="mt-3">Select an inspection to view its timeline</h5>
            </div>
            <?php else:
                $doneKeys  = array_column($timeline, 'status');
                $currentIdx = -1;
                foreach ($stepKeys as $k => $sk) { if (in_array($sk, $doneKeys)) $currentIdx = $k; }
            ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                        <div>
                            <h5 class="fw-bold mb-0"><?= e($inspection['reference_no']) ?></h5>
                            <span class="text-muted"><?= e($inspection['product_name']) ?> — <?= e($inspection['supplier_name']) ?></span>
                        </div>
                        <span class="badge fs-6" style="background:#FF6B35;">$<?= number_format($inspection['price'],2) ?></span>
                    </div>
                    <!-- Timeline steps -->
                    <div class="position-relative mt-4">
                        <?php foreach ($stepKeys as $idx => $sk):
                            $done   = in_array($sk, $doneKeys);
                            $isCurr = $idx === $currentIdx;
                            $tEntry = null;
                            foreach ($timeline as $te) { if ($te['status'] === $sk) { $tEntry = $te; break; } }
                            $color  = $done ? '#FF6B35' : '#dee2e6';
                            $textC  = $done ? '#FF6B35' : '#adb5bd';
                        ?>
                        <div class="d-flex mb-4 <?= $idx < count($stepKeys)-1 ? 'step-item' : '' ?>">
                            <div class="me-3 d-flex flex-column align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                     style="width:40px;height:40px;background:<?= $color ?>;color:#fff;font-size:1.1rem;">
                                    <i class="<?= $steps[$sk]['icon'] ?>"></i>
                                </div>
                                <?php if ($idx < count($stepKeys)-1): ?>
                                <div style="width:2px;flex:1;min-height:24px;background:<?= $done && $idx < $currentIdx ? '#FF6B35' : '#dee2e6' ?>;"></div>
                                <?php endif; ?>
                            </div>
                            <div class="pt-1">
                                <div class="fw-semibold" style="color:<?= $textC ?>;"><?= $steps[$sk]['label'] ?></div>
                                <?php if ($tEntry): ?>
                                <small class="text-muted"><?= formatDateTime($tEntry['created_at']) ?></small>
                                <?php if ($tEntry['notes']): ?>
                                <p class="mb-0 small mt-1"><?= e($tEntry['notes']) ?></p>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (in_array($inspection['status'], ['report_ready','completed'])): ?>
                    <a href="/pages/inspection/report.php?id=<?= $inspection['id'] ?>" class="btn text-white" style="background:#1B2A4A;">
                        <i class="bi bi-file-earmark-text me-1"></i>View Full Report
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
