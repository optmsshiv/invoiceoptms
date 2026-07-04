<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_exception_handler(function($e) {
    header('Content-Type: text/plain', true, 500);
    echo get_class($e) . ": " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo $e->getTraceAsString();
    exit;
});
// ================================================================
//  api/pdf.php  — Server-side PDF generation using mPDF
//
//  GET /api/pdf.php?t=TOKEN           → download PDF for invoice
//  GET /api/pdf.php?t=TOKEN&inline=1  → view in browser instead
//
//  No login required — uses same portal token as portal/index.php
//  mPDF must be installed via Composer in: /api/vendor/
// ================================================================

// Never echo errors — any stray output before PDF headers corrupts the binary
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('memory_limit', '128M');

require_once __DIR__ . '/../config/db.php';

// ── Locate mPDF via autoloader ────────────────────────────────
require_once '/home1/edrppymy/public_html/invoiceoptms/vendor/autoload.php';

// ── Helpers ───────────────────────────────────────────────────
function pdf_fmt_date($d) {
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date('d M Y', $ts) : $d;
}
function pdf_fmt_money($n, $sym = '₹') {
    return $sym . number_format((float)$n, 2, '.', ',');
}

// ── Resolve invoice from token OR from invoice_id (internal, authenticated) ──
$rawToken        = $_GET['t'] ?? '';
$invoiceIdParam  = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
$inline          = !empty($_GET['inline']);
$invoiceId       = 0;
$error           = '';

if ($invoiceIdParam > 0) {
    // ── Internal download — used by the app's own "Print / Download PDF" buttons.
    //    Requires an active login session (no public token needed).
    require_once __DIR__ . '/../includes/auth.php';
    requireLogin();
    $invoiceId = $invoiceIdParam;
} elseif (!$rawToken) {
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
        http_response_code(404);
        header('Content-Type: application/json');
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
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// ── Which template was selected for this invoice? ──────────────
// Isolated on purpose: if the column name ever differs, this silently
// falls back to '2' (canonical) rather than breaking the whole PDF.
$templateId = '2';
try {
    $tStmt = $db->prepare('SELECT template_id FROM invoices WHERE id = :id LIMIT 1');
    $tStmt->execute([':id' => $invoiceId]);
    $tRow = $tStmt->fetch(PDO::FETCH_ASSOC);
    if ($tRow && !empty($tRow['template_id'])) {
        $templateId = (string)$tRow['template_id'];
    }
} catch (Exception $e) {
    // column missing or any other issue — keep default '2'
}
$isFormal = ($templateId === 'F');

// ── Computed values ────────────────────────────────────────────
$sym          = $inv['currency'] ?: '₹';
$isEstimate   = ($inv['status'] ?? '') === 'Estimate';
$totalAmt     = (float)($inv['amount'] ?? 0);
$totalCash    = array_sum(array_column($payments, 'amount'));
$totalSettle  = array_sum(array_column($payments, 'settlement_discount'));
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
$discFactor   = $calcSubtotal > 0 ? (1 - $discountAmt / $calcSubtotal) : 1;
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

// ── Top accent bar colour — mirrors the status band used in the in-app preview ──
$accentColors = [
    'Paid'      => '#16A34A',
    'Pending'   => '#00897B',
    'Overdue'   => '#DC2626',
    'Partial'   => '#D97706',
    'Draft'     => '#2563EB',
    'Cancelled' => '#6B7280',
    'Estimate'  => '#3949AB',
];
$accentColor = $accentColors[$inv['status']] ?? '#00897B';

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

/* Header — dark logo sidebar + white content panel (canonical design) */
.hdr-accent { height: 5px; background: <?= $accentColor ?>; }
.hdr-wrap { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
.hdr-logo-cell { background: #2A3580; width: 86px; text-align: center; vertical-align: middle; padding: 18px 8px; }
.hdr-logo-mono { width: 40px; height: 40px; border-radius: 9px; background: rgba(255,255,255,0.15); color: #fff; font-size: 15px; font-weight: bold; text-align: center; line-height: 40px; }
.hdr-content-cell { background: #fff; padding: 18px 26px; vertical-align: top; }
.hdr-co-name { font-size: 16px; font-weight: bold; color: #1A1A2E; }
.hdr-co-sub { font-size: 10px; color: #9CA3AF; margin-top: 2px; line-height: 1.6; }
.hdr-inv-num { font-size: 18px; font-weight: bold; color: #1A1A2E; font-family: 'DejaVu Sans Mono', monospace; text-align: right; }
.hdr-badges { text-align: right; margin-top: 6px; }
.hdr-pill { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 8.5px; font-weight: bold; letter-spacing: .6px; margin-left: 5px; }
.hdr-pill-outline { background: #F3F4F6; color: #4B5563; }
.hdr-divider { height: 1px; background: #F0F1F3; margin: 13px 0 11px; }
.hdr-contact-row { width: 100%; }
.hdr-contact-row td { width: 33.33%; vertical-align: top; }
.hdr-contact-lbl { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: .7px; color: #9CA3AF; margin-bottom: 2px; }
.hdr-contact-val { font-size: 11px; font-weight: 600; color: #1A1A2E; }

/* Cards
   NOTE: no `overflow:hidden` here on purpose — mPDF cannot paginate a block
   with overflow:hidden + border-radius across a page break, so any card
   taller than one page (most often the Line Items card on invoices with
   many items) would get silently clipped at the bottom instead of flowing
   onto the next page. border-radius alone renders fine in mPDF without it. */
.card { border: 1px solid #E5E7EB; border-radius: 8px; margin-bottom: 12px; }
.card-head { padding: 9px 14px; background: #F8F9FA; border-bottom: 1px solid #E5E7EB; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; color: #6B7280; border-radius: 7px 7px 0 0; }
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
.tfoot-grand td:first-child { border-bottom-left-radius: 7px; }
.tfoot-grand td:last-child { border-bottom-right-radius: 7px; }
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

/* ── Formal Letterhead template (template_id = 'F') ──
     Serif, monochrome, ruled — table-based layout for mPDF safety. */
.flh-body { font-family: 'DejaVu Serif', Georgia, 'Times New Roman', serif; color: #1a1a1a; }
.flh-head { text-align: center; padding: 24px 32px 14px; border-bottom: 2px solid #1a1a1a; }
.flh-co-name { font-size: 17px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
.flh-co-meta { font-size: 9px; letter-spacing: .5px; color: #555; line-height: 1.9; margin-top: 5px; font-family: 'DejaVu Sans', Arial, sans-serif; }
.flh-ref { width: 100%; padding: 14px 32px 11px; border-bottom: 0.5px solid #ccc; }
.flh-ref-lbl { font-size: 9.5px; color: #444; line-height: 2; font-family: 'DejaVu Sans', Arial, sans-serif; }
.flh-ref-lbl strong { color: #1a1a1a; font-family: 'DejaVu Sans Mono', monospace; }
.flh-inv-type { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #888; font-family: 'DejaVu Sans', Arial, sans-serif; text-align: right; }
.flh-inv-num { font-size: 18px; font-weight: bold; font-family: 'DejaVu Sans Mono', monospace; letter-spacing: .5px; text-align: right; }
.flh-status-outline { display: inline-block; margin-top: 5px; padding: 2px 10px; border: 1px solid #374151; font-size: 8px; letter-spacing: 2px; text-transform: uppercase; font-family: 'DejaVu Sans', Arial, sans-serif; }
.flh-parties { width: 100%; padding: 12px 32px; border-bottom: 0.5px solid #ccc; }
.flh-parties td { width: 50%; vertical-align: top; padding-right: 20px; }
.flh-parties td:last-child { padding-right: 0; padding-left: 20px; border-left: 0.5px solid #ccc; }
.flh-party-lbl { font-size: 8px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: #888; margin-bottom: 5px; font-family: 'DejaVu Sans', Arial, sans-serif; }
.flh-party-name { font-size: 12px; font-weight: bold; }
.flh-party-line { font-size: 10px; color: #555; line-height: 1.8; margin-top: 2px; font-family: 'DejaVu Sans', Arial, sans-serif; }
.flh-items { width: 100%; border-collapse: collapse; padding: 0 32px; font-family: 'DejaVu Sans', Arial, sans-serif; }
.flh-items th { padding: 6px 6px; font-size: 8px; letter-spacing: 1.2px; text-transform: uppercase; font-weight: bold; text-align: left; border-top: 1.5px solid #1a1a1a; border-bottom: 1px solid #1a1a1a; }
.flh-items th.r, .flh-items td.r { text-align: right; }
.flh-items td { padding: 6px 6px; font-size: 10px; border-bottom: 0.5px solid #ddd; }
.flh-tot-wrap { width: 100%; }
.flh-tot-row td { padding: 4px 0; font-size: 10px; border-bottom: 0.5px solid #ddd; font-family: 'DejaVu Sans', Arial, sans-serif; }
.flh-tot-row td.r { text-align: right; font-family: 'DejaVu Sans Mono', monospace; font-weight: 600; }
.flh-tot-grand td { padding: 7px 0; border-top: 1.5px solid #333; border-bottom: none; font-family: 'DejaVu Sans', Arial, sans-serif; }
.flh-tot-grand .flh-grand-lbl { font-size: 12px; font-weight: bold; }
.flh-tot-grand .flh-grand-val { font-size: 14px; font-weight: bold; font-family: 'DejaVu Sans Mono', monospace; text-align: right; }
.flh-pmt-title { font-size: 8px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: #555; margin-bottom: 7px; font-family: 'DejaVu Sans', Arial, sans-serif; }
.flh-pmt-table { width: 100%; border-collapse: collapse; border-top: 1.5px solid #333; border-bottom: 1px solid #333; font-family: 'DejaVu Sans', Arial, sans-serif; }
.flh-pmt-table th { padding: 5px 6px; font-size: 8px; letter-spacing: 1px; text-transform: uppercase; text-align: left; border-bottom: 1px solid #333; }
.flh-pmt-table th.r, .flh-pmt-table td.r { text-align: right; }
.flh-pmt-table td { padding: 5px 6px; font-size: 10px; border-bottom: 0.5px solid #e5e5e5; }
.flh-section { padding: 10px 32px 0; }
.flh-bank-box { background: #fafafa; border: 0.5px solid #ccc; padding: 10px 12px; font-size: 10px; line-height: 1.7; color: #333; font-family: 'DejaVu Sans', Arial, sans-serif; margin-bottom: 10px; }
.flh-notes-lbl { font-size: 8px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; color: #888; margin-bottom: 4px; font-family: 'DejaVu Sans', Arial, sans-serif; }
.flh-sig-block { text-align: right; padding-top: 30px; }
.flh-sig-line { width: 150px; border-bottom: 1px solid #666; margin-left: auto; margin-bottom: 4px; }
.flh-sig-label { font-size: 9px; color: #888; font-family: 'DejaVu Sans', Arial, sans-serif; }
.flh-footer-rule { margin: 14px 32px 0; padding-top: 10px; border-top: 0.5px solid #ccc; }
.flh-footer-bar { height: 3px; background: #1a1a1a; margin-top: 8px; }
</style>
</head>
<body>

<?php if ($inv['status'] === 'Paid'): ?>
<div class="watermark">PAID</div>
<?php endif; ?>

<?php if ($isFormal):
    // ── Computed values specific to the Formal Letterhead template ──
    $refNo = ($isEstimate ? 'EST' : 'INV') . '/' . date('Y') . '/' .
             str_pad(preg_replace('/[^0-9]/', '', $inv['invoice_number']) ?: '0', 4, '0', STR_PAD_LEFT);
    $lastPaymentDate = $payments ? pdf_fmt_date(end($payments)['payment_date'] ?? '') : '';
    $statusBorders = [
        'Paid' => '#166534', 'Pending' => '#92400E', 'Overdue' => '#991B1B',
        'Draft' => '#374151', 'Partial' => '#D97706', 'Cancelled' => '#6B7280', 'Estimate' => '#1E40AF',
    ];
    $sBdr = $statusBorders[$inv['status']] ?? '#374151';
?>
<div class="flh-body">

  <!-- LETTERHEAD -->
  <div class="flh-head">
    <?php if ($companyLogo): ?>
      <img src="<?= htmlspecialchars($companyLogo) ?>" style="height:44px;max-width:200px;object-fit:contain;display:block;margin:0 auto 8px" alt="Logo">
    <?php else: ?>
      <div class="flh-co-name"><?= htmlspecialchars($companyName) ?></div>
    <?php endif; ?>
    <div class="flh-co-meta">
      <?= $companyAddress ? htmlspecialchars(str_replace("\n", ' · ', $companyAddress)) . ' &nbsp;|&nbsp; ' : '' ?>
      <?= $companyGST ? 'GSTIN: ' . htmlspecialchars($companyGST) . ' &nbsp;|&nbsp; ' : '' ?>
      <?= $companyPhone ? htmlspecialchars($companyPhone) : '' ?>
      <?= $companyEmail ? ' &nbsp;|&nbsp; ' . htmlspecialchars($companyEmail) : '' ?>
    </div>
  </div>

  <!-- REF BLOCK -->
  <table class="flh-ref">
    <tr>
      <td style="width:60%;vertical-align:top">
        <div class="flh-ref-lbl">Ref. No. &nbsp;<strong><?= htmlspecialchars($refNo) ?></strong></div>
        <div class="flh-ref-lbl">Issue Date &nbsp;<strong><?= pdf_fmt_date($inv['issue_date']) ?></strong></div>
        <div class="flh-ref-lbl"><?= $isEstimate ? 'Valid Until' : 'Due Date' ?> &nbsp;<strong><?= pdf_fmt_date($inv['due_date']) ?></strong></div>
        <?php if ($inv['status'] === 'Paid' && $lastPaymentDate): ?>
        <div class="flh-ref-lbl">Paid On &nbsp;<strong style="color:#166534"><?= $lastPaymentDate ?></strong></div>
        <?php endif; ?>
      </td>
      <td style="width:40%;vertical-align:top;text-align:right">
        <div class="flh-inv-type"><?= $isEstimate ? 'Estimate' : 'Invoice' ?></div>
        <div class="flh-inv-num">#<?= htmlspecialchars($inv['invoice_number']) ?></div>
        <div><span class="flh-status-outline" style="color:<?= $sBdr ?>;border-color:<?= $sBdr ?>"><?= $stLabel ?></span></div>
      </td>
    </tr>
  </table>

  <!-- BILLED BY / BILLED TO -->
  <table class="flh-parties">
    <tr>
      <td>
        <div class="flh-party-lbl">Billed By</div>
        <div class="flh-party-name"><?= htmlspecialchars($companyName) ?></div>
        <div class="flh-party-line">
          <?php if ($companyGST): ?><div>GSTIN: <?= htmlspecialchars($companyGST) ?></div><?php endif; ?>
          <?php if ($companyAddress): ?><div><?= htmlspecialchars(str_replace("\n", ', ', $companyAddress)) ?></div><?php endif; ?>
        </div>
      </td>
      <td>
        <div class="flh-party-lbl">Billed To</div>
        <div class="flh-party-name"><?= htmlspecialchars($client['name'] ?? '—') ?></div>
        <div class="flh-party-line">
          <?php if (!empty($client['email'])): ?><div><?= htmlspecialchars($client['email']) ?></div><?php endif; ?>
          <?php if (!empty($client['phone'])): ?><div><?= htmlspecialchars($client['phone']) ?></div><?php endif; ?>
          <?php if (!empty($client['address'])): ?><div><?= nl2br(htmlspecialchars($client['address'])) ?></div><?php endif; ?>
          <?php if (!empty($client['gst_number'])): ?><div>GSTIN: <?= htmlspecialchars($client['gst_number']) ?></div><?php endif; ?>
        </div>
      </td>
    </tr>
  </table>

  <!-- ITEMS TABLE -->
  <div class="flh-section">
    <table class="flh-items">
      <thead>
        <tr>
          <th style="width:22px">#</th>
          <th>Description</th>
          <th class="r" style="width:48px">Qty</th>
          <th class="r" style="width:76px">Rate</th>
          <th class="r" style="width:76px">Amount</th>
          <th class="r" style="width:46px">GST</th>
          <th class="r" style="width:82px">Total</th>
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
        <td><?= $idx + 1 ?></td>
        <td><?= htmlspecialchars($item['description']) ?></td>
        <td class="r"><?= number_format($q, 2) ?></td>
        <td class="r"><?= pdf_fmt_money($r, '') ?></td>
        <td class="r"><?= pdf_fmt_money($amt, '') ?></td>
        <td class="r"><?= number_format($g, 2) ?>%</td>
        <td class="r" style="font-weight:bold"><?= pdf_fmt_money($tot, $sym) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <!-- TOTALS -->
    <table class="flh-tot-wrap">
      <tr><td style="width:65%"></td><td class="flh-tot-row" style="width:35%">
        <table width="100%"><tr class="flh-tot-row"><td>Subtotal</td><td class="r"><?= pdf_fmt_money($calcSubtotal, $sym) ?></td></tr></table>
      </td></tr>
      <?php if ($discountAmt > 0): ?>
      <tr><td></td><td>
        <table width="100%"><tr class="flh-tot-row"><td>Discount<?= $discountPct > 0 ? ' (' . (int)$discountPct . '%)' : '' ?></td><td class="r" style="color:#b91c1c">− <?= pdf_fmt_money($discountAmt, $sym) ?></td></tr></table>
      </td></tr>
      <?php endif; ?>
      <tr><td></td><td>
        <table width="100%"><tr class="flh-tot-row"><td>GST</td><td class="r">+ <?= pdf_fmt_money($calcGstFinal, $sym) ?></td></tr></table>
      </td></tr>
      <tr><td></td><td>
        <table width="100%" class="flh-tot-grand"><tr><td class="flh-grand-lbl">Total Due</td><td class="flh-grand-val"><?= pdf_fmt_money($calcGrand, $sym) ?></td></tr></table>
      </td></tr>
    </table>

    <!-- PAYMENT RECORD -->
    <?php if ($payments && in_array($inv['status'], ['Paid', 'Partial'])): ?>
    <div style="margin-top:16px;padding-top:12px;border-top:1px solid #ccc">
      <div class="flh-pmt-title"><?= $inv['status'] === 'Paid' ? 'Payment Record' : 'Partial Payment Record' ?></div>
      <table class="flh-pmt-table">
        <thead><tr><th>Date</th><th>Mode</th><th>Ref / Txn ID</th><th class="r">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $pmt): ?>
        <tr>
          <td><?= pdf_fmt_date($pmt['payment_date'] ?? '') ?></td>
          <td><?= htmlspecialchars($pmt['method'] ?? '—') ?></td>
          <td style="font-family:'DejaVu Sans Mono',monospace"><?= htmlspecialchars($pmt['transaction_id'] ?? '—') ?></td>
          <td class="r" style="font-weight:bold;font-family:'DejaVu Sans Mono',monospace"><?= pdf_fmt_money((float)$pmt['amount'], $sym) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($inv['status'] === 'Partial'): ?>
      <div style="margin-top:6px;font-size:10px;font-family:'DejaVu Sans',Arial,sans-serif;color:#92400E">
        Balance outstanding: <strong><?= pdf_fmt_money($remaining, $sym) ?></strong>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- BANK / NOTES / TERMS / SIGNATURE -->
  <table class="flh-section" width="100%">
    <tr>
      <td style="width:60%;vertical-align:top;padding-right:24px">
        <?php if (!empty($inv['bank_details'])): ?>
        <div class="flh-bank-box"><?= nl2br(htmlspecialchars($inv['bank_details'])) ?></div>
        <?php endif; ?>
        <?php if (!empty($inv['notes'])): ?>
        <div class="flh-notes-lbl">Notes</div>
        <div style="font-size:10px;color:#555;line-height:1.7;font-family:'DejaVu Sans',Arial,sans-serif;margin-bottom:10px"><?= nl2br(htmlspecialchars($inv['notes'])) ?></div>
        <?php endif; ?>
        <?php if (!empty($inv['terms'])): ?>
        <div class="flh-notes-lbl">Terms &amp; Conditions</div>
        <div style="font-size:9.5px;color:#888;line-height:1.7;font-family:'DejaVu Sans',Arial,sans-serif"><?= nl2br(htmlspecialchars($inv['terms'])) ?></div>
        <?php endif; ?>
      </td>
      <td style="width:40%;vertical-align:bottom">
        <?php if ($companySign): ?>
        <div class="flh-sig-block">
          <img src="<?= htmlspecialchars($companySign) ?>" style="max-height:42px;max-width:140px;display:block;margin-left:auto;margin-bottom:4px" alt="Signature">
          <div class="flh-sig-label">Authorised Signatory · <?= htmlspecialchars($companyName) ?></div>
        </div>
        <?php else: ?>
        <div class="flh-sig-block">
          <div class="flh-sig-line"></div>
          <div class="flh-sig-label">Authorised Signatory · <?= htmlspecialchars($companyName) ?></div>
        </div>
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <!-- FOOTER -->
  <div class="flh-footer-rule">
    <div style="font-size:9px;color:#999;font-family:'DejaVu Sans',Arial,sans-serif">
      This is a computer-generated <?= $isEstimate ? 'estimate' : 'invoice' ?> and is valid without a physical signature. ·
      Generated by <strong><?= htmlspecialchars($companyName) ?></strong> · OPTMS Invoice Manager · <?= date('d M Y') ?>
    </div>
  </div>
  <div class="flh-footer-bar"></div>

</div>
<?php else: ?>

<!-- Header: accent strip + dark logo sidebar + white content panel -->
<div class="hdr-accent"></div>
<table class="hdr-wrap" cellpadding="0" cellspacing="0">
  <tr>
    <td class="hdr-logo-cell">
      <?php if ($companyLogo): ?>
        <img src="<?= htmlspecialchars($companyLogo) ?>" style="max-width:54px;max-height:44px;display:inline-block" alt="Logo">
      <?php else: ?>
        <div class="hdr-logo-mono"><?= htmlspecialchars(strtoupper(substr($companyName, 0, 2))) ?></div>
      <?php endif; ?>
    </td>
    <td class="hdr-content-cell">
      <table width="100%">
        <tr>
          <td style="vertical-align:top">
            <div class="hdr-co-name"><?= htmlspecialchars($companyName) ?></div>
            <?php if ($companyAddress): ?><div class="hdr-co-sub"><?= htmlspecialchars(str_replace("\n", ', ', $companyAddress)) ?></div><?php endif; ?>
          </td>
          <td style="vertical-align:top;text-align:right;white-space:nowrap">
            <div class="hdr-inv-num"><?= htmlspecialchars($inv['invoice_number']) ?></div>
            <div class="hdr-badges">
              <span class="hdr-pill hdr-pill-outline"><?= $isEstimate ? 'ESTIMATE' : 'TAX INVOICE' ?></span>
              <span class="hdr-pill" style="background:<?= $stBg ?>;color:<?= $stFg ?>"><?= $stLabel ?></span>
            </div>
          </td>
        </tr>
      </table>
      <div class="hdr-divider"></div>
      <table class="hdr-contact-row">
        <tr>
          <?php if ($companyPhone): ?>
          <td><div class="hdr-contact-lbl">Phone</div><div class="hdr-contact-val"><?= htmlspecialchars($companyPhone) ?></div></td>
          <?php endif; ?>
          <?php if ($companyEmail): ?>
          <td><div class="hdr-contact-lbl">Email</div><div class="hdr-contact-val"><?= htmlspecialchars($companyEmail) ?></div></td>
          <?php endif; ?>
          <?php if ($companyGST): ?>
          <td><div class="hdr-contact-lbl">GSTIN</div><div class="hdr-contact-val mono"><?= htmlspecialchars($companyGST) ?></div></td>
          <?php endif; ?>
        </tr>
      </table>
    </td>
  </tr>
</table>

<?php if ($isEstimate): ?>
<div class="estimate-banner">
  <strong>This is an Estimate / Quotation — not a final invoice.</strong>
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

<!-- Billed To / Invoice Details -->
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
      <td class="tfoot-val r disc-val" style="text-align:right">- <?= pdf_fmt_money($discountAmt, $sym) ?></td>
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

<?php endif; ?>

</body>
</html>
<?php
// Capture the HTML, then flush ALL output buffers before sending PDF binary
$html = ob_get_clean();
while (ob_get_level() > 0) {
    ob_end_clean();
}

// ── Generate PDF with mPDF ─────────────────────────────────────
try {
    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'tempDir'       => '/tmp',
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
    $mpdf->autoScriptToLang         = true;
    $mpdf->autoLangToFont           = true;
    $mpdf->allow_charset_conversion = false;

    $mpdf->WriteHTML($html);

    $filename = ($isEstimate ? 'Estimate' : 'Invoice') . '-' . preg_replace('/[^A-Za-z0-9\-_]/', '-', $inv['invoice_number']) . '.pdf';
    $dest     = $inline ? 'I' : 'D'; // I = inline browser view, D = force download

    $mpdf->Output($filename, $dest);

} catch (\Throwable $e) {
    error_log('pdf.php mPDF error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'PDF generation failed: ' . $e->getMessage()]);
}