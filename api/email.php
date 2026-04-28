<?php
// ================================================================
//  api/email.php — Full Email System for OPTMS Invoice Manager
//
//  POST action=test          → Test SMTP connection
//  POST action=send          → Send invoice/estimate email
//  POST action=send_receipt  → Send payment receipt
//  POST action=send_reminder → Send overdue/due reminder
//  POST action=preview       → Return rendered HTML preview
//  GET  action=logs          → List email logs
//  GET  action=logs&invoice_id=X → Logs for one invoice
//  GET  action=templates     → List email templates
//  POST action=save_template → Save/update an email template
//  GET  action=smtp_profiles → List SMTP profiles
//  POST action=save_profile  → Save SMTP profile
//  DELETE action=del_profile → Delete SMTP profile
// ================================================================
ob_start();
error_reporting(0);

// Public tracking pixel — no auth required
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['track'])) {
    require_once __DIR__ . '/../config/db.php';
    $token = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['track']);
    try {
        $db = getDB();
        $db->prepare("UPDATE email_logs SET opened_at = IF(opened_at IS NULL, NOW(), opened_at), open_count = open_count + 1 WHERE track_token = ? LIMIT 1")->execute([$token]);
    } catch (Exception $e) {}
    // Return 1x1 transparent GIF
    header('Content-Type: image/gif');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Allow unauthenticated tracking pixel only (handled above)
requireLogin();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($method === 'GET' ? 'logs' : '');
if ($method === 'POST') {
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? 'send';
}

$db = getDB();

// ── Auto-create tables if not exist ─────────────────────────────
function ensureEmailTables($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS email_logs (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id    INT DEFAULT NULL,
        invoice_number VARCHAR(50) DEFAULT NULL,
        client_name   VARCHAR(200) DEFAULT NULL,
        to_email      VARCHAR(200) NOT NULL,
        subject       VARCHAR(500) NOT NULL,
        body_html     MEDIUMTEXT,
        status        ENUM('sent','failed','pending') NOT NULL DEFAULT 'pending',
        error_msg     TEXT DEFAULT NULL,
        smtp_profile  VARCHAR(100) DEFAULT 'default',
        type          VARCHAR(60) NOT NULL DEFAULT 'invoice' COMMENT 'invoice|estimate|receipt|reminder|overdue|followup|test',
        track_token   VARCHAR(64) DEFAULT NULL,
        opened_at     DATETIME DEFAULT NULL,
        open_count    INT UNSIGNED NOT NULL DEFAULT 0,
        sent_at       DATETIME DEFAULT NULL,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_el_invoice (invoice_id),
        INDEX idx_el_status (status),
        INDEX idx_el_track (track_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS email_templates (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        type        VARCHAR(60) NOT NULL UNIQUE COMMENT 'invoice|estimate|receipt|reminder|overdue|followup',
        name        VARCHAR(150) NOT NULL,
        subject     VARCHAR(500) NOT NULL,
        body        MEDIUMTEXT NOT NULL,
        is_active   TINYINT(1) NOT NULL DEFAULT 1,
        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS smtp_profiles (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100) NOT NULL,
        host        VARCHAR(200) NOT NULL,
        port        SMALLINT NOT NULL DEFAULT 587,
        username    VARCHAR(200) NOT NULL,
        password    VARCHAR(500) NOT NULL,
        from_email  VARCHAR(200) NOT NULL,
        from_name   VARCHAR(200) NOT NULL DEFAULT 'OPTMS Tech',
        encryption  VARCHAR(10) NOT NULL DEFAULT 'tls' COMMENT 'tls|ssl|none',
        provider    VARCHAR(30) NOT NULL DEFAULT 'smtp' COMMENT 'smtp|gmail|sendgrid|mailgun',
        api_key     VARCHAR(500) DEFAULT NULL,
        is_default  TINYINT(1) NOT NULL DEFAULT 0,
        is_active   TINYINT(1) NOT NULL DEFAULT 1,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
ensureEmailTables($db);

// ── Default templates ────────────────────────────────────────────
function getDefaultTemplates(): array {
    return [
        'invoice' => [
            'name'    => 'New Invoice',
            'subject' => 'Invoice #{invoice_no} from {company_name} – {currency}{amount}',
            'body'    => "Dear {client_name},\n\nPlease find your invoice details below.\n\n📄 Invoice No: #{invoice_no}\n📅 Issue Date: {issue_date}\n⏰ Due Date: {due_date}\n💰 Amount Due: {currency}{amount}\n📋 Service: {service}\n\n{item_list}\n\nPayment Options:\n🏦 {bank_details}\n💳 UPI: {upi}\n\n🔗 View Invoice Online:\n{invoice_link}\n\nThank you for your business!\n\n{company_name}\n{company_phone} | {company_email}",
        ],
        'estimate' => [
            'name'    => 'New Estimate / Quotation',
            'subject' => 'Estimate #{invoice_no} from {company_name} – {currency}{amount}',
            'body'    => "Dear {client_name},\n\nThank you for your inquiry. Please find our estimate below.\n\n📋 Estimate No: #{invoice_no}\n📅 Date: {issue_date}\n✅ Valid Until: {due_date}\n💰 Estimated Amount: {currency}{amount}\n📋 Service: {service}\n\n{item_list}\n\n⚠️ Note: This is an ESTIMATE only, not a final invoice. Actual charges may vary.\n\n🔗 View & Approve Estimate Online:\n{invoice_link}\n\nPlease reply to this email to approve or request changes.\n\n{company_name}\n{company_phone} | {company_email}",
        ],
        'receipt' => [
            'name'    => 'Payment Receipt',
            'subject' => 'Payment Received – Invoice #{invoice_no} | {company_name}',
            'body'    => "Dear {client_name},\n\nWe have received your payment. Thank you!\n\n✅ Payment Confirmed\n📄 Invoice No: #{invoice_no}\n💰 Amount Paid: {currency}{paid_amount}\n📅 Payment Date: {issue_date}\n\n{remaining_amount}\n\nThank you for your prompt payment!\n\n{company_name}\n{company_phone} | {company_email}",
        ],
        'reminder' => [
            'name'    => 'Payment Due Reminder',
            'subject' => 'Payment Reminder – Invoice #{invoice_no} Due on {due_date}',
            'body'    => "Dear {client_name},\n\nThis is a friendly reminder that your invoice is due soon.\n\n📄 Invoice No: #{invoice_no}\n⏰ Due Date: {due_date}\n💰 Amount Due: {currency}{amount}\n📋 Service: {service}\n\n🔗 View Invoice:\n{invoice_link}\n\nPlease ensure payment is made by the due date.\n\nBest regards,\n{company_name}\n{company_phone}",
        ],
        'overdue' => [
            'name'    => 'Overdue Notice',
            'subject' => '⚠️ Overdue Invoice #{invoice_no} – Action Required',
            'body'    => "Dear {client_name},\n\nYour invoice is now overdue. Please arrange payment immediately.\n\n📄 Invoice No: #{invoice_no}\n⏰ Was Due: {due_date}\n📅 Days Overdue: {days_overdue}\n💰 Amount Due: {currency}{amount}\n\n🔗 Pay Now:\n{invoice_link}\n\nIf you have already made the payment, please ignore this message or send us the transaction details.\n\n{company_name}\n{company_phone}",
        ],
        'followup' => [
            'name'    => 'Overdue Follow-up',
            'subject' => 'Follow-up: Invoice #{invoice_no} Still Unpaid – {days_overdue} Days Overdue',
            'body'    => "Dear {client_name},\n\nWe are writing to follow up on the outstanding invoice #{invoice_no}.\n\nDespite our previous reminder, we have not yet received your payment.\n\n📄 Invoice: #{invoice_no}\n💰 Amount: {currency}{amount}\n📅 Days Overdue: {days_overdue}\n\nPlease contact us immediately to arrange payment or discuss any concerns.\n\n🔗 View Invoice:\n{invoice_link}\n\n{company_name}\n{company_phone}",
        ],
    ];
}

// ── Get SMTP config ──────────────────────────────────────────────
function getSmtpConfig($db, ?string $profileName = null): array {
    // Try named profile first
    if ($profileName) {
        $stmt = $db->prepare("SELECT * FROM smtp_profiles WHERE name = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$profileName]);
        $p = $stmt->fetch();
        if ($p) return mapProfile($p);
    }
    // Try default profile
    $stmt = $db->query("SELECT * FROM smtp_profiles WHERE is_default = 1 AND is_active = 1 LIMIT 1");
    $p = $stmt->fetch();
    if ($p) return mapProfile($p);
    // Fall back to settings table
    $stmt = $db->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from','smtp_name')");
    $cfg  = [];
    foreach ($stmt->fetchAll() as $r) $cfg[$r['key']] = $r['value'];
    return [
        'host'       => $cfg['smtp_host'] ?? '',
        'port'       => (int)($cfg['smtp_port'] ?? 587),
        'user'       => $cfg['smtp_user'] ?? '',
        'pass'       => $cfg['smtp_pass'] ?? '',
        'from'       => $cfg['smtp_from'] ?? $cfg['smtp_user'] ?? '',
        'name'       => $cfg['smtp_name'] ?? 'OPTMS Tech',
        'encryption' => 'tls',
        'provider'   => 'smtp',
        'api_key'    => '',
    ];
}
function mapProfile(array $p): array {
    return [
        'host'       => $p['host'],
        'port'       => (int)$p['port'],
        'user'       => $p['username'],
        'pass'       => $p['password'],
        'from'       => $p['from_email'],
        'name'       => $p['from_name'],
        'encryption' => $p['encryption'] ?? 'tls',
        'provider'   => $p['provider']   ?? 'smtp',
        'api_key'    => $p['api_key']    ?? '',
    ];
}

// ── Replace template variables ───────────────────────────────────
function replaceVars(string $tpl, array $data): string {
    $map = [
        '{client_name}'          => $data['client_name']          ?? '',
        '{invoice_no}'           => $data['invoice_number']        ?? '',
        '{amount}'               => $data['amount']                ?? '',
        '{currency}'             => $data['currency']              ?? '₹',
        '{due_date}'             => $data['due_date']              ?? '',
        '{issue_date}'           => $data['issued_date']           ?? '',
        '{service}'              => $data['service_type']          ?? '',
        '{company_name}'         => $data['company_name']          ?? 'OPTMS Tech',
        '{company_phone}'        => $data['company_phone']         ?? '',
        '{company_email}'        => $data['company_email']         ?? '',
        '{upi}'                  => $data['upi']                   ?? '',
        '{bank_details}'         => $data['bank_details']          ?? '',
        '{days_overdue}'         => $data['days_overdue']          ?? '0',
        '{item_list}'            => $data['item_list']             ?? '',
        '{paid_amount}'          => $data['paid_amount']           ?? '',
        '{remaining_amount}'     => $data['remaining_amount']      ?? '',
        '{settlement_discount}'  => $data['settlement_discount']   ?? '',
        '{invoice_link}'         => $data['invoice_link']          ?? '',
    ];
    return str_replace(array_keys($map), array_values($map), $tpl);
}

// ── Build item list text ─────────────────────────────────────────
function buildItemList(array $items): string {
    if (empty($items)) return '';
    $lines = ["Items:"];
    foreach ($items as $item) {
        $lines[] = "  • " . $item['description'] . " — Qty: " . $item['quantity'] . " × ₹" . number_format((float)$item['rate'], 2);
    }
    return implode("\n", $lines);
}

// ── Build branded HTML email — OPTMS Design ─────────────────────
function buildEmailHTML(string $body, array $data, ?string $trackToken, string $appUrl): string {
    $company   = htmlspecialchars($data['company_name']   ?? 'OPTMS Tech');
    $phone     = htmlspecialchars($data['company_phone']  ?? '');
    $email     = htmlspecialchars($data['company_email']  ?? '');
    $gst       = htmlspecialchars($data['company_gst']    ?? '');
    $logo      = $data['company_logo'] ?? '';
    $signature = $data['company_sign'] ?? $data['signature'] ?? '';
    $trackImg  = $trackToken ? "<img src='{$appUrl}/api/email.php?track={$trackToken}' width='1' height='1' style='display:none' alt=''>" : '';
    $year      = date('Y');
    $type      = $data['type'] ?? 'invoice';
    $isEst     = $type === 'estimate';

    // colours
    $teal      = '#0b3d35';
    $tealMid   = '#0f5a47';
    $tealAccent= '#4ecdc4';
    $tealLight = '#ddf0ec';
    $tealBorder= '#0f7a5f';

    // ── Logo block ─────────────────────────────────────────────
    if ($logo) {
        $logoBlock = "<img src='{$logo}' alt='{$company}' style='max-height:52px;max-width:170px;object-fit:contain;border-radius:8px'>";
    } else {
        $logoBlock = "
        <table cellpadding='0' cellspacing='0' border='0'>
          <tr>
            <td style='padding-right:14px;vertical-align:middle'>
              <svg width='52' height='52' viewBox='0 0 52 52' xmlns='http://www.w3.org/2000/svg'>
                <polygon points='26,2 48,14 48,38 26,50 4,38 4,14' fill='#1a7a65' stroke='{$tealAccent}' stroke-width='2'/>
                <polygon points='26,8 43,18 43,36 26,46 9,36 9,18' fill='none' stroke='{$tealAccent}' stroke-width='1' opacity='0.5'/>
                <text x='15' y='33' font-family='Arial Black,Arial' font-weight='900' font-size='22' fill='{$tealAccent}'>P</text>
              </svg>
            </td>
            <td style='vertical-align:middle'>
              <div style='font-size:22px;font-weight:900;color:#ffffff;letter-spacing:1px;line-height:1'>" . strtoupper($company) . "</div>
              <div style='font-size:12px;color:#7ecfc4;margin-top:4px'>Code your way to progress</div>
            </td>
          </tr>
        </table>";
    }

    // ── Invoice info card — 5 column with teal border ──────────
    $invNum  = htmlspecialchars($data['invoice_number'] ?? '');
    $rawDue  = $data['due_date'] ?? '';
    $cur     = htmlspecialchars($data['currency'] ?? '&#8377;');
    $amount  = $cur . htmlspecialchars($data['amount'] ?? '');
    $service = htmlspecialchars($data['service_type'] ?? '');
    try { $dueFormatted = $rawDue ? (new DateTime($rawDue))->format('d F Y') : ''; } catch (Exception $e) { $dueFormatted = $rawDue; }
    $dueLabel = $isEst ? 'Valid Until' : 'Due Date';
    $invLabel = $isEst ? 'Estimate No.' : 'Invoice No.';

    $infoCard = $invNum ? "
    <table cellpadding='0' cellspacing='0' border='0' width='100%'
      style='border:1.8px solid {$tealBorder};border-radius:12px;overflow:hidden;margin:20px 0;background:#ffffff'>
      <tr>
        <!-- receipt icon -->
        <td width='90' style='border-right:1px solid #c4e8e0;background:#f9fefd;text-align:center;padding:22px 8px;vertical-align:middle'>
          <div style='width:62px;height:62px;border-radius:50%;background:{$tealLight};display:inline-block;text-align:center;line-height:62px;font-size:28px'>&#128196;</div>
        </td>
        <!-- invoice no -->
        <td style='border-right:1px solid #e5e7eb;text-align:center;padding:18px 8px;vertical-align:middle'>
          <div style='width:36px;height:36px;border-radius:50%;background:{$tealLight};display:inline-block;text-align:center;line-height:36px;font-size:14px;margin-bottom:6px'>#</div>
          <div style='font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px'>{$invLabel}</div>
          <div style='font-size:13px;font-weight:700;color:#111827;margin-top:3px'>{$invNum}</div>
        </td>
        <!-- amount -->
        <td style='border-right:1px solid #e5e7eb;text-align:center;padding:18px 8px;vertical-align:middle'>
          <div style='width:36px;height:36px;border-radius:50%;background:{$tealLight};display:inline-block;text-align:center;line-height:36px;font-size:14px;margin-bottom:6px'>&#8377;</div>
          <div style='font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px'>Amount Due</div>
          <div style='font-size:13px;font-weight:700;color:#111827;margin-top:3px'>{$amount}</div>
        </td>
        <!-- due date -->
        <td style='border-right:1px solid #e5e7eb;text-align:center;padding:18px 8px;vertical-align:middle'>
          <div style='width:36px;height:36px;border-radius:50%;background:{$tealLight};display:inline-block;text-align:center;line-height:36px;font-size:14px;margin-bottom:6px'>&#128197;</div>
          <div style='font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px'>{$dueLabel}</div>
          <div style='font-size:13px;font-weight:700;color:#111827;margin-top:3px'>{$dueFormatted}</div>
        </td>
        <!-- service -->
        <td style='text-align:center;padding:18px 8px;vertical-align:middle'>
          <div style='width:36px;height:36px;border-radius:50%;background:{$tealLight};display:inline-block;text-align:center;line-height:36px;font-size:14px;margin-bottom:6px'>&#127760;</div>
          <div style='font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px'>Service</div>
          <div style='font-size:13px;font-weight:700;color:#111827;margin-top:3px'>{$service}</div>
        </td>
      </tr>
    </table>" : '';

    // ── Payment details ────────────────────────────────────────
    $upi     = htmlspecialchars($data['upi'] ?? $data['company_upi'] ?? '');
    $bankRaw = $data['bank_details'] ?? $data['default_bank'] ?? '';
    $payRows = '';
    if ($upi) {
        $payRows .= "
        <tr>
          <td style='background:{$tealLight};padding:14px 0;text-align:center;width:54px;border-bottom:1px solid #c4e8e0;border-right:1px solid #c4e8e0'>
            <span style='font-size:18px'>&#128241;</span>
          </td>
          <td style='padding:14px 18px;border-bottom:1px solid #e5e7eb;font-weight:600;color:#111827;font-size:13.5px;width:130px'>UPI</td>
          <td style='padding:14px 4px;border-bottom:1px solid #e5e7eb;color:#6b7280;width:10px'>:</td>
          <td style='padding:14px 16px;border-bottom:1px solid #e5e7eb;color:#111827;font-size:13.5px'>{$upi}</td>
        </tr>";
    }
    // Map bank detail labels to icons
    $iconMap = [
        'bank'           => '&#127970;',
        'account name'   => '&#128100;',
        'account no'     => '&#128179;',
        'account number' => '&#128179;',
        'ifsc'           => '&#127758;',
        'branch'         => '&#128205;',
    ];
    $bankLines = array_filter(array_map('trim', explode("\n", $bankRaw)));
    $lastBankLine = end($bankLines);
    foreach ($bankLines as $line) {
        if (strpos($line, ':') === false) continue;
        [$k, $v] = array_map('trim', explode(':', $line, 2));
        $ico = '&#128203;';
        foreach ($iconMap as $kw => $ic) {
            if (stripos($k, $kw) !== false) { $ico = $ic; break; }
        }
        $isLast = ($line === $lastBankLine);
        $borderB = $isLast ? '' : 'border-bottom:1px solid #e5e7eb';
        $borderBi = $isLast ? '' : 'border-bottom:1px solid #c4e8e0';
        $payRows .= "
        <tr>
          <td style='background:{$tealLight};padding:14px 0;text-align:center;width:54px;{$borderBi};border-right:1px solid #c4e8e0'>
            <span style='font-size:18px'>{$ico}</span>
          </td>
          <td style='padding:14px 18px;{$borderB};font-weight:600;color:#111827;font-size:13.5px;width:130px'>" . htmlspecialchars($k) . "</td>
          <td style='padding:14px 4px;{$borderB};color:#6b7280;width:10px'>:</td>
          <td style='padding:14px 16px;{$borderB};color:#111827;font-size:13.5px'>" . htmlspecialchars($v) . "</td>
        </tr>";
    }
    $paySection = $payRows ? "
    <div style='margin:20px 0 24px'>
      <div style='font-size:13px;font-weight:800;color:#1a1a2e;letter-spacing:1px;text-transform:uppercase;margin-bottom:6px'>Payment Details</div>
      <div style='width:36px;height:3px;background:#e74c3c;border-radius:2px;margin-bottom:16px'></div>
      <table cellpadding='0' cellspacing='0' border='0' width='100%'
        style='border:1.8px solid {$tealBorder};border-radius:10px;overflow:hidden;background:#ffffff'>
        {$payRows}
      </table>
    </div>" : '';

    // ── Line items ─────────────────────────────────────────────
    $itemsSection = '';
    if (!empty($data['items'])) {
        $irows = '';
        foreach ($data['items'] as $it) {
            $tot = number_format((float)($it['line_total'] ?? ((float)($it['quantity']??1) * (float)($it['rate']??0))), 2);
            $irows .= "<tr style='border-bottom:1px solid #eef3f2'>
              <td style='padding:10px 16px;font-size:13px;color:#333'>" . htmlspecialchars($it['description']??'') . "</td>
              <td style='padding:10px 8px;font-size:13px;color:#666;text-align:center'>" . ($it['quantity']??1) . "</td>
              <td style='padding:10px 8px;font-size:13px;color:#666;text-align:right'>&#8377;" . number_format((float)($it['rate']??0), 2) . "</td>
              <td style='padding:10px 16px;font-size:13px;font-weight:700;color:#111;text-align:right'>&#8377;{$tot}</td>
            </tr>";
        }
        $itemsSection = "
        <table cellpadding='0' cellspacing='0' border='0' width='100%'
          style='border:1.5px solid {$tealBorder};border-radius:12px;overflow:hidden;margin-bottom:20px'>
          <thead><tr style='background:#f7faf9'>
            <th style='padding:10px 16px;font-size:11px;color:#888;text-align:left;font-weight:700;text-transform:uppercase;letter-spacing:.4px'>Description</th>
            <th style='padding:10px 8px;font-size:11px;color:#888;text-align:center;font-weight:700;text-transform:uppercase'>Qty</th>
            <th style='padding:10px 8px;font-size:11px;color:#888;text-align:right;font-weight:700;text-transform:uppercase'>Rate</th>
            <th style='padding:10px 16px;font-size:11px;color:#888;text-align:right;font-weight:700;text-transform:uppercase'>Total</th>
          </tr></thead>
          <tbody>{$irows}</tbody>
        </table>";
    }

    // ── CTA button ─────────────────────────────────────────────
    $ctaBtn = '';
    if (!empty($data['invoice_link'])) {
        $ctaLink  = htmlspecialchars($data['invoice_link']);
        $ctaLabel = $isEst ? '&#128203;&nbsp; View Estimate' : '&#128196;&nbsp; VIEW INVOICE';
        $ctaBtn = "
        <div style='text-align:center;margin:24px 0 28px'>
          <a href='{$ctaLink}'
            style='display:inline-block;background:{$teal};color:#ffffff;text-decoration:none;
                   padding:14px 52px;border-radius:8px;font-size:14px;font-weight:700;letter-spacing:.5px'>
            {$ctaLabel}
          </a>
        </div>";
    }

    // ── Divider — dotted line + email icon ─────────────────────
    $divider = "
    <table cellpadding='0' cellspacing='0' border='0' width='100%' style='margin:0 0 24px'>
      <tr valign='middle'>
        <td style='border-top:2.5px dashed #a8ddd5'></td>
        <td width='70' style='text-align:center;padding:0 11px'>
          <div style='width:48px;height:48px;border-radius:50%;background:{$tealLight};border:2px solid {$tealBorder};
                      display:inline-block;text-align:center;line-height:48px;font-size:22px'>&#128140;</div>
        </td>
        <td style='border-top:2.5px dashed #a8ddd5'></td>
      </tr>
    </table>";

    // ── Greeting / body ────────────────────────────────────────
    $clientName = htmlspecialchars($data['client_name'] ?? '');
    $bodyText   = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));

    // ── Signature / footer card ────────────────────────────────
    $sigImgHtml = $signature ? "<img src='{$signature}' alt='Signature' style='max-height:40px;max-width:130px;object-fit:contain;margin-top:6px;display:block'>" : '';
    if ($logo) {
        $sigLogoImg = "<img src='{$logo}' alt='{$company}' style='max-height:44px;max-width:130px;object-fit:contain'>";
    } else {
        $sigLogoImg = "
        <svg width='44' height='44' viewBox='0 0 44 44' xmlns='http://www.w3.org/2000/svg'>
          <circle cx='22' cy='22' r='22' fill='{$teal}'/>
          <polygon points='22,6 36,14 36,30 22,38 8,30 8,14' fill='#1a7a65' stroke='{$tealAccent}' stroke-width='1.5'/>
          <text x='13' y='29' font-family='Arial Black,Arial' font-weight='900' font-size='16' fill='{$tealAccent}'>P</text>
        </svg>";
    }
    $phHtml  = $phone ? "<span style='font-size:12.5px;color:#374151'>&#128222;&nbsp;{$phone}</span>" : '';
    $emHtml  = $email ? "<span style='font-size:12.5px;color:#374151'>&#9993;&nbsp;{$email}</span>" : '';
    $gstLine = $gst ? "<div style='font-size:11px;color:#9ca3af;margin-top:4px'>GST: {$gst}</div>" : '';

    // Social icons in bottom bar
    $socials = "
    <table cellpadding='0' cellspacing='0' border='0'>
      <tr>
        <td style='padding-right:8px'>
          <div style='width:28px;height:28px;border:1.5px solid {$tealAccent};border-radius:50%;text-align:center;line-height:26px;color:{$tealAccent};font-size:12px;font-weight:700'>f</div>
        </td>
        <td style='padding-right:8px'>
          <div style='width:28px;height:28px;border:1.5px solid {$tealAccent};border-radius:50%;text-align:center;line-height:26px;color:{$tealAccent};font-size:10px;font-weight:700'>in</div>
        </td>
        <td>
          <div style='width:28px;height:28px;border:1.5px solid {$tealAccent};border-radius:50%;text-align:center;line-height:26px;color:{$tealAccent};font-size:12px;font-weight:700'>&#10005;</div>
        </td>
      </tr>
    </table>";

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Email from {$company}</title>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,300,0,0" rel="stylesheet"/>
<style>
.ico{font-family:'Material Symbols Outlined';font-style:normal;font-weight:300;font-size:20px;
     line-height:1;display:inline-block;vertical-align:middle;
     font-variation-settings:'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 24;color:{$teal}}
     /* Greeting */
.greeting { padding: 28px 36px 16px; }
.greeting h2 { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.greeting h2 em { color: #0f7a5f; font-style: normal; }
.greeting p { color: #6b7280; font-size: 14px; margin-top: 8px; }

</style>
</head>
<body style="margin:0;padding:0;background:#f0f4f4;font-family:'Segoe UI',Helvetica,Arial,sans-serif">
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f0f4f4;padding:24px 0">
<tr><td align="center">
<table cellpadding="0" cellspacing="0" border="0" width="600"
  style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.10)">

  <!-- ══ HEADER ══ -->
  <tr>
    <td style="background:linear-gradient(135deg,{$teal} 0%,{$tealMid} 50%,#0d4a3a 100%);padding:0;overflow:hidden">
      <table cellpadding="0" cellspacing="0" border="0" width="100%">
        <tr>
          <td style="padding:28px 32px 0;position:relative">
            <div style="position:absolute;top:0;right:0;bottom:0;width:55%;opacity:.10;
                        background-image:repeating-linear-gradient(45deg,{$tealAccent} 0px,{$tealAccent} 1px,transparent 0px,transparent 50%);
                        background-size:12px 12px;pointer-events:none"></div>
            <table cellpadding="0" cellspacing="0" border="0" width="100%">
              <tr>
                <td style="vertical-align:middle">{$logoBlock}</td>
                <td align="right" style="vertical-align:middle">
                  <table cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td style="vertical-align:middle">
                        <div style="color:#7ecfc4;font-size:12px;font-weight:600;letter-spacing:.3px">Trusted. Reliable. Professional.</div>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="line-height:0;padding:0">
            <svg viewBox="0 0 600 40" xmlns="http://www.w3.org/2000/svg" style="display:block;width:100%">
              <path d="M0,20 Q75,40 150,20 Q225,0 300,20 Q375,40 450,20 Q525,0 600,20 L600,40 L0,40 Z" fill="#ffffff"/>
            </svg>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ══ BODY ══ -->
  <tr>
    <td style="padding:28px 36px 8px">

      <!-- Greeting -->
      <div style="font-size:22px;font-weight:700;color:#1a1a2e;margin-bottom:8px">
        <span style="color:{$tealBorder}">{$clientName},</span>
      </div>
          <div class="greeting">
            <h2>Hello <em>{$clientName},</em></h2>
            <p>We hope you are doing well. Please find your invoice details below.</p>
       </div>


      <div style="font-size:14px;color:#6b7280;line-height:1.75;margin-bottom:8px">{$bodyText}</div>

      <!-- Invoice card -->
      {$infoCard}

      <!-- Line items -->
      {$itemsSection}

      <!-- Payment details -->
      {$paySection}

    </td>
  </tr>

  <!-- ══ CTA BUTTON ══ -->
  <tr>
    <td align="center" style="padding:0 36px 28px">
      {$ctaBtn}
    </td>
  </tr>

  <!-- ══ DOTTED DIVIDER + EMAIL ICON ══ -->
  <tr>
    <td style="padding:0 36px 20px">
      {$divider}
    </td>
  </tr>

  <!-- ══ SUPPORT NOTE ══ -->
  <tr>
    <td align="center" style="padding:0 36px 28px">
      <div style="font-size:13px;color:#6b7280;margin-bottom:6px">If you have any questions, feel free to reach out.</div>
      <div style="font-size:14px;font-weight:700;color:{$tealBorder}">Thank you for your business!</div>
    </td>
  </tr>

  <!-- ══ FOOTER CARD ══ -->
  <tr>
    <td style="padding:0 36px 28px">
      <table cellpadding="0" cellspacing="0" border="0" width="100%"
        style="border:1.5px solid #e5e7eb;border-radius:12px;overflow:hidden">
        <tr>
          <!-- Company info -->
          <td width="50%" valign="top" style="padding:20px;border-right:1px solid #e5e7eb">
            <div style="font-size:12px;color:#6b7280;margin-bottom:10px">Best regards,</div>
            <table cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="padding-right:12px;vertical-align:middle">{$sigLogoImg}</td>
                <td style="vertical-align:middle">
                  <div style="font-size:13px;font-weight:700;color:#111827">{$company}</div>
                  <div style="font-size:11px;color:#9ca3af">Code your way to progress</div>
                </td>
              </tr>
            </table>
            <div style="margin-top:12px">{$phHtml}</div>
            <div style="margin-top:6px">{$emHtml}</div>
            {$gstLine}
          </td>
          <!-- Trust badges -->
          <td width="50%" valign="top" style="padding:20px">
            <!-- Secure Payments -->
            <table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px">
              <tr>
                <td style="padding-right:10px;vertical-align:middle">
                  <div style="width:34px;height:34px;border-radius:50%;background:{$tealLight};text-align:center;line-height:34px">
                    <span class="ico" style="font-size:17px;line-height:34px">lock</span>
                  </div>
                </td>
                <td style="vertical-align:middle">
                  <div style="font-weight:700;font-size:13px;color:#111827">Secure Payments</div>
                  <div style="font-size:11.5px;color:#9ca3af">Your payments are safe with us.</div>
                </td>
              </tr>
            </table>
            <!-- 24/7 Support -->
            <table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px">
              <tr>
                <td style="padding-right:10px;vertical-align:middle">
                  <div style="width:34px;height:34px;border-radius:50%;background:{$tealLight};text-align:center;line-height:34px">
                    <span class="ico" style="font-size:17px;line-height:34px">headset_mic</span>
                  </div>
                </td>
                <td style="vertical-align:middle">
                  <div style="font-weight:700;font-size:13px;color:#111827">24/7 Support</div>
                  <div style="font-size:11.5px;color:#9ca3af">We&apos;re here to help anytime.</div>
                </td>
              </tr>
            </table>
            <!-- Fast & Reliable -->
            <table cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="padding-right:10px;vertical-align:middle">
                  <div style="width:34px;height:34px;border-radius:50%;background:{$tealLight};text-align:center;line-height:34px">
                    <span class="ico" style="font-size:17px;line-height:34px">verified</span>
                  </div>
                </td>
                <td style="vertical-align:middle">
                  <div style="font-weight:700;font-size:13px;color:#111827">Fast &amp; Reliable</div>
                  <div style="font-size:11.5px;color:#9ca3af">Timely service, always.</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ══ BOTTOM BAR ══ -->
  <tr>
    <td style="background:{$teal};padding:16px 36px;border-radius:0 0 16px 16px">
      <table cellpadding="0" cellspacing="0" border="0" width="100%">
        <tr>
          <td style="vertical-align:middle">{$socials}</td>
          <td align="right" style="vertical-align:middle">
            <div style="color:#7ecfc4;font-size:11px">&#169; {$year} {$company}. All rights reserved.</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

</table>
</td></tr>
</table>
{$trackImg}
</body>
</html>
HTML;
}

// ── Core SMTP sender (PHPMailer / mail() fallback) ───────────────
function sendSmtpEmail(array $smtp, string $to, string $toName, string $subject, string $html, array $opts = []): array {
    if (empty($smtp['host']) || empty($smtp['user']) || empty($smtp['pass'])) {
        return ['success' => false, 'error' => 'SMTP not configured. Fill all fields and Save first.'];
    }
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
            $enc = $smtp['encryption'] ?? 'tls';
            $mail->SMTPSecure = ($enc === 'ssl' || (int)$smtp['port'] === 465)
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)$smtp['port'];
            $mail->setFrom($smtp['from'], $smtp['name']);
            $mail->addAddress($to, $toName);
            // CC self
            if (!empty($opts['cc_self'])) $mail->addCC($smtp['from'], $smtp['name']);
            // Extra CC/BCC
            if (!empty($opts['cc']))  foreach ((array)$opts['cc']  as $cc)  $mail->addCC($cc);
            if (!empty($opts['bcc'])) foreach ((array)$opts['bcc'] as $bcc) $mail->addBCC($bcc);
            $mail->isHTML(true);
            $mail->Subject  = $subject;
            $mail->Body     = $html;
            $mail->AltBody  = strip_tags(str_replace(['<br>','<br/>','<br />','</p>'], "\n", $html));
            $mail->CharSet  = 'UTF-8';
            $mail->send();
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
        }
    }
    // Fallback native mail()
    $headers  = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$smtp['name']} <{$smtp['from']}>\r\nReply-To: {$smtp['from']}\r\n";
    $sent = @mail($to, $subject, $html, $headers);
    if ($sent) return ['success' => true];
    return ['success' => false, 'error' => 'PHPMailer not installed. Run: composer require phpmailer/phpmailer'];
}

// ── Log an email ─────────────────────────────────────────────────
function logEmail($db, array $data): int {
    $stmt = $db->prepare("INSERT INTO email_logs (invoice_id, invoice_number, client_name, to_email, subject, body_html, status, error_msg, smtp_profile, type, track_token, sent_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $data['invoice_id']    ?? null,
        $data['invoice_number'] ?? null,
        $data['client_name']   ?? null,
        $data['to_email'],
        $data['subject'],
        $data['body_html']     ?? null,
        $data['status'],
        $data['error_msg']     ?? null,
        $data['smtp_profile']  ?? 'default',
        $data['type']          ?? 'invoice',
        $data['track_token']   ?? null,
        $data['status'] === 'sent' ? date('Y-m-d H:i:s') : null,
    ]);
    return (int)$db->lastInsertId();
}

// ── Fetch invoice data for email ─────────────────────────────────
function getInvoiceData($db, int $invId): array {
    $stmt = $db->prepare("SELECT i.*, c.email as c_email, c.phone as c_phone, c.whatsapp as c_wa FROM invoices i LEFT JOIN clients c ON c.id = i.client_id WHERE i.id = ?");
    $stmt->execute([$invId]);
    $inv = $stmt->fetch() ?: [];
    if ($inv) {
        $si = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order");
        $si->execute([$invId]);
        $inv['items'] = $si->fetchAll();
    }
    return $inv;
}

// ── Get company settings ─────────────────────────────────────────
function getCompanySettings($db): array {
    $stmt = $db->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('company_name','company_phone','company_email','company_upi','company_logo','default_bank')");
    $s = [];
    foreach ($stmt->fetchAll() as $r) $s[$r['key']] = $r['value'];
    return $s;
}

// ── Get portal link for invoice ──────────────────────────────────
function getPortalLink($db, int $invId, string $appUrl): string {
    $stmt = $db->prepare("SELECT token FROM portal_tokens WHERE invoice_id = ? LIMIT 1");
    $stmt->execute([$invId]);
    $row = $stmt->fetch();
    if ($row) return $appUrl . '/portal?token=' . $row['token'];
    // Auto-generate token
    try {
        $token = bin2hex(random_bytes(16));
        $db->prepare("INSERT INTO portal_tokens (invoice_id, token) VALUES (?,?) ON DUPLICATE KEY UPDATE token=VALUES(token)")->execute([$invId, $token]);
        return $appUrl . '/portal?token=' . $token;
    } catch (Exception $e) {
        return $appUrl;
    }
}

// ── Get email template ───────────────────────────────────────────
function getEmailTemplate($db, string $type): array {
    $stmt = $db->prepare("SELECT * FROM email_templates WHERE type = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$type]);
    $tpl = $stmt->fetch();
    if ($tpl) return $tpl;
    $defaults = getDefaultTemplates();
    return $defaults[$type] ?? $defaults['invoice'];
}

$appUrl = defined('APP_URL') ? APP_URL : 'http://invcs.optms.co.in';

// ════════════════════════════════════════════════════════════════
//  GET ACTIONS
// ════════════════════════════════════════════════════════════════
if ($method === 'GET') {

    // ── Email logs ──────────────────────────────────────────────
    if ($action === 'logs') {
        $where = ['1=1']; $params = [];
        if (!empty($_GET['invoice_id'])) { $where[] = 'invoice_id = ?'; $params[] = (int)$_GET['invoice_id']; }
        if (!empty($_GET['type']))       { $where[] = 'type = ?';       $params[] = $_GET['type']; }
        if (!empty($_GET['status']))     { $where[] = 'status = ?';     $params[] = $_GET['status']; }
        $sql  = 'SELECT id,invoice_id,invoice_number,client_name,to_email,subject,status,error_msg,type,opened_at,open_count,sent_at,created_at FROM email_logs WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 200';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // ── Email templates ─────────────────────────────────────────
    if ($action === 'templates') {
        $rows = $db->query("SELECT * FROM email_templates ORDER BY type")->fetchAll();
        $defaults = getDefaultTemplates();
        // Merge defaults for any missing types
        $found = array_column($rows, 'type');
        foreach ($defaults as $type => $d) {
            if (!in_array($type, $found)) {
                $rows[] = array_merge($d, ['id' => null, 'type' => $type, 'is_active' => 1]);
            }
        }
        jsonResponse(['success' => true, 'data' => $rows]);
    }

    // ── SMTP profiles ───────────────────────────────────────────
    if ($action === 'smtp_profiles') {
        $rows = $db->query("SELECT id,name,host,port,username,from_email,from_name,encryption,provider,is_default,is_active FROM smtp_profiles ORDER BY is_default DESC, name")->fetchAll();
        jsonResponse(['success' => true, 'data' => $rows]);
    }

    jsonResponse(['success' => false, 'error' => 'Unknown GET action'], 400);
}

// ════════════════════════════════════════════════════════════════
//  DELETE ACTIONS
// ════════════════════════════════════════════════════════════════
if ($method === 'DELETE') {
    if ($action === 'del_profile') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'error' => 'ID required'], 422);
        $db->prepare("DELETE FROM smtp_profiles WHERE id = ?")->execute([$id]);
        jsonResponse(['success' => true]);
    }
    jsonResponse(['success' => false, 'error' => 'Unknown DELETE action'], 400);
}

// ════════════════════════════════════════════════════════════════
//  POST ACTIONS
// ════════════════════════════════════════════════════════════════

// ── Save email template ─────────────────────────────────────────
if ($action === 'save_template') {
    $type    = $input['type']    ?? '';
    $subject = $input['subject'] ?? '';
    $body    = $input['body']    ?? '';
    if (!$type || !$subject || !$body) jsonResponse(['success' => false, 'error' => 'type, subject and body required'], 422);
    $validTypes = ['invoice','estimate','receipt','reminder','overdue','followup'];
    if (!in_array($type, $validTypes)) jsonResponse(['success' => false, 'error' => 'Invalid type'], 422);
    $name = $input['name'] ?? ucfirst($type);
    $db->prepare("INSERT INTO email_templates (type,name,subject,body) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),subject=VALUES(subject),body=VALUES(body),is_active=1")->execute([$type, $name, $subject, $body]);
    jsonResponse(['success' => true]);
}

// ── Save SMTP profile ───────────────────────────────────────────
if ($action === 'save_profile') {
    $name      = trim($input['name']       ?? '');
    $host      = trim($input['host']       ?? '');
    $port      = (int)($input['port']      ?? 587);
    $username  = trim($input['username']   ?? '');
    $password  = trim($input['password']   ?? '');
    $fromEmail = trim($input['from_email'] ?? '');
    $fromName  = trim($input['from_name']  ?? 'OPTMS Tech');
    $enc       = in_array($input['encryption'] ?? 'tls', ['tls','ssl','none']) ? $input['encryption'] : 'tls';
    $provider  = in_array($input['provider'] ?? 'smtp', ['smtp','gmail','sendgrid','mailgun']) ? $input['provider'] : 'smtp';
    $apiKey    = trim($input['api_key']    ?? '');
    $isDefault = (int)($input['is_default'] ?? 0);
    if (!$name || !$host || !$username) jsonResponse(['success' => false, 'error' => 'name, host and username required'], 422);
    if ($isDefault) $db->exec("UPDATE smtp_profiles SET is_default = 0");
    $id = (int)($input['id'] ?? 0);
    if ($id) {
        $db->prepare("UPDATE smtp_profiles SET name=?,host=?,port=?,username=?,password=?,from_email=?,from_name=?,encryption=?,provider=?,api_key=?,is_default=? WHERE id=?")->execute([$name,$host,$port,$username,$password,$fromEmail,$fromName,$enc,$provider,$apiKey,$isDefault,$id]);
    } else {
        $db->prepare("INSERT INTO smtp_profiles (name,host,port,username,password,from_email,from_name,encryption,provider,api_key,is_default) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute([$name,$host,$port,$username,$password,$fromEmail,$fromName,$enc,$provider,$apiKey,$isDefault]);
        $id = (int)$db->lastInsertId();
    }
    jsonResponse(['success' => true, 'id' => $id]);
}

// ── Preview ─────────────────────────────────────────────────────
if ($action === 'preview') {
    $type   = $input['type']       ?? 'invoice';
    $invId  = (int)($input['invoice_id'] ?? 0);
    $tpl    = getEmailTemplate($db, $type);
    $cs     = getCompanySettings($db);
    $inv    = $invId ? getInvoiceData($db, $invId) : [];
    $link   = $invId ? getPortalLink($db, $invId, $appUrl) : $appUrl;
    $data   = array_merge($cs, $inv, [
        'company_name'  => $cs['company_name']  ?? 'OPTMS Tech',
        'company_phone' => $cs['company_phone']  ?? '',
        'company_email' => $cs['company_email']  ?? '',
        'upi'           => $cs['company_upi']    ?? '',
        'bank_details'  => $inv['bank_details']  ?? $cs['default_bank'] ?? '',
        'invoice_link'  => $link,
        'item_list'     => buildItemList($inv['items'] ?? []),
        'amount'        => number_format((float)($inv['grand_total'] ?? 0), 2),
        'type'          => $type,
    ]);
    $subject = replaceVars($tpl['subject'], $data);
    $body    = replaceVars($tpl['body'],    $data);
    $html    = buildEmailHTML($body, $data, null, $appUrl);
    jsonResponse(['success' => true, 'subject' => $subject, 'html' => $html]);
}

// ── Test ────────────────────────────────────────────────────────
if ($action === 'test') {
    $smtp = $input['smtp_host'] ? [
        'host' => $input['smtp_host'], 'port' => (int)($input['smtp_port'] ?? 587),
        'user' => $input['smtp_user'] ?? '', 'pass' => $input['smtp_pass'] ?? '',
        'from' => $input['smtp_from'] ?? $input['smtp_user'] ?? '',
        'name' => $input['smtp_name'] ?? 'OPTMS Tech', 'encryption' => 'tls',
    ] : getSmtpConfig($db, $input['profile'] ?? null);
    if (empty($smtp['host'])) jsonResponse(['success' => false, 'error' => 'SMTP Host required. Fill and save settings first.'], 422);
    $to      = $input['to'] ?? $smtp['user'];
    $subject = '✅ SMTP Test — OPTMS Invoice Manager';
    $body    = "This is a test email from your OPTMS Tech Invoice Manager.\n\nSMTP is working correctly!\n\nHost: {$smtp['host']}\nPort: {$smtp['port']}\nFrom: {$smtp['from']}";
    $html    = buildEmailHTML($body, ['company_name' => $smtp['name'], 'type' => 'test'], null, $appUrl);
    $result  = sendSmtpEmail($smtp, $to, 'Test Recipient', $subject, $html);
    logEmail($db, ['to_email' => $to, 'subject' => $subject, 'status' => $result['success'] ? 'sent' : 'failed', 'error_msg' => $result['error'] ?? null, 'type' => 'test']);
    jsonResponse($result);
}

// ── Main send (invoice/estimate/receipt/reminder) ────────────────
if (in_array($action, ['send', 'send_receipt', 'send_reminder'])) {
    $type   = $input['type'] ?? ($action === 'send_receipt' ? 'receipt' : ($action === 'send_reminder' ? 'reminder' : 'invoice'));
    $invId  = (int)($input['invoice_id'] ?? 0);
    $to     = trim($input['to'] ?? '');
    $toName = trim($input['to_name'] ?? 'Client');

    if (!$to && $invId) {
        $inv = getInvoiceData($db, $invId);
        $to  = $inv['c_email'] ?? $inv['client_email'] ?? '';
        $toName = $inv['client_name'] ?? 'Client';
    }
    if (!$to) jsonResponse(['success' => false, 'error' => 'Recipient email not found. Please add email to client profile.'], 422);

    // Get template
    $tpl = getEmailTemplate($db, $type);

    // Override subject/body if passed directly
    $subjOverride = trim($input['subject'] ?? '');
    $bodyOverride = trim($input['body']    ?? '');

    // Load invoice data
    $inv = $invId ? getInvoiceData($db, $invId) : [];
    $cs  = getCompanySettings($db);
    $link = $invId ? getPortalLink($db, $invId, $appUrl) : $appUrl;

    // Days overdue
    $daysOverdue = 0;
    if (!empty($inv['due_date'])) {
        $due = new DateTime($inv['due_date']);
        $now = new DateTime();
        if ($now > $due) $daysOverdue = (int)$now->diff($due)->days;
    }

    $data = array_merge($cs, $inv, [
        'company_name'  => $cs['company_name']  ?? 'OPTMS Tech',
        'company_phone' => $cs['company_phone']  ?? '',
        'company_email' => $cs['company_email']  ?? '',
        'upi'           => $cs['company_upi']    ?? '',
        'bank_details'  => $inv['bank_details']  ?? $cs['default_bank'] ?? '',
        'invoice_link'  => $link,
        'item_list'     => buildItemList($inv['items'] ?? []),
        'amount'        => number_format((float)($inv['grand_total']  ?? 0), 2),
        'paid_amount'   => number_format((float)($input['paid_amount'] ?? 0), 2),
        'remaining_amount' => (float)($input['remaining'] ?? 0) > 0 ? "Remaining: ₹" . number_format((float)$input['remaining'], 2) : '',
        'days_overdue'  => $daysOverdue,
        'type'          => $type,
    ]);

    $subject  = replaceVars($bodyOverride ? $subjOverride : $tpl['subject'], $data);
    $bodyText = replaceVars($bodyOverride ?: $tpl['body'],    $data);

    // Track token
    $trackToken = bin2hex(random_bytes(8));

    $html = buildEmailHTML($bodyText, $data, $trackToken, $appUrl);

    $smtp = getSmtpConfig($db, $input['profile'] ?? null);
    $opts = [
        'cc_self' => !empty($input['cc_self']),
        'cc'      => $input['cc']  ?? [],
        'bcc'     => $input['bcc'] ?? [],
    ];

    $result = sendSmtpEmail($smtp, $to, $toName, $subject, $html, $opts);

    // Log
    $logId = logEmail($db, [
        'invoice_id'     => $invId ?: null,
        'invoice_number' => $inv['invoice_number'] ?? null,
        'client_name'    => $toName,
        'to_email'       => $to,
        'subject'        => $subject,
        'body_html'      => $html,
        'status'         => $result['success'] ? 'sent' : 'failed',
        'error_msg'      => $result['error'] ?? null,
        'type'           => $type,
        'track_token'    => $result['success'] ? $trackToken : null,
    ]);

    // Activity log
    if ($result['success'] && $invId) {
        try { logActivity($_SESSION['user_id'], 'email_sent', 'invoice', $invId, "Email ({$type}) sent to {$to}"); } catch(Exception $e) {}
    }

    jsonResponse(array_merge($result, ['log_id' => $logId]));
}

jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);