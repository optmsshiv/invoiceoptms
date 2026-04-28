<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB(); $method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
  case 'GET':
    if (!empty($_GET['id'])) {
      $s = $db->prepare('SELECT * FROM clients WHERE id=?'); $s->execute([(int)$_GET['id']]);
      $c = $s->fetch(); if(!$c) jsonResponse(['error'=>'Not found'],404);
      jsonResponse(['data'=>$c]);
    }
    // Return ALL clients (active + inactive) — frontend handles display filtering
    $q = !empty($_GET['q']) ? '%'.$_GET['q'].'%' : null;
    if ($q) { $s=$db->prepare('SELECT * FROM clients WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY name'); $s->execute([$q,$q,$q]); }
    else    { $s=$db->query('SELECT * FROM clients ORDER BY name'); }
    $clients = $s->fetchAll();
    foreach ($clients as &$c) {
      $c['id']       = (string)$c['id'];
      $c['person']   = $c['person']     ?? '';
      $c['wa']       = $c['whatsapp']   ?? '';
      $c['gst']      = $c['gst_number'] ?? '';
      $c['addr']     = $c['address']    ?? '';
      $c['landmark'] = $c['landmark']   ?? '';
      $c['image']    = !empty($c['logo']) ? $c['logo'] : '';
      $c['color']    = $c['color']      ?? '#00897B';
      $c['active']   = isset($c['is_active']) ? (int)$c['is_active'] : 1;
    }
    jsonResponse(['data'=>$clients]);

  case 'POST':
    $d    = json_decode(file_get_contents('php://input'), true);
    $logo = $d['logo'] ?? $d['image'] ?? '';
    $i    = $db->prepare('INSERT INTO clients (name,person,email,phone,whatsapp,gst_number,address,landmark,color,logo) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $i->execute([$d['name']??'',$d['person']??'',$d['email']??'',$d['phone']??'',$d['wa']??$d['whatsapp']??'',$d['gst']??$d['gst_number']??'',$d['addr']??$d['address']??'',$d['landmark']??'',$d['color']??'#00897B',$logo]);
    $id = (int)$db->lastInsertId();
    logActivity((int)$_SESSION['user_id'],'create','client',$id,"Added client: ".($d['name']??''));
    jsonResponse(['success'=>true,'id'=>$id]);

  case 'PUT':
    $d        = json_decode(file_get_contents('php://input'), true);
    $id       = (int)($_GET['id'] ?? $d['id'] ?? 0); if(!$id) jsonResponse(['error'=>'ID required'],400);
    $isActive = isset($d['active']) ? (int)$d['active'] : 1;
    $logo     = $d['logo'] ?? $d['image'] ?? '';
    $u = $db->prepare('UPDATE clients SET name=?,person=?,email=?,phone=?,whatsapp=?,gst_number=?,address=?,landmark=?,color=?,logo=?,is_active=? WHERE id=?');
    $u->execute([$d['name']??'',$d['person']??'',$d['email']??'',$d['phone']??'',$d['wa']??$d['whatsapp']??'',$d['gst']??$d['gst_number']??'',$d['addr']??$d['address']??'',$d['landmark']??'',$d['color']??'#00897B',$logo,$isActive,$id]);
    logActivity((int)$_SESSION['user_id'],'update','client',$id,"Updated client #$id");
    jsonResponse(['success'=>true]);

  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0); if(!$id) jsonResponse(['error'=>'ID required'],400);
    $db->prepare('DELETE FROM clients WHERE id=?')->execute([$id]);
    logActivity((int)$_SESSION['user_id'],'delete','client',$id,"Deleted client #$id");
    jsonResponse(['success'=>true]);

  default: jsonResponse(['error'=>'Method not allowed'],405);
}