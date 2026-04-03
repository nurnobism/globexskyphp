<?php
/**
 * includes/ai-search.php — AI-Enhanced Smart Search
 *
 * Natural language query interpretation and AI-powered search results
 * using DeepSeek.  Falls back to standard SQL search on AI unavailability.
 *
 * Requires includes/ai-engine.php.
 */

require_once __DIR__ . '/ai-engine.php';

/**
 * AI-enhanced product search.
 * Interprets natural language queries and builds structured SQL queries.
 *
 * @return array ['products' => [...], 'interpreted' => string, 'total' => int, 'ai_enhanced' => bool]
 */
function enhancedSearch(string $query, ?int $userId = null): array
{
    $query     = trim($query);
    $startTime = microtime(true);

    if (empty($query)) {
        return ['products' => [], 'interpreted' => '', 'total' => 0, 'ai_enhanced' => false];
    }

    $interpreted = $query;
    $aiEnhanced  = false;
    $filters     = [];

    // Use AI to parse the query if feature is enabled
    if (isAiEnabled('search')) {
        $messages = [
            ['role' => 'system', 'content' =>
                'You are a product search query parser. Extract structured filters from natural language. '
              . 'Return a JSON object only with these optional keys: '
              . 'keywords (string), category (string), min_price (number), max_price (number), '
              . 'features (array of strings), interpreted_query (human-readable summary). '
              . 'Return ONLY valid JSON, no other text.'],
            ['role' => 'user', 'content' => "Parse this product search query: $query"],
        ];

        $result = deepseekRequest($messages, [
            'user_id'     => $userId,
            'feature'     => 'search',
            'max_tokens'  => 300,
            'temperature' => 0.2,
        ]);

        if ($result['success']) {
            $raw = $result['content'];
            if (preg_match('/\{.*\}/s', $raw, $m)) {
                $parsed = json_decode($m[0], true);
                if (is_array($parsed)) {
                    $filters     = $parsed;
                    $interpreted = $parsed['interpreted_query'] ?? $query;
                    $aiEnhanced  = true;
                }
            }
        }
    }

    // Build SQL from parsed filters or raw query
    $products   = _runProductSearch($query, $filters);
    $responseMs = (int)((microtime(true) - $startTime) * 1000);

    // Log search
    _logSearch($userId, $query, $interpreted, 'product', count($products), $aiEnhanced, $responseMs);

    return [
        'products'    => $products,
        'interpreted' => $interpreted,
        'total'       => count($products),
        'ai_enhanced' => $aiEnhanced,
        'filters'     => $filters,
    ];
}

/**
 * Return autocomplete suggestions for a partial query.
 *
 * @return string[]
 */
function suggestSearchTerms(string $partialQuery): array
{
    if (strlen($partialQuery) < 2) {
        return [];
    }

    // First try DB-based suggestions (recent popular searches)
    $suggestions = [];
    try {
        $stmt = getDB()->prepare(
            "SELECT DISTINCT query_text
             FROM ai_search_logs
             WHERE query_text LIKE ? AND ai_enhanced = 1
             ORDER BY created_at DESC
             LIMIT 5"
        );
        $stmt->execute([$partialQuery . '%']);
        $suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) { /* ignore */ }

    if (count($suggestions) >= 5) {
        return $suggestions;
    }

    // Supplement with product titles
    try {
        $stmt = getDB()->prepare(
            "SELECT DISTINCT title FROM products
             WHERE title LIKE ? AND status = 'active'
             LIMIT 5"
        );
        $stmt->execute(['%' . $partialQuery . '%']);
        $titles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $suggestions = array_unique(array_merge($suggestions, $titles));
    } catch (Throwable $e) { /* ignore */ }

    return array_slice($suggestions, 0, 8);
}

/**
 * AI-powered spell correction for product search queries.
 */
function spellCorrect(string $query): string
{
    if (!isAiEnabled('search') || strlen($query) < 3) {
        return $query;
    }

    $messages = [
        ['role' => 'system', 'content' => 'Correct spelling errors in product search queries. Return ONLY the corrected query text.'],
        ['role' => 'user',   'content' => "Correct: $query"],
    ];

    $result = deepseekRequest($messages, [
        'feature'     => 'search',
        'max_tokens'  => 100,
        'temperature' => 0.1,
    ]);

    return $result['success'] ? trim($result['content']) : $query;
}

/**
 * Translate a search query to a target language for cross-language matching.
 */
function translateSearchQuery(string $query, string $targetLang): string
{
    if (!isAiEnabled('search')) {
        return $query;
    }

    $messages = [
        ['role' => 'system', 'content' => "Translate product search queries to $targetLang. Return only the translated text."],
        ['role' => 'user',   'content' => $query],
    ];

    $result = deepseekRequest($messages, [
        'feature'     => 'search',
        'max_tokens'  => 200,
        'temperature' => 0.1,
    ]);

    return $result['success'] ? trim($result['content']) : $query;
}

/**
 * Get search analytics for admin dashboard.
 * Period: 'today', 'week', 'month'
 */
function getSearchAnalytics(string $period = 'week'): array
{
    $dateFilter = match ($period) {
        'today' => 'AND DATE(created_at) = CURDATE()',
        'week'  => 'AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
        'month' => 'AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
        default => '',
    };

    try {
        $db = getDB();

        $stmt = $db->prepare(
            "SELECT
               COUNT(*) AS total_searches,
               SUM(ai_enhanced) AS ai_searches,
               AVG(results_count) AS avg_results,
               AVG(response_time_ms) AS avg_response_ms
             FROM ai_search_logs
             WHERE 1=1 $dateFilter"
        );
        $stmt->execute();
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $db->prepare(
            "SELECT query_text, COUNT(*) AS count
             FROM ai_search_logs
             WHERE 1=1 $dateFilter
             GROUP BY query_text
             ORDER BY count DESC
             LIMIT 20"
        );
        $stmt->execute();
        $topQueries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['summary' => $summary, 'top_queries' => $topQueries];
    } catch (Throwable $e) {
        return ['summary' => [], 'top_queries' => []];
    }
}

// ── Internal helpers ──────────────────────────────────────────

/**
 * Run actual SQL product search using parsed filters.
 */
function _runProductSearch(string $rawQuery, array $filters): array
{
    $keywords = $filters['keywords'] ?? $rawQuery;
    $minPrice = isset($filters['min_price']) ? (float)$filters['min_price'] : null;
    $maxPrice = isset($filters['max_price']) ? (float)$filters['max_price'] : null;

    $where  = ["p.status = 'active'"];
    $params = [];

    if (!empty($keywords)) {
        $where[]  = '(p.title LIKE ? OR p.description LIKE ?)';
        $like     = '%' . $keywords . '%';
        $params[] = $like;
        $params[] = $like;
    }
    if ($minPrice !== null) {
        $where[]  = 'p.price >= ?';
        $params[] = $minPrice;
    }
    if ($maxPrice !== null) {
        $where[]  = 'p.price <= ?';
        $params[] = $maxPrice;
    }
    if (!empty($filters['category'])) {
        $where[]  = 'c.name LIKE ?';
        $params[] = '%' . $filters['category'] . '%';
    }

    $params[] = 20; // limit

    try {
        $stmt = getDB()->prepare(
            'SELECT p.id, p.title, p.price, p.image_url, p.category_id
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.created_at DESC
             LIMIT ?'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Log a search query to ai_search_logs.
 */
function _logSearch(
    ?int $userId,
    string $queryText,
    string $interpretedQuery,
    string $searchType,
    int $resultsCount,
    bool $aiEnhanced,
    int $responseMs
): void {
    try {
        getDB()->prepare(
            'INSERT INTO ai_search_logs
             (user_id, query_text, interpreted_query, search_type, results_count, ai_enhanced, response_time_ms)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $userId ?: null,
            mb_substr($queryText, 0, 500),
            mb_substr($interpretedQuery, 0, 500),
            $searchType,
            $resultsCount,
            $aiEnhanced ? 1 : 0,
            $responseMs,
        ]);
    } catch (Throwable $e) { /* silent */ }
}
