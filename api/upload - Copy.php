<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$type    = $_POST['type'] ?? 'logo'; // logo | signature | qr | client_logo
$allowed = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
$file    = $_FILES['file'] ?? null;

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => 'No file uploaded or upload error'], 400);
}
if ($file['size'] > UPLOAD_MAX_SIZE) {
    jsonResponse(['error' => 'File too large (max 3MB)'], 400);
}

// Detect MIME
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, $allowed)) {
    jsonResponse(['error' => 'Invalid file type. Use JPG, PNG, GIF, WebP or SVG'], 400);
}

// Create upload directory if needed
$uploadDir = UPLOAD_PATH;
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Generate unique filename
$ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = $type . '_' . $_SESSION['user_id'] . '_' . time() . '.' . strtolower($ext);
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    jsonResponse(['error' => 'Failed to save file'], 500);
}

// Return public URL
$publicUrl = APP_URL . '/assets/uploads/' . $filename;

// If it's company logo or signature, auto-save to settings
if (in_array($type, ['logo', 'signature'])) {
    $settingKey = $type === 'logo' ? 'company_logo' : 'company_sign';
    $db = getDB();
    $db->prepare('INSERT INTO settings (`key`, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?')
       ->execute([$settingKey, $publicUrl, $publicUrl]);
}

logActivity($_SESSION['user_id'], 'upload', 'file', 0, "Uploaded $type: $filename");
jsonResponse(['success' => true, 'url' => $publicUrl, 'filename' => $filename]);
