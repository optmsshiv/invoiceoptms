<?php
// ================================================================
//  api/backfill_sale_payment_methods.php — ONE-OFF maintenance script
//
//  Retroactively fixes sales.payment_method (and, along the way,
//  amount_received/payment_status too) for every Sale that has real
//  rows in sale_payments, using the exact same recompute logic that
//  sale_payments.php's recacheSaleReceived() now runs automatically
//  on every new payment/delete going forward.
//
//  This only needs to run ONCE, to backfill sales that were paid
//  through the ledger BEFORE that fix existed (like INV/26-27/0021).
//  Safe to re-run — it's fully idempotent, just recomputes the same
//  values from the real ledger rows each time.
//
//  Admin only. Visit once in the browser (or curl it), check the
//  summary it prints, then delete this file — it's not meant to stay
//  in the codebase as a live endpoint.
// ================================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$user = currentUser();
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin only']);
    exit;
}

$db = getDB();

// Every sale that has at least one real (non-deleted) payment row —
// these are the only ones that could possibly be out of sync, since a
// sale with zero ledger rows was never touched by the old bug in the
// first place.
$saleIds = $db->query(
    'SELECT DISTINCT sale_id FROM sale_payments WHERE sale_deleted = 0'
)->fetchAll(PDO::FETCH_COLUMN);

$results = [];

foreach ($saleIds as $saleId) {
    $saleId = (int)$saleId;

    $sumStmt = $db->prepare(
        'SELECT COALESCE(SUM(amount),0) AS received FROM sale_payments
          WHERE sale_id = ? AND sale_deleted = 0'
    );
    $sumStmt->execute([$saleId]);
    $received = (float)$sumStmt->fetch()['received'];

    $totStmt = $db->prepare('SELECT invoice_no, total, payment_method AS old_method, amount_received AS old_received FROM sales WHERE id = ?');
    $totStmt->execute([$saleId]);
    $sale = $totStmt->fetch(PDO::FETCH_ASSOC);
    if (!$sale) continue; // sale itself no longer exists — skip

    $grandTotal = (float)$sale['total'];

    if ($grandTotal > 0 && ($grandTotal - $received) <= 0.01) {
        $status = 'Paid';
    } elseif ($received > 0.004) {
        $status = 'Partial';
    } else {
        $status = 'Pending';
    }

    $methodStmt = $db->prepare(
        'SELECT DISTINCT method FROM sale_payments
          WHERE sale_id = ? AND sale_deleted = 0 AND method IS NOT NULL AND method <> \'\''
    );
    $methodStmt->execute([$saleId]);
    $methods = $methodStmt->fetchAll(PDO::FETCH_COLUMN);
    $paymentMethod = count($methods) === 1 ? $methods[0] : (count($methods) > 1 ? 'Split' : '');

    $changed = ($sale['old_method'] !== $paymentMethod) || (abs((float)$sale['old_received'] - $received) > 0.004);

    $db->prepare('UPDATE sales SET amount_received = ?, payment_status = ?, payment_method = ? WHERE id = ?')
       ->execute([$received, $status, $paymentMethod, $saleId]);

    if ($changed) {
        $results[] = [
            'sale_id'      => $saleId,
            'invoice_no'   => $sale['invoice_no'],
            'method_before'=> $sale['old_method'],
            'method_after' => $paymentMethod,
            'received_before' => (float)$sale['old_received'],
            'received_after'  => $received,
        ];
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success'        => true,
    'sales_checked'  => count($saleIds),
    'sales_corrected'=> count($results),
    'corrected'      => $results,
], JSON_PRETTY_PRINT);
