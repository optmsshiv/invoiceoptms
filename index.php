<?php
// ================================================================
//  index.php — root entry point
//  Now just a thin redirect into the MPA: logged-in users go to
//  the dashboard, everyone else goes to the login page.
//
//  This used to be the full single-page app (~24,900 lines). That
//  version is preserved at index.php.old-spa-backup if you ever
//  need to reference it, but it's no longer wired into .htaccess
//  or linked from anywhere — pages/ is the live app now.
// ================================================================
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

startSession();

if (empty($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

header('Location: /dashboard.php');
exit;
