<?php
// ================================================================
//  api/role_permissions.php — Role Permission Management (Owner only)
//
//  GET  ?action=list  → full catalog with per-role state + plan ceiling
//  POST ?action=set   → {role, permission_key, enabled}
// ================================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner'); // super_admin always passes too (see requireRole())

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$body = [];
if (in_array($method, ['POST','PATCH','PUT'])) {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?: [];
}

$ROLES = ['admin','manager','accountant','sales','viewer'];

try {
    $master   = getMasterDB();
    $tenantId = $_SESSION['tenant_id'] ?? null;
    if (!$tenantId) jsonResponse(['error' => 'No tenant context'], 400);

    $tStmt = $master->prepare('SELECT plan FROM tenants WHERE id=?');
    $tStmt->execute([$tenantId]);
    $plan = $tStmt->fetchColumn() ?: 'trial';

    // ── Helper: what does this tenant's plan/override ceiling allow? ──
    $getCeiling = function(string $key) use ($master, $tenantId, $plan): bool {
        $ov = $master->prepare('SELECT enabled FROM tenant_permission_overrides WHERE tenant_id=? AND permission_key=?');
        $ov->execute([$tenantId, $key]);
        $ovRow = $ov->fetch();
        if ($ovRow !== false) return (bool)$ovRow['enabled'];

        $pp = $master->prepare('SELECT enabled FROM plan_permissions WHERE plan=? AND permission_key=?');
        $pp->execute([$plan, $key]);
        $ppRow = $pp->fetch();
        return $ppRow === false ? true : (bool)$ppRow['enabled'];
    };

    // ── LIST: catalog + per-role state + ceiling (for graying out UI) ──
    if ($method === 'GET' && $action === 'list') {
        $catalog = $master->query(
            'SELECT `key`, label, category, sort_order FROM permissions ORDER BY sort_order'
        )->fetchAll();

        $tenantDb  = getDB();
        $rolePerms = $tenantDb->query('SELECT role, permission_key, enabled FROM role_permissions')->fetchAll();
        $roleMap   = [];
        foreach ($rolePerms as $rp) $roleMap[$rp['role']][$rp['permission_key']] = (bool)$rp['enabled'];

        $data = [];
        foreach ($catalog as $perm) {
            $key   = $perm['key'];
            $row   = ['key' => $key, 'label' => $perm['label'], 'category' => $perm['category']];
            $row['ceiling'] = $getCeiling($key);
            $row['roles']   = [];
            foreach ($ROLES as $role) {
                $row['roles'][$role] = $roleMap[$role][$key] ?? false;
            }
            $data[] = $row;
        }
        jsonResponse(['success' => true, 'plan' => $plan, 'data' => $data]);
    }

    // ── SET: one role/permission cell ──────────────────────────────
    if ($method === 'POST' && $action === 'set') {
        $role    = $body['role'] ?? '';
        $key     = $body['permission_key'] ?? '';
        $enabled = !empty($body['enabled']) ? 1 : 0;

        if (!in_array($role, $ROLES, true) || !$key) {
            jsonResponse(['error' => 'Invalid role or permission_key'], 400);
        }

        // Defense in depth: never allow enabling something the tenant's
        // plan/override ceiling doesn't allow, even if a request is crafted
        // directly bypassing the (already grayed-out) UI control.
        if ($enabled && !$getCeiling($key)) {
            jsonResponse(['error' => 'This feature is not available on your current plan'], 403);
        }

        $tenantDb = getDB();
        $tenantDb->prepare(
            'INSERT INTO role_permissions (role, permission_key, enabled) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)'
        )->execute([$role, $key, $enabled]);

        logActivity($_SESSION['user_id'], 'role_permission_set', 'permission', 0,
            "{$role} -> {$key} = " . ($enabled ? 'ON' : 'OFF'));

        jsonResponse(['success' => true]);
    }

    jsonResponse(['error' => 'Unknown action: ' . $action], 400);

} catch (Exception $e) {
    error_log('role_permissions.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
}
