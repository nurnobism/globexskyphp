<?php
/**
 * pages/supplier/earnings/methods.php — Manage Payout Methods (PR #11)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/payouts.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db         = getDB();
$supplierId = (int)$_SESSION['user_id'];

$error   = '';
$success = '';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['form_action'] ?? '';

        switch ($action) {
            case 'save':
                $method    = trim($_POST['method'] ?? '');
                $isDefault = !empty($_POST['is_default']);
                $details   = [];
                switch ($method) {
                    case 'bank_transfer':
                        $details = [
                            'account_name'   => trim($_POST['bank_account_name'] ?? ''),
                            'bank_name'      => trim($_POST['bank_name'] ?? ''),
                            'account_number' => trim($_POST['account_number'] ?? ''),
                            'routing_number' => trim($_POST['routing_number'] ?? ''),
                            'swift_code'     => trim($_POST['swift_code'] ?? ''),
                            'country'        => trim($_POST['bank_country'] ?? ''),
                        ];
                        break;
                    case 'paypal':
                        $details = ['email' => trim($_POST['paypal_email'] ?? '')];
                        break;
                    case 'wise':
                        $details = [
                            'email'      => trim($_POST['wise_email'] ?? ''),
                            'account_id' => trim($_POST['wise_account_id'] ?? ''),
                        ];
                        break;
                }
                $result = savePayoutMethod($supplierId, $method, $details, $isDefault);
                if ($result['success']) {
                    $success = 'Payout method saved successfully.';
                } else {
                    $error = $result['error'];
                }
                break;

            case 'delete':
                $methodId = (int)($_POST['method_id'] ?? 0);
                $result   = deletePayoutMethod($methodId, $supplierId);
                if ($result['success']) {
                    $success = 'Payout method deleted.';
                } else {
                    $error = $result['error'];
                }
                break;

            case 'set_default':
                $methodId = (int)($_POST['method_id'] ?? 0);
                $result   = setDefaultPayoutMethod($methodId, $supplierId);
                if ($result['success']) {
                    $success = 'Default payout method updated.';
                } else {
                    $error = $result['error'];
                }
                break;
        }
    }
}

$methods   = getPayoutMethods($supplierId);
$pageTitle = 'Payout Methods';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-4" style="max-width:900px;">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-wallet2 text-primary me-2"></i>Payout Methods</h3>
        <a href="/pages/supplier/earnings/" class="btn btn-outline-secondary btn-sm">← Earnings</a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <!-- Security Info -->
    <div class="alert alert-light border d-flex gap-2 align-items-start mb-4">
        <i class="bi bi-shield-check text-success fs-5 flex-shrink-0 mt-1"></i>
        <div>
            <strong>Your payout details are stored securely.</strong>
            You can change your default method at any time. Only the last 4 digits of account numbers are shown.
        </div>
    </div>

    <div class="row g-4">
        <!-- Saved Methods -->
        <div class="col-md-7">
            <h5 class="fw-semibold mb-3">Saved Methods</h5>
            <?php if (empty($methods)): ?>
            <div class="text-center text-muted py-4 border rounded bg-light">
                <i class="bi bi-wallet2 fs-1 d-block mb-2"></i>
                No saved payout methods yet. Add one below.
            </div>
            <?php else: ?>
            <?php foreach ($methods as $m):
                $masked = maskPayoutDetails($m['details_decoded']);
                $icon   = match($m['method_type']) {
                    'bank_transfer' => '🏦',
                    'paypal'        => '💙',
                    'wise'          => '💚',
                    default         => '💳',
                };
                $summary = match($m['method_type']) {
                    'bank_transfer' => ($masked['bank_name'] ?? '') . ' — ···' . substr($masked['account_number'] ?? '', -4),
                    'paypal'        => $masked['email'] ?? '',
                    'wise'          => $masked['email'] ?? $masked['account_id'] ?? '',
                    default         => '',
                };
            ?>
            <div class="card border-0 shadow-sm mb-3 <?= $m['is_default'] ? 'border-success border-start border-3' : '' ?>">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="fs-2 flex-shrink-0"><?= $icon ?></div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">
                            <?= payoutMethodLabel($m['method_type']) ?>
                            <?php if ($m['is_default']): ?>
                            <span class="badge bg-success ms-1">Default</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted"><?= e($summary) ?></small>
                    </div>
                    <div class="d-flex gap-1 flex-shrink-0">
                        <?php if (!$m['is_default']): ?>
                        <form method="POST" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="form_action" value="set_default">
                            <input type="hidden" name="method_id" value="<?= (int)$m['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-success" title="Set Default">
                                <i class="bi bi-star"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('Delete this payout method?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="method_id" value="<?= (int)$m['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Add New Method Form -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-plus-circle me-1"></i>Add New Method</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="form_action" value="save">

                        <div class="mb-3">
                            <label class="form-label small">Method Type</label>
                            <select name="method" id="addMethodType" class="form-select form-select-sm"
                                    onchange="showAddFields()">
                                <option value="">— Select —</option>
                                <option value="bank_transfer">🏦 Bank Transfer</option>
                                <option value="paypal">💙 PayPal</option>
                                <option value="wise">💚 Wise</option>
                            </select>
                        </div>

                        <!-- Bank Transfer -->
                        <div id="addBankFields" style="display:none;">
                            <input type="text" name="bank_account_name" class="form-control form-control-sm mb-2"
                                   placeholder="Account Holder Name">
                            <input type="text" name="bank_name" class="form-control form-control-sm mb-2"
                                   placeholder="Bank Name">
                            <input type="text" name="account_number" class="form-control form-control-sm mb-2"
                                   placeholder="Account Number">
                            <input type="text" name="routing_number" class="form-control form-control-sm mb-2"
                                   placeholder="Routing / Sort Code">
                            <input type="text" name="swift_code" class="form-control form-control-sm mb-2"
                                   placeholder="SWIFT / BIC">
                            <input type="text" name="bank_country" class="form-control form-control-sm mb-2"
                                   placeholder="Country">
                        </div>

                        <!-- PayPal -->
                        <div id="addPaypalFields" style="display:none;">
                            <input type="email" name="paypal_email" class="form-control form-control-sm mb-2"
                                   placeholder="PayPal email address">
                        </div>

                        <!-- Wise -->
                        <div id="addWiseFields" style="display:none;">
                            <input type="email" name="wise_email" class="form-control form-control-sm mb-2"
                                   placeholder="Wise email">
                            <input type="text" name="wise_account_id" class="form-control form-control-sm mb-2"
                                   placeholder="Or Wise Account ID">
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_default" value="1" id="addIsDefault">
                            <label class="form-check-label small" for="addIsDefault">Set as default method</label>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-plus me-1"></i>Save Method
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showAddFields() {
    const m = document.getElementById('addMethodType').value;
    document.getElementById('addBankFields').style.display   = m === 'bank_transfer' ? 'block' : 'none';
    document.getElementById('addPaypalFields').style.display = m === 'paypal'        ? 'block' : 'none';
    document.getElementById('addWiseFields').style.display   = m === 'wise'          ? 'block' : 'none';
}
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
