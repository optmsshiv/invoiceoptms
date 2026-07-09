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
    $status = $_GET['status'] ?? 'active';
    $stmt = $db->prepare('SELECT * FROM products WHERE status = ? ORDER BY name ASC');
    $stmt->execute([$status]);
    $rows = $stmt->fetchAll();
    // Frontend expects product ids as a "p"-prefixed string (e.g. "p12") —
    // editProduct/deleteProduct/restoreProduct all strip a literal 'p' before calling this API.
    foreach ($rows as &$r) { $r['id'] = 'p' . $r['id']; }
    unset($r);
    jsonResponse(['data' => $rows]);
    break;

  case 'POST':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    // Restore from archive
    if (($_GET['action'] ?? '') === 'restore' && !empty($_GET['id'])) {
      $stmt = $db->prepare('UPDATE products SET status = "active" WHERE id = ?');
      $stmt->execute([(int)$_GET['id']]);
      logActivity((int)$_SESSION['user_id'], 'restore', 'product', (int)$_GET['id'], 'Product restored');
      jsonResponse(['success' => true]);
      break;
    }

    if (empty($d['name'])) jsonResponse(['error' => 'Service/product name is required'], 400);

    $stmt = $db->prepare('INSERT INTO products (name, category, rate, hsn, gst, unit_family) VALUES (?,?,?,?,?,?)');
    $stmt->execute([
      $d['name'],
      $d['category'] ?? 'Other',
      (float)($d['rate'] ?? 0),
      $d['hsn'] ?? '',
      (int)($d['gst'] ?? 18),
      in_array($d['unit_family'] ?? '', ['count','weight','volume']) ? $d['unit_family'] : 'count',
    ]);
    $id = $db->lastInsertId();
    logActivity((int)$_SESSION['user_id'], 'create', 'product', (int)$id, 'Product added: ' . $d['name']);
    jsonResponse(['success' => true, 'id' => $id]);
    break;

  case 'PUT':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $stmt = $db->prepare('UPDATE products SET name=?, category=?, rate=?, hsn=?, gst=?, unit_family=? WHERE id=?');
    $stmt->execute([
      $d['name'] ?? '',
      $d['category'] ?? 'Other',
      (float)($d['rate'] ?? 0),
      $d['hsn'] ?? '',
      (int)($d['gst'] ?? 18),
      in_array($d['unit_family'] ?? '', ['count','weight','volume']) ? $d['unit_family'] : 'count',
      $id,
    ]);
    logActivity((int)$_SESSION['user_id'], 'update', 'product', $id, 'Product updated');
    jsonResponse(['success' => true]);
    break;

  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    // Soft delete (archive) — never hard-delete, purchases/invoices may reference this product
    $stmt = $db->prepare('UPDATE products SET status = "archived" WHERE id = ?');
    $stmt->execute([$id]);
    logActivity((int)$_SESSION['user_id'], 'archive', 'product', $id, 'Product archived');
    jsonResponse(['success' => true]);
    break;

  default:
    jsonResponse(['error' => 'Method not allowed'], 405);
}
} catch (Throwable $e) {
  jsonResponse(['error' => 'Products API error: ' . $e->getMessage()], 500);
}
