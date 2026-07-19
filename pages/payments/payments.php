<?php
// ================================================================
//  pages/payments/payments.php
//  Old unified Payments page — split into payments-product.php and
//  payments-service.php (routed by business_type). Kept as a thin
//  redirect stub so any existing bookmarks/links to this exact URL
//  still land somewhere correct, rather than 404ing.
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$settingsRows = [];
try {
    $rows = getDB()->query('SELECT `key`, value FROM settings')->fetchAll();
    foreach ($rows as $r) $settingsRows[$r['key']] = $r['value'];
} catch (Exception $e) {
    error_log('Settings load error: ' . $e->getMessage());
}
$businessType = $settingsRows['business_type'] ?? 'both';

header('Location: ' . ($businessType === 'product'
    ? '/pages/payments/payments-product.php'
    : '/pages/payments/payments-service.php'));
exit;
