<?php
// ================================================================
//  api/cash_in_hand.php — Cash in Hand fund ledger
//
//  A single shared cash pool the Owner tops up, which Sales
//  Managers/Managers can then draw from when making Purchases or
//  Expenses (payment_mode / method = "Cash in Hand"). The balance
//  is allowed to go negative — the UI flags this, it isn't blocked
//  here — since the point is visibility, not enforcement.
//
//  GET                         → current balance + paginated ledger history
//    ?limit=&offset=           → pagination (default limit 50)
//    ?from=&to=                → optional date filter on entry_date
//  POST ?action=topup          → add funds (Owner only)
//  POST ?action=correction     → fix a mistaken entry WITHOUT rewriting
//                                 history — posts an offsetting adjustment
//                                 (Owner only, note required)
//  PATCH ?action=edit_topup    → direct edit, ONLY allowed when the topup
//                                 being edited is still the single latest
//                                 ledger row (nothing recorded since it),
//                                 so no other balance depends on it yet.
//                                 Otherwise rejected — use a correction
//                                 entry instead. (Owner only)
// ================================================================
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function cihRequireOwner() {
  // TODO: this should become a real permission-catalog entry
  // (e.g. action.cash_in_hand.manage) checked via getEffectivePermissions(),
  // consistent with every other permission in the app, instead of a
  // hardcoded role check. Left as-is pending that wiring — see chat.
  $user = currentUser();
  if (($user['role'] ?? '') !== 'owner') {
    jsonResponse(['error' => 'Only the owner can manage Cash in Hand funds'], 403);
  }
  return $user;
}

try {
  $db->exec("CREATE TABLE IF NOT EXISTS `cash_in_hand_ledger` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entry_date`     DATE NOT NULL,
    `type`           ENUM('topup','purchase','expense','adjustment') NOT NULL DEFAULT 'topup',
    `direction`      ENUM('in','out') NOT NULL DEFAULT 'in',
    `amount`         DECIMAL(12,2) NOT NULL DEFAULT 0,
    `balance_after`  DECIMAL(12,2) NOT NULL DEFAULT 0,
    `reference_type` VARCHAR(30)  DEFAULT NULL COMMENT 'purchase | expense | topup | adjustment',
    `reference_id`   INT UNSIGNED DEFAULT NULL,
    `note`           VARCHAR(255) DEFAULT NULL,
    `created_by`     INT UNSIGNED DEFAULT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_cih_date` (`entry_date`),
    INDEX `idx_cih_ref`  (`reference_type`,`reference_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  function cihCurrentBalance(PDO $db): float {
    $row = $db->query('SELECT balance_after FROM cash_in_hand_ledger ORDER BY id DESC LIMIT 1')->fetch();
    return $row ? (float)$row['balance_after'] : 0.0;
  }

  // ── GET: balance + paginated (optionally date-filtered) history ────
  if ($method === 'GET' && empty($_GET['breakdown'])) {
    $balance = cihCurrentBalance($db);
    $limit   = min(200, max(1, (int)($_GET['limit'] ?? 50)));
    $offset  = max(0, (int)($_GET['offset'] ?? 0));

    $where = '1=1';
    $params = [];
    if (!empty($_GET['from'])) { $where .= ' AND l.entry_date >= ?'; $params[] = $_GET['from']; }
    if (!empty($_GET['to']))   { $where .= ' AND l.entry_date <= ?'; $params[] = $_GET['to']; }

    $stmt = $db->prepare("SELECT l.*, u.name AS created_by_name
                           FROM cash_in_hand_ledger l
                           LEFT JOIN users u ON u.id = l.created_by
                           WHERE {$where}
                           ORDER BY l.id DESC LIMIT {$limit} OFFSET {$offset}");
    $stmt->execute($params);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM cash_in_hand_ledger l WHERE {$where}");
    $countStmt->execute($params);

    // The single latest row overall (not affected by the date filter) —
    // the frontend uses this to know which topup, if any, is still safely
    // editable (nothing recorded since it).
    $latestId = (int)($db->query('SELECT id FROM cash_in_hand_ledger ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);

    jsonResponse([
      'balance' => $balance, 'data' => $stmt->fetchAll(),
      'total' => (int)$countStmt->fetchColumn(), 'latest_id' => $latestId,
    ]);
  }

  // ── GET ?breakdown=1&from=&to=: Total In / Total Out + spend breakdown
  // for a date range. Total Out is computed from the LIVE Purchases/
  // Expenses tables (not the ledger's edit/reversal history) — same
  // source of truth Finance Report already uses, so it can never
  // double-count a purchase that was later edited, and always matches
  // what Purchases/Expenses actually show.
  if ($method === 'GET' && !empty($_GET['breakdown'])) {
    $from = $_GET['from'] ?? date('Y-m-01');
    $to   = $_GET['to']   ?? date('Y-m-d');

    $inStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM cash_in_hand_ledger
                             WHERE direction='in' AND type='topup' AND entry_date BETWEEN ? AND ?");
    $inStmt->execute([$from, $to]);
    $totalIn = (float)$inStmt->fetchColumn();

    $purStmt = $db->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM purchases
                              WHERE payment_mode = 'Cash in Hand' AND purchase_date BETWEEN ? AND ?");
    $purStmt->execute([$from, $to]);
    $purchaseAmt = (float)$purStmt->fetchColumn();

    $expStmt = $db->prepare("SELECT category, COALESCE(SUM(amount),0) amt FROM expenses
                              WHERE method = 'Cash in Hand' AND `date` BETWEEN ? AND ?
                              GROUP BY category ORDER BY amt DESC");
    $expStmt->execute([$from, $to]);
    $expRows = $expStmt->fetchAll();

    $breakdown = [];
    if ($purchaseAmt > 0) $breakdown[] = ['bucket' => 'Purchase', 'amount' => $purchaseAmt];
    foreach ($expRows as $r) {
      if ((float)$r['amt'] > 0) $breakdown[] = ['bucket' => $r['category'] ?: 'Other', 'amount' => (float)$r['amt']];
    }
    usort($breakdown, fn($a, $b) => $b['amount'] <=> $a['amount']);
    $totalOut = $purchaseAmt + array_sum(array_column($expRows, 'amt'));

    jsonResponse(['total_in' => $totalIn, 'total_out' => $totalOut, 'breakdown' => $breakdown]);
  }

  // ── POST: top up (Owner only) ─────────────────────────────────
  if ($method === 'POST' && $action === 'topup') {
    $user = cihRequireOwner();

    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $amount = (float)($d['amount'] ?? 0);
    if ($amount <= 0) jsonResponse(['error' => 'Enter an amount greater than 0'], 400);
    $date = $d['date'] ?? date('Y-m-d');
    $note = trim($d['note'] ?? '') ?: 'Funds added';

    $newBalance = cihCurrentBalance($db) + $amount;
    $stmt = $db->prepare(
      "INSERT INTO cash_in_hand_ledger
         (entry_date, type, direction, amount, balance_after, reference_type, note, created_by)
       VALUES (?, 'topup', 'in', ?, ?, 'topup', ?, ?)"
    );
    $stmt->execute([$date, $amount, $newBalance, $note, (int)($user['id'] ?? 0)]);

    logActivity((int)($user['id'] ?? 0), 'topup', 'cash_in_hand', (int)$db->lastInsertId(),
      'Cash in Hand funded: ₹' . number_format($amount, 2));

    jsonResponse(['success' => true, 'balance' => $newBalance]);
  }

  // ── POST: correction — fixes a mistake WITHOUT rewriting history.
  // Posts a standalone offsetting entry (can be positive or negative) with
  // a required note explaining why, so both the original mistaken entry
  // and the fix stay visible in the ledger — same principle as a
  // correcting journal entry in real bookkeeping, rather than silently
  // editing the past.
  if ($method === 'POST' && $action === 'correction') {
    $user = cihRequireOwner();

    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $amount = (float)($d['amount'] ?? 0);
    if ($amount == 0) jsonResponse(['error' => 'Enter a non-zero amount (positive to add, negative to remove)'], 400);
    $note = trim($d['note'] ?? '');
    if (!$note) jsonResponse(['error' => 'A note explaining the correction is required'], 400);
    $date = $d['date'] ?? date('Y-m-d');

    $direction = $amount > 0 ? 'in' : 'out';
    $absAmount = abs($amount);
    $newBalance = $direction === 'in' ? cihCurrentBalance($db) + $absAmount : cihCurrentBalance($db) - $absAmount;

    $stmt = $db->prepare(
      "INSERT INTO cash_in_hand_ledger
         (entry_date, type, direction, amount, balance_after, reference_type, note, created_by)
       VALUES (?, 'adjustment', ?, ?, ?, 'adjustment', ?, ?)"
    );
    $stmt->execute([$date, $direction, $absAmount, $newBalance, 'Correction: ' . $note, (int)($user['id'] ?? 0)]);

    logActivity((int)($user['id'] ?? 0), 'correction', 'cash_in_hand', (int)$db->lastInsertId(),
      'Cash in Hand correction: ' . ($amount > 0 ? '+' : '-') . '₹' . number_format($absAmount, 2) . ' — ' . $note);

    jsonResponse(['success' => true, 'balance' => $newBalance]);
  }

  // ── PATCH: direct edit of a topup — ONLY when it's still the single
  // latest row in the whole ledger (nothing recorded since it, so no
  // other balance depends on the value being changed). Otherwise
  // rejected outright — use a correction entry instead.
  if ($method === 'PATCH' && $action === 'edit_topup') {
    $user = cihRequireOwner();

    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);
    $id = (int)($d['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id is required'], 400);

    $entryStmt = $db->prepare('SELECT * FROM cash_in_hand_ledger WHERE id = ?');
    $entryStmt->execute([$id]);
    $entry = $entryStmt->fetch();
    if (!$entry) jsonResponse(['error' => 'Entry not found'], 404);
    if ($entry['type'] !== 'topup') jsonResponse(['error' => 'Only top-up entries can be edited'], 400);

    $latestId = (int)($db->query('SELECT id FROM cash_in_hand_ledger ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);
    if ($id != $latestId) {
      jsonResponse(['error' => 'This entry can no longer be edited directly — other activity (a purchase, expense, or another top-up) has happened since it was added, so changing it now would make the balance history inconsistent. Add a correction entry instead.'], 409);
    }

    $newAmount = (float)($d['amount'] ?? 0);
    if ($newAmount <= 0) jsonResponse(['error' => 'Enter an amount greater than 0'], 400);
    $newNote = trim($d['note'] ?? '') ?: 'Funds added';
    $newDate = $d['date'] ?? $entry['entry_date'];

    // Balance before this entry = the row before it (or 0 if this is the very first row ever)
    $prevStmt = $db->prepare('SELECT balance_after FROM cash_in_hand_ledger WHERE id < ? ORDER BY id DESC LIMIT 1');
    $prevStmt->execute([$id]);
    $prevBalance = (float)($prevStmt->fetchColumn() ?: 0);
    $newBalance = $prevBalance + $newAmount;

    $db->prepare('UPDATE cash_in_hand_ledger SET entry_date=?, amount=?, balance_after=?, note=? WHERE id=?')
       ->execute([$newDate, $newAmount, $newBalance, $newNote, $id]);

    logActivity((int)($user['id'] ?? 0), 'edit', 'cash_in_hand', $id,
      "Cash in Hand top-up edited: {$entry['amount']} → {$newAmount}");

    jsonResponse(['success' => true, 'balance' => $newBalance]);
  }

  jsonResponse(['error' => 'Unknown request'], 400);

} catch (Throwable $e) {
  error_log('cash_in_hand.php error: ' . $e->getMessage());
  jsonResponse(['error' => 'Cash in Hand API error: ' . $e->getMessage()], 500);
}
