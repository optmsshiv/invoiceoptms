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

    // Auto-migrate: "Compare Sessions" used to piggyback on the
    // menu.finance_report permission key (see index.php sidebar), so there
    // was no way to grant/restrict it independently in the Team permissions
    // UI. Give it its own catalog row, placed right after Finance Report,
    // the first time this endpoint runs after the update. Self-healing —
    // once the row exists this block is a no-op on every later request.
    try {
        $csExists = $master->prepare('SELECT id FROM permissions WHERE `key` = ?');
        $csExists->execute(['menu.compare_sessions']);
        if (!$csExists->fetch()) {
            $frStmt = $master->prepare('SELECT category, sort_order FROM permissions WHERE `key` = ?');
            $frStmt->execute(['menu.finance_report']);
            $fr = $frStmt->fetch();
            $category  = $fr['category'] ?? 'Menu';
            $sortOrder = $fr ? ((int)$fr['sort_order'] + 1) : 999;
            // Make room so it lands directly after Finance Report instead of at the end
            $master->prepare('UPDATE permissions SET sort_order = sort_order + 1 WHERE sort_order >= ?')
                   ->execute([$sortOrder]);
            $master->prepare('INSERT INTO permissions (`key`, label, category, sort_order) VALUES (?,?,?,?)')
                   ->execute(['menu.compare_sessions', 'Compare Sessions', $category, $sortOrder]);
        }
    } catch (Exception $e) { error_log('role_permissions.php compare_sessions migrate: ' . $e->getMessage()); }

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

        // One-time per-tenant carry-forward: menu.compare_sessions is newly
        // split out from menu.finance_report (they used to share one toggle).
        // If this tenant has no row for the new key yet, seed each role's
        // Compare Sessions toggle from its current Finance Report toggle, so
        // access doesn't silently disappear the first time this loads after
        // the update — from here on the owner can change either independently.
        $hasCompareSessionsRow = false;
        foreach ($ROLES as $role) { if (isset($roleMap[$role]['menu.compare_sessions'])) { $hasCompareSessionsRow = true; break; } }
        if (!$hasCompareSessionsRow) {
            $seedIns = $tenantDb->prepare(
                'INSERT INTO role_permissions (role, permission_key, enabled) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)'
            );
            foreach ($ROLES as $role) {
                $inherited = $roleMap[$role]['menu.finance_report'] ?? false;
                $seedIns->execute([$role, 'menu.compare_sessions', $inherited ? 1 : 0]);
                $roleMap[$role]['menu.compare_sessions'] = $inherited;
            }
        }

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

    // ── BULK-SET: reset a whole role's action permissions at once ──
    if ($method === 'POST' && $action === 'set_bulk') {
        $role    = $body['role'] ?? '';
        $perms   = $body['permissions'] ?? [];
        if (!in_array($role, $ROLES, true) || !is_array($perms)) {
            jsonResponse(['error' => 'Invalid role or permissions'], 400);
        }
        $tenantDb = getDB();
        foreach ($perms as $key => $enabled) {
            $enabled = $enabled ? 1 : 0;
            if ($enabled && !$getCeiling($key)) continue;
            $tenantDb->prepare(
                'INSERT INTO role_permissions (role, permission_key, enabled) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)'
            )->execute([$role, $key, $enabled]);
        }
        logActivity((int)$_SESSION['user_id'], 'role_permission_bulk_set', 'permission', 0, "Bulk set for role: {$role}");
        jsonResponse(['success' => true]);
    }

    jsonResponse(['error' => 'Unknown action: ' . $action], 400);

} catch (Exception $e) {
    error_log('role_permissions.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
}