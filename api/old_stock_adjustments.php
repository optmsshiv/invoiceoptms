<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

function cleanProductId($v) {
  if (empty($v)) return null;
  $n = (int) preg_replace('/\D/', '', (string)$v);
  return $n > 0 ? $n : null;
}

function currentStock($db, $productId) {
  $stmt = $db->prepare('SELECT COALESCE(SUM(CASE WHEN direction="in" THEN qty ELSE -qty END),0) AS bal FROM stock_ledger WHERE product_id = ?');
  $stmt->execute([$productId]);
  return (float)$stmt->fetch()['bal'];
}

function writeAdjustmentLedger($db, $productId, $adjustmentId, $qty, $date, $note, $warehouse = 'Main Warehouse', $batchNo = '') {
  if ($qty <= 0) return;
  $bal = currentStock($db, $productId) - $qty;
  $stmt = $db->prepare('INSERT INTO stock_ledger (product_id, ref_type, ref_id, direction, qty, rate, balance_after, movement_date, notes, warehouse, batch_no) VALUES (?,"adjustment",?,"out",?,0,?,?,?,?,?)');
  $stmt->execute([$productId, $adjustmentId, $qty, $bal, $date, $note, $warehouse, $batchNo]);
}

function saveAttachment($dataUrl) {
  if (!$dataUrl || !preg_match('/^data:(image\/(png|jpe?g|webp)|application\/pdf);base64,(.+)$/', $dataUrl, $m)) return null;
  $ext  = str_contains($m[1], 'pdf') ? 'pdf' : ($m[2] === 'jpeg' ? 'jpg' : $m[2]);
  $blob = base64_decode($m[3]);
  if ($blob === false || strlen($blob) > (defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 5242880)) return null;
  $dir = rtrim(defined('UPLOAD_PATH') ? UPLOAD_PATH : (__DIR__ . '/../assets/uploads/'), '/') . '/stock_adjustments';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $fname = 'adj_' . bin2hex(random_bytes(8)) . '.' . $ext;
  file_put_contents($dir . '/' . $fname, $blob);
  return '/assets/uploads/stock_adjustments/' . $fname;
}

try {
switch ($method) {
  case 'GET':
    if (!empty($_GET['id'])) {
      $stmt = $db->prepare('SELECT sa.*, p.name AS product_name, s.name AS supplier_name
        FROM stock_adjustments sa LEFT JOIN products p ON p.id = sa.product_id LEFT JOIN suppliers s ON s.id = sa.supplier_id
        WHERE sa.id = ?');
      $stmt->execute([(int)$_GET['id']]);
      $row = $stmt->fetch();
      if (!$row) jsonResponse(['error' => 'Not found'], 404);
      jsonResponse(['data' => $row]);
      break;
    }
    $limit = (int)($_GET['limit'] ?? 0);
    $sql = 'SELECT sa.*, p.name AS product_name FROM stock_adjustments sa LEFT JOIN products p ON p.id = sa.product_id ORDER BY sa.adjustment_date DESC, sa.id DESC';
    if ($limit > 0) $sql .= ' LIMIT ' . $limit;
    jsonResponse(['data' => $db->query($sql)->fetchAll()]);
    break;

  case 'POST':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);
    $productId = cleanProductId($d['product_id'] ?? null);
    if (!$productId) jsonResponse(['error' => 'Product is required'], 400);
    if (empty($d['adjustment_date'])) jsonResponse(['error' => 'Adjustment date is required'], 400);

    $adjustmentNo = trim($d['adjustment_no'] ?? '');
    if ($adjustmentNo === '') {
      $y = date('y'); $y2 = date('y', strtotime('+1 year'));
      $cnt = $db->query('SELECT COUNT(*) c FROM stock_adjustments')->fetch()['c'] + 1;
      $adjustmentNo = "ADJ/{$y}-{$y2}/" . str_pad($cnt, 4, '0', STR_PAD_LEFT);
    }

    // Server-authoritative calc
    $opening = (float)($d['opening_stock'] ?? 0);
    $moistBefore = isset($d['moisture_before_pct']) && $d['moisture_before_pct'] !== '' ? (float)$d['moisture_before_pct'] : null;
    $moistAfter  = isset($d['moisture_after_pct'])  && $d['moisture_after_pct']  !== '' ? (float)$d['moisture_after_pct']  : null;
    $moistLoss = ($moistBefore !== null && $moistAfter !== null) ? round($moistBefore - $moistAfter, 2) : null;
    $weightLoss = (float)($d['weight_loss_kg'] ?? 0);
    $finalStock = round($opening - $weightLoss, 3);

    $attachmentPath = saveAttachment($d['attachment'] ?? null);

    $stmt = $db->prepare('INSERT INTO stock_adjustments
      (adjustment_no, adjustment_date, adjustment_type, warehouse, reference_no, reference_date,
       product_id, variety_grade, grade, unit, batch_no, manufacture_date, expiry_date, supplier_id,
       opening_stock, moisture_before_pct, moisture_after_pct, moisture_loss_pct, weight_loss_kg, final_stock,
       reason, remarks, attachment_path, approved_by, approval_date, notes, created_by)
      VALUES (?,?,?,?,?,?, ?,?,?,?,?,?,?,?, ?,?,?,?,?,?, ?,?,?,?,?,?,?)');
    $stmt->execute([
      $adjustmentNo, $d['adjustment_date'], $d['adjustment_type'] ?? 'Moisture Loss', $d['warehouse'] ?? 'Main Warehouse',
      $d['reference_no'] ?? '', $d['reference_date'] ?: null,
      $productId, $d['variety_grade'] ?? '', $d['grade'] ?? '', $d['unit'] ?? 'Kg', $d['batch_no'] ?? '',
      $d['manufacture_date'] ?: null, $d['expiry_date'] ?: null, !empty($d['supplier_id']) ? (int)$d['supplier_id'] : null,
      $opening, $moistBefore, $moistAfter, $moistLoss, $weightLoss, $finalStock,
      $d['reason'] ?? '', $d['remarks'] ?? '', $attachmentPath, $d['approved_by'] ?? '', $d['approval_date'] ?: null,
      $d['notes'] ?? '', (int)($_SESSION['user_id'] ?? 0),
    ]);
    $adjId = (int)$db->lastInsertId();

    writeAdjustmentLedger($db, $productId, $adjId, $weightLoss, $d['adjustment_date'], ($d['adjustment_type'] ?? 'Adjustment') . ' — ' . $adjustmentNo, $d['warehouse'] ?? 'Main Warehouse', $d['batch_no'] ?? '');

    logActivity((int)($_SESSION['user_id'] ?? 0), 'create', 'stock_adjustment', $adjId, 'Stock adjustment: ' . $adjustmentNo);
    jsonResponse(['success' => true, 'id' => $adjId, 'adjustment_no' => $adjustmentNo, 'final_stock' => $finalStock]);
    break;

  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $db->prepare('DELETE FROM stock_ledger WHERE ref_type = "adjustment" AND ref_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM stock_adjustments WHERE id = ?')->execute([$id]);
    logActivity((int)($_SESSION['user_id'] ?? 0), 'delete', 'stock_adjustment', $id, 'Stock adjustment deleted');
    jsonResponse(['success' => true]);
    break;

  default:
    jsonResponse(['error' => 'Method not allowed'], 405);
}
} catch (Throwable $e) {
  jsonResponse(['error' => 'Stock Adjustments API error: ' . $e->getMessage()], 500);
}
