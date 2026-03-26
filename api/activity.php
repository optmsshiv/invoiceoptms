<?php
// ================================================================
//  api/activity.php  — Activity / Audit Log
//
//  GET    /api/activity.php               → list log (filters via QS)
//  POST   /api/activity.php               → append entry
//  DELETE /api/activity.php               → clear all (admin only)
//
//  Query params for GET:
//    ?type=invoice_created               filter by event type
//    ?from=YYYY-MM-DD&to=YYYY-MM-DD      date range
//    ?invoice_id=X                       events for one invoice
//    ?limit=100&offset=0                 pagination (default limit 100)
// ================================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();

    // ── GET ──────────────────────────────────────────────────────
    if ($method === 'GET') {
        $where  = ['1=1'];
        $params = [];

        if (!empty($_GET['type'])) {
            $where[] = 'type = :type';
            $params[':type'] = $_GET['type'];
        }
        if (!empty($_GET['invoice_id'])) {
            $where[] = 'invoice_id = :inv';
            $params[':inv'] = (int)$_GET['invoice_id'];
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
            $where[] = '(label LIKE :s OR detail LIKE :s2)';
            $params[':s']  = '%' . $_GET['search'] . '%';
            $params[':s2'] = '%' . $_GET['search'] . '%';
        }

        $limit  = min((int)($_GET['limit']  ?? 200), 500);
        $offset = max((int)($_GET['offset'] ?? 0), 0);

        $sql  = 'SELECT * FROM activity_log WHERE ' . implode(' AND ', $where)
              . ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Total count for pagination
        $cSql   = 'SELECT COUNT(*) FROM activity_log WHERE ' . implode(' AND ', $where);
        $cStmt  = $db->prepare($cSql);
        $cStmt->execute($params);
        $total = (int)$cStmt->fetchColumn();

        echo json_encode(['success'=>true,'data'=>$rows,'total'=>$total,'limit'=>$limit,'offset'=>$offset]);
        exit;
    }

    // ── Read body ────────────────────────────────────────────────
    $body = [];
    if (in_array($method, ['POST','PUT'])) {
        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: [];
        if (empty($body)) $body = $_POST;
    }

    // ── POST: append log entry ────────────────────────────────────
    if ($method === 'POST') {
        $type  = trim($body['type']  ?? '');
        $label = trim($body['label'] ?? '');
        if (!$type || !$label) {
            http_response_code(422);
            echo json_encode(['success'=>false,'error'=>'type and label are required']);
            exit;
        }
        $user  = currentUser();
        $uid   = $user['id'] ?? null;
        $ip    = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt  = $db->prepare(
            'INSERT INTO activity_log (type, label, detail, invoice_id, user_id, ip)
             VALUES (:type, :label, :detail, :inv, :uid, :ip)'
        );
        $stmt->execute([
            ':type'   => $type,
            ':label'  => $label,
            ':detail' => $body['detail']     ?? '',
            ':inv'    => !empty($body['invoice_id']) ? (int)$body['invoice_id'] : null,
            ':uid'    => $uid,
            ':ip'     => $ip,
        ]);
        echo json_encode(['success'=>true,'id'=>(int)$db->lastInsertId()]);
        exit;
    }

    // ── DELETE: clear log ─────────────────────────────────────────
    if ($method === 'DELETE') {
        $db->exec('DELETE FROM activity_log');
        echo json_encode(['success'=>true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);

} catch (Exception $e) {
    error_log('activity.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error']);
}
