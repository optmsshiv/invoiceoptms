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
    $limit = (int)($_GET['limit'] ?? 0);
    $sql = 'SELECT * FROM payment_vouchers ORDER BY payment_date DESC, id DESC';
    if ($limit > 0) $sql .= ' LIMIT ' . $limit;
    jsonResponse(['data' => $db->query($sql)->fetchAll()]);
    break;

  case 'POST':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);
    if (empty($d['party_name'])) jsonResponse(['error' => 'Party name is required'], 400);
    if (empty($d['payment_date'])) jsonResponse(['error' => 'Payment date is required'], 400);
    if (empty($d['amount']) || (float)$d['amount'] <= 0) jsonResponse(['error' => 'Amount must be greater than 0'], 400);

    $refNo = trim($d['reference_no'] ?? '');
    if ($refNo === '') {
      $y = date('y'); $y2 = date('y', strtotime('+1 year'));
      $cnt = $db->query('SELECT COUNT(*) c FROM payment_vouchers')->fetch()['c'] + 1;
      $refNo = "PAY/{$y}-{$y2}/" . str_pad($cnt, 4, '0', STR_PAD_LEFT);
    }

    $stmt = $db->prepare('INSERT INTO payment_vouchers
      (reference_no, payment_date, direction, party_type, party_name, payment_for, payment_mode, amount, status, notes, created_by)
      VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
      $refNo, $d['payment_date'], $d['direction'] ?? 'out', $d['party_type'] ?? 'Vendor', $d['party_name'],
      $d['payment_for'] ?? '', $d['payment_mode'] ?? 'Cash', (float)$d['amount'], $d['status'] ?? 'Paid',
      $d['notes'] ?? '', (int)($_SESSION['user_id'] ?? 0),
    ]);
    $id = (int)$db->lastInsertId();
    logActivity((int)($_SESSION['user_id'] ?? 0), 'create', 'payment_voucher', $id, 'Payment recorded: ' . $refNo);
    jsonResponse(['success' => true, 'id' => $id, 'reference_no' => $refNo]);
    break;

  case 'PUT':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);
    $db->prepare('UPDATE payment_vouchers SET status=? WHERE id=?')->execute([$d['status'] ?? 'Paid', $id]);
    jsonResponse(['success' => true]);
    break;

  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $db->prepare('DELETE FROM payment_vouchers WHERE id = ?')->execute([$id]);
    logActivity((int)($_SESSION['user_id'] ?? 0), 'delete', 'payment_voucher', $id, 'Payment voucher deleted');
    jsonResponse(['success' => true]);
    break;

  default:
    jsonResponse(['error' => 'Method not allowed'], 405);
}
} catch (Throwable $e) {
  jsonResponse(['error' => 'Payment Vouchers API error: ' . $e->getMessage()], 500);
}
