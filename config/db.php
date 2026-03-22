<?php
// ================================================================
//  OPTMS Invoice Manager — config/db.php
//  Edit the 4 lines marked ← before uploading
// ================================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'optms_invoice');   // ← your database name
define('DB_USER',    'root');            // ← your MySQL username
define('DB_PASS',    '');               // ← your MySQL password
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'OPTMS Tech Invoice Manager');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://inv.optms.co.in');  // ← your domain (no trailing slash)

define('SESSION_LIFETIME', 7200);
define('UPLOAD_MAX_SIZE',  3145728);
define('UPLOAD_PATH',      __DIR__ . '/../assets/uploads/');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database unavailable']);
        } else {
            echo '<!DOCTYPE html><html><head><title>DB Error</title>
            <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f5f6fa}
            .b{text-align:center;padding:40px;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.1)}</style></head>
            <body><div class="b"><h2 style="color:#e53935">Database Error</h2>
            <p>Could not connect. Please check <code>config/db.php</code></p></div></body></html>';
        }
        exit;
    }
    return $pdo;
}
