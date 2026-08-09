<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Self-heal: same missing-table issue as suppliers.php. Columns mirror
// $FIELDS + the INSERT/UPDATE statements below.
$db->exec("CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `customer_type` VARCHAR(60) DEFAULT '',
  `mobile` VARCHAR(30) DEFAULT '',
  `email` VARCHAR(150) DEFAULT '',
  `gstin` VARCHAR(30) DEFAULT '',
  `state` VARCHAR(100) DEFAULT '',
  `district` VARCHAR(100) DEFAULT '',
  `billing_address` VARCHAR(255) DEFAULT '',
  `shipping_address` VARCHAR(255) DEFAULT '',
  `credit_limit` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `payment_terms` VARCHAR(100) DEFAULT '',
  `sales_executive` VARCHAR(150) DEFAULT '',
  `notes` TEXT NULL,
  `customer_code` VARCHAR(40) DEFAULT '',
  `business_name` VARCHAR(200) DEFAULT '',
  `display_name` VARCHAR(200) DEFAULT '',
  `group_name` VARCHAR(100) DEFAULT '',
  `alternate_phone` VARCHAR(30) DEFAULT '',
  `whatsapp_no` VARCHAR(30) DEFAULT '',
  `billing_city` VARCHAR(100) DEFAULT '',
  `billing_pincode` VARCHAR(20) DEFAULT '',
  `shipping_city` VARCHAR(100) DEFAULT '',
  `shipping_state` VARCHAR(100) DEFAULT '',
  `shipping_pincode` VARCHAR(20) DEFAULT '',
  `pan_no` VARCHAR(20) DEFAULT '',
  `business_type` VARCHAR(60) DEFAULT '',
  `tan_no` VARCHAR(20) DEFAULT '',
  `iec_no` VARCHAR(30) DEFAULT '',
  `trade_license_no` VARCHAR(40) DEFAULT '',
  `currency` VARCHAR(10) DEFAULT 'INR',
  `opening_balance` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `opening_balance_type` VARCHAR(20) DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `country` VARCHAR(100) DEFAULT '',
  `documents` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_cust_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

function saveCustomerDoc($dataUrl) {
  if (!$dataUrl || !preg_match('/^data:(image\/(png|jpe?g|webp)|application\/pdf);base64,(.+)$/', $dataUrl, $m)) return null;
  $ext  = str_contains($m[1], 'pdf') ? 'pdf' : ($m[2] === 'jpeg' ? 'jpg' : $m[2]);
  $blob = base64_decode($m[3]);
  if ($blob === false || strlen($blob) > (defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 5242880)) return null;
  $dir = rtrim(defined('UPLOAD_PATH') ? UPLOAD_PATH : (__DIR__ . '/../assets/uploads/'), '/') . '/customers';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $fname = 'cus_' . bin2hex(random_bytes(8)) . '.' . $ext;
  file_put_contents($dir . '/' . $fname, $blob);
  return '/assets/uploads/customers/' . $fname;
}
function processCustomerDocArray($items) {
  $out = [];
  foreach ((array)$items as $it) {
    if (is_string($it) && str_starts_with($it, 'data:')) { $saved = saveCustomerDoc($it); if ($saved) $out[] = $saved; }
    elseif (is_string($it) && $it !== '') { $out[] = $it; }
  }
  return $out;
}

$FIELDS = [
  'name','customer_type','mobile','email','gstin','state','district','billing_address','shipping_address',
  'credit_limit','payment_terms','sales_executive','notes',
  'customer_code','business_name','display_name','group_name','alternate_phone','whatsapp_no',
  'billing_city','billing_pincode','shipping_city','shipping_state','shipping_pincode',
  'pan_no','business_type','tan_no','iec_no','trade_license_no','currency',
  'opening_balance','opening_balance_type','status','country',
];

try {
switch ($method) {
  case 'GET':
    if (!empty($_GET['summary_for'])) {
      $cid = (int)$_GET['summary_for'];
      $ytdStmt = $db->prepare('SELECT COALESCE(SUM(total),0) AS total_sales FROM sales WHERE customer_id = ? AND YEAR(sale_date) = YEAR(CURDATE()) AND status != "Cancelled"');
      $ytdStmt->execute([$cid]);
      $ytd = $ytdStmt->fetch()['total_sales'];

      $lastStmt = $db->prepare('SELECT invoice_no, sale_date FROM sales WHERE customer_id = ? AND status != "Cancelled" ORDER BY sale_date DESC, id DESC LIMIT 1');
      $lastStmt->execute([$cid]);
      $last = $lastStmt->fetch();

      $outStmt = $db->prepare('SELECT COALESCE(SUM(total - amount_received),0) AS outstanding FROM sales WHERE customer_id = ? AND status != "Cancelled"');
      $outStmt->execute([$cid]);
      $outstanding = $outStmt->fetch()['outstanding'];

      $avgStmt = $db->prepare('SELECT AVG(DATEDIFF(payment_date, sale_date)) AS avg_days FROM sales WHERE customer_id = ? AND payment_date IS NOT NULL AND status != "Cancelled"');
      $avgStmt->execute([$cid]);
      $avgDays = $avgStmt->fetch()['avg_days'];

      jsonResponse(['data' => [
        'total_sales_ytd' => (float)$ytd,
        'outstanding' => (float)$outstanding,
        'last_invoice_no' => $last['invoice_no'] ?? null,
        'last_invoice_date' => $last['sale_date'] ?? null,
        'avg_payment_days' => $avgDays !== null ? round((float)$avgDays) : null,
      ]]);
      break;
    }

    $status = $_GET['status'] ?? 'active';
    $stmt = $db->prepare('SELECT * FROM customers WHERE status = ? ORDER BY name ASC');
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
      $stmt = $db->prepare('UPDATE customers SET status = "active" WHERE id = ?');
      $stmt->execute([(int)$_GET['id']]);
      logActivity((int)$_SESSION['user_id'], 'restore', 'customer', (int)$_GET['id'], 'Customer restored');
      jsonResponse(['success' => true]);
      break;
    }

    if (empty($d['name'])) jsonResponse(['error' => 'Customer name is required'], 400);

    $custCode = trim($d['customer_code'] ?? '');
    if ($custCode === '' || strtolower($custCode) === 'auto generate') {
      $cnt = $db->query('SELECT COUNT(*) c FROM customers')->fetch()['c'] + 1;
      $custCode = 'CUST-' . str_pad($cnt, 4, '0', STR_PAD_LEFT);
    }

    $docs = processCustomerDocArray($d['documents'] ?? []);
    $d['customer_code'] = $custCode;
    $cols = array_merge($FIELDS, ['documents']);
    $vals = array_map(fn($f) => $d[$f] ?? '', $FIELDS);
    $vals[] = json_encode($docs);

    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $colList = implode(',', array_map(fn($c) => "`$c`", $cols));
    $stmt = $db->prepare("INSERT INTO customers ($colList) VALUES ($placeholders)");
    $stmt->execute($vals);
    $id = $db->lastInsertId();
    logActivity((int)$_SESSION['user_id'], 'create', 'customer', (int)$id, 'Customer added: ' . $d['name']);
    jsonResponse(['success' => true, 'id' => $id, 'customer_code' => $custCode]);
    break;

  case 'PUT':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $docs = processCustomerDocArray($d['documents'] ?? []);
    $setSql = implode(',', array_map(fn($f) => "`$f`=?", $FIELDS)) . ', documents=?';
    $vals = array_map(fn($f) => $d[$f] ?? '', $FIELDS);
    $vals[] = json_encode($docs);
    $vals[] = $id;

    $db->prepare("UPDATE customers SET $setSql WHERE id=?")->execute($vals);
    logActivity((int)$_SESSION['user_id'], 'update', 'customer', $id, 'Customer updated');
    jsonResponse(['success' => true]);
    break;

  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $stmt = $db->prepare('UPDATE customers SET status = "archived" WHERE id = ?');
    $stmt->execute([$id]);
    logActivity((int)$_SESSION['user_id'], 'archive', 'customer', $id, 'Customer archived');
    jsonResponse(['success' => true]);
    break;

  default:
    jsonResponse(['error' => 'Method not allowed'], 405);
}
} catch (Throwable $e) {
  jsonResponse(['error' => 'Customers API error: ' . $e->getMessage()], 500);
}
