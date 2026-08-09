<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Product IDs arrive from the frontend as "p12" (Products page convention) —
// strip any non-digit characters before using as an int FK.
function cleanProductId($v) {
  if (empty($v)) return null;
  $n = (int) preg_replace('/\D/', '', (string)$v);
  return $n > 0 ? $n : null;
}

try {
// Self-heal: stock_ledger was never created for some tenants. Same
// definition as in purchases.php/sales.php (all three write here).
$db->exec("CREATE TABLE IF NOT EXISTS `stock_ledger` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `ref_type` VARCHAR(30) NOT NULL DEFAULT 'adjustment',
  `ref_id` INT UNSIGNED NULL,
  `direction` ENUM('in','out') NOT NULL,
  `qty` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `rate` DECIMAL(12,4) NOT NULL DEFAULT 0,
  `balance_after` DECIMAL(14,3) NOT NULL DEFAULT 0,
  `movement_date` DATE NOT NULL,
  `notes` VARCHAR(255) DEFAULT '',
  `warehouse` VARCHAR(100) DEFAULT 'Main Warehouse',
  `batch_no` VARCHAR(60) DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_sl_product` (`product_id`),
  INDEX `idx_sl_ref` (`ref_type`,`ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

switch ($method) {
  case 'GET':
    // Per-product movement history (chronological, with running balance recomputed live
    // so it's always correct even if past purchases/sales were edited afterwards)
    if (!empty($_GET['product_id'])) {
      $pid = (int)$_GET['product_id'];
      $stmt = $db->prepare('SELECT * FROM stock_ledger WHERE product_id = ? ORDER BY movement_date ASC, id ASC');
      $stmt->execute([$pid]);
      $rows = $stmt->fetchAll();
      $running = 0;
      foreach ($rows as &$r) {
        $running += ($r['direction'] === 'in' ? 1 : -1) * (float)$r['qty'];
        $r['running_balance'] = $running;
      }
      unset($r);
      jsonResponse(['data' => array_reverse($rows)]); // most recent first for display
      break;
    }

    // Summary: current stock on hand per product — includes ALL products
    // (even those with zero or no ledger entries) so the dashboard Top
    // Products and stock donut are never silently blank.
    $stmt = $db->query('SELECT
        p.id AS product_id, p.name, p.category,
        COALESCE(SUM(CASE WHEN sl.direction="in" THEN sl.qty ELSE -sl.qty END), 0) AS current_stock,
        MAX(sl.movement_date) AS last_movement
      FROM products p
      LEFT JOIN stock_ledger sl ON sl.product_id = p.id
      WHERE p.status = "active" OR p.status IS NULL
      GROUP BY p.id, p.name, p.category
      ORDER BY p.name ASC');
    jsonResponse(['data' => $stmt->fetchAll()]);
    break;

  case 'POST':
    // Manual stock adjustment (damage, recount, opening stock correction, etc.)
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);
    if (empty($d['product_id']))    jsonResponse(['error' => 'Product is required'], 400);
    if (empty($d['direction']) || !in_array($d['direction'], ['in','out'])) jsonResponse(['error' => 'Direction must be in or out'], 400);
    if (empty($d['qty']) || (float)$d['qty'] <= 0) jsonResponse(['error' => 'Quantity must be greater than 0'], 400);
    if (empty($d['movement_date'])) jsonResponse(['error' => 'Date is required'], 400);

    $pid = cleanProductId($d['product_id']);
    if (!$pid) jsonResponse(['error' => 'Invalid product'], 400);
    $bal = $db->prepare('SELECT COALESCE(SUM(CASE WHEN direction="in" THEN qty ELSE -qty END),0) AS bal FROM stock_ledger WHERE product_id = ?');
    $bal->execute([$pid]);
    $current = (float)$bal->fetch()['bal'];
    $qty = (float)$d['qty'];
    $newBal = $d['direction'] === 'in' ? $current + $qty : $current - $qty;

    $stmt = $db->prepare('INSERT INTO stock_ledger (product_id, ref_type, ref_id, direction, qty, rate, balance_after, movement_date, notes) VALUES (?,"adjustment",NULL,?,?,?,?,?,?)');
    $stmt->execute([$pid, $d['direction'], $qty, (float)($d['rate'] ?? 0), $newBal, $d['movement_date'], $d['notes'] ?? 'Manual adjustment']);

    logActivity((int)$_SESSION['user_id'], 'create', 'stock_adjustment', (int)$db->lastInsertId(), 'Stock adjustment: ' . $d['direction'] . ' ' . $qty);
    jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'new_balance' => $newBal]);
    break;

  case 'DELETE':
    // Only manual adjustment entries can be removed directly — purchase/sale-derived
    // entries must be corrected by editing/deleting the source purchase or invoice,
    // so stock always stays consistent with the documents that generated it.
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $row = $db->prepare('SELECT ref_type FROM stock_ledger WHERE id = ?');
    $row->execute([$id]);
    $found = $row->fetch();
    if (!$found) jsonResponse(['error' => 'Not found'], 404);
    if ($found['ref_type'] !== 'adjustment') jsonResponse(['error' => 'Only manual adjustments can be deleted directly'], 400);
    $db->prepare('DELETE FROM stock_ledger WHERE id = ?')->execute([$id]);
    logActivity((int)$_SESSION['user_id'], 'delete', 'stock_adjustment', $id, 'Stock adjustment deleted');
    jsonResponse(['success' => true]);
    break;

  default:
    jsonResponse(['error' => 'Method not allowed'], 405);
}
} catch (Throwable $e) {
  // Most likely cause here: the migration SQL wasn't run yet (stock_ledger/purchases
  // tables don't exist), or a column name doesn't match your actual schema.
  jsonResponse(['error' => 'Stock API error: ' . $e->getMessage()], 500);
}