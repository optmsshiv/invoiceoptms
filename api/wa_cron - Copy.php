<?php
// ================================================================
//  api/wa_cron.php — Daily WhatsApp Automation Cron Job
//
//  Set up in cPanel → Cron Jobs:
//  Command: php /home/youraccount/public_html/api/wa_cron.php
//  Schedule: Daily at 9:00 AM (0 9 * * *)
//
//  Handles:
//  - Due date reminders (N days before due)        → payment_reminder template
//  - On due date reminder                          → payment_reminder template
//  - Overdue alert (first send)                    → payment_overdue template
//  - Overdue follow-up sequence (every N days)     → invoice_followup template
//
//  Uses existing wa_message_log table (same as browser WA sends)
//  Calls wa_send.php internally — reuses phone sanitization + Meta v22.0
// ================================================================
define('CRON_MODE', true);
ob_start();
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/db.php';

$db    = getDB();
$today = date('Y-m-d');
$log   = [];

// ── Load all settings ────────────────────────────────────────────
$cfgRows = $db->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_ASSOC);
$cfg = [];
foreach ($cfgRows as $r) $cfg[$r['key']] = $r['value'];

// ── Meta API credentials ─────────────────────────────────────────
$waToken = $cfg['wa_token'] ?? '';
$waPid   = $cfg['wa_pid']   ?? '';

if (empty($waToken) || empty($waPid)) {
    echo "[" . date('Y-m-d H:i:s') . "] WhatsApp API not configured (wa_token / wa_pid missing). Exiting.\n";
    exit;
}

// ── Automation flags ─────────────────────────────────────────────
$autoRemind   = ($cfg['wa_auto_remind']   ?? '1') === '1';
$autoOverdue  = ($cfg['wa_auto_overdue']  ?? '1') === '1';
$autoFollowup = ($cfg['wa_auto_followup'] ?? '1') === '1';

if (!$autoRemind && !$autoOverdue && !$autoFollowup) {
    echo "[" . date('Y-m-d H:i:s') . "] All WhatsApp automation is OFF. Nothing to do.\n";
    exit;
}

// ── Timing rules from reminder_settings (single source of truth) ─
$remSettings = [];
try {
    $remRow = $db->query("SELECT * FROM reminder_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    if ($remRow) $remSettings = $remRow;
} catch (Exception $e) {}

$remindDays   = max(1, (int)($remSettings['before_days']  ?? $cfg['before_days']  ?? 3));
$followupDays = max(1, (int)($remSettings['overdue_freq'] ?? $cfg['overdue_freq']  ?? 7));
$maxFollowup  = max(1, (int)($remSettings['max_overdue']  ?? $cfg['max_overdue']   ?? 3));
$onDue        = ($remSettings['on_due'] ?? $cfg['on_due'] ?? '1') === '1';

// ── Template names/langs from settings ───────────────────────────
$tplReminder = $cfg['wa_tpl_name_reminder'] ?? 'payment_reminder';
$tplLangRem  = $cfg['wa_tpl_lang_reminder'] ?? 'en_US';
$tplOverdue  = $cfg['wa_tpl_name_overdue']  ?? 'payment_overdue';
$tplLangOv   = $cfg['wa_tpl_lang_overdue']  ?? 'en_US';
$tplFollowup = $cfg['wa_tpl_name_followup'] ?? 'invoice_followup';
$tplLangFu   = $cfg['wa_tpl_lang_followup'] ?? 'en_US';

// ── Company info ─────────────────────────────────────────────────
$company = [
    'company_name'  => $cfg['company_name']  ?? '',
    'company_phone' => $cfg['company_phone'] ?? '',
    'upi'           => $cfg['company_upi']   ?? '',
];

// Portal base URL
$portalBase = rtrim($cfg['portal_base_url'] ?? '', '/') . '/';
if (!$portalBase || $portalBase === '/') {
    $portalBase = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/portal/';
}

// ================================================================
//  HELPERS
// ================================================================

// ── Get or create portal token link ─────────────────────────────
function waGetPortalLink($db, int $invId, string $portalBase): string {
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
        return '';
    }
}

// ── Build template params (matches JS buildWATplParams order) ────
//  reminder  → {{1}}name {{2}}inv# {{3}}amount {{4}}due  {{5}}upi {{6}}company {{7}}link
//  overdue   → {{1}}name {{2}}inv# {{3}}amount {{4}}days {{5}}upi {{6}}company {{7}}link
//  followup  → {{1}}name {{2}}inv# {{3}}amount {{4}}days {{5}}upi {{6}}phone   {{7}}link
function waBuildParams(string $type, array $inv, array $company, string $portalLink): array {
    $sym      = $inv['currency'] ?? '₹';
    $amount   = $sym . number_format((float)($inv['grand_total'] ?? $inv['amount'] ?? 0), 2);
    $dueFmt   = !empty($inv['due_date']) ? date('d M Y', strtotime($inv['due_date'])) : '';
    $daysOver = (string)(int)($inv['days_overdue'] ?? 0);
    $name     = $inv['client_name'] ?? 'Valued Client';
    $invNo    = $inv['invoice_number'] ?? '';

    return match($type) {
        'reminder' => [$name, $invNo, $amount, $dueFmt,   $company['upi'], $company['company_name'],  $portalLink],
        'overdue'  => [$name, $invNo, $amount, $daysOver, $company['upi'], $company['company_name'],  $portalLink],
        'followup' => [$name, $invNo, $amount, $daysOver, $company['upi'], $company['company_phone'], $portalLink],
        default    => [$name, $invNo, $amount],
    };
}

// ── Send via wa_send.php (reuses phone sanitization + Meta v22.0) ─
function waCronSend(string $waToken, string $waPid, string $phone,
                    string $tplName, string $tplLang, array $params): bool {
    $payload = json_encode([
        'token'           => $waToken,
        'pid'             => $waPid,
        'to'              => $phone,
        'type'            => 'template',
        'message'         => '',
        'template_name'   => $tplName,
        'template_lang'   => $tplLang,
        'template_params' => $params,
    ]);

    $url = 'http://localhost' . dirname($_SERVER['SCRIPT_NAME'] ?? '/api/wa_cron.php') . '/wa_send.php';
    // Fallback: call Meta directly if localhost call not possible
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Cron-Auth: 1',   // wa_send.php auth bypass for cron (see note below)
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // If localhost call fails, call Meta directly as fallback
    if (!$resp || $status !== 200) {
        return waCronSendDirect($waToken, $waPid, $phone, $tplName, $tplLang, $params);
    }

    $data = json_decode($resp, true);
    return !empty($data['success']);
}

// ── Direct Meta API call (fallback if localhost wa_send.php unreachable) ─
function waCronSendDirect(string $token, string $pid, string $toPhone,
                          string $tplName, string $tplLang, array $params): bool {
    $phone = preg_replace('/\D/', '', $toPhone);
    if (strlen($phone) === 10) $phone = '91' . $phone;
    if (strlen($phone) < 10) return false;

    $components = [];
    if (!empty($params)) {
        $components[] = [
            'type'       => 'body',
            'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => (string)$p], $params),
        ];
    }

    $payload = json_encode([
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $phone,
        'type'              => 'template',
        'template'          => [
            'name'       => $tplName,
            'language'   => ['code' => $tplLang],
            'components' => $components,
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init("https://graph.facebook.com/v22.0/{$pid}/messages");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($err || $status >= 400) {
        error_log("wa_cron direct send error [{$status}]: " . ($err ?: $resp));
        return false;
    }
    return true;
}

// ── Log to wa_message_log (same table as browser sends) ──────────
function waCronLog($db, int $invId, string $type, array $inv, string $tplName, bool $ok): void {
    $entryId = 'cron_' . $invId . '_' . $type . '_' . date('Ymd');
    try {
        $db->prepare("INSERT IGNORE INTO wa_message_log
            (entry_id, ts, type, status, client, phone, inv_id, inv_num, inv_amt, inv_status, msg, error)
            VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
           ->execute([
               $entryId,
               $type,
               $ok ? 'sent_api' : 'failed',
               $inv['client_name'] ?? '',
               $inv['c_phone']     ?? '',
               (string)($inv['id'] ?? ''),
               $inv['invoice_number'] ?? '',
               $inv['currency'] ?? '₹' . number_format((float)($inv['grand_total'] ?? $inv['amount'] ?? 0), 2),
               $inv['status'] ?? '',
               '[cron] ' . $tplName,
               $ok ? null : 'Cron send failed',
           ]);
    } catch (Exception $e) {
        error_log('waCronLog: ' . $e->getMessage());
    }
}

// ── Already sent this type today for this invoice? ───────────────
function waAlreadySentToday($db, int $invId, string $type): bool {
    try {
        $stmt = $db->prepare(
            "SELECT id FROM wa_message_log
             WHERE inv_id=? AND type=? AND DATE(ts)=CURDATE()
             AND status IN ('sent_api','sent_web') LIMIT 1"
        );
        $stmt->execute([(string)$invId, $type]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// ── Count total sends of a type for an invoice ───────────────────
function waCountSent($db, int $invId, string $type): int {
    try {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM wa_message_log
             WHERE inv_id=? AND type=? AND status IN ('sent_api','sent_web')"
        );
        $stmt->execute([(string)$invId, $type]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// ── Last sent timestamp for overdue/followup ─────────────────────
function waLastSent($db, int $invId, array $types): ?string {
    $placeholders = implode(',', array_fill(0, count($types), '?'));
    try {
        $stmt = $db->prepare(
            "SELECT MAX(ts) FROM wa_message_log
             WHERE inv_id=? AND type IN ({$placeholders}) AND status IN ('sent_api','sent_web')"
        );
        $stmt->execute(array_merge([(string)$invId], $types));
        $val = $stmt->fetchColumn();
        return $val ?: null;
    } catch (Exception $e) {
        return null;
    }
}

// ================================================================
//  1. PRE-DUE REMINDER
// ================================================================
if ($autoRemind) {
    $reminderDate = date('Y-m-d', strtotime("+{$remindDays} days"));
    $stmt = $db->prepare("
        SELECT i.*, c.phone AS c_phone, c.name AS client_name
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        WHERE i.due_date = ?
          AND i.status IN ('Pending','Partial')
          AND c.phone IS NOT NULL AND c.phone != ''
    ");
    $stmt->execute([$reminderDate]);
    $invs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sent = 0;

    foreach ($invs as $inv) {
        $invId = (int)$inv['id'];
        if (waAlreadySentToday($db, $invId, 'payment_reminder')) continue;
        $portalLink = waGetPortalLink($db, $invId, $portalBase);
        $params     = waBuildParams('reminder', $inv, $company, $portalLink);
        $ok         = waCronSend($waToken, $waPid, $inv['c_phone'], $tplReminder, $tplLangRem, $params);
        waCronLog($db, $invId, 'payment_reminder', $inv, $tplReminder, $ok);
        $log[] = ($ok ? '✅' : '❌') . " WA Reminder → {$inv['client_name']} ({$inv['c_phone']}) — #{$inv['invoice_number']}";
        $sent++;
    }
    echo "[WA Reminder] {$sent} sent out of " . count($invs) . " eligible (due in {$remindDays} days)\n";
}

// ================================================================
//  1b. ON DUE DATE REMINDER
// ================================================================
if ($autoRemind && $onDue) {
    $stmt = $db->prepare("
        SELECT i.*, c.phone AS c_phone, c.name AS client_name
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        WHERE i.due_date = CURDATE()
          AND i.status IN ('Pending','Partial')
          AND c.phone IS NOT NULL AND c.phone != ''
    ");
    $stmt->execute();
    $invs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sent = 0;

    foreach ($invs as $inv) {
        $invId = (int)$inv['id'];
        if (waAlreadySentToday($db, $invId, 'payment_reminder')) continue;
        $portalLink = waGetPortalLink($db, $invId, $portalBase);
        $params     = waBuildParams('reminder', $inv, $company, $portalLink);
        $ok         = waCronSend($waToken, $waPid, $inv['c_phone'], $tplReminder, $tplLangRem, $params);
        waCronLog($db, $invId, 'payment_reminder', $inv, $tplReminder, $ok);
        $log[] = ($ok ? '✅' : '❌') . " WA Due Today → {$inv['client_name']} ({$inv['c_phone']}) — #{$inv['invoice_number']}";
        $sent++;
    }
    echo "[WA On Due] {$sent} due-today reminder(s) sent out of " . count($invs) . " eligible\n";
}

// ================================================================
//  2. OVERDUE ALERT (first send only)
// ================================================================
if ($autoOverdue) {
    $stmt = $db->prepare("
        SELECT i.*, c.phone AS c_phone, c.name AS client_name,
               DATEDIFF(CURDATE(), i.due_date) AS days_overdue
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        WHERE i.due_date < CURDATE()
          AND i.status IN ('Pending','Partial')
          AND c.phone IS NOT NULL AND c.phone != ''
    ");
    $stmt->execute();
    $invs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sent = 0;

    foreach ($invs as $inv) {
        $invId = (int)$inv['id'];
        // Only fire if no overdue/followup has ever been sent for this invoice
        if (waCountSent($db, $invId, 'payment_overdue') > 0)  continue;
        if (waCountSent($db, $invId, 'invoice_followup') > 0) continue;
        if (waAlreadySentToday($db, $invId, 'payment_overdue')) continue;

        $portalLink = waGetPortalLink($db, $invId, $portalBase);
        $params     = waBuildParams('overdue', $inv, $company, $portalLink);
        $ok         = waCronSend($waToken, $waPid, $inv['c_phone'], $tplOverdue, $tplLangOv, $params);
        waCronLog($db, $invId, 'payment_overdue', $inv, $tplOverdue, $ok);
        $log[] = ($ok ? '✅' : '❌') . " WA Overdue → {$inv['client_name']} — #{$inv['invoice_number']} ({$inv['days_overdue']} days)";
        $sent++;
    }
    echo "[WA Overdue] {$sent} overdue alert(s) sent out of " . count($invs) . " eligible\n";
}

// ================================================================
//  3. OVERDUE FOLLOW-UP SEQUENCE
// ================================================================
if ($autoFollowup) {
    $stmt = $db->prepare("
        SELECT i.*, c.phone AS c_phone, c.name AS client_name,
               DATEDIFF(CURDATE(), i.due_date) AS days_overdue
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        WHERE i.due_date < CURDATE()
          AND i.status IN ('Pending','Partial')
          AND c.phone IS NOT NULL AND c.phone != ''
    ");
    $stmt->execute();
    $invs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sent = 0;

    foreach ($invs as $inv) {
        $invId = (int)$inv['id'];

        // Must have had first overdue alert before follow-ups start
        if (waCountSent($db, $invId, 'payment_overdue') === 0) continue;

        // Cap reached?
        $fuCount = waCountSent($db, $invId, 'invoice_followup');
        if ($fuCount >= $maxFollowup) continue;

        // Too soon since last overdue/followup send?
        $lastSent = waLastSent($db, $invId, ['payment_overdue', 'invoice_followup']);
        if ($lastSent && strtotime($lastSent) > strtotime("-{$followupDays} days")) continue;

        if (waAlreadySentToday($db, $invId, 'invoice_followup')) continue;

        $portalLink = waGetPortalLink($db, $invId, $portalBase);
        $params     = waBuildParams('followup', $inv, $company, $portalLink);
        $ok         = waCronSend($waToken, $waPid, $inv['c_phone'], $tplFollowup, $tplLangFu, $params);
        waCronLog($db, $invId, 'invoice_followup', $inv, $tplFollowup, $ok);
        $log[] = ($ok ? '✅' : '❌') . " WA Follow-up #" . ($fuCount + 1) . " → {$inv['client_name']} — #{$inv['invoice_number']} ({$inv['days_overdue']} days)";
        $sent++;
    }
    echo "[WA Follow-up] {$sent} follow-up(s) sent out of " . count($invs) . " eligible\n";
}

// ================================================================
echo "\n=== WA Cron complete [" . date('Y-m-d H:i:s') . "] ===\n";
echo implode("\n", $log) . "\n";
echo "Total WA messages processed: " . count($log) . "\n";
ob_end_flush();
