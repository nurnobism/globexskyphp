<?php
require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');
$action = $_REQUEST['action'] ?? '';
$db = getDB();

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

switch ($action) {
    case 'apply':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $userId       = $_SESSION['user_id'];
        $financeType  = trim($_POST['finance_type'] ?? '');
        $amount       = (float)($_POST['requested_amount'] ?? 0);
        $businessName = trim($_POST['business_name'] ?? '');
        $businessType = trim($_POST['business_type'] ?? 'llc');
        $years        = (int)($_POST['years_in_business'] ?? 0);
        $revenue      = !empty($_POST['annual_revenue']) ? (float)$_POST['annual_revenue'] : null;
        $purpose      = trim($_POST['purpose'] ?? '');
        $additional   = trim($_POST['additional_info'] ?? '');
        if (!$financeType || $amount <= 0 || !$businessName || !$purpose) {
            jsonResponse(['error' => 'Required fields missing'], 422);
        }
        $stmt = $db->prepare("INSERT INTO trade_finance_applications
            (applicant_id, finance_type, requested_amount, business_name, business_type, years_in_business, annual_revenue, purpose, additional_info, status)
            VALUES (?,?,?,?,?,?,?,?,?,'pending')");
        $stmt->execute([$userId, $financeType, $amount, $businessName, $businessType, $years, $revenue, $purpose, $additional]);
        header('Location: /pages/trade-finance/credit-application.php');
        exit;

    case 'list_applications':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare("SELECT * FROM trade_finance_applications WHERE applicant_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        jsonResponse(['success' => true, 'applications' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    case 'apply_lc':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $userId          = $_SESSION['user_id'];
        $lcType          = trim($_POST['lc_type'] ?? 'irrevocable');
        $amount          = (float)($_POST['amount'] ?? 0);
        $currency        = trim($_POST['currency'] ?? 'USD');
        $expiryDate      = trim($_POST['expiry_date'] ?? '');
        $beneficiary     = trim($_POST['beneficiary_name'] ?? '');
        $beneficiaryBank = trim($_POST['beneficiary_bank'] ?? '');
        $goodsDesc       = trim($_POST['goods_description'] ?? '');
        $specialTerms    = trim($_POST['special_terms'] ?? '');
        if (!$amount || !$expiryDate || !$beneficiary || !$goodsDesc) {
            jsonResponse(['error' => 'Required fields missing'], 422);
        }
        $lcNumber = 'GS-LC-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));
        $stmt = $db->prepare("INSERT INTO letters_of_credit
            (applicant_id, lc_number, lc_type, amount, currency, expiry_date, beneficiary_name, beneficiary_bank, goods_description, special_terms, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,'submitted')");
        $stmt->execute([$userId, $lcNumber, $lcType, $amount, $currency, $expiryDate, $beneficiary, $beneficiaryBank, $goodsDesc, $specialTerms]);
        header('Location: /pages/trade-finance/letter-of-credit.php');
        exit;

    case 'list_lcs':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare("SELECT * FROM letters_of_credit WHERE applicant_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        jsonResponse(['success' => true, 'letters_of_credit' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    case 'insurance_quote':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $userId      = $_SESSION['user_id'];
        $type        = trim($_POST['insurance_type'] ?? 'cargo');
        $coverage    = (float)($_POST['coverage_amount'] ?? 0);
        $origin      = trim($_POST['origin_country'] ?? '');
        $destination = trim($_POST['destination_country'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (!$coverage) jsonResponse(['error' => 'Coverage amount required'], 422);
        $rates = ['cargo' => 0.003, 'credit' => 0.005, 'political_risk' => 0.008, 'product_liability' => 0.01];
        $rate = $rates[$type] ?? 0.005;
        $premium = round($coverage * $rate, 2);
        $stmt = $db->prepare("INSERT INTO trade_insurance
            (user_id, insurance_type, coverage_amount, origin_country, destination_country, description, premium_amount, status)
            VALUES (?,?,?,?,?,?,?,'quote_requested')");
        $stmt->execute([$userId, $type, $coverage, $origin, $destination, $description, $premium]);
        jsonResponse(['success' => true, 'premium' => $premium, 'rate' => $rate * 100 . '%', 'message' => 'Quote submitted. You will be contacted within 24 hours.']);

    case 'update_application':
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        verifyCsrf();
        $id     = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $notes  = trim($_POST['notes'] ?? '');
        if (!in_array($status, ['pending','under_review','approved','rejected'])) jsonResponse(['error' => 'Invalid status'], 422);
        $stmt = $db->prepare("UPDATE trade_finance_applications SET status = ?, admin_notes = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
        $stmt->execute([$status, $notes, $_SESSION['user_id'], $id]);
        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
