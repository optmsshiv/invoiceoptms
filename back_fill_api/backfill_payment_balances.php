<?php
// ================================================================
//  api/backfill_payment_balances.php — ONE-TIME maintenance script
//
//  purchases.php and sales.php now recalculate every existing
//  purchase_payments/sale_payments row's remaining_amt automatically
//  whenever a Purchase/Sale's total is edited after payments were
//  already recorded against it (see recalcPurchasePaymentBalances /
//  recalcSalePaymentBalances). That fix only applies going forward —
//  any record whose total was already edited BEFORE that fix shipped
//  is still sitting on stale "Balance After" snapshots in its Payment
//  History. This script is the one-time catch-up for that existing
//  data — not something meant to run on a schedule or get wired into
//  any page.
//
//  Safe to re-run any number of times — fully idempotent. It always
//  recalculates each payment row from scratch against the record's
//  CURRENT total (not by applying a delta), so running it twice in a
//  row the second time reports zero rows changed.
//
//  GET  (no params)   → dry run: reports exactly what WOULD change,
//                        writes nothing. Always run this first.
//  GET  ?confirm=1    → actually applies the fixes found above.
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner'); // rewrites financial history rows — owner/super_admin only

header('Content-Type: application/json');
$db = getDB();
$confirm = !empty($_GET['confirm']);

// Shared by both Purchases and Sales below — same recalculation logic
// as recalcPurchasePaymentBalances()/recalcSalePaymentBalances(), just
// looped across every record instead of one at a time, and reporting
// what changed instead of assuming it's already correct.
function backfillTable(PDO $db, string $paymentsTable, string $fkCol, string $parentTable, string $deletedCol, bool $confirm): array {
  $affected = [];
  $totalRowsFixed = 0;

  $parents = $db->query("SELECT id, total FROM `{$parentTable}`")->fetchAll();
  $upd = $db->prepare("UPDATE `{$paymentsTable}` SET remaining_amt = ? WHERE id = ?");

  foreach ($parents as $parent) {
    $parentId = (int)$parent['id'];
    $newTotal = (float)$parent['total'];

    $rows = $db->prepare(
      "SELECT id, amount, remaining_amt FROM `{$paymentsTable}`
        WHERE `{$fkCol}` = ? AND `{$deletedCol}` = 0
        ORDER BY payment_date ASC, id ASC"
    );
    $rows->execute([$parentId]);

    $running = 0.0;
    $rowsFixedForThisParent = 0;
    foreach ($rows->fetchAll() as $r) {
      $running += (float)$r['amount'];
      $correct = max(0, round($newTotal - $running, 2));
      if (abs($correct - (float)$r['remaining_amt']) > 0.004) {
        $rowsFixedForThisParent++;
        if ($confirm) $upd->execute([$correct, $r['id']]);
      }
    }

    if ($rowsFixedForThisParent > 0) {
      $affected[] = ['id' => $parentId, 'rows_fixed' => $rowsFixedForThisParent];
      $totalRowsFixed += $rowsFixedForThisParent;
    }
  }

  return ['records_affected' => count($affected), 'rows_fixed' => $totalRowsFixed, 'detail' => $affected];
}

try {
  // Either table may not exist yet for a tenant that's never used
  // Purchases/Sales payments — non-fatal, just reports zero for that side.
  try {
    $purchaseResult = backfillTable($db, 'purchase_payments', 'purchase_id', 'purchases', 'purchase_deleted', $confirm);
  } catch (Throwable $e) {
    $purchaseResult = ['records_affected' => 0, 'rows_fixed' => 0, 'detail' => [], 'skipped' => $e->getMessage()];
  }
  try {
    $saleResult = backfillTable($db, 'sale_payments', 'sale_id', 'sales', 'sale_deleted', $confirm);
  } catch (Throwable $e) {
    $saleResult = ['records_affected' => 0, 'rows_fixed' => 0, 'detail' => [], 'skipped' => $e->getMessage()];
  }

  if ($confirm && ($purchaseResult['rows_fixed'] + $saleResult['rows_fixed']) > 0) {
    logActivity((int)($_SESSION['user_id'] ?? 0), 'backfill', 'payment_balances', 0,
      "Backfilled stale payment balances: {$purchaseResult['rows_fixed']} purchase-payment rows across {$purchaseResult['records_affected']} purchases, " .
      "{$saleResult['rows_fixed']} sale-payment rows across {$saleResult['records_affected']} sales");
  }

  jsonResponse([
    'success' => true,
    'mode' => $confirm ? 'applied' : 'dry_run',
    'note' => $confirm ? 'Changes have been written.' : 'Nothing was written — add ?confirm=1 to the URL to actually apply these fixes.',
    'purchases' => $purchaseResult,
    'sales' => $saleResult,
  ]);
} catch (Throwable $e) {
  jsonResponse(['error' => 'Backfill error: ' . $e->getMessage()], 500);
}
