<?php
// ================================================================
//  api/email.php — Send emails via SMTP (PHPMailer or mail())
//
//  POST  action=test   → Send test email to verify SMTP config
//  POST  action=send   → Send invoice email to client
// ================================================================

ob_start();
error_reporting(0);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { jsonResponse(['success' => false, 'error' => 'Invalid JSON'], 400); }

$action = $input['action'] ?? 'send';

// ── Load SMTP config ─────────────────────────────────────────────
function getSmtpConfig(array $input, $db): array {
    if (!empty($input['smtp_host'])) {
        return [
            'host' => $input['smtp_host'],
            'port' => (int)($input['smtp_port'] ?? 587),
            'user' => $input['smtp_user'] ?? '',
            'pass' => $input['smtp_pass'] ?? '',
            'from' => $input['smtp_from'] ?? $input['smtp_user'] ?? '',
            'name' => $input['smtp_name'] ?? 'Invoice',
        ];
    }
    $cfg  = [];
    $stmt = $db->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ('smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from','smtp_name')");
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) { $cfg[$row['key']] = $row['value']; }
    return [
        'host' => $cfg['smtp_host'] ?? '',
        'port' => (int)($cfg['smtp_port'] ?? 587),
        'user' => $cfg['smtp_user'] ?? '',
        'pass' => $cfg['smtp_pass'] ?? '',
        'from' => $cfg['smtp_from'] ?? $cfg['smtp_user'] ?? '',
        'name' => $cfg['smtp_name'] ?? 'Invoice',
    ];
}

// ── SMTP sender ──────────────────────────────────────────────────
function sendSmtpEmail(array $smtp, string $to, string $toName, string $subject, string $htmlBody): array {
    if (empty($smtp['host']) || empty($smtp['user']) || empty($smtp['pass'])) {
        return ['success' => false, 'error' => 'SMTP not configured. Fill all fields and Save first.'];
    }
    // Try PHPMailer if installed via Composer
    foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../vendor/autoload.php'] as $p) {
        if (file_exists($p)) { require_once $p; break; }
    }
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['user'];
            $mail->Password   = $smtp['pass'];
            $mail->SMTPSecure = ($smtp['port'] == 465)
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtp['port'];
            $mail->setFrom($smtp['from'], $smtp['name']);
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);
            $mail->send();
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
        }
    }
    // Fallback: native PHP mail()
    $headers  = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$smtp['name']} <{$smtp['from']}>\r\nReply-To: {$smtp['from']}\r\n";
    $sent = @mail($to, $subject, $htmlBody, $headers);
    if ($sent) return ['success' => true];
    return ['success' => false, 'error' => 'PHPMailer not found & PHP mail() failed. Run: composer require phpmailer/phpmailer'];
}

// ── HTML wrapper ─────────────────────────────────────────────────
function buildEmailHTML(string $body): string {
    $b = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
    return "<html><head><style>body{font-family:Arial,sans-serif;background:#f5f5f5;padding:20px}.wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)}.hdr{background:#00897B;color:#fff;padding:22px 30px;font-size:18px;font-weight:700}.bdy{padding:26px 30px;color:#333;font-size:15px;line-height:1.8}.ftr{background:#f9f9f9;padding:14px 30px;font-size:12px;color:#999;border-top:1px solid #eee}</style></head><body><div class='wrap'><div class='hdr'>📄 Invoice from OPTMS Tech</div><div class='bdy'>$b</div><div class='ftr'>Sent via OPTMS Tech Invoice Manager · optmstech.in</div></div></body></html>";
}

// ── Main ─────────────────────────────────────────────────────────
try {
    $db   = getDB();
    $smtp = getSmtpConfig($input, $db);

    if ($action === 'test') {
        if (empty($smtp['host'])) jsonResponse(['success'=>false,'error'=>'SMTP Host required'], 422);
        $to      = $input['to'] ?? $smtp['user'];
        $subject = 'SMTP Test — OPTMS Tech Invoice Manager';
        $body    = "Test email from OPTMS Tech Invoice Manager.\n\nSMTP is working!\n\nHost: {$smtp['host']}\nPort: {$smtp['port']}\nFrom: {$smtp['from']}";
        jsonResponse(sendSmtpEmail($smtp, $to, 'Test', $subject, buildEmailHTML($body)));
    }

    if ($action === 'send') {
        $to     = $input['to']      ?? '';
        $toName = $input['to_name'] ?? 'Client';
        $subj   = $input['subject'] ?? 'Invoice from OPTMS Tech';
        $body   = $input['body']    ?? '';
        $invId  = (int)($input['invoice_id'] ?? 0);
        if (!$to)   jsonResponse(['success'=>false,'error'=>'Recipient email required'], 422);
        if (!$body) jsonResponse(['success'=>false,'error'=>'Email body required'], 422);
        $result = sendSmtpEmail($smtp, $to, $toName, $subj, buildEmailHTML($body));
        if ($result['success'] && $invId) {
            try { logActivity($_SESSION['user_id'], 'email_sent', 'invoice', $invId, "Email sent to $to"); } catch(\Exception $e){}
        }
        jsonResponse($result);
    }

    jsonResponse(['success'=>false,'error'=>'Unknown action'], 400);

} catch (\Exception $e) {
    error_log('email.php: ' . $e->getMessage());
    jsonResponse(['success'=>false,'error'=>'Server error: '.$e->getMessage()], 500);
}
