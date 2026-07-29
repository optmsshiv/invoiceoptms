<?php
// ================================================================
//  api/expenses.php  — Expense Tracker CRUD
//  GET    /api/expenses.php              → list all expenses
//  GET    /api/expenses.php?id=X         → single expense
//  POST   /api/expenses.php              → create expense
//  PUT    /api/expenses.php?id=X         → replace expense
//  DELETE /api/expenses.php?id=X         → delete expense
// ================================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

function logAct(PDO $db, string $type, string $label, string $detail = '', ?int $invoiceId = null): void {
    try {
        $user = currentUser();
        $uid  = $user['id'] ?? null;
        $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
        $db->prepare(
            'INSERT INTO activity_log (type,label,detail,invoice_id,user_id,ip)
             VALUES (:t,:l,:d,:i,:u,:ip)'
        )->execute([':t'=>$type,':l'=>$label,':d'=>$detail,':i'=>$invoiceId,':u'=>$uid,':ip'=>$ip]);
    } catch (Exception $e) { /* activity_log may not exist yet — silent */ }
}

// ── Cash in Hand ledger — shared fund pool, drawn from when an expense's
// method is "Cash in Hand". Best-effort: wrapped in try/catch so a ledger
// hiccup never blocks the actual expense save.
function cihBalance($db) {
    $row = $db->query('SELECT balance_after FROM cash_in_hand_ledger ORDER BY id DESC LIMIT 1')->fetch();
    return $row ? (float)$row['balance_after'] : 0.0;
}
function recordCashInHandMovement($db, $direction, $amount, $refType, $refId, $note, $userId) {
    if ($amount <= 0) return;
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `cash_in_hand_ledger` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `entry_date` DATE NOT NULL,
            `type` ENUM('topup','purchase','expense','adjustment') NOT NULL DEFAULT 'topup',
            `direction` ENUM('in','out') NOT NULL DEFAULT 'in',
            `amount` DECIMAL(12,2) NOT NULL DEFAULT 0, `balance_after` DECIMAL(12,2) NOT NULL DEFAULT 0,
            `reference_type` VARCHAR(30) DEFAULT NULL, `reference_id` INT UNSIGNED DEFAULT NULL,
            `note` VARCHAR(255) DEFAULT NULL, `created_by` INT UNSIGNED DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`),
            INDEX `idx_cih_date` (`entry_date`), INDEX `idx_cih_ref` (`reference_type`,`reference_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $newBal = $direction === 'in' ? cihBalance($db) + $amount : cihBalance($db) - $amount;
        $stmt = $db->prepare('INSERT INTO cash_in_hand_ledger
            (entry_date, type, direction, amount, balance_after, reference_type, reference_id, note, created_by)
            VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$refType === 'adjustment' ? 'adjustment' : $refType, $direction, $amount, $newBal, $refType, $refId, $note, $userId]);
    } catch (Throwable $e) {
        error_log('recordCashInHandMovement (expenses.php) failed: ' . $e->getMessage());
    }
}

try {
    $db = getDB();

    // ── Ensure table exists (auto-create if migration not yet run) ──
    $db->exec("CREATE TABLE IF NOT EXISTS `expenses` (
        `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `date`       DATE          NOT NULL,
        `category`   VARCHAR(80)   NOT NULL DEFAULT 'Other',
        `vendor`     VARCHAR(200)  NOT NULL,
        `amount`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `method`     VARCHAR(60)   NOT NULL DEFAULT 'UPI',
        `notes`      TEXT          NULL,
        `created_by` INT UNSIGNED  NULL,
        `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_expenses_date` (`date`),
        INDEX `idx_expenses_cat`  (`category`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── GET ──────────────────────────────────────────────────────
    if ($method === 'GET') {
        // Summary by category
        if (!$id && !empty($_GET['action']) && $_GET['action'] === 'summary') {
            $from = $_GET['from'] ?? date('Y-m-01');
            $to   = $_GET['to']   ?? date('Y-m-d');
            $stmt = $db->prepare('SELECT category, SUM(amount) total, COUNT(*) cnt FROM expenses WHERE `date` BETWEEN ? AND ? GROUP BY category ORDER BY total DESC');
            $stmt->execute([$from, $to]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total = array_sum(array_column($rows, 'total'));
            echo json_encode(['success'=>true,'data'=>$rows,'total'=>$total,'count'=>array_sum(array_column($rows,'cnt'))]);
            exit;
        }
        // Categories list
        if (!$id && !empty($_GET['action']) && $_GET['action'] === 'categories') {
            $fixed = ['Rent','Salary','Electricity','Fuel','Telephone','Transport','Labour','Maintenance','Stationery','Packaging','Utilities','Bank Charges','Other'];
            $stmt = $db->query("SELECT DISTINCT category FROM expenses ORDER BY category");
            $all = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $custom = array_values(array_diff($all, $fixed));
            echo json_encode(['success'=>true,'fixed'=>$fixed,'custom'=>$custom]);
            exit;
        }
        if ($id) {
            $stmt = $db->prepare('SELECT * FROM expenses WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($row
                ? ['success'=>true,'data'=>$row]
                : ['success'=>false,'error'=>'Not found']);
        } else {
            $where  = ['1=1'];
            $params = [];
            if (!empty($_GET['category'])) {
                $where[]           = 'category = :cat';
                $params[':cat']    = $_GET['category'];
            }
            if (!empty($_GET['month'])) {
                $where[]           = "DATE_FORMAT(`date`,'%Y-%m') = :month";
                $params[':month']  = $_GET['month'];
            }
            if (!empty($_GET['from'])) {
                $where[]           = '`date` >= :from';
                $params[':from']   = $_GET['from'];
            }
            if (!empty($_GET['to'])) {
                $where[]           = '`date` <= :to';
                $params[':to']     = $_GET['to'];
            }
            $sql  = 'SELECT * FROM expenses WHERE '.implode(' AND ',$where)
                  . ' ORDER BY `date` DESC, id DESC';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true,'data'=>$rows,'count'=>count($rows)]);
        }
        exit;
    }

    // ── Read body ────────────────────────────────────────────────
    $body = [];
    if (in_array($method, ['POST','PUT','PATCH'])) {
        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: [];
        if (empty($body)) $body = $_POST;
    }

    // ── POST — create ─────────────────────────────────────────────
    if ($method === 'POST') {
        $date    = trim($body['date']     ?? '');
        $cat     = trim($body['category'] ?? 'Other');
        $vendor  = trim($body['vendor']   ?? '');
        $amount  = (float)($body['amount'] ?? 0);
        $meth    = trim($body['method']   ?? 'UPI');
        $notes   = trim($body['notes']    ?? '');

        if (!$date || !$vendor || $amount <= 0) {
            http_response_code(422);
            echo json_encode(['success'=>false,'error'=>'date, vendor, and amount are required']);
            exit;
        }
        $stmt = $db->prepare(
            'INSERT INTO expenses (`date`,category,vendor,amount,method,notes)
             VALUES (:date,:cat,:vendor,:amount,:method,:notes)'
        );
        $stmt->execute([':date'=>$date,':cat'=>$cat,':vendor'=>$vendor,
            ':amount'=>$amount,':method'=>$meth,':notes'=>$notes]);
        $newId = $db->lastInsertId();
        logAct($db, 'expense_added', "Expense added: $vendor", '₹'.number_format($amount,2));
        if ($meth === 'Cash in Hand') {
            $user = currentUser();
            recordCashInHandMovement($db, 'out', $amount, 'expense', (int)$newId, "Expense: {$vendor}", (int)($user['id'] ?? 0));
        }
        echo json_encode(['success'=>true,'id'=>(int)$newId]);
        exit;
    }

    // ── PUT — full replace ────────────────────────────────────────
    if ($method === 'PUT' && $id) {
        $date   = trim($body['date']     ?? '');
        $cat    = trim($body['category'] ?? 'Other');
        $vendor = trim($body['vendor']   ?? '');
        $amount = (float)($body['amount'] ?? 0);
        $meth   = trim($body['method']   ?? 'UPI');
        $notes  = trim($body['notes']    ?? '');

        if (!$date || !$vendor || $amount <= 0) {
            http_response_code(422);
            echo json_encode(['success'=>false,'error'=>'date, vendor, and amount are required']);
            exit;
        }

        // Capture the pre-edit state so we can reverse its Cash in Hand
        // impact below if it changes (or the amount changed).
        $oldStmt = $db->prepare('SELECT method, amount, vendor FROM expenses WHERE id=:id');
        $oldStmt->execute([':id'=>$id]);
        $oldExp = $oldStmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare(
            'UPDATE expenses SET `date`=:date,category=:cat,vendor=:vendor,
             amount=:amount,method=:method,notes=:notes WHERE id=:id'
        );
        $stmt->execute([':date'=>$date,':cat'=>$cat,':vendor'=>$vendor,
            ':amount'=>$amount,':method'=>$meth,':notes'=>$notes,':id'=>$id]);
        logAct($db, 'expense_added', "Expense edited: $vendor", '₹'.number_format($amount,2));
        $user = currentUser(); $uid = $user['id'] ?? null;
        if ($oldExp && $oldExp['method'] === 'Cash in Hand' && (float)$oldExp['amount'] > 0) {
            recordCashInHandMovement($db, 'in', (float)$oldExp['amount'], 'adjustment', $id,
                "Reversal: Expense {$oldExp['vendor']} edited", $uid);
        }
        if ($meth === 'Cash in Hand') {
            recordCashInHandMovement($db, 'out', $amount, 'expense', $id, "Expense: {$vendor} (edited)", $uid);
        }
        echo json_encode(['success'=>true]);
        exit;
    }

    // ── DELETE ────────────────────────────────────────────────────
    if ($method === 'DELETE' && $id) {
        $stmt = $db->prepare('SELECT vendor,amount,method FROM expenses WHERE id=:id');
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $db->prepare('DELETE FROM expenses WHERE id=:id')->execute([':id'=>$id]);
        if ($row) {
            logAct($db,'expense_added',"Expense deleted: {$row['vendor']}",'₹'.number_format($row['amount'],2));
            if ($row['method'] === 'Cash in Hand' && (float)$row['amount'] > 0) {
                $user = currentUser();
                recordCashInHandMovement($db, 'in', (float)$row['amount'], 'adjustment', $id,
                    "Reversal: Expense {$row['vendor']} deleted", $user['id'] ?? null);
            }
        }
        echo json_encode(['success'=>true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);

} catch (Exception $e) {
    error_log('expenses.php error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}