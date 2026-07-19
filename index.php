<?php
// ================================================================
//  index.php — MPA cutover entry point
//
//  This file used to render the entire 30,000-line SPA directly.
//  As of the full MPA cutover, it just checks login and redirects:
//    not logged in -> /auth/login.php (via requireLogin() itself)
//    logged in      -> /dashboard.php (lives at project root)
//
//  The original SPA is preserved as index_spa_backup.php in case
//  you need to roll back — it's fully self-contained and still
//  works if you rename it back to index.php. It is NOT linked from
//  anywhere and .htaccess does not route to it, so it's inert
//  sitting on the server (just dead weight, not a security issue
//  beyond normal PHP-file-on-server exposure — consider deleting it
//  once you're confident you won't need to roll back).
// ================================================================

date_default_timezone_set('Asia/Kolkata');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin(); // redirects to /auth/login.php itself if not logged in

header('Location: /dashboard.php');
exit;
