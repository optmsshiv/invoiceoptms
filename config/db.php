<?php
// ═══════════════════════════════════════════════════════
//  OPTMS Invoice Manager — Database Configuration
// ═══════════════════════════════════════════════════════

define('DB_HOST', 'localhost');
define('DB_NAME', 'optms_invoice');
define('DB_USER', 'edrppymy_optms_invoice');          // ← Change to your MySQL username
define('DB_PASS', '1234@Optmsdatabase');              // ← Change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'OPTMS Tech Invoice Manager');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/optms_invoice'); // ← Change to your domain

// Session settings
define('SESSION_LIFETIME', 7200); // 2 hours
define('UPLOAD_MAX_SIZE',  3145728); // 3MB
define('UPLOAD_PATH',      __DIR__ . '/../assets/uploads/');

// ── PDO Connection ──────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
    }
    return $pdo;
}
