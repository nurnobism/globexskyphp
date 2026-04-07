<?php
/**
 * pages/account/addresses/form.php — Add / Edit Address Form (PR #17)
 *
 * Smart form with:
 *   - Label tabs (Home/Office/Other)
 *   - Country searchable dropdown → loads states dynamically
 *   - City auto-complete
 *   - Postal code format validation + auto-lookup
 *   - Real-time validation feedback
 *   - Default shipping / billing checkboxes
 */

require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/addresses.php';

requireLogin();

$userId    = (int)$_SESSION['user_id'];
$addressId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$address   = null;
$isEdit    = false;

if ($addressId > 0) {
    $address = getAddress($addressId, $userId);
    if (!$address) {
        header('Location: /pages/account/addresses/index.php?error=' . urlencode('Address not found.'));
        exit;
    }
    $isEdit = true;
}

// Handle POST submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $data = [
            'label'              => trim($_POST['label'] ?? 'Home'),
            'full_name'          => trim($_POST['full_name'] ?? ''),
            'phone'              => trim($_POST['phone'] ?? ''),
            'address_line_1'     => trim($_POST['address_line_1'] ?? ''),
            'address_line_2'     => trim($_POST['address_line_2'] ?? ''),
            'city'               => trim($_POST['city'] ?? ''),
            'state_province'     => trim($_POST['state_province'] ?? ''),
            'state_code'         => trim($_POST['state_code'] ?? ''),
            'postal_code'        => trim($_POST['postal_code'] ?? ''),
            'country_code'       => strtoupper(trim($_POST['country_code'] ?? 'US')),
            'is_default_shipping'=> !empty($_POST['is_default_shipping']),
            'is_default_billing' => !empty($_POST['is_default_billing']),
        ];

        try {
            if ($isEdit) {
                updateAddress($addressId, $userId, $data);
                header('Location: /pages/account/addresses/index.php?success=' . urlencode('Address updated successfully.'));
            } else {
                createAddress($userId, $data);
                header('Location: /pages/account/addresses/index.php?success=' . urlencode('Address added successfully.'));
            }
            exit;
        } catch (InvalidArgumentException $e) {
            $errors = [$e->getMessage()];
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Pre-populate from existing address if editing
$v = [];
if ($isEdit && $address) {
    $v = [
        'label'              => $address['label'] ?? 'Home',
        'full_name'          => $address['full_name'] ?? '',
        'phone'              => $address['phone'] ?? '',
        'address_line_1'     => $address['address_line_1'] ?? $address['address_line1'] ?? '',
        'address_line_2'     => $address['address_line_2'] ?? $address['address_line2'] ?? '',
        'city'               => $address['city'] ?? '',
        'state_province'     => $address['state_province'] ?? $address['state'] ?? '',
        'state_code'         => $address['state_code'] ?? '',
        'postal_code'        => $address['postal_code'] ?? '',
        'country_code'       => $address['country_code'] ?? 'US',
        'is_default_shipping'=> (bool)($address['is_default_shipping'] ?? 0),
        'is_default_billing' => (bool)($address['is_default_billing']  ?? 0),
    ];
} elseif (!empty($_POST)) {
    $v = $_POST;
    $v['is_default_shipping'] = !empty($_POST['is_default_shipping']);
    $v['is_default_billing']  = !empty($_POST['is_default_billing']);
} else {
    $v = [
        'label' => 'Home', 'full_name' => '', 'phone' => '',
        'address_line_1' => '', 'address_line_2' => '',
        'city' => '', 'state_province' => '', 'state_code' => '',
        'postal_code' => '', 'country_code' => 'US',
        'is_default_shipping' => (getAddressCount($userId) === 0),
        'is_default_billing'  => (getAddressCount($userId) === 0),
    ];
}

$countries      = getCountries();
$currentStates  = getStates($v['country_code'] ?? 'US');
$pageTitle      = $isEdit ? 'Edit Address' : 'Add New Address';
include __DIR__ . '/../../../includes/header.php';
?>

<div class="container py-5" style="max-width:700px">

    <div class="mb-4">
        <a href="/pages/account/addresses/index.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Addresses
        </a>
    </div>

    <h3 class="fw-bold mb-4">
        <i class="bi bi-geo-alt-fill text-primary me-2"></i>
        <?= $isEdit ? 'Edit Address' : 'Add New Address' ?>
    </h3>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form method="POST" id="addressForm" novalidate>
                <?= csrfField() ?>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="address_id" value="<?= $addressId ?>">
                <?php endif; ?>

                <!-- Label tabs -->
                <div class="mb-4">
                    <label class="form-label fw-semibold">Address Type</label>
                    <div class="btn-group w-100" role="group">
                        <?php foreach (['Home' => '🏠', 'Office' => '🏢', 'Other' => '📍'] as $lbl => $icon): ?>
                        <input type="radio" class="btn-check" name="label" id="label_<?= $lbl ?>"
                               value="<?= $lbl ?>"
                               <?= (($v['label'] ?? 'Home') === $lbl) ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary" for="label_<?= $lbl ?>">
                            <?= $icon ?> <?= $lbl ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="row g-3">
                    <!-- Full name -->
                    <div class="col-12">
                        <label class="form-label" for="full_name">Full Name <span class="text-danger">*</span></label>
                        <input type="text" id="full_name" name="full_name" class="form-control"
                               value="<?= htmlspecialchars($v['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Recipient's full name" required maxlength="200">
                    </div>

                    <!-- Phone -->
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text" id="phoneCodeDisplay">
                                <?php
                                $currentCountry = getCountry($v['country_code'] ?? 'US');
                                echo htmlspecialchars($currentCountry['phone_code'] ?? '+1', ENT_QUOTES, 'UTF-8');
                                ?>
                            </span>
                            <input type="tel" id="phone" name="phone" class="form-control"
                                   value="<?= htmlspecialchars($v['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="Phone number">
                        </div>
                    </div>

                    <!-- Country -->
                    <div class="col-md-6">
                        <label class="form-label" for="country_code">Country <span class="text-danger">*</span></label>
                        <select id="country_code" name="country_code" class="form-select" required>
                            <?php foreach ($countries as $c): ?>
                            <option value="<?= htmlspecialchars($c['code'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-phone="<?= htmlspecialchars($c['phone_code'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-has-states="<?= $c['has_states'] ? '1' : '0' ?>"
                                <?= (($v['country_code'] ?? 'US') === $c['code']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['flag'] . ' ' . $c['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Address line 1 -->
                    <div class="col-12">
                        <label class="form-label" for="address_line_1">Address Line 1 <span class="text-danger">*</span></label>
                        <input type="text" id="address_line_1" name="address_line_1" class="form-control"
                               value="<?= htmlspecialchars($v['address_line_1'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Street address, P.O. Box" required>
                    </div>

                    <!-- Address line 2 -->
                    <div class="col-12">
                        <label class="form-label" for="address_line_2">
                            Address Line 2 <small class="text-muted">(optional)</small>
                        </label>
                        <input type="text" id="address_line_2" name="address_line_2" class="form-control"
                               value="<?= htmlspecialchars($v['address_line_2'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Apartment, suite, unit, building, floor">
                    </div>

                    <!-- City -->
                    <div class="col-md-4">
                        <label class="form-label" for="city">City <span class="text-danger">*</span></label>
                        <input type="text" id="city" name="city" class="form-control" autocomplete="off"
                               value="<?= htmlspecialchars($v['city'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="City" required>
                        <div id="citySuggestions" class="list-group position-absolute w-100 z-3" style="display:none;max-height:200px;overflow-y:auto"></div>
                    </div>

                    <!-- State/Province -->
                    <div class="col-md-4" id="stateWrapper">
                        <label class="form-label" for="state_province">State / Province</label>
                        <select id="stateSelect" name="state_code" class="form-select"
                                style="<?= empty($currentStates) ? 'display:none' : '' ?>">
                            <option value="">— Select State —</option>
                            <?php foreach ($currentStates as $st): ?>
                            <option value="<?= htmlspecialchars($st['code'], ENT_QUOTES, 'UTF-8') ?>"
                                <?= (($v['state_code'] ?? '') === $st['code']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($st['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="stateInput" name="state_province" class="form-control"
                               value="<?= htmlspecialchars($v['state_province'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="State / Province"
                               style="<?= !empty($currentStates) ? 'display:none' : '' ?>">
                        <div id="stateRequiredMsg" class="invalid-feedback">
                            State/Province is required for this country.
                        </div>
                    </div>

                    <!-- Postal code -->
                    <div class="col-md-4">
                        <label class="form-label" for="postal_code">Postal / ZIP Code</label>
                        <input type="text" id="postal_code" name="postal_code" class="form-control"
                               value="<?= htmlspecialchars($v['postal_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Postal code">
                        <div id="postalFeedback" class="form-text"></div>
                    </div>

                    <!-- Address preview -->
                    <div class="col-12" id="previewWrapper" style="display:none">
                        <div class="alert alert-light border">
                            <small class="text-muted d-block mb-1">Preview:</small>
                            <div id="addressPreview" class="small"></div>
                        </div>
                    </div>

                    <!-- Default checkboxes -->
                    <div class="col-12">
                        <div class="form-check mb-2">
                            <input type="checkbox" name="is_default_shipping" value="1"
                                   class="form-check-input" id="isDefaultShipping"
                                   <?= ($v['is_default_shipping'] ?? false) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isDefaultShipping">
                                <i class="bi bi-truck me-1 text-success"></i>
                                Set as default shipping address
                            </label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_default_billing" value="1"
                                   class="form-check-input" id="isDefaultBilling"
                                   <?= ($v['is_default_billing'] ?? false) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isDefaultBilling">
                                <i class="bi bi-credit-card me-1 text-info"></i>
                                Set as default billing address
                            </label>
                        </div>
                    </div>

                </div><!-- /.row -->

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-circle me-1"></i>
                        <?= $isEdit ? 'Save Changes' : 'Add Address' ?>
                    </button>
                    <a href="/pages/account/addresses/index.php" class="btn btn-outline-secondary px-4">
                        Cancel
                    </a>
                </div>

            </form>
        </div><!-- /.card-body -->
    </div><!-- /.card -->
</div>

<script>
(function() {
    'use strict';

    const countrySelect  = document.getElementById('country_code');
    const stateSelect    = document.getElementById('stateSelect');
    const stateInput     = document.getElementById('stateInput');
    const cityInput      = document.getElementById('city');
    const postalInput    = document.getElementById('postal_code');
    const phoneCode      = document.getElementById('phoneCodeDisplay');
    const postalFeedback = document.getElementById('postalFeedback');
    const citySugg       = document.getElementById('citySuggestions');
    const previewWrapper = document.getElementById('previewWrapper');
    const previewEl      = document.getElementById('addressPreview');

    // ── Country change: load states + update phone code ──────────────────
    countrySelect.addEventListener('change', function() {
        const code     = this.value;
        const opt      = this.selectedOptions[0];
        const hasStates = opt.dataset.hasStates === '1';

        // Update phone code display
        if (phoneCode) phoneCode.textContent = opt.dataset.phone || '+1';

        // Load states via API
        fetch('/api/addresses.php?action=states&country_code=' + encodeURIComponent(code))
            .then(r => r.json())
            .then(res => {
                if (res.success && res.states.length > 0) {
                    stateSelect.innerHTML = '<option value="">— Select State —</option>';
                    res.states.forEach(st => {
                        const o = document.createElement('option');
                        o.value       = st.code;
                        o.textContent = st.name;
                        stateSelect.appendChild(o);
                    });
                    stateSelect.style.display = '';
                    stateInput.style.display  = 'none';
                } else {
                    stateSelect.style.display = 'none';
                    stateInput.style.display  = '';
                }
            });

        // Reset city suggestions
        citySugg.style.display = 'none';
        citySugg.innerHTML     = '';
    });

    // ── City auto-complete ────────────────────────────────────────────────
    let cityTimer = null;
    cityInput.addEventListener('input', function() {
        clearTimeout(cityTimer);
        const q = this.value.trim();
        if (q.length < 1) { citySugg.style.display = 'none'; return; }

        cityTimer = setTimeout(() => {
            const code = countrySelect.value;
            fetch('/api/addresses.php?action=suggest_city&country_code=' + encodeURIComponent(code) + '&query=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.cities.length > 0) {
                        citySugg.innerHTML = '';
                        res.cities.forEach(city => {
                            const a = document.createElement('a');
                            a.href      = '#';
                            a.className = 'list-group-item list-group-item-action py-1';
                            a.textContent = city;
                            a.addEventListener('click', function(e) {
                                e.preventDefault();
                                cityInput.value        = city;
                                citySugg.style.display = 'none';
                                updatePreview();
                            });
                            citySugg.appendChild(a);
                        });
                        citySugg.style.display = '';
                    } else {
                        citySugg.style.display = 'none';
                    }
                });
        }, 250);
    });

    document.addEventListener('click', function(e) {
        if (!citySugg.contains(e.target) && e.target !== cityInput) {
            citySugg.style.display = 'none';
        }
    });

    // ── Postal code: format validation + reverse lookup ───────────────────
    let postalTimer = null;
    postalInput.addEventListener('blur', function() {
        const code   = countrySelect.value;
        const postal = this.value.trim();
        if (!postal) { postalFeedback.textContent = ''; return; }

        // Reverse lookup
        fetch('/api/addresses.php?action=postal_lookup&country_code=' + encodeURIComponent(code) + '&postal_code=' + encodeURIComponent(postal))
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    postalFeedback.textContent = '✓ ' + res.data.city + ', ' + res.data.state_name;
                    postalFeedback.className   = 'form-text text-success';
                    // Auto-fill city if empty
                    if (cityInput.value.trim() === '') {
                        cityInput.value = res.data.city;
                    }
                    // Auto-select state if available
                    if (stateSelect.style.display !== 'none') {
                        for (const opt of stateSelect.options) {
                            if (opt.value === res.data.state_code) {
                                stateSelect.value = res.data.state_code;
                                break;
                            }
                        }
                    } else {
                        if (stateInput.value.trim() === '') {
                            stateInput.value = res.data.state_name;
                        }
                    }
                    updatePreview();
                } else {
                    postalFeedback.textContent = '';
                }
            });
    });

    // ── Live address preview ──────────────────────────────────────────────
    const previewFields = ['full_name','address_line_1','address_line_2','city','postal_code'];
    previewFields.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updatePreview);
    });
    stateSelect.addEventListener('change', updatePreview);
    stateInput.addEventListener('input', updatePreview);
    countrySelect.addEventListener('change', updatePreview);

    function updatePreview() {
        const name    = document.getElementById('full_name').value.trim();
        const line1   = document.getElementById('address_line_1').value.trim();
        const line2   = document.getElementById('address_line_2').value.trim();
        const city    = cityInput.value.trim();
        const state   = stateSelect.style.display !== 'none'
                        ? stateSelect.selectedOptions[0]?.textContent.trim()
                        : stateInput.value.trim();
        const postal  = postalInput.value.trim();
        const country = countrySelect.selectedOptions[0]?.textContent.replace(/^.{1,4}\s/, '').trim();

        const parts = [name, line1, line2, [city, state, postal].filter(Boolean).join(', '), country].filter(Boolean);
        if (parts.length > 1) {
            previewWrapper.style.display = '';
            previewEl.innerHTML = parts.map(p => '<span>' + p.replace(/</g,'&lt;') + '</span>').join('<br>');
        } else {
            previewWrapper.style.display = 'none';
        }
    }

    // ── Form validation on submit ─────────────────────────────────────────
    document.getElementById('addressForm').addEventListener('submit', function(e) {
        const required = ['full_name', 'address_line_1', 'city', 'country_code'];
        let hasError = false;
        required.forEach(id => {
            const el = document.getElementById(id);
            if (el && !el.value.trim()) {
                el.classList.add('is-invalid');
                hasError = true;
            } else if (el) {
                el.classList.remove('is-invalid');
            }
        });
        if (hasError) {
            e.preventDefault();
            window.scrollTo(0, 0);
        }
    });

    // Initial preview
    updatePreview();
})();
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
