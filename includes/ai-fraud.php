<?php
/**
 * includes/ai-fraud.php — AI Fraud Detection Engine
 *
 * Analyses orders, logins, registrations, and reviews for fraud signals
 * using DeepSeek AI.  All calls are low-temperature (deterministic).
 *
 * Requires includes/ai-engine.php.
 */

require_once __DIR__ . '/ai-engine.php';

/**
 * Analyse an order for fraud risk.
 *
 * @return array ['risk_score' => int, 'risk_level' => string, 'recommendation' => string, 'log_id' => int|null]
 */
function analyzeOrderFraud(int $orderId): array
{
    if (!isAiEnabled('fraud')) {
        return ['risk_score' => 0, 'risk_level' => 'low', 'recommendation' => 'approve', 'log_id' => null];
    }

    try {
        $db = getDB();

        // Gather order signals
        $stmt = $db->prepare(
            'SELECT o.*, u.email, u.created_at AS account_created
             FROM orders o
             JOIN users u ON u.id = o.user_id
             WHERE o.id = ? LIMIT 1'
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return ['risk_score' => 0, 'risk_level' => 'low', 'recommendation' => 'approve', 'log_id' => null];
        }

        $userId = (int)$order['user_id'];

        // Order velocity: count orders in the last hour
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM orders WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute([$userId]);
        $hourlyOrders = (int)$stmt->fetchColumn();

        // Historical refund rate
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'refunded'"
        );
        $stmt->execute([$userId]);
        $refundCount = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $stmt->execute([$userId]);
        $totalOrders = (int)$stmt->fetchColumn();
        $refundRate  = $totalOrders > 0 ? round($refundCount / $totalOrders, 3) : 0;

        $accountAgeHours = 0;
        if (!empty($order['account_created'])) {
            $accountAgeHours = (int)((time() - strtotime($order['account_created'])) / 3600);
        }

        $factors = [
            'order_amount'       => (float)($order['total_amount'] ?? $order['total'] ?? 0),
            'hourly_order_count' => $hourlyOrders,
            'refund_rate'        => $refundRate,
            'account_age_hours'  => $accountAgeHours,
            'email_domain'       => explode('@', $order['email'] ?? '@')[1] ?? 'unknown',
        ];

        return _runFraudAnalysis($userId, $orderId, 'order', $factors);
    } catch (Throwable $e) {
        error_log('analyzeOrderFraud error: ' . $e->getMessage());
        return ['risk_score' => 0, 'risk_level' => 'low', 'recommendation' => 'approve', 'log_id' => null];
    }
}

/**
 * Analyse a login attempt for suspicious activity.
 */
function analyzeLoginFraud(int $userId, string $ipAddress, string $userAgent): array
{
    if (!isAiEnabled('fraud')) {
        return ['risk_score' => 0, 'risk_level' => 'low', 'recommendation' => 'approve', 'log_id' => null];
    }

    $factors = [
        'ip_address'  => $ipAddress,
        'user_agent'  => mb_substr($userAgent, 0, 200),
        'user_id'     => $userId,
        'timestamp'   => date('Y-m-d H:i:s'),
    ];

    return _runFraudAnalysis($userId, null, 'login', $factors);
}

/**
 * Analyse a new registration for suspicious signals.
 */
function analyzeRegistrationFraud(string $email, string $ipAddress): array
{
    if (!isAiEnabled('fraud')) {
        return ['risk_score' => 0, 'risk_level' => 'low', 'recommendation' => 'approve', 'log_id' => null];
    }

    $domain      = explode('@', $email)[1] ?? 'unknown';
    $disposable  = in_array($domain, ['mailinator.com', 'tempmail.com', 'guerrillamail.com', '10minutemail.com'], true);

    $factors = [
        'email'          => $email,
        'email_domain'   => $domain,
        'is_disposable'  => $disposable,
        'ip_address'     => $ipAddress,
    ];

    return _runFraudAnalysis(0, null, 'registration', $factors);
}

/**
 * Detect fake reviews.
 */
function analyzeReviewFraud(int $reviewId): array
{
    if (!isAiEnabled('fraud')) {
        return ['risk_score' => 0, 'risk_level' => 'low', 'recommendation' => 'approve', 'log_id' => null];
    }

    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT r.*, u.created_at AS account_created
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             WHERE r.id = ? LIMIT 1'
        );
        $stmt->execute([$reviewId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$review) {
            return ['risk_score' => 0, 'risk_level' => 'low', 'recommendation' => 'approve', 'log_id' => null];
        }

        $userId = (int)$review['user_id'];

        // Review velocity
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM reviews WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
        );
        $stmt->execute([$userId]);
        $dailyReviews = (int)$stmt->fetchColumn();

        $factors = [
            'review_text'          => mb_substr($review['comment'] ?? $review['content'] ?? '', 0, 500),
            'rating'               => (int)($review['rating'] ?? 5),
            'daily_review_count'   => $dailyReviews,
            'account_age_hours'    => (int)((time() - strtotime($review['account_created'] ?? 'now')) / 3600),
        ];

        return _runFraudAnalysis($userId, null, 'review', $factors);
    } catch (Throwable $e) {
        return ['risk_score' => 0, 'risk_level' => 'low', 'recommendation' => 'approve', 'log_id' => null];
    }
}

/**
 * Get fraud dashboard statistics for admin.
 */
function getFraudDashboard(array $filters = []): array
{
    try {
        $db = getDB();

        $where  = '';
        $params = [];
        if (!empty($filters['risk_level'])) {
            $where   .= ' AND risk_level = ?';
            $params[] = $filters['risk_level'];
        }
        if (!empty($filters['event_type'])) {
            $where   .= ' AND event_type = ?';
            $params[] = $filters['event_type'];
        }
        if (!empty($filters['admin_decision'])) {
            $where   .= ' AND admin_decision = ?';
            $params[] = $filters['admin_decision'];
        }

        $stmt = $db->prepare(
            "SELECT
               COUNT(*) AS total,
               SUM(risk_level = 'critical') AS critical_count,
               SUM(risk_level = 'high')     AS high_count,
               SUM(risk_level = 'medium')   AS medium_count,
               SUM(admin_decision = 'pending') AS pending_count,
               AVG(risk_score) AS avg_risk_score
             FROM ai_fraud_logs
             WHERE 1=1 $where"
        );
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $db->prepare(
            "SELECT id, user_id, order_id, event_type, risk_score, risk_level,
                    ai_recommendation, admin_decision, created_at
             FROM ai_fraud_logs
             WHERE 1=1 $where
             ORDER BY created_at DESC LIMIT 50"
        );
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['stats' => $stats, 'items' => $items];
    } catch (Throwable $e) {
        return ['stats' => [], 'items' => []];
    }
}

/**
 * Admin resolves a fraud case.
 */
function resolveFraudCase(int $fraudLogId, int $adminId, string $decision, string $notes = ''): bool
{
    $validDecisions = ['approved', 'rejected', 'escalated'];
    if (!in_array($decision, $validDecisions, true)) {
        return false;
    }

    try {
        $stmt = getDB()->prepare(
            'UPDATE ai_fraud_logs
             SET admin_decision = ?, admin_notes = ?, resolved_by = ?, resolved_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$decision, $notes, $adminId, $fraudLogId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Calculate a composite risk score from multiple numeric signals (0-100).
 */
function calculateRiskScore(array $factors): int
{
    $score = 0;

    if (isset($factors['hourly_order_count']) && $factors['hourly_order_count'] > 3) {
        $score += min(30, $factors['hourly_order_count'] * 8);
    }
    if (isset($factors['refund_rate']) && $factors['refund_rate'] > 0.3) {
        $score += 20;
    }
    if (isset($factors['account_age_hours']) && $factors['account_age_hours'] < 24) {
        $score += 15;
    }
    if (!empty($factors['is_disposable'])) {
        $score += 25;
    }

    return min(100, $score);
}

/**
 * Get orders flagged as high risk and pending review.
 */
function getHighRiskOrders(int $limit = 20): array
{
    try {
        $stmt = getDB()->prepare(
            "SELECT id, user_id, order_id, risk_score, risk_level, ai_recommendation, created_at
             FROM ai_fraud_logs
             WHERE risk_level IN ('high', 'critical')
               AND admin_decision = 'pending'
               AND event_type = 'order'
             ORDER BY risk_score DESC, created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

// ── Internal helper ───────────────────────────────────────────

/**
 * Core fraud analysis runner: calls DeepSeek and persists results.
 */
function _runFraudAnalysis(int $userId, ?int $relatedId, string $eventType, array $factors): array
{
    // Quick heuristic score (no API call for obviously low-risk events)
    $heuristicScore = calculateRiskScore($factors);

    $messages = [
        ['role' => 'system', 'content' => buildSystemPrompt('fraud_review')],
        ['role' => 'user',   'content' =>
            "Analyze these fraud signals for a $eventType event on GlobexSky marketplace. "
          . 'Factors: ' . json_encode($factors) . ' '
          . "Heuristic pre-score: $heuristicScore/100. "
          . 'Return JSON only with keys: risk_score (0-100), risk_level, factors (array), recommendation, analysis.'],
    ];

    $result = deepseekRequest($messages, [
        'user_id'     => $userId ?: null,
        'feature'     => 'fraud',
        'temperature' => 0.1,
        'max_tokens'  => 500,
    ]);

    $riskScore     = $heuristicScore;
    $riskLevel     = _scoreToLevel($heuristicScore);
    $recommendation = 'approve';
    $aiAnalysis    = '';
    $aiFactors     = $factors;

    if ($result['success']) {
        $raw = $result['content'];
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $parsed = json_decode($m[0], true);
            if (is_array($parsed)) {
                $riskScore     = min(100, max(0, (int)($parsed['risk_score'] ?? $heuristicScore)));
                $riskLevel     = $parsed['risk_level'] ?? _scoreToLevel($riskScore);
                $recommendation = $parsed['recommendation'] ?? 'approve';
                $aiAnalysis    = $parsed['analysis'] ?? '';
                if (!empty($parsed['factors']) && is_array($parsed['factors'])) {
                    $aiFactors = $parsed['factors'];
                }
            }
        }
    }

    // Validate enums
    if (!in_array($riskLevel, ['low', 'medium', 'high', 'critical'], true)) {
        $riskLevel = _scoreToLevel($riskScore);
    }
    if (!in_array($recommendation, ['approve', 'review', 'hold', 'block'], true)) {
        $recommendation = 'approve';
    }

    // Persist to DB
    $logId = null;
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'INSERT INTO ai_fraud_logs
             (user_id, order_id, event_type, risk_score, risk_level, factors, ai_analysis, ai_recommendation)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId ?: null,
            $relatedId ?: null,
            $eventType,
            $riskScore,
            $riskLevel,
            json_encode($aiFactors),
            $aiAnalysis,
            $recommendation,
        ]);
        $logId = (int)$db->lastInsertId();
    } catch (Throwable $e) {
        error_log('_runFraudAnalysis persist error: ' . $e->getMessage());
    }

    return [
        'risk_score'     => $riskScore,
        'risk_level'     => $riskLevel,
        'recommendation' => $recommendation,
        'analysis'       => $aiAnalysis,
        'log_id'         => $logId,
    ];
}

/** Convert numeric risk score to level string. */
function _scoreToLevel(int $score): string
{
    if ($score >= 80) return 'critical';
    if ($score >= 60) return 'high';
    if ($score >= 40) return 'medium';
    return 'low';
}
