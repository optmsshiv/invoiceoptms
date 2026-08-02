<?php
date_default_timezone_set('Asia/Kolkata');
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
//  POST ?action=topup          → add funds — requires action.cash_in_hand.edit
//  POST ?action=correction     → fix a mistaken entry WITHOUT rewriting
//                                 history — posts an offsetting adjustment
//                                 — requires action.cash_in_hand.delete,
//                                 note required
//  PATCH ?action=edit_topup    → direct edit, ONLY allowed when the topup
//                                 being edited is still the single latest
//                                 ledger row (nothing recorded since it),
//                                 so no other balance depends on it yet.
//                                 Otherwise rejected — use a correction
//                                 entry instead. Requires action.cash_in_hand.edit
//
//  Owner and super_admin always pass regardless of role_permissions
//  (matching getEffectivePermissions()'s ceiling-only rule for those two
//  roles). Other roles need action.cash_in_hand.edit / .delete granted
//  via Team Permissions — see migration note below; these fail SAFE
//  (denied) until the catalog rows exist, unlike most ?? true fallbacks
//  elsewhere in the app.
// ================================================================
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Cash in Hand permission keys. Deliberately their own keys (not the
// generic action.edit/action.delete) since this is a shared money fund,
// not a normal record. Fails SAFE (owner-only) if the catalog rows for
// these keys haven't been added to the `permissions` table yet — see the
// migration note in the header comment above — rather than defaulting
// open like most ?? true fallbacks elsewhere in the app.
function cihRequirePermission(string $key) {
  $role = $_SESSION['user_role'] ?? 'viewer';
  if (in_array($role, ['owner', 'super_admin'], true)) return currentUser();
  if (!can($key)) {
    jsonResponse(['error' => 'You don\'t have permission to manage Cash in Hand. Ask the owner to grant this in Team Permissions.'], 403);
  }
  return currentUser();
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

  // Auto-migrate: 'carry_forward' wasn't part of the original ENUM.
  try { $db->exec("ALTER TABLE cash_in_hand_ledger MODIFY COLUMN `type` ENUM('topup','purchase','expense','adjustment','carry_forward') NOT NULL DEFAULT 'topup'"); } catch (Throwable $e) { /* already migrated */ }
  // Auto-migrate: tracks which source session a carry_forward entry came
  // from, so a second transfer from the SAME source can be reliably
  // detected and blocked, instead of silently double-counting the money.
  try { $db->exec("ALTER TABLE cash_in_hand_ledger ADD COLUMN source_end_date DATE NULL"); } catch (Throwable $e) { /* already exists */ }

  // Blocks an action if the given session (identified by its own end date)
  // was already carried forward elsewhere AND the strict restriction is
  // currently enabled. Shared by topup and correction — both represent
  // editing a session's own balance, which shouldn't drift after that
  // balance has already been moved (and likely spent) somewhere else.
  function cihCheckCarriedRestriction(PDO $db, ?string $sessionToDate): void {
    if (!$sessionToDate) return; // no session context given — nothing to check
    if (getSetting('cih_restrict_carried_sessions', '1') !== '1') return; // restriction turned off
    $stmt = $db->prepare(
      "SELECT l.amount, l.created_at, u.name AS by_name
       FROM cash_in_hand_ledger l LEFT JOIN users u ON u.id = l.created_by
       WHERE l.type = 'carry_forward' AND l.source_end_date = ? LIMIT 1"
    );
    $stmt->execute([$sessionToDate]);
    $row = $stmt->fetch();
    if (!$row) return;
    jsonResponse(['error' => 'This session\'s closing balance (₹' . number_format($row['amount'], 2) .
      ') was already carried forward on ' . date('d-m-Y', strtotime($row['created_at'])) . ' by ' . ($row['by_name'] ?: 'someone') .
      '. Editing this session further could drift from what was actually carried. Turn off the restriction in Settings if this correction is genuinely needed.'], 400);
  }

  function cihCurrentBalance(PDO $db): float {
    $row = $db->query('SELECT balance_after FROM cash_in_hand_ledger ORDER BY id DESC LIMIT 1')->fetch();
    return $row ? (float)$row['balance_after'] : 0.0;
  }

  // ── GET: balance + paginated (optionally date-filtered) history ────
  if ($method === 'GET' && empty($_GET['breakdown']) && empty($_GET['check_carried'])) {
    $balance = cihCurrentBalance($db);
    $limit   = min(200, max(1, (int)($_GET['limit'] ?? 50)));
    $offset  = max(0, (int)($_GET['offset'] ?? 0));

    $where = '1=1';
    $params = [];
    if (!empty($_GET['from'])) { $where .= ' AND l.entry_date >= ?'; $params[] = $_GET['from']; }
    if (!empty($_GET['to']))   { $where .= ' AND l.entry_date <= ?'; $params[] = $_GET['to']; }

    $sessionScoped = !empty($_GET['from']) && !empty($_GET['to']);

    if ($sessionScoped) {
      // A session should show its OWN running balance — starting fresh at
      // ₹0 unless a Carry Forward entry was explicitly added — not the
      // true all-time cumulative figure. Otherwise old money from before
      // the session would silently appear here even without ever using
      // Carry Forward, which defeats the point of that feature entirely.
      // Fetch everything in the range chronologically first (can't paginate
      // at the SQL level and still compute a correct running total), walk
      // forward to assign each row its session-relative balance, then
      // paginate + reverse for display to match the existing newest-first UI.
      $allStmt = $db->prepare("SELECT l.*, u.name AS created_by_name
                             FROM cash_in_hand_ledger l
                             LEFT JOIN users u ON u.id = l.created_by
                             WHERE {$where}
                             ORDER BY l.entry_date ASC, l.created_at ASC, l.id ASC");
      $allStmt->execute($params);
      $allRows = $allStmt->fetchAll();

      $running = 0;
      foreach ($allRows as &$row) {
        $running += ($row['direction'] === 'in' ? 1 : -1) * (float)$row['amount'];
        $row['balance_after'] = $running; // override the stored all-time value with the session-relative one
      }
      unset($row);

      $total = count($allRows);
      $newestFirst = array_reverse($allRows);
      $rows = array_slice($newestFirst, $offset, $limit);
    } else {
      $stmt = $db->prepare("SELECT l.*, u.name AS created_by_name
                             FROM cash_in_hand_ledger l
                             LEFT JOIN users u ON u.id = l.created_by
                             WHERE {$where}
                             ORDER BY l.id DESC LIMIT {$limit} OFFSET {$offset}");
      $stmt->execute($params);
      $rows = $stmt->fetchAll();

      $countStmt = $db->prepare("SELECT COUNT(*) FROM cash_in_hand_ledger l WHERE {$where}");
      $countStmt->execute($params);
      $total = (int)$countStmt->fetchColumn();
    }

    // The single latest row overall (not affected by the date filter) —
    // the frontend uses this to know which topup, if any, is still safely
    // editable (nothing recorded since it).
    $latestId = (int)($db->query('SELECT id FROM cash_in_hand_ledger ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);

    // Balance AS OF the 'to' date — used when the Global Date Range filter
    // is active, so the fund's balance shown matches "what it was at the
    // end of this period" rather than the true live balance. Only computed
    // when a 'to' is actually given; the running-balance ledger already
    // tracks everything, this just sums up to a cutoff instead of all-time.
    $balanceAsOf = null;
    if (!empty($_GET['to'])) {
      $asOfStmt = $db->prepare(
        "SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE -amount END),0)
         FROM cash_in_hand_ledger WHERE entry_date <= ?"
      );
      $asOfStmt->execute([$_GET['to']]);
      $balanceAsOf = (float)$asOfStmt->fetchColumn();
    }

    jsonResponse([
      'balance' => $balance, 'balance_as_of' => $balanceAsOf, 'data' => $rows,
      'total' => $total, 'latest_id' => $latestId,
    ]);
  }

  // ── GET ?breakdown=1&from=&to=: Total In / Total Out + spend breakdown
  // for a date range. Total Out is computed from the LIVE Purchases/
  // Expenses tables (not the ledger's edit/reversal history) — same
  // source of truth Finance Report already uses, so it can never
  // double-count a purchase that was later edited, and always matches
  // what Purchases/Expenses actually show.
  // ── GET ?check_carried=1&to=: was THIS session's closing balance
  // already carried forward elsewhere? Used for the warning banner and
  // to gate Add Funds/Correction when the strict restriction is on.
  if ($method === 'GET' && !empty($_GET['check_carried'])) {
    $to = $_GET['to'] ?? null;
    if (!$to) jsonResponse(['carried' => false]);
    $stmt = $db->prepare(
      "SELECT l.amount, l.entry_date, l.created_at, u.name AS by_name
       FROM cash_in_hand_ledger l LEFT JOIN users u ON u.id = l.created_by
       WHERE l.type = 'carry_forward' AND l.source_end_date = ? LIMIT 1"
    );
    $stmt->execute([$to]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(['carried' => false]);
    jsonResponse([
      'carried' => true, 'amount' => (float)$row['amount'], 'to_date' => $row['entry_date'],
      'by_name' => $row['by_name'] ?: 'someone', 'when' => $row['created_at'],
      'restrict_enabled' => getSetting('cih_restrict_carried_sessions', '1') === '1',
    ]);
  }

  if ($method === 'GET' && !empty($_GET['breakdown'])) {
    $from = $_GET['from'] ?? date('Y-m-01');
    $to   = $_GET['to']   ?? date('Y-m-d');

    $inStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM cash_in_hand_ledger
                             WHERE direction='in' AND entry_date BETWEEN ? AND ?");
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

    // Negative corrections (type='adjustment', direction='out') — safe to
    // add directly since 'adjustment' entries have no live Purchase/Expense
    // row to double-count against, unlike purchase/expense-driven entries.
    $adjOutStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM cash_in_hand_ledger
                                 WHERE direction='out' AND type='adjustment' AND entry_date BETWEEN ? AND ?");
    $adjOutStmt->execute([$from, $to]);
    $adjOutAmt = (float)$adjOutStmt->fetchColumn();

    $breakdown = [];
    if ($purchaseAmt > 0) $breakdown[] = ['bucket' => 'Purchase', 'amount' => $purchaseAmt];
    foreach ($expRows as $r) {
      if ((float)$r['amt'] > 0) $breakdown[] = ['bucket' => $r['category'] ?: 'Other', 'amount' => (float)$r['amt']];
    }
    if ($adjOutAmt > 0) $breakdown[] = ['bucket' => 'Correction', 'amount' => $adjOutAmt];
    usort($breakdown, fn($a, $b) => $b['amount'] <=> $a['amount']);
    $totalOut = $purchaseAmt + array_sum(array_column($expRows, 'amt')) + $adjOutAmt;

    jsonResponse(['total_in' => $totalIn, 'total_out' => $totalOut, 'breakdown' => $breakdown]);
  }

  // ── POST: top up ────────────────────────────────────────────
  if ($method === 'POST' && $action === 'topup') {
    $user = cihRequirePermission('action.cash_in_hand.edit');

    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $amount = (float)($d['amount'] ?? 0);
    if ($amount <= 0) jsonResponse(['error' => 'Enter an amount greater than 0'], 400);
    $date = $d['date'] ?? date('Y-m-d');
    $note = trim($d['note'] ?? '') ?: 'Funds added';
    cihCheckCarriedRestriction($db, $d['session_to_date'] ?? null);

    $newBalance = cihCurrentBalance($db) + $amount;
    $stmt = $db->prepare(
      "INSERT INTO cash_in_hand_ledger
         (entry_date, type, direction, amount, balance_after, reference_type, note, created_by, created_at)
       VALUES (?, 'topup', 'in', ?, ?, 'topup', ?, ?, ?)"
    );
    $stmt->execute([$date, $amount, $newBalance, $note, (int)($user['id'] ?? 0), date('Y-m-d H:i:s')]);

    logActivity((int)($user['id'] ?? 0), 'topup', 'cash_in_hand', (int)$db->lastInsertId(),
      'Cash in Hand funded: ₹' . number_format($amount, 2));

    jsonResponse(['success' => true, 'balance' => $newBalance]);
  }

  // ── POST: carry forward — moves a past session's true closing balance
  // into the currently active session as one real, traceable ledger
  // entry (not a cosmetic recalculation). The source session is always
  // explicitly chosen by the user, never auto-detected, since sessions
  // aren't guaranteed to be chronologically sequential.
  if ($method === 'POST' && $action === 'carry_forward') {
    $user = cihRequirePermission('action.cash_in_hand.edit');

    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $sourceToDate = $d['source_to_date'] ?? null;   // end date of the session being carried FROM
    $entryDate    = $d['entry_date'] ?? null;        // start date of the session being carried INTO
    $sourceName   = trim($d['source_session_name'] ?? 'a previous session');
    if (!$sourceToDate || !$entryDate) jsonResponse(['error' => 'Missing source or target session date'], 400);

    // Block a second transfer from the SAME source session — otherwise
    // the same money gets counted twice if someone double-clicks, or a
    // teammate does the same transfer without realizing it's already done.
    $dupStmt = $db->prepare(
      "SELECT l.amount, l.entry_date, l.created_at, u.name AS by_name
       FROM cash_in_hand_ledger l LEFT JOIN users u ON u.id = l.created_by
       WHERE l.type = 'carry_forward' AND l.source_end_date = ? LIMIT 1"
    );
    $dupStmt->execute([$sourceToDate]);
    $dup = $dupStmt->fetch();
    if ($dup) {
      jsonResponse(['error' => 'Already carried forward from this session on ' . date('d-m-Y', strtotime($dup['created_at'])) .
        ' by ' . ($dup['by_name'] ?: 'someone') . ' — ₹' . number_format($dup['amount'], 2) .
        '. Carrying forward again would count that money twice.'], 400);
    }

    // True cumulative closing balance of the source session — everything
    // up to and including its end date, same calculation the balance
    // card itself uses, not just that session's own isolated net.
    $balStmt = $db->prepare(
      "SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE -amount END),0)
       FROM cash_in_hand_ledger WHERE entry_date <= ?"
    );
    $balStmt->execute([$sourceToDate]);
    $closingBalance = (float)$balStmt->fetchColumn();

    if (abs($closingBalance) < 0.01) jsonResponse(['error' => 'That session\'s closing balance is ₹0 — nothing to carry forward'], 400);

    $direction = $closingBalance >= 0 ? 'in' : 'out';
    $absAmount = abs($closingBalance);
    $newBalance = $direction === 'in' ? cihCurrentBalance($db) + $absAmount : cihCurrentBalance($db) - $absAmount;
    $note = 'Carried forward from ' . $sourceName . ' (closing balance as of ' . $sourceToDate . ')';

    $stmt = $db->prepare(
      "INSERT INTO cash_in_hand_ledger
         (entry_date, type, direction, amount, balance_after, reference_type, note, created_by, created_at, source_end_date)
       VALUES (?, 'carry_forward', ?, ?, ?, 'carry_forward', ?, ?, ?, ?)"
    );
    $stmt->execute([$entryDate, $direction, $absAmount, $newBalance, $note, (int)($user['id'] ?? 0), date('Y-m-d H:i:s'), $sourceToDate]);

    logActivity((int)($user['id'] ?? 0), 'carry_forward', 'cash_in_hand', (int)$db->lastInsertId(),
      'Cash in Hand: ' . $note);

    jsonResponse(['success' => true, 'balance' => $newBalance, 'amount' => $closingBalance]);
  }

  // ── POST: correction — fixes a mistake WITHOUT rewriting history.
  // Posts a standalone offsetting entry (can be positive or negative) with
  // a required note explaining why, so both the original mistaken entry
  // and the fix stay visible in the ledger — same principle as a
  // correcting journal entry in real bookkeeping, rather than silently
  // editing the past.
  if ($method === 'POST' && $action === 'correction') {
    $user = cihRequirePermission('action.cash_in_hand.delete');

    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $amount = (float)($d['amount'] ?? 0);
    if ($amount == 0) jsonResponse(['error' => 'Enter a non-zero amount (positive to add, negative to remove)'], 400);
    $note = trim($d['note'] ?? '');
    if (!$note) jsonResponse(['error' => 'A note explaining the correction is required'], 400);
    $date = $d['date'] ?? date('Y-m-d');
    cihCheckCarriedRestriction($db, $d['session_to_date'] ?? null);

    $direction = $amount > 0 ? 'in' : 'out';
    $absAmount = abs($amount);
    $newBalance = $direction === 'in' ? cihCurrentBalance($db) + $absAmount : cihCurrentBalance($db) - $absAmount;

    $stmt = $db->prepare(
      "INSERT INTO cash_in_hand_ledger
         (entry_date, type, direction, amount, balance_after, reference_type, note, created_by, created_at)
       VALUES (?, 'adjustment', ?, ?, ?, 'adjustment', ?, ?, ?)"
    );
    $stmt->execute([$date, $direction, $absAmount, $newBalance, 'Correction: ' . $note, (int)($user['id'] ?? 0), date('Y-m-d H:i:s')]);

    logActivity((int)($user['id'] ?? 0), 'correction', 'cash_in_hand', (int)$db->lastInsertId(),
      'Cash in Hand correction: ' . ($amount > 0 ? '+' : '-') . '₹' . number_format($absAmount, 2) . ' — ' . $note);

    jsonResponse(['success' => true, 'balance' => $newBalance]);
  }

  // ── PATCH: direct edit of a topup — ONLY when it's still the single
  // latest row in the whole ledger (nothing recorded since it, so no
  // other balance depends on the value being changed). Otherwise
  // rejected outright — use a correction entry instead.
  if ($method === 'PATCH' && $action === 'edit_topup') {
    $user = cihRequirePermission('action.cash_in_hand.edit');

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
