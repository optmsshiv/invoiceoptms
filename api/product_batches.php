<?php
// ================================================================
//  api/product_batches.php — Batch tracking for products with
//  track_batch=1. One row per physical batch/lot: how much came in,
//  how much is left, when it expires.
//
//  GET    ?product_id=N        → list batches for one product
//  GET    ?product_id=N&action=active → only batches with remaining_qty>0
//         (FIFO order — oldest first — for Sale Entry's batch picker)
//  POST                        → create a batch (manual add, or called
//                                 internally by purchases.php on receipt)
//  PUT    ?id=N                → edit a batch (code, expiry, notes —
//                                 NOT qty, see ?action=adjust for that)
//  POST   ?action=adjust       → increment/decrement remaining_qty
//                                 (used by Sale Entry when a batch is
//                                 picked, and by Purchase Entry edits)
//  DELETE ?id=N                → remove a batch (only if untouched —
//                                 remaining_qty must equal qty, i.e.
//                                 nothing has been sold from it yet)
// ================================================================
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
  $db->exec("CREATE TABLE IF NOT EXISTS `product_batches` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`    INT UNSIGNED NOT NULL,
    `batch_code`    VARCHAR(60)  NOT NULL,
    `qty`           DECIMAL(12,3) NOT NULL DEFAULT 0,
    `remaining_qty` DECIMAL(12,3) NOT NULL DEFAULT 0,
    `mfg_date`      DATE NULL,
    `expiry_date`   DATE NULL,
    `purchase_id`   INT UNSIGNED NULL COMMENT 'which Purchase this batch was received on, if any',
    `notes`         VARCHAR(255) DEFAULT NULL,
    `status`        ENUM('active','depleted') NOT NULL DEFAULT 'active',
    `created_by`    INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_pb_product` (`product_id`),
    INDEX `idx_pb_expiry`  (`expiry_date`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  // ── GET: list batches for a product ─────────────────────────────
  if ($method === 'GET') {
    $productId = (int)($_GET['product_id'] ?? 0);
    if (!$productId) jsonResponse(['error' => 'product_id is required'], 400);

    $where = 'product_id = ?';
    $params = [$productId];
    if (($_GET['action'] ?? '') === 'active') { $where .= ' AND remaining_qty > 0'; }

    // FIFO by default — oldest batch (earliest expiry, else earliest created) first.
    // This is the order Sale Entry's batch picker should offer.
    $stmt = $db->prepare("SELECT * FROM product_batches WHERE $where ORDER BY (expiry_date IS NULL), expiry_date ASC, created_at ASC");
    $stmt->execute($params);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
  }

  // ── POST: create a batch ────────────────────────────────────────
  if ($method === 'POST' && $action !== 'adjust') {
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $productId = (int)($d['product_id'] ?? 0);
    $batchCode = trim($d['batch_code'] ?? '');
    $qty       = (float)($d['qty'] ?? 0);
    if (!$productId || !$batchCode || $qty <= 0) {
      jsonResponse(['error' => 'product_id, batch_code, and a qty greater than 0 are required'], 400);
    }

    $stmt = $db->prepare(
      'INSERT INTO product_batches (product_id, batch_code, qty, remaining_qty, mfg_date, expiry_date, purchase_id, notes, created_by)
       VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
      $productId, $batchCode, $qty, $qty,
      $d['mfg_date'] ?? null, $d['expiry_date'] ?? null,
      !empty($d['purchase_id']) ? (int)$d['purchase_id'] : null,
      trim($d['notes'] ?? ''), (int)($_SESSION['user_id'] ?? 0),
    ]);
    $id = (int)$db->lastInsertId();
    logActivity((int)($_SESSION['user_id'] ?? 0), 'create', 'product_batch', $id, "Batch added: {$batchCode} (qty {$qty})");
    jsonResponse(['success' => true, 'id' => $id]);
  }

  // ── POST ?action=adjust: increment/decrement remaining_qty ──────
  // amount is signed: negative when stock is sold/consumed from this
  // batch, positive for a correction back up (e.g. a sale was reversed).
  if ($method === 'POST' && $action === 'adjust') {
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);
    $id     = (int)($d['id'] ?? 0);
    $amount = (float)($d['amount'] ?? 0);
    if (!$id || $amount == 0) jsonResponse(['error' => 'id and a non-zero amount are required'], 400);

    $bStmt = $db->prepare('SELECT * FROM product_batches WHERE id=?');
    $bStmt->execute([$id]);
    $batch = $bStmt->fetch();
    if (!$batch) jsonResponse(['error' => 'Batch not found'], 404);

    $newRemaining = (float)$batch['remaining_qty'] + $amount;
    if ($newRemaining < -0.001) {
      jsonResponse(['error' => "Not enough remaining in batch {$batch['batch_code']} (has " . (float)$batch['remaining_qty'] . ")"], 409);
    }
    $newRemaining = max(0, $newRemaining);
    $newStatus = $newRemaining <= 0.001 ? 'depleted' : 'active';

    $db->prepare('UPDATE product_batches SET remaining_qty=?, status=? WHERE id=?')
       ->execute([$newRemaining, $newStatus, $id]);

    jsonResponse(['success' => true, 'remaining_qty' => $newRemaining, 'status' => $newStatus]);
  }

  // ── PUT: edit batch metadata (not quantity) ─────────────────────
  if ($method === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $db->prepare('UPDATE product_batches SET batch_code=?, mfg_date=?, expiry_date=?, notes=? WHERE id=?')
       ->execute([
         trim($d['batch_code'] ?? ''), $d['mfg_date'] ?? null, $d['expiry_date'] ?? null,
         trim($d['notes'] ?? ''), $id,
       ]);
    logActivity((int)($_SESSION['user_id'] ?? 0), 'update', 'product_batch', $id, 'Batch updated');
    jsonResponse(['success' => true]);
  }

  // ── DELETE: only if nothing has been consumed from it yet ───────
  if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $bStmt = $db->prepare('SELECT * FROM product_batches WHERE id=?');
    $bStmt->execute([$id]);
    $batch = $bStmt->fetch();
    if (!$batch) jsonResponse(['error' => 'Batch not found'], 404);
    if (abs((float)$batch['remaining_qty'] - (float)$batch['qty']) > 0.001) {
      jsonResponse(['error' => 'Cannot delete — some stock from this batch has already been sold/used'], 409);
    }
    $db->prepare('DELETE FROM product_batches WHERE id=?')->execute([$id]);
    logActivity((int)($_SESSION['user_id'] ?? 0), 'delete', 'product_batch', $id, "Batch deleted: {$batch['batch_code']}");
    jsonResponse(['success' => true]);
  }

  jsonResponse(['error' => 'Unknown request'], 400);

} catch (Throwable $e) {
  error_log('product_batches.php error: ' . $e->getMessage());
  jsonResponse(['error' => 'Product Batches API error: ' . $e->getMessage()], 500);
}
