<?php
// ================================================================
//  OPTMS Invoice Manager — api/wa_send.php
//  Server-side proxy for WhatsApp Business API
//  Bypasses browser CORS restrictions on graph.facebook.com
// ================================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    jsonResponse(['error' => 'Invalid JSON body'], 400);
}

$token   = trim($input['token']   ?? '');
$pid     = trim($input['pid']     ?? '');  // Phone Number ID (15-digit numeric ID)
$to      = trim($input['to']      ?? '');  // Recipient phone number with country code
$message = trim($input['message'] ?? '');

if (!$token)   jsonResponse(['error' => 'API token is required'], 400);
if (!$pid)     jsonResponse(['error' => 'Phone Number ID (pid) is required'], 400);
if (!$to)      jsonResponse(['error' => 'Recipient phone number is required'], 400);
if (!$message) jsonResponse(['error' => 'Message body is required'], 400);

// Sanitise phone: strip non-digits, ensure country code
$phone = preg_replace('/\D/', '', $to);
if (strlen($phone) === 10) {
    $phone = '91' . $phone;   // default to India
}
if (strlen($phone) < 10) {
    jsonResponse(['error' => 'Invalid phone number: ' . $to], 400);
}

// Build Meta WA Business API request
$url  = "https://graph.facebook.com/v22.0/{$pid}/messages";
$body = json_encode([
    'messaging_product' => 'whatsapp',
    'recipient_type'    => 'individual',
    'to'                => $phone,
    'type'              => 'text',
    'text'              => [
        'preview_url' => false,
        'body'        => $message,
    ],
]);

// Use cURL for the server-side request
if (!function_exists('curl_init')) {
    jsonResponse(['error' => 'cURL is not enabled on this server'], 500);
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($body),
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response   = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError  = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log("WA API cURL error: $curlError");
    jsonResponse(['error' => 'Network error: ' . $curlError], 502);
}

$data = json_decode($response, true);

// Meta API error format: { "error": { "message": "...", "code": ... } }
if ($httpStatus >= 400 || isset($data['error'])) {
    $errMsg = $data['error']['message'] ?? "HTTP $httpStatus";
    $errCode = $data['error']['code']   ?? $httpStatus;
    error_log("WA API error {$errCode}: {$errMsg}");
    jsonResponse([
        'error'   => $errMsg,
        'code'    => $errCode,
        'details' => $data['error'] ?? null,
    ], $httpStatus >= 400 ? $httpStatus : 400);
}

// Success — log it
logActivity(
    $_SESSION['user_id'], 'wa_send', 'message', 0,
    "WA sent to +$phone via pid $pid"
);

jsonResponse([
    'success'  => true,
    'phone'    => '+' . $phone,
    'messages' => $data['messages'] ?? [],
]);
