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
      // Supplier Type options — user-managed list (Settings → Catalog),
      // seeded with the 5 values that used to be hardcoded in a <select>
      // so existing behavior is unchanged until someone actually edits
      // the list. The Purchase Entry / Supplier form fields are a type-
      // or-pick combo box, not a rigid dropdown, so this list is really
      // just "known suggestions" — any value can still be typed freely.
      'supplier_types'    => '["Farmer","Trader","Company","Cooperative","Other"]',
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
      // ── Named Date Range Presets — a convenience layer on top of the
      // same three keys above, nothing more. Activating a preset just
      // writes its from/to into global_date_active/from/to (exactly what
      // the plain toggle already does) and records which preset that was,
      // purely so the UI can show its name instead of raw dates. Every
      // page's filtering logic reads only the three keys above — presets
      // never touch it directly, so there's only ever one real filtering
      // mechanism, not two competing ones.
      'date_range_presets' => '[]', // JSON array of {id,name,from,to}
      'active_preset_id'   => '',   // empty = no preset active (plain toggle in control, or filter off)
      // ── Session-wise Product Pricing — separate from presets themselves
      // (a preset is just a date range; this is a separate map of
      // {preset_id: {product_id: {purchase_rate, sale_rate}}}) so pricing
      // data doesn't bloat the preset objects other features (Compare
      // Sessions, the plain toggle) don't need to know about at all.
      'session_pricing_enabled' => '0',
      'session_product_prices'  => '{}',
      // Blocks Add Funds/Correction on a session whose closing balance was
      // already carried forward elsewhere — that money's already been
      // moved and likely spent, so editing the source session further
      // would silently drift from what was actually carried. Default ON
      // (safer); toggle off only when a genuine correction is needed.
      'cih_restrict_carried_sessions' => '1',
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

    // Human-friendly labels for the audit log — falls back to a plain
    // title-cased version of the key (underscores → spaces) for any key
    // not listed here, so every field still reads reasonably without
    // needing to hand-map all ~80 possible settings keys.
    $labels = [
      'company_name' => 'Company Name', 'company_gst' => 'GST Number',
      'company_phone' => 'Phone', 'company_email' => 'Email',
      'company_website' => 'Website', 'company_upi' => 'UPI ID',
      'company_address' => 'Address', 'company_logo' => 'Logo',
      'company_sign' => 'Signature', 'company_bank' => 'Bank Details',
      'default_gst' => 'Default GST %', 'due_days' => 'Due Days',
      'active_template' => 'Active Template', 'default_tnc' => 'Terms & Conditions',
      'default_notes' => 'Default Notes', 'default_currency' => 'Currency',
      'business_type' => 'Business Type', 'estimate_prefix' => 'Estimate Prefix',
      'invoice_prefix' => 'Invoice Prefix', 'company_tagline' => 'Tagline',
      'company_iso' => 'ISO Certificate No.', 'show_dhalta_pct' => 'Show Dhalta %',
      'supplier_types' => 'Supplier Types',
      'global_date_active' => 'Global Date Range (on/off)',
      'global_date_from' => 'Global Date Range — From',
      'global_date_to' => 'Global Date Range — To',
      'date_range_presets' => 'Date Range Presets', 'active_preset_id' => 'Active Date Preset',
      'session_pricing_enabled' => 'Session-wise Pricing (on/off)',
      'session_product_prices' => 'Session Product Prices',
      'cih_restrict_carried_sessions' => 'Cash in Hand — Restrict Carried Sessions',
      'wa_followup_days' => 'WhatsApp Follow-up Days',
      'wa_allow_web_fallback' => 'WhatsApp Web Fallback',
      'generated_by' => 'Generated By', 'due_days' => 'Due Days',
    ];

    // Fields where the raw value isn't meaningful in an audit log — JSON
    // blobs, images, bank details — logging just the field name that
    // changed instead of dumping the full before/after content, which
    // would be noise at best and expose sensitive data (bank details) at
    // worst.
    $opaqueKeys = ['company_logo','company_sign','company_bank','supplier_types',
      'product_form_config','product_dropdowns','product_var_grade_map',
      'ofr_print_settings','date_range_presets','session_product_prices',
      'sig_prepared_img','sig_verified_img'];

    // Fetch current values BEFORE overwriting, so the log can report an
    // accurate old → new diff instead of a fixed generic sentence that
    // can't tell one save apart from any other.
    $keys = array_keys($d);
    $oldVals = [];
    if ($keys) {
      $placeholders = implode(',', array_fill(0, count($keys), '?'));
      $oldStmt = $db->prepare("SELECT `key`, value FROM settings WHERE `key` IN ($placeholders)");
      $oldStmt->execute($keys);
      foreach ($oldStmt->fetchAll() as $r) { $oldVals[$r['key']] = $r['value']; }
    }

    $stmt = $db->prepare('INSERT INTO settings (`key`, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?');
    $changes = [];
    foreach ($d as $key => $val) {
      $stmt->execute([$key, $val, $val]);

      $old = $oldVals[$key] ?? null;
      if ((string)$old === (string)$val) continue; // no real change — skip from the log, it's not something to audit

      $label = $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
      if (in_array($key, $opaqueKeys, true)) {
        $changes[] = $label;
      } else {
        // Keep each side short so one very long field can't blow out the
        // whole log line — the field is still saved in full either way,
        // this only affects what's shown in the audit message.
        $oldDisp = ($old === null || $old === '') ? '(empty)' : (strlen((string)$old) > 40 ? substr((string)$old, 0, 40) . '…' : $old);
        $newDisp = ($val === null || $val === '') ? '(empty)' : (strlen((string)$val) > 40 ? substr((string)$val, 0, 40) . '…' : $val);
        $changes[] = "{$label}: {$oldDisp} → {$newDisp}";
      }
    }

    // Nothing actually changed (e.g. the form was resubmitted with
    // identical values) — nothing meaningful to audit, so skip logging
    // entirely rather than writing an entry that says nothing happened.
    if ($changes) {
      $detail = count($changes) <= 4
        ? implode('; ', $changes)
        : (count($changes) . ' fields updated: ' . implode(', ', array_map(fn($c) => explode(':', $c)[0], array_slice($changes, 0, 4))) . '…');
      logActivity((int)$_SESSION['user_id'], 'update', 'settings', 0, $detail);
    }

    jsonResponse(['success'=>true]);

  default: 
    jsonResponse(['error'=>'Method not allowed'], 405);
}