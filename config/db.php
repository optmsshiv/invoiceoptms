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
define('APP_URL',     'http://inv.optms.co.in');  // ← your live domain

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
        // ── Auto-migrate: add 'Estimate' to status ENUM ─────────────────
        // Strategy 1: temporarily disable strict mode so the ALTER always succeeds
        // even if the user lacks full ALTER privilege on the column definition.
        // Strategy 2: if ALTER still fails, patch any existing '' rows via UPDATE
        // so they never show as blank again (PHP-level workaround).
        static $enumChecked = false;
        if (!$enumChecked) {
            $enumChecked = true;
            $enumOk = false;
            try {
                // Disable strict mode for this session so ALTER won't error on existing '' values
                $pdo->exec("SET SESSION sql_mode = REPLACE(@@sql_mode, 'STRICT_TRANS_TABLES', '')");
                $pdo->exec("SET SESSION sql_mode = REPLACE(@@sql_mode, 'STRICT_ALL_TABLES', '')");
                $pdo->exec("ALTER TABLE invoices MODIFY COLUMN status ENUM('Draft','Pending','Paid','Overdue','Partial','Cancelled','Estimate') NOT NULL DEFAULT 'Draft'");
                $enumOk = true;
            } catch (\Exception $e) {
                error_log('Auto-migrate status ENUM failed (will use UPDATE fallback): ' . $e->getMessage());
            }
            if (!$enumOk) {
                // Fallback: directly UPDATE any blank-status rows that look like estimates
                // (invoice_number starts with QT-) and any other blanks → Draft
                try {
                    $pdo->exec("UPDATE invoices SET status = 'Estimate' WHERE (status = '' OR status IS NULL) AND invoice_number LIKE 'QT-%'");
                    $pdo->exec("UPDATE invoices SET status = 'Draft'    WHERE (status = '' OR status IS NULL)");
                } catch (\Exception $e2) {
                    error_log('Status blank-row patch failed: ' . $e2->getMessage());
                }
            } else {
                // ENUM was updated — patch any lingering '' rows from before the fix
                try {
                    $pdo->exec("UPDATE invoices SET status = 'Estimate' WHERE status = '' AND invoice_number LIKE 'QT-%'");
                    $pdo->exec("UPDATE invoices SET status = 'Draft'    WHERE status = ''");
                } catch (\Exception $e3) { /* ignore */ }
            }
        }
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