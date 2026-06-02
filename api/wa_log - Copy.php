<?php
// ================================================================
//  api/wa_log.php  — WhatsApp Message Log (DB persistence)
//
//  GET    /api/wa_log.php              → fetch recent log (newest first, max 500)
//  POST   /api/wa_log.php              → append a log entry
//  DELETE /api/wa_log.php              → clear all log entries
// ================================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

const WA_ALLOWED_TYPES = [
    'invoice_created', 'estimate_created', 'payment_received', 'partial_payment',
    'split_payment', 'payment_overdue', 'payment_reminder', 'invoice_followup',
    'festival', 'unknown'
];
const WA_ALLOWED_STATUSES = ['sending', 'sent_api', 'sent_web', 'failed'];

try {
    $db = getDB();

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
        INDEX `idx_wa_log_ts` (`ts`),
        INDEX `idx_wa_log_inv` (`inv_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── GET: fetch log ───────────────────────────────────────────
    if ($method === 'GET') {
        $stmt = $db->query(
            'SELECT entry_id AS id, ts, type, status, client, phone,
                    inv_id, inv_num, inv_amt, inv_status, msg, error
             FROM wa_message_log
             ORDER BY ts DESC
             LIMIT 500'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    // ── Read body ────────────────────────────────────────────────
    $body = [];
    if ($method === 'POST') {
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
                echo json_encode(['success' => true, 'updated' => true]);
                exit;
            }
        }

        // Insert new row (INSERT IGNORE to handle race conditions)
        $stmt = $db->prepare(
            'INSERT IGNORE INTO wa_message_log
               (entry_id, ts, type, status, client, phone, inv_id, inv_num, inv_amt, inv_status, msg, error)
             VALUES
               (:eid, :ts, :type, :status, :client, :phone, :inv_id, :inv_num, :inv_amt, :inv_status, :msg, :error)'
        );
        $stmt->execute([
            ':eid'        => $entryId,
            ':ts'         => !empty($body['ts'])
                                ? date('Y-m-d H:i:s', strtotime($body['ts']))
                                : date('Y-m-d H:i:s'),
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

        echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);
        exit;
    }

    // ── DELETE: clear log ────────────────────────────────────────
    if ($method === 'DELETE') {
        $db->exec('DELETE FROM wa_message_log');
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);

} catch (Exception $e) {
    error_log('wa_log.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
