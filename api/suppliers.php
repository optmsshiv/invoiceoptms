<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
  case 'GET':
    $status = $_GET['status'] ?? 'active';
    $stmt = $db->prepare('SELECT * FROM suppliers WHERE status = ? ORDER BY name ASC');
    $stmt->execute([$status]);
    jsonResponse(['data' => $stmt->fetchAll()]);
    break;

  case 'POST':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    // Restore from archive
    if (($_GET['action'] ?? '') === 'restore' && !empty($_GET['id'])) {
      $stmt = $db->prepare('UPDATE suppliers SET status = "active" WHERE id = ?');
      $stmt->execute([(int)$_GET['id']]);
      logActivity((int)$_SESSION['user_id'], 'restore', 'supplier', (int)$_GET['id'], 'Supplier restored');
      jsonResponse(['success' => true]);
      break;
    }

    if (empty($d['name'])) jsonResponse(['error' => 'Supplier name is required'], 400);

    $stmt = $db->prepare('INSERT INTO suppliers
      (name, contact_person, phone, email, gst_number, country, address, payment_terms, opening_balance, notes)
      VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
      $d['name'],
      $d['contact_person'] ?? '',
      $d['phone'] ?? '',
      $d['email'] ?? '',
      $d['gst_number'] ?? '',
      $d['country'] ?? 'India',
      $d['address'] ?? '',
      $d['payment_terms'] ?? '',
      $d['opening_balance'] ?? 0,
      $d['notes'] ?? '',
    ]);
    $id = $db->lastInsertId();
    logActivity((int)$_SESSION['user_id'], 'create', 'supplier', (int)$id, 'Supplier added: ' . $d['name']);
    jsonResponse(['success' => true, 'id' => $id]);
    break;

  case 'PUT':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $stmt = $db->prepare('UPDATE suppliers SET
      name=?, contact_person=?, phone=?, email=?, gst_number=?, country=?, address=?, payment_terms=?, opening_balance=?, notes=?
      WHERE id=?');
    $stmt->execute([
      $d['name'] ?? '',
      $d['contact_person'] ?? '',
      $d['phone'] ?? '',
      $d['email'] ?? '',
      $d['gst_number'] ?? '',
      $d['country'] ?? 'India',
      $d['address'] ?? '',
      $d['payment_terms'] ?? '',
      $d['opening_balance'] ?? 0,
      $d['notes'] ?? '',
      $id,
    ]);
    logActivity((int)$_SESSION['user_id'], 'update', 'supplier', $id, 'Supplier updated');
    jsonResponse(['success' => true]);
    break;

  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    // Soft delete (archive) — never hard-delete, purchases may reference this supplier
    $stmt = $db->prepare('UPDATE suppliers SET status = "archived" WHERE id = ?');
    $stmt->execute([$id]);
    logActivity((int)$_SESSION['user_id'], 'archive', 'supplier', $id, 'Supplier archived');
    jsonResponse(['success' => true]);
    break;

  default:
    jsonResponse(['error' => 'Method not allowed'], 405);
}
