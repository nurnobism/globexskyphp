<?php
/**
 * pages/supplier/billing/addons.php — Add-On Store Page (PR #10)
 *
 * Supplier marketplace to purchase add-ons that extend plan limits.
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/addons.php';
require_once __DIR__ . '/../../../includes/plan_limits.php';

requireRole(['supplier', 'admin', 'super_admin']);

$db         = getDB();
$supplierId = (int)$_SESSION['user_id'];

$catalog      = getAddons();
$activeAddons = getSupplierAddons($supplierId);
$boosts       = getActiveBoosts($supplierId);
$featured     = getActiveFeatured($supplierId);

$apiCredits    = getRemainingCredits($supplierId, 'api_calls_pack');
$transCredits  = getRemainingCredits($supplierId, 'translation_credit');
$liveCredits   = getRemainingCredits($supplierId, 'livestream_session');

$effectiveProducts = getEffectiveLimit($supplierId, 'products');
$effectiveImages   = getEffectiveLimit($supplierId, 'images_per_product');

$csrfToken = generateCsrf();
$pageTitle = 'Add-On Store';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-bag-plus text-primary me-2"></i>Add-On Store</h3>
        <div>
            <a href="/pages/supplier/billing/invoices.php" class="btn btn-outline-secondary btn-sm me-2">
                <i class="bi bi-receipt me-1"></i>My Invoices
            </a>
            <a href="/pages/supplier/billing.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-credit-card me-1"></i>Billing
            </a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_GET['success'], ENT_QUOTES) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_GET['error'], ENT_QUOTES) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Active Status Summary -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 bg-light">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-box-seam fs-3 text-primary me-3"></i>
                        <div>
                            <div class="text-muted small">Effective Product Limit</div>
                            <div class="fw-bold fs-5"><?= $effectiveProducts < 0 ? 'Unlimited' : $effectiveProducts ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-light">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-code-slash fs-3 text-info me-3"></i>
                        <div>
                            <div class="text-muted small">API Credits Remaining</div>
                            <div class="fw-bold fs-5"><?= number_format($apiCredits) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-light">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-translate fs-3 text-success me-3"></i>
                        <div>
                            <div class="text-muted small">Translation Credits</div>
                            <div class="fw-bold fs-5"><?= number_format($transCredits) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add-On Catalog -->
    <h5 class="fw-bold mb-3">Available Add-Ons</h5>
    <div class="row g-4 mb-5" id="addonCatalog">
        <?php foreach ($catalog as $addon):
            $isCredit = in_array($addon['type'], ['api_calls_pack', 'translation_credit', 'livestream_session'], true);
            $isTarget = in_array($addon['type'], ['product_boost', 'featured_listing'], true);
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <span class="fs-2 me-3"><i class="bi <?= htmlspecialchars($addon['icon'], ENT_QUOTES) ?> text-primary"></i></span>
                        <div>
                            <h6 class="mb-0 fw-bold"><?= htmlspecialchars($addon['name'], ENT_QUOTES) ?></h6>
                            <span class="badge bg-primary">$<?= number_format((float)$addon['price'], 2) ?></span>
                        </div>
                    </div>
                    <p class="text-muted small mb-3"><?= htmlspecialchars($addon['description'], ENT_QUOTES) ?></p>

                    <button class="btn btn-primary btn-sm w-100"
                        data-addon-id="<?= (int)$addon['id'] ?>"
                        data-addon-name="<?= htmlspecialchars($addon['name'], ENT_QUOTES) ?>"
                        data-addon-price="<?= (float)$addon['price'] ?>"
                        data-is-target="<?= $isTarget ? '1' : '0' ?>"
                        data-is-credit="<?= $isCredit ? '1' : '0' ?>"
                        onclick="openPurchaseModal(this)">
                        <i class="bi bi-cart-plus me-1"></i> Buy Now
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($catalog)): ?>
        <div class="col-12"><div class="alert alert-info">No add-ons available at the moment.</div></div>
        <?php endif; ?>
    </div>

    <!-- Active Add-Ons -->
    <?php if (!empty($boosts) || !empty($featured)): ?>
    <h5 class="fw-bold mb-3">Active Add-Ons</h5>
    <div class="row g-3 mb-4">
        <?php foreach ($boosts as $b): ?>
        <div class="col-md-6">
            <div class="card border-warning">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <i class="bi bi-rocket text-warning me-2"></i>
                        <strong><?= htmlspecialchars($b['product_name'] ?? 'Product #' . $b['target_product_id'], ENT_QUOTES) ?></strong>
                        <div class="text-muted small">Boost expires: <?= htmlspecialchars($b['expires_at'] ?? 'N/A', ENT_QUOTES) ?></div>
                    </div>
                    <span class="badge bg-warning text-dark">Boosted</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php foreach ($featured as $f): ?>
        <div class="col-md-6">
            <div class="card border-success">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <i class="bi bi-star-fill text-success me-2"></i>
                        <strong><?= htmlspecialchars($f['product_name'] ?? 'Product #' . $f['target_product_id'], ENT_QUOTES) ?></strong>
                        <div class="text-muted small">Featured until: <?= htmlspecialchars($f['expires_at'] ?? 'N/A', ENT_QUOTES) ?></div>
                    </div>
                    <span class="badge bg-success">Featured</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Purchase Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="purchaseForm" method="post" action="/api/addons.php?action=purchase">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <input type="hidden" name="addon_id" id="modalAddonId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cart-plus me-2"></i><span id="modalAddonName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3" id="qtyGroup">
                        <label class="form-label fw-bold">Quantity</label>
                        <input type="number" class="form-control" name="quantity" id="modalQty"
                            min="1" value="1" oninput="updatePrice()">
                    </div>
                    <div class="mb-3 d-none" id="targetGroup">
                        <label class="form-label fw-bold">Select Product</label>
                        <select class="form-select" name="target_product_id" id="modalTarget">
                            <option value="">Loading products…</option>
                        </select>
                    </div>
                    <div class="alert alert-info py-2">
                        Total: <strong id="modalTotal">$0.00</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-credit-card me-1"></i> Confirm Purchase
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentPrice = 0;

function openPurchaseModal(btn) {
    const addonId   = btn.dataset.addonId;
    const addonName = btn.dataset.addonName;
    const price     = parseFloat(btn.dataset.addonPrice);
    const isTarget  = btn.dataset.isTarget === '1';
    const isCredit  = btn.dataset.isCredit === '1';

    currentPrice = price;
    document.getElementById('modalAddonId').value = addonId;
    document.getElementById('modalAddonName').textContent = addonName;
    document.getElementById('modalQty').value = 1;

    document.getElementById('targetGroup').classList.toggle('d-none', !isTarget);
    document.getElementById('qtyGroup').classList.toggle('d-none', isTarget);

    if (isTarget) {
        loadProducts();
    }

    updatePrice();
    new bootstrap.Modal(document.getElementById('purchaseModal')).show();
}

function updatePrice() {
    const qty   = parseInt(document.getElementById('modalQty').value) || 1;
    const total = (currentPrice * qty).toFixed(2);
    document.getElementById('modalTotal').textContent = '$' + total;
}

function loadProducts() {
    fetch('/api/products.php?action=list&limit=500')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('modalTarget');
            sel.innerHTML = '<option value="">— Select a product —</option>';
            const products = data.products || data.rows || [];
            products.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                sel.appendChild(opt);
            });
        })
        .catch(() => {
            document.getElementById('modalTarget').innerHTML = '<option value="">Could not load products</option>';
        });
}

document.getElementById('purchaseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('/api/addons.php?action=purchase', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            bootstrap.Modal.getInstance(document.getElementById('purchaseModal')).hide();
            if (data.success) {
                window.location.href = '?success=Add-on+purchased+successfully!+Invoice+%23' + data.invoice_id;
            } else {
                window.location.href = '?error=' + encodeURIComponent(data.error || 'Purchase failed');
            }
        })
        .catch(() => {
            window.location.href = '?error=Network+error.+Please+try+again.';
        });
});
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
