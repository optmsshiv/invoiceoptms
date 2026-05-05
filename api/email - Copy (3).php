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
try {
    switch ($action) {
        case 'templates':     handleGetTemplates($db);        break;
        case 'save_template': handleSaveTemplate($db, $input); break;
        case 'preview':       handlePreview($db, $input);      break;
        case 'logs':          handleLogs($db);                  break;
        case 'smtp_profiles': handleGetProfiles($db);           break;
        case 'save_profile':  handleSaveProfile($db, $input);   break;
        case 'del_profile':   handleDelProfile($db);            break;
        case 'test':          handleTest($db, $input);          break;
        case 'send':          handleSend($db, $input);          break;
        default:
            if ($method === 'GET') { handleGetTemplates($db); break; }
            jsonResponse(['success'=>false,'error'=>'Unknown action: '.$action], 400);
    }
} catch (\Throwable $e) {
    error_log('email.php fatal [' . $action . ']: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success'=>false,'error'=>'Server error: '.$e->getMessage()], 500);
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
    $html    = buildEmailHTML(replacePlaceholders($tpl['body'], $vars), $type, $vars);

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
    // Return has_password flag instead of actual password (never expose credentials to frontend)
    $rows = $db->query("SELECT id, name, host, port, username, from_email, from_name, provider, is_default,
                               CASE WHEN password IS NOT NULL AND password != '' THEN 1 ELSE 0 END AS has_password
                        FROM smtp_profiles ORDER BY is_default DESC, name ASC")->fetchAll();
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
    $result  = sendSmtpEmail($smtp, $to, 'Test', $subject, buildEmailHTML($body, 'test', []));
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

    // ── Strip raw portal link from body — it's shown as a CTA button in the template ──
    // Also prevents the double-link issue where auto-inject added a 2nd raw URL.
    if (!empty($vars['{invoice_link}'])) {
        $rawBody = str_replace($vars['{invoice_link}'], '', $rawBody);
        $rawBody = trim(preg_replace('/View your invoice online:\s*/i', '', $rawBody));
        $rawBody = trim(preg_replace('/To review, approve, or request changes, please visit:\s*/i', '', $rawBody));
        $rawBody = trim(preg_replace('/Or view and pay your invoice online:\s*/i', '', $rawBody));
        $rawBody = trim(preg_replace('/You can also view, download, and pay your invoice online:\s*/i', '', $rawBody));
        $rawBody = trim(preg_replace('/View and pay online:\s*/i', '', $rawBody));
        $rawBody = trim(preg_replace('/View your invoice here:\s*/i', '', $rawBody));
    }

    // ── Strip warm regards block — dedicated section handles it now ──
    $rawBody = preg_replace('/(We look forward[^\n]*\n?)?(Warm regards[,.]?\s*\n)?({company_name}[^\n]*\n?)?({company_phone}[^\n]*\n?)?$/i', '', $rawBody);
    $rawBody = trim($rawBody);
    // Removes lines like "  Invoice No : #INV-001", "  Amount Due : ₹1,000" etc.
    $rawBody = preg_replace('/^\s*(Invoice No|Estimate No|Service|Amount Due|Amount Paid|Balance Due|Issue Date|Due Date|Valid Until|Total|UPI|Pay via UPI)[^\n]*\n?/im', '', $rawBody);
    // Also strip "To pay via UPI, use: ..." lines (shown in portal, not needed in email)
    $rawBody = preg_replace('/^To pay via UPI, use:.*\n?/im', '', $rawBody);
    // Clean up extra blank lines left after stripping
    $rawBody = trim(preg_replace('/\n{3,}/', "\n\n", $rawBody));

    $smtp   = getSmtpConfig($input, $db);
    $html   = buildEmailHTML($rawBody, $type, $vars);   // pass $vars for structured template
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
function buildEmailHTML(string $body, string $type = 'invoice', array $vars = []): string {

    // Type config: [headerBg, accentColor, badgeEmoji, label]
    $types = [
        'invoice'  => ['#1A237E', '#3949AB', '📄', 'Invoice'],
        'estimate' => ['#1565C0', '#1976D2', '📋', 'Estimate'],
        'receipt'  => ['#1B5E20', '#388E3C', '✅', 'Payment Receipt'],
        'reminder' => ['#E65100', '#F57C00', '🔔', 'Payment Reminder'],
        'overdue'  => ['#B71C1C', '#E53935', '⚠️', 'Invoice Overdue'],
        'followup' => ['#4A148C', '#7B1FA2', '📞', 'Follow-up'],
        'test'     => ['#004D40', '#00897B', '🧪', 'SMTP Test'],
    ];
    [$hdrBg, $accent, $emoji, $typeLabel] = $types[$type] ?? $types['invoice'];

    // Pull structured vars if available
    $company    = $vars['{company_name}']    ?? 'OPTMS Tech';
    $clientName = $vars['{client_name}']     ?? '';
    $invoiceNo  = $vars['{invoice_no}']      ?? '';
    $amount     = $vars['{amount}']          ?? '';
    $dueDate    = $vars['{due_date}']        ?? '';
    $issueDate  = $vars['{issue_date}']      ?? '';
    $service    = $vars['{service}']         ?? '';
    $status     = $vars['{status}']          ?? '';
    $remaining  = $vars['{remaining_amount}']?? '';
    $paidAmount = $vars['{paid_amount}']     ?? '';
    $daysOver   = (int)($vars['{days_overdue}'] ?? 0);
    $portalLink = $vars['{invoice_link}']    ?? '';
    $compPhone  = $vars['{company_phone}']   ?? '';
    $compEmail  = $vars['{company_email}']   ?? '';

    // Status badge colours
    $statusColours = [
        'Paid'      => ['#E8F5E9','#1B5E20'],
        'Partial'   => ['#FFF3E0','#E65100'],
        'Overdue'   => ['#FFEBEE','#B71C1C'],
        'Cancelled' => ['#F5F5F5','#616161'],
    ];
    [$sBg, $sCol] = $statusColours[$status] ?? ['#FFF8E1','#E65100'];

    // ── #4 Hero strip light bg per type (Gmail-safe solid hex, no gradient) ──
    $heroBgMap = [
        '#1A237E' => ['#E8EAF6','#5C6BC0','#1A237E'],  // invoice  — indigo tint
        '#1565C0' => ['#E3F2FD','#1565C0','#0D47A1'],  // estimate — blue tint
        '#1B5E20' => ['#E8F5E9','#388E3C','#1B5E20'],  // receipt  — green tint
        '#E65100' => ['#FFF3E0','#E65100','#BF360C'],  // reminder — orange tint
        '#B71C1C' => ['#FFEBEE','#C62828','#B71C1C'],  // overdue  — red tint
        '#4A148C' => ['#F3E5F5','#7B1FA2','#4A148C'],  // followup — purple tint
        '#004D40' => ['#E0F2F1','#00897B','#004D40'],  // test     — teal tint
    ];
    [$heroBg, $heroLabel, $heroNum] = $heroBgMap[$hdrBg] ?? ['#E8EAF6','#5C6BC0','#1A237E'];

    // Build the plain-text message body
    $cleanBody = $body;
    if ($portalLink) {
        $cleanBody = str_replace($portalLink, '', $cleanBody);
    }
    $cleanBody = trim(preg_replace('/\n{3,}/', "\n\n", $cleanBody));
    $cleanBody = nl2br(htmlspecialchars($cleanBody, ENT_QUOTES, 'UTF-8'));

    // ── #3 Summary rows — per-type labels ─────────────────────────
    $isEstimate = ($type === 'estimate');
    $isReceipt  = ($type === 'receipt');
    $invoiceLabel = $isEstimate ? 'Estimate No' : 'Invoice No';
    $dueDateLabel = $isEstimate ? 'Valid Until'  : 'Due Date';

    $summaryRows = '';
    if ($invoiceNo) $summaryRows .= "<tr><td style='padding:6px 0;color:#666;font-size:13px;border-bottom:1px solid #eee'>{$invoiceLabel}</td><td style='padding:6px 0;text-align:right;font-size:13px;font-weight:600;color:{$accent};border-bottom:1px solid #eee'>{$invoiceNo}</td></tr>";
    if ($service)   $summaryRows .= "<tr><td style='padding:6px 0;color:#666;font-size:13px;border-bottom:1px solid #eee'>Service</td><td style='padding:6px 0;text-align:right;font-size:13px;border-bottom:1px solid #eee'>" . htmlspecialchars($service) . "</td></tr>";
    if ($issueDate) $summaryRows .= "<tr><td style='padding:6px 0;color:#666;font-size:13px;border-bottom:1px solid #eee'>Issue Date</td><td style='padding:6px 0;text-align:right;font-size:13px;border-bottom:1px solid #eee'>{$issueDate}</td></tr>";
    if ($dueDate)   $summaryRows .= "<tr><td style='padding:6px 0;color:#666;font-size:13px;border-bottom:1px solid #eee'>{$dueDateLabel}</td><td style='padding:6px 0;text-align:right;font-size:13px;border-bottom:1px solid #eee'>{$dueDate}</td></tr>";

    // ── #5 Receipt: green paid + orange balance; others: amount due ─
    if ($isReceipt) {
        if ($paidAmount) $summaryRows .= "<tr><td style='padding:8px 0 4px;color:#1B5E20;font-size:14px;font-weight:700'>&#10003; Amount Paid</td><td style='padding:8px 0 4px;text-align:right;font-size:15px;font-weight:700;color:#1B5E20'>{$paidAmount}</td></tr>";
        if ($remaining && $remaining !== '&#8377;0.00' && $remaining !== '₹0.00') {
            $summaryRows .= "<tr><td style='padding:4px 0 0;color:#E65100;font-size:13px;font-weight:700'>Balance Due</td><td style='padding:4px 0 0;text-align:right;font-size:13px;font-weight:700;color:#E65100'>{$remaining}</td></tr>";
        }
    } else {
        if ($amount) $summaryRows .= "<tr><td style='padding:8px 0 0;color:#333;font-size:14px;font-weight:700'>" . ($isEstimate ? 'Total' : 'Amount Due') . "</td><td style='padding:8px 0 0;text-align:right;font-size:15px;font-weight:700;color:{$accent}'>{$amount}</td></tr>";
    }

    // CTA button label based on type
    $ctaLabel = match($type) {
        'estimate' => 'View &amp; Download Estimate',
        'receipt'  => 'View Payment Receipt',
        default    => 'View &amp; Download Invoice',
    };

    // CTA button
    $ctaBtn = $portalLink ? "
    <div style='text-align:center;margin:24px 0 8px'>
      <a href='{$portalLink}' style='display:inline-block;background:{$hdrBg};color:#fff;text-decoration:none;padding:14px 36px;border-radius:8px;font-size:15px;font-weight:700;font-family:Arial,sans-serif;letter-spacing:.3px'>{$emoji} {$ctaLabel}</a>
    </div>
    <p style='text-align:center;font-size:11px;color:#aaa;margin:0 0 8px'>Button not working? <a href='{$portalLink}' style='color:{$accent}'>Click here</a></p>
    " : '';

    // Contact details for closer
    $contactParts = [];
    if ($compPhone) $contactParts[] = $compPhone;
    if ($compEmail) $contactParts[] = "<a href='mailto:{$compEmail}' style='color:#c5cae9'>{$compEmail}</a>";
    $contactCloser = !empty($contactParts) ? implode(' &nbsp;&middot;&nbsp; ', $contactParts) : '';
    $contactLine = '';

    // ── #2 Urgency bar — overdue & reminder ───────────────────────
    $urgencyBar = '';
    if ($type === 'overdue' && $daysOver > 0 && $amount) {
        $urgencyBar = "
    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin-bottom:4px'>
      <tr>
        <td bgcolor='#FFEBEE' style='background:#FFEBEE;border-left:4px solid #B71C1C;padding:12px 20px'>
          <table width='100%' cellpadding='0' cellspacing='0'>
            <tr>
              <td style='font-size:13px;font-weight:700;color:#B71C1C;font-family:Arial,sans-serif'>&#9888; {$daysOver} day(s) overdue</td>
              <td style='text-align:right;font-size:13px;font-weight:700;color:#B71C1C;font-family:Arial,sans-serif'>{$amount} unpaid</td>
            </tr>
          </table>
        </td>
      </tr>
    </table>";
    } elseif ($type === 'reminder' && $dueDate && $amount) {
        $urgencyBar = "
    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin-bottom:4px'>
      <tr>
        <td bgcolor='#FFF3E0' style='background:#FFF3E0;border-left:4px solid #E65100;padding:12px 20px'>
          <table width='100%' cellpadding='0' cellspacing='0'>
            <tr>
              <td style='font-size:13px;font-weight:700;color:#E65100;font-family:Arial,sans-serif'>&#128276; Payment due {$dueDate}</td>
              <td style='text-align:right;font-size:13px;font-weight:700;color:#E65100;font-family:Arial,sans-serif'>{$amount} due</td>
            </tr>
          </table>
        </td>
      </tr>
    </table>";
    }

    // ── #4 Hero strip with type-matched colours ────────────────────
    $heroSection = '';
    if ($invoiceNo || $amount) {
        $statusBadge = $status ? "<span style='display:inline-block;background:{$sBg};color:{$sCol};border-radius:12px;padding:3px 10px;font-size:11px;font-weight:700;margin-top:8px'>{$status}</span>" : '';
        $heroSection = "
    <div style='background:{$heroBg};padding:18px 32px;text-align:center;border-bottom:1px solid #e0e0e0'>
      <div style='font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:{$heroLabel};margin-bottom:4px'>{$typeLabel}</div>
      <div style='font-size:20px;font-weight:700;color:{$heroNum};font-family:Courier New,monospace'>{$invoiceNo}</div>
      {$statusBadge}
    </div>";
    }

    // Summary card
    $summaryCard = $summaryRows ? "
    <div style='background:#F8F9FF;border-radius:8px;padding:14px 16px;margin:16px 0;border:1px solid #E0E4FF'>
      <table style='width:100%;border-collapse:collapse'>{$summaryRows}</table>
    </div>" : '';

    $compInitials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $company), 0, 2));

    // ── #6 Type-specific header badge ─────────────────────────────
    $badgeLabels = [
        'invoice'  => '&#9993; Invoice',
        'estimate' => '&#9993; Estimate',
        'receipt'  => '&#10003; Receipt',
        'reminder' => '&#128276; Reminder',
        'overdue'  => '&#9888; Overdue',
        'followup' => '&#128222; Follow-up',
        'test'     => '&#9881; Test',
    ];
    $badgeText = $badgeLabels[$type] ?? '&#9993; Email';
    // Using solid hex for inner elements — Gmail strips rgba()
    // Semi-transparent white overlay approximated as solid lighter shade of hdrBg
    $logoBox  = 'background:#ffffff;opacity:0.15'; // fallback — overridden per-type below
    // Compute a lightened version for logo box background (20% white overlay approximation)
    $logoBg   = '#3949AB'; // default
    $badgeBg  = '#3949AB';
    $logoBgMap = [
        '#1A237E' => '#3949AB',
        '#1565C0' => '#1976D2',
        '#1B5E20' => '#2E7D32',
        '#E65100' => '#F57C00',
        '#B71C1C' => '#C62828',
        '#4A148C' => '#6A1B9A',
        '#004D40' => '#00695C',
    ];
    $logoBg = $logoBgMap[$hdrBg] ?? '#3949AB';

    $emailHeader = '
      <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td bgcolor="' . $hdrBg . '" style="background:' . $hdrBg . ';padding:16px 20px;vertical-align:middle;width:52px">
            <div style="width:40px;height:40px;border-radius:8px;background:' . $logoBg . ';text-align:center;line-height:40px;font-size:14px;font-weight:700;color:#fff;font-family:Arial,sans-serif">' . $compInitials . '</div>
          </td>
          <td bgcolor="' . $hdrBg . '" style="background:' . $hdrBg . ';padding:16px 8px;vertical-align:middle">
            <div style="font-size:15px;font-weight:700;color:#ffffff;margin:0;font-family:Arial,sans-serif">' . htmlspecialchars($company) . '</div>
            <div style="font-size:11px;color:#c5cae9;margin-top:2px;font-family:Arial,sans-serif">Invoice Manager</div>
          </td>
          <td bgcolor="' . $hdrBg . '" style="background:' . $hdrBg . ';padding:16px 20px;vertical-align:middle;text-align:right;white-space:nowrap">
            <table cellpadding="0" cellspacing="0" border="0" style="display:inline-table;margin-left:auto">
              <tr>
                <td bgcolor="' . $logoBg . '" style="background:' . $logoBg . ';border-radius:12px;padding:4px 11px;font-size:10px;font-weight:700;color:#ffffff;font-family:Arial,sans-serif;white-space:nowrap">' . $badgeText . '</td>
              </tr>
            </table>
          </td>
        </tr>
      </table>';

    $closingMessages = [
        'invoice'  => 'Thank you for your business!',
        'estimate' => 'We look forward to working with you!',
        'receipt'  => 'We truly appreciate your prompt payment!',
        'reminder' => 'Please settle at your earliest convenience.',
        'overdue'  => 'Immediate payment is appreciated.',
        'followup' => 'We hope to resolve this matter soon.',
        'test'     => 'SMTP is working correctly.',
    ];
    $closingMsg = $closingMessages[$type] ?? 'Thank you for your business!';

    $emailCloser = '
      <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td bgcolor="' . $hdrBg . '" style="background:' . $hdrBg . ';padding:18px 24px;text-align:center">
            <div style="font-size:13px;color:#c5cae9;line-height:1.8;font-family:Arial,sans-serif">' . $closingMsg . '</div>
            <div style="font-size:14px;font-weight:700;color:#ffffff;margin-top:4px;font-family:Arial,sans-serif">Warm regards, ' . htmlspecialchars($company) . '</div>
            <div style="font-size:11px;color:#9fa8da;margin-top:5px;font-family:Arial,sans-serif">' . $contactCloser . '</div>
          </td>
        </tr>
      </table>';

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$typeLabel} from {$company}</title>
</head>
<body style="font-family:Arial,sans-serif;background:#f0f2f5;padding:20px;margin:0">
  <div style="max-width:600px;margin:0 auto">

    <!-- Card -->
    <div style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.08)">

      <!-- Header -->
      {$emailHeader}

      <!-- Hero (invoice number + status) -->
      {$heroSection}

      <!-- Urgency bar (overdue/reminder only) -->
      {$urgencyBar}

      <!-- Body -->
      <div style="padding:24px 28px;color:#333;font-size:14px;line-height:1.85">
        {$cleanBody}
        {$summaryCard}
        {$ctaBtn}
        {$contactLine}
      </div>

      <!-- Navy closer -->
      {$emailCloser}

    </div>

    <!-- Footer -->
    <div style="text-align:center;padding:14px;font-size:11px;color:#aaa">
      Sent via <a href="https://optmstech.in" style="color:#777">OPTMS Tech Invoice Manager</a>
      &middot; <a href="https://optmstech.in" style="color:#777">optmstech.in</a>
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
    $tables = [
        "CREATE TABLE IF NOT EXISTS email_templates (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            type       VARCHAR(32) NOT NULL UNIQUE,
            subject    TEXT NOT NULL,
            body       TEXT NOT NULL,
            enabled    TINYINT(1) DEFAULT 1,
            created_at DATETIME,
            updated_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS email_logs (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS smtp_profiles (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS invoice_portal_tokens (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            token      VARCHAR(64) NOT NULL UNIQUE,
            created_at DATETIME,
            KEY idx_invoice (invoice_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($tables as $sql) {
        try { $db->exec($sql); } catch (\Exception $e) {
            error_log('ensureEmailTables: ' . $e->getMessage());
        }
    }

    // ── MySQL 5.7 compatible column migrations ──────────────────
    // ADD COLUMN IF NOT EXISTS is MySQL 8.0+ only.
    // Use INFORMATION_SCHEMA to check before altering on 5.7.
    $migrateColumns = [
        ['smtp_profiles', 'updated_at', "ALTER TABLE smtp_profiles ADD COLUMN updated_at DATETIME NULL DEFAULT NULL"],
        ['smtp_profiles', 'created_at', "ALTER TABLE smtp_profiles ADD COLUMN created_at DATETIME NULL DEFAULT NULL"],
        ['smtp_profiles', 'api_key',    "ALTER TABLE smtp_profiles ADD COLUMN api_key VARCHAR(500) NULL DEFAULT NULL"],
    ];
    foreach ($migrateColumns as [$table, $column, $alterSql]) {
        try {
            $chk = $db->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );
            $chk->execute([$table, $column]);
            if ((int)$chk->fetchColumn() === 0) {
                $db->exec($alterSql);
            }
        } catch (\Exception $e) {
            error_log("migrate {$table}.{$column}: " . $e->getMessage());
        }
    }
}

// ── Default built-in templates (used when DB has none) ───────────
function getDefaultTemplates(): array {
    return [

        // ── Pending Invoice ──────────────────────────────────────
        'invoice' => [
            'subject' => 'Invoice #{invoice_no} from {company_name} – {amount}',
            'body'    =>
"Dear {client_name},

Please find your invoice from {company_name} below. Kindly review and make the payment before the due date.

If you have any questions, feel free to contact us at {company_email} or {company_phone}.",
        ],

        // ── Estimate / Quotation ─────────────────────────────────
        'estimate' => [
            'subject' => 'Estimate #{invoice_no} from {company_name} – {amount}',
            'body'    =>
"Dear {client_name},

Thank you for your enquiry. Please find our estimate below.

This is an estimate only and is subject to change upon your approval.",
        ],

        // ── Payment Receipt (Paid / Partial) ─────────────────────
        'receipt' => [
            'subject' => 'Payment Receipt for Invoice #{invoice_no} – {company_name}',
            'body'    =>
"Dear {client_name},

Thank you! We have received your payment for Invoice #{invoice_no}.",
        ],

        // ── Payment Reminder ─────────────────────────────────────
        'reminder' => [
            'subject' => 'Friendly Reminder: Invoice #{invoice_no} due on {due_date}',
            'body'    =>
"Dear {client_name},

This is a friendly reminder that Invoice #{invoice_no} is due on {due_date}. If you have already made the payment, please ignore this message.",
        ],

        // ── Overdue Notice ───────────────────────────────────────
        'overdue' => [
            'subject' => 'OVERDUE: Invoice #{invoice_no} — {days_overdue} day(s) past due',
            'body'    =>
"Dear {client_name},

Your Invoice #{invoice_no} is now {days_overdue} day(s) overdue. Immediate payment is requested to avoid any disruption to services.",
        ],

        // ── Follow-up ────────────────────────────────────────────
        'followup' => [
            'subject' => 'Follow-up: Invoice #{invoice_no} still outstanding',
            'body'    =>
"Dear {client_name},

We are following up on Invoice #{invoice_no} which remains outstanding for {days_overdue} day(s).
 We kindly request you to settle the amount at your earliest convenience. 
 If you have any concerns or wish to discuss a payment arrangement, please do not hesitate to contact us.",
        ],
    ];
}
// ================================================================
ob_end_clean();