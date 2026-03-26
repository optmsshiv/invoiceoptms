<?php
// ================================================================
//  portal/index.php  — Client Portal (Public, No Login Required)
//  URL: https://invcs.optms.co.in/portal?t=TOKEN
//  Shows invoice details to the client via a secure token link...
//  - No authentication required, but token must be valid and not expired.
// ================================================================

// NO auth required — this is intentionally public
require_once __DIR__ . '/../config/db.php';

$token = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['t'] ?? '');
$error = '';
$inv   = null;
$client= [];
$payments = [];
$settings = [];

if (!$token) {
    $error = 'Invalid or missing link. Please ask your service provider for a valid invoice link.';
} else {
    try {
        $db = getDB();

        // Auto-create table in case migration not run yet
        $db->exec("CREATE TABLE IF NOT EXISTS `portal_tokens` (
            `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `invoice_id`  INT UNSIGNED NOT NULL,
            `token`       VARCHAR(64)  NOT NULL,
            `views`       INT UNSIGNED NOT NULL DEFAULT 0,
            `last_viewed` DATETIME     NULL,
            `expires_at`  DATETIME     NULL,
            `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_portal_invoice` (`invoice_id`),
            UNIQUE KEY `uk_portal_token`   (`token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Load token + invoice in one query
        $stmt = $db->prepare("
            SELECT pt.invoice_id, pt.views, pt.expires_at,
                   i.invoice_number, i.issue_date, i.due_date, i.amount,
                   i.status, i.client_id, i.service_type, i.notes,
                   i.terms, i.bank_details, i.gst_rate, i.discount, i.currency,
                   i.items_json
            FROM portal_tokens pt
            JOIN invoices i ON i.id = pt.invoice_id
            WHERE pt.token = :token
              AND (pt.expires_at IS NULL OR pt.expires_at > NOW())
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inv) {
            $error = 'This link is invalid or has expired. Please contact your service provider.';
        } else {
            // Bump view counter
            $db->prepare('UPDATE portal_tokens SET views = views + 1, last_viewed = NOW() WHERE token = :t')
               ->execute([':t' => $token]);

            // Client details
            $cStmt = $db->prepare('SELECT name, email, phone, address FROM clients WHERE id = :id');
            $cStmt->execute([':id' => $inv['client_id']]);
            $client = $cStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Payments
            $pStmt = $db->prepare('SELECT amount, payment_date, method, transaction_id, notes FROM payments WHERE invoice_id = :id ORDER BY payment_date ASC');
            $pStmt->execute([':id' => $inv['invoice_id']]);
            $payments = $pStmt->fetchAll(PDO::FETCH_ASSOC);

            // Company settings
            $sStmt = $db->query("SELECT `key`, value FROM settings");
            foreach ($sStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $settings[$r['key']] = $r['value'];
        }
    } catch (Exception $e) {
        error_log('portal/index.php error: ' . $e->getMessage());
        $error = 'A server error occurred. Please try again later.';
    }
}

// ── Helpers ──
function fmt_inr($n, $sym = '₹') {
    return $sym . number_format((float)$n, 2, '.', ',');
}
function status_color($s) {
    return match($s) {
        'Paid'      => '#388E3C',
        'Pending'   => '#F9A825',
        'Overdue'   => '#C62828',
        'Partial'   => '#E65100',
        'Draft'     => '#9E9E9E',
        'Cancelled' => '#757575',
        default     => '#888'
    };
}

// Calc totals
$totalPaid = array_sum(array_column($payments, 'amount'));
$totalAmt  = (float)($inv['amount'] ?? 0);
$remaining = max(0, $totalAmt - $totalPaid);
$pct       = $totalAmt > 0 ? min(100, round($totalPaid / $totalAmt * 100)) : 0;

$companyName    = $settings['company_name']    ?? 'OPTMS Tech';
$companyAddress = $settings['company_address'] ?? '';
$companyGST     = $settings['company_gst']     ?? '';
$companyEmail   = $settings['company_email']   ?? '';
$companyPhone   = $settings['company_phone']   ?? '';
$companyUPI     = $settings['company_upi']     ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice <?= htmlspecialchars($inv['invoice_number'] ?? '') ?> — <?= htmlspecialchars($companyName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--teal:#00897B;--teal-l:#4DB6AC;--teal-bg:#E0F2F1;--green:#388E3C;--green-bg:#E8F5E9;--amber:#F9A825;--amber-bg:#FFF8E1;--red:#C62828;--red-bg:#FFEBEE;--orange:#E65100;--muted:#6B7280;--border:#E5E7EB;--card:#fff;--bg:#F5F6FA;--text:#1A1A2E;--mono:'JetBrains Mono',monospace;--font:'Public Sans',-apple-system,sans-serif}
html{font-size:15px;background:var(--bg)}
body{font-family:var(--font);color:var(--text);min-height:100vh;padding:20px 16px 60px}
.wrap{max-width:700px;margin:0 auto}

/* Top bar */
.portal-header{background:linear-gradient(135deg,var(--teal),#00695C);border-radius:14px;padding:20px 24px;color:#fff;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.portal-brand{display:flex;align-items:center;gap:12px}
.portal-logo{width:42px;height:42px;border-radius:10px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;color:#fff;flex-shrink:0}
.portal-company{font-size:16px;font-weight:800;line-height:1.2}
.portal-tagline{font-size:11px;opacity:.75;margin-top:2px}
.portal-badge{display:flex;flex-direction:column;align-items:flex-end;gap:4px}
.portal-inv-num{font-size:18px;font-weight:800;font-family:var(--mono);letter-spacing:.5px}
.portal-status{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3)}

/* Cards */
.card{background:var(--card);border-radius:12px;border:1px solid var(--border);box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:16px;overflow:hidden}
.card-head{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:var(--text)}
.card-head i{color:var(--teal);font-size:13px}
.card-body{padding:16px 18px}

/* Info grid */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.info-item label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);display:block;margin-bottom:3px}
.info-item span{font-size:13px;font-weight:600;color:var(--text)}

/* Amount strip */
.amount-strip{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1px;background:var(--border)}
.amt-cell{background:var(--card);padding:14px 16px;text-align:center}
.amt-cell .lbl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:4px}
.amt-cell .val{font-size:16px;font-weight:800;font-family:var(--mono)}
.amt-cell.green .val{color:var(--green)}
.amt-cell.red   .val{color:var(--red)}
.amt-cell.teal  .val{color:var(--teal)}

/* Progress bar */
.progress-wrap{padding:12px 18px;background:var(--bg);border-top:1px solid var(--border)}
.progress-bar{height:8px;background:#E5E7EB;border-radius:4px;overflow:hidden;margin-bottom:6px}
.progress-fill{height:100%;background:linear-gradient(90deg,#43A047,#66BB6A);border-radius:4px;transition:width .6s ease}
.progress-meta{display:flex;justify-content:space-between;font-size:11px;color:var(--muted)}

/* Payment history */
.pmt-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px}
.pmt-row:last-child{border:none}
.pmt-icon{width:32px;height:32px;border-radius:8px;background:var(--green-bg);color:var(--green);display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.pmt-info{flex:1;min-width:0}
.pmt-method{font-weight:600;font-size:12px}
.pmt-date{font-size:11px;color:var(--muted);margin-top:1px}
.pmt-amt{font-family:var(--mono);font-weight:700;color:var(--green);font-size:14px;white-space:nowrap}
.pmt-txn{font-size:10px;color:var(--muted);margin-top:2px;font-family:var(--mono)}

/* Items table */
table{width:100%;border-collapse:collapse;font-size:13px}
th{padding:9px 12px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);background:var(--bg);border-bottom:2px solid var(--border)}
td{padding:9px 12px;border-bottom:1px solid var(--border)}
tr:last-child td{border:none}
.tr{text-align:right}

/* UPI section */
.upi-box{background:var(--teal-bg);border-radius:10px;padding:14px 16px;display:flex;align-items:center;gap:12px}
.upi-icon{width:36px;height:36px;border-radius:8px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.upi-id{font-family:var(--mono);font-size:14px;font-weight:700;color:var(--teal)}
.copy-btn{margin-left:auto;padding:6px 12px;background:var(--teal);color:#fff;border:none;border-radius:7px;cursor:pointer;font-size:12px;font-weight:600;font-family:var(--font)}

/* Error */
.error-box{background:#fff;border-radius:14px;border:2px solid var(--red-bg);padding:40px;text-align:center;max-width:480px;margin:60px auto}

/* Footer */
.portal-footer{text-align:center;margin-top:24px;font-size:11px;color:var(--muted);line-height:1.8}

@media(max-width:480px){
  .info-grid{grid-template-columns:1fr}
  .amount-strip{grid-template-columns:1fr}
}
</style>
</head>
<body>

<div class="wrap">

<?php if ($error): ?>
<!-- ── Error state ── -->
<div class="error-box">
  <i class="fas fa-link-slash" style="font-size:40px;color:#E5E7EB;margin-bottom:16px;display:block"></i>
  <h2 style="font-size:18px;margin-bottom:10px;color:var(--text)">Link Unavailable</h2>
  <p style="color:var(--muted);font-size:13px;line-height:1.6"><?= htmlspecialchars($error) ?></p>
  <div style="margin-top:20px;font-size:12px;color:var(--muted)">
    Powered by <strong><?= htmlspecialchars($companyName) ?></strong>
  </div>
</div>

<?php else: ?>

<!-- ── Portal header ── -->
<div class="portal-header">
  <div class="portal-brand">
    <div class="portal-logo"><?= strtoupper(substr($companyName,0,2)) ?></div>
    <div>
      <div class="portal-company"><?= htmlspecialchars($companyName) ?></div>
      <div class="portal-tagline"><?= htmlspecialchars($companyAddress) ?></div>
    </div>
  </div>
  <div class="portal-badge">
    <div class="portal-inv-num"><?= htmlspecialchars($inv['invoice_number'] ?? '') ?></div>
    <div class="portal-status">
      <span style="width:6px;height:6px;border-radius:50%;background:<?= status_color($inv['status']) ?>"></span>
      <?= htmlspecialchars($inv['status'] ?? '') ?>
    </div>
  </div>
</div>

<!-- ── Amount summary ── -->
<div class="card" style="margin-bottom:16px;overflow:hidden">
  <div class="amount-strip">
    <div class="amt-cell teal">
      <div class="lbl">Invoice Total</div>
      <div class="val"><?= fmt_inr($totalAmt, $inv['currency'] ?: '₹') ?></div>
    </div>
    <div class="amt-cell green">
      <div class="lbl">Amount Paid</div>
      <div class="val"><?= fmt_inr($totalPaid, $inv['currency'] ?: '₹') ?></div>
    </div>
    <div class="amt-cell <?= $remaining > 0 ? 'red' : 'green' ?>">
      <div class="lbl"><?= $remaining > 0 ? 'Balance Due' : 'Fully Paid' ?></div>
      <div class="val"><?= $remaining > 0 ? fmt_inr($remaining, $inv['currency'] ?: '₹') : '✓ Cleared' ?></div>
    </div>
  </div>
  <?php if ($totalPaid > 0 || $totalAmt > 0): ?>
  <div class="progress-wrap">
    <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
    <div class="progress-meta"><span><?= $pct ?>% paid</span><span><?= count($payments) ?> payment<?= count($payments)!==1?'s':'' ?></span></div>
  </div>
  <?php endif; ?>
</div>

<!-- ── Invoice details ── -->
<div class="card">
  <div class="card-head"><i class="fas fa-file-invoice"></i> Invoice Details</div>
  <div class="card-body">
    <div class="info-grid">
      <div class="info-item"><label>Invoice Number</label><span style="font-family:var(--mono)"><?= htmlspecialchars($inv['invoice_number'] ?? '') ?></span></div>
      <div class="info-item"><label>Service</label><span><?= htmlspecialchars($inv['service_type'] ?? '—') ?></span></div>
      <div class="info-item"><label>Issue Date</label><span><?= htmlspecialchars($inv['issue_date'] ?? '—') ?></span></div>
      <div class="info-item"><label>Due Date</label><span style="color:<?= $inv['status']==='Overdue'?'var(--red)':'inherit' ?>"><?= htmlspecialchars($inv['due_date'] ?? '—') ?></span></div>
      <?php if (!empty($inv['gst_rate'])): ?>
      <div class="info-item"><label>GST Rate</label><span><?= htmlspecialchars($inv['gst_rate']) ?>%</span></div>
      <?php endif; ?>
      <?php if (!empty($inv['discount'])): ?>
      <div class="info-item"><label>Discount</label><span><?= htmlspecialchars($inv['discount']) ?></span></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Client info ── -->
<?php if ($client): ?>
<div class="card">
  <div class="card-head"><i class="fas fa-user"></i> Billed To</div>
  <div class="card-body">
    <div class="info-grid">
      <div class="info-item"><label>Name</label><span><?= htmlspecialchars($client['name'] ?? '—') ?></span></div>
      <?php if (!empty($client['email'])): ?>
      <div class="info-item"><label>Email</label><span><?= htmlspecialchars($client['email']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($client['phone'])): ?>
      <div class="info-item"><label>Phone</label><span><?= htmlspecialchars($client['phone']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($client['address'])): ?>
      <div class="info-item" style="grid-column:1/-1"><label>Address</label><span><?= nl2br(htmlspecialchars($client['address'])) ?></span></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Line items ── -->
<?php
$items = [];
if (!empty($inv['items_json'])) {
    $items = json_decode($inv['items_json'], true) ?: [];
}
if ($items):
?>
<div class="card">
  <div class="card-head"><i class="fas fa-list"></i> Line Items</div>
  <table>
    <thead><tr>
      <th>#</th><th>Description</th><th class="tr">Qty</th><th class="tr">Rate</th><th class="tr">GST</th><th class="tr">Amount</th>
    </tr></thead>
    <tbody>
    <?php foreach ($items as $i => $item): ?>
      <tr>
        <td style="color:var(--muted)"><?= $i+1 ?></td>
        <td><strong><?= htmlspecialchars($item['name'] ?? $item['desc'] ?? '') ?></strong><?php if (!empty($item['type'])): ?><br><span style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($item['type']) ?></span><?php endif; ?></td>
        <td class="tr"><?= htmlspecialchars($item['qty'] ?? 1) ?></td>
        <td class="tr" style="font-family:var(--mono)"><?= fmt_inr($item['rate'] ?? 0) ?></td>
        <td class="tr"><?= htmlspecialchars($item['gst'] ?? '') ?>%</td>
        <td class="tr" style="font-family:var(--mono);font-weight:700"><?= fmt_inr($item['total'] ?? $item['amount'] ?? 0) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="background:var(--bg)"><td colspan="5" style="text-align:right;font-weight:700;padding:10px 12px">Grand Total</td><td class="tr" style="font-family:var(--mono);font-size:15px;font-weight:800;color:var(--teal);padding:10px 12px"><?= fmt_inr($totalAmt, $inv['currency'] ?: '₹') ?></td></tr>
    </tfoot>
  </table>
</div>
<?php endif; ?>

<!-- ── Payment history ── -->
<?php if ($payments): ?>
<div class="card">
  <div class="card-head"><i class="fas fa-receipt"></i> Payment History</div>
  <div class="card-body" style="padding:0 18px">
    <?php foreach ($payments as $p): ?>
    <div class="pmt-row">
      <div class="pmt-icon"><i class="fas fa-check"></i></div>
      <div class="pmt-info">
        <div class="pmt-method"><?= htmlspecialchars($p['method'] ?? 'Payment') ?></div>
        <div class="pmt-date"><?= htmlspecialchars($p['payment_date'] ?? '') ?></div>
        <?php if (!empty($p['transaction_id'])): ?>
        <div class="pmt-txn">Ref: <?= htmlspecialchars($p['transaction_id']) ?></div>
        <?php endif; ?>
      </div>
      <div class="pmt-amt"><?= fmt_inr($p['amount'], $inv['currency'] ?: '₹') ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Payment info / UPI ── -->
<?php if ($remaining > 0 && $companyUPI): ?>
<div class="card">
  <div class="card-head"><i class="fas fa-mobile-alt"></i> Pay Now</div>
  <div class="card-body">
    <div style="margin-bottom:10px;font-size:12px;color:var(--muted)">Pay the remaining <strong style="color:var(--red)"><?= fmt_inr($remaining, $inv['currency'] ?: '₹') ?></strong> using UPI:</div>
    <div class="upi-box">
      <div class="upi-icon"><i class="fas fa-rupee-sign"></i></div>
      <div>
        <div style="font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:2px">UPI ID</div>
        <div class="upi-id" id="upiId"><?= htmlspecialchars($companyUPI) ?></div>
      </div>
      <button class="copy-btn" onclick="copyUPI()"><i class="fas fa-copy"></i> Copy</button>
    </div>
    <?php if (!empty($settings['company_qr'])): ?>
    <div style="text-align:center;margin-top:12px">
      <img src="<?= htmlspecialchars($settings['company_qr']) ?>" alt="QR Code" style="width:160px;height:160px;border-radius:10px;border:1px solid var(--border)">
      <div style="font-size:11px;color:var(--muted);margin-top:6px">Scan to pay</div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Notes / T&C ── -->
<?php if (!empty($inv['notes']) || !empty($inv['terms'])): ?>
<div class="card">
  <div class="card-head"><i class="fas fa-sticky-note"></i> Notes & Terms</div>
  <div class="card-body">
    <?php if (!empty($inv['notes'])): ?>
    <div style="margin-bottom:12px">
      <div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Notes</div>
      <div style="font-size:13px;color:var(--text);line-height:1.6"><?= nl2br(htmlspecialchars($inv['notes'])) ?></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($inv['terms'])): ?>
    <div>
      <div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Terms & Conditions</div>
      <div style="font-size:12px;color:var(--muted);line-height:1.6"><?= nl2br(htmlspecialchars($inv['terms'])) ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Company info ── -->
<div class="card">
  <div class="card-head"><i class="fas fa-building"></i> From</div>
  <div class="card-body">
    <div class="info-grid">
      <div class="info-item"><label>Company</label><span style="font-weight:700"><?= htmlspecialchars($companyName) ?></span></div>
      <?php if ($companyGST): ?><div class="info-item"><label>GSTIN</label><span style="font-family:var(--mono)"><?= htmlspecialchars($companyGST) ?></span></div><?php endif; ?>
      <?php if ($companyPhone): ?><div class="info-item"><label>Phone</label><span><?= htmlspecialchars($companyPhone) ?></span></div><?php endif; ?>
      <?php if ($companyEmail): ?><div class="info-item"><label>Email</label><span><?= htmlspecialchars($companyEmail) ?></span></div><?php endif; ?>
      <?php if ($companyAddress): ?><div class="info-item" style="grid-column:1/-1"><label>Address</label><span><?= nl2br(htmlspecialchars($companyAddress)) ?></span></div><?php endif; ?>
    </div>
  </div>
</div>

<div class="portal-footer">
  <div>This is a secure read-only view of your invoice.</div>
  <div>Generated by <strong><?= htmlspecialchars($companyName) ?></strong> · Powered by OPTMS Invoice Manager</div>
  <div style="margin-top:6px;font-size:10px">View count: <?= (int)$inv['views'] + 1 ?></div>
</div>

<?php endif; ?>
</div>

<script>
function copyUPI() {
  const id = document.getElementById('upiId')?.textContent?.trim();
  if (!id) return;
  navigator.clipboard.writeText(id).then(() => {
    const btn = document.querySelector('.copy-btn');
    if (btn) { btn.innerHTML = '<i class="fas fa-check"></i> Copied!'; btn.style.background='#388E3C'; setTimeout(()=>{ btn.innerHTML='<i class="fas fa-copy"></i> Copy'; btn.style.background=''; }, 2000); }
  }).catch(() => {
    const ta = document.createElement('textarea'); ta.value = id;
    document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
    alert('UPI ID copied: ' + id);
  });
}
</script>
</body>
</html>