<?php
require_once __DIR__ . '/../includes/auth.php';
doLogout();
header('Location: ' . APP_URL . '/auth/login.php?msg=logged_out');
exit;
