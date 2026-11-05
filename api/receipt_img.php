<?php
// ═══════════════════════════════════════════════════════
//  API: /api/receipt_img.php?payment_id=N[&force=1]
//  Builds (and caches) a PNG payment receipt and returns its public URL,
//  ready to be used as the image header of a WhatsApp template message.
//  A receipt is a snapshot of the moment it was first generated.
// ═══════════════════════════════════════════════════════
date_default_timezone_set('Asia/Kolkata');
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/receipt_render.php';
requireLogin();
$db = getDB();

$pid = (int)($_GET['payment_id'] ?? 0);
if (!$pid) jsonResponse(['error' => 'payment_id required'], 400);

// ── storage: uploads/receipts/ (private-by-obscurity: unguessable file names, no directory listing) ──
$dir = __DIR__ . '/../uploads/receipts';
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) jsonResponse(['error' => 'Cannot create uploads/receipts folder — check folder permissions'], 500);
if (!is_file("$dir/.htaccess")) @file_put_contents("$dir/.htaccess", "Options -Indexes\n<FilesMatch \"\\.(php|phtml|php5)$\">\n  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n  <IfModule !mod_authz_core.c>\n    Deny from all\n  </IfModule>\n</FilesMatch>\n");
if (!is_file("$dir/index.html")) @file_put_contents("$dir/index.html", '');
$secretFile = "$dir/secret.php";                         // random key; a .php file can't be read over HTTP
if (!is_file($secretFile)) @file_put_contents($secretFile, "<?php return '" . bin2hex(random_bytes(24)) . "';\n");
$secret = is_file($secretFile) ? (string)(include $secretFile) : '';
if ($secret === '') jsonResponse(['error' => 'Cannot create receipt key — check folder permissions'], 500);
$token = substr(hash_hmac('sha256', 'receipt|' . $pid, $secret), 0, 24);
$fname = "rcpt-$pid-$token.png";
$path  = "$dir/$fname";

$scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$base   = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
$url    = "$scheme://{$_SERVER['HTTP_HOST']}$base/uploads/receipts/$fname";

if (is_file($path) && empty($_GET['force'])) {
  jsonResponse(['success' => true, 'url' => $url, 'cached' => true, 'receipt_no' => 'RCPT-' . $pid]);
}

// ── gather data ──────────────────────────────────────────
$s = $db->prepare('SELECT * FROM payments WHERE id = ?'); $s->execute([$pid]);
$p = $s->fetch(PDO::FETCH_ASSOC);
if (!$p) jsonResponse(['error' => 'Payment not found'], 404);

$inv = null;
if (!empty($p['invoice_id'])) {
  try { $q = $db->prepare('SELECT id, invoice_number, grand_total, currency, plan_id FROM invoices WHERE id = ?'); $q->execute([(int)$p['invoice_id']]); $inv = $q->fetch(PDO::FETCH_ASSOC); }
  catch (Exception $e) { $q = $db->prepare('SELECT id, invoice_number, grand_total, currency FROM invoices WHERE id = ?'); $q->execute([(int)$p['invoice_id']]); $inv = $q->fetch(PDO::FETCH_ASSOC); }
}
$set = [];
try { foreach ($db->query('SELECT `key`, value FROM settings')->fetchAll(PDO::FETCH_ASSOC) as $r) $set[$r['key']] = $r['value']; } catch (Exception $e) {}

$balance = 0.0;
if ($inv) {
  $c = $db->prepare('SELECT COALESCE(SUM(amount + settlement_discount),0) FROM payments WHERE invoice_id = ? AND invoice_deleted = 0 AND id <= ?');
  $c->execute([(int)$inv['id'], $pid]);
  $balance = max(0, (float)$inv['grand_total'] - (float)$c->fetchColumn());
}
$partial = $balance > 0.009;

// installment plan line (only when the invoice belongs to a plan)
$planTitle = ''; $planLine = '';
if ($inv && !empty($inv['plan_id'])) {
  try {
    require_once __DIR__ . '/../includes/plans_schema.php';
    $pl = $db->prepare('SELECT * FROM payment_plans WHERE id = ?'); $pl->execute([(int)$inv['plan_id']]);
    $plan = $pl->fetch(PDO::FETCH_ASSOC);
    if ($plan) {
      $st = planStats($db, $plan);
      $cur = ($inv['currency'] ?: '₹');
      $planTitle = $st['service_name'] . ' — installment plan';
      if ($st['remaining'] < 0.01)  $planLine = 'Plan complete · all ' . $cur . rcpt_inr($st['total_amount']) . ' paid';
      elseif ($partial)             $planLine = $cur . rcpt_inr($st['paid']) . ' of ' . $cur . rcpt_inr($st['total_amount']) . ' paid · ' . $cur . rcpt_inr($st['remaining']) . ' remaining';
      else                          $planLine = 'Month ' . $st['months_paid'] . ' of ' . $st['term_months'] . ' paid · ' . $cur . rcpt_inr($st['remaining']) . ' remaining';
    }
  } catch (Exception $e) { /* plans not set up — receipt simply has no plan box */ }
}

$ts = strtotime((string)$p['payment_date']);
$dateText = $ts ? (date('H:i', $ts) === '00:00' ? date('d M Y', $ts) : date('d M Y, h:i A', $ts)) : '';
$phone = trim((string)($set['company_phone'] ?? '')); $email = trim((string)($set['company_email'] ?? ''));

$png = renderReceiptPng([
  'company'      => (string)($set['company_name'] ?? 'Payment Receipt'),
  'company_line' => implode(' · ', array_filter([$phone, $email])),
  'receipt_no'   => 'RCPT-' . $pid,
  'date_text'    => $dateText,
  'client'       => (string)($p['client_name'] ?? ''),
  'invoice_no'   => (string)($p['invoice_number'] ?? ($inv['invoice_number'] ?? '')),
  'method'       => (string)($p['method'] ?? ''),
  'txn'          => (string)($p['transaction_id'] ?? ''),
  'amount'       => (float)$p['amount'],
  'settlement'   => (float)($p['settlement_discount'] ?? 0),
  'balance'      => $balance,
  'partial'      => $partial,
  'plan_title'   => $planTitle,
  'plan_line'    => $planLine,
  'currency'     => ($inv['currency'] ?? '') ?: '₹',
]);
if (!$png) jsonResponse(['error' => 'Could not draw the receipt — the server needs the PHP GD extension with FreeType, and fonts/DejaVuSans*.ttf must be uploaded'], 500);
if (@file_put_contents($path, $png) === false) jsonResponse(['error' => 'Could not save the receipt image — check uploads/receipts permissions'], 500);

// housekeeping: delete receipt images older than 120 days (≈ 1 in 20 runs)
if (random_int(1, 20) === 1) foreach (glob("$dir/rcpt-*.png") ?: [] as $f) if (filemtime($f) < time() - 120 * 86400) @unlink($f);

jsonResponse(['success' => true, 'url' => $url, 'cached' => false, 'receipt_no' => 'RCPT-' . $pid]);
