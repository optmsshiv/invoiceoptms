<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB(); $method = $_SERVER['REQUEST_METHOD'];
// Auto-migrate: add Partial status if not exists
try {
  $db->exec("ALTER TABLE invoices MODIFY COLUMN status ENUM('Draft','Pending','Partial','Paid','Overdue','Cancelled') DEFAULT 'Draft'");
} catch(Exception $e) { /* already migrated */ }

function nullIfEmpty($v) { return ($v === '' || $v === null) ? null : $v; }

switch ($method) {
  case 'GET':
    $where=['1=1']; $params=[];
    if (!empty($_GET['invoice_id'])) { $where[]='p.invoice_id=?'; $params[]=(int)$_GET['invoice_id']; }
    if (!empty($_GET['from']))       { $where[]='p.payment_date>=?'; $params[]=$_GET['from']; }
    if (!empty($_GET['to']))         { $where[]='p.payment_date<=?'; $params[]=$_GET['to']; }
    if (!empty($_GET['method']))     { $where[]='p.method=?'; $params[]=$_GET['method']; }
    if (!empty($_GET['q'])) {
      $q='%'.$_GET['q'].'%';
      $where[]='(p.invoice_number LIKE ? OR p.client_name LIKE ? OR p.transaction_id LIKE ?)';
      array_push($params,$q,$q,$q);
    }
    $sql='SELECT p.* FROM payments p WHERE '.implode(' AND ',$where).' ORDER BY p.payment_date DESC, p.created_at DESC';
    $s=$db->prepare($sql); $s->execute($params);
    $payments=$s->fetchAll();
    foreach ($payments as &$p) {
      $p['amount']    =(float)$p['amount'];
      $p['invoice_id']=(int)$p['invoice_id'];
      $p['inv']       =$p['invoice_number'];
      $p['client']    =$p['client_name'];
      $p['method']    =$p['method'];
      $p['txn']       =$p['transaction_id'];
      $p['date']      =$p['payment_date'];
    }
    jsonResponse(['data'=>$payments]);

  case 'POST':
    $d=json_decode(file_get_contents('php://input'),true);
    // If marking invoice paid, update invoice status based on cumulative payments
    if (!empty($d['invoice_id'])) {
      $invoiceId  = (int)$d['invoice_id'];
      $partial    = !empty($d['partial']) && $d['partial'] == 1;
      // Sum all previous payments for this invoice
      $prevStmt   = $db->prepare('SELECT COALESCE(SUM(amount),0) as paid FROM payments WHERE invoice_id = ?');
      $prevStmt->execute([$invoiceId]);
      $prevPaid   = (float)$prevStmt->fetchColumn();
      $thisAmt    = floatval($d['amount'] ?? 0);
      $cumulative = $prevPaid + $thisAmt;
      // Get invoice grand_total to compare
      $invStmt    = $db->prepare('SELECT grand_total FROM invoices WHERE id = ?');
      $invStmt->execute([$invoiceId]);
      $grandTotal = (float)$invStmt->fetchColumn();
      // If cumulative >= grand_total → fully paid regardless of partial flag
      if ($grandTotal > 0 && ($grandTotal - $cumulative) <= 0.01) {
        $db->prepare("UPDATE invoices SET status='Paid' WHERE id=?")->execute([$invoiceId]);
      } elseif ($partial) {
        $db->prepare("UPDATE invoices SET status='Partial' WHERE id=?")->execute([$invoiceId]);
      } else {
        $db->prepare("UPDATE invoices SET status='Paid' WHERE id=?")->execute([$invoiceId]);
      }
    }
    $s=$db->prepare('INSERT INTO payments (invoice_id,invoice_number,client_name,amount,payment_date,method,transaction_id,status,notes) VALUES (?,?,?,?,?,?,?,?,?)');
    try {
      $s->execute([
        nullIfEmpty($d['invoice_id']??null),
        $d['invoice_number']??$d['inv']??'',
        $d['client_name']??$d['client']??'',
        $d['amount']??0,
        nullIfEmpty($d['payment_date']??$d['date']??date('Y-m-d')),
        $d['method']??'',
        $d['transaction_id']??$d['txn']??'',
        $d['status']??'Success',
        $d['notes']??''
      ]);
    } catch (\PDOException $e) {
      error_log('Payment INSERT error: ' . $e->getMessage());
      jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
    $id=(int)$db->lastInsertId();
    logActivity((int)$_SESSION['user_id'],'create','payment',$id,"Recorded payment: ".($d['invoice_number']??$d['inv']??''));
    jsonResponse(['success'=>true,'id'=>$id]);

  case 'DELETE':
    $id=(int)($_GET['id']??0); if(!$id) jsonResponse(['error'=>'ID required'],400);
    $db->prepare('DELETE FROM payments WHERE id=?')->execute([$id]);
    jsonResponse(['success'=>true]);

  default: jsonResponse(['error'=>'Method not allowed'],405);
}
