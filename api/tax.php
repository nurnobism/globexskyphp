<?php
/**
 * api/tax.php — Tax Calculation Engine API (PR #12)
 *
 * Actions:
 *   calculate      POST  Calculate tax for given subtotal + address
 *   rates          GET   Get all tax rates (admin)
 *   set_rate       POST  Set country/state tax rate (admin)
 *   delete_rate    POST  Delete a tax rate (admin)
 *   validate_vat   POST  Validate a VAT number
 *   config         GET   Get current tax configuration (admin)
 *   update_config  POST  Update tax configuration (admin)
 *   report         GET   Tax report (admin)
 *   exemptions     GET   List tax exemptions (admin)
 *   add_exemption  POST  Add a tax exemption (admin)
 */

require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/tax_engine.php';
require_once __DIR__ . '/../includes/countries.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

/** @return never */
function taxJson(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

switch ($action) {

    // ── calculate ──────────────────────────────────────────────────────────
    case 'calculate':
        if ($method !== 'POST') taxJson(['error' => 'POST required'], 405);

        $subtotal    = (float)($_POST['subtotal']     ?? 0);
        $countryCode = strtoupper(trim($_POST['country_code'] ?? ''));
        $stateCode   = strtoupper(trim($_POST['state_code']   ?? ''));
        $vatNumber   = trim($_POST['vat_number']  ?? '');
        $userId      = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;

        if ($subtotal <= 0) taxJson(['error' => 'subtotal must be > 0'], 400);

        $shippingAddress = ['country' => $countryCode, 'state' => $stateCode];
        $result = calculateTax($subtotal, $shippingAddress, [], $userId, $vatNumber);

        taxJson(['success' => true, 'data' => $result]);

    // ── rates ──────────────────────────────────────────────────────────────
    case 'rates':
        requireAdmin();
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 100)));

        $rates = getAllTaxRates($page, $perPage);
        taxJson(['success' => true, 'data' => $rates]);

    // ── set_rate ───────────────────────────────────────────────────────────
    case 'set_rate':
        requireAdmin();
        if ($method !== 'POST') taxJson(['error' => 'POST required'], 405);
        verifyCsrf();

        $countryCode = strtoupper(trim($_POST['country_code'] ?? ''));
        $stateCode   = strtoupper(trim($_POST['state_code']   ?? ''));
        $rate        = (float)($_POST['rate']    ?? 0);
        $taxName     = trim($_POST['name']       ?? 'Tax');
        $stateName   = trim($_POST['state_name'] ?? '');
        $countryName = trim($_POST['country_name'] ?? '');

        if ($countryCode === '' || strlen($countryCode) !== 2) {
            taxJson(['error' => 'country_code must be a 2-letter ISO code'], 400);
        }
        if ($rate < 0 || $rate > 100) {
            taxJson(['error' => 'rate must be between 0 and 100'], 400);
        }

        $ok = setCountryTaxRate($countryCode, $rate, $taxName, $stateCode, $stateName, $countryName);
        taxJson(['success' => $ok]);

    // ── delete_rate ────────────────────────────────────────────────────────
    case 'delete_rate':
        requireAdmin();
        if ($method !== 'POST') taxJson(['error' => 'POST required'], 405);
        verifyCsrf();

        $rateId = (int)($_POST['rate_id'] ?? 0);
        if ($rateId <= 0) taxJson(['error' => 'rate_id required'], 400);

        $ok = deleteCountryTaxRate($rateId);
        taxJson(['success' => $ok]);

    // ── validate_vat ───────────────────────────────────────────────────────
    case 'validate_vat':
        if ($method !== 'POST') taxJson(['error' => 'POST required'], 405);

        $vatNumber   = strtoupper(trim(str_replace([' ', '-', '.'], '', $_POST['vat_number']   ?? '')));
        $countryCode = strtoupper(trim($_POST['country_code'] ?? ''));

        if ($vatNumber === '') taxJson(['error' => 'vat_number required'], 400);

        // Extract country code from VAT number prefix if not explicitly provided
        if ($countryCode === '' && strlen($vatNumber) >= 2) {
            $prefix = substr($vatNumber, 0, 2);
            if (ctype_alpha($prefix)) {
                $countryCode = $prefix;
            }
        }

        $valid = validateVatNumber($vatNumber, $countryCode);
        taxJson(['success' => true, 'data' => ['valid' => $valid]]);

    // ── config ─────────────────────────────────────────────────────────────
    case 'config':
        requireAdmin();
        $keys = ['tax_mode','tax_fixed_rate','tax_default_rate','tax_inclusive',
                 'show_tax_on_product','tax_label','vies_validation_enabled'];
        $config = [];
        foreach ($keys as $k) {
            $config[$k] = getTaxSetting($k, '');
        }
        taxJson(['success' => true, 'data' => $config]);

    // ── update_config ──────────────────────────────────────────────────────
    case 'update_config':
        requireAdmin();
        if ($method !== 'POST') taxJson(['error' => 'POST required'], 405);
        verifyCsrf();

        $db      = getDB();
        $allowed = ['tax_mode','tax_fixed_rate','tax_default_rate','tax_inclusive',
                    'show_tax_on_product','tax_label','vies_validation_enabled'];

        $updated = 0;
        foreach ($allowed as $key) {
            if (!isset($_POST[$key])) continue;
            $value = trim((string)$_POST[$key]);

            // Validate
            if ($key === 'tax_mode' && !in_array($value, ['fixed','per_country','vat'], true)) continue;
            if (in_array($key, ['tax_fixed_rate','tax_default_rate'], true)) {
                $v = (float)$value;
                if ($v < 0 || $v > 100) continue;
                $value = (string)$v;
            }
            if (in_array($key, ['tax_inclusive','show_tax_on_product','vies_validation_enabled'], true)) {
                $value = $value === '1' || $value === 'true' || $value === 'on' ? '1' : '0';
            }

            try {
                $db->prepare(
                    "INSERT INTO system_settings (setting_key, setting_value)
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
                )->execute([$key, $value]);
                $updated++;
            } catch (PDOException $e) { /* ignore */ }
        }

        taxJson(['success' => true, 'updated' => $updated]);

    // ── report ─────────────────────────────────────────────────────────────
    case 'report':
        requireAdmin();
        $dateFrom = trim($_GET['date_from'] ?? date('Y-m-01'));
        $dateTo   = trim($_GET['date_to']   ?? date('Y-m-d'));
        $groupBy  = trim($_GET['group_by']  ?? 'country');
        $export   = ($_GET['export'] ?? '') === 'csv';

        if ($export) {
            $csv = exportTaxReport($dateFrom, $dateTo);
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="tax-report-' . date('Y-m-d') . '.csv"');
            echo $csv;
            exit;
        }

        $rows    = getTaxReport($dateFrom, $dateTo, $groupBy);
        $summary = [
            'month'   => getTaxSummary('month'),
            'quarter' => getTaxSummary('quarter'),
            'year'    => getTaxSummary('year'),
        ];
        taxJson(['success' => true, 'data' => $rows, 'summary' => $summary]);

    // ── exemptions ─────────────────────────────────────────────────────────
    case 'exemptions':
        requireAdmin();
        $page    = max(1, (int)($_GET['page']     ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $result  = getTaxExemptions($page, $perPage);
        taxJson(array_merge(['success' => true], $result));

    // ── add_exemption ──────────────────────────────────────────────────────
    case 'add_exemption':
        requireAdmin();
        if ($method !== 'POST') taxJson(['error' => 'POST required'], 405);
        verifyCsrf();

        $userId            = (int)($_POST['user_id']            ?? 0);
        $exemptionType     = trim($_POST['type']                ?? 'full');
        $certificateNumber = trim($_POST['certificate_number']  ?? '');
        $expiryDate        = trim($_POST['expiry_date']         ?? '');
        $grantedBy         = (int)$_SESSION['user_id'];

        if ($userId <= 0) taxJson(['error' => 'user_id required'], 400);

        $ok = setTaxExemption($userId, $exemptionType, $certificateNumber, $expiryDate, $grantedBy);
        taxJson(['success' => $ok]);

    // ── default ────────────────────────────────────────────────────────────
    default:
        taxJson(['error' => 'Unknown action', 'available' => [
            'calculate','rates','set_rate','delete_rate','validate_vat',
            'config','update_config','report','exemptions','add_exemption',
        ]], 400);
}
