<?php
// ================================================================
//  OPTMS Invoice Manager — config/db.php
//  Multi-tenant version — separate DB per tenant
// ================================================================

if (!ob_get_level()) ob_start();

// ── Master DB credentials ─────────────────────────────────────────
define('MASTER_DB_HOST',    'localhost');
define('MASTER_DB_NAME',    'edrppymy_optms_master');
define('MASTER_DB_USER',    'edrppymy_optms_master');
define('MASTER_DB_PASS',    '123@optms_master');
define('MASTER_DB_CHARSET', 'utf8mb4');

// ── cPanel API credentials (used for tenant DB provisioning) ───────
// Fill these in with your NEW token — generate it fresh in
// cPanel → Security → Manage API Tokens, since any token pasted
// into a chat should be treated as compromised and revoked.
define('CPANEL_HOST',      'https://invcs.optms.co.in:2083'); // confirm port (usually 2083)
define('CPANEL_USERNAME',  'edrppymy');                         // confirm this is your cPanel login username
define('CPANEL_API_TOKEN', 'BMN3WI0RGV07SO1GEHZCSTSLWP337Y97');

// ── App constants ─────────────────────────────────────────────────
define('APP_NAME',    'OPTMS Tech Invoice Manager');
define('APP_VERSION', '2.0.0');
define('APP_URL',     'https://invcs.optms.co.in');

define('SESSION_LIFETIME', 7200);
define('UPLOAD_MAX_SIZE',  3145728);
define('UPLOAD_PATH',      __DIR__ . '/../assets/uploads/');

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

    // super_admin with no tenant context → use master
    if (!$targetDb) return getMasterDB();

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