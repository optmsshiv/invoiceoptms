<?php
// ================================================================
//  OPTMS Invoice Manager — config/db.php
//  Multi-tenant version — separate DB per tenant
// ================================================================

if (!ob_get_level()) ob_start();

require_once __DIR__ . '/env.php';
loadEnv(__DIR__ . '/../.env');

// ── Master DB credentials ─────────────────────────────────────────
define('MASTER_DB_HOST',    env('MASTER_DB_HOST', 'localhost'));
define('MASTER_DB_NAME',    env('MASTER_DB_NAME'));
define('MASTER_DB_USER',    env('MASTER_DB_USER'));
define('MASTER_DB_PASS',    env('MASTER_DB_PASS'));
define('MASTER_DB_CHARSET', env('MASTER_DB_CHARSET', 'utf8mb4'));

// ── cPanel API credentials (used for tenant DB provisioning) ───────
define('CPANEL_HOST',      env('CPANEL_HOST'));
define('CPANEL_USERNAME',  env('CPANEL_USERNAME'));
define('CPANEL_API_TOKEN', env('CPANEL_API_TOKEN'));

// ── App constants ─────────────────────────────────────────────────
define('APP_NAME',    env('APP_NAME', 'OPTMS Tech Invoice Manager'));
define('APP_VERSION', env('APP_VERSION', '2.0.0'));
define('APP_URL',     env('APP_URL'));

define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', 7200));
define('UPLOAD_MAX_SIZE',  (int) env('UPLOAD_MAX_SIZE', 3145728));
define('UPLOAD_PATH',      __DIR__ . '/../assets/uploads/');

// Fail loudly (in logs) rather than silently connecting to nothing
// if required secrets are missing from .env
foreach (['MASTER_DB_NAME', 'MASTER_DB_USER', 'MASTER_DB_PASS'] as $required) {
    if (constant($required) === null || constant($required) === '') {
        error_log("db.php: required env var {$required} is missing — check your .env file");
    }
}


// ── Role hierarchy ────────────────────────────────────────────────
// Higher number = more permissions
define('ROLE_WEIGHTS', [
    'viewer'      => 1,
    'sales'       => 2,
    'accountant'  => 3,
    'manager'     => 4,
    'admin'       => 5,
    'owner'       => 6,
    'super_admin' => 99,
]);

// ── Master DB connection (always optms_master) ────────────────────
function getMasterDB(): PDO {
    static $masterPdo = null;
    if ($masterPdo !== null) return $masterPdo;
    try {
        $masterPdo = new PDO(
            'mysql:host=' . MASTER_DB_HOST .
            ';dbname='    . MASTER_DB_NAME .
            ';charset='   . MASTER_DB_CHARSET,
            MASTER_DB_USER, MASTER_DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        error_log('Master DB connection failed: ' . $e->getMessage());
        _dbError('Master database connection failed');
    }
    return $masterPdo;
}

// ── Tenant DB connection (switches per session) ───────────────────
// Returns PDO connected to the current tenant's DB.
// If no tenant in session (e.g. super_admin), returns master DB.
function getDB(): PDO {
    static $tenantPdo  = null;
    static $currentDb  = null;

    // Resolve which DB to use
    $targetDb = null;
    if (session_status() !== PHP_SESSION_NONE) {
        $targetDb = $_SESSION['tenant_db'] ?? null;
    }

    // super_admin with no tenant context → throw, don't hard-exit.
    // This must be CATCHABLE: index.php's settings loader wraps getDB()
    // in try/catch and should degrade gracefully (matching pre-fix
    // behavior), while api/*.php endpoints (which already wrap their
    // logic in try/catch and call jsonResponse on error) will turn this
    // into a clean JSON error instead of quietly hitting master.
    if (!$targetDb) {
        throw new RuntimeException('No database is connected to this session. Super admin: use "Connect Database" first.');
    }

    // Re-use if same DB
    if ($tenantPdo !== null && $currentDb === $targetDb) return $tenantPdo;

    try {
        $tenantPdo = new PDO(
            'mysql:host=' . MASTER_DB_HOST .
            ';dbname='    . $targetDb .
            ';charset=utf8mb4',
            MASTER_DB_USER, MASTER_DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        $currentDb = $targetDb;
    } catch (PDOException $e) {
        error_log("Tenant DB [{$targetDb}] connection failed: " . $e->getMessage());
        _dbError("Tenant database connection failed. Please contact support.");
    }
    return $tenantPdo;
}

// ── Connect to a specific DB by name (used during provisioning) ───
function getDBByName(string $dbName): PDO {
    try {
        return new PDO(
            'mysql:host=' . MASTER_DB_HOST .
            ';dbname='    . $dbName .
            ';charset=utf8mb4',
            MASTER_DB_USER, MASTER_DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        throw new RuntimeException("Cannot connect to [{$dbName}]: " . $e->getMessage());
    }
}

// ── DB error handler ──────────────────────────────────────────────
function _dbError(string $message): never {
    while (ob_get_level()) ob_end_clean();
    $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false
          || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
    if ($isApi) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
    } else {
        http_response_code(500);
        echo "<!DOCTYPE html><html><body style='font-family:sans-serif;padding:40px'>
        <h2 style='color:#e53935'>Database Error</h2>
        <p>{$message}</p></body></html>";
    }
    exit;
}