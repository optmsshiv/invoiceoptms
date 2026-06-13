<?php
// ================================================================
//  api/activity.php  — Activity / Audit Log
//
//  Reads/writes from `activity_log` table (the real one used by
//  logActivity() in auth.php). The old `activitys_log` table was
//  a duplicate created by mistake — now removed.
//
//  GET    /api/activity.php               → list log (filters via QS)
//  POST   /api/activity.php               → append entry
//  DELETE /api/activity.php               → clear all (admin only)
//
//  Query params for GET:
//    ?action=delete                      filter by action
//    ?entity_type=payment                filter by entity type
//    ?entity_id=X                        filter by specific record
//    ?from=YYYY-MM-DD&to=YYYY-MM-DD      date range
//    ?search=text                        search in details
//    ?limit=100&offset=0                 pagination (default 200)
// ================================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();

    // ── Auto-migrate: ensure details column is TEXT ───────────────
    try {
        $db->exec("ALTER TABLE `activity_log` MODIFY COLUMN `details` TEXT NULL");
    } catch (Exception $e) { /* already correct */ }

    // ── GET ──────────────────────────────────────────────────────
    if ($method === 'GET') {
        $where  = ['1=1'];
        $params = [];

        if (!empty($_GET['action'])) {
            $where[] = 'action = :action';
            $params[':action'] = $_GET['action'];
        }
        if (!empty($_GET['entity_type'])) {
            $where[] = 'entity_type = :entity_type';
            $params[':entity_type'] = $_GET['entity_type'];
        }
        if (!empty($_GET['entity_id'])) {
            $where[] = 'entity_id = :entity_id';
            $params[':entity_id'] = (int)$_GET['entity_id'];
        }
        if (!empty($_GET['user_id'])) {
            $where[] = 'user_id = :user_id';
            $params[':user_id'] = (int)$_GET['user_id'];
        }
        if (!empty($_GET['from'])) {
            $where[] = 'DATE(created_at) >= :from';
            $params[':from'] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $where[] = 'DATE(created_at) <= :to';
            $params[':to'] = $_GET['to'];
        }
        if (!empty($_GET['search'])) {
            $where[] = '(action LIKE :s OR entity_type LIKE :s2 OR details LIKE :s3)';
            $params[':s']  = '%' . $_GET['search'] . '%';
            $params[':s2'] = '%' . $_GET['search'] . '%';
            $params[':s3'] = '%' . $_GET['search'] . '%';
        }

        $limit  = min((int)($_GET['limit']  ?? 200), 500);
        $offset = max((int)($_GET['offset'] ?? 0), 0);

        $sql = "SELECT
                    id,
                    user_id,
                    action,
                    entity_type,
                    entity_id,
                    details,
                    ip_address,
                    DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%s+05:30') AS created_at
                FROM activity_log
                WHERE " . implode(' AND ', $where) .
               ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Total count for pagination
        $cStmt = $db->prepare('SELECT COUNT(*) FROM activity_log WHERE ' . implode(' AND ', $where));
        $cStmt->execute($params);
        $total = (int)$cStmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'data'    => $rows,
            'total'   => $total,
            'limit'   => $limit,
            'offset'  => $offset
        ]);
        exit;
    }

    // ── Read body ────────────────────────────────────────────────
    $body = [];
    if (in_array($method, ['POST', 'PUT'])) {
        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: [];
        if (empty($body)) $body = $_POST;
    }

    // ── POST: append log entry manually ──────────────────────────
    if ($method === 'POST') {
        $action     = trim($body['action']      ?? '');
        $entityType = trim($body['entity_type'] ?? '');
        $entityId   = (int)($body['entity_id']  ?? 0);
        $details    = trim($body['details']      ?? '');

        if (!$action || !$entityType) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'action and entity_type are required']);
            exit;
        }

        $user = currentUser();
        $uid  = $user['id'] ?? $_SESSION['user_id'] ?? null;
        $ip   = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt = $db->prepare(
            'INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$uid, $action, $entityType, $entityId ?: null, $details, $ip]);

        echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);
        exit;
    }

    // ── DELETE: clear log (admin only) ────────────────────────────
    if ($method === 'DELETE') {
        // Only admins can clear the entire log
        $user = currentUser();
        if (($user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin only']);
            exit;
        }
        $db->exec('DELETE FROM activity_log');
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);

} catch (Exception $e) {
    error_log('activity.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}