<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

function cleanId($v) {
  if (empty($v)) return null;
  $n = (int) preg_replace('/\D/', '', (string)$v);
  return $n > 0 ? $n : null;
}

try {
  if ($method !== 'GET') jsonResponse(['error' => 'Method not allowed'], 405);

  $productId = cleanId($_GET['product_id'] ?? null);
  $batchNo   = $_GET['batch_no'] ?? '';
  $warehouse = $_GET['warehouse'] ?? '';
  $dateFrom  = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
  $dateTo    = $_GET['date_to']   ?? date('Y-m-d');
  $txnType   = $_GET['transaction_type'] ?? ''; // in | out | adjustment
  $refType   = $_GET['reference_type'] ?? ''; // purchase | sale | adjustment | stock_in

  $where = ['sl.movement_date BETWEEN ? AND ?'];
  $params = [$dateFrom, $dateTo];
  if ($productId) { $where[] = 'sl.product_id = ?'; $params[] = $productId; }
  if ($batchNo)   { $where[] = 'sl.batch_no = ?'; $params[] = $batchNo; }
  if ($warehouse) { $where[] = 'sl.warehouse = ?'; $params[] = $warehouse; }
  if ($refType)   { $where[] = 'sl.ref_type = ?'; $params[] = $refType; }
  if ($txnType === 'in')  $where[] = 'sl.direction = "in"';
  if ($txnType === 'out') $where[] = 'sl.direction = "out"';
  if ($txnType === 'adjustment') $where[] = 'sl.ref_type = "adjustment"';
  $whereSql = implode(' AND ', $where);

  $sql = "SELECT sl.*, p.name AS product_name,
      CASE sl.ref_type
        WHEN 'purchase'   THEN (SELECT purchase_no FROM purchases WHERE id = sl.ref_id)
        WHEN 'stock_in'   THEN (SELECT reference_no FROM stock_in_entries WHERE id = sl.ref_id)
        WHEN 'sale'       THEN (SELECT invoice_no FROM sales WHERE id = sl.ref_id)
        WHEN 'adjustment' THEN (SELECT adjustment_no FROM stock_adjustments WHERE id = sl.ref_id)
      END AS reference_no
    FROM stock_ledger sl
    JOIN products p ON p.id = sl.product_id
    WHERE $whereSql
    ORDER BY sl.movement_date ASC, sl.id ASC";
  $stmt = $db->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();

  // Opening stock (per product, not one mixed number): each product's real
  // balance immediately before date_from, under the same batch/warehouse
  // filters. Summing distinct products' openings is still a meaningful
  // total ("stock on hand across everything, at the start of the period"),
  // but a single running-balance column mixing different products together
  // as it walks through rows would not be — so that's tracked per product.
  function openingFor($db, $pid, $dateFrom, $batchNo, $warehouse) {
    $w = ['product_id = ?', 'movement_date < ?'];
    $p = [$pid, $dateFrom];
    if ($batchNo)   { $w[] = 'batch_no = ?'; $p[] = $batchNo; }
    if ($warehouse) { $w[] = 'warehouse = ?'; $p[] = $warehouse; }
    $stmt = $db->prepare('SELECT COALESCE(SUM(CASE WHEN direction="in" THEN qty ELSE -qty END),0) bal FROM stock_ledger WHERE ' . implode(' AND ', $w));
    $stmt->execute($p);
    return (float)$stmt->fetch()['bal'];
  }

  $totalIn = 0; $totalOut = 0;
  $productRunning = []; // product_id => running balance, tracked independently per product
  $openingStock = 0;
  foreach ($rows as &$r) {
    $pid = $r['product_id'];
    if (!isset($productRunning[$pid])) {
      $productRunning[$pid] = openingFor($db, $pid, $dateFrom, $batchNo, $warehouse);
      $openingStock += $productRunning[$pid]; // sum of each distinct product's own opening balance
    }
    if ($r['direction'] === 'in') { $totalIn += (float)$r['qty']; $productRunning[$pid] += (float)$r['qty']; }
    else { $totalOut += (float)$r['qty']; $productRunning[$pid] -= (float)$r['qty']; }
    $r['running_balance'] = $productRunning[$pid];
  }
  unset($r);
  // Closing stock = sum of every touched product's final balance. If a
  // specific product is selected there's only one, so this still works
  // for the single-product case exactly as before.
  $closingStock = array_sum($productRunning);

  // Current stock value (weighted avg cost × current stock), for the stat card
  $valStmt = $db->prepare("SELECT
      COALESCE(SUM(CASE WHEN direction='in' THEN qty ELSE -qty END),0) AS stock,
      COALESCE(SUM(CASE WHEN direction='in' THEN qty*rate ELSE 0 END),0) AS in_value,
      COALESCE(SUM(CASE WHEN direction='in' THEN qty ELSE 0 END),0) AS in_qty
    FROM stock_ledger WHERE product_id " . ($productId ? '= ?' : 'IS NOT NULL'));
  $valParams = $productId ? [$productId] : [];
  $valStmt->execute($valParams);
  $val = $valStmt->fetch();
  $avgCost = $val['in_qty'] > 0 ? $val['in_value'] / $val['in_qty'] : 0;
  $currentStockValue = round($val['stock'] * $avgCost, 2);

  jsonResponse([
    'data' => $rows,
    'stats' => [
      'opening_stock' => round($openingStock, 3),
      'total_in' => round($totalIn, 3),
      'total_out' => round($totalOut, 3),
      'closing_stock' => round($closingStock, 3),
      'current_stock_value' => $currentStockValue,
    ],
  ]);
} catch (Throwable $e) {
  jsonResponse(['error' => 'Stock History API error: ' . $e->getMessage()], 500);
}
