<?php
// ================================================================
//  api/expenses.php  — Expense Tracker CRUD
//  GET    /api/expenses.php              → list all expenses
//  GET    /api/expenses.php?id=X         → single expense
//  POST   /api/expenses.php              → create expense
//  PUT    /api/expenses.php?id=X         → replace expense
//  DELETE /api/expenses.php?id=X         → delete expense
// ================================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $db = getDB();

    // ── helper: log to activity_log ──────────────────────────────
    function logActivity(PDO $db, string $type, string $label, string $detail = '', ?int $invoiceId = null): void {
        $user = currentUser();
        $uid  = $user['id'] ?? null;
        $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $db->prepare(
            'INSERT INTO activity_log (type, label, detail, invoice_id, user_id, ip)
             VALUES (:type, :label, :detail, :inv, :uid, :ip)'
        );
        $stmt->execute([':type'=>$type,':label'=>$label,':detail'=>$detail,':inv'=>$invoiceId,':uid'=>$uid,':ip'=>$ip]);
    }

    // ── GET ──────────────────────────────────────────────────────
    if ($method === 'GET') {
        if ($id) {
            $stmt = $db->prepare('SELECT * FROM expenses WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($row ? ['success'=>true,'data'=>$row] : ['success'=>false,'error'=>'Not found']);
        } else {
            // Filters: ?category=X&month=YYYY-MM&from=YYYY-MM-DD&to=YYYY-MM-DD
            $where = ['1=1'];
            $params = [];
            if (!empty($_GET['category'])) {
                $where[] = 'category = :cat';
                $params[':cat'] = $_GET['category'];
            }
            if (!empty($_GET['month'])) {
                $where[] = "DATE_FORMAT(`date`, '%Y-%m') = :month";
                $params[':month'] = $_GET['month'];
            }
            if (!empty($_GET['from'])) {
                $where[] = '`date` >= :from';
                $params[':from'] = $_GET['from'];
            }
            if (!empty($_GET['to'])) {
                $where[] = '`date` <= :to';
                $params[':to'] = $_GET['to'];
            }
            $sql  = 'SELECT * FROM expenses WHERE ' . implode(' AND ', $where) . ' ORDER BY `date` DESC, id DESC';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true,'data'=>$rows,'count'=>count($rows)]);
        }
        exit;
    }

    // ── Read body for POST / PUT ──────────────────────────────────
    $body = [];
    if (in_array($method, ['POST','PUT','PATCH'])) {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: [];
        if (empty($body)) $body = $_POST;
    }

    // ── POST — create ─────────────────────────────────────────────
    if ($method === 'POST') {
        $date     = trim($body['date']     ?? '');
        $category = trim($body['category'] ?? 'Other');
        $vendor   = trim($body['vendor']   ?? '');
        $amount   = (float)($body['amount'] ?? 0);
        $method2  = trim($body['method']   ?? 'UPI');
        $notes    = trim($body['notes']    ?? '');

        if (!$date || !$vendor || $amount <= 0) {
            http_response_code(422);
            echo json_encode(['success'=>false,'error'=>'date, vendor, and amount are required']);
            exit;
        }

        $stmt = $db->prepare(
            'INSERT INTO expenses (`date`, category, vendor, amount, method, notes)
             VALUES (:date, :cat, :vendor, :amount, :method, :notes)'
        );
        $stmt->execute([':date'=>$date,':cat'=>$category,':vendor'=>$vendor,':amount'=>$amount,':method'=>$method2,':notes'=>$notes]);
        $newId = $db->lastInsertId();

        logActivity($db, 'expense_added', "Expense added: $vendor", '₹'.number_format($amount,2));
        echo json_encode(['success'=>true,'id'=>(int)$newId]);
        exit;
    }

    // ── PUT — full replace ────────────────────────────────────────
    if ($method === 'PUT' && $id) {
        $date     = trim($body['date']     ?? '');
        $category = trim($body['category'] ?? 'Other');
        $vendor   = trim($body['vendor']   ?? '');
        $amount   = (float)($body['amount'] ?? 0);
        $method2  = trim($body['method']   ?? 'UPI');
        $notes    = trim($body['notes']    ?? '');

        if (!$date || !$vendor || $amount <= 0) {
            http_response_code(422);
            echo json_encode(['success'=>false,'error'=>'date, vendor, and amount are required']);
            exit;
        }

        $stmt = $db->prepare(
            'UPDATE expenses SET `date`=:date, category=:cat, vendor=:vendor,
             amount=:amount, method=:method, notes=:notes WHERE id=:id'
        );
        $stmt->execute([':date'=>$date,':cat'=>$category,':vendor'=>$vendor,':amount'=>$amount,':method'=>$method2,':notes'=>$notes,':id'=>$id]);

        logActivity($db, 'expense_added', "Expense edited: $vendor", '₹'.number_format($amount,2));
        echo json_encode(['success'=>true]);
        exit;
    }

    // ── DELETE ────────────────────────────────────────────────────
    if ($method === 'DELETE' && $id) {
        $stmt = $db->prepare('SELECT vendor, amount FROM expenses WHERE id=:id');
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare('DELETE FROM expenses WHERE id = :id');
        $stmt->execute([':id' => $id]);

        if ($row) logActivity($db, 'expense_added', "Expense deleted: {$row['vendor']}", '₹'.number_format($row['amount'],2));
        echo json_encode(['success'=>true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);

} catch (Exception $e) {
    error_log('expenses.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error']);
}
