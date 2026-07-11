<?php
// ================================================================
//  pages/comms/whatsapp.php
//  WhatsApp Business setup: message templates per invoice event,
//  manual send, festival bulk campaigns, automation toggles.
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.whatsapp');

$user = currentUser();

$activePage  = 'whatsapp';
$pageTitle   = 'WhatsApp Setup';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/wa-shared.js', '/assets/js/whatsapp.js'];

include __DIR__ . '/../../includes/layout_header.php';
?>
    <div id="page-whatsapp" class="page active">
    <style>
      /* ── WA Page Layout ── */
      .wa-page { display:flex; flex-direction:column; gap:16px; }
      .wa-row   { display:grid; gap:16px; }
      .wa-row-2 { grid-template-columns:1fr 1fr; }
      .wa-row-3 { grid-template-columns:1fr 1fr 1fr; }

      /* ── Template Tabs ── */
      .wa-tab-bar { display:flex; gap:0; border-bottom:2px solid var(--border); margin-bottom:16px; overflow-x:auto; flex-wrap:nowrap; }
      .wa-tab-btn {
        padding:8px 16px; font-size:12px; font-weight:600; cursor:pointer;
        border:none; background:transparent; color:var(--muted); white-space:nowrap;
        border-bottom:2px solid transparent; margin-bottom:-2px; font-family:var(--font);
        transition:.2s;
      }
      .wa-tab-btn:hover { color:var(--teal); }
      .wa-tab-btn.active { color:var(--teal); border-bottom-color:var(--teal); }
      .wa-tab-pane { display:none; }
      .wa-tab-pane.active { display:block; }

      /* ── Variable chips ── */
      .wa-vars { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:12px; }
      .wa-var-chip {
        padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600;
        background:var(--teal-bg); color:var(--teal); cursor:pointer;
        border:1px solid var(--teal-l); transition:.15s; font-family:var(--mono);
      }
      .wa-var-chip:hover { background:var(--teal); color:#fff; }

      /* ── Char counter ── */
      .wa-char-counter { font-size:10px; color:var(--muted); text-align:right; margin-top:3px; }
      .wa-char-counter.warn { color:var(--amber); }
      .wa-char-counter.over { color:var(--red); }

      /* ── Preview bubble ── */
      .wa-preview-wrap { background:#E5DDD5; border-radius:10px; padding:14px; margin-top:10px; display:none; }
      .wa-preview-wrap.show { display:block; }
      .wa-bubble {
        background:#fff; border-radius:0 10px 10px 10px; padding:10px 14px;
        font-size:12.5px; line-height:1.7; color:#111; max-width:320px;
        box-shadow:0 1px 3px rgba(0,0,0,.12); white-space:pre-wrap; word-break:break-word;
      }
      .wa-bubble strong { font-weight:700; }
      .wa-bubble-meta { font-size:10px; color:#888; text-align:right; margin-top:4px; }

      /* ── Send mode badge ── */
      .wa-mode-badge {
        display:inline-flex; align-items:center; gap:5px;
        padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700;
      }
      .wa-mode-badge.session  { background:var(--blue-bg); color:var(--blue); }
      .wa-mode-badge.template { background:#E8F5E9; color:#2E7D32; }

      /* ── Section divider label ── */
      .wa-section-label {
        font-size:10px; font-weight:800; text-transform:uppercase;
        letter-spacing:1.2px; color:var(--muted); margin-bottom:12px;
        display:flex; align-items:center; gap:8px;
      }
      .wa-section-label::after { content:''; flex:1; height:1px; background:var(--border); }

      /* ── Quick reply chips ── */
      .wa-quick-chip {
        padding:5px 12px; border-radius:20px; font-size:11px; font-weight:600;
        background:var(--bg); border:1.5px solid var(--border); cursor:pointer;
        color:var(--text2); transition:.15s; font-family:var(--font);
      }
      .wa-quick-chip:hover { border-color:var(--teal); color:var(--teal); background:var(--teal-bg); }
    </style>

      <div class="wa-page">

        <!-- ── ROW 1: Connection + Automation ── -->
        <div class="wa-row wa-row-2">

          <!-- API Credentials -->
          <div class="settings-block" style="margin:0">
            <div class="sb-title"><i class="fab fa-whatsapp" style="color:#25D366"></i> WhatsApp Business API
              <span id="wa-conn-status" style="margin-left:auto;font-size:11px;font-weight:600"></span>
            </div>
            <div class="form-grid g2">
              <div class="field"><label>API Token</label><input type="password" id="wa-token" placeholder="Bearer token from Meta Developer Console" value="<?= htmlspecialchars($settings['wa_token']??'') ?>"></div>
              <div class="field"><label>Phone Number ID</label><input id="wa-pid" placeholder="123456789012345" value="<?= htmlspecialchars($settings['wa_pid']??'') ?>"></div>
              <div class="field"><label>Business Account ID</label><input id="wa-bid" placeholder="Your WABA ID" value="<?= htmlspecialchars($settings['wa_bid']??'') ?>"></div>
              <div class="field">
                <label>Webhook Verify Token <span style="font-size:11px;color:var(--muted);font-weight:400">— paste this in Meta Developer Console → Webhooks</span></label>
                <div style="display:flex;gap:8px;align-items:center">
                  <input id="wa-webhook-token" placeholder="e.g. optms_wa_webhook_2026" value="<?= htmlspecialchars($settings['wa_webhook_token']??'') ?>" style="flex:1">
                  <button type="button" class="btn btn-outline" style="font-size:12px;white-space:nowrap" onclick="const t='optms_'+Math.random().toString(36).slice(2,10);document.getElementById('wa-webhook-token').value=t;saveWASettings();toast('✅ Token generated & saved','success')"><i class="fas fa-dice"></i> Generate</button>
                </div>
                <div style="font-size:11px;color:var(--muted);margin-top:4px">Webhook URL: <code><?= (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'] ?>/api/wa_webhook.php</code></div>
              </div>
              <div class="field"><label>Test Phone Number</label><input id="wa-test-phone" placeholder="+91 XXXXX XXXXX" value="<?= htmlspecialchars($settings['wa_test_phone']??'') ?>"></div>
            </div>
            <div class="toggle-item" style="border-bottom:none;padding-top:12px">
              <span><strong>Allow WhatsApp Web Fallback</strong> — open wa.me if API isn't configured</span>
              <div class="tog <?= (($settings['wa_allow_web_fallback']??'0')==='1')?'on':'' ?>" id="wa-allow-web-fallback" onclick="this.classList.toggle('on'); saveWASettings()"></div>
            </div>
            <div style="background:var(--amber-bg);border-radius:8px;padding:10px 14px;font-size:12px;color:#8A5A00;margin-top:2px;margin-bottom:2px;line-height:1.7">
              <i class="fas fa-info-circle"></i> Off by default — sending fails with an error instead of auto-opening WhatsApp Web. Turn this on only if you're fine with WhatsApp Web popping open and needing a manual tap to send.
            </div>
            <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
              <button class="btn btn-whatsapp" onclick="testWA()"><i class="fab fa-whatsapp"></i> Test &amp; Send</button>
              <button class="btn btn-primary" onclick="saveWASettings()"><i class="fas fa-save"></i> Save</button>
            </div>
          </div>

          <!-- Automation Triggers -->
          <div class="settings-block" style="margin:0">
            <div class="sb-title"><i class="fas fa-robot"></i> Automation Triggers</div>
            <div class="toggle-list" style="margin-top:0">
              <div class="toggle-item" style="flex-wrap:wrap;gap:6px">
                <span style="flex:1"><strong>New Invoice</strong> — auto-send when created</span>
                <div class="tog <?= (($settings['wa_auto_inv']??'0')==='1')?'on':'' ?>" id="twa1" onclick="this.classList.toggle('on'); saveWAToggle('wa_auto_inv', this)"></div>
              </div>
              <div style="padding:8px 12px;margin:-4px 0 8px;background:var(--teal-bg);border-radius:0 0 8px 8px;font-size:11px;color:var(--teal)" id="twa1-hint">
              When ON: sends invoice details, amount, due date, UPI, and item list to client automatically
              </div>
              <div class="toggle-item" style="flex-wrap:wrap;gap:6px">
                <span style="flex:1"><strong>New Estimate</strong> — auto-send when estimate is saved</span>
                <div class="tog <?= (($settings['wa_auto_estimate']??'1')==='1')?'on':'' ?>" id="twa7" onclick="this.classList.toggle('on'); saveWAToggle('wa_auto_estimate', this)"></div>
              </div>
              <div class="toggle-item"><span><strong>Payment Receipt</strong> — when fully paid</span><div class="tog <?= (($settings['wa_auto_paid']??'1')!=='0')?'on':'' ?>" id="twa2" onclick="this.classList.toggle('on'); saveWAToggle('wa_auto_paid', this)"></div></div>
              <div class="toggle-item"><span><strong>Partial Payment</strong> — on partial receipt</span><div class="tog <?= (($settings['wa_auto_partial']??'1')!=='0')?'on':'' ?>" id="twa6" onclick="this.classList.toggle('on'); saveWAToggle('wa_auto_partial', this)"></div></div>
              <div class="toggle-item"><span><strong>Due Soon Reminder</strong> — before due date</span><div class="tog <?= (($settings['wa_auto_remind']??'1')!=='0')?'on':'' ?>" id="twa3" onclick="this.classList.toggle('on'); saveWAToggle('wa_auto_remind', this)"></div></div>
              <div class="toggle-item"><span><strong>Overdue Alert</strong> — on due date if unpaid</span><div class="tog <?= (($settings['wa_auto_overdue']??'1')!=='0')?'on':'' ?>" id="twa4" onclick="this.classList.toggle('on'); saveWAToggle('wa_auto_overdue', this)"></div></div>
              <div class="toggle-item"><span><strong>Overdue Follow-up</strong> — repeat every <span id="wa-followup-days-label"><?= htmlspecialchars($settings['wa_followup_days'] ?? $settings['overdue_freq'] ?? '7') ?></span> days</span><div class="tog <?= (($settings['wa_auto_followup']??'0')==='1')?'on':'' ?>" id="twa5" onclick="this.classList.toggle('on'); saveWAToggle('wa_auto_followup', this)"></div></div>
            </div>
            <div style="background:var(--teal-bg);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--teal);margin-top:10px;line-height:1.7">
              <i class="fas fa-info-circle"></i> <strong>Timing rules</strong> (days before due, follow-up interval, max follow-ups) are configured in the
              <a href="#" onclick="window.location.href='/pages/tools/reminders.php';return false;" style="color:var(--teal);font-weight:700;text-decoration:underline">Reminders page</a>.
            </div>
          </div>
        </div>

        <!-- ── ROW 2: Message Templates (full width, tabbed) ── -->
        <div class="settings-block" style="margin:0">
          <div class="sb-title"><i class="fas fa-comment-alt"></i> Message Templates
            <span id="wa-mode-badge-tpl" style="margin-left:auto"></span>
          </div>

          <!-- Tab bar -->
          <div class="wa-tab-bar">
            <button class="wa-tab-btn active" onclick="waTab('inv',this)">📄 Invoice</button>
            <button class="wa-tab-btn" onclick="waTab('estimate',this)">📋 Estimate</button>
            <button class="wa-tab-btn" onclick="waTab('paid',this)">✅ Receipt</button>
            <button class="wa-tab-btn" onclick="waTab('partial',this)">💚 Partial</button>
            <button class="wa-tab-btn" onclick="waTab('remind',this)">🔔 Reminder</button>
            <button class="wa-tab-btn" onclick="waTab('custom',this)">✏️ Custom</button>
            <button class="wa-tab-btn" onclick="waTab('overdue',this)">⚠️ Overdue</button>
            <button class="wa-tab-btn" onclick="waTab('followup',this)">📋 Follow-up</button>
            <button class="wa-tab-btn" onclick="waTab('recurring',this)">🔁 Recurring</button>
          </div>

          <!-- Variable inserter -->
          <div class="wa-section-label">Click to insert variable</div>
          <div class="wa-vars" id="wa-var-chips">
            <?php foreach(['{client_name}','{invoice_no}','{amount}','{currency}','{due_date}','{issue_date}','{service}','{company_name}','{company_phone}','{company_email}','{upi}','{bank_details}','{days_overdue}','{item_list}','{paid_amount}','{remaining_amount}','{settlement_discount}','{invoice_link}'] as $v): ?>
            <span class="wa-var-chip" onclick="waInsertVar('<?= $v ?>')"><?= $v ?></span>
            <?php endforeach; ?>
          </div>

          <!-- Tab panes -->
          <div class="wa-tab-pane active" id="watab-inv">
            <div class="field">
              <textarea id="wa-tpl-inv" style="min-height:140px;font-family:var(--mono);font-size:12.5px" oninput="saveWASettings();waUpdateCounter('wa-tpl-inv','wa-cnt-inv');waUpdatePreview('wa-tpl-inv','wa-prev-inv')">Hi {client_name}! 👋

*Invoice #{invoice_no}* from *{company_name}* is ready.

📋 Service: {service}
📅 Due Date: {due_date}
💰 Amount: *{currency}{amount}*

💳 Pay via UPI: {upi}
🏦 {bank_details}

🔗 View &amp; Download Invoice:
{invoice_link}

Thank you for choosing {company_name}!
📞 {company_phone}</textarea>
              <div class="wa-char-counter" id="wa-cnt-inv"></div>
            </div>
            <button class="btn btn-outline" style="font-size:12px;padding:5px 12px" onclick="waTogglePreview('wa-prev-inv')"><i class="fas fa-mobile-alt"></i> Preview</button>
            <div class="wa-preview-wrap" id="wa-prev-inv"><div class="wa-bubble" id="wa-prev-inv-bubble"></div><div class="wa-bubble-meta">Delivered ✓✓</div></div>
          </div>

          <div class="wa-tab-pane" id="watab-estimate">
            <div style="background:#E8EAF6;border-radius:8px;padding:10px 14px;font-size:12px;color:#1A237E;margin-bottom:10px;line-height:1.7">
              <strong>📋 Estimate Template:</strong> Sent automatically when you save an invoice with <strong>Estimate</strong> status. Includes a clear disclaimer and a portal link for client approval.
            </div>
            <div class="field">
              <textarea id="wa-tpl-estimate" style="min-height:200px;font-family:var(--mono);font-size:12.5px" oninput="saveWASettings();waUpdateCounter('wa-tpl-estimate','wa-cnt-estimate');waUpdatePreview('wa-tpl-estimate','wa-prev-estimate')">Hi {client_name}! 👋

📋 *Estimation / Quotation*
From: *{company_name}*

We have prepared a cost estimate for your requirements:

🔢 Quote No: *#{invoice_no}*
📅 Date: *{issue_date}*
💰 Estimated Amount: *{currency}{amount}*
⏳ Valid Until: *{due_date}*
📋 Service: {service}

⚠️ *Please note: This is an ESTIMATE only, not a final invoice. Actual charges may vary.*

👁️ View & Review your estimate online:
{invoice_link}

To *accept* this estimate, reply *APPROVED*.
To request changes, reply with your feedback.

Thank you for considering {company_name}! 🙏
📞 {company_phone} | ✉ {company_email}</textarea>
              <div class="wa-char-counter" id="wa-cnt-estimate"></div>
            </div>
            <button class="btn btn-outline" style="font-size:12px;padding:5px 12px" onclick="waTogglePreview('wa-prev-estimate')"><i class="fas fa-mobile-alt"></i> Preview</button>
            <div class="wa-preview-wrap" id="wa-prev-estimate"><div class="wa-bubble" id="wa-prev-estimate-bubble"></div><div class="wa-bubble-meta">Delivered ✓✓</div></div>
          </div>

          <div class="wa-tab-pane" id="watab-paid">
            <div class="field">
              <textarea id="wa-tpl-paid" style="min-height:140px;font-family:var(--mono);font-size:12.5px" oninput="saveWASettings();waUpdateCounter('wa-tpl-paid','wa-cnt-paid');waUpdatePreview('wa-tpl-paid','wa-prev-paid')">Hi {client_name}! ✅

Payment received for *Invoice #{invoice_no}*{settlement_discount_line}

💰 Amount Received: *{currency}{amount}*
📅 Date: {issue_date}
📋 Service: {service}

🔗 View Receipt:
{invoice_link}

Your account is now clear. Thank you! 🙏
— *{company_name}* | 📞 {company_phone}</textarea>
              <div class="wa-char-counter" id="wa-cnt-paid"></div>
            </div>
            <button class="btn btn-outline" style="font-size:12px;padding:5px 12px" onclick="waTogglePreview('wa-prev-paid')"><i class="fas fa-mobile-alt"></i> Preview</button>
            <div class="wa-preview-wrap" id="wa-prev-paid"><div class="wa-bubble" id="wa-prev-paid-bubble"></div><div class="wa-bubble-meta">Delivered ✓✓</div></div>
          </div>

          <div class="wa-tab-pane" id="watab-partial">
            <div class="field">
              <textarea id="wa-tpl-partial" style="min-height:140px;font-family:var(--mono);font-size:12.5px" oninput="saveWASettings();waUpdateCounter('wa-tpl-partial','wa-cnt-partial');waUpdatePreview('wa-tpl-partial','wa-prev-partial')">Hi {client_name}! 💚

*Partial Payment Received* for Invoice #{invoice_no}

✅ Paid: *{paid_amount}*
⏳ Remaining: *{remaining_amount}*
📋 Invoice Total: {currency}{amount}
📅 Date: {issue_date}

Please clear the remaining balance by *{due_date}*.
💳 UPI: {upi}

🔗 View Invoice:
{invoice_link}

Thank you! — *{company_name}*
📞 {company_phone}</textarea>
              <div class="wa-char-counter" id="wa-cnt-partial"></div>
            </div>
            <button class="btn btn-outline" style="font-size:12px;padding:5px 12px" onclick="waTogglePreview('wa-prev-partial')"><i class="fas fa-mobile-alt"></i> Preview</button>
            <div class="wa-preview-wrap" id="wa-prev-partial"><div class="wa-bubble" id="wa-prev-partial-bubble"></div><div class="wa-bubble-meta">Delivered ✓✓</div></div>
          </div>

          <div class="wa-tab-pane" id="watab-remind">
            <div class="field">
              <textarea id="wa-tpl-remind" style="min-height:140px;font-family:var(--mono);font-size:12.5px" oninput="saveWASettings();waUpdateCounter('wa-tpl-remind','wa-cnt-remind');waUpdatePreview('wa-tpl-remind','wa-prev-remind')">Hi {client_name}! 🔔 *Payment Reminder*

*Invoice #{invoice_no}* for *{currency}{amount}* is due on *{due_date}*.

📋 Service: {service}

💳 Pay via UPI: {upi}
🏦 {bank_details}

🔗 View Invoice:
{invoice_link}

Please make payment at your earliest convenience.
— {company_name} | 📞 {company_phone}</textarea>
              <div class="wa-char-counter" id="wa-cnt-remind"></div>
            </div>
            <button class="btn btn-outline" style="font-size:12px;padding:5px 12px" onclick="waTogglePreview('wa-prev-remind')"><i class="fas fa-mobile-alt"></i> Preview</button>
            <div class="wa-preview-wrap" id="wa-prev-remind"><div class="wa-bubble" id="wa-prev-remind-bubble"></div><div class="wa-bubble-meta">Delivered ✓✓</div></div>
          </div>

          <div class="wa-tab-pane" id="watab-overdue">
            <div class="field">
              <textarea id="wa-tpl-overdue" style="min-height:140px;font-family:var(--mono);font-size:12.5px" oninput="saveWASettings();waUpdateCounter('wa-tpl-overdue','wa-cnt-overdue');waUpdatePreview('wa-tpl-overdue','wa-prev-overdue')">Hi {client_name}! ⚠️ *Overdue Notice*

*Invoice #{invoice_no}* for *{currency}{amount}* was due on *{due_date}*.
Overdue by: *{days_overdue} days*

📋 Service: {service}

Please clear this immediately to avoid any inconvenience.
💳 UPI: {upi}

🔗 View Invoice:
{invoice_link}

— {company_name} | 📞 {company_phone}</textarea>
              <div class="wa-char-counter" id="wa-cnt-overdue"></div>
            </div>
            <button class="btn btn-outline" style="font-size:12px;padding:5px 12px" onclick="waTogglePreview('wa-prev-overdue')"><i class="fas fa-mobile-alt"></i> Preview</button>
            <div class="wa-preview-wrap" id="wa-prev-overdue"><div class="wa-bubble" id="wa-prev-overdue-bubble"></div><div class="wa-bubble-meta">Delivered ✓✓</div></div>
          </div>

          <div class="wa-tab-pane" id="watab-followup">
            <div class="field">
              <textarea id="wa-tpl-followup" style="min-height:140px;font-family:var(--mono);font-size:12.5px" oninput="saveWASettings();waUpdateCounter('wa-tpl-followup','wa-cnt-followup');waUpdatePreview('wa-tpl-followup','wa-prev-followup')">Hi {client_name},

This is a follow-up for *Invoice #{invoice_no}* (*{currency}{amount}*).
⚠️ Still overdue by *{days_overdue} days*

📋 Service: {service}

Kindly process payment immediately or contact us to discuss.
💳 UPI: {upi}

🔗 View Invoice:
{invoice_link}

— {company_name} | 📞 {company_phone} | ✉ {company_email}</textarea>
              <div class="wa-char-counter" id="wa-cnt-followup"></div>
            </div>
            <button class="btn btn-outline" style="font-size:12px;padding:5px 12px" onclick="waTogglePreview('wa-prev-followup')"><i class="fas fa-mobile-alt"></i> Preview</button>
            <div class="wa-preview-wrap" id="wa-prev-followup"><div class="wa-bubble" id="wa-prev-followup-bubble"></div><div class="wa-bubble-meta">Delivered ✓✓</div></div>
          </div>

          <div class="wa-tab-pane" id="watab-recurring">
            <div style="background:#E8F5FD;border-radius:8px;padding:10px 14px;font-size:12px;color:#0D47A1;margin-bottom:10px;line-height:1.7">
              <strong>🔁 Recurring Invoice Template:</strong> Sent automatically when a recurring schedule generates a new invoice. Supports all standard variables plus <code>{outstanding_dues}</code> which lists previous unpaid invoices and <code>{total_payable}</code> for combined outstanding amount.
            </div>
            <div class="field">
              <textarea id="wa-tpl-recurring" style="min-height:160px;font-family:var(--mono);font-size:12.5px" oninput="saveWASettings();waUpdateCounter('wa-tpl-recurring','wa-cnt-recurring');waUpdatePreview('wa-tpl-recurring','wa-prev-recurring')">Hi {client_name}! 🔁

*Recurring Invoice #{invoice_no}* from *{company_name}* is ready.

📋 Service: {service}
📅 Issue Date: {issue_date}
⏳ Due Date: *{due_date}*
💰 Amount: *{currency}{amount}*

{item_list}

💳 *Pay via UPI:* {upi}
🏦 {bank_details}

{outstanding_dues}

🔗 *View &amp; Download Invoice:*
{invoice_link}

Thank you for choosing {company_name}!
📞 {company_phone} | ✉ {company_email}</textarea>
              <div class="wa-char-counter" id="wa-cnt-recurring"></div>
            </div>
            <button class="btn btn-outline" style="font-size:12px;padding:5px 12px" onclick="waTogglePreview('wa-prev-recurring')"><i class="fas fa-mobile-alt"></i> Preview</button>
            <div class="wa-preview-wrap" id="wa-prev-recurring"><div class="wa-bubble" id="wa-prev-recurring-bubble"></div><div class="wa-bubble-meta">Delivered ✓✓</div></div>
          </div>

          <div class="wa-tab-pane" id="watab-custom">
            <div style="background:#E8F5E9;border-radius:8px;padding:10px 14px;font-size:12px;color:#1B5E20;margin-bottom:12px;line-height:1.7">
              <strong>✏️ Custom Message:</strong> Send a free-form message to any phone number via WhatsApp Business API. Bypasses all templates — use for special cases, replies, or one-off communication.
            </div>
            <div class="field">
              <label style="font-size:12px;color:var(--muted);margin-bottom:4px;display:block">Recipient Phone <span style="color:var(--muted)">(digits only, with country code e.g. 919876543210)</span></label>
              <input id="wa-custom-phone" type="tel" placeholder="919876543210" style="font-size:13px;margin-bottom:10px;width:100%">
            </div>
            <div class="field">
              <label style="font-size:12px;color:var(--muted);margin-bottom:4px;display:block">Message <span id="wa-custom-char" style="float:right;color:var(--muted)">0 / 4096</span></label>
              <textarea id="wa-custom-msg" rows="6" placeholder="Type your custom message here…"
                style="width:100%;font-size:13px;font-family:var(--mono);resize:vertical"
                oninput="const l=this.value.length;document.getElementById('wa-custom-char').textContent=l+' / 4096';if(l>4096)this.value=this.value.slice(0,4096)"></textarea>
            </div>
            <div style="display:flex;gap:8px;margin-top:8px;align-items:center">
              <button class="btn btn-primary" onclick="sendWACustomMessage()" style="display:flex;align-items:center;gap:6px">
                <i class="fab fa-whatsapp"></i> Send Now
              </button>
              <button class="btn btn-outline" onclick="document.getElementById('wa-custom-msg').value='';document.getElementById('wa-custom-phone').value='';document.getElementById('wa-custom-char').textContent='0 / 4096'">
                <i class="fas fa-times"></i> Clear
              </button>
              <span id="wa-custom-status" style="font-size:12px;color:var(--muted);margin-left:4px"></span>
            </div>
          </div>

          <div style="margin-top:14px;display:flex;gap:8px">
            <button class="btn btn-primary" onclick="saveWASettings()"><i class="fas fa-save"></i> Save All Templates</button>
            <button class="btn btn-outline" onclick="waResetCurrentTab()"><i class="fas fa-undo"></i> Reset to Default</button>
          </div>
        </div>

        <!-- ── ROW 3: Approved Templates + Manual Send ── -->
        <div class="wa-row wa-row-2">

          <!-- Approved Templates -->
          <div class="settings-block" style="margin:0">
            <div class="sb-title"><i class="fas fa-check-circle" style="color:#25D366"></i> Approved Templates (Meta)</div>
            <div style="background:#E8F5E9;border-radius:8px;padding:10px 14px;font-size:12px;color:#1B5E20;margin-bottom:14px;line-height:1.7">
              <strong>📋 How it works:</strong><br>
             • <strong>Session mode</strong> — free-form text, Works only within 24h of client messaging you.<br>
             • <strong>Template mode</strong> — Meta-approved, works anytime for any number.Requires template approval from Meta first..
            </div>
            <div class="field" style="margin-bottom:14px">
              <label>Sending Mode</label>
              <div style="display:flex;gap:10px;margin-top:6px">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:9px 14px;border:1.5px solid var(--border);border-radius:9px;flex:1;transition:.2s" id="mode-session-lbl">
                  <input type="radio" name="wa-msg-mode" value="session" id="mode-session" onchange="setWAMode('session')" style="accent-color:var(--teal)">
                  <div><div style="font-weight:700;font-size:12px">💬 Session</div><div style="font-size:10px;color:var(--muted)">24h window</div></div>
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:9px 14px;border:1.5px solid var(--border);border-radius:9px;flex:1;transition:.2s" id="mode-template-lbl">
                  <input type="radio" name="wa-msg-mode" value="template" id="mode-template" onchange="setWAMode('template')" style="accent-color:var(--teal)">
                  <div><div style="font-weight:700;font-size:12px">✅ Templates</div><div style="font-size:10px;color:var(--muted)">Any time</div></div>
                </label>
              </div>
            </div>
            <div id="tpl-names-section" style="display:none">
              <div style="font-size:11px;color:var(--muted);margin-bottom:10px">
                Enter template names exactly as approved in <a href="https://business.facebook.com/wa/manage/message-templates/" target="_blank" style="color:var(--teal)">Meta Business Manager</a>. Language: <code>en_US</code>
              </div>
              <div class="form-grid g1" style="gap:10px">
                <div class="field"><label>📄 Invoice Created</label><div style="display:flex;gap:6px"><input id="tpl-name-invoice" placeholder="invoice_created" style="flex:1"><input id="tpl-lang-invoice" placeholder="en_US" style="width:70px;text-align:center"></div><div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}amount {{4}}due {{5}}upi {{6}}company {{7}}link</div></div>
                <div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}amount {{4}}due {{5}}upi {{6}}company {{7}}link</div>
                <div class="field"><label>📋 Estimate / Quote</label><div style="display:flex;gap:6px"><input id="tpl-name-estimate" placeholder="estimate_created" style="flex:1"><input id="tpl-lang-estimate" placeholder="en_US" style="width:70px;text-align:center"></div><div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}quote# {{3}}amount {{4}}valid_until {{5}}service {{6}}company {{7}}link</div></div>
                <div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}amount {{4}}due {{5}}service {{6}}company {{7}}link</div>
                <div class="field"><label>🔔 Payment Reminder</label><div style="display:flex;gap:6px"><input id="tpl-name-reminder" placeholder="payment_reminder" style="flex:1"><input id="tpl-lang-reminder" placeholder="en_US" style="width:70px;text-align:center"></div><div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}amount {{4}}due {{5}}upi {{6}}company {{7}}link</div></div>
                <div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}amount {{4}}due {{5}}upi {{6}}company {{7}}link</div>
                <div class="field"><label>⚠️ Payment Overdue</label><div style="display:flex;gap:6px"><input id="tpl-name-overdue" placeholder="payment_overdue" style="flex:1"><input id="tpl-lang-overdue" placeholder="en_US" style="width:70px;text-align:center"></div><div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}amount {{4}}days {{5}}upi {{6}}company {{7}}link</div></div>
                <div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}amount {{4}}days {{5}}upi {{6}}link {{7}}phone {{8}}company</div>
                <div class="field"><label>✅ Payment Received</label><div style="display:flex;gap:6px"><input id="tpl-name-paid" placeholder="payment_received" style="flex:1"><input id="tpl-lang-paid" placeholder="en_US" style="width:70px;text-align:center"></div><div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}amount {{4}}disc {{5}}date {{6}}company {{7}}link</div></div>
                <div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}amount {{4}}discount {{5}}date {{6}}company {{7}}link</div>
                <div class="field"><label>📋 Invoice Follow-up</label><div style="display:flex;gap:6px"><input id="tpl-name-followup" placeholder="invoice_followup" style="flex:1"><input id="tpl-lang-followup" placeholder="en_US" style="width:70px;text-align:center"></div><div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}amount {{4}}days {{5}}upi {{6}}phone {{7}}link</div></div>
                <div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}amount {{4}}days {{5}}upi {{6}}phone {{7}}link</div>
                <div class="field"><label>🔁 Recurring Invoice</label><div style="display:flex;gap:6px"><input id="tpl-name-recurring" placeholder="recurring_invoice" style="flex:1"><input id="tpl-lang-recurring" placeholder="en_US" style="width:70px;text-align:center"></div><div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}amount {{4}}due {{5}}upi {{6}}link {{7}}outstanding</div></div>
                <div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}amount {{4}}due {{5}}upi {{6}}company {{7}}link</div>
                <div class="field"><label>💚 Partial Payment</label><div style="display:flex;gap:6px"><input id="tpl-name-partial" placeholder="partial_payment" style="flex:1"><input id="tpl-lang-partial" placeholder="en_US" style="width:70px;text-align:center"></div><div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}paid {{4}}remaining {{5}}due {{6}}link</div></div>
                <div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}paid {{4}}remaining {{5}}due {{6}}link</div>
                <div class="field"><label>💰 Balance Reminder</label><div style="display:flex;gap:6px"><input id="tpl-name-balance-reminder" placeholder="balance_reminder" style="flex:1"><input id="tpl-lang-balance-reminder" placeholder="en_US" style="width:70px;text-align:center"></div><div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}paid {{4}}remaining {{5}}due {{6}}link</div></div>
                <div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}inv# {{3}}paid {{4}}remaining {{5}}due {{6}}link</div>
                <div class="field"><label>🎉 Festival Greeting</label><div style="display:flex;gap:6px"><input id="tpl-name-festival" placeholder="festival_greeting" style="flex:1"><input id="tpl-lang-festival" placeholder="en_US" style="width:70px;text-align:center"></div><div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}company {{3}}phone</div></div>
                <div style="font-size:10px;color:var(--muted);margin-top:2px">{{1}}name {{2}}company {{3}}phone</div>
              </div>

              <!-- Suggested template content — collapsible -->
              <details style="margin-top:14px">
                <summary style="cursor:pointer;font-size:12px;font-weight:700;color:var(--muted);list-style:none;display:flex;align-items:center;gap:6px"><i class="fas fa-file-alt"></i> Suggested content for Meta approval</summary>
                <div style="margin-top:10px;background:var(--bg);border-radius:8px;padding:12px;border:1px solid var(--border)">
                  <details style="margin-bottom:6px"><summary style="cursor:pointer;font-size:12px;font-weight:600;color:#3949AB">estimate_created — UTILITY</summary><pre style="font-size:11px;background:#fff;padding:8px;border-radius:6px;margin-top:4px;white-space:pre-wrap;border:1px solid var(--border)">Hi {{1}},

📋 *Estimate #{{2}}* from {{6}}

💰 Estimated Amount: *{{3}}*
⏳ Valid Until: *{{4}}*
📋 Service: {{5}}

⚠️ This is an ESTIMATE only, not a final invoice.

👁️ View &amp; Review: {{7}}

To accept, reply *APPROVED*. — {{6}}</pre></details>
                  <details style="margin-bottom:6px"><summary style="cursor:pointer;font-size:12px;font-weight:600;color:var(--teal)">invoice_created — UTILITY</summary><pre style="font-size:11px;background:#fff;padding:8px;border-radius:6px;margin-top:4px;white-space:pre-wrap;border:1px solid var(--border)">
Hi {{1}},

 *Invoice -  #{{2}}* from {{6}}
 *Summary :*
- Service: {{3}}
- *Issue Date:* {{4}}
- *Due Date:* {{5}}
- *Total Amount Due :* *{{7}}*
*Breakdown*
{{8}}

*Pay via UPI:* {{9}}

{{10}}
*Invoice Link*
{{11}}

Thank you for choosing {{6}}!
{{12}} | ✉ {{13}}
                  </pre>
                </details>
                  <details style="margin-bottom:6px"><summary style="cursor:pointer;font-size:12px;font-weight:600;color:var(--amber)">payment_reminder — UTILITY</summary><pre style="font-size:11px;background:#fff;padding:8px;border-radius:6px;margin-top:4px;white-space:pre-wrap;border:1px solid var(--border)">Hi {{1}},

Friendly reminder: Invoice #{{2}} for ₹{{3}} is due on {{4}}.
Pay via UPI: {{5}}

Thank you, {{6}}
View Invoice: {{7}}</pre></details>
                  <details style="margin-bottom:6px"><summary style="cursor:pointer;font-size:12px;font-weight:600;color:var(--red)">payment_overdue — UTILITY</summary><pre style="font-size:11px;background:#fff;padding:8px;border-radius:6px;margin-top:4px;white-space:pre-wrap;border:1px solid var(--border)">Hi {{1}},

Hi *{{1}}*,
Invoice #*{{2}}* for ₹*{{3}}* is overdue by *{{4}}* days.
Please pay immediately via *UPI* : {{5}}

*View Invoice* : {{6}}
Contact *{{7}}* for any queries.
*{{8}}*
Thanks</pre></details>
                  <details style="margin-bottom:6px"><summary style="cursor:pointer;font-size:12px;font-weight:600;color:var(--blue)">payment_received — UTILITY</summary><pre style="font-size:11px;background:#fff;padding:8px;border-radius:6px;margin-top:4px;white-space:pre-wrap;border:1px solid var(--border)">Hi {{1}},

Payment received for Invoice #{{2}}!
Amount: ₹{{3}} | Discount: {{4}}
Date: {{5}}

Thank you! — {{6}}
View Receipt: {{7}}</pre></details>
                  <details style="margin-bottom:6px"><summary style="cursor:pointer;font-size:12px;font-weight:600;color:var(--orange)">invoice_followup — UTILITY</summary><pre style="font-size:11px;background:#fff;padding:8px;border-radius:6px;margin-top:4px;white-space:pre-wrap;border:1px solid var(--border)">Hi {{1}},

Follow-up for Invoice #{{2}} (₹{{3}}).
Overdue by {{4}} days. Pay via UPI: {{5}}

Contact: {{6}}
View Invoice: {{7}}</pre></details>
                  <details style="margin-bottom:6px"><summary style="cursor:pointer;font-size:12px;font-weight:600;color:var(--green)">partial_payment — UTILITY</summary><pre style="font-size:11px;background:#fff;padding:8px;border-radius:6px;margin-top:4px;white-space:pre-wrap;border:1px solid var(--border)">Hi {{1}},

Partial payment received for Invoice #{{2}}.
Paid: ₹{{3}} | Remaining: ₹{{4}}
Due by: {{5}}

View Invoice: {{6}}</pre></details>
                  <details style="margin-bottom:6px"><summary style="cursor:pointer;font-size:12px;font-weight:600;color:#D97706">balance_reminder — UTILITY</summary><pre style="font-size:11px;background:#fff;padding:8px;border-radius:6px;margin-top:4px;white-space:pre-wrap;border:1px solid var(--border)">Hi {{1}},

💰 *Balance Reminder* — Invoice #{{2}}

Amount Paid: *₹{{3}}*
Remaining Balance: *₹{{4}}*
Due by: {{5}}

Please clear the balance at your earliest convenience.

View Invoice: {{6}}</pre></details>
                </div>
              </details>
              <button class="btn btn-primary" style="margin-top:12px" onclick="saveWASettings()"><i class="fas fa-save"></i> Save Template Settings</button>
            </div>
          </div>

          <!-- Manual Send + Quick Replies -->
          <div class="settings-block" style="margin:0">
            <div class="sb-title"><i class="fas fa-paper-plane"></i> Send Manual Message</div>
            <div class="form-grid g2">
              <div class="field"><label>Client</label>
                <select id="wa-manual-client" onchange="fillWaManualPhone()">
                  <option value="">-- Select Client --</option>
                </select>
              </div>
              <div class="field"><label>WhatsApp Number</label>
                <input id="wa-manual-phone" placeholder="+91 XXXXX XXXXX">
              </div>
            </div>

            <!-- Quick Reply Templates -->
            <div class="wa-section-label" style="margin-top:12px">Quick Replies — click to use</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">
              <span class="wa-quick-chip" onclick="waQuickReply('payment')">💰 Payment request</span>
              <span class="wa-quick-chip" onclick="waQuickReply('followup')">📋 Follow-up</span>
              <span class="wa-quick-chip" onclick="waQuickReply('thankyou')">🙏 Thank you</span>
              <span class="wa-quick-chip" onclick="waQuickReply('custom')">✏️ Custom…</span>
            </div>

            <div class="field">
              <label>Message <span id="wa-manual-counter" style="float:right;font-size:10px;color:var(--muted)"></span></label>
              <textarea id="wa-manual-msg" style="min-height:100px;font-family:var(--mono);font-size:12.5px" placeholder="Type your message here..." oninput="waUpdateCounter('wa-manual-msg','wa-manual-counter')"></textarea>
            </div>
            <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
              <button class="btn btn-whatsapp" style="flex:1" onclick="sendManualWA()"><i class="fab fa-whatsapp"></i> Send</button>
              <button class="btn btn-outline" onclick="waTogglePreview('wa-prev-manual')"><i class="fas fa-mobile-alt"></i> Preview</button>
            </div>
            <div class="wa-preview-wrap" id="wa-prev-manual"><div class="wa-bubble" id="wa-prev-manual-bubble"></div><div class="wa-bubble-meta">Delivered ✓✓</div></div>
          </div>
        </div>

        <!-- ── ROW 4: Festival / Bulk (full width) ── -->
        <div class="settings-block" style="margin:0">
          <div class="sb-title"><i class="fas fa-star" style="color:var(--amber)"></i> Festival &amp; Bulk Campaign</div>
          <div style="background:var(--amber-bg);border-radius:8px;padding:10px 14px;font-size:12px;color:#92400E;margin-bottom:14px">
            ✨ Send personalised festival greetings to all or selected clients. Requires WhatsApp Business API.
          </div>
          <div class="form-grid g2" style="gap:12px">
            <div class="field"><label>Festival / Occasion</label>
              <select id="wa-festival">
                <option value="diwali">Diwali 🪔</option>
                <option value="holi">Holi 🎨</option>
                <option value="eid">Eid Mubarak 🌙</option>
                <option value="christmas">Christmas 🎄</option>
                <option value="newyear">New Year 🎊</option>
                <option value="independence">Independence Day 🇮🇳</option>
                <option value="custom">Custom Occasion</option>
              </select>
            </div>
            <div class="field"><label>Custom Occasion Name</label>
              <input id="wa-festival-custom" placeholder="e.g. Our Anniversary Sale">
            </div>
            <div class="field"><label>Festival Image URL</label>
              <div style="display:flex;gap:6px">
                <input id="wa-festival-img" placeholder="https://... (optional)" style="flex:1">
                <label style="display:flex;align-items:center;gap:4px;padding:0 10px;border-radius:8px;border:1.5px solid var(--border);cursor:pointer;font-size:11px;color:var(--muted);white-space:nowrap;background:var(--bg)">
                  <i class="fas fa-upload"></i>
                  <input type="file" accept="image/*" style="display:none" onchange="handleLogoUpload(this,'wa-festival-img','wa-festival-img-preview')">
                </label>
              </div>
              <div id="wa-festival-img-preview" style="margin-top:6px"></div>
            </div>
            <div class="field"><label>Send To</label>
              <select id="wa-send-to">
                <option value="all">All Active Clients</option>
                <option value="paid">Clients with Paid Invoices</option>
                <option value="active">Recent Activity (90 days)</option>
              </select>
            </div>
            <div class="field"><label>Schedule Date &amp; Time <span style="font-size:10px;color:var(--muted)">(blank = send now)</span></label>
              <input type="datetime-local" id="wa-festival-schedule" style="width:100%">
            </div>
            <div class="field"><label>Repeat</label>
              <select id="wa-festival-repeat">
                <option value="">No repeat (one-time)</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option>
              </select>
            </div>
          </div>
          <div class="field" style="margin-top:12px"><label>Festival Message</label>
            <textarea id="wa-tpl-festival" style="min-height:80px;font-family:var(--mono);font-size:12.5px" oninput="saveWASettings()"><?= htmlspecialchars($settings['wa_tpl_festival'] ?? 'Hi {client_name}! 🌟 Wishing you and your family warm greetings on this special occasion! Thank you for your continued trust in {company_name}! 🙏') ?></textarea>
          </div>
          <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
            <button class="btn btn-outline" onclick="previewFestivalMsg()"><i class="fas fa-eye"></i> Preview</button>
            <button class="btn btn-primary" onclick="saveFestivalCampaign()"><i class="fas fa-save"></i> Save Campaign</button>
            <button class="btn btn-whatsapp" onclick="sendFestivalBulk()"><i class="fab fa-whatsapp"></i> Send Now</button>
          </div>
          <div id="wa-bulk-log" style="margin-top:14px;max-height:150px;overflow-y:auto;background:var(--bg);border-radius:8px;padding:10px;font-size:12px;color:var(--muted);display:none"></div>
          <div id="wa-campaigns-list" style="margin-top:12px"></div>
        </div>

      </div><!-- end wa-page -->
    </div>

    <!-- ─────────── EMAIL SETUP ─────────── -->
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
