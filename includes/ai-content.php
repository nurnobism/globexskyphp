<?php
/**
 * includes/ai-content.php — AI Content Generation
 *
 * Generates product descriptions, SEO meta, translations, review
 * summaries, and email templates using DeepSeek AI.
 * Results are cached in ai_content_cache to minimise API calls.
 *
 * Requires includes/ai-engine.php.
 */

require_once __DIR__ . '/ai-engine.php';

/**
 * Generate a product description from basic product data.
 *
 * @param  array  $productData  Keys: title, category, features, specs, ...
 * @param  string $style        professional|casual|luxury|technical|seo
 * @return string
 */
function generateProductDescription(array $productData, string $style = 'professional'): string
{
    if (!isAiEnabled('content')) {
        return '';
    }

    $cacheKey = 'desc_' . md5($style . json_encode($productData));
    $cached   = checkContentFromCache($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    $styleGuide = match ($style) {
        'casual'      => 'Write in a friendly, conversational tone.',
        'luxury'      => 'Write in an upscale, sophisticated tone emphasising premium quality.',
        'technical'   => 'Write in a precise, technical tone with specifications and details.',
        'seo'         => 'Write SEO-optimised copy with natural keyword integration.',
        default       => 'Write in a professional, clear business tone.',
    };

    $messages = [
        ['role' => 'system', 'content' => buildSystemPrompt('content_generation')],
        ['role' => 'user',   'content' =>
            "Generate a compelling product description for GlobexSky marketplace. $styleGuide "
          . 'Include: headline (1 line), description (80-120 words), key features (5 bullet points). '
          . 'Product data: ' . json_encode($productData)],
    ];

    $result = deepseekRequest($messages, [
        'feature'     => 'content',
        'temperature' => (float)getAiConfig('temperature_content', '0.5'),
        'max_tokens'  => (int)getAiConfig('max_tokens_content', '4096'),
    ]);

    if (!$result['success']) {
        return '';
    }

    $content = $result['content'];
    saveContentToCache(
        $cacheKey,
        $content,
        'product_description',
        (int)($result['tokens']['total_tokens'] ?? 0)
    );

    return $content;
}

/**
 * Generate SEO meta title and description for a page.
 *
 * @return array ['title' => string, 'description' => string]
 */
function generateSeoMeta(array $pageData): array
{
    if (!isAiEnabled('content')) {
        return ['title' => '', 'description' => ''];
    }

    $cacheKey = 'seo_' . md5(json_encode($pageData));
    $cached   = checkContentFromCache($cacheKey);
    if ($cached !== null) {
        $decoded = json_decode($cached, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $messages = [
        ['role' => 'system', 'content' => buildSystemPrompt('content_generation')],
        ['role' => 'user',   'content' =>
            'Generate an SEO meta title (max 60 chars) and meta description (max 160 chars) for this page. '
          . 'Return JSON only: {"title": "...", "description": "..."}. '
          . 'Page data: ' . json_encode($pageData)],
    ];

    $result = deepseekRequest($messages, [
        'feature'     => 'content',
        'temperature' => 0.4,
        'max_tokens'  => 200,
    ]);

    if (!$result['success']) {
        return ['title' => '', 'description' => ''];
    }

    $raw = $result['content'];
    if (preg_match('/\{.*\}/s', $raw, $m)) {
        $parsed = json_decode($m[0], true);
        if (is_array($parsed)) {
            saveContentToCache($cacheKey, json_encode($parsed), 'seo_meta', (int)($result['tokens']['total_tokens'] ?? 0));
            return $parsed;
        }
    }

    return ['title' => '', 'description' => ''];
}

/**
 * Translate content to a target language.
 */
function translateContent(string $content, string $targetLanguage): string
{
    if (!isAiEnabled('content') || empty(trim($content))) {
        return $content;
    }

    $cacheKey = 'trans_' . md5($targetLanguage . $content);
    $cached   = checkContentFromCache($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    $messages = [
        ['role' => 'system', 'content' => 'You are a professional translator. Translate e-commerce content accurately while preserving tone and formatting. Return only the translated text.'],
        ['role' => 'user',   'content' => "Translate to $targetLanguage:\n\n$content"],
    ];

    $result = deepseekRequest($messages, [
        'feature'     => 'content',
        'temperature' => 0.2,
        'max_tokens'  => 2000,
    ]);

    if (!$result['success']) {
        return $content;
    }

    $translated = $result['content'];
    saveContentToCache($cacheKey, $translated, 'translation', (int)($result['tokens']['total_tokens'] ?? 0));

    return $translated;
}

/**
 * Summarise product reviews into key points.
 *
 * @return string  Summary text
 */
function summarizeReviews(int $productId): string
{
    $cacheKey = 'review_summary_' . $productId;
    $cached   = checkContentFromCache($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT rating, comment FROM reviews WHERE product_id = ? ORDER BY created_at DESC LIMIT 50'
        );
        $stmt->execute([$productId]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return '';
    }

    if (empty($reviews)) {
        return '';
    }

    $messages = [
        ['role' => 'system', 'content' => buildSystemPrompt('content_generation')],
        ['role' => 'user',   'content' =>
            'Summarise these product reviews into 3-5 key points (pros, cons, overall sentiment). '
          . 'Be concise and factual. Reviews: ' . json_encode($reviews)],
    ];

    $result = deepseekRequest($messages, [
        'feature'     => 'content',
        'temperature' => 0.4,
        'max_tokens'  => 500,
    ]);

    if (!$result['success']) {
        return '';
    }

    $summary = $result['content'];
    saveContentToCache($cacheKey, $summary, 'summary', (int)($result['tokens']['total_tokens'] ?? 0), '+7 days');

    return $summary;
}

/**
 * Generate email template content.
 *
 * @param  string $type  welcome|order_confirm|shipping|refund|newsletter
 * @param  array  $data  Dynamic data to inject
 * @return string
 */
function generateEmailTemplate(string $type, array $data = []): string
{
    if (!isAiEnabled('content')) {
        return '';
    }

    $cacheKey = 'email_' . md5($type . json_encode(array_keys($data)));
    $cached   = checkContentFromCache($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    $messages = [
        ['role' => 'system', 'content' => buildSystemPrompt('content_generation')],
        ['role' => 'user',   'content' =>
            "Generate a professional HTML email template for GlobexSky marketplace. "
          . "Email type: $type. Available data variables: " . implode(', ', array_keys($data)) . '. '
          . 'Include subject line as the first line prefixed with "Subject: ".'],
    ];

    $result = deepseekRequest($messages, [
        'feature'     => 'content',
        'temperature' => 0.5,
        'max_tokens'  => 1000,
    ]);

    if (!$result['success']) {
        return '';
    }

    $template = $result['content'];
    saveContentToCache($cacheKey, $template, 'email_template', (int)($result['tokens']['total_tokens'] ?? 0), '+30 days');

    return $template;
}

/**
 * Suggest improvements for a product listing.
 *
 * @return array ['suggestions' => string[], 'improved_title' => string, 'score' => int]
 */
function improveProductListing(int $productId): array
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT title, description, price, category_id FROM products WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return ['suggestions' => [], 'improved_title' => '', 'score' => 0];
    }

    if (!$product) {
        return ['suggestions' => [], 'improved_title' => '', 'score' => 0];
    }

    $messages = [
        ['role' => 'system', 'content' => buildSystemPrompt('content_generation')],
        ['role' => 'user',   'content' =>
            'Analyse this product listing and provide improvement suggestions. '
          . 'Return JSON: {"score": 0-100, "improved_title": "...", "suggestions": ["...", "..."]}. '
          . 'Product: ' . json_encode($product)],
    ];

    $result = deepseekRequest($messages, [
        'feature'     => 'content',
        'temperature' => 0.4,
        'max_tokens'  => 600,
    ]);

    if (!$result['success']) {
        return ['suggestions' => [], 'improved_title' => '', 'score' => 0];
    }

    $raw = $result['content'];
    if (preg_match('/\{.*\}/s', $raw, $m)) {
        $parsed = json_decode($m[0], true);
        if (is_array($parsed)) {
            return [
                'suggestions'     => (array)($parsed['suggestions'] ?? []),
                'improved_title'  => (string)($parsed['improved_title'] ?? ''),
                'score'           => (int)($parsed['score'] ?? 0),
            ];
        }
    }

    return ['suggestions' => [], 'improved_title' => '', 'score' => 0];
}

// ── Cache helpers ─────────────────────────────────────────────

/**
 * Check the ai_content_cache for a cached entry.
 *
 * @return string|null  Cached content or null on miss/expired
 */
function checkContentFromCache(string $cacheKey): ?string
{
    try {
        $stmt = getDB()->prepare(
            'SELECT generated_content FROM ai_content_cache
             WHERE cache_key = ? AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1'
        );
        $stmt->execute([$cacheKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // Increment hit counter
            getDB()->prepare(
                'UPDATE ai_content_cache SET hit_count = hit_count + 1 WHERE cache_key = ?'
            )->execute([$cacheKey]);
            return $row['generated_content'];
        }
    } catch (Throwable $e) { /* cache miss */ }
    return null;
}

/**
 * Persist generated content to ai_content_cache.
 */
function saveContentToCache(
    string $cacheKey,
    string $content,
    string $contentType,
    int $tokensUsed = 0,
    string $expiresIn = '+24 hours'
): void {
    $validTypes = ['product_description', 'seo_meta', 'email_template', 'translation', 'summary'];
    if (!in_array($contentType, $validTypes, true)) {
        $contentType = 'summary';
    }

    $inputHash = md5($cacheKey);
    $expiresAt = date('Y-m-d H:i:s', strtotime($expiresIn));

    try {
        getDB()->prepare(
            'INSERT INTO ai_content_cache
             (cache_key, content_type, input_hash, generated_content, tokens_used, expires_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               generated_content = VALUES(generated_content),
               tokens_used = VALUES(tokens_used),
               expires_at = VALUES(expires_at)'
        )->execute([$cacheKey, $contentType, $inputHash, $content, $tokensUsed, $expiresAt]);
    } catch (Throwable $e) {
        error_log('saveContentToCache error: ' . $e->getMessage());
    }
}
