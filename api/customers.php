<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
switch ($method) {
  case 'GET':
    // Customer ledger summary for the Sales page sidebar:
    // total sales (YTD), last invoice, outstanding balance, average payment days.
    if (!empty($_GET['summary_for'])) {
      $cid = (int)$_GET['summary_for'];
      $ytdStmt = $db->prepare('SELECT COALESCE(SUM(total),0) AS total_sales FROM sales WHERE customer_id = ? AND YEAR(sale_date) = YEAR(CURDATE()) AND status != "Cancelled"');
      $ytdStmt->execute([$cid]);
      $ytd = $ytdStmt->fetch()['total_sales'];

      $lastStmt = $db->prepare('SELECT invoice_no, sale_date FROM sales WHERE customer_id = ? AND status != "Cancelled" ORDER BY sale_date DESC, id DESC LIMIT 1');
      $lastStmt->execute([$cid]);
      $last = $lastStmt->fetch();

      $outStmt = $db->prepare('SELECT COALESCE(SUM(total - amount_received),0) AS outstanding FROM sales WHERE customer_id = ? AND status != "Cancelled"');
      $outStmt->execute([$cid]);
      $outstanding = $outStmt->fetch()['outstanding'];

      // Average days between sale_date and payment_date for paid invoices
      $avgStmt = $db->prepare('SELECT AVG(DATEDIFF(payment_date, sale_date)) AS avg_days FROM sales WHERE customer_id = ? AND payment_date IS NOT NULL AND status != "Cancelled"');
      $avgStmt->execute([$cid]);
      $avgDays = $avgStmt->fetch()['avg_days'];

      jsonResponse(['data' => [
        'total_sales_ytd' => (float)$ytd,
        'outstanding' => (float)$outstanding,
        'last_invoice_no' => $last['invoice_no'] ?? null,
        'last_invoice_date' => $last['sale_date'] ?? null,
        'avg_payment_days' => $avgDays !== null ? round((float)$avgDays) : null,
      ]]);
      break;
    }

    $status = $_GET['status'] ?? 'active';
    $stmt = $db->prepare('SELECT * FROM customers WHERE status = ? ORDER BY name ASC');
    $stmt->execute([$status]);
    jsonResponse(['data' => $stmt->fetchAll()]);
    break;

  case 'POST':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    if (($_GET['action'] ?? '') === 'restore' && !empty($_GET['id'])) {
      $stmt = $db->prepare('UPDATE customers SET status = "active" WHERE id = ?');
      $stmt->execute([(int)$_GET['id']]);
      logActivity((int)$_SESSION['user_id'], 'restore', 'customer', (int)$_GET['id'], 'Customer restored');
      jsonResponse(['success' => true]);
      break;
    }

    if (empty($d['name'])) jsonResponse(['error' => 'Customer name is required'], 400);

    $stmt = $db->prepare('INSERT INTO customers
      (name, customer_type, mobile, email, gstin, state, district, billing_address, shipping_address,
       credit_limit, payment_terms, sales_executive, notes)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
      $d['name'], $d['customer_type'] ?? 'Domestic', $d['mobile'] ?? '', $d['email'] ?? '', $d['gstin'] ?? '',
      $d['state'] ?? '', $d['district'] ?? '', $d['billing_address'] ?? '', $d['shipping_address'] ?? '',
      (float)($d['credit_limit'] ?? 0), $d['payment_terms'] ?? '', $d['sales_executive'] ?? '', $d['notes'] ?? '',
    ]);
    $id = $db->lastInsertId();
    logActivity((int)$_SESSION['user_id'], 'create', 'customer', (int)$id, 'Customer added: ' . $d['name']);
    jsonResponse(['success' => true, 'id' => $id]);
    break;

  case 'PUT':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $stmt = $db->prepare('UPDATE customers SET
      name=?, customer_type=?, mobile=?, email=?, gstin=?, state=?, district=?, billing_address=?, shipping_address=?,
      credit_limit=?, payment_terms=?, sales_executive=?, notes=?
      WHERE id=?');
    $stmt->execute([
      $d['name'] ?? '', $d['customer_type'] ?? 'Domestic', $d['mobile'] ?? '', $d['email'] ?? '', $d['gstin'] ?? '',
      $d['state'] ?? '', $d['district'] ?? '', $d['billing_address'] ?? '', $d['shipping_address'] ?? '',
      (float)($d['credit_limit'] ?? 0), $d['payment_terms'] ?? '', $d['sales_executive'] ?? '', $d['notes'] ?? '',
      $id,
    ]);
    logActivity((int)$_SESSION['user_id'], 'update', 'customer', $id, 'Customer updated');
    jsonResponse(['success' => true]);
    break;

  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $stmt = $db->prepare('UPDATE customers SET status = "archived" WHERE id = ?');
    $stmt->execute([$id]);
    logActivity((int)$_SESSION['user_id'], 'archive', 'customer', $id, 'Customer archived');
    jsonResponse(['success' => true]);
    break;

  default:
    jsonResponse(['error' => 'Method not allowed'], 405);
}
} catch (Throwable $e) {
  jsonResponse(['error' => 'Customers API error: ' . $e->getMessage()], 500);
}
