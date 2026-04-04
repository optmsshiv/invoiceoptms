<?php
// ================================================================
//  OPTMS Invoice Manager — api/wa_send.php
//  Server-side proxy for WhatsApp Business API
//  Supports both free-form text (session) and approved templates
// ================================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(['error' => 'Invalid JSON body'], 400);

$token   = trim($input['token']   ?? '');
$pid     = trim($input['pid']     ?? '');
$to      = trim($input['to']      ?? '');
$message = trim($input['message'] ?? '');
$type    = $input['type']     ?? 'text';      // 'text' or 'template'
$tplName = $input['template_name'] ?? '';
$tplLang = $input['template_lang'] ?? 'en';
$tplParams = $input['template_params'] ?? []; // array of strings

if (!$token)  jsonResponse(['error' => 'API token is required'], 400);
if (!$pid)    jsonResponse(['error' => 'Phone Number ID is required'], 400);
if (!$to)     jsonResponse(['error' => 'Recipient phone number is required'], 400);

// Sanitise phone: strip non-digits, ensure country code
$phone = preg_replace('/\D/', '', $to);
if (strlen($phone) === 10) $phone = '91' . $phone;
if (strlen($phone) < 10)   jsonResponse(['error' => 'Invalid phone number: ' . $to], 400);

// Build message body
if ($type === 'template' && $tplName) {
    // Build template components from params array
    $components = [];
    if (!empty($tplParams)) {
        $params = array_map(fn($p) => ['type' => 'text', 'text' => (string)$p], $tplParams);
        $components[] = ['type' => 'body', 'parameters' => $params];
    }
    $msgBody = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $phone,
        'type'              => 'template',
        'template'          => [
            'name'       => $tplName,
            'language'   => ['code' => $tplLang],
            'components' => $components,
        ],
    ];
} else {
    // Free-form text (session message)
    if (!$message) jsonResponse(['error' => 'Message body is required for text type'], 400);
    $msgBody = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $phone,
        'type'              => 'text',
        'text'              => ['preview_url' => false, 'body' => $message],
    ];
}

$url      = "https://graph.facebook.com/v22.0/{$pid}/messages";
$bodyJson = json_encode($msgBody);

if (!function_exists('curl_init')) {
    jsonResponse(['error' => 'cURL is not enabled on this server'], 500);
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $bodyJson,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($bodyJson),
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

if ($httpStatus >= 400 || isset($data['error'])) {
    $errMsg  = $data['error']['message'] ?? "HTTP $httpStatus";
    $errCode = $data['error']['code']    ?? $httpStatus;
    $errData = $data['error']            ?? null;
    error_log("WA API error {$errCode}: {$errMsg} | phone: +{$phone} | type: {$type}");
    jsonResponse(['error' => $errMsg, 'code' => $errCode, 'details' => $errData], 400);
}

logActivity($_SESSION['user_id'], 'wa_send', 'message', 0,
    "WA {$type} sent to +{$phone}" . ($tplName ? " [tpl:{$tplName}]" : ''));

jsonResponse([
    'success'  => true,
    'phone'    => '+' . $phone,
    'type'     => $type,
    'messages' => $data['messages'] ?? [],
]);
