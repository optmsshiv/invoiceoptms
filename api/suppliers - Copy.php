<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

function saveSupplierDoc($dataUrl) {
  if (!$dataUrl || !preg_match('/^data:(image\/(png|jpe?g|webp)|application\/pdf);base64,(.+)$/', $dataUrl, $m)) return null;
  $ext  = str_contains($m[1], 'pdf') ? 'pdf' : ($m[2] === 'jpeg' ? 'jpg' : $m[2]);
  $blob = base64_decode($m[3]);
  if ($blob === false || strlen($blob) > (defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 5242880)) return null;
  $dir = rtrim(defined('UPLOAD_PATH') ? UPLOAD_PATH : (__DIR__ . '/../assets/uploads/'), '/') . '/suppliers';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $fname = 'sup_' . bin2hex(random_bytes(8)) . '.' . $ext;
  file_put_contents($dir . '/' . $fname, $blob);
  return '/assets/uploads/suppliers/' . $fname;
}
function processDocArray($items) {
  $out = [];
  foreach ((array)$items as $it) {
    if (is_string($it) && str_starts_with($it, 'data:')) { $saved = saveSupplierDoc($it); if ($saved) $out[] = $saved; }
    elseif (is_string($it) && $it !== '') { $out[] = $it; }
  }
  return $out;
}

$FIELDS = [
  'name','contact_person','phone','email','gst_number','country','address','payment_terms','opening_balance','notes',
  'supplier_type','state','district','date_of_registration','business_nature','website','city','pincode',
  'pan_no','aadhaar_no','state_code','tan_no','msme_no','fssai_no',
  'bank_name','bank_account_no','ifsc_code','account_holder_name','credit_limit','default_price_list',
];

try {
switch ($method) {
  case 'GET':
    if (!empty($_GET['summary_for'])) {
      $sid = (int)$_GET['summary_for'];
      $stmt = $db->prepare('SELECT
          COALESCE(SUM(total), 0)       AS total_purchases,
          COALESCE(SUM(amount_paid), 0) AS total_paid,
          COALESCE(SUM(total - amount_paid), 0) AS outstanding
        FROM purchases WHERE supplier_id = ?');
      $stmt->execute([$sid]);
      jsonResponse(['data' => $stmt->fetch()]);
      break;
    }

    $status = $_GET['status'] ?? 'active';
    $stmt = $db->prepare('SELECT * FROM suppliers WHERE status = ? ORDER BY name ASC');
    $stmt->execute([$status]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) { $r['documents'] = $r['documents'] ? json_decode($r['documents'], true) : []; }
    unset($r);
    jsonResponse(['data' => $rows]);
    break;

  case 'POST':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    if (($_GET['action'] ?? '') === 'restore' && !empty($_GET['id'])) {
      $stmt = $db->prepare('UPDATE suppliers SET status = "active" WHERE id = ?');
      $stmt->execute([(int)$_GET['id']]);
      logActivity((int)$_SESSION['user_id'], 'restore', 'supplier', (int)$_GET['id'], 'Supplier restored');
      jsonResponse(['success' => true]);
      break;
    }

    if (empty($d['name'])) jsonResponse(['error' => 'Supplier name is required'], 400);

    $docs = processDocArray($d['documents'] ?? []);
    $cols = array_merge($FIELDS, ['documents']);
    $vals = array_map(fn($f) => $d[$f] ?? '', $FIELDS);
    $vals[] = json_encode($docs);

    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $colList = implode(',', array_map(fn($c) => "`$c`", $cols));
    $stmt = $db->prepare("INSERT INTO suppliers ($colList) VALUES ($placeholders)");
    $stmt->execute($vals);
    $id = $db->lastInsertId();
    logActivity((int)$_SESSION['user_id'], 'create', 'supplier', (int)$id, 'Supplier added: ' . $d['name']);
    jsonResponse(['success' => true, 'id' => $id]);
    break;

  case 'PUT':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $docs = processDocArray($d['documents'] ?? []);
    $setSql = implode(',', array_map(fn($f) => "`$f`=?", $FIELDS)) . ', documents=?';
    $vals = array_map(fn($f) => $d[$f] ?? '', $FIELDS);
    $vals[] = json_encode($docs);
    $vals[] = $id;

    $db->prepare("UPDATE suppliers SET $setSql WHERE id=?")->execute($vals);
    logActivity((int)$_SESSION['user_id'], 'update', 'supplier', $id, 'Supplier updated');
    jsonResponse(['success' => true]);
    break;

  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    if (!empty($_GET['permanent'])) {
      // Hard delete — only allowed if no purchases reference this supplier,
      // otherwise history would break. Archive is the right tool for those.
      $refCount = $db->prepare('SELECT COUNT(*) c FROM purchases WHERE supplier_id = ?');
      $refCount->execute([$id]);
      if ((int)$refCount->fetch()['c'] > 0) {
        jsonResponse(['error' => 'This supplier has purchase records and cannot be permanently deleted. Use Archive instead.'], 400);
      }
      $db->prepare('DELETE FROM suppliers WHERE id = ?')->execute([$id]);
      logActivity((int)$_SESSION['user_id'], 'delete', 'supplier', $id, 'Supplier permanently deleted');
      jsonResponse(['success' => true]);
      break;
    }
    $stmt = $db->prepare('UPDATE suppliers SET status = "archived" WHERE id = ?');
    $stmt->execute([$id]);
    logActivity((int)$_SESSION['user_id'], 'archive', 'supplier', $id, 'Supplier archived');
    jsonResponse(['success' => true]);
    break;

  default:
    jsonResponse(['error' => 'Method not allowed'], 405);
}
} catch (Throwable $e) {
  jsonResponse(['error' => 'Suppliers API error: ' . $e->getMessage()], 500);
}
