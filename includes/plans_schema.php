<?php
// ═══════════════════════════════════════════════════════
//  Installment / payment-plan schema + shared calculations
//  Used by api/plans.php and api/invoices.php.
//  Self-migrating (same pattern as api/invoices.php): safe to run on every request.
// ═══════════════════════════════════════════════════════

function ensurePlanSchema(PDO $db): void {
  static $done = false;
  if ($done) return;
  $done = true;
  try {
    $db->exec("CREATE TABLE IF NOT EXISTS payment_plans (
      id INT AUTO_INCREMENT PRIMARY KEY,
      client_id INT NULL,
      client_name VARCHAR(200) NULL,
      service_name VARCHAR(200) NOT NULL,
      total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
      installment_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
      term_months INT NOT NULL DEFAULT 12,
      start_date DATE NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'Active',
      notes VARCHAR(500) NULL,
      created_by INT NULL,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      KEY idx_pp_client (client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  } catch (Exception $e) { error_log('migrate payment_plans: ' . $e->getMessage()); }
  try {
    $chk = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoices' AND COLUMN_NAME='plan_id'");
    $chk->execute();
    if ((int)$chk->fetchColumn() === 0) {
      $db->exec("ALTER TABLE invoices ADD COLUMN plan_id INT NULL DEFAULT NULL, ADD KEY idx_inv_plan (plan_id)");
    }
  } catch (Exception $e) { error_log('migrate invoices.plan_id: ' . $e->getMessage()); }
  // Which LINE ITEM is the plan item (so an invoice can mix e.g. WhatsApp + other services)
  try {
    $chk = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_items' AND COLUMN_NAME='plan_id'");
    $chk->execute();
    if ((int)$chk->fetchColumn() === 0) {
      $db->exec("ALTER TABLE invoice_items ADD COLUMN plan_id INT NULL DEFAULT NULL");
    }
  } catch (Exception $e) { error_log('migrate invoice_items.plan_id: ' . $e->getMessage()); }
}

/**
 * The part of an invoice that belongs to a plan: the flagged line item(s) with their own GST and
 * their share of any invoice-level discount — exactly the maths the invoice form uses.
 * $items: [qty, rate, gst, disc, discType('pct'|'fixed'), plan_id].
 * Legacy invoices (linked to a plan before line items were flagged) have no flagged item, so the
 * whole invoice ($fallbackGrand) counts, as before.
 */
function planShareCalc(array $items, string $mode, float $discAmt, int $planId, float $fallbackGrand): float {
  $flagged = array_filter($items, fn($i) => (int)($i['plan_id'] ?? 0) === $planId);
  if (!$flagged) return round($fallbackGrand, 2);
  $sub = 0.0;
  foreach ($items as $i) $sub += (float)($i['qty'] ?? 1) * (float)($i['rate'] ?? 0);
  $factor = $sub > 0 ? max(0, 1 - $discAmt / $sub) : 1;
  $out = 0.0;
  foreach ($flagged as $i) {
    $line = (float)($i['qty'] ?? 1) * (float)($i['rate'] ?? 0);
    $g = (float)($i['gst'] ?? 0);
    if ($mode === 'item') {
      $d = (($i['discType'] ?? 'pct') === 'fixed') ? min((float)($i['disc'] ?? 0), $line) : $line * (float)($i['disc'] ?? 0) / 100;
      $out += ($line - $d) * (1 + $g / 100);
    } else {
      $out += $line * (1 + $g / 100) * $factor;
    }
  }
  return round($out, 2);
}

/** Live invoices linked to a plan, each with its plan share and how much of the invoice is covered. */
function planLinkedInvoices(PDO $db, int $planId, int $excludeId = 0): array {
  $q = $db->prepare("SELECT id, invoice_number, status, grand_total, issued_date, due_date, discount_amt, discount_mode
                     FROM invoices
                     WHERE plan_id = ? AND id <> ? AND status NOT IN ('Cancelled','Draft','Estimate')
                     ORDER BY issued_date ASC, id ASC");
  $q->execute([$planId, $excludeId]);
  $inv = $q->fetchAll(PDO::FETCH_ASSOC);
  if (!$inv) return [];
  $ids = implode(',', array_map('intval', array_column($inv, 'id')));

  $cov = [];
  try {
    $pm = $db->query("SELECT invoice_id, SUM(amount + COALESCE(settlement_discount,0)) AS c FROM payments WHERE invoice_id IN ($ids) GROUP BY invoice_id");
  } catch (Exception $e) {
    $pm = $db->query("SELECT invoice_id, SUM(amount) AS c FROM payments WHERE invoice_id IN ($ids) GROUP BY invoice_id");
  }
  foreach ($pm->fetchAll(PDO::FETCH_ASSOC) as $r) $cov[(int)$r['invoice_id']] = (float)$r['c'];

  $items = [];
  try {
    $im = $db->query("SELECT invoice_id, quantity, rate, gst_rate, item_discount, item_discount_type, plan_id FROM invoice_items WHERE invoice_id IN ($ids)");
    foreach ($im->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $items[(int)$r['invoice_id']][] = ['qty' => $r['quantity'], 'rate' => $r['rate'], 'gst' => $r['gst_rate'],
        'disc' => $r['item_discount'], 'discType' => $r['item_discount_type'], 'plan_id' => $r['plan_id']];
    }
  } catch (Exception $e) { /* item columns missing: every invoice falls back to its full total */ }

  $out = [];
  foreach ($inv as $i) {
    $id = (int)$i['id']; $t = (float)$i['grand_total'];
    $c = ($i['status'] === 'Paid') ? $t : min($t, $cov[$id] ?? 0);
    $share = min($t, planShareCalc($items[$id] ?? [], (string)($i['discount_mode'] ?: 'invoice'), (float)$i['discount_amt'], $planId, $t));
    $ratio = $t > 0 ? $share / $t : 1;
    $out[] = ['id' => $id, 'invoice_number' => $i['invoice_number'], 'status' => $i['status'],
      'total' => $t, 'share' => $share,
      'paid' => round($c * $ratio, 2),                 // plan part of what was paid (payments split in proportion)
      'plan_left' => round(max(0, $share - $c * $ratio), 2),
      'covered' => $c, 'left' => max(0, $t - $c),      // whole-invoice balance
      'issued_date' => $i['issued_date'], 'due_date' => $i['due_date']];
  }
  return $out;
}


/**
 * Everything the UI and the portal need to know about one plan.
 * Remaining is ALWAYS derived from invoices + payments — never stored — so it
 * cannot drift when a payment is edited or an invoice is deleted.
 *
 * "paid"     = cash + settlement discount actually received on linked invoices
 * "billed"   = grand total of linked, live (not Draft/Estimate/Cancelled) invoices
 * "remaining"= plan total − paid          (what the client still owes overall)
 * "unbilled" = plan total − billed        (what is still left to invoice)
 */
function planStats(PDO $db, array $plan): array {
  $total = (float)$plan['total_amount'];
  $rows = planLinkedInvoices($db, (int)$plan['id']);
  $paid = 0; $billed = 0; $monthsPaid = 0;
  foreach ($rows as $r) {
    $paid += $r['paid']; $billed += $r['share'];
    if ($r['left'] < 0.01) $monthsPaid++;
  }
  $remaining = max(0, $total - $paid);
  $unbilled  = max(0, $total - $billed);
  $inst      = (float)$plan['installment_amount'];
  return [
    'id' => (int)$plan['id'], 'client_id' => $plan['client_id'] !== null ? (int)$plan['client_id'] : null,
    'service_name' => $plan['service_name'], 'total_amount' => $total,
    'installment_amount' => $inst, 'term_months' => (int)$plan['term_months'],
    'start_date' => $plan['start_date'], 'status' => $plan['status'], 'notes' => $plan['notes'] ?? '',
    'paid' => round($paid, 2), 'billed' => round($billed, 2),
    'remaining' => round($remaining, 2), 'unbilled' => round($unbilled, 2),
    'months_paid' => $monthsPaid, 'invoice_count' => count($rows),
    'months_left' => $inst > 0 ? (int)ceil($remaining / $inst - 1e-9) : 0,
    'suggested' => round($inst > 0 ? min($inst, $unbilled) : $unbilled, 2),
    'invoices' => $rows,
  ];
}

/** How much of the plan is still free to invoice, ignoring one invoice (used when editing it). */
function planUnbilled(PDO $db, int $planId, int $excludeInvoiceId = 0): ?float {
  $p = $db->prepare('SELECT total_amount FROM payment_plans WHERE id = ?');
  $p->execute([$planId]);
  $total = $p->fetchColumn();
  if ($total === false) return null;
  $billed = 0.0;
  foreach (planLinkedInvoices($db, $planId, $excludeInvoiceId) as $r) $billed += $r['share'];
  return max(0, (float)$total - $billed);
}
