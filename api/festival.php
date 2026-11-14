<?php
// ================================================================
//  api/festival.php — saved Festival / Bulk WhatsApp campaigns
//
//  GET                          → { campaigns:[...], images:{occasion:url} }
//  POST action=save_campaign    → create / update a campaign (id optional)
//  POST action=toggle           → { id, active }
//  POST action=delete_campaign  → { id }
//  POST action=set_image        → { occasion, url }   (remembered image per occasion)
//
//  Campaigns are sent by api/wa_cron.php when their schedule time has passed.
//  Self-migrating tables (safe to run on every request).
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json');

$db = getDB();
$db->exec("CREATE TABLE IF NOT EXISTS festival_campaigns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  occasion VARCHAR(40) NOT NULL DEFAULT 'custom',
  name VARCHAR(120) NOT NULL DEFAULT '',
  message TEXT NULL,
  image VARCHAR(600) NULL,
  sendto VARCHAR(20) NOT NULL DEFAULT 'all',
  schedule DATETIME NULL,
  repeat_mode VARCHAR(12) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_run DATETIME NULL,
  last_result VARCHAR(200) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS festival_images (
  occasion VARCHAR(40) NOT NULL PRIMARY KEY,
  url VARCHAR(600) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $c = $db->query("SELECT * FROM festival_campaigns ORDER BY (schedule IS NULL), schedule ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $i = $db->query("SELECT occasion, url FROM festival_images")->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode(['success' => true, 'campaigns' => $c, 'images' => $i ?: new stdClass()]);
    exit;
}
if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'GET or POST only']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $in['action'] ?? '';
$fail = function ($m, $code = 422) { http_response_code($code); echo json_encode(['success' => false, 'error' => $m]); exit; };

if ($action === 'save_campaign') {
    $id       = (int)($in['id'] ?? 0);
    $occasion = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($in['occasion'] ?? 'custom'))) ?: 'custom';
    $name     = trim((string)($in['name'] ?? ''));
    $message  = trim((string)($in['message'] ?? ''));
    $image    = trim((string)($in['image'] ?? ''));
    $sendto   = in_array($in['sendto'] ?? '', ['all', 'paid', 'active'], true) ? $in['sendto'] : 'all';
    $repeat   = in_array($in['repeat'] ?? '', ['weekly', 'monthly', 'yearly'], true) ? $in['repeat'] : '';
    $active   = isset($in['active']) ? (int)!!$in['active'] : 1;
    $sched    = trim((string)($in['schedule'] ?? ''));
    $ts = $sched !== '' ? strtotime($sched) : false;
    if ($sched === '' || !$ts) $fail('Pick the date and time to send this campaign.');
    if ($ts <= time()) $fail('The schedule time must be in the future.');
    if ($image !== '' && !preg_match('#^(https://|[A-Za-z0-9_./-]+$)#', $image)) $fail('The image must be a public https link.');
    if ($name === '') $name = ucfirst($occasion);
    $schedSql = date('Y-m-d H:i:s', $ts);
    if ($id) {
        $db->prepare("UPDATE festival_campaigns SET occasion=?,name=?,message=?,image=?,sendto=?,schedule=?,repeat_mode=?,active=?,last_result=NULL WHERE id=?")
           ->execute([$occasion, $name, $message, $image, $sendto, $schedSql, $repeat, $active, $id]);
    } else {
        $db->prepare("INSERT INTO festival_campaigns (occasion,name,message,image,sendto,schedule,repeat_mode,active) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$occasion, $name, $message, $image, $sendto, $schedSql, $repeat, $active]);
        $id = (int)$db->lastInsertId();
    }
    echo json_encode(['success' => true, 'id' => $id]); exit;
}
if ($action === 'toggle') {
    $db->prepare("UPDATE festival_campaigns SET active=? WHERE id=?")->execute([(int)!!($in['active'] ?? 0), (int)($in['id'] ?? 0)]);
    echo json_encode(['success' => true]); exit;
}
if ($action === 'delete_campaign') {
    $db->prepare("DELETE FROM festival_campaigns WHERE id=?")->execute([(int)($in['id'] ?? 0)]);
    echo json_encode(['success' => true]); exit;
}
if ($action === 'set_image') {
    $occ = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($in['occasion'] ?? '')));
    $url = trim((string)($in['url'] ?? ''));
    if ($occ === '') $fail('Occasion required');
    if ($url === '') { $db->prepare("DELETE FROM festival_images WHERE occasion=?")->execute([$occ]); }
    else {
        if (!preg_match('#^(https://|[A-Za-z0-9_./-]+$)#', $url)) $fail('The image must be a public https link.');
        $q = $db->prepare("SELECT COUNT(*) FROM festival_images WHERE occasion=?"); $q->execute([$occ]);
        if ((int)$q->fetchColumn()) $db->prepare("UPDATE festival_images SET url=? WHERE occasion=?")->execute([$url, $occ]);
        else $db->prepare("INSERT INTO festival_images (occasion,url) VALUES (?,?)")->execute([$occ, $url]);
    }
    echo json_encode(['success' => true]); exit;
}
$fail('Unknown action', 400);
