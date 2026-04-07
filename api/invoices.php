<?php
/**
 * api/invoices.php — Invoice API (PR #10)
 *
 * Actions:
 *   list      — GET:  List invoices (supplier: own, admin: all)
 *   get       — GET:  Get single invoice detail
 *   download  — GET:  Download/render invoice as printable HTML
 *   stats     — GET:  Invoice/spending statistics
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/invoices.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

function invoicesJson(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

switch ($action) {

    // ── List invoices ─────────────────────────────────────────────────
    case 'list':
        requireLogin();

        $supplierId = isAdmin() ? (int)($_GET['supplier_id'] ?? 0) : (int)$_SESSION['user_id'];
        $page       = max(1, (int)($_GET['page'] ?? 1));
        $perPage    = min(100, max(1, (int)($_GET['per_page'] ?? 20)));

        $filters = [];
        if (!empty($_GET['type']))      $filters['type']      = $_GET['type'];
        if (!empty($_GET['status']))    $filters['status']    = $_GET['status'];
        if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
        if (!empty($_GET['date_to']))   $filters['date_to']   = $_GET['date_to'];

        $result = getInvoices($supplierId, $filters, $page, $perPage);
        invoicesJson(['success' => true] + $result);

    // ── Get single invoice ────────────────────────────────────────────
    case 'get':
        requireLogin();

        $invoiceId  = (int)($_GET['invoice_id'] ?? 0);
        if ($invoiceId <= 0) invoicesJson(['error' => 'invoice_id required'], 400);

        $supplierId = isAdmin() ? null : (int)$_SESSION['user_id'];
        $invoice    = getInvoice($invoiceId, $supplierId);
        if (!$invoice) invoicesJson(['error' => 'Invoice not found'], 404);

        invoicesJson(['success' => true, 'invoice' => $invoice]);

    // ── Download invoice as printable HTML ────────────────────────────
    case 'download':
        requireLogin();

        $invoiceId = (int)($_GET['invoice_id'] ?? 0);
        if ($invoiceId <= 0) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'invoice_id required']);
            exit;
        }

        $supplierId = isAdmin() ? null : (int)$_SESSION['user_id'];
        $invoice    = getInvoice($invoiceId, $supplierId);
        if (!$invoice) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => 'Invoice not found']);
            exit;
        }

        // Output HTML for printing/download
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="invoice-' . $invoice['invoice_number'] . '.html"');
        echo generateInvoicePdf($invoiceId);
        exit;

    // ── Spending statistics ───────────────────────────────────────────
    case 'stats':
        requireLogin();

        $supplierId = isAdmin()
            ? (int)($_GET['supplier_id'] ?? $_SESSION['user_id'])
            : (int)$_SESSION['user_id'];

        $stats = getInvoiceStats($supplierId);
        invoicesJson(['success' => true, 'stats' => $stats]);

    default:
        invoicesJson(['error' => 'Unknown action'], 400);
}
