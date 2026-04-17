<?php
// ================================================================
//  OPTMS Tech Invoice Manager — api/recurring.php
//  Handles: GET / POST / PUT / PATCH / DELETE
//  for recurring_schedules table
// ================================================================

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// ── Auth guard ─────────────────────────────────────────────────
requireLogin();
$user = currentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated']);
    exit;
}

// ── Only accept XHR ────────────────────────────────────────────
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db     = getDB();

// ── Helper: read JSON body ─────────────────────────────────────
function getBody(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// ── Helper: sanitise a row coming out of the DB ────────────────
function normalizeRow(array $row): array {
    // Cast numerics
    $row['id']              = (int)$row['id'];
    $row['client_id']       = (int)$row['client_id'];
    $row['amount']          = (float)$row['amount'];
    $row['discount_pct']    = (float)$row['discount_pct'];
    $row['discount_amt']    = (float)$row['discount_amt'];
    $row['disc_val']        = (float)($row['disc_val'] ?? 0);
    $row['gst']             = (float)$row['gst'];
    $row['gst_amt']         = (float)$row['gst_amt'];
    $row['grand_total']     = (float)$row['grand_total'];
    $row['due_days']        = (int)$row['due_days'];
    $row['template_id']     = (int)$row['template_id'];
    $row['generated_count'] = (int)$row['generated_count'];

    // Decode items JSON → array (fallback to empty array)
    if (isset($row['items'])) {
        $decoded = json_decode($row['items'], true);
        $row['items'] = is_array($decoded) ? $decoded : [];
    } else {
        $row['items'] = [];
    }

    return $row;
}

// ==============================================================
//  GET — list all schedules (with optional ?id=N for single)
// ==============================================================
if ($method === 'GET') {
    try {
        if ($id) {
            $stmt = $db->prepare(
                'SELECT r.*, c.name AS client_name_joined
                   FROM recurring_schedules r
                   LEFT JOIN clients c ON c.id = r.client_id
                  WHERE r.id = ?'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) {
                http_response_code(404);
                echo json_encode(['error' => 'Schedule not found']);
                exit;
            }
            echo json_encode(['ok' => true, 'data' => normalizeRow($row)]);
        } else {
            $stmt = $db->query(
                'SELECT r.*, c.name AS client_name_joined
                   FROM recurring_schedules r
                   LEFT JOIN clients c ON c.id = r.client_id
                  ORDER BY r.id DESC'
            );
            $rows = $stmt->fetchAll();
            echo json_encode(['ok' => true, 'data' => array_map('normalizeRow', $rows)]);
        }
    } catch (Exception $e) {
        error_log('recurring GET error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch schedules']);
    }
    exit;
}

// ==============================================================
//  POST — create a new schedule
// ==============================================================
if ($method === 'POST') {
    $b = getBody();

    // ── Validate required fields ───────────────────────────────
    $clientId = isset($b['clientId']) ? (int)$b['clientId'] : 0;
    if (!$clientId) {
        http_response_code(422);
        echo json_encode(['error' => 'client_id is required']);
        exit;
    }
    $freq = trim($b['freq'] ?? 'monthly');
    if (!in_array($freq, ['weekly','biweekly','monthly','quarterly','halfyearly','yearly'], true)) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid frequency']);
        exit;
    }
    $nextDate = trim($b['nextDate'] ?? $b['next_date'] ?? '');
    if (!$nextDate || !strtotime($nextDate)) {
        http_response_code(422);
        echo json_encode(['error' => 'next_date is required']);
        exit;
    }

    // ── Resolve client_name ────────────────────────────────────
    $clientName = trim($b['clientName'] ?? $b['client_name'] ?? '');
    if (!$clientName) {
        $cs = $db->prepare('SELECT name FROM clients WHERE id = ?');
        $cs->execute([$clientId]);
        $cr = $cs->fetch();
        $clientName = $cr ? $cr['name'] : '';
    }

    // ── Pull all fields ────────────────────────────────────────
    $service     = trim($b['service']  ?? '');
    $amount      = (float)($b['amount']     ?? 0);
    $discType    = in_array($b['discType'] ?? 'pct', ['pct','fixed'], true) ? $b['discType'] : 'pct';
    $discVal     = (float)($b['discVal']    ?? 0);
    $discPct     = (float)($b['discPct']    ?? $b['discount_pct'] ?? 0);
    $discAmt     = (float)($b['discAmt']    ?? $b['discount_amt'] ?? 0);
    $gst         = (float)($b['gst']        ?? 0);
    $gstAmt      = (float)($b['gstAmt']     ?? $b['gst_amt']      ?? 0);
    $grand       = (float)($b['grand']      ?? $b['grand_total']  ?? 0);
    $endDate     = trim($b['endDate']   ?? $b['end_date']   ?? '') ?: null;
    $dueDays     = (int)($b['dueDays']  ?? $b['due_days']  ?? 15);
    $templateId  = (int)($b['template'] ?? $b['template_id'] ?? 1);
    $notes       = trim($b['notes']     ?? '');
    $items       = isset($b['items']) && is_array($b['items']) ? $b['items'] : [];
    $itemsJson   = json_encode($items);

    try {
        $stmt = $db->prepare(
            'INSERT INTO recurring_schedules
               (client_id, client_name, service, amount,
                discount_pct, discount_amt, disc_type, disc_val,
                gst, gst_amt, grand_total, items,
                freq, next_date, end_date, due_days,
                template_id, notes, status, generated_count, created_at)
             VALUES
               (?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, \'active\', 0, NOW())'
        );
        $stmt->execute([
            $clientId,   $clientName, $service,  $amount,
            $discPct,    $discAmt,    $discType, $discVal,
            $gst,        $gstAmt,     $grand,    $itemsJson,
            $freq,       $nextDate,   $endDate,  $dueDays,
            $templateId, $notes,
        ]);

        $newId = (int)$db->lastInsertId();
        echo json_encode(['ok' => true, 'id' => $newId, 'message' => 'Schedule created']);
    } catch (Exception $e) {
        error_log('recurring POST error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create schedule: ' . $e->getMessage()]);
    }
    exit;
}

// ==============================================================
//  PUT — full update of an existing schedule (by ?id=N)
// ==============================================================
if ($method === 'PUT') {
    if (!$id) {
        http_response_code(422);
        echo json_encode(['error' => 'id param required for PUT']);
        exit;
    }
    $b = getBody();

    $clientId   = isset($b['clientId'])   ? (int)$b['clientId']   : (int)($b['client_id'] ?? 0);
    $clientName = trim($b['clientName']   ?? $b['client_name'] ?? '');
    $service    = trim($b['service']      ?? '');
    $amount     = (float)($b['amount']    ?? 0);
    $discType   = in_array($b['discType'] ?? 'pct', ['pct','fixed'], true) ? $b['discType'] : 'pct';
    $discVal    = (float)($b['discVal']   ?? 0);
    $discPct    = (float)($b['discPct']   ?? $b['discount_pct'] ?? 0);
    $discAmt    = (float)($b['discAmt']   ?? $b['discount_amt'] ?? 0);
    $gst        = (float)($b['gst']       ?? 0);
    $gstAmt     = (float)($b['gstAmt']    ?? $b['gst_amt']     ?? 0);
    $grand      = (float)($b['grand']     ?? $b['grand_total'] ?? 0);
    $freq       = trim($b['freq']         ?? 'monthly');
    $nextDate   = trim($b['nextDate']     ?? $b['next_date']  ?? '');
    $endDate    = trim($b['endDate']      ?? $b['end_date']   ?? '') ?: null;
    $dueDays    = (int)($b['dueDays']     ?? $b['due_days']   ?? 15);
    $templateId = (int)($b['template']    ?? $b['template_id'] ?? 1);
    $notes      = trim($b['notes']        ?? '');
    $items      = isset($b['items']) && is_array($b['items']) ? $b['items'] : [];
    $itemsJson  = json_encode($items);

    if (!$clientId || !$nextDate) {
        http_response_code(422);
        echo json_encode(['error' => 'client_id and next_date are required']);
        exit;
    }

    try {
        $stmt = $db->prepare(
            'UPDATE recurring_schedules SET
               client_id      = ?, client_name   = ?, service    = ?, amount      = ?,
               discount_pct   = ?, discount_amt  = ?, disc_type  = ?, disc_val    = ?,
               gst            = ?, gst_amt       = ?, grand_total = ?, items       = ?,
               freq           = ?, next_date     = ?, end_date   = ?, due_days    = ?,
               template_id    = ?, notes         = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $clientId,   $clientName, $service,    $amount,
            $discPct,    $discAmt,    $discType,   $discVal,
            $gst,        $gstAmt,     $grand,      $itemsJson,
            $freq,       $nextDate,   $endDate,    $dueDays,
            $templateId, $notes,
            $id,
        ]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Schedule not found']);
            exit;
        }
        echo json_encode(['ok' => true, 'message' => 'Schedule updated']);
    } catch (Exception $e) {
        error_log('recurring PUT error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update schedule']);
    }
    exit;
}

// ==============================================================
//  PATCH — partial update: status, nextDate, generatedCount, lastGenerated
//  Used by: pause/resume toggle + runRecurringCheck() after generation
// ==============================================================
if ($method === 'PATCH') {
    if (!$id) {
        http_response_code(422);
        echo json_encode(['error' => 'id param required for PATCH']);
        exit;
    }
    $b = getBody();

    // Build dynamic SET clause — only update what was sent
    $allowed = [
        'status'          => 'status',
        'next_date'       => 'next_date',
        'nextDate'        => 'next_date',
        'generated_count' => 'generated_count',
        'generatedCount'  => 'generated_count',
        'last_generated'  => 'last_generated',
        'lastGenerated'   => 'last_generated',
    ];

    $setClauses = [];
    $params     = [];

    foreach ($allowed as $jsKey => $dbCol) {
        if (array_key_exists($jsKey, $b)) {
            // Avoid duplicate columns if both camelCase and snake_case sent
            $setClauses[$dbCol] = "$dbCol = ?";
            $val = $b[$jsKey];
            // Validate status
            if ($dbCol === 'status' && !in_array($val, ['active','paused','completed'], true)) {
                http_response_code(422);
                echo json_encode(['error' => 'Invalid status value']);
                exit;
            }
            $params[$dbCol] = $val;
        }
    }

    if (empty($setClauses)) {
        http_response_code(422);
        echo json_encode(['error' => 'No valid fields to update']);
        exit;
    }

    // Preserve column order for PDO positional binding
    $setStr = implode(', ', $setClauses);
    $vals   = array_values($params);
    $vals[] = $id;

    try {
        $stmt = $db->prepare("UPDATE recurring_schedules SET $setStr WHERE id = ?");
        $stmt->execute($vals);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Schedule not found or no change']);
            exit;
        }
        echo json_encode(['ok' => true, 'message' => 'Schedule patched']);
    } catch (Exception $e) {
        error_log('recurring PATCH error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to patch schedule']);
    }
    exit;
}

// ==============================================================
//  DELETE — remove a schedule by ?id=N
// ==============================================================
if ($method === 'DELETE') {
    if (!$id) {
        http_response_code(422);
        echo json_encode(['error' => 'id param required for DELETE']);
        exit;
    }
    try {
        $stmt = $db->prepare('DELETE FROM recurring_schedules WHERE id = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Schedule not found']);
            exit;
        }
        echo json_encode(['ok' => true, 'message' => 'Schedule deleted']);
    } catch (Exception $e) {
        error_log('recurring DELETE error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete schedule']);
    }
    exit;
}

// ── Fallback for unsupported methods ───────────────────────────
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
