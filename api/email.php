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

    // ── Status guard: fetch invoice and block invalid sends ───────
    if ($invId) {
        try {
            $invChk = $db->prepare("SELECT status FROM invoices WHERE id=? LIMIT 1");
            $invChk->execute([$invId]);
            $invStatus = $invChk->fetchColumn();
            if ($invStatus !== false) {
                // Paid   → only receipt is allowed (it IS the payment confirmation)
                // Cancelled → nothing is allowed
                // Draft  → only invoice/estimate allowed; no reminders/overdue/followup
                if ($invStatus === 'Paid' && !in_array($type, ['receipt','test'])) {
                    jsonResponse([
                        'success' => false,
                        'error'   => "Cannot send a {$type} email — invoice is already Paid. Only a receipt can be sent.",
                    ], 422);
                }
                if ($invStatus === 'Cancelled') {
                    jsonResponse([
                        'success' => false,
                        'error'   => "Cannot email a Cancelled invoice.",
                    ], 422);
                }
                if ($invStatus === 'Draft' && in_array($type, ['reminder','overdue','followup'])) {
                    jsonResponse([
                        'success' => false,
                        'error'   => "Cannot send a {$type} email — invoice is still a Draft.",
                    ], 422);
                }
            }
        } catch (\Exception $e) { /* non-fatal — proceed */ }
    }

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

    // Portal link — Format B: base64(invoiceId:invoiceNumber) with src=email
    // This uses the same token format as the frontend _portalURL() function,
    // so no DB token table is needed and portal/index.php decodes it directly.
    $portalLink = '';
    try {
        $invNum   = $inv['invoice_number'] ?? '';
        $b64token = rtrim(strtr(base64_encode($invId . ':' . $invNum), '+/', '-_'), '=');
        $portalLink = $portalBase . '?t=' . $b64token . '&src=email';
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
        'invoice'  => ['#1e2a5e', '#f59e0b', 'Invoice',         'Pending'],
        'estimate' => ['#1976D2', '#60a5fa', 'Estimate',        'Pending'],
        'receipt'  => ['#1e5e2a', '#34d399', 'Payment Receipt', 'Paid'],
        'reminder' => ['#7c4700', '#f9a825', 'Payment Reminder','Due Soon'],
        'overdue'  => ['#5e1e1e', '#ef4444', 'Invoice Overdue', 'Overdue'],
        'followup' => ['#3b1e5e', '#a78bfa', 'Follow-up',       'Outstanding'],
        'test'     => ['#1e2a5e', '#f59e0b', 'SMTP Test',       'Test'],
    ];
    [$headerBg, $accentColor, $typeLabel, $defaultBadge] = $accents[$type] ?? $accents['invoice'];

    // ── Extract structured data from body text ────────────────────
    // Patterns: "  Label      : value" or "Label: value"
    $fields        = [];
    $portalUrl     = '';
    $greeting      = '';
    $openingLine   = '';
    $closingLines  = [];
    $upiLine       = '';

    $lines = explode("\n", $body);

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;

        // Portal URL detection
        if (preg_match('/https?:\/\/\S+/i', $trimmed, $m)) {
            $portalUrl = $m[0];
            continue;
        }

        // UPI line
        if (stripos($trimmed, 'upi') !== false && strpos($trimmed, ':') !== false) {
            $parts = explode(':', $trimmed, 2);
            $upiLine = trim($parts[1] ?? '');
            continue;
        }

        // Structured field: "  Invoice No : #123" or "Amount Due: ₹12,500"
        $matched = false;
        if (preg_match('/^\s*([\w\s#]+?)\s*:\s*(.+)$/', $trimmed, $m)) {
            $label = trim($m[1]);
            $value = trim($m[2]);
            $knownLabels = ['Invoice No','Estimate No','Service','Amount Due','Amount Paid','Balance Due',
                            'Issue Date','Due Date','Valid Until','Total','Status','Days Overdue',
                            'Settlement Discount','Payment Method'];
            foreach ($knownLabels as $kl) {
                if (stripos($label, $kl) !== false) {
                    $fields[$kl] = $value;
                    $matched = true;
                    break;
                }
            }
        }
        if ($matched) continue;

        // Greeting line
        if (!$greeting && preg_match('/^Dear\s/i', $trimmed)) {
            $greeting = $trimmed;
            continue;
        }

        // Opening line (first non-greeting, non-field sentence before fields appear)
        if (!$openingLine && empty($fields) && !preg_match('/^Dear\s/i', $trimmed)) {
            $openingLine = $trimmed;
            continue;
        }

        // Closing lines (after fields section)
        if (!empty($fields)) {
            $closingLines[] = $trimmed;
        }
    }

    // ── Determine invoice number / status for hero section ───────
    $invoiceNo  = $fields['Invoice No'] ?? $fields['Estimate No'] ?? '';
    $amountDue  = $fields['Amount Due'] ?? $fields['Total'] ?? $fields['Amount Paid'] ?? '';
    $dueDate    = $fields['Due Date'] ?? $fields['Valid Until'] ?? '';

    // Badge colour based on type
    $badgeColors = [
        'invoice'  => ['#fff7ed','#f59e0b','#92400e'],
        'estimate' => ['#eff6ff','#3b82f6','#1e40af'],
        'receipt'  => ['#f0fdf4','#22c55e','#166534'],
        'reminder' => ['#fffbeb','#f59e0b','#92400e'],
        'overdue'  => ['#fef2f2','#ef4444','#991b1b'],
        'followup' => ['#faf5ff','#a855f7','#581c87'],
        'test'     => ['#f0f9ff','#0ea5e9','#0c4a6e'],
    ];
    [$badgeBg, $badgeBorder, $badgeText] = $badgeColors[$type] ?? $badgeColors['invoice'];

    // ── Build table rows HTML ─────────────────────────────────────
    $tableRows = '';
    $rowData = [];
    $labelOrder = ['Service','Issue Date','Due Date','Valid Until','Amount Due','Total',
                   'Amount Paid','Balance Due','Settlement Discount','Payment Method','Status'];
    foreach ($labelOrder as $lbl) {
        if (isset($fields[$lbl])) {
            $rowData[$lbl] = $fields[$lbl];
        }
    }
    // Also include any extra fields not in the order list
    foreach ($fields as $lbl => $val) {
        if (!isset($rowData[$lbl])) $rowData[$lbl] = $val;
    }

    $isLast = count($rowData);
    $i = 0;
    foreach ($rowData as $lbl => $val) {
        $i++;
        $isAmountRow = in_array($lbl, ['Amount Due','Total','Balance Due']);
        $valStyle    = $isAmountRow
            ? "font-size:15px;font-weight:700;color:{$accentColor}"
            : 'font-size:14px;color:#374151';
        $borderBottom = ($i < $isLast) ? '1px solid #f3f4f6' : 'none';
        $tableRows .= <<<ROW
        <tr>
          <td style="padding:11px 0;font-size:13px;color:#6b7280;border-bottom:{$borderBottom};width:50%">{$lbl}</td>
          <td style="padding:11px 0;{$valStyle};border-bottom:{$borderBottom};text-align:right">{$val}</td>
        </tr>
ROW;
    }

    // ── Closing text (contact info etc.) ─────────────────────────
    $closingHtml = '';
    foreach ($closingLines as $cl) {
        $safe = htmlspecialchars($cl, ENT_QUOTES, 'UTF-8');
        // linkify emails and phones
        $safe = preg_replace('/[\w.\-]+@[\w.\-]+\.\w+/', '<a href="mailto:$0" style="color:'.$accentColor.';text-decoration:none">$0</a>', $safe);
        $safe = preg_replace('/\+?\d[\d\s\-]{8,}/', '<a href="tel:$0" style="color:'.$accentColor.';text-decoration:none">$0</a>', $safe);
        $closingHtml .= "<p style='margin:4px 0;font-size:13px;color:#6b7280'>{$safe}</p>";
    }

    // ── UPI block ─────────────────────────────────────────────────
    $upiHtml = '';
    if ($upiLine) {
        $upiHtml = <<<UPI
    <div style="background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:12px 16px;margin:20px 0;text-align:center">
      <p style="margin:0 0 4px;font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8">Pay via UPI</p>
      <p style="margin:0;font-size:15px;font-weight:700;color:#1e293b;letter-spacing:.03em">{$upiLine}</p>
    </div>
UPI;
    }

    // ── CTA Button ───────────────────────────────────────────────
    $ctaHtml = '';
    if ($portalUrl) {
        $safeUrl = htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8');
        $ctaHtml = <<<CTA
    <div style="text-align:center;margin:28px 0 8px">
      <a href="{$safeUrl}"
         style="display:inline-block;background:{$headerBg};color:#fff;text-decoration:none;
                font-size:15px;font-weight:600;padding:14px 36px;border-radius:8px;
                letter-spacing:.02em">
        View &amp; Download Invoice &rarr;
      </a>
      <p style="margin:10px 0 0;font-size:11px;color:#94a3b8">Single clean button — no raw URL shown</p>
    </div>
CTA;
    }

    // ── Safe greeting / opening ───────────────────────────────────
    $safeGreeting = htmlspecialchars($greeting ?: '', ENT_QUOTES, 'UTF-8');
    $safeOpening  = htmlspecialchars($openingLine ?: 'Please find your invoice below.', ENT_QUOTES, 'UTF-8');

    // ── Invoice number hero ───────────────────────────────────────
    $dueDateSuffix = $dueDate ? ' &middot; Due ' . $dueDate : '';
    $heroHtml = '';
    if ($invoiceNo) {
        $heroHtml = <<<HERO
    <div style="text-align:center;padding:24px 0 0">
      <p style="margin:0 0 6px;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:{$accentColor}">
        {$typeLabel}
      </p>
      <p style="margin:0 0 10px;font-size:22px;font-weight:800;color:#111827">#{$invoiceNo}</p>
      <span style="display:inline-block;background:{$badgeBg};border:1px solid {$badgeBorder};
                   color:{$badgeText};font-size:12px;font-weight:600;padding:4px 14px;border-radius:20px">
        ⏳ {$defaultBadge}{$dueDateSuffix}
      </span>
    </div>
HERO;
    }

    // ── Pre-compute heredoc vars ─────────────────────────────────
    $cardHtml = $tableRows ? '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:6px 20px 2px;margin:0 0 4px"><table style="width:100%;border-collapse:collapse">' . $tableRows . '</table></div>' : '';
    $closingBlock = $closingHtml ? '<div style="margin-top:20px">' . $closingHtml . '</div>' : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>OPTMS Tech Invoice</title>
</head>
<body style="margin:0;padding:0;background:#eef2f7;font-family:'Segoe UI',Arial,sans-serif">
  <div style="max-width:520px;margin:32px auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.10)">

    <!-- ── Header ── -->
    <div style="background:{$headerBg};padding:20px 28px;display:flex;align-items:center">
      <div style="width:40px;height:40px;background:rgba(255,255,255,.18);border-radius:10px;
                  display:inline-flex;align-items:center;justify-content:center;
                  font-size:16px;font-weight:800;color:#fff;margin-right:12px;
                  vertical-align:middle">OT</div>
      <div style="display:inline-block;vertical-align:middle">
        <p style="margin:0;font-size:15px;font-weight:700;color:#fff">OPTMS Tech</p>
        <p style="margin:0;font-size:12px;color:rgba(255,255,255,.65)">Invoice Manager</p>
      </div>
      <div style="margin-left:auto">
        <span style="background:rgba(255,255,255,.15);color:#fff;font-size:11px;font-weight:600;
                     padding:4px 12px;border-radius:6px;border:1px solid rgba(255,255,255,.25)">
          ✉ Email
        </span>
      </div>
    </div>

    <!-- ── Body ── -->
    <div style="padding:28px 32px">

      {$heroHtml}

      <!-- Greeting -->
      <p style="margin:20px 0 4px;font-size:15px;color:#111827;font-weight:500">{$safeGreeting}</p>
      <p style="margin:0 0 20px;font-size:14px;color:#6b7280">{$safeOpening}</p>

      <!-- Invoice details card -->
      {$cardHtml}

      {$upiHtml}
      {$ctaHtml}

      <!-- Closing lines -->
      {$closingBlock}

    </div>

    <!-- ── Footer ── -->
    <div style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:14px 32px;text-align:center">
      <p style="margin:0;font-size:11px;color:#9ca3af">
        Sent via <strong style="color:#374151">OPTMS Tech Invoice Manager</strong>
        &middot;
        <a href="https://optmstech.in" style="color:{$accentColor};text-decoration:none">optmstech.in</a>
      </p>
    </div>

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
ob_end_flush();