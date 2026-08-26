<?php
// ================================================================
//  api/purchase_payments.php — Payment-history for Purchases
//  Mirrors api/payments.php's design (Invoices side):
//    - Every payment is its own row, NEVER overwritten
//    - Deletes are soft (a flag), so history survives
//    - "Amount paid" is always recomputed from the sum of real
//      payment rows, then cached back onto purchases.amount_paid /
//      purchases.status so every existing page that already reads
//      those two columns (stats, CSV export, table, filters) keeps
//      working correctly with zero changes elsewhere.
//
//  Deliberately NOT mirrored from payments.php: a per-payment
//  "settlement discount" field. Purchases already has its own
//  discount mechanism (trade_discount_pct / cash_discount_pct,
//  applied once at creation) — adding a second, different discount
//  concept per-payment would just create two competing systems.
//
//  GET    ?purchase_id=X  → payment history for one purchase
//  POST                   → record a new payment
//  DELETE ?id=X            → soft-delete one payment (never a real DELETE)
// ================================================================
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

$db->exec("CREATE TABLE IF NOT EXISTS `purchase_payments` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `purchase_id`     INT UNSIGNED  NOT NULL,
    `purchase_no`     VARCHAR(60)   NULL,
    `supplier_name`   VARCHAR(200)  NULL,
    `amount`          DECIMAL(12,2) NOT NULL DEFAULT 0,
    `remaining_amt`   DECIMAL(12,2) NOT NULL DEFAULT 0,
    `payment_date`    DATETIME      NULL,
    `method`          VARCHAR(60)   NULL,
    `transaction_id`  VARCHAR(100)  NULL,
    `notes`           VARCHAR(500)  NULL,
    `purchase_deleted` TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`      INT UNSIGNED  NULL,
    `created_at`      DATETIME      NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_pp_purchase` (`purchase_id`),
    INDEX `idx_pp_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function nullIfEmpty($v) { return ($v === '' || $v === null) ? null : $v; }

// Recompute total paid from real payment rows, then cache it back onto
// the purchase — this is what keeps every existing page (stats, CSV
// export, filters) correct without needing to touch them.
function recachePurchasePaid(PDO $db, int $purchaseId): void {
    $sumStmt = $db->prepare(
        'SELECT COALESCE(SUM(amount),0) AS paid FROM purchase_payments
          WHERE purchase_id = ? AND purchase_deleted = 0'
    );
    $sumStmt->execute([$purchaseId]);
    $paid = (float)$sumStmt->fetch()['paid'];

    $totStmt = $db->prepare('SELECT total FROM purchases WHERE id = ?');
    $totStmt->execute([$purchaseId]);
    $grandTotal = (float)$totStmt->fetchColumn();

    if ($grandTotal > 0 && ($grandTotal - $paid) <= 0.01) {
        $status = 'Paid';
    } elseif ($paid > 0.004) {
        $status = 'Partial';
    } else {
        $status = 'Pending';
    }

    $db->prepare('UPDATE purchases SET amount_paid = ?, status = ? WHERE id = ?')
       ->execute([$paid, $status, $purchaseId]);
}

try {
    switch ($method) {

        case 'GET':
            $purchaseId = (int)($_GET['purchase_id'] ?? 0);
            if (!$purchaseId) jsonResponse(['error' => 'purchase_id is required'], 400);
            $stmt = $db->prepare(
                'SELECT * FROM purchase_payments
                  WHERE purchase_id = ? AND purchase_deleted = 0
                  ORDER BY payment_date DESC, id DESC'
            );
            $stmt->execute([$purchaseId]);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) { $r['amount'] = (float)$r['amount']; $r['remaining_amt'] = (float)$r['remaining_amt']; }
            unset($r);

            // "Paid By" name resolution — users live in the MASTER DB,
            // purchase_payments lives in the TENANT DB (this app is
            // multi-tenant), so this can never be a plain SQL JOIN across
            // the two. Look up the distinct user IDs actually needed,
            // once, against the master DB instead.
            $userIds = array_values(array_unique(array_filter(array_column($rows, 'created_by'))));
            $names = [];
            if ($userIds) {
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $uStmt = getMasterDB()->prepare("SELECT id, name FROM users WHERE id IN ($placeholders)");
                $uStmt->execute($userIds);
                foreach ($uStmt->fetchAll() as $u) { $names[$u['id']] = $u['name']; }
            }
            foreach ($rows as &$r) { $r['paid_by_name'] = $names[$r['created_by']] ?? null; }
            unset($r);

            jsonResponse(['data' => $rows]);
            break;

        case 'POST':
            $d = json_decode(file_get_contents('php://input'), true) ?: [];
            $purchaseId = (int)($d['purchase_id'] ?? 0);
            $amount     = (float)($d['amount'] ?? 0);
            if (!$purchaseId) jsonResponse(['error' => 'purchase_id is required'], 400);
            if ($amount <= 0) jsonResponse(['error' => 'Amount must be greater than 0'], 400);

            $pStmt = $db->prepare('SELECT purchase_no, total, amount_paid FROM purchases WHERE id = ?');
            $pStmt->execute([$purchaseId]);
            $purchase = $pStmt->fetch();
            if (!$purchase) jsonResponse(['error' => 'Purchase not found'], 404);

            $prevPaidStmt = $db->prepare(
                'SELECT COALESCE(SUM(amount),0) AS paid FROM purchase_payments
                  WHERE purchase_id = ? AND purchase_deleted = 0'
            );
            $prevPaidStmt->execute([$purchaseId]);
            $prevPaid = (float)$prevPaidStmt->fetch()['paid'];

            $grandTotal   = (float)$purchase['total'];
            $remainingAmt = max(0, round($grandTotal - $prevPaid - $amount, 2));

            $stmt = $db->prepare(
                'INSERT INTO purchase_payments
                   (purchase_id, purchase_no, supplier_name, amount, remaining_amt,
                    payment_date, method, transaction_id, notes, created_by, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $purchaseId,
                $purchase['purchase_no'] ?? '',
                $d['supplier_name'] ?? '',
                $amount,
                $remainingAmt,
                nullIfEmpty($d['payment_date'] ?? null) ?? date('Y-m-d H:i:s'),
                $d['method'] ?? '',
                $d['transaction_id'] ?? '',
                $d['notes'] ?? '',
                (int)($_SESSION['user_id'] ?? 0),
                date('Y-m-d H:i:s'),
            ]);
            $id = (int)$db->lastInsertId();

            recachePurchasePaid($db, $purchaseId);

            logActivity((int)($_SESSION['user_id'] ?? 0), 'create', 'purchase_payment', $id,
                "Recorded payment of ₹{$amount} for purchase " . ($purchase['purchase_no'] ?? "#{$purchaseId}"));

            jsonResponse(['success' => true, 'id' => $id, 'remaining_amt' => $remainingAmt]);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'id is required'], 400);

            $rowStmt = $db->prepare('SELECT * FROM purchase_payments WHERE id = ?');
            $rowStmt->execute([$id]);
            $row = $rowStmt->fetch();
            if (!$row) jsonResponse(['error' => 'Not found'], 404);

            $db->prepare('UPDATE purchase_payments SET purchase_deleted = 1 WHERE id = ?')->execute([$id]);
            recachePurchasePaid($db, (int)$row['purchase_id']);

            logActivity((int)($_SESSION['user_id'] ?? 0), 'delete', 'purchase_payment', $id,
                'Removed payment of ₹' . $row['amount'] . ' from purchase ' . ($row['purchase_no'] ?? ''));

            jsonResponse(['success' => true]);
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    error_log('purchase_payments.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Purchase Payments API error: ' . $e->getMessage()], 500);
}
