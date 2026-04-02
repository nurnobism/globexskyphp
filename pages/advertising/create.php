<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
$campaign = null;
$isEdit = false;

if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM advertising_campaigns WHERE id=? AND user_id=?');
    $stmt->execute([$id, $_SESSION['user_id']]);
    $campaign = $stmt->fetch();
    if ($campaign) {
        $isEdit = true;
    }
}

$pageTitle = $isEdit ? 'Edit Campaign' : 'Create Campaign';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Advertising Campaigns</a></li>
                    <li class="breadcrumb-item active"><?= $isEdit ? 'Edit Campaign' : 'Create Campaign' ?></li>
                </ol>
            </nav>

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'plus-circle' ?> me-2 text-primary"></i>
                        <?= $isEdit ? 'Edit Campaign' : 'New Advertising Campaign' ?>
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="/api/advertising.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
                        <?php if ($isEdit): ?>
                            <input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
                        <?php endif; ?>

                        <!-- Title -->
                        <div class="mb-4">
                            <label for="title" class="form-label fw-semibold">Campaign Title <span class="text-danger">*</span></label>
                            <input type="text" id="title" name="title" class="form-control"
                                   placeholder="e.g. Summer Sale Banner Campaign"
                                   value="<?= e($campaign['title'] ?? '') ?>" required>
                            <div class="form-text">A clear, descriptive name for this campaign.</div>
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <label for="description" class="form-label fw-semibold">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3"
                                      placeholder="Describe the goals and details of this campaign…"><?= e($campaign['description'] ?? '') ?></textarea>
                        </div>

                        <div class="row g-4 mb-4">
                            <!-- Budget -->
                            <div class="col-md-6">
                                <label for="budget" class="form-label fw-semibold">Budget ($) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                                    <input type="number" id="budget" name="budget" class="form-control"
                                           placeholder="0.00" min="0" step="0.01"
                                           value="<?= e($campaign['budget'] ?? '') ?>" required>
                                </div>
                            </div>

                            <!-- Campaign Type -->
                            <div class="col-md-6">
                                <label for="type" class="form-label fw-semibold">Campaign Type <span class="text-danger">*</span></label>
                                <select id="type" name="type" class="form-select" required>
                                    <option value="">Select a type…</option>
                                    <?php foreach (['banner' => 'Banner Ad', 'sponsored' => 'Sponsored Listing', 'email' => 'Email Campaign', 'social' => 'Social Media'] as $val => $label): ?>
                                        <option value="<?= $val ?>" <?= ($campaign['type'] ?? '') === $val ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <!-- Start Date -->
                            <div class="col-md-6">
                                <label for="start_date" class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                                <input type="date" id="start_date" name="start_date" class="form-control"
                                       value="<?= e($campaign['start_date'] ?? '') ?>" required>
                            </div>

                            <!-- End Date -->
                            <div class="col-md-6">
                                <label for="end_date" class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                                <input type="date" id="end_date" name="end_date" class="form-control"
                                       value="<?= e($campaign['end_date'] ?? '') ?>" required>
                            </div>
                        </div>

                        <!-- Target Audience -->
                        <div class="mb-4">
                            <label for="target_audience" class="form-label fw-semibold">Target Audience</label>
                            <textarea id="target_audience" name="target_audience" class="form-control" rows="3"
                                      placeholder="Describe your target audience (e.g. SME buyers in manufacturing sector)…"><?= e($campaign['target_audience'] ?? '') ?></textarea>
                            <div class="form-text">Optional: specify the audience segment you want to reach.</div>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex align-items-center gap-2 pt-2 border-top">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check-circle me-1"></i>
                                <?= $isEdit ? 'Save Changes' : 'Create Campaign' ?>
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
document.getElementById('start_date').addEventListener('change', function () {
    const endInput = document.getElementById('end_date');
    if (endInput.value && endInput.value < this.value) {
        endInput.value = this.value;
    }
    endInput.min = this.value;
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
