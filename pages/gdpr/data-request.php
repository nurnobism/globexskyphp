<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$db = getDB();
$user = getCurrentUser();

$stmt = $db->prepare("SELECT * FROM gdpr_requests WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$requests = $stmt->fetchAll();

$pageTitle = 'Data Export Request';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/pages/gdpr/">GDPR</a></li>
            <li class="breadcrumb-item active">Data Request</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="h2 mb-4"><i class="bi bi-download me-2"></i>Request Your Data</h1>

            <div id="alertContainer"></div>

            <!-- Request Form -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-arrow-down me-2"></i>New Data Request</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Under GDPR, you have the right to access and export your personal data. Choose a request type below:</p>

                    <form id="dataRequestForm" method="post" action="/api/gdpr.php?action=request_export">
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Request Type <span class="text-danger">*</span></label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="type" id="typeExport" value="export" checked>
                                <label class="form-check-label" for="typeExport">
                                    <strong>Data Export</strong>
                                    <p class="text-muted small mb-0">Download a full copy of your personal data in a portable format (JSON/CSV).</p>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="typeAccess" value="access">
                                <label class="form-check-label" for="typeAccess">
                                    <strong>Data Access Report</strong>
                                    <p class="text-muted small mb-0">Receive a detailed report of what personal data we hold about you.</p>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Additional Details</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                      placeholder="Optionally describe what specific data you are requesting..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="bi bi-send me-2"></i>Submit Request
                        </button>
                    </form>
                </div>
            </div>

            <!-- Existing Requests -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Your Requests</h5>
                </div>
                <?php if (empty($requests)): ?>
                    <div class="card-body text-center text-muted py-4">
                        <i class="bi bi-inbox display-4"></i>
                        <p class="mt-2 mb-0">You haven't made any data requests yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Completed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $req): ?>
                                    <?php
                                        $statusMap = [
                                            'pending'    => ['warning', 'clock', 'text-dark'],
                                            'processing' => ['info', 'gear', ''],
                                            'completed'  => ['success', 'check-circle', ''],
                                            'rejected'   => ['danger', 'x-circle', ''],
                                        ];
                                        $s = strtolower($req['status'] ?? 'pending');
                                        $badge = $statusMap[$s] ?? ['secondary', 'question-circle', ''];
                                    ?>
                                    <tr>
                                        <td><code>#<?= e($req['id']) ?></code></td>
                                        <td>
                                            <?php if (($req['type'] ?? '') === 'export'): ?>
                                                <i class="bi bi-download text-primary me-1"></i>Export
                                            <?php else: ?>
                                                <i class="bi bi-eye text-info me-1"></i>Access
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $badge[0] ?> <?= $badge[2] ?>">
                                                <i class="bi bi-<?= $badge[1] ?> me-1"></i><?= e(ucfirst($s)) ?>
                                            </span>
                                        </td>
                                        <td><?= formatDate($req['created_at'] ?? '') ?></td>
                                        <td><?php if (!empty($req['completed_at'])): ?><?= formatDate($req['completed_at']) ?><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-3 text-center">
                <small class="text-muted">
                    Requests are typically processed within 30 days as required by GDPR.
                    <a href="/pages/gdpr/">Learn more about your data rights.</a>
                </small>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('dataRequestForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const alert = document.getElementById('alertContainer');
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

    fetch(this.action, { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-2"></i>Submit Request';
            if (data.success || data.status === 'success') {
                alert.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Request submitted successfully. We will process it within 30 days.</div>';
                setTimeout(() => location.reload(), 2000);
            } else {
                alert.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>' + (data.message || data.error || 'Failed to submit request') + '</div>';
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-2"></i>Submit Request';
            alert.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Network error. Please try again.</div>';
        });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
