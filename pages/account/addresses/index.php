<?php
/**
 * pages/account/addresses/index.php — Address Book Page (PR #17)
 *
 * Displays user's saved addresses as cards with default badges,
 * edit/delete/set-default actions, and an "Add New Address" button.
 */

require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/addresses.php';

requireLogin();

$userId    = (int)$_SESSION['user_id'];
$addresses = getUserAddresses($userId);
$count     = count($addresses);
$pageTitle = 'My Addresses';
include __DIR__ . '/../../../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">

            <!-- Header row -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1"><i class="bi bi-geo-alt-fill text-primary me-2"></i>Address Book</h3>
                    <?php if ($count > 0): ?>
                        <small class="text-muted"><?= $count ?>/<?= ADDRESS_MAX_PER_USER ?> addresses used</small>
                    <?php endif; ?>
                </div>
                <?php if ($count < ADDRESS_MAX_PER_USER): ?>
                    <a href="/pages/account/addresses/form.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Add New Address
                    </a>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled title="Maximum addresses reached">
                        <i class="bi bi-plus-circle me-1"></i> Add New Address
                    </button>
                <?php endif; ?>
            </div>

            <?php if (!empty($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Empty state -->
            <?php if (empty($addresses)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-geo-alt display-1 text-muted mb-3 d-block"></i>
                    <h5 class="text-muted">No addresses yet.</h5>
                    <p class="text-muted mb-4">Add your first address to speed up checkout!</p>
                    <a href="/pages/account/addresses/form.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-plus-circle me-1"></i> Add Your First Address
                    </a>
                </div>
            <?php else: ?>

            <!-- Address cards grid -->
            <div class="row g-3">
                <?php foreach ($addresses as $addr): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100
                        <?= ($addr['is_default_shipping'] || $addr['is_default_billing']) ? 'border border-primary border-2' : '' ?>">
                        <div class="card-body">

                            <!-- Label icon & type -->
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <?php
                                    $labelIcon = match(strtolower($addr['label'] ?? 'home')) {
                                        'office' => '🏢',
                                        'other'  => '📍',
                                        default  => '🏠',
                                    };
                                    ?>
                                    <span class="me-1"><?= $labelIcon ?></span>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($addr['label'] ?? 'Home', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="/pages/account/addresses/form.php?id=<?= (int)$addr['id'] ?>">
                                                <i class="bi bi-pencil me-2"></i>Edit
                                            </a>
                                        </li>
                                        <?php if (!$addr['is_default_shipping']): ?>
                                        <li>
                                            <form method="POST" action="/api/addresses.php?action=set_default" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="address_id" value="<?= (int)$addr['id'] ?>">
                                                <input type="hidden" name="type" value="shipping">
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bi bi-truck me-2"></i>Set as Default Shipping
                                                </button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                        <?php if (!$addr['is_default_billing']): ?>
                                        <li>
                                            <form method="POST" action="/api/addresses.php?action=set_default" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="address_id" value="<?= (int)$addr['id'] ?>">
                                                <input type="hidden" name="type" value="billing">
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bi bi-credit-card me-2"></i>Set as Default Billing
                                                </button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="/api/addresses.php?action=delete"
                                                  onsubmit="return confirm('Remove this address?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="address_id" value="<?= (int)$addr['id'] ?>">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="bi bi-trash me-2"></i>Delete
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Default badges -->
                            <?php if ($addr['is_default_shipping']): ?>
                                <span class="badge bg-success mb-1 me-1">
                                    <i class="bi bi-truck me-1"></i>Default Shipping ✓
                                </span>
                            <?php endif; ?>
                            <?php if ($addr['is_default_billing']): ?>
                                <span class="badge bg-info text-dark mb-1">
                                    <i class="bi bi-credit-card me-1"></i>Default Billing ✓
                                </span>
                            <?php endif; ?>

                            <!-- Address details -->
                            <div class="mt-2 small">
                                <strong><?= htmlspecialchars($addr['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong><br>
                                <?= htmlspecialchars($addr['address_line_1'] ?? $addr['address_line1'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                <?php $line2 = $addr['address_line_2'] ?? $addr['address_line2'] ?? ''; ?>
                                <?php if ($line2): ?>
                                    <br><?= htmlspecialchars($line2, ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                                <br>
                                <?php
                                $cityLine = htmlspecialchars($addr['city'] ?? '', ENT_QUOTES, 'UTF-8');
                                $statePart = $addr['state_province'] ?? $addr['state'] ?? '';
                                if ($statePart) $cityLine .= ', ' . htmlspecialchars($statePart, ENT_QUOTES, 'UTF-8');
                                $postal = $addr['postal_code'] ?? '';
                                if ($postal) $cityLine .= ' ' . htmlspecialchars($postal, ENT_QUOTES, 'UTF-8');
                                echo $cityLine;
                                ?>
                                <br>
                                <?php $country = $addr['country_name'] ?? $addr['country'] ?? ''; ?>
                                <?= htmlspecialchars($country, ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($addr['phone'])): ?>
                                    <br><span class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($addr['phone'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div><!-- /.row -->

            <?php endif; ?>

        </div><!-- /.col -->
    </div><!-- /.row -->
</div>

<script>
// Handle set_default and delete form submissions via fetch for SPA-feel
document.querySelectorAll('form[action*="api/addresses.php"]').forEach(form => {
    form.addEventListener('submit', function(e) {
        const action = new URL(form.action, location.href).searchParams.get('action');
        if (action === 'set_default') {
            e.preventDefault();
            const data = new FormData(form);
            fetch(form.action, { method: 'POST', body: data })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.message || 'Error updating default address.');
                    }
                });
        } else if (action === 'delete') {
            // confirm is inline; let native submit proceed but use fetch redirect
            e.preventDefault();
            if (!confirm('Remove this address?')) return;
            const data = new FormData(form);
            fetch(form.action, { method: 'POST', body: data })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.message || 'Error deleting address.');
                    }
                });
        }
    });
});
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
