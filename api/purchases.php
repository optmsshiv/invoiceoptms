<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Current stock for a product = sum of all ins minus all outs in the ledger
function currentStock($db, $productId) {
  $stmt = $db->prepare('SELECT COALESCE(SUM(CASE WHEN direction="in" THEN qty ELSE -qty END),0) AS bal FROM stock_ledger WHERE product_id = ?');
  $stmt->execute([$productId]);
  return (float)$stmt->fetch()['bal'];
}

// Write one stock-ledger IN row for a purchase item and return the new running balance
function writeStockIn($db, $productId, $purchaseId, $qty, $rate, $date, $note) {
  $bal = currentStock($db, $productId) + $qty;
  $stmt = $db->prepare('INSERT INTO stock_ledger (product_id, ref_type, ref_id, direction, qty, rate, balance_after, movement_date, notes) VALUES (?,"purchase",?,"in",?,?,?,?,?)');
  $stmt->execute([$productId, $purchaseId, $qty, $rate, $bal, $date, $note]);
  return $bal;
}

// Remove all stock-ledger rows tied to a purchase (used before re-adding on edit, or on delete)
function clearStockForPurchase($db, $purchaseId) {
  $stmt = $db->prepare('DELETE FROM stock_ledger WHERE ref_type = "purchase" AND ref_id = ?');
  $stmt->execute([$purchaseId]);
}

// Convert whatever the user typed (e.g. "500 g") into the product's base unit
// (e.g. "0.5 kg") so Stock Ledger numbers always mean the same thing for a
// product no matter which unit different purchase bills happened to use.
// Rate is converted the opposite way so amount (entered_qty × entered_rate)
// stays mathematically identical — only the ledger's internal bookkeeping unit changes.
function normalizeQtyRate($db, $productId, $enteredQty, $enteredRate, $enteredUnit) {
  if (!$productId) return [$enteredQty, $enteredRate, $enteredUnit ?: 'pcs']; // free-text line, no product to convert against
  $stmt = $db->prepare('SELECT unit_family FROM products WHERE id = ?');
  $stmt->execute([$productId]);
  $fam = $stmt->fetch()['unit_family'] ?? 'count';
  $unit = strtolower(trim((string)$enteredUnit));
  $factor = 1; $base = 'pcs'; // count family: no conversion, tracked in pcs
  if ($fam === 'weight') { $base = 'kg'; $factor = ($unit === 'g') ? 1000 : 1; }
  elseif ($fam === 'volume') { $base = 'ltr'; $factor = ($unit === 'ml') ? 1000 : 1; }
  return [ $enteredQty / $factor, $enteredRate * $factor, $base ];
}

// Product IDs arrive from the frontend as "p12" (a "p"-prefixed string, matching
// the Products page convention) — strip any non-digit characters before using as an int FK.
function cleanProductId($v) {
  if (empty($v)) return null;
  $n = (int) preg_replace('/\D/', '', (string)$v);
  return $n > 0 ? $n : null;
}

try {
switch ($method) {
  case 'GET':
    if (!empty($_GET['id'])) {
      $id = (int)$_GET['id'];
      $stmt = $db->prepare('SELECT p.*, s.name AS supplier_name FROM purchases p JOIN suppliers s ON s.id = p.supplier_id WHERE p.id = ?');
      $stmt->execute([$id]);
      $purchase = $stmt->fetch();
      if (!$purchase) jsonResponse(['error' => 'Not found'], 404);
      $itemsStmt = $db->prepare('SELECT * FROM purchase_items WHERE purchase_id = ? ORDER BY id ASC');
      $itemsStmt->execute([$id]);
      $purchase['items'] = $itemsStmt->fetchAll();
      jsonResponse(['data' => $purchase]);
      break;
    }
    $stmt = $db->query('SELECT p.*, s.name AS supplier_name,
      (SELECT COUNT(*) FROM purchase_items pi WHERE pi.purchase_id = p.id) AS item_count
      FROM purchases p JOIN suppliers s ON s.id = p.supplier_id ORDER BY p.purchase_date DESC, p.id DESC');
    jsonResponse(['data' => $stmt->fetchAll()]);
    break;

  case 'POST':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);
    if (empty($d['supplier_id']))  jsonResponse(['error' => 'Supplier is required'], 400);
    if (empty($d['purchase_date'])) jsonResponse(['error' => 'Purchase date is required'], 400);
    $items = $d['items'] ?? [];
    if (!is_array($items) || count($items) === 0) jsonResponse(['error' => 'At least one item is required'], 400);

    // Auto-generate purchase number if not supplied
    $purchaseNo = trim($d['purchase_no'] ?? '');
    if ($purchaseNo === '') {
      $cnt = $db->query('SELECT COUNT(*) c FROM purchases')->fetch()['c'] + 1;
      $purchaseNo = 'PO-' . date('Y') . '-' . str_pad($cnt, 4, '0', STR_PAD_LEFT);
    }

    // Compute totals from items server-side (don't trust client math)
    $subtotal = 0; $gstAmount = 0;
    foreach ($items as $it) {
      $amt = (float)($it['qty'] ?? 0) * (float)($it['rate'] ?? 0);
      $subtotal  += $amt;
      $gstAmount += $amt * ((float)($it['gst_pct'] ?? 0) / 100);
    }
    $total = $subtotal + $gstAmount;

    $stmt = $db->prepare('INSERT INTO purchases
      (purchase_no, supplier_id, supplier_invoice_ref, purchase_date, currency, exchange_rate, subtotal, gst_amount, total, amount_paid, status, notes)
      VALUES (?,?,?,?,?,?,?,?,?,0,?,?)');
    $stmt->execute([
      $purchaseNo,
      (int)$d['supplier_id'],
      $d['supplier_invoice_ref'] ?? '',
      $d['purchase_date'],
      $d['currency'] ?? 'INR',
      (float)($d['exchange_rate'] ?? 1),
      $subtotal, $gstAmount, $total,
      $d['status'] ?? 'Pending',
      $d['notes'] ?? '',
    ]);
    $purchaseId = (int)$db->lastInsertId();

    $itemStmt = $db->prepare('INSERT INTO purchase_items (purchase_id, product_id, description, hsn, qty, unit, entered_qty, entered_unit, rate, gst_pct, amount) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($items as $it) {
      $enteredQty  = (float)($it['qty'] ?? 0);
      $enteredRate = (float)($it['rate'] ?? 0);
      $enteredUnit = $it['unit'] ?? 'pcs';
      $amt  = $enteredQty * $enteredRate; // invariant regardless of unit conversion
      $productId = cleanProductId($it['product_id'] ?? null);
      [$qtyBase, $rateBase, $baseUnit] = normalizeQtyRate($db, $productId, $enteredQty, $enteredRate, $enteredUnit);
      $itemStmt->execute([
        $purchaseId, $productId, $it['description'] ?? '', $it['hsn'] ?? '',
        $qtyBase, $baseUnit, $enteredQty, $enteredUnit, $enteredRate, (float)($it['gst_pct'] ?? 0), $amt,
      ]);
      if ($productId) {
        writeStockIn($db, $productId, $purchaseId, $qtyBase, $rateBase, $d['purchase_date'], 'Purchase ' . $purchaseNo);
      }
    }

    logActivity((int)$_SESSION['user_id'], 'create', 'purchase', $purchaseId, 'Purchase added: ' . $purchaseNo);
    jsonResponse(['success' => true, 'id' => $purchaseId, 'purchase_no' => $purchaseNo]);
    break;

  case 'PUT':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);
    $items = $d['items'] ?? [];
    if (!is_array($items) || count($items) === 0) jsonResponse(['error' => 'At least one item is required'], 400);

    $subtotal = 0; $gstAmount = 0;
    foreach ($items as $it) {
      $amt = (float)($it['qty'] ?? 0) * (float)($it['rate'] ?? 0);
      $subtotal  += $amt;
      $gstAmount += $amt * ((float)($it['gst_pct'] ?? 0) / 100);
    }
    $total = $subtotal + $gstAmount;

    $stmt = $db->prepare('UPDATE purchases SET
      supplier_id=?, supplier_invoice_ref=?, purchase_date=?, currency=?, exchange_rate=?, subtotal=?, gst_amount=?, total=?, status=?, notes=?
      WHERE id=?');
    $stmt->execute([
      (int)$d['supplier_id'], $d['supplier_invoice_ref'] ?? '', $d['purchase_date'],
      $d['currency'] ?? 'INR', (float)($d['exchange_rate'] ?? 1),
      $subtotal, $gstAmount, $total, $d['status'] ?? 'Pending', $d['notes'] ?? '', $id,
    ]);

    // Replace items and stock-ledger entries tied to this purchase.
    // NOTE: this recomputes balances going forward from "now" — if later purchases/sales
    // were recorded after this one, their stored balance_after snapshots won't be
    // retroactively corrected. Current stock is always safe to trust (it's a live SUM),
    // only the historical balance_after trail for entries after this edit may drift.
    clearStockForPurchase($db, $id);
    $db->prepare('DELETE FROM purchase_items WHERE purchase_id = ?')->execute([$id]);

    $itemStmt = $db->prepare('INSERT INTO purchase_items (purchase_id, product_id, description, hsn, qty, unit, entered_qty, entered_unit, rate, gst_pct, amount) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($items as $it) {
      $enteredQty  = (float)($it['qty'] ?? 0);
      $enteredRate = (float)($it['rate'] ?? 0);
      $enteredUnit = $it['unit'] ?? 'pcs';
      $amt  = $enteredQty * $enteredRate;
      $productId = cleanProductId($it['product_id'] ?? null);
      [$qtyBase, $rateBase, $baseUnit] = normalizeQtyRate($db, $productId, $enteredQty, $enteredRate, $enteredUnit);
      $itemStmt->execute([
        $id, $productId, $it['description'] ?? '', $it['hsn'] ?? '',
        $qtyBase, $baseUnit, $enteredQty, $enteredUnit, $enteredRate, (float)($it['gst_pct'] ?? 0), $amt,
      ]);
      if ($productId) {
        writeStockIn($db, $productId, $id, $qtyBase, $rateBase, $d['purchase_date'], 'Purchase ' . ($d['purchase_no'] ?? ('#' . $id)) . ' (edited)');
      }
    }

    logActivity((int)$_SESSION['user_id'], 'update', 'purchase', $id, 'Purchase updated');
    jsonResponse(['success' => true]);
    break;

  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    clearStockForPurchase($db, $id);
    // purchase_items cascade-deletes via FK ON DELETE CASCADE
    $db->prepare('DELETE FROM purchases WHERE id = ?')->execute([$id]);
    logActivity((int)$_SESSION['user_id'], 'delete', 'purchase', $id, 'Purchase deleted');
    jsonResponse(['success' => true]);
    break;

  default:
    jsonResponse(['error' => 'Method not allowed'], 405);
}
} catch (Throwable $e) {
  jsonResponse(['error' => 'Purchases API error: ' . $e->getMessage()], 500);
}
