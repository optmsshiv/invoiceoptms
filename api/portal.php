<?php
// ================================================================
//  api/portal.php  — Client Portal Token Management
//
//  GET    /api/portal.php                 → list all portal tokens
//  GET    /api/portal.php?invoice_id=X    → token for one invoice
//  GET    /api/portal.php?token=ABC       → public: verify token & return invoice (no auth)
//  POST   /api/portal.php                 → generate/regenerate token for invoice
//  DELETE /api/portal.php?invoice_id=X    → revoke portal token
//
//  The ?token= endpoint is intentionally PUBLIC (no login required)
//  so clients can view their invoice via the portal link.
// ================================================================

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

// ── Public token verification — no auth needed ────────────────
if ($method === 'GET' && !empty($_GET['token'])) {
    $token = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['token']);
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT pt.*, i.invoice_number, i.issue_date, i.due_date, i.amount,
                    i.status, i.client_id, i.service_type, i.notes, i.terms, i.bank_details,
                    i.gst_rate, i.discount, i.currency
             FROM portal_tokens pt
             JOIN invoices i ON i.id = pt.invoice_id
             WHERE pt.token = :token
               AND (pt.expires_at IS NULL OR pt.expires_at > NOW())'
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success'=>false,'error'=>'Invalid or expired link']);
            exit;
        }
        // Update view counter
        $db->prepare('UPDATE portal_tokens SET views = views + 1, last_viewed = NOW() WHERE token = :t')
           ->execute([':t' => $token]);

        // Fetch client info
        $cStmt = $db->prepare('SELECT name, email, phone FROM clients WHERE id = :id');
        $cStmt->execute([':id' => $row['client_id']]);
        $client = $cStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Fetch payments
        $pStmt = $db->prepare('SELECT amount, payment_date, method, transaction_id FROM payments WHERE invoice_id = :id ORDER BY payment_date ASC');
        $pStmt->execute([':id' => $row['invoice_id']]);
        $payments = $pStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success'=>true,'invoice'=>$row,'client'=>$client,'payments'=>$payments,'views'=>(int)$row['views']+1]);
    } catch (Exception $e) {
        error_log('portal.php token error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>'Server error']);
    }
    exit;
}

// ── All other endpoints require login ─────────────────────────
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

try {
    $db = getDB();

    // ── GET: list all tokens ──────────────────────────────────────
    if ($method === 'GET' && empty($_GET['invoice_id'])) {
        $stmt = $db->query(
            'SELECT pt.*, i.invoice_number, i.amount, i.status,
                    c.name AS client_name
             FROM portal_tokens pt
             JOIN invoices i ON i.id = pt.invoice_id
             LEFT JOIN clients c ON c.id = i.client_id
             ORDER BY pt.created_at DESC'
        );
        echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── GET: token for specific invoice ───────────────────────────
    if ($method === 'GET' && !empty($_GET['invoice_id'])) {
        $invId = (int)$_GET['invoice_id'];
        $stmt  = $db->prepare('SELECT * FROM portal_tokens WHERE invoice_id = :id');
        $stmt->execute([':id' => $invId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ? ['success'=>true,'data'=>$row] : ['success'=>false,'error'=>'No token']);
        exit;
    }

    // ── Read body ────────────────────────────────────────────────
    $body = [];
    if (in_array($method, ['POST','PUT'])) {
        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: [];
        if (empty($body)) $body = $_POST;
    }

    // ── POST: generate or regenerate token ───────────────────────
    if ($method === 'POST') {
        $invId = (int)($body['invoice_id'] ?? 0);
        if (!$invId) {
            http_response_code(422);
            echo json_encode(['success'=>false,'error'=>'invoice_id required']);
            exit;
        }
        // Verify invoice exists
        $chk = $db->prepare('SELECT id FROM invoices WHERE id = :id');
        $chk->execute([':id' => $invId]);
        if (!$chk->fetch()) {
            http_response_code(404);
            echo json_encode(['success'=>false,'error'=>'Invoice not found']);
            exit;
        }
        // Generate cryptographically random token
        $token   = bin2hex(random_bytes(16)); // 32 hex chars
        $expires = !empty($body['expires_at']) ? $body['expires_at'] : null;

        $stmt = $db->prepare(
            'INSERT INTO portal_tokens (invoice_id, token, expires_at)
             VALUES (:inv, :tok, :exp)
             ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), views = 0'
        );
        $stmt->execute([':inv'=>$invId,':tok'=>$token,':exp'=>$expires]);

        echo json_encode(['success'=>true,'token'=>$token,'invoice_id'=>$invId]);
        exit;
    }

    // ── DELETE: revoke token ──────────────────────────────────────
    if ($method === 'DELETE') {
        $invId = (int)($_GET['invoice_id'] ?? 0);
        if (!$invId) {
            http_response_code(422);
            echo json_encode(['success'=>false,'error'=>'invoice_id required']);
            exit;
        }
        $db->prepare('DELETE FROM portal_tokens WHERE invoice_id = :id')->execute([':id'=>$invId]);
        echo json_encode(['success'=>true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);

} catch (Exception $e) {
    error_log('portal.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error']);
}
