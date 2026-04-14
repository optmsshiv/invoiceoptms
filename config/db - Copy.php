<?php
// ================================================================
//  OPTMS Invoice Manager — config/db.php
//  Edit DB_NAME, DB_USER, DB_PASS before deploying
// ================================================================

// Start output buffering immediately so no stray whitespace leaks into JSON responses
if (!ob_get_level()) ob_start();

define('DB_HOST',    'localhost');
define('DB_NAME',    'edrppymy_optms_invoice');   // ← your database name
define('DB_USER',    'edrppymy_optms_invoice');            // ← your MySQL username
define('DB_PASS',    '1234@Optmsdatabase');               // ← your MySQL password
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'OPTMS Tech Invoice Manager');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://invcs.optms.co.in');  // ← your live domain

define('SESSION_LIFETIME', 7200);
define('UPLOAD_MAX_SIZE',  3145728);
define('UPLOAD_PATH',      __DIR__ . '/../assets/uploads/');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        // Auto-migrate: ensure 'Estimate' and 'Partial' are in the status ENUM (safe to run repeatedly)
        try {
            $pdo->exec("ALTER TABLE invoices MODIFY COLUMN status ENUM('Draft','Pending','Paid','Overdue','Partial','Cancelled','Estimate') NOT NULL DEFAULT 'Draft'");
        } catch (\Exception $e) {
            // Ignore — may already include these values, or DB user may lack ALTER privilege
            error_log('Auto-migrate status ENUM: ' . $e->getMessage());
        }
        // Secondary safety: verify 'Estimate' is accepted by attempting a dry-run SELECT
        // (If ENUM is missing Estimate, rows with status=Estimate are stored as '' in MySQL strict mode)
        // We expose this via a column check so devs can diagnose:
        try {
            $col = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'status'")->fetch(\PDO::FETCH_ASSOC);
            if ($col && strpos($col['Type'], 'Estimate') === false) {
                error_log('WARNING: invoices.status ENUM is missing Estimate. Run: ALTER TABLE invoices MODIFY COLUMN status ENUM(\'Draft\',\'Pending\',\'Paid\',\'Overdue\',\'Partial\',\'Cancelled\',\'Estimate\') NOT NULL DEFAULT \'Draft\'');
            }
        } catch (\Exception $e) { /* ignore */ }
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        while (ob_get_level()) ob_end_clean();
        $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
        if ($isApi) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database connection failed']);
        } else {
            http_response_code(500);
            echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px">
            <h2 style="color:#e53935">Database Error</h2>
            <p>Cannot connect. Check <code>config/db.php</code> credentials.</p>
            </body></html>';
        }
        exit;
    }
    return $pdo;
}