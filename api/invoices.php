<?php
// ═══════════════════════════════════════════════════════
//  API: /api/invoices.php — Invoices CRUD
// ═══════════════════════════════════════════════════════
ob_start();                          // buffer any stray output/notices
error_reporting(0);                  // suppress warnings from leaking into JSON
require_once __DIR__ . '/../includes/auth.php';

// Helper: convert empty string to null (prevents MySQL DATE/INT strict-mode errors)
function nullIfEmpty($v) { return ($v === '' || $v === null) ? null : $v; }
requireLogin();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

  // ── GET: list all or single ─────────────────────────
  case 'GET':
    if (!empty($_GET['id'])) {
      $stmt = $db->prepare('
        SELECT i.*, c.name as client_name_rel, c.email as client_email,
               c.phone as client_phone, c.whatsapp as client_wa,
               c.gst_number as client_gst, c.address as client_addr,
               c.color as client_color
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        WHERE i.id = ?');
      $stmt->execute([(int)$_GET['id']]);
      $inv = $stmt->fetch();
      if (!$inv) { jsonResponse(['error'=>'Not found'], 404); }
      // Load items
      $si = $db->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order');
      $si->execute([$inv['id']]);
      $rawItems = $si->fetchAll();
      $inv['items'] = array_map(function($it) {
        return [
          'id'    => $it['id'],
          'desc'  => $it['description'],
          'qty'   => (float)$it['quantity'],
          'rate'  => (float)$it['rate'],
          'gst'   => (float)$it['gst_rate'],
          'total' => (float)$it['line_total'],
        ];
      }, $rawItems);
      if ($inv['pdf_options']) $inv['pdf_options'] = json_decode($inv['pdf_options'], true);
      // Remap for JS frontend
      $inv['num']      = $inv['invoice_number'];
      $inv['client']   = (string)$inv['client_id'];
      $inv['service']  = $inv['service_type'];
      $inv['issued']   = $inv['issued_date'];
      $inv['due']      = $inv['due_date'];
      $inv['amount']   = (float)$inv['grand_total'];
      $inv['disc']     = (float)$inv['discount_pct'];
      $inv['discount_type'] = $inv['discount_type'] ?? 'percent';
      $inv['currency'] = $inv['currency'];
      $inv['template'] = (int)$inv['template_id'];
      $inv['subtotal'] = (float)$inv['subtotal'];
      jsonResponse(['data' => $inv]);
    }
    // List with optional filters
    $where = ['1=1'];
    $params = [];
    if (!empty($_GET['status']))    { $where[] = 'i.status = ?';              $params[] = $_GET['status']; }
    if (!empty($_GET['client_id'])) { $where[] = 'i.client_id = ?';           $params[] = (int)$_GET['client_id']; }
    if (!empty($_GET['from']))      { $where[] = 'i.issued_date >= ?';         $params[] = $_GET['from']; }
    if (!empty($_GET['to']))        { $where[] = 'i.issued_date <= ?';         $params[] = $_GET['to']; }
    if (!empty($_GET['q'])) {
      $q = '%' . $_GET['q'] . '%';
      $where[] = '(i.invoice_number LIKE ? OR i.client_name LIKE ? OR i.service_type LIKE ?)';
      array_push($params, $q, $q, $q);
    }
    $sql  = 'SELECT i.*, c.color as client_color FROM invoices i LEFT JOIN clients c ON c.id = i.client_id WHERE ' . implode(' AND ', $where) . ' ORDER BY i.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();
    // Load items for each
    foreach ($invoices as &$inv) {
      $si = $db->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order');
      $si->execute([$inv['id']]);
      $rawItems = $si->fetchAll();
      $inv['items'] = array_map(function($it){
        return ['id'=>$it['id'],'desc'=>$it['description'],'qty'=>(float)$it['quantity'],'rate'=>(float)$it['rate'],'gst'=>(float)$it['gst_rate'],'total'=>(float)$it['line_total']];
      }, $rawItems);
      $inv['num']       = $inv['invoice_number'];
      $inv['client']    = (string)$inv['client_id'];
      $inv['service']   = $inv['service_type'];
      $inv['issued']    = $inv['issued_date'];
      $inv['due']       = $inv['due_date'];
      $inv['amount']    = (float)$inv['grand_total'];
      $inv['disc']      = (float)$inv['discount_pct'];
      $inv['discount_type'] = $inv['discount_type'] ?? 'percent';
      $inv['currency']  = $inv['currency'];
      $inv['template']  = (int)$inv['template_id'];
      $inv['subtotal']  = (float)$inv['subtotal'];
      $inv['bank']      = $inv['bank_details'] ?? '';
      $inv['tnc']       = $inv['terms'] ?? '';
      $inv['clientName']= $inv['client_name'] ?? '';
      if ($inv['pdf_options']) $inv['pdf_options'] = json_decode($inv['pdf_options'], true);
    }
    jsonResponse(['data' => $invoices]);

  // ── POST: create ────────────────────────────────────
  case 'POST':
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) { jsonResponse(['error'=>'Invalid JSON'], 400); }
    $userId = $_SESSION['user_id'];
    // Validate status against allowed values (guards against DB ENUM not yet migrated)
    $allowedStatuses = ['Draft','Pending','Paid','Overdue','Partial','Cancelled','Estimate'];
    $inputStatus = $input['status'] ?? 'Draft';
    if (!in_array($inputStatus, $allowedStatuses, true)) $inputStatus = 'Draft';
    $input['status'] = $inputStatus;
    // Auto-generate number if not provided
    if (empty($input['invoice_number'])) {
      $status = $input['status'] ?? 'Draft';
      if ($status === 'Estimate') {
        // Estimates use the configurable estimate prefix from settings (fallback: QT-YYYY-)
        $pfx = getSetting('estimate_prefix', 'QT-' . date('Y') . '-');
      } else {
        $pfx = getSetting('invoice_prefix', 'OT-' . date('Y') . '-');
      }
      // Find the highest existing numeric suffix for this prefix to avoid collisions
      $like = $pfx . '%';
      $row  = $db->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
      $row->execute([$like]);
      $last = $row->fetchColumn();
      if ($last) {
        // Extract trailing digits from last invoice number
        preg_match('/(\d+)$/', $last, $m);
        $cnt = isset($m[1]) ? ((int)$m[1] + 1) : 1;
      } else {
        // No invoices with this prefix yet — start at 1
        $cnt = 1;
      }
      $input['invoice_number'] = $pfx . str_pad($cnt, 3, '0', STR_PAD_LEFT);
      // Safety: if still collides (race condition), fall back to MAX id + 1
      try {
        $check = $db->prepare('SELECT id FROM invoices WHERE invoice_number = ?');
        $check->execute([$input['invoice_number']]);
        if ($check->fetch()) {
          $maxId = (int)$db->query('SELECT COALESCE(MAX(id),0)+1 FROM invoices')->fetchColumn();
          $input['invoice_number'] = $pfx . str_pad($maxId, 3, '0', STR_PAD_LEFT);
        }
      } catch (\Exception $e) { /* ignore safety check errors */ }
    }
    $stmt = $db->prepare('
      INSERT INTO invoices (invoice_number,client_id,client_name,service_type,issued_date,due_date,
        status,currency,subtotal,discount_pct,discount_type,discount_amt,gst_amount,grand_total,
        notes,bank_details,terms,company_logo,client_logo,signature,qr_code,
        template_id,generated_by,show_generated,pdf_options,created_by)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    try {
      $stmt->execute([
        $input['invoice_number'], nullIfEmpty($input['client_id']??null), $input['client_name']??'',
        $input['service_type']??'', nullIfEmpty($input['issued_date']??null), nullIfEmpty($input['due_date']??null),
        $input['status']??'Draft', $input['currency']??'₹',
        $input['subtotal']??0, $input['discount_pct']??0,
        in_array($input['discount_type']??'percent', ['percent','flat']) ? $input['discount_type'] : 'percent',
        $input['discount_amt']??0,
        $input['gst_amount']??0, $input['grand_total']??0,
        $input['notes']??'', $input['bank_details']??'', $input['terms']??'',
        $input['company_logo']??'', $input['client_logo']??'',
        $input['signature']??'', $input['qr_code']??'',
        $input['template_id']??1, $input['generated_by']??'OPTMS Tech Invoice Manager',
        $input['show_generated']??1,
        isset($input['pdf_options']) ? json_encode($input['pdf_options']) : null,
        (int)$userId
      ]);
    } catch (\PDOException $e) {
      error_log('Invoice INSERT error: ' . $e->getMessage());
      jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
    $invId = (int)$db->lastInsertId();
    // Insert items
    if (!empty($input['items'])) {
      $si = $db->prepare('INSERT INTO invoice_items (invoice_id,description,quantity,rate,gst_rate,line_total,sort_order) VALUES (?,?,?,?,?,?,?)');
      foreach ($input['items'] as $idx => $item) {
        $line = (float)($item['qty']??1) * (float)($item['rate']??0);
        $si->execute([$invId, $item['desc']??'', $item['qty']??1, $item['rate']??0, $item['gst']??18, $line, $idx]);
      }
    }
    logActivity((int)$userId, 'create', 'invoice', $invId, "Created invoice " . $input['invoice_number']);
    jsonResponse(['success'=>true, 'id'=>$invId, 'invoice_number'=>$input['invoice_number']]);

  // ── PUT: update ─────────────────────────────────────
  case 'PATCH':
    // Partial update — only update supplied fields (notes/bank/terms auto-save)
    $input = json_decode(file_get_contents('php://input'), true);
    $id    = (int)($_GET['id'] ?? $input['id'] ?? 0);
    if (!$id) { jsonResponse(['error'=>'ID required'], 400); }
    // Validate status if being updated edited here (guards against DB ENUM not yet migrated)
    $allowedStatuses = ['Draft','Pending','Paid','Overdue','Partial','Cancelled','Estimate'];
    if (isset($input['status']) && !in_array($input['status'], $allowedStatuses, true)) {
      $input['status'] = 'Draft';
    }
    $allowed = ['notes','bank_details','terms','status'];
    $sets=[]; $vals=[];
    foreach($allowed as $f) {
      if (array_key_exists($f, $input)) { $sets[]='`'.$f.'`=?'; $vals[]=$input[$f]; }
    }
    if (empty($sets)) { jsonResponse(['error'=>'Nothing to update'],400); }
    $vals[]=$id;
    $db->prepare('UPDATE invoices SET '.implode(',',$sets).' WHERE id=?')->execute($vals);
    jsonResponse(['success'=>true]);
    break;

  case 'PUT':
    $input = json_decode(file_get_contents('php://input'), true);
    $id    = (int)($_GET['id'] ?? $input['id'] ?? 0);
    if (!$id) { jsonResponse(['error'=>'ID required'], 400); }
    // Validate status
    $allowedStatuses = ['Draft','Pending','Paid','Overdue','Partial','Cancelled','Estimate'];
    if (isset($input['status']) && !in_array($input['status'], $allowedStatuses, true)) {
      $input['status'] = 'Draft';
    }
    $stmt = $db->prepare('
      UPDATE invoices SET client_id=?,client_name=?,service_type=?,issued_date=?,due_date=?,
        status=?,currency=?,subtotal=?,discount_pct=?,discount_type=?,discount_amt=?,gst_amount=?,grand_total=?,
        notes=?,bank_details=?,terms=?,company_logo=?,client_logo=?,signature=?,qr_code=?,
        template_id=?,generated_by=?,show_generated=?,pdf_options=?
      WHERE id=?');
    try {
      $stmt->execute([
        nullIfEmpty($input['client_id']??null), $input['client_name']??'', $input['service_type']??'',
        nullIfEmpty($input['issued_date']??null), nullIfEmpty($input['due_date']??null), $input['status']??'Draft',
        $input['currency']??'₹', $input['subtotal']??0, $input['discount_pct']??0,
        in_array($input['discount_type']??'percent', ['percent','flat']) ? $input['discount_type'] : 'percent',
        $input['discount_amt']??0, $input['gst_amount']??0, $input['grand_total']??0,
        $input['notes']??'', $input['bank_details']??'', $input['terms']??'',
        $input['company_logo']??'', $input['client_logo']??'',
        $input['signature']??'', $input['qr_code']??'',
        $input['template_id']??1, $input['generated_by']??'OPTMS Tech Invoice Manager',
        $input['show_generated']??1,
        isset($input['pdf_options']) ? json_encode($input['pdf_options']) : null,
        $id
      ]);
    } catch (\PDOException $e) {
      error_log('Invoice UPDATE error: ' . $e->getMessage());
      jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
    // Re-insert items
    $db->prepare('DELETE FROM invoice_items WHERE invoice_id=?')->execute([$id]);
    if (!empty($input['items'])) {
      $si = $db->prepare('INSERT INTO invoice_items (invoice_id,description,quantity,rate,gst_rate,line_total,sort_order) VALUES (?,?,?,?,?,?,?)');
      foreach ($input['items'] as $idx => $item) {
        $line = (float)($item['qty']??1) * (float)($item['rate']??0);
        $si->execute([$id, $item['desc']??'', $item['qty']??1, $item['rate']??0, $item['gst']??18, $line, $idx]);
      }
    }
    logActivity($_SESSION['user_id'], 'update', 'invoice', $id, "Updated invoice #$id");
    jsonResponse(['success'=>true]);

  // ── DELETE ──────────────────────────────────────────
  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { jsonResponse(['error'=>'ID required'], 400); }
    $db->prepare('DELETE FROM invoices WHERE id=?')->execute([$id]);
    logActivity($_SESSION['user_id'], 'delete', 'invoice', $id, "Deleted invoice #$id");
    jsonResponse(['success'=>true]);

  default:
    jsonResponse(['error'=>'Method not allowed'], 405);
}