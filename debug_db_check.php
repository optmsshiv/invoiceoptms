<?php
// ================================================================
//  TEMPORARY DEBUG SCRIPT — delete this file immediately after use.
//  Do NOT leave this on a production server.
//
//  Upload to your project ROOT (same level as config/, includes/, .env)
//  and open it in the browser, e.g.:
//    https://invcs.optms.co.in/debug_db_check.php
// ================================================================

require_once __DIR__ . '/config/env.php';

echo "<pre style='font-family:monospace;font-size:14px;padding:20px'>";

echo "1) Looking for .env at: " . __DIR__ . "/.env\n";
echo "   File exists? " . (is_file(__DIR__ . '/.env') ? "YES\n" : "NO  <-- PROBLEM: .env not found here\n");
echo "   File readable? " . (is_readable(__DIR__ . '/.env') ? "YES\n" : "NO  <-- PROBLEM: permissions issue\n");

loadEnv(__DIR__ . '/.env');

echo "\n2) Environment variables loaded:\n";
$vars = ['MASTER_DB_HOST','MASTER_DB_NAME','MASTER_DB_USER','MASTER_DB_PASS','MASTER_DB_CHARSET'];
foreach ($vars as $v) {
    $val = getenv($v);
    if ($val === false) {
        echo "   {$v} = <NOT SET>  <-- PROBLEM\n";
    } elseif ($v === 'MASTER_DB_PASS') {
        echo "   {$v} = " . str_repeat('*', strlen($val)) . " (length " . strlen($val) . ")\n";
    } else {
        echo "   {$v} = {$val}\n";
    }
}

echo "\n3) Attempting raw PDO connection with these values...\n";
try {
    $pdo = new PDO(
        'mysql:host=' . getenv('MASTER_DB_HOST') .
        ';dbname='    . getenv('MASTER_DB_NAME') .
        ';charset='   . (getenv('MASTER_DB_CHARSET') ?: 'utf8mb4'),
        getenv('MASTER_DB_USER'),
        getenv('MASTER_DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "   SUCCESS — connected to database.\n";
    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    echo "   users table row count: " . $stmt->fetchColumn() . "\n";
} catch (PDOException $e) {
    echo "   FAILED — real PDO error:\n";
    echo "   " . $e->getMessage() . "\n";
}

echo "</pre>";
