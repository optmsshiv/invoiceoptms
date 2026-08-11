<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
  if ($method !== 'GET') jsonResponse(['error' => 'Method not allowed'], 405);

  $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
  $dateTo   = $_GET['date_to']   ?? date('Y-m-d');
  $warehouse = $_GET['warehouse'] ?? '';

  // Previous period of equal length, for the vs-Previous-Period comparison
  $days = (strtotime($dateTo) - strtotime($dateFrom)) / 86400 + 1;
  $prevFrom = date('Y-m-d', strtotime($dateFrom . " -{$days} days"));
  $prevTo   = date('Y-m-d', strtotime($dateFrom . " -1 days"));

  $whWhereSales = $warehouse ? " AND EXISTS (SELECT 1 FROM sale_items si WHERE si.sale_id = sales.id AND si.warehouse = " . $db->quote($warehouse) . ")" : '';
  $whWherePur   = $warehouse ? ' AND warehouse = ' . $db->quote($warehouse) : '';

  function sumSales($db, $from, $to, $whClause) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(total),0) t, COALESCE(SUM(amount_received),0) r FROM sales WHERE sale_date BETWEEN ? AND ? AND status != 'Cancelled'" . $whClause);
    $stmt->execute([$from, $to]);
    return $stmt->fetch();
  }
  function sumPurchases($db, $from, $to, $whClause) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(total),0) t, COALESCE(SUM(amount_paid),0) p FROM purchases WHERE purchase_date BETWEEN ? AND ?" . $whClause);
    $stmt->execute([$from, $to]);
    return $stmt->fetch();
  }

  $curSales = sumSales($db, $dateFrom, $dateTo, $whWhereSales);
  $curPur   = sumPurchases($db, $dateFrom, $dateTo, $whWherePur);
  $prevSales = sumSales($db, $prevFrom, $prevTo, $whWhereSales);
  $prevPur   = sumPurchases($db, $prevFrom, $prevTo, $whWherePur);

  $pctChange = function($cur, $prev) {
    $cur = (float)$cur; $prev = (float)$prev;
    if ($prev == 0) return $cur > 0 ? 100 : 0;
    return round((($cur - $prev) / $prev) * 100, 1);
  };

  // Business expenses for the period
  $curExpStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) e FROM expenses WHERE `date` BETWEEN ? AND ?");
  $curExpStmt->execute([$dateFrom, $dateTo]);
  $curExp = $curExpStmt->fetchColumn();
  $prevExpStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) e FROM expenses WHERE `date` BETWEEN ? AND ?");
  $prevExpStmt->execute([$prevFrom, $prevTo]);
  $prevExp = $prevExpStmt->fetchColumn();

  $netProfit = (float)$curSales['t'] - (float)$curPur['t'] - (float)$curExp;
  $prevNetProfit = (float)$prevSales['t'] - (float)$prevPur['t'] - (float)$prevExp;

  $stats = [
    'total_sales'       => ['value' => (float)$curSales['t'], 'change' => $pctChange($curSales['t'], $prevSales['t'])],
    'total_purchase'    => ['value' => (float)$curPur['t'],   'change' => $pctChange($curPur['t'], $prevPur['t'])],
    'total_collections' => ['value' => (float)$curSales['r'], 'change' => $pctChange($curSales['r'], $prevSales['r'])],
    'total_payments'    => ['value' => (float)$curPur['p'],   'change' => $pctChange($curPur['p'], $prevPur['p'])],
    'net_profit'        => ['value' => $netProfit, 'change' => $pctChange($netProfit, $prevNetProfit)],
  ];

  // ── Daily trend: Income (Sales) vs Expense (Purchases) ──────────
  $trendStmt = $db->prepare("SELECT sale_date d, SUM(total) t FROM sales WHERE sale_date BETWEEN ? AND ? AND status != 'Cancelled' GROUP BY sale_date");
  $trendStmt->execute([$dateFrom, $dateTo]);
  $salesByDay = [];
  foreach ($trendStmt->fetchAll() as $r) $salesByDay[$r['d']] = (float)$r['t'];

  $trendPurStmt = $db->prepare("SELECT purchase_date d, SUM(total) t FROM purchases WHERE purchase_date BETWEEN ? AND ? GROUP BY purchase_date");
  $trendPurStmt->execute([$dateFrom, $dateTo]);
  $purByDay = [];
  foreach ($trendPurStmt->fetchAll() as $r) $purByDay[$r['d']] = (float)$r['t'];

  $trend = [];
  $cursor = strtotime($dateFrom);
  while ($cursor <= strtotime($dateTo)) {
    $d = date('Y-m-d', $cursor);
    // Fetch daily expenses for the trend
    $trend[] = ['date' => $d, 'income' => $salesByDay[$d] ?? 0, 'expense' => $purByDay[$d] ?? 0, 'biz_expense' => 0];
    $cursor = strtotime('+1 day', $cursor);
  }

  // Add business expenses to trend
  $expTrendStmt = $db->prepare("SELECT `date` d, SUM(amount) t FROM expenses WHERE `date` BETWEEN ? AND ? GROUP BY `date`");
  $expTrendStmt->execute([$dateFrom, $dateTo]);
  $expByDay = [];
  foreach ($expTrendStmt->fetchAll() as $r) $expByDay[$r['d']] = (float)$r['t'];
  foreach ($trend as &$t) $t['biz_expense'] = $expByDay[$t['date']] ?? 0;
  unset($t);

  // ── Income breakdown — only real source is Sales; no other-income
  // tracking exists in the system, so nothing else is fabricated here. ──
  $incomeHeads = [['head' => 'Sales', 'amount' => (float)$curSales['t']]];

  // ── Expense breakdown — real, from Purchases' subtotal + charge fields ──
  $expStmt = $db->prepare("SELECT
      COALESCE(SUM(subtotal),0) purchase_amt,
      COALESCE(SUM(transport_charge),0) transport_amt,
      COALESCE(SUM(loading_charge),0) loading_amt,
      COALESCE(SUM(packing_charge),0) packing_amt,
      COALESCE(SUM(other_charges),0) other_amt
    FROM purchases WHERE purchase_date BETWEEN ? AND ?" . $whWherePur);
  $expStmt->execute([$dateFrom, $dateTo]);
  $exp = $expStmt->fetch();
  $expenseHeads = [
    ['head' => 'Purchase',          'amount' => (float)$exp['purchase_amt']],
    ['head' => 'Transport Expense', 'amount' => (float)$exp['transport_amt']],
    ['head' => 'Loading Expense',   'amount' => (float)$exp['loading_amt']],
    ['head' => 'Packing Expense',   'amount' => (float)$exp['packing_amt']],
    ['head' => 'Other Expenses',    'amount' => (float)$exp['other_amt']],
  ];
  $expenseHeads = array_values(array_filter($expenseHeads, fn($e) => $e['amount'] > 0));

  // ── Payment mode summaries — kept SEPARATE by nature of money flow.
  // Previously these were merged into one map, which silently added
  // "cash received from sales" to "cash paid for purchases" as if they
  // were the same number — meaningless for reading cash flow. Now each
  // gets its own breakdown + its own subtotal.
  function modeBreakdown($db, $sql, $params) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $map = [];
    foreach ($stmt->fetchAll() as $r) {
      $m = str_starts_with($r['m'], 'Split:') ? 'Split Payment' : $r['m'];
      $map[$m] = ($map[$m] ?? 0) + (float)$r['a'];
    }
    arsort($map);
    $out = [];
    foreach ($map as $mode => $amt) $out[] = ['mode' => $mode, 'amount' => $amt];
    return $out;
  }

  $paymentModesSales = modeBreakdown($db,
    "SELECT payment_method m, SUM(amount_received) a FROM sales
     WHERE sale_date BETWEEN ? AND ? AND payment_method != '' GROUP BY payment_method",
    [$dateFrom, $dateTo]);

  $paymentModesPurchases = modeBreakdown($db,
    "SELECT payment_mode m, SUM(amount_paid) a FROM purchases
     WHERE purchase_date BETWEEN ? AND ? AND payment_mode != ''" . $whWherePur . " GROUP BY payment_mode",
    [$dateFrom, $dateTo]);

  // Expenses — its own separate card, not folded into Purchases
  $paymentModesExpenses = modeBreakdown($db,
    "SELECT method m, SUM(amount) a FROM expenses
     WHERE `date` BETWEEN ? AND ? AND method != '' GROUP BY method",
    [$dateFrom, $dateTo]);

  // ── Trade Summary: Kg quantities + dhalta (for agri businesses) ──
  $tradeStmt = $db->prepare("
    SELECT
      COALESCE(SUM(si.qty),0)              AS sale_qty,
      COALESCE(SUM(si.line_total),0)       AS sale_value,
      COALESCE(SUM(s.kanta_gross_weight),0) AS sale_gross_wt,
      COALESCE(SUM(s.kanta_tare_weight),0)  AS sale_tare_wt,
      COALESCE(SUM(s.kanta_dhalta_kg),0)    AS sale_dhalta_kg
    FROM sale_items si
    JOIN sales s ON s.id = si.sale_id
    WHERE s.sale_date BETWEEN ? AND ?
      AND s.status != 'Cancelled'" . $whWhereSales);
  $tradeStmt->execute([$dateFrom, $dateTo]);
  $tradeS = $tradeStmt->fetch();

  // Use purchase-level total (not item-level amount) so the value matches
  // the Finance Report card exactly — both now read from purchases.total.
  // Qty/weight/dhalta still come from purchase_items (item level).
  $tradePurStmt = $db->prepare("
    SELECT
      COALESCE(SUM(pi.qty),0)             AS pur_qty,
      COALESCE(SUM(pi.dhalta_kg),0)       AS dhalta_kg,
      COALESCE(SUM(pi.gross_weight),0)    AS gross_wt,
      COALESCE(SUM(pi.tare_weight),0)     AS tare_wt,
      COALESCE(SUM(pi.billable_weight),0) AS billable_wt
    FROM purchase_items pi
    JOIN purchases p ON p.id = pi.purchase_id
    WHERE p.purchase_date BETWEEN ? AND ?" . $whWherePur);
  $tradePurStmt->execute([$dateFrom, $dateTo]);
  $tradeP = $tradePurStmt->fetch();

  // Purchase value from bill totals (distinct per bill, avoids multi-item multiplication)
  $purValStmt = $db->prepare("SELECT COALESCE(SUM(total),0) pur_value FROM purchases WHERE purchase_date BETWEEN ? AND ?" . $whWherePur);
  $purValStmt->execute([$dateFrom, $dateTo]);
  $purVal = $purValStmt->fetchColumn();

  // Top products by sale qty
  $topProdStmt = $db->prepare("
    SELECT p.name, SUM(si.qty) qty, SUM(si.line_total) value
    FROM sale_items si
    JOIN sales s ON s.id = si.sale_id
    JOIN products p ON p.id = si.product_id
    WHERE s.sale_date BETWEEN ? AND ? AND s.status != 'Cancelled'
    GROUP BY si.product_id, p.name ORDER BY qty DESC LIMIT 10");
  $topProdStmt->execute([$dateFrom, $dateTo]);
  $topProducts = $topProdStmt->fetchAll();

  // Dhalta detail by product
  $dhaltaStmt = $db->prepare("
    SELECT p.name, SUM(pi.dhalta_kg) dhalta_kg, SUM(pi.qty) total_qty,
           ROUND(SUM(pi.dhalta_kg)/NULLIF(SUM(pi.qty),0)*100,2) dhalta_pct
    FROM purchase_items pi
    JOIN purchases pu ON pu.id = pi.purchase_id
    JOIN products p ON p.id = pi.product_id
    WHERE pu.purchase_date BETWEEN ? AND ?
      AND pi.dhalta_kg > 0
    GROUP BY pi.product_id, p.name ORDER BY dhalta_kg DESC LIMIT 10");
  $dhaltaStmt->execute([$dateFrom, $dateTo]);
  $dhaltaDetail = $dhaltaStmt->fetchAll();

  $tradeSummary = [
    'sale_qty'        => (float)$tradeS['sale_qty'],
    'sale_value'      => (float)$tradeS['sale_value'],
    'sale_gross_wt'   => (float)$tradeS['sale_gross_wt'],
    'sale_tare_wt'    => (float)$tradeS['sale_tare_wt'],
    'sale_dhalta_kg'  => (float)$tradeS['sale_dhalta_kg'],
    'pur_qty'         => (float)$tradeP['pur_qty'],
    'pur_value'       => (float)$purVal,
    'pur_item_value'  => (float)$exp['purchase_amt'],
    'transport_amt'   => (float)$exp['transport_amt'],
    'loading_amt'     => (float)$exp['loading_amt'],
    'packing_amt'     => (float)$exp['packing_amt'],
    'other_amt'       => (float)$exp['other_amt'],
    'dhalta_kg'     => (float)$tradeP['dhalta_kg'],
    'gross_wt'      => (float)$tradeP['gross_wt'],
    'tare_wt'       => (float)$tradeP['tare_wt'],
    'billable_wt'   => (float)$tradeP['billable_wt'],
    'top_products'  => $topProducts,
    'dhalta_detail' => $dhaltaDetail,
  ];

  // Expenses
  $expStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) total, COUNT(*) cnt FROM expenses WHERE `date` BETWEEN ? AND ?");
  $expStmt->execute([$dateFrom, $dateTo]);
  $expData = $expStmt->fetch();
  $expByCatStmt = $db->prepare("SELECT category, SUM(amount) total FROM expenses WHERE `date` BETWEEN ? AND ? GROUP BY category ORDER BY total DESC");
  $expByCatStmt->execute([$dateFrom, $dateTo]);
  $expByCategory = $expByCatStmt->fetchAll();

  jsonResponse([
    'stats' => $stats,
    'trend' => $trend,
    'income_heads' => $incomeHeads,
    'expense_heads' => $expenseHeads,
    'payment_modes_sales'     => $paymentModesSales,
    'payment_modes_purchases' => $paymentModesPurchases,
    'payment_modes_expenses'  => $paymentModesExpenses,
    'cash_flow' => [
      'total_collections' => (float)$curSales['r'],
      'total_payments' => (float)$curPur['p'],
      'net_flow' => (float)$curSales['r'] - (float)$curPur['p'],
    ],
    'trade_summary' => $tradeSummary,
    'expenses' => [
      'total'       => (float)$expData['total'],
      'count'       => (int)$expData['cnt'],
      'by_category' => $expByCategory,
    ],
  ]);
} catch (Throwable $e) {
  jsonResponse(['error' => 'Finance Report API error: ' . $e->getMessage()], 500);
}