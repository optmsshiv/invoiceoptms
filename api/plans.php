<?php
// ═══════════════════════════════════════════════════════
//  API: /api/plans.php — Installment payment plans
//  GET    ?client_id=N | ?id=N     → plans with live paid / remaining figures
//  POST   {client_id, service_name, total_amount, term_months, [installment_amount], [start_date]}
//  PUT    ?id=N  {…fields…, status: Active|Closed}
//  DELETE ?id=N  (only while no invoice is linked)
// ═══════════════════════════════════════════════════════
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/plans_schema.php';
requireLogin();
$db = getDB();
ensurePlanSchema($db);

function planFetch(PDO $db, int $id): ?array {
  $s = $db->prepare('SELECT * FROM payment_plans WHERE id = ?');
  $s->execute([$id]);
  return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
// A plan whose full amount has been collected closes itself; reopening is automatic if a payment is removed.
function planAutoStatus(PDO $db, array $st): string {
  if ($st['status'] === 'Closed') return 'Closed';
  $want = ($st['remaining'] < 0.01 && $st['unbilled'] < 0.01 && $st['invoice_count'] > 0) ? 'Completed' : 'Active';
  if ($want !== $st['status']) {
    $db->prepare('UPDATE payment_plans SET status = ? WHERE id = ?')->execute([$want, $st['id']]);
  }
  return $want;
}
function planClean(array $in, ?array $old = null): array {
  $name  = trim((string)($in['service_name'] ?? ($old['service_name'] ?? '')));
  $total = round((float)($in['total_amount'] ?? ($old['total_amount'] ?? 0)), 2);
  $term  = (int)($in['term_months'] ?? ($old['term_months'] ?? 12));
  if ($name === '')                    jsonResponse(['error' => 'Service name is required'], 400);
  if ($total <= 0)                     jsonResponse(['error' => 'Plan total must be greater than zero'], 400);
  if ($term < 1 || $term > 120)        jsonResponse(['error' => 'Number of months must be between 1 and 120'], 400);
  $inst = isset($in['installment_amount']) && (float)$in['installment_amount'] > 0
        ? round((float)$in['installment_amount'], 2)
        : round($total / $term, 2);
  if ($inst > $total)                  jsonResponse(['error' => 'Installment cannot be more than the plan total'], 400);
  $start = $in['start_date'] ?? ($old['start_date'] ?? null);
  if ($start === '' || $start === null) $start = date('Y-m-d');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$start)) jsonResponse(['error' => 'Invalid start date'], 400);
  return [$name, $total, $inst, $term, $start];
}

switch ($_SERVER['REQUEST_METHOD']) {

case 'GET':
  if (!empty($_GET['id'])) {
    $p = planFetch($db, (int)$_GET['id']);
    if (!$p) jsonResponse(['error' => 'Plan not found'], 404);
    $st = planStats($db, $p);
    $st['status'] = planAutoStatus($db, $st);
    jsonResponse(['data' => $st]);
  }
  $where = ['1=1']; $params = [];
  if (!empty($_GET['client_id'])) { $where[] = 'client_id = ?'; $params[] = (int)$_GET['client_id']; }
  if (!empty($_GET['status']))    { $where[] = 'status = ?';    $params[] = $_GET['status']; }
  $s = $db->prepare('SELECT * FROM payment_plans WHERE ' . implode(' AND ', $where) . " ORDER BY FIELD(status,'Active','Completed','Closed'), id DESC");
  $s->execute($params);
  $out = [];
  foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $st = planStats($db, $p);
    $st['status'] = planAutoStatus($db, $st);
    $out[] = $st;
  }
  jsonResponse(['data' => $out]);

case 'POST':
  $in = json_decode(file_get_contents('php://input'), true);
  if (!$in) jsonResponse(['error' => 'Invalid JSON'], 400);
  [$name, $total, $inst, $term, $start] = planClean($in);
  $cid = !empty($in['client_id']) ? (int)$in['client_id'] : null;
  if (!$cid) jsonResponse(['error' => 'Select a saved client first — plans are kept per client'], 400);
  $uid = null;
  try { $u = $db->prepare('SELECT 1 FROM users WHERE id = ?'); $u->execute([$_SESSION['user_id']]); if ($u->fetch()) $uid = $_SESSION['user_id']; } catch (Exception $e) {}
  $db->prepare('INSERT INTO payment_plans (client_id, client_name, service_name, total_amount, installment_amount, term_months, start_date, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?)')
     ->execute([$cid, (string)($in['client_name'] ?? ''), $name, $total, $inst, $term, $start, mb_substr((string)($in['notes'] ?? ''), 0, 500), $uid]);
  $id = (int)$db->lastInsertId();
  logActivity((int)$_SESSION['user_id'], 'create', 'plan', $id, "Created payment plan: $name ($total in $term months)");
  $st = planStats($db, planFetch($db, $id));
  jsonResponse(['success' => true, 'id' => $id, 'data' => $st]);

case 'PUT':
  $in = json_decode(file_get_contents('php://input'), true) ?: [];
  $id = (int)($_GET['id'] ?? $in['id'] ?? 0);
  $old = $id ? planFetch($db, $id) : null;
  if (!$old) jsonResponse(['error' => 'Plan not found'], 404);
  if (array_key_exists('status', $in) && count($in) <= 2) {            // status-only change (Close / Reopen)
    $new = $in['status'] === 'Closed' ? 'Closed' : 'Active';
    $db->prepare('UPDATE payment_plans SET status = ? WHERE id = ?')->execute([$new, $id]);
    logActivity((int)$_SESSION['user_id'], 'update', 'plan', $id, "Plan #$id set to $new");
    jsonResponse(['success' => true]);
  }
  [$name, $total, $inst, $term, $start] = planClean($in, $old);
  $billed = planStats($db, $old)['billed'];
  if ($total + 0.01 < $billed) jsonResponse(['error' => 'Plan total cannot be lower than what is already invoiced (' . number_format($billed, 2) . ')'], 400);
  $db->prepare('UPDATE payment_plans SET service_name=?, total_amount=?, installment_amount=?, term_months=?, start_date=?, notes=? WHERE id=?')
     ->execute([$name, $total, $inst, $term, $start, mb_substr((string)($in['notes'] ?? $old['notes'] ?? ''), 0, 500), $id]);
  logActivity((int)$_SESSION['user_id'], 'update', 'plan', $id, "Updated payment plan #$id");
  jsonResponse(['success' => true]);

case 'DELETE':
  $id = (int)($_GET['id'] ?? 0);
  if (!$id) jsonResponse(['error' => 'ID required'], 400);
  $n = $db->prepare('SELECT COUNT(*) FROM invoices WHERE plan_id = ?');
  $n->execute([$id]);
  if ((int)$n->fetchColumn() > 0) jsonResponse(['error' => 'This plan already has invoices — close it instead of deleting.'], 409);
  $db->prepare('DELETE FROM payment_plans WHERE id = ?')->execute([$id]);
  logActivity((int)$_SESSION['user_id'], 'delete', 'plan', $id, "Deleted payment plan #$id");
  jsonResponse(['success' => true]);

default:
  jsonResponse(['error' => 'Method not allowed'], 405);
}
