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
$onDue        = ($remSettings['on_due'] ?? $cfg['on_due'] ?? '1') == '1'; // == not === (DB returns int)
$remChannel   = $remSettings['channel'] ?? 'whatsapp'; // 'whatsapp','email','both'
// If reminder_settings.channel is 'email', WA cron should do nothing
if ($remChannel === 'email') {
    echo "[" . date('Y-m-d H:i:s') . "] Reminder channel set to email-only. WA cron skipping.\n";
    exit;
}

// ── Send-time guard ───────────────────────────────────────────────
// Only applies if send_hour column exists in reminder_settings.
// If column missing (migration not run yet), skip guard and always run.
date_default_timezone_set('Asia/Kolkata');
$sendHour     = isset($remSettings['send_hour']) ? (int)$remSettings['send_hour']   : null;
$sendMinute   = isset($remSettings['send_hour']) ? (int)($remSettings['send_minute'] ?? 0) : null;

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
function waBuildParams(string $type, array $inv, array $company, string $portalLink, $db = null): array {
    $sym   = $inv['currency'] ?? '₹';
    $grand = (float)($inv['grand_total'] ?? $inv['amount'] ?? 0);
    // For Partial invoices use remaining balance, not full grand_total
    $displayAmt = $grand;
    if (($inv['status'] ?? '') === 'Partial' && $db !== null) {
        try {
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=?");
            $stmt->execute([$inv['id']]);
            $paid = (float)$stmt->fetchColumn();
            $displayAmt = max(0, $grand - $paid);
        } catch (Exception $e) {}
    }
    $amount   = $sym . number_format($displayAmt, 2);
    $dueFmt   = !empty($inv['due_date']) ? date('d M Y', strtotime($inv['due_date'])) : '';
    $daysOver = (string)(int)($inv['days_overdue'] ?? 0);
    $name     = $inv['client_name'] ?? 'Valued Client';
    $invNo    = $inv['invoice_number'] ?? '';

    // Param order must match EXACTLY what is registered in Meta Business Manager
    return match($type) {
        // payment_reminder: {{1}}name {{2}}inv# {{3}}amount {{4}}due_date {{5}}upi {{6}}company_name {{7}}link
        'reminder' => [$name, $invNo, $amount, $dueFmt,   $company['upi'], $company['company_name'], $portalLink],
        // payment_overdue: {{1}}name {{2}}inv# {{3}}amount {{4}}days {{5}}upi {{6}}link {{7}}phone {{8}}company
        'overdue'  => [$name, $invNo, $amount, $daysOver, $company['upi'], $portalLink, $company['company_phone'], $company['company_name']],
        // invoice_followup: {{1}}name {{2}}inv# {{3}}amount {{4}}days {{5}}upi {{6}}phone {{7}}link
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
               $inv['_display_amt'] ?? ($inv['currency'] ?? '₹') . number_format((float)($inv['grand_total'] ?? $inv['amount'] ?? 0), 2),
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
        // Use IST date explicitly — MySQL CURDATE() is UTC which can be yesterday at 9 AM IST
        $stmt = $db->prepare(
            "SELECT id FROM wa_message_log
             WHERE inv_id=? AND type=?
               AND DATE(CONVERT_TZ(ts,'UTC','Asia/Kolkata')) = CURDATE()
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

// ── Check if invoice has an active promise-to-pay (suppresses overdue/followup) ─
function waHasActivePromise($db, int $invId): bool {
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

// ================================================================
//  CONSOLIDATED SEND HELPER
//  Groups invoices by client, sends ONE message per client covering
//  all their eligible invoices. Logs per-invoice so state tracking works.
// ================================================================

/**
 * Groups invoice rows by client_id.
 * Returns: [ client_id => [ 'phone'=>..., 'name'=>..., 'invs'=>[...] ], ... ]
 */
function waGroupByClient(array $invs): array {
    $groups = [];
    foreach ($invs as $inv) {
        // Group by client_id if present, else by phone number (never by invoice id)
        $cid = !empty($inv['client_id']) ? (string)$inv['client_id'] : ('phone_' . preg_replace('/\D/', '', $inv['c_phone'] ?? ''));
        if (!isset($groups[$cid])) {
            $groups[$cid] = [
                'phone' => $inv['c_phone'] ?? '',
                'name'  => $inv['client_name'] ?? 'Client',
                'invs'  => [],
            ];
        }
        $groups[$cid]['invs'][] = $inv;
    }
    return $groups;
}

/**
 * For a group of invoices, compute total outstanding amount.
 */
function waGroupTotalAmt($db, array $invs): array {
    $sym   = $invs[0]['currency'] ?? '₹';
    $total = 0;
    foreach ($invs as $inv) {
        $grand = (float)($inv['grand_total'] ?? $inv['amount'] ?? 0);
        if (($inv['status'] ?? '') === 'Partial' && $db !== null) {
            try {
                $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=?");
                $stmt->execute([$inv['id']]);
                $paid  = (float)$stmt->fetchColumn();
                $total += max(0, $grand - $paid);
            } catch (Exception $e) { $total += $grand; }
        } else {
            $total += $grand;
        }
    }
    return ['sym' => $sym, 'total' => $total, 'fmt' => $sym . number_format($total, 2)];
}

/**
 * Pick anchor invoice for template: oldest due date (most urgent).
 * Returns the anchor inv with overridden amount = total outstanding.
 */
function waPickAnchor(array $invs, array $amtInfo): array {
    usort($invs, fn($a, $b) => strcmp($a['due_date'] ?? '', $b['due_date'] ?? ''));
    $anchor = $invs[0];
    // Override amount fields so template shows total outstanding, not single invoice
    $anchor['grand_total'] = $amtInfo['total'];
    $anchor['amount']      = $amtInfo['total'];
    $anchor['currency']    = $amtInfo['sym'];
    // Summarise invoice numbers in a readable way
    $nums = array_map(fn($i) => '#' . ($i['invoice_number'] ?? ''), $invs);
    if (count($nums) > 1) {
        // Put all inv numbers into invoice_number field for template param
        $anchor['invoice_number'] = implode(', ', $nums);
    }
    return $anchor;
}

// ================================================================
//  1. PRE-DUE REMINDER (CONSOLIDATED)
//     One WA message per client even if they have multiple invoices
//     due on the same reminder date.
// ================================================================
if ($autoRemind) {
    $reminderDate = date('Y-m-d', strtotime("+{$remindDays} days"));
    $stmt = $db->prepare("
        SELECT i.*, COALESCE(NULLIF(c.whatsapp,''), NULLIF(c.phone,''), NULLIF(i.client_wa,'')) AS c_phone, COALESCE(c.name, i.client_name) AS client_name
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        WHERE i.due_date = ?
          AND i.status IN ('Pending','Partial','Overdue')
          AND (NULLIF(c.whatsapp,'') IS NOT NULL OR NULLIF(c.phone,'') IS NOT NULL OR NULLIF(i.client_wa,'') IS NOT NULL)
        ORDER BY i.due_date ASC
    ");
    $stmt->execute([$reminderDate]);
    $invs   = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $groups = waGroupByClient($invs);
    $sent   = 0;

    foreach ($groups as $group) {
        // Filter out invoices already reminded today
        $eligible = array_filter($group['invs'],
            fn($inv) => !waAlreadySentToday($db, (int)$inv['id'], 'payment_reminder'));
        if (empty($eligible)) continue;

        $amtInfo    = waGroupTotalAmt($db, array_values($eligible));
        $anchor     = waPickAnchor(array_values($eligible), $amtInfo);
        $portalLink = waGetPortalLink($db, (int)$anchor['id'], $portalBase);
        $params     = waBuildParams('reminder', $anchor, $company, $portalLink);
        $ok         = waCronSend($waToken, $waPid, $group['phone'], $tplReminder, $tplLangRem, $params);

        // Log once per invoice in the group so per-invoice state is tracked
        $invNums = [];
        foreach ($eligible as $inv) {
            waCronLog($db, (int)$inv['id'], 'payment_reminder', $inv, $tplReminder, $ok);
            $invNums[] = '#' . ($inv['invoice_number'] ?? '');
        }
        $label  = $ok ? '✅' : '❌';
        $count  = count($eligible);
        $log[]  = "{$label} WA Reminder → {$group['name']} ({$group['phone']}) — " .
                  ($count > 1 ? "{$count} invoices: " . implode(', ', $invNums) : $invNums[0]) .
                  " — Total: {$amtInfo['fmt']}";
        $sent++;
    }
    $total = array_sum(array_map(fn($g) => count($g['invs']), $groups));
    echo "[WA Reminder] {$sent} client(s) notified covering {$total} invoice(s) (due in {$remindDays} days)\n";
}

// ================================================================
//  1b. ON DUE DATE REMINDER (CONSOLIDATED)
// ================================================================
if ($autoRemind && $onDue) {
    $stmt = $db->prepare("
        SELECT i.*, COALESCE(NULLIF(c.whatsapp,''), NULLIF(c.phone,''), NULLIF(i.client_wa,'')) AS c_phone, COALESCE(c.name, i.client_name) AS client_name
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        WHERE i.due_date = CURDATE()
          AND i.status IN ('Pending','Partial','Overdue')
          AND (NULLIF(c.whatsapp,'') IS NOT NULL OR NULLIF(c.phone,'') IS NOT NULL OR NULLIF(i.client_wa,'') IS NOT NULL)
        ORDER BY i.due_date ASC
    ");
    $stmt->execute();
    $invs   = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $groups = waGroupByClient($invs);
    $sent   = 0;

    foreach ($groups as $group) {
        $eligible = array_filter($group['invs'],
            fn($inv) => !waAlreadySentToday($db, (int)$inv['id'], 'payment_reminder'));
        if (empty($eligible)) continue;

        $amtInfo    = waGroupTotalAmt($db, array_values($eligible));
        $anchor     = waPickAnchor(array_values($eligible), $amtInfo);
        $portalLink = waGetPortalLink($db, (int)$anchor['id'], $portalBase);
        $params     = waBuildParams('reminder', $anchor, $company, $portalLink);
        $ok         = waCronSend($waToken, $waPid, $group['phone'], $tplReminder, $tplLangRem, $params);

        $invNums = [];
        foreach ($eligible as $inv) {
            waCronLog($db, (int)$inv['id'], 'payment_reminder', $inv, $tplReminder, $ok);
            $invNums[] = '#' . ($inv['invoice_number'] ?? '');
        }
        $label = $ok ? '✅' : '❌';
        $count = count($eligible);
        $log[] = "{$label} WA Due Today → {$group['name']} ({$group['phone']}) — " .
                 ($count > 1 ? "{$count} invoices: " . implode(', ', $invNums) : $invNums[0]) .
                 " — Total: {$amtInfo['fmt']}";
        $sent++;
    }
    $total = array_sum(array_map(fn($g) => count($g['invs']), $groups));
    echo "[WA On Due] {$sent} client(s) notified covering {$total} invoice(s)\n";
}

// ================================================================
//  2. OVERDUE ALERT — CONSOLIDATED (first alert per invoice,
//     but only ONE WA message per client per day)
// ================================================================
if ($autoOverdue) {
    $stmt = $db->prepare("
        SELECT i.*, COALESCE(NULLIF(c.whatsapp,''), NULLIF(c.phone,''), NULLIF(i.client_wa,'')) AS c_phone, COALESCE(c.name, i.client_name) AS client_name,
               DATEDIFF(CURDATE(), i.due_date) AS days_overdue
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        WHERE i.due_date < CURDATE()
          AND i.status IN ('Pending','Partial','Overdue')
          AND (NULLIF(c.whatsapp,'') IS NOT NULL OR NULLIF(c.phone,'') IS NOT NULL OR NULLIF(i.client_wa,'') IS NOT NULL)
        ORDER BY i.due_date ASC
    ");
    $stmt->execute();
    $invs   = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $groups = waGroupByClient($invs);
    $sent   = 0;

    foreach ($groups as $group) {
        // Eligible = never had overdue alert, not already sent today, no active promise
        $eligible = array_filter($group['invs'], function($inv) use ($db) {
            if (waCountSent($db, (int)$inv['id'], 'payment_overdue') > 0) return false;
            if (waAlreadySentToday($db, (int)$inv['id'], 'payment_overdue'))  return false;
            if (waHasActivePromise($db, (int)$inv['id']))                     return false;
            return true;
        });
        if (empty($eligible)) continue;

        $amtInfo    = waGroupTotalAmt($db, array_values($eligible));
        $anchor     = waPickAnchor(array_values($eligible), $amtInfo);
        $portalLink = waGetPortalLink($db, (int)$anchor['id'], $portalBase);
        $params     = waBuildParams('overdue', $anchor, $company, $portalLink);
        $ok         = waCronSend($waToken, $waPid, $group['phone'], $tplOverdue, $tplLangOv, $params);

        $invNums = [];
        foreach ($eligible as $inv) {
            waCronLog($db, (int)$inv['id'], 'payment_overdue', $inv, $tplOverdue, $ok);
            $invNums[] = '#' . ($inv['invoice_number'] ?? '');
        }
        $label = $ok ? '✅' : '❌';
        $count = count($eligible);
        $log[] = "{$label} WA Overdue → {$group['name']} — " .
                 ($count > 1 ? "{$count} invoices: " . implode(', ', $invNums) : $invNums[0]) .
                 " — Total: {$amtInfo['fmt']}";
        $sent++;
    }
    $total = array_sum(array_map(fn($g) => count($g['invs']), $groups));
    echo "[WA Overdue] {$sent} client(s) notified covering {$total} eligible invoice(s)\n";
}

// ================================================================
//  3. OVERDUE FOLLOW-UP SEQUENCE — CONSOLIDATED
//     Per-invoice cap/timing still respected, but one WA per client.
// ================================================================
if ($autoFollowup) {
    $stmt = $db->prepare("
        SELECT i.*, COALESCE(NULLIF(c.whatsapp,''), NULLIF(c.phone,''), NULLIF(i.client_wa,'')) AS c_phone, COALESCE(c.name, i.client_name) AS client_name,
               DATEDIFF(CURDATE(), i.due_date) AS days_overdue
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        WHERE i.due_date < CURDATE()
          AND i.status IN ('Pending','Partial','Overdue')
          AND (NULLIF(c.whatsapp,'') IS NOT NULL OR NULLIF(c.phone,'') IS NOT NULL OR NULLIF(i.client_wa,'') IS NOT NULL)
        ORDER BY i.due_date ASC
    ");
    $stmt->execute();
    $invs   = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $groups = waGroupByClient($invs);
    $sent   = 0;

    foreach ($groups as $group) {
        $eligible = array_filter($group['invs'], function($inv) use ($db, $maxFollowup, $followupDays) {
            $invId = (int)$inv['id'];
            if (waCountSent($db, $invId, 'payment_overdue') === 0) return false; // first alert not sent yet
            $fuCount  = waCountSent($db, $invId, 'invoice_followup');
            if ($fuCount >= $maxFollowup) return false; // cap reached
            $lastSent = waLastSent($db, $invId, ['payment_overdue', 'invoice_followup']);
            if ($lastSent && strtotime($lastSent) > strtotime("-{$followupDays} days")) return false; // too soon
            if (waAlreadySentToday($db, $invId, 'invoice_followup')) return false;
            if (waHasActivePromise($db, $invId)) return false;
            return true;
        });
        if (empty($eligible)) continue;

        $amtInfo    = waGroupTotalAmt($db, array_values($eligible));
        $anchor     = waPickAnchor(array_values($eligible), $amtInfo);
        $portalLink = waGetPortalLink($db, (int)$anchor['id'], $portalBase);
        $params     = waBuildParams('followup', $anchor, $company, $portalLink);
        $ok         = waCronSend($waToken, $waPid, $group['phone'], $tplFollowup, $tplLangFu, $params);

        $invNums = [];
        foreach ($eligible as $inv) {
            $fuCount = waCountSent($db, (int)$inv['id'], 'invoice_followup');
            waCronLog($db, (int)$inv['id'], 'invoice_followup', $inv, $tplFollowup, $ok);
            $invNums[] = '#' . ($inv['invoice_number'] ?? '');
        }
        $label = $ok ? '✅' : '❌';
        $count = count($eligible);
        $log[] = "{$label} WA Follow-up → {$group['name']} — " .
                 ($count > 1 ? "{$count} invoices: " . implode(', ', $invNums) : $invNums[0]) .
                 " — Total: {$amtInfo['fmt']}";
        $sent++;
    }
    $total = array_sum(array_map(fn($g) => count($g['invs']), $groups));
    echo "[WA Follow-up] {$sent} client(s) notified covering {$total} eligible invoice(s)\n";
}

// ================================================================
echo "\n=== WA Cron complete [" . date('Y-m-d H:i:s') . "] ===\n";
echo implode("\n", $log) . "\n";
echo "Total WA messages processed: " . count($log) . "\n";
ob_end_flush();