<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB(); $method = $_SERVER['REQUEST_METHOD'];

// Restore a soft-deleted service. Kept separate from PUT so restoring never
// overwrites name/rate/etc with blanks — it only flips is_active back on.
if ($method === 'POST' && ($_GET['action'] ?? '') === 'restore') {
  $id = (int)str_replace('p','',($_GET['id']??0)); if(!$id) jsonResponse(['error'=>'ID required'],400);
  $db->prepare('UPDATE products SET is_active=1 WHERE id=?')->execute([$id]);
  logActivity((int)$_SESSION['user_id'],'restore','product',$id,"Restored product #$id");
  jsonResponse(['success'=>true]);
}

switch ($method) {
  case 'GET':
    if (!empty($_GET['id'])) {
      $s=$db->prepare('SELECT * FROM products WHERE id=?'); $s->execute([(int)$_GET['id']]);
      $p=$s->fetch(); if(!$p) jsonResponse(['error'=>'Not found'],404);
      jsonResponse(['data'=>$p]);
    }
    $activeFlag = (($_GET['status'] ?? '') === 'archived') ? 0 : 1;
    $q = !empty($_GET['q']) ? '%'.$_GET['q'].'%' : null;
    $cat = $_GET['category']??'';
    if ($q && $cat) {
      $s=$db->prepare('SELECT * FROM products WHERE is_active=? AND (name LIKE ? OR category LIKE ? OR hsn_code LIKE ?) AND category=? ORDER BY name');
      $s->execute([$activeFlag,$q,$q,$q,$cat]);
    } elseif ($q) {
      $s=$db->prepare('SELECT * FROM products WHERE is_active=? AND (name LIKE ? OR category LIKE ? OR hsn_code LIKE ?) ORDER BY name');
      $s->execute([$activeFlag,$q,$q,$q]);
    } elseif ($cat) {
      $s=$db->prepare('SELECT * FROM products WHERE is_active=? AND category=? ORDER BY name');
      $s->execute([$activeFlag,$cat]);
    } else {
      $s=$db->prepare('SELECT * FROM products WHERE is_active=? ORDER BY category,name');
      $s->execute([$activeFlag]);
    }
    $products = $s->fetchAll();
    foreach ($products as &$p) {
      $p['id']   = 'p'.$p['id'];
      $p['rate'] = (float)$p['rate'];
      $p['gst']  = (float)$p['gst_rate'];
      $p['hsn']  = $p['hsn_code'];
    }
    jsonResponse(['data'=>$products]);

  case 'POST':
    $d=json_decode(file_get_contents('php://input'),true);
    $s=$db->prepare('INSERT INTO products (name,category,rate,hsn_code,gst_rate,description) VALUES (?,?,?,?,?,?)');
    $s->execute([$d['name']??'',$d['category']??'Other',$d['rate']??0,$d['hsn']??$d['hsn_code']??'998314',$d['gst']??$d['gst_rate']??18,$d['description']??'']);
    $id=(int)$db->lastInsertId();
    logActivity((int)$_SESSION['user_id'],'create','product',$id,"Added product: ".($d['name']??''));
    jsonResponse(['success'=>true,'id'=>$id]);

  case 'PUT':
    $d=json_decode(file_get_contents('php://input'),true);
    $id=(int)str_replace('p','',($_GET['id']??$d['id']??0)); if(!$id) jsonResponse(['error'=>'ID required'],400);
    $s=$db->prepare('UPDATE products SET name=?,category=?,rate=?,hsn_code=?,gst_rate=?,description=? WHERE id=?');
    $s->execute([$d['name']??'',$d['category']??'Other',$d['rate']??0,$d['hsn']??$d['hsn_code']??'998314',$d['gst']??$d['gst_rate']??18,$d['description']??'',$id]);
    logActivity((int)$_SESSION['user_id'],'update','product',$id,"Updated product #$id");
    jsonResponse(['success'=>true]);

  case 'DELETE':
    $id=(int)str_replace('p','',($_GET['id']??0)); if(!$id) jsonResponse(['error'=>'ID required'],400);
    $db->prepare('UPDATE products SET is_active=0 WHERE id=?')->execute([$id]);
    logActivity((int)$_SESSION['user_id'],'delete','product',$id,"Deleted product #$id");
    jsonResponse(['success'=>true]);

  default: jsonResponse(['error'=>'Method not allowed'],405);
}