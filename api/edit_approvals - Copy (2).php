<?php
// ================================================================
//  api/edit_approvals.php — Edit Approval Request workflow
//
//  POST ?action=request  → non-admin requests permission to edit a record
//  GET  ?action=check    → requester polls their pending request status
//  GET  ?action=pending  → admin/owner fetches all pending requests
//  POST ?action=approve  → admin/owner approves a request
//  POST ?action=reject   → admin/owner rejects a request
// ================================================================
ob_start();
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$body = [];
if (in_array($method, ['POST','PATCH'])) {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
}

$userId   = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Unknown';
$userRole = $_SESSION['user_role'] ?? 'viewer';

// Auto-expire old requests before every response
$db->exec("UPDATE edit_approval_requests SET status='expired'
           WHERE status='pending' AND expires_at < NOW()");

try { switch (true) {

    // ── REQUEST: non-admin asks for edit permission ───────────────
    case ($method === 'POST' && $action === 'request'):
        $entityType  = trim($body['entity_type']  ?? '');
        // Product IDs arrive as "p12" (prefixed) — strip non-digits before casting
        $rawId    = $body['entity_id'] ?? 0;
        $entityId = (int) preg_replace('/\D/', '', (string)$rawId);
        $entityLabel = trim($body['entity_label'] ?? '');
        $reason      = trim(substr($body['reason'] ?? '', 0, 500));

        $allowed = ['purchase','sale','supplier','customer','product','stock_adjustment','stock_in'];
        if (!in_array($entityType, $allowed) || !$entityId) {
            jsonResponse(['error' => 'Invalid entity'], 400);
        }

        // Check if there's already a pending/approved request for this
        // user+entity combination — no duplicates
        $dup = $db->prepare(
            'SELECT id, status FROM edit_approval_requests
              WHERE requested_by=? AND entity_type=? AND entity_id=?
                AND status IN ("pending","approved") AND expires_at > NOW()'
        );
        $dup->execute([$userId, $entityType, $entityId]);
        if ($existing = $dup->fetch()) {
            jsonResponse(['success' => true, 'id' => $existing['id'], 'status' => $existing['status'], 'already_exists' => true]);
        }

        $stmt = $db->prepare(
            'INSERT INTO edit_approval_requests
               (requested_by, requester_name, entity_type, entity_id, entity_label, reason, expires_at)
             VALUES (?,?,?,?,?,?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
        );
        $stmt->execute([$userId, $userName, $entityType, $entityId, $entityLabel, $reason]);
        $reqId = (int)$db->lastInsertId();

        logActivity($userId, 'edit_approval_requested', $entityType, $entityId,
            "{$userName} requested edit approval for {$entityType} #{$entityId}: {$entityLabel}");

        jsonResponse(['success' => true, 'id' => $reqId, 'status' => 'pending']);

    // ── CHECK: requester polls their request ──────────────────────
    case ($method === 'GET' && $action === 'check'):
        $reqId = (int)($_GET['id'] ?? 0);
        if (!$reqId) jsonResponse(['error' => 'Missing id'], 400);
        $stmt = $db->prepare('SELECT * FROM edit_approval_requests WHERE id=? AND requested_by=?');
        $stmt->execute([$reqId, $userId]);
        $req = $stmt->fetch();
        if (!$req) jsonResponse(['error' => 'Not found'], 404);
        jsonResponse(['success' => true, 'data' => $req]);

    // ── PENDING: admin/owner/manager fetches all pending requests ─────────
    case ($method === 'GET' && $action === 'pending'):
        if (!in_array($userRole, ['owner','admin','manager','super_admin'])) {
            jsonResponse(['error' => 'Forbidden'], 403);
        }
        $stmt = $db->query(
            'SELECT * FROM edit_approval_requests
              WHERE status="pending" AND expires_at > NOW()
              ORDER BY created_at DESC LIMIT 50'
        );
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);

    // ── APPROVE ───────────────────────────────────────────────────
    case ($method === 'POST' && $action === 'approve'):
        if (!in_array($userRole, ['owner','admin','manager','super_admin'])) {
            jsonResponse(['error' => 'Forbidden'], 403);
        }
        $reqId = (int)($body['id'] ?? 0);
        $note  = trim(substr($body['note'] ?? '', 0, 500));
        if (!$reqId) jsonResponse(['error' => 'Missing id'], 400);

        $db->prepare(
            'UPDATE edit_approval_requests
                SET status="approved", reviewed_by=?, reviewer_name=?,
                    review_note=?, approved_at=NOW(),
                    -- Extend window: 1h to actually do the edit
                    expires_at=DATE_ADD(NOW(), INTERVAL 1 HOUR)
              WHERE id=? AND status="pending"'
        )->execute([$userId, $userName, $note, $reqId]);

        // Fetch to log
        $r = $db->prepare('SELECT * FROM edit_approval_requests WHERE id=?');
        $r->execute([$reqId]);
        $req = $r->fetch();
        if ($req) logActivity($userId, 'edit_approval_approved', $req['entity_type'], $req['entity_id'],
            "{$userName} approved edit request #{$reqId} for {$req['requester_name']}");

        jsonResponse(['success' => true]);

    // ── REJECT ────────────────────────────────────────────────────
    case ($method === 'POST' && $action === 'reject'):
        if (!in_array($userRole, ['owner','admin','manager','super_admin'])) {
            jsonResponse(['error' => 'Forbidden'], 403);
        }
        $reqId = (int)($body['id'] ?? 0);
        $note  = trim(substr($body['note'] ?? '', 0, 500));
        if (!$reqId) jsonResponse(['error' => 'Missing id'], 400);

        $db->prepare(
            'UPDATE edit_approval_requests
                SET status="rejected", reviewed_by=?, reviewer_name=?, review_note=?
              WHERE id=? AND status="pending"'
        )->execute([$userId, $userName, $note, $reqId]);

        $r = $db->prepare('SELECT * FROM edit_approval_requests WHERE id=?');
        $r->execute([$reqId]);
        $req = $r->fetch();
        if ($req) logActivity($userId, 'edit_approval_rejected', $req['entity_type'], $req['entity_id'],
            "{$userName} rejected edit request #{$reqId} for {$req['requester_name']}");

        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Unknown action: ' . $action], 400);

}} catch (Throwable $e) {
    error_log('edit_approvals.php: ' . $e->getMessage());
    jsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
}