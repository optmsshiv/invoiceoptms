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
    $q = !empty($_GET['q']) ? '%'.$_GET['q'].'%' : null;
    if ($q) { $s=$db->prepare('SELECT * FROM clients WHERE is_active=1 AND (name LIKE ? OR email LIKE ? OR phone LIKE ?) ORDER BY name'); $s->execute([$q,$q,$q]); }
    else    { $s=$db->query('SELECT * FROM clients WHERE is_active=1 ORDER BY name'); }
    $clients = $s->fetchAll();
    // Remap for frontend compatibility
    foreach ($clients as &$c) {
      $c['id']       = (string)$c['id'];
      $c['person']   = $c['person']??'';
      $c['wa']       = $c['whatsapp']??'';
      $c['gst']      = $c['gst_number']??'';
      $c['addr']     = $c['address']??'';
      $c['landmark'] = $c['landmark']??'';
      $c['image']    = $c['logo']??'';
    }
    jsonResponse(['data'=>$clients]);

  case 'POST':
    $i=$db->prepare('INSERT INTO clients (name,person,email,phone,whatsapp,gst_number,address,landmark,color,logo) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $d=json_decode(file_get_contents('php://input'),true);
    $i->execute([$d['name']??'',$d['person']??'',$d['email']??'',$d['phone']??'',$d['wa']??$d['whatsapp']??'',$d['gst']??$d['gst_number']??'',$d['addr']??$d['address']??'',$d['landmark']??'',$d['color']??'#00897B',$d['logo']??$d['image']??'']);
    $id=(int)$db->lastInsertId();
    logActivity((int)$_SESSION['user_id'],'create','client',$id,"Added client: ".($d['name']??''));
    jsonResponse(['success'=>true,'id'=>$id]);

  case 'PUT':
    $d=json_decode(file_get_contents('php://input'),true);
    $id=(int)($_GET['id']??$d['id']??0); if(!$id) jsonResponse(['error'=>'ID required'],400);
    $u=$db->prepare('UPDATE clients SET name=?,person=?,email=?,phone=?,whatsapp=?,gst_number=?,address=?,landmark=?,color=?,logo=? WHERE id=?');
    $u->execute([$d['name']??'',$d['person']??'',$d['email']??'',$d['phone']??'',$d['wa']??$d['whatsapp']??'',$d['gst']??$d['gst_number']??'',$d['addr']??$d['address']??'',$d['landmark']??'',$d['color']??'#00897B',$d['logo']??$d['image']??'',$id]);
    logActivity((int)$_SESSION['user_id'],'update','client',$id,"Updated client #$id");
    jsonResponse(['success'=>true]);

  case 'DELETE':
    $id=(int)($_GET['id']??0); if(!$id) jsonResponse(['error'=>'ID required'],400);
    $db->prepare('UPDATE clients SET is_active=0 WHERE id=?')->execute([$id]);
    logActivity((int)$_SESSION['user_id'],'delete','client',$id,"Deleted client #$id");
    jsonResponse(['success'=>true]);

  default: jsonResponse(['error'=>'Method not allowed'],405);
}