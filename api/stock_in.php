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
function writeStockInEntry($db, $productId, $stockInId, $qty, $rate, $date, $note, $warehouse = 'Main Warehouse', $batchNo = '') {
  if ($qty <= 0) return;
  $bal = currentStock($db, $productId) + $qty;
  $stmt = $db->prepare('INSERT INTO stock_ledger (product_id, ref_type, ref_id, direction, qty, rate, balance_after, movement_date, notes, warehouse, batch_no) VALUES (?,"stock_in",?,"in",?,?,?,?,?,?,?)');
  $stmt->execute([$productId, $stockInId, $qty, $rate, $bal, $date, $note, $warehouse, $batchNo]);
}
function clearStockForEntry($db, $stockInId) {
  $db->prepare('DELETE FROM stock_ledger WHERE ref_type = "stock_in" AND ref_id = ?')->execute([$stockInId]);
}
function saveAttachment($dataUrl, $subdir) {
  if (!$dataUrl || !preg_match('/^data:(image\/(png|jpe?g|webp)|application\/pdf);base64,(.+)$/', $dataUrl, $m)) return null;
  $ext  = str_contains($m[1], 'pdf') ? 'pdf' : ($m[2] === 'jpeg' ? 'jpg' : $m[2]);
  $blob = base64_decode($m[3]);
  if ($blob === false || strlen($blob) > (defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 5242880)) return null;
  $dir = rtrim(defined('UPLOAD_PATH') ? UPLOAD_PATH : (__DIR__ . '/../assets/uploads/'), '/') . '/' . $subdir;
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $fname = 'sti_' . bin2hex(random_bytes(8)) . '.' . $ext;
  file_put_contents($dir . '/' . $fname, $blob);
  return '/assets/uploads/' . $subdir . '/' . $fname;
}

try {
switch ($method) {
  case 'GET':
    if (!empty($_GET['id'])) {
      $id = (int)$_GET['id'];
      $stmt = $db->prepare('SELECT si.*, s.name AS supplier_name FROM stock_in_entries si LEFT JOIN suppliers s ON s.id = si.supplier_id WHERE si.id = ?');
      $stmt->execute([$id]);
      $row = $stmt->fetch();
      if (!$row) jsonResponse(['error' => 'Not found'], 404);
      $itemsStmt = $db->prepare('SELECT sti.*, p.name AS product_name FROM stock_in_items sti LEFT JOIN products p ON p.id = sti.product_id WHERE sti.stock_in_id = ? ORDER BY sti.id ASC');
      $itemsStmt->execute([$id]);
      $row['items'] = $itemsStmt->fetchAll();
      $row['attachments'] = $row['attachments'] ? json_decode($row['attachments'], true) : [];
      jsonResponse(['data' => $row]);
      break;
    }
    $limit = (int)($_GET['limit'] ?? 0);
    $sql = 'SELECT si.*, (SELECT COUNT(*) FROM stock_in_items x WHERE x.stock_in_id = si.id) AS item_count
      FROM stock_in_entries si ORDER BY si.reference_date DESC, si.id DESC';
    if ($limit > 0) $sql .= ' LIMIT ' . $limit;
    jsonResponse(['data' => $db->query($sql)->fetchAll()]);
    break;

  case 'POST':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);
    if (empty($d['reference_date'])) jsonResponse(['error' => 'Reference date is required'], 400);
    $items = $d['items'] ?? [];
    if (!is_array($items) || count($items) === 0) jsonResponse(['error' => 'At least one product is required'], 400);

    $refNo = trim($d['reference_no'] ?? '');
    if ($refNo === '') {
      $y = date('y'); $y2 = date('y', strtotime('+1 year'));
      $cnt = $db->query('SELECT COUNT(*) c FROM stock_in_entries')->fetch()['c'] + 1;
      $refNo = "STK/IN/{$y}-{$y2}/" . str_pad($cnt, 5, '0', STR_PAD_LEFT);
    }

    $totalQty = 0; $totalAmt = 0;
    foreach ($items as $it) { $totalQty += (float)($it['qty'] ?? 0); $totalAmt += (float)($it['qty'] ?? 0) * (float)($it['rate'] ?? 0); }

    $slipPath = saveAttachment($d['slip'] ?? null, 'stock_in');
    $attachments = array_values(array_filter(array_map(function($a) {
      return (is_string($a) && str_starts_with($a, 'data:')) ? saveAttachment($a, 'stock_in') : $a;
    }, $d['attachments'] ?? [])));

    $stmt = $db->prepare('INSERT INTO stock_in_entries
      (reference_no, reference_date, warehouse, stock_in_type, remarks,
       weighing_type, weighbridge_name, weighbridge_slip_no, weight_datetime, gross_weight, tare_weight, operator_name, slip_path,
       supplier_id, challan_no, challan_date, vehicle_no, driver_name,
       attachments, total_quantity, total_amount, created_by)
      VALUES (?,?,?,?,?, ?,?,?,?,?,?,?,?, ?,?,?,?,?, ?,?,?,?)');
    $stmt->execute([
      $refNo, $d['reference_date'], $d['warehouse'] ?? 'Main Warehouse', $d['stock_in_type'] ?? 'Purchase', $d['remarks'] ?? '',
      $d['weighing_type'] ?? 'Own Weighbridge', $d['weighbridge_name'] ?? '', $d['weighbridge_slip_no'] ?? '', $d['weight_datetime'] ?: null,
      (float)($d['gross_weight'] ?? 0), (float)($d['tare_weight'] ?? 0), $d['operator_name'] ?? '', $slipPath,
      !empty($d['supplier_id']) ? (int)$d['supplier_id'] : null, $d['challan_no'] ?? '', $d['challan_date'] ?: null,
      $d['vehicle_no'] ?? '', $d['driver_name'] ?? '',
      json_encode($attachments), $totalQty, $totalAmt, (int)($_SESSION['user_id'] ?? 0),
    ]);
    $stockInId = (int)$db->lastInsertId();

    $itemStmt = $db->prepare('INSERT INTO stock_in_items (stock_in_id, product_id, variety, grade, batch_no, mfg_date, expiry_date, qty, rate, amount) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ($items as $it) {
      $productId = cleanProductId($it['product_id'] ?? null);
      if (!$productId) continue;
      $qty = (float)($it['qty'] ?? 0);
      $rate = (float)($it['rate'] ?? 0);
      $amount = round($qty * $rate, 2);
      $itemStmt->execute([$stockInId, $productId, $it['variety'] ?? '', $it['grade'] ?? '', $it['batch_no'] ?? '', $it['mfg_date'] ?: null, $it['expiry_date'] ?: null, $qty, $rate, $amount]);
      writeStockInEntry($db, $productId, $stockInId, $qty, $rate, $d['reference_date'], 'Stock In ' . $refNo, $d['warehouse'] ?? 'Main Warehouse', $it['batch_no'] ?? '');
    }

    logActivity((int)($_SESSION['user_id'] ?? 0), 'create', 'stock_in', $stockInId, 'Stock In: ' . $refNo);
    jsonResponse(['success' => true, 'id' => $stockInId, 'reference_no' => $refNo]);
    break;

  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    clearStockForEntry($db, $id);
    $db->prepare('DELETE FROM stock_in_entries WHERE id = ?')->execute([$id]);
    logActivity((int)($_SESSION['user_id'] ?? 0), 'delete', 'stock_in', $id, 'Stock In deleted');
    jsonResponse(['success' => true]);
    break;

  default:
    jsonResponse(['error' => 'Method not allowed'], 405);
}
} catch (Throwable $e) {
  jsonResponse(['error' => 'Stock In API error: ' . $e->getMessage()], 500);
}
