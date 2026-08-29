<?php
// ================================================================
//  api/sale_payments.php — Payment-history for Sales
//  Direct mirror of api/purchase_payments.php's design:
//    - Every payment is its own row, NEVER overwritten
//    - Deletes are soft (a flag), so history survives
//    - "Amount received" is always recomputed from the sum of real
//      payment rows, then cached back onto sales.amount_received /
//      sales.status so every existing page that already reads those
//      two columns keeps working correctly with zero changes.
//
//  GET    ?sale_id=X → payment history for one sale
//  POST               → record a new payment
//  DELETE ?id=X        → soft-delete one payment (never a real DELETE)
// ================================================================
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

$db->exec("CREATE TABLE IF NOT EXISTS `sale_payments` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `sale_id`         INT UNSIGNED  NOT NULL,
    `invoice_no`      VARCHAR(60)   NULL,
    `customer_name`   VARCHAR(200)  NULL,
    `amount`          DECIMAL(12,2) NOT NULL DEFAULT 0,
    `remaining_amt`   DECIMAL(12,2) NOT NULL DEFAULT 0,
    `payment_date`    DATETIME      NULL,
    `method`          VARCHAR(60)   NULL,
    `transaction_id`  VARCHAR(100)  NULL,
    `notes`           VARCHAR(500)  NULL,
    `sale_deleted`    TINYINT(1)    NOT NULL DEFAULT 0,
    `created_by`      INT UNSIGNED  NULL,
    `created_at`      DATETIME      NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_sp_sale` (`sale_id`),
    INDEX `idx_sp_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function nullIfEmptySP($v) { return ($v === '' || $v === null) ? null : $v; }

// Recompute total received from real payment rows, then cache it back
// onto the sale — keeps every existing page (stats, CSV export, filters)
// correct without needing to touch them.
function recacheSaleReceived(PDO $db, int $saleId): void {
    $sumStmt = $db->prepare(
        'SELECT COALESCE(SUM(amount),0) AS received FROM sale_payments
          WHERE sale_id = ? AND sale_deleted = 0'
    );
    $sumStmt->execute([$saleId]);
    $received = (float)$sumStmt->fetch()['received'];

    $totStmt = $db->prepare('SELECT total FROM sales WHERE id = ?');
    $totStmt->execute([$saleId]);
    $grandTotal = (float)$totStmt->fetchColumn();

    if ($grandTotal > 0 && ($grandTotal - $received) <= 0.01) {
        $status = 'Paid';
    } elseif ($received > 0.004) {
        $status = 'Partial';
    } else {
        $status = 'Pending';
    }

    $db->prepare('UPDATE sales SET amount_received = ?, payment_status = ? WHERE id = ?')
       ->execute([$received, $status, $saleId]);
}

try {
    switch ($method) {

        case 'GET':
            $saleId = (int)($_GET['sale_id'] ?? 0);
            if (!$saleId) jsonResponse(['error' => 'sale_id is required'], 400);
            $stmt = $db->prepare(
                'SELECT * FROM sale_payments
                  WHERE sale_id = ? AND sale_deleted = 0
                  ORDER BY payment_date DESC, id DESC'
            );
            $stmt->execute([$saleId]);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) { $r['amount'] = (float)$r['amount']; $r['remaining_amt'] = (float)$r['remaining_amt']; }
            unset($r);

            // "Paid By" name resolution — same as purchase_payments.php:
            // users live in the MASTER DB, sale_payments lives in the
            // TENANT DB, so this can never be a plain SQL JOIN.
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
            $saleId = (int)($d['sale_id'] ?? 0);
            $amount = (float)($d['amount'] ?? 0);
            if (!$saleId) jsonResponse(['error' => 'sale_id is required'], 400);
            if ($amount <= 0) jsonResponse(['error' => 'Amount must be greater than 0'], 400);

            $sStmt = $db->prepare('SELECT invoice_no, total, amount_received FROM sales WHERE id = ?');
            $sStmt->execute([$saleId]);
            $sale = $sStmt->fetch();
            if (!$sale) jsonResponse(['error' => 'Sale not found'], 404);

            $prevRecStmt = $db->prepare(
                'SELECT COALESCE(SUM(amount),0) AS received FROM sale_payments
                  WHERE sale_id = ? AND sale_deleted = 0'
            );
            $prevRecStmt->execute([$saleId]);
            $prevReceived = (float)$prevRecStmt->fetch()['received'];

            $grandTotal   = (float)$sale['total'];
            $remainingAmt = max(0, round($grandTotal - $prevReceived - $amount, 2));

            $stmt = $db->prepare(
                'INSERT INTO sale_payments
                   (sale_id, invoice_no, customer_name, amount, remaining_amt,
                    payment_date, method, transaction_id, notes, created_by, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $saleId,
                $sale['invoice_no'] ?? '',
                $d['customer_name'] ?? '',
                $amount,
                $remainingAmt,
                nullIfEmptySP($d['payment_date'] ?? null) ?? date('Y-m-d H:i:s'),
                $d['method'] ?? '',
                $d['transaction_id'] ?? '',
                $d['notes'] ?? '',
                (int)($_SESSION['user_id'] ?? 0),
                date('Y-m-d H:i:s'),
            ]);
            $id = (int)$db->lastInsertId();

            recacheSaleReceived($db, $saleId);

            logActivity((int)($_SESSION['user_id'] ?? 0), 'create', 'sale_payment', $id,
                "Recorded payment of ₹{$amount} for sale " . ($sale['invoice_no'] ?? "#{$saleId}"));

            jsonResponse(['success' => true, 'id' => $id, 'remaining_amt' => $remainingAmt]);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'id is required'], 400);

            $rowStmt = $db->prepare('SELECT * FROM sale_payments WHERE id = ?');
            $rowStmt->execute([$id]);
            $row = $rowStmt->fetch();
            if (!$row) jsonResponse(['error' => 'Not found'], 404);

            $db->prepare('UPDATE sale_payments SET sale_deleted = 1 WHERE id = ?')->execute([$id]);
            recacheSaleReceived($db, (int)$row['sale_id']);

            logActivity((int)($_SESSION['user_id'] ?? 0), 'delete', 'sale_payment', $id,
                'Removed payment of ₹' . $row['amount'] . ' from sale ' . ($row['invoice_no'] ?? ''));

            jsonResponse(['success' => true]);
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    error_log('sale_payments.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Sale Payments API error: ' . $e->getMessage()], 500);
}
