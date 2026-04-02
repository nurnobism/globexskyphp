<?php
/**
 * api/ai/search.php — AI-Powered Product Search
 * GET ?q=<query>&type=text|voice|image|barcode&page=1&limit=20
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/deepseek.php';

header('Content-Type: application/json');

$q     = trim(get('q', ''));
$type  = get('type', 'text');   // text | voice | image | barcode
$page  = max(1, (int)get('page', 1));
$limit = min(50, max(1, (int)get('limit', 20)));

if ($q === '') {
    jsonResponse(['success' => false, 'message' => 'Search query is required.'], 400);
}

$db = getDB();

// --- AI: extract structured filters from natural-language query ---
$filters           = [];
$queryInterpretation = $q;

try {
    $deepseek = getDeepSeek();
    $system   = 'You are a product search filter extractor for a B2B marketplace. '
              . 'Given a natural language query, extract structured search filters. '
              . 'Return ONLY valid JSON with these optional keys: '
              . '"keywords" (string), "category" (string), "min_price" (number), '
              . '"max_price" (number), "min_order" (number), "certifications" (array of strings), '
              . '"query_interpretation" (plain-English summary of what the user wants). '
              . 'Do not include markdown or explanation outside the JSON object.';

    $raw     = $deepseek->chat($q, $system, ['max_tokens' => 300, 'temperature' => 0.1]);
    $decoded = json_decode($raw, true);

    if (is_array($decoded)) {
        $filters             = $decoded;
        $queryInterpretation = $decoded['query_interpretation'] ?? $q;
    }
} catch (Throwable $e) {
    error_log('AI search filter error: ' . $e->getMessage());
}

// --- Build SQL query ---
$keywords = $filters['keywords'] ?? $q;
$words    = array_filter(array_map('trim', explode(' ', $keywords)));

$where  = ['p.status = "active"'];
$params = [];

// Keyword matching — search name, description, sku
if (!empty($words)) {
    $keywordClauses = [];
    foreach ($words as $word) {
        $keywordClauses[] = '(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)';
        $params[] = "%$word%";
        $params[] = "%$word%";
        $params[] = "%$word%";
    }
    $where[] = '(' . implode(' OR ', $keywordClauses) . ')';
}

// Category filter
if (!empty($filters['category'])) {
    $where[]  = '(c.name LIKE ? OR c.slug LIKE ?)';
    $params[] = '%' . $filters['category'] . '%';
    $params[] = '%' . $filters['category'] . '%';
}

// Price filters
if (!empty($filters['min_price']) && is_numeric($filters['min_price'])) {
    $where[]  = 'p.price >= ?';
    $params[] = (float)$filters['min_price'];
}
if (!empty($filters['max_price']) && is_numeric($filters['max_price'])) {
    $where[]  = 'p.price <= ?';
    $params[] = (float)$filters['max_price'];
}

// Min order
if (!empty($filters['min_order']) && is_numeric($filters['min_order'])) {
    $where[]  = 'p.min_order_qty <= ?';
    $params[] = (int)$filters['min_order'];
}

$whereSql = implode(' AND ', $where);

// Total count
$countSql  = "SELECT COUNT(*) FROM products p
              LEFT JOIN categories c ON c.id = p.category_id
              WHERE $whereSql";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Paginated results with relevance score
$offset = ($page - 1) * $limit;
$sql    = "SELECT
               p.id,
               p.name,
               p.price,
               p.image,
               p.sku,
               p.min_order_qty,
               p.stock_qty,
               p.rating,
               p.review_count,
               c.name  AS category_name,
               s.company_name AS supplier_name,
               (
                   (CASE WHEN p.name    LIKE ? THEN 40 ELSE 0 END) +
                   (CASE WHEN p.sku     LIKE ? THEN 30 ELSE 0 END) +
                   (CASE WHEN p.description LIKE ? THEN 10 ELSE 0 END) +
                   COALESCE(p.view_count, 0) / 100
               ) AS relevance_score
           FROM products p
           LEFT JOIN categories c ON c.id = p.category_id
           LEFT JOIN suppliers  s ON s.id = p.supplier_id
           WHERE $whereSql
           ORDER BY relevance_score DESC, p.rating DESC
           LIMIT $limit OFFSET $offset";

$relevanceParams = ["%$keywords%", "%$keywords%", "%$keywords%"];
$stmt = $db->prepare($sql);
$stmt->execute(array_merge($relevanceParams, $params));
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$results = array_map(function (array $row) {
    return [
        'id'              => (int)$row['id'],
        'name'            => $row['name'],
        'price'           => (float)$row['price'],
        'image'           => $row['image'] ?: '/assets/img/no-image.png',
        'sku'             => $row['sku'],
        'min_order_qty'   => (int)$row['min_order_qty'],
        'stock_qty'       => (int)$row['stock_qty'],
        'rating'          => (float)$row['rating'],
        'review_count'    => (int)$row['review_count'],
        'category'        => $row['category_name'],
        'supplier'        => $row['supplier_name'],
        'relevance_score' => round((float)$row['relevance_score'], 2),
    ];
}, $rows);

// Log search query if user is logged in
try {
    $userId = $_SESSION['user_id'] ?? null;
    $db->prepare(
        'INSERT IGNORE INTO admin_logs (user_id, action, entity, entity_id, details, created_at)
         VALUES (?, "ai_search", "search", NULL, ?, NOW())'
    )->execute([$userId, json_encode(['query' => $q, 'type' => $type, 'filters' => $filters, 'results' => $total])]);
} catch (Throwable $e) {
    // non-critical
}

jsonResponse([
    'success'              => true,
    'results'              => $results,
    'total'                => $total,
    'page'                 => $page,
    'pages'                => max(1, (int)ceil($total / $limit)),
    'query_interpretation' => $queryInterpretation,
    'applied_filters'      => $filters,
    'search_type'          => $type,
]);
