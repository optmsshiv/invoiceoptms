<?php
// ================================================================
//  OPTMS Invoice Manager — api/wa_webhook.php
//  WhatsApp Business API Webhook Receiver
//
//  Handles:
//    GET  — Meta webhook verification challenge
//    POST — Incoming status updates (delivered, read, failed)
//           and inbound messages (logged, not processed)
//
//  Setup in Meta Developer Console:
//    Callback URL : https://yourdomain.com/api/wa_webhook.php
//    Verify Token : (must match WA_WEBHOOK_VERIFY_TOKEN in settings table)
//    Subscriptions: messages, message_status_updates
// ================================================================

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// ── Load verify token from settings table ────────────────────────
function getVerifyToken(PDO $db): string {
    try {
        $stmt = $db->query("SELECT value FROM settings WHERE `key` = 'wa_webhook_token' LIMIT 1");
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['value'] ?? '';
    } catch (Exception $e) {
        return '';
    }
}

// ── Map Meta status strings to our allowed statuses ──────────────
function mapMetaStatus(string $metaStatus): string {
    return match($metaStatus) {
        'delivered' => 'delivered',
        'read'      => 'read',
        'failed'    => 'failed',
        'sent'      => 'sent_api',
        default     => ''
    };
}

try {
    $db = getDB();
    $db->exec("SET time_zone = '+05:30'");

    $method = $_SERVER['REQUEST_METHOD'];

    // ── GET: Webhook verification by Meta ────────────────────────
    if ($method === 'GET') {
        $mode      = $_GET['hub_mode']          ?? '';
        $token     = $_GET['hub_verify_token']  ?? '';
        $challenge = $_GET['hub_challenge']     ?? '';

        error_log("[WA_WEBHOOK] Verification attempt — mode={$mode} token={$token} challenge={$challenge}");
    
        $verifyToken = getVerifyToken($db);

        if (!$verifyToken) {
            http_response_code(500);
            echo json_encode(['error' => 'Webhook verify token not configured. Set wa_webhook_token in settings.']);
            exit;
        }

        if ($mode === 'subscribe' && $token === $verifyToken) {
            // Return the challenge as plain text — Meta requires this
            header('Content-Type: text/plain');
            echo $challenge;
            exit;
        }

        http_response_code(403);
        echo json_encode(['error' => 'Verification token mismatch']);
        exit;
    }

    // ── POST: Incoming webhook events ────────────────────────────
    if ($method === 'POST') {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!$data || ($data['object'] ?? '') !== 'whatsapp_business_account') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid payload']);
            exit;
        }

        // Always respond 200 immediately — Meta will retry if we don't
        http_response_code(200);
        echo json_encode(['success' => true]);
        // Flush response so Meta gets 200 while we process
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

        $entries = $data['entry'] ?? [];
        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];

                // ── Status updates (delivered, read, failed) ──────
                $statuses = $value['statuses'] ?? [];
                foreach ($statuses as $s) {
                    $wamid      = $s['id']        ?? '';
                    $metaStatus = $s['status']    ?? '';
                    $phone      = $s['recipient_id'] ?? '';
                    $ts         = isset($s['timestamp'])
                                    ? date('Y-m-d H:i:s', (int)$s['timestamp'])
                                    : date('Y-m-d H:i:s');

                    $ourStatus = mapMetaStatus($metaStatus);
                    if (!$wamid || !$ourStatus) continue;

                    // Only update to delivered/read if current status is less advanced
                    // Priority: sent_api < delivered < read
                    $priorityMap = ['sent_api'=>1,'sent_web'=>1,'delivered'=>2,'read'=>3,'failed'=>0];
                    $newPriority = $priorityMap[$ourStatus] ?? 0;

                    $stmt = $db->prepare(
                        'UPDATE wa_message_log
                         SET status = :status
                         WHERE wamid = :wamid
                           AND (
                             (:priority >= 2 AND status IN ("sent_api","sent_web","delivered"))
                             OR (:priority = 1 AND status IN ("sent_api","sent_web"))
                             OR (:priority = 0)
                           )'
                    );
                    $stmt->execute([
                        ':status'   => $ourStatus,
                        ':wamid'    => $wamid,
                        ':priority' => $newPriority,
                    ]);

                    error_log("[WA_WEBHOOK] Status update: wamid={$wamid} status={$ourStatus} phone={$phone} ts={$ts}");
                }

                // ── Inbound messages — log for reference ──────────
                $messages = $value['messages'] ?? [];
                foreach ($messages as $m) {
                    $fromPhone = $m['from']               ?? '';
                    $msgType   = $m['type']               ?? 'unknown';
                    $body      = $m['text']['body']       ?? '[non-text message]';
                    $ts        = isset($m['timestamp'])
                                    ? date('Y-m-d H:i:s', (int)$m['timestamp'])
                                    : date('Y-m-d H:i:s');
                    error_log("[WA_WEBHOOK] Inbound message from +{$fromPhone} ({$msgType}): " . substr($body, 0, 100));
                    // Future: store inbound messages in a separate table
                }
            }
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

} catch (Exception $e) {
    error_log('[WA_WEBHOOK] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
