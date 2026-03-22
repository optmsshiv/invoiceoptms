<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
doLogout();
header('Location: /auth/login.php');
exit;
