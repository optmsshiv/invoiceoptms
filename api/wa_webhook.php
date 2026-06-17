<?php
// ================================================================
//  OPTMS Invoice Manager — api/wa_webhook.php
//  WhatsApp Business API Webhook Receiver
// ================================================================

ob_start(); // catch any stray output from includes

date_default_timezone_set('Asia/Kolkata');

function getVerifyToken(): string {
    try {
        require_once __DIR__ . '/../config/db.php';
        $db   = getDB();
        $stmt = $db->query("SELECT `value` FROM `settings` WHERE `key` = 'wa_webhook_token' LIMIT 1");
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($row['value'])) return trim($row['value']);
    } catch (Throwable $e) {
        error_log('[WA_WEBHOOK] DB error: ' . $e->getMessage());
    }
    if (!empty($_ENV['WA_WEBHOOK_TOKEN']))  return trim($_ENV['WA_WEBHOOK_TOKEN']);
    if (!empty(getenv('WA_WEBHOOK_TOKEN'))) return trim(getenv('WA_WEBHOOK_TOKEN'));
    return '';
}

function mapMetaStatus(string $s): string {
    return match($s) {
        'delivered' => 'delivered',
        'read'      => 'read',
        'failed'    => 'failed',
        'sent'      => 'sent_api',
        default     => ''
    };
}

$method = $_SERVER['REQUEST_METHOD'];

// ── Temporary debug — remove after fixing ────────────────────────
error_log('[WA_WEBHOOK] REQUEST_URI: '    . ($_SERVER['REQUEST_URI']    ?? 'EMPTY'));
error_log('[WA_WEBHOOK] QUERY_STRING: '   . ($_SERVER['QUERY_STRING']   ?? 'EMPTY'));
error_log('[WA_WEBHOOK] GET dump: '       . json_encode($_GET));
error_log('[WA_WEBHOOK] HTTP_HOST: '      . ($_SERVER['HTTP_HOST']      ?? 'EMPTY'));

// ── GET: Meta webhook verification ───────────────────────────────
if ($method === 'GET') {
    $mode      = $_GET['hub_mode']         ?? '';
    $token     = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge']    ?? '';

    $verifyToken = getVerifyToken();
    error_log('[WA_WEBHOOK] stored_raw: ' . json_encode($verifyToken) . ' len=' . strlen($verifyToken));

    error_log("[WA_WEBHOOK] GET | mode={$mode} | received={$token} | stored={$verifyToken}");

    ob_clean(); // discard any output from includes before sending response

    if (!$verifyToken) {
        http_response_code(500);
        header('Content-Type: text/plain');
        echo 'ERROR: webhook token not configured in settings';
        exit;
    }

    if ($mode === 'subscribe' && $token === $verifyToken) {
        http_response_code(200);
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }

    error_log("[WA_WEBHOOK] Token mismatch | expected={$verifyToken} | got={$token}");
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'FORBIDDEN';
    exit;
}

// ── POST: Process webhook events ─────────────────────────────────
if ($method === 'POST') {
    ob_clean();
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    else { ob_flush(); flush(); }

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || ($data['object'] ?? '') !== 'whatsapp_business_account') {
        error_log('[WA_WEBHOOK] Invalid payload: ' . substr($raw, 0, 200));
        exit;
    }

    try {
        require_once __DIR__ . '/../config/db.php';
        $db = getDB();
        $db->exec("SET time_zone = '+05:30'");

        foreach (($data['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];

                foreach (($value['statuses'] ?? []) as $s) {
                    $wamid     = $s['id']          ?? '';
                    $metaStatus = $s['status']      ?? '';
                    $phone     = $s['recipient_id'] ?? '';
                    $ourStatus = mapMetaStatus($metaStatus);
                    if (!$wamid || !$ourStatus) continue;

                    $priorityMap = ['sent_api'=>1,'sent_web'=>1,'delivered'=>2,'read'=>3,'failed'=>0];
                    $p = $priorityMap[$ourStatus] ?? 0;

                    $stmt = $db->prepare(
                        'UPDATE wa_message_log SET status = :status
                         WHERE wamid = :wamid AND (
                           (:p >= 2 AND status IN ("sent_api","sent_web","delivered"))
                           OR (:p = 1 AND status IN ("sent_api","sent_web"))
                           OR (:p = 0)
                         )'
                    );
                    $stmt->execute([':status'=>$ourStatus, ':wamid'=>$wamid, ':p'=>$p]);
                    error_log("[WA_WEBHOOK] {$ourStatus} | wamid={$wamid} | phone={$phone}");
                }

                foreach (($value['messages'] ?? []) as $m) {
                    $from = $m['from'] ?? '';
                    $body = $m['text']['body'] ?? '[non-text]';
                    error_log("[WA_WEBHOOK] Inbound from +{$from}: " . substr($body, 0, 100));
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[WA_WEBHOOK] POST error: ' . $e->getMessage());
    }
    exit;
}

// ── Anything else ─────────────────────────────────────────────────
ob_clean();
http_response_code(405);
header('Content-Type: text/plain');
echo 'Method not allowed';