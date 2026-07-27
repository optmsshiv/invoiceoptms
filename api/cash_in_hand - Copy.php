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
//  GET                    → current balance + recent ledger history
//  POST ?action=topup     → add funds (Owner only)
// ================================================================
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

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

  // ── GET: balance + history ────────────────────────────────────
  if ($method === 'GET') {
    $balance = cihCurrentBalance($db);
    $stmt = $db->query('SELECT l.*, u.name AS created_by_name
                         FROM cash_in_hand_ledger l
                         LEFT JOIN users u ON u.id = l.created_by
                         ORDER BY l.id DESC LIMIT 200');
    jsonResponse(['balance' => $balance, 'data' => $stmt->fetchAll()]);
  }

  // ── POST: top up (Owner only) ─────────────────────────────────
  if ($method === 'POST' && $action === 'topup') {
    $user = currentUser();
    $role = $user['role'] ?? '';
    if ($role !== 'owner') {
      jsonResponse(['error' => 'Only the owner can add funds to Cash in Hand'], 403);
    }

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

  jsonResponse(['error' => 'Unknown request'], 400);

} catch (Throwable $e) {
  error_log('cash_in_hand.php error: ' . $e->getMessage());
  jsonResponse(['error' => 'Cash in Hand API error: ' . $e->getMessage()], 500);
}
