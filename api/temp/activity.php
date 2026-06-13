<?php
// ================================================================
//  api/activity.php  — Activity / Audit Log
//
//  Reads/writes `activity_log` table (written by logActivity() in auth.php).
//
//  The frontend uses: type, label, detail, invoice_id
//  The DB table uses: action, entity_type, entity_id, details, ip_address
//
//  This file maps between the two transparently so index.php needs no changes.
//
//  GET    /api/activity.php               → list log (filters via QS)
//  POST   /api/activity.php               → append entry
//  DELETE /api/activity.php               → clear all (admin only)
//
//  Query params for GET:
//    ?type=invoice_created               filter by action
//    ?from=YYYY-MM-DD&to=YYYY-MM-DD      date range
//    ?invoice_id=X                       events for one invoice (entity_id)
//    ?limit=100&offset=0                 pagination (default 200)
//    ?search=text                        search in details
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

        // frontend sends ?type=  → maps to action column
        if (!empty($_GET['type'])) {
            $where[] = 'action = :action';
            $params[':action'] = $_GET['type'];
        }
        // frontend sends ?invoice_id=  → maps to entity_id column
        if (!empty($_GET['invoice_id'])) {
            $where[] = 'entity_id = :entity_id';
            $params[':entity_id'] = (int)$_GET['invoice_id'];
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

        // Map DB columns → frontend field names
        $sql = "SELECT
                    id,
                    user_id,
                    action      AS type,
                    entity_type AS label,
                    details     AS detail,
                    entity_id   AS invoice_id,
                    ip_address  AS ip,
                    DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%s+05:30') AS created_at
                FROM activity_log
                WHERE " . implode(' AND ', $where) .
               ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Total count
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

    // ── POST: append log entry ────────────────────────────────────
    if ($method === 'POST') {
        // Accept both frontend naming (type/label/detail/invoice_id)
        // and direct naming (action/entity_type/details/entity_id)
        $action     = trim($body['type']        ?? $body['action']      ?? '');
        $entityType = trim($body['label']       ?? $body['entity_type'] ?? '');
        $details    = trim($body['detail']      ?? $body['details']     ?? '');
        $entityId   = (int)($body['invoice_id'] ?? $body['entity_id']   ?? 0);

        if (!$action) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'type/action is required']);
            exit;
        }

        $user = currentUser();
        $uid  = $user['id'] ?? $_SESSION['user_id'] ?? null;
        $ip   = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt = $db->prepare(
            'INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $uid,
            $action,
            $entityType ?: null,
            $entityId ?: null,
            $details,
            $ip
        ]);

        echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);
        exit;
    }

    // ── DELETE: clear log (admin only) ────────────────────────────
    if ($method === 'DELETE') {
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