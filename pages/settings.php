<?php
// ================================================================
//  pages/settings.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.settings');
$user = currentUser();

$activePage = 'settings';
$pageTitle  = 'Settings';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div class="settings-wrap">

        <!-- ── Tab Bar ── -->
        <div class="stab-bar">
          <button class="stab-btn active" onclick="settingsTab('company',this)"><i class="fas fa-building"></i> Company</button>
          <button class="stab-btn" onclick="settingsTab('invoice',this)"><i class="fas fa-file-invoice"></i> Invoice</button>
          <button class="stab-btn" onclick="settingsTab('catalog',this)"><i class="fas fa-tags"></i> Catalog</button>
          <button class="stab-btn" onclick="settingsTab('backup',this)"><i class="fas fa-database"></i> Backup</button>
        </div>

        <!-- ══ TAB: COMPANY ══ -->
        <div id="stab-company" class="stab-pane active">
          <div class="settings-block">
            <div class="sb-title"><i class="fas fa-building" style="color:var(--teal)"></i> Company Profile</div>
            <div class="form-grid g2">
              <div class="field"><label>Company Name</label><input id="sc-name" value="<?= htmlspecialchars($companyName) ?>"></div>
              <div class="field"><label>GST Number</label><input id="sc-gst" value="<?= htmlspecialchars($companyGst) ?>"></div>
              <div class="field"><label>PAN</label><input id="sc-pan" placeholder="AAAAA0000A" value="<?= htmlspecialchars($companyPan) ?>"></div>
              <div class="field"><label>IEC Number</label><input id="sc-iec" placeholder="Import Export Code" value="<?= htmlspecialchars($companyIec) ?>"></div>
              <div class="field"><label>FSSAI License</label><input id="sc-fssai" placeholder="14-digit FSSAI license no." value="<?= htmlspecialchars($companyFssai) ?>"></div>
              <div class="field"><label>APEDA RCMC</label><input id="sc-apeda" placeholder="APEDA RCMC No." value="<?= htmlspecialchars($companyApeda) ?>"></div>
              <div class="field"><label>CIN</label><input id="sc-cin" placeholder="Corporate Identification No." value="<?= htmlspecialchars($companyCin) ?>"></div>
              <div class="field"><label>MSME / Udyam No.</label><input id="sc-msme" placeholder="UDYAM-XX-00-0000000" value="<?= htmlspecialchars($companyMsme) ?>"></div>
              <div class="field"><label>Phone</label><input id="sc-phone" value="<?= htmlspecialchars($companyPhone) ?>"></div>
              <div class="field"><label>Email</label><input id="sc-email" value="<?= htmlspecialchars($companyEmail) ?>"></div>
              <div class="field"><label>Website</label><input id="sc-web" value="<?= htmlspecialchars($companyWebsite) ?>"></div>
              <div class="field"><label>UPI ID</label><input id="sc-upi" value="<?= htmlspecialchars($companyUpi) ?>"></div>
              <div class="field"><label>Default Currency</label>
                <select id="sc-cur"><option value="₹"<?= ($defaultCurrency==="₹")?" selected":"" ?>>INR (₹)</option><option value="$"<?= ($defaultCurrency==="$")?" selected":"" ?>>USD ($)</option></select>
              </div>
              <div class="field"><label>Invoice Prefix</label><input id="sc-prefix" value="<?= htmlspecialchars($prefix) ?>"></div>
              <div class="field"><label>Estimate / Quote Prefix</label><input id="sc-estimate-prefix" placeholder="QT-<?= date('Y') ?>-" value="<?= htmlspecialchars($estPrefix) ?>"></div>
              <div class="field">
                <label>Business Type <span style="font-size:10px;color:var(--muted);text-transform:none;font-weight:400">(controls wording on the catalog page)</span></label>
                <select id="sc-business-type" onchange="applyBusinessTypeLabels(this.value)">
                  <option value="service" <?= $businessType==='service'?'selected':'' ?>>Services (consulting, web dev, ERP…)</option>
                  <option value="product" <?= $businessType==='product'?'selected':'' ?>>Products (trading, import/export, retail…)</option>
                  <option value="both" <?= $businessType==='both'?'selected':'' ?>>Both / Mixed</option>
                </select>
              </div>
              <div class="field">
                <label>Dhalta % Column <span style="font-size:10px;color:var(--muted);text-transform:none;font-weight:400">(purchase items table &amp; local voucher)</span></label>
                <select id="sc-show-dhaltapct">
                  <option value="1" <?= $showDhaltaPct!=='0'?'selected':'' ?>>Show Dhalta %</option>
                  <option value="0" <?= $showDhaltaPct==='0'?'selected':'' ?>>Hide Dhalta %</option>
                </select>
              </div>
              <div class="field g-full"><label>Address</label><textarea id="sc-addr"><?= htmlspecialchars($companyAddress) ?></textarea></div>
              <div class="field g-full"><label>Default Bank Account Details <span style="font-size:10px;color:var(--muted)">(pre-fills in new invoices)</span></label>
                <textarea id="sc-bank" style="min-height:80px" placeholder="Bank: SBI | A/C: XXXXXXXXX | IFSC: SBIN0001234 | Name: Your Company | UPI: yourname@upi"><?= htmlspecialchars($companyBank) ?></textarea>
              </div>
              <div class="field">
                <label>Company Logo</label>
                <div style="display:flex;gap:6px;align-items:stretch">
                  <input id="sc-logo" placeholder="https://… or upload →" style="flex:1;min-width:0">
                  <label style="display:flex;align-items:center;gap:5px;padding:0 12px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap;transition:.2s" onmouseover="this.style.borderColor='var(--teal)';this.style.color='var(--teal)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                    <i class="fas fa-upload"></i> Upload
                    <input type="file" accept="image/*" style="display:none" onchange="handleLogoUpload(this,'sc-logo','sc-logo-preview')">
                  </label>
                </div>
                <div id="sc-logo-preview" style="margin-top:8px;min-height:0"></div>
              </div>
              <div class="field">
                <label>Authorised Signature</label>
                <div style="display:flex;gap:6px;align-items:stretch">
                  <input id="sc-sign" placeholder="https://… or upload →" style="flex:1;min-width:0">
                  <label style="display:flex;align-items:center;gap:5px;padding:0 12px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap;transition:.2s" onmouseover="this.style.borderColor='var(--teal)';this.style.color='var(--teal)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                    <i class="fas fa-pen-nib"></i> Upload Signature
                    <input type="file" accept="image/*" style="display:none" onchange="handleLogoUpload(this,'sc-sign','sc-sign-preview')">
                  </label>
                </div>
                <div id="sc-sign-preview" style="margin-top:6px;min-height:0"></div>
                <div style="font-size:10px;color:var(--muted);margin-top:4px">Transparent PNG recommended for best results in PDF</div>
              </div>
            </div>
          </div>
          <div class="stab-footer">
            <button class="btn btn-primary" onclick="saveCompanySettings()"><i class="fas fa-save"></i> Save Company Settings</button>
          </div>
        </div>

        <!-- ══ TAB: INVOICE ══ -->
        <div id="stab-invoice" class="stab-pane">
          <div class="settings-block">
            <div class="sb-title"><i class="fas fa-sliders-h" style="color:var(--teal)"></i> Invoice Defaults</div>
            <div class="form-grid g2">
              <div class="field"><label>Default GST Rate</label>
                <select id="sd-gst">
                  <option value="0">0%</option><option value="5">5%</option><option value="12">12%</option>
                  <option value="18" selected>18%</option><option value="28">28%</option>
                </select>
              </div>
              <div class="field"><label>Payment Due (days)</label>
                <input type="number" id="sd-due" value="<?= htmlspecialchars($dueDays ?: '15') ?>" min="1" max="365" oninput="STATE.settings.dueDays = parseInt(this.value) || 15;">
              </div>
              <div class="field"><label>Default Template</label>
                <select id="sd-tpl">
                  <option value="2">Colorful Matte</option>
                  <option value="A">Clean Minimal</option>
                  <option value="B">Corporate Split</option>
                  <option value="E">Dark Header</option>
                  <option value="F">Formal Letterhead</option>
                </select>
              </div>
              <div class="field"><label>Default Currency</label>
                <select id="sd-currency">
                  <option value="₹">INR (₹)</option><option value="$">USD ($)</option><option value="€">EUR (€)</option>
                </select>
              </div>
              <div class="field g-full"><label>Default Notes to Client</label>
                <textarea id="sd-notes" style="min-height:60px" placeholder="e.g. Thank you for your business. Payment due within {{due_days}} days."><?= htmlspecialchars($settings['default_notes'] ?? '') ?></textarea>
              </div>
              <div class="field g-full"><label>Default Terms &amp; Conditions</label>
                <textarea id="sd-tnc" style="min-height:80px" placeholder="Enter default terms and conditions for all invoices..."><?= htmlspecialchars($defaultTnc) ?></textarea>
              </div>
            </div>
          </div>
          <div class="stab-footer">
            <button class="btn btn-primary" onclick="saveInvoiceDefaults()"><i class="fas fa-save"></i> Save Invoice Defaults</button>
          </div>
        </div>

        <!-- ══ TAB: CATALOG ══ -->
        <div id="stab-catalog" class="stab-pane">
          <div class="settings-block">
            <div class="sb-title"><i class="fas fa-tags" style="color:var(--teal)"></i> Service / Product Categories</div>
            <p style="font-size:12px;color:var(--muted);margin-bottom:12px">Create and color-code categories to organise your services and products.</p>
            <div id="cat-list" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px"></div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <input id="cat-new-name" class="table-search" placeholder="Category name…" style="flex:1;min-width:140px;max-width:220px">
              <input type="color" id="cat-new-color" value="#00897B" style="width:36px;height:36px;border:1.5px solid var(--border);border-radius:7px;padding:2px;cursor:pointer;background:var(--card)">
              <button class="btn btn-primary" style="padding:6px 14px;font-size:13px" onclick="addCategory()"><i class="fas fa-plus"></i> Add</button>
            </div>
          </div>
          <div class="settings-block">
            <div class="sb-title"><i class="fas fa-receipt" style="color:var(--teal)"></i> Expense Categories</div>
            <p style="font-size:12px;color:var(--muted);margin-bottom:12px">Create and color-code categories used when logging expenses.</p>
            <div id="exp-cat-list" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px"></div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <input id="exp-cat-new-name" class="table-search" placeholder="Category name…" style="flex:1;min-width:140px;max-width:220px">
              <input type="color" id="exp-cat-new-color" value="#1976D2" style="width:36px;height:36px;border:1.5px solid var(--border);border-radius:7px;padding:2px;cursor:pointer;background:var(--card)">
              <button class="btn btn-primary" style="padding:6px 14px;font-size:13px" onclick="addExpenseCategory()"><i class="fas fa-plus"></i> Add</button>
            </div>
          </div>
          <div class="settings-block">
            <div class="sb-title"><i class="fas fa-layer-group" style="color:var(--teal)"></i> Line Item Types</div>
            <p style="font-size:12px;color:var(--muted);margin-bottom:12px">Manage item types shown in the invoice line-item "Type" dropdown.</p>
            <div id="item-type-list" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px"></div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <input id="itype-new-name" class="table-search" placeholder="Type name e.g. Subscription…" style="flex:1;min-width:160px;max-width:240px">
              <input type="color" id="itype-new-color" value="#1976D2" style="width:36px;height:36px;border:1.5px solid var(--border);border-radius:7px;padding:2px;cursor:pointer;background:var(--card)">
              <button class="btn btn-primary" style="padding:6px 14px;font-size:13px" onclick="addItemType()"><i class="fas fa-plus"></i> Add Type</button>
            </div>
            <p style="font-size:11px;color:var(--muted);margin-top:10px"><i class="fas fa-info-circle"></i> Default types (Service, Product, Labour, Other) are always available even if deleted.</p>
          </div>
        </div>

        <!-- ══ TAB: BACKUP ══ -->
        <div id="stab-backup" class="stab-pane">
          <div class="settings-block">
            <div class="sb-title"><i class="fas fa-database" style="color:var(--teal)"></i> Backup &amp; Export</div>
            <div class="backup-actions">
              <button class="backup-btn" onclick="exportAllJSON()"><i class="fas fa-file-code"></i><span>Export All Data (JSON)</span></button>
              <button class="backup-btn" onclick="exportCSV()"><i class="fas fa-file-csv"></i><span>Export Invoices (CSV)</span></button>
              <button class="backup-btn" onclick="importData()"><i class="fas fa-file-upload"></i><span>Import Data (JSON)</span></button>
              <button class="backup-btn" onclick="clearAllData()"><i class="fas fa-trash"></i><span>Clear All Data</span></button>
            </div>
            <div class="field" style="margin-top:16px"><label>Last Backup</label><input value="Never" readonly style="background:#f5f5f5"></div>
          </div>
        </div>

      </div>
    </div>

<!-- Backup moved into Settings → Backup tab -->

    <!-- ─────────── MESSAGE LOG (Updated: IST timezone, proper ordering, optimistic updates) ─────────── -->
    <div id="page-msglog" class="page">
    <style>
      /* ── WhatsApp Log Table Styling ── */
      .wa-log-table { width: 100%; border-collapse: collapse; font-size: 13px; }
      .wa-log-table thead tr { background: var(--bg); border-bottom: 2px solid var(--border); }
      .wa-log-table th {
        padding: 10px 14px; text-align: left; font-weight: 700;
        color: var(--muted); font-size: 11px; text-transform: uppercase;
        letter-spacing: .5px;
      }
      .wa-log-table td { padding: 10px 14px; border-bottom: 1px solid var(--border); color: var(--text); }
      .wa-log-table tbody tr:hover { background: rgba(0,137,123,.02); }

      /* ── Time column (monospace) ── */
      .wa-log-ts {
        font-family: var(--mono); font-size: 12px; color: var(--muted);
        white-space: nowrap;
      }

      /* ── Message column ── */
      .wa-log-msg {
        font-size: 12px; color: var(--text2);
        max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
      }

      /* ── Status badges ── */
      .wa-badge {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600;
      }
      .wa-badge-sending {
        background: var(--amber-bg); color: var(--amber);
      }
      .wa-badge-sent_web {
        background: var(--blue-bg); color: var(--blue);
      }
      .wa-badge-sent_api {
        background: var(--green-bg); color: var(--green);
      }
      .wa-badge-failed {
        background: var(--red-bg); color: var(--red);
      }

      /* ── Stats cards ── */
      .wa-stat-card {
        background: var(--card); border: 1px solid var(--border);
        border-radius: 8px; padding: 12px 16px;
        display: flex; align-items: center; gap: 10px; min-width: 160px;
      }
      .wa-stat-icon {
        font-size: 20px; width: 40px; height: 40px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 8px;
      }
      .wa-stat-content { flex: 1; }
      .wa-stat-label { font-size: 11px; color: var(--muted); font-weight: 600; text-transform: uppercase; }
      .wa-stat-value { font-size: 18px; font-weight: 700; color: var(--text); margin-top: 2px; }
    </style>

      <div class="page-toolbar">
        <div class="toolbar-left">
          <input
            id="msglog-search"
            type="text"
            placeholder="Search by client, phone, invoice…"
            style="padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;width:260px;background:var(--card);color:var(--text)"
            oninput="renderWALog()"
          >
          <select
            id="msglog-filter-type"
            style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:var(--card);color:var(--text)"
            onchange="renderWALog()"
          >
            <option value="">📋 All Types</option>
            <option value="invoice_created">📄 New Invoice</option>
            <option value="estimate_created">📋 Estimate</option>
            <option value="payment_received">✅ Payment Receipt</option>
            <option value="partial_payment">💛 Partial Payment</option>
            <option value="split_payment">⚡ Split Payment</option>
            <option value="payment_overdue">🔴 Overdue Alert</option>
            <option value="payment_reminder">🔔 Due Reminder</option>
            <option value="invoice_followup">📞 Follow-up</option>
          </select>
          <select
            id="msglog-filter-status"
            style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:var(--card);color:var(--text)"
            onchange="renderWALog()"
          >
            <option value="">📊 All Status</option>
            <option value="sending">⏳ Sending</option>
            <option value="sent_api">✅ Sent (API)</option>
            <option value="sent_web">📱 Opened (Manual)</option>
            <option value="failed">❌ Failed</option>
          </select>
        </div>
        <div class="toolbar-right">
          <span id="wa-log-last-refresh" style="font-size:11px;color:var(--muted);align-self:center;white-space:nowrap"></span>
          <button class="btn btn-outline" id="wa-log-refresh-btn" onclick="renderWALog(true)" title="Refresh log"><i class="fas fa-sync-alt"></i> Refresh</button>
          <button class="btn btn-outline" onclick="exportMsgLog()"><i class="fas fa-download"></i> Export</button>
          <button class="btn btn-outline" style="color:#E53935;border-color:#E53935" onclick="WA_LOG.clearLogs()"><i class="fas fa-trash"></i> Clear Log</button>
        </div>
      </div>

      <!-- Statistics Row -->
      <div style="display:flex;gap:12px;flex-wrap:wrap;padding:0 0 16px;margin-bottom:8px" id="wa-log-stats">
        <div class="wa-stat-card">
          <div class="wa-stat-icon" style="background:var(--blue-bg);color:var(--blue)"><i class="fas fa-envelope"></i></div>
          <div class="wa-stat-content">
            <div class="wa-stat-label">Total Messages</div>
            <div class="wa-stat-value" id="wa-stat-total">0</div>
          </div>
        </div>
        <div class="wa-stat-card">
          <div class="wa-stat-icon" style="background:var(--green-bg);color:var(--green)"><i class="fas fa-check-circle"></i></div>
          <div class="wa-stat-content">
            <div class="wa-stat-label">Sent (API)</div>
            <div class="wa-stat-value" id="wa-stat-sent">0</div>
          </div>
        </div>
        <div class="wa-stat-card">
          <div class="wa-stat-icon" style="background:var(--blue-bg);color:var(--blue)"><i class="fas fa-mobile-alt"></i></div>
          <div class="wa-stat-content">
            <div class="wa-stat-label">Manual</div>
            <div class="wa-stat-value" id="wa-stat-manual">0</div>
          </div>
        </div>
        <div class="wa-stat-card">
          <div class="wa-stat-icon" style="background:var(--amber-bg);color:var(--amber)"><i class="fas fa-hourglass-half"></i></div>
          <div class="wa-stat-content">
            <div class="wa-stat-label">Sending</div>
            <div class="wa-stat-value" id="wa-stat-sending">0</div>
          </div>
        </div>
        <div class="wa-stat-card">
          <div class="wa-stat-icon" style="background:var(--red-bg);color:var(--red)"><i class="fas fa-times-circle"></i></div>
          <div class="wa-stat-content">
            <div class="wa-stat-label">Failed</div>
            <div class="wa-stat-value" id="wa-stat-failed">0</div>
          </div>
        </div>
      </div>

      <!-- Log table -->
      <div style="background:var(--card);border-radius:12px;border:1px solid var(--border);overflow:hidden">
        <table id="wa-log-table" class="wa-log-table">
          <thead>
            <tr>
              <th style="width:130px">Time</th>
              <th style="width:120px">Type</th>
              <th style="width:150px">Client</th>
              <th style="width:120px">Invoice</th>
              <th style="width:160px">Message</th>
              <th style="width:100px">Status</th>
              <th style="width:90px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="7" style="padding:40px;text-align:center;color:var(--muted)">
                <i class="fas fa-whatsapp" style="font-size:40px;color:#25D366;opacity:0.3;display:block;margin-bottom:8px"></i>
                <div>No messages logged yet</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    <!-- /page-msglog -->

    <!--


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/pages/shared-data.js"></script>
<script src="/assets/js/pages/wa-shared.js"></script>
<script src="/assets/js/pages/settings.js"></script>
