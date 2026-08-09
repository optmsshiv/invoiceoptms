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

// Auto-migrate: address/alt_phone were added later in a profile-page
// redesign. On any install created before that, these columns won't
// exist yet — which makes currentUser()'s SELECT fail silently (it's
// wrapped in try/catch and just returns null), so the whole Profile page
// would appear broken, not just Address. Self-healing, matches the
// ALTER-TABLE-in-try/catch pattern already used elsewhere (payments.php etc).
try { $db->exec("ALTER TABLE users ADD COLUMN address VARCHAR(255) NULL"); } catch (Exception $e) { /* already exists */ }
try { $db->exec("ALTER TABLE users ADD COLUMN alt_phone VARCHAR(30) NULL"); } catch (Exception $e) { /* already exists */ }

$sets   = [];
$params = [];

// Name/email are only validated when the client actually sends them —
// an avatar-only or mobile-only save shouldn't be forced to resupply both.
if (array_key_exists('name', $d) || array_key_exists('email', $d)) {
    $name  = trim($d['name']  ?? '');
    $email = strtolower(trim($d['email'] ?? ''));
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

    // Require the current password before allowing a change — without this,
    // anyone with an active/unattended session could take over the account
    // permanently by just setting a new password, no re-verification at all.
    $curCheck = $db->prepare('SELECT password FROM users WHERE id=?');
    $curCheck->execute([$uid]);
    $curHash = $curCheck->fetchColumn();
    if (empty($d['current_password']) || !$curHash || !password_verify($d['current_password'], $curHash)) {
        jsonResponse(['error'=>'Current password is incorrect'], 403);
    }

    $sets[]   = 'password=?';
    $params[] = password_hash($d['password'], PASSWORD_BCRYPT, ['cost'=>12]);
}
// avatar uses array_key_exists (not !empty) so an explicit empty string —
// i.e. "remove photo" — is actually saved instead of silently ignored.
// Omitting the key entirely (most saves) still leaves it untouched.
if (array_key_exists('avatar', $d)) { $sets[] = 'avatar=?'; $params[] = $d['avatar'] ?: null; }
// Accept either key — the users table column is `phone`, but some callers
// (and the old Profile page payload) send it as `mobile`. team.php already
// does the same fallback for this exact column.
if (isset($d['phone']) || isset($d['mobile'])) { $sets[] = 'phone=?'; $params[] = trim($d['phone'] ?? $d['mobile'] ?? ''); }
if (isset($d['alt_phone'])) { $sets[] = 'alt_phone=?'; $params[] = trim($d['alt_phone']); }
if (isset($d['address']))   { $sets[] = 'address=?';   $params[] = trim($d['address']); }

if (empty($sets)) jsonResponse(['error'=>'Nothing to update'], 400);

$params[] = $uid;
$db->prepare('UPDATE users SET '.implode(',',$sets).' WHERE id=?')->execute($params);

// activity_log is tenant-scoped by design — logActivity() already
// resolves the tenant DB internally, so this stays as-is.
logActivity($uid, 'update', 'user', $uid, 'Profile updated');
jsonResponse(['success'=>true]);