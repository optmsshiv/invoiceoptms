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
$remChannel   = $remSettings['channel'] ?? 'whatsapp'; // FIX: was 'email', must match wa_cron default
// If reminder_settings.channel is 'whatsapp', email cron should do nothing
if ($remChannel === 'whatsapp') {
    echo "[" . date('Y-m-d H:i:s') . "] Reminder channel set to whatsapp-only. Email cron skipping.\n";
    exit;
}

// ── Send-time guard ───────────────────────────────────────────────
// Only applies if send_hour column exists in reminder_settings.
// If column missing (migration not run yet), skip guard and always run.
date_default_timezone_set('Asia/Kolkata');
$sendHour   = isset($remSettings['send_hour']) ? (int)$remSettings['send_hour']   : null;
$sendMinute = isset($remSettings['send_hour']) ? (int)($remSettings['send_minute'] ?? 0) : null;

if ($sendHour !== null) {
    $nowTotalMin  = (int)date('G') * 60 + (int)date('i');
    $sendTotalMin = $sendHour * 60 + $sendMinute;
    $diffMin      = abs($nowTotalMin - $sendTotalMin);
    if ($diffMin > 20 && !isset($_GET['force']) && !defined('CRON_FORCE')) {
        echo "[" . date('Y-m-d H:i:s') . "] Not in send window (configured: " .
             sprintf('%02d:%02d', $sendHour, $sendMinute) . " IST, now: " . date('H:i') .
             ", diff: {$diffMin} min, tolerance ±20 min). Exiting.\n";
        exit;
    }
    echo "[" . date('Y-m-d H:i:s') . "] Send window OK (configured: " .
         sprintf('%02d:%02d', $sendHour, $sendMinute) . " IST, diff: {$diffMin} min).\n";
} else {
    echo "[" . date('Y-m-d H:i:s') . "] send_hour not configured — skipping time guard, running now.\n";
}

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
            $sym . number_format((float)($d['_display_amt'] ?? $d['grand_total'] ?? $d['amount'] ?? 0), 2),
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
    // FIX: Use IST date — MySQL CURDATE() is UTC, but cron runs at IST time
    $stmt = $db->prepare("SELECT id FROM email_logs WHERE invoice_id=? AND type=? AND DATE(CONVERT_TZ(created_at,'UTC','Asia/Kolkata'))=CURDATE() LIMIT 1");
    $stmt->execute([$invId, $type]);
    return (bool)$stmt->fetch();
}

// ── Remaining balance for Partial invoices ──────────────────────
function emailGetDisplayAmt($db, array $inv): float {
    $grand = (float)($inv['grand_total'] ?? $inv['amount'] ?? 0);
    if (($inv['status'] ?? '') === 'Partial') {
        try {
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=?");
            $stmt->execute([$inv['id']]);
            $paid = (float)$stmt->fetchColumn();
            return max(0, $grand - $paid);
        } catch (Exception $e) {}
    }
    return $grand;
}

// ── Suppress overdue/followup if active promise-to-pay exists ───
function emailHasActivePromise($db, int $invId): bool {
    try {
        $stmt = $db->prepare(
            "SELECT id FROM promise_to_pay
             WHERE invoice_id=? AND status IN ('pending','reminded')
             AND promise_date >= CURDATE() LIMIT 1"
        );
        $stmt->execute([$invId]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}


// ── Consolidated email helpers ────────────────────────────────────

/**
 * Groups invoice rows by client email.
 * Returns: [ email => ['email'=>..,'name'=>..,'invs'=>[..]], .. ]
 */
function emailGroupByClient(array $invs): array {
    $groups = [];
    foreach ($invs as $inv) {
        $email = $inv['c_email'] ?? '';
        if (!$email) continue;
        if (!isset($groups[$email])) {
            $groups[$email] = [
                'email' => $email,
                'name'  => $inv['client_name'] ?? 'Client',
                'invs'  => [],
            ];
        }
        $groups[$email]['invs'][] = $inv;
    }
    return $groups;
}

/**
 * Build a styled HTML invoice table for all invoices in a group.
 * Used in consolidated emails instead of single-invoice body text.
 */
function emailBuildInvoiceTable($db, array $invs, array $company, string $portalBase): array {
    $sym        = $invs[0]['currency'] ?? '₹';
    $totalAmt   = 0;
    $rows       = '';
    $links      = [];
    $maxOverdue = 0;

    foreach ($invs as $inv) {
        $displayAmt = emailGetDisplayAmt($db, $inv);
        $totalAmt  += $displayAmt;
        $dueDate    = !empty($inv['due_date']) ? date('d M Y', strtotime($inv['due_date'])) : '—';
        $daysOver   = max(0, (int)($inv['days_overdue'] ?? 0));
        if ($daysOver > $maxOverdue) $maxOverdue = $daysOver;
        $amtFmt     = $sym . number_format($displayAmt, 2);
        $status     = $inv['status'] ?? '';
        $statusCol  = $status === 'Partial' ? '#B45309' : '#C0392B';
        $link       = cronGetPortalLink($db, (int)$inv['id'], $portalBase);
        $links[]    = $link;
        $invNo      = htmlspecialchars($inv['invoice_number'] ?? '', ENT_QUOTES);
        $rows .= "<tr>
          <td style='padding:8px 12px;border-bottom:1px solid #f0f0f0;font-weight:700;font-family:monospace'>#{$invNo}</td>
          <td style='padding:8px 12px;border-bottom:1px solid #f0f0f0'>{$dueDate}</td>
          <td style='padding:8px 12px;border-bottom:1px solid #f0f0f0;color:{$statusCol};font-weight:600'>{$status}" .
          ($daysOver > 0 ? " <span style='font-size:11px'>({$daysOver}d)</span>" : '') .
          "</td>
          <td style='padding:8px 12px;border-bottom:1px solid #f0f0f0;font-weight:700;text-align:right'>{$amtFmt}</td>
          <td style='padding:8px 12px;border-bottom:1px solid #f0f0f0;text-align:center'>" .
          ($link ? "<a href='{$link}' style='font-size:12px;color:#00897B;font-weight:600'>Pay →</a>" : '—') .
          "</td>
        </tr>
";
    }

    $totalFmt = $sym . number_format($totalAmt, 2);
    $table = "
<table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px'>
  <thead>
    <tr style='background:#f5f5f5'>
      <th style='padding:9px 12px;text-align:left;font-size:12px;color:#666;border-bottom:2px solid #ddd'>Invoice</th>
      <th style='padding:9px 12px;text-align:left;font-size:12px;color:#666;border-bottom:2px solid #ddd'>Due Date</th>
      <th style='padding:9px 12px;text-align:left;font-size:12px;color:#666;border-bottom:2px solid #ddd'>Status</th>
      <th style='padding:9px 12px;text-align:right;font-size:12px;color:#666;border-bottom:2px solid #ddd'>Amount Due</th>
      <th style='padding:9px 12px;text-align:center;font-size:12px;color:#666;border-bottom:2px solid #ddd'>Portal</th>
    </tr>
  </thead>
  <tbody>{$rows}</tbody>
  <tfoot>
    <tr style='background:#fafafa'>
      <td colspan='3' style='padding:10px 12px;font-weight:700;font-size:13px'>Total Outstanding</td>
      <td style='padding:10px 12px;font-weight:700;font-size:15px;text-align:right;color:#C0392B'>{$totalFmt}</td>
      <td></td>
    </tr>
  </tfoot>
</table>";

    return [
        'table'       => $table,
        'total_amt'   => $totalAmt,
        'total_fmt'   => $totalFmt,
        'sym'         => $sym,
        'max_overdue' => $maxOverdue,
        'links'       => $links,
        'primary_link'=> $links[0] ?? '',
    ];
}

/**
 * Build consolidated HTML email body for multiple invoices.
 */
function emailBuildConsolidatedHTML(
    string $clientName, string $type, array $tableData,
    array $company, string $intro
): string {
    $accents = [
        'reminder' => ['#F9A825', '🔔 Payment Reminder'],
        'overdue'  => ['#E53935', '⚠️ Invoices Overdue'],
        'followup' => ['#7B1FA2', '📞 Follow-up Notice'],
    ];
    [$color, $heading] = $accents[$type] ?? ['#00897B', '📄 Invoice Summary'];

    $safeIntro  = nl2br(htmlspecialchars($intro, ENT_QUOTES, 'UTF-8'));
    $table      = $tableData['table']; // already HTML, don't escape
    $upi        = htmlspecialchars($company['upi'] ?? '', ENT_QUOTES);
    $phone      = htmlspecialchars($company['company_phone'] ?? '', ENT_QUOTES);
    $cname      = htmlspecialchars($company['company_name'] ?? '', ENT_QUOTES);

    return <<<HTML
<html><head><style>
body{font-family:Arial,sans-serif;background:#f5f5f5;padding:20px;margin:0}
.wrap{max-width:620px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.10)}
.hdr{background:{$color};color:#fff;padding:24px 32px;font-size:18px;font-weight:700}
.bdy{padding:24px 32px;color:#333;font-size:15px;line-height:1.8}
.ftr{background:#f9f9f9;padding:14px 32px;font-size:12px;color:#999;border-top:1px solid #eee;text-align:center}
a{color:{$color}}
</style></head><body>
<div class="wrap">
  <div class="hdr">{$heading}</div>
  <div class="bdy">
    <p>{$safeIntro}</p>
    {$table}
    <p style="margin-top:16px">Pay via UPI: <strong>{$upi}</strong></p>
    <p style="color:#888;font-size:13px">For queries, call us at {$phone}.</p>
    <p>Thank you,<br><strong>{$cname}</strong></p>
  </div>
  <div class="ftr">Sent via Invoice Manager</div>
</div>
</body></html>
HTML;
}

// ================================================================
//  1. DUE SOON REMINDER — CONSOLIDATED (one email per client)
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
        ORDER BY i.due_date ASC
    ");
    $stmt->execute([$reminderDate]);
    $invs   = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $groups = emailGroupByClient($invs);
    $sent   = 0;

    foreach ($groups as $group) {
        $eligible = array_filter($group['invs'],
            fn($inv) => !alreadySentToday($db, (int)$inv['id'], 'reminder'));
        if (empty($eligible)) continue;
        $eligible = array_values($eligible);

        if (count($eligible) === 1) {
            // Single invoice: use existing per-invoice template
            $inv = $eligible[0];
            $inv['_display_amt'] = emailGetDisplayAmt($db, $inv);
            $inv['invoice_link'] = cronGetPortalLink($db, (int)$inv['id'], $portalBase);
            $tpl  = getCronTemplate($db, 'reminder');
            $data = array_merge($inv, $company);
            $subj = cronReplaceVars($tpl['subject'], $data);
            $body = cronReplaceVars($tpl['body'], $data);
            $html = cronBuildHTML($body, 'reminder');
            $ok   = cronSendEmail($smtp, $group['email'], $group['name'], $subj, $html);
            cronLogEmail($db, (int)$inv['id'], 'reminder', $group['email'], $subj, $ok);
            $log[] = ($ok ? '✅' : '❌') . " Reminder → {$group['name']} ({$group['email']}) — #{$inv['invoice_number']}";
        } else {
            // Multiple invoices: one consolidated email with invoice table
            $tableData = emailBuildInvoiceTable($db, $eligible, $company, $portalBase);
            $intro = "Dear {$group['name']},

This is a friendly reminder that you have " . count($eligible) .
                     " invoice(s) due on " . date('d M Y', strtotime($reminderDate)) .
                     " totalling {$tableData['total_fmt']}. Kindly arrange payment before the due date.";
            $subj  = "Payment Reminder: " . count($eligible) . " invoice(s) due — {$tableData['total_fmt']}";
            $html  = emailBuildConsolidatedHTML($group['name'], 'reminder', $tableData, $company, $intro);
            $ok    = cronSendEmail($smtp, $group['email'], $group['name'], $subj, $html);
            foreach ($eligible as $inv) {
                cronLogEmail($db, (int)$inv['id'], 'reminder', $group['email'], $subj, $ok);
            }
            $invNums = implode(', ', array_map(fn($i) => '#'.($i['invoice_number']??''), $eligible));
            $log[] = ($ok ? '✅' : '❌') . " Reminder (consolidated) → {$group['name']} — {$invNums} — Total: {$tableData['total_fmt']}";
        }
        $sent++;
    }
    $total = array_sum(array_map(fn($g) => count($g['invs']), $groups));
    echo "[Reminder] {$sent} client(s) notified covering {$total} invoice(s) (due in {$remindDays} days)\n";
}

// ================================================================
//  1b. ON DUE DATE REMINDER — CONSOLIDATED
// ================================================================
$onDue = ($remSettings['on_due'] ?? $cfg['on_due'] ?? '1') == '1'; // == not === (DB returns int)
if ($autoRemind && $onDue) {
    $stmt = $db->prepare("
        SELECT i.*, c.email AS c_email, c.name AS client_name
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        WHERE i.due_date = CURDATE()
          AND i.status IN ('Pending', 'Partial')
          AND c.email IS NOT NULL AND c.email != ''
        ORDER BY i.due_date ASC
    ");
    $stmt->execute();
    $invs   = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $groups = emailGroupByClient($invs);
    $sent   = 0;

    foreach ($groups as $group) {
        $eligible = array_filter($group['invs'],
            fn($inv) => !alreadySentToday($db, (int)$inv['id'], 'reminder'));
        if (empty($eligible)) continue;
        $eligible = array_values($eligible);

        if (count($eligible) === 1) {
            $inv = $eligible[0];
            $inv['_display_amt'] = emailGetDisplayAmt($db, $inv);
            $inv['invoice_link'] = cronGetPortalLink($db, (int)$inv['id'], $portalBase);
            $tpl  = getCronTemplate($db, 'reminder');
            $data = array_merge($inv, $company);
            $subj = cronReplaceVars($tpl['subject'], $data);
            $body = cronReplaceVars($tpl['body'], $data);
            $html = cronBuildHTML($body, 'reminder');
            $ok   = cronSendEmail($smtp, $group['email'], $group['name'], $subj, $html);
            cronLogEmail($db, (int)$inv['id'], 'reminder', $group['email'], $subj, $ok);
            $log[] = ($ok ? '✅' : '❌') . " Due Today → {$group['name']} ({$group['email']}) — #{$inv['invoice_number']}";
        } else {
            $tableData = emailBuildInvoiceTable($db, $eligible, $company, $portalBase);
            $intro = "Dear {$group['name']},

This is a reminder that you have " . count($eligible) .
                     " invoice(s) due today totalling {$tableData['total_fmt']}. Please arrange payment today.";
            $subj  = "Due Today: " . count($eligible) . " invoice(s) — {$tableData['total_fmt']}";
            $html  = emailBuildConsolidatedHTML($group['name'], 'reminder', $tableData, $company, $intro);
            $ok    = cronSendEmail($smtp, $group['email'], $group['name'], $subj, $html);
            foreach ($eligible as $inv) {
                cronLogEmail($db, (int)$inv['id'], 'reminder', $group['email'], $subj, $ok);
            }
            $invNums = implode(', ', array_map(fn($i) => '#'.($i['invoice_number']??''), $eligible));
            $log[] = ($ok ? '✅' : '❌') . " Due Today (consolidated) → {$group['name']} — {$invNums} — Total: {$tableData['total_fmt']}";
        }
        $sent++;
    }
    $total = array_sum(array_map(fn($g) => count($g['invs']), $groups));
    echo "[On Due] {$sent} client(s) notified covering {$total} invoice(s)\n";
}

// ================================================================
//  2. OVERDUE ALERT — CONSOLIDATED
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
        ORDER BY i.due_date ASC
    ");
    $stmt->execute();
    $invs   = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $groups = emailGroupByClient($invs);
    $sent   = 0;

    foreach ($groups as $group) {
        $eligible = array_filter($group['invs'], function($inv) use ($db) {
            if (alreadySentToday($db, (int)$inv['id'], 'overdue'))    return false;
            if (emailHasActivePromise($db, (int)$inv['id']))           return false;
            return true;
        });
        if (empty($eligible)) continue;
        $eligible = array_values($eligible);

        if (count($eligible) === 1) {
            $inv = $eligible[0];
            $inv['_display_amt'] = emailGetDisplayAmt($db, $inv);
            $inv['invoice_link'] = cronGetPortalLink($db, (int)$inv['id'], $portalBase);
            $tpl  = getCronTemplate($db, 'overdue');
            $data = array_merge($inv, $company);
            $subj = cronReplaceVars($tpl['subject'], $data);
            $body = cronReplaceVars($tpl['body'], $data);
            $html = cronBuildHTML($body, 'overdue');
            $ok   = cronSendEmail($smtp, $group['email'], $group['name'], $subj, $html);
            cronLogEmail($db, (int)$inv['id'], 'overdue', $group['email'], $subj, $ok);
            $log[] = ($ok ? '✅' : '❌') . " Overdue → {$group['name']} — #{$inv['invoice_number']} ({$inv['days_overdue']} days)";
        } else {
            $tableData = emailBuildInvoiceTable($db, $eligible, $company, $portalBase);
            $maxDays   = $tableData['max_overdue'];
            $intro = "Dear {$group['name']},

You have " . count($eligible) .
                     " overdue invoice(s) totalling {$tableData['total_fmt']}. " .
                     "The oldest is {$maxDays} day(s) past due. Please arrange payment immediately.";
            $subj  = "⚠️ OVERDUE: " . count($eligible) . " invoice(s) — {$tableData['total_fmt']}";
            $html  = emailBuildConsolidatedHTML($group['name'], 'overdue', $tableData, $company, $intro);
            $ok    = cronSendEmail($smtp, $group['email'], $group['name'], $subj, $html);
            foreach ($eligible as $inv) {
                cronLogEmail($db, (int)$inv['id'], 'overdue', $group['email'], $subj, $ok);
            }
            $invNums = implode(', ', array_map(fn($i) => '#'.($i['invoice_number']??''), $eligible));
            $log[] = ($ok ? '✅' : '❌') . " Overdue (consolidated) → {$group['name']} — {$invNums} — Total: {$tableData['total_fmt']}";
        }
        $sent++;
    }
    $total = array_sum(array_map(fn($g) => count($g['invs']), $groups));
    echo "[Overdue] {$sent} client(s) notified covering {$total} eligible invoice(s)\n";
}

// ================================================================
//  3. OVERDUE FOLLOW-UP SEQUENCE — CONSOLIDATED
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
        ORDER BY i.due_date ASC
    ");
    $stmt->execute();
    $invs   = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $groups = emailGroupByClient($invs);
    $sent   = 0;

    foreach ($groups as $group) {
        $eligible = array_filter($group['invs'], function($inv) use ($db, $maxFollowup, $followupDays) {
            $invId = (int)$inv['id'];
            $cntStmt = $db->prepare("SELECT COUNT(*) FROM email_logs WHERE invoice_id=? AND type='followup'");
            $cntStmt->execute([$invId]);
            if ((int)$cntStmt->fetchColumn() >= $maxFollowup) return false;
            $lastStmt = $db->prepare("SELECT MAX(created_at) FROM email_logs WHERE invoice_id=? AND type IN ('followup','overdue')");
            $lastStmt->execute([$invId]);
            $lastSent = $lastStmt->fetchColumn();
            if ($lastSent && strtotime($lastSent) > strtotime("-{$followupDays} days")) return false;
            if (alreadySentToday($db, $invId, 'followup'))  return false;
            if (emailHasActivePromise($db, $invId))          return false;
            return true;
        });
        if (empty($eligible)) continue;
        $eligible = array_values($eligible);

        if (count($eligible) === 1) {
            $inv   = $eligible[0];
            $invId = (int)$inv['id'];
            $cntStmt = $db->prepare("SELECT COUNT(*) FROM email_logs WHERE invoice_id=? AND type='followup'");
            $cntStmt->execute([$invId]);
            $totalSent = (int)$cntStmt->fetchColumn();
            $inv['_display_amt'] = emailGetDisplayAmt($db, $inv);
            $inv['invoice_link'] = cronGetPortalLink($db, $invId, $portalBase);
            $tpl  = getCronTemplate($db, 'followup');
            $data = array_merge($inv, $company);
            $subj = cronReplaceVars($tpl['subject'], $data);
            $body = cronReplaceVars($tpl['body'], $data);
            $html = cronBuildHTML($body, 'followup');
            $ok   = cronSendEmail($smtp, $group['email'], $group['name'], $subj, $html);
            cronLogEmail($db, $invId, 'followup', $group['email'], $subj, $ok);
            $log[] = ($ok ? '✅' : '❌') . " Follow-up #" . ($totalSent + 1) . " → {$group['name']} — #{$inv['invoice_number']} ({$inv['days_overdue']} days)";
        } else {
            $tableData = emailBuildInvoiceTable($db, $eligible, $company, $portalBase);
            $maxDays   = $tableData['max_overdue'];
            $intro = "Dear {$group['name']},

We are following up on " . count($eligible) .
                     " outstanding invoice(s) totalling {$tableData['total_fmt']}. " .
                     "The oldest is {$maxDays} day(s) overdue. Kindly settle these at your earliest convenience.";
            $subj  = "Follow-up: " . count($eligible) . " outstanding invoice(s) — {$tableData['total_fmt']}";
            $html  = emailBuildConsolidatedHTML($group['name'], 'followup', $tableData, $company, $intro);
            $ok    = cronSendEmail($smtp, $group['email'], $group['name'], $subj, $html);
            foreach ($eligible as $inv) {
                cronLogEmail($db, (int)$inv['id'], 'followup', $group['email'], $subj, $ok);
            }
            $invNums = implode(', ', array_map(fn($i) => '#'.($i['invoice_number']??''), $eligible));
            $log[] = ($ok ? '✅' : '❌') . " Follow-up (consolidated) → {$group['name']} — {$invNums} — Total: {$tableData['total_fmt']}";
        }
        $sent++;
    }
    $total = array_sum(array_map(fn($g) => count($g['invs']), $groups));
    echo "[Follow-up] {$sent} client(s) notified covering {$total} eligible invoice(s)\n";
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