<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB(); $method = $_SERVER['REQUEST_METHOD'];

// ── Auto-migrate: add new columns if missing ─────────────────────
$cols = $db->query("SHOW COLUMNS FROM clients")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('tags',           $cols)) $db->exec("ALTER TABLE clients ADD COLUMN `tags`           TEXT    NULL DEFAULT NULL");
if (!in_array('extra_contacts', $cols)) $db->exec("ALTER TABLE clients ADD COLUMN `extra_contacts` TEXT    NULL DEFAULT NULL");

switch ($method) {
  case 'GET':
    if (!empty($_GET['id'])) {
      $s = $db->prepare('SELECT * FROM clients WHERE id=?'); $s->execute([(int)$_GET['id']]);
      $c = $s->fetch(); if(!$c) jsonResponse(['error'=>'Not found'],404);
      jsonResponse(['data'=>normalizeClient($c)]);
    }
    $q = !empty($_GET['q']) ? '%'.$_GET['q'].'%' : null;
    if ($q) { $s=$db->prepare('SELECT * FROM clients WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY name'); $s->execute([$q,$q,$q]); }
    else    { $s=$db->query('SELECT * FROM clients ORDER BY name'); }
    $clients = array_map('normalizeClient', $s->fetchAll());
    jsonResponse(['data'=>$clients]);

  case 'POST':
    $d    = json_decode(file_get_contents('php://input'), true);
    $logo = $d['logo'] ?? $d['image'] ?? '';
    $i    = $db->prepare('INSERT INTO clients (name,person,email,phone,whatsapp,gst_number,address,landmark,color,logo,tags,extra_contacts) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $i->execute([
      $d['name']??'', $d['person']??'', $d['email']??'', $d['phone']??'',
      $d['wa']??$d['whatsapp']??'', $d['gst']??$d['gst_number']??'',
      $d['addr']??$d['address']??'', $d['landmark']??'',
      $d['color']??'#00897B', $logo,
      $d['tags']??'[]', $d['extra_contacts']??'[]'
    ]);
    $id = (int)$db->lastInsertId();
    logActivity((int)$_SESSION['user_id'],'create','client',$id,"Added client: ".($d['name']??''));
    jsonResponse(['success'=>true,'id'=>$id]);

  case 'PUT':
    $d        = json_decode(file_get_contents('php://input'), true);
    $id       = (int)($_GET['id'] ?? $d['id'] ?? 0); if(!$id) jsonResponse(['error'=>'ID required'],400);
    $isActive = isset($d['active']) ? (int)$d['active'] : 1;
    $logo     = $d['logo'] ?? $d['image'] ?? '';
    $u = $db->prepare('UPDATE clients SET name=?,person=?,email=?,phone=?,whatsapp=?,gst_number=?,address=?,landmark=?,color=?,logo=?,is_active=?,tags=?,extra_contacts=? WHERE id=?');
    $u->execute([
      $d['name']??'', $d['person']??'', $d['email']??'', $d['phone']??'',
      $d['wa']??$d['whatsapp']??'', $d['gst']??$d['gst_number']??'',
      $d['addr']??$d['address']??'', $d['landmark']??'',
      $d['color']??'#00897B', $logo, $isActive,
      $d['tags']??'[]', $d['extra_contacts']??'[]', $id
    ]);
    logActivity((int)$_SESSION['user_id'],'update','client',$id,"Updated client #$id");
    jsonResponse(['success'=>true]);

  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0); if(!$id) jsonResponse(['error'=>'ID required'],400);
    $db->prepare('DELETE FROM clients WHERE id=?')->execute([$id]);
    logActivity((int)$_SESSION['user_id'],'delete','client',$id,"Deleted client #$id");
    jsonResponse(['success'=>true]);

  default: jsonResponse(['error'=>'Method not allowed'],405);
}

function normalizeClient($c) {
  $logo = $c['logo'] ?? '';
  return [
    'id'             => (string)$c['id'],
    'name'           => $c['name']          ?? '',
    'person'         => $c['person']        ?? '',
    'email'          => $c['email']         ?? '',
    'phone'          => $c['phone']         ?? '',
    'wa'             => $c['whatsapp']      ?? '',
    'gst'            => $c['gst_number']    ?? '',
    'addr'           => $c['address']       ?? '',
    'landmark'       => $c['landmark']      ?? '',
    'color'          => $c['color']         ?? '#00897B',
    'image'          => (strpos($logo,'data:image')===0||strpos($logo,'http')===0) ? $logo : '',
    'active'         => isset($c['is_active']) ? (int)$c['is_active'] : 1,
    'tags'           => $c['tags']           ?? '[]',
    'extra_contacts' => $c['extra_contacts'] ?? '[]',
  ];
}