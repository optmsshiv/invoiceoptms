<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
  if ($method !== 'GET') jsonResponse(['error' => 'Method not allowed'], 405);

  // ── Movement summary + trend — defaults to the last 7 real calendar
  // days, or a specific date range when one's provided (e.g. the Global
  // Date Range filter). Capped at 31 days even if a wider range is given —
  // this does 4 queries per day, so an uncapped multi-month range would be
  // both slow and produce an unreadably long table.
  if (!empty($_GET['movement_summary'])) {
    $fromParam = $_GET['from'] ?? null;
    $toParam   = $_GET['to'] ?? null;

    if ($fromParam && $toParam) {
      $days = [];
      $cursor = strtotime($toParam);
      $limit  = strtotime($fromParam);
      while ($cursor >= $limit && count($days) < 31) {
        $days[] = date('Y-m-d', $cursor);
        $cursor = strtotime('-1 day', $cursor);
      }
      $days = array_reverse($days); // chronological order
      $truncated = (strtotime($toParam) - strtotime($fromParam)) / 86400 + 1 > 31;
    } else {
      $days = [];
      for ($i = 6; $i >= 0; $i--) $days[] = date('Y-m-d', strtotime("-$i days"));
      $truncated = false;
    }

    $rows = [];
    foreach ($days as $day) {
      $openStmt = $db->prepare('SELECT COALESCE(SUM(CASE WHEN direction="in" THEN qty ELSE -qty END),0) AS bal FROM stock_ledger WHERE movement_date < ?');
      $openStmt->execute([$day]);
      $opening = (float)$openStmt->fetch()['bal'];

      $inStmt = $db->prepare('SELECT COALESCE(SUM(qty),0) AS q FROM stock_ledger WHERE movement_date = ? AND direction = "in"');
      $inStmt->execute([$day]);
      $stockIn = (float)$inStmt->fetch()['q'];

      $outSaleStmt = $db->prepare('SELECT COALESCE(SUM(qty),0) AS q FROM stock_ledger WHERE movement_date = ? AND direction = "out" AND ref_type = "sale"');
      $outSaleStmt->execute([$day]);
      $stockOut = (float)$outSaleStmt->fetch()['q'];

      $adjStmt = $db->prepare('SELECT COALESCE(SUM(qty),0) AS q, ref_type FROM stock_ledger WHERE movement_date = ? AND direction = "out" AND ref_type = "adjustment"');
      $adjStmt->execute([$day]);
      $adjustment = (float)$adjStmt->fetch()['q'];

      $closing = $opening + $stockIn - $stockOut - $adjustment;
      $rows[] = [
        'date' => $day, 'opening_stock' => $opening, 'stock_in' => $stockIn,
        'stock_out' => $stockOut, 'adjustment' => $adjustment, 'closing_stock' => $closing,
      ];
    }
    jsonResponse(['data' => $rows, 'truncated_to_31_days' => $truncated]);
  }

  // ── Batch/warehouse-level stock summary + dashboard stats ────
  // Starts from `products` (LEFT JOIN to stock_ledger) so every active
  // product always appears — even one with zero stock or no ledger
  // activity yet — instead of silently disappearing just because the
  // old INNER JOIN + HAVING structure only showed products that already
  // had a matching ledger row. Matches stock.php's pattern.
  $productFilter = cleanId($_GET['product_id'] ?? null);
  $warehouseFilter = $_GET['warehouse'] ?? '';
  $batchFilter = $_GET['batch_no'] ?? '';

  $where = ['(p.status = "active" OR p.status IS NULL)'];
  if ($productFilter) { $where[] = 'p.id = ?'; }
  $whereSql = implode(' AND ', $where);

  // Warehouse/batch filters apply to the LEFT-JOINed ledger rows, not the
  // product itself — kept in the JOIN condition (not WHERE) so filtering
  // by warehouse/batch doesn't wrongly hide products that simply don't
  // have a row in that specific warehouse/batch (a WHERE on joined
  // columns would incorrectly drop the LEFT JOIN's NULL rows entirely).
  $joinExtra = '';
  if ($warehouseFilter) $joinExtra .= ' AND sl.warehouse = ?';
  if ($batchFilter) $joinExtra .= ' AND sl.batch_no LIKE ?';

  // Params in the exact positional order they appear in the SQL below:
  // the JOIN clause (warehouse/batch) comes before the WHERE clause
  // (product filter) in the query text, so they must be bound in that
  // same order — not the order they're conceptually applied.
  $params = [];
  if ($warehouseFilter) $params[] = $warehouseFilter;
  if ($batchFilter) $params[] = '%' . $batchFilter . '%';
  if ($productFilter) $params[] = $productFilter;

  $sql = "SELECT
      p.id AS product_id, p.name, p.category, p.variety, p.reorder_level,
      COALESCE(sl.warehouse, 'Main Warehouse') AS warehouse, COALESCE(sl.batch_no, '') AS batch_no,
      COALESCE(SUM(CASE WHEN sl.direction='in' THEN sl.qty ELSE -sl.qty END), 0) AS available_stock,
      COALESCE(SUM(CASE WHEN sl.direction='in' THEN sl.qty*sl.rate ELSE 0 END), 0) AS in_value,
      COALESCE(SUM(CASE WHEN sl.direction='in' THEN sl.qty ELSE 0 END), 0) AS in_qty,
      MAX(CASE WHEN sl.direction='in' THEN sl.movement_date END) AS last_inward_date
    FROM products p
    LEFT JOIN stock_ledger sl ON sl.product_id = p.id{$joinExtra}
    WHERE $whereSql
    GROUP BY p.id, sl.warehouse, sl.batch_no
    ORDER BY p.name ASC, sl.warehouse ASC, sl.batch_no ASC";
  $stmt = $db->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();

  $totalStock = 0; $totalValue = 0; $inStockCount = 0; $lowStockCount = 0;
  $productIds = [];
  foreach ($rows as &$r) {
    $avgCost = $r['in_qty'] > 0 ? round($r['in_value'] / $r['in_qty'], 2) : 0;
    $r['avg_cost'] = $avgCost;
    $r['stock_value'] = round($r['available_stock'] * $avgCost, 2);
    // Reserved/In-Transit aren't tracked yet anywhere in the system (no
    // sales-order or in-transit-shipment concept exists) — reported as 0
    // rather than fabricated, until that's actually built.
    $r['reserved_stock'] = 0;
    $r['in_transit'] = 0;
    $r['total_stock'] = $r['available_stock'] + $r['reserved_stock'] + $r['in_transit'];
    unset($r['in_value'], $r['in_qty']);

    $totalStock += (float)$r['available_stock'];
    $totalValue += (float)$r['stock_value'];
    if ($r['available_stock'] > 0) $inStockCount++;
    if (!empty($r['reorder_level']) && $r['available_stock'] < (float)$r['reorder_level']) $lowStockCount++;
    $productIds[$r['product_id']] = true;
  }
  unset($r);

  jsonResponse([
    'data' => $rows,
    'stats' => [
      'total_products' => count($productIds),
      'total_stock' => round($totalStock, 2),
      'total_value' => round($totalValue, 2),
      'in_stock' => $inStockCount,
      'low_stock' => $lowStockCount,
    ],
  ]);

} catch (Throwable $e) {
  jsonResponse(['error' => 'Product Stock API error: ' . $e->getMessage()], 500);
}

function cleanId($v) {
  if (empty($v)) return null;
  $n = (int) preg_replace('/\D/', '', (string)$v);
  return $n > 0 ? $n : null;
}
