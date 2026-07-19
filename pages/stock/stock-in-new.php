<?php
// ================================================================
//  pages/stock/stock-in-new.php
//  Manual multi-product stock inward (not tied to a Purchase).
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.stock');

$user = currentUser();

$activePage  = 'stock';
$pageTitle   = 'Add Stock';
$pageScripts = [
    '/assets/js/shared-data.js',
    '/assets/js/stock-in-new.js',
];

include __DIR__ . '/../../includes/layout_header.php';
?>
    <div id="page-stock-in-new" class="page">
      <div style="padding:14px 24px 0"><span style="font-size:12px;color:var(--muted)">Dashboard &gt; Inventory &gt; Product Stock &gt; <strong style="color:var(--text)">Add Stock</strong></span></div>
      <div class="pne-topbar">
        <div><div class="pne-title" id="sti-page-title">Add Product to Stock (Stock In)</div></div>
        <div class="pne-actions">
          <button class="btn btn-outline" onclick="cancelStockIn()">Cancel</button>
          <button class="btn pne-btn-savenew" onclick="saveStockInEntry('new')"><i class="fas fa-plus"></i> Save &amp; New</button>
          <button class="btn pne-btn-save" onclick="saveStockInEntry('close')"><i class="fas fa-plus"></i> Save &amp; Close</button>
        </div>
      </div>

      <div class="pne-layout">
        <div class="pne-main">

          <!-- 1. Basic Information -->
          <div class="pne-card">
            <div class="pne-card-head"><span class="pne-num"><i class="fas fa-clipboard-list"></i></span> Basic Information</div>
            <div class="pne-grid5">
              <div class="field"><label>Reference No.</label><input id="sti-refno" placeholder="Auto-generated"></div>
              <div class="field"><label>Reference Date *</label><input type="date" id="sti-refdate"></div>
              <div class="field"><label>Warehouse *</label><select id="sti-warehouse"><option>Main Warehouse</option><option>Secondary Warehouse</option></select></div>
              <div class="field"><label>Stock In Type *</label>
                <select id="sti-type"><option>Purchase</option><option>Transfer</option><option>Return</option><option>Adjustment</option><option>Other</option></select>
              </div>
              <div class="field"><label>Remarks</label><input id="sti-remarks" placeholder="Optional"></div>
            </div>
          </div>

          <!-- 2. Product Details -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-purple"><span class="pne-num"><i class="fas fa-boxes-stacked"></i></span> Product Details</div>
            <div class="pne-grid5" style="margin-bottom:6px">
              <div class="field"><label>Product *</label><select id="sti-p-product"><option value="">Select product…</option></select></div>
              <div class="field"><label>Variety</label><select id="sti-p-variety" onchange="onSTIVarietyChange()"><option value="">—</option><option>Premium</option><option>SBD</option><option>BD</option><option>CD</option><option>RBD</option></select></div>
              <div class="field"><label>Grade</label><select id="sti-p-grade" onchange="onSTIGradeChange()"><option value="">—</option><option>Grade-1</option><option>Grade-2</option><option>Grade-3</option><option>Grade-4</option><option>Grade-5</option></select></div>
              <div class="field"><label>Category</label><input id="sti-p-category" readonly></div>
              <div class="field"><label>Unit</label><input id="sti-p-unit" readonly></div>
            </div>
            <div class="pne-grid4">
              <div class="field" style="display:flex;flex-direction:row;gap:6px;align-items:flex-end">
                <div style="flex:1"><label style="display:block;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;margin-bottom:5px">Batch / Lot No.</label><input id="sti-p-batchno" placeholder="Optional"></div>
              </div>
              <div class="field"><label>Mfg. Date</label><input type="date" id="sti-p-mfgdate"></div>
              <div class="field"><label>Expiry Date</label><input type="date" id="sti-p-expdate"></div>
              <div class="field" style="display:flex;gap:8px;align-items:flex-end">
                <div style="flex:1"><label style="display:block;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;margin-bottom:5px">Qty (Kg) / Rate (₹)</label>
                  <div style="display:flex;gap:6px"><input type="number" id="sti-p-qty" placeholder="Qty" min="0" step="0.01"><input type="number" id="sti-p-rate" placeholder="Rate" min="0" step="0.01"></div>
                </div>
              </div>
            </div>
            <button class="btn btn-outline pne-small-btn" style="margin-top:10px" onclick="addSTIProduct()"><i class="fas fa-plus"></i> Add Product</button>

            <div class="table-card pit-card" style="overflow-x:auto;margin-top:14px">
              <table class="data-table pne-items-table">
                <colgroup><col style="width:30px"><col style="width:135px"><col style="width:85px"><col style="width:80px"><col style="width:100px"><col style="width:95px"><col style="width:95px"><col style="width:85px"><col style="width:85px"><col style="width:95px"><col style="width:56px"></colgroup>
                <thead><tr><th>#</th><th>Product</th><th>Variety</th><th>Grade</th><th>Batch / Lot No.</th><th>Mfg. Date</th><th>Expiry Date</th><th>Quantity (Kg)</th><th>Rate (₹/Kg)</th><th>Amount (₹)</th><th>Action</th></tr></thead>
                <tbody id="sti-items-tbody"></tbody>
              </table>
            </div>
            <div class="pne-items-footer">
              <span>Total Quantity <strong id="sti-total-qty">0.00 Kg</strong></span>
              <span style="margin-left:auto">Total Amount <strong id="sti-total-amount" class="pne-total-amt">₹0.00</strong></span>
            </div>
          </div>

          <!-- 3. Weight / Measurement Details -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-blue"><span class="pne-num"><i class="fas fa-weight-hanging"></i></span> Weight / Measurement Details</div>
            <div class="pne-grid4">
              <div class="field"><label>Weighing Type *</label>
                <select id="sti-weighingtype"><option>Own Weighbridge</option><option>Dharam Kanta</option><option>Digital Kanta</option><option>Platform Scale</option><option>Self Declared</option></select>
              </div>
              <div class="field"><label>Weighbridge Name *</label><input id="sti-weighbridgename" placeholder="e.g. AgriTrade Weighbridge - 1"></div>
              <div class="field"><label>Weighbridge Slip No.</label><input id="sti-slipno" placeholder="Optional"></div>
              <div class="field"><label>Weight Date &amp; Time *</label><input type="datetime-local" id="sti-weightdatetime"></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Gross Weight (Kg) *</label><input type="number" id="sti-gross" min="0" step="0.01" oninput="calcSTIWeight()"></div>
              <div class="field"><label>Tare Weight (Kg) *</label><input type="number" id="sti-tare" min="0" step="0.01" oninput="calcSTIWeight()"></div>
              <div class="field"><label>Net Weight (Kg)</label><input id="sti-net" readonly style="background:#E8F5E9;color:#00897B;font-weight:700"></div>
              <div class="field"><label>Operator Name</label><input id="sti-operator" placeholder="Optional"></div>
            </div>
            <div class="pne-note" style="background:var(--blue-bg);color:var(--blue);border-radius:7px;padding:8px 12px;font-style:normal">
              <i class="fas fa-info-circle"></i> Net Weight (Kg) = Gross Weight (Kg) − Tare Weight (Kg)
            </div>
            <div id="sti-reconcile-banner" style="display:none;border-radius:7px;padding:9px 12px;margin-top:8px;font-size:12.5px;font-weight:600"></div>
            <div class="field" style="margin-top:12px;max-width:340px">
              <label>Upload Slip</label>
              <label class="pp-dropzone" for="sti-slip-input" style="padding:14px;flex-direction:row;justify-content:flex-start;gap:12px" id="sti-slip-label">
                <i class="fas fa-cloud-upload-alt"></i>
                <div style="text-align:left">Drag &amp; drop or click to upload<br><span style="font-size:10px">Supported: JPG, PNG, PDF (Max 5MB)</span></div>
              </label>
              <input type="file" id="sti-slip-input" accept="application/pdf,image/png,image/jpeg" style="display:none" onchange="stiSlipChange(this.files[0])">
            </div>
          </div>

          <!-- 4. Additional Information -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-amber"><span class="pne-num"><i class="fas fa-circle-info"></i></span> Additional Information</div>
            <div class="pne-grid5">
              <div class="field"><label>Supplier</label><select id="sti-supplier"><option value="">Select or —</option></select></div>
              <div class="field"><label>Challan No.</label><input id="sti-challanno" placeholder="Optional"></div>
              <div class="field"><label>Challan Date</label><input type="date" id="sti-challandate"></div>
              <div class="field"><label>Vehicle No.</label><input id="sti-vehicleno" placeholder="Optional"></div>
              <div class="field"><label>Driver Name</label><input id="sti-drivername" placeholder="Optional"></div>
            </div>
          </div>

          <!-- 5. Attachments -->
          <div class="pne-card">
            <div class="pne-card-head"><span class="pne-num"><i class="fas fa-paperclip"></i></span> Attachments (Optional)</div>
            <label class="pp-dropzone" for="sti-attachments-input">
              <i class="fas fa-cloud-upload-alt"></i>
              <div>Drag &amp; drop files here or click to upload</div>
            </label>
            <input type="file" id="sti-attachments-input" accept="application/pdf,image/png,image/jpeg" multiple style="display:none" onchange="stiAddAttachments(this.files)">
            <div style="font-size:10px;color:var(--muted);margin-top:6px">Supported: PDF, JPG, PNG (Max 5MB)</div>
            <div id="sti-attachments-list" style="margin-top:10px;display:flex;flex-direction:column;gap:6px"></div>
          </div>
        </div>

        <!-- Right sidebar -->
        <div class="pne-sidebar">
          <div class="pne-card">
            <div class="pne-card-head pne-head-green"><i class="fas fa-boxes-packing"></i> Stock Summary <span style="font-weight:400;font-size:11px;color:var(--muted)">(After This Inward)</span></div>
            <div class="pne-kv"><span>Product</span><strong id="sti-sum-product">—</strong></div>
            <div class="pne-kv"><span>Batch / Lot No.</span><strong id="sti-sum-batch">—</strong></div>
            <div class="pne-kv"><span>Warehouse</span><strong id="sti-sum-warehouse">Main Warehouse</strong></div>
            <div class="pne-kv"><span>Before Stock</span><strong id="sti-sum-before">0.00 Kg</strong></div>
            <div class="pne-kv"><span>Inward Quantity</span><strong id="sti-sum-inward" style="color:#00897B">0.00 Kg</strong></div>
            <div class="pne-kv" style="border-top:1px dashed var(--border);margin-top:6px;padding-top:8px"><span>After Stock</span><strong id="sti-sum-after" style="color:var(--teal)">0.00 Kg</strong></div>
          </div>
          <div class="pne-card">
            <div class="pne-card-head" style="justify-content:space-between">
              <span><i class="fas fa-history"></i> Recent Stock In History</span>
              <a href="#" style="font-size:11px;color:var(--teal)" id="sti-viewall-link" onclick="event.preventDefault(); expandSTIHistory();">View All</a>
            </div>
            <div id="sti-recent-list" style="display:flex;flex-direction:column;gap:12px"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════ FINANCE REPORT ═══════════ -->
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
