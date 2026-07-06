<?php
// ================================================================
//  api/users.php
//  Team member management endpoint.
//  Actions: list (GET), add (POST), remove (PATCH), change_password (PATCH)
// ================================================================

require __DIR__ . '/../includes/auth.php'; // pulls in config/db.php too

requireLogin();

$user     = currentUser();
$tenantId = $user['tenant_id'] ?? null;

if (!$tenantId && ($user['role'] ?? '') !== 'super_admin') {
    jsonResponse(['success' => false, 'error' => 'No tenant context'], 400);
}

// ---- Config you may need to adjust ----------------------------
define('AVATAR_UPLOAD_URL', '/assets/uploads/avatars'); // public URL; files land in UPLOAD_PATH/avatars
define('TEMP_PASS_LENGTH', 10);

$avatarDir = rtrim(UPLOAD_PATH, '/') . '/avatars';
if (!is_dir($avatarDir)) {
    @mkdir($avatarDir, 0755, true);
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
switch ($action) {
    case 'list':
        if ($method !== 'GET') jsonResponse(['success' => false, 'error' => 'Invalid method'], 405);
        actionList($tenantId);
        break;

    case 'add':
        if ($method !== 'POST') jsonResponse(['success' => false, 'error' => 'Invalid method'], 405);
        requirePermission('action.users.manage');
        actionAdd($tenantId, $user, $avatarDir);
        break;

    case 'remove':
        if ($method !== 'PATCH') jsonResponse(['success' => false, 'error' => 'Invalid method'], 405);
        requirePermission('action.users.manage');
        actionRemove($tenantId, $user);
        break;

    case 'change_password':
        if ($method !== 'PATCH') jsonResponse(['success' => false, 'error' => 'Invalid method'], 405);
        actionChangePassword($user);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 404);
}
} catch (Throwable $e) {
    error_log('users.php error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
}

// ================================================================
//  LIST — team members table on team.php
// ================================================================
function actionList(?int $tenantId): void {
    $stmt = getMasterDB()->prepare("
        SELECT id, name, email, phone, role, status, avatar, last_login, tags
        FROM users
        WHERE tenant_id = :tid AND status != 'inactive'
        ORDER BY FIELD(role,'owner','admin','manager','accountant','sales','viewer'), name ASC
    ");
    $stmt->execute([':tid' => $tenantId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['tags'] = $r['tags'] ? json_decode($r['tags'], true) : [];
        $r['id']   = (int)$r['id'];
    }

    jsonResponse(['success' => true, 'data' => $rows]);
}

// ================================================================
//  ADD — Add Team Member modal
// ================================================================
function actionAdd(?int $tenantId, array $currentU, string $avatarDir): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $name     = trim($body['name'] ?? '');
    $email    = trim(strtolower($body['email'] ?? ''));
    $phone    = trim($body['mobile'] ?? '');       // modal sends "mobile" -> stored in "phone" column
    $address  = trim($body['address'] ?? '');
    $role     = trim($body['role'] ?? 'viewer');
    $tags     = is_array($body['tags'] ?? null) ? $body['tags'] : [];
    $contacts = is_array($body['contacts'] ?? null) ? $body['contacts'] : [];
    $avatarB64 = $body['avatar'] ?? null;
    $password  = trim($body['password'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'error' => 'A valid email is required'], 400);
    }

    $allowedRoles = ['admin', 'manager', 'accountant', 'sales', 'viewer'];
    if (!in_array($role, $allowedRoles, true)) {
        jsonResponse(['success' => false, 'error' => 'Invalid role'], 400);
    }

    $master = getMasterDB();

    // Uniqueness check — email is globally unique across ALL tenants (uk_email)
    $chk = $master->prepare("SELECT id FROM users WHERE email = :email");
    $chk->execute([':email' => $email]);
    if ($chk->fetch()) {
        jsonResponse(['success' => false, 'error' => 'A user with this email already exists'], 400);
    }

    $tempPass = $password !== '' ? $password : generateTempPassword();
    $hash = password_hash($tempPass, PASSWORD_BCRYPT);

    // Handle avatar (data URL -> file on disk)
    $avatarPath = null;
    if ($avatarB64 && preg_match('/^data:image\/(png|jpe?g|webp);base64,(.+)$/', $avatarB64, $m)) {
        $ext  = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $blob = base64_decode($m[2]);
        if ($blob !== false && strlen($blob) <= UPLOAD_MAX_SIZE) {
            $fname = 'u_' . $tenantId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            file_put_contents($avatarDir . '/' . $fname, $blob);
            $avatarPath = AVATAR_UPLOAD_URL . '/' . $fname;
        }
    }

    $master->beginTransaction();
    try {
        $ins = $master->prepare("
            INSERT INTO users (tenant_id, name, email, phone, address, role, status, password, avatar, tags, login_count)
            VALUES (:tid, :name, :email, :phone, :address, :role, 'invited', :hash, :avatar, :tags, 0)
        ");
        $ins->execute([
            ':tid'     => $tenantId,
            ':name'    => $name,
            ':email'   => $email,
            ':phone'   => $phone ?: null,
            ':address' => $address ?: null,
            ':role'    => $role,
            ':hash'    => $hash,
            ':avatar'  => $avatarPath,
            ':tags'    => $tags ? json_encode($tags) : null,
        ]);
        $newUserId = (int)$master->lastInsertId();

        if ($contacts) {
            $cIns = $master->prepare("
                INSERT INTO user_contacts (user_id, name, phone, relation, created_at)
                VALUES (:uid, :name, :phone, :relation, NOW())
            ");
            foreach ($contacts as $c) {
                $cName  = trim($c['name'] ?? '');
                $cPhone = trim($c['phone'] ?? '');
                $cRel   = trim($c['relation'] ?? '');
                if ($cName === '' && $cPhone === '') continue; // skip empty rows
                $cIns->execute([
                    ':uid'      => $newUserId,
                    ':name'     => $cName ?: null,
                    ':phone'    => $cPhone ?: null,
                    ':relation' => $cRel ?: null,
                ]);
            }
        }

        $master->commit();
    } catch (Throwable $e) {
        $master->rollBack();
        error_log('users.php add error: ' . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'Failed to create user'], 500);
    }

    masterAuditLog($currentU['id'], $tenantId, 'user.add', "Added user #{$newUserId} ({$email})");

    jsonResponse([
        'success'   => true,
        'id'        => $newUserId,
        'email'     => $email,
        'temp_pass' => $tempPass,
    ]);
}

// ================================================================
//  REMOVE — deactivate a team member
// ================================================================
function actionRemove(?int $tenantId, array $currentU): void {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $userId = (int)($body['user_id'] ?? 0);
    if (!$userId) jsonResponse(['success' => false, 'error' => 'user_id is required'], 400);

    if ($userId === (int)$currentU['id']) {
        jsonResponse(['success' => false, 'error' => "You can't remove your own account"], 400);
    }

    $master = getMasterDB();
    $stmt = $master->prepare("SELECT role, email FROM users WHERE id = :id AND tenant_id = :tid AND status != 'inactive'");
    $stmt->execute([':id' => $userId, ':tid' => $tenantId]);
    $target = $stmt->fetch();
    if (!$target) jsonResponse(['success' => false, 'error' => 'User not found'], 404);
    if ($target['role'] === 'owner') jsonResponse(['success' => false, 'error' => "The account owner can't be removed"], 400);

    $upd = $master->prepare("UPDATE users SET status = 'inactive' WHERE id = :id AND tenant_id = :tid");
    $upd->execute([':id' => $userId, ':tid' => $tenantId]);

    masterAuditLog($currentU['id'], $tenantId, 'user.remove', "Removed user #{$userId} ({$target['email']})");

    jsonResponse(['success' => true]);
}

// ================================================================
//  CHANGE PASSWORD — current logged-in user
// ================================================================
function actionChangePassword(array $currentU): void {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $current = $body['current_password'] ?? '';
    $newPass = $body['new_password'] ?? '';

    if (strlen($newPass) < 8) {
        jsonResponse(['success' => false, 'error' => 'New password must be at least 8 characters'], 400);
    }

    $master = getMasterDB();
    $stmt = $master->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->execute([':id' => $currentU['id']]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($current, $row['password'])) {
        jsonResponse(['success' => false, 'error' => 'Current password is incorrect'], 400);
    }

    $hash = password_hash($newPass, PASSWORD_BCRYPT);
    $master->prepare("UPDATE users SET password = :hash WHERE id = :id")
           ->execute([':hash' => $hash, ':id' => $currentU['id']]);

    masterAuditLog($currentU['id'], $currentU['tenant_id'] ?? null, 'user.change_password', 'Password changed');

    jsonResponse(['success' => true]);
}

// ================================================================
//  Helpers
// ================================================================
function generateTempPassword(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $pass = '';
    for ($i = 0; $i < TEMP_PASS_LENGTH; $i++) {
        $pass .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pass;
}