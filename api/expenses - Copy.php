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

// NOTE: this file previously had its own local logAct() helper that wrote
// to activity_log with different column names (type,label,detail,
// invoice_id,user_id,ip) than the shared logActivity() in auth.php
// (user_id,action,entity_type,entity_id,details,ip_address,created_at) —
// which every other file (purchases.php, sales.php, suppliers.php,
// customers.php) actually uses. Since activity_log's real schema is the
// one logActivity() expects, every logAct() call here was almost
// certainly throwing and getting silently swallowed by its own catch —
// Expense create/edit/delete were likely never reaching Activity Log at
// all. Removed in favor of the shared logActivity(), used below.

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

    // credit_entry_conversions is normally created by credit_entries.php —
    // but this endpoint's new LEFT JOIN below needs it to exist regardless
    // of whether that page has ever actually been used yet on this tenant.
    // Without this guard, a fresh install hitting Expenses first (before
    // ever touching Credit) would throw "table doesn't exist" here and take
    // down the whole Expense list, not just the missing credit-link feature.
    $db->exec("CREATE TABLE IF NOT EXISTS `credit_entry_conversions` (
        `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `credit_entry_id`  INT UNSIGNED  NOT NULL,
        `expense_id`       INT UNSIGNED  NOT NULL,
        `amount`           DECIMAL(12,2) NOT NULL DEFAULT 0,
        `created_by`       INT UNSIGNED  NULL,
        `created_at`       DATETIME      NOT NULL,
        PRIMARY KEY (`id`),
        INDEX `idx_cec_entry` (`credit_entry_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
            // LEFT JOIN — a credit-sourced expense has exactly one row here
            // (each conversion creates a brand-new expense, 1:1), so this is
            // safe without risking duplicate/multiplied rows. NULL for a
            // directly-entered expense.
            $stmt = $db->prepare(
                'SELECT e.*, cec.credit_entry_id, u.name AS created_by_name FROM expenses e
                 LEFT JOIN credit_entry_conversions cec ON cec.expense_id = e.id
                 LEFT JOIN users u ON u.id = e.created_by
                 WHERE e.id = :id'
            );
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
            // Same LEFT JOIN as the single-row fetch above, so the Expense
            // Tracker table can link a "Via Credit" badge straight back to
            // the credit entry it came from instead of just labelling it.
            $sql  = 'SELECT e.*, cec.credit_entry_id, u.name AS created_by_name FROM expenses e
                     LEFT JOIN credit_entry_conversions cec ON cec.expense_id = e.id
                     LEFT JOIN users u ON u.id = e.created_by
                     WHERE '.implode(' AND ',$where)
                  . ' ORDER BY e.`date` DESC, e.id DESC';
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
            'INSERT INTO expenses (`date`,category,vendor,amount,method,notes,created_by,created_at,updated_at)
             VALUES (:date,:cat,:vendor,:amount,:method,:notes,:created_by,:created_at,:updated_at)'
        );
        // Explicit PHP timestamp (Asia/Kolkata, set in includes/auth.php)
        // instead of the column's own DEFAULT CURRENT_TIMESTAMP — that
        // default runs on MySQL's own server timezone, which caused the
        // same "wrong time shown" bug we already fixed for email logs.
        $now = date('Y-m-d H:i:s');
        $stmt->execute([':date'=>$date,':cat'=>$cat,':vendor'=>$vendor,
            ':amount'=>$amount,':method'=>$meth,':notes'=>$notes,
            ':created_by'=>$_SESSION['user_id'] ?? null,
            ':created_at'=>$now,':updated_at'=>$now]);
        $newId = $db->lastInsertId();
        logActivity((int)($_SESSION['user_id'] ?? 0), 'create', 'expense', (int)$newId,
            "Expense added: $vendor (₹".number_format($amount,2).")");
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
             amount=:amount,method=:method,notes=:notes,updated_at=:updated_at WHERE id=:id'
        );
        $stmt->execute([':date'=>$date,':cat'=>$cat,':vendor'=>$vendor,
            ':amount'=>$amount,':method'=>$meth,':notes'=>$notes,
            ':updated_at'=>date('Y-m-d H:i:s'),':id'=>$id]);
        $oldAmt = $oldExp ? (float)$oldExp['amount'] : null;
        $diffLabel = ($oldAmt !== null && abs($oldAmt - $amount) > 0.004)
            ? '₹'.number_format($oldAmt,2).' → ₹'.number_format($amount,2)
            : '₹'.number_format($amount,2).' (amount unchanged)';
        logActivity((int)($_SESSION['user_id'] ?? 0), 'update', 'expense', (int)$id,
            "Expense edited: $vendor ($diffLabel)");
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
            logActivity((int)($_SESSION['user_id'] ?? 0), 'delete', 'expense', (int)$id,
                "Expense deleted: {$row['vendor']} (₹".number_format($row['amount'],2).")");
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