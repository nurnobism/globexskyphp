<?php
/**
 * pages/supplier/orders/ship.php — Supplier: Ship Order Form (PR #15)
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/tracking.php';
requireRole(['supplier', 'admin', 'super_admin']);

$db         = getDB();
$user       = getCurrentUser();
$supplierId = (int)$user['id'];
$orderId    = (int)get('order_id', 0);

if (!$orderId) {
    flashMessage('danger', 'Invalid order.');
    redirect('/pages/supplier/orders.php');
}

// Load order
$order = null;
try {
    $stmt = $db->prepare(
        'SELECT o.* FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         JOIN products p ON p.id = oi.product_id
         WHERE o.id = ? AND p.supplier_id = ?
         LIMIT 1'
    );
    $stmt->execute([$orderId, $supplierId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist — allow for demo
}

if (!$order && !isAdmin()) {
    flashMessage('danger', 'Order not found or access denied.');
    redirect('/pages/supplier/orders.php');
}

// Existing shipments for this order
$existingShipments = getOrderShipments($orderId);

// Carrier list
$carriers = getCarriers();

$errors  = [];
$success = false;

// ── Handle form submit ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['bulk_csv'])) {
    if (!verifyCsrf()) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        $carrierCode = trim(htmlspecialchars($_POST['carrier'] ?? '', ENT_QUOTES, 'UTF-8'));
        $trackNum    = trim(htmlspecialchars($_POST['tracking_number'] ?? '', ENT_QUOTES, 'UTF-8'));
        $trackUrl    = trim(htmlspecialchars($_POST['tracking_url'] ?? '', ENT_QUOTES, 'UTF-8'));
        $estDelivery = trim($_POST['estimated_delivery'] ?? '');
        $weightKg    = isset($_POST['weight_kg']) && $_POST['weight_kg'] !== '' ? (float)$_POST['weight_kg'] : null;
        $dims        = trim(htmlspecialchars($_POST['package_dimensions'] ?? '', ENT_QUOTES, 'UTF-8'));

        if ($trackNum === '') {
            $errors[] = 'Tracking number is required.';
        }
        if ($carrierCode === '') {
            $errors[] = 'Please select a carrier.';
        }

        if (empty($errors)) {
            $shipmentId = createShipment($orderId, $supplierId, [
                'carrier_code'       => $carrierCode,
                'tracking_number'    => $trackNum,
                'tracking_url'       => $trackUrl,
                'estimated_delivery' => $estDelivery,
                'weight_kg'          => $weightKg,
                'package_dimensions' => $dims,
            ]);

            if ($shipmentId) {
                $success = true;
                $existingShipments = getOrderShipments($orderId);
                flashMessage('success', 'Order marked as shipped! Tracking number: ' . $trackNum);
            } else {
                $errors[] = 'Failed to create shipment. Please try again.';
            }
        }
    }
}

// ── Handle bulk CSV upload ────────────────────────────────────────────────────
$bulkResults = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_csv'])) {
    if (!verifyCsrf()) {
        $errors[] = 'Invalid CSRF token.';
    } elseif (empty($_FILES['csv_file']['tmp_name'])) {
        $errors[] = 'Please upload a CSV file.';
    } else {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $lineNo = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $lineNo++;
            if ($lineNo === 1) { continue; } // header
            $csvOrderId  = trim($row[0] ?? '');
            $csvCarrier  = trim($row[1] ?? '');
            $csvTrackNum = trim($row[2] ?? '');
            if (!$csvOrderId || !$csvTrackNum) { continue; }
            $sid = createShipment((int)$csvOrderId, $supplierId, [
                'carrier_code'    => $csvCarrier ?: 'generic',
                'tracking_number' => $csvTrackNum,
            ]);
            $bulkResults[] = ['order_id' => $csvOrderId, 'tracking' => $csvTrackNum, 'success' => (bool)$sid];
        }
        fclose($handle);
    }
}

$pageTitle = 'Ship Order' . ($order ? ' #' . e($order['order_number'] ?? $orderId) : '');
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <!-- Back -->
    <a href="/pages/supplier/orders.php" class="btn btn-outline-secondary btn-sm mb-3">
        <i class="bi bi-arrow-left me-1"></i>Back to Orders
    </a>

    <h4 class="fw-bold mb-4">
        <i class="bi bi-truck me-2"></i>
        Ship Order<?php if ($order): ?> — #<?= e($order['order_number'] ?? $orderId) ?><?php endif; ?>
    </h4>

    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($bulkResults)): ?>
    <div class="alert alert-info">
        <strong>Bulk Upload Results:</strong>
        <?php $bulkOk = count(array_filter($bulkResults, fn($r) => $r['success'])); ?>
        <?= $bulkOk ?> / <?= count($bulkResults) ?> shipments created successfully.
    </div>
    <?php endif; ?>

    <!-- Existing shipments for this order -->
    <?php if (!empty($existingShipments)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h6 class="fw-bold mb-3">Existing Shipments for this Order</h6>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Carrier</th><th>Tracking #</th><th>Status</th>
                            <th>Shipped</th><th>Est. Delivery</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($existingShipments as $s): ?>
                        <tr>
                            <td><?= e($s['carrier_name'] ?: $s['carrier_code']) ?></td>
                            <td class="font-monospace"><?= e($s['tracking_number']) ?></td>
                            <td><span class="badge bg-<?= getStatusColor($s['status']) ?>"><?= e(getStatusLabel($s['status'])) ?></span></td>
                            <td><?= $s['shipped_date'] ? e(date('M j, Y', strtotime($s['shipped_date']))) : '—' ?></td>
                            <td><?= $s['estimated_delivery'] ? e(date('M j, Y', strtotime($s['estimated_delivery']))) : '—' ?></td>
                            <td>
                                <?php $tUrl = getCarrierTrackingUrl($s['carrier_code'] ?? '', $s['tracking_number'] ?? '', $s['tracking_url'] ?? ''); ?>
                                <?php if ($tUrl): ?>
                                <a href="<?= e($tUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Ship Form -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle me-2"></i>Add Shipment</h6>
                    <form method="POST" id="ship-form">
                        <?= csrfField() ?>
                        <!-- Carrier -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Carrier <span class="text-danger">*</span></label>
                            <select name="carrier" id="carrier-select" class="form-select" required>
                                <option value="">— Select Carrier —</option>
                                <?php foreach ($carriers as $c): ?>
                                <option value="<?= e($c['code']) ?>"
                                        data-template="<?= e($c['tracking_url_template'] ?? '') ?>"
                                    <?= (($_POST['carrier'] ?? '') === $c['code']) ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Tracking number -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tracking Number <span class="text-danger">*</span></label>
                            <input type="text" name="tracking_number" id="tracking-number" class="form-control"
                                   placeholder="e.g. 1Z999AA10123456784"
                                   value="<?= e($_POST['tracking_number'] ?? '') ?>" required>
                        </div>

                        <!-- Tracking URL (auto-generated) -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tracking URL</label>
                            <input type="url" name="tracking_url" id="tracking-url" class="form-control"
                                   placeholder="Auto-generated from carrier + tracking number"
                                   value="<?= e($_POST['tracking_url'] ?? '') ?>">
                            <div class="form-text">Leave blank to auto-generate from carrier template.</div>
                        </div>

                        <!-- Estimated delivery -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Estimated Delivery Date</label>
                            <input type="date" name="estimated_delivery" class="form-control"
                                   value="<?= e($_POST['estimated_delivery'] ?? '') ?>">
                        </div>

                        <!-- Package details -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Weight (kg)</label>
                                <input type="number" name="weight_kg" step="0.001" min="0" class="form-control"
                                       placeholder="0.000"
                                       value="<?= e($_POST['weight_kg'] ?? '') ?>">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Dimensions (L×W×H cm)</label>
                                <input type="text" name="package_dimensions" class="form-control"
                                       placeholder="e.g. 30×20×15"
                                       value="<?= e($_POST['package_dimensions'] ?? '') ?>">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-truck me-2"></i>Mark as Shipped
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Bulk Ship -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-upload me-2"></i>Bulk Ship via CSV</h6>
                    <p class="text-muted small mb-3">
                        Upload a CSV with columns: <code>order_id, carrier, tracking_number</code>
                    </p>
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="bulk_csv" value="1">
                        <div class="mb-3">
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn btn-outline-secondary px-4">
                            <i class="bi bi-cloud-upload me-2"></i>Upload &amp; Process
                        </button>
                    </form>
                    <hr>
                    <p class="small text-muted mb-1">CSV format example:</p>
                    <pre class="small bg-light p-2 rounded mb-0">order_id,carrier,tracking_number
1001,dhl,1234567890
1002,fedex,9876543210</pre>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-generate tracking URL from carrier template + tracking number
(function() {
    var carrierSelect = document.getElementById('carrier-select');
    var trackInput    = document.getElementById('tracking-number');
    var urlInput      = document.getElementById('tracking-url');

    function updateUrl() {
        var selected = carrierSelect.options[carrierSelect.selectedIndex];
        var template = selected ? selected.getAttribute('data-template') : '';
        var num      = trackInput.value.trim();
        if (template && num) {
            urlInput.value = template.replace('{tracking_number}', encodeURIComponent(num));
        }
    }

    if (carrierSelect && trackInput && urlInput) {
        carrierSelect.addEventListener('change', updateUrl);
        trackInput.addEventListener('input', updateUrl);
    }
})();
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
