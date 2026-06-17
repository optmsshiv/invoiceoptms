<?php
// ================================================================
//  api/wa_log.php  — WhatsApp Message Log (DB persistence)
//  UPDATED: Timezone fix, proper ordering, security improvements
//
//  GET    /api/wa_log.php              → fetch recent log (newest first, max 500)
//  POST   /api/wa_log.php              → append a log entry
//  DELETE /api/wa_log.php              → clear all log entries (with confirmation)
// ================================================================

// ── SET TIMEZONE TO INDIA STANDARD TIME (IST) ───────────────────
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$user   = currentUser();

const WA_ALLOWED_TYPES = [
    'invoice_created', 'estimate_created', 'payment_received', 'partial_payment',
    'split_payment', 'payment_overdue', 'payment_reminder', 'invoice_followup',
    'balance_reminder', 'festival', 'unknown'
];
const WA_ALLOWED_STATUSES = ['sending', 'sent_api', 'sent_web', 'failed'];

try {
    $db = getDB();
    
    // ── SET MYSQL TIMEZONE TO IST (+05:30) ──────────────────────
    $db->exec("SET time_zone = '+05:30'");

    // ── Auto-create table if migration not run ───────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS `wa_message_log` (
        `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `entry_id`   VARCHAR(40)   NOT NULL,
        `ts`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `type`       VARCHAR(40)   NOT NULL DEFAULT 'unknown',
        `status`     VARCHAR(20)   NOT NULL DEFAULT 'sent_web',
        `client`     VARCHAR(200)  NULL,
        `phone`      VARCHAR(30)   NULL,
        `inv_id`     VARCHAR(20)   NULL,
        `inv_num`    VARCHAR(40)   NULL,
        `inv_amt`    VARCHAR(30)   NULL,
        `inv_status` VARCHAR(30)   NULL,
        `msg`        TEXT          NULL,
        `error`      VARCHAR(500)  NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_entry_id` (`entry_id`),
        INDEX `idx_wa_log_ts_id` (`ts` DESC, `id` DESC),
        INDEX `idx_wa_log_inv` (`inv_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── GET: fetch log ───────────────────────────────────────────
    if ($method === 'GET') {
        // ✅ FIXED: Order by ts DESC (newest first), then by id DESC for consistent ordering
        $stmt = $db->query(
            'SELECT 
                entry_id AS id, 
                DATE_FORMAT(ts, "%Y-%m-%d %H:%i:%s") as ts,
                type, 
                status, 
                client, 
                phone,
                inv_id, 
                inv_num, 
                inv_amt, 
                inv_status, 
                msg, 
                error
             FROM wa_message_log
             ORDER BY ts DESC, id DESC
             LIMIT 500'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'success'  => true,
            'timezone' => 'Asia/Kolkata (IST, UTC+05:30)',
            'count'    => count($rows),
            'data'     => $rows
        ]);
        exit;
    }

    // ── Read body ────────────────────────────────────────────────
    $body = [];
    if (in_array($method, ['POST', 'DELETE'])) {
        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: [];
    }

    // ── POST: save or update a log entry ────────────────────────
    if ($method === 'POST') {
        $type   = in_array($body['type']   ?? '', WA_ALLOWED_TYPES)    ? $body['type']   : 'unknown';
        $status = in_array($body['status'] ?? '', WA_ALLOWED_STATUSES) ? $body['status'] : 'sent_web';

        $entryId = substr($body['id'] ?? '', 0, 40);
        if (!$entryId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing entry id']);
            exit;
        }

        // If status is not 'sending', try to update existing 'sending' row first
        if ($status !== 'sending') {
            $upd = $db->prepare(
                'UPDATE wa_message_log
                 SET status = :status, error = :error
                 WHERE entry_id = :eid AND status = "sending"'
            );
            $upd->execute([
                ':status' => $status,
                ':error'  => substr($body['error'] ?? '', 0, 500),
                ':eid'    => $entryId,
            ]);
            if ($upd->rowCount() > 0) {
                echo json_encode([
                    'success'  => true, 
                    'updated'  => true,
                    'timezone' => 'Asia/Kolkata (IST)'
                ]);
                exit;
            }
        }

        // Insert new row (INSERT IGNORE to handle race conditions)
        // ✅ FIXED: Using date() which now respects Asia/Kolkata timezone
        $stmt = $db->prepare(
            'INSERT IGNORE INTO wa_message_log
               (entry_id, ts, type, status, client, phone, inv_id, inv_num, inv_amt, inv_status, msg, error)
             VALUES
               (:eid, :ts, :type, :status, :client, :phone, :inv_id, :inv_num, :inv_amt, :inv_status, :msg, :error)'
        );
        
        // ✅ Get current time in IST
        $currentTime = date('Y-m-d H:i:s');
        
        $stmt->execute([
            ':eid'        => $entryId,
            ':ts'         => !empty($body['ts'])
                                ? date('Y-m-d H:i:s', strtotime($body['ts']))
                                : $currentTime,  // ✅ NOW USES IST
            ':type'       => $type,
            ':status'     => $status,
            ':client'     => substr($body['client']     ?? '', 0, 200),
            ':phone'      => substr($body['phone']      ?? '', 0, 30),
            ':inv_id'     => substr($body['inv_id']     ?? '', 0, 20),
            ':inv_num'    => substr($body['inv_num']    ?? '', 0, 40),
            ':inv_amt'    => substr($body['inv_amt']    ?? '', 0, 30),
            ':inv_status' => substr($body['inv_status'] ?? '', 0, 30),
            ':msg'        => $body['msg']   ?? '',
            ':error'      => substr($body['error'] ?? '', 0, 500),
        ]);

        echo json_encode([
            'success'  => true,
            'id'       => (int)$db->lastInsertId(),
            'timezone' => 'Asia/Kolkata (IST)',
            'ts'       => $currentTime
        ]);
        exit;
    }

    // ── DELETE: single entry or clear all ───────────────────────
    if ($method === 'DELETE') {
        // ── Single entry delete ──────────────────────────────────
        if (!empty($body['entry_id'])) {
            $entryId = substr($body['entry_id'], 0, 40);
            $db->prepare("DELETE FROM wa_message_log WHERE entry_id = ?")->execute([$entryId]);
            echo json_encode(['success' => true, 'deleted' => $entryId]);
            exit;
        }

        // ── Clear all with confirmation ──────────────────────────
        $confirmCode = $body['confirm_code'] ?? '';
        $expectedCode = 'CLEAR_WA_LOG_' . date('Y-m-d');
        
        if ($confirmCode !== $expectedCode) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error'   => 'Deletion confirmation failed',
                'hint'    => 'Send confirm_code: ' . $expectedCode
            ]);
            exit;
        }

        // ✅ Log deletion action for audit trail
        error_log('[WA_LOG_DELETE] User: ' . ($user['email'] ?? 'unknown') . 
                  ' | Time: ' . date('Y-m-d H:i:s') . 
                  ' | IP: ' . $_SERVER['REMOTE_ADDR']);

        $db->exec('DELETE FROM wa_message_log');
        echo json_encode([
            'success'  => true,
            'message'  => 'All WhatsApp message logs cleared',
            'timezone' => 'Asia/Kolkata (IST)',
            'cleared_at' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);

} catch (Exception $e) {
    error_log('wa_log.php error: ' . $e->getMessage() . ' | Time: ' . date('Y-m-d H:i:s'));
    http_response_code(500);
    echo json_encode([
        'success'  => false,
        'error'    => 'Server error',
        'timezone' => 'Asia/Kolkata (IST)'
    ]);
}
?>