<?php
// ================================================================
//  api/reminders.php  — Reminder Settings + Reminder Log
//
//  GET    /api/reminders.php                → get settings + recent log
//  GET    /api/reminders.php?log=1          → reminder log only
//  POST   /api/reminders.php                → save reminder settings
//  POST   /api/reminders.php?action=log     → append a reminder log entry
//  DELETE /api/reminders.php                → clear reminder log  [FIX #2: removed ?log=1 requirement]
// ================================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$isLog  = isset($_GET['log']);

// ── FIX #5: Allowed values for type and channel ──────────────────
const ALLOWED_TYPES    = ['due_reminder', 'overdue', 'followup', 'paid'];
const ALLOWED_CHANNELS = ['whatsapp', 'sms', 'email'];
const ALLOWED_STATUSES = ['sent', 'failed', 'pending'];

try {
    $db = getDB();

    // ── Auto-create tables if migration not run ──────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS `reminder_settings` (
        `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `before_days`  TINYINT      NOT NULL DEFAULT 3,
        `on_due`       TINYINT(1)   NOT NULL DEFAULT 1,
        `overdue_freq` TINYINT      NOT NULL DEFAULT 7,
        `max_overdue`  TINYINT      NOT NULL DEFAULT 3,
        `channel`      VARCHAR(20)  NOT NULL DEFAULT 'whatsapp',
        `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("INSERT IGNORE INTO `reminder_settings` (id,before_days,on_due,overdue_freq,max_overdue,channel) VALUES (1,3,1,7,3,'whatsapp')");
    $db->exec("CREATE TABLE IF NOT EXISTS `reminder_log` (
        `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `invoice_id`  INT UNSIGNED  NULL,
        `invoice_num` VARCHAR(40)   NULL,
        `client_name` VARCHAR(200)  NULL,
        `type`        VARCHAR(40)   NOT NULL DEFAULT 'due_reminder',
        `channel`     VARCHAR(20)   NOT NULL DEFAULT 'whatsapp',
        `status`      VARCHAR(20)   NOT NULL DEFAULT 'sent',
        `message`     TEXT          NULL,
        `sent_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_remlog_inv` (`invoice_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── GET ──────────────────────────────────────────────────────
    if ($method === 'GET') {
        if ($isLog) {
            // Return reminder log (newest first, max 200)
            $stmt = $db->query(
                'SELECT * FROM reminder_log ORDER BY sent_at DESC LIMIT 200'
            );
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } else {
            // Return settings row + recent log
            $stmt     = $db->query('SELECT * FROM reminder_settings WHERE id=1');
            $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // FIX #3: Cast on_due to a real boolean so JS receives true/false, not "0"/"1" string
            if (!empty($settings)) {
                $settings['on_due']      = (bool)(int)$settings['on_due'];
                $settings['before_days'] = (int)$settings['before_days'];
                $settings['overdue_freq']= (int)$settings['overdue_freq'];
                $settings['max_overdue'] = (int)$settings['max_overdue'];
            }

            $stmt2 = $db->query(
                'SELECT * FROM reminder_log ORDER BY sent_at DESC LIMIT 50'
            );
            $log = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'settings' => $settings, 'log' => $log]);
        }
        exit;
    }

    // ── Read body ────────────────────────────────────────────────
    $body = [];
    if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: [];
        if (empty($body)) $body = $_POST;
    }

    // ── POST: log a reminder entry ────────────────────────────────
    if ($method === 'POST' && $action === 'log') {
        // FIX #5: Validate channel against allowed list
        $type    = in_array($body['type']    ?? '', ALLOWED_TYPES)    ? $body['type']    : 'due_reminder';
        $channel = in_array($body['channel'] ?? '', ALLOWED_CHANNELS) ? $body['channel'] : 'whatsapp';
        $status  = in_array($body['status']  ?? '', ALLOWED_STATUSES) ? $body['status']  : 'sent';

        $stmt = $db->prepare(
            'INSERT INTO reminder_log
               (invoice_id, invoice_num, client_name, type, channel, status, message)
             VALUES (:inv_id, :inv_num, :client, :type, :channel, :status, :msg)'
        );
        $stmt->execute([
            ':inv_id'  => !empty($body['invoice_id']) ? (int)$body['invoice_id'] : null,
            ':inv_num' => substr($body['invoice_num'] ?? '', 0, 40),
            ':client'  => substr($body['client_name'] ?? '', 0, 200),
            ':type'    => $type,
            ':channel' => $channel,
            ':status'  => $status,
            ':msg'     => $body['message'] ?? '',
        ]);

        // Also write to activity_log
        $user   = currentUser();
        $uid    = $user['id'] ?? null;
        $ip     = $_SERVER['REMOTE_ADDR'] ?? null;
        $label  = 'Reminder sent: ' . ($body['invoice_num'] ?? '');
        $detail = ($body['client_name'] ?? '') . ' via ' . $channel;
        $aStmt  = $db->prepare(
            'INSERT INTO activity_log (type, label, detail, invoice_id, user_id, ip)
             VALUES (:type, :label, :detail, :inv, :uid, :ip)'
        );
        $aStmt->execute([
            ':type'  => 'reminder_sent',
            ':label' => $label,
            ':detail'=> $detail,
            ':inv'   => !empty($body['invoice_id']) ? (int)$body['invoice_id'] : null,
            ':uid'   => $uid,
            ':ip'    => $ip,
        ]);

        echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);
        exit;
    }

    // ── POST: save reminder settings ──────────────────────────────
    if ($method === 'POST') {
        // validate channel against allowed list, default to 'whatsapp' if invalid or missing
        $channel = in_array($body['channel'] ?? '', ALLOWED_CHANNELS) ? $body['channel'] : 'whatsapp';

        $stmt = $db->prepare(
            'INSERT INTO reminder_settings (id, before_days, on_due, overdue_freq, max_overdue, channel)
             VALUES (1, :bd, :od, :of, :mo, :ch)
             ON DUPLICATE KEY UPDATE
               before_days  = VALUES(before_days),
               on_due       = VALUES(on_due),
               overdue_freq = VALUES(overdue_freq),
               max_overdue  = VALUES(max_overdue),
               channel      = VALUES(channel)'
        );
        $stmt->execute([
            ':bd' => (int)($body['before_days']  ?? 3),
            ':od' => (int)($body['on_due']        ?? 1),
            ':of' => (int)($body['overdue_freq']  ?? 7),
            ':mo' => (int)($body['max_overdue']   ?? 3),
            ':ch' => $channel,
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ── DELETE: clear log ─────────────────────────────────────────
    // FIX #2: Removed && $isLog — DELETE always clears the log regardless of query param.
    // There is only one DELETE action in this endpoint, so ?log=1 guard was wrong.
    if ($method === 'DELETE') {
        $db->exec('DELETE FROM reminder_log');
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);

} catch (Exception $e) {
    error_log('reminders.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}