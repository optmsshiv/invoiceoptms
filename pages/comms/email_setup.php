<?php
// ================================================================
//  pages/comms/email_setup.php
//  SMTP configuration, email templates, automation rules, send
//  logs, and multi-profile SMTP management.
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.email_setup');

$user = currentUser();

$activePage  = 'email-setup';
$pageTitle   = 'Email Setup';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/email-setup.js'];

include __DIR__ . '/../../includes/layout_header.php';
?>
    <div id="page-email-setup" class="page">

        <!-- ── Tab Bar (full width, outside settings-wrap) ── -->
        <div style="display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:20px;overflow-x:auto">
          <button class="em-tab-btn active" onclick="emTab('smtp',this)" style="padding:10px 20px;background:none;border:none;font-weight:700;font-size:13px;cursor:pointer;border-bottom:2px solid var(--teal);color:var(--teal);margin-bottom:-2px;white-space:nowrap"><i class="fas fa-server"></i> SMTP</button>
          <button class="em-tab-btn" onclick="emTab('tpl',this)" style="padding:10px 20px;background:none;border:none;font-weight:600;font-size:13px;cursor:pointer;color:var(--muted);margin-bottom:-2px;white-space:nowrap"><i class="fas fa-file-alt"></i> Templates</button>
          <button class="em-tab-btn" onclick="emTab('auto',this)" style="padding:10px 20px;background:none;border:none;font-weight:600;font-size:13px;cursor:pointer;color:var(--muted);margin-bottom:-2px;white-space:nowrap"><i class="fas fa-robot"></i> Automation</button>
          <button class="em-tab-btn" onclick="emTab('logs',this)" style="padding:10px 20px;background:none;border:none;font-weight:600;font-size:13px;cursor:pointer;color:var(--muted);margin-bottom:-2px;white-space:nowrap"><i class="fas fa-history"></i> Logs</button>
          <button class="em-tab-btn" onclick="emTab('profiles',this)" style="padding:10px 20px;background:none;border:none;font-weight:600;font-size:13px;cursor:pointer;color:var(--muted);margin-bottom:-2px;white-space:nowrap"><i class="fas fa-layer-group"></i> Profiles</button>
        </div>

      <!-- ── Narrow tabs (SMTP / Templates / Automation / Profiles) stay in settings-wrap ── -->
      <div class="settings-wrap">

        <!-- ══ TAB: SMTP ══ -->
        <div id="em-tab-smtp" class="em-tab-pane">
          <div class="settings-block">
            <div class="sb-title"><i class="fas fa-server" style="color:#1976D2"></i> SMTP Configuration
              <span id="em-smtp-status" style="margin-left:auto;font-size:11px;font-weight:600"></span>
            </div>
            <!-- Provider quick-select -->
            <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
              <button onclick="emFillProvider('gmail')" style="padding:6px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px"><img src="https://www.google.com/favicon.ico" width="14" height="14"> Gmail</button>
              <button onclick="emFillProvider('outlook')" style="padding:6px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);font-size:12px;font-weight:600;cursor:pointer">🪟 Outlook</button>
              <button onclick="emFillProvider('yahoo')" style="padding:6px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);font-size:12px;font-weight:600;cursor:pointer">🟣 Yahoo</button>
              <button onclick="emFillProvider('sendgrid')" style="padding:6px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);font-size:12px;font-weight:600;cursor:pointer">📨 SendGrid</button>
              <button onclick="emFillProvider('mailgun')" style="padding:6px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);font-size:12px;font-weight:600;cursor:pointer">🔫 Mailgun</button>
              <button onclick="emFillProvider('custom')" style="padding:6px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);font-size:12px;font-weight:600;cursor:pointer">⚙️ Custom</button>
            </div>
            <div class="form-grid g2">
              <div class="field"><label>From Email</label><input id="em-from" placeholder="invoices@yourcompany.in"></div>
              <div class="field"><label>From Name</label><input id="em-name" placeholder="<?= htmlspecialchars($companyName) ?> Invoices"></div>
              <div class="field"><label>SMTP Host</label><input id="em-host" placeholder="smtp.gmail.com"></div>
              <div class="field"><label>Port</label>
                <select id="em-port" style="width:100%;padding:9px 12px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);color:var(--text);font-size:13px">
                  <option value="587">587 — TLS (recommended)</option>
                  <option value="465">465 — SSL</option>
                  <option value="25">25 — Plain (not recommended)</option>
                </select>
              </div>
              <div class="field"><label>Username</label><input id="em-user" placeholder="your@gmail.com"></div>
              <div class="field"><label>App Password <span style="font-size:10px;color:var(--muted);font-weight:400">(not your main password)</span></label>
                <div style="position:relative">
                  <input type="password" id="em-pass" placeholder="Gmail App Password" style="width:100%;padding-right:36px">
                  <i class="fas fa-eye" onclick="emTogglePass()" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--muted)"></i>
                </div>
              </div>
            </div>
            <!-- Gmail helper -->
            <div id="em-gmail-hint" style="display:none;background:#E8F5E9;border-radius:8px;padding:12px 16px;font-size:12px;color:#2E7D32;margin-bottom:12px;line-height:1.7">
              <strong>📌 Gmail Setup:</strong><br>
              1. Go to <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:#2E7D32">myaccount.google.com/apppasswords</a><br>
              2. Create an App Password for "Mail"<br>
              3. Paste that 16-character password above — NOT your Gmail login password<br>
              4. Make sure 2-Step Verification is ON in your Google account
            </div>
            <div class="toggle-list" style="margin-top:12px">
              <div class="toggle-item"><span><strong>CC yourself</strong> on every email sent</span><div class="tog" id="em-tog-cc" onclick="this.classList.toggle('on')"></div></div>
              <div class="toggle-item"><span><strong>Open Tracking</strong> — know when client opens email</span><div class="tog on" id="em-tog-track" onclick="this.classList.toggle('on')"></div></div>
            </div>
            <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap">
              <button class="btn btn-outline" onclick="testEmail()"><i class="fas fa-paper-plane"></i> Send Test Email</button>
              <button class="btn btn-primary" onclick="saveEmailSettings()"><i class="fas fa-save"></i> Save Settings</button>
            </div>
          </div>
        </div>

        <!-- ══ TAB: TEMPLATES ══ -->
        <div id="em-tab-tpl" class="em-tab-pane" style="display:none">
          <div class="settings-block">
            <div class="sb-title"><i class="fas fa-file-alt" style="color:#6A1B9A"></i> Email Templates
              <button class="btn btn-primary" style="margin-left:auto;padding:6px 14px;font-size:12px" onclick="saveEmailTemplate()"><i class="fas fa-save"></i> Save Template</button>
            </div>
            <!-- Template type tabs -->
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
              <button class="em-tpl-btn active" onclick="emTplTab('invoice',this)" style="padding:7px 14px;border-radius:8px;border:1.5px solid var(--teal);background:var(--teal);color:#fff;font-size:12px;font-weight:700;cursor:pointer">📄 Invoice</button>
              <button class="em-tpl-btn" onclick="emTplTab('estimate',this)" style="padding:7px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);font-size:12px;font-weight:600;cursor:pointer">📋 Estimate</button>
              <button class="em-tpl-btn" onclick="emTplTab('receipt',this)" style="padding:7px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);font-size:12px;font-weight:600;cursor:pointer">✅ Receipt</button>
              <button class="em-tpl-btn" onclick="emTplTab('reminder',this)" style="padding:7px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);font-size:12px;font-weight:600;cursor:pointer">🔔 Reminder</button>
              <button class="em-tpl-btn" onclick="emTplTab('overdue',this)" style="padding:7px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);font-size:12px;font-weight:600;cursor:pointer">⚠️ Overdue</button>
              <button class="em-tpl-btn" onclick="emTplTab('followup',this)" style="padding:7px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);font-size:12px;font-weight:600;cursor:pointer">📞 Follow-up</button>
            </div>
            <input type="hidden" id="em-tpl-type" value="invoice">
            <!-- Variable chips -->
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px">Click to insert variable</div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px" id="em-var-chips">
              <?php foreach(['{client_name}','{invoice_no}','{amount}','{currency}','{due_date}','{issue_date}','{service}','{company_name}','{company_phone}','{company_email}','{upi}','{bank_details}','{days_overdue}','{item_list}','{paid_amount}','{remaining_amount}','{invoice_link}'] as $v): ?>
              <span onclick="emInsertVar('<?= $v ?>')" style="padding:3px 10px;border-radius:20px;background:var(--teal-bg);color:var(--teal);font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--teal)"><?= $v ?></span>
              <?php endforeach; ?>
            </div>
            <div class="field"><label>Subject</label><input id="em-tpl-subj" placeholder="Email subject line..."></div>
            <div class="field"><label>Email Body</label>
              <textarea id="em-tpl-body" style="min-height:220px;font-family:var(--mono);font-size:12.5px;line-height:1.7" placeholder="Email body..."></textarea>
            </div>
            <button class="btn btn-outline" style="margin-top:8px" onclick="emPreviewTemplate()"><i class="fas fa-eye"></i> Preview Email</button>
          </div>
        </div>

        <!-- ══ TAB: AUTOMATION ══ -->
        <div id="em-tab-auto" class="em-tab-pane" style="display:none">
          <div class="settings-block">
            <div class="sb-title"><i class="fas fa-robot" style="color:#E65100"></i> Email Automation</div>
            <div class="toggle-list">
              <div class="toggle-item">
                <span><strong>📄 New Invoice</strong> — auto-send email when invoice is created</span>
                <div class="tog" id="em-auto-inv" onclick="this.classList.toggle('on');saveEmailAuto()"></div>
              </div>
              <div class="toggle-item">
                <span><strong>📋 New Estimate</strong> — auto-send email when estimate is saved</span>
                <div class="tog" id="em-auto-est" onclick="this.classList.toggle('on');saveEmailAuto()"></div>
              </div>
              <div class="toggle-item">
                <span><strong>✅ Payment Received</strong> — send receipt when invoice marked Paid</span>
                <div class="tog on" id="em-auto-paid" onclick="this.classList.toggle('on');saveEmailAuto()"></div>
              </div>
              <div class="toggle-item">
                <span><strong>💚 Partial Payment</strong> — send receipt on partial payment</span>
                <div class="tog on" id="em-auto-partial" onclick="this.classList.toggle('on');saveEmailAuto()"></div>
              </div>
              <div class="toggle-item">
                <span><strong>🔔 Due Date Reminder</strong> — email N days before due date</span>
                <div class="tog on" id="em-auto-remind" onclick="this.classList.toggle('on');saveEmailAuto()"></div>
              </div>
              <div class="toggle-item">
                <span><strong>⚠️ Overdue Alert</strong> — email on due date if unpaid</span>
                <div class="tog on" id="em-auto-overdue" onclick="this.classList.toggle('on');saveEmailAuto()"></div>
              </div>
              <div class="toggle-item">
                <span><strong>📞 Overdue Follow-up</strong> — repeat overdue emails every N days</span>
                <div class="tog" id="em-auto-followup" onclick="this.classList.toggle('on');saveEmailAuto()"></div>
              </div>
            </div>
            <div style="background:var(--teal-bg);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--teal);margin-top:12px;line-height:1.7">
              <i class="fas fa-info-circle"></i> <strong>Timing rules</strong> (days before due, follow-up interval, max follow-ups) are configured in the
              <a href="#" onclick="window.location.href='/pages/tools/reminders.php';return false;" style="color:var(--teal);font-weight:700;text-decoration:underline">Reminders page</a>.
            </div>
            <div style="background:var(--teal-bg);border-radius:8px;padding:12px 16px;font-size:12px;color:var(--teal);margin-top:8px;line-height:1.7">
              <strong>⚙️ How automation works:</strong> A cron job at <code>api/email_cron.php</code> runs daily and checks all invoices. Set it up in cPanel → Cron Jobs → <code>php /path/to/api/email_cron.php</code> → Every day at 9 AM.
            </div>
          </div>
        </div>

      </div><!-- end settings-wrap: SMTP / Templates / Automation -->

        <!-- ══ TAB: LOGS — full width, outside settings-wrap ══ -->
        <div id="em-tab-logs" class="em-tab-pane" style="display:none">
          <div class="settings-block" style="max-width:100%">
            <div class="sb-title"><i class="fas fa-history" style="color:#37474F"></i> Email Logs
              <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
                <button class="btn btn-outline" style="padding:5px 12px;font-size:12px" onclick="toggleEmailSubject(this)"><i class="fas fa-eye-slash"></i> Hide Subject</button>
                <button class="btn btn-outline" style="padding:5px 12px;font-size:12px" onclick="exportEmailLogsCsv()"><i class="fas fa-download"></i> CSV</button>
                <button class="btn btn-outline" style="padding:5px 12px;font-size:12px" onclick="loadEmailLogs()"><i class="fas fa-sync"></i> Refresh</button>
              </div>
            </div>

            <!-- Stats row -->
            <div id="em-log-stats" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px"></div>

            <!-- Filters -->
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:flex-end">
              <div>
                <div style="font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Status</div>
                <div style="display:flex;border:1px solid var(--border);border-radius:8px;overflow:hidden">
                  <button class="em-status-pill active" data-val="" onclick="emLogPill(this,'status')" style="padding:6px 13px;font-size:12px;border:none;background:var(--teal);color:#fff;cursor:pointer;white-space:nowrap">All</button>
                  <button class="em-status-pill" data-val="sent" onclick="emLogPill(this,'status')" style="padding:6px 13px;font-size:12px;border:none;background:var(--bg);color:var(--muted);cursor:pointer;white-space:nowrap">Sent</button>
                  <button class="em-status-pill" data-val="failed" onclick="emLogPill(this,'status')" style="padding:6px 13px;font-size:12px;border:none;background:var(--bg);color:var(--muted);cursor:pointer;white-space:nowrap">Failed</button>
                  <button class="em-status-pill" data-val="opened" onclick="emLogPill(this,'status')" style="padding:6px 13px;font-size:12px;border:none;background:var(--bg);color:var(--muted);cursor:pointer;white-space:nowrap">Opened</button>
                </div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Type</div>
                <div style="display:flex;border:1px solid var(--border);border-radius:8px;overflow:hidden">
                  <button class="em-type-pill active" data-val="" onclick="emLogPill(this,'type')" style="padding:6px 13px;font-size:12px;border:none;background:var(--teal);color:#fff;cursor:pointer;white-space:nowrap">All</button>
                  <button class="em-type-pill" data-val="invoice" onclick="emLogPill(this,'type')" style="padding:6px 13px;font-size:12px;border:none;background:var(--bg);color:var(--muted);cursor:pointer;white-space:nowrap">Invoice</button>
                  <button class="em-type-pill" data-val="receipt" onclick="emLogPill(this,'type')" style="padding:6px 13px;font-size:12px;border:none;background:var(--bg);color:var(--muted);cursor:pointer;white-space:nowrap">Receipt</button>
                  <button class="em-type-pill" data-val="reminder" onclick="emLogPill(this,'type')" style="padding:6px 13px;font-size:12px;border:none;background:var(--bg);color:var(--muted);cursor:pointer;white-space:nowrap">Reminder</button>
                  <button class="em-type-pill" data-val="overdue" onclick="emLogPill(this,'type')" style="padding:6px 13px;font-size:12px;border:none;background:var(--bg);color:var(--muted);cursor:pointer;white-space:nowrap">Overdue</button>
                  <button class="em-type-pill" data-val="followup" onclick="emLogPill(this,'type')" style="padding:6px 13px;font-size:12px;border:none;background:var(--bg);color:var(--muted);cursor:pointer;white-space:nowrap">Follow-up</button>
                </div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">From</div>
                <input type="date" id="em-log-from" onchange="loadEmailLogs()" style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:12px">
              </div>
              <div>
                <div style="font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">To</div>
                <input type="date" id="em-log-to" onchange="loadEmailLogs()" style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:12px">
              </div>
            </div>

            <div id="em-logs-table" style="font-size:13px">
              <div style="color:var(--muted);text-align:center;padding:32px">Click Refresh to load logs</div>
            </div>

            <!-- Pagination -->
            <div id="em-log-pagination" style="display:none;align-items:center;justify-content:space-between;padding:12px 0 0;font-size:12px;color:var(--muted)">
              <span id="em-log-page-info"></span>
              <div id="em-log-page-btns" style="display:flex;gap:4px"></div>
            </div>
          </div>
        </div>

      <div class="settings-wrap"><!-- reopen settings-wrap for Profiles -->

        <!-- ══ TAB: SMTP PROFILES ══ -->
        <div id="em-tab-profiles" class="em-tab-pane" style="display:none">
          <div class="settings-block">
            <div class="sb-title"><i class="fas fa-layer-group" style="color:#1565C0"></i> SMTP Profiles
              <button class="btn btn-primary" style="margin-left:auto;padding:6px 14px;font-size:12px" onclick="emNewProfile()"><i class="fas fa-plus"></i> Add Profile</button>
            </div>
            <div id="em-profiles-list" style="font-size:13px">
              <div style="color:var(--muted);text-align:center;padding:32px">Loading...</div>
            </div>
          </div>
          <!-- Profile form -->
          <div id="em-profile-form" class="settings-block" style="display:none;margin-top:16px">
            <div class="sb-title"><i class="fas fa-edit"></i> <span id="em-profile-form-title">New SMTP Profile</span></div>
            <input type="hidden" id="ep-id">
            <div class="form-grid g2">
              <div class="field"><label>Profile Name</label><input id="ep-name" placeholder="e.g. Gmail Main"></div>
              <div class="field"><label>Provider</label>
                <select id="ep-provider" onchange="emProfileProviderChange()" style="width:100%;padding:9px 12px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);color:var(--text);font-size:13px">
                  <option value="smtp">Custom SMTP</option>
                  <option value="gmail">Gmail</option>
                  <option value="outlook">Outlook</option>
                  <option value="sendgrid">SendGrid</option>
                  <option value="mailgun">Mailgun</option>
                </select>
              </div>
              <div class="field"><label>SMTP Host</label><input id="ep-host" placeholder="smtp.gmail.com"></div>
              <div class="field"><label>Port</label><input id="ep-port" value="587" type="number"></div>
              <div class="field"><label>Username</label><input id="ep-user" placeholder="your@gmail.com"></div>
              <div class="field"><label>Password / App Password</label><input type="password" id="ep-pass" placeholder="Enter password or app password"></div>
              <div class="field"><label>From Email</label><input id="ep-from" placeholder="noreply@<?= htmlspecialchars($settings['company_website'] ?? 'yourcompany.in') ?>"></div>
              <div class="field"><label>From Name</label><input id="ep-fname" placeholder="<?= htmlspecialchars($companyName) ?>"></div>
              <div class="field"><label>API Key <span style="font-size:10px;color:var(--muted)">(SendGrid/Mailgun only)</span></label><input id="ep-apikey" placeholder="SG.xxxx or key-xxxx"></div>
              <div class="field" style="display:flex;align-items:center;gap:10px;padding-top:20px">
                <input type="checkbox" id="ep-default" style="width:16px;height:16px">
                <label for="ep-default" style="font-size:13px;font-weight:600;cursor:pointer">Set as Default</label>
              </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:16px">
              <button class="btn btn-primary" onclick="saveSmtpProfile()"><i class="fas fa-save"></i> Save Profile</button>
              <button class="btn btn-outline" onclick="document.getElementById('em-profile-form').style.display='none'">Cancel</button>
            </div>
          </div>
        </div>

      </div><!-- end settings-wrap: Profiles -->
    </div><!-- end page-email-setup -->

    <!-- ── Email Preview Modal ── -->
    <div id="em-preview-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;display:none;align-items:center;justify-content:center;padding:20px">
      <div style="background:#fff;border-radius:16px;width:100%;max-width:680px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column">
        <div style="padding:16px 24px;border-bottom:1px solid #eee;display:flex;align-items:center;gap:12px">
          <div>
            <div style="font-weight:700;font-size:15px">Email Preview</div>
            <div id="em-preview-subject" style="font-size:12px;color:#666;margin-top:2px"></div>
          </div>
          <button onclick="document.getElementById('em-preview-modal').style.display='none'" style="margin-left:auto;background:none;border:none;font-size:20px;cursor:pointer;color:#999">✕</button>
        </div>
        <div style="overflow-y:auto;flex:1">
          <iframe id="em-preview-frame" style="width:100%;height:600px;border:none"></iframe>
        </div>
      </div>
    </div>

    <!-- ─────────── PROFILE ─────────── -->
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
