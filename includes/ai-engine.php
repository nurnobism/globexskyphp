<?php
/**
 * includes/ai-engine.php — DeepSeek AI Core Engine
 *
 * Pure cURL-based DeepSeek API client (no external dependencies).
 * Compatible with Namecheap shared hosting (PHP 8.x).
 *
 * Functions:
 *   deepseekRequest($messages, $options)  — Call DeepSeek chat completions
 *   getAiConfig($key)                     — Read config from ai_config table
 *   setAiConfig($key, $value, $userId)    — Update config value
 *   isAiEnabled($feature)                 — Check feature toggle
 *   checkTokenLimit($userId)              — Check user daily budget
 *   logAiUsage($userId, $feature, ...)    — Log token usage
 *   getAiUsageStats($userId, $period)     — Get usage statistics
 *   buildSystemPrompt($context)           — Build context-aware system prompt
 */

if (!defined('AI_ENGINE_LOADED')) {
    define('AI_ENGINE_LOADED', true);
}

// ── Config cache ──────────────────────────────────────────────
$_aiConfigCache = [];

/**
 * Get a config value from ai_config table (with in-request cache).
 */
function getAiConfig(string $key, string $default = ''): string
{
    global $_aiConfigCache;

    if (isset($_aiConfigCache[$key])) {
        return $_aiConfigCache[$key];
    }

    // Fall back to env variable if DB not available
    $envMap = [
        'deepseek_api_key'  => getenv('DEEPSEEK_API_KEY') ?: '',
        'deepseek_base_url' => getenv('DEEPSEEK_BASE_URL') ?: 'https://api.deepseek.com/v1',
        'deepseek_model'    => getenv('DEEPSEEK_MODEL') ?: 'deepseek-chat',
        'ai_enabled'        => getenv('AI_ENABLED') !== false ? (getenv('AI_ENABLED') ?: '1') : '1',
    ];

    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT config_value FROM ai_config WHERE config_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $value = ($row !== false) ? (string)$row['config_value'] : ($envMap[$key] ?? $default);
    } catch (Throwable $e) {
        $value = $envMap[$key] ?? $default;
    }

    $_aiConfigCache[$key] = $value;
    return $value;
}

/**
 * Update a config value in ai_config table.
 */
function setAiConfig(string $key, string $value, int $updatedBy = 0): bool
{
    global $_aiConfigCache;
    try {
        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO ai_config (config_key, config_value, updated_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_by = VALUES(updated_by)'
        );
        $ok = $stmt->execute([$key, $value, $updatedBy ?: null]);
        if ($ok) {
            $_aiConfigCache[$key] = $value;
        }
        return $ok;
    } catch (Throwable $e) {
        error_log('setAiConfig error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check whether an AI feature is enabled.
 * Feature names: 'general', 'chatbot', 'recommendations', 'fraud', 'search', 'content'
 */
function isAiEnabled(string $feature = 'general'): bool
{
    if (getAiConfig('ai_enabled', '1') !== '1') {
        return false;
    }
    if ($feature === 'general') {
        return true;
    }
    $key = 'ai_' . $feature . '_enabled';
    return getAiConfig($key, '1') === '1';
}

/**
 * Check if a user has remaining token budget for today.
 * Returns ['allowed' => bool, 'used' => int, 'limit' => int, 'remaining' => int]
 */
function checkTokenLimit(int $userId): array
{
    $limit = (int)getAiConfig('daily_token_limit_free', '5000');

    // Determine user plan for higher limits
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT plan_type FROM supplier_plans WHERE user_id = ? AND status = 'active' LIMIT 1"
        );
        $stmt->execute([$userId]);
        $plan = $stmt->fetchColumn();
        if ($plan === 'enterprise') {
            $limit = (int)getAiConfig('daily_token_limit_enterprise', '500000');
        } elseif ($plan === 'pro') {
            $limit = (int)getAiConfig('daily_token_limit_pro', '50000');
        }
    } catch (Throwable $e) {
        // table may not exist yet — use free limit
    }

    $used = 0;
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(tokens_input + tokens_output), 0)
             FROM ai_usage
             WHERE user_id = ? AND DATE(created_at) = CURDATE()'
        );
        $stmt->execute([$userId]);
        $used = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        // ai_usage table not yet created
    }

    $remaining = max(0, $limit - $used);
    return [
        'allowed'   => $remaining > 0,
        'used'      => $used,
        'limit'     => $limit,
        'remaining' => $remaining,
    ];
}

/**
 * Log AI token usage for billing/quota tracking.
 */
function logAiUsage(
    ?int $userId,
    string $feature,
    int $tokensIn,
    int $tokensOut,
    string $model = 'deepseek-chat'
): void {
    // Cost estimate: ~$0.14/M input + $0.28/M output for deepseek-chat
    $costUsd = round(($tokensIn * 0.00000014) + ($tokensOut * 0.00000028), 8);
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'INSERT INTO ai_usage (user_id, feature, tokens_input, tokens_output, cost_usd, model)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId ?: null, $feature, $tokensIn, $tokensOut, $costUsd, $model]);
    } catch (Throwable $e) {
        error_log('logAiUsage error: ' . $e->getMessage());
    }
}

/**
 * Get AI usage statistics for a user.
 * Period: 'today', 'week', 'month', 'all'
 */
function getAiUsageStats(?int $userId, string $period = 'today'): array
{
    $dateFilter = match ($period) {
        'today' => 'AND DATE(created_at) = CURDATE()',
        'week'  => 'AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
        'month' => 'AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
        default => '',
    };

    $userFilter = $userId ? 'AND user_id = ?' : '';
    $params     = $userId ? [$userId] : [];

    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT
               COALESCE(SUM(tokens_input), 0)  AS tokens_input,
               COALESCE(SUM(tokens_output), 0) AS tokens_output,
               COALESCE(SUM(cost_usd), 0)      AS cost_usd,
               COUNT(*)                         AS requests
             FROM ai_usage
             WHERE 1=1 $userFilter $dateFilter"
        );
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Build a context-aware system prompt for GlobexSky AI.
 */
function buildSystemPrompt(string $context): string
{
    $base = 'You are GlobexSky AI Assistant, powered by DeepSeek. GlobexSky is a B2B/B2C '
          . 'e-commerce platform connecting global buyers with suppliers, primarily from China. '
          . 'You help with product sourcing, recommendations, trade inquiries, and support. '
          . 'Always be professional, concise, and helpful.';

    return match ($context) {
        'product_search' =>
            $base . ' The user is searching for products. Help them find what they need by '
          . 'understanding their natural language query, extracting key attributes (category, '
          . 'features, price range, quantity), and suggesting the best matching products.',

        'sourcing' =>
            $base . ' The user wants to source products from global suppliers. Help them with: '
          . 'identifying reliable suppliers, estimating costs (product + shipping + duties), '
          . 'understanding MOQ requirements, evaluating quality certifications, and negotiation tips.',

        'support' =>
            $base . ' The user needs customer support. Help them with order tracking, return policies, '
          . 'payment issues, account questions, and dispute resolution. Be empathetic and solution-focused.',

        'fraud_review' =>
            'You are a fraud detection specialist for GlobexSky marketplace. Analyze the provided '
          . 'transaction data objectively and return a structured risk assessment. '
          . 'Be precise and conservative — when in doubt, flag for human review. '
          . 'Return valid JSON only with keys: risk_score (0-100), risk_level (low/medium/high/critical), '
          . 'factors (array of risk factor strings), recommendation (approve/review/hold/block), analysis (string).',

        'admin' =>
            $base . ' You are assisting a GlobexSky administrator. Provide detailed analytics insights, '
          . 'operational recommendations, and platform management guidance.',

        'content_generation' =>
            'You are a professional e-commerce copywriter for GlobexSky marketplace. '
          . 'Generate high-quality, SEO-friendly product content that is accurate and compelling. '
          . 'Follow e-commerce best practices and GlobexSky brand voice.',

        default => $base,
    };
}

/**
 * Make a request to the DeepSeek Chat Completions API.
 *
 * @param  array  $messages  Array of ['role' => '...', 'content' => '...']
 * @param  array  $options   Optional overrides: model, temperature, max_tokens, user_id, feature
 * @return array  ['success' => bool, 'content' => string, 'tokens' => [...], 'error' => string|null]
 */
function deepseekRequest(array $messages, array $options = []): array
{
    $apiKey  = getAiConfig('deepseek_api_key', getenv('DEEPSEEK_API_KEY') ?: '');
    $baseUrl = rtrim(getAiConfig('deepseek_base_url', 'https://api.deepseek.com/v1'), '/');
    $model   = $options['model'] ?? getAiConfig('deepseek_model', 'deepseek-chat');
    $temp    = isset($options['temperature']) ? (float)$options['temperature'] : 0.7;
    $maxTok  = (int)($options['max_tokens'] ?? getAiConfig('max_tokens_chat', '2048'));

    if (empty($apiKey)) {
        return [
            'success' => false,
            'content' => '',
            'tokens'  => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            'error'   => 'DeepSeek API key not configured',
        ];
    }

    $payload = json_encode([
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => $temp,
        'max_tokens'  => $maxTok,
    ]);

    $ch = curl_init($baseUrl . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log('DeepSeek cURL error: ' . $curlErr);
        return ['success' => false, 'content' => '', 'tokens' => [], 'error' => 'Network error'];
    }

    $data = json_decode((string)$result, true);

    if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
        $errMsg = $data['error']['message'] ?? ('HTTP ' . $httpCode);
        error_log('DeepSeek API error: ' . $errMsg);
        return ['success' => false, 'content' => '', 'tokens' => [], 'error' => $errMsg];
    }

    $content = $data['choices'][0]['message']['content'];
    $tokens  = $data['usage'] ?? [
        'prompt_tokens'     => 0,
        'completion_tokens' => 0,
        'total_tokens'      => 0,
    ];

    // Log usage
    $userId  = $options['user_id'] ?? null;
    $feature = $options['feature'] ?? 'general';
    logAiUsage(
        $userId ? (int)$userId : null,
        $feature,
        (int)($tokens['prompt_tokens'] ?? 0),
        (int)($tokens['completion_tokens'] ?? 0),
        $model
    );

    return [
        'success' => true,
        'content' => $content,
        'tokens'  => $tokens,
        'error'   => null,
    ];
}
