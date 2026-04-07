<?php
/**
 * pages/supplier/earnings/withdraw.php — Withdrawal Request Page (PR #11)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/payouts.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db         = getDB();
$supplierId = (int)$_SESSION['user_id'];
$balance    = getSupplierBalance($supplierId);
$methods    = getPayoutMethods($supplierId);

$error   = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_withdrawal'])) {
    if (!verifyCsrf()) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        $amount         = round((float)($_POST['amount'] ?? 0), 2);
        $method         = trim($_POST['payout_method'] ?? '');
        $payoutMethodId = (int)($_POST['payout_method_id'] ?? 0);
        $saveMethod     = !empty($_POST['save_method']);
        $isDefault      = !empty($_POST['is_default']);

        // Collect account details
        $accountDetails = [];
        if ($payoutMethodId > 0) {
            foreach ($methods as $m) {
                if ((int)$m['id'] === $payoutMethodId) {
                    $method         = $m['method_type'];
                    $accountDetails = $m['details_decoded'];
                    break;
                }
            }
        } else {
            switch ($method) {
                case 'bank_transfer':
                    $accountDetails = [
                        'account_name'   => trim($_POST['bank_account_name'] ?? ''),
                        'bank_name'      => trim($_POST['bank_name'] ?? ''),
                        'account_number' => trim($_POST['account_number'] ?? ''),
                        'routing_number' => trim($_POST['routing_number'] ?? ''),
                        'swift_code'     => trim($_POST['swift_code'] ?? ''),
                        'country'        => trim($_POST['bank_country'] ?? ''),
                    ];
                    break;
                case 'paypal':
                    $accountDetails = ['email' => trim($_POST['paypal_email'] ?? '')];
                    break;
                case 'wise':
                    $accountDetails = [
                        'email'      => trim($_POST['wise_email'] ?? ''),
                        'account_id' => trim($_POST['wise_account_id'] ?? ''),
                    ];
                    break;
            }
        }

        $result = requestPayout($supplierId, $amount, $method, $accountDetails, $payoutMethodId);
        if ($result['success']) {
            // Save method if requested
            if ($saveMethod && $payoutMethodId === 0) {
                savePayoutMethod($supplierId, $method, $accountDetails, $isDefault);
            }
            $success = 'Payout request #' . $result['id'] . ' submitted successfully! You will be notified when it is processed.';
            // Refresh balance
            $balance = getSupplierBalance($supplierId);
            $methods = getPayoutMethods($supplierId);
        } else {
            $error = $result['error'];
        }
    }
}

$pageTitle = 'Withdraw Funds';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-4" style="max-width:800px;">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-cash-coin text-success me-2"></i>Withdraw Funds</h3>
        <a href="/pages/supplier/earnings/" class="btn btn-outline-secondary btn-sm">← Earnings</a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <!-- Balance display -->
    <div class="card border-0 shadow-sm bg-success text-white mb-4">
        <div class="card-body d-flex align-items-center justify-content-between">
            <div>
                <div class="small opacity-75">Available Balance</div>
                <div class="display-6 fw-bold">$<?= number_format($balance['available_balance'], 2) ?></div>
            </div>
            <div class="text-end small opacity-75">
                <div>In Hold: $<?= number_format($balance['in_hold'], 2) ?></div>
                <div>Pending: $<?= number_format($balance['pending_payouts'], 2) ?></div>
            </div>
        </div>
    </div>

    <?php if ($balance['available_balance'] < PAYOUT_MIN_AMOUNT): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Minimum payout amount is <strong>$<?= number_format(PAYOUT_MIN_AMOUNT, 2) ?></strong>.
        Your available balance of <strong>$<?= number_format($balance['available_balance'], 2) ?></strong>
        is below the minimum. Earn more to withdraw.
    </div>
    <?php else: ?>

    <form method="POST" id="withdrawForm" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="submit_withdrawal" value="1">

        <!-- Amount -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Withdrawal Amount</h6>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" name="amount" id="amountInput" class="form-control form-control-lg"
                           min="<?= PAYOUT_MIN_AMOUNT ?>" max="<?= $balance['available_balance'] ?>"
                           step="0.01" placeholder="0.00" required
                           value="<?= htmlspecialchars($_POST['amount'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <button type="button" class="btn btn-outline-secondary" id="withdrawAllBtn">
                        Withdraw All
                    </button>
                </div>
                <div class="form-text">
                    Minimum: $<?= number_format(PAYOUT_MIN_AMOUNT, 2) ?> · 
                    Maximum: $<?= number_format($balance['available_balance'], 2) ?>
                </div>
            </div>
        </div>

        <!-- Payout Method Selection -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Payout Method</h6>

                <?php if ($methods): ?>
                <div class="mb-3">
                    <label class="form-label text-muted small">Saved Methods</label>
                    <?php foreach ($methods as $m):
                        $label   = payoutMethodLabel($m['method_type']);
                        $masked  = maskPayoutDetails($m['details_decoded']);
                        $summary = match($m['method_type']) {
                            'bank_transfer' => ($masked['bank_name'] ?? '') . ' ···' . substr($masked['account_number'] ?? '', -4),
                            'paypal'        => $masked['email'] ?? '',
                            'wise'          => $masked['email'] ?? $masked['account_id'] ?? '',
                            default         => '',
                        };
                    ?>
                    <div class="form-check border rounded p-3 mb-2 <?= $m['is_default'] ? 'border-success' : '' ?>">
                        <input class="form-check-input" type="radio" name="payout_method_id"
                               id="method_<?= (int)$m['id'] ?>" value="<?= (int)$m['id'] ?>"
                               <?= $m['is_default'] ? 'checked' : '' ?>
                               onchange="document.getElementById('newMethodSection').style.display='none'">
                        <label class="form-check-label w-100" for="method_<?= (int)$m['id'] ?>">
                            <span class="fw-semibold"><?= $label ?></span>
                            <?php if ($m['is_default']): ?>
                            <span class="badge bg-success ms-1 small">Default</span>
                            <?php endif; ?>
                            <br><small class="text-muted"><?= e($summary) ?></small>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="form-check border rounded p-3 mb-3">
                    <input class="form-check-input" type="radio" name="payout_method_id" id="methodNew" value="0"
                           <?= !$methods ? 'checked' : '' ?>
                           onchange="document.getElementById('newMethodSection').style.display='block'">
                    <label class="form-check-label" for="methodNew">
                        <i class="bi bi-plus-circle me-1"></i><strong>Add New Method</strong>
                    </label>
                </div>

                <!-- New Method Form -->
                <div id="newMethodSection" style="display:<?= !$methods ? 'block' : 'none' ?>;">
                    <div class="mb-3">
                        <label class="form-label">Method Type</label>
                        <select name="payout_method" id="methodTypeSelect" class="form-select" onchange="showMethodFields()">
                            <option value="">— Select Method —</option>
                            <option value="bank_transfer" <?= ($_POST['payout_method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>>🏦 Bank Transfer</option>
                            <option value="paypal"        <?= ($_POST['payout_method'] ?? '') === 'paypal' ? 'selected' : '' ?>>💙 PayPal</option>
                            <option value="wise"          <?= ($_POST['payout_method'] ?? '') === 'wise' ? 'selected' : '' ?>>💚 Wise</option>
                        </select>
                    </div>

                    <!-- Bank Transfer Fields -->
                    <div id="bankFields" style="display:none;">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">Account Holder Name</label>
                                <input type="text" name="bank_account_name" class="form-control"
                                       value="<?= e($_POST['bank_account_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control"
                                       value="<?= e($_POST['bank_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Account Number</label>
                                <input type="text" name="account_number" class="form-control"
                                       value="<?= e($_POST['account_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Routing / Sort Code</label>
                                <input type="text" name="routing_number" class="form-control"
                                       value="<?= e($_POST['routing_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">SWIFT / BIC</label>
                                <input type="text" name="swift_code" class="form-control"
                                       value="<?= e($_POST['swift_code'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Country</label>
                                <input type="text" name="bank_country" class="form-control"
                                       value="<?= e($_POST['bank_country'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- PayPal Fields -->
                    <div id="paypalFields" style="display:none;">
                        <label class="form-label small">PayPal Email Address</label>
                        <input type="email" name="paypal_email" class="form-control"
                               value="<?= e($_POST['paypal_email'] ?? '') ?>"
                               placeholder="your@paypal.com">
                    </div>

                    <!-- Wise Fields -->
                    <div id="wiseFields" style="display:none;">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">Wise Email</label>
                                <input type="email" name="wise_email" class="form-control"
                                       value="<?= e($_POST['wise_email'] ?? '') ?>"
                                       placeholder="your@wise.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Or Wise Account ID</label>
                                <input type="text" name="wise_account_id" class="form-control"
                                       value="<?= e($_POST['wise_account_id'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="save_method" value="1" id="saveMethod"
                                   <?= !empty($_POST['save_method']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="saveMethod">
                                Save this method for future use
                            </label>
                        </div>
                        <div class="form-check ms-3" id="setDefaultRow" style="display:none;">
                            <input class="form-check-input" type="checkbox" name="is_default" value="1" id="isDefault">
                            <label class="form-check-label small" for="isDefault">Set as default method</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Terms & Submit -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="termsCheck" required>
                    <label class="form-check-label" for="termsCheck">
                        I confirm the payout details are correct and authorize this withdrawal.
                    </label>
                </div>
                <button type="submit" class="btn btn-success btn-lg w-100" id="submitBtn" disabled>
                    <i class="bi bi-send-check me-2"></i>Submit Withdrawal Request
                </button>
            </div>
        </div>

        <!-- Processing Info -->
        <div class="alert alert-light border">
            <h6 class="fw-semibold">Processing Times</h6>
            <ul class="mb-0 small">
                <li>Payout requests are reviewed within <strong>1-3 business days</strong></li>
                <li>Bank transfers take <strong>3-5 business days</strong> after approval</li>
                <li>PayPal / Wise transfers are typically <strong>same-day</strong> after approval</li>
            </ul>
        </div>
    </form>

    <?php endif; ?>
</div>

<script>
const availableBalance = <?= json_encode($balance['available_balance']) ?>;

document.getElementById('withdrawAllBtn')?.addEventListener('click', function() {
    document.getElementById('amountInput').value = availableBalance.toFixed(2);
});

document.getElementById('termsCheck')?.addEventListener('change', function() {
    document.getElementById('submitBtn').disabled = !this.checked;
});

document.getElementById('saveMethod')?.addEventListener('change', function() {
    document.getElementById('setDefaultRow').style.display = this.checked ? 'block' : 'none';
});

function showMethodFields() {
    const method = document.getElementById('methodTypeSelect').value;
    document.getElementById('bankFields').style.display   = method === 'bank_transfer' ? 'block' : 'none';
    document.getElementById('paypalFields').style.display = method === 'paypal'        ? 'block' : 'none';
    document.getElementById('wiseFields').style.display   = method === 'wise'          ? 'block' : 'none';
}

// Double-submit prevention
document.getElementById('withdrawForm')?.addEventListener('submit', function() {
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitBtn').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
});

// Show fields for pre-selected method (on validation error return)
showMethodFields();

// Show new method section if no saved method was previously selected
const savedMethodRadios = document.querySelectorAll('input[name="payout_method_id"]:not(#methodNew)');
if (savedMethodRadios.length > 0) {
    const anyChecked = Array.from(savedMethodRadios).some(r => r.checked);
    if (!anyChecked) {
        document.getElementById('methodNew').checked = true;
        document.getElementById('newMethodSection').style.display = 'block';
    }
}
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
