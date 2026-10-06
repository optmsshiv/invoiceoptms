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

  // ── Split-payment breakdown helpers ─────────────────────────────
  // There are two different ways a purchase/sale ends up "Split", and
  // each stores the real per-method amounts differently:
  //  1. Split at creation, in one entry — payment_mode/payment_method is
  //     literally the string "Split: Cash: ₹500.00 + UPI: ₹300.00", built
  //     by getPNESplitLabel()/getSNSplitLabel(). Parseable directly.
  //  2. Paid off over time through the Purchase/Sale Payments ledger with
  //     different methods on different payments — payment_mode/method is
  //     just the bare word "Split" (see recachePurchasePaid() /
  //     recacheSaleReceived()), with the real amounts sitting in the
  //     purchase_payments / sale_payments tables instead.
  // Both need to resolve to the same {method => amount} shape so the rest
  // of this file doesn't need to care which kind of split it's looking at.
  function parseSplitLabel(string $label): array {
    $out = [];
    if (!preg_match('/^Split:\s*(.+)$/', $label, $m)) return $out;
    foreach (explode(' + ', $m[1]) as $part) {
      if (preg_match('/^(.+?):\s*₹\s*([\d,]+\.?\d*)/', trim($part), $pm)) {
        $method = trim($pm[1]);
        $out[$method] = ($out[$method] ?? 0) + (float)str_replace(',', '', $pm[2]);
      }
    }
    return $out;
  }
  function getLedgerSplitBreakdown(PDO $db, string $type, int $id): array {
    $table = $type === 'purchases' ? 'purchase_payments' : 'sale_payments';
    $idCol = $type === 'purchases' ? 'purchase_id' : 'sale_id';
    $delCol = $type === 'purchases' ? 'purchase_deleted' : 'sale_deleted';
    $stmt = $db->prepare("SELECT method, SUM(amount) amt FROM `$table`
      WHERE `$idCol` = ? AND `$delCol` = 0 AND method IS NOT NULL AND method <> '' GROUP BY method");
    $stmt->execute([$id]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) { $out[$r['method']] = (float)$r['amt']; }
    return $out;
  }
  function getSplitBreakdown(PDO $db, string $type, int $id, string $rawMode): array {
    if (str_starts_with($rawMode, 'Split:')) return parseSplitLabel($rawMode);
    if ($rawMode === 'Split') return getLedgerSplitBreakdown($db, $type, $id);
    return [];
  }
  function isSplitMode(string $mode): bool {
    return $mode === 'Split' || str_starts_with($mode, 'Split:');
  }

  // ── Drill-down: the actual transactions behind one payment-mode total
  // on the Finance Report (e.g. "Bank Transfer — ₹7,34,550.75" under
  // Received/Paid Out/Expenses). Exits early — this isn't part of the
  // main report payload, it's fetched on-demand when a mode row is
  // clicked, using whatever date range/warehouse the report is currently
  // showing so the drill-down total always matches the summary figure.
  // Computes its own warehouse clauses rather than reusing $whWhereSales/
  // $whWherePur below, since those aren't defined yet at this point in
  // the file and this block exits before reaching them.
  if (($_GET['action'] ?? '') === 'paymode_detail') {
    $type = $_GET['type'] ?? '';
    $mode = $_GET['mode'] ?? '';
    if (!in_array($type, ['sales', 'purchases', 'expenses'], true) || $mode === '') {
      jsonResponse(['error' => 'type and mode are required'], 400);
    }
    $ddWhWhereSales = $warehouse ? " AND EXISTS (SELECT 1 FROM sale_items si WHERE si.sale_id = s.id AND si.warehouse = " . $db->quote($warehouse) . ")" : '';
    $ddWhWherePur   = $warehouse ? ' AND p.warehouse = ' . $db->quote($warehouse) : '';

    if ($type === 'expenses') {
      // No split concept for expenses — exact match, unchanged.
      $sql = "SELECT id, `date`, vendor AS party, category AS reference, amount
              FROM expenses WHERE `date` BETWEEN ? AND ? AND method = ?
              ORDER BY `date` DESC, id DESC";
      $stmt = $db->prepare($sql);
      $stmt->execute([$dateFrom, $dateTo, $mode]);
      jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // Split Payment itself was clicked — show every split transaction with
    // its full amount and its own composition. Clicking a REAL method
    // (e.g. "Cash") instead needs to also surface split transactions that
    // included Cash as one of their components — but only that method's
    // own portion as the amount, since that's what's actually counted in
    // the Cash bucket on the summary card, not the transaction's full total.
    $isSplitSummary = ($mode === 'Split Payment');

    if ($type === 'sales') {
      $sql = "SELECT s.id, s.sale_date AS `date`, c.name AS party, s.invoice_no AS reference,
                     s.amount_received AS amount, s.payment_method AS raw_mode
              FROM sales s JOIN customers c ON c.id = s.customer_id
              WHERE s.sale_date BETWEEN ? AND ? AND s.status != 'Cancelled'"
              . $ddWhWhereSales . " ORDER BY s.sale_date DESC, s.id DESC";
    } else { // purchases
      $sql = "SELECT p.id, p.purchase_date AS `date`, s.name AS party, p.purchase_no AS reference,
                     p.amount_paid AS amount, p.payment_mode AS raw_mode
              FROM purchases p JOIN suppliers s ON s.id = p.supplier_id
              WHERE p.purchase_date BETWEEN ? AND ?"
              . $ddWhWherePur . " ORDER BY p.purchase_date DESC, p.id DESC";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute([$dateFrom, $dateTo]);

    $result = [];
    foreach ($stmt->fetchAll() as $r) {
      $rawMode = $r['raw_mode'];
      $split = isSplitMode($rawMode);

      if ($isSplitSummary) {
        if (!$split) continue;
        $breakdown = getSplitBreakdown($db, $type, (int)$r['id'], $rawMode);
        $chips = [];
        foreach ($breakdown as $method => $amt) $chips[] = ['method' => $method, 'amount' => $amt];
        $result[] = ['id' => $r['id'], 'date' => $r['date'], 'party' => $r['party'], 'reference' => $r['reference'],
                      'amount' => (float)$r['amount'], 'is_split' => true, 'breakdown' => $chips];
        continue;
      }

      if (!$split) {
        if ($rawMode !== $mode) continue;
        $result[] = ['id' => $r['id'], 'date' => $r['date'], 'party' => $r['party'], 'reference' => $r['reference'],
                      'amount' => (float)$r['amount'], 'is_split' => false];
        continue;
      }

      $breakdown = getSplitBreakdown($db, $type, (int)$r['id'], $rawMode);
      if (!isset($breakdown[$mode])) continue; // this method wasn't part of the split
      $chips = [];
      foreach ($breakdown as $method => $amt) $chips[] = ['method' => $method, 'amount' => $amt];
      $result[] = ['id' => $r['id'], 'date' => $r['date'], 'party' => $r['party'], 'reference' => $r['reference'],
                    'amount' => $breakdown[$mode], 'is_split' => true, 'breakdown' => $chips];
    }

    jsonResponse(['success' => true, 'data' => $result]);
  }

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

  // Same idea as modeBreakdown() above, but for sales/purchases, where a
  // "Split" transaction's real per-method amounts need to be attributed
  // to their actual methods (so Cash/UPI totals reflect real money
  // movement), while ALSO keeping a separate "Split Payment" line so it's
  // still visible which transactions were split ones. This deliberately
  // double-counts split amounts (once under their real method, once under
  // Split Payment) — the summary card's rows will not sum to the period
  // total, by design; that's the same rupee shown two different ways, not
  // new money. Needs per-row ids (not a GROUP BY) so each split row's real
  // breakdown can be looked up individually.
  function modeBreakdownWithSplit(PDO $db, string $type, string $sql, array $params): array {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $map = [];
    $splitTotal = 0;
    $splitBreakdown = [];

    foreach ($stmt->fetchAll() as $r) {
      $mode = $r['m'];
      $amt  = (float)$r['a'];

      if (!isSplitMode($mode)) {
        $map[$mode] = ($map[$mode] ?? 0) + $amt;
        continue;
      }

      $breakdown = getSplitBreakdown($db, $type, (int)$r['id'], $mode);
      if (!$breakdown) {
        // Split marker but no real breakdown found (e.g. the ledger rows
        // it depended on were since deleted) — fall back to a single
        // unattributed bucket rather than silently dropping the amount
        // from every total.
        $map['Split Payment'] = ($map['Split Payment'] ?? 0) + $amt;
        $splitTotal += $amt;
        continue;
      }
      foreach ($breakdown as $method => $portion) {
        $map[$method] = ($map[$method] ?? 0) + $portion;
        $splitBreakdown[$method] = ($splitBreakdown[$method] ?? 0) + $portion;
      }
      $splitTotal += $amt;
    }

    arsort($map);
    $out = [];
    foreach ($map as $mode => $amt) $out[] = ['mode' => $mode, 'amount' => $amt];

    if ($splitTotal > 0) {
      arsort($splitBreakdown);
      $chips = [];
      foreach ($splitBreakdown as $method => $amt) $chips[] = ['method' => $method, 'amount' => $amt];
      $out[] = ['mode' => 'Split Payment', 'amount' => $splitTotal, 'is_split_summary' => true, 'breakdown' => $chips];
    }

    return $out;
  }

  $paymentModesSales = modeBreakdownWithSplit($db, 'sales',
    "SELECT id, payment_method m, amount_received a FROM sales
     WHERE sale_date BETWEEN ? AND ? AND payment_method != ''",
    [$dateFrom, $dateTo]);

  $paymentModesPurchases = modeBreakdownWithSplit($db, 'purchases',
    "SELECT id, payment_mode m, amount_paid a FROM purchases
     WHERE purchase_date BETWEEN ? AND ? AND payment_mode != ''" . $whWherePur,
    [$dateFrom, $dateTo]);

  // Expenses — its own separate card, not folded into Purchases. No split
  // concept here, so the original simple GROUP BY breakdown is unchanged.
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
      AND s.status != 'Cancelled'" . ($warehouse ? ' AND si.warehouse = ' . $db->quote($warehouse) : ''));
  $tradeStmt->execute([$dateFrom, $dateTo]);
  $tradeS = $tradeStmt->fetch();

  // Sale weights, computed ONE ROW PER INVOICE. The old query summed the
  // sales-level kanta_* columns after joining sale_items, so a 3-item
  // invoice counted its weighbridge weights 3 times; and invoices entered
  // without a kanta slip contributed 0, which is why Net/Billable Wt showed
  // empty. Per invoice: if kanta gross > 0 use kanta net (gross − tare),
  // otherwise fall back to the invoice's own item quantities (Kg).
  $saleWtSql = "SELECT s.id, s.kanta_gross_weight g, s.kanta_tare_weight t, s.kanta_dhalta_kg d,
      (SELECT COALESCE(SUM(si.qty),0) FROM sale_items si WHERE si.sale_id = s.id"
      . ($warehouse ? ' AND si.warehouse = ' . $db->quote($warehouse) : '') . ") q
    FROM sales s
    WHERE s.sale_date BETWEEN ? AND ? AND s.status != 'Cancelled'"
    . ($warehouse ? " AND EXISTS (SELECT 1 FROM sale_items si2 WHERE si2.sale_id = s.id AND si2.warehouse = " . $db->quote($warehouse) . ")" : '');
  $saleWtStmt = $db->prepare($saleWtSql);
  $saleWtStmt->execute([$dateFrom, $dateTo]);
  $saleGross = $saleTare = $saleNet = $saleDhalta = $saleBillable = 0.0;
  $saleFromItems = 0;
  foreach ($saleWtStmt->fetchAll() as $w) {
    $g = (float)$w['g']; $t = (float)$w['t']; $d = (float)$w['d'];
    if ($g > 0) { $net = max(0, $g - $t); $saleGross += $g; $saleTare += $t; }
    else        { $net = (float)$w['q']; $saleFromItems++; }
    $saleNet      += $net;
    $saleDhalta   += $d;
    $saleBillable += max(0, $net - $d);
  }

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
    'sale_gross_wt'   => $saleGross,
    'sale_tare_wt'    => $saleTare,
    'sale_net_wt'     => $saleNet,
    'sale_dhalta_kg'  => $saleDhalta,
    'sale_billable_wt'=> $saleBillable,
    'sale_wt_from_items' => $saleFromItems,   // invoices with no kanta weight → used item qty
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

  // ── Bags Summary: number of bags purchased vs sold ───────────────
  // Source of truth is the per-line `bags` column on purchase_items /
  // sale_items (the same field the Purchase/Sale entry screens save).
  // Warehouse filter is applied at line level for sales (sale_items.warehouse)
  // so a multi-warehouse invoice only counts the bags from the selected one.
  // Cancelled sales are excluded, matching every other sales figure here.
  function bagsByProduct(PDO $db, string $kind, string $from, string $to, string $warehouse): array {
    if ($kind === 'purchase') {
      $sql = "SELECT COALESCE(pr.name, 'Other items') AS name,
                     SUM(pi.bags) AS bags, COUNT(DISTINCT p.id) AS bills
              FROM purchase_items pi
              JOIN purchases p ON p.id = pi.purchase_id
              LEFT JOIN products pr ON pr.id = pi.product_id
              WHERE p.purchase_date BETWEEN ? AND ? AND pi.bags > 0"
              . ($warehouse ? ' AND p.warehouse = ' . $db->quote($warehouse) : '')
              . " GROUP BY pi.product_id, pr.name";
    } else {
      $sql = "SELECT COALESCE(pr.name, 'Other items') AS name,
                     SUM(si.bags) AS bags, COUNT(DISTINCT s.id) AS bills
              FROM sale_items si
              JOIN sales s ON s.id = si.sale_id
              LEFT JOIN products pr ON pr.id = si.product_id
              WHERE s.sale_date BETWEEN ? AND ? AND s.status != 'Cancelled' AND si.bags > 0"
              . ($warehouse ? ' AND si.warehouse = ' . $db->quote($warehouse) : '')
              . " GROUP BY si.product_id, pr.name";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
  }

  $purBagRows  = bagsByProduct($db, 'purchase', $dateFrom, $dateTo, $warehouse);
  $saleBagRows = bagsByProduct($db, 'sale',     $dateFrom, $dateTo, $warehouse);

  // Merge both sides into one row per product name
  $bagsMap = [];
  foreach ($purBagRows as $r) {
    $bagsMap[$r['name']] = ['name' => $r['name'], 'pur_bags' => (float)$r['bags'], 'sale_bags' => 0.0];
  }
  foreach ($saleBagRows as $r) {
    if (!isset($bagsMap[$r['name']])) $bagsMap[$r['name']] = ['name' => $r['name'], 'pur_bags' => 0.0, 'sale_bags' => 0.0];
    $bagsMap[$r['name']]['sale_bags'] = (float)$r['bags'];
  }
  $bagsProducts = array_values($bagsMap);
  foreach ($bagsProducts as &$bp) { $bp['net_bags'] = $bp['pur_bags'] - $bp['sale_bags']; }
  unset($bp);
  usort($bagsProducts, fn($a, $b) => ($b['pur_bags'] + $b['sale_bags']) <=> ($a['pur_bags'] + $a['sale_bags']));

  $purBagsTotal  = array_sum(array_column($purBagRows,  'bags'));
  $saleBagsTotal = array_sum(array_column($saleBagRows, 'bags'));

  // Previous period (same length) for the % change under each tile
  $prevPurBags  = array_sum(array_column(bagsByProduct($db, 'purchase', $prevFrom, $prevTo, $warehouse), 'bags'));
  $prevSaleBags = array_sum(array_column(bagsByProduct($db, 'sale',     $prevFrom, $prevTo, $warehouse), 'bags'));

  // Distinct bills that carried bags (a bill with 3 products counts once)
  $purBillsStmt = $db->prepare("SELECT COUNT(DISTINCT p.id) FROM purchase_items pi JOIN purchases p ON p.id = pi.purchase_id
    WHERE p.purchase_date BETWEEN ? AND ? AND pi.bags > 0" . ($warehouse ? ' AND p.warehouse = ' . $db->quote($warehouse) : ''));
  $purBillsStmt->execute([$dateFrom, $dateTo]);
  $saleBillsStmt = $db->prepare("SELECT COUNT(DISTINCT s.id) FROM sale_items si JOIN sales s ON s.id = si.sale_id
    WHERE s.sale_date BETWEEN ? AND ? AND s.status != 'Cancelled' AND si.bags > 0" . ($warehouse ? ' AND si.warehouse = ' . $db->quote($warehouse) : ''));
  $saleBillsStmt->execute([$dateFrom, $dateTo]);

  $bagsSummary = [
    'purchased'     => ['value' => (float)$purBagsTotal,  'bills' => (int)$purBillsStmt->fetchColumn(),  'change' => $pctChange($purBagsTotal,  $prevPurBags)],
    'sold'          => ['value' => (float)$saleBagsTotal, 'bills' => (int)$saleBillsStmt->fetchColumn(), 'change' => $pctChange($saleBagsTotal, $prevSaleBags)],
    'net'           => (float)$purBagsTotal - (float)$saleBagsTotal,   // purchased − sold (+ = bags added to stock)
    'by_product'    => $bagsProducts,
  ];
  $tradeSummary['pur_bags']  = $bagsSummary['purchased']['value'];
  $tradeSummary['sale_bags'] = $bagsSummary['sold']['value'];

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
    'bags_summary'  => $bagsSummary,
    'expenses' => [
      'total'       => (float)$expData['total'],
      'count'       => (int)$expData['cnt'],
      'by_category' => $expByCategory,
    ],
  ]);
} catch (Throwable $e) {
  jsonResponse(['error' => 'Finance Report API error: ' . $e->getMessage()], 500);
}