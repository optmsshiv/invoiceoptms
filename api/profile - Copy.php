<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// users is the canonical table in the MASTER DB — this is what
// currentUser() and attemptLogin() both read from. Writing to getDB()
// (tenant DB mirror) here would silently desync name/email/password
// from what login actually checks.
$db  = getMasterDB();
$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error'=>'POST required'], 405);

$d = json_decode(file_get_contents('php://input'), true) ?: [];

$sets   = [];
$params = [];

// Name/email are only validated when the client actually sends them —
// an avatar-only or mobile-only save shouldn't be forced to resupply both.
if (array_key_exists('name', $d) || array_key_exists('email', $d)) {
    $name  = trim($d['name']  ?? '');
    $email = trim($d['email'] ?? '');
    if (!$name || !$email) jsonResponse(['error'=>'Name and email required'], 400);

    $check = $db->prepare('SELECT id FROM users WHERE email=? AND id!=?');
    $check->execute([$email, $uid]);
    if ($check->fetch()) jsonResponse(['error'=>'Email already in use'], 409);

    $sets[] = 'name=?';  $params[] = $name;
    $sets[] = 'email=?'; $params[] = $email;
    $_SESSION['user_name']  = $name;
    $_SESSION['user_email'] = $email;
}

if (!empty($d['password'])) {
    if (strlen($d['password']) < 6) jsonResponse(['error'=>'Password min 6 chars'], 400);
    $sets[]   = 'password=?';
    $params[] = password_hash($d['password'], PASSWORD_BCRYPT, ['cost'=>12]);
}
if (!empty($d['avatar']))   { $sets[] = 'avatar=?';    $params[] = $d['avatar']; }
if (isset($d['phone']))     { $sets[] = 'phone=?';     $params[] = trim($d['phone']); }
if (isset($d['alt_phone'])) { $sets[] = 'alt_phone=?'; $params[] = trim($d['alt_phone']); }
if (isset($d['address']))   { $sets[] = 'address=?';   $params[] = trim($d['address']); }

if (empty($sets)) jsonResponse(['error'=>'Nothing to update'], 400);

$params[] = $uid;
$db->prepare('UPDATE users SET '.implode(',',$sets).' WHERE id=?')->execute($params);

// activity_log is tenant-scoped by design — logActivity() already
// resolves the tenant DB internally, so this stays as-is.
logActivity($uid, 'update', 'user', $uid, 'Profile updated');
jsonResponse(['success'=>true]);