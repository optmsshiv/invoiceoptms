<?php
// ================================================================
//  api/email.php — Send emails via SMTP (PHPMailer or mail())
//
//  GET  action=templates        → List all email templates
//  GET  action=logs             → Email send log
//  GET  action=smtp_profiles    → List SMTP profiles
//  POST action=test             → Send test email to verify SMTP
//  POST action=send             → Send typed email to client
//  POST action=save_template    → Save/update an email template
//  POST action=save_profile     → Save/update an SMTP profile
//  POST action=preview          → Return rendered HTML preview
//  DELETE action=del_profile    → Delete an SMTP profile
// ================================================================

ob_start();
error_reporting(0);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── Parse body for POST/DELETE ───────────────────────────────────
$input = [];
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
    $action = $input['action'] ?? $action;
}
if ($method === 'DELETE') {
    $action = $_GET['action'] ?? 'del_profile';
}

// ── DB ───────────────────────────────────────────────────────────
try { $db = getDB(); } catch (\Exception $e) {
    jsonResponse(['success'=>false,'error'=>'DB error: '.$e->getMessage()], 500);
}

// ================================================================
//  ROUTE
// ================================================================
switch ($action) {

    case 'templates':     handleGetTemplates($db);   break;
    case 'save_template': handleSaveTemplate($db, $input); break;
    case 'preview':       handlePreview($db, $input); break;
    case 'logs':          handleLogs($db);            break;
    case 'smtp_profiles': handleGetProfiles($db);     break;
    case 'save_profile':  handleSaveProfile($db, $input); break;
    case 'del_profile':   handleDelProfile($db);      break;
    case 'test':          handleTest($db, $input);    break;
    case 'send':          handleSend($db, $input);    break;
    default:
        if ($method === 'GET') { handleGetTemplates($db); break; }
        jsonResponse(['success'=>false,'error'=>'Unknown action: '.$action], 400);
}

// ================================================================
//  HANDLERS
// ================================================================

// ── GET /api/email.php?action=templates ─────────────────────────
function handleGetTemplates($db) {
    ensureEmailTables($db);
    $rows = $db->query("SELECT * FROM email_templates ORDER BY FIELD(type,'invoice','estimate','receipt','reminder','overdue','followup')")->fetchAll();
    $defaults = getDefaultTemplates();
    // Merge: return DB rows, fill missing types with defaults
    $byType = [];
    foreach ($rows as $r) $byType[$r['type']] = $r;
    $result = [];
    foreach ($defaults as $type => $d) {
        $result[] = $byType[$type] ?? array_merge(['id'=>null,'type'=>$type,'enabled'=>1], $d);
    }
    jsonResponse(['success'=>true,'data'=>$result]);
}

// ── POST action=save_template ────────────────────────────────────
function handleSaveTemplate($db, $input) {
    ensureEmailTables($db);
    $type    = trim($input['type']    ?? '');
    $subject = trim($input['subject'] ?? '');
    $body    = trim($input['body']    ?? '');
    $enabled = isset($input['enabled']) ? (int)$input['enabled'] : 1;
    if (!$type || !$subject || !$body) {
        jsonResponse(['success'=>false,'error'=>'type, subject and body are required'], 422);
    }
    $allowed = ['invoice','estimate','receipt','reminder','overdue','followup'];
    if (!in_array($type, $allowed)) {
        jsonResponse(['success'=>false,'error'=>'Invalid template type'], 422);
    }
    $exists = $db->prepare("SELECT id FROM email_templates WHERE type=?")->execute([$type]);
    $row    = $db->prepare("SELECT id FROM email_templates WHERE type=?");
    $row->execute([$type]);
    $existing = $row->fetch();
    if ($existing) {
        $db->prepare("UPDATE email_templates SET subject=?,body=?,enabled=?,updated_at=NOW() WHERE type=?")->execute([$subject,$body,$enabled,$type]);
    } else {
        $db->prepare("INSERT INTO email_templates (type,subject,body,enabled,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())")->execute([$type,$subject,$body,$enabled]);
    }
    jsonResponse(['success'=>true]);
}

// ── POST action=preview ─────────────────────────────────────────
function handlePreview($db, $input) {
    ensureEmailTables($db);
    $type  = $input['type']       ?? 'invoice';
    $invId = (int)($input['invoice_id'] ?? 0);

    $tpl  = getTemplate($db, $type);
    $vars = buildTemplateVars($db, $invId, $type);

    $subject = replacePlaceholders($tpl['subject'], $vars);
    $html    = buildEmailHTML(replacePlaceholders($tpl['body'], $vars), $type);

    jsonResponse(['success'=>true,'subject'=>$subject,'html'=>$html]);
}

// ── GET action=logs ─────────────────────────────────────────────
function handleLogs($db) {
    ensureEmailTables($db);
    $invId  = (int)($_GET['invoice_id'] ?? 0);
    $type   = $_GET['type']   ?? '';
    $status = $_GET['status'] ?? '';
    $sql    = "SELECT * FROM email_logs WHERE 1";
    $params = [];
    if ($invId)  { $sql .= " AND invoice_id=?";  $params[] = $invId; }
    if ($type)   { $sql .= " AND type=?";         $params[] = $type; }
    if ($status) { $sql .= " AND status=?";       $params[] = $status; }
    $sql .= " ORDER BY created_at DESC LIMIT 200";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['success'=>true,'data'=>$stmt->fetchAll()]);
}

// ── GET action=smtp_profiles ────────────────────────────────────
function handleGetProfiles($db) {
    ensureEmailTables($db);
    $rows = $db->query("SELECT id,name,host,port,username,from_email,from_name,provider,is_default FROM smtp_profiles ORDER BY is_default DESC,name ASC")->fetchAll();
    jsonResponse(['success'=>true,'data'=>$rows]);
}

// ── POST action=save_profile ────────────────────────────────────
function handleSaveProfile($db, $input) {
    ensureEmailTables($db);
    $id       = (int)($input['id'] ?? 0);
    $name     = trim($input['name']       ?? '');
    $host     = trim($input['host']       ?? '');
    $port     = (int)($input['port']      ?? 587);
    $user     = trim($input['username']   ?? '');
    $pass     = trim($input['password']   ?? '');
    $from     = trim($input['from_email'] ?? '');
    $fname    = trim($input['from_name']  ?? '');
    $provider = trim($input['provider']   ?? 'smtp');
    $isDefault= (int)($input['is_default']?? 0);
    $apikey   = trim($input['api_key']    ?? '');
    if (!$name || !$host || !$user) {
        jsonResponse(['success'=>false,'error'=>'Name, host and username are required'], 422);
    }
    if ($isDefault) $db->exec("UPDATE smtp_profiles SET is_default=0");
    if ($id) {
        $sql = "UPDATE smtp_profiles SET name=?,host=?,port=?,username=?,from_email=?,from_name=?,provider=?,is_default=?,api_key=?,updated_at=NOW()";
        $params = [$name,$host,$port,$user,$from,$fname,$provider,$isDefault,$apikey];
        if ($pass) { $sql .= ",password=?"; $params[] = $pass; }
        $sql .= " WHERE id=?"; $params[] = $id;
        $db->prepare($sql)->execute($params);
    } else {
        $db->prepare("INSERT INTO smtp_profiles (name,host,port,username,password,from_email,from_name,provider,is_default,api_key,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")
           ->execute([$name,$host,$port,$user,$pass,$from,$fname,$provider,$isDefault,$apikey]);
    }
    jsonResponse(['success'=>true]);
}

// ── DELETE action=del_profile ───────────────────────────────────
function handleDelProfile($db) {
    ensureEmailTables($db);
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['success'=>false,'error'=>'ID required'], 422);
    $db->prepare("DELETE FROM smtp_profiles WHERE id=?")->execute([$id]);
    jsonResponse(['success'=>true]);
}

// ── POST action=test ────────────────────────────────────────────
function handleTest($db, $input) {
    $smtp = getSmtpConfig($input, $db);
    if (empty($smtp['host'])) jsonResponse(['success'=>false,'error'=>'SMTP Host required'], 422);
    $to      = $input['to'] ?? $smtp['user'];
    $subject = 'SMTP Test — OPTMS Tech Invoice Manager';
    $body    = "Test email from OPTMS Tech Invoice Manager.\n\nSMTP is working!\n\nHost: {$smtp['host']}\nPort: {$smtp['port']}\nFrom: {$smtp['from']}";
    $result  = sendSmtpEmail($smtp, $to, 'Test', $subject, buildEmailHTML($body, 'test'));
    if ($result['success']) {
        try { logEmailSent($db, 0, 'test', $to, $subject, 'sent'); } catch(\Exception $e){}
    }
    jsonResponse($result);
}

// ── POST action=send ────────────────────────────────────────────
function handleSend($db, $input) {
    ensureEmailTables($db);

    $to     = trim($input['to']         ?? '');
    $toName = trim($input['to_name']    ?? 'Client');
    $invId  = (int)($input['invoice_id']?? 0);
    $type   = trim($input['type']       ?? 'invoice');   // ← KEY FIX: honour type

    // Map legacy/alias type names
    $typeMap = [
        'pending'      => 'invoice',
        'paid_receipt' => 'receipt',
        'paid'         => 'receipt',
        'partial'      => 'receipt',
        'remind'       => 'reminder',
        'payment_reminder' => 'reminder',
        'payment_overdue'  => 'overdue',
        'invoice_followup' => 'followup',
        'invoice_created'  => 'invoice',
        'estimate_created' => 'estimate',
        'payment_received' => 'receipt',
        'partial_payment'  => 'receipt',
    ];
    $type = $typeMap[$type] ?? $type;
    $allowed = ['invoice','estimate','receipt','reminder','overdue','followup','test'];
    if (!in_array($type, $allowed)) $type = 'invoice';

    if (!$to) jsonResponse(['success'=>false,'error'=>'Recipient email required'], 422);

    // ── Load template (DB first, then defaults) ──────────────────
    $tpl  = getTemplate($db, $type);

    // ── Build variable map for this invoice ──────────────────────
    $vars = buildTemplateVars($db, $invId, $type);

    // ── Allow caller to override individual vars ─────────────────
    // (e.g. sendEmailForClient passes subject/body directly)
    if (!empty($input['subject']) && empty($input['use_template'])) {
        $subject = $input['subject'];
    } else {
        $subject = replacePlaceholders($tpl['subject'], $vars);
    }
    if (!empty($input['body']) && empty($input['use_template'])) {
        $rawBody = $input['body'];
    } else {
        $rawBody = replacePlaceholders($tpl['body'], $vars);
    }

    // ── Always inject portal link if available and placeholder missing ──
    if (!empty($vars['{invoice_link}']) && strpos($rawBody, '{invoice_link}') === false) {
        $rawBody .= "\n\nView your invoice online: " . $vars['{invoice_link}'];
    }

    $smtp   = getSmtpConfig($input, $db);
    $html   = buildEmailHTML($rawBody, $type);
    $result = sendSmtpEmail($smtp, $to, $toName, $subject, $html);

    $status = $result['success'] ? 'sent' : 'failed';
    $errMsg = $result['error']   ?? '';
    try { logEmailSent($db, $invId, $type, $to, $subject, $status, $errMsg); } catch(\Exception $e){}

    if ($result['success'] && $invId && isset($_SESSION['user_id'])) {
        try { logActivity($_SESSION['user_id'], 'email_sent', 'invoice', $invId, "Email ($type) sent to $to"); } catch(\Exception $e){}
    }
    jsonResponse($result);
}

// ================================================================
//  HELPERS
// ================================================================

// ── Load SMTP config (input override → DB settings) ─────────────
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
    // Try default SMTP profile first
    try {
        $prof = $db->query("SELECT * FROM smtp_profiles WHERE is_default=1 LIMIT 1")->fetch();
        if ($prof && $prof['host']) {
            return [
                'host' => $prof['host'],
                'port' => (int)$prof['port'],
                'user' => $prof['username'],
                'pass' => $prof['password'],
                'from' => $prof['from_email'] ?: $prof['username'],
                'name' => $prof['from_name']  ?: 'Invoice',
            ];
        }
    } catch(\Exception $e){}
    // Fallback: settings table
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

// ── Fetch template from DB, fall back to defaults ────────────────
function getTemplate($db, string $type): array {
    try {
        $stmt = $db->prepare("SELECT subject,body FROM email_templates WHERE type=? AND enabled=1 LIMIT 1");
        $stmt->execute([$type]);
        $row = $stmt->fetch();
        if ($row && $row['subject'] && $row['body']) return $row;
    } catch(\Exception $e){}
    $defaults = getDefaultTemplates();
    return $defaults[$type] ?? $defaults['invoice'];
}

// ── Build all placeholder vars for an invoice ───────────────────
function buildTemplateVars($db, int $invId, string $type): array {
    // Load company settings
    $cfg = [];
    try {
        $rows = $db->query("SELECT `key`,`value` FROM settings")->fetchAll();
        foreach ($rows as $r) $cfg[$r['key']] = $r['value'];
    } catch(\Exception $e){}

    $company = $cfg['company_name']    ?? 'OPTMS Tech';
    $phone   = $cfg['company_phone']   ?? '';
    $email   = $cfg['company_email']   ?? '';
    $upi     = $cfg['company_upi']     ?? '';
    $bank    = $cfg['company_bank']    ?? '';
    $website = $cfg['company_website'] ?? '';
    $portalBase = rtrim($cfg['portal_base_url'] ?? 'https://invcs.optms.co.in/portal', '/') . '/';

    $vars = [
        '{company_name}'   => $company,
        '{company_phone}'  => $phone,
        '{company_email}'  => $email,
        '{upi}'            => $upi,
        '{bank_details}'   => $bank,
        '{company_website}'=> $website,
        '{client_name}'    => 'Valued Client',
        '{invoice_no}'     => '',
        '{amount}'         => '',
        '{due_date}'       => '',
        '{issue_date}'     => date('d M Y'),
        '{service}'        => '',
        '{status}'         => '',
        '{days_overdue}'   => '0',
        '{paid_amount}'    => '',
        '{remaining_amount}' => '',
        '{settlement_discount}' => '',
        '{invoice_link}'   => '',
        '{payment_method}' => '',
    ];

    if (!$invId) return $vars;

    // Load invoice
    try {
        $stmt = $db->prepare("SELECT * FROM invoices WHERE id=? LIMIT 1");
        $stmt->execute([$invId]);
        $inv = $stmt->fetch();
    } catch(\Exception $e) { $inv = null; }
    if (!$inv) return $vars;

    // Load client
    $client = [];
    try {
        $cs = $db->prepare("SELECT * FROM clients WHERE id=? LIMIT 1");
        $cs->execute([$inv['client_id'] ?? $inv['client'] ?? 0]);
        $client = $cs->fetch() ?: [];
    } catch(\Exception $e){}

    $sym      = $inv['currency'] ?? '₹';
    $grand    = (float)($inv['grand_total'] ?? $inv['amount'] ?? 0);
    $due      = $inv['due_date'] ?? $inv['due'] ?? '';
    $issued   = $inv['issued_date'] ?? $inv['issued'] ?? date('Y-m-d');
    $dueFmt   = $due    ? date('d M Y', strtotime($due))    : '';
    $issFmt   = $issued ? date('d M Y', strtotime($issued)) : '';
    $daysOver = $due    ? max(0, (int)floor((time() - strtotime($due)) / 86400)) : 0;
    $num      = $inv['invoice_number'] ?? $inv['num'] ?? '';
    $service  = $inv['service_type']   ?? $inv['service'] ?? '';
    $status   = $inv['status'] ?? '';

    // Payments
    $totalPaid = 0;
    $settleDisc = 0;
    try {
        $ps = $db->prepare("SELECT SUM(amount) as paid, SUM(settlement_discount) as disc FROM payments WHERE invoice_id=?");
        $ps->execute([$invId]);
        $pr = $ps->fetch();
        $totalPaid  = (float)($pr['paid']  ?? 0);
        $settleDisc = (float)($pr['disc']  ?? 0);
    } catch(\Exception $e){}
    $remaining = max(0, $grand - $totalPaid);

    // Portal link
    $portalLink = '';
    try {
        $pt = $db->prepare("SELECT token FROM invoice_portal_tokens WHERE invoice_id=? ORDER BY id DESC LIMIT 1");
        $pt->execute([$invId]);
        $tok = $pt->fetchColumn();
        if ($tok) $portalLink = $portalBase . '?t=' . $tok;
        else {
            // Auto-generate token if missing
            $newToken = bin2hex(random_bytes(24));
            $db->prepare("INSERT INTO invoice_portal_tokens (invoice_id,token,created_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE token=VALUES(token)")
               ->execute([$invId, $newToken]);
            $portalLink = $portalBase . '?t=' . $newToken;
        }
    } catch(\Exception $e){}

    $vars['{client_name}']          = $client['name'] ?? $client['client_name'] ?? 'Valued Client';
    $vars['{invoice_no}']           = $num;
    $vars['{amount}']               = $sym . number_format($grand, 2);
    $vars['{due_date}']             = $dueFmt;
    $vars['{issue_date}']           = $issFmt;
    $vars['{service}']              = $service;
    $vars['{status}']               = $status;
    $vars['{days_overdue}']         = (string)$daysOver;
    $vars['{paid_amount}']          = $sym . number_format($totalPaid, 2);
    $vars['{remaining_amount}']     = $sym . number_format($remaining, 2);
    $vars['{settlement_discount}']  = $settleDisc > 0 ? $sym . number_format($settleDisc, 2) : '';
    $vars['{invoice_link}']         = $portalLink;

    return $vars;
}

function replacePlaceholders(string $tpl, array $vars): string {
    return str_replace(array_keys($vars), array_values($vars), $tpl);
}

// ── Styled HTML email wrapper (type-aware accent colour) ─────────
function buildEmailHTML(string $body, string $type = 'invoice'): string {
    $accents = [
        'invoice'  => ['#00897B', '📄 Invoice from OPTMS Tech'],
        'estimate' => ['#1976D2', '📋 Estimate from OPTMS Tech'],
        'receipt'  => ['#388E3C', '✅ Payment Receipt — OPTMS Tech'],
        'reminder' => ['#F9A825', '🔔 Payment Reminder — OPTMS Tech'],
        'overdue'  => ['#E53935', '⚠️ Invoice Overdue — OPTMS Tech'],
        'followup' => ['#7B1FA2', '📞 Follow-up — OPTMS Tech'],
        'test'     => ['#00897B', '🧪 SMTP Test — OPTMS Tech'],
    ];
    [$color, $heading] = $accents[$type] ?? $accents['invoice'];
    $b = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
    // Make portal link clickable
    $b = preg_replace(
        '/(https?:\/\/[^\s<]+)/i',
        '<a href="$1" style="color:'.$color.';word-break:break-all">$1</a>',
        $b
    );
    return <<<HTML
<html>
<head>
<style>
  body{font-family:Arial,sans-serif;background:#f5f5f5;padding:20px;margin:0}
  .wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.10)}
  .hdr{background:{$color};color:#fff;padding:24px 32px;font-size:18px;font-weight:700;line-height:1.3}
  .bdy{padding:28px 32px;color:#333;font-size:15px;line-height:1.85}
  .ftr{background:#f9f9f9;padding:14px 32px;font-size:12px;color:#999;border-top:1px solid #eee;text-align:center}
  a{color:{$color}}
</style>
</head>
<body>
  <div class="wrap">
    <div class="hdr">{$heading}</div>
    <div class="bdy">{$b}</div>
    <div class="ftr">Sent via OPTMS Tech Invoice Manager &middot; <a href="https://optmstech.in">optmstech.in</a></div>
  </div>
</body>
</html>
HTML;
}

// ── PHPMailer / mail() sender ────────────────────────────────────
function sendSmtpEmail(array $smtp, string $to, string $toName, string $subject, string $htmlBody): array {
    if (empty($smtp['host']) || empty($smtp['user']) || empty($smtp['pass'])) {
        return ['success'=>false,'error'=>'SMTP not configured. Fill all fields and Save first.'];
    }
    foreach ([__DIR__.'/../vendor/autoload.php', __DIR__.'/../../vendor/autoload.php'] as $p) {
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
            $mail->AltBody = strip_tags(str_replace(['<br>','<br/>','</p>'],"\n",$htmlBody));
            $mail->send();
            return ['success'=>true];
        } catch (\Exception $e) {
            return ['success'=>false,'error'=>$mail->ErrorInfo ?: $e->getMessage()];
        }
    }
    // Fallback: native PHP mail()
    $headers  = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$smtp['name']} <{$smtp['from']}>\r\nReply-To: {$smtp['from']}\r\n";
    $sent = @mail($to, $subject, $htmlBody, $headers);
    if ($sent) return ['success'=>true];
    return ['success'=>false,'error'=>'PHPMailer not found & PHP mail() failed. Run: composer require phpmailer/phpmailer'];
}

// ── Log sent email ───────────────────────────────────────────────
function logEmailSent($db, int $invId, string $type, string $to, string $subject, string $status, string $error=''): void {
    try {
        $db->prepare("INSERT INTO email_logs (invoice_id,type,to_email,subject,status,error_msg,created_at) VALUES (?,?,?,?,?,?,NOW())")
           ->execute([$invId ?: null, $type, $to, $subject, $status, $error ?: null]);
    } catch(\Exception $e) { error_log('logEmailSent: '.$e->getMessage()); }
}

// ── Ensure required tables exist ─────────────────────────────────
function ensureEmailTables($db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS email_templates (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            type       VARCHAR(32) NOT NULL UNIQUE,
            subject    TEXT NOT NULL,
            body       TEXT NOT NULL,
            enabled    TINYINT(1) DEFAULT 1,
            created_at DATETIME,
            updated_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS email_logs (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT DEFAULT NULL,
            type       VARCHAR(32),
            to_email   VARCHAR(255),
            subject    VARCHAR(500),
            status     VARCHAR(20) DEFAULT 'sent',
            error_msg  TEXT,
            opened_at  DATETIME DEFAULT NULL,
            open_count INT DEFAULT 0,
            sent_at    DATETIME DEFAULT NULL,
            created_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS smtp_profiles (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(100) NOT NULL,
            host        VARCHAR(255) NOT NULL,
            port        SMALLINT DEFAULT 587,
            username    VARCHAR(255) NOT NULL,
            password    VARCHAR(255),
            from_email  VARCHAR(255),
            from_name   VARCHAR(100),
            provider    VARCHAR(50) DEFAULT 'smtp',
            is_default  TINYINT(1) DEFAULT 0,
            api_key     VARCHAR(500),
            created_at  DATETIME,
            updated_at  DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Portal tokens table (if not exists — portal.php may create it too)
    $db->exec("
        CREATE TABLE IF NOT EXISTS invoice_portal_tokens (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            token      VARCHAR(64) NOT NULL UNIQUE,
            created_at DATETIME,
            KEY idx_invoice (invoice_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ── Default built-in templates (used when DB has none) ───────────
function getDefaultTemplates(): array {
    return [

        // ── Pending Invoice ──────────────────────────────────────
        'invoice' => [
            'subject' => 'Invoice #{invoice_no} from {company_name} – {amount}',
            'body'    =>
"Dear {client_name},

Please find attached your invoice from {company_name}.

  Invoice No : #{invoice_no}
  Service    : {service}
  Amount Due : {amount}
  Issue Date : {issue_date}
  Due Date   : {due_date}

To pay via UPI, use: {upi}

You can also view, download, and pay your invoice online:
{invoice_link}

If you have any questions, feel free to contact us at {company_email} or {company_phone}.

Thank you for your business!
Warm regards,
{company_name}",
        ],

        // ── Estimate / Quotation ─────────────────────────────────
        'estimate' => [
            'subject' => 'Estimate #{invoice_no} from {company_name} – {amount}',
            'body'    =>
"Dear {client_name},

Thank you for your enquiry. Please find our estimate below.

  Estimate No : #{invoice_no}
  Service     : {service}
  Total       : {amount}
  Valid Until : {due_date}

This is an estimate only and is subject to change upon your approval.

To review, approve, or request changes, please visit:
{invoice_link}

We look forward to working with you!
Warm regards,
{company_name}
{company_phone}",
        ],

        // ── Payment Receipt (Paid / Partial) ─────────────────────
        'receipt' => [
            'subject' => 'Payment Receipt for Invoice #{invoice_no} – {company_name}',
            'body'    =>
"Dear {client_name},

Thank you! We have received your payment.

  Invoice No  : #{invoice_no}
  Amount Paid : {paid_amount}
  Balance Due : {remaining_amount}
  Service     : {service}

You can view your updated invoice and download the receipt here:
{invoice_link}

We truly appreciate your prompt payment. Looking forward to serving you again!

Warm regards,
{company_name}
{company_phone}",
        ],

        // ── Payment Reminder ─────────────────────────────────────
        'reminder' => [
            'subject' => 'Friendly Reminder: Invoice #{invoice_no} due on {due_date}',
            'body'    =>
"Dear {client_name},

This is a friendly reminder that Invoice #{invoice_no} for {amount} is due on {due_date}.

If you have already made the payment, please ignore this message.

To pay via UPI, use: {upi}

Or view and pay your invoice online:
{invoice_link}

Please feel free to reach us at {company_phone} if you have any questions.

Thank you,
{company_name}",
        ],

        // ── Overdue Notice ───────────────────────────────────────
        'overdue' => [
            'subject' => 'OVERDUE: Invoice #{invoice_no} — {days_overdue} day(s) past due',
            'body'    =>
"Dear {client_name},

⚠️ Your invoice is now {days_overdue} day(s) overdue.

  Invoice No : #{invoice_no}
  Amount Due : {amount}
  Due Date   : {due_date}

Immediate payment is requested to avoid any disruption to services.

Pay via UPI: {upi}

View and pay online:
{invoice_link}

If you are facing difficulties, please contact us immediately at {company_phone}.

Regards,
{company_name}",
        ],

        // ── Follow-up ────────────────────────────────────────────
        'followup' => [
            'subject' => 'Follow-up: Invoice #{invoice_no} still outstanding',
            'body'    =>
"Dear {client_name},

We are following up on Invoice #{invoice_no} for {amount}, which remains outstanding for {days_overdue} day(s).

We kindly request you to settle the amount at your earliest convenience.

Pay via UPI: {upi}

View your invoice here:
{invoice_link}

If you have any concerns or wish to discuss a payment arrangement, please call us at {company_phone}.

Thank you for your attention to this matter.
{company_name}",
        ],
    ];
}

// ================================================================
ob_end_clean();