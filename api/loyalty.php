<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

// Tier thresholds (points balance)
const TIER_GOLD     = 5000;
const TIER_SILVER   = 1000;

function resolveTier(int $balance): string {
    if ($balance >= TIER_GOLD)   return 'gold';
    if ($balance >= TIER_SILVER) return 'silver';
    return 'bronze';
}

switch ($action) {
    case 'get_balance':
        requireAuth();
        $uid  = $_SESSION['user_id'];
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(CASE WHEN type = 'earn' THEN points ELSE -points END), 0) AS balance
             FROM loyalty_points
             WHERE user_id = ?"
        );
        $stmt->execute([$uid]);
        $balance = (int) $stmt->fetchColumn();
        jsonOut([
            'success' => true,
            'data'    => [
                'balance' => $balance,
                'tier'    => resolveTier($balance),
            ],
        ]);
        break;

    case 'earn_points':
        requireAuth();
        validateCsrf();
        $uid         = $_SESSION['user_id'];
        $points      = (int) sanitize($_POST['points'] ?? '0');
        $description = sanitize($_POST['description'] ?? '');
        $refId       = sanitize($_POST['reference_id'] ?? '');

        if ($points <= 0) {
            jsonOut(['success' => false, 'message' => 'points must be a positive integer'], 422);
        }

        $stmt = $db->prepare(
            "INSERT INTO loyalty_points (user_id, points, type, description, reference_id, created_at)
             VALUES (?, ?, 'earn', ?, ?, NOW())"
        );
        $stmt->execute([$uid, $points, $description, $refId]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId(), 'message' => "$points points earned"]);
        break;

    case 'redeem_points':
        requireAuth();
        validateCsrf();
        $uid      = $_SESSION['user_id'];
        $points   = (int) sanitize($_POST['points'] ?? '0');
        $rewardId = sanitize($_POST['reward_id'] ?? '');

        if ($points <= 0) {
            jsonOut(['success' => false, 'message' => 'points must be a positive integer'], 422);
        }

        // Check current balance
        $bal = $db->prepare(
            "SELECT COALESCE(SUM(CASE WHEN type = 'earn' THEN points ELSE -points END), 0) AS balance
             FROM loyalty_points WHERE user_id = ?"
        );
        $bal->execute([$uid]);
        $balance = (int) $bal->fetchColumn();

        if ($balance < $points) {
            jsonOut(['success' => false, 'message' => 'Insufficient points balance'], 422);
        }

        // Optionally validate reward exists
        if ($rewardId) {
            $rcheck = $db->prepare('SELECT id, points_required FROM loyalty_rewards WHERE id = ? AND active = 1');
            $rcheck->execute([$rewardId]);
            $reward = $rcheck->fetch();
            if (!$reward) {
                jsonOut(['success' => false, 'message' => 'Reward not found or inactive'], 404);
            }
            if ($points < $reward['points_required']) {
                jsonOut(['success' => false, 'message' => 'Insufficient points for this reward'], 422);
            }
        }

        $stmt = $db->prepare(
            "INSERT INTO loyalty_points (user_id, points, type, reward_id, created_at)
             VALUES (?, ?, 'redeem', ?, NOW())"
        );
        $stmt->execute([$uid, $points, $rewardId ?: null]);
        jsonOut(['success' => true, 'id' => $db->lastInsertId(), 'message' => "$points points redeemed"]);
        break;

    case 'list_history':
        requireAuth();
        $uid  = $_SESSION['user_id'];
        $stmt = $db->prepare(
            'SELECT * FROM loyalty_points WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$uid]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'list_rewards':
        $stmt = $db->query('SELECT * FROM loyalty_rewards WHERE active = 1 ORDER BY points_required ASC');
        jsonOut(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
