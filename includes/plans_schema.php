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
  $q = $db->prepare("SELECT id, invoice_number, status, grand_total, issued_date, due_date
                     FROM invoices
                     WHERE plan_id = ? AND status NOT IN ('Cancelled','Draft','Estimate')
                     ORDER BY issued_date ASC, id ASC");
  $q->execute([(int)$plan['id']]);
  $inv = $q->fetchAll(PDO::FETCH_ASSOC);

  $cov = [];
  if ($inv) {
    $ids = implode(',', array_map('intval', array_column($inv, 'id')));
    try {
      $pm = $db->query("SELECT invoice_id, SUM(amount + COALESCE(settlement_discount,0)) AS c FROM payments WHERE invoice_id IN ($ids) GROUP BY invoice_id");
    } catch (Exception $e) {
      $pm = $db->query("SELECT invoice_id, SUM(amount) AS c FROM payments WHERE invoice_id IN ($ids) GROUP BY invoice_id");
    }
    foreach ($pm->fetchAll(PDO::FETCH_ASSOC) as $r) $cov[(int)$r['invoice_id']] = (float)$r['c'];
  }

  $paid = 0; $billed = 0; $monthsPaid = 0; $rows = [];
  foreach ($inv as $i) {
    $t = (float)$i['grand_total'];
    $c = ($i['status'] === 'Paid') ? $t : min($t, $cov[(int)$i['id']] ?? 0);
    $paid   += $c;
    $billed += $t;
    if ($t - $c < 0.01) $monthsPaid++;
    $rows[] = [
      'id' => (int)$i['id'], 'invoice_number' => $i['invoice_number'], 'status' => $i['status'],
      'total' => $t, 'paid' => $c, 'left' => max(0, $t - $c),
      'issued_date' => $i['issued_date'], 'due_date' => $i['due_date'],
    ];
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
  $q = $db->prepare("SELECT COALESCE(SUM(grand_total),0) FROM invoices
                     WHERE plan_id = ? AND id <> ? AND status NOT IN ('Cancelled','Draft','Estimate')");
  $q->execute([$planId, $excludeInvoiceId]);
  return max(0, (float)$total - (float)$q->fetchColumn());
}
