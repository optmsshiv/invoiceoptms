<?php
// ================================================================
//  pages/purchases/purchase-new.php
//  Add/Edit Purchase — full page. ?id=123 edits that purchase; no
//  ?id= starts a blank one.
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.purchases');

$user = currentUser();

$activePage  = 'purchases';
$pageTitle   = 'New Purchase Entry';
$pageScripts = [
    '/assets/js/shared-data.js',
    '/assets/js/wa-shared.js',
    '/assets/js/edit-approval-shared.js',
    '/assets/js/purchase-print-shared.js',
    '/assets/js/suppliers.js',
    '/assets/js/purchase-new.js',
];

include __DIR__ . '/../../includes/layout_header.php';
?>
    <div id="page-purchase-new" class="page">
      <div class="pne-topbar">
        <div>
          <div class="pne-title" id="pne-title">New Purchase Entry</div>
          <div class="pne-subtitle" id="pne-subtitle">Local Purchase — grains, spices &amp; other produce</div>
        </div>
        <div class="pne-actions">
          <button class="btn btn-outline" onclick="cancelPurchaseEntry()">Cancel</button>
          <button class="btn pne-btn-save" onclick="savePurchaseEntry('stay')">Save</button>
          <button class="btn pne-btn-savenew" onclick="savePurchaseEntry('new')">Save &amp; New</button>
          <div class="pne-split">
            <button class="btn pne-btn-print" onclick="savePurchaseEntry('print')"><i class="fas fa-print"></i> Save &amp; Print</button>
          </div>
        </div>
      </div>

      <div class="pne-layout">
        <div class="pne-main">

          <!-- 1. Purchase Information -->
          <div class="pne-card">
            <div class="pne-card-head"><span class="pne-num"><i class="fas fa-file-invoice"></i></span> Purchase Information</div>
            <div class="pne-grid4">
              <div class="field"><label>Purchase No. *</label><input id="pn-no" placeholder="Auto-generated"></div>
              <div class="field"><label>Purchase Date *</label><input type="date" id="pn-date"></div>
              <div class="field"><label>Supplier Type *</label>
                <select id="pn-suppliertype" onchange="onSupplierTypeChange()">
                  <option>Farmer</option><option>Trader</option><option>Company</option><option>Cooperative</option><option>Other</option>
                </select>
              </div>
              <div class="field"><label>Reference (PO No.)</label><input id="pn-refpo" placeholder="Enter (Optional)"></div>
            </div>
            <div class="pne-grid4">
              <div class="field" style="grid-column:span 1;position:relative">
                <label>Supplier / Farmer Name *</label>
                <div style="display:flex;gap:6px">
                  <select id="pn-supplier" style="flex:1" onchange="onSupplierPicked()"><option value="">Select or add supplier…</option></select>
                  <button class="btn btn-outline" style="padding:0 12px" title="Add new supplier" onclick="openAddSupplierModal()"><i class="fas fa-plus"></i></button>
                </div>
              </div>
              <div class="field"><label>Mobile No.</label><input id="pn-mobile" placeholder="+91 XXXXX XXXXX" readonly></div>
              <div class="field"><label>State *</label><input id="pn-state" placeholder="—" readonly></div>
              <div class="field"><label>District</label><input id="pn-district" placeholder="—" readonly></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Address</label><input id="pn-address" placeholder="—" readonly></div>
              <div class="field"><label>GST Applicable?</label>
                <div class="pne-pill-toggle">
                  <button type="button" class="pne-pill active" id="pn-gst-no" onclick="setGstApplicable(false)">No (Exempt)</button>
                  <button type="button" class="pne-pill" id="pn-gst-yes" onclick="setGstApplicable(true)">Yes</button>
                </div>
              </div>
              <div class="field"><label>Supplier GSTIN</label><input id="pn-gstin" placeholder="Enter GSTIN (If applicable)" disabled></div>
              <div class="field"><label>Supply Type</label>
                <select id="pn-supplytype" disabled>
                  <option>Intra-State</option><option>Inter-State</option>
                </select>
              </div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Invoice / Bill No. <i class="fas fa-info-circle" title="Supplier's own invoice number, if any" style="color:var(--muted)"></i></label><input id="pn-invno" placeholder="NA (Farmer Purchase)"></div>
              <div class="field"><label>Transport Mode</label>
                <select id="pn-transportmode"><option>Road</option><option>Rail</option><option>Air</option><option>Self Pickup</option></select>
              </div>
              <div class="field"><label>Vehicle No.</label><input id="pn-vehicleno" placeholder="e.g. BR-07-GA-1234"></div>
              <div class="field"><label>Driver Name</label><input id="pn-drivername" placeholder="Optional"></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Warehouse *</label>
                <select id="pn-warehouse"><option>Main Warehouse</option></select>
              </div>
              <div class="field"><label>Payment Terms</label>
                <select id="pn-paymentterms"><option>Immediate</option><option>Net 7</option><option>Net 15</option><option>Net 30</option><option>Advance</option></select>
              </div>
              <div class="field"><label>Payment Type</label>
                <select id="pn-paymenttype"><option>Cash</option><option>Bank Transfer</option><option>UPI</option><option>Cheque</option><option value="Split">Split</option></select>
              </div>
              <div class="field"><label>Remarks</label><input id="pn-remarks" placeholder="Optional"></div>
            </div>
          </div>

          <!-- Items Details -->
          <div class="pne-card">
            <div class="pne-card-head" style="justify-content:space-between">
              <span class="pne-head-purple"><span class="pne-num"><i class="fas fa-boxes-stacked"></i></span> Items Details</span>
              <span style="display:flex;gap:8px;align-items:center">
                <button class="btn btn-outline pne-small-btn" onclick="addPurchaseNewItem()"><i class="fas fa-plus"></i> Add Item</button>
                <button class="btn btn-outline pne-small-btn" onclick="toast('📷 Barcode scanning needs a camera-enabled device — coming soon','info')"><i class="fas fa-barcode"></i> Scan Barcode</button>
                <select id="pne-entry-mode" class="table-filter" style="font-size:12px" title="New items will use this entry mode">
                  <option value="catalog">Catalog product</option>
                  <option value="freetext">Free text (misc. line)</option>
                </select>
              </span>
            </div>
            <div class="table-card pit-card" style="overflow-x:auto">
              <table class="data-table pne-items-table">
                <colgroup>
                  <col style="width:30px"><col style="width:140px"><col style="width:90px">
                  <col style="width:70px"><col style="width:90px">
                  <col style="width:72px"><col style="width:72px"><col style="width:78px">
                  <col style="width:55px" id="pne-col-dhpct"><col style="width:65px" id="pne-col-dhkg">
                  <col style="width:90px">
                  <col style="width:82px"><col style="width:58px"><col style="width:92px"><col style="width:80px">
                </colgroup>
                <thead>
                  <tr>
                    <th rowspan="2">#</th><th rowspan="2">Product Name</th><th rowspan="2">Variety</th>
                    <th rowspan="2">Moisture %</th><th rowspan="2">Quality Grade</th>
                    <th colspan="3">Weight (Kg)</th>
                    <th colspan="2" id="pne-th-dhalta-group">Dhalta</th>
                    <th rowspan="2">Billable Wt</th>
                    <th rowspan="2">Rate (₹/Kg)</th><th rowspan="2">Disc %</th><th rowspan="2">Amount (₹)</th><th rowspan="2">Action</th>
                  </tr>
                  <tr>
                    <th>Gross</th><th>Tare</th><th>Net</th>
                    <th class="pne-dhpct-col">%</th><th>Kg</th>
                  </tr>
                </thead>
                <tbody id="pne-items-tbody"></tbody>
              </table>
            </div>
            <div class="pne-items-footer">
              <span>Total Net Weight <strong id="pne-total-net">0.00 Kg</strong></span>
              <span>Total Dhalta <strong id="pne-total-dhalta">0.00 Kg</strong></span>
              <span>Total Billable Weight <strong id="pne-total-billable">0.00 Kg</strong></span>
              <span>Total Amount <strong id="pne-total-amount" class="pne-total-amt">₹0.00</strong></span>
            </div>
            <div class="pne-note">Note: Net Weight = Gross Weight − Tare Weight &nbsp;|&nbsp; Billable Weight = Net Weight − Dhalta</div>
          </div>

          <!-- Weight Information (Kanta / Dharam Kanta) -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-blue"><span class="pne-num"><i class="fas fa-weight-hanging"></i></span> Weight Information (Kanta / Dharam Kanta)</div>
            <div class="pne-grid4">
              <div class="field"><label>Weighing Type *</label>
                <select id="pn-weighingtype"><option>Dharam Kanta</option><option>Digital Kanta</option><option>Platform Scale</option><option>Electronic Scale</option><option>Self Declared</option></select>
              </div>
              <div class="field"><label>Dharam Kanta Name *</label><input id="pn-kantaname" placeholder="e.g. Shree Ganesh Dharam Kanta"></div>
              <div class="field"><label>Weighbridge Slip No. *</label><input id="pn-slipno" placeholder="e.g. DK-24581"></div>
              <div class="field"><label>Weight Date &amp; Time *</label><input type="datetime-local" id="pn-weightdatetime"></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Gross Weight (Kg) *</label><input type="number" id="pn-kanta-gross" placeholder="Enter gross weight" step="0.01" onchange="calcPNEKantaSummary()" oninput="calcPNEKantaSummary()"></div>
              <div class="field"><label>Tare Weight (Kg) *</label><input type="number" id="pn-kanta-tare" placeholder="Enter tare weight" step="0.01" onchange="calcPNEKantaSummary()" oninput="calcPNEKantaSummary()"></div>
              <div class="field"><label>Net Weight (Kg)</label><input id="pn-kanta-net" readonly style="background:#E8F5E9;color:#00897B;font-weight:700"></div>
              <div class="field"><label>Operator Name</label><input id="pn-kanta-operator" placeholder="Optional"></div>
            </div>
            <!-- Dhalta moved here from Quality section — it's a weight deduction, not a quality metric -->
            <div class="pne-grid4" style="margin-top:4px">
              <div class="field pne-dhpct-col"><label>Dhalta (%)</label><input type="number" id="pn-q-dhaltapct" readonly style="background:var(--bg);color:var(--muted)" title="Auto-averaged from item rows"></div>
              <div class="field"><label>Dhalta (Kg)</label><input type="number" id="pn-q-dhaltakg" step="0.01" placeholder="Auto from table" oninput="onPNEHeaderDhaltaKgInput(this.value)"></div>
              <div class="field"><label>Billable Weight (Kg)</label><input id="pn-q-billable" readonly style="background:#E8F5E9;color:#00897B;font-weight:700"></div>
            </div>
            <div class="pne-note" style="background:var(--blue-bg);color:var(--blue);border-radius:7px;padding:8px 12px;font-style:normal;margin-top:4px">
              <i class="fas fa-info-circle"></i> Net − Dhalta = Billable Weight &nbsp;|&nbsp; Dhalta Kg auto-syncs from the items table
            </div>
            <div class="pne-grid4" style="margin-top:12px">
              <div class="field" style="grid-column:span 2">
                <label>Upload Weight Slip</label>
                <label class="pp-dropzone" for="pn-kanta-slip-input" style="padding:14px;flex-direction:row;justify-content:flex-start;gap:12px" id="pn-kanta-slip-label">
                  <i class="fas fa-cloud-upload-alt"></i>
                  <div style="text-align:left">Drag &amp; drop or click to upload<br><span style="font-size:10px">Supported: PDF, JPG, PNG (Max 5MB)</span></div>
                </label>
                <input type="file" id="pn-kanta-slip-input" accept="application/pdf,image/png,image/jpeg" style="display:none" onchange="pneKantaSlipChange(this.files[0])">
              </div>
            </div>
          </div>

          <!-- Quality & Moisture -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-amber"><span class="pne-num"><i class="fas fa-vial"></i></span> Quality &amp; Moisture</div>
            <div class="pne-grid4">
              <div class="field"><label>Moisture (%)</label><input type="number" id="pn-q-moisture" placeholder="Auto-averaged" step="0.1" oninput="calcPNEQualitySummary()"></div>
              <div class="field"><label>Impurity / Foreign Matter (%)</label><input type="number" id="pn-q-impurity" min="0" max="100" step="0.01" title="Overall load reading"></div>
            </div>
          </div>

          <!-- 3/4/5 row -->
          <div class="pne-row3">
            <div class="pne-card">
              <div class="pne-card-head pne-head-rose"><span class="pne-num"><i class="fas fa-coins"></i></span> Additional Charges</div>
              <div class="field"><label>Transport Charge (₹)</label><input type="number" id="pn-transportcharge" value="0" min="0" oninput="calcPurchaseNewTotals()"></div>
              <div class="field"><label>Loading / Unloading (₹)</label><input type="number" id="pn-loadingcharge" value="0" min="0" oninput="calcPurchaseNewTotals()"></div>
              <div class="field"><label>Packing Charge (₹)</label><input type="number" id="pn-packingcharge" value="0" min="0" oninput="calcPurchaseNewTotals()"></div>
              <div class="field"><label>Other Charges (₹)</label><input type="number" id="pn-othercharge" value="0" min="0" oninput="calcPurchaseNewTotals()"></div>
              <div class="pne-charge-total"><span>Total Additional Charges</span><strong id="pn-addcharges-total">₹0.00</strong></div>
            </div>

            <div class="pne-card">
              <div class="pne-card-head pne-head-rose" style="justify-content:space-between">
                <span><span class="pne-num"><i class="fas fa-minus"></i></span> Deductions <span style="font-weight:400;font-size:11px;color:var(--muted)">(Optional)</span></span>
                <button class="btn btn-outline pne-small-btn" style="padding:3px 8px;font-size:11px" onclick="addPNDeduction()"><i class="fas fa-plus"></i></button>
              </div>
              <div id="pn-deductions-list" style="display:flex;flex-direction:column;gap:8px"></div>
              <div class="pne-charge-total" style="margin-top:8px"><span>Total Deductions</span><strong id="pn-deductions-total" style="color:#E53935">₹0.00</strong></div>
            </div>

            <div class="pne-card">
              <div class="pne-card-head pne-head-indigo"><span class="pne-num"><i class="fas fa-tags"></i></span> Discounts</div>
              <div class="field"><label>Trade Discount (%)</label><input type="number" id="pn-tradediscpct" value="0" min="0" max="100" step="0.01" oninput="calcPurchaseNewTotals()"></div>
              <div class="field"><label>Cash Discount (CD %)</label><input type="number" id="pn-cashdiscpct" value="0" min="0" max="100" step="0.01" oninput="calcPurchaseNewTotals()"></div>
              <div class="field"><label>CD Applicable Within</label>
                <select id="pn-cdwithin"><option>Same Day</option><option>7 Days</option><option>15 Days</option><option>30 Days</option></select>
              </div>
              <div class="pne-charge-total" style="margin-top:6px;background:#E8F5E9;border-radius:8px;padding:10px 12px">
                <span style="color:#2E7D32;font-weight:700;font-size:12px">Cash Discount Amt</span><strong id="pn-cashdisc-amt" style="color:#2E7D32">₹0.00</strong>
              </div>
              <div style="font-size:10px;color:var(--muted);margin-top:4px" id="pn-cashdisc-note">(0% of Total Gross Amount)</div>
            </div>

            <div class="pne-card">
              <div class="pne-card-head pne-head-green"><span class="pne-num"><i class="fas fa-receipt"></i></span> Tax &amp; Amount Summary</div>
              <div class="pne-summary-row"><span>Sub Total (Items)</span><strong id="pn-sum-subtotal">₹0.00</strong></div>
              <div class="pne-summary-row"><span>Add: Additional Charges</span><strong id="pn-sum-addcharges">₹0.00</strong></div>
              <div class="pne-summary-row" id="pn-sum-deductions-row" style="display:none"><span style="color:#E53935">Less: Deductions</span><strong id="pn-sum-deductions" style="color:#E53935">₹0.00</strong></div>
              <div class="pne-summary-row pne-summary-strong"><span>Taxable Amount</span><strong id="pn-sum-taxable">₹0.00</strong></div>
              <div class="pne-summary-row">
                <span>GST / Tax <span id="pn-gst-rate-wrap" style="display:none">(<input type="number" id="pn-gst-pct" value="0" min="0" max="28" class="pne-inline-num-sm" oninput="calcPurchaseNewTotals()">%)</span></span>
                <strong id="pn-sum-gst">₹0.00</strong>
              </div>
              <div class="pne-gst-note" id="pn-gst-note">(Purchase is Exempt from GST)</div>
              <div class="pne-grand-total"><span>Grand Total (₹)</span><strong id="pn-sum-grand">₹0.00</strong></div>
            </div>

            <div class="pne-card">
              <div class="pne-card-head pne-head-indigo"><span class="pne-num"><i class="fas fa-credit-card"></i></span> Payment Information</div>
              <div class="field"><label>Payment Status</label>
                <select id="pn-paystatus" onchange="calcPurchaseNewTotals()"><option>Pending</option><option>Partial</option><option>Paid</option></select>
              </div>
              <div class="field"><label>Amount Paid (₹)</label><input type="number" id="pn-amountpaid" value="0" min="0" oninput="calcPurchaseNewTotals()"></div>

              <div id="pn-partial-card" style="display:none;border:1px solid #FFCC80;border-radius:10px;overflow:hidden;margin-bottom:14px;font-size:12px">
                <div style="background:#FB8C00;color:#fff;padding:8px 12px;font-weight:700;font-size:12px;display:flex;align-items:center;gap:7px"><i class="fas fa-triangle-exclamation" style="font-size:11px"></i> Partial Payment Detected</div>
                <div style="background:#FFF8E1;padding:12px">
                  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-bottom:10px">
                    <div style="background:#fff;border:1px solid #eee;border-radius:7px;padding:7px;text-align:center">
                      <div style="font-size:8.5px;color:var(--muted);font-weight:700;letter-spacing:.3px">INVOICE TOTAL</div>
                      <div style="font-size:13px;font-weight:800;margin-top:2px" id="pn-partial-total">₹0.00</div>
                    </div>
                    <div style="background:#fff;border:1px solid #eee;border-radius:7px;padding:7px;text-align:center">
                      <div style="font-size:8.5px;color:var(--muted);font-weight:700;letter-spacing:.3px">RECEIVED</div>
                      <div style="font-size:13px;font-weight:800;margin-top:2px;color:#2E7D32" id="pn-partial-received">₹0.00</div>
                    </div>
                    <div style="background:#fff;border:1px solid #eee;border-radius:7px;padding:7px;text-align:center">
                      <div style="font-size:8.5px;color:var(--muted);font-weight:700;letter-spacing:.3px">REMAINING</div>
                      <div style="font-size:13px;font-weight:800;margin-top:2px;color:#C62828" id="pn-partial-remaining">₹0.00</div>
                    </div>
                  </div>
                  <div style="height:5px;background:#E0E0E0;border-radius:3px;overflow:hidden;margin-bottom:10px"><div id="pn-partial-bar" style="height:100%;background:#43A047;width:0%;transition:width .2s"></div></div>
                  <label style="display:flex;gap:9px;align-items:flex-start;background:#fff;border-radius:7px;padding:9px;cursor:pointer">
                    <input type="checkbox" id="pn-partial-keepactive" checked style="margin-top:2px">
                    <span>
                      <strong style="font-size:11.5px;display:flex;align-items:center;gap:5px;font-weight:700"><i class="fas fa-check-square" style="color:#FB8C00;font-size:11px"></i> Record as partial payment</strong>
                      <span style="font-size:10px;color:var(--muted);display:block;margin-top:2px;line-height:1.4">Purchase stays active — you can pay the remaining amount later. If unchecked, purchase will be marked Paid.</span>
                    </span>
                  </label>
                </div>
              </div>

              <div class="field"><label>Payment Mode</label>
                <select id="pn-paymode" onchange="togglePNESplitPayment()"><option>Cash</option><option>Bank Transfer</option><option>UPI</option><option>Cheque</option><option value="Split Payment">Split Payment</option></select>
              </div>
              <div id="pne-split-panel" style="display:none">
                <div class="pne-split-card">
                  <div class="pne-split-card-head"><i class="fas fa-bolt"></i> Split Payment — Enter amount per method</div>
                  <div id="pne-split-rows" style="display:flex;flex-direction:column;gap:8px"></div>
                  <div class="pne-split-actions">
                    <button type="button" class="pne-split-addbtn" onclick="addPNESplitRow(); syncPNESplitAutoRow();"><i class="fas fa-plus"></i> Add Method</button>
                    <span class="pne-split-totallabel">Split Total: <strong id="pne-split-total-amt">₹0.00</strong></span>
                  </div>
                  <div id="pne-split-footer" class="pne-split-footer"></div>
                  <div id="pne-split-mismatch" style="display:none;font-size:11px;font-weight:600;color:#E65100;background:#FFF3E0;border:1px solid #FFCC80;border-radius:6px;padding:7px 10px;margin-top:8px"></div>
                </div>
              </div>
              <div class="field"><label>Transaction No.</label><input id="pn-transactionno" placeholder="—"></div>
              <div class="field"><label>Payment Date</label><input type="date" id="pn-paydate"></div>
            </div>
          </div>
        </div>

        <!-- Right sidebar -->
        <div class="pne-sidebar">
          <div class="pne-card">
            <div class="pne-card-head pne-head-blue"><i class="fas fa-calculator"></i> Weight Summary <span style="font-weight:400;font-size:11px;color:var(--muted)">(Auto Calculated)</span></div>
            <div class="pne-kv"><span>Gross Weight</span><strong id="pnk-sum-gross">0.00 Kg</strong></div>
            <div class="pne-kv"><span>Tare Weight</span><strong id="pnk-sum-tare">0.00 Kg</strong></div>
            <div class="pne-kv"><span>Net Weight</span><strong id="pnk-sum-net">0.00 Kg</strong></div>
            <div class="pne-kv"><span>Dhalta Weight</span><strong id="pnk-sum-dhalta">0.00 Kg</strong></div>
            <div class="pne-kv pne-kanta-summary-billable"><span>Billable Weight</span><strong id="pnk-sum-billable">0.00 Kg</strong></div>
          </div>
          <div class="pne-card">
            <div class="pne-card-head"><i class="fas fa-user-circle"></i> Supplier Summary</div>
            <div id="pne-supplier-summary" class="pne-summary-empty">Select a supplier to see their purchase history.</div>
          </div>
          <div class="pne-card">
            <div class="pne-card-head"><i class="fas fa-box"></i> Product Summary <span style="font-weight:400;font-size:11px;color:var(--muted)">(Selected Items)</span></div>
            <div class="pne-kv"><span>Total Items</span><strong id="pne-sb-items">0</strong></div>
            <div class="pne-kv"><span>Total Net Weight</span><strong id="pne-sb-net">0.00 Kg</strong></div>
            <div class="pne-kv"><span>Total Dhalta</span><strong id="pne-sb-dhalta">0.00 Kg</strong></div>
            <div class="pne-kv"><span>Total Billable Weight</span><strong id="pne-sb-billable">0.00 Kg</strong></div>
            <div class="pne-kv"><span>Total Discount</span><strong id="pne-sb-discount" style="color:#E65100">₹0.00</strong></div>
            <div class="pne-kv"><span>Total Amount</span><strong id="pne-sb-amount">₹0.00</strong></div>
          </div>

          <div class="pne-card">
            <div class="pne-card-head"><i class="fas fa-paperclip"></i> Attachments</div>
            <div style="font-size:11px;color:var(--muted);margin-bottom:8px">Invoice / Bill (Optional)</div>
            <input type="file" id="pn-attachment" accept="image/png,image/jpeg,application/pdf" style="font-size:12px">
            <div style="font-size:10px;color:var(--muted);margin-top:6px">Supported: PDF, JPG, PNG (Max 5MB)</div>
          </div>
          <div class="pne-card">
            <div class="pne-card-head"><i class="fas fa-sticky-note"></i> Notes</div>
            <textarea id="pn-notes" placeholder="Type any additional notes here…" style="min-height:80px"></textarea>
          </div>
        </div>
      </div>
      <div class="pne-feature-footer">
        <div class="pne-feature-item"><span class="pne-feature-icon"><i class="fas fa-shield-alt"></i></span><div><strong>No GST (Farmer Purchase)</strong><span>Purchase is GST Exempt</span></div></div>
        <div class="pne-feature-item"><span class="pne-feature-icon"><i class="fas fa-award"></i></span><div><strong>Quality First</strong><span>Moisture &amp; Grade Tracked</span></div></div>
        <div class="pne-feature-item"><span class="pne-feature-icon"><i class="fas fa-warehouse"></i></span><div><strong>Stock Updated</strong><span>Real-time Inventory Update</span></div></div>
        <div class="pne-feature-item"><span class="pne-feature-icon"><i class="fas fa-chart-bar"></i></span><div><strong>Reports &amp; Analytics</strong><span>Better Purchase Insights</span></div></div>
        <div class="pne-feature-item"><span class="pne-feature-icon"><i class="fas fa-file-shield"></i></span><div><strong>Audit Ready</strong><span>Complete Purchase Trail</span></div></div>
      </div>
    </div>


    <!-- ─────────── STOCK LEDGER ─────────── -->
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
