<?php
// ================================================================
//  pages/recurring.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.recurring');
$user = currentUser();

$activePage = 'recurring';
$pageTitle  = 'Recurring';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div class="page-toolbar">
        <div class="toolbar-left">
          <span style="font-weight:700;font-size:16px;color:var(--text)"><i class="fas fa-sync-alt" style="color:var(--teal);margin-right:8px"></i>Recurring Invoices</span>
        </div>
        <div class="toolbar-right">
          <button class="btn btn-primary" onclick="openRecurringModal()"><i class="fas fa-plus"></i> New Schedule</button>
        </div>
      </div>
      <!-- Stats row -->
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px">
        <div class="stat-card"><div class="stat-icon" style="background:var(--teal-bg);color:var(--teal)"><i class="fas fa-sync-alt"></i></div><div class="stat-body"><div class="stat-val" id="rec-stat-active">0</div><div class="stat-lbl">Active Schedules</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:var(--amber-bg);color:var(--amber)"><i class="fas fa-clock"></i></div><div class="stat-body"><div class="stat-val" id="rec-stat-due">0</div><div class="stat-lbl">Due Today</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bg);color:var(--blue)"><i class="fas fa-file-invoice"></i></div><div class="stat-body"><div class="stat-val" id="rec-stat-generated">0</div><div class="stat-lbl">Total Generated</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:var(--purple-bg);color:var(--purple)"><i class="fas fa-calendar-check"></i></div><div class="stat-body"><div class="stat-val" id="rec-stat-paused">0</div><div class="stat-lbl">Paused</div></div></div>
      </div>
      <!-- Schedule table -->
      <div class="table-card">
        <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
          <span style="font-weight:700;font-size:14px">Schedules</span>
          <button class="btn btn-outline" style="font-size:12px" onclick="runRecurringCheck()"><i class="fas fa-play"></i> Run Now</button>
        </div>
        <table class="data-table"><thead><tr>
          <th>Client</th><th>Service</th><th>Amount</th><th>Frequency</th><th>Next Due</th><th>Last Generated</th><th>Status</th><th>Generated</th><th>Actions</th>
        </tr></thead><tbody id="rec-table-body"></tbody></table>
        <div id="rec-empty" style="padding:40px;text-align:center;color:var(--muted);display:none">
          <i class="fas fa-sync-alt" style="font-size:32px;margin-bottom:10px;opacity:.3"></i>
          <div style="font-weight:600;margin-bottom:6px">No recurring schedules yet</div>
          <div style="font-size:13px">Create a schedule to auto-generate invoices on a set frequency</div>
        </div>
      </div>
    </div>

    <!-- ─────────── RECURRING MODAL (2-step redesign) ─────────── -->
    <div id="modal-recurring" class="modal-overlay" onclick="if(event.target===this)closeModal('modal-recurring')">
      <div class="modal-box" style="width:580px;max-width:96vw;border-radius:14px;overflow:hidden">
        <input type="hidden" id="rec-edit-id" value="">

        <!-- ── Header ── -->
        <div style="padding:18px 22px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--card)">
          <div>
            <div style="font-size:16px;font-weight:700;color:var(--text)" id="rec-modal-title">New Recurring Schedule</div>
            <div style="display:flex;gap:6px;margin-top:8px">
              <div id="rec-step-dot-1" style="height:4px;width:32px;border-radius:2px;background:var(--teal);transition:background .2s"></div>
              <div id="rec-step-dot-2" style="height:4px;width:32px;border-radius:2px;background:var(--border);transition:background .2s"></div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:10px">
            <span id="rec-step-label" style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px">Step 1 of 2 — Schedule</span>
            <button class="modal-close" onclick="closeModal('modal-recurring')" style="margin:0">×</button>
          </div>
        </div>

        <!-- ══ STEP 1: Who & When ══ -->
        <div id="rec-step-1" style="padding:20px 22px;display:flex;flex-direction:column;gap:16px;max-height:72vh;overflow-y:auto">

          <!-- Client + Frequency -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="field">
              <label>Client <span style="color:var(--red)">*</span></label>
              <select id="rec-client" style="width:100%" onchange="recClientChange()">
                <option value="">— Select Client —</option>
              </select>
            </div>
            <div class="field">
              <label>Frequency <span style="color:var(--red)">*</span></label>
              <select id="rec-freq" style="width:100%" onchange="recFreqChange()">
                <option value="weekly">📅 Weekly</option>
                <option value="biweekly">📅 Bi-Weekly</option>
                <option value="monthly" selected>📅 Monthly</option>
                <option value="quarterly">📅 Quarterly</option>
                <option value="halfyearly">📅 Half-Yearly</option>
                <option value="yearly">📅 Yearly</option>
              </select>
            </div>
          </div>

          <!-- Copy from invoice row -->
          <div id="rec-copy-row" style="display:none">
            <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--teal-bg);border:1.5px solid var(--teal-l);border-radius:9px">
              <i class="fas fa-magic" style="color:var(--teal);font-size:13px"></i>
              <span style="font-size:12px;color:var(--teal);font-weight:600;flex:1">Items auto-filled from latest invoice</span>
              <select id="rec-copy-select" style="font-size:12px;padding:5px 8px;border:1px solid var(--teal-l);border-radius:6px;background:var(--card);color:var(--text);max-width:210px" onchange="recCopyFromInvoice(this.value)">
              </select>
            </div>
          </div>

          <!-- Dates -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="field">
              <label>Start Date <span style="color:var(--red)">*</span></label>
              <input type="date" id="rec-start" style="width:100%" oninput="recFreqChange()">
            </div>
            <div class="field">
              <label>End Date <span style="font-size:11px;color:var(--muted)">(optional — leave blank = forever)</span></label>
              <input type="date" id="rec-end" style="width:100%" oninput="recFreqChange()">
            </div>
          </div>

          <!-- Due Days + Template -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="field">
              <label>Payment Due After <span style="font-size:11px;color:var(--muted)">(days)</span></label>
              <input type="number" id="rec-due-days" value="15" min="1" max="90" style="width:100%" oninput="recFreqChange()">
            </div>
            <div class="field">
              <label>Invoice Template</label>
              <select id="rec-template" style="width:100%">
                <option value="2">Colorful Matte</option>
                <option value="A">Clean Minimal</option>
                <option value="B">Corporate Split</option>
                <option value="E">Dark Header</option>
                  <option value="F">Formal Letterhead</option>
              </select>
            </div>
          </div>

          <!-- Preview info card -->
          <div style="border-radius:10px;border:1px solid var(--border);background:var(--bg);overflow:hidden">
            <div style="padding:10px 14px;background:var(--teal-bg);border-bottom:1px solid var(--teal-l);display:flex;align-items:center;gap:8px">
              <i class="fas fa-calendar-check" style="color:var(--teal);font-size:13px"></i>
              <span style="font-size:12px;font-weight:700;color:var(--teal)">Schedule Preview</span>
            </div>
            <div style="padding:12px 14px;display:grid;grid-template-columns:1fr 1fr;gap:8px">
              <div>
                <div style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px">First Invoice</div>
                <div style="font-size:13px;font-weight:700;color:var(--text)" id="rec-prev-first">—</div>
              </div>
              <div>
                <div style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px">Next After That</div>
                <div style="font-size:13px;font-weight:700;color:var(--text)" id="rec-prev-next">—</div>
              </div>
              <div>
                <div style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px">Due Date</div>
                <div style="font-size:13px;font-weight:700;color:var(--text)" id="rec-prev-due">—</div>
              </div>
              <div>
                <div style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px">Total Invoices</div>
                <div style="font-size:13px;font-weight:700;color:var(--teal)" id="rec-prev-count">—</div>
              </div>
            </div>
          </div>

        </div>

        <!-- ══ STEP 2: What to Bill ══ -->
        <div id="rec-step-2" style="display:none;padding:20px 22px;display:none;flex-direction:column;gap:16px;max-height:72vh;overflow-y:auto">

          <!-- Line items table -->
          <div class="field">
            <label>Line Items <span style="color:var(--red)">*</span></label>
            <div style="border:1.5px solid var(--border);border-radius:9px;overflow:hidden">
              <div style="display:grid;grid-template-columns:1fr 65px 110px 75px 32px;background:var(--bg);font-size:10.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px">
                <span style="padding:8px 10px">Description</span>
                <span style="padding:8px 6px;text-align:center">Qty</span>
                <span style="padding:8px 6px;text-align:right">Rate (₹)</span>
                <span style="padding:8px 6px;text-align:center">GST %</span>
                <span></span>
              </div>
              <div id="rec-items-list"></div>
            </div>
            <button type="button" onclick="recAddItem()" style="margin-top:8px;padding:6px 14px;border:1.5px dashed var(--teal);border-radius:7px;background:transparent;color:var(--teal);font-size:12px;font-weight:600;cursor:pointer">
              <i class="fas fa-plus"></i> Add Item
            </button>
          </div>

          <!-- Discount + Notes -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="field">
              <label>Discount</label>
              <div style="display:flex;gap:6px">
                <select id="rec-disc-type" style="width:88px;flex-shrink:0" onchange="recCalcTotals()">
                  <option value="pct">%</option>
                  <option value="fixed">₹ Fixed</option>
                </select>
                <input type="number" id="rec-disc" value="0" min="0" step="0.01" style="flex:1" oninput="recCalcTotals()">
              </div>
            </div>
            <div class="field">
              <label>Notes <span style="font-size:11px;color:var(--muted)">(optional)</span></label>
              <input type="text" id="rec-notes" placeholder="e.g. Monthly retainer" style="width:100%">
            </div>
          </div>

          <!-- Totals card -->
          <div style="border-radius:10px;border:1.5px solid var(--border);overflow:hidden">
            <div style="padding:10px 16px;background:var(--bg);border-bottom:1px solid var(--border);font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px">
              Invoice Summary
            </div>
            <div style="padding:12px 16px;display:flex;flex-direction:column;gap:6px">
              <div style="display:flex;justify-content:space-between;font-size:13px">
                <span style="color:var(--muted)">Subtotal</span><span id="rec-tot-sub">₹0.00</span>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:13px">
                <span style="color:var(--muted)">Discount</span><span id="rec-tot-disc" style="color:var(--red)">-₹0.00</span>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:13px">
                <span style="color:var(--muted)">GST</span><span id="rec-tot-gst">₹0.00</span>
              </div>
              <div style="height:1px;background:var(--border);margin:4px 0"></div>
              <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:800">
                <span>Per Invoice</span>
                <span style="color:var(--teal)" id="rec-tot-grand">₹0.00</span>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted)">
                <span id="rec-tot-count-label">× — invoices</span>
                <span style="font-weight:700;color:var(--text)" id="rec-tot-overall">—</span>
              </div>
            </div>
          </div>

        </div>

        <!-- ── Footer ── -->
        <div style="padding:14px 22px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:space-between;background:var(--card)">
          <button class="btn btn-outline" id="rec-btn-back" onclick="recGoStep(1)" style="display:none">← Back</button>
          <button class="btn btn-outline" id="rec-btn-cancel" onclick="closeModal('modal-recurring')">Cancel</button>
          <div style="display:flex;gap:10px">
            <button class="btn btn-primary" id="rec-btn-next" onclick="recGoStep(2)">Next → Billing</button>
            <button class="btn btn-primary" id="rec-btn-save" onclick="saveRecurring()" style="display:none"><i class="fas fa-save"></i> Save Schedule</button>
          </div>
        </div>
      </div>
    

    <!--


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/shared-data.js"></script>
<script src="/assets/js/wa-shared.js"></script>
<script src="/assets/js/pages/recurring.js"></script>
