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
            `type` ENUM('topup','purchase','expense','adjustment','carry_forward') NOT NULL DEFAULT 'topup',
            `direction` ENUM('in','out') NOT NULL DEFAULT 'in',
            `amount` DECIMAL(12,2) NOT NULL DEFAULT 0, `balance_after` DECIMAL(12,2) NOT NULL DEFAULT 0,
            `reference_type` VARCHAR(30) DEFAULT NULL, `reference_id` INT UNSIGNED DEFAULT NULL,
            `note` VARCHAR(255) DEFAULT NULL, `created_by` INT UNSIGNED DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `source_end_date` DATE NULL, PRIMARY KEY (`id`),
            INDEX `idx_cih_date` (`entry_date`), INDEX `idx_cih_ref` (`reference_type`,`reference_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $newBal = $direction === 'in' ? cihBalance($db) + $amount : cihBalance($db) - $amount;
        $stmt = $db->prepare('INSERT INTO cash_in_hand_ledger
            (entry_date, type, direction, amount, balance_after, reference_type, reference_id, note, created_by, created_at)
            VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$refType === 'adjustment' ? 'adjustment' : $refType, $direction, $amount, $newBal, $refType, $refId, $note, $userId, date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
        error_log('recordCashInHandMovement (expenses.php) failed: ' . $e->getMessage());
    }
}

// Blocks spending from Cash in Hand if the session it belongs to was
// already carried forward elsewhere AND the strict restriction is on —
// same rule and same underlying data as the Cash in Hand page itself,
// just enforced here too since an Expense can also draw from that pool.
function expCheckCarriedRestriction($db, $sessionToDate) {
    if (!$sessionToDate) return;
    try {
        $restrictSetting = $db->prepare('SELECT value FROM settings WHERE `key` = ?');
        $restrictSetting->execute(['cih_restrict_carried_sessions']);
        $restrictVal = $restrictSetting->fetchColumn();
        if ($restrictVal !== false && $restrictVal !== '1') return; // explicitly turned off
    } catch (Throwable $e) { /* settings table missing — fail open, don't block */ return; }

    try {
        $stmt = $db->prepare(
            "SELECT l.amount, l.created_at, u.name AS by_name
             FROM cash_in_hand_ledger l LEFT JOIN users u ON u.id = l.created_by
             WHERE l.type = 'carry_forward' AND l.source_end_date = ? LIMIT 1"
        );
        $stmt->execute([$sessionToDate]);
        $row = $stmt->fetch();
        if (!$row) return;
        http_response_code(400);
        echo json_encode(['success' => false, 'error' =>
            'This session\'s Cash in Hand balance (₹' . number_format($row['amount'], 2) . ') was already carried forward on ' .
            date('d-m-Y', strtotime($row['created_at'])) . ' by ' . ($row['by_name'] ?: 'someone') .
            '. Turn off the restriction in Settings if this expense genuinely needs to draw from it.']);
        exit;
    } catch (Throwable $e) { /* table/column missing — fail open, don't block */ }
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
        if ($meth === 'Cash in Hand') {
            expCheckCarriedRestriction($db, trim($body['session_to_date'] ?? '')); // checked BEFORE insert — never leaves an orphaned expense if blocked
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
        if ($meth === 'Cash in Hand') {
            expCheckCarriedRestriction($db, trim($body['session_to_date'] ?? ''));
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
        $oldAmt = $oldExp ? (float)$oldExp['amount'] : null;
        $diffLabel = ($oldAmt !== null && abs($oldAmt - $amount) > 0.004)
            ? '₹'.number_format($oldAmt,2).' → ₹'.number_format($amount,2)
            : '₹'.number_format($amount,2).' (amount unchanged)';
        logAct($db, 'expense_edited', "Expense edited: $vendor", $diffLabel);
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
        $role = $_SESSION['user_role'] ?? 'viewer';
        if (!in_array($role, ['owner','super_admin'], true) && !can('action.expense.delete')) {
            http_response_code(403);
            echo json_encode(['success'=>false,'error'=>'You do not have permission to delete expenses. Ask the owner to grant this in Team Permissions.']);
            exit;
        }
        $stmt = $db->prepare('SELECT vendor,amount,method FROM expenses WHERE id=:id');
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $db->prepare('DELETE FROM expenses WHERE id=:id')->execute([':id'=>$id]);
        if ($row) {
            logAct($db,'expense_deleted',"Expense deleted: {$row['vendor']}",'₹'.number_format($row['amount'],2));
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