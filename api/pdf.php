<?php
// ================================================================
//  api/pdf.php  — Server-side PDF generation using mPDF
//
//  GET /api/pdf.php?t=TOKEN           → download PDF for invoice
//  GET /api/pdf.php?t=TOKEN&inline=1  → view in browser instead
//
//  No login required — uses same portal token as portal/index.php
//  mPDF must be uploaded to: /vendor/mpdf/  (root of public_html)
// ================================================================

ob_start();
// error_reporting(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db.php';

// ── Locate mPDF via autoloader ────────────────────────────────
// Try multiple possible locations for vendor/autoload.php
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',              // /api/vendor/autoload.php
    dirname(__DIR__) . '/vendor/autoload.php',     // /invoiceoptms/vendor/autoload.php
    dirname(dirname(__DIR__)) . '/vendor/autoload.php', // /public_html/vendor/autoload.php
    $_SERVER['DOCUMENT_ROOT'] . '/invoiceoptms/api/vendor/autoload.php',
    $_SERVER['DOCUMENT_ROOT'] . '/invoiceoptms/vendor/autoload.php',
];

$loaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    // TEMP DEBUG — remove after fixing
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: text/plain');
    // Find vendor directories
    $out = shell_exec('find ' . escapeshellarg(dirname($_SERVER['DOCUMENT_ROOT'])) . ' -name "autoload.php" -path "*/vendor/*" 2>/dev/null');
    echo "Found autoloads:\n" . $out;
    exit;
}

// if (!$loaded || !class_exists('\Mpdf\Mpdf')) {
//     ob_end_clean();
//     http_response_code(500);
//     header('Content-Type: application/json');
//     echo json_encode([
//         'error' => 'mPDF not found. Ensure vendor/autoload.php exists at your server root. Checked: ' . implode(', ', $autoloadPaths)
//     ]);
//     exit;
// }

// ── Helpers ───────────────────────────────────────────────────
function pdf_fmt_date($d) {
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date('d M Y', $ts) : $d;
}
function pdf_fmt_money($n, $sym = '₹') {
    return $sym . number_format((float)$n, 2, '.', ',');
}

// ── Resolve invoice from token ────────────────────────────────
$rawToken  = $_GET['t'] ?? '';
$inline    = !empty($_GET['inline']);
$invoiceId = 0;
$error     = '';

if (!$rawToken) {
    $error = 'Missing token';
} elseif (preg_match('/^[0-9a-f]{32}$/', $rawToken)) {
    // Format A: hex token stored in portal_tokens
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT invoice_id FROM portal_tokens
             WHERE token = :t AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1'
        );
        $stmt->execute([':t' => $rawToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $invoiceId = (int)$row['invoice_id'];
        } else {
            $error = 'Invalid or expired link';
        }
    } catch (Exception $e) {
        $error = 'Server error';
        error_log('pdf.php token lookup: ' . $e->getMessage());
    }
} else {
    // Format B: base64(id:num)
    $decoded = base64_decode(strtr($rawToken, '-_', '+/'), true);
    if ($decoded && strpos($decoded, ':') !== false) {
        $parts     = explode(':', $decoded, 2);
        $invoiceId = (int)$parts[0];
    } else {
        $error = 'Invalid token format';
    }
}

if ($error || $invoiceId <= 0) {
    ob_end_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => $error ?: 'Invalid invoice ID']);
    exit;
}

// ── Fetch all data ─────────────────────────────────────────────
try {
    $db = getDB();

    // Invoice
    $stmt = $db->prepare("
        SELECT i.id AS invoice_id, i.invoice_number, i.issued_date AS issue_date,
               i.due_date, i.grand_total AS amount, i.subtotal,
               i.discount_pct, i.discount_amt, i.gst_amount,
               i.status, i.client_id, i.service_type,
               i.notes, i.terms, i.bank_details, i.currency,
               i.company_logo, i.signature
        FROM invoices i WHERE i.id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $invoiceId]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inv) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode(['error' => 'Invoice not found']);
        exit;
    }

    // Client
    $cStmt = $db->prepare('SELECT name, email, phone, address, gst_number FROM clients WHERE id = :id');
    $cStmt->execute([':id' => $inv['client_id']]);
    $client = $cStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Line items
    $iStmt = $db->prepare('SELECT description, quantity AS qty, rate, gst_rate AS gst, line_total FROM invoice_items WHERE invoice_id = :id ORDER BY sort_order ASC');
    $iStmt->execute([':id' => $inv['invoice_id']]);
    $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

    // Payments
    $pStmt = $db->prepare('SELECT amount, COALESCE(settlement_discount,0) AS settlement_discount, payment_date, method, transaction_id FROM payments WHERE invoice_id = :id ORDER BY payment_date ASC');
    $pStmt->execute([':id' => $inv['invoice_id']]);
    $payments = $pStmt->fetchAll(PDO::FETCH_ASSOC);

    // Settings
    $settings = [];
    $sRows = $db->query("SELECT `key`, value FROM settings")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sRows as $r) $settings[$r['key']] = $r['value'];

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// ── Computed values ────────────────────────────────────────────
$sym         = $inv['currency'] ?: '₹';
$isEstimate  = ($inv['status'] ?? '') === 'Estimate';
$totalAmt    = (float)($inv['amount'] ?? 0);
$totalCash   = array_sum(array_column($payments, 'amount'));
$totalSettle = array_sum(array_column($payments, 'settlement_discount'));
$totalCovered = $totalCash + $totalSettle;

if ($inv['status'] === 'Paid' && $totalCovered < 0.01) $totalCovered = $totalAmt;
$remaining = max(0, $totalAmt - $totalCovered);

// Line item totals
$calcSubtotal = 0; $calcGst = 0;
foreach ($items as $item) {
    $a = (float)$item['qty'] * (float)$item['rate'];
    $calcSubtotal += $a;
    $calcGst      += $a * (float)$item['gst'] / 100;
}
$discountAmt = (float)($inv['discount_amt'] ?? 0);
$discountPct = (float)($inv['discount_pct'] ?? 0);
if ($discountAmt == 0 && $discountPct > 0) $discountAmt = $calcSubtotal * $discountPct / 100;
$discFactor  = $calcSubtotal > 0 ? (1 - $discountAmt / $calcSubtotal) : 1;
$calcGstFinal = $discountAmt > 0 ? $calcGst * $discFactor : $calcGst;
$calcGrand    = $calcSubtotal - $discountAmt + $calcGstFinal;

// Company info
$companyName    = $settings['company_name']    ?? 'OPTMS Tech';
$companyAddress = $settings['company_address'] ?? '';
$companyGST     = $settings['company_gst']     ?? '';
$companyPhone   = $settings['company_phone']   ?? '';
$companyEmail   = $settings['company_email']   ?? '';
$companyLogo    = $inv['company_logo'] ?: ($settings['company_logo'] ?? '');
$companySign    = $inv['signature']    ?: ($settings['company_sign'] ?? '');

// ── Status badge colours ───────────────────────────────────────
$statusColors = [
    'Paid'      => ['bg' => '#E8F5E9', 'fg' => '#2E7D32'],
    'Pending'   => ['bg' => '#FFF8E1', 'fg' => '#F57F17'],
    'Overdue'   => ['bg' => '#FFEBEE', 'fg' => '#C62828'],
    'Partial'   => ['bg' => '#FFF3E0', 'fg' => '#E65100'],
    'Draft'     => ['bg' => '#F5F5F5', 'fg' => '#757575'],
    'Cancelled' => ['bg' => '#EEEEEE', 'fg' => '#616161'],
    'Estimate'  => ['bg' => '#E8EAF6', 'fg' => '#3949AB'],
];
$sc     = $statusColors[$inv['status']] ?? ['bg' => '#F5F5F5', 'fg' => '#888'];
$stBg   = $sc['bg'];
$stFg   = $sc['fg'];
$stLabel = match($inv['status']) {
    'Paid'      => 'PAID',
    'Pending'   => 'PENDING',
    'Overdue'   => 'OVERDUE',
    'Partial'   => 'PARTIALLY PAID',
    'Draft'     => 'DRAFT',
    'Cancelled' => 'CANCELLED',
    'Estimate'  => 'ESTIMATE',
    default     => strtoupper($inv['status'] ?? '')
};

// ── Build HTML for mPDF ───────────────────────────────────────
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; color: #1A1A2E; background: #fff; }

/* Header */
.header { background: #00897B; padding: 20px 24px; color: #fff; margin-bottom: 0; }
.header-inner { display: flex; justify-content: space-between; align-items: flex-start; }
.company-name { font-size: 18px; font-weight: bold; color: #fff; margin-bottom: 3px; }
.company-sub { font-size: 10px; color: rgba(255,255,255,0.8); line-height: 1.6; }
.inv-block { text-align: right; }
.inv-type { font-size: 9px; font-weight: bold; letter-spacing: 1.5px; color: rgba(255,255,255,0.7); text-transform: uppercase; margin-bottom: 4px; }
.inv-num { font-size: 20px; font-weight: bold; color: #fff; font-family: 'DejaVu Sans Mono', monospace; }
.status-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 9px; font-weight: bold; letter-spacing: .8px; margin-top: 6px; background: <?= $stBg ?>; color: <?= $stFg ?>; }

/* Divider */
.header-bar { height: 4px; background: linear-gradient(90deg, #26A69A, #80CBC4); margin-bottom: 16px; }

/* Cards */
.card { border: 1px solid #E5E7EB; border-radius: 8px; margin-bottom: 12px; overflow: hidden; }
.card-head { padding: 9px 14px; background: #F8F9FA; border-bottom: 1px solid #E5E7EB; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; color: #6B7280; }
.card-body { padding: 12px 14px; }

/* Two-column info grid */
.info-grid { width: 100%; }
.info-grid td { padding: 4px 8px 4px 0; vertical-align: top; width: 25%; }
.info-lbl { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; color: #9CA3AF; display: block; margin-bottom: 2px; }
.info-val { font-size: 11px; font-weight: 600; color: #1A1A2E; }

/* Two-column layout for billed-to / issued-by */
.two-col { width: 100%; }
.two-col td { vertical-align: top; width: 50%; padding: 0 8px 0 0; }
.two-col td:last-child { padding-left: 16px; border-left: 1px solid #E5E7EB; }

/* Amount strip */
.amt-strip { width: 100%; border-top: 2px solid #00897B; margin-bottom: 12px; }
.amt-strip td { text-align: center; padding: 12px 8px; border-right: 1px solid #E5E7EB; }
.amt-strip td:last-child { border-right: none; }
.amt-lbl { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; color: #9CA3AF; margin-bottom: 4px; }
.amt-val { font-size: 15px; font-weight: bold; }

/* Line items table */
.items-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.items-table th { padding: 8px 10px; background: #F8F9FA; border-bottom: 2px solid #E5E7EB; text-align: left; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; color: #6B7280; }
.items-table th.r, .items-table td.r { text-align: right; }
.items-table td { padding: 9px 10px; border-bottom: 1px solid #F3F4F6; vertical-align: top; }
.items-table tr:last-child td { border-bottom: none; }
.item-name { font-weight: 600; font-size: 12px; }
.item-type { font-size: 9px; color: #9CA3AF; margin-top: 2px; }
.mono { font-family: 'DejaVu Sans Mono', monospace; }

/* Totals footer */
.tfoot-row { width: 100%; margin-top: 2px; }
.tfoot-row td { padding: 4px 10px; text-align: right; }
.tfoot-lbl { font-size: 10px; color: #6B7280; }
.tfoot-val { font-size: 11px; font-family: 'DejaVu Sans Mono', monospace; min-width: 90px; display: inline-block; }
.tfoot-grand td { padding: 8px 10px; background: #F0FDF4; border-top: 2px solid #E5E7EB; }
.tfoot-grand .tfoot-lbl { font-size: 12px; font-weight: bold; color: #1A1A2E; }
.tfoot-grand .tfoot-val { font-size: 14px; font-weight: bold; color: #00897B; }
.disc-val { color: #C62828; }

/* Payment history */
.pmt-row { padding: 7px 0; border-bottom: 1px solid #F3F4F6; }
.pmt-row:last-child { border-bottom: none; }
.pmt-method { font-weight: 600; font-size: 11px; }
.pmt-date { font-size: 10px; color: #6B7280; }
.pmt-txn { font-size: 9px; color: #9CA3AF; font-family: 'DejaVu Sans Mono', monospace; }
.pmt-amt { font-weight: bold; color: #388E3C; font-family: 'DejaVu Sans Mono', monospace; }

/* Notes / Terms */
.notes-box { font-size: 11px; line-height: 1.6; color: #374151; }
.section-lbl { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; color: #9CA3AF; margin-bottom: 5px; }

/* Watermark for Paid */
.watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font-size: 72px; font-weight: bold; color: rgba(56,142,60,0.08); z-index: -1; letter-spacing: 8px; }

/* Signature */
.signature-block { text-align: right; padding-top: 16px; margin-top: 8px; border-top: 1px dashed #E5E7EB; }
.sig-line { width: 160px; border-bottom: 1.5px solid #9CA3AF; margin-left: auto; margin-bottom: 5px; height: 40px; }
.sig-label { font-size: 9px; color: #9CA3AF; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; }

/* Footer */
.pdf-footer { text-align: center; font-size: 9px; color: #9CA3AF; padding-top: 12px; margin-top: 8px; border-top: 1px solid #E5E7EB; line-height: 1.8; }

/* Balance section */
.balance-bar { background: <?= $remaining > 0 ? '#FFEBEE' : '#E8F5E9' ?>; border: 1px solid <?= $remaining > 0 ? '#FFCDD2' : '#C8E6C9' ?>; border-radius: 8px; padding: 12px 16px; margin-bottom: 12px; }
.balance-lbl { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; color: <?= $remaining > 0 ? '#C62828' : '#2E7D32' ?>; margin-bottom: 4px; }
.balance-val { font-size: 16px; font-weight: bold; color: <?= $remaining > 0 ? '#C62828' : '#2E7D32' ?>; font-family: 'DejaVu Sans Mono', monospace; }

/* Logo */
.logo-img { max-height: 48px; max-width: 140px; }

/* Estimate banner */
.estimate-banner { background: #E8EAF6; border: 1px solid #9FA8DA; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; font-size: 11px; color: #3949AB; }
.estimate-banner strong { color: #1A237E; }
</style>
</head>
<body>

<?php if ($inv['status'] === 'Paid'): ?>
<div class="watermark">PAID</div>
<?php endif; ?>

<!-- Header -->
<div class="header">
  <table class="header-inner" width="100%">
    <tr>
      <td style="vertical-align:top">
        <?php if ($companyLogo): ?>
        <img src="<?= htmlspecialchars($companyLogo) ?>" class="logo-img" alt="Logo"><br><br>
        <?php endif; ?>
        <div class="company-name"><?= htmlspecialchars($companyName) ?></div>
        <div class="company-sub">
          <?php if ($companyAddress): ?><?= nl2br(htmlspecialchars($companyAddress)) ?><br><?php endif; ?>
          <?php if ($companyPhone): ?> <?= htmlspecialchars($companyPhone) ?><?php endif; ?>
          <?php if ($companyEmail): ?>  ✉ <?= htmlspecialchars($companyEmail) ?><?php endif; ?>
          <?php if ($companyGST): ?><br>GSTIN: <?= htmlspecialchars($companyGST) ?><?php endif; ?>
        </div>
      </td>
      <td style="text-align:right;vertical-align:top">
        <div class="inv-type"><?= $isEstimate ? 'Estimate / Quotation' : 'Tax Invoice' ?></div>
        <div class="inv-num"><?= htmlspecialchars($inv['invoice_number']) ?></div>
        <div><span class="status-badge"><?= $stLabel ?></span></div>
      </td>
    </tr>
  </table>
</div>
<div class="header-bar"></div>

<?php if ($isEstimate): ?>
<div class="estimate-banner">
  📋 <strong>This is an Estimate / Quotation — not a final invoice.</strong>
  Valid until: <strong><?= pdf_fmt_date($inv['due_date']) ?></strong>
</div>
<?php endif; ?>

<!-- Amount Summary Strip -->
<table class="amt-strip" width="100%">
  <tr>
    <td>
      <div class="amt-lbl"><?= $isEstimate ? 'Estimated Total' : 'Invoice Total' ?></div>
      <div class="amt-val" style="color:#00897B"><?= pdf_fmt_money($totalAmt, $sym) ?></div>
    </td>
    <?php if (!$isEstimate): ?>
    <td>
      <div class="amt-lbl">Amount Paid</div>
      <div class="amt-val" style="color:#388E3C"><?= pdf_fmt_money($totalCovered, $sym) ?></div>
    </td>
    <td>
      <div class="amt-lbl">Balance Due</div>
      <div class="amt-val" style="color:<?= $remaining > 0 ? '#C62828' : '#388E3C' ?>"><?= pdf_fmt_money($remaining, $sym) ?></div>
    </td>
    <?php else: ?>
    <td>
      <div class="amt-lbl">Issue Date</div>
      <div class="amt-val" style="font-size:12px;color:#374151"><?= pdf_fmt_date($inv['issue_date']) ?></div>
    </td>
    <td>
      <div class="amt-lbl">Valid Until</div>
      <div class="amt-val" style="font-size:12px;color:#374151"><?= pdf_fmt_date($inv['due_date']) ?></div>
    </td>
    <?php endif; ?>
  </tr>
</table>

<!-- Billed To / Issued By -->
<div class="card">
  <table class="two-col" width="100%">
    <tr>
      <td>
        <div class="card-head" style="background:none;border:none;padding:8px 0 6px 0">Billed To</div>
        <div class="info-val" style="font-size:13px"><?= htmlspecialchars($client['name'] ?? '—') ?></div>
        <?php if (!empty($client['email'])): ?><div style="font-size:10px;color:#6B7280;margin-top:3px"><?= htmlspecialchars($client['email']) ?></div><?php endif; ?>
        <?php if (!empty($client['phone'])): ?><div style="font-size:10px;color:#6B7280"><?= htmlspecialchars($client['phone']) ?></div><?php endif; ?>
        <?php if (!empty($client['address'])): ?><div style="font-size:10px;color:#6B7280;margin-top:2px"><?= nl2br(htmlspecialchars($client['address'])) ?></div><?php endif; ?>
        <?php if (!empty($client['gst_number'])): ?><div style="font-size:10px;color:#6B7280">GSTIN: <?= htmlspecialchars($client['gst_number']) ?></div><?php endif; ?>
      </td>
      <td>
        <div class="card-head" style="background:none;border:none;padding:8px 0 6px 0">Invoice Details</div>
        <table width="100%">
          <tr><td class="info-lbl"><?= $isEstimate ? 'Quote #' : 'Invoice #' ?></td><td class="mono" style="font-size:11px;font-weight:600"><?= htmlspecialchars($inv['invoice_number']) ?></td></tr>
          <tr><td class="info-lbl">Service</td><td><?= htmlspecialchars($inv['service_type'] ?? '—') ?></td></tr>
          <tr><td class="info-lbl">Issue Date</td><td><?= pdf_fmt_date($inv['issue_date']) ?></td></tr>
          <tr><td class="info-lbl"><?= $isEstimate ? 'Valid Until' : 'Due Date' ?></td><td><?= pdf_fmt_date($inv['due_date']) ?></td></tr>
        </table>
      </td>
    </tr>
  </table>
</div>

<!-- Line Items -->
<?php if ($items): ?>
<div class="card" style="padding:0">
  <div class="card-head"><?= $isEstimate ? 'Estimate Items' : 'Line Items' ?></div>
  <table class="items-table" width="100%">
    <thead>
      <tr>
        <th style="width:20px">#</th>
        <th>Description</th>
        <th class="r" style="width:50px">Qty</th>
        <th class="r" style="width:80px">Rate</th>
        <th class="r" style="width:80px">Amount</th>
        <th class="r" style="width:50px">GST</th>
        <th class="r" style="width:85px">Total</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $idx => $item):
        $q   = (float)$item['qty'];
        $r   = (float)$item['rate'];
        $g   = (float)$item['gst'];
        $amt = $q * $r;
        $tot = $amt + $amt * $g / 100;
    ?>
    <tr>
      <td style="color:#9CA3AF"><?= $idx + 1 ?></td>
      <td>
        <div class="item-name"><?= htmlspecialchars($item['description']) ?></div>
      </td>
      <td class="r mono"><?= number_format($q, 2) ?></td>
      <td class="r mono"><?= pdf_fmt_money($r, '') ?></td>
      <td class="r mono"><?= pdf_fmt_money($amt, '') ?></td>
      <td class="r"><?= number_format($g, 2) ?>%</td>
      <td class="r mono" style="font-weight:bold"><?= pdf_fmt_money($tot, $sym) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Totals -->
  <table width="100%" style="border-top:1px solid #E5E7EB">
    <tr class="tfoot-row">
      <td style="width:60%"></td>
      <td class="tfoot-lbl">Subtotal</td>
      <td class="tfoot-val r" style="text-align:right"><?= pdf_fmt_money($calcSubtotal, $sym) ?></td>
    </tr>
    <?php if ($discountAmt > 0): ?>
    <tr class="tfoot-row">
      <td></td>
      <td class="tfoot-lbl">Discount<?= $discountPct > 0 ? ' (' . (int)$discountPct . '%)' : '' ?></td>
      <td class="tfoot-val r disc-val" style="text-align:right">− <?= pdf_fmt_money($discountAmt, $sym) ?></td>
    </tr>
    <?php endif; ?>
    <tr class="tfoot-row">
      <td></td>
      <td class="tfoot-lbl">GST</td>
      <td class="tfoot-val r" style="text-align:right"><?= pdf_fmt_money($calcGstFinal, $sym) ?></td>
    </tr>
    <tr class="tfoot-grand">
      <td style="width:60%"></td>
      <td class="tfoot-lbl">Grand Total</td>
      <td class="tfoot-val r" style="text-align:right"><?= pdf_fmt_money($calcGrand, $sym) ?></td>
    </tr>
  </table>
</div>
<?php endif; ?>

<!-- Payment History -->
<?php if ($payments): ?>
<div class="card">
  <div class="card-head">Payment History</div>
  <div class="card-body">
    <?php foreach ($payments as $pmt):
        $pAmt  = (float)$pmt['amount'];
        $pDisc = (float)$pmt['settlement_discount'];
    ?>
    <table width="100%" style="margin-bottom:6px">
      <tr>
        <td>
          <div class="pmt-method"><?= htmlspecialchars($pmt['method'] ?? 'Payment') ?></div>
          <div class="pmt-date"><?= pdf_fmt_date($pmt['payment_date'] ?? '') ?></div>
          <?php if (!empty($pmt['transaction_id'])): ?><div class="pmt-txn">Ref: <?= htmlspecialchars($pmt['transaction_id']) ?></div><?php endif; ?>
        </td>
        <td style="text-align:right">
          <div class="pmt-amt"><?= pdf_fmt_money($pAmt, $sym) ?></div>
          <?php if ($pDisc > 0): ?><div style="font-size:9px;color:#6B7280">Settlement: <?= pdf_fmt_money($pDisc, $sym) ?></div><?php endif; ?>
        </td>
      </tr>
    </table>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Bank Details -->
<?php if (!empty($inv['bank_details'])): ?>
<div class="card">
  <div class="card-head">Bank Details</div>
  <div class="card-body">
    <div class="notes-box mono"><?= nl2br(htmlspecialchars($inv['bank_details'])) ?></div>
  </div>
</div>
<?php endif; ?>

<!-- Notes & Terms -->
<?php if (!empty($inv['notes']) || !empty($inv['terms'])): ?>
<div class="card">
  <div class="card-head">Notes &amp; Terms</div>
  <div class="card-body" style="display:flex;gap:16px">
    <?php if (!empty($inv['notes'])): ?>
    <div style="flex:1">
      <div class="section-lbl">Notes</div>
      <div class="notes-box"><?= nl2br(htmlspecialchars($inv['notes'])) ?></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($inv['terms'])): ?>
    <div style="flex:1">
      <div class="section-lbl">Terms &amp; Conditions</div>
      <div class="notes-box" style="color:#6B7280"><?= nl2br(htmlspecialchars($inv['terms'])) ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Signature -->
<?php if ($companySign): ?>
<div class="signature-block">
  <img src="<?= htmlspecialchars($companySign) ?>" style="max-height:50px;max-width:160px;display:block;margin-left:auto;margin-bottom:4px" alt="Signature">
  <div class="sig-label">Authorised Signatory · <?= htmlspecialchars($companyName) ?></div>
</div>
<?php else: ?>
<div class="signature-block">
  <div class="sig-line"></div>
  <div class="sig-label">Authorised Signatory · <?= htmlspecialchars($companyName) ?></div>
</div>
<?php endif; ?>

<!-- Footer -->
<div class="pdf-footer">
  This is a computer-generated <?= $isEstimate ? 'estimate' : 'invoice' ?> and is valid without a physical signature.<br>
  Generated by <strong><?= htmlspecialchars($companyName) ?></strong> · OPTMS Invoice Manager · <?= date('d M Y') ?>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// ── Generate PDF with mPDF ─────────────────────────────────────
ob_end_clean();

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_left'   => 12,
        'margin_right'  => 12,
        'margin_top'    => 12,
        'margin_bottom' => 16,
        'margin_header' => 0,
        'margin_footer' => 0,
        'default_font'  => 'dejavusans',
    ]);

    $mpdf->SetTitle(($isEstimate ? 'Estimate' : 'Invoice') . ' ' . $inv['invoice_number']);
    $mpdf->SetAuthor($companyName);
    $mpdf->SetCreator('OPTMS Invoice Manager');
    $mpdf->autoScriptToLang  = true;
    $mpdf->autoLangToFont    = true;
    $mpdf->allow_charset_conversion = false;

    $mpdf->WriteHTML($html);

    $filename = ($isEstimate ? 'Estimate' : 'Invoice') . '-' . preg_replace('/[^A-Za-z0-9\-_]/', '-', $inv['invoice_number']) . '.pdf';
    $dest     = $inline ? 'I' : 'D'; // I = inline browser view, D = force download

    $mpdf->Output($filename, $dest);

} catch (Exception $e) {
    error_log('pdf.php mPDF error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'PDF generation failed: ' . $e->getMessage()]);
}