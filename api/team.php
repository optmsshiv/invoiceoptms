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
            'SELECT id, name, email, role, status, last_login, created_at
             FROM users WHERE tenant_id = ? ORDER BY role, name'
        );
        $stmt->execute([$tenantId]);
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // ── ADD a user to this tenant ────────────────────────────────────
    if ($method === 'POST' && $action === 'add') {
        $email    = trim($body['email'] ?? '');
        $name     = trim($body['name']  ?? '');
        $role     = $body['role'] ?? 'sales';
        $password = $body['password'] ?? bin2hex(random_bytes(6));

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

        $master->prepare(
            'INSERT INTO users (tenant_id, name, email, password, role, status, created_by)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$tenantId, $name, $email, $hashedPass, $role, 'active', $_SESSION['user_id']]);
        $userId = (int)$master->lastInsertId();

        // Mirror into the tenant's own DB (used for FKs like created_by within that DB)
        $tenantDb = getDBByName($tenant['db_name']);
        $tenantDb->prepare(
            'INSERT IGNORE INTO users (id, name, email, password, role, is_active)
             VALUES (?,?,?,?,?,1)'
        )->execute([$userId, $name, $email, $hashedPass, $role]);

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
