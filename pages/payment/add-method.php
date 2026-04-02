<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$pageTitle = 'Add Payment Method';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="d-flex align-items-center mb-4">
                <a href="index.php" class="btn btn-outline-secondary me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="h3 mb-0"><i class="bi bi-plus-circle me-2"></i>Add Payment Method</h1>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="post" action="../../api/payments.php?action=add_method">
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="type" class="form-label">Payment Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="">Select type...</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="bank">Bank Account</option>
                                <option value="wallet">Wallet</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="provider" class="form-label">Provider <span class="text-danger">*</span></label>
                            <select class="form-select" id="provider" name="provider" required>
                                <option value="">Select provider...</option>
                                <optgroup label="Credit Cards">
                                    <option value="Visa">Visa</option>
                                    <option value="Mastercard">Mastercard</option>
                                    <option value="American Express">American Express</option>
                                    <option value="Discover">Discover</option>
                                </optgroup>
                                <optgroup label="Wallets">
                                    <option value="PayPal">PayPal</option>
                                    <option value="Apple Pay">Apple Pay</option>
                                    <option value="Google Pay">Google Pay</option>
                                </optgroup>
                                <optgroup label="Banks">
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Wire Transfer">Wire Transfer</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="last_four" class="form-label">Last 4 Digits <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_four" name="last_four"
                                   pattern="[0-9]{4}" maxlength="4" required
                                   placeholder="e.g. 4242">
                            <div class="form-text">Enter the last 4 digits of your account or card number.</div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1">
                                <label class="form-check-label" for="is_default">
                                    Set as default payment method
                                </label>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-plus-lg me-1"></i>Add Payment Method
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
