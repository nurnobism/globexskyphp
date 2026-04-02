<?php
// includes/deepseek.php — DeepSeek AI Helper Class

class DeepSeek {
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private int $maxTokens;
    private float $temperature;
    private int $timeout;

    public function __construct(array $config) {
        $this->apiKey      = $config['api_key'];
        $this->baseUrl     = rtrim($config['base_url'], '/');
        $this->model       = $config['model'] ?? 'deepseek-chat';
        $this->maxTokens   = $config['max_tokens'] ?? 2000;
        $this->temperature = $config['temperature'] ?? 0.3;
        $this->timeout     = $config['timeout'] ?? 30;
    }

    public function chat(string $prompt, string $systemMessage = 'You are a helpful assistant for Globex Sky marketplace.', array $options = []): string {
        $payload = [
            'model'    => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user',   'content' => $prompt],
            ],
            'temperature' => $options['temperature'] ?? $this->temperature,
            'max_tokens'  => $options['max_tokens']  ?? $this->maxTokens,
        ];

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

        $result = curl_exec($ch);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("DeepSeek API curl error: $error");
            return '';
        }

        $data = json_decode($result, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }

    public function smartSearch(string $query): array {
        $system = 'You are a product search assistant. Extract search filters from natural language queries. Return JSON with keys: keywords, category, min_price, max_price, location, min_order, certifications. Return only valid JSON.';
        $response = $this->chat($query, $system);
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['keywords' => $query];
    }

    public function getRecommendations(array $userHistory, array $availableProducts): string {
        $historyStr = json_encode(array_slice($userHistory, 0, 10));
        $productsStr = json_encode(array_slice($availableProducts, 0, 20));
        $prompt = "Based on user purchase/browse history: $historyStr\nRecommend products from: $productsStr\nReturn top 5 product IDs as JSON array.";
        return $this->chat($prompt, 'You are a product recommendation engine. Return only a JSON array of product IDs.');
    }

    public function analyzeFraud(array $transaction): array {
        $txStr = json_encode($transaction);
        $prompt = "Analyze this transaction for fraud indicators: $txStr\nReturn JSON: {risk_score: 0-100, risk_level: low/medium/high, flags: [], recommendation: string}";
        $response = $this->chat($prompt, 'You are a fraud detection AI. Analyze transactions and return structured JSON.');
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['risk_score' => 0, 'risk_level' => 'low', 'flags' => [], 'recommendation' => 'Unable to analyze'];
    }

    public function generateInsights(array $salesData): string {
        $dataStr = json_encode($salesData);
        $prompt = "Analyze this sales data and provide 3-5 business insights with actionable recommendations: $dataStr";
        return $this->chat($prompt, 'You are a business intelligence analyst specializing in B2B/B2C marketplace analytics.');
    }

    public function translateText(string $text, string $targetLanguage): string {
        $prompt = "Translate the following text to $targetLanguage. Return only the translated text:\n\n$text";
        return $this->chat($prompt, 'You are a professional translator.');
    }

    public function generateMarketingContent(array $product): string {
        $productStr = json_encode($product);
        $prompt = "Generate compelling marketing content for this product: $productStr\nInclude: title, description (100 words), key features (5 bullets), call-to-action.";
        return $this->chat($prompt, 'You are a professional e-commerce copywriter.');
    }

    public function explainAnalytics(array $chartData): string {
        $dataStr = json_encode($chartData);
        $prompt = "Explain these analytics metrics in simple business language and provide actionable insights: $dataStr";
        return $this->chat($prompt, 'You are a data analyst who explains metrics clearly to business owners.');
    }
}

function getDeepSeek(): DeepSeek {
    static $instance = null;
    if ($instance === null) {
        $config = require __DIR__ . '/../config/ai.php';
        $instance = new DeepSeek($config['deepseek']);
    }
    return $instance;
}
