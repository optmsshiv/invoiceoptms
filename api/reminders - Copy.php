<?php
// ================================================================
//  api/reminders.php  — Reminder Settings + Reminder Log
// ================================================================

// ── SET TIMEZONE TO INDIA STANDARD TIME (IST) ───────────────────
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$isLog  = isset($_GET['log']);

// ── FIX #5: Allowed values for type and channel ──────────────────
const ALLOWED_TYPES    = ['due_reminder', 'due_soon', 'due_today', 'overdue', 'followup', 'paid', 'promise_reminder'];
const ALLOWED_CHANNELS = ['whatsapp', 'sms', 'email', 'both'];
const ALLOWED_STATUSES = ['sent', 'failed', 'pending', 'skipped', 'promise'];

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

    // ── Promise to Pay table ──────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS `promise_to_pay` (
        `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `invoice_id`   INT UNSIGNED  NOT NULL,
        `invoice_num`  VARCHAR(40)   NOT NULL DEFAULT '',
        `client_name`  VARCHAR(200)  NOT NULL DEFAULT '',
        `promise_date` DATE          NOT NULL,
        `amount`       DECIMAL(12,2) NOT NULL DEFAULT 0,
        `note`         TEXT          NULL,
        `channel`      VARCHAR(20)   NOT NULL DEFAULT 'whatsapp',
        `status`       VARCHAR(20)   NOT NULL DEFAULT 'pending',
        `reminded_at`  DATETIME      NULL,
        `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_ptp_inv`  (`invoice_id`),
        INDEX `idx_ptp_date` (`promise_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
        // ── GET promises ─────────────────────────────────────────
        if ($action === 'promises') {
            $stmt = $db->query(
                "SELECT * FROM promise_to_pay
                 WHERE status IN ('pending','reminded')
                 ORDER BY promise_date ASC"
            );
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }
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
            if (empty($settings['channel'])) $settings['channel'] = 'whatsapp';

        
            if (!empty($settings)) {
                $settings['on_due']      = (int)$settings['on_due'];
                $settings['before_days'] = (int)$settings['before_days'];
                $settings['overdue_freq']= (int)$settings['overdue_freq'];
                $settings['max_overdue'] = (int)$settings['max_overdue'];
            }

            $stmt2 = $db->query(
                'SELECT * FROM reminder_log ORDER BY sent_at DESC LIMIT 50'
            );
            $log = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            $stmt3 = $db->query(
                "SELECT * FROM promise_to_pay
                 WHERE status IN ('pending','reminded')
                 ORDER BY promise_date ASC"
            );
            $promises = $stmt3->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'settings' => $settings, 'log' => $log, 'promises' => $promises]);
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

        // Also write to activitys_log (best-effort — don't fail the whole request)
        try {
            $user   = currentUser();
            $uid    = $user['id'] ?? null;
            $ip     = $_SERVER['REMOTE_ADDR'] ?? null;
            $label  = 'Reminder sent: ' . ($body['invoice_num'] ?? '');
            $detail = ($body['client_name'] ?? '') . ' via ' . $channel;
            $aStmt  = $db->prepare(
                'INSERT INTO activitys_log (type, label, detail, invoice_id, user_id, ip)
                 VALUES (:type, :label, :detail, :inv, :uid, :ip)'
            );
            $aStmt->execute([
                ':type'   => 'reminder_sent',
                ':label'  => $label,
                ':detail' => $detail,
                ':inv'    => !empty($body['invoice_id']) ? (int)$body['invoice_id'] : null,
                ':uid'    => $uid,
                ':ip'     => $ip,
            ]);
        } catch (Exception $eAct) {
            error_log('activitys_log write failed (non-fatal): ' . $eAct->getMessage());
        }

        echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);
        exit;
    }

    // ── POST: save a promise-to-pay entry ───────────────────────
    if ($method === 'POST' && $action === 'promise') {
        $invoiceId   = (int)($body['invoice_id']  ?? 0);
        $promiseDate = $body['promise_date'] ?? '';
        $amount      = (float)($body['amount']    ?? 0);
        $note        = substr($body['note']       ?? '', 0, 500);
        $channel     = in_array($body['channel']  ?? '', ALLOWED_CHANNELS) ? $body['channel'] : 'whatsapp';
        $invNum      = substr($body['invoice_num']  ?? '', 0, 40);
        $clientName  = substr($body['client_name']  ?? '', 0, 200);

        if (!$invoiceId || !$promiseDate) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'invoice_id and promise_date are required']);
            exit;
        }
        // Validate date format
        $d = DateTime::createFromFormat('Y-m-d', $promiseDate);
        if (!$d || $d->format('Y-m-d') !== $promiseDate) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD']);
            exit;
        }

        $stmt = $db->prepare(
            'INSERT INTO promise_to_pay
               (invoice_id, invoice_num, client_name, promise_date, amount, note, channel, status)
             VALUES (:inv_id, :inv_num, :client, :pdate, :amt, :note, :ch, "pending")'
        );
        $stmt->execute([
            ':inv_id'  => $invoiceId,
            ':inv_num' => $invNum,
            ':client'  => $clientName,
            ':pdate'   => $promiseDate,
            ':amt'     => $amount,
            ':note'    => $note,
            ':ch'      => $channel,
        ]);
        $newId = (int)$db->lastInsertId();

        // Log to reminder_log as well
        $db->prepare(
            'INSERT INTO reminder_log
               (invoice_id, invoice_num, client_name, type, channel, status, message)
             VALUES (:inv_id, :inv_num, :client, "promise_reminder", :ch, "promise",
                     :msg)'
        )->execute([
            ':inv_id'  => $invoiceId,
            ':inv_num' => $invNum,
            ':client'  => $clientName,
            ':ch'      => $channel,
            ':msg'     => 'Promise to pay by ' . $promiseDate . ($note ? ' — ' . $note : ''),
        ]);

        echo json_encode(['success' => true, 'id' => $newId]);
        exit;
    }

    // ── POST: mark promise as fulfilled / cancelled ───────────────
    if ($method === 'POST' && $action === 'promise_update') {
        $pid    = (int)($body['id']     ?? 0);
        $status = $body['status']       ?? 'fulfilled';
        $allowed = ['fulfilled','cancelled','reminded'];
        if (!$pid || !in_array($status, $allowed)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid id or status']);
            exit;
        }
        $set = $status === 'reminded'
            ? 'status=:s, reminded_at=NOW()'
            : 'status=:s';
        $db->prepare("UPDATE promise_to_pay SET {$set} WHERE id=:id")
           ->execute([':s' => $status, ':id' => $pid]);
        echo json_encode(['success' => true]);
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