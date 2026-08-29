<?php
// ================================================================
//  api/backfill_purchase_payment_methods.php — ONE-OFF maintenance script
//
//  Retroactively fixes purchases.payment_type / payment_mode (and,
//  along the way, amount_paid/status too) for every Purchase that has
//  real rows in purchase_payments, using the exact same recompute
//  logic that purchase_payments.php's recachePurchasePaid() now runs
//  automatically on every new payment/delete going forward.
//
//  This only needs to run ONCE, to backfill purchases that were paid
//  through the ledger BEFORE that fix existed — same situation as
//  sales.payment_method was in before backfill_sale_payment_methods.php.
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

// Every purchase that has at least one real (non-deleted) payment row —
// these are the only ones that could possibly be out of sync, since a
// purchase with zero ledger rows was never touched by the old bug.
$purchaseIds = $db->query(
    'SELECT DISTINCT purchase_id FROM purchase_payments WHERE purchase_deleted = 0'
)->fetchAll(PDO::FETCH_COLUMN);

$results = [];

foreach ($purchaseIds as $purchaseId) {
    $purchaseId = (int)$purchaseId;

    $sumStmt = $db->prepare(
        'SELECT COALESCE(SUM(amount),0) AS paid FROM purchase_payments
          WHERE purchase_id = ? AND purchase_deleted = 0'
    );
    $sumStmt->execute([$purchaseId]);
    $paid = (float)$sumStmt->fetch()['paid'];

    $totStmt = $db->prepare(
        'SELECT purchase_no, total, payment_type AS old_type, payment_mode AS old_mode, amount_paid AS old_paid
           FROM purchases WHERE id = ?'
    );
    $totStmt->execute([$purchaseId]);
    $purchase = $totStmt->fetch(PDO::FETCH_ASSOC);
    if (!$purchase) continue; // purchase itself no longer exists — skip

    $grandTotal = (float)$purchase['total'];

    if ($grandTotal > 0 && ($grandTotal - $paid) <= 0.01) {
        $status = 'Paid';
    } elseif ($paid > 0.004) {
        $status = 'Partial';
    } else {
        $status = 'Pending';
    }

    $methodStmt = $db->prepare(
        'SELECT DISTINCT method FROM purchase_payments
          WHERE purchase_id = ? AND purchase_deleted = 0 AND method IS NOT NULL AND method <> \'\''
    );
    $methodStmt->execute([$purchaseId]);
    $methods = $methodStmt->fetchAll(PDO::FETCH_COLUMN);
    $paymentMode = count($methods) === 1 ? $methods[0] : (count($methods) > 1 ? 'Split' : '');

    $changed = ($purchase['old_type'] !== $paymentMode) || ($purchase['old_mode'] !== $paymentMode)
        || (abs((float)$purchase['old_paid'] - $paid) > 0.004);

    $db->prepare('UPDATE purchases SET amount_paid = ?, status = ?, payment_type = ?, payment_mode = ? WHERE id = ?')
       ->execute([$paid, $status, $paymentMode, $paymentMode, $purchaseId]);

    if ($changed) {
        $results[] = [
            'purchase_id'   => $purchaseId,
            'purchase_no'   => $purchase['purchase_no'],
            'mode_before'   => $purchase['old_mode'],
            'mode_after'    => $paymentMode,
            'paid_before'   => (float)$purchase['old_paid'],
            'paid_after'    => $paid,
        ];
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success'            => true,
    'purchases_checked'  => count($purchaseIds),
    'purchases_corrected'=> count($results),
    'corrected'          => $results,
], JSON_PRETTY_PRINT);
