<?php
// ================================================================
//  OPTMS Invoice Manager — api/wa_webhook.php
//  WhatsApp Business API Webhook Receiver
// ================================================================

// Buffer ALL output — prevents any stray whitespace/BOM/error from
// db.php or other includes corrupting the challenge response
ob_start();

date_default_timezone_set('Asia/Kolkata');
ini_set('display_errors', '0');   // Never leak PHP errors to Meta
ini_set('log_errors', '1');
error_reporting(E_ALL);

function getVerifyToken(): string {
    try {
        require_once __DIR__ . '/../config/db.php';
        $db   = getDB();
        $stmt = $db->query("SELECT `value` FROM `settings` WHERE `key` = 'wa_webhook_token' LIMIT 1");
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($row['value'])) return trim($row['value']);
    } catch (Throwable $e) {
        error_log('[WA_WEBHOOK] DB token read failed: ' . $e->getMessage());
    }
    if (!empty($_ENV['WA_WEBHOOK_TOKEN']))  return trim($_ENV['WA_WEBHOOK_TOKEN']);
    if (!empty(getenv('WA_WEBHOOK_TOKEN'))) return trim(getenv('WA_WEBHOOK_TOKEN'));
    return '';
}

// ... rest of file ...

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $mode      = $_GET['hub_mode']         ?? '';
    $token     = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge']    ?? '';

    $verifyToken = getVerifyToken();

    error_log("[WA_WEBHOOK] Verify | mode={$mode} | received={$token} | stored={$verifyToken}");

    if (!$verifyToken) {
        ob_end_clean();   // ← discard any buffered output
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Webhook verify token not configured']);
        exit;
    }

    if ($mode === 'subscribe' && $token === $verifyToken) {
        ob_end_clean();   // ← discard any stray output before challenge
        http_response_code(200);
        header('Content-Type: text/plain');
        echo $challenge;  // Meta needs ONLY this — nothing else
        exit;
    }

    error_log("[WA_WEBHOOK] Token mismatch | expected={$verifyToken} | got={$token}");
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Token mismatch']);
    exit;
}