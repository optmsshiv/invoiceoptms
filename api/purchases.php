<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cash_book.php';
requireLogin();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Product IDs arrive from the frontend as "p12" (Products page convention) —
// strip any non-digit characters before using as an int FK.
function cleanProductId($v) {
  if (empty($v)) return null;
  $n = (int) preg_replace('/\D/', '', (string)$v);
  return $n > 0 ? $n : null;
}

// Builds a real "what changed" description for the activity log instead
// of a flat "Purchase updated" with no detail — same pre-edit-capture +
// diff pattern used in expenses.php. Scoped to Total and Supplier only:
// those are the two fields on this PUT that actually carry financial/
// identity significance. amount_paid and status are deliberately NOT
// diffed here since this endpoint never touches them at all (see the
// comment above the UPDATE statement below) — they can only change via
// purchase_payments.php, which logs its own activity entries separately.
function buildPurchaseEditDiff($db, $oldPay, $newTotal, $newSupplierId) {
  $parts = [];
  if ($oldPay && abs((float)$oldPay['total'] - $newTotal) > 0.004) {
    $parts[] = 'Total ₹' . number_format((float)$oldPay['total'], 2) . ' → ₹' . number_format($newTotal, 2);
  }
  if ($oldPay && (int)$oldPay['supplier_id'] !== $newSupplierId) {
    $names = $db->prepare('SELECT id, name FROM suppliers WHERE id IN (?,?)');
    $names->execute([(int)$oldPay['supplier_id'], $newSupplierId]);
    $map = [];
    foreach ($names->fetchAll() as $r) $map[(int)$r['id']] = $r['name'];
    $oldName = $map[(int)$oldPay['supplier_id']] ?? ('#' . $oldPay['supplier_id']);
    $newName = $map[$newSupplierId] ?? ('#' . $newSupplierId);
    $parts[] = "Supplier {$oldName} → {$newName}";
  }
  if (empty($parts)) return 'Purchase updated (items or other details changed)';
  return 'Purchase updated: ' . implode(' · ', $parts);
}

// If a Purchase's total changes after payments were already recorded
// against it, every existing purchase_payments row's stored
// remaining_amt is a snapshot taken against the OLD total — nothing
// recalculates it automatically. The live "Remaining Amount" on the
// Purchase itself stays correct (computed fresh from the current total
// every time), but the Payment History table's "Balance After" column
// silently goes stale, off by exactly the size of the total change, on
// every row from that point on. Called only when total actually changed
// (see the caller) — walks payments chronologically and recomputes each
// one's remaining balance against the new total.
function recalcPurchasePaymentBalances(PDO $db, int $purchaseId, float $newTotal): void {
  try {
    $rows = $db->prepare('SELECT id, amount FROM purchase_payments WHERE purchase_id = ? AND purchase_deleted = 0 ORDER BY payment_date ASC, id ASC');
    $rows->execute([$purchaseId]);
    $running = 0.0;
    $upd = $db->prepare('UPDATE purchase_payments SET remaining_amt = ? WHERE id = ?');
    foreach ($rows->fetchAll() as $r) {
      $running += (float)$r['amount'];
      $upd->execute([max(0, round($newTotal - $running, 2)), $r['id']]);
    }
  } catch (Throwable $e) { /* purchase_payments table may not exist for this tenant yet — non-fatal */ }
}

// Current stock for a product = sum of all ins minus all outs in the ledger
function currentStock($db, $productId) {
  $stmt = $db->prepare('SELECT COALESCE(SUM(CASE WHEN direction="in" THEN qty ELSE -qty END),0) AS bal FROM stock_ledger WHERE product_id = ?');
  $stmt->execute([$productId]);
  return (float)$stmt->fetch()['bal'];
}

// Write one stock-ledger IN row. Physical stock received = NET weight
// (gross − tare), not billable weight — dhalta is a financial deduction,
// not goods that vanished from the warehouse. Rate is the effective cost
// per physical kg (item amount ÷ net weight), so dhalta's cost impact is
// correctly amortized into the stock's cost basis.
function writeStockIn($db, $productId, $purchaseId, $netWeight, $effectiveRate, $date, $note, $warehouse = 'Main Warehouse', $batchNo = '') {
  if ($netWeight <= 0) return;
  $bal = currentStock($db, $productId) + $netWeight;
  // created_at set explicitly via PHP's date() (Asia/Kolkata) — same fix
  // already applied to purchases/sales/expenses/email_logs. Without this,
  // it falls back to the column's own DEFAULT CURRENT_TIMESTAMP, which
  // runs on the DB server's own clock, not IST — exactly what was showing
  // the wrong time in the Stock History table.
  $stmt = $db->prepare('INSERT INTO stock_ledger (product_id, ref_type, ref_id, direction, qty, rate, balance_after, movement_date, notes, warehouse, batch_no, created_at) VALUES (?,"purchase",?,"in",?,?,?,?,?,?,?,?)');
  $stmt->execute([$productId, $purchaseId, $netWeight, $effectiveRate, $bal, $date, $note, $warehouse, $batchNo, date('Y-m-d H:i:s')]);
}

function clearStockForPurchase($db, $purchaseId) {
  $stmt = $db->prepare('DELETE FROM stock_ledger WHERE ref_type = "purchase" AND ref_id = ?');
  $stmt->execute([$purchaseId]);
}

// Server-side authoritative calculation for one line item — never trusts
// client-computed amounts, only the raw inputs (gross, tare, dhalta%, rate, discount%).
function computeItemWeights($it) {
  $gross = (float)($it['gross_weight'] ?? 0);
  $tare  = (float)($it['tare_weight']  ?? 0);
  $net   = max(0, $gross - $tare);
  // Dhalta Kg is what's actually entered (matches how it's weighed at the mandi);
  // Dhalta % is derived from it purely for display/reporting.
  $dhaltaKg  = max(0, round((float)($it['dhalta_kg'] ?? 0), 3));
  $dhaltaPct = $net > 0 ? round($dhaltaKg / $net * 100, 2) : 0;
  $billable  = max(0, $net - $dhaltaKg);
  $rate      = (float)($it['rate'] ?? 0);
  $discPct   = (float)($it['discount_pct'] ?? 0);
  $amount    = round($billable * $rate * (1 - $discPct / 100), 2);
  $effectiveRate = $net > 0 ? round($amount / $net, 4) : $rate; // for stock costing
  return compact('gross','tare','net','dhaltaPct','dhaltaKg','billable','rate','discPct','amount','effectiveRate');
}

// Handle invoice/bill attachment (data URL -> file on disk), mirrors the
// pattern already used for avatar uploads in api/users.php.
function saveAttachment($dataUrl) {
  if (!$dataUrl || !preg_match('/^data:(image\/(png|jpe?g|webp)|application\/pdf);base64,(.+)$/', $dataUrl, $m)) return null;
  $ext  = str_contains($m[1], 'pdf') ? 'pdf' : ($m[2] === 'jpeg' ? 'jpg' : $m[2]);
  $blob = base64_decode($m[3]);
  if ($blob === false || strlen($blob) > (defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 5242880)) return null;
  $dir = rtrim(defined('UPLOAD_PATH') ? UPLOAD_PATH : (__DIR__ . '/../assets/uploads/'), '/') . '/purchases';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $fname = 'pur_' . bin2hex(random_bytes(8)) . '.' . $ext;
  file_put_contents($dir . '/' . $fname, $blob);
  return '/assets/uploads/purchases/' . $fname;
}

// Cash Book (Cash in Hand) writes go through includes/cash_book.php — the one
// shared, locked, transactional implementation. The private copies of
// cihBalance / recordCashInHandMovement / pnCheckCarriedRestriction that used
// to live here were removed.

// ── Batch tracking on receipt — receiving stock for a batch-tracked
// product either tops up an existing batch with that code, or creates a
// new one. Shares the same product_batches table Manage Batches / opening
// stock use, so all three stay reconciled instead of drifting apart.
function _pbEnsureTable($db) {
  $db->exec("CREATE TABLE IF NOT EXISTS `product_batches` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `product_id` INT UNSIGNED NOT NULL,
    `batch_code` VARCHAR(60) NOT NULL, `qty` DECIMAL(12,3) NOT NULL DEFAULT 0,
    `remaining_qty` DECIMAL(12,3) NOT NULL DEFAULT 0, `mfg_date` DATE NULL, `expiry_date` DATE NULL,
    `purchase_id` INT UNSIGNED NULL, `notes` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('active','depleted') NOT NULL DEFAULT 'active',
    `created_by` INT UNSIGNED DEFAULT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), INDEX `idx_pb_product` (`product_id`), INDEX `idx_pb_expiry` (`expiry_date`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function receiveIntoBatch($db, $productId, $batchCode, $qty, $userId, $mfgDate = null, $expDate = null, $purchaseId = null) {
  $batchCode = trim((string)$batchCode);
  if ($batchCode === '' || $qty <= 0) return;
  try {
    _pbEnsureTable($db);
    $existing = $db->prepare('SELECT id FROM product_batches WHERE product_id=? AND batch_code=? LIMIT 1');
    $existing->execute([$productId, $batchCode]);
    $row = $existing->fetch();
    if ($row) {
      $db->prepare('UPDATE product_batches SET qty=qty+?, remaining_qty=remaining_qty+?, status="active" WHERE id=?')
         ->execute([$qty, $qty, $row['id']]);
    } else {
      $db->prepare('INSERT INTO product_batches (product_id,batch_code,qty,remaining_qty,mfg_date,expiry_date,purchase_id,created_by) VALUES (?,?,?,?,?,?,?,?)')
         ->execute([$productId, $batchCode, $qty, $qty, $mfgDate ?: null, $expDate ?: null, $purchaseId, $userId]);
    }
  } catch (Throwable $e) { error_log('receiveIntoBatch (purchases.php) failed: ' . $e->getMessage()); }
}
// Reversal for edit/delete — removes exactly what a specific purchase item
// previously added, rather than guessing from current totals.
function reverseBatchReceive($db, $productId, $batchCode, $qty) {
  $batchCode = trim((string)$batchCode);
  if ($batchCode === '' || $qty <= 0) return;
  try {
    _pbEnsureTable($db);
    $db->prepare('UPDATE product_batches SET qty=GREATEST(0,qty-?), remaining_qty=GREATEST(0,remaining_qty-?),
                  status=IF(remaining_qty-? <= 0.001, "depleted", "active") WHERE product_id=? AND batch_code=?')
       ->execute([$qty, $qty, $qty, $productId, $batchCode]);
  } catch (Throwable $e) { error_log('reverseBatchReceive (purchases.php) failed: ' . $e->getMessage()); }
}

try {
// Self-heal: purchases, purchase_items, and stock_ledger were never
// created for some tenants — this file assumed they already existed
// while defensively creating purchase_payments/product_batches/
// cash_in_hand_ledger below. Columns mirror the INSERT statements in
// this file (and stock.php for stock_ledger).
$db->exec("CREATE TABLE IF NOT EXISTS `purchases` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_no` VARCHAR(60) DEFAULT '',
  `supplier_id` INT UNSIGNED NOT NULL,
  `supplier_invoice_ref` VARCHAR(100) DEFAULT '',
  `purchase_date` DATE NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'INR',
  `exchange_rate` DECIMAL(10,4) NOT NULL DEFAULT 1,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `gst_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `gst_pct` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status` VARCHAR(30) NOT NULL DEFAULT 'Pending',
  `notes` TEXT NULL,
  `reference_po_no` VARCHAR(100) DEFAULT '',
  `supplier_type` VARCHAR(60) DEFAULT '',
  `gst_applicable` TINYINT(1) NOT NULL DEFAULT 0,
  `supply_type` VARCHAR(30) DEFAULT 'Intra-State',
  `transport_mode` VARCHAR(60) DEFAULT '',
  `vehicle_no` VARCHAR(30) DEFAULT '',
  `driver_name` VARCHAR(150) DEFAULT '',
  `warehouse` VARCHAR(100) DEFAULT 'Main Warehouse',
  `payment_terms` VARCHAR(100) DEFAULT '',
  `payment_type` VARCHAR(60) DEFAULT '',
  `expected_payment_date` DATE NULL,
  `remarks` TEXT NULL,
  `transport_charge` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `loading_charge` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `packing_charge` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `other_charges` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount_remarks` VARCHAR(255) DEFAULT '',
  `deductions` TEXT NULL,
  `deduction_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `additions` TEXT NULL,
  `addition_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total_bags` INT UNSIGNED NOT NULL DEFAULT 0,
  `trade_discount_pct` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `cash_discount_pct` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `cd_applicable_within` VARCHAR(30) DEFAULT 'Same Day',
  `trade_discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `cash_discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `attachment_path` VARCHAR(255) DEFAULT '',
  `payment_mode` VARCHAR(60) DEFAULT '',
  `transaction_no` VARCHAR(100) DEFAULT '',
  `payment_date` DATETIME NULL,
  `weighing_type` VARCHAR(30) DEFAULT 'Dharam Kanta',
  `kanta_name` VARCHAR(150) DEFAULT '',
  `weighbridge_slip_no` VARCHAR(100) DEFAULT '',
  `weight_datetime` DATETIME NULL,
  `kanta_gross_weight` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `kanta_tare_weight` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `kanta_operator_name` VARCHAR(150) DEFAULT '',
  `kanta_slip_path` VARCHAR(255) DEFAULT '',
  `header_moisture_pct` DECIMAL(5,2) NULL,
  `header_impurity_pct` DECIMAL(5,2) NULL,
  `header_dhalta_pct` DECIMAL(5,2) NULL,
  `header_dhalta_kg` DECIMAL(12,3) NULL,
  `header_billable_weight` DECIMAL(12,3) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_pur_supplier` (`supplier_id`),
  INDEX `idx_pur_date` (`purchase_date`),
  INDEX `idx_pur_expected_pay` (`expected_payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Migration guard — for installs where `purchases` already existed
// before expected_payment_date was added. This is the field the client
// asked for: "when do we plan/intend to pay this supplier" — kept as
// its own explicit date rather than derived from payment_terms (Net 7/
// Net 15/...) because payment_terms is just a category, and the actual
// planned date can shift (supplier agrees to wait, cash flow changes)
// independent of what category was picked at purchase time.
try { $db->exec("ALTER TABLE purchases ADD COLUMN expected_payment_date DATE NULL AFTER payment_type"); }
catch (Throwable $e) { /* already exists */ }
try { $db->exec("ALTER TABLE purchases ADD INDEX idx_pur_expected_pay (expected_payment_date)"); }
catch (Throwable $e) { /* already exists */ }

// Migration guard — Additions: the mirror-image of the existing
// Deductions feature. Same {name, amount} JSON line-item shape, same
// idea, just added to the taxable amount instead of subtracted (e.g.
// a quality premium or bonus, as opposed to a moisture/quality
// deduction).
try { $db->exec("ALTER TABLE purchases ADD COLUMN additions TEXT NULL AFTER deduction_amount"); }
catch (Throwable $e) { /* already exists */ }
try { $db->exec("ALTER TABLE purchases ADD COLUMN addition_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER additions"); }
catch (Throwable $e) { /* already exists */ }

// Number of Bags — per line item, like weight (different products in the
// same purchase naturally come in different bag counts), plus a cached
// header-level total mirroring how other item-sum fields work.
try { $db->exec("ALTER TABLE purchase_items ADD COLUMN bags INT UNSIGNED NOT NULL DEFAULT 0"); }
catch (Throwable $e) { /* already exists */ }
try { $db->exec("ALTER TABLE purchases ADD COLUMN total_bags INT UNSIGNED NOT NULL DEFAULT 0 AFTER addition_amount"); }
catch (Throwable $e) { /* already exists */ }

$db->exec("CREATE TABLE IF NOT EXISTS `purchase_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `description` VARCHAR(255) DEFAULT '',
  `hsn` VARCHAR(20) DEFAULT '',
  `qty` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `unit` VARCHAR(20) DEFAULT 'kg',
  `entered_qty` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `entered_unit` VARCHAR(20) DEFAULT 'kg',
  `rate` DECIMAL(12,4) NOT NULL DEFAULT 0,
  `gst_pct` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `variety_grade` VARCHAR(100) DEFAULT '',
  `moisture_pct` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `quality_grade` VARCHAR(100) DEFAULT '',
  `gross_weight` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `tare_weight` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `dhalta_pct` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `dhalta_kg` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `billable_weight` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `discount_pct` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `batch_no` VARCHAR(60) DEFAULT '',
  `bags` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_pi_purchase` (`purchase_id`),
  INDEX `idx_pi_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->exec("CREATE TABLE IF NOT EXISTS `stock_ledger` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `ref_type` VARCHAR(30) NOT NULL DEFAULT 'adjustment',
  `ref_id` INT UNSIGNED NULL,
  `direction` ENUM('in','out') NOT NULL,
  `qty` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `rate` DECIMAL(12,4) NOT NULL DEFAULT 0,
  `balance_after` DECIMAL(14,3) NOT NULL DEFAULT 0,
  `movement_date` DATE NOT NULL,
  `notes` VARCHAR(255) DEFAULT '',
  `warehouse` VARCHAR(100) DEFAULT 'Main Warehouse',
  `batch_no` VARCHAR(60) DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_sl_product` (`product_id`),
  INDEX `idx_sl_ref` (`ref_type`,`ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Auto-migrate: purchase_items didn't have a batch_no column before —
// stock_ledger already did (used by Sales), this brings Purchases in sync.
try { $db->exec("ALTER TABLE purchase_items ADD COLUMN batch_no VARCHAR(60) DEFAULT ''"); } catch (Throwable $e) { /* already exists */ }

// Idempotency key for duplicate-save protection — see the POST handler
// below. Nullable + unique: multiple NULLs are allowed by MySQL's unique
// index, so this stays backward-compatible with any caller that doesn't
// send one.
$purCols = $db->query("SHOW COLUMNS FROM purchases")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('client_request_id', $purCols, true)) {
    try { $db->exec("ALTER TABLE purchases ADD COLUMN client_request_id VARCHAR(64) NULL, ADD UNIQUE INDEX idx_purchases_client_request_id (client_request_id)"); } catch (Throwable $e) { /* already exists */ }
}
// Who created this purchase — for the "Added by X" byline in the detail
// view. Not shown as a table column (would just add clutter to an
// already-dense list); resolved via the users JOIN in the GET queries
// below and surfaced only where the record is actually opened.
if (!in_array('created_by', $purCols, true)) {
    try { $db->exec("ALTER TABLE purchases ADD COLUMN created_by INT UNSIGNED NULL"); } catch (Throwable $e) { /* already exists */ }
}

// Same table purchase_payments.php creates — needed here too since this
// file also writes to it directly (the "first payment at creation" row).
// Without this, a brand-new install's very first paid purchase would hit
// a 1146 "table doesn't exist" error if this file happened to run before
// purchase_payments.php ever had.
$db->exec("CREATE TABLE IF NOT EXISTS `purchase_payments` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `purchase_id`     INT UNSIGNED  NOT NULL,
    `purchase_no`     VARCHAR(60)   NULL,
    `supplier_name`   VARCHAR(200)  NULL,
    `amount`          DECIMAL(12,2) NOT NULL DEFAULT 0,
    `remaining_amt`   DECIMAL(12,2) NOT NULL DEFAULT 0,
    `payment_date`    DATETIME      NULL,
    `method`          VARCHAR(60)   NULL,
    `transaction_id`  VARCHAR(100)  NULL,
    `notes`           VARCHAR(500)  NULL,
    `purchase_deleted` TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`      INT UNSIGNED  NULL,
    `created_at`      DATETIME      NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_pp_purchase` (`purchase_id`),
    INDEX `idx_pp_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// All DDL above is finished — safe to ensure the Cash Book schema now (DDL
// would silently commit a Cash Book transaction if it ran later).
cbEnsureSchema($db);

switch ($method) {
  case 'GET':
    if (!empty($_GET['id'])) {
      $id = (int)$_GET['id'];
      $stmt = $db->prepare('SELECT p.*, s.name AS supplier_name, s.supplier_type,
        s.phone AS supplier_phone, s.address AS supplier_address, s.city AS supplier_city,
        s.state AS supplier_state, s.pincode AS supplier_pincode,
        u.name AS created_by_name
        FROM purchases p JOIN suppliers s ON s.id = p.supplier_id
        LEFT JOIN users u ON u.id = p.created_by WHERE p.id = ?');
      $stmt->execute([$id]);
      $purchase = $stmt->fetch();
      if (!$purchase) jsonResponse(['error' => 'Not found'], 404);
      $itemsStmt = $db->prepare('SELECT * FROM purchase_items WHERE purchase_id = ? ORDER BY id ASC');
      $itemsStmt->execute([$id]);
      $purchase['items'] = $itemsStmt->fetchAll();
      $purchase['deductions'] = $purchase['deductions'] ? json_decode($purchase['deductions'], true) : [];
      $purchase['additions'] = $purchase['additions'] ? json_decode($purchase['additions'], true) : [];
      jsonResponse(['data' => $purchase]);
      break;
    }

    $stmt = $db->query('SELECT p.*, s.name AS supplier_name, s.supplier_type,
      (SELECT COUNT(*) FROM purchase_items pi WHERE pi.purchase_id = p.id) AS item_count,
      (SELECT COALESCE(SUM(pi.qty),0) FROM purchase_items pi WHERE pi.purchase_id = p.id) AS total_qty,
      (SELECT GROUP_CONCAT(DISTINCT pi.product_id) FROM purchase_items pi WHERE pi.purchase_id = p.id) AS product_ids,
      (SELECT GROUP_CONCAT(DISTINCT pi.description ORDER BY pi.id SEPARATOR \'|~|\') FROM purchase_items pi WHERE pi.purchase_id = p.id) AS product_names,
      COALESCE(
        (SELECT MAX(pp.payment_date) FROM purchase_payments pp WHERE pp.purchase_id = p.id AND pp.purchase_deleted = 0),
        p.payment_date
      ) AS last_payment_date
      FROM purchases p JOIN suppliers s ON s.id = p.supplier_id ORDER BY p.purchase_date DESC, p.id DESC');
    jsonResponse(['data' => $stmt->fetchAll()]);
    break;

  case 'POST':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    // Duplicate-save protection — the frontend generates one UUID per
    // "New Purchase" session (see PNE.idempotencyKey in index.php) and
    // resends the SAME id on every retry of that same save attempt (e.g.
    // after a network timeout where the user doesn't know if the first
    // request actually landed). If a purchase already exists with this
    // id, this attempt already succeeded once — return that existing
    // record instead of inserting a second Purchase. A retry becomes
    // safe instead of creating a duplicate.
    $clientRequestId = trim($d['client_request_id'] ?? '');
    if ($clientRequestId !== '') {
        $dupStmt = $db->prepare('SELECT id, purchase_no FROM purchases WHERE client_request_id = ?');
        $dupStmt->execute([$clientRequestId]);
        if ($dup = $dupStmt->fetch()) {
            jsonResponse(['success' => true, 'id' => (int)$dup['id'], 'purchase_no' => $dup['purchase_no'], 'duplicate_prevented' => true]);
        }
    }

    if (empty($d['supplier_id']))   jsonResponse(['error' => 'Supplier is required'], 400);
    if (empty($d['purchase_date'])) jsonResponse(['error' => 'Purchase date is required'], 400);
    $items = $d['items'] ?? [];
    if (!is_array($items) || count($items) === 0) jsonResponse(['error' => 'At least one item is required'], 400);
    if (($d['payment_mode'] ?? '') === 'Cash in Hand' && (float)($d['amount_paid'] ?? 0) > 0) {
      // checked before ANY insert — purchase writes its header+items before the payment-mode block further down
      $lockMsg = cbSessionLockMessage($db, trim($d['session_to_date'] ?? ''), 'this purchase');
      if ($lockMsg) jsonResponse(['error' => $lockMsg], 400);
    }

    $purchaseNo = trim($d['purchase_no'] ?? '');
    if ($purchaseNo === '') {
      $cnt = $db->query('SELECT COUNT(*) c FROM purchases')->fetch()['c'] + 1;
      $purchaseNo = 'PUR-' . date('y') . '-' . date('y', strtotime('+1 year')) . '-' . str_pad($cnt, 6, '0', STR_PAD_LEFT);
    }

    // Compute item-level amounts server-side (authoritative)
    $computed = array_map('computeItemWeights', $items);
    $subtotal = array_sum(array_column($computed, 'amount'));

    $transportCharge = (float)($d['transport_charge'] ?? 0);
    $loadingCharge   = (float)($d['loading_charge'] ?? 0);
    $packingCharge   = (float)($d['packing_charge'] ?? 0);
    $otherCharges    = (float)($d['other_charges'] ?? 0);
    $addCharges      = $transportCharge + $loadingCharge + $packingCharge + $otherCharges;
    $discountAmount  = (float)($d['discount_amount'] ?? 0);
    $deductions      = is_array($d['deductions'] ?? null) ? $d['deductions'] : [];
    $deductionAmount = array_sum(array_map(fn($x) => (float)($x['amount'] ?? 0), $deductions));
    // Additions — mirror image of Deductions, same {name, amount} shape,
    // added to the taxable amount instead of subtracted.
    $additions       = is_array($d['additions'] ?? null) ? $d['additions'] : [];
    $additionAmount  = array_sum(array_map(fn($x) => (float)($x['amount'] ?? 0), $additions));
    $tradeDiscPct    = (float)($d['trade_discount_pct'] ?? 0);
    $cashDiscPct     = (float)($d['cash_discount_pct'] ?? 0);
    $tradeDiscAmount = round($subtotal * $tradeDiscPct / 100, 2);
    $cashDiscAmount  = round($subtotal * $cashDiscPct / 100, 2);
    $taxable         = $subtotal + $addCharges + $additionAmount - $discountAmount - $deductionAmount - $tradeDiscAmount - $cashDiscAmount;

    $gstApplicable = !empty($d['gst_applicable']);
    $gstPct = $gstApplicable ? (float)($d['gst_pct'] ?? 0) : 0;
    $gstAmount = $gstApplicable ? round($taxable * $gstPct / 100, 2) : 0;
    $total = round($taxable + $gstAmount, 2);

    $attachmentPath = saveAttachment($d['attachment'] ?? null);
    $kantaSlipPath  = saveAttachment($d['kanta_slip'] ?? null);

    // created_at set explicitly via PHP's date() (Asia/Kolkata), not left
    // to the column's own DEFAULT CURRENT_TIMESTAMP — same fix already
    // applied to expenses.php and email_logs for the same reason: MySQL's
    // default runs on the DB server's own clock, not IST.
    //
    // NOTE: not also setting updated_at here — couldn't confirm that
    // column actually exists on `purchases` (no schema file available,
    // and the PUT/edit handler below never references it either). If it
    // does exist and also has the same DEFAULT CURRENT_TIMESTAMP issue,
    // share the table's CREATE statement and I'll add it properly.
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare('INSERT INTO purchases
      (purchase_no, supplier_id, supplier_invoice_ref, purchase_date, currency, exchange_rate,
       subtotal, gst_amount, gst_pct, total, amount_paid, status, notes,
       reference_po_no, supplier_type, gst_applicable, supply_type,
       transport_mode, vehicle_no, driver_name, warehouse, payment_terms, payment_type, expected_payment_date, remarks,
       transport_charge, loading_charge, packing_charge, other_charges, discount_amount, discount_remarks,
       deductions, deduction_amount, additions, addition_amount, trade_discount_pct, cash_discount_pct, cd_applicable_within, trade_discount_amount, cash_discount_amount,
       attachment_path, payment_mode, transaction_no, payment_date,
       weighing_type, kanta_name, weighbridge_slip_no, weight_datetime,
       kanta_gross_weight, kanta_tare_weight, kanta_operator_name, kanta_slip_path,
       header_moisture_pct, header_impurity_pct, header_dhalta_pct, header_dhalta_kg, header_billable_weight,
       client_request_id, created_by, created_at)
      VALUES (?,?,?,?,?,?, ?,?,?,?,?,?,?, ?,?,?,?, ?,?,?,?,?,?,?,?, ?,?,?,?,?,?, ?,?,?,?,?,?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?,?, ?,?,?)');
    $stmt->execute([
      $purchaseNo, (int)$d['supplier_id'], $d['invoice_bill_no'] ?? '', $d['purchase_date'],
      $d['currency'] ?? 'INR', (float)($d['exchange_rate'] ?? 1),
      $subtotal, $gstAmount, $gstPct, $total, (float)($d['amount_paid'] ?? 0),
      $d['payment_status'] ?? 'Pending', $d['notes'] ?? '',
      $d['reference_po_no'] ?? '', $d['supplier_type'] ?? '', $gstApplicable ? 1 : 0, $d['supply_type'] ?? 'Intra-State',
      $d['transport_mode'] ?? '', $d['vehicle_no'] ?? '', $d['driver_name'] ?? '', $d['warehouse'] ?? 'Main Warehouse',
      $d['payment_terms'] ?? '', $d['payment_type'] ?? '', ($d['expected_payment_date'] ?? '') ?: null, $d['remarks'] ?? '',
      $transportCharge, $loadingCharge, $packingCharge, $otherCharges, $discountAmount, mb_substr($d['discount_remarks'] ?? '', 0, 255),
      json_encode($deductions), $deductionAmount, json_encode($additions), $additionAmount, $tradeDiscPct, $cashDiscPct, $d['cd_applicable_within'] ?? 'Same Day', $tradeDiscAmount, $cashDiscAmount,
      $attachmentPath, $d['payment_mode'] ?? '', $d['transaction_no'] ?? '', $d['payment_date'] ?? null,
      $d['weighing_type'] ?? 'Dharam Kanta', $d['kanta_name'] ?? '', $d['weighbridge_slip_no'] ?? '', $d['weight_datetime'] ?: null,
      (float)($d['kanta_gross_weight'] ?? 0), (float)($d['kanta_tare_weight'] ?? 0), $d['kanta_operator_name'] ?? '', $kantaSlipPath,
      $d['header_moisture_pct'] ?? null, $d['header_impurity_pct'] ?? null, $d['header_dhalta_pct'] ?? null,
      $d['header_dhalta_kg'] ?? null, $d['header_billable_weight'] ?? null,
      $clientRequestId !== '' ? $clientRequestId : null, (int)$_SESSION['user_id'], $now,
    ]);
    $purchaseId = (int)$db->lastInsertId();

    $itemStmt = $db->prepare('INSERT INTO purchase_items
      (purchase_id, product_id, description, hsn, qty, unit, entered_qty, entered_unit, rate, gst_pct, amount,
       variety_grade, moisture_pct, quality_grade, gross_weight, tare_weight, dhalta_pct, dhalta_kg, billable_weight, discount_pct, batch_no, bags)
      VALUES (?,?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?,?,?,?,?)');
    $totalBags = 0;
    foreach ($items as $i => $it) {
      $c = $computed[$i];
      $productId = cleanProductId($it['product_id'] ?? null);
      $batchNo = trim($it['batch_no'] ?? '');
      $bags = max(0, (int)($it['bags'] ?? 0));
      $totalBags += $bags;
      $itemStmt->execute([
        $purchaseId, $productId, $it['description'] ?? '', $it['hsn'] ?? '',
        $c['net'], 'kg', $c['net'], 'kg', $c['rate'], 0, $c['amount'],
        $it['variety_grade'] ?? '', $it['moisture_pct'] ?? 0, $it['quality_grade'] ?? '',
        $c['gross'], $c['tare'], $c['dhaltaPct'], $c['dhaltaKg'], $c['billable'], $c['discPct'], $batchNo, $bags,
      ]);
      if ($productId) {
        writeStockIn($db, $productId, $purchaseId, $c['net'], $c['effectiveRate'], $d['purchase_date'], 'Purchase ' . $purchaseNo, $d['warehouse'] ?? 'Main Warehouse', $batchNo);
        if ($batchNo !== '') {
          receiveIntoBatch($db, $productId, $batchNo, $c['net'], (int)$_SESSION['user_id'],
            $it['mfg_date'] ?? null, $it['expiry_date'] ?? null, $purchaseId);
        }
      }
    }

    $db->prepare('UPDATE purchases SET total_bags = ? WHERE id = ?')->execute([$totalBags, $purchaseId]);

    logActivity((int)$_SESSION['user_id'], 'create', 'purchase', $purchaseId, 'Purchase added: ' . $purchaseNo);
    // First payment-history row, if anything was paid at creation time —
    // keeps the payment-history table complete from day one instead of
    // starting only from the 2nd payment onward. See purchase_payments.php
    // for how every payment after this one gets recorded.
    $initialPaid = (float)($d['amount_paid'] ?? 0);
    $cashBookWarning = null;
    if ($initialPaid > 0) {
      $supNameStmt = $db->prepare('SELECT name FROM suppliers WHERE id = ?');
      $supNameStmt->execute([(int)$d['supplier_id']]);
      $remainingAtCreate = max(0, round($total - $initialPaid, 2));
      $initialPayDate = ($d['payment_date'] ?? '') ?: $now;
      $db->prepare(
        'INSERT INTO purchase_payments
           (purchase_id, purchase_no, supplier_name, amount, remaining_amt,
            payment_date, method, transaction_id, notes, created_by, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
      )->execute([
        $purchaseId, $purchaseNo, $supNameStmt->fetchColumn() ?: '',
        $initialPaid, $remainingAtCreate,
        $initialPayDate, $d['payment_mode'] ?? '', $d['transaction_no'] ?? '',
        'Initial payment at purchase creation',
        (int)$_SESSION['user_id'], $now,
      ]);
      $initialPaymentId = (int)$db->lastInsertId(); // before the ledger insert changes it

      // Cash Book: tie the entry to THIS payment row (not to the purchase),
      // so deleting or reversing the payment later undoes exactly this
      // amount. The purchase itself is saved in several steps that can't all
      // share one transaction (stock/batch helpers run DDL), so if the book
      // write fails the purchase stays saved and the caller is TOLD, instead
      // of the failure being swallowed as it used to be.
      if (($d['payment_mode'] ?? '') === 'Cash in Hand') {
        try {
          cbPost($db, [
            'type' => 'purchase', 'direction' => 'out', 'amount' => $initialPaid,
            'date' => $initialPayDate, 'ref_type' => 'purchase_payment', 'ref_id' => $initialPaymentId,
            'note' => "Purchase {$purchaseNo}", 'user_id' => (int)$_SESSION['user_id'],
          ]);
        } catch (Throwable $cbEx) {
          error_log('purchases.php Cash Book entry failed: ' . $cbEx->getMessage());
          $cashBookWarning = 'Purchase saved, but the Cash Book entry for ₹' . number_format($initialPaid, 2)
            . ' could not be recorded. Add a correction entry in the Cash Book.';
          logActivity((int)$_SESSION['user_id'], 'create', 'purchase', $purchaseId,
            '⚠ Cash Book entry FAILED for ' . $purchaseNo . ' (₹' . number_format($initialPaid, 2) . ')');
        }
      }
    }
    $resp = ['success' => true, 'id' => $purchaseId, 'purchase_no' => $purchaseNo];
    if ($cashBookWarning) $resp['cash_book_warning'] = $cashBookWarning;
    jsonResponse($resp);
    break;

  case 'PUT':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);
    $items = $d['items'] ?? [];
    if (!is_array($items) || count($items) === 0) jsonResponse(['error' => 'At least one item is required'], 400);
    // NOTE: editing a purchase no longer touches the Cash Book at all, so
    // there is no carried-session check here either. What was PAID is only
    // ever changed through purchase_payments.php (each cash payment has its
    // own Cash Book entry); this endpoint deliberately never updates
    // amount_paid. The old code reversed and re-posted a Cash Book entry on
    // every edit — which could refund cash that had never been deducted
    // (payments made later from the payment-history screen) and wrote two
    // meaningless rows per edit even when nothing changed.

    // Capture the pre-edit state so the activity log can say what actually
    // changed instead of just "Purchase updated" with no detail — the same
    // pre-edit-capture + diff pattern already used in expenses.php.
    $oldPayStmt = $db->prepare('SELECT payment_mode, amount_paid, purchase_no, total, status, supplier_id FROM purchases WHERE id = ?');
    $oldPayStmt->execute([$id]);
    $oldPay = $oldPayStmt->fetch();

    // Capture old items' batch_no + qty too, so batch quantities can be
    // reversed before the new items are received below (same principle
    // as the Cash in Hand reversal-then-reapply pattern above).
    $oldItemsStmt = $db->prepare('SELECT product_id, batch_no, qty FROM purchase_items WHERE purchase_id = ?');
    $oldItemsStmt->execute([$id]);
    $oldItems = $oldItemsStmt->fetchAll();

    $computed = array_map('computeItemWeights', $items);
    $subtotal = array_sum(array_column($computed, 'amount'));

    $transportCharge = (float)($d['transport_charge'] ?? 0);
    $loadingCharge   = (float)($d['loading_charge'] ?? 0);
    $packingCharge   = (float)($d['packing_charge'] ?? 0);
    $otherCharges    = (float)($d['other_charges'] ?? 0);
    $addCharges      = $transportCharge + $loadingCharge + $packingCharge + $otherCharges;
    $discountAmount  = (float)($d['discount_amount'] ?? 0);
    $deductions      = is_array($d['deductions'] ?? null) ? $d['deductions'] : [];
    $deductionAmount = array_sum(array_map(fn($x) => (float)($x['amount'] ?? 0), $deductions));
    $additions       = is_array($d['additions'] ?? null) ? $d['additions'] : [];
    $additionAmount  = array_sum(array_map(fn($x) => (float)($x['amount'] ?? 0), $additions));
    $tradeDiscPct    = (float)($d['trade_discount_pct'] ?? 0);
    $cashDiscPct     = (float)($d['cash_discount_pct'] ?? 0);
    $tradeDiscAmount = round($subtotal * $tradeDiscPct / 100, 2);
    $cashDiscAmount  = round($subtotal * $cashDiscPct / 100, 2);
    $taxable         = $subtotal + $addCharges + $additionAmount - $discountAmount - $deductionAmount - $tradeDiscAmount - $cashDiscAmount;

    $gstApplicable = !empty($d['gst_applicable']);
    $gstPct = $gstApplicable ? (float)($d['gst_pct'] ?? 0) : 0;
    $gstAmount = $gstApplicable ? round($taxable * $gstPct / 100, 2) : 0;
    $total = round($taxable + $gstAmount, 2);

    // Only overwrite the attachment/slip if a new one was sent
    $newAttachment = saveAttachment($d['attachment'] ?? null);
    $newKantaSlip  = saveAttachment($d['kanta_slip'] ?? null);

    // amount_paid and status are DELIBERATELY not in this UPDATE anymore —
    // that was the actual root cause of the "second partial payment
    // overwrites the first" bug. Payment info is now only ever changed by
    // purchase_payments.php (recachePurchasePaid), never by editing the
    // purchase itself, so this SET clause structurally cannot touch it.
    $sql = 'UPDATE purchases SET
      supplier_id=?, supplier_invoice_ref=?, purchase_date=?, currency=?, exchange_rate=?,
      subtotal=?, gst_amount=?, gst_pct=?, total=?, notes=?,
      reference_po_no=?, supplier_type=?, gst_applicable=?, supply_type=?,
      transport_mode=?, vehicle_no=?, driver_name=?, warehouse=?, payment_terms=?, payment_type=?, expected_payment_date=?, remarks=?,
      transport_charge=?, loading_charge=?, packing_charge=?, other_charges=?, discount_amount=?, discount_remarks=?,
      deductions=?, deduction_amount=?, additions=?, addition_amount=?, trade_discount_pct=?, cash_discount_pct=?, cd_applicable_within=?, trade_discount_amount=?, cash_discount_amount=?,
      payment_mode=?, transaction_no=?, payment_date=?,
      weighing_type=?, kanta_name=?, weighbridge_slip_no=?, weight_datetime=?,
      kanta_gross_weight=?, kanta_tare_weight=?, kanta_operator_name=?,
      header_moisture_pct=?, header_impurity_pct=?, header_dhalta_pct=?, header_dhalta_kg=?, header_billable_weight=?'
      . ($newAttachment ? ', attachment_path=?' : '') . ($newKantaSlip ? ', kanta_slip_path=?' : '') . '
      WHERE id=?';
    $params = [
      (int)$d['supplier_id'], $d['invoice_bill_no'] ?? '', $d['purchase_date'],
      $d['currency'] ?? 'INR', (float)($d['exchange_rate'] ?? 1),
      $subtotal, $gstAmount, $gstPct, $total, $d['notes'] ?? '',
      $d['reference_po_no'] ?? '', $d['supplier_type'] ?? '', $gstApplicable ? 1 : 0, $d['supply_type'] ?? 'Intra-State',
      $d['transport_mode'] ?? '', $d['vehicle_no'] ?? '', $d['driver_name'] ?? '', $d['warehouse'] ?? 'Main Warehouse',
      $d['payment_terms'] ?? '', $d['payment_type'] ?? '', ($d['expected_payment_date'] ?? '') ?: null, $d['remarks'] ?? '',
      $transportCharge, $loadingCharge, $packingCharge, $otherCharges, $discountAmount, mb_substr($d['discount_remarks'] ?? '', 0, 255),
      json_encode($deductions), $deductionAmount, json_encode($additions), $additionAmount, $tradeDiscPct, $cashDiscPct, $d['cd_applicable_within'] ?? 'Same Day', $tradeDiscAmount, $cashDiscAmount,
      $d['payment_mode'] ?? '', $d['transaction_no'] ?? '', $d['payment_date'] ?? null,
      $d['weighing_type'] ?? 'Dharam Kanta', $d['kanta_name'] ?? '', $d['weighbridge_slip_no'] ?? '', $d['weight_datetime'] ?: null,
      (float)($d['kanta_gross_weight'] ?? 0), (float)($d['kanta_tare_weight'] ?? 0), $d['kanta_operator_name'] ?? '',
      $d['header_moisture_pct'] ?? null, $d['header_impurity_pct'] ?? null, $d['header_dhalta_pct'] ?? null,
      $d['header_dhalta_kg'] ?? null, $d['header_billable_weight'] ?? null,
    ];
    if ($newAttachment) $params[] = $newAttachment;
    if ($newKantaSlip) $params[] = $newKantaSlip;
    $params[] = $id;
    $db->prepare($sql)->execute($params);

    // Replace items and stock-ledger entries tied to this purchase.
    clearStockForPurchase($db, $id);
    $db->prepare('DELETE FROM purchase_items WHERE purchase_id = ?')->execute([$id]);

    // Reverse the batch quantities the OLD items had added, before the new
    // items (below) add their own — same reverse-then-reapply principle
    // used for Cash in Hand edits.
    foreach ($oldItems as $oi) {
      if ($oi['product_id'] && trim((string)$oi['batch_no']) !== '') {
        reverseBatchReceive($db, $oi['product_id'], $oi['batch_no'], (float)$oi['qty']);
      }
    }

    $itemStmt = $db->prepare('INSERT INTO purchase_items
      (purchase_id, product_id, description, hsn, qty, unit, entered_qty, entered_unit, rate, gst_pct, amount,
       variety_grade, moisture_pct, quality_grade, gross_weight, tare_weight, dhalta_pct, dhalta_kg, billable_weight, discount_pct, batch_no, bags)
      VALUES (?,?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?,?,?,?,?)');
    $totalBags = 0;
    foreach ($items as $i => $it) {
      $c = $computed[$i];
      $productId = cleanProductId($it['product_id'] ?? null);
      $batchNo = trim($it['batch_no'] ?? '');
      $bags = max(0, (int)($it['bags'] ?? 0));
      $totalBags += $bags;
      $itemStmt->execute([
        $id, $productId, $it['description'] ?? '', $it['hsn'] ?? '',
        $c['net'], 'kg', $c['net'], 'kg', $c['rate'], 0, $c['amount'],
        $it['variety_grade'] ?? '', $it['moisture_pct'] ?? 0, $it['quality_grade'] ?? '',
        $c['gross'], $c['tare'], $c['dhaltaPct'], $c['dhaltaKg'], $c['billable'], $c['discPct'], $batchNo, $bags,
      ]);
      if ($productId) {
        writeStockIn($db, $productId, $id, $c['net'], $c['effectiveRate'], $d['purchase_date'], 'Purchase ' . ($d['purchase_no'] ?? ('#' . $id)) . ' (edited)', $d['warehouse'] ?? 'Main Warehouse', $batchNo);
        if ($batchNo !== '') {
          receiveIntoBatch($db, $productId, $batchNo, $c['net'], (int)$_SESSION['user_id'],
            $it['mfg_date'] ?? null, $it['expiry_date'] ?? null, $id);
        }
      }
    }

    $db->prepare('UPDATE purchases SET total_bags = ? WHERE id = ?')->execute([$totalBags, $id]);

    logActivity((int)$_SESSION['user_id'], 'update', 'purchase', $id, buildPurchaseEditDiff($db, $oldPay, $total, (int)$d['supplier_id']));
    if ($oldPay && abs((float)$oldPay['total'] - $total) > 0.004) {
      recalcPurchasePaymentBalances($db, $id, $total);
    }
    // Correct balance_after for every affected product in the full ledger
    $affectedProducts = array_filter(array_map(
        fn($it) => cleanProductId($it['product_id'] ?? null), $items
    ));
    rebalanceStockLedger($db, array_values($affectedProducts));

    jsonResponse(['success' => true]);
    break;

  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    // Capture affected products BEFORE clearing, so we can rebalance after
    $delProds = $db->prepare('SELECT DISTINCT product_id FROM purchase_items WHERE purchase_id = ?');
    $delProds->execute([$id]);
    $delProdIds = array_column($delProds->fetchAll(), 'product_id');
    // Capture the purchase number and every payment row BEFORE deleting, for
    // the Cash Book reversal below. All rows, including ones already
    // soft-deleted: cbReverse nets out what is still outstanding per row, so
    // a payment that was already refunded is simply skipped.
    $delPayStmt = $db->prepare('SELECT purchase_no FROM purchases WHERE id = ?');
    $delPayStmt->execute([$id]);
    $delPay = $delPayStmt->fetch();
    $delPayRows = [];
    try {
      $delPayRowsStmt = $db->prepare('SELECT id FROM purchase_payments WHERE purchase_id = ?');
      $delPayRowsStmt->execute([$id]);
      $delPayRows = $delPayRowsStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) { /* purchase_payments missing — nothing to reverse */ }
    // Capture item batch info before deleting, to reverse batch quantities
    $delItemsStmt = $db->prepare('SELECT product_id, batch_no, qty FROM purchase_items WHERE purchase_id = ?');
    $delItemsStmt->execute([$id]);
    $delItems = $delItemsStmt->fetchAll();
    clearStockForPurchase($db, $id);
    $db->prepare('DELETE FROM purchase_items WHERE purchase_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM purchases WHERE id = ?')->execute([$id]);
    logActivity((int)$_SESSION['user_id'], 'delete', 'purchase', $id, 'Purchase deleted');
    rebalanceStockLedger($db, $delProdIds);
    foreach ($delItems as $di) {
      if ($di['product_id'] && trim((string)$di['batch_no']) !== '') {
        reverseBatchReceive($db, $di['product_id'], $di['batch_no'], (float)$di['qty']);
      }
    }
    // Cash Book: give back whatever this purchase still holds. Reverses each
    // payment row's own entry, plus the old-style entry that purchases made
    // BEFORE this fix recorded under the purchase's own id. Safe when nothing
    // was ever posted (other payment methods): nothing is outstanding, so
    // nothing is posted. Never blocks the delete — a failure is reported.
    $delCashWarning = null;
    $delPurNo = $delPay['purchase_no'] ?? ('#' . $id);
    try {
      cbBegin($db);
      try {
        foreach ($delPayRows as $pid) {
          cbReverse($db, 'purchase_payment', (int)$pid, "Reversal: Purchase {$delPurNo} deleted", (int)$_SESSION['user_id']);
        }
        cbReverse($db, 'purchase', $id, "Reversal: Purchase {$delPurNo} deleted", (int)$_SESSION['user_id']);
        cbCommit($db);
      } catch (Throwable $inner) { cbRollback($db); throw $inner; }
    } catch (Throwable $cbEx) {
      error_log('purchases.php delete Cash Book reversal failed: ' . $cbEx->getMessage());
      $delCashWarning = 'Purchase deleted, but its Cash Book refund could not be recorded. Add a correction entry in the Cash Book.';
      logActivity((int)$_SESSION['user_id'], 'delete', 'purchase', $id, '⚠ Cash Book reversal FAILED for ' . $delPurNo);
    }
    $delResp = ['success' => true];
    if ($delCashWarning) $delResp['cash_book_warning'] = $delCashWarning;
    jsonResponse($delResp);
    break;

  default:
    jsonResponse(['error' => 'Method not allowed'], 405);
}
} catch (Throwable $e) {
  jsonResponse(['error' => 'Purchases API error: ' . $e->getMessage()], 500);
}
