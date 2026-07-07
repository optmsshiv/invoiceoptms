<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

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

    // Summary: current stock on hand per product
    $stmt = $db->query('SELECT
        p.id AS product_id, p.name, p.category, p.hsn,
        COALESCE(SUM(CASE WHEN sl.direction="in" THEN sl.qty ELSE -sl.qty END), 0) AS current_stock,
        MAX(sl.movement_date) AS last_movement
      FROM products p
      LEFT JOIN stock_ledger sl ON sl.product_id = p.id
      GROUP BY p.id, p.name, p.category, p.hsn
      HAVING current_stock <> 0 OR last_movement IS NOT NULL
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

    $pid = (int)$d['product_id'];
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
