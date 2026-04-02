<?php
require_once __DIR__ . '/../../includes/middleware.php';

if (isLoggedIn()) redirect('/');

$pageTitle = 'Create Account';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold"><i class="bi bi-person-plus-fill text-primary"></i> Create Account</h3>
                        <p class="text-muted small">Join <?= e(APP_NAME) ?> as a buyer, supplier, or carrier</p>
                    </div>
                    <form method="POST" action="/api/auth.php?action=register" id="registerForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="_redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">First Name *</label>
                                <input type="text" name="first_name" class="form-control" placeholder="John" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Last Name</label>
                                <input type="text" name="last_name" class="form-control" placeholder="Doe">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address *</label>
                            <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Account Type</label>
                            <select name="role" id="roleSelect" class="form-select" onchange="toggleRoleFields(this.value)">
                                <option value="buyer">Buyer — I want to purchase products</option>
                                <option value="supplier">Supplier — I want to sell products</option>
                                <option value="carrier">Carrier — I deliver packages internationally</option>
                            </select>
                        </div>

                        <!-- Supplier fields -->
                        <div id="supplierFields" class="d-none">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Company Name *</label>
                                <input type="text" name="company_name" class="form-control" placeholder="Your company name">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Business Type</label>
                                <select name="business_type" class="form-select">
                                    <option value="">Select type</option>
                                    <option value="manufacturer">Manufacturer</option>
                                    <option value="trading_company">Trading Company</option>
                                    <option value="wholesaler">Wholesaler</option>
                                    <option value="retailer">Retailer</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Country</label>
                                <input type="text" name="country" class="form-control" placeholder="e.g. China, USA">
                            </div>
                        </div>

                        <!-- Carrier fields -->
                        <div id="carrierFields" class="d-none">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Passport Number *</label>
                                <input type="text" name="passport_number" class="form-control" placeholder="e.g. AB1234567">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nationality</label>
                                <input type="text" name="nationality" class="form-control" placeholder="e.g. Bangladeshi">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Password *</label>
                            <div class="input-group">
                                <input type="password" name="password" id="regPwd" class="form-control"
                                       placeholder="Min 8 chars, uppercase, number, special" required minlength="8">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('regPwd',this)"><i class="bi bi-eye"></i></button>
                            </div>
                            <div class="form-text small text-muted">
                                <i class="bi bi-info-circle"></i>
                                Must contain: uppercase letter, number, and special character (e.g. @!#$)
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Confirm Password *</label>
                            <input type="password" name="password_confirm" class="form-control" placeholder="Repeat password" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="agreeTerms" required>
                            <label class="form-check-label" for="agreeTerms">
                                I agree to the <a href="/pages/terms.php" target="_blank">Terms of Service</a> and <a href="/pages/privacy.php" target="_blank">Privacy Policy</a>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">Create Account</button>
                    </form>
                    <hr>
                    <p class="text-center small mb-0">
                        Already have an account? <a href="/pages/auth/login.php" class="fw-semibold text-decoration-none">Sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function toggleRoleFields(role) {
    document.getElementById('supplierFields').classList.toggle('d-none', role !== 'supplier');
    document.getElementById('carrierFields').classList.toggle('d-none', role !== 'carrier');
}
function togglePwd(id, btn) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.innerHTML = input.type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

