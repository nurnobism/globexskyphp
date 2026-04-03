<?php
/**
 * pages/admin/kyc-management.php — Admin KYC Management (Phase 9)
 */
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/kyc.php';
requireRole(['admin', 'super_admin']);

$db = getDB();

// Stats
try {
    $stats = [
        'pending'  => (int)$db->query('SELECT COUNT(*) FROM kyc_submissions WHERE status="pending"')->fetchColumn(),
        'approved' => (int)$db->query('SELECT COUNT(*) FROM kyc_submissions WHERE status="approved"')->fetchColumn(),
        'rejected' => (int)$db->query('SELECT COUNT(*) FROM kyc_submissions WHERE status="rejected"')->fetchColumn(),
        'l1'       => (int)$db->query('SELECT COUNT(*) FROM kyc_levels WHERE current_level>=1')->fetchColumn(),
        'l2'       => (int)$db->query('SELECT COUNT(*) FROM kyc_levels WHERE current_level>=2')->fetchColumn(),
        'l3'       => (int)$db->query('SELECT COUNT(*) FROM kyc_levels WHERE current_level>=3')->fetchColumn(),
        'l4'       => (int)$db->query('SELECT COUNT(*) FROM kyc_levels WHERE current_level>=4')->fetchColumn(),
    ];
} catch (PDOException $e) {
    $stats = array_fill_keys(['pending','approved','rejected','l1','l2','l3','l4'], 0);
}

$filter = $_GET['status'] ?? 'pending';
$pending = getPendingKYCSubmissions(100);

// All submissions with filter
try {
    $sql = 'SELECT s.*, u.email, u.first_name, u.last_name
            FROM kyc_submissions s JOIN users u ON u.id=s.user_id
            WHERE s.status=? ORDER BY s.submitted_at DESC LIMIT 100';
    $stmt = $db->prepare($sql);
    $stmt->execute([$filter]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $submissions = [];
}

$pageTitle = 'Admin — KYC Management';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-shield-check text-primary me-2"></i>KYC Management</h3>
        <a href="/pages/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <?php $statCards = [
            ['Pending Review', $stats['pending'], 'clock-fill', 'warning'],
            ['Approved', $stats['approved'], 'check-circle-fill', 'success'],
            ['Rejected', $stats['rejected'], 'x-circle-fill', 'danger'],
            ['L1 Verified', $stats['l1'], 'patch-check-fill', 'info'],
            ['L2 Business', $stats['l2'], 'building-fill', 'primary'],
            ['L3 Premium', $stats['l3'], 'star-fill', 'success'],
            ['L4 Gold', $stats['l4'], 'award-fill', 'warning'],
        ]; ?>
        <?php foreach ($statCards as [$label, $val, $icon, $color]): ?>
        <div class="col-6 col-md-3 col-xl-auto flex-grow-1">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-<?= $color ?> bg-opacity-10 p-3">
                        <i class="bi bi-<?= $icon ?> text-<?= $color ?> fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?= number_format($val) ?></h5>
                        <small class="text-muted"><?= $label ?></small>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter Tabs -->
    <ul class="nav nav-tabs mb-3">
        <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $s => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filter === $s ? 'active' : '' ?>"
               href="?status=<?= $s ?>">
                <?= $label ?>
                <?php if ($s === 'pending' && $stats['pending'] > 0): ?>
                    <span class="badge bg-warning text-dark ms-1"><?= $stats['pending'] ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- Submissions Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Level</th>
                            <th>Document</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No <?= $filter ?> submissions</td></tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $s): ?>
                        <tr>
                            <td>
                                <strong><?= e($s['first_name'] . ' ' . $s['last_name']) ?></strong>
                                <br><small class="text-muted"><?= e($s['email']) ?></small>
                            </td>
                            <td><span class="badge bg-primary">L<?= $s['level'] ?></span></td>
                            <td><?= e(str_replace('_', ' ', ucwords($s['document_type'], '_'))) ?></td>
                            <td><small><?= e(date('M j, Y H:i', strtotime($s['submitted_at']))) ?></small></td>
                            <td>
                                <span class="badge bg-<?= ['pending'=>'warning','approved'=>'success','rejected'=>'danger'][$s['status']] ?>">
                                    <?= ucfirst($s['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($s['status'] === 'pending'): ?>
                                <div class="d-flex gap-1">
                                    <form method="POST" action="/api/kyc.php?action=verify" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="submission_id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="decision" value="approved">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="bi bi-check-lg"></i> Approve
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-danger btn-sm"
                                            data-bs-toggle="modal" data-bs-target="#rejectModal"
                                            data-id="<?= $s['id'] ?>">
                                        <i class="bi bi-x-lg"></i> Reject
                                    </button>
                                </div>
                                <?php else: ?>
                                    <?php if ($s['review_notes']): ?>
                                        <small class="text-muted"><?= e(mb_substr($s['review_notes'], 0, 40)) ?>...</small>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject KYC Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/kyc.php?action=verify">
                <?= csrfField() ?>
                <div class="modal-body">
                    <input type="hidden" name="submission_id" id="rejectSubmissionId">
                    <input type="hidden" name="decision" value="rejected">
                    <div class="mb-3">
                        <label class="form-label">Reason for rejection</label>
                        <textarea name="review_notes" class="form-control" rows="3" required
                                  placeholder="Explain why this document is rejected..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('rejectModal').addEventListener('show.bs.modal', function(e) {
    document.getElementById('rejectSubmissionId').value = e.relatedTarget.dataset.id;
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
