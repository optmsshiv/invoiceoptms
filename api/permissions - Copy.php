<?php
// ================================================================
//  api/permissions.php — Permission Management (Super Admin only)
//
//  GET    ?action=catalog                    → full permission catalog
//  GET    ?action=plan&plan=X                → plan's permissions (merged w/ catalog)
//  POST   ?action=set_plan                   → set one permission for a plan
//  GET    ?action=tenant&tenant_id=N         → tenant's effective permissions
//  POST   ?action=set_tenant_override        → force on/off for one tenant
//  POST   ?action=clear_tenant_override      → revert one tenant permission to plan default
// ================================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$body = [];
if (in_array($method, ['POST','PATCH','PUT'])) {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?: [];
    if (empty($body)) $body = $_POST;
}

$VALID_PLANS = ['trial','basic','pro','enterprise'];

try {
    $master = getMasterDB();

    // ── Full catalog (every controllable permission) ────────────────
    if ($method === 'GET' && $action === 'catalog') {
        $rows = $master->query(
            'SELECT `key`, label, category, default_min_role, sort_order
             FROM permissions ORDER BY sort_order'
        )->fetchAll();
        jsonResponse(['success' => true, 'data' => $rows]);
    }

    // ── One plan's permissions (catalog merged with plan_permissions) ──
    if ($method === 'GET' && $action === 'plan') {
        $plan = $_GET['plan'] ?? '';
        if (!in_array($plan, $VALID_PLANS)) jsonResponse(['error' => 'Invalid plan'], 400);

        $rows = $master->prepare(
            'SELECT p.`key`, p.label, p.category, p.sort_order,
                    COALESCE(pp.enabled, 1) AS enabled
             FROM permissions p
             LEFT JOIN plan_permissions pp ON pp.permission_key = p.`key` AND pp.plan = ?
             ORDER BY p.sort_order'
        );
        $rows->execute([$plan]);
        jsonResponse(['success' => true, 'plan' => $plan, 'data' => $rows->fetchAll()]);
    }

    // ── Set one permission for a plan ────────────────────────────────
    if ($method === 'POST' && $action === 'set_plan') {
        $plan    = $body['plan'] ?? '';
        $key     = $body['permission_key'] ?? '';
        $enabled = !empty($body['enabled']) ? 1 : 0;

        if (!in_array($plan, $VALID_PLANS) || !$key) {
            jsonResponse(['error' => 'plan and permission_key are required'], 400);
        }

        $master->prepare(
            'INSERT INTO plan_permissions (plan, permission_key, enabled) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)'
        )->execute([$plan, $key, $enabled]);

        masterAuditLog($_SESSION['user_id'], null, 'plan_permission_set',
            "Plan '{$plan}' → {$key} = " . ($enabled ? 'ON' : 'OFF'));

        jsonResponse(['success' => true]);
    }

    // ── One tenant's effective permissions (override > plan default) ──
    if ($method === 'GET' && $action === 'tenant') {
        $tenantId = (int)($_GET['tenant_id'] ?? 0);
        $tStmt = $master->prepare('SELECT id, company_name, plan FROM tenants WHERE id=?');
        $tStmt->execute([$tenantId]);
        $tenant = $tStmt->fetch();
        if (!$tenant) jsonResponse(['error' => 'Tenant not found'], 404);

        $rows = $master->prepare(
            'SELECT p.`key`, p.label, p.category, p.sort_order,
                    COALESCE(pp.enabled, 1)  AS plan_default,
                    tpo.enabled              AS override_value,
                    (tpo.id IS NOT NULL)     AS is_override
             FROM permissions p
             LEFT JOIN plan_permissions pp
                    ON pp.permission_key = p.`key` AND pp.plan = ?
             LEFT JOIN tenant_permission_overrides tpo
                    ON tpo.permission_key = p.`key` AND tpo.tenant_id = ?
             ORDER BY p.sort_order'
        );
        $rows->execute([$tenant['plan'], $tenantId]);
        $data = array_map(function($r) {
            $r['effective'] = $r['is_override'] ? (bool)$r['override_value'] : (bool)$r['plan_default'];
            $r['is_override'] = (bool)$r['is_override'];
            $r['plan_default'] = (bool)$r['plan_default'];
            return $r;
        }, $rows->fetchAll());

        jsonResponse(['success' => true, 'tenant' => $tenant, 'data' => $data]);
    }

    // ── Force on/off for one tenant (overrides plan default) ─────────
    if ($method === 'POST' && $action === 'set_tenant_override') {
        $tenantId = (int)($body['tenant_id'] ?? 0);
        $key      = $body['permission_key'] ?? '';
        $enabled  = !empty($body['enabled']) ? 1 : 0;

        if (!$tenantId || !$key) jsonResponse(['error' => 'tenant_id and permission_key are required'], 400);

        $master->prepare(
            'INSERT INTO tenant_permission_overrides (tenant_id, permission_key, enabled, set_by)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), set_by = VALUES(set_by)'
        )->execute([$tenantId, $key, $enabled, $_SESSION['user_id']]);

        masterAuditLog($_SESSION['user_id'], $tenantId, 'tenant_permission_override',
            "{$key} forced " . ($enabled ? 'ON' : 'OFF'));

        jsonResponse(['success' => true]);
    }

    // ── Revert one tenant permission back to plan default ─────────────
    if ($method === 'POST' && $action === 'clear_tenant_override') {
        $tenantId = (int)($body['tenant_id'] ?? 0);
        $key      = $body['permission_key'] ?? '';
        if (!$tenantId || !$key) jsonResponse(['error' => 'tenant_id and permission_key are required'], 400);

        $master->prepare(
            'DELETE FROM tenant_permission_overrides WHERE tenant_id=? AND permission_key=?'
        )->execute([$tenantId, $key]);

        masterAuditLog($_SESSION['user_id'], $tenantId, 'tenant_permission_reset', $key);

        jsonResponse(['success' => true]);
    }

    jsonResponse(['error' => 'Unknown action: ' . $action], 400);

} catch (Exception $e) {
    error_log('permissions.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
}
