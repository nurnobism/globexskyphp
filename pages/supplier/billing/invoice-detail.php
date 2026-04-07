<?php
/**
 * pages/supplier/billing/invoice-detail.php — Single Invoice View (PR #10)
 *
 * Professional invoice layout with print/download support.
 */
require_once __DIR__ . '/../../../includes/middleware.php';
require_once __DIR__ . '/../../../includes/invoices.php';

requireRole(['supplier', 'admin', 'super_admin']);

$invoiceId  = (int)($_GET['id'] ?? 0);
$supplierId = isAdmin() ? null : (int)$_SESSION['user_id'];

if ($invoiceId <= 0) {
    header('Location: /pages/supplier/billing/invoices.php');
    exit;
}

$invoice = getInvoice($invoiceId, $supplierId);
if (!$invoice) {
    header('Location: /pages/supplier/billing/invoices.php?error=Invoice+not+found');
    exit;
}

$db = getDB();
$supplier = [];
try {
    $stmt = $db->prepare('SELECT name, email, company_name, address FROM users WHERE id = ?');
    $stmt->execute([$invoice['supplier_id']]);
    $supplier = $stmt->fetch() ?: [];
} catch (PDOException $e) { /* ignore */ }

$items   = $invoice['items_decoded'];
$pageTitle = 'Invoice ' . $invoice['invoice_number'];
include __DIR__ . '/../../../includes/header.php';
?>
<style>
.invoice-box { max-width: 860px; margin: 0 auto; padding: 30px; }
.invoice-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; }
.company-brand { font-size: 22px; font-weight: bold; color: #0d6efd; }
.company-sub { font-size: 12px; color: #666; margin-top: 4px; }
.inv-number { font-size: 28px; font-weight: bold; color: #aaa; }
.party-section { display: flex; justify-content: space-between; margin-bottom: 30px; }
.party h6 { font-size: 11px; text-transform: uppercase; color: #888; margin-bottom: 6px; letter-spacing: .05em; }
@media print {
    .no-print { display: none !important; }
    .invoice-box { margin: 0; padding: 15px; }
}
</style>

<div class="container py-4">
    <div class="no-print mb-3 d-flex justify-content-between">
        <a href="/pages/supplier/billing/invoices.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Invoices
        </a>
        <div>
            <button class="btn btn-outline-primary btn-sm me-2" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Print
            </button>
            <a href="/api/invoices.php?action=download&invoice_id=<?= $invoiceId ?>"
                target="_blank" class="btn btn-primary btn-sm">
                <i class="bi bi-download me-1"></i>Download HTML
            </a>
        </div>
    </div>

    <div class="invoice-box card shadow-sm p-4">

        <!-- Header -->
        <div class="invoice-header">
            <div>
                <div class="company-brand">🌐 GlobexSky</div>
                <div class="company-sub">Global B2B Marketplace<br>support@globexsky.com</div>
            </div>
            <div class="text-end">
                <div class="inv-number">INVOICE</div>
                <div class="text-muted small mt-1">
                    <strong>#<?= htmlspecialchars($invoice['invoice_number'], ENT_QUOTES) ?></strong><br>
                    Date: <?= date('M d, Y', strtotime($invoice['created_at'])) ?><br>
                    Due: <?= date('M d, Y', strtotime($invoice['created_at'] . ' +14 days')) ?>
                </div>
                <div class="mt-2">
                    <?php if ($invoice['status'] === 'paid'): ?>
                        <span class="badge bg-success fs-6">✅ PAID</span>
                    <?php elseif ($invoice['status'] === 'refunded'): ?>
                        <span class="badge bg-secondary fs-6">↩️ REFUNDED</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark fs-6">⏳ PENDING</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <hr>

        <!-- Parties -->
        <div class="party-section">
            <div class="party">
                <h6>From</h6>
                <strong>GlobexSky Marketplace</strong><br>
                <span class="text-muted">support@globexsky.com</span>
            </div>
            <div class="party text-end">
                <h6>Bill To</h6>
                <strong><?= htmlspecialchars($supplier['name'] ?? '', ENT_QUOTES) ?></strong><br>
                <?php if (!empty($supplier['company_name'])): ?>
                    <?= htmlspecialchars($supplier['company_name'], ENT_QUOTES) ?><br>
                <?php endif; ?>
                <span class="text-muted"><?= htmlspecialchars($supplier['email'] ?? '', ENT_QUOTES) ?></span>
            </div>
        </div>

        <!-- Line Items -->
        <div class="table-responsive mt-2 mb-4">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Description</th>
                        <th class="text-center" width="80">Qty</th>
                        <th class="text-end" width="120">Unit Price</th>
                        <th class="text-end" width="120">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES) ?></td>
                        <td class="text-center"><?= (int)($item['quantity'] ?? 1) ?></td>
                        <td class="text-end">$<?= number_format((float)($item['unit_price'] ?? 0), 2) ?></td>
                        <td class="text-end">$<?= number_format((float)($item['total'] ?? 0), 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($items)): ?>
                    <tr><td colspan="4" class="text-center text-muted">No line items.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="row justify-content-end">
            <div class="col-md-4">
                <table class="table table-sm">
                    <tr>
                        <td>Subtotal</td>
                        <td class="text-end">$<?= number_format((float)$invoice['subtotal'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Tax</td>
                        <td class="text-end">$<?= number_format((float)$invoice['tax_amount'], 2) ?></td>
                    </tr>
                    <tr class="fw-bold border-top">
                        <td>Total</td>
                        <td class="text-end fs-5">$<?= number_format((float)$invoice['total'], 2) ?>
                            <small class="text-muted"><?= htmlspecialchars($invoice['currency'], ENT_QUOTES) ?></small>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Payment Info -->
        <?php if ($invoice['payment_method'] || $invoice['payment_ref']): ?>
        <div class="alert alert-light border mt-2">
            <strong>Payment Info:</strong>
            <?php if ($invoice['payment_method']): ?>
                Method: <?= htmlspecialchars(ucfirst($invoice['payment_method']), ENT_QUOTES) ?> &nbsp;|&nbsp;
            <?php endif; ?>
            <?php if ($invoice['payment_ref']): ?>
                Transaction: <code><?= htmlspecialchars($invoice['payment_ref'], ENT_QUOTES) ?></code>
            <?php endif; ?>
            <?php if ($invoice['paid_at']): ?>
                &nbsp;|&nbsp; Paid at: <?= date('M d, Y H:i', strtotime($invoice['paid_at'])) ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-muted small mt-4 border-top pt-3">
            Thank you for using GlobexSky. For billing inquiries contact support@globexsky.com.<br>
            This invoice is generated automatically and is valid without a signature.
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
