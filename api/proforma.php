<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Auto-create table if not exists
try {
  $db->exec("CREATE TABLE IF NOT EXISTS `proforma_invoices` (
    `id`             INT(11) NOT NULL AUTO_INCREMENT,
    `ofr_no`         VARCHAR(50) NOT NULL,
    `customer_id`    INT(11) DEFAULT NULL,
    `customer_name`  VARCHAR(200) DEFAULT '',
    `ofr_date`       DATE NOT NULL,
    `valid_until`    DATE DEFAULT NULL,
    `destination`    VARCHAR(200) DEFAULT '',
    `incoterms`      VARCHAR(50) DEFAULT 'FOB',
    `payment_terms`  VARCHAR(200) DEFAULT '',
    `currency`       ENUM('INR','USD','BOTH') DEFAULT 'BOTH',
    `usd_rate`       DECIMAL(10,4) DEFAULT 93.5000,
    `is_international` TINYINT(1) DEFAULT 1,
    `products`       LONGTEXT DEFAULT NULL COMMENT 'JSON array of product rows',
    `charges`        LONGTEXT DEFAULT NULL COMMENT 'JSON array of charge rows',
    `subtotal_inr`   DECIMAL(14,2) DEFAULT 0,
    `total_inr`      DECIMAL(14,2) DEFAULT 0,
    `total_usd`      DECIMAL(14,2) DEFAULT 0,
    `per_kg_inr`     DECIMAL(10,2) DEFAULT 0,
    `per_kg_usd`     DECIMAL(10,4) DEFAULT 0,
    `notes`          TEXT DEFAULT NULL,
    `internal_notes` TEXT DEFAULT NULL,
    `status`         ENUM('Draft','Pending','Accepted','Cancelled','Expired') DEFAULT 'Pending',
    `sale_id`        INT(11) DEFAULT NULL,
    `created_by`     INT(11) DEFAULT NULL,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch(Throwable $e) {}

// Auto-expire
try {
  $db->exec("UPDATE proforma_invoices SET status='Expired'
             WHERE status='Pending' AND valid_until < CURDATE()");
} catch(Throwable $e) {}

function nextOfrNo($db) {
  $yr  = date('Y');
  $row = $db->query("SELECT ofr_no FROM proforma_invoices
                     WHERE ofr_no LIKE 'OFR-{$yr}-%'
                     ORDER BY id DESC LIMIT 1")->fetch();
  if ($row) {
    preg_match('/OFR-\d{4}-(\d+)/', $row['ofr_no'], $m);
    $seq = intval($m[1] ?? 0) + 1;
  } else { $seq = 1; }
  return "OFR-{$yr}-" . str_pad($seq, 3, '0', STR_PAD_LEFT);
}

try { switch ($method) {

  case 'GET':
    if (!empty($_GET['id'])) {
      $s = $db->prepare('SELECT * FROM proforma_invoices WHERE id=?');
      $s->execute([(int)$_GET['id']]);
      $row = $s->fetch();
      if (!$row) jsonResponse(['error'=>'Not found'],404);
      $row['products'] = $row['products'] ? json_decode($row['products'],true) : [];
      $row['charges']  = $row['charges']  ? json_decode($row['charges'],true)  : [];
      jsonResponse(['data'=>$row]);
    }
    // List
    $where = 'WHERE 1=1';
    $params = [];
    if (!empty($_GET['status'])) { $where .= ' AND status=?'; $params[] = $_GET['status']; }
    if (!empty($_GET['customer_id'])) { $where .= ' AND customer_id=?'; $params[] = (int)$_GET['customer_id']; }
    $s = $db->prepare("SELECT id,ofr_no,customer_name,ofr_date,valid_until,total_inr,total_usd,currency,status,sale_id FROM proforma_invoices $where ORDER BY id DESC LIMIT 200");
    $s->execute($params);
    jsonResponse(['data'=>$s->fetchAll(),'next_no'=>nextOfrNo($db)]);

  case 'POST':
    $d = json_decode(file_get_contents('php://input'),true);
    if (!$d) jsonResponse(['error'=>'Invalid JSON'],400);
    $ofrNo = !empty($d['ofr_no']) ? trim($d['ofr_no']) : nextOfrNo($db);
    $s = $db->prepare('INSERT INTO proforma_invoices
      (ofr_no,customer_id,customer_name,ofr_date,valid_until,destination,incoterms,payment_terms,
       currency,usd_rate,is_international,products,charges,
       subtotal_inr,total_inr,total_usd,per_kg_inr,per_kg_usd,notes,internal_notes,status,created_by)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $s->execute([
      $ofrNo, $d['customer_id']??null, $d['customer_name']??'',
      $d['ofr_date'], $d['valid_until']??null,
      $d['destination']??'', $d['incoterms']??'FOB', $d['payment_terms']??'',
      $d['currency']??'BOTH', (float)($d['usd_rate']??93.5),
      $d['is_international']??1,
      json_encode($d['products']??[]), json_encode($d['charges']??[]),
      (float)($d['subtotal_inr']??0), (float)($d['total_inr']??0),
      (float)($d['total_usd']??0), (float)($d['per_kg_inr']??0),
      (float)($d['per_kg_usd']??0),
      $d['notes']??'', $d['internal_notes']??'',
      $d['status']??'Pending', (int)$_SESSION['user_id'],
    ]);
    $id = (int)$db->lastInsertId();
    logActivity((int)$_SESSION['user_id'],'create','proforma',$id,'Proforma created: '.$ofrNo);
    jsonResponse(['success'=>true,'id'=>$id,'ofr_no'=>$ofrNo]);

  case 'PUT':
    $id = (int)($_GET['id']??0);
    if (!$id) jsonResponse(['error'=>'Missing id'],400);
    $d = json_decode(file_get_contents('php://input'),true);
    if (!$d) jsonResponse(['error'=>'Invalid JSON'],400);
    $s = $db->prepare('UPDATE proforma_invoices SET
      ofr_no=?,customer_id=?,customer_name=?,ofr_date=?,valid_until=?,destination=?,
      incoterms=?,payment_terms=?,currency=?,usd_rate=?,is_international=?,
      products=?,charges=?,subtotal_inr=?,total_inr=?,total_usd=?,
      per_kg_inr=?,per_kg_usd=?,notes=?,internal_notes=?,status=?
      WHERE id=?');
    $s->execute([
      $d['ofr_no'], $d['customer_id']??null, $d['customer_name']??'',
      $d['ofr_date'], $d['valid_until']??null,
      $d['destination']??'', $d['incoterms']??'FOB', $d['payment_terms']??'',
      $d['currency']??'BOTH', (float)($d['usd_rate']??93.5),
      $d['is_international']??1,
      json_encode($d['products']??[]), json_encode($d['charges']??[]),
      (float)($d['total_inr']??0)-0, (float)($d['total_inr']??0),
      (float)($d['total_usd']??0), (float)($d['per_kg_inr']??0),
      (float)($d['per_kg_usd']??0),
      $d['notes']??'', $d['internal_notes']??'',
      $d['status']??'Pending', $id,
    ]);
    logActivity((int)$_SESSION['user_id'],'update','proforma',$id,'Proforma updated');
    jsonResponse(['success'=>true]);

  case 'DELETE':
    $id = (int)($_GET['id']??0);
    if (!$id) jsonResponse(['error'=>'Missing id'],400);
    $db->prepare('DELETE FROM proforma_invoices WHERE id=?')->execute([$id]);
    logActivity((int)$_SESSION['user_id'],'delete','proforma',$id,'Proforma deleted');
    jsonResponse(['success'=>true]);

  default:
    jsonResponse(['error'=>'Unknown method'],400);

}} catch(Throwable $e) {
  error_log('proforma.php: '.$e->getMessage());
  jsonResponse(['error'=>'Server error: '.$e->getMessage()],500);
}
