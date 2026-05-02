<?php
// ================================================================
//  api/credit_notes.php — Credit Notes CRUD
//
//  GET    /api/credit_notes.php           → list all
//  GET    /api/credit_notes.php?id=X      → single
//  POST   /api/credit_notes.php           → create
//  PUT    /api/credit_notes.php?id=X      → full update
//  PATCH  /api/credit_notes.php?id=X      → partial update (status)
//  DELETE /api/credit_notes.php?id=X      → delete
// ================================================================
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── Auto-migrate: create table if not yet present ──────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `credit_notes` (
        `id`              INT(11)        NOT NULL AUTO_INCREMENT,
        `cn_number`       VARCHAR(50)    NOT NULL,
        `invoice_id`      INT(11)        NULL DEFAULT NULL,
        `invoice_number`  VARCHAR(50)    NOT NULL DEFAULT '',
        `client_name`     VARCHAR(200)   NOT NULL DEFAULT '',
        `amount`          DECIMAL(12,2)  NOT NULL DEFAULT 0,
        `issued_date`     DATE           NULL,
        `reason`          TEXT           NOT NULL,
        `notes`           TEXT           NULL,
        `status`          ENUM('Draft','Issued','Applied','Void') NOT NULL DEFAULT 'Draft',
        `created_by`      INT(11)        NULL,
        `created_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_cn_number` (`cn_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* table already exists */ }

function nullIfEmpty($v) { return ($v === '' || $v === null) ? null : $v; }

function generateCNNumber($db) {
    $year = date('Y');
    $pfx  = 'CN-' . $year . '-';
    $like = $pfx . '%';
    $row  = $db->prepare("SELECT cn_number FROM credit_notes WHERE cn_number LIKE ? ORDER BY LENGTH(cn_number) DESC, cn_number DESC LIMIT 1");
    $row->execute([$like]);
    $last = $row->fetchColumn();
    if ($last) {
        preg_match('/(\d+)$/', $last, $m);
        $cnt = isset($m[1]) ? ((int)$m[1] + 1) : 1;
    } else {
        $cnt = 1;
    }
    $num = $pfx . str_pad($cnt, 3, '0', STR_PAD_LEFT);
    // Collision safety
    $check = $db->prepare('SELECT id FROM credit_notes WHERE cn_number = ?');
    $check->execute([$num]);
    while ($check->fetch()) {
        $cnt++;
        $num = $pfx . str_pad($cnt, 3, '0', STR_PAD_LEFT);
        $check->execute([$num]);
    }
    return $num;
}

switch ($method) {

    // ── GET ──────────────────────────────────────────────────────
    case 'GET':
        if (!empty($_GET['id'])) {
            $s = $db->prepare('SELECT * FROM credit_notes WHERE id = ?');
            $s->execute([(int)$_GET['id']]);
            $row = $s->fetch();
            if (!$row) jsonResponse(['error' => 'Not found'], 404);
            jsonResponse(['data' => $row]);
        }
        $where = ['1=1']; $params = [];
        if (!empty($_GET['status']))    { $where[] = 'status = ?';       $params[] = $_GET['status']; }
        if (!empty($_GET['client']))    { $where[] = 'client_name LIKE ?'; $params[] = '%'.$_GET['client'].'%'; }
        if (!empty($_GET['invoice_id'])){ $where[] = 'invoice_id = ?';   $params[] = (int)$_GET['invoice_id']; }
        $s = $db->prepare('SELECT * FROM credit_notes WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC');
        $s->execute($params);
        $rows = $s->fetchAll();
        foreach ($rows as &$r) {
            $r['amount'] = (float)$r['amount'];
        }
        jsonResponse(['data' => $rows]);

    // ── POST: create ─────────────────────────────────────────────
    case 'POST':
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

        $allowedStatuses = ['Draft','Issued','Applied','Void'];
        $status = in_array($d['status']??'Draft', $allowedStatuses, true) ? $d['status'] : 'Draft';
        $cnNum  = generateCNNumber($db);

        $s = $db->prepare('INSERT INTO credit_notes
            (cn_number, invoice_id, invoice_number, client_name, amount, issued_date, reason, notes, status, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?)');
        try {
            $s->execute([
                $cnNum,
                nullIfEmpty($d['invoice_id'] ?? null),
                $d['invoice_number'] ?? '',
                $d['client_name']    ?? '',
                floatval($d['amount'] ?? 0),
                nullIfEmpty($d['issued_date'] ?? null),
                $d['reason'] ?? '',
                $d['notes']  ?? '',
                $status,
                (int)$_SESSION['user_id'],
            ]);
        } catch (\PDOException $e) {
            error_log('CN INSERT error: ' . $e->getMessage());
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
        $id = (int)$db->lastInsertId();
        logActivity((int)$_SESSION['user_id'], 'create', 'credit_note', $id, "Created credit note $cnNum");
        jsonResponse(['success' => true, 'id' => $id, 'cn_number' => $cnNum]);

    // ── PUT: full update ─────────────────────────────────────────
    case 'PUT':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $d = json_decode(file_get_contents('php://input'), true);
        $allowedStatuses = ['Draft','Issued','Applied','Void'];
        $status = in_array($d['status']??'Draft', $allowedStatuses, true) ? $d['status'] : 'Draft';
        $s = $db->prepare('UPDATE credit_notes SET
            invoice_id=?, invoice_number=?, client_name=?, amount=?,
            issued_date=?, reason=?, notes=?, status=? WHERE id=?');
        try {
            $s->execute([
                nullIfEmpty($d['invoice_id'] ?? null),
                $d['invoice_number'] ?? '',
                $d['client_name']    ?? '',
                floatval($d['amount'] ?? 0),
                nullIfEmpty($d['issued_date'] ?? null),
                $d['reason'] ?? '',
                $d['notes']  ?? '',
                $status,
                $id,
            ]);
        } catch (\PDOException $e) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
        logActivity((int)$_SESSION['user_id'], 'update', 'credit_note', $id, "Updated credit note #$id");
        jsonResponse(['success' => true]);

    // ── PATCH: status only ───────────────────────────────────────
    case 'PATCH':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $d = json_decode(file_get_contents('php://input'), true);
        $allowedStatuses = ['Draft','Issued','Applied','Void'];
        if (!isset($d['status']) || !in_array($d['status'], $allowedStatuses, true)) {
            jsonResponse(['error' => 'Invalid status'], 400);
        }
        $db->prepare('UPDATE credit_notes SET status=? WHERE id=?')->execute([$d['status'], $id]);
        logActivity((int)$_SESSION['user_id'], 'update', 'credit_note', $id, "Status → {$d['status']} for CN #$id");
        jsonResponse(['success' => true]);

    // ── DELETE ───────────────────────────────────────────────────
    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $db->prepare('DELETE FROM credit_notes WHERE id=?')->execute([$id]);
        logActivity((int)$_SESSION['user_id'], 'delete', 'credit_note', $id, "Deleted CN #$id");
        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
