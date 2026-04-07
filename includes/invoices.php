<?php
/**
 * includes/invoices.php — Invoice System (PR #10)
 *
 * Handles generating, retrieving and rendering invoices for
 * plan subscriptions and add-on purchases.
 */

/**
 * Generate (create) a new invoice record.
 *
 * @param array    $data   Keys: supplier_id, type, items, subtotal, tax_amount, total,
 *                         currency, status, payment_method, payment_ref, stripe_invoice_id, notes
 * @param PDO|null $db     Optionally pass existing connection (for transactions)
 * @return int  Newly-created invoice ID
 */
function generateInvoice(array $data, ?PDO $db = null): int
{
    if (!$db) $db = getDB();

    $supplierId    = (int)($data['supplier_id'] ?? 0);
    $type          = $data['type'] ?? 'addon_purchase';
    $items         = $data['items'] ?? [];
    $subtotal      = (float)($data['subtotal'] ?? 0);
    $taxAmount     = (float)($data['tax_amount'] ?? 0);
    $total         = (float)($data['total'] ?? $subtotal + $taxAmount);
    $currency      = $data['currency'] ?? 'USD';
    $status        = $data['status'] ?? 'pending';
    $paymentMethod = $data['payment_method'] ?? null;
    $paymentRef    = $data['payment_ref'] ?? null;
    $stripeInvId   = $data['stripe_invoice_id'] ?? null;
    $notes         = $data['notes'] ?? null;

    $invoiceNumber = _generateInvoiceNumber($db);
    $itemsJson     = json_encode($items);
    $paidAt        = ($status === 'paid') ? date('Y-m-d H:i:s') : null;

    $stmt = $db->prepare('INSERT INTO invoices
        (supplier_id, invoice_number, type, items_json, subtotal, tax_amount, total, currency,
         status, payment_method, payment_ref, stripe_invoice_id, notes, created_at, paid_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)');
    $stmt->execute([
        $supplierId, $invoiceNumber, $type, $itemsJson, $subtotal, $taxAmount, $total,
        $currency, $status, $paymentMethod, $paymentRef, $stripeInvId, $notes, $paidAt,
    ]);

    return (int)$db->lastInsertId();
}

/**
 * Generate next sequential invoice number: INV-YYYYMMDD-NNNN
 */
function _generateInvoiceNumber(PDO $db): string
{
    $prefix = 'INV-' . date('Ymd') . '-';
    try {
        $stmt = $db->prepare("SELECT invoice_number FROM invoices
            WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetchColumn();
        if ($last) {
            $seq = (int)substr($last, strlen($prefix)) + 1;
        } else {
            $seq = 1;
        }
    } catch (PDOException $e) {
        $seq = 1;
    }
    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Get a single invoice, optionally scoped to a supplier.
 */
function getInvoice(int $invoiceId, ?int $supplierId = null): array|false
{
    $db = getDB();
    try {
        if ($supplierId) {
            $stmt = $db->prepare('SELECT * FROM invoices WHERE id = ? AND supplier_id = ?');
            $stmt->execute([$invoiceId, $supplierId]);
        } else {
            $stmt = $db->prepare('SELECT * FROM invoices WHERE id = ?');
            $stmt->execute([$invoiceId]);
        }
        $inv = $stmt->fetch();
        if ($inv) {
            $inv['items_decoded'] = json_decode($inv['items_json'] ?? '[]', true) ?: [];
        }
        return $inv ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * List invoices with optional filters and pagination.
 *
 * @param int   $supplierId  0 = all (admin)
 * @param array $filters     ['type', 'status', 'date_from', 'date_to']
 */
function getInvoices(int $supplierId, array $filters = [], int $page = 1, int $perPage = 20): array
{
    $db     = getDB();
    $offset = ($page - 1) * $perPage;
    $where  = [];
    $params = [];

    if ($supplierId > 0) {
        $where[]  = 'i.supplier_id = ?';
        $params[] = $supplierId;
    }
    if (!empty($filters['type'])) {
        $where[]  = 'i.type = ?';
        $params[] = $filters['type'];
    }
    if (!empty($filters['status'])) {
        $where[]  = 'i.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['date_from'])) {
        $where[]  = 'i.created_at >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
        $where[]  = 'i.created_at <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $countParams = $params;
        $countStmt   = $db->prepare("SELECT COUNT(*) FROM invoices i $whereSql");
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetchColumn();

        $listParams   = array_merge($params, [$perPage, $offset]);
        $stmt         = $db->prepare("SELECT i.*, u.name AS supplier_name, u.company_name
            FROM invoices i
            LEFT JOIN users u ON u.id = i.supplier_id
            $whereSql ORDER BY i.created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute($listParams);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$r) {
            $r['items_decoded'] = json_decode($r['items_json'] ?? '[]', true) ?: [];
        }

        return ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'rows' => $rows];
    } catch (PDOException $e) {
        return ['total' => 0, 'page' => $page, 'per_page' => $perPage, 'rows' => []];
    }
}

/**
 * Generate printable HTML for an invoice.
 */
function generateInvoicePdf(int $invoiceId): string
{
    $inv = getInvoice($invoiceId);
    if (!$inv) return '<p>Invoice not found.</p>';

    $db = getDB();
    $supplier = [];
    try {
        $stmt = $db->prepare('SELECT name, email, company_name, address FROM users WHERE id = ?');
        $stmt->execute([$inv['supplier_id']]);
        $supplier = $stmt->fetch() ?: [];
    } catch (PDOException $e) { /* ignore */ }

    $items    = $inv['items_decoded'];
    $invNum   = htmlspecialchars($inv['invoice_number'], ENT_QUOTES);
    $date     = date('M d, Y', strtotime($inv['created_at']));
    $dueDate  = date('M d, Y', strtotime($inv['created_at'] . ' +14 days'));
    $status   = strtoupper($inv['status']);
    $statusCl = match($inv['status']) {
        'paid'     => '#28a745',
        'refunded' => '#fd7e14',
        default    => '#dc3545',
    };

    $lineRows = '';
    foreach ($items as $item) {
        $desc   = htmlspecialchars($item['description'] ?? '', ENT_QUOTES);
        $qty    = (int)($item['quantity'] ?? 1);
        $unit   = number_format((float)($item['unit_price'] ?? 0), 2);
        $amount = number_format((float)($item['total'] ?? 0), 2);
        $lineRows .= "<tr><td>$desc</td><td style='text-align:center'>$qty</td>"
            . "<td style='text-align:right'>$$$unit</td>"
            . "<td style='text-align:right'>$$$amount</td></tr>";
    }

    $subtotal = number_format((float)$inv['subtotal'], 2);
    $tax      = number_format((float)$inv['tax_amount'], 2);
    $total    = number_format((float)$inv['total'], 2);

    $supplierName    = htmlspecialchars($supplier['name'] ?? '', ENT_QUOTES);
    $supplierCompany = htmlspecialchars($supplier['company_name'] ?? '', ENT_QUOTES);
    $supplierEmail   = htmlspecialchars($supplier['email'] ?? '', ENT_QUOTES);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice $invNum</title>
<style>
  body { font-family: Arial, sans-serif; font-size: 14px; color: #333; margin: 0; padding: 20px; }
  .inv-header { display: flex; justify-content: space-between; margin-bottom: 30px; }
  .company-logo { font-size: 24px; font-weight: bold; color: #0d6efd; }
  .company-info { font-size: 12px; color: #666; margin-top: 4px; }
  .inv-title { font-size: 32px; font-weight: bold; color: #666; }
  .inv-meta { font-size: 13px; margin-bottom: 30px; }
  .parties { display: flex; justify-content: space-between; margin-bottom: 30px; }
  .party h4 { font-size: 12px; text-transform: uppercase; color: #888; margin-bottom: 6px; }
  table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  table.items th { background: #f8f9fa; border-bottom: 2px solid #dee2e6; padding: 10px; text-align: left; font-size: 12px; }
  table.items td { border-bottom: 1px solid #dee2e6; padding: 10px; }
  .totals { float: right; width: 280px; }
  .totals table { width: 100%; border-collapse: collapse; }
  .totals td { padding: 6px 10px; font-size: 13px; }
  .totals .total-row { font-weight: bold; font-size: 15px; border-top: 2px solid #333; }
  .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; color: #fff;
    background: $statusCl; font-size: 12px; font-weight: bold; }
  .footer { margin-top: 60px; font-size: 11px; color: #999; border-top: 1px solid #dee2e6; padding-top: 10px; }
  @media print { body { padding: 0; } }
</style>
</head>
<body>
<div class="inv-header">
  <div>
    <div class="company-logo">🌐 GlobexSky</div>
    <div class="company-info">Global B2B Marketplace<br>support@globexsky.com</div>
  </div>
  <div style="text-align:right">
    <div class="inv-title">INVOICE</div>
    <div class="inv-meta">
      <strong>#$invNum</strong><br>
      Date: $date<br>
      Due: $dueDate<br>
      <span class="status-badge">$status</span>
    </div>
  </div>
</div>

<div class="parties">
  <div class="party">
    <h4>From</h4>
    <strong>GlobexSky Marketplace</strong><br>
    support@globexsky.com
  </div>
  <div class="party">
    <h4>Bill To</h4>
    <strong>$supplierName</strong><br>
    $supplierCompany<br>
    $supplierEmail
  </div>
</div>

<table class="items">
  <thead>
    <tr>
      <th>Description</th>
      <th style="text-align:center">Qty</th>
      <th style="text-align:right">Unit Price</th>
      <th style="text-align:right">Amount</th>
    </tr>
  </thead>
  <tbody>
    $lineRows
  </tbody>
</table>

<div class="totals">
  <table>
    <tr><td>Subtotal</td><td style="text-align:right">$$subtotal</td></tr>
    <tr><td>Tax</td><td style="text-align:right">$$tax</td></tr>
    <tr class="total-row"><td>Total</td><td style="text-align:right">$$total {$inv['currency']}</td></tr>
  </table>
</div>
<div style="clear:both"></div>

<div class="footer">
  Payment method: {$inv['payment_method']} &nbsp;|&nbsp; Ref: {$inv['payment_ref']}<br>
  Thank you for using GlobexSky. For questions email support@globexsky.com.
</div>

<div style="margin-top:20px">
  <button onclick="window.print()" style="padding:8px 20px;cursor:pointer">🖨️ Print</button>
</div>
</body>
</html>
HTML;
}

/**
 * Mark an invoice as paid.
 */
function markInvoicePaid(int $invoiceId, string $paymentRef = ''): bool
{
    $db = getDB();
    try {
        $stmt = $db->prepare("UPDATE invoices
            SET status = 'paid', payment_ref = ?, paid_at = NOW()
            WHERE id = ?");
        $stmt->execute([$paymentRef, $invoiceId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get spending statistics for a supplier.
 */
function getInvoiceStats(int $supplierId): array
{
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT
            COALESCE(SUM(total), 0) AS total_spent,
            COALESCE(SUM(CASE WHEN type = 'plan_subscription' THEN total ELSE 0 END), 0) AS plans_spent,
            COALESCE(SUM(CASE WHEN type = 'addon_purchase' THEN total ELSE 0 END), 0) AS addons_spent,
            COUNT(*) AS invoice_count
            FROM invoices WHERE supplier_id = ? AND status = 'paid'");
        $stmt->execute([$supplierId]);
        return $stmt->fetch() ?: ['total_spent' => 0, 'plans_spent' => 0, 'addons_spent' => 0, 'invoice_count' => 0];
    } catch (PDOException $e) {
        return ['total_spent' => 0, 'plans_spent' => 0, 'addons_spent' => 0, 'invoice_count' => 0];
    }
}
