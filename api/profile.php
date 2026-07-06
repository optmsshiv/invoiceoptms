<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error'=>'POST required'], 405);

$d     = json_decode(file_get_contents('php://input'), true);
$uid   = (int)$_SESSION['user_id'];
$name  = trim($d['name']  ?? '');
$email = trim($d['email'] ?? '');

if (!$name || !$email) jsonResponse(['error'=>'Name and email required'], 400);

// Check email not taken by another user
$check = $db->prepare('SELECT id FROM users WHERE email=? AND id!=?');
$check->execute([$email, $uid]);
if ($check->fetch()) jsonResponse(['error'=>'Email already in use'], 409);

$sets = ['name=?','email=?']; $params = [$name, $email];

if (!empty($d['password'])) {
    if (strlen($d['password']) < 6) jsonResponse(['error'=>'Password min 6 chars'], 400);
    $sets[]   = 'password=?';
    $params[] = password_hash($d['password'], PASSWORD_BCRYPT, ['cost'=>12]);
}
if (!empty($d['avatar'])) { $sets[]='avatar=?'; $params[]=$d['avatar']; }
if (isset($d['mobile']))    { $sets[]='mobile=?';    $params[]=trim($d['mobile']); }
if (isset($d['alt_phone'])) { $sets[]='alt_phone=?'; $params[]=trim($d['alt_phone']); }

$params[] = $uid;
$db->prepare('UPDATE users SET '.implode(',',$sets).' WHERE id=?')->execute($params);

$_SESSION['user_name']  = $name;
$_SESSION['user_email'] = $email;
logActivity($uid,'update','user',$uid,"Profile updated");
jsonResponse(['success'=>true]);