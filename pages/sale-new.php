<?php
// ================================================================
//  pages/sales.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.sales');
$user = currentUser();

$activePage = 'sales';
$pageTitle  = 'New Sale';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div class="pne-topbar">
        <div>
          <div class="pne-title" id="psn-title">New Sale Entry</div>
          <div class="pne-subtitle" id="psn-subtitle">Create an export / local sale invoice</div>
        </div>
        <div class="pne-actions">
          <button class="btn btn-outline" onclick="cancelSaleEntry()">Cancel</button>
          <button class="btn btn-outline" onclick="saveSaleEntry('draft')">Save Draft</button>
          <button class="btn pne-btn-save" onclick="saveSaleEntry('stay')">Save</button>
          <button class="btn pne-btn-print" onclick="saveSaleEntry('print')"><i class="fas fa-print"></i> Save &amp; Print</button>
          <button class="btn pne-btn-savenew" onclick="toast('🚚 E-Way Bill generation needs GST-portal integration — coming soon','info')"><i class="fas fa-truck"></i> Generate E-Way Bill</button>
        </div>
      </div>

      <div class="pne-layout">
        <div class="pne-main">

          <!-- 1. Customer Information -->
          <div class="pne-card">
            <div class="pne-card-head"><span class="pne-num"><i class="fas fa-user"></i></span> Customer Information</div>
            <div class="pne-grid4">
              <div class="field">
                <label>Customer Name *</label>
                <div style="display:flex;gap:6px">
                  <select id="sn-customer" style="flex:1" onchange="onCustomerPicked()"><option value="">Select or add customer…</option></select>
                  <button class="btn btn-outline" style="padding:0 12px" title="Add new customer" onclick="goToNewCustomerFromSale()"><i class="fas fa-plus"></i></button>
                </div>
              </div>
              <div class="field"><label>Customer Type *</label>
                <select id="sn-customertype"><option>Domestic</option><option>Exporter</option><option>Wholesaler</option><option>Retailer</option></select>
              </div>
              <div class="field"><label>Customer Mobile</label><input id="sn-mobile" readonly></div>
              <div class="field"><label>GSTIN *</label><input id="sn-gstin" placeholder="—" readonly></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>State *</label><input id="sn-state" readonly></div>
              <div class="field"><label>District *</label><input id="sn-district" readonly></div>
              <div class="field"><label>Billing Address *</label><input id="sn-billing" readonly></div>
              <div class="field"><label>Shipping Address *</label><input id="sn-shipping"></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Sales Executive</label><select id="sn-salesexec"><option value="">— Select —</option></select></div>
              <div class="field"><label>Invoice No.</label><input id="sn-invno" placeholder="Auto-generated"></div>
              <div class="field"><label>Invoice Date *</label><input type="date" id="sn-invdate"></div>
              <div class="field"><label>Due Date</label><input type="date" id="sn-duedate"></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Payment Terms</label>
                <select id="sn-paymentterms"><option>Immediate</option><option>7 Days</option><option>15 Days</option><option>30 Days</option><option>Advance</option></select>
              </div>
              <div class="field"><label>Sales Type</label>
                <select id="sn-salestype" onchange="onSalesTypeChange()"><option>Local Sales</option><option>Export Sales</option><option>Interstate Sales</option></select>
              </div>
              <div class="field"><label>Place of Supply</label><input id="sn-placeofsupply" readonly></div>
              <div class="field"><label>Currency</label>
                <select id="sn-currency"><option value="INR">INR</option><option value="USD">USD</option><option value="EUR">EUR</option><option value="GBP">GBP</option></select>
              </div>
            </div>
          </div>

          <!-- Weight / Measurement Details -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-blue"><span class="pne-num"><i class="fas fa-weight-hanging"></i></span> Weight / Measurement Details</div>
            <div class="pne-grid4">
              <div class="field"><label>Weighing Type *</label>
                <select id="sn-weighingtype"><option>Dharam Kanta</option><option>Digital Kanta</option><option>Platform Scale</option><option>Electronic Scale</option><option>Self Declared</option></select>
              </div>
              <div class="field"><label>Dharam Kanta Name</label><input id="sn-kantaname" placeholder="e.g. Shree Ganesh Dharam Kanta"></div>
              <div class="field"><label>Weighbridge Slip No. *</label><input id="sn-slipno" placeholder="e.g. DK-24581"></div>
              <div class="field"><label>Weight Date &amp; Time *</label><input type="datetime-local" id="sn-weightdatetime"></div>
              <div class="field"><label>Operator Name</label><input id="sn-kantaoperator" placeholder="Optional"></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Gross Weight (Kg) *</label><input type="number" id="sn-kanta-gross" min="0" step="0.01" oninput="calcSNWeightSummary()"></div>
              <div class="field"><label>Tare Weight (Kg) *</label><input type="number" id="sn-kanta-tare" min="0" step="0.01" oninput="calcSNWeightSummary()"></div>
              <div class="field"><label>Net Weight (Kg)</label><input id="sn-kanta-net" readonly style="background:#E8F5E9;color:#00897B;font-weight:700"></div>
              <div class="field"><label>Moisture %</label><input type="number" id="sn-kanta-moisture" min="0" max="100" step="0.01" oninput="calcSNWeightSummary()"></div>
              <div class="field"><label>Dhalta (Kg)</label><input type="number" id="sn-kanta-dhaltakg" min="0" step="0.01" oninput="calcSNWeightSummary()"></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Billable Weight (Kg)</label><input id="sn-kanta-billable" readonly style="background:#E8F5E9;color:#00897B;font-weight:700"><span style="font-size:10px;color:#00897B;font-weight:600">Auto Calculated</span></div>
            </div>
          </div>

          <!-- 2. Product Details -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-purple" style="justify-content:space-between">
              <span><span class="pne-num"><i class="fas fa-boxes-stacked"></i></span> Product Details</span>
              <span style="display:flex;gap:8px;align-items:center">
                <button class="btn btn-outline pne-small-btn" onclick="addSaleNewItem()"><i class="fas fa-plus"></i> Add Item</button>
                <button class="btn btn-outline pne-small-btn" onclick="toast('📷 Barcode scanning needs a camera-enabled device — coming soon','info')"><i class="fas fa-barcode"></i> Scan Barcode</button>
                <button class="btn btn-outline pne-small-btn" onclick="toast('📊 Excel import — coming soon','info')"><i class="fas fa-file-excel"></i> Import Excel</button>
              </span>
            </div>
            <div class="table-card pit-card" style="overflow-x:auto">
              <table class="data-table pne-items-table" id="sn-items-table">
                <colgroup>
                  <col style="width:30px"><col style="width:130px"><col style="width:90px">
                  <col style="width:85px"><col style="width:65px"><col style="width:80px"><col style="width:65px">
                  <col style="width:90px"><col style="width:100px"><col style="width:75px">
                  <col style="width:55px"><col style="width:80px"><col style="width:70px">
                  <col style="width:55px"><col style="width:85px"><col style="width:90px"><col style="width:56px">
                </colgroup>
                <thead><tr>
                  <th>#</th><th>Product</th><th>Category</th><th>Variety</th><th>Grade</th><th>Batch No.</th><th>Moisture %</th>
                  <th>Available Stock (Kg)</th><th>Warehouse</th><th>Quantity (Kg)</th><th>Unit</th>
                  <th>Rate (₹/Kg)</th><th>Discount (%)</th><th>GST %</th><th>Tax Amount (₹)</th><th>Line Total (₹)</th><th>Action</th>
                </tr></thead>
                <tbody id="sn-items-tbody"></tbody>
              </table>
            </div>
            <div class="pne-items-footer">
              <span>Total Items <strong id="sn-total-items">0</strong></span>
              <span>Total Quantity <strong id="sn-total-qty">0.00 Kg</strong></span>
            </div>
          </div>

          <!-- 4. Deductions & Discounts -->
          <div class="pne-row2-eq">
            <div class="pne-card">
              <div class="pne-card-head pne-head-rose" style="justify-content:space-between">
                <span><span class="pne-num"><i class="fas fa-minus"></i></span> Deductions <span style="font-weight:400;font-size:11px;color:var(--muted)">(Add multiple deduction lines)</span></span>
                <button class="btn btn-outline pne-small-btn" onclick="addSNDeduction()"><i class="fas fa-plus"></i> Add Deduction</button>
              </div>
              <div class="table-card" style="overflow-x:auto">
                <table class="data-table" style="min-width:420px">
                  <thead><tr><th style="width:30px">#</th><th>Deduction Type</th><th>Description</th><th style="width:120px">Amount (₹)</th><th style="width:60px">Action</th></tr></thead>
                  <tbody id="sn-deductions-tbody"></tbody>
                </table>
              </div>
              <div class="pne-charge-total" style="margin-top:10px"><span>Total Deductions</span><strong id="sn-deductions-total" style="color:#E53935">₹0.00</strong></div>
            </div>

            <div class="pne-card">
              <div class="pne-card-head pne-head-indigo"><span class="pne-num"><i class="fas fa-tags"></i></span> Discounts</div>
              <div class="field"><label>Trade Discount (%)</label><input type="number" id="sn-tradediscpct" value="0" min="0" max="100" step="0.01" oninput="calcSaleNewTotals()"></div>
              <div class="field"><label>Cash Discount (CD %)</label><input type="number" id="sn-cashdiscpct" value="0" min="0" max="100" step="0.01" oninput="calcSaleNewTotals()"></div>
              <div class="field"><label>CD Applicable Within</label>
                <select id="sn-cdwithin"><option>Same Day</option><option>7 Days</option><option>15 Days</option><option>30 Days</option></select>
              </div>
              <div class="pne-charge-total" style="margin-top:6px;background:#E8F5E9;border-radius:8px;padding:10px 12px">
                <span style="color:#2E7D32;font-weight:700">Cash Discount Amount</span><strong id="sn-cashdisc-amt" style="color:#2E7D32">₹0.00</strong>
              </div>
              <div style="font-size:10.5px;color:var(--muted);margin-top:4px" id="sn-cashdisc-note">(0% of Total Gross Amount)</div>
            </div>
          </div>

          <!-- 3/4/5 row -->
          <div class="pne-row3">
            <div class="pne-card">
              <div class="pne-card-head pne-head-rose"><span class="pne-num"><i class="fas fa-coins"></i></span> Additional Charges</div>
              <div class="field"><label>Transport Charges (₹)</label><input type="number" id="sn-transportcharge" value="0" min="0" oninput="calcSaleNewTotals()"></div>
              <div class="field"><label>Loading Charges (₹)</label><input type="number" id="sn-loadingcharge" value="0" min="0" oninput="calcSaleNewTotals()"></div>
              <div class="field"><label>Packing Charges (₹)</label><input type="number" id="sn-packingcharge" value="0" min="0" oninput="calcSaleNewTotals()"></div>
              <div class="field"><label>Insurance (₹)</label><input type="number" id="sn-insurance" value="0" min="0" oninput="calcSaleNewTotals()"></div>
              <div class="field"><label>Other Charges (₹)</label><input type="number" id="sn-othercharge" value="0" min="0" oninput="calcSaleNewTotals()"></div>
              <div class="field"><label>Round Off (₹)</label><input type="number" id="sn-roundoff" value="0" step="0.01" oninput="calcSaleNewTotals()"></div>
              <div class="pne-charge-total"><span>Total Additional Charges</span><strong id="sn-addcharges-total">₹0.00</strong></div>
            </div>

            <div class="pne-card">
              <div class="pne-card-head pne-head-indigo"><span class="pne-num"><i class="fas fa-credit-card"></i></span> Payment Information</div>
              <div class="field"><label>Payment Status *</label>
                <select id="sn-paystatus" onchange="calcSaleNewTotals()"><option>Pending</option><option>Partial</option><option>Paid</option></select>
              </div>
              <div class="field"><label>Amount Received (₹)</label><input type="number" id="sn-amountreceived" value="0" min="0" oninput="calcSaleNewTotals()"></div>

              <div id="sn-partial-card" style="display:none;border:1px solid #FFCC80;border-radius:10px;overflow:hidden;margin-bottom:14px;font-size:12px">
                <div style="background:#FB8C00;color:#fff;padding:8px 12px;font-weight:700;font-size:12px;display:flex;align-items:center;gap:7px"><i class="fas fa-triangle-exclamation" style="font-size:11px"></i> Partial Payment Detected</div>
                <div style="background:#FFF8E1;padding:12px">
                  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-bottom:10px">
                    <div style="background:#fff;border:1px solid #eee;border-radius:7px;padding:7px;text-align:center">
                      <div style="font-size:8.5px;color:var(--muted);font-weight:700;letter-spacing:.3px">INVOICE TOTAL</div>
                      <div style="font-size:13px;font-weight:800;margin-top:2px" id="sn-partial-total">₹0.00</div>
                    </div>
                    <div style="background:#fff;border:1px solid #eee;border-radius:7px;padding:7px;text-align:center">
                      <div style="font-size:8.5px;color:var(--muted);font-weight:700;letter-spacing:.3px">RECEIVED</div>
                      <div style="font-size:13px;font-weight:800;margin-top:2px;color:#2E7D32" id="sn-partial-received">₹0.00</div>
                    </div>
                    <div style="background:#fff;border:1px solid #eee;border-radius:7px;padding:7px;text-align:center">
                      <div style="font-size:8.5px;color:var(--muted);font-weight:700;letter-spacing:.3px">REMAINING</div>
                      <div style="font-size:13px;font-weight:800;margin-top:2px;color:#C62828" id="sn-partial-remaining">₹0.00</div>
                    </div>
                  </div>
                  <div style="height:5px;background:#E0E0E0;border-radius:3px;overflow:hidden;margin-bottom:10px"><div id="sn-partial-bar" style="height:100%;background:#43A047;width:0%;transition:width .2s"></div></div>
                  <label style="display:flex;gap:9px;align-items:flex-start;background:#fff;border-radius:7px;padding:9px;cursor:pointer">
                    <input type="checkbox" id="sn-partial-keepactive" checked style="margin-top:2px">
                    <span>
                      <strong style="font-size:11.5px;display:flex;align-items:center;gap:5px;font-weight:700"><i class="fas fa-check-square" style="color:#FB8C00;font-size:11px"></i> Record as partial payment</strong>
                      <span style="font-size:10px;color:var(--muted);display:block;margin-top:2px;line-height:1.4">Invoice stays active — you can collect the remaining amount later. If unchecked, invoice will be marked Paid.</span>
                    </span>
                  </label>
                </div>
              </div>

              <div class="field"><label>Payment Method *</label>
                <select id="sn-paymethod" onchange="toggleSNSplitPayment()"><option>Cash</option><option>Bank Transfer</option><option>UPI</option><option>Cheque</option><option value="Split Payment">Split Payment</option></select>
              </div>
              <div id="sn-split-panel" style="display:none">
                <div class="pne-split-card">
                  <div class="pne-split-card-head"><i class="fas fa-bolt"></i> Split Payment — Enter amount per method</div>
                  <div id="sn-split-rows" style="display:flex;flex-direction:column;gap:8px"></div>
                  <div class="pne-split-actions">
                    <button type="button" class="pne-split-addbtn" onclick="addSNSplitRow(); syncSNSplitAutoRow();"><i class="fas fa-plus"></i> Add Method</button>
                    <span class="pne-split-totallabel">Split Total: <strong id="sn-split-total-amt">₹0.00</strong></span>
                  </div>
                  <div id="sn-split-footer" class="pne-split-footer"></div>
                  <div id="sn-split-mismatch" style="display:none;font-size:11px;font-weight:600;color:#E65100;background:#FFF3E0;border:1px solid #FFCC80;border-radius:6px;padding:7px 10px;margin-top:8px"></div>
                </div>
              </div>
              <div class="field"><label>Transaction No.</label><input id="sn-transactionno" placeholder="—"></div>
              <div class="field"><label>Payment Date *</label><input type="date" id="sn-paydate" onchange="syncSNInvoiceDateToPayment()"></div>
              <div class="pne-charge-total"><span>Outstanding Amount</span><strong id="sn-outstanding-amount" style="color:#E53935">₹0.00</strong></div>
            </div>

            <div class="pne-card">
              <div class="pne-card-head pne-head-green"><span class="pne-num"><i class="fas fa-receipt"></i></span> Tax &amp; Invoice Summary</div>
              <div class="pne-summary-row"><span>Sub Total</span><strong id="sn-sum-subtotal">₹0.00</strong></div>
              <div class="pne-summary-row"><span>Discount</span><strong><input type="number" id="sn-discount" value="0" min="0" class="pne-inline-num" oninput="calcSaleNewTotals()"></strong></div>
              <div class="pne-summary-row" id="sn-discount-remarks-row"><span style="font-size:11px;color:var(--muted)">Discount Remarks</span><strong><input id="sn-discount-remarks" placeholder="Reason (shown on invoice)" maxlength="255" style="width:170px;font-size:11px;padding:4px 8px;border:1px solid var(--border);border-radius:6px;text-align:right"></strong></div>
              <div class="pne-summary-row"><span>Deductions</span><strong id="sn-sum-deductions" style="color:#E53935">₹0.00</strong></div>
              <div class="pne-summary-row"><span>Trade Discount</span><strong id="sn-sum-tradedisc" style="color:#E53935">₹0.00</strong></div>
              <div class="pne-summary-row"><span>Cash Discount</span><strong id="sn-sum-cashdisc" style="color:#E53935">₹0.00</strong></div>
              <div class="pne-summary-row pne-summary-strong"><span>Taxable Amount</span><strong id="sn-sum-taxable">₹0.00</strong></div>
              <div class="pne-summary-row" id="sn-cgst-row"><span>CGST</span><strong id="sn-sum-cgst">₹0.00</strong></div>
              <div class="pne-summary-row" id="sn-sgst-row"><span>SGST</span><strong id="sn-sum-sgst">₹0.00</strong></div>
              <div class="pne-summary-row" id="sn-igst-row"><span>IGST</span><strong id="sn-sum-igst">₹0.00</strong></div>
              <div class="pne-summary-row"><span>Total Tax</span><strong id="sn-sum-totaltax">₹0.00</strong></div>
              <div class="pne-summary-row"><span>Additional Charges</span><strong id="sn-sum-addcharges2">₹0.00</strong></div>
              <div class="pne-summary-row"><span>Round Off</span><strong id="sn-sum-roundoff">₹0.00</strong></div>
              <div class="pne-grand-total"><span>Grand Total (₹)</span><strong id="sn-sum-grand">₹0.00</strong></div>
              <div class="pne-summary-row"><span>Amount Received</span><strong id="sn-sum-received">₹0.00</strong></div>
              <div class="pne-summary-row"><span>Balance Due</span><strong id="sn-sum-balance" style="color:#E53935">₹0.00</strong></div>
            </div>
          </div>

          <!-- Notes -->
          <div class="pne-card">
            <div class="pne-card-head"><i class="fas fa-sticky-note"></i> Notes</div>
            <div class="pne-grid3">
              <div class="field"><label>Customer Notes</label><textarea id="sn-customernotes" style="min-height:70px" placeholder="Visible to customer on the invoice"></textarea></div>
              <div class="field"><label>Internal Notes</label><textarea id="sn-internalnotes" style="min-height:70px" placeholder="Internal use only"></textarea></div>
              <div class="field"><label>Delivery Instructions</label><textarea id="sn-deliveryinstructions" style="min-height:70px" placeholder="e.g. Deliver before 5 PM"></textarea></div>
            </div>
          </div>

          <!-- Signatures -->
          <div class="pne-card">
            <div class="pne-signature-row">
              <div class="pne-sig-block"><label>Prepared By</label><input id="sn-preparedby" placeholder="Name"></div>
              <div class="pne-sig-block"><label>Checked By</label><input id="sn-checkedby" placeholder="Name"></div>
              <div class="pne-sig-block"><label>Approved By</label><input id="sn-approvedby" placeholder="Name"></div>
              <div class="pne-sig-block"><label>Customer Signature</label><div class="pne-sig-line"></div></div>
              <div class="pne-sig-block"><label>Authorised Signatory</label><div class="pne-sig-line"></div></div>
            </div>
          </div>
        </div>

        <!-- Right sidebar -->
        <div class="pne-sidebar">
          <div class="pne-card">
            <div class="pne-card-head"><i class="fas fa-user-circle"></i> Customer Summary</div>
            <div id="sn-customer-summary" class="pne-summary-empty">Select a customer to see their sales history.</div>
          </div>
          <div class="pne-card">
            <div class="pne-card-head pne-head-purple"><i class="fas fa-chart-line"></i> Sales Summary</div>
            <div class="pne-kv"><span>Total Items</span><strong id="sn-sb-items">0</strong></div>
            <div class="pne-kv"><span>Total Quantity</span><strong id="sn-sb-qty">0.00 Kg</strong></div>
            <div class="pne-kv"><span>Total Deductions</span><strong id="sn-sb-deductions" style="color:#E53935">₹0.00</strong></div>
            <div class="pne-kv"><span>Total Additional Charges</span><strong id="sn-sb-addcharges">₹0.00</strong></div>
            <div class="pne-kv"><span>Taxable Amount</span><strong id="sn-sb-taxable">₹0.00</strong></div>
            <div class="pne-kv"><span>Total Tax</span><strong id="sn-sb-tax">₹0.00</strong></div>
            <div class="pne-kv"><span>Invoice Value</span><strong id="sn-sb-invvalue">₹0.00</strong></div>
            <div class="pne-kv"><span>Paid Amount</span><strong id="sn-sb-paidamount">₹0.00</strong></div>
            <div class="pne-kv" style="border-top:1px dashed var(--border);margin-top:6px;padding-top:8px"><span>Net Payable</span><strong id="sn-sb-netpayable" style="color:var(--teal)">₹0.00</strong></div>
          </div>
          <div class="pne-card">
            <div class="pne-card-head"><i class="fas fa-paperclip"></i> Attachments</div>
            <label class="pp-dropzone" for="sn-attachments-input">
              <i class="fas fa-cloud-upload-alt"></i>
              <div>Drag &amp; drop files here<br>or click to upload</div>
            </label>
            <input type="file" id="sn-attachments-input" accept="application/pdf,image/png,image/jpeg" multiple style="display:none" onchange="snAddAttachments(this.files)">
            <div style="font-size:10px;color:var(--muted);margin-top:6px">Supported: PDF, JPG, PNG (Max 5MB)</div>
            <div id="sn-attachments-list" style="margin-top:8px;display:flex;flex-direction:column;gap:6px"></div>
          </div>
          <div class="pne-card">
            <div class="pne-card-head pne-head-amber"><i class="fas fa-bolt"></i> Quick Actions</div>
            <div style="display:flex;flex-direction:column;gap:8px">
              <a class="btn btn-outline" href="/pages/sales.php">View Previous Sales</a>
              <button class="btn btn-outline" onclick="toast('🧾 Payment receipt creation — coming soon','info')">Create Payment Receipt</button>
              <button class="btn btn-outline" onclick="printCurrentSaleInvoice()">Print Invoice</button>
              <button class="btn btn-outline" onclick="toast('📤 Sharing via Email/WhatsApp — coming soon','info')">Share Invoice (Email/WhatsApp)</button>
            </div>
          </div>
        </div>
      </div>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/sales-shared.js"></script>
<script src="/assets/js/edit-approval-shared.js"></script>
<script src="/assets/js/pages/sale-new.js"></script>
