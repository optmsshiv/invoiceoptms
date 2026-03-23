<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB(); $method = $_SERVER['REQUEST_METHOD'];
// Auto-migrate: add Partial status if not exists
try {
  $db->exec("ALTER TABLE invoices MODIFY COLUMN status ENUM('Draft','Pending','Partial','Paid','Overdue','Cancelled') DEFAULT 'Draft'");
} catch(Exception $e) { /* already migrated */ }

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
    // If marking invoice paid, update invoice status
    if (!empty($d['invoice_id'])) {
      $partial = !empty($d['partial']) && $d['partial'] == 1;
      if (!$partial) {
        $db->prepare("UPDATE invoices SET status='Paid' WHERE id=?")->execute([$d['invoice_id']]);
      } else {
        // Partial payment — mark as 'Partial' so PDF shows remaining amount
        $remainAmt = floatval($d['remaining_amt'] ?? 0);
        $db->prepare("UPDATE invoices SET status='Partial' WHERE id=?")->execute([$d['invoice_id']]);
        // Note is stored in the payment record itself, NOT appended to invoice notes
      }
    }
    $s=$db->prepare('INSERT INTO payments (invoice_id,invoice_number,client_name,amount,payment_date,method,transaction_id,status,notes) VALUES (?,?,?,?,?,?,?,?,?)');
    $s->execute([$d['invoice_id']??null,$d['invoice_number']??$d['inv']??'',$d['client_name']??$d['client']??'',$d['amount']??0,$d['payment_date']??$d['date']??date('Y-m-d'),$d['method']??'',$d['transaction_id']??$d['txn']??'',$d['status']??'Success',$d['notes']??'']);
    $id=(int)$db->lastInsertId();
    logActivity($_SESSION['user_id'],'create','payment',$id,"Recorded payment: ".($d['invoice_number']??$d['inv']??''));
    jsonResponse(['success'=>true,'id'=>$id]);

  case 'DELETE':
    $id=(int)($_GET['id']??0); if(!$id) jsonResponse(['error'=>'ID required'],400);
    $db->prepare('DELETE FROM payments WHERE id=?')->execute([$id]);
    jsonResponse(['success'=>true]);

  default: jsonResponse(['error'=>'Method not allowed'],405);
}
