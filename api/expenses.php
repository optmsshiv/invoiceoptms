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
require_once __DIR__ . '/../includes/cash_book.php';
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

// Cash Book (Cash in Hand) writes go through includes/cash_book.php — the one
// shared, locked, transactional implementation. The private copies of
// cihBalance / recordCashInHandMovement / expCheckCarriedRestriction that used
// to live here were removed.

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

    // Same fresh-install concern as above — `source` is normally added by
    // credit_entries.php's own migration guard (run only once someone has
    // actually converted a Credit entry), so a tenant that hits Expenses
    // first would 500 on the DELETE handler's SELECT below without this.
    try { $db->exec("ALTER TABLE expenses ADD COLUMN source VARCHAR(20) NULL AFTER method"); }
    catch (Throwable $e) { /* already exists */ }

    // All DDL above is finished — safe to ensure the Cash Book schema now (DDL
    // would silently commit a Cash Book transaction if it ran later).
    cbEnsureSchema($db);

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
            // checked BEFORE insert — never leaves an orphaned expense if blocked
            $lockMsg = cbSessionLockMessage($db, trim($body['session_to_date'] ?? ''), 'this expense');
            if ($lockMsg) {
                http_response_code(400);
                echo json_encode(['success'=>false,'error'=>$lockMsg]);
                exit;
            }
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
        $uid = (int)($_SESSION['user_id'] ?? 0);

        // The expense and its Cash Book entry are saved TOGETHER or not at
        // all. Before, a failed ledger write was swallowed and the expense
        // stayed saved without ever drawing down the Cash Book. The entry is
        // dated with the EXPENSE's own date (not "today"), so it lands in the
        // right session.
        cbBegin($db);
        try {
            $stmt->execute([':date'=>$date,':cat'=>$cat,':vendor'=>$vendor,
                ':amount'=>$amount,':method'=>$meth,':notes'=>$notes,
                ':created_by'=>$_SESSION['user_id'] ?? null,
                ':created_at'=>$now,':updated_at'=>$now]);
            $newId = $db->lastInsertId(); // read BEFORE the ledger insert changes it
            if ($meth === 'Cash in Hand') {
                cbPost($db, [
                    'type' => 'expense', 'direction' => 'out', 'amount' => $amount, 'date' => cbEntryDate($db, $date),
                    'ref_type' => 'expense', 'ref_id' => (int)$newId, 'note' => "Expense: {$vendor}", 'user_id' => $uid,
                ]);
            }
            cbCommit($db);
        } catch (Throwable $ex) {
            cbRollback($db);
            throw $ex;
        }
        logActivity($uid, 'create', 'expense', (int)$newId,
            "Expense added: $vendor (₹".number_format($amount,2).")");
        echo json_encode(['success'=>true,'id'=>(int)$newId]);
        exit;
    }

    // ── PUT — full replace ────────────────────────────────────────
    if ($method === 'PUT' && $id) {
        // Capture the pre-edit state FIRST — needed both to decide whether
        // editing is even allowed (see the lock below) and, if it is, to
        // reverse its old Cash in Hand impact further down.
        $oldStmt = $db->prepare('SELECT method, amount, vendor, source FROM expenses WHERE id=:id');
        $oldStmt->execute([':id'=>$id]);
        $oldExp = $oldStmt->fetch(PDO::FETCH_ASSOC);
        if (!$oldExp) {
            http_response_code(404);
            echo json_encode(['success'=>false,'error'=>'Expense not found']);
            exit;
        }

        // Cash-in-Hand and Credit-sourced expenses can't be edited safely —
        // Cash in Hand's amount is tied to a real shared fund balance, and
        // a Credit-sourced expense's amount is tied to a credit entry's
        // converted_amount; neither gets kept in sync by a plain edit.
        // Delete (which correctly reverses both — see cbReverse (includes/cash_book.php)
        // and the credit_entry_conversions reversal in the DELETE handler
        // below) and re-add instead. Checked here too, not just hidden in
        // the UI, since this endpoint could otherwise be called directly.
        if ($oldExp && ($oldExp['method'] === 'Cash in Hand' || ($oldExp['source'] ?? '') === 'credit')) {
            http_response_code(400);
            echo json_encode(['success'=>false,'error'=>'This expense is linked to ' .
                (($oldExp['source'] ?? '') === 'credit' ? 'a Credit entry' : 'Cash in Hand') .
                ' and can\'t be edited directly — delete and re-add it instead, so the linked balance stays correct.']);
            exit;
        }

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
            $lockMsg = cbSessionLockMessage($db, trim($body['session_to_date'] ?? ''), 'this expense');
            if ($lockMsg) {
                http_response_code(400);
                echo json_encode(['success'=>false,'error'=>$lockMsg]);
                exit;
            }
        }

        $uid = (int)($_SESSION['user_id'] ?? 0);
        // An expense that was ALREADY paid from the Cash Book can't reach this
        // point (blocked above), so the only Cash Book effect of an edit is a
        // non-cash expense being switched TO Cash in Hand — one new entry.
        // Saved together with the update, dated with the expense's own date.
        cbBegin($db);
        try {
            $stmt = $db->prepare(
                'UPDATE expenses SET `date`=:date,category=:cat,vendor=:vendor,
                 amount=:amount,method=:method,notes=:notes,updated_at=:updated_at WHERE id=:id'
            );
            $stmt->execute([':date'=>$date,':cat'=>$cat,':vendor'=>$vendor,
                ':amount'=>$amount,':method'=>$meth,':notes'=>$notes,
                ':updated_at'=>date('Y-m-d H:i:s'),':id'=>$id]);
            if ($meth === 'Cash in Hand') {
                cbPost($db, [
                    'type' => 'expense', 'direction' => 'out', 'amount' => $amount, 'date' => cbEntryDate($db, $date),
                    'ref_type' => 'expense', 'ref_id' => (int)$id, 'note' => "Expense: {$vendor} (edited)", 'user_id' => $uid,
                ]);
            }
            cbCommit($db);
        } catch (Throwable $ex) {
            cbRollback($db);
            throw $ex;
        }
        $oldAmt = $oldExp ? (float)$oldExp['amount'] : null;
        $diffLabel = ($oldAmt !== null && abs($oldAmt - $amount) > 0.004)
            ? '₹'.number_format($oldAmt,2).' → ₹'.number_format($amount,2)
            : '₹'.number_format($amount,2).' (amount unchanged)';
        logActivity($uid, 'update', 'expense', (int)$id,
            "Expense edited: $vendor ($diffLabel)");
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
        $stmt = $db->prepare('SELECT vendor,amount,method,source FROM expenses WHERE id=:id');
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Everything from here to the final commit is ONE unit: the credit
        // entry un-conversion, the expense removal and the Cash Book refund
        // all happen, or none do (any failure is rolled back in the catch at
        // the bottom of this file).
        cbBegin($db);

        // If this expense came from a Credit entry conversion, undo that
        // portion of the conversion BEFORE the expense row itself is gone —
        // otherwise the credit entry stays marked converted/partial forever,
        // pointing at converted_expense_id/credit_entry_conversions rows
        // that reference an expense which no longer exists. Mirrors the
        // same "recompute from what's actually still real" pattern already
        // used for purchase_payments.php → recachePurchasePaid().
        if ($row && ($row['source'] ?? '') === 'credit') {
            $convStmt = $db->prepare('SELECT * FROM credit_entry_conversions WHERE expense_id = :id');
            $convStmt->execute([':id'=>$id]);
            $conversions = $convStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($conversions as $conv) {
                $creditEntryId = (int)$conv['credit_entry_id'];
                $convAmount    = (float)$conv['amount'];

                $ceStmt = $db->prepare('SELECT * FROM credit_entries WHERE id = :id');
                $ceStmt->execute([':id'=>$creditEntryId]);
                $creditEntry = $ceStmt->fetch(PDO::FETCH_ASSOC);
                if ($creditEntry) {
                    $newConverted = max(0, round((float)$creditEntry['converted_amount'] - $convAmount, 2));
                    // Never back to 'converted' automatically — that only
                    // happens through a real conversion. 'pending' once
                    // nothing converted remains, 'partial' otherwise.
                    $newStatus = $newConverted <= 0.004 ? 'pending' : 'partial';

                    // The cached converted_expense_id/converted_at (used for
                    // display) might have pointed at THIS expense — if so,
                    // repoint to whichever conversion (if any) still remains
                    // instead of leaving it referencing a deleted expense.
                    $remStmt = $db->prepare(
                        'SELECT expense_id, created_at FROM credit_entry_conversions
                          WHERE credit_entry_id = :ceid AND expense_id <> :xid
                          ORDER BY id DESC LIMIT 1'
                    );
                    $remStmt->execute([':ceid'=>$creditEntryId, ':xid'=>$id]);
                    $remaining = $remStmt->fetch(PDO::FETCH_ASSOC);

                    $db->prepare(
                        'UPDATE credit_entries SET status=:status, converted_amount=:amt,
                           converted_expense_id=:xid, converted_at=:at WHERE id=:id'
                    )->execute([
                        ':status'=>$newStatus, ':amt'=>$newConverted,
                        ':xid'=>$remaining ? (int)$remaining['expense_id'] : null,
                        ':at'=>$remaining ? $remaining['created_at'] : null,
                        ':id'=>$creditEntryId,
                    ]);

                    logActivity((int)($_SESSION['user_id'] ?? 0), 'restore', 'credit_entry', $creditEntryId,
                        "Credit entry conversion reversed — expense #{$id} deleted, ₹" . number_format($convAmount, 2) .
                        " un-converted (now {$newStatus})");
                }
                // The conversion row itself now points at a deleted expense —
                // remove it so credit_entry_conversions only ever reflects
                // conversions that still actually exist.
                $db->prepare('DELETE FROM credit_entry_conversions WHERE id = :cid')->execute([':cid'=>$conv['id']]);
            }
        }

        $db->prepare('DELETE FROM expenses WHERE id=:id')->execute([':id'=>$id]);
        if ($row) {
            // Refund exactly what this expense still holds on the Cash Book.
            // Works for expenses recorded before this fix too (old-style
            // reversal rows are netted by their note), and posts nothing for
            // an expense that never used Cash in Hand.
            cbReverse($db, 'expense', (int)$id, "Reversal: Expense {$row['vendor']} deleted", (int)($_SESSION['user_id'] ?? 0));
        }
        cbCommit($db);
        if ($row) {
            logActivity((int)($_SESSION['user_id'] ?? 0), 'delete', 'expense', (int)$id,
                "Expense deleted: {$row['vendor']} (₹".number_format($row['amount'],2).")");
        }
        echo json_encode(['success'=>true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);

} catch (Exception $e) {
    if (isset($db)) cbRollback($db); // no-op unless a Cash Book transaction is still open
    error_log('expenses.php error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}