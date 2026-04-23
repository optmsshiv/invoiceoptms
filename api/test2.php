<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/vendor/autoload.php';

echo "Autoload loaded OK<br>";
echo "Class exists: " . (class_exists('\Mpdf\Mpdf') ? 'YES' : 'NO') . "<br>";

// Show what composer knows about mpdf
$vendorDir = dirname(__DIR__) . '/vendor';
$autoloadFile = $vendorDir . '/composer/autoload_classmap.php';
$map = require $autoloadFile;
$found = array_filter(array_keys($map), fn($k) => stripos($k, 'mpdf') !== false);
echo "<pre>mPDF classes found:\n" . implode("\n", array_slice($found, 0, 10)) . "</pre>";