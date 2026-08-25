<?php
// ================================================================
//  api/credit_entries.php — Owner's personal-expense staging area
//
//  Quick-capture log for money the owner (or permitted staff) spent
//  personally, before it becomes a formal categorized Expense. An
//  entry can be edited (date/amount/purpose/paid_to/payment_method)
//  right up until it's FULLY converted — the Convert step still lets
//  you fix a typo at that moment too, but that only corrects the
//  resulting Expense row, not this source record, so a real edit
//  path matters. Once status reaches 'converted', it's locked: no
//  more edit, no delete — the paper trail to the Expense it became
//  must stay trustworthy from that point on.
//
//  GET    ?action=list              → all entries, newest first
//  GET    ?id=X                     → single entry
//  POST                             → create a new entry
//  PUT    ?id=X                     → edit an entry (pending/partial only)
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
    `status`              ENUM('pending','partial','converted') NOT NULL DEFAULT 'pending',
    `converted_amount`    DECIMAL(12,2) NOT NULL DEFAULT 0,
    `converted_expense_id` INT UNSIGNED NULL,
    `converted_at`        DATETIME      NULL,
    `created_by`          INT UNSIGNED  NULL,
    `created_at`          DATETIME      NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_credit_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// One row per conversion event — a single credit entry can now be
// converted in more than one part (e.g. ₹30 of a ₹100 entry today,
// the remaining ₹70 later), so "which expense(s) did this become" is
// a one-to-many relationship, not the single converted_expense_id
// column above (kept only for backward-compat display of the most
// recent conversion).
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

// Migration guards — for installs where credit_entries already existed
// before these columns/statuses were added.
try { $db->exec("ALTER TABLE credit_entries ADD COLUMN payment_method VARCHAR(60) NULL AFTER paid_to"); }
catch (Throwable $e) { /* already exists */ }
try { $db->exec("ALTER TABLE credit_entries ADD COLUMN converted_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER status"); }
catch (Throwable $e) { /* already exists */ }
try { $db->exec("ALTER TABLE credit_entries MODIFY COLUMN status ENUM('pending','partial','converted') NOT NULL DEFAULT 'pending'"); }
catch (Throwable $e) { /* already correct */ }

// NOTE: Cash in Hand is deliberately NOT integrated with this flow.
// A credit entry represents money already paid personally (by the
// owner or permitted staff), not money drawn from the shared Cash in
// Hand fund — so converting one to an Expense never touches that
// ledger. "Cash in Hand" is explicitly rejected as a payment method
// in the convert action below.

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

    // ── CONVERT: create a real Expense row for PART OR ALL of this
    // entry's amount. Only flips to fully "converted" once the running
    // total of all conversions reaches the original amount — before
    // that, it's "partial" and stays convertible for whatever remains.
    if ($method === 'POST' && $action === 'convert') {
        $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'Missing id'], 400);

        $stmt = $db->prepare('SELECT * FROM credit_entries WHERE id = ?');
        $stmt->execute([$id]);
        $entry = $stmt->fetch();
        if (!$entry) jsonResponse(['error' => 'Not found'], 404);
        if ($entry['status'] === 'converted') jsonResponse(['error' => 'This entry has already been fully converted.'], 400);

        $alreadyConverted = (float)$entry['converted_amount'];
        $remaining = round((float)$entry['amount'] - $alreadyConverted, 2);

        $date     = trim($body['date']     ?? '') ?: $entry['entry_date'];
        $amount   = isset($body['amount']) && $body['amount'] !== '' ? (float)$body['amount'] : $remaining;
        $vendor   = trim($body['vendor']   ?? '') ?: ($entry['paid_to'] ?: $entry['purpose']);
        $category = trim($body['category'] ?? 'Other');
        // Cash in Hand is deliberately NOT allowed here — a credit entry
        // represents money the owner/staff already paid personally, not
        // money drawn from the shared fund, so this conversion must never
        // touch that ledger. Rejected explicitly (not just excluded from
        // the UI dropdown) in case a request is ever crafted directly.
        $method_ = trim($body['method'] ?? '') ?: ($entry['payment_method'] ?: 'Cash');
        if ($method_ === 'Cash in Hand') {
            jsonResponse(['error' => 'Cash in Hand is not a valid payment method for a credit conversion — this money was paid personally, not drawn from the shared fund.'], 422);
        }
        $notes    = trim($body['notes']    ?? $entry['purpose']);

        if (!$date || !$vendor || $amount <= 0) {
            jsonResponse(['error' => 'date, vendor, and amount are required'], 422);
        }
        // Can't convert more than what's actually left — this is the
        // core of the bug fix: previously any amount here silently
        // marked the WHOLE entry as converted, even a partial one.
        if ($amount > $remaining + 0.004) {
            jsonResponse(['error' => "Only ₹" . number_format($remaining, 2) . " remains on this entry — can't convert ₹" . number_format($amount, 2) . "."], 422);
        }

        // Migration guard — traces which expenses came from a Credit
        // conversion vs. being entered directly, so the Expense ledger
        // can show a clear badge for these. NULL/'direct' = normal.
        try { $db->exec("ALTER TABLE expenses ADD COLUMN source VARCHAR(20) NULL AFTER method"); }
        catch (Throwable $e) { /* already exists */ }

        $now = date('Y-m-d H:i:s');
        $exp = $db->prepare(
            'INSERT INTO expenses (`date`,category,vendor,amount,method,source,notes,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $exp->execute([$date, $category, $vendor, $amount, $method_, 'credit', $notes, $now, $now]);
        $expenseId = (int)$db->lastInsertId();

        // No Cash in Hand ledger movement here at all — this money never
        // came from the shared fund, so nothing about it should ever
        // touch that ledger's balance.

        $db->prepare(
            'INSERT INTO credit_entry_conversions (credit_entry_id, expense_id, amount, created_by, created_at)
             VALUES (?,?,?,?,?)'
        )->execute([$id, $expenseId, $amount, (int)($_SESSION['user_id'] ?? 0), $now]);

        $newConverted = round($alreadyConverted + $amount, 2);
        $newStatus = ($newConverted >= (float)$entry['amount'] - 0.004) ? 'converted' : 'partial';

        $db->prepare(
            'UPDATE credit_entries SET status=?, converted_amount=?, converted_expense_id=?, converted_at=? WHERE id=?'
        )->execute([$newStatus, $newConverted, $expenseId, $now, $id]);

        logActivity((int)($_SESSION['user_id'] ?? 0), 'convert', 'credit_entry', $id,
            "Converted ₹" . number_format($amount, 2) . " of credit entry to expense #{$expenseId}: {$vendor}" .
            ($newStatus === 'partial' ? " (₹" . number_format((float)$entry['amount'] - $newConverted, 2) . " still remaining)" : ' (fully converted)'));

        jsonResponse(['success' => true, 'expense_id' => $expenseId, 'status' => $newStatus,
            'remaining' => round((float)$entry['amount'] - $newConverted, 2)]);
    }

    // ── EDIT — allowed while pending or partial, locked once fully
    // converted. Amount can't be reduced below what's already been
    // converted out of a partial entry, or the remaining/"X left"
    // math would go negative.
    if ($method === 'PUT') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'Missing id'], 400);

        $stmt = $db->prepare('SELECT * FROM credit_entries WHERE id = ?');
        $stmt->execute([$id]);
        $entry = $stmt->fetch();
        if (!$entry) jsonResponse(['error' => 'Not found'], 404);
        if ($entry['status'] === 'converted') {
            jsonResponse(['error' => 'This entry has already been fully converted and can no longer be edited.'], 400);
        }

        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: [];

        $date    = trim($body['date']    ?? '');
        $amount  = (float)($body['amount'] ?? 0);
        $purpose = trim($body['purpose'] ?? '');
        $paidTo  = trim($body['paid_to'] ?? '');
        $payMethod = trim($body['payment_method'] ?? '');

        if (!$date || !$purpose || $amount <= 0) {
            jsonResponse(['error' => 'date, purpose, and amount are required'], 422);
        }

        $alreadyConverted = (float)$entry['converted_amount'];
        if ($amount < $alreadyConverted - 0.004) {
            jsonResponse(['error' => "Amount can't be less than ₹" . number_format($alreadyConverted, 2) . " — that much has already been converted to an expense from this entry."], 422);
        }

        $db->prepare(
            'UPDATE credit_entries SET entry_date=?, amount=?, purpose=?, paid_to=?, payment_method=? WHERE id=?'
        )->execute([$date, $amount, $purpose, $paidTo ?: null, $payMethod ?: null, $id]);

        logActivity((int)($_SESSION['user_id'] ?? 0), 'update', 'credit_entry', $id,
            "Credit entry edited: {$purpose} — ₹" . number_format($amount, 2));

        jsonResponse(['success' => true]);
    }

    // ── POST — create ──
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
