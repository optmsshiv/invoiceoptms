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
require_once __DIR__ . '/../includes/cash_book.php';
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
//
// Also recomputes payment_type/payment_mode from the same real rows.
// Without this, a purchase created Pending (payment_type/mode left
// blank on purpose) that later gets paid off through THIS ledger —
// rather than by editing the purchase itself — would flip to
// Partial/Paid with amount_paid updated, but payment_type/mode would
// stay blank forever, since nothing else ever writes to those two
// columns. That showed up as the Purchases table's Payment Mode
// column stuck on "—" even though the purchase was clearly no longer
// Pending. One method across all real payments → that method; more
// than one distinct method → "Split", same convention the New/Edit
// Purchase form already uses for a split payment. No real payments
// left (all soft-deleted, or none yet) → blank, matching the "nothing
// decided yet" rule used everywhere else for a Pending purchase.
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

    $methodStmt = $db->prepare(
        'SELECT DISTINCT method FROM purchase_payments
          WHERE purchase_id = ? AND purchase_deleted = 0 AND method IS NOT NULL AND method <> \'\''
    );
    $methodStmt->execute([$purchaseId]);
    $methods = $methodStmt->fetchAll(PDO::FETCH_COLUMN);
    $paymentMode = count($methods) === 1 ? $methods[0] : (count($methods) > 1 ? 'Split' : '');

    $db->prepare('UPDATE purchases SET amount_paid = ?, status = ?, payment_type = ?, payment_mode = ? WHERE id = ?')
       ->execute([$paid, $status, $paymentMode, $paymentMode, $purchaseId]);
}

try {
    // Must run BEFORE any Cash Book transaction opens: DDL commits silently.
    cbEnsureSchema($db);

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

            $payMethod = (string)($d['method'] ?? '');
            $isCash    = ($payMethod === 'Cash in Hand');
            $payDate   = nullIfEmpty($d['payment_date'] ?? null) ?? date('Y-m-d H:i:s');
            $userId    = (int)($_SESSION['user_id'] ?? 0);

            // A session that was already carried forward must not be drawn
            // from (when the restriction is on). Checked BEFORE anything is
            // written, so a refused payment leaves no trace.
            if ($isCash) {
                $lockMsg = cbSessionLockMessage($db, trim($d['session_to_date'] ?? ''), 'this payment');
                if ($lockMsg) jsonResponse(['error' => $lockMsg], 400);
            }

            // The payment row, the Cash Book entry and the purchase's cached
            // "paid" total are saved TOGETHER or not at all. Before this fix a
            // payment made in Cash in Hand here never reached the Cash Book,
            // so the book kept showing cash that had already been paid out.
            cbBegin($db);
            try {
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
                    $payDate,
                    $payMethod,
                    $d['transaction_id'] ?? '',
                    $d['notes'] ?? '',
                    $userId,
                    date('Y-m-d H:i:s'),
                ]);
                $id = (int)$db->lastInsertId(); // read BEFORE the ledger insert changes it

                if ($isCash) {
                    cbPost($db, [
                        'type' => 'purchase', 'direction' => 'out', 'amount' => $amount,
                        'date' => cbEntryDate($db, $payDate), 'ref_type' => 'purchase_payment', 'ref_id' => $id,
                        'note' => 'Purchase ' . ($purchase['purchase_no'] ?? ('#' . $purchaseId)) . ' payment',
                        'user_id' => $userId,
                    ]);
                }

                recachePurchasePaid($db, $purchaseId);
                cbCommit($db);
            } catch (Throwable $ex) {
                cbRollback($db);
                throw $ex;
            }

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
            // Already removed — nothing more to do (and, crucially, nothing
            // more to refund to the Cash Book a second time).
            if ((int)$row['purchase_deleted'] === 1) jsonResponse(['success' => true]);

            $userId = (int)($_SESSION['user_id'] ?? 0);
            $purNo  = $row['purchase_no'] ?? ('#' . $row['purchase_id']);

            cbBegin($db);
            try {
                $db->prepare('UPDATE purchase_payments SET purchase_deleted = 1 WHERE id = ?')->execute([$id]);

                // Give back exactly what this payment took from the Cash
                // Book. Safe for payments that never touched it (other
                // methods, or older cash payments that were never deducted):
                // cbReverse finds nothing outstanding and posts nothing.
                $refunded = cbReverse($db, 'purchase_payment', $id,
                    "Reversal: payment removed from purchase {$purNo}", $userId);

                // Purchases created BEFORE this fix recorded their very first
                // payment on the Cash Book under the purchase's own id, not
                // the payment row's. Fall back to that old-style entry — but
                // only for the first payment, which is what created it.
                if ($refunded === 0
                    && ($row['method'] ?? '') === 'Cash in Hand'
                    && ($row['notes'] ?? '') === 'Initial payment at purchase creation') {
                    cbReverse($db, 'purchase', (int)$row['purchase_id'],
                        "Reversal: Purchase {$purNo} payment removed", $userId);
                }

                recachePurchasePaid($db, (int)$row['purchase_id']);
                cbCommit($db);
            } catch (Throwable $ex) {
                cbRollback($db);
                throw $ex;
            }

            logActivity((int)($_SESSION['user_id'] ?? 0), 'delete', 'purchase_payment', $id,
                'Removed payment of ₹' . $row['amount'] . ' from purchase ' . ($row['purchase_no'] ?? ''));

            jsonResponse(['success' => true]);
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    cbRollback($db); // no-op unless a Cash Book transaction is still open
    error_log('purchase_payments.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Purchase Payments API error: ' . $e->getMessage()], 500);
}
