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

$token     = trim($input['token']   ?? '');
$pid       = trim($input['pid']     ?? '');
$to        = trim($input['to']      ?? '');
$message   = trim($input['message'] ?? '');
$type      = $input['type']             ?? 'text';
$tplName   = trim($input['template_name'] ?? '');
$tplLang   = trim($input['template_lang'] ?? 'en');
$tplParams = $input['template_params']  ?? [];

if (!$token)  jsonResponse(['error' => 'API token is required'], 400);
if (!$pid)    jsonResponse(['error' => 'Phone Number ID is required'], 400);
if (!$to)     jsonResponse(['error' => 'Recipient phone number is required'], 400);

// ── Sanitise phone: strip non-digits, ensure country code ────────
$phone = preg_replace('/\D/', '', $to);
if (strlen($phone) === 10) $phone = '91' . $phone;
if (strlen($phone) < 10)   jsonResponse(['error' => 'Invalid phone number: ' . $to], 400);

// ── Normalise language code ───────────────────────────────────────
// Meta requires full locale codes. Map common short codes.
$langMap = [
    'en'    => 'en_US',
    'hi'    => 'hi',
    'mr'    => 'mr',
    'gu'    => 'gu',
    'ta'    => 'ta',
    'te'    => 'te',
    'kn'    => 'kn',
    'bn'    => 'bn',
    'pa'    => 'pa',
    'ur'    => 'ur',
    'en_us' => 'en_US',
    'en_gb' => 'en_GB',
];
$tplLangNorm = $langMap[strtolower($tplLang)] ?? $tplLang;

// ── Build message body ────────────────────────────────────────────
if ($type === 'template' && $tplName) {

    // Validate template name — only lowercase letters, digits, underscores
    if (!preg_match('/^[a-z0-9_]+$/', $tplName)) {
        jsonResponse([
            'error' => "Invalid template name \"{$tplName}\". Meta template names must be lowercase letters, digits and underscores only (no spaces, hyphens or uppercase).",
        ], 400);
    }

    // Build body components from params array
    $components = [];
    if (!empty($tplParams)) {
        $params = array_map(
            fn($p) => ['type' => 'text', 'text' => (string)$p],
            array_values($tplParams)   // re-index to ensure JSON array not object
        );
        $components[] = ['type' => 'body', 'parameters' => $params];
    }

    $msgBody = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $phone,
        'type'              => 'template',
        'template'          => [
            'name'       => $tplName,
            'language'   => ['code' => $tplLangNorm],
            'components' => $components,
        ],
    ];

} else {
    // ── Free-form text (session message) ─────────────────────────
    if (!$message) jsonResponse(['error' => 'Message body is required for text type'], 400);

    // Detect if message contains a URL — enable preview if so
    $hasUrl = (bool) preg_match('/https?:\/\/\S+/', $message);

    $msgBody = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $phone,
        'type'              => 'text',
        'text'              => [
            'preview_url' => $hasUrl,   // enable link preview when URL present
            'body'        => $message,
        ],
    ];
}

// ── Call Meta Graph API ───────────────────────────────────────────
$url      = "https://graph.facebook.com/v22.0/{$pid}/messages";
$bodyJson = json_encode($msgBody, JSON_UNESCAPED_UNICODE);

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

// ── Handle Meta API errors ────────────────────────────────────────
if ($httpStatus >= 400 || isset($data['error'])) {
    $errCode    = $data['error']['code']              ?? $httpStatus;
    $errMsg     = $data['error']['message']           ?? "HTTP $httpStatus";
    $errSubcode = $data['error']['error_subcode']     ?? null;
    $errData    = $data['error']                      ?? null;
    $fbTraceId  = $data['error']['fbtrace_id']        ?? null;

    // ── Friendly messages for common Meta error codes ─────────────
    $friendly = match((int)$errCode) {
        131030  => 'Recipient phone number is not a valid WhatsApp number.',
        131047  => 'Message failed: this number has not messaged you in the last 24 hours and you are in session-only mode. Switch to Approved Templates mode.',
        130429  => 'Rate limit hit — too many messages sent. Please wait before trying again.',
        132000  => "Template \"{$tplName}\" has not been approved by Meta yet, or was rejected. Check your Meta Business Manager.",
        132001  => "Template \"{$tplName}\" not found for language \"{$tplLangNorm}\". Verify: (1) template name is exactly as approved, (2) language code matches approval (try en_US instead of en), (3) template status is APPROVED in Meta Business Manager.",
        132007  => 'Template parameter count mismatch — the number of {{params}} in the template body does not match what was sent.',
        132012  => 'Template parameter format mismatch — a parameter contains characters not allowed by the template.',
        135000  => 'Generic template error — check your template content and parameters.',
        default => $errMsg,
    };

    error_log("WA API error {$errCode}" .
        ($errSubcode ? "/{$errSubcode}" : '') .
        ": {$errMsg}" .
        " | phone: +{$phone}" .
        " | type: {$type}" .
        ($tplName ? " | tpl: {$tplName} [{$tplLangNorm}]" : '') .
        ($fbTraceId ? " | trace: {$fbTraceId}" : '') .
        " | params_count: " . count($tplParams));

    jsonResponse([
        'error'    => $friendly,
        'code'     => $errCode,
        'subcode'  => $errSubcode,
        'details'  => $errData,
        'debug'    => [
            'template_name'   => $tplName ?: null,
            'language_sent'   => $tplLangNorm ?: null,
            'params_count'    => count($tplParams),
            'params_preview'  => array_slice($tplParams, 0, 3),
            'phone'           => '+' . $phone,
        ],
    ], 400);
}

// ── Success ───────────────────────────────────────────────────────
logActivity($_SESSION['user_id'], 'wa_send', 'message', 0,
    "WA {$type} sent to +{$phone}" . ($tplName ? " [tpl:{$tplName}/{$tplLangNorm}]" : ''));

jsonResponse([
    'success'  => true,
    'phone'    => '+' . $phone,
    'type'     => $type,
    'messages' => $data['messages'] ?? [],
]);
