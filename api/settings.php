<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB(); $method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
  case 'GET':
    $rows = $db->query('SELECT `key`, value FROM settings')->fetchAll();
    $out  = [];
    foreach ($rows as $r) $out[$r['key']] = $r['value'];
    jsonResponse(['data'=>$out]);

  case 'POST':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error'=>'Invalid JSON'], 400);
    $stmt = $db->prepare('INSERT INTO settings (`key`, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?');
    foreach ($d as $key => $val) {
      $stmt->execute([$key, $val, $val]);
    }
    logActivity((int)$_SESSION['user_id'], 'update', 'settings', 0, 'Company settings updated');
    jsonResponse(['success'=>true]);

  default: jsonResponse(['error'=>'Method not allowed'], 405);
}
