<?php
// ================================================================
//  includes/cash_book.php — the ONE place that writes to the Cash Book
//  ledger (table `cash_in_hand_ledger`; the table name is unchanged on
//  purpose so old data, permission keys and the API keep working).
//
//  Before this file existed, purchases.php, expenses.php and
//  cash_in_hand.php each carried their own copy of "read the last
//  balance, insert a row". Three copies drift apart, and none of them
//  was safe when two entries were saved at the same moment.
//
//  What this file guarantees
//   1. SERIALISED  — a MySQL named lock is held from "read balance"
//      until the row is committed, so two simultaneous saves can never
//      both start from the same balance.
//   2. ATOMIC      — cbBegin()/cbCommit()/cbRollback() let a caller
//      save its own record AND its ledger row together, or neither.
//   3. TRACEABLE   — a reversal keeps the ORIGINAL reference_type and
//      reference_id (type = 'adjustment'). Before, every reversal was
//      stored as reference_type 'adjustment', so purchase #12 and
//      expense #12 could not be told apart.
//   4. IDEMPOTENT  — cbReverse() works out what is still outstanding for
//      a reference and reverses exactly that. Calling it twice, or on a
//      record that never touched the book, posts nothing.
//
//  IMPORTANT: DDL (CREATE/ALTER TABLE) silently commits an open
//  transaction in MySQL. cbEnsureSchema() therefore must run BEFORE
//  cbBegin(), never inside it. Every endpoint calls it at the top.
//
//  This file never calls jsonResponse(); it returns values or throws, so
//  each endpoint decides how to answer.
// ================================================================

const CB_TYPES = ['topup', 'purchase', 'expense', 'adjustment', 'carry_forward'];

// ── Schema ────────────────────────────────────────────────────────
function cbEnsureSchema(PDO $db): void {
  static $done = false;
  if ($done) return;

  $db->exec("CREATE TABLE IF NOT EXISTS `cash_in_hand_ledger` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entry_date`      DATE NOT NULL,
    `type`            ENUM('topup','purchase','expense','adjustment','carry_forward') NOT NULL DEFAULT 'topup',
    `direction`       ENUM('in','out') NOT NULL DEFAULT 'in',
    `amount`          DECIMAL(12,2) NOT NULL DEFAULT 0,
    `balance_after`   DECIMAL(12,2) NOT NULL DEFAULT 0,
    `reference_type`  VARCHAR(30)  DEFAULT NULL,
    `reference_id`    INT UNSIGNED DEFAULT NULL,
    `note`            VARCHAR(255) DEFAULT NULL,
    `created_by`      INT UNSIGNED DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `source_end_date` DATE NULL,
    `transfer_id`     INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_cih_date` (`entry_date`),
    INDEX `idx_cih_ref`  (`reference_type`,`reference_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  // Older installs: the ENUM may lack 'carry_forward'. Only ALTER when needed.
  try {
    $t = $db->query("SHOW COLUMNS FROM `cash_in_hand_ledger` LIKE 'type'")->fetch();
    if ($t && stripos((string)$t['Type'], 'carry_forward') === false) {
      $db->exec("ALTER TABLE `cash_in_hand_ledger` MODIFY COLUMN `type` ENUM('topup','purchase','expense','adjustment','carry_forward') NOT NULL DEFAULT 'topup'");
    }
  } catch (Throwable $e) { error_log('cbEnsureSchema enum: ' . $e->getMessage()); }

  // Older installs: add the columns only if missing.
  try {
    $cols = $db->query("SHOW COLUMNS FROM `cash_in_hand_ledger`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('source_end_date', $cols, true)) $db->exec("ALTER TABLE `cash_in_hand_ledger` ADD COLUMN `source_end_date` DATE NULL");
    if (!in_array('transfer_id', $cols, true))     $db->exec("ALTER TABLE `cash_in_hand_ledger` ADD COLUMN `transfer_id` INT UNSIGNED NULL");
  } catch (Throwable $e) { error_log('cbEnsureSchema columns: ' . $e->getMessage()); }

  $done = true;
}

// ── Locking + transaction (re-entrant) ────────────────────────────
// State is kept per request. depth counts nested cbBegin() calls so a
// helper that wraps itself (cbPost) can safely be called from inside a
// caller's own cbBegin()..cbCommit() block.
function &_cbRef(): array {
  static $s = ['depth' => 0, 'owns_tx' => false, 'lock' => null];
  return $s;
}

function _cbLockName(PDO $db): string {
  // Named locks are server-wide, and this app is multi-tenant (one
  // database per tenant) — so the lock name includes the database name,
  // otherwise one tenant's save would make every other tenant wait.
  $dbName = (string)$db->query('SELECT DATABASE()')->fetchColumn();
  return 'cashbook_' . substr(md5($dbName), 0, 20);
}

function cbBegin(PDO $db): void {
  $s = &_cbRef();
  if ($s['depth'] === 0) {
    $name = _cbLockName($db);
    $got = $db->query('SELECT GET_LOCK(' . $db->quote($name) . ', 10)')->fetchColumn();
    if ((int)$got !== 1) {
      throw new RuntimeException('The Cash Book is busy right now. Please try again in a moment.');
    }
    $s['lock'] = $name;
    // If the caller already opened a transaction, join it and leave the
    // commit to them; otherwise open our own.
    if ($db->inTransaction()) {
      $s['owns_tx'] = false;
    } else {
      $db->beginTransaction();
      $s['owns_tx'] = true;
    }
  }
  $s['depth']++;
}

function _cbRelease(PDO $db): void {
  $s = &_cbRef();
  if ($s['lock'] !== null) {
    try { $db->query('SELECT RELEASE_LOCK(' . $db->quote($s['lock']) . ')')->fetchColumn(); } catch (Throwable $e) { /* connection gone */ }
  }
  $s['lock'] = null;
  $s['owns_tx'] = false;
  $s['depth'] = 0;
}

function cbCommit(PDO $db): void {
  $s = &_cbRef();
  if ($s['depth'] <= 0) return;
  $s['depth']--;
  if ($s['depth'] === 0) {
    try {
      if ($s['owns_tx'] && $db->inTransaction()) $db->commit();
    } finally {
      _cbRelease($db); // always let go of the lock, even if the commit threw
    }
  }
}

function cbRollback(PDO $db): void {
  $s = &_cbRef();
  if ($s['depth'] <= 0) return;
  try {
    if ($s['owns_tx'] && $db->inTransaction()) $db->rollBack();
  } catch (Throwable $e) { /* already gone */ }
  _cbRelease($db);
}

// ── Reading ───────────────────────────────────────────────────────
// Current balance = the last row's stored balance_after. FOR UPDATE makes
// it a locking read, which always sees the latest committed row (a plain
// read inside an older snapshot could be stale).
function cbBalance(PDO $db, bool $forUpdate = false): float {
  $sql = 'SELECT balance_after FROM cash_in_hand_ledger ORDER BY id DESC LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
  $row = $db->query($sql)->fetch();
  return $row ? (float)$row['balance_after'] : 0.0;
}

// ── Writing ───────────────────────────────────────────────────────
function cbDateOnly($v): string {
  if ($v) {
    $ts = strtotime((string)$v);
    if ($ts !== false) return date('Y-m-d', $ts);
  }
  return date('Y-m-d');
}

/**
 * Post ONE ledger entry. Returns the new row id, or 0 when amount <= 0.
 * $e keys: type, direction ('in'|'out'), amount, date, ref_type, ref_id,
 *          note, user_id, source_end_date (opt), transfer_id (opt)
 * Throws on failure — the caller decides whether that blocks its save.
 */
function cbPost(PDO $db, array $e): int {
  $amount = round((float)($e['amount'] ?? 0), 2);
  if ($amount <= 0) return 0;

  $dir = $e['direction'] ?? '';
  if ($dir !== 'in' && $dir !== 'out') throw new InvalidArgumentException('Cash Book entry needs direction in/out');
  $type = $e['type'] ?? 'adjustment';
  if (!in_array($type, CB_TYPES, true)) throw new InvalidArgumentException('Unknown Cash Book entry type: ' . $type);

  $note = $e['note'] ?? null;
  if ($note !== null) $note = mb_substr((string)$note, 0, 255);

  cbBegin($db);
  try {
    $bal    = cbBalance($db, true);
    $newBal = round($dir === 'in' ? $bal + $amount : $bal - $amount, 2);

    $stmt = $db->prepare(
      'INSERT INTO cash_in_hand_ledger
         (entry_date, type, direction, amount, balance_after, reference_type, reference_id,
          note, created_by, created_at, source_end_date, transfer_id)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
      cbDateOnly($e['date'] ?? null), $type, $dir, $amount, $newBal,
      $e['ref_type'] ?? null,
      isset($e['ref_id']) && $e['ref_id'] !== null ? (int)$e['ref_id'] : null,
      $note,
      isset($e['user_id']) && $e['user_id'] ? (int)$e['user_id'] : null,
      date('Y-m-d H:i:s'),
      $e['source_end_date'] ?? null,
      isset($e['transfer_id']) ? (int)$e['transfer_id'] : null,
    ]);
    $id = (int)$db->lastInsertId();
    cbCommit($db);
    return $id;
  } catch (Throwable $ex) {
    cbRollback($db);
    throw $ex;
  }
}

// ── Reversal ──────────────────────────────────────────────────────
// WHERE-clause matching every row that belongs to one record. For
// purchases/expenses it ALSO matches the OLD-style reversal rows (stored
// as reference_type 'adjustment' with a "Reversal: Purchase …/Expense …"
// note) so records created before this fix net out correctly too. The
// note prefix is what stops purchase #12 matching expense #12.
function _cbRefClause(string $refType, int $refId): array {
  $sql    = '(reference_type = ? AND reference_id = ?)';
  $params = [$refType, $refId];
  $legacy = ['purchase' => 'Reversal: Purchase %', 'expense' => 'Reversal: Expense %'];
  if (isset($legacy[$refType])) {
    $sql     .= " OR (reference_type = 'adjustment' AND reference_id = ? AND note LIKE ?)";
    $params[] = $refId;
    $params[] = $legacy[$refType];
  }
  return ['(' . $sql . ')', $params];
}

// What is still outstanding on the book for one record:
// negative = cash went out and has not come back; positive = the reverse.
function cbNetFor(PDO $db, string $refType, int $refId): float {
  [$where, $params] = _cbRefClause($refType, $refId);
  $stmt = $db->prepare("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE -amount END),0)
                          FROM cash_in_hand_ledger WHERE {$where}");
  $stmt->execute($params);
  return round((float)$stmt->fetchColumn(), 2);
}

// The latest date a Carry Forward has already closed. A reversal that
// would land on or before it must NOT be dated there (that would change a
// session whose balance was already moved on), so it is dated today.
function cbClosedThrough(PDO $db): ?string {
  try {
    $d = $db->query("SELECT MAX(source_end_date) FROM cash_in_hand_ledger
                      WHERE type = 'carry_forward' AND source_end_date IS NOT NULL")->fetchColumn();
    return $d ?: null;
  } catch (Throwable $e) { return null; }
}

/**
 * Reverse whatever is still outstanding for one record. Safe to call
 * when nothing was ever posted (does nothing) and safe to call twice
 * (the second call finds a zero balance). Returns the new row id, or 0.
 */
function cbReverse(PDO $db, string $refType, int $refId, string $note, ?int $userId): int {
  cbBegin($db);
  try {
    $net = cbNetFor($db, $refType, $refId);
    if (abs($net) < 0.005) { cbCommit($db); return 0; }

    $reverseDir = $net < 0 ? 'in' : 'out';          // undo cash that left → put it back in
    $origDir    = $net < 0 ? 'out' : 'in';

    // Date: where the original money movement sits — unless that period has
    // already been carried forward, in which case today.
    [$where, $params] = _cbRefClause($refType, $refId);
    $dStmt = $db->prepare("SELECT MAX(entry_date) FROM cash_in_hand_ledger WHERE {$where} AND direction = ?");
    $dStmt->execute(array_merge($params, [$origDir]));
    $origDate = $dStmt->fetchColumn() ?: date('Y-m-d');
    $closed   = cbClosedThrough($db);
    $date     = ($closed !== null && $origDate <= $closed) ? date('Y-m-d') : $origDate;

    $id = cbPost($db, [
      'type' => 'adjustment', 'direction' => $reverseDir, 'amount' => abs($net),
      'date' => $date, 'ref_type' => $refType, 'ref_id' => $refId,
      'note' => $note, 'user_id' => $userId,
    ]);
    cbCommit($db);
    return $id;
  } catch (Throwable $ex) {
    cbRollback($db);
    throw $ex;
  }
}

// ── Carried-session lock (shared by Purchases, Expenses, Payments) ─
// Returns an error message when the session ending $sessionToDate has
// already been carried forward AND the strict restriction is on; null
// when the action is allowed. Fails OPEN on any error — a missing table
// must never stop someone saving a purchase.
function cbSessionLockMessage(PDO $db, ?string $sessionToDate, string $what = 'this entry'): ?string {
  if (!$sessionToDate) return null;
  try {
    $setting = function_exists('getSetting') ? getSetting('cih_restrict_carried_sessions', '1') : '1';
    if ($setting !== '1') return null;
  } catch (Throwable $e) { return null; }

  try {
    $sql = "SELECT l.amount, l.created_at, %s AS by_name FROM cash_in_hand_ledger l %s
             WHERE l.type = 'carry_forward' AND l.source_end_date = ? LIMIT 1";
    try {
      $stmt = $db->prepare(sprintf($sql, 'u.name', 'LEFT JOIN users u ON u.id = l.created_by'));
      $stmt->execute([$sessionToDate]);
    } catch (Throwable $e) {
      $stmt = $db->prepare(sprintf($sql, 'NULL', ''));  // no users table in this DB
      $stmt->execute([$sessionToDate]);
    }
    $row = $stmt->fetch();
    if (!$row) return null;
    return 'This session\'s Cash Book balance (₹' . number_format((float)$row['amount'], 2) . ') was already carried forward on '
      . date('d-m-Y', strtotime($row['created_at'])) . ' by ' . ($row['by_name'] ?: 'someone')
      . '. Turn off the restriction in Settings if ' . $what . ' genuinely needs to draw from it.';
  } catch (Throwable $e) {
    return null;
  }
}
