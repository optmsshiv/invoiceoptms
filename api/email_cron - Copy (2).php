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
//  - Overdue alerts (first day past due)
//  - Overdue follow-up sequence (every N days, up to max)
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

$db    = getDB();
$today = date('Y-m-d');
$log   = [];

// ── Load all settings ────────────────────────────────────────────
$cfgRows = $db->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_ASSOC);
$cfg = [];
foreach ($cfgRows as $r) $cfg[$r['key']] = $r['value'];

// ── Automation flags ─────────────────────────────────────────────
$autoRemind   = ($cfg['email_auto_remind']   ?? '1') === '1';
$autoOverdue  = ($cfg['email_auto_overdue']  ?? '1') === '1';
$autoFollowup  = ($cfg['email_auto_followup'] ?? '1') === '1';
$autoRecurring = ($cfg['email_auto_inv']      ?? '0') === '1'; // Recurring uses invoice creation toggle

// ── Timing rules: read from reminder_settings (single source of truth) ──
// Falls back to settings table keys for backward compat, then hardcoded defaults
$remSettings = [];
try {
    $remRow = $db->query("SELECT * FROM reminder_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    if ($remRow) $remSettings = $remRow;
} catch (Exception $e) {}
$remindDays   = max(1, (int)($remSettings['before_days']  ?? $cfg['before_days']  ?? $cfg['email_remind_days']   ?? 3));
$followupDays = max(1, (int)($remSettings['overdue_freq'] ?? $cfg['overdue_freq']  ?? $cfg['email_followup_days'] ?? 7));
$maxFollowup  = max(1, (int)($remSettings['max_overdue']  ?? $cfg['max_overdue']   ?? $cfg['email_max_followup']  ?? 3));

if (!$autoRemind && !$autoOverdue && !$autoFollowup) {
    echo "[" . date('Y-m-d H:i:s') . "] All email automation is OFF. Nothing to do.\n";
    exit;
}

// ── SMTP config: prefer default profile, fall back to settings ───
$smtp = [];
try {
    $prof = $db->query("SELECT * FROM smtp_profiles WHERE is_default=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($prof && !empty($prof['host'])) {
        $smtp = [
            'host' => $prof['host'],
            'port' => (int)$prof['port'],
            'user' => $prof['username'],
            'pass' => $prof['password'],
            'from' => $prof['from_email'] ?: $prof['username'],
            'name' => $prof['from_name']  ?: ($cfg['company_name'] ?? ''),
        ];
    }
} catch (Exception $e) {}

if (empty($smtp['host'])) {
    $smtp = [
        'host' => $cfg['smtp_host'] ?? '',
        'port' => (int)($cfg['smtp_port'] ?? 587),
        'user' => $cfg['smtp_user'] ?? '',
        'pass' => $cfg['smtp_pass'] ?? '',
        'from' => $cfg['smtp_from'] ?? $cfg['smtp_user'] ?? '',
        'name' => $cfg['smtp_name'] ?? ($cfg['company_name'] ?? ''),
    ];
}

if (empty($smtp['host']) || empty($smtp['user']) || empty($smtp['pass'])) {
    echo "[" . date('Y-m-d H:i:s') . "] SMTP not configured. Exiting.\n";
    exit;
}

// ── Company info ─────────────────────────────────────────────────
$company = [
    'company_name'  => $cfg['company_name']    ?? '',
    'company_phone' => $cfg['company_phone']   ?? '',
    'company_email' => $cfg['company_email']   ?? '',
    'upi'           => $cfg['company_upi']     ?? '',
    'bank_details'  => $cfg['company_bank']    ?? '',
];

// Portal base URL (trailing slash)
$portalBase = rtrim($cfg['portal_base_url'] ?? '', '/') . '/';
if (!$portalBase || $portalBase === '/') {
    // portal_base_url not configured — use server-relative fallback
    $portalBase = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/portal/';
}

// ================================================================
//  HELPERS
// ================================================================

// ── Load template from DB (uses `enabled`, not `is_active`) ─────
function getCronTemplate($db, string $type): array {
    // FIX: column is `enabled`, not `is_active`
    $stmt = $db->prepare("SELECT subject, body FROM email_templates WHERE type=? AND enabled=1 LIMIT 1");
    $stmt->execute([$type]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['subject'] && $row['body']) return $row;
    // Built-in fallbacks so cron never sends blank emails
    return getBuiltinTemplate($type);
}

function getBuiltinTemplate(string $type): array {
    $tpls = [
        'reminder' => [
            'subject' => 'Friendly Reminder: Invoice #{invoice_no} due on {due_date}',
            'body'    =>
"Dear {client_name},

This is a friendly reminder that Invoice #{invoice_no} for {amount} is due on {due_date}.

If you have already made the payment, please ignore this message.

Pay via UPI: {upi}

View & pay online: {invoice_link}

Thank you,
{company_name}
{company_phone}",
        ],
        'overdue' => [
            'subject' => 'OVERDUE: Invoice #{invoice_no} — {days_overdue} day(s) past due',
            'body'    =>
"Dear {client_name},

⚠️ Your invoice is now {days_overdue} day(s) overdue.

  Invoice No : #{invoice_no}
  Amount Due : {amount}
  Due Date   : {due_date}

Please arrange payment immediately.

Pay via UPI: {upi}

View & pay online: {invoice_link}

Regards,
{company_name}
{company_phone}",
        ],
        'followup' => [
            'subject' => 'Follow-up: Invoice #{invoice_no} still outstanding',
            'body'    =>
"Dear {client_name},

We are following up on Invoice #{invoice_no} for {amount}, which remains outstanding for {days_overdue} day(s).

Kindly settle the amount at your earliest convenience.

Pay via UPI: {upi}

View & pay online: {invoice_link}

If you have questions, call us at {company_phone}.

Thank you,
{company_name}",
        ],
    ];
    $tpls['recurring'] = [
        'subject' => 'Recurring Invoice #{invoice_no} from {company_name} — {amount}',
        'body'    =>
"Dear {client_name},

Your recurring invoice from {company_name} for this billing cycle is ready.

  Invoice No : #{invoice_no}
  Amount     : {amount}
  Issue Date : {issue_date}
  Due Date   : {due_date}
  Service    : {service}

Pay via UPI: {upi}
{bank_details}

{outstanding_dues}

View & download: {invoice_link}

Thank you,
{company_name}
{company_phone}",
    ];

    return $tpls[$type] ?? ['subject' => 'Invoice #{invoice_no} — {company_name}', 'body' => 'Dear {client_name}, please check your invoice #{invoice_no}.'];
}

// ── Replace all template variables ───────────────────────────────
function cronReplaceVars(string $s, array $d): string {
    $sym = $d['currency'] ?? '₹';
    return str_replace(
        ['{client_name}','{invoice_no}','{amount}','{currency}','{due_date}',
         '{issue_date}','{service}','{company_name}','{company_phone}','{company_email}',
         '{days_overdue}','{invoice_link}','{upi}','{bank_details}',
         '{outstanding_dues}','{total_payable}'],
        [
            $d['client_name']      ?? '',
            $d['invoice_number']   ?? '',
            $sym . number_format((float)($d['grand_total'] ?? $d['amount'] ?? 0), 2),
            $sym,
            isset($d['due_date'])    ? date('d M Y', strtotime($d['due_date']))    : '',
            isset($d['issued_date']) ? date('d M Y', strtotime($d['issued_date'])) : '',
            $d['service_type']     ?? $d['service'] ?? '',
            $d['company_name']     ?? '',
            $d['company_phone']    ?? '',
            $d['company_email']    ?? '',
            (string)($d['days_overdue'] ?? 0),
            $d['invoice_link']     ?? '',
            $d['upi']              ?? '',
            $d['bank_details']     ?? '',
            $d['outstanding_dues'] ?? '',
            $d['total_payable']    ?? '',
        ],
        $s
    );
}

// ── Styled HTML email (type-aware accent) ────────────────────────
function cronBuildHTML(string $body, string $type): string {
    $accents = [
        'reminder'  => ['#F9A825', '🔔 Payment Reminder'],
        'overdue'   => ['#E53935', '⚠️ Invoice Overdue'],
        'followup'  => ['#7B1FA2', '📞 Follow-up Notice'],
        'recurring' => ['#3949AB', '🔁 Recurring Invoice'],
    ];
    [$color, $heading] = $accents[$type] ?? ['#00897B', '📄 Invoice'];
    $b = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
    $b = preg_replace('/(https?:\/\/[^\s<]+)/i', '<a href="$1" style="color:'.$color.';word-break:break-all">$1</a>', $b);
    return <<<HTML
<html><head><style>
body{font-family:Arial,sans-serif;background:#f5f5f5;padding:20px;margin:0}
.wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.10)}
.hdr{background:{$color};color:#fff;padding:24px 32px;font-size:18px;font-weight:700}
.bdy{padding:28px 32px;color:#333;font-size:15px;line-height:1.85}
.ftr{background:#f9f9f9;padding:14px 32px;font-size:12px;color:#999;border-top:1px solid #eee;text-align:center}
a{color:{$color}}
</style></head><body>
<div class="wrap">
  <div class="hdr">{$heading}</div>
  <div class="bdy">{$b}</div>
  <div class="ftr">Sent via Invoice Manager</div>
</div>
</body></html>
HTML;
}

// ── Get or create portal link for invoice ────────────────────────
// FIX: correct table name is `invoice_portal_tokens`, not `portal_tokens`
function cronGetPortalLink($db, int $invId, string $portalBase): string {
    try {
        $stmt = $db->prepare("SELECT token FROM invoice_portal_tokens WHERE invoice_id=? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$invId]);
        $token = $stmt->fetchColumn();
        if (!$token) {
            $token = bin2hex(random_bytes(24));
            $db->prepare("INSERT INTO invoice_portal_tokens (invoice_id, token, created_at) VALUES (?, ?, NOW())")
               ->execute([$invId, $token]);
        }
        return $portalBase . '?t=' . $token;
    } catch (Exception $e) {
        error_log('cronGetPortalLink: ' . $e->getMessage());
        return '';
    }
}

// ── Send email via PHPMailer or PHP mail() ───────────────────────
function cronSendEmail(array $smtp, string $to, string $toName, string $subject, string $html): bool {
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['user'];
            $mail->Password   = $smtp['pass'];
            $mail->SMTPSecure = ((int)$smtp['port'] === 465)
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)$smtp['port'];
            $mail->setFrom($smtp['from'], $smtp['name']);
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = strip_tags(str_replace(['<br>','<br/>','</p>'], "\n", $html));
            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log('email_cron send error: ' . $e->getMessage());
            return false;
        }
    }
    // Fallback: native mail()
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$smtp['name']} <{$smtp['from']}>\r\nReply-To: {$smtp['from']}\r\n";
    return (bool)@mail($to, $subject, $html, $headers);
}

// ── Log sent email ───────────────────────────────────────────────
// FIX: only inserts columns that actually exist in email_logs table
function cronLogEmail($db, int $invId, string $type, string $to, string $subject, bool $ok, string $error = ''): void {
    try {
        $db->prepare("INSERT INTO email_logs (invoice_id, type, to_email, subject, status, error_msg, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())")
           ->execute([$invId ?: null, $type, $to, $subject, $ok ? 'sent' : 'failed', $error ?: null]);
    } catch (Exception $e) {
        error_log('cronLogEmail: ' . $e->getMessage());
    }
}

// ── Check if this email type was already sent today for this invoice ──
function alreadySentToday($db, int $invId, string $type): bool {
    $stmt = $db->prepare("SELECT id FROM email_logs WHERE invoice_id=? AND type=? AND DATE(created_at)=CURDATE() LIMIT 1");
    $stmt->execute([$invId, $type]);
    return (bool)$stmt->fetch();
}

// ================================================================
//  1. DUE SOON REMINDER
//     Only for: Pending, Partial  — NOT Paid, Draft, Cancelled, Estimate
// ================================================================
if ($autoRemind) {
    $reminderDate = date('Y-m-d', strtotime("+{$remindDays} days"));
    $stmt = $db->prepare("
        SELECT i.*, c.email AS c_email, c.name AS client_name
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        WHERE i.due_date = ?
          AND i.status IN ('Pending', 'Partial')
          AND c.email IS NOT NULL AND c.email != ''
    ");
    $stmt->execute([$reminderDate]);
    $invs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tpl  = getCronTemplate($db, 'reminder');

    foreach ($invs as $inv) {
        // Skip if already reminded today
        if (alreadySentToday($db, (int)$inv['id'], 'reminder')) continue;

        $inv['invoice_link'] = cronGetPortalLink($db, (int)$inv['id'], $portalBase);
        $data = array_merge($inv, $company);
        $subj = cronReplaceVars($tpl['subject'], $data);
        $body = cronReplaceVars($tpl['body'],    $data);
        $html = cronBuildHTML($body, 'reminder');

        $ok  = cronSendEmail($smtp, $inv['c_email'], $inv['client_name'] ?? 'Client', $subj, $html);
        cronLogEmail($db, (int)$inv['id'], 'reminder', $inv['c_email'], $subj, $ok);
        $log[] = ($ok ? '✅' : '❌') . " Reminder → {$inv['client_name']} ({$inv['c_email']}) — {$inv['invoice_number']}";
    }
    echo "[Reminder] " . count($invs) . " invoice(s) checked (due in {$remindDays} days)\n";
}

// ================================================================
//  1b. ON DUE DATE REMINDER
//      Fires when due_date = today AND on_due setting is enabled
//      (respects reminder_settings.on_due, fallback to settings table)
// ================================================================
$onDue = ($remSettings['on_due'] ?? $cfg['on_due'] ?? '1') === '1';
if ($autoRemind && $onDue) {
    $stmt = $db->prepare("
        SELECT i.*, c.email AS c_email, c.name AS client_name
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        WHERE i.due_date = CURDATE()
          AND i.status IN ('Pending', 'Partial')
          AND c.email IS NOT NULL AND c.email != ''
    ");
    $stmt->execute();
    $invs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tpl  = getCronTemplate($db, 'reminder');
    $sent = 0;
    foreach ($invs as $inv) {
        if (alreadySentToday($db, (int)$inv['id'], 'reminder')) continue;
        $inv['invoice_link'] = cronGetPortalLink($db, (int)$inv['id'], $portalBase);
        $data = array_merge($inv, $company);
        $subj = cronReplaceVars($tpl['subject'], $data);
        $body = cronReplaceVars($tpl['body'],    $data);
        $html = cronBuildHTML($body, 'reminder');
        $ok   = cronSendEmail($smtp, $inv['c_email'], $inv['client_name'] ?? 'Client', $subj, $html);
        cronLogEmail($db, (int)$inv['id'], 'reminder', $inv['c_email'], $subj, $ok);
        $log[] = ($ok ? '✅' : '❌') . " Due Today → {$inv['client_name']} ({$inv['c_email']}) — #{$inv['invoice_number']}";
        $sent++;
    }
    echo "[On Due] {$sent} due-today reminder(s) sent out of " . count($invs) . " eligible invoice(s)\n";
}

// ================================================================
//  2. OVERDUE ALERT
//     - Invoice is past due date
//     - Status must be Pending or Partial (NOT Paid, not Cancelled)
//     - Only send once per day per invoice
// ================================================================
if ($autoOverdue) {
    $stmt = $db->prepare("
        SELECT i.*,
               c.email AS c_email,
               c.name  AS client_name,
               DATEDIFF(CURDATE(), i.due_date) AS days_overdue
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        WHERE i.due_date < CURDATE()
          AND i.status IN ('Pending', 'Partial')
          AND c.email IS NOT NULL AND c.email != ''
    ");
    $stmt->execute();
    $invs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tpl  = getCronTemplate($db, 'overdue');
    $sent = 0;

    foreach ($invs as $inv) {
        // Skip if already sent an overdue email today
        if (alreadySentToday($db, (int)$inv['id'], 'overdue')) continue;

        $inv['invoice_link'] = cronGetPortalLink($db, (int)$inv['id'], $portalBase);
        $data = array_merge($inv, $company);
        $subj = cronReplaceVars($tpl['subject'], $data);
        $body = cronReplaceVars($tpl['body'],    $data);
        $html = cronBuildHTML($body, 'overdue');

        $ok  = cronSendEmail($smtp, $inv['c_email'], $inv['client_name'] ?? 'Client', $subj, $html);
        cronLogEmail($db, (int)$inv['id'], 'overdue', $inv['c_email'], $subj, $ok);
        $log[] = ($ok ? '✅' : '❌') . " Overdue → {$inv['client_name']} — #{$inv['invoice_number']} ({$inv['days_overdue']} days)";
        $sent++;
    }
    echo "[Overdue] {$sent} overdue alert(s) sent out of " . count($invs) . " eligible invoice(s)\n";
}

// ================================================================
//  3. OVERDUE FOLLOW-UP SEQUENCE
//     - Past due AND Pending/Partial only (never Paid/Cancelled)
//     - Caps at $maxFollowup total follow-up emails per invoice
//     - Waits at least $followupDays between each follow-up
// ================================================================
if ($autoFollowup) {
    $stmt = $db->prepare("
        SELECT i.*,
               c.email AS c_email,
               c.name  AS client_name,
               DATEDIFF(CURDATE(), i.due_date) AS days_overdue
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        WHERE i.due_date < CURDATE()
          AND i.status IN ('Pending', 'Partial')
          AND c.email IS NOT NULL AND c.email != ''
    ");
    $stmt->execute();
    $invs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tpl  = getCronTemplate($db, 'followup');
    $sent = 0;

    foreach ($invs as $inv) {
        $invId = (int)$inv['id'];

        // How many follow-ups already sent?
        $cntStmt = $db->prepare("SELECT COUNT(*) FROM email_logs WHERE invoice_id=? AND type='followup'");
        $cntStmt->execute([$invId]);
        $totalSent = (int)$cntStmt->fetchColumn();
        if ($totalSent >= $maxFollowup) continue; // Cap reached

        // When was the last follow-up (or overdue alert) sent?
        $lastStmt = $db->prepare("SELECT MAX(created_at) FROM email_logs WHERE invoice_id=? AND type IN ('followup','overdue')");
        $lastStmt->execute([$invId]);
        $lastSent = $lastStmt->fetchColumn();
        if ($lastSent && strtotime($lastSent) > strtotime("-{$followupDays} days")) continue; // Too soon

        // Skip if already sent a follow-up today
        if (alreadySentToday($db, $invId, 'followup')) continue;

        $inv['invoice_link'] = cronGetPortalLink($db, $invId, $portalBase);
        $data = array_merge($inv, $company);
        $subj = cronReplaceVars($tpl['subject'], $data);
        $body = cronReplaceVars($tpl['body'],    $data);
        $html = cronBuildHTML($body, 'followup');

        $ok  = cronSendEmail($smtp, $inv['c_email'], $inv['client_name'] ?? 'Client', $subj, $html);
        cronLogEmail($db, $invId, 'followup', $inv['c_email'], $subj, $ok);
        $label = $ok ? '✅' : '❌';
        $log[] = "{$label} Follow-up #" . ($totalSent + 1) . " → {$inv['client_name']} — #{$inv['invoice_number']} ({$inv['days_overdue']} days)";
        $sent++;
    }
    echo "[Follow-up] {$sent} follow-up(s) sent out of " . count($invs) . " eligible invoice(s)\n";
}

// ================================================================
//  4. RECURRING INVOICE EMAIL
//     Sends email for invoices generated by recurring schedules today.
//     Checks recurring_generated_today flag on invoices (or uses
//     invoice created_at = today as proxy).
// ================================================================
if ($autoRecurring) {
    $stmt = $db->prepare("
        SELECT i.*,
               c.email      AS c_email,
               c.name       AS client_name,
               rs.client_id AS rec_client_id
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        INNER JOIN recurring_schedules rs ON rs.client_id = i.client_id
        WHERE DATE(i.created_at) = CURDATE()
          AND i.status IN ('Pending')
          AND rs.status = 'active'
          AND c.email IS NOT NULL AND c.email != ''
        GROUP BY i.id
    ");
    $stmt->execute();
    $invs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tpl  = getCronTemplate($db, 'recurring');
    $sent = 0;

    foreach ($invs as $inv) {
        $invId = (int)$inv['id'];
        if (alreadySentToday($db, $invId, 'recurring')) continue;

        // Build outstanding dues for this client
        $outstandingDues = '';
        $totalPayable    = (float)($inv['grand_total'] ?? $inv['amount'] ?? 0);
        try {
            $os = $db->prepare("
                SELECT invoice_number, grand_total, amount, status, due_date
                FROM invoices
                WHERE (client_id=? OR client=?) AND status IN ('Pending','Overdue','Partial') AND id != ?
                ORDER BY due_date ASC
            ");
            $os->execute([$inv['client_id'] ?? 0, $inv['client_id'] ?? 0, $invId]);
            $prev = $os->fetchAll(PDO::FETCH_ASSOC);
            if ($prev) {
                $lines = [];
                foreach ($prev as $p) {
                    $pAmt = (float)($p['grand_total'] ?? $p['amount'] ?? 0);
                    $totalPayable += $pAmt;
                    $sym = $inv['currency'] ?? '₹';
                    $lines[] = "  #{$p['invoice_number']} — {$sym}" . number_format($pAmt, 2) . " ({$p['status']}) Due: {$p['due_date']}";
                }
                $outstandingDues =
                    "Previous Outstanding Dues:
" .
                    implode("
", $lines) . "
" .
                    "Total Payable (all invoices): " . ($inv['currency'] ?? '₹') . number_format($totalPayable, 2);
            }
        } catch (Exception $e) {}

        $inv['invoice_link']     = cronGetPortalLink($db, $invId, $portalBase);
        $inv['outstanding_dues'] = $outstandingDues;
        $inv['total_payable']    = ($outstandingDues ? ($inv['currency'] ?? '₹') . number_format($totalPayable, 2) : '');
        $data = array_merge($inv, $company);
        $subj = cronReplaceVars($tpl['subject'], $data);
        $body = cronReplaceVars($tpl['body'],    $data);
        $html = cronBuildHTML($body, 'recurring');

        $ok  = cronSendEmail($smtp, $inv['c_email'], $inv['client_name'] ?? 'Client', $subj, $html);
        cronLogEmail($db, $invId, 'recurring', $inv['c_email'], $subj, $ok);
        $log[] = ($ok ? '✅' : '❌') . " Recurring → {$inv['client_name']} ({$inv['c_email']}) — #{$inv['invoice_number']}";
        $sent++;
    }
    echo "[Recurring] {$sent} recurring invoice email(s) sent
";
}

// ================================================================
echo "
=== Cron complete [" . date('Y-m-d H:i:s') . "] ===\n";
echo implode("\n", $log) . "\n";
echo "Total emails processed: " . count($log) . "\n";
ob_end_flush();