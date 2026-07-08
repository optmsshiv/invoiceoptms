<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
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
function writeStockIn($db, $productId, $purchaseId, $netWeight, $effectiveRate, $date, $note) {
  if ($netWeight <= 0) return;
  $bal = currentStock($db, $productId) + $netWeight;
  $stmt = $db->prepare('INSERT INTO stock_ledger (product_id, ref_type, ref_id, direction, qty, rate, balance_after, movement_date, notes) VALUES (?,"purchase",?,"in",?,?,?,?,?)');
  $stmt->execute([$productId, $purchaseId, $netWeight, $effectiveRate, $bal, $date, $note]);
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
  $dhaltaPct = (float)($it['dhalta_pct'] ?? 0);
  $dhaltaKg  = round($net * $dhaltaPct / 100, 3);
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
    if (empty($d['supplier_id']))   jsonResponse(['error' => 'Supplier is required'], 400);
    if (empty($d['purchase_date'])) jsonResponse(['error' => 'Purchase date is required'], 400);
    $items = $d['items'] ?? [];
    if (!is_array($items) || count($items) === 0) jsonResponse(['error' => 'At least one item is required'], 400);

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
    $taxable         = $subtotal + $addCharges - $discountAmount;

    $gstApplicable = !empty($d['gst_applicable']);
    $gstPct = $gstApplicable ? (float)($d['gst_pct'] ?? 0) : 0;
    $gstAmount = $gstApplicable ? round($taxable * $gstPct / 100, 2) : 0;
    $total = round($taxable + $gstAmount, 2);

    $attachmentPath = saveAttachment($d['attachment'] ?? null);

    $stmt = $db->prepare('INSERT INTO purchases
      (purchase_no, supplier_id, supplier_invoice_ref, purchase_date, currency, exchange_rate,
       subtotal, gst_amount, gst_pct, total, amount_paid, status, notes,
       reference_po_no, supplier_type, gst_applicable, supply_type,
       transport_mode, vehicle_no, driver_name, warehouse, payment_terms, payment_type, remarks,
       transport_charge, loading_charge, packing_charge, other_charges, discount_amount,
       attachment_path, payment_mode, transaction_no, payment_date)
      VALUES (?,?,?,?,?,?, ?,?,?,?,?,?,?, ?,?,?,?, ?,?,?,?,?,?,?, ?,?,?,?,?, ?,?,?,?)');
    $stmt->execute([
      $purchaseNo, (int)$d['supplier_id'], $d['invoice_bill_no'] ?? '', $d['purchase_date'],
      $d['currency'] ?? 'INR', (float)($d['exchange_rate'] ?? 1),
      $subtotal, $gstAmount, $gstPct, $total, (float)($d['amount_paid'] ?? 0),
      $d['payment_status'] ?? 'Pending', $d['notes'] ?? '',
      $d['reference_po_no'] ?? '', $d['supplier_type'] ?? '', $gstApplicable ? 1 : 0, $d['supply_type'] ?? 'Intra-State',
      $d['transport_mode'] ?? '', $d['vehicle_no'] ?? '', $d['driver_name'] ?? '', $d['warehouse'] ?? 'Main Warehouse',
      $d['payment_terms'] ?? '', $d['payment_type'] ?? '', $d['remarks'] ?? '',
      $transportCharge, $loadingCharge, $packingCharge, $otherCharges, $discountAmount,
      $attachmentPath, $d['payment_mode'] ?? '', $d['transaction_no'] ?? '', $d['payment_date'] ?? null,
    ]);
    $purchaseId = (int)$db->lastInsertId();

    $itemStmt = $db->prepare('INSERT INTO purchase_items
      (purchase_id, product_id, description, hsn, qty, unit, entered_qty, entered_unit, rate, gst_pct, amount,
       variety_grade, moisture_pct, quality_grade, gross_weight, tare_weight, dhalta_pct, dhalta_kg, billable_weight, discount_pct)
      VALUES (?,?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?,?,?)');
    foreach ($items as $i => $it) {
      $c = $computed[$i];
      $productId = cleanProductId($it['product_id'] ?? null);
      $itemStmt->execute([
        $purchaseId, $productId, $it['description'] ?? '', $it['hsn'] ?? '',
        $c['net'], 'kg', $c['net'], 'kg', $c['rate'], 0, $c['amount'],
        $it['variety_grade'] ?? '', $it['moisture_pct'] ?? 0, $it['quality_grade'] ?? '',
        $c['gross'], $c['tare'], $c['dhaltaPct'], $c['dhaltaKg'], $c['billable'], $c['discPct'],
      ]);
      if ($productId) {
        writeStockIn($db, $productId, $purchaseId, $c['net'], $c['effectiveRate'], $d['purchase_date'], 'Purchase ' . $purchaseNo);
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

    $computed = array_map('computeItemWeights', $items);
    $subtotal = array_sum(array_column($computed, 'amount'));

    $transportCharge = (float)($d['transport_charge'] ?? 0);
    $loadingCharge   = (float)($d['loading_charge'] ?? 0);
    $packingCharge   = (float)($d['packing_charge'] ?? 0);
    $otherCharges    = (float)($d['other_charges'] ?? 0);
    $addCharges      = $transportCharge + $loadingCharge + $packingCharge + $otherCharges;
    $discountAmount  = (float)($d['discount_amount'] ?? 0);
    $taxable         = $subtotal + $addCharges - $discountAmount;

    $gstApplicable = !empty($d['gst_applicable']);
    $gstPct = $gstApplicable ? (float)($d['gst_pct'] ?? 0) : 0;
    $gstAmount = $gstApplicable ? round($taxable * $gstPct / 100, 2) : 0;
    $total = round($taxable + $gstAmount, 2);

    // Only overwrite the attachment if a new one was sent
    $newAttachment = saveAttachment($d['attachment'] ?? null);

    $sql = 'UPDATE purchases SET
      supplier_id=?, supplier_invoice_ref=?, purchase_date=?, currency=?, exchange_rate=?,
      subtotal=?, gst_amount=?, gst_pct=?, total=?, amount_paid=?, status=?, notes=?,
      reference_po_no=?, supplier_type=?, gst_applicable=?, supply_type=?,
      transport_mode=?, vehicle_no=?, driver_name=?, warehouse=?, payment_terms=?, payment_type=?, remarks=?,
      transport_charge=?, loading_charge=?, packing_charge=?, other_charges=?, discount_amount=?,
      payment_mode=?, transaction_no=?, payment_date=?' . ($newAttachment ? ', attachment_path=?' : '') . '
      WHERE id=?';
    $params = [
      (int)$d['supplier_id'], $d['invoice_bill_no'] ?? '', $d['purchase_date'],
      $d['currency'] ?? 'INR', (float)($d['exchange_rate'] ?? 1),
      $subtotal, $gstAmount, $gstPct, $total, (float)($d['amount_paid'] ?? 0),
      $d['payment_status'] ?? 'Pending', $d['notes'] ?? '',
      $d['reference_po_no'] ?? '', $d['supplier_type'] ?? '', $gstApplicable ? 1 : 0, $d['supply_type'] ?? 'Intra-State',
      $d['transport_mode'] ?? '', $d['vehicle_no'] ?? '', $d['driver_name'] ?? '', $d['warehouse'] ?? 'Main Warehouse',
      $d['payment_terms'] ?? '', $d['payment_type'] ?? '', $d['remarks'] ?? '',
      $transportCharge, $loadingCharge, $packingCharge, $otherCharges, $discountAmount,
      $d['payment_mode'] ?? '', $d['transaction_no'] ?? '', $d['payment_date'] ?? null,
    ];
    if ($newAttachment) $params[] = $newAttachment;
    $params[] = $id;
    $db->prepare($sql)->execute($params);

    // Replace items and stock-ledger entries tied to this purchase.
    clearStockForPurchase($db, $id);
    $db->prepare('DELETE FROM purchase_items WHERE purchase_id = ?')->execute([$id]);

    $itemStmt = $db->prepare('INSERT INTO purchase_items
      (purchase_id, product_id, description, hsn, qty, unit, entered_qty, entered_unit, rate, gst_pct, amount,
       variety_grade, moisture_pct, quality_grade, gross_weight, tare_weight, dhalta_pct, dhalta_kg, billable_weight, discount_pct)
      VALUES (?,?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?,?,?)');
    foreach ($items as $i => $it) {
      $c = $computed[$i];
      $productId = cleanProductId($it['product_id'] ?? null);
      $itemStmt->execute([
        $id, $productId, $it['description'] ?? '', $it['hsn'] ?? '',
        $c['net'], 'kg', $c['net'], 'kg', $c['rate'], 0, $c['amount'],
        $it['variety_grade'] ?? '', $it['moisture_pct'] ?? 0, $it['quality_grade'] ?? '',
        $c['gross'], $c['tare'], $c['dhaltaPct'], $c['dhaltaKg'], $c['billable'], $c['discPct'],
      ]);
      if ($productId) {
        writeStockIn($db, $productId, $id, $c['net'], $c['effectiveRate'], $d['purchase_date'], 'Purchase ' . ($d['purchase_no'] ?? ('#' . $id)) . ' (edited)');
      }
    }

    logActivity((int)$_SESSION['user_id'], 'update', 'purchase', $id, 'Purchase updated');
    jsonResponse(['success' => true]);
    break;

  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    clearStockForPurchase($db, $id);
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
