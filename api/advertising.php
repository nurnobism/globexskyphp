<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

switch ($action) {
    case 'list':
        $page = (int)($_GET['page'] ?? 1);
        $status = sanitize($_GET['status'] ?? '');
        $params = [];
        $sql = 'SELECT id, title, budget, impressions, clicks, status, start_date, end_date FROM advertising_campaigns';
        if ($status !== '') {
            $sql .= ' WHERE status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY created_at DESC';
        $result = paginate($db, $sql, $params, $page);
        jsonOut(['success' => true, 'data' => $result]);
    break;

    case 'detail':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid id'], 400);
        $stmt = $db->prepare('SELECT * FROM advertising_campaigns WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) jsonOut(['success' => false, 'message' => 'Campaign not found'], 404);
        jsonOut(['success' => true, 'data' => $row]);
    break;

    case 'create':
        requireAuth();
        validateCsrf();
        $title           = sanitize($_POST['title'] ?? '');
        $description     = sanitize($_POST['description'] ?? '');
        $budget          = (float)($_POST['budget'] ?? 0);
        $start_date      = sanitize($_POST['start_date'] ?? '');
        $end_date        = sanitize($_POST['end_date'] ?? '');
        $target_audience = sanitize($_POST['target_audience'] ?? '');
        $type            = sanitize($_POST['type'] ?? '');
        if ($title === '' || $budget <= 0 || $start_date === '' || $end_date === '') {
            jsonOut(['success' => false, 'message' => 'title, budget, start_date and end_date are required'], 400);
        }
        $stmt = $db->prepare(
            'INSERT INTO advertising_campaigns (title, description, budget, start_date, end_date, target_audience, type, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'draft\', NOW())'
        );
        $stmt->execute([$title, $description, $budget, $start_date, $end_date, $target_audience, $type]);
        $newId = $db->lastInsertId();
        jsonOut(['success' => true, 'message' => 'Campaign created', 'id' => $newId], 201);
    break;

    case 'update':
        requireAuth();
        validateCsrf();
        $id              = (int)($_POST['id'] ?? 0);
        $title           = sanitize($_POST['title'] ?? '');
        $description     = sanitize($_POST['description'] ?? '');
        $budget          = (float)($_POST['budget'] ?? 0);
        $start_date      = sanitize($_POST['start_date'] ?? '');
        $end_date        = sanitize($_POST['end_date'] ?? '');
        $target_audience = sanitize($_POST['target_audience'] ?? '');
        $type            = sanitize($_POST['type'] ?? '');
        $status          = sanitize($_POST['status'] ?? '');
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid id'], 400);
        $check = $db->prepare('SELECT id FROM advertising_campaigns WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) jsonOut(['success' => false, 'message' => 'Campaign not found'], 404);
        $stmt = $db->prepare(
            'UPDATE advertising_campaigns
             SET title=?, description=?, budget=?, start_date=?, end_date=?, target_audience=?, type=?, status=?, updated_at=NOW()
             WHERE id=?'
        );
        $stmt->execute([$title, $description, $budget, $start_date, $end_date, $target_audience, $type, $status, $id]);
        jsonOut(['success' => true, 'message' => 'Campaign updated']);
    break;

    case 'delete':
        requireAuth();
        validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid id'], 400);
        $check = $db->prepare('SELECT id FROM advertising_campaigns WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) jsonOut(['success' => false, 'message' => 'Campaign not found'], 404);
        $db->prepare('DELETE FROM ad_analytics WHERE campaign_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM advertising_campaigns WHERE id = ?')->execute([$id]);
        jsonOut(['success' => true, 'message' => 'Campaign deleted']);
    break;

    case 'analytics':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid campaign id'], 400);
        $stmt = $db->prepare(
            'SELECT ac.id, ac.title, ac.budget,
                    COALESCE(SUM(aa.impressions), 0) AS total_impressions,
                    COALESCE(SUM(aa.clicks), 0)      AS total_clicks,
                    COALESCE(SUM(aa.spend), 0)       AS total_spend,
                    CASE WHEN SUM(aa.impressions) > 0
                         THEN ROUND(SUM(aa.clicks) / SUM(aa.impressions) * 100, 2)
                         ELSE 0 END AS ctr
             FROM advertising_campaigns ac
             LEFT JOIN ad_analytics aa ON aa.campaign_id = ac.id
             WHERE ac.id = ?
             GROUP BY ac.id, ac.title, ac.budget'
        );
        $stmt->execute([$id]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$summary) jsonOut(['success' => false, 'message' => 'Campaign not found'], 404);
        $detail = $db->prepare(
            'SELECT date, impressions, clicks, spend FROM ad_analytics WHERE campaign_id = ? ORDER BY date ASC'
        );
        $detail->execute([$id]);
        $rows = $detail->fetchAll(PDO::FETCH_ASSOC);
        jsonOut(['success' => true, 'data' => ['summary' => $summary, 'daily' => $rows]]);
    break;

    default:
        jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
}
