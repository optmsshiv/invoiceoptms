<?php
// ================================================================
//  api/product_serials.php — Serial number tracking for products
//  with track_serial=1. One row per physical unit — no partial
//  quantities, a serial is either in stock or sold.
//
//  GET    ?product_id=N                → list serials for one product
//  GET    ?product_id=N&action=active  → only status='in_stock'
//  POST                                 → add serial(s) — accepts either
//                                          one {serial_no} or a bulk
//                                          {serial_nos:[...]} array (for
//                                          receiving a batch of units at once)
//  POST   ?action=mark_sold             → {id, sale_id} mark one serial sold
//  POST   ?action=unmark_sold           → {id} revert to in_stock (sale voided)
//  DELETE ?id=N                         → remove a serial (only if still in_stock)
// ================================================================
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
  $db->exec("CREATE TABLE IF NOT EXISTS `product_serials` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`  INT UNSIGNED NOT NULL,
    `serial_no`   VARCHAR(80)  NOT NULL,
    `status`      ENUM('in_stock','sold') NOT NULL DEFAULT 'in_stock',
    `purchase_id` INT UNSIGNED NULL,
    `sale_id`     INT UNSIGNED NULL,
    `notes`       VARCHAR(255) DEFAULT NULL,
    `created_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sold_at`     DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_product_serial` (`product_id`,`serial_no`),
    INDEX `idx_ps_status` (`status`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  // ── GET: list serials for a product ─────────────────────────────
  if ($method === 'GET') {
    $productId = (int)($_GET['product_id'] ?? 0);
    if (!$productId) jsonResponse(['error' => 'product_id is required'], 400);
    $where = 'product_id = ?';
    $params = [$productId];
    if (($_GET['action'] ?? '') === 'active') { $where .= " AND status = 'in_stock'"; }
    $stmt = $db->prepare("SELECT * FROM product_serials WHERE $where ORDER BY created_at ASC");
    $stmt->execute($params);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
  }

  // ── POST: add serial(s) ─────────────────────────────────────────
  if ($method === 'POST' && $action === '') {
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);
    $productId = (int)($d['product_id'] ?? 0);
    if (!$productId) jsonResponse(['error' => 'product_id is required'], 400);

    $serials = !empty($d['serial_nos']) && is_array($d['serial_nos'])
      ? array_values(array_filter(array_map('trim', $d['serial_nos'])))
      : (trim($d['serial_no'] ?? '') !== '' ? [trim($d['serial_no'])] : []);
    if (!$serials) jsonResponse(['error' => 'At least one serial number is required'], 400);

    $stmt = $db->prepare(
      'INSERT IGNORE INTO product_serials (product_id, serial_no, purchase_id, notes, created_by)
       VALUES (?,?,?,?,?)'
    );
    $added = 0; $skipped = 0;
    foreach ($serials as $sn) {
      $ok = $stmt->execute([
        $productId, $sn, !empty($d['purchase_id']) ? (int)$d['purchase_id'] : null,
        trim($d['notes'] ?? ''), (int)($_SESSION['user_id'] ?? 0),
      ]);
      if ($stmt->rowCount() > 0) $added++; else $skipped++; // skipped = duplicate serial for this product
    }
    logActivity((int)($_SESSION['user_id'] ?? 0), 'create', 'product_serial', $productId,
      "Added {$added} serial(s)" . ($skipped ? ", {$skipped} duplicate(s) skipped" : ''));
    jsonResponse(['success' => true, 'added' => $added, 'skipped_duplicates' => $skipped]);
  }

  // ── POST ?action=mark_sold ──────────────────────────────────────
  if ($method === 'POST' && $action === 'mark_sold') {
    $d = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id is required'], 400);
    $sStmt = $db->prepare('SELECT status FROM product_serials WHERE id=?');
    $sStmt->execute([$id]);
    $cur = $sStmt->fetchColumn();
    if ($cur === false) jsonResponse(['error' => 'Serial not found'], 404);
    if ($cur === 'sold') jsonResponse(['error' => 'This serial is already marked sold'], 409);
    $db->prepare('UPDATE product_serials SET status="sold", sale_id=?, sold_at=NOW() WHERE id=?')
       ->execute([!empty($d['sale_id']) ? (int)$d['sale_id'] : null, $id]);
    jsonResponse(['success' => true]);
  }

  // ── POST ?action=unmark_sold (a sale containing this serial was voided) ──
  if ($method === 'POST' && $action === 'unmark_sold') {
    $d = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id is required'], 400);
    $db->prepare('UPDATE product_serials SET status="in_stock", sale_id=NULL, sold_at=NULL WHERE id=?')->execute([$id]);
    jsonResponse(['success' => true]);
  }

  // ── DELETE: only if still in stock ──────────────────────────────
  if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $sStmt = $db->prepare('SELECT status, serial_no FROM product_serials WHERE id=?');
    $sStmt->execute([$id]);
    $row = $sStmt->fetch();
    if (!$row) jsonResponse(['error' => 'Serial not found'], 404);
    if ($row['status'] === 'sold') jsonResponse(['error' => 'Cannot delete — this serial has already been sold'], 409);
    $db->prepare('DELETE FROM product_serials WHERE id=?')->execute([$id]);
    logActivity((int)($_SESSION['user_id'] ?? 0), 'delete', 'product_serial', $id, "Serial deleted: {$row['serial_no']}");
    jsonResponse(['success' => true]);
  }

  jsonResponse(['error' => 'Unknown request'], 400);

} catch (Throwable $e) {
  error_log('product_serials.php error: ' . $e->getMessage());
  jsonResponse(['error' => 'Product Serials API error: ' . $e->getMessage()], 500);
}
