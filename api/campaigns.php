<?php
require_once __DIR__ . '/../includes/middleware.php';
header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data, $code=200){ http_response_code($code); echo json_encode($data); exit; }

switch($action){
  case 'list':
    $type = $_GET['type'] ?? 'active';
    $now = date('Y-m-d H:i:s');
    if($type==='active') $where="start_date <= '$now' AND end_date >= '$now' AND status='active'";
    elseif($type==='upcoming') $where="start_date > '$now' AND status='active'";
    else $where="end_date < '$now'";
    $rows = $db->query("SELECT * FROM campaigns WHERE $where ORDER BY end_date ASC")->fetchAll(PDO::FETCH_ASSOC);
    jsonOut(['success'=>true,'data'=>$rows]);

  case 'detail':
    $id = (int)($_GET['id'] ?? 0);
    $row = $db->prepare("SELECT * FROM campaigns WHERE id=?");
    $row->execute([$id]);
    $data = $row->fetch(PDO::FETCH_ASSOC);
    if(!$data) jsonOut(['success'=>false,'message'=>'Not found'],404);
    jsonOut(['success'=>true,'data'=>$data]);

  case 'create':
    requireRole('admin');
    validateCsrf();
    $stmt = $db->prepare("INSERT INTO campaigns(title,description,discount_percent,type,start_date,end_date,status,created_at) VALUES(?,?,?,?,?,?,'active',NOW())");
    $stmt->execute([
      sanitize($_POST['title']),
      sanitize($_POST['description']),
      (float)$_POST['discount_percent'],
      sanitize($_POST['type']),
      $_POST['start_date'],
      $_POST['end_date']
    ]);
    jsonOut(['success'=>true,'id'=>$db->lastInsertId()]);

  case 'update':
    requireRole('admin');
    validateCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $db->prepare("UPDATE campaigns SET title=?,description=?,discount_percent=?,type=?,start_date=?,end_date=?,status=? WHERE id=?");
    $stmt->execute([
      sanitize($_POST['title']),
      sanitize($_POST['description']),
      (float)$_POST['discount_percent'],
      sanitize($_POST['type']),
      $_POST['start_date'],
      $_POST['end_date'],
      sanitize($_POST['status']),
      $id
    ]);
    jsonOut(['success'=>true]);

  case 'delete':
    requireRole('admin');
    validateCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare("DELETE FROM campaigns WHERE id=?")->execute([$id]);
    jsonOut(['success'=>true]);

  case 'apply_coupon':
    requireAuth();
    validateCsrf();
    $code = strtoupper(sanitize($_POST['code'] ?? ''));
    $stmt = $db->prepare("SELECT * FROM coupons WHERE code=? AND status='active' AND (expires_at IS NULL OR expires_at > NOW())");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$coupon) jsonOut(['success'=>false,'message'=>'Invalid or expired coupon'],400);
    jsonOut(['success'=>true,'discount'=>$coupon['discount_percent'],'type'=>$coupon['discount_type']]);

  default:
    jsonOut(['success'=>false,'message'=>'Invalid action'],400);
}
