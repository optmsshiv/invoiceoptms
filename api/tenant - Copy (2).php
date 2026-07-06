<?php
// ================================================================
//  api/tenant.php — Tenant Management (Super Admin only)
//
//  POST   ?action=create           → provision new tenant + DB
//  POST   ?action=finish_provision → complete setup after a manual
//                                     cPanel privilege grant (see create)
//  GET    ?action=list      → list all tenants
//  GET    ?action=get&id=N  → get single tenant
//  PATCH  ?action=suspend   → suspend tenant
//  PATCH  ?action=activate  → reactivate tenant
//  DELETE ?action=delete    → hard delete (careful!)
//  POST   ?action=add_user  → add user to tenant
//  GET    ?action=users&tenant_id=N → list tenant users
//  PATCH  ?action=update_user       → update user role/status
//  DELETE ?action=remove_user&id=N  → remove user from tenant
// ================================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Read body for POST/PATCH
$body = [];
if (in_array($method, ['POST','PATCH','PUT'])) {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?: [];
    if (empty($body)) $body = $_POST;
}

try {
    $master = getMasterDB();

    // ── LIST tenants ───────────────────────────────────────────────
    if ($method === 'GET' && $action === 'list') {
        $stmt = $master->query(
            'SELECT t.*, COUNT(u.id) AS user_count
             FROM tenants t
             LEFT JOIN users u ON u.tenant_id = t.id AND u.status != "inactive"
             GROUP BY t.id
             ORDER BY t.created_at DESC'
        );
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // ── GET single tenant ──────────────────────────────────────────
    if ($method === 'GET' && $action === 'get') {
        $id   = (int)($_GET['id'] ?? 0);
        $stmt = $master->prepare('SELECT * FROM tenants WHERE id=?');
        $stmt->execute([$id]);
        $tenant = $stmt->fetch();
        if (!$tenant) jsonResponse(['error' => 'Tenant not found'], 404);
        jsonResponse(['success' => true, 'data' => $tenant]);
    }

    // ── LIST tenant users ──────────────────────────────────────────
    if ($method === 'GET' && $action === 'users') {
        $tid  = (int)($_GET['tenant_id'] ?? 0);
        $stmt = $master->prepare(
            'SELECT id, name, email, role, status, last_login, created_at
             FROM users WHERE tenant_id = ? ORDER BY role, name'
        );
        $stmt->execute([$tid]);
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // ── CREATE tenant ──────────────────────────────────────────────
    if ($method === 'POST' && $action === 'create') {
        $name       = trim($body['company_name'] ?? '');
        $slug       = _makeSlug($body['slug'] ?? $name);
        $ownerEmail = trim($body['owner_email'] ?? '');
        $ownerName  = trim($body['owner_name']  ?? '');
        $ownerPass  = $body['password'] ?? _randomPassword();
        $plan       = $body['plan'] ?? 'trial';
        $phone      = trim($body['phone'] ?? '');

        if (!$name || !$ownerEmail) {
            jsonResponse(['error' => 'company_name and owner_email are required'], 400);
        }

        // Validate slug uniqueness
        $slugCheck = $master->prepare('SELECT id FROM tenants WHERE slug=?');
        $slugCheck->execute([$slug]);
        if ($slugCheck->fetch()) {
            $slug = $slug . '_' . substr(uniqid(), -4);
        }

        // Build DB name
        $dbName = 'edrppymy_' . preg_replace('/[^a-z0-9]/', '_', strtolower($slug));
        // Ensure DB name is unique
        $dbCheck = $master->prepare('SELECT id FROM tenants WHERE db_name=?');
        $dbCheck->execute([$dbName]);
        if ($dbCheck->fetch()) {
            $dbName .= '_' . substr(uniqid(), -4);
        }

        // ── Provision tenant DB ──────────────────────────────────
        try {
            _createTenantDatabase($dbName);
            _runTenantSchema($dbName);
            _seedRolePermissions($master, $dbName);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'set_privileges_on_database')) {
                // DB was created but the privilege grant failed (known
                // shared-hosting restriction on this specific UAPI call).
                // Don't lose the work — tell the admin how to finish manually.
                jsonResponse([
                    'success'            => false,
                    'needs_manual_grant' => true,
                    'db_name'            => $dbName,
                    'message'            => "Database '{$dbName}' was created, but the automatic privilege grant "
                                           . "was blocked by your hosting provider. In cPanel → MySQL Databases → "
                                           . "'Add User To Database', grant ALL PRIVILEGES for user '" . MASTER_DB_USER
                                           . "' on database '{$dbName}', then submit this same form again with "
                                           . "action=finish_provision and db_name='{$dbName}' to complete setup.",
                    'resume_payload'     => [
                        'db_name'      => $dbName,
                        'slug'         => $slug,
                        'company_name' => $name,
                        'owner_email'  => $ownerEmail,
                        'owner_name'   => $ownerName,
                        'password'     => $ownerPass,
                        'plan'         => $plan,
                        'phone'        => $phone,
                    ],
                ], 202);
            }
            throw $e; // some other failure (e.g. create_database itself failed) — real error
        }

        jsonResponse(_finalizeTenantCreation(
            $master, $dbName, $slug, $name, $ownerEmail, $ownerName,
            $ownerPass, $plan, $phone, $_SESSION['user_id']
        ));
    }

    // ── FINISH provisioning after a manual privilege grant ──────────
    // Call this after action=create returns needs_manual_grant=true and
    // you've granted privileges to MASTER_DB_USER on db_name in cPanel.
    if ($method === 'POST' && $action === 'finish_provision') {
        $dbName     = trim($body['db_name'] ?? '');
        $slug       = trim($body['slug'] ?? '');
        $name       = trim($body['company_name'] ?? '');
        $ownerEmail = trim($body['owner_email'] ?? '');
        $ownerName  = trim($body['owner_name'] ?? '');
        $ownerPass  = $body['password'] ?? _randomPassword();
        $plan       = $body['plan'] ?? 'trial';
        $phone      = trim($body['phone'] ?? '');

        if (!$dbName || !$slug || !$name || !$ownerEmail) {
            jsonResponse(['error' => 'db_name, slug, company_name and owner_email are required'], 400);
        }

        // Guard against double-finishing the same tenant
        $dbCheck = $master->prepare('SELECT id FROM tenants WHERE db_name=?');
        $dbCheck->execute([$dbName]);
        if ($dbCheck->fetch()) {
            jsonResponse(['error' => "Tenant for database '{$dbName}' already exists"], 409);
        }

        // Will throw if privileges still haven't been granted in cPanel
        _runTenantSchema($dbName);
        _seedRolePermissions($master, $dbName);

        jsonResponse(_finalizeTenantCreation(
            $master, $dbName, $slug, $name, $ownerEmail, $ownerName,
            $ownerPass, $plan, $phone, $_SESSION['user_id']
        ));
    }

    // ── SUSPEND tenant ─────────────────────────────────────────────
    if ($method === 'PATCH' && $action === 'suspend') {
        $id = (int)($body['id'] ?? 0);
        $master->prepare('UPDATE tenants SET status="suspended" WHERE id=?')->execute([$id]);
        masterAuditLog($_SESSION['user_id'], $id, 'tenant_suspended', '');
        jsonResponse(['success' => true]);
    }

    // ── ACTIVATE tenant ────────────────────────────────────────────
    if ($method === 'PATCH' && $action === 'activate') {
        $id = (int)($body['id'] ?? 0);
        $master->prepare('UPDATE tenants SET status="active" WHERE id=?')->execute([$id]);
        masterAuditLog($_SESSION['user_id'], $id, 'tenant_activated', '');
        jsonResponse(['success' => true]);
    }

    // ── ADD user to tenant ─────────────────────────────────────────
    if ($method === 'POST' && $action === 'add_user') {
        $tenantId  = (int)($body['tenant_id'] ?? 0);
        $email     = trim($body['email'] ?? '');
        $name      = trim($body['name']  ?? '');
        $role      = $body['role'] ?? 'sales';
        $password  = $body['password'] ?? _randomPassword();

        $allowedRoles = ['owner','admin','manager','accountant','sales','viewer'];
        if (!in_array($role, $allowedRoles)) {
            jsonResponse(['error' => 'Invalid role'], 400);
        }
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Valid email required'], 400);
        }

        // Check tenant exists
        $tStmt = $master->prepare('SELECT db_name FROM tenants WHERE id=? AND status="active"');
        $tStmt->execute([$tenantId]);
        $tenant = $tStmt->fetch();
        if (!$tenant) jsonResponse(['error' => 'Tenant not found or suspended'], 404);

        $hashedPass = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Check email not already taken
        $emailCheck = $master->prepare('SELECT id FROM users WHERE email=?');
        $emailCheck->execute([$email]);
        if ($emailCheck->fetch()) jsonResponse(['error' => 'Email already in use'], 409);

        // Insert into master users
        $master->prepare(
            'INSERT INTO users (tenant_id, name, email, password, role, status, created_by)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$tenantId, $name, $email, $hashedPass, $role, 'active',
                    $_SESSION['user_id']]);
        $userId = (int)$master->lastInsertId();

        // Mirror into tenant DB users table
        $tenantDb = getDBByName($tenant['db_name']);
        $tenantDb->prepare(
            'INSERT IGNORE INTO users (id, name, email, password, role, is_active)
             VALUES (?,?,?,?,?,1)'
        )->execute([$userId, $name, $email, $hashedPass, $role]);

        masterAuditLog($_SESSION['user_id'], $tenantId, 'user_added',
            "Added user: {$email} ({$role})");

        jsonResponse([
            'success'   => true,
            'user_id'   => $userId,
            'email'     => $email,
            'role'      => $role,
            'temp_pass' => $password,
        ]);
    }

    // ── UPDATE user role/status ────────────────────────────────────
    if ($method === 'PATCH' && $action === 'update_user') {
        $userId = (int)($body['user_id'] ?? 0);
        $field  = $body['field'] ?? '';
        $value  = $body['value'] ?? '';
        if (!in_array($field, ['role','status'])) {
            jsonResponse(['error' => 'Only role and status can be updated'], 400);
        }
        $master->prepare("UPDATE users SET {$field}=? WHERE id=?")->execute([$value, $userId]);
        jsonResponse(['success' => true]);
    }

    // ── REMOVE user ────────────────────────────────────────────────
    if (($method === 'DELETE' || $method === 'PATCH') && $action === 'remove_user') {
        $userId = (int)($body['user_id'] ?? $_GET['id'] ?? 0);
        $master->prepare('UPDATE users SET status="inactive" WHERE id=?')->execute([$userId]);
        jsonResponse(['success' => true]);
    }

    jsonResponse(['error' => 'Unknown action: ' . $action], 400);

} catch (Exception $e) {
    error_log('tenant.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
}

// ── Helpers ────────────────────────────────────────────────────────

function _makeSlug(string $text): string {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
    $slug = trim($slug, '_');
    return $slug ?: 'tenant_' . substr(uniqid(), -6);
}

function _randomPassword(int $len = 12): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789@#!';
    $pass  = '';
    for ($i = 0; $i < $len; $i++) {
        $pass .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pass;
}

// ── cPanel UAPI helper ────────────────────────────────────────────
function _cpanelApi(string $module, string $function, array $params = []): array {
    $url = rtrim(CPANEL_HOST, '/') . "/execute/{$module}/{$function}";
    if ($params) $url .= '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: cpanel ' . CPANEL_USERNAME . ':' . CPANEL_API_TOKEN],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("cPanel API request failed ({$module}::{$function}): {$err}");
    }
    curl_close($ch);

    $data = json_decode($response, true);
    if (!isset($data['status']) || $data['status'] != 1) {
        $msg = $data['errors'][0] ?? ($data['error'] ?? 'Unknown cPanel API error');
        throw new RuntimeException("cPanel API error ({$module}::{$function}): {$msg}");
    }
    return $data;
}

function _createTenantDatabase(string $dbName): void {
    // NOTE: on this cPanel version, create_database expects the FULL
    // prefixed database name (e.g. "edrppymy_acme"), not just the
    // suffix — passing just the suffix throws "does not begin with
    // the required prefix" errors.
    _cpanelApi('Mysql', 'create_database', ['name' => $dbName]);

    // 2) Grant the master DB user full privileges on the new database.
    //    NOTE: on some shared-hosting accounts this specific UAPI call is
    //    blocked server-side (opaque "request failed" with no detail),
    //    even though create_database and the equivalent manual "Add User
    //    to Database" action in cPanel's UI both work fine. If this call
    //    throws, the DB has still been created — the caller can catch
    //    this specific failure, ask the admin to grant privileges
    //    manually in cPanel, then resume via _runTenantSchema().
    _cpanelApi('Mysql', 'set_privileges_on_database', [
        'user'       => MASTER_DB_USER,
        'database'   => $dbName,
        'privileges' => 'ALL',
    ]);
}

// ── Seed default role_permissions for a freshly-provisioned tenant ──
// Mirrors the legacy hardcoded hierarchy (see permissions.default_min_role
// in the master DB) so a brand-new tenant behaves exactly like before,
// until the owner customizes it via the Team > Role Permissions screen.
function _seedRolePermissions(PDO $master, string $dbName): void {
    $catalog = $master->query('SELECT `key`, default_min_role FROM permissions')->fetchAll();
    if (!$catalog) return; // permissions migration not run yet — nothing to seed

    $roles = ['admin','manager','accountant','sales','viewer'];
    $tenantDb = getDBByName($dbName);
    $stmt = $tenantDb->prepare(
        'INSERT IGNORE INTO role_permissions (role, permission_key, enabled) VALUES (?,?,?)'
    );

    foreach ($catalog as $perm) {
        $minWeight = ROLE_WEIGHTS[$perm['default_min_role']] ?? 0;
        foreach ($roles as $role) {
            $enabled = (ROLE_WEIGHTS[$role] ?? 0) >= $minWeight ? 1 : 0;
            $stmt->execute([$role, $perm['key'], $enabled]);
        }
    }
}

function _runTenantSchema(string $dbName): void {
    // Assumes the DB exists and MASTER_DB_USER already has privileges on it
    // (either granted automatically above, or manually in cPanel).
    $pdo = new PDO(
        'mysql:host=' . MASTER_DB_HOST . ';dbname=' . $dbName . ';charset=utf8mb4',
        MASTER_DB_USER, MASTER_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $schemaFile = __DIR__ . '/../config/tenant_schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException("tenant_schema.sql not found at: {$schemaFile}");
    }

    // Strip SQL line comments (-- ...) and block comments (/* ... */) BEFORE
    // splitting on ';'. phpMyAdmin dumps prefix nearly every real statement
    // with a comment block, so checking only the start of each ;-delimited
    // chunk was discarding almost all CREATE TABLE / ALTER TABLE statements.
    $sql = file_get_contents($schemaFile);
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    // Naive explode(';', $sql) breaks any CREATE TRIGGER/PROCEDURE/FUNCTION
    // body that contains its own internal ';' inside a BEGIN...END block
    // (e.g. trg_backup_payment_before_delete) — it gets cut into two
    // invalid fragments. _splitSqlStatements() only splits on ';' that are
    // NOT inside a BEGIN...END block, so such statements stay intact.
    $statements = _splitSqlStatements($sql);
    foreach ($statements as $stmt) {
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            // Skip "already exists" errors during provisioning
            if ($e->getCode() !== '42S01') throw $e;
        }
    }
}

// ── Split a SQL script into individual statements, respecting
//    BEGIN...END blocks (triggers/procedures/functions) so a ';'
//    inside a trigger body doesn't get treated as a statement end. ──
function _splitSqlStatements(string $sql): array {
    $statements = [];
    $buffer     = '';
    $depth      = 0;
    foreach (preg_split('/\r\n|\r|\n/', $sql) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;
        $buffer .= $line . "\n";
        if (preg_match('/\bBEGIN\b/i', $line)) $depth++;
        if (preg_match('/\bEND\b/i', $line))   $depth--;
        if ($depth <= 0 && str_ends_with(rtrim($trimmed), ';')) {
            $clean = trim($buffer);
            if ($clean !== '' && $clean !== ';') $statements[] = $clean;
            $buffer = '';
            $depth  = 0;
        }
    }
    $clean = trim($buffer);
    if ($clean !== '') $statements[] = $clean;
    return $statements;
}

// ── Insert tenant + owner user rows once the DB + schema are ready ─
function _finalizeTenantCreation(
    PDO $master, string $dbName, string $slug, string $name,
    string $ownerEmail, string $ownerName, string $ownerPass,
    string $plan, string $phone, int $actingUserId
): array {
    $master->prepare(
        'INSERT INTO tenants (slug, company_name, db_name, plan, owner_email, owner_name, phone, created_by)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([$slug, $name, $dbName, $plan, $ownerEmail, $ownerName, $phone, $actingUserId]);
    $tenantId = (int)$master->lastInsertId();

    $hashedPass = password_hash($ownerPass, PASSWORD_BCRYPT, ['cost' => 12]);
    $master->prepare(
        'INSERT INTO users (tenant_id, name, email, password, role, status, created_by)
         VALUES (?,?,?,?,?,?,?)'
    )->execute([$tenantId, $ownerName ?: $name, $ownerEmail, $hashedPass, 'owner', 'active', $actingUserId]);
    $userId = (int)$master->lastInsertId();

    $tenantDb = getDBByName($dbName);
    $tenantDb->prepare(
        'INSERT IGNORE INTO users (id, name, email, password, role, is_active)
         VALUES (?,?,?,?,?,1)'
    )->execute([$userId, $ownerName ?: $name, $ownerEmail, $hashedPass, 'owner']);

    $tenantDb->prepare(
        'INSERT INTO settings (`key`, value) VALUES ("company_name", ?)
         ON DUPLICATE KEY UPDATE value=?'
    )->execute([$name, $name]);

    masterAuditLog($actingUserId, $tenantId, 'tenant_created', "Created tenant: {$name} (DB: {$dbName})");

    return [
        'success'     => true,
        'tenant_id'   => $tenantId,
        'db_name'     => $dbName,
        'slug'        => $slug,
        'owner_email' => $ownerEmail,
        'temp_pass'   => $ownerPass,
        'message'     => "Tenant '{$name}' created. Share credentials with the client.",
    ];
}