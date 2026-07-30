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
      'wa_allow_web_fallback' => '0',
      'business_type'     => 'both', // 'service' | 'product' | 'both' — controls Products page wording
      'product_form_config' => '', // JSON: field visibility + label overrides
      'product_dropdowns'   => '', // JSON: custom dropdown options per field
      'product_var_grade_map' => '', // JSON: { variety: grade } linked pair map
      'product_active_preset' => '', // last applied preset name
      'ofr_print_settings'    => '', // JSON: proforma print toggle settings
      'show_dhalta_pct'   => '1', // '1' show / '0' hide Dhalta % in purchase items table & local voucher
      'company_tagline'   => '', // shown under company name on printed documents (blank = hidden)
      'company_iso'       => '', // ISO 22000 certificate number
      // ── Global Date Range filter — owner-set, tenant-wide, applies to
      // every transaction list/report until manually changed (never
      // auto-resets). Master data (customers/suppliers/products) is
      // never affected by this — see chat.
      'global_date_active' => '0',
      'global_date_from'   => '',
      'global_date_to'     => '',
      // ── Document signatures: up to 3 roles, each with its own image +
      // name, and its own on/off default per document type. Authorized
      // Signatory reuses company_sign as its image (already existed).
      'sig_authorized_name' => '',
      'sig_prepared_img'    => '',
      'sig_prepared_name'   => '',
      'sig_verified_img'    => '',
      'sig_verified_name'   => '',
      'sig_show_authorized_proforma' => '1',
      'sig_show_authorized_sales'    => '1',
      'sig_show_authorized_purchase' => '1',
      'sig_show_prepared_proforma'   => '0',
      'sig_show_prepared_sales'      => '0',
      'sig_show_prepared_purchase'   => '0',
      'sig_show_verified_proforma'   => '0',
      'sig_show_verified_sales'      => '0',
      'sig_show_verified_purchase'   => '0',
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
      'wa_tpl_name_balance_reminder'   => '',
      'wa_tpl_lang_balance_reminder'   => 'en_US',
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