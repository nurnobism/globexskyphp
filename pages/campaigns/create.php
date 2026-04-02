<?php
require_once __DIR__ . '/../../includes/middleware.php';
// requireRole('admin') would go here; using requireAuth() until role middleware is available
requireAuth();
$db = getDB();

$pageTitle = 'Create Campaign';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Marketing Campaigns</a></li>
                    <li class="breadcrumb-item active">Create Campaign</li>
                </ol>
            </nav>

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-megaphone me-2 text-primary"></i>New Marketing Campaign
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="/api/campaigns.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="create">

                        <!-- Title -->
                        <div class="mb-4">
                            <label for="title" class="form-label fw-semibold">Campaign Title <span class="text-danger">*</span></label>
                            <input type="text" id="title" name="title" class="form-control"
                                   placeholder="e.g. Summer 2025 Flash Sale" required>
                            <div class="form-text">Choose a clear, compelling name for this campaign.</div>
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <label for="description" class="form-label fw-semibold">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="4"
                                      placeholder="Describe what this campaign offers, who it targets, and any special terms…"></textarea>
                            <div class="form-text">This text is shown to buyers on the campaign page.</div>
                        </div>

                        <div class="row g-4 mb-4">
                            <!-- Campaign Type -->
                            <div class="col-md-6">
                                <label for="type" class="form-label fw-semibold">Campaign Type <span class="text-danger">*</span></label>
                                <select id="type" name="type" class="form-select" required>
                                    <option value="">Select type…</option>
                                    <option value="flash">⚡ Flash Sale</option>
                                    <option value="seasonal">☀️ Seasonal</option>
                                    <option value="clearance">🗑️ Clearance</option>
                                    <option value="promotional">🎁 Promotional</option>
                                </select>
                                <div class="form-text">Determines how the campaign is displayed.</div>
                            </div>

                            <!-- Discount Percent -->
                            <div class="col-md-6">
                                <label for="discount_percent" class="form-label fw-semibold">Discount (%)</label>
                                <div class="input-group">
                                    <input type="number" id="discount_percent" name="discount_percent"
                                           class="form-control" min="0" max="100" step="1"
                                           placeholder="e.g. 20">
                                    <span class="input-group-text"><i class="bi bi-percent"></i></span>
                                </div>
                                <div class="form-text">Enter 0 for no discount, or up to 100.</div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <!-- Start Date -->
                            <div class="col-md-6">
                                <label for="start_date" class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                                <input type="date" id="start_date" name="start_date" class="form-control" required>
                            </div>

                            <!-- End Date -->
                            <div class="col-md-6">
                                <label for="end_date" class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                                <input type="date" id="end_date" name="end_date" class="form-control" required>
                            </div>
                        </div>

                        <!-- Preview Card -->
                        <div class="mb-4 p-3 bg-light rounded-3 border">
                            <div class="small text-muted mb-2 fw-semibold"><i class="bi bi-eye me-1"></i>Preview</div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-3 bg-primary bg-opacity-10 p-3">
                                    <i class="bi bi-tag-fill fs-3 text-primary" id="preview-icon"></i>
                                </div>
                                <div>
                                    <div class="fw-bold" id="preview-title">Campaign title will appear here</div>
                                    <div id="preview-discount" class="text-muted small"></div>
                                    <div id="preview-dates" class="text-muted small"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex align-items-center gap-2 pt-3 border-top">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check-circle me-1"></i> Create Campaign
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const typeIcons = { flash: 'lightning-charge-fill', seasonal: 'sun-fill', clearance: 'trash3-fill', promotional: 'gift-fill' };

function updatePreview() {
    const title    = document.getElementById('title').value || 'Campaign title will appear here';
    const discount = document.getElementById('discount_percent').value;
    const start    = document.getElementById('start_date').value;
    const end      = document.getElementById('end_date').value;
    const type     = document.getElementById('type').value;

    document.getElementById('preview-title').textContent = title;

    const discountEl = document.getElementById('preview-discount');
    discountEl.textContent = discount > 0 ? discount + '% discount applied' : '';

    const datesEl = document.getElementById('preview-dates');
    if (start && end) {
        datesEl.textContent = 'Runs: ' + start + ' → ' + end;
    } else if (start) {
        datesEl.textContent = 'Starts: ' + start;
    } else {
        datesEl.textContent = '';
    }

    const iconEl = document.getElementById('preview-icon');
    const iconName = typeIcons[type] || 'tag-fill';
    iconEl.className = 'bi bi-' + iconName + ' fs-3 text-primary';
}

['title','discount_percent','start_date','end_date','type'].forEach(function (id) {
    document.getElementById(id).addEventListener('input', updatePreview);
    document.getElementById(id).addEventListener('change', updatePreview);
});

document.getElementById('start_date').addEventListener('change', function () {
    const endInput = document.getElementById('end_date');
    if (endInput.value && endInput.value < this.value) endInput.value = this.value;
    endInput.min = this.value;
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
