<?php
/**
 * api/ai/recommendations.php — Personalized Product Recommendations
 * GET (requires login)
 * Returns {success, sections:[{title, products:[]}]}
 */

require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/deepseek.php';

header('Content-Type: application/json');

requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// ------------------------------------------------------------------
// 1. Gather user history
// ------------------------------------------------------------------

// Recent orders (last 30 days)
$orderedStmt = $db->prepare(
    "SELECT DISTINCT p.id, p.name, p.category_id, p.price
     FROM order_items oi
     JOIN orders o  ON o.id  = oi.order_id
     JOIN products p ON p.id = oi.product_id
     WHERE o.buyer_id = ? AND o.placed_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
     LIMIT 20"
);
$orderedStmt->execute([$userId]);
$orderedProducts = $orderedStmt->fetchAll(PDO::FETCH_ASSOC);

// Wishlist / viewed products (from wishlist_items as a proxy for browsing)
$wishlistStmt = $db->prepare(
    "SELECT p.id, p.name, p.category_id, p.price
     FROM wishlist_items w
     JOIN products p ON p.id = w.product_id
     WHERE w.user_id = ?
     LIMIT 20"
);
$wishlistStmt->execute([$userId]);
$browsedProducts = $wishlistStmt->fetchAll(PDO::FETCH_ASSOC);

// Derive category IDs from history
$historyCategoryIds = array_unique(array_filter(array_merge(
    array_column($orderedProducts, 'category_id'),
    array_column($browsedProducts, 'category_id')
)));

// ------------------------------------------------------------------
// 2. Trending products (high rating, recent, not yet bought)
// ------------------------------------------------------------------
$boughtIds = array_column($orderedProducts, 'id') ?: [0];
$placeholders = implode(',', array_fill(0, count($boughtIds), '?'));

$trendingStmt = $db->prepare(
    "SELECT p.id, p.name, p.price, p.image, p.rating, p.review_count,
            c.name AS category_name, s.company_name AS supplier_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN suppliers  s ON s.id = p.supplier_id
     WHERE p.status = 'active'
       AND p.id NOT IN ($placeholders)
     ORDER BY p.rating DESC, p.view_count DESC
     LIMIT 8"
);
$trendingStmt->execute($boughtIds);
$trendingProducts = $trendingStmt->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------------------------------------------
// 3. Category-based recommendations
// ------------------------------------------------------------------
$categoryProducts = [];
if (!empty($historyCategoryIds)) {
    $catPlaceholders = implode(',', array_fill(0, count($historyCategoryIds), '?'));
    $catStmt = $db->prepare(
        "SELECT p.id, p.name, p.price, p.image, p.rating, p.review_count,
                c.name AS category_name, s.company_name AS supplier_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         LEFT JOIN suppliers  s ON s.id = p.supplier_id
         WHERE p.status = 'active'
           AND p.category_id IN ($catPlaceholders)
           AND p.id NOT IN ($placeholders)
         ORDER BY p.rating DESC
         LIMIT 8"
    );
    $catStmt->execute(array_merge($historyCategoryIds, $boughtIds));
    $categoryProducts = $catStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ------------------------------------------------------------------
// 4. AI-enhanced product selection (optional deep personalisation)
// ------------------------------------------------------------------
$aiPersonalised = [];
try {
    $deepseek = getDeepSeek();

    // Ask AI to pick best product IDs from the trending pool
    $candidateIds = array_column($trendingProducts, 'id');
    if (!empty($candidateIds) && (!empty($orderedProducts) || !empty($browsedProducts))) {
        $historyStr    = json_encode(array_slice(array_merge($orderedProducts, $browsedProducts), 0, 10));
        $candidateStr  = json_encode($candidateIds);

        $aiRaw = $deepseek->chat(
            "User purchase/browse history: $historyStr\n"
            . "Candidate product IDs: $candidateStr\n"
            . "Return a JSON array of up to 5 product IDs the user is most likely to buy next. "
            . "Return ONLY the JSON array, no explanation.",
            'You are a recommendation engine. Respond with a JSON array of integers only.',
            ['max_tokens' => 100, 'temperature' => 0.2]
        );

        $pickedIds = json_decode($aiRaw, true);
        if (is_array($pickedIds)) {
            $pickedIds = array_map('intval', $pickedIds);
            foreach ($trendingProducts as $p) {
                if (in_array((int)$p['id'], $pickedIds)) {
                    $aiPersonalised[] = $p;
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log('AI recommendations error: ' . $e->getMessage());
}

// ------------------------------------------------------------------
// 5. Format product cards helper
// ------------------------------------------------------------------
$formatProduct = function (array $p): array {
    return [
        'id'           => (int)$p['id'],
        'name'         => $p['name'],
        'price'        => (float)$p['price'],
        'image'        => $p['image'] ?: '/assets/img/no-image.png',
        'rating'       => (float)($p['rating'] ?? 0),
        'review_count' => (int)($p['review_count'] ?? 0),
        'category'     => $p['category_name'] ?? '',
        'supplier'     => $p['supplier_name'] ?? '',
    ];
};

// ------------------------------------------------------------------
// 6. Build sections
// ------------------------------------------------------------------
$sections = [];

if (!empty($aiPersonalised)) {
    $sections[] = [
        'title'       => 'Recommended Just for You',
        'icon'        => 'stars',
        'description' => 'AI-curated picks based on your history',
        'products'    => array_map($formatProduct, $aiPersonalised),
    ];
}

if (!empty($categoryProducts)) {
    $sections[] = [
        'title'       => 'More in Your Favourite Categories',
        'icon'        => 'tag-fill',
        'description' => 'Similar to items you have browsed or ordered',
        'products'    => array_map($formatProduct, $categoryProducts),
    ];
}

$sections[] = [
    'title'       => 'Trending on GlobexSky',
    'icon'        => 'graph-up-arrow',
    'description' => 'Top-rated products loved by our community',
    'products'    => array_map($formatProduct, $trendingProducts),
];

jsonResponse([
    'success'  => true,
    'sections' => $sections,
    'user_id'  => $userId,
]);
