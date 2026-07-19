<?php
// ================================================================
//  pages/stock.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.stock');
$user = currentUser();

$activePage = 'stock';
$pageTitle  = 'Stock Adjustment';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div style="padding:14px 24px 0"><span style="font-size:12px;color:var(--muted)">Dashboard &gt; Inventory &gt; Stock Adjustment &gt; <strong style="color:var(--text)">Add New</strong></span></div>
      <div class="pne-topbar">
        <div>
          <div class="pne-title">Stock Adjustment / Moisture Adjustment</div>
        </div>
        <div class="pne-actions">
          <a class="btn btn-outline" href="/pages/stock.php">Cancel</a>
          <button class="btn pne-btn-save" onclick="saveStockAdjustmentEntry()"><i class="fas fa-check"></i> Save</button>
        </div>
      </div>

      <div class="pne-layout">
        <div class="pne-main">

          <!-- 1. Adjustment Details -->
          <div class="pne-card">
            <div class="pne-card-head"><span class="pne-num"><i class="fas fa-sliders-h"></i></span> Adjustment Details</div>
            <div class="pne-grid4">
              <div class="field"><label>Adjustment No.</label><input id="sa-no" placeholder="Auto-generated"></div>
              <div class="field"><label>Adjustment Date *</label><input type="date" id="sa-date"></div>
              <div class="field"><label>Direction *</label>
                <select id="sa-direction" onchange="onSADirectionChange()">
                  <option value="out">Decrease Stock (Loss)</option>
                  <option value="in">Increase Stock (Gain)</option>
                  <option value="adjust">Set Exact Stock (Correction)</option>
                </select>
              </div>
              <div class="field"><label>Adjustment Type *</label>
                <select id="sa-type"><option>Moisture Loss</option><option>Damage Loss</option><option>Cleaning Loss</option><option>Recount</option><option>Opening Stock Correction</option><option>Other</option></select>
              </div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Warehouse *</label><select id="sa-warehouse"><option>Main Warehouse</option><option>Secondary Warehouse</option></select></div>
              <div class="field"><label>Reference No.</label><input id="sa-refno" placeholder="Enter reference no. (optional)"></div>
              <div class="field"><label>Reference Date</label><input type="date" id="sa-refdate"></div>
            </div>
          </div>

          <!-- 2. Product & Batch Details -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-blue"><span class="pne-num"><i class="fas fa-box"></i></span> Product &amp; Batch Details</div>
            <div class="pne-grid4">
              <div class="field"><label>Product *</label>
                <select id="sa-product" onchange="onSAProductChange()"><option value="">Select product…</option></select>
              </div>
              <div class="field"><label>Variety</label><input id="sa-variety" placeholder="Optional"></div>
              <div class="field"><label>Grade</label><input id="sa-grade" placeholder="Optional"></div>
              <div class="field"><label>Unit</label><input id="sa-unit" readonly></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Batch / Lot No.</label><input id="sa-batchno" placeholder="Optional"></div>
              <div class="field"><label>Manufacture Date</label><input type="date" id="sa-mfgdate"></div>
              <div class="field"><label>Expiry Date</label><input type="date" id="sa-expdate"></div>
              <div class="field"><label>Supplier / Farmer</label><select id="sa-supplier"><option value="">Select or —</option></select></div>
            </div>
          </div>

          <!-- 3. Stock & Moisture Details -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-amber"><span class="pne-num"><i class="fas fa-tint"></i></span> Stock &amp; Moisture Details</div>
            <div class="pne-grid4">
              <div class="field"><label>Opening Stock (Kg) *</label><input type="number" id="sa-openingstock" min="0" step="0.01" oninput="calcStockAdjustment()"></div>
              <div class="field"><label>Moisture Before (%)</label><input type="number" id="sa-moistbefore" min="0" max="100" step="0.01" oninput="calcStockAdjustment()"></div>
              <div class="field"><label>Moisture After (%)</label><input type="number" id="sa-moistafter" min="0" max="100" step="0.01" oninput="calcStockAdjustment()"></div>
              <div class="field"><label>Moisture Loss (%)</label><input id="sa-moistloss" readonly></div>
              <div class="field"><label id="sa-qty-label">Weight Loss / Gain (Kg) *</label><input type="number" id="sa-weightloss" min="0" step="0.01" oninput="calcStockAdjustment()"></div>
              <div class="field" id="sa-adjustto-row" style="display:none"><label>Adjust Stock To (Kg) *</label><input type="number" id="sa-adjustto" min="0" step="0.01" placeholder="Target stock value" oninput="calcStockAdjustment()"><span style="font-size:11px;color:var(--muted);margin-top:3px;display:block">Enter the correct stock quantity — the system will calculate the difference automatically</span></div>
              <div class="field"><label>Final Stock (Kg) *</label><input id="sa-finalstock" readonly style="background:#E8F5E9;color:#00897B;font-weight:700"><span style="font-size:10px;color:#00897B;font-weight:600">Auto Calculated</span></div>
            </div>
            <div class="pne-grid2">
              <div class="field"><label>Reason / Description *</label>
                <select id="sa-reason"><option>Drying / Moisture Loss</option><option>Physical Damage</option><option>Cleaning / Impurity Removal</option><option>Physical Recount</option><option>Pest / Spoilage</option><option>Other</option></select>
              </div>
              <div class="field"><label>Remarks (optional)</label><textarea id="sa-remarks" style="min-height:44px" placeholder="Optional notes"></textarea></div>
            </div>
          </div>

          <!-- 4. Summary -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-green"><span class="pne-num"><i class="fas fa-calculator"></i></span> Summary</div>
            <div class="sa-summary-row">
              <div class="sa-summary-chip"><span class="sa-chip-icon" style="background:#E3F2FD;color:#1976D2"><i class="fas fa-box"></i></span><div><span>Opening Stock (Kg)</span><strong id="sa-sum-opening">0.00</strong></div></div>
              <span class="sa-op" id="sa-sum-op">−</span>
              <div class="sa-summary-chip"><span class="sa-chip-icon" style="background:#FFF3E0;color:#E65100"><i class="fas fa-weight-hanging"></i></span><div><span id="sa-sum-loss-label">Weight Loss (Kg)</span><strong id="sa-sum-loss">0.00</strong></div></div>
              <span class="sa-op">=</span>
              <div class="sa-summary-chip"><span class="sa-chip-icon" style="background:#E8F5E9;color:#00897B"><i class="fas fa-check-circle"></i></span><div><span>Final Stock (Kg)</span><strong id="sa-sum-final">0.00</strong></div></div>
              <div class="sa-summary-chip"><span class="sa-chip-icon" style="background:#E3F2FD;color:#1976D2"><i class="fas fa-tint"></i></span><div><span>Moisture Before</span><strong id="sa-sum-mbefore">0.00 %</strong></div></div>
              <span class="sa-op"><i class="fas fa-arrow-right"></i></span>
              <div class="sa-summary-chip"><span class="sa-chip-icon" style="background:#FFF3E0;color:#E65100"><i class="fas fa-tint-slash"></i></span><div><span>Moisture After</span><strong id="sa-sum-mafter">0.00 %</strong></div></div>
              <div class="sa-summary-chip"><span class="sa-chip-icon" style="background:#FFEBEE;color:#C62828"><i class="fas fa-chart-line"></i></span><div><span>Moisture Loss</span><strong id="sa-sum-mloss">0.00 %</strong></div></div>
            </div>
          </div>

          <!-- 5. Attachment & Approval -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-purple"><span class="pne-num"><i class="fas fa-paperclip"></i></span> Attachment &amp; Approval</div>
            <div class="pne-grid4">
              <div class="field" style="grid-column:span 1">
                <label>Attachment (optional)</label>
                <label class="pp-dropzone" for="sa-attachment-input" id="sa-attachment-label">
                  <i class="fas fa-cloud-upload-alt"></i>
                  <div>Drag &amp; drop files here<br><span style="font-size:10px">Supported: PDF, JPG, PNG (Max 5MB)</span></div>
                </label>
                <input type="file" id="sa-attachment-input" accept="application/pdf,image/png,image/jpeg" style="display:none" onchange="saAttachmentChange(this.files[0])">
              </div>
              <div class="field"><label>Approved By</label><select id="sa-approvedby"><option value="">Select…</option></select></div>
              <div class="field"><label>Approval Date</label><input type="date" id="sa-approvaldate"></div>
              <div class="field"><label>Notes (optional)</label><textarea id="sa-notes" style="min-height:44px" placeholder="Optional"></textarea></div>
            </div>
          </div>
          <div style="display:flex;gap:8px;justify-content:flex-end">
            <a class="btn btn-outline" href="/pages/stock.php">Cancel</a>
            <button class="btn pne-btn-save" onclick="saveStockAdjustmentEntry()"><i class="fas fa-check"></i> Save</button>
          </div>
        </div>

        <!-- Right sidebar -->
        <div class="pne-sidebar">
          <div class="pne-card">
            <div class="pne-card-head pne-head-green"><i class="fas fa-bullseye"></i> Adjustment Impact</div>
            <div style="font-size:11.5px;color:var(--muted);margin-bottom:10px">This adjustment will affect the available stock.</div>
            <div class="pne-kv" style="display:block;padding-top:8px"><span>Warehouse</span><br><strong id="sa-imp-warehouse">Main Warehouse</strong></div>
            <div class="pne-kv" style="display:block;padding-top:8px"><span>Product</span><br><strong id="sa-imp-product">—</strong></div>
            <div class="pne-kv" style="display:block;padding-top:8px"><span>Batch / Lot No.</span><br><strong id="sa-imp-batch">—</strong></div>
            <div class="pne-kv" style="display:block;padding-top:8px"><span>Impact</span><br><strong style="color:#E53935">Stock Decrease</strong></div>
            <div class="pne-kv" style="display:block;padding-top:8px"><span>Accounting Impact</span><br><strong>Inventory Adjustment (Loss)</strong></div>
          </div>
          <div class="pne-card">
            <div class="pne-card-head" style="justify-content:space-between">
              <span><i class="fas fa-history"></i> Recent Adjustments</span>
              <a href="#" style="font-size:11px;color:var(--teal)" onclick="event.preventDefault()">View All</a>
            </div>
            <div id="sa-recent-list" style="display:flex;flex-direction:column;gap:12px"></div>
          </div>
        </div>
      </div>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/stock-shared.js"></script>
<script src="/assets/js/pages/stock-adjust-new.js"></script>
