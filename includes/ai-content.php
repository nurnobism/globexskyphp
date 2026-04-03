<?php
/**
 * includes/ai-content.php — AI Content Generation Helpers (Phase 8)
 */

require_once __DIR__ . '/deepseek.php';

/**
 * Generate AI product description.
 */
function generateProductDescription(array $productData, string $style = 'professional', string $language = 'en'): string {
    $ai     = getDeepSeek();
    $source = json_encode($productData) . " Style: $style";
    return $ai->generateContent('product_description', $source, $language, (int)($_SESSION['user_id'] ?? 0));
}

/**
 * Generate SEO-optimized product title.
 */
function generateSEOTitle(array $productData): string {
    $ai = getDeepSeek();
    return $ai->generateContent('seo_title', json_encode($productData), 'en', (int)($_SESSION['user_id'] ?? 0));
}

/**
 * Generate SEO meta description.
 */
function generateSEOMeta(array $productData): string {
    $ai = getDeepSeek();
    return $ai->generateContent('seo_meta', json_encode($productData), 'en', (int)($_SESSION['user_id'] ?? 0));
}

/**
 * Generate ad copy for a product.
 */
function generateAdCopy(array $productData, string $platform = 'google'): array {
    $ai       = getDeepSeek();
    $source   = json_encode($productData) . " Platform: $platform";
    $response = $ai->generateContent('ad_copy', $source, 'en', (int)($_SESSION['user_id'] ?? 0));
    $decoded  = json_decode($response, true);
    return is_array($decoded) ? $decoded : ['headline' => '', 'body' => $response, 'cta' => ''];
}

/**
 * Summarize product reviews with pros/cons/verdict.
 */
function summarizeReviews(int $productId): array {
    $ai = getDeepSeek();
    return $ai->summarizeReviews($productId);
}

/**
 * Translate content from one language to another.
 */
function translateContent(string $text, string $fromLang, string $toLang): string {
    $ai     = getDeepSeek();
    $source = "From $fromLang: $text";
    return $ai->generateContent('translation', $source, $toLang, (int)($_SESSION['user_id'] ?? 0));
}

/**
 * Generate email template content.
 */
function generateEmailTemplate(string $type, array $context): array {
    $ai       = getDeepSeek();
    $source   = "Type: $type. Context: " . json_encode($context);
    $response = $ai->generateContent('email_template', $source, 'en', (int)($_SESSION['user_id'] ?? 0));
    $decoded  = json_decode($response, true);
    return is_array($decoded) ? $decoded : ['subject' => '', 'body' => $response];
}

/**
 * Improve existing text based on instructions.
 */
function improveText(string $text, string $instructions = 'Make it more professional and compelling.'): string {
    $ai = getDeepSeek();
    return $ai->chat(
        "Improve this text with these instructions: $instructions\n\nOriginal text:\n$text\n\nReturn only the improved text.",
        'You are a professional copywriter.'
    );
}

/**
 * Check content quality and provide suggestions.
 */
function checkContentQuality(string $text): array {
    $ai       = getDeepSeek();
    $response = $ai->chat(
        "Rate this content and provide improvement suggestions:\n$text\n"
        . "Return JSON: {score:1-10, strengths:[string], weaknesses:[string], suggestions:[string]}",
        'You are a content quality analyst. Return valid JSON only.'
    );
    $result = json_decode($response, true);
    return is_array($result) ? $result : ['score' => 5, 'strengths' => [], 'weaknesses' => [], 'suggestions' => []];
}

/**
 * Get content generation history for a user.
 */
function getContentHistory(int $userId, string $type = '', int $limit = 20): array {
    $db = getDB();
    try {
        if ($type) {
            $stmt = $db->prepare(
                "SELECT * FROM ai_content_generations WHERE user_id = ? AND content_type = ? ORDER BY created_at DESC LIMIT ?"
            );
            $stmt->execute([$userId, $type, $limit]);
        } else {
            $stmt = $db->prepare(
                "SELECT * FROM ai_content_generations WHERE user_id = ? ORDER BY created_at DESC LIMIT ?"
            );
            $stmt->execute([$userId, $limit]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Approve a generated content item.
 */
function approveContent(int $contentId, int $userId): bool {
    $db = getDB();
    try {
        $db->prepare("UPDATE ai_content_generations SET is_approved = 1 WHERE id = ? AND user_id = ?")
           ->execute([$contentId, $userId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
