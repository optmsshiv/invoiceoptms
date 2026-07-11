<?php
// ================================================================
//  pages/settings.php
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.settings');

$user = currentUser();

$activePage  = 'settings';
$pageTitle   = 'Settings';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/settings.js'];

include __DIR__ . '/../../includes/layout_header.php';
?>
      <div class="settings-wrap">
        <div class="stab-bar">
          <button class="stab-btn active" onclick="settingsTab('company',this)"><i class="fas fa-building"></i> Company</button>
          <button class="stab-btn" onclick="settingsTab('invoice',this)"><i class="fas fa-file-invoice"></i> Invoice</button>
          <button class="stab-btn" onclick="settingsTab('catalog',this)"><i class="fas fa-tags"></i> Catalog</button>
          <button class="stab-btn" onclick="settingsTab('backup',this)"><i class="fas fa-database"></i> Backup</button>
        </div>

        <!-- TAB: COMPANY -->
        <div id="stab-company" class="stab-pane active">
          <div class="settings-block">
            <div class="sb-title"><i class="fas fa-building" style="color:var(--teal)"></i> Company Profile</div>
            <div class="form-grid g2">
              <div class="field"><label>Company Name</label><input id="sc-name"></div>
              <div class="field"><label>GST Number</label><input id="sc-gst"></div>
              <div class="field"><label>Phone</label><input id="sc-phone"></div>
              <div class="field"><label>Email</label><input id="sc-email"></div>
              <div class="field"><label>Website</label><input id="sc-web"></div>
              <div class="field"><label>UPI ID</label><input id="sc-upi"></div>
              <div class="field"><label>Default Currency</label>
                <select id="sc-cur"><option value="₹">INR (₹)</option><option value="$">USD ($)</option></select>
              </div>
              <div class="field"><label>Invoice Prefix</label><input id="sc-prefix"></div>
              <div class="field"><label>Estimate / Quote Prefix</label><input id="sc-estimate-prefix" placeholder="QT-<?= date('Y') ?>-"></div>
              <div class="field">
                <label>Business Type <span style="font-size:10px;color:var(--muted);text-transform:none;font-weight:400">(controls wording on the catalog page, and whether Sales/Products show in the sidebar)</span></label>
                <select id="sc-business-type">
                  <option value="service">Services (consulting, web dev, ERP…)</option>
                  <option value="product">Products (trading, import/export, retail…)</option>
                  <option value="both">Both / Mixed</option>
                </select>
              </div>
              <div class="field g-full"><label>Address</label><textarea id="sc-addr"></textarea></div>
              <div class="field g-full"><label>Default Bank Account Details <span style="font-size:10px;color:var(--muted)">(pre-fills in new invoices)</span></label>
                <textarea id="sc-bank" style="min-height:80px" placeholder="Bank: SBI | A/C: XXXXXXXXX | IFSC: SBIN0001234 | Name: Your Company | UPI: yourname@upi"></textarea>
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

        <!-- TAB: INVOICE -->
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
                <input type="number" id="sd-due" value="15" min="1" max="365">
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
                <textarea id="sd-notes" style="min-height:60px" placeholder="e.g. Thank you for your business. Payment due within {{due_days}} days."></textarea>
              </div>
              <div class="field g-full"><label>Default Terms &amp; Conditions</label>
                <textarea id="sd-tnc" style="min-height:80px" placeholder="Enter default terms and conditions for all invoices..."></textarea>
              </div>
            </div>
          </div>
          <div class="stab-footer">
            <button class="btn btn-primary" onclick="saveInvoiceDefaults()"><i class="fas fa-save"></i> Save Invoice Defaults</button>
          </div>
        </div>

        <!-- TAB: CATALOG -->
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

        <!-- TAB: BACKUP -->
        <div id="stab-backup" class="stab-pane">
          <div class="settings-block">
            <div class="sb-title"><i class="fas fa-database" style="color:var(--teal)"></i> Backup &amp; Export</div>
            <div class="backup-actions">
              <button class="backup-btn" onclick="exportAllJSON()"><i class="fas fa-file-code"></i><span>Export All Data (JSON)</span></button>
              <button class="backup-btn" onclick="settingsExportCSV()"><i class="fas fa-file-csv"></i><span>Export Invoices (CSV)</span></button>
              <button class="backup-btn" onclick="importData()"><i class="fas fa-file-upload"></i><span>Import Data (JSON)</span></button>
              <button class="backup-btn" onclick="clearAllData()"><i class="fas fa-trash"></i><span>Clear All Data</span></button>
            </div>
            <div class="field" style="margin-top:16px"><label>Last Backup</label><input value="Never" readonly style="background:#f5f5f5"></div>
          </div>
        </div>
      </div>
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
