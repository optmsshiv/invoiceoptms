<?php
// ================================================================
//  OPTMS Invoice Manager — includes/auth.php
// ================================================================
require_once __DIR__ . '/../config/db.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,   // set true only if HTTPS
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// For regular pages — redirects to login if not authenticated
function requireLogin(): void {
    startSession();
    if (empty($_SESSION['user_id'])) {
        // If this is an API/AJAX request, return JSON 401 instead of redirect
        $isApi = (
            strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false ||
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        );
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Not authenticated', 'redirect' => '/auth/login.php']);
            exit;
        }
        header('Location: /auth/login.php');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function currentUser(): ?array {
    startSession();
    if (empty($_SESSION['user_id'])) return null;
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, name, email, role, avatar FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) { return null; }
}

function attemptLogin(string $email, string $password): array|false {
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([trim($email)]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password'])) return false;
        startSession();
        session_regenerate_id(true);
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_name']     = $user['name'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['user_role']     = $user['role'];
        $_SESSION['last_activity'] = time();
        logActivity($user['id'], 'login', 'user', $user['id'], 'User logged in');
        return $user;
    } catch (Exception $e) { return false; }
}

function doLogout(): void {
    startSession();
    if (!empty($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], 'User logged out');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function logActivity(int $userId, string $action, string $entityType, int $entityId, string $details = ''): void {
    try {
        $db   = getDB();
        $db->prepare('INSERT INTO activity_log (user_id,action,entity_type,entity_id,details,ip_address) VALUES (?,?,?,?,?,?)')
           ->execute([$userId, $action, $entityType, $entityId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) { /* non-fatal */ }
}

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

function jsonResponse(mixed $data, int $code = 200): never {
    // Clear any output buffer to prevent stray whitespace/HTML before JSON
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
