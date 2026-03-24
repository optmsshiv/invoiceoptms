<?php
// ═══════════════════════════════════════════════════════
//  API: /api/invoices.php — Invoices CRUD
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/../includes/auth.php';
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
    // Auto-generate number if not provided
    if (empty($input['invoice_number'])) {
      $cnt = $db->query('SELECT COUNT(*)+1 as n FROM invoices')->fetch()['n'];
      $pfx = getSetting('invoice_prefix', 'OT-' . date('Y') . '-');
      $input['invoice_number'] = $pfx . str_pad($cnt, 3, '0', STR_PAD_LEFT);
    }
    $stmt = $db->prepare('
      INSERT INTO invoices (invoice_number,client_id,client_name,service_type,issued_date,due_date,
        status,currency,subtotal,discount_pct,discount_amt,gst_amount,grand_total,
        notes,bank_details,terms,company_logo,client_logo,signature,qr_code,
        template_id,generated_by,show_generated,pdf_options,created_by)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
      $input['invoice_number'], $input['client_id']??null, $input['client_name']??'',
      $input['service_type']??'', $input['issued_date']??null, $input['due_date']??null,
      $input['status']??'Draft', $input['currency']??'₹',
      $input['subtotal']??0, $input['discount_pct']??0, $input['discount_amt']??0,
      $input['gst_amount']??0, $input['grand_total']??0,
      $input['notes']??'', $input['bank_details']??'', $input['terms']??'',
      $input['company_logo']??'', $input['client_logo']??'',
      $input['signature']??'', $input['qr_code']??'',
      $input['template_id']??1, $input['generated_by']??'OPTMS Tech Invoice Manager',
      $input['show_generated']??1,
      isset($input['pdf_options']) ? json_encode($input['pdf_options']) : null,
      $userId
    ]);
    $invId = (int)$db->lastInsertId();
    // Insert items
    if (!empty($input['items'])) {
      $si = $db->prepare('INSERT INTO invoice_items (invoice_id,description,quantity,rate,gst_rate,line_total,sort_order) VALUES (?,?,?,?,?,?,?)');
      foreach ($input['items'] as $idx => $item) {
        $line = (float)($item['qty']??1) * (float)($item['rate']??0);
        $si->execute([$invId, $item['desc']??'', $item['qty']??1, $item['rate']??0, $item['gst']??18, $line, $idx]);
      }
    }
    logActivity($userId, 'create', 'invoice', $invId, "Created invoice " . $input['invoice_number']);
    jsonResponse(['success'=>true, 'id'=>$invId, 'invoice_number'=>$input['invoice_number']]);

  // ── PUT: update ─────────────────────────────────────
  case 'PATCH':
    // Partial update — only update supplied fields (notes/bank/terms auto-save)
    $input = json_decode(file_get_contents('php://input'), true);
    $id    = (int)($_GET['id'] ?? $input['id'] ?? 0);
    if (!$id) { jsonResponse(['error'=>'ID required'], 400); }
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
    $stmt = $db->prepare('
      UPDATE invoices SET client_id=?,client_name=?,service_type=?,issued_date=?,due_date=?,
        status=?,currency=?,subtotal=?,discount_pct=?,discount_amt=?,gst_amount=?,grand_total=?,
        notes=?,bank_details=?,terms=?,company_logo=?,client_logo=?,signature=?,qr_code=?,
        template_id=?,generated_by=?,show_generated=?,pdf_options=?
      WHERE id=?');
    $stmt->execute([
      $input['client_id']??null, $input['client_name']??'', $input['service_type']??'',
      $input['issued_date']??null, $input['due_date']??null, $input['status']??'Draft',
      $input['currency']??'₹', $input['subtotal']??0, $input['discount_pct']??0,
      $input['discount_amt']??0, $input['gst_amount']??0, $input['grand_total']??0,
      $input['notes']??'', $input['bank_details']??'', $input['terms']??'',
      $input['company_logo']??'', $input['client_logo']??'',
      $input['signature']??'', $input['qr_code']??'',
      $input['template_id']??1, $input['generated_by']??'OPTMS Tech Invoice Manager',
      $input['show_generated']??1,
      isset($input['pdf_options']) ? json_encode($input['pdf_options']) : null,
      $id
    ]);
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
