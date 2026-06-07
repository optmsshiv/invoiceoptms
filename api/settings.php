<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB(); 
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
  case 'GET':
    // Define fallback defaults for keys that might not exist in DB yet
    $defaults = [
      'estimate_prefix'   => 'QT-' . date('Y') . '-',
      'invoice_prefix'    => 'INV-' . date('Y') . '-',
      'company_name'      => '',
      'company_gst'       => '',
      'company_phone'     => '',
      'company_email'     => '',
      'company_website'   => '',
      'company_upi'       => '',
      'company_address'   => '',
      'company_logo'      => '',
      'company_sign'      => '',
      'company_bank'      => '',
      'default_gst'       => '18',
      'due_days'          => '15',
      'active_template'   => '1',
      'default_tnc'       => '',
      'default_notes'     => '',
      'generated_by'      => '',
      'default_currency'  => '₹',
      'wa_followup_days'  => '7',
      'tpl_logo_position'  => 'left',
      'tpl_watermark_text' => 'PAID',
      'tpl_color_theme'    => '1',
      'before_days'        => '3',
      'on_due'             => '1',
      'overdue_freq'       => '7',
      'max_overdue'        => '3',
      'channel'            => 'whatsapp',
      // ── WhatsApp Meta template names & languages ──────────────
      'wa_msg_mode'                    => 'session',
      'wa_tpl_name_invoice'            => '',
      'wa_tpl_lang_invoice'            => 'en_US',
      'wa_tpl_name_estimate'           => '',
      'wa_tpl_lang_estimate'           => 'en_US',
      'wa_tpl_name_reminder'           => '',
      'wa_tpl_lang_reminder'           => 'en_US',
      'wa_tpl_name_overdue'            => '',
      'wa_tpl_lang_overdue'            => 'en_US',
      'wa_tpl_name_paid'               => '',
      'wa_tpl_lang_paid'               => 'en_US',
      'wa_tpl_name_followup'           => '',
      'wa_tpl_lang_followup'           => 'en_US',
      'wa_tpl_name_recurring'          => '',
      'wa_tpl_lang_recurring'          => 'en_US',
      'wa_tpl_name_partial'            => '',
      'wa_tpl_lang_partial'            => 'en_US',
      'wa_tpl_name_balance'            => '',
      'wa_tpl_lang_balance'            => 'en_US',
      'wa_tpl_name_festival'           => '',
      'wa_tpl_lang_festival'           => 'en_US',
    ];
    
    // Fetch existing settings from DB
    $rows = $db->query('SELECT `key`, value FROM settings')->fetchAll();
    
    // Start with defaults, then overwrite with DB values
    $out = $defaults;
    foreach ($rows as $r) {
      $out[$r['key']] = $r['value'];
    }
    
    jsonResponse(['data' => $out]);

  case 'POST':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error'=>'Invalid JSON'], 400);
    
    $stmt = $db->prepare('INSERT INTO settings (`key`, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?');
    foreach ($d as $key => $val) {
      $stmt->execute([$key, $val, $val]);
    }
    
    logActivity((int)$_SESSION['user_id'], 'update', 'settings', 0, 'Company settings updated');
    jsonResponse(['success'=>true]);

  default: 
    jsonResponse(['error'=>'Method not allowed'], 405);
}