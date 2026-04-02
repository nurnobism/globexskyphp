<?php
/**
 * pages/api-platform/keys.php — API Key Management
 */

require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db     = getDB();
$userId = $_SESSION['user_id'];

$keyStmt = $db->prepare('SELECT id, name, key_prefix, created_at, last_used_at, status, revoked_at
    FROM api_keys WHERE user_id = ? ORDER BY created_at DESC');
$keyStmt->execute([$userId]);
$keys = $keyStmt->fetchAll();

$pageTitle = 'API Key Management';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4 class="fw-bold mb-0"><i class="bi bi-key me-2" style="color:#FF6B35;"></i>API Keys</h4>
        <div class="d-flex gap-2">
            <a href="/pages/api-platform/dashboard.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
            <button class="btn btn-sm text-white" style="background:#FF6B35;" data-bs-toggle="modal" data-bs-target="#createKeyModal">
                <i class="bi bi-plus-lg me-1"></i>New API Key
            </button>
        </div>
    </div>

    <!-- New key banner -->
    <div id="newKeyBanner" class="alert alert-success d-none">
        <strong><i class="bi bi-check-circle me-1"></i>New API key created!</strong>
        Copy your key now — it will not be shown again.<br>
        <code id="newKeyValue" class="fs-6"></code>
        <button class="btn btn-sm btn-outline-success ms-2" onclick="copyNewKey()">
            <i class="bi bi-clipboard" id="newCopyIcon"></i> Copy
        </button>
    </div>

    <div id="alertBox" class="d-none mb-3"></div>

    <!-- Keys table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Key</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Last Used</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($keys)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-key display-4 d-block mb-2"></i>
                            No API keys yet. Create your first key to get started.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($keys as $key): ?>
                    <tr id="key-row-<?= $key['id'] ?>">
                        <td class="fw-semibold"><?= e($key['name']) ?></td>
                        <td>
                            <code class="bg-light px-2 py-1 rounded">
                                <?= e($key['key_prefix']) ?><?= str_repeat('•', 20) ?>
                            </code>
                        </td>
                        <td>
                            <?php if ($key['status'] === 'active'): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                            <?php else: ?>
                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Revoked</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= formatDate($key['created_at']) ?></td>
                        <td class="text-muted small"><?= $key['last_used_at'] ? formatDate($key['last_used_at']) : 'Never' ?></td>
                        <td>
                            <?php if ($key['status'] === 'active'): ?>
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="revokeKey(<?= $key['id'] ?>, '<?= e($key['name']) ?>')">
                                <i class="bi bi-trash me-1"></i>Revoke
                            </button>
                            <?php else: ?>
                            <span class="text-muted small">Revoked <?= $key['revoked_at'] ? formatDate($key['revoked_at']) : '' ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="alert alert-warning mt-3">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Security tip:</strong> Never share API keys or commit them to version control.
        Revoke and regenerate immediately if compromised. Max 10 active keys per account.
    </div>
</div>

<!-- Create Key Modal -->
<div class="modal fade" id="createKeyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#1B2A4A;">
                <h5 class="modal-title text-white"><i class="bi bi-plus-circle me-2"></i>Create New API Key</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="createKeyForm">
                <?= csrfField() ?>
                <div class="modal-body">
                    <label class="form-label fw-semibold">Key Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Production Server, My App" required maxlength="60">
                    <div class="form-text">A descriptive label to identify where this key is used.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background:#FF6B35;" id="createBtn">
                        <i class="bi bi-plus-lg me-1"></i>Create Key
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const csrfToken = document.querySelector('input[name="_csrf_token"]').value;

document.getElementById('createKeyForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('createBtn');
    btn.disabled = true;
    const res = await fetch('/api/api-platform.php?action=create_key', {method:'POST', body: new FormData(this)});
    const data = await res.json();
    if (data.success) {
        bootstrap.Modal.getInstance(document.getElementById('createKeyModal')).hide();
        document.getElementById('newKeyValue').textContent = data.api_key;
        document.getElementById('newKeyBanner').classList.remove('d-none');
        setTimeout(() => location.reload(), 8000);
    } else {
        showAlert(data.error || 'Failed to create key.', 'danger');
    }
    btn.disabled = false;
});

async function revokeKey(id, name) {
    if (!confirm(`Revoke API key "${name}"? This cannot be undone.`)) return;
    const fd = new FormData();
    fd.append('key_id', id);
    fd.append('_csrf_token', csrfToken);
    const res = await fetch('/api/api-platform.php?action=revoke_key', {method:'POST', body: fd});
    const data = await res.json();
    if (data.success) {
        const row = document.getElementById('key-row-' + id);
        row.querySelector('td:nth-child(3)').innerHTML = '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Revoked</span>';
        row.querySelector('td:last-child').innerHTML = '<span class="text-muted small">Just revoked</span>';
    } else {
        showAlert(data.error || 'Revoke failed.', 'danger');
    }
}

function copyNewKey() {
    navigator.clipboard.writeText(document.getElementById('newKeyValue').textContent).then(() => {
        document.getElementById('newCopyIcon').className = 'bi bi-clipboard-check';
    });
}

function showAlert(msg, type) {
    const box = document.getElementById('alertBox');
    box.className = `alert alert-${type}`;
    box.textContent = msg;
    box.classList.remove('d-none');
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
