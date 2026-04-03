<?php
/**
 * api/ai/content.php — AI Content Generation Endpoint (Phase 8)
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/deepseek.php';
require_once __DIR__ . '/../../includes/ai-content.php';

header('Content-Type: application/json');
requireLogin();

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'generate_description':
        if ($method !== 'POST') { jsonResponse(['success' => false, 'error' => 'POST required'], 405); }
        $productId   = (int)($input['product_id'] ?? 0);
        $productData = $input['product_data'] ?? [];
        $style       = $input['style']    ?? 'professional';
        $language    = $input['language'] ?? 'en';

        if ($productId && empty($productData)) {
            $db = getDB();
            try {
                $stmt = $db->prepare("SELECT id, name, price, description, category_id FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                $productData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e) {}
        }
        if (empty($productData)) { jsonResponse(['success' => false, 'error' => 'product_id or product_data required'], 400); }

        $result = generateProductDescription($productData, $style, $language);
        jsonResponse(['success' => true, 'data' => ['generated_text' => $result]]);
        break;

    case 'generate_seo':
        if ($method !== 'POST') { jsonResponse(['success' => false, 'error' => 'POST required'], 405); }
        $productId   = (int)($input['product_id'] ?? 0);
        $productData = $input['product_data'] ?? [];
        if ($productId && empty($productData)) {
            $db = getDB();
            try {
                $stmt = $db->prepare("SELECT id, name, description FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                $productData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e) {}
        }
        if (empty($productData)) { jsonResponse(['success' => false, 'error' => 'product_id or product_data required'], 400); }

        $title = generateSEOTitle($productData);
        $meta  = generateSEOMeta($productData);
        jsonResponse(['success' => true, 'data' => ['seo_title' => $title, 'seo_meta' => $meta]]);
        break;

    case 'summarize_reviews':
        $productId = (int)($input['product_id'] ?? $_GET['product_id'] ?? 0);
        if (!$productId) { jsonResponse(['success' => false, 'error' => 'product_id required'], 400); }
        $result = summarizeReviews($productId);
        jsonResponse(['success' => true, 'data' => $result]);
        break;

    case 'translate':
        if ($method !== 'POST') { jsonResponse(['success' => false, 'error' => 'POST required'], 405); }
        $text         = trim($input['text']            ?? '');
        $targetLang   = trim($input['target_language'] ?? 'en');
        $fromLang     = trim($input['from_language']   ?? 'auto');
        if (!$text) { jsonResponse(['success' => false, 'error' => 'text required'], 400); }
        $result = translateContent($text, $fromLang, $targetLang);
        jsonResponse(['success' => true, 'data' => ['translated_text' => $result]]);
        break;

    case 'improve':
        if ($method !== 'POST') { jsonResponse(['success' => false, 'error' => 'POST required'], 405); }
        $text         = trim($input['text']         ?? '');
        $instructions = trim($input['instructions'] ?? 'Make it more professional and compelling.');
        if (!$text) { jsonResponse(['success' => false, 'error' => 'text required'], 400); }
        $result = improveText($text, $instructions);
        jsonResponse(['success' => true, 'data' => ['improved_text' => $result]]);
        break;

    case 'generate_ad_copy':
        if ($method !== 'POST') { jsonResponse(['success' => false, 'error' => 'POST required'], 405); }
        $productData = $input['product_data'] ?? [];
        $platform    = $input['platform']     ?? 'google';
        if (empty($productData)) { jsonResponse(['success' => false, 'error' => 'product_data required'], 400); }
        $result = generateAdCopy($productData, $platform);
        jsonResponse(['success' => true, 'data' => $result]);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}
