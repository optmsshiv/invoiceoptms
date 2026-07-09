<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Product IDs arrive from the frontend as "p12" (Products page convention).
function cleanProductId($v) {
  if (empty($v)) return null;
  $n = (int) preg_replace('/\D/', '', (string)$v);
  return $n > 0 ? $n : null;
}

function currentStock($db, $productId) {
  $stmt = $db->prepare('SELECT COALESCE(SUM(CASE WHEN direction="in" THEN qty ELSE -qty END),0) AS bal FROM stock_ledger WHERE product_id = ?');
  $stmt->execute([$productId]);
  return (float)$stmt->fetch()['bal'];
}

// Write one stock-ledger OUT row — the sell-side counterpart to Purchases'
// writeStockIn(), finally closing the loop: stock now moves in from
// Purchases and out from Sales, so "current stock" is trustworthy end to end.
function writeStockOut($db, $productId, $saleId, $qty, $rate, $date, $note) {
  if ($qty <= 0) return;
  $bal = currentStock($db, $productId) - $qty;
  $stmt = $db->prepare('INSERT INTO stock_ledger (product_id, ref_type, ref_id, direction, qty, rate, balance_after, movement_date, notes) VALUES (?,"sale",?,"out",?,?,?,?,?)');
  $stmt->execute([$productId, $saleId, $qty, $rate, $bal, $date, $note]);
}

function clearStockForSale($db, $saleId) {
  $stmt = $db->prepare('DELETE FROM stock_ledger WHERE ref_type = "sale" AND ref_id = ?');
  $stmt->execute([$saleId]);
}

// Server-authoritative line calc — never trusts client-computed totals.
function computeSaleItem($it) {
  $qty   = (float)($it['qty'] ?? 0);
  $rate  = (float)($it['rate'] ?? 0);
  $disc  = (float)($it['discount_pct'] ?? 0);
  $gst   = (float)($it['gst_pct'] ?? 0);
  $lineSubtotal = round($qty * $rate * (1 - $disc / 100), 2);
  $taxAmount    = round($lineSubtotal * $gst / 100, 2);
  $lineTotal    = round($lineSubtotal + $taxAmount, 2);
  return compact('qty', 'rate', 'lineSubtotal', 'taxAmount', 'lineTotal');
}

function saveAttachment($dataUrl) {
  if (!$dataUrl || !preg_match('/^data:(image\/(png|jpe?g|webp)|application\/pdf);base64,(.+)$/', $dataUrl, $m)) return null;
  $ext  = str_contains($m[1], 'pdf') ? 'pdf' : ($m[2] === 'jpeg' ? 'jpg' : $m[2]);
  $blob = base64_decode($m[3]);
  if ($blob === false || strlen($blob) > (defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 5242880)) return null;
  $dir = rtrim(defined('UPLOAD_PATH') ? UPLOAD_PATH : (__DIR__ . '/../assets/uploads/'), '/') . '/sales';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $fname = 'sale_' . bin2hex(random_bytes(8)) . '.' . $ext;
  file_put_contents($dir . '/' . $fname, $blob);
  return '/assets/uploads/sales/' . $fname;
}

try {
switch ($method) {
  case 'GET':
    if (!empty($_GET['id'])) {
      $id = (int)$_GET['id'];
      $stmt = $db->prepare('SELECT s.*, c.name AS customer_name FROM sales s JOIN customers c ON c.id = s.customer_id WHERE s.id = ?');
      $stmt->execute([$id]);
      $sale = $stmt->fetch();
      if (!$sale) jsonResponse(['error' => 'Not found'], 404);
      $itemsStmt = $db->prepare('SELECT si.*, COALESCE(p.name, si.description) AS product_name
        FROM sale_items si LEFT JOIN products p ON p.id = si.product_id WHERE si.sale_id = ? ORDER BY si.id ASC');
      $itemsStmt->execute([$id]);
      $sale['items'] = $itemsStmt->fetchAll();
      $sale['attachments'] = $sale['attachments'] ? json_decode($sale['attachments'], true) : [];
      jsonResponse(['data' => $sale]);
      break;
    }

    $stmt = $db->query('SELECT s.*, c.name AS customer_name,
      (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count
      FROM sales s JOIN customers c ON c.id = s.customer_id ORDER BY s.sale_date DESC, s.id DESC');
    jsonResponse(['data' => $stmt->fetchAll()]);
    break;

  case 'POST':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);
    if (empty($d['customer_id'])) jsonResponse(['error' => 'Customer is required'], 400);
    if (empty($d['sale_date']))   jsonResponse(['error' => 'Sale date is required'], 400);
    $items = $d['items'] ?? [];
    if (!is_array($items) || count($items) === 0) jsonResponse(['error' => 'At least one item is required'], 400);

    $invoiceNo = trim($d['invoice_no'] ?? '');
    if ($invoiceNo === '') {
      $y = date('y'); $y2 = date('y', strtotime('+1 year'));
      $cnt = $db->query('SELECT COUNT(*) c FROM sales')->fetch()['c'] + 1;
      $invoiceNo = "INV/{$y}-{$y2}/" . str_pad($cnt, 4, '0', STR_PAD_LEFT);
    }

    $computed = array_map('computeSaleItem', $items);
    $subtotal = array_sum(array_column($computed, 'lineSubtotal'));
    $itemsTax = array_sum(array_column($computed, 'taxAmount'));

    $transportCharge = (float)($d['transport_charge'] ?? 0);
    $loadingCharge   = (float)($d['loading_charge'] ?? 0);
    $packingCharge   = (float)($d['packing_charge'] ?? 0);
    $insuranceCharge = (float)($d['insurance_charge'] ?? 0);
    $otherCharges    = (float)($d['other_charges'] ?? 0);
    $addCharges      = $transportCharge + $loadingCharge + $packingCharge + $insuranceCharge + $otherCharges;
    $discountAmount  = (float)($d['discount_amount'] ?? 0);
    $roundOff        = (float)($d['round_off'] ?? 0);

    $taxable = round($subtotal + $addCharges - $discountAmount, 2);
    // Item-level tax was computed against each line's own subtotal; scale it
    // proportionally if a header discount changed the taxable base, so total
    // tax stays consistent with the taxable amount actually being charged.
    $totalTax = $subtotal > 0 ? round($itemsTax * ($taxable / $subtotal), 2) : 0;

    $isInterstate = !empty($d['is_interstate']);
    $cgst = $isInterstate ? 0 : round($totalTax / 2, 2);
    $sgst = $isInterstate ? 0 : round($totalTax / 2, 2);
    $igst = $isInterstate ? $totalTax : 0;

    $total = round($taxable + $totalTax + $roundOff, 2);
    $attachments = array_values(array_filter(array_map(function($a) {
      return (is_string($a) && str_starts_with($a, 'data:')) ? saveAttachment($a) : $a;
    }, $d['attachments'] ?? [])));

    $stmt = $db->prepare('INSERT INTO sales
      (invoice_no, customer_id, sale_date, due_date, sales_executive, payment_terms, sales_type, place_of_supply, currency,
       subtotal, transport_charge, loading_charge, packing_charge, insurance_charge, other_charges, round_off, discount_amount,
       taxable_amount, cgst_amount, sgst_amount, igst_amount, total_tax, total,
       payment_status, payment_method, amount_received, transaction_no, payment_date,
       customer_notes, internal_notes, delivery_instructions, attachments,
       prepared_by, checked_by, approved_by, status,
       weighing_type, kanta_name, weighbridge_slip_no, weight_datetime, kanta_operator_name,
       kanta_gross_weight, kanta_tare_weight, kanta_moisture_pct, kanta_dhalta_kg)
      VALUES (?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?,?, ?,?,?,?,?,?, ?,?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?,?, ?,?,?,?)');
    $stmt->execute([
      $invoiceNo, (int)$d['customer_id'], $d['sale_date'], $d['due_date'] ?? null,
      $d['sales_executive'] ?? '', $d['payment_terms'] ?? '', $d['sales_type'] ?? 'Local Sales', $d['place_of_supply'] ?? '', $d['currency'] ?? 'INR',
      $subtotal, $transportCharge, $loadingCharge, $packingCharge, $insuranceCharge, $otherCharges, $roundOff, $discountAmount,
      $taxable, $cgst, $sgst, $igst, $totalTax, $total,
      $d['payment_status'] ?? 'Pending', $d['payment_method'] ?? '', (float)($d['amount_received'] ?? 0), $d['transaction_no'] ?? '', $d['payment_date'] ?? null,
      $d['customer_notes'] ?? '', $d['internal_notes'] ?? '', $d['delivery_instructions'] ?? '', json_encode($attachments),
      $d['prepared_by'] ?? '', $d['checked_by'] ?? '', $d['approved_by'] ?? '', $d['status'] ?? 'Confirmed',
      $d['weighing_type'] ?? 'Dharam Kanta', $d['kanta_name'] ?? '', $d['weighbridge_slip_no'] ?? '', $d['weight_datetime'] ?: null, $d['kanta_operator_name'] ?? '',
      (float)($d['kanta_gross_weight'] ?? 0), (float)($d['kanta_tare_weight'] ?? 0), $d['kanta_moisture_pct'] ?? null, (float)($d['kanta_dhalta_kg'] ?? 0),
    ]);
    $saleId = (int)$db->lastInsertId();

    $itemStmt = $db->prepare('INSERT INTO sale_items
      (sale_id, product_id, description, variety_grade, batch_no, warehouse, qty, unit, rate, discount_pct, gst_pct, tax_amount, line_total)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($items as $i => $it) {
      $c = $computed[$i];
      $productId = cleanProductId($it['product_id'] ?? null);
      $itemStmt->execute([
        $saleId, $productId, $it['description'] ?? '', $it['variety_grade'] ?? '', $it['batch_no'] ?? '',
        $it['warehouse'] ?? 'Main Warehouse', $c['qty'], $it['unit'] ?? 'Kg', $c['rate'],
        (float)($it['discount_pct'] ?? 0), (float)($it['gst_pct'] ?? 0), $c['taxAmount'], $c['lineTotal'],
      ]);
      if ($productId) {
        writeStockOut($db, $productId, $saleId, $c['qty'], $c['rate'], $d['sale_date'], 'Sale ' . $invoiceNo);
      }
    }

    logActivity((int)$_SESSION['user_id'], 'create', 'sale', $saleId, 'Sale created: ' . $invoiceNo);
    jsonResponse(['success' => true, 'id' => $saleId, 'invoice_no' => $invoiceNo]);
    break;

  case 'PUT':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);
    $items = $d['items'] ?? [];
    if (!is_array($items) || count($items) === 0) jsonResponse(['error' => 'At least one item is required'], 400);

    $computed = array_map('computeSaleItem', $items);
    $subtotal = array_sum(array_column($computed, 'lineSubtotal'));
    $itemsTax = array_sum(array_column($computed, 'taxAmount'));

    $transportCharge = (float)($d['transport_charge'] ?? 0);
    $loadingCharge   = (float)($d['loading_charge'] ?? 0);
    $packingCharge   = (float)($d['packing_charge'] ?? 0);
    $insuranceCharge = (float)($d['insurance_charge'] ?? 0);
    $otherCharges    = (float)($d['other_charges'] ?? 0);
    $addCharges      = $transportCharge + $loadingCharge + $packingCharge + $insuranceCharge + $otherCharges;
    $discountAmount  = (float)($d['discount_amount'] ?? 0);
    $roundOff        = (float)($d['round_off'] ?? 0);

    $taxable = round($subtotal + $addCharges - $discountAmount, 2);
    $totalTax = $subtotal > 0 ? round($itemsTax * ($taxable / $subtotal), 2) : 0;

    $isInterstate = !empty($d['is_interstate']);
    $cgst = $isInterstate ? 0 : round($totalTax / 2, 2);
    $sgst = $isInterstate ? 0 : round($totalTax / 2, 2);
    $igst = $isInterstate ? $totalTax : 0;
    $total = round($taxable + $totalTax + $roundOff, 2);

    $attachments = array_values(array_filter(array_map(function($a) {
      return (is_string($a) && str_starts_with($a, 'data:')) ? saveAttachment($a) : $a;
    }, $d['attachments'] ?? [])));

    $db->prepare('UPDATE sales SET
      customer_id=?, sale_date=?, due_date=?, sales_executive=?, payment_terms=?, sales_type=?, place_of_supply=?, currency=?,
      subtotal=?, transport_charge=?, loading_charge=?, packing_charge=?, insurance_charge=?, other_charges=?, round_off=?, discount_amount=?,
      taxable_amount=?, cgst_amount=?, sgst_amount=?, igst_amount=?, total_tax=?, total=?,
      payment_status=?, payment_method=?, amount_received=?, transaction_no=?, payment_date=?,
      customer_notes=?, internal_notes=?, delivery_instructions=?, attachments=?,
      prepared_by=?, checked_by=?, approved_by=?, status=?,
      weighing_type=?, kanta_name=?, weighbridge_slip_no=?, weight_datetime=?, kanta_operator_name=?,
      kanta_gross_weight=?, kanta_tare_weight=?, kanta_moisture_pct=?, kanta_dhalta_kg=?
      WHERE id=?')->execute([
      (int)$d['customer_id'], $d['sale_date'], $d['due_date'] ?? null,
      $d['sales_executive'] ?? '', $d['payment_terms'] ?? '', $d['sales_type'] ?? 'Local Sales', $d['place_of_supply'] ?? '', $d['currency'] ?? 'INR',
      $subtotal, $transportCharge, $loadingCharge, $packingCharge, $insuranceCharge, $otherCharges, $roundOff, $discountAmount,
      $taxable, $cgst, $sgst, $igst, $totalTax, $total,
      $d['payment_status'] ?? 'Pending', $d['payment_method'] ?? '', (float)($d['amount_received'] ?? 0), $d['transaction_no'] ?? '', $d['payment_date'] ?? null,
      $d['customer_notes'] ?? '', $d['internal_notes'] ?? '', $d['delivery_instructions'] ?? '', json_encode($attachments),
      $d['prepared_by'] ?? '', $d['checked_by'] ?? '', $d['approved_by'] ?? '', $d['status'] ?? 'Confirmed',
      $d['weighing_type'] ?? 'Dharam Kanta', $d['kanta_name'] ?? '', $d['weighbridge_slip_no'] ?? '', $d['weight_datetime'] ?: null, $d['kanta_operator_name'] ?? '',
      (float)($d['kanta_gross_weight'] ?? 0), (float)($d['kanta_tare_weight'] ?? 0), $d['kanta_moisture_pct'] ?? null, (float)($d['kanta_dhalta_kg'] ?? 0),
      $id,
    ]);

    clearStockForSale($db, $id);
    $db->prepare('DELETE FROM sale_items WHERE sale_id = ?')->execute([$id]);

    $itemStmt = $db->prepare('INSERT INTO sale_items
      (sale_id, product_id, description, variety_grade, batch_no, warehouse, qty, unit, rate, discount_pct, gst_pct, tax_amount, line_total)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($items as $i => $it) {
      $c = $computed[$i];
      $productId = cleanProductId($it['product_id'] ?? null);
      $itemStmt->execute([
        $id, $productId, $it['description'] ?? '', $it['variety_grade'] ?? '', $it['batch_no'] ?? '',
        $it['warehouse'] ?? 'Main Warehouse', $c['qty'], $it['unit'] ?? 'Kg', $c['rate'],
        (float)($it['discount_pct'] ?? 0), (float)($it['gst_pct'] ?? 0), $c['taxAmount'], $c['lineTotal'],
      ]);
      if ($productId) {
        writeStockOut($db, $productId, $id, $c['qty'], $c['rate'], $d['sale_date'], 'Sale ' . ($d['invoice_no'] ?? ('#' . $id)) . ' (edited)');
      }
    }

    logActivity((int)$_SESSION['user_id'], 'update', 'sale', $id, 'Sale updated');
    jsonResponse(['success' => true]);
    break;

  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    clearStockForSale($db, $id);
    $db->prepare('DELETE FROM sales WHERE id = ?')->execute([$id]);
    logActivity((int)$_SESSION['user_id'], 'delete', 'sale', $id, 'Sale deleted');
    jsonResponse(['success' => true]);
    break;

  default:
    jsonResponse(['error' => 'Method not allowed'], 405);
}
} catch (Throwable $e) {
  jsonResponse(['error' => 'Sales API error: ' . $e->getMessage()], 500);
}
