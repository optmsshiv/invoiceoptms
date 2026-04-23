<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Find where autoload.php actually is
$out = shell_exec('find /home1/edrppymy -name "autoload.php" -path "*/vendor/*" 2>/dev/null');
echo "<pre>" . htmlspecialchars($out) . "</pre>";
exit;