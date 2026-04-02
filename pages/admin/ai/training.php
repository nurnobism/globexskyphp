<?php
require_once __DIR__ . '/../../../includes/middleware.php';
requireAdmin();

$db = getDB();
$datasets = $db->query("SELECT * FROM ai_training_data ORDER BY created_at DESC LIMIT 100")->fetchAll();

$pageTitle = 'AI Training Data';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-database-fill text-success me-2"></i>AI Training Data</h3>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDataModal">
                <i class="bi bi-plus-circle me-1"></i> Add Entry
            </button>
            <a href="/pages/admin/ai/index.php" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Type</th><th>Input</th><th>Output</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($datasets)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No training data yet.</td></tr>
                <?php else: ?>
                <?php foreach ($datasets as $d): ?>
                <tr>
                    <td><span class="badge bg-info"><?= e($d['data_type'] ?? 'qa') ?></span></td>
                    <td><div class="text-truncate" style="max-width:250px"><?= e($d['input_text']) ?></div></td>
                    <td><div class="text-truncate" style="max-width:250px"><?= e($d['output_text']) ?></div></td>
                    <td><span class="badge bg-<?= ($d['status'] ?? 'pending') === 'approved' ? 'success' : 'warning' ?>"><?= ucfirst($d['status'] ?? 'pending') ?></span></td>
                    <td><?= formatDate($d['created_at']) ?></td>
                    <td>
                        <form method="POST" action="/api/admin.php?action=delete_training_data" onsubmit="return confirm('Delete this entry?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addDataModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Training Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/admin.php?action=add_training_data">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Data Type</label>
                            <select name="data_type" class="form-select">
                                <option value="qa">Q&A</option>
                                <option value="classification">Classification</option>
                                <option value="completion">Completion</option>
                                <option value="instruction">Instruction</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Input / Prompt *</label>
                            <textarea name="input_text" class="form-control" rows="3" required placeholder="Input text or question..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Expected Output / Answer *</label>
                            <textarea name="output_text" class="form-control" rows="3" required placeholder="Expected response..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
