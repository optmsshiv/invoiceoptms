<?php
// ═══════════════════════════════════════════════════════
//  OPTMS Invoice Manager — Auth Helper
// ═══════════════════════════════════════════════════════

require_once __DIR__ . '/../config/db.php';

// Start session safely
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

// Check if user is logged in; redirect if not
function requireLogin(): void {
    startSession();
    if (empty($_SESSION['user_id'])) {
        header('Location: /auth/login.php');
        exit;
    }
    // Refresh session timeout on activity
    $_SESSION['last_activity'] = time();
}

// Get current user array
function currentUser(): ?array {
    startSession();
    if (empty($_SESSION['user_id'])) return null;
    $db   = getDB();
    $stmt = $db->prepare('SELECT id, name, email, role, avatar FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

// Attempt login — returns user array or false
function attemptLogin(string $email, string $password): array|false {
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
}

// Logout
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

// Log activity
function logActivity(int $userId, string $action, string $entityType, int $entityId, string $details = ''): void {
    try {
        $db   = getDB();
        $stmt = $db->prepare('INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$userId, $action, $entityType, $entityId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) { /* non-fatal */ }
}

// Get a single setting value
function getSetting(string $key, string $default = ''): string {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT value FROM settings WHERE `key` = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? (string)$row['value'] : $default;
    } catch (Exception $e) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

// JSON response helper for API endpoints
function jsonResponse(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
