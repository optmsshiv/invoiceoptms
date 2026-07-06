<?php
// ================================================================
//  OPTMS Invoice Manager — includes/auth.php
//  Multi-tenant version
// ================================================================
require_once __DIR__ . '/../config/db.php';

// ── Session start ─────────────────────────────────────────────────
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// ── Require login — redirects or returns 401 ─────────────────────
function requireLogin(): void {
    startSession();
    if (empty($_SESSION['user_id'])) {
        _authFail('Not authenticated', '/auth/login.php');
    }
    // Session timeout check
    if (!empty($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        doLogout();
        _authFail('Session expired', '/auth/login.php');
    }
    $_SESSION['last_activity'] = time();
}

// ── Require specific role(s) ──────────────────────────────────────
// Usage: requireRole(['owner','admin','manager'])
// Usage: requireRole('owner')  ← single role
// Usage: requireRole(3)        ← minimum weight level
function requireRole(array|string|int $roles): void {
    requireLogin();
    $userRole   = $_SESSION['user_role'] ?? 'viewer';
    $userWeight = ROLE_WEIGHTS[$userRole] ?? 0;

    if (is_int($roles)) {
        // Numeric weight check
        if ($userWeight < $roles) _roleFail($userRole);
        return;
    }

    $allowed = is_array($roles) ? $roles : [$roles];
    // super_admin always passes
    if ($userRole === 'super_admin') return;
    if (!in_array($userRole, $allowed, true)) _roleFail($userRole);
}

// ── Require super admin ───────────────────────────────────────────
function requireSuperAdmin(): void {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'super_admin') {
        _roleFail($_SESSION['user_role'] ?? 'unknown');
    }
}

// ── Check role without throwing ───────────────────────────────────
function hasRole(array|string $roles): bool {
    $userRole = $_SESSION['user_role'] ?? 'viewer';
    if ($userRole === 'super_admin') return true;
    $allowed = is_array($roles) ? $roles : [$roles];
    return in_array($userRole, $allowed, true);
}

function hasMinRole(string $minRole): bool {
    $userWeight = ROLE_WEIGHTS[$_SESSION['user_role'] ?? 'viewer'] ?? 0;
    $minWeight  = ROLE_WEIGHTS[$minRole] ?? 0;
    return $userWeight >= $minWeight;
}

// ── Effective permission map for a role/tenant ─────────────────────
// Merge order: tenant_permission_overrides > plan_permissions > true (ceiling)
// then AND with the tenant's role_permissions toggle (owner/super_admin
// bypass the role toggle and answer only to the ceiling).
function getEffectivePermissions(?int $tenantId, string $role): array {
    static $cache = [];
    $cacheKey = $tenantId . '|' . $role;
    if (isset($cache[$cacheKey])) return $cache[$cacheKey];

    $master = getMasterDB();

    $plan = 'trial';
    if ($tenantId) {
        $tStmt = $master->prepare('SELECT plan FROM tenants WHERE id=?');
        $tStmt->execute([$tenantId]);
        $plan = $tStmt->fetchColumn() ?: 'trial';
    }

    $overrides = [];
    if ($tenantId) {
        $ovStmt = $master->prepare('SELECT permission_key, enabled FROM tenant_permission_overrides WHERE tenant_id=?');
        $ovStmt->execute([$tenantId]);
        foreach ($ovStmt->fetchAll() as $row) $overrides[$row['permission_key']] = (bool)$row['enabled'];
    }

    $planPerms = [];
    $ppStmt = $master->prepare('SELECT permission_key, enabled FROM plan_permissions WHERE plan=?');
    $ppStmt->execute([$plan]);
    foreach ($ppStmt->fetchAll() as $row) $planPerms[$row['permission_key']] = (bool)$row['enabled'];

    $catalog = $master->query('SELECT `key` FROM permissions')->fetchAll(PDO::FETCH_COLUMN);

    $roleMap = [];
    if (!in_array($role, ['owner', 'super_admin'], true)) {
        try {
            $rpStmt = getDB()->prepare('SELECT permission_key, enabled FROM role_permissions WHERE role=?');
            $rpStmt->execute([$role]);
            foreach ($rpStmt->fetchAll() as $row) $roleMap[$row['permission_key']] = (bool)$row['enabled'];
        } catch (Exception $e) { /* no tenant DB context, e.g. super_admin */ }
    }

    $result = [];
    foreach ($catalog as $key) {
        $ceiling = array_key_exists($key, $overrides) ? $overrides[$key] : ($planPerms[$key] ?? true);
        $result[$key] = in_array($role, ['owner', 'super_admin'], true)
            ? $ceiling
            : ($ceiling && ($roleMap[$key] ?? false));
    }

    return $cache[$cacheKey] = $result;
}

// ── Require a specific permission key (throws/redirects if missing) ──
function requirePermission(string $key): void {
    requireLogin();
    $role = $_SESSION['user_role'] ?? 'viewer';
    if ($role === 'super_admin') return;
    $perms = getEffectivePermissions($_SESSION['tenant_id'] ?? null, $role);
    if (empty($perms[$key])) _roleFail($role);
}

// ── Check a permission key without throwing (for hiding UI) ────────
function can(string $key): bool {
    $role = $_SESSION['user_role'] ?? 'viewer';
    if ($role === 'super_admin') return true;
    $perms = getEffectivePermissions($_SESSION['tenant_id'] ?? null, $role);
    return !empty($perms[$key]);
}

// ── Current user (from master DB) ────────────────────────────────
function currentUser(): ?array {
    startSession();
    if (empty($_SESSION['user_id'])) return null;
    try {
        $stmt = getMasterDB()->prepare(
            'SELECT u.id, u.name, u.email, u.role, u.avatar, u.phone,
                    u.tenant_id, t.company_name, t.slug AS tenant_slug,
                    t.db_name AS tenant_db, t.plan, t.status AS tenant_status
             FROM users u
             LEFT JOIN tenants t ON t.id = u.tenant_id
             WHERE u.id = ? AND u.status = "active"'
        );
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        error_log('currentUser error: ' . $e->getMessage());
        return null;
    }
}

// ── Login ─────────────────────────────────────────────────────────
function attemptLogin(string $email, string $password): array|false {
    try {
        $db   = getMasterDB();
        $stmt = $db->prepare(
            'SELECT u.*, t.db_name AS tenant_db, t.slug AS tenant_slug,
                    t.company_name, t.status AS tenant_status
             FROM users u
             LEFT JOIN tenants t ON t.id = u.tenant_id
             WHERE u.email = ? AND u.status IN ("active","invited")'
        );
        $stmt->execute([trim($email)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) return false;

        // Check tenant is active (unless super_admin)
        if ($user['role'] !== 'super_admin' && $user['tenant_status'] !== 'active') {
            return false; // suspended/cancelled tenant
        }

        startSession();
        session_regenerate_id(true);

        $_SESSION['user_id']      = $user['id'];
        $_SESSION['user_name']    = $user['name'];
        $_SESSION['user_email']   = $user['email'];
        $_SESSION['user_role']    = $user['role'];
        $_SESSION['tenant_id']    = $user['tenant_id'];
        $_SESSION['tenant_db']    = $user['tenant_db'] ?? null;
        $_SESSION['tenant_slug']  = $user['tenant_slug'] ?? null;
        $_SESSION['company_name'] = $user['company_name'] ?? APP_NAME;
        $_SESSION['last_activity']= time();

        // If first login via invite — activate user
        if ($user['status'] === 'invited') {
            $db->prepare('UPDATE users SET status="active", last_login=NOW(), login_count=login_count+1 WHERE id=?')
               ->execute([$user['id']]);
        } else {
            $db->prepare('UPDATE users SET last_login=NOW(), login_count=login_count+1 WHERE id=?')
               ->execute([$user['id']]);
        }

        masterAuditLog($user['id'], $user['tenant_id'] ?? null, 'login', 'User logged in');
        return $user;

    } catch (Exception $e) {
        error_log('attemptLogin error: ' . $e->getMessage());
        return false;
    }
}

// ── Logout ────────────────────────────────────────────────────────
function doLogout(): void {
    startSession();
    if (!empty($_SESSION['user_id'])) {
        masterAuditLog($_SESSION['user_id'], $_SESSION['tenant_id'] ?? null, 'logout', 'User logged out');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
                  $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── Tenant-scoped activity log (writes to tenant DB) ─────────────
function logActivity(int $userId, string $action, string $entityType,
                     int $entityId, string $details = ''): void {
    try {
        getDB()->prepare(
            'INSERT INTO activity_log
               (user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (?,?,?,?,?,?)'
        )->execute([$userId, $action, $entityType, $entityId,
                    $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) { /* non-fatal */ }
}

// ── Master audit log (writes to master DB) ────────────────────────
function masterAuditLog(int $userId, ?int $tenantId,
                         string $action, string $details = ''): void {
    try {
        getMasterDB()->prepare(
            'INSERT INTO master_audit_log (user_id, tenant_id, action, details, ip)
             VALUES (?,?,?,?,?)'
        )->execute([$userId, $tenantId, $action,
                    $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) { /* non-fatal */ }
}

// ── Settings helper (reads from tenant DB) ────────────────────────
function getSetting(string $key, string $default = ''): string {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    try {
        $stmt = getDB()->prepare('SELECT value FROM settings WHERE `key` = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? (string)$row['value'] : $default;
    } catch (Exception $e) { $cache[$key] = $default; }
    return $cache[$key];
}

// ── JSON response helper ──────────────────────────────────────────
function jsonResponse(mixed $data, int $code = 200): never {
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── Internal helpers ──────────────────────────────────────────────
function _authFail(string $msg, string $redirect): never {
    $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false
          || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
          || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    if ($isApi) {
        jsonResponse(['error' => $msg, 'redirect' => $redirect], 401);
    }
    header('Location: ' . $redirect);
    exit;
}

function _roleFail(string $role): never {
    $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false
          || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
    if ($isApi) {
        jsonResponse(['error' => 'Permission denied', 'role' => $role], 403);
    }
    http_response_code(403);
    echo "<!DOCTYPE html><html><body style='font-family:sans-serif;padding:40px'>
    <h2>Access Denied</h2><p>Your role ({$role}) does not have permission for this action.</p>
    <a href='/'>← Back to Dashboard</a></body></html>";
    exit;
}