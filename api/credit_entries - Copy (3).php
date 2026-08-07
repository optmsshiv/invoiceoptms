<?php
// ================================================================
//  api/credit_entries.php — Owner's personal-expense staging area
//
//  Quick-capture log for money the owner (or permitted staff) spent
//  personally, before it becomes a formal categorized Expense. Once
//  created, an entry is LOCKED — no edit, no delete. The only way to
//  change anything is the Convert step, which is also where the
//  fields a real expense needs (category, payment method) get filled
//  in — that's also where a typo in amount/date/purpose gets fixed,
//  since editing isn't allowed before that point.
//
//  GET    ?action=list              → all entries, newest first
//  GET    ?id=X                     → single entry
//  POST                             → create a new (locked) entry
//  POST   ?action=convert&id=X      → convert to a real Expense row
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Menu-level permission gate — same pattern as every other menu.*
// permission: owner always passes, staff need it explicitly granted.
if (!can('menu.credit')) {
    jsonResponse(['error' => 'You do not have permission to access Credit entries.'], 403);
}

$db->exec("CREATE TABLE IF NOT EXISTS `credit_entries` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `entry_date`          DATE          NOT NULL,
    `amount`              DECIMAL(12,2) NOT NULL DEFAULT 0,
    `purpose`             VARCHAR(255)  NOT NULL,
    `paid_to`             VARCHAR(200)  NULL,
    `payment_method`      VARCHAR(60)   NULL,
    `status`              ENUM('pending','converted') NOT NULL DEFAULT 'pending',
    `converted_expense_id` INT UNSIGNED NULL,
    `converted_at`        DATETIME      NULL,
    `created_by`          INT UNSIGNED  NULL,
    `created_at`          DATETIME      NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_credit_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Migration guard — for installs where credit_entries already existed
// before payment_method was added to the schema above.
try { $db->exec("ALTER TABLE credit_entries ADD COLUMN payment_method VARCHAR(60) NULL AFTER paid_to"); }
catch (Throwable $e) { /* already exists */ }

// Same schema/logic as expenses.php's own version — kept separate (not
// shared) since expenses.php defines this locally, not in a shared
// includes file. Records money leaving the shared Cash in Hand fund when
// a converted expense's payment method is "Cash in Hand" — without this,
// the expense record itself would be correct, but the CIH balance
// wouldn't reflect that money actually left the fund.
function creditCihBalance($db) {
    $row = $db->query('SELECT balance_after FROM cash_in_hand_ledger ORDER BY id DESC LIMIT 1')->fetch();
    return $row ? (float)$row['balance_after'] : 0.0;
}
function creditRecordCashInHandMovement($db, $direction, $amount, $refType, $refId, $note, $userId) {
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
        $newBal = $direction === 'in' ? creditCihBalance($db) + $amount : creditCihBalance($db) - $amount;
        $stmt = $db->prepare('INSERT INTO cash_in_hand_ledger
            (entry_date, type, direction, amount, balance_after, reference_type, reference_id, note, created_by, created_at)
            VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute(['expense', $direction, $amount, $newBal, $refType, $refId, $note, $userId, date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
        error_log('creditRecordCashInHandMovement failed: ' . $e->getMessage());
    }
}

try {
    if ($method === 'GET') {
        if (!empty($_GET['id'])) {
            $stmt = $db->prepare('SELECT * FROM credit_entries WHERE id = ?');
            $stmt->execute([(int)$_GET['id']]);
            $row = $stmt->fetch();
            jsonResponse($row ? ['success' => true, 'data' => $row] : ['success' => false, 'error' => 'Not found']);
        }
        $stmt = $db->query('SELECT * FROM credit_entries ORDER BY entry_date DESC, id DESC');
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    $body = [];
    if ($method === 'POST') {
        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: [];
    }

    // ── CONVERT: create a real Expense row, lock this entry as done ──
    if ($method === 'POST' && $action === 'convert') {
        $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'Missing id'], 400);

        $stmt = $db->prepare('SELECT * FROM credit_entries WHERE id = ?');
        $stmt->execute([$id]);
        $entry = $stmt->fetch();
        if (!$entry) jsonResponse(['error' => 'Not found'], 404);
        if ($entry['status'] === 'converted') jsonResponse(['error' => 'This entry has already been converted.'], 400);

        // Values can be adjusted here at conversion time (this is the
        // "correction" step, since the entry itself is locked) — falls
        // back to the original entry's values if not overridden.
        $date     = trim($body['date']     ?? '') ?: $entry['entry_date'];
        $amount   = isset($body['amount']) && $body['amount'] !== '' ? (float)$body['amount'] : (float)$entry['amount'];
        $vendor   = trim($body['vendor']   ?? '') ?: ($entry['paid_to'] ?: $entry['purpose']);
        $category = trim($body['category'] ?? 'Other');
        $method_  = trim($body['method']   ?? '') ?: ($entry['payment_method'] ?: 'Cash');
        $notes    = trim($body['notes']    ?? $entry['purpose']);

        if (!$date || !$vendor || $amount <= 0) {
            jsonResponse(['error' => 'date, vendor, and amount are required'], 422);
        }

        $now = date('Y-m-d H:i:s');
        $exp = $db->prepare(
            'INSERT INTO expenses (`date`,category,vendor,amount,method,notes,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $exp->execute([$date, $category, $vendor, $amount, $method_, $notes, $now, $now]);
        $expenseId = (int)$db->lastInsertId();

        if ($method_ === 'Cash in Hand') {
            creditRecordCashInHandMovement($db, 'out', $amount, 'expense', $expenseId,
                "Credit converted: {$vendor}", (int)($_SESSION['user_id'] ?? 0));
        }

        $db->prepare(
            'UPDATE credit_entries SET status="converted", converted_expense_id=?, converted_at=? WHERE id=?'
        )->execute([$expenseId, $now, $id]);

        logActivity((int)($_SESSION['user_id'] ?? 0), 'convert', 'credit_entry', $id,
            "Converted credit entry to expense #{$expenseId}: {$vendor} — ₹" . number_format($amount, 2));

        jsonResponse(['success' => true, 'expense_id' => $expenseId]);
    }

    // ── POST — create (locked forever after; no PUT/DELETE at all) ──
    if ($method === 'POST') {
        $date    = trim($body['date']    ?? '');
        $amount  = (float)($body['amount'] ?? 0);
        $purpose = trim($body['purpose'] ?? '');
        $paidTo  = trim($body['paid_to'] ?? '');
        $payMethod = trim($body['payment_method'] ?? '');

        if (!$date || !$purpose || $amount <= 0) {
            jsonResponse(['error' => 'date, purpose, and amount are required'], 422);
        }

        $stmt = $db->prepare(
            'INSERT INTO credit_entries (entry_date, amount, purpose, paid_to, payment_method, created_by, created_at)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([$date, $amount, $purpose, $paidTo ?: null, $payMethod ?: null, (int)($_SESSION['user_id'] ?? 0), date('Y-m-d H:i:s')]);
        $newId = (int)$db->lastInsertId();

        logActivity((int)($_SESSION['user_id'] ?? 0), 'create', 'credit_entry', $newId,
            "Credit entry added: {$purpose} — ₹" . number_format($amount, 2));

        jsonResponse(['success' => true, 'id' => $newId]);
    }

    jsonResponse(['error' => 'Method not allowed'], 405);

} catch (Throwable $e) {
    error_log('credit_entries.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
}
