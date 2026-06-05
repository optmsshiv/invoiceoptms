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
        $db->exec("SET time_zone = '+05:30'");
        $stmt = $db->prepare(
            'SELECT pt.invoice_id, pt.views, pt.expires_at, pt.created_at,
                    i.invoice_number, i.issued_date AS issue_date, i.due_date,
                    i.grand_total AS amount, i.subtotal, i.discount_pct, i.discount_amt,
                    i.gst_amount, i.status, i.client_id, i.service_type,
                    i.notes, i.terms, i.bank_details, i.currency
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

        // ── Normalize blank status (MySQL ENUM missing 'Estimate') ──
        // If DB stored '' instead of 'Estimate', restore from invoice number
        // prefix and immediately persist fix to DB.
        if (empty($row['status'])) {
            $row['status'] = str_starts_with($row['invoice_number'] ?? '', 'QT-') ? 'Estimate' : 'Draft';
            try {
                $db->prepare("UPDATE invoices SET status = :s WHERE id = :id AND (status IS NULL OR status = '')")
                   ->execute([':s' => $row['status'], ':id' => $row['invoice_id']]);
            } catch (Exception $e) {
                error_log('portal.php status fix: ' . $e->getMessage());
            }
        }

        // Update view counter — set first_viewed only on first visit
        $db->prepare('UPDATE portal_tokens
                      SET views        = views + 1,
                          last_viewed  = NOW(),
                          first_viewed = COALESCE(first_viewed, NOW())
                      WHERE token = :t')
           ->execute([':t' => $token]);

        // One-time backfill: if first_viewed still null for other tokens, use last_viewed
        try {
            $db->exec('UPDATE portal_tokens SET first_viewed = last_viewed WHERE first_viewed IS NULL AND last_viewed IS NOT NULL');
        } catch (Exception $e) { /* non-fatal */ }

        // Fetch client info
        $cStmt = $db->prepare('SELECT name, email, phone, address, gst_number FROM clients WHERE id = :id');
        $cStmt->execute([':id' => $row['client_id']]);
        $client = $cStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Fetch line items from invoice_items table
        $iStmt = $db->prepare('SELECT description, quantity, rate, gst_rate, line_total, item_type FROM invoice_items WHERE invoice_id = :id ORDER BY sort_order ASC');
        $iStmt->execute([':id' => $row['invoice_id']]);
        $lineItems = $iStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch payments
        $pStmt = $db->prepare('SELECT amount, payment_date, method, transaction_id FROM payments WHERE invoice_id = :id ORDER BY payment_date ASC');
        $pStmt->execute([':id' => $row['invoice_id']]);
        $payments = $pStmt->fetchAll(PDO::FETCH_ASSOC);

        // Re-fetch after update to get accurate first_viewed / last_viewed
        $vFresh = $db->prepare('SELECT views, first_viewed, last_viewed FROM portal_tokens WHERE token = :t LIMIT 1');
        $vFresh->execute([':t' => $token]);
        $vRow = $vFresh->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success'=>true,'invoice'=>$row,'client'=>$client,'items'=>$lineItems,'payments'=>$payments,'views'=>(int)($vRow['views'] ?? 1),'first_viewed'=>$vRow['first_viewed'] ?? null,'last_viewed'=>$vRow['last_viewed'] ?? null]);
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
    $db->exec("SET time_zone = '+05:30'");

    // ── GET: list all tokens ──────────────────────────────────────
    if ($method === 'GET' && empty($_GET['invoice_id'])) {
        $stmt = $db->query(
            'SELECT pt.*, i.invoice_number, i.grand_total AS amount, i.status,
                    COALESCE(c.name, i.client_name) AS client_name,
                    pt.expires_at
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
        // Verify invoice exists and get current status
        $chk = $db->prepare('SELECT id, status FROM invoices WHERE id = :id');
        $chk->execute([':id' => $invId]);
        $invRow = $chk->fetch();
        if (!$invRow) {
            http_response_code(404);
            echo json_encode(['success'=>false,'error'=>'Invoice not found']);
            exit;
        }
        $invStatus = $invRow['status'] ?? '';
        $expires   = !empty($body['expires_at']) ? $body['expires_at'] : null;
        $forceNew  = !empty($body['regenerate']); // explicit regenerate flag

        // FIX: Check if a valid token already exists — reuse it instead of replacing
        $existing = $db->prepare('SELECT token FROM portal_tokens WHERE invoice_id = :id AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1');
        $existing->execute([':id' => $invId]);
        $existingRow = $existing->fetch();

        if ($existingRow && !$forceNew) {
            // Reuse existing token — just update status in case it changed
            $token = $existingRow['token'];
            $db->prepare('UPDATE portal_tokens SET status = :s WHERE invoice_id = :id')
               ->execute([':s' => $invStatus, ':id' => $invId]);
        } else {
            // Generate new token (first time or explicit regenerate)
            $token = bin2hex(random_bytes(16));
            $stmt  = $db->prepare(
                'INSERT INTO portal_tokens (invoice_id, token, status, expires_at)
                 VALUES (:inv, :tok, :status, :exp)
                 ON DUPLICATE KEY UPDATE
                   token = VALUES(token),
                   status = VALUES(status),
                   expires_at = VALUES(expires_at),
                   views = 0,
                   view_count = 0,
                   first_viewed = NULL'
            );
            $stmt->execute([':inv'=>$invId,':tok'=>$token,':status'=>$invStatus,':exp'=>$expires]);
        }

        echo json_encode(['success'=>true,'token'=>$token,'invoice_id'=>$invId]);
        exit;
    }

    // ── PATCH: set or remove expiry on a token ────────────────────
    if ($method === 'PATCH') {
        $raw   = file_get_contents('php://input');
        $body  = json_decode($raw, true) ?: [];
        $token = trim($body['token'] ?? '');
        $days  = isset($body['expiry_days']) ? (int)$body['expiry_days'] : -1;

        if (!$token) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'token required']);
            exit;
        }

        if ($days <= 0) {
            // Remove expiry — link lives forever
            $db->prepare('UPDATE portal_tokens SET expires_at = NULL WHERE token = ?')
               ->execute([$token]);
            echo json_encode(['success' => true, 'expires_at' => null]);
        } else {
            // Set expiry N days from now
            $expiresAt = (new DateTime())->modify("+{$days} days")->format('Y-m-d H:i:s');
            $db->prepare('UPDATE portal_tokens SET expires_at = ? WHERE token = ?')
               ->execute([$expiresAt, $token]);
            echo json_encode(['success' => true, 'expires_at' => $expiresAt]);
        }
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