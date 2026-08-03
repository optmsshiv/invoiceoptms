<?php
// ================================================================
//  api/license.php — Self-service license renewal requests
//
//  Deliberately a SEPARATE file from api/tenant.php: that file hard-
//  requires requireSuperAdmin() at the top for every action, but this
//  one must be callable by an ordinary (currently license-locked-out)
//  staff member acting on their own account.
//
//  POST ?action=request_renewal → create a pending request for the
//                                   CURRENT logged-in user
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

try {
    $master = getMasterDB();

    // ── Request a license renewal (self-service) ────────────────────
    if ($method === 'POST' && $action === 'request_renewal') {
        if (!$userId) jsonResponse(['error' => 'Not authenticated'], 401);

        // Don't create duplicate pending requests if one already exists —
        // just tell the caller it's already in the queue.
        $chk = $master->prepare(
            "SELECT id FROM license_renewal_requests WHERE user_id = ? AND status = 'pending'"
        );
        $chk->execute([$userId]);
        if ($chk->fetch()) {
            jsonResponse(['success' => true, 'already_pending' => true,
                'message' => 'A renewal request is already pending review.']);
        }

        $tenantId = $_SESSION['tenant_id'] ?? null;
        $master->prepare(
            "INSERT INTO license_renewal_requests (user_id, tenant_id, requested_at, status)
             VALUES (?, ?, ?, 'pending')"
        )->execute([$userId, $tenantId, date('Y-m-d H:i:s')]);

        masterAuditLog($userId, $tenantId, 'license_renewal_requested', 'User requested license renewal');

        jsonResponse(['success' => true, 'message' => 'Renewal request sent. You will be notified once approved.']);
    }

    jsonResponse(['error' => 'Unknown action: ' . $action], 400);

} catch (Exception $e) {
    error_log('license.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
}
