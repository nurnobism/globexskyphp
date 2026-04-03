<?php
// includes/deepseek.php — DeepSeek AI Engine (Phase 8)

class DeepSeek {
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private int $maxTokens;
    private float $temperature;
    private int $timeout;
    private ?PDO $db = null;

    public function __construct(array $config) {
        $this->apiKey      = $config['api_key']     ?? '';
        $this->baseUrl     = rtrim($config['base_url'] ?? 'https://api.deepseek.com', '/');
        $this->model       = $config['model']       ?? 'deepseek-chat';
        $this->maxTokens   = (int)($config['max_tokens']  ?? 2000);
        $this->temperature = (float)($config['temperature'] ?? 0.3);
        $this->timeout     = (int)($config['timeout']     ?? 30);
    }

    // ── Core: send a single prompt ────────────────────────────────────────────

    public function chat(string $prompt, string $systemMessage = 'You are a helpful assistant for GlobexSky marketplace.', array $options = []): string {
        $messages = [
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user',   'content' => $prompt],
        ];
        $result = $this->callApi($messages, $options);
        return $result['content'] ?? '';
    }

    // ── Conversation with history ─────────────────────────────────────────────

    public function chatWithHistory(int $conversationId, string $userMessage): string {
        $db = $this->getDb();
        $start = microtime(true);

        // Load previous messages
        $stmt = $db->prepare('SELECT role, content FROM ai_messages WHERE conversation_id = ? ORDER BY created_at ASC LIMIT 40');
        $stmt->execute([$conversationId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get conversation context
        $convStmt = $db->prepare('SELECT * FROM ai_conversations WHERE id = ?');
        $convStmt->execute([$conversationId]);
        $conv = $convStmt->fetch(PDO::FETCH_ASSOC);

        $systemMsg = $this->buildSystemPrompt($conv['context_type'] ?? 'general');
        $messages  = [['role' => 'system', 'content' => $systemMsg]];
        foreach ($history as $h) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $result = $this->callApi($messages, []);
        $responseText = $result['content'] ?? '';
        $elapsed = (int)((microtime(true) - $start) * 1000);

        // Save user message
        $db->prepare('INSERT INTO ai_messages (conversation_id, role, content, model, response_time_ms) VALUES (?,?,?,?,?)')
           ->execute([$conversationId, 'user', $userMessage, $this->model, 0]);

        // Save assistant response
        $tokens = $result['tokens'] ?? 0;
        $db->prepare('INSERT INTO ai_messages (conversation_id, role, content, tokens_used, model, response_time_ms) VALUES (?,?,?,?,?,?)')
           ->execute([$conversationId, 'assistant', $responseText, $tokens, $this->model, $elapsed]);

        // Update conversation
        $db->prepare('UPDATE ai_conversations SET message_count = message_count + 2, updated_at = NOW() WHERE id = ?')
           ->execute([$conversationId]);

        // Log usage
        $userId = $conv['user_id'] ?? null;
        $inputTokens  = $result['input_tokens']  ?? 0;
        $outputTokens = $result['output_tokens'] ?? 0;
        $this->logUsage($userId, 'chatbot', $inputTokens, $outputTokens, $result['status'] ?? 'success', $elapsed);

        return $responseText;
    }

    // ── Product Recommendations ───────────────────────────────────────────────

    public function getRecommendations(int $userId, string $type = 'personalized', array $context = []): array {
        $db = $this->getDb();
        $start = microtime(true);

        // Build context from user history
        try {
            $ordStmt = $db->prepare(
                "SELECT p.id, p.name, p.category_id FROM order_items oi
                 JOIN orders o ON o.id = oi.order_id
                 JOIN products p ON p.id = oi.product_id
                 WHERE o.buyer_id = ? ORDER BY o.placed_at DESC LIMIT 20"
            );
            $ordStmt->execute([$userId]);
            $history = $ordStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $history = [];
        }

        try {
            $prodStmt = $db->prepare(
                "SELECT id, name, category_id, price FROM products WHERE status='active' ORDER BY rating DESC, view_count DESC LIMIT 30"
            );
            $prodStmt->execute();
            $candidates = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $candidates = [];
        }

        $prompt = "User purchase history: " . json_encode(array_slice($history, 0, 10))
                . "\nCandidate products: " . json_encode(array_slice($candidates, 0, 20))
                . "\nRecommendation type: $type"
                . "\nReturn JSON array of up to 8 objects: [{product_id:int, score:float 0-1, reason:string}]";

        $response = $this->chat($prompt, 'You are a product recommendation engine. Return only valid JSON array.');
        $elapsed  = (int)((microtime(true) - $start) * 1000);

        $picks = json_decode($response, true);
        $saved = [];
        if (is_array($picks)) {
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            foreach ($picks as $pick) {
                if (empty($pick['product_id'])) continue;
                try {
                    $db->prepare(
                        "INSERT INTO ai_recommendations (user_id, recommendation_type, recommended_product_id, score, reason, expires_at)
                         VALUES (?,?,?,?,?,?)"
                    )->execute([
                        $userId, $type, (int)$pick['product_id'],
                        min(1.0, max(0.0, (float)($pick['score'] ?? 0.5))),
                        $pick['reason'] ?? '',
                        $expires,
                    ]);
                    $saved[] = $pick;
                } catch (PDOException $e) { /* ignore */ }
            }
        }

        $this->logUsage($userId, 'recommendations', 0, 0, 'success', $elapsed);
        return $saved;
    }

    // ── Fraud Detection ───────────────────────────────────────────────────────

    public function detectFraud(string $entityType, int $entityId, array $data): array {
        $start = microtime(true);
        $prompt = "Analyze the following $entityType for fraud signals:\n"
                . json_encode($data)
                . "\nReturn JSON: {risk_score:0-100, risk_level:\"low|medium|high|critical\", factors:[\"...\"], reasoning:\"...\", action:\"none|flag|hold|block|notify_admin\"}";

        $response = $this->chat($prompt, 'You are a fraud detection AI for a global B2B marketplace. Analyze entities and return structured JSON only.');
        $elapsed  = (int)((microtime(true) - $start) * 1000);

        $result = json_decode($response, true);
        if (!is_array($result)) {
            $result = ['risk_score' => 0, 'risk_level' => 'low', 'factors' => [], 'reasoning' => 'Analysis unavailable', 'action' => 'none'];
        }

        $riskScore = min(100, max(0, (float)($result['risk_score'] ?? 0)));
        $riskLevel = in_array($result['risk_level'] ?? '', ['low','medium','high','critical']) ? $result['risk_level'] : 'low';
        $action    = in_array($result['action'] ?? '', ['none','flag','hold','block','notify_admin']) ? $result['action'] : 'none';

        try {
            $db = $this->getDb();
            $db->prepare(
                "INSERT INTO ai_fraud_logs (entity_type, entity_id, risk_score, risk_level, factors, ai_reasoning, action_taken)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([
                $entityType, $entityId, $riskScore, $riskLevel,
                json_encode($result['factors'] ?? []),
                $result['reasoning'] ?? '',
                $action,
            ]);
        } catch (PDOException $e) { /* table may not exist yet */ }

        $this->logUsage(null, 'fraud_detection', 0, 0, 'success', $elapsed);

        return [
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'factors'    => $result['factors'] ?? [],
            'reasoning'  => $result['reasoning'] ?? '',
            'action'     => $action,
        ];
    }

    // ── Search Enhancement ────────────────────────────────────────────────────

    public function enhanceSearch(string $query, int $userId = 0): array {
        $start = microtime(true);
        $prompt = "Natural language product search query: \"$query\"\n"
                . "Return JSON: {intent:string, enhanced_query:string, filters:{category:string|null, min_price:number|null, max_price:number|null, location:string|null, min_order:number|null}, keywords:[string]}";

        $response = $this->chat($prompt, 'You are a B2B marketplace search engine. Extract structured search parameters from natural language. Return valid JSON only.');
        $elapsed  = (int)((microtime(true) - $start) * 1000);

        $result = json_decode($response, true);
        if (!is_array($result)) {
            $result = ['intent' => 'product_search', 'enhanced_query' => $query, 'filters' => [], 'keywords' => [$query]];
        }

        try {
            $db = $this->getDb();
            $db->prepare(
                "INSERT INTO ai_search_logs (user_id, original_query, enhanced_query, intent, filters_suggested, response_time_ms)
                 VALUES (?,?,?,?,?,?)"
            )->execute([
                $userId ?: null,
                $query,
                $result['enhanced_query'] ?? $query,
                $result['intent'] ?? '',
                json_encode($result['filters'] ?? []),
                $elapsed,
            ]);
        } catch (PDOException $e) { /* ignore */ }

        $this->logUsage($userId ?: null, 'ai_search', 0, 0, 'success', $elapsed);
        return $result;
    }

    // ── Content Generation ────────────────────────────────────────────────────

    public function generateContent(string $type, string $sourceText, string $language = 'en', int $userId = 0): string {
        $start = microtime(true);

        $prompts = [
            'product_description' => "Write a compelling product description for: $sourceText\nLanguage: $language\nReturn only the description (150-200 words).",
            'seo_title'           => "Write an SEO-optimized product title (max 70 chars) for: $sourceText\nReturn only the title.",
            'seo_meta'            => "Write an SEO meta description (max 160 chars) for: $sourceText\nReturn only the meta description.",
            'review_summary'      => "Summarize these product reviews into pros, cons, and verdict: $sourceText\nReturn JSON: {pros:[],cons:[],verdict:string}",
            'ad_copy'             => "Write compelling ad copy for: $sourceText\nInclude headline, body (2 sentences), CTA. Return JSON: {headline:string,body:string,cta:string}",
            'email_template'      => "Write a professional email for: $sourceText\nReturn JSON: {subject:string,body:string}",
            'translation'         => "Translate to $language: $sourceText\nReturn only the translation.",
        ];

        $prompt    = $prompts[$type] ?? "Process this text: $sourceText";
        $response  = $this->chat($prompt, 'You are a professional e-commerce copywriter and translator.');
        $elapsed   = (int)((microtime(true) - $start) * 1000);

        try {
            $db = $this->getDb();
            $db->prepare(
                "INSERT INTO ai_content_generations (user_id, content_type, source_text, generated_text, language, tokens_used)
                 VALUES (?,?,?,?,?,?)"
            )->execute([$userId ?: 0, $type, $sourceText, $response, $language, 0]);
        } catch (PDOException $e) { /* ignore */ }

        $this->logUsage($userId ?: null, 'content_generation', 0, 0, 'success', $elapsed);
        return $response;
    }

    // ── Supplier Analysis ─────────────────────────────────────────────────────

    public function analyzeSupplier(int $supplierId): array {
        $db    = $this->getDb();
        $start = microtime(true);

        // Gather supplier metrics
        $data = ['supplier_id' => $supplierId];
        try {
            $stmt = $db->prepare("SELECT company_name, created_at, is_verified FROM suppliers WHERE id = ?");
            $stmt->execute([$supplierId]);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($supplier) $data = array_merge($data, $supplier);

            $ordStmt = $db->prepare(
                "SELECT COUNT(*) as total_orders, AVG(DATEDIFF(delivered_at, placed_at)) as avg_delivery_days,
                        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled_orders
                 FROM orders WHERE supplier_id = ? AND placed_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)"
            );
            $ordStmt->execute([$supplierId]);
            $orderStats = $ordStmt->fetch(PDO::FETCH_ASSOC);
            if ($orderStats) $data['order_stats'] = $orderStats;
        } catch (PDOException $e) { /* ignore */ }

        $prompt = "Analyze this supplier and generate trust scores:\n" . json_encode($data)
                . "\nReturn JSON: {overall_score:0-100, reliability_score:0-100, quality_score:0-100, communication_score:0-100, delivery_score:0-100, factors:[string], summary:string}";

        $response = $this->chat($prompt, 'You are a supplier verification AI for a global B2B marketplace. Return valid JSON only.');
        $elapsed  = (int)((microtime(true) - $start) * 1000);

        $result = json_decode($response, true);
        if (!is_array($result)) {
            $result = ['overall_score' => 50, 'reliability_score' => 50, 'quality_score' => 50,
                       'communication_score' => 50, 'delivery_score' => 50, 'factors' => [], 'summary' => ''];
        }

        try {
            $db->prepare(
                "INSERT INTO ai_supplier_scores (supplier_id, overall_score, reliability_score, quality_score, communication_score, delivery_score, factors, ai_summary)
                 VALUES (?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   overall_score=VALUES(overall_score), reliability_score=VALUES(reliability_score),
                   quality_score=VALUES(quality_score), communication_score=VALUES(communication_score),
                   delivery_score=VALUES(delivery_score), factors=VALUES(factors), ai_summary=VALUES(ai_summary),
                   calculated_at=NOW()"
            )->execute([
                $supplierId,
                min(100, max(0, (float)($result['overall_score']       ?? 50))),
                min(100, max(0, (float)($result['reliability_score']   ?? 50))),
                min(100, max(0, (float)($result['quality_score']       ?? 50))),
                min(100, max(0, (float)($result['communication_score'] ?? 50))),
                min(100, max(0, (float)($result['delivery_score']      ?? 50))),
                json_encode($result['factors'] ?? []),
                $result['summary'] ?? '',
            ]);
        } catch (PDOException $e) { /* ignore */ }

        $this->logUsage(null, 'supplier_analysis', 0, 0, 'success', $elapsed);
        return $result;
    }

    // ── Sales Data Analysis ───────────────────────────────────────────────────

    public function analyzeSalesData(int $supplierId, string $period = '30days'): string {
        $db    = $this->getDb();
        $start = microtime(true);

        $interval = match ($period) {
            '7days'  => 'INTERVAL 7 DAY',
            '90days' => 'INTERVAL 90 DAY',
            default  => 'INTERVAL 30 DAY',
        };

        $data = [];
        try {
            $stmt = $db->prepare(
                "SELECT DATE(o.placed_at) as date, COUNT(*) as orders, SUM(o.total_amount) as revenue
                 FROM orders o WHERE o.supplier_id = ? AND o.placed_at >= DATE_SUB(NOW(), $interval)
                 GROUP BY DATE(o.placed_at) ORDER BY date ASC"
            );
            $stmt->execute([$supplierId]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { /* ignore */ }

        $prompt = "Analyze these sales metrics and provide insights:\n" . json_encode($data)
                . "\nProvide: trends, peak periods, anomalies, 3 actionable recommendations.";

        $response = $this->chat($prompt, 'You are a business intelligence analyst specializing in B2B marketplace analytics.');
        $this->logUsage(null, 'sales_analysis', 0, 0, 'success', (int)((microtime(true) - $start) * 1000));
        return $response;
    }

    // ── Review Summarizer ─────────────────────────────────────────────────────

    public function summarizeReviews(int $productId): array {
        $db    = $this->getDb();
        $start = microtime(true);

        try {
            $stmt = $db->prepare("SELECT rating, comment FROM reviews WHERE product_id = ? AND status='approved' ORDER BY created_at DESC LIMIT 50");
            $stmt->execute([$productId]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $reviews = [];
        }

        if (empty($reviews)) {
            return ['pros' => [], 'cons' => [], 'verdict' => 'No reviews yet.', 'avg_rating' => 0];
        }

        $reviewText = json_encode($reviews);
        $avgRating  = round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1);

        $response = $this->chat(
            "Summarize these product reviews: $reviewText\nReturn JSON: {pros:[string],cons:[string],verdict:string}",
            'You are a product review analyst. Return valid JSON only.'
        );

        $result = json_decode($response, true);
        if (!is_array($result)) {
            $result = ['pros' => [], 'cons' => [], 'verdict' => 'Unable to summarize reviews.'];
        }
        $result['avg_rating'] = $avgRating;

        $this->logUsage(null, 'review_summary', 0, 0, 'success', (int)((microtime(true) - $start) * 1000));
        return $result;
    }

    // ── Translation ───────────────────────────────────────────────────────────

    public function translateText(string $text, string $targetLang): string {
        $response = $this->chat(
            "Translate to $targetLang:\n$text\nReturn only the translation.",
            'You are a professional translator.'
        );
        $this->logUsage(null, 'translation', 0, 0, 'success', 0);
        return $response;
    }

    // ── Support Ticket Classifier ─────────────────────────────────────────────

    public function classifyTicket(string $ticketText): array {
        $prompt = "Classify this support ticket:\n$ticketText\n"
                . "Return JSON: {category:string, priority:\"low|medium|high|urgent\", sentiment:\"positive|neutral|negative\", summary:string}";
        $response = $this->chat($prompt, 'You are a customer support AI. Classify tickets and return valid JSON only.');
        $result   = json_decode($response, true);
        return is_array($result) ? $result : ['category' => 'general', 'priority' => 'medium', 'sentiment' => 'neutral', 'summary' => $ticketText];
    }

    // ── Legacy compatibility methods ──────────────────────────────────────────

    public function smartSearch(string $query): array {
        return $this->enhanceSearch($query);
    }

    public function analyzeFraud(array $transaction): array {
        return $this->detectFraud('transaction', (int)($transaction['id'] ?? 0), $transaction);
    }

    public function generateInsights(array $salesData): string {
        return $this->chat(
            "Analyze: " . json_encode($salesData) . "\nProvide 3-5 business insights.",
            'You are a business intelligence analyst.'
        );
    }

    public function generateMarketingContent(array $product): string {
        return $this->generateContent('product_description', json_encode($product));
    }

    public function explainAnalytics(array $chartData): string {
        return $this->chat(
            "Explain these analytics: " . json_encode($chartData),
            'You are a data analyst who explains metrics clearly.'
        );
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function callApi(array $messages, array $options): array {
        if (empty($this->apiKey)) {
            return ['content' => 'AI features require configuration. Please set DEEPSEEK_API_KEY.', 'tokens' => 0, 'status' => 'error'];
        }

        $payload = [
            'model'       => $options['model']       ?? $this->model,
            'messages'    => $messages,
            'temperature' => $options['temperature'] ?? $this->temperature,
            'max_tokens'  => $options['max_tokens']  ?? $this->maxTokens,
        ];

        if (!empty($options['json_mode'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $maxRetries = 3;
        $lastError  = '';

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init($this->baseUrl . '/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                ],
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $raw      = curl_exec($ch);
            $curlErr  = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlErr) {
                $lastError = "cURL error: $curlErr";
                if ($attempt < $maxRetries) {
                    sleep((int)pow(2, $attempt - 1));
                    continue;
                }
                error_log("DeepSeek API failed after $maxRetries retries: $lastError");
                return ['content' => '', 'tokens' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'status' => 'error'];
            }

            $data = json_decode((string)$raw, true);

            if ($httpCode === 429) {
                $lastError = 'Rate limited';
                if ($attempt < $maxRetries) {
                    sleep((int)pow(2, $attempt));
                    continue;
                }
                return ['content' => '', 'tokens' => 0, 'status' => 'rate_limited'];
            }

            if ($httpCode >= 500) {
                $lastError = "Server error $httpCode";
                if ($attempt < $maxRetries) {
                    sleep((int)pow(2, $attempt - 1));
                    continue;
                }
                return ['content' => '', 'tokens' => 0, 'status' => 'error'];
            }

            $content      = $data['choices'][0]['message']['content'] ?? '';
            $inputTokens  = $data['usage']['prompt_tokens']     ?? 0;
            $outputTokens = $data['usage']['completion_tokens'] ?? 0;

            return [
                'content'       => $content,
                'tokens'        => $inputTokens + $outputTokens,
                'input_tokens'  => $inputTokens,
                'output_tokens' => $outputTokens,
                'status'        => 'success',
            ];
        }

        return ['content' => '', 'tokens' => 0, 'status' => 'error'];
    }

    private function logUsage(?int $userId, string $feature, int $inputTokens, int $outputTokens, string $status, int $responseTimeMs = 0, string $errorMsg = ''): void {
        try {
            $db = $this->getDb();
            $total = $inputTokens + $outputTokens;
            $cost  = $this->estimateCost($inputTokens, $outputTokens);
            $db->prepare(
                "INSERT INTO ai_usage (user_id, feature, model, input_tokens, output_tokens, total_tokens, cost_usd, response_time_ms, status, error_message)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            )->execute([$userId, $feature, $this->model, $inputTokens, $outputTokens, $total, $cost, $responseTimeMs, $status, $errorMsg ?: null]);
        } catch (PDOException $e) {
            error_log("AI usage log failed: " . $e->getMessage());
        }
    }

    private function estimateCost(int $inputTokens, int $outputTokens): float {
        // DeepSeek-chat pricing: ~$0.14/1M input, ~$0.28/1M output
        return ($inputTokens * 0.00000014) + ($outputTokens * 0.00000028);
    }

    private function buildSystemPrompt(string $context): string {
        $base = 'You are GlobexBot, the AI assistant for GlobexSky — a global B2B marketplace. Be concise, professional, and helpful.';
        $contextPrompts = [
            'product'   => ' Help users understand product specifications, quality, sourcing, and pricing.',
            'order'     => ' Help users track orders, understand shipping, and resolve order issues.',
            'support'   => ' Help users resolve platform issues, account problems, and disputes.',
            'sourcing'  => ' Help buyers find suppliers, evaluate RFQs, and source products efficiently.',
            'fraud'     => ' You are analyzing entities for fraud. Return structured analysis.',
            'analytics' => ' Provide data-driven insights and actionable business recommendations.',
        ];
        return $base . ($contextPrompts[$context] ?? '');
    }

    private function getDb(): PDO {
        if ($this->db === null) {
            if (function_exists('getDB')) {
                $this->db = getDB();
            } else {
                $cfg = require __DIR__ . '/../config/database.php';
                $dsn = "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset=utf8mb4";
                $this->db = new PDO($dsn, $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            }
        }
        return $this->db;
    }
}

function getDeepSeek(): DeepSeek {
    static $instance = null;
    if ($instance === null) {
        $config   = require __DIR__ . '/../config/ai.php';
        $instance = new DeepSeek($config['deepseek']);
    }
    return $instance;
}
