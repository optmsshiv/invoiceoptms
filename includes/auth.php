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

    // Already locked (from a previous idle timeout, another tab, or a
    // proactive client-triggered lock) — don't let any protected action
    // through until the correct password is re-entered.
    if (!empty($_SESSION['locked'])) {
        // Safety net: if a locked session sits unattended too long, force
        // a real logout rather than leaving it locked forever.
        if (!empty($_SESSION['locked_at']) &&
            (time() - $_SESSION['locked_at']) > LOCK_MAX_DURATION) {
            doLogout();
            _authFail('Session expired', '/auth/login.php');
        }
        _lockFail();
    }

    // Idle timeout → lock the session instead of destroying it.
    if (!empty($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        lockSession();
        _lockFail();
    }
    $_SESSION['last_activity'] = time();

    // Re-issue the session cookie with a fresh expiry so the browser-side
    // cookie lifetime slides along with server-side activity, instead of
    // expiring at a fixed time (login time + SESSION_LIFETIME) regardless
    // of how active the user is.
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + SESSION_LIFETIME,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
}

// ── Lock the current session (idle timeout, or user-triggered) ────
// Keeps $_SESSION intact (user stays "logged in" server-side) but blocks
// every protected action until unlockSession() succeeds.
function lockSession(): void {
    startSession();
    if (!empty($_SESSION['user_id']) && empty($_SESSION['locked'])) {
        $_SESSION['locked']          = true;
        $_SESSION['locked_at']       = time();
        $_SESSION['unlock_attempts'] = 0;
        masterAuditLog($_SESSION['user_id'], $_SESSION['tenant_id'] ?? null,
                        'lock', 'Session locked (idle timeout)');
    }
}

function isLocked(): bool {
    startSession();
    return !empty($_SESSION['locked']);
}

// ── Attempt to unlock the current session with the user's password ──
// Returns an array: ['ok' => bool, 'reason' => string, 'attempts_left' => int]
function unlockSession(string $password): array {
    startSession();
    if (empty($_SESSION['user_id'])) {
        return ['ok' => false, 'reason' => 'no_session'];
    }

    $_SESSION['unlock_attempts'] = ($_SESSION['unlock_attempts'] ?? 0) + 1;
    if ($_SESSION['unlock_attempts'] > 5) {
        masterAuditLog($_SESSION['user_id'], $_SESSION['tenant_id'] ?? null,
                        'lock_failed', 'Too many failed unlock attempts — forced logout');
        doLogout();
        return ['ok' => false, 'reason' => 'too_many_attempts'];
    }

    try {
        $stmt = getMasterDB()->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $hash = $stmt->fetchColumn();
        if (!$hash || !password_verify($password, $hash)) {
            return [
                'ok'            => false,
                'reason'        => 'wrong_password',
                'attempts_left' => max(0, 5 - $_SESSION['unlock_attempts']),
            ];
        }
    } catch (Exception $e) {
        error_log('unlockSession error: ' . $e->getMessage());
        return ['ok' => false, 'reason' => 'error'];
    }

    // Correct password — unlock and refresh the session as if freshly active.
    $_SESSION['locked']          = false;
    $_SESSION['unlock_attempts'] = 0;
    unset($_SESSION['locked_at']);
    $_SESSION['last_activity'] = time();

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + SESSION_LIFETIME,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }

    masterAuditLog($_SESSION['user_id'], $_SESSION['tenant_id'] ?? null, 'unlock', 'Session unlocked');
    return ['ok' => true];
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
// NOTE: assumes `address` and `alt_phone` columns exist on the master
// `users` table. If your schema predates the profile-page redesign,
// run: ALTER TABLE users ADD COLUMN address VARCHAR(255) NULL,
//                        ADD COLUMN alt_phone VARCHAR(30) NULL;
function currentUser(): ?array {
    startSession();
    if (empty($_SESSION['user_id'])) return null;
    try {
        $stmt = getMasterDB()->prepare(
            'SELECT u.id, u.name, u.email, u.role, u.avatar, u.phone, u.alt_phone,
                    u.address, u.created_at,
                    u.is_verified, u.license_no, u.license_expiry,
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

// ── Recent login/logout activity for a user (master DB) ───────────
// Feeds the "Activity Timeline" card on the profile page.
// Assumes master_audit_log has a `created_at` timestamp column
// (default CURRENT_TIMESTAMP) — adjust the ORDER BY/select if yours
// is named differently.
function getRecentLoginActivity(int $userId, int $limit = 20): array {
    try {
        $stmt = getMasterDB()->prepare(
            'SELECT action, ip, created_at
             FROM master_audit_log
             WHERE user_id = ? AND action IN ("login","logout")
             ORDER BY created_at DESC, id DESC
             LIMIT ' . (int)$limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('getRecentLoginActivity error: ' . $e->getMessage());
        return [];
    }
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

// ── Session-timeout frontend config ───────────────────────────────
// Usage: call renderSessionTimeoutAssets() once in your authenticated
// layout (e.g. right before the closing </body> tag) to wire up
// assets/js/session-timeout.js.
function renderSessionTimeoutAssets(int $warningSeconds = 120): void {
    if (empty($_SESSION['user_id'])) return; // only needed once logged in
    $lifetime = SESSION_LIFETIME;
    $userName = htmlspecialchars($_SESSION['user_name'] ?? '', ENT_QUOTES);
    echo <<<HTML
    <script>
      window.OPTMS_SESSION_CONFIG = {
        lifetime: {$lifetime},
        warningSecs: {$warningSeconds},
        keepaliveUrl: '/includes/session_keepalive.php',
        lockUrl: '/includes/session_lock.php',
        unlockUrl: '/includes/session_unlock.php',
        loginUrl: '/auth/login.php',
        logoutUrl: '/auth/logout.php',
        userName: "{$userName}"
      };
    </script>
    <script src="/assets/js/session-timeout.js"></script>
    HTML;
}

// ── JSON response helper ──────────────────────────────────────────
function jsonResponse($data, int $code = 200): void {
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── Internal helpers ──────────────────────────────────────────────
function _lockFail(): void {
    $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false
          || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
          || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    if ($isApi) {
        jsonResponse(['error' => 'Session locked', 'locked' => true, 'redirect' => '/auth/locked.php'], 423);
    }
    header('Location: /auth/locked.php?return=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit;
}

function _authFail(string $msg, string $redirect): void {
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

function _roleFail(string $role): void {
    $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false
          || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
    if ($isApi) {
        jsonResponse(['error' => 'Permission denied', 'role' => $role], 403);
    }
    http_response_code(403);

    // Styled 403 page — adjust this path if error_403.php lives elsewhere
    // in your folder structure. Falls back to plain HTML if not found.
    $errorPage = __DIR__ . '/../auth/error_403.php';
    if (file_exists($errorPage)) {
        $roleFailReason = "Your role ({$role}) does not have permission for this action.";
        require $errorPage;
    } else {
        echo "<!DOCTYPE html><html><body style='font-family:sans-serif;padding:40px'>
        <h2>Access Denied</h2><p>Your role ({$role}) does not have permission for this action.</p>
        <a href='/'>← Back to Dashboard</a></body></html>";
    }
    exit;
}