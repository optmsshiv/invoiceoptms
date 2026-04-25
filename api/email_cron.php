<?php
// ================================================================
//  api/email_cron.php — Daily Email Automation Cron Job
//
//  Set up in cPanel → Cron Jobs:
//  Command: php /home/youraccount/public_html/api/email_cron.php
//  Schedule: Daily at 9:00 AM (0 9 * * *)
//
//  Handles:
//  - Due date reminders (N days before due)
//  - Overdue alerts (on due date if unpaid)
//  - Overdue follow-up sequence
// ================================================================
define('CRON_MODE', true);
ob_start();
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/db.php';
// Load PHPMailer if available
foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../vendor/autoload.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}

$db = getDB();
$today = date('Y-m-d');
$log   = [];

// Load settings
$cfgRows = $db->query("SELECT `key`, `value` FROM settings")->fetchAll();
$cfg = [];
foreach ($cfgRows as $r) $cfg[$r['key']] = $r['value'];

$autoRemind   = ($cfg['email_auto_remind']   ?? '1') === '1';
$autoOverdue  = ($cfg['email_auto_overdue']  ?? '1') === '1';
$autoFollowup = ($cfg['email_auto_followup'] ?? '0') === '1';
$remindDays   = (int)($cfg['email_remind_days']   ?? 3);
$followupDays = (int)($cfg['email_followup_days'] ?? 7);
$maxFollowup  = (int)($cfg['email_max_followup']  ?? 3);

if (!$autoRemind && !$autoOverdue && !$autoFollowup) {
    echo "All email automation is OFF. Nothing to do.\n";
    exit;
}

// ── Helper: fetch SMTP config ────────────────────────────────────
$smtp = [
    'host' => $cfg['smtp_host'] ?? '',
    'port' => (int)($cfg['smtp_port'] ?? 587),
    'user' => $cfg['smtp_user'] ?? '',
    'pass' => $cfg['smtp_pass'] ?? '',
    'from' => $cfg['smtp_from'] ?? $cfg['smtp_user'] ?? '',
    'name' => $cfg['smtp_name'] ?? 'OPTMS Tech',
];
if (empty($smtp['host']) || empty($smtp['user'])) {
    echo "SMTP not configured. Exiting.\n";
    exit;
}

// ── Helper: get email template ───────────────────────────────────
function getCronTemplate($db, string $type): array {
    $stmt = $db->prepare("SELECT subject, body FROM email_templates WHERE type = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$type]);
    $tpl = $stmt->fetch();
    return $tpl ?: ['subject' => '', 'body' => ''];
}

// ── Helper: replace variables ────────────────────────────────────
function cronReplaceVars(string $s, array $d): string {
    return str_replace(
        ['{client_name}','{invoice_no}','{amount}','{currency}','{due_date}','{issue_date}','{service}','{company_name}','{company_phone}','{days_overdue}','{invoice_link}','{upi}','{bank_details}'],
        [$d['client_name']??'',$d['invoice_number']??'',$d['amount']??'',$d['currency']??'₹',$d['due_date']??'',$d['issued_date']??'',$d['service_type']??'',$d['company_name']??'',$d['company_phone']??'',$d['days_overdue']??0,$d['invoice_link']??'',$d['upi']??'',$d['bank_details']??''],
        $s
    );
}

// ── Helper: send one email ───────────────────────────────────────
function cronSendEmail(array $smtp, string $to, string $toName, string $subject, string $html): bool {
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP(); $mail->Host = $smtp['host']; $mail->SMTPAuth = true;
            $mail->Username = $smtp['user']; $mail->Password = $smtp['pass'];
            $mail->SMTPSecure = ((int)$smtp['port'] === 465) ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)$smtp['port'];
            $mail->setFrom($smtp['from'], $smtp['name']);
            $mail->addAddress($to, $toName);
            $mail->isHTML(true); $mail->Subject = $subject; $mail->Body = $html; $mail->AltBody = strip_tags($html);
            $mail->send();
            return true;
        } catch (\Exception $e) { error_log('email_cron send error: ' . $e->getMessage()); return false; }
    }
    return (bool)@mail($to, $subject, $html, "From: {$smtp['name']} <{$smtp['from']}>\r\nContent-type: text/html; charset=UTF-8\r\n");
}

// ── Helper: log email ────────────────────────────────────────────
function cronLogEmail($db, array $d): void {
    try {
        $db->prepare("INSERT INTO email_logs (invoice_id,invoice_number,client_name,to_email,subject,status,type,sent_at) VALUES (?,?,?,?,?,?,?,NOW())")
           ->execute([$d['id'],$d['invoice_number'],$d['client_name'],$d['to'],$d['subject'],$d['ok']?'sent':'failed',$d['type']]);
    } catch (Exception $e) { error_log('cronLogEmail: '.$e->getMessage()); }
}

// ── Get company info ─────────────────────────────────────────────
$company = ['company_name'=>$cfg['company_name']??'OPTMS Tech','company_phone'=>$cfg['company_phone']??'','upi'=>$cfg['company_upi']??''];

// ── Helper: get portal link ──────────────────────────────────────
$appUrl = defined('APP_URL') ? APP_URL : 'http://invcs.optms.co.in';
function getLink($db, int $invId, string $appUrl): string {
    $stmt = $db->prepare("SELECT token FROM portal_tokens WHERE invoice_id = ? LIMIT 1");
    $stmt->execute([$invId]);
    $row = $stmt->fetch();
    if ($row) return $appUrl . '/portal?token=' . $row['token'];
    try {
        $token = bin2hex(random_bytes(16));
        $db->prepare("INSERT INTO portal_tokens (invoice_id,token) VALUES (?,?) ON DUPLICATE KEY UPDATE token=VALUES(token)")->execute([$invId,$token]);
        return $appUrl . '/portal?token=' . $token;
    } catch (Exception $e) { return $appUrl; }
}

// ── 1. DUE SOON REMINDER ────────────────────────────────────────
if ($autoRemind) {
    $reminderDate = date('Y-m-d', strtotime("+{$remindDays} days"));
    $stmt = $db->prepare("SELECT i.*, c.email as c_email FROM invoices i LEFT JOIN clients c ON c.id = i.client_id WHERE i.due_date = ? AND i.status IN ('Pending','Partial') AND (c.email IS NOT NULL AND c.email != '')");
    $stmt->execute([$reminderDate]);
    $invs = $stmt->fetchAll();
    $tpl  = getCronTemplate($db, 'reminder');
    foreach ($invs as $inv) {
        // Check not already sent today
        $chk = $db->prepare("SELECT id FROM email_logs WHERE invoice_id=? AND type='reminder' AND DATE(sent_at)=CURDATE() LIMIT 1");
        $chk->execute([$inv['id']]);
        if ($chk->fetch()) continue;
        $data = array_merge($inv, $company, ['amount' => number_format((float)$inv['grand_total'],2), 'invoice_link' => getLink($db,(int)$inv['id'],$appUrl), 'bank_details'=>$inv['bank_details']??'']);
        $subj = cronReplaceVars($tpl['subject'], $data);
        $body = cronReplaceVars($tpl['body'],    $data);
        $html = "<html><body style='font-family:Arial;padding:20px'>".nl2br(htmlspecialchars($body,ENT_QUOTES,'UTF-8'))."</body></html>";
        $ok = cronSendEmail($smtp, $inv['c_email'], $inv['client_name']??'Client', $subj, $html);
        cronLogEmail($db, array_merge($inv, ['to'=>$inv['c_email'],'subject'=>$subj,'type'=>'reminder','ok'=>$ok]));
        $log[] = ($ok?'✅':'❌') . " Reminder → {$inv['client_name']} ({$inv['c_email']}) — {$inv['invoice_number']}";
    }
}

// ── 2. OVERDUE ALERT ─────────────────────────────────────────────
if ($autoOverdue) {
    $stmt = $db->prepare("SELECT i.*, c.email as c_email, DATEDIFF(CURDATE(), i.due_date) as days_overdue FROM invoices i LEFT JOIN clients c ON c.id = i.client_id WHERE i.due_date < CURDATE() AND i.status IN ('Pending','Partial') AND (c.email IS NOT NULL AND c.email != '')");
    $stmt->execute();
    $invs = $stmt->fetchAll();
    $tpl  = getCronTemplate($db, 'overdue');
    foreach ($invs as $inv) {
        $chk = $db->prepare("SELECT id FROM email_logs WHERE invoice_id=? AND type='overdue' AND DATE(sent_at)=CURDATE() LIMIT 1");
        $chk->execute([$inv['id']]);
        if ($chk->fetch()) continue;
        $data = array_merge($inv, $company, ['amount'=>number_format((float)$inv['grand_total'],2),'days_overdue'=>$inv['days_overdue'],'invoice_link'=>getLink($db,(int)$inv['id'],$appUrl),'bank_details'=>$inv['bank_details']??'']);
        $subj = cronReplaceVars($tpl['subject'], $data);
        $body = cronReplaceVars($tpl['body'],    $data);
        $html = "<html><body style='font-family:Arial;padding:20px'>".nl2br(htmlspecialchars($body,ENT_QUOTES,'UTF-8'))."</body></html>";
        $ok = cronSendEmail($smtp, $inv['c_email'], $inv['client_name']??'Client', $subj, $html);
        cronLogEmail($db, array_merge($inv, ['to'=>$inv['c_email'],'subject'=>$subj,'type'=>'overdue','ok'=>$ok]));
        $log[] = ($ok?'✅':'❌') . " Overdue → {$inv['client_name']} — {$inv['invoice_number']} ({$inv['days_overdue']} days)";
    }
}

// ── 3. OVERDUE FOLLOW-UP ─────────────────────────────────────────
if ($autoFollowup) {
    $stmt = $db->prepare("SELECT i.*, c.email as c_email, DATEDIFF(CURDATE(), i.due_date) as days_overdue FROM invoices i LEFT JOIN clients c ON c.id = i.client_id WHERE i.due_date < CURDATE() AND i.status IN ('Pending','Partial') AND (c.email IS NOT NULL AND c.email != '')");
    $stmt->execute();
    $invs = $stmt->fetchAll();
    $tpl  = getCronTemplate($db, 'followup');
    foreach ($invs as $inv) {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM email_logs WHERE invoice_id=? AND type='followup'");
        $countStmt->execute([$inv['id']]);
        $sent = (int)$countStmt->fetchColumn();
        if ($sent >= $maxFollowup) continue;
        // Check last follow-up was at least $followupDays ago
        $lastStmt = $db->prepare("SELECT MAX(sent_at) FROM email_logs WHERE invoice_id=? AND type='followup'");
        $lastStmt->execute([$inv['id']]);
        $lastSent = $lastStmt->fetchColumn();
        if ($lastSent && strtotime($lastSent) > strtotime("-{$followupDays} days")) continue;
        $data = array_merge($inv, $company, ['amount'=>number_format((float)$inv['grand_total'],2),'days_overdue'=>$inv['days_overdue'],'invoice_link'=>getLink($db,(int)$inv['id'],$appUrl),'bank_details'=>$inv['bank_details']??'']);
        $subj = cronReplaceVars($tpl['subject'], $data);
        $body = cronReplaceVars($tpl['body'],    $data);
        $html = "<html><body style='font-family:Arial;padding:20px'>".nl2br(htmlspecialchars($body,ENT_QUOTES,'UTF-8'))."</body></html>";
        $ok = cronSendEmail($smtp, $inv['c_email'], $inv['client_name']??'Client', $subj, $html);
        cronLogEmail($db, array_merge($inv, ['to'=>$inv['c_email'],'subject'=>$subj,'type'=>'followup','ok'=>$ok]));
        $log[] = ($ok?'✅':'❌') . " Follow-up #{$sent} → {$inv['client_name']} — {$inv['invoice_number']}";
    }
}

echo "Done [" . date('Y-m-d H:i:s') . "]\n";
echo implode("\n", $log) . "\n";
echo "Total: " . count($log) . " emails processed\n";
