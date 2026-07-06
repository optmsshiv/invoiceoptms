<?php
// ================================================================
//  api/team.php — Team Management (Owner only, own tenant only)
//
//  GET    ?action=list                 → list this tenant's users
//  POST   ?action=add                  → add a user to this tenant
//  PATCH  ?action=update                → update role/status of a user
//  DELETE ?action=remove&user_id=N      → deactivate a user
// ================================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner'); // super_admin passes too, but has no tenant_id — see below

header('Content-Type: application/json');

// ── Avatar upload config (mirrors api/users.php) ────────────────
if (!defined('AVATAR_UPLOAD_URL')) define('AVATAR_UPLOAD_URL', '/assets/uploads/avatars');
$avatarDir = rtrim(UPLOAD_PATH, '/') . '/avatars';
if (!is_dir($avatarDir)) {
    @mkdir($avatarDir, 0755, true);
}

$method   = $_SERVER['REQUEST_METHOD'];
$action   = $_GET['action'] ?? $_POST['action'] ?? '';
$tenantId = $_SESSION['tenant_id'] ?? null;

if (!$tenantId) {
    jsonResponse(['error' => 'This endpoint requires a tenant context (not usable by super_admin directly)'], 400);
}

$body = [];
if (in_array($method, ['POST','PATCH','PUT'])) {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?: [];
}

// Owners can assign any role except super_admin. Only super_admin
// (via api/tenant.php) can create another owner — an owner shouldn't
// be able to mint a second owner account without going through that
// higher-trust path. Adjust here if you want owners to do that too.
$ASSIGNABLE_ROLES = ['admin','manager','accountant','sales','viewer'];

try {
    $master = getMasterDB();

    // ── LIST this tenant's users ────────────────────────────────────
    if ($method === 'GET' && $action === 'list') {
        $stmt = $master->prepare(
            'SELECT id, name, email, phone, address, role, status, avatar, tags, last_login, created_at
             FROM users WHERE tenant_id = ? ORDER BY role, name'
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['tags'] = $r['tags'] ? (json_decode($r['tags'], true) ?: []) : [];
            $r['id']   = (int)$r['id'];
        }
        unset($r);
        jsonResponse(['success' => true, 'data' => $rows]);
    }

    // ── GET a single user's full profile (for the Edit modal) ───────
    if ($method === 'GET' && $action === 'get') {
        $userId = (int)($_GET['user_id'] ?? 0);
        $stmt = $master->prepare(
            'SELECT id, name, email, phone, address, role, status, avatar, tags
             FROM users WHERE id=? AND tenant_id=?'
        );
        $stmt->execute([$userId, $tenantId]);
        $u = $stmt->fetch();
        if (!$u) jsonResponse(['error' => 'User not found in your team'], 404);
        $u['tags'] = $u['tags'] ? (json_decode($u['tags'], true) ?: []) : [];
        $u['id']   = (int)$u['id'];

        $cStmt = $master->prepare('SELECT id, name, phone, relation FROM user_contacts WHERE user_id=?');
        $cStmt->execute([$userId]);
        $contacts = $cStmt->fetchAll();

        jsonResponse(['success' => true, 'data' => $u, 'contacts' => $contacts]);
    }

    // ── ADD a user to this tenant ────────────────────────────────────
    if ($method === 'POST' && $action === 'add') {
        $email    = trim($body['email'] ?? '');
        $name     = trim($body['name']  ?? '');
        $phone    = trim($body['mobile'] ?? $body['phone'] ?? '');
        $address  = trim($body['address'] ?? '');
        $role     = $body['role'] ?? 'sales';
        $tags     = is_array($body['tags'] ?? null) ? array_values(array_filter(array_map('trim', $body['tags']))) : [];
        $contacts = is_array($body['contacts'] ?? null) ? $body['contacts'] : [];
        $avatarB64 = $body['avatar'] ?? null;
        $password = trim($body['password'] ?? '') !== '' ? trim($body['password']) : bin2hex(random_bytes(6));

        if (!in_array($role, $ASSIGNABLE_ROLES, true)) {
            jsonResponse(['error' => 'Invalid role'], 400);
        }
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Valid email required'], 400);
        }
        if (!$name) {
            jsonResponse(['error' => 'Name required'], 400);
        }

        // Enforce plan's max_users (0 = unlimited)
        $tStmt = $master->prepare('SELECT plan, db_name FROM tenants WHERE id=? AND status="active"');
        $tStmt->execute([$tenantId]);
        $tenant = $tStmt->fetch();
        if (!$tenant) jsonResponse(['error' => 'Tenant not found or suspended'], 404);

        $planStmt = $master->prepare('SELECT max_users FROM plans WHERE slug=?');
        $planStmt->execute([$tenant['plan']]);
        $maxUsers = (int)($planStmt->fetchColumn() ?: 0);

        if ($maxUsers > 0) {
            $countStmt = $master->prepare(
                'SELECT COUNT(*) FROM users WHERE tenant_id=? AND status != "inactive"'
            );
            $countStmt->execute([$tenantId]);
            if ((int)$countStmt->fetchColumn() >= $maxUsers) {
                jsonResponse(['error' => "Your {$tenant['plan']} plan allows a maximum of {$maxUsers} users. Upgrade to add more."], 403);
            }
        }

        // Email must be globally unique (master.users.email has a unique key)
        $emailCheck = $master->prepare('SELECT id FROM users WHERE email=?');
        $emailCheck->execute([$email]);
        if ($emailCheck->fetch()) jsonResponse(['error' => 'Email already in use'], 409);

        $hashedPass = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

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
            $master->prepare(
                'INSERT INTO users (tenant_id, name, email, phone, address, password, role, status, avatar, tags, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $tenantId, $name, $email, $phone ?: null, $address ?: null,
                $hashedPass, $role, 'active', $avatarPath,
                $tags ? json_encode($tags) : null, $_SESSION['user_id']
            ]);
            $userId = (int)$master->lastInsertId();

            if ($contacts) {
                $cIns = $master->prepare(
                    'INSERT INTO user_contacts (user_id, name, phone, relation, created_at)
                     VALUES (?,?,?,?,NOW())'
                );
                foreach ($contacts as $c) {
                    $cName  = trim($c['name'] ?? '');
                    $cPhone = trim($c['phone'] ?? '');
                    $cRel   = trim($c['relation'] ?? '');
                    if ($cName === '' && $cPhone === '') continue; // skip empty rows
                    $cIns->execute([$userId, $cName ?: null, $cPhone ?: null, $cRel ?: null]);
                }
            }

            $master->commit();
        } catch (Exception $e) {
            $master->rollBack();
            throw $e;
        }

        // Mirror into the tenant's own DB (used for FKs like created_by within that DB)
        // Wrapped defensively — the tenant DB's users table may not have every
        // column the master DB has (phone/avatar/tags), so failure here must
        // not undo the master insert above.
        try {
            $tenantDb = getDBByName($tenant['db_name']);
            $tenantDb->prepare(
                'INSERT IGNORE INTO users (id, name, email, password, role, is_active)
                 VALUES (?,?,?,?,?,1)'
            )->execute([$userId, $name, $email, $hashedPass, $role]);
        } catch (Exception $e) {
            error_log('team.php tenant-db mirror error: ' . $e->getMessage());
        }

        logActivity($_SESSION['user_id'], 'team_user_added', 'user', $userId,
            "Added {$email} ({$role})");

        jsonResponse([
            'success'   => true,
            'user_id'   => $userId,
            'email'     => $email,
            'role'      => $role,
            'temp_pass' => $password,
        ]);
    }

    // ── EDIT profile fields — scoped to THIS tenant only ────────────
    if ($method === 'PATCH' && $action === 'edit') {
        $userId      = (int)($body['user_id'] ?? 0);
        $name        = trim($body['name'] ?? '');
        $email       = trim($body['email'] ?? '');
        $phone       = trim($body['mobile'] ?? $body['phone'] ?? '');
        $address     = trim($body['address'] ?? '');
        $tags        = is_array($body['tags'] ?? null) ? array_values(array_filter(array_map('trim', $body['tags']))) : [];
        // contacts: null = leave untouched, array (possibly empty) = replace entirely
        $contacts    = array_key_exists('contacts', $body) && is_array($body['contacts']) ? $body['contacts'] : null;
        // avatar: absent key = leave untouched, '' = explicitly clear, data URL = replace
        $avatarSent  = array_key_exists('avatar', $body);
        $avatarB64   = $body['avatar'] ?? null;
        $newPassword = trim($body['password'] ?? '');

        if (!$name)  jsonResponse(['error' => 'Name required'], 400);
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Valid email required'], 400);
        }

        $check = $master->prepare('SELECT id, role, avatar FROM users WHERE id=? AND tenant_id=?');
        $check->execute([$userId, $tenantId]);
        $target = $check->fetch();
        if (!$target) jsonResponse(['error' => 'User not found in your team'], 404);
        if ($target['role'] === 'owner') {
            jsonResponse(['error' => 'The tenant owner cannot be edited here'], 403);
        }

        // Email must stay globally unique (excluding this user)
        $emailCheck = $master->prepare('SELECT id FROM users WHERE email=? AND id != ?');
        $emailCheck->execute([$email, $userId]);
        if ($emailCheck->fetch()) jsonResponse(['error' => 'Email already in use'], 409);

        $avatarPath = $target['avatar']; // keep existing unless changed
        if ($avatarSent) {
            if ($avatarB64 === '' || $avatarB64 === null) {
                $avatarPath = null; // explicit clear
            } elseif (preg_match('/^data:image\/(png|jpe?g|webp);base64,(.+)$/', $avatarB64, $m)) {
                $ext  = $m[1] === 'jpeg' ? 'jpg' : $m[1];
                $blob = base64_decode($m[2]);
                if ($blob !== false && strlen($blob) <= UPLOAD_MAX_SIZE) {
                    $fname = 'u_' . $tenantId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    file_put_contents($avatarDir . '/' . $fname, $blob);
                    $avatarPath = AVATAR_UPLOAD_URL . '/' . $fname;
                }
            }
        }

        $master->beginTransaction();
        try {
            $params = [
                ':name'    => $name,
                ':email'   => $email,
                ':phone'   => $phone ?: null,
                ':address' => $address ?: null,
                ':avatar'  => $avatarPath,
                ':tags'    => $tags ? json_encode($tags) : null,
            ];
            $sql = 'UPDATE users SET name=:name, email=:email, phone=:phone, address=:address, avatar=:avatar, tags=:tags';
            if ($newPassword !== '') {
                $sql .= ', password=:password';
                $params[':password'] = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            }
            $sql .= ' WHERE id=:id AND tenant_id=:tid';
            $params[':id']  = $userId;
            $params[':tid'] = $tenantId;
            $master->prepare($sql)->execute($params);

            if ($contacts !== null) {
                $master->prepare('DELETE FROM user_contacts WHERE user_id=?')->execute([$userId]);
                if ($contacts) {
                    $cIns = $master->prepare(
                        'INSERT INTO user_contacts (user_id, name, phone, relation, created_at)
                         VALUES (?,?,?,?,NOW())'
                    );
                    foreach ($contacts as $c) {
                        $cName  = trim($c['name'] ?? '');
                        $cPhone = trim($c['phone'] ?? '');
                        $cRel   = trim($c['relation'] ?? '');
                        if ($cName === '' && $cPhone === '') continue;
                        $cIns->execute([$userId, $cName ?: null, $cPhone ?: null, $cRel ?: null]);
                    }
                }
            }

            $master->commit();
        } catch (Exception $e) {
            $master->rollBack();
            throw $e;
        }

        // Mirror name/email/password to the tenant's own DB — defensive,
        // won't undo the master update above if the tenant DB differs.
        try {
            $tStmt2 = $master->prepare('SELECT db_name FROM tenants WHERE id=?');
            $tStmt2->execute([$tenantId]);
            $dbName = $tStmt2->fetchColumn();
            if ($dbName) {
                $tenantDb = getDBByName($dbName);
                $upd2 = 'UPDATE users SET name=?, email=?';
                $p2   = [$name, $email];
                if ($newPassword !== '') {
                    $upd2 .= ', password=?';
                    $p2[] = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                }
                $upd2 .= ' WHERE id=?';
                $p2[] = $userId;
                $tenantDb->prepare($upd2)->execute($p2);
            }
        } catch (Exception $e) {
            error_log('team.php tenant-db mirror (edit) error: ' . $e->getMessage());
        }

        logActivity($_SESSION['user_id'], 'team_user_edited', 'user', $userId,
            "Updated profile for {$email}");

        jsonResponse(['success' => true]);
    }

    // ── UPDATE role/status — scoped to THIS tenant only ─────────────
    if ($method === 'PATCH' && $action === 'update') {
        $userId = (int)($body['user_id'] ?? 0);
        $field  = $body['field'] ?? '';
        $value  = $body['value'] ?? '';

        if (!in_array($field, ['role','status'], true)) {
            jsonResponse(['error' => 'Only role and status can be updated'], 400);
        }
        if ($field === 'role' && !in_array($value, $ASSIGNABLE_ROLES, true)) {
            jsonResponse(['error' => 'Invalid role'], 400);
        }
        if ($field === 'status' && !in_array($value, ['active','inactive'], true)) {
            jsonResponse(['error' => 'Invalid status'], 400);
        }

        // Ownership check: the target user must belong to this tenant,
        // and must not be the tenant's owner (owners aren't editable here).
        $check = $master->prepare('SELECT id, role FROM users WHERE id=? AND tenant_id=?');
        $check->execute([$userId, $tenantId]);
        $target = $check->fetch();
        if (!$target) jsonResponse(['error' => 'User not found in your team'], 404);
        if ($target['role'] === 'owner') {
            jsonResponse(['error' => 'The tenant owner cannot be modified here'], 403);
        }

        $master->prepare("UPDATE users SET {$field}=? WHERE id=? AND tenant_id=?")
               ->execute([$value, $userId, $tenantId]);

        logActivity($_SESSION['user_id'], 'team_user_updated', 'user', $userId,
            "{$field} -> {$value}");

        jsonResponse(['success' => true]);
    }

    // ── REMOVE (deactivate) — scoped to THIS tenant only ────────────
    if (($method === 'DELETE' || $method === 'PATCH') && $action === 'remove') {
        $userId = (int)($body['user_id'] ?? $_GET['user_id'] ?? 0);

        $check = $master->prepare('SELECT id, role FROM users WHERE id=? AND tenant_id=?');
        $check->execute([$userId, $tenantId]);
        $target = $check->fetch();
        if (!$target) jsonResponse(['error' => 'User not found in your team'], 404);
        if ($target['role'] === 'owner') {
            jsonResponse(['error' => 'The tenant owner cannot be removed'], 403);
        }

        $master->prepare('UPDATE users SET status="inactive" WHERE id=? AND tenant_id=?')
               ->execute([$userId, $tenantId]);

        logActivity($_SESSION['user_id'], 'team_user_removed', 'user', $userId, '');

        jsonResponse(['success' => true]);
    }

    jsonResponse(['error' => 'Unknown action: ' . $action], 400);

} catch (Exception $e) {
    error_log('team.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
}