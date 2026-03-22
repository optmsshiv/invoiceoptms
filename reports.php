<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();

$from  = $_GET['from']  ?? date('Y-m-01');
$to    = $_GET['to']    ?? date('Y-m-t');
$group = $_GET['group'] ?? 'month'; // month | week | service | client

// Summary stats
$stats = $db->prepare('
  SELECT
    COUNT(*) as total_invoices,
    SUM(grand_total) as total_revenue,
    SUM(CASE WHEN status="Paid" THEN grand_total ELSE 0 END) as paid_revenue,
    SUM(CASE WHEN status="Pending" THEN grand_total ELSE 0 END) as pending_revenue,
    SUM(CASE WHEN status="Overdue" THEN grand_total ELSE 0 END) as overdue_revenue,
    COUNT(CASE WHEN status="Paid" THEN 1 END) as paid_count,
    COUNT(CASE WHEN status="Pending" THEN 1 END) as pending_count,
    COUNT(CASE WHEN status="Overdue" THEN 1 END) as overdue_count
  FROM invoices WHERE issued_date BETWEEN ? AND ?');
$stats->execute([$from, $to]);
$summary = $stats->fetch();

// Revenue by service
$svc = $db->prepare('
  SELECT service_type as service, SUM(grand_total) as revenue, COUNT(*) as count
  FROM invoices WHERE issued_date BETWEEN ? AND ? AND status="Paid"
  GROUP BY service_type ORDER BY revenue DESC');
$svc->execute([$from, $to]);
$byService = $svc->fetchAll();

// Monthly trend (current year)
$trend = $db->prepare('
  SELECT DATE_FORMAT(issued_date,"%Y-%m") as month,
         SUM(grand_total) as revenue,
         SUM(CASE WHEN status="Paid" THEN grand_total ELSE 0 END) as paid
  FROM invoices WHERE YEAR(issued_date) = YEAR(?)
  GROUP BY month ORDER BY month');
$trend->execute([$from]);
$monthly = $trend->fetchAll();

// Top clients
$top = $db->prepare('
  SELECT client_name, client_id, SUM(grand_total) as revenue, COUNT(*) as invoices
  FROM invoices WHERE issued_date BETWEEN ? AND ? AND status="Paid"
  GROUP BY client_id, client_name ORDER BY revenue DESC LIMIT 5');
$top->execute([$from, $to]);
$topClients = $top->fetchAll();

// Transactions list
$txn = $db->prepare('
  SELECT i.id, i.invoice_number as num, i.client_name,
         c.color as client_color,
         i.service_type as service, i.issued_date as issued,
         i.due_date as due, i.grand_total as amount, i.status
  FROM invoices i
  LEFT JOIN clients c ON c.id = i.client_id
  WHERE i.issued_date BETWEEN ? AND ?
  ORDER BY i.issued_date DESC');
$txn->execute([$from, $to]);
$transactions = $txn->fetchAll();
foreach ($transactions as &$t) { $t['amount'] = (float)$t['amount']; }

jsonResponse([
  'data' => [
    'summary'      => $summary,
    'by_service'   => $byService,
    'monthly_trend'=> $monthly,
    'top_clients'  => $topClients,
    'transactions' => $transactions,
    'period'       => ['from'=>$from,'to'=>$to]
  ]
]);
