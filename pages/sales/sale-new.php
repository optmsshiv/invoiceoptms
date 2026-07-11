<?php
// ================================================================
//  pages/sales/sale-new.php
//  New / Edit Sale Entry — full page (was a modal-less SPA view).
//  ?id=123 in the URL edits that sale; no ?id= starts a blank one.
//  Gated server-side by business_type, same as sales.php.
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.sales');

$user = currentUser();

$settingsRows = [];
try {
    $rows = getDB()->query('SELECT `key`, value FROM settings')->fetchAll();
    foreach ($rows as $r) $settingsRows[$r['key']] = $r['value'];
} catch (Exception $e) {
    error_log('Settings load error: ' . $e->getMessage());
}
$businessType = $settingsRows['business_type'] ?? 'both';
if (!in_array($businessType, ['product', 'both'], true)) {
    header('Location: /dashboard.php');
    exit;
}

$activePage  = 'sales';
$pageTitle   = 'New Sale Entry';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/sale-new.js'];

include __DIR__ . '/../../includes/layout_header.php';
?>
    <div id="page-sale-new" class="page active">
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
                  <button class="btn btn-outline" style="padding:0 12px" title="Add new customer" onclick="openAddCustomerModal()"><i class="fas fa-plus"></i></button>
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
              <div class="field"><label>Sales Executive</label><input id="sn-salesexec" placeholder="Optional"></div>
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
                <select id="sn-weighingtype"><option>Dharam Kanta</option><option>Platform Scale</option><option>Electronic Scale</option><option>Self Declared</option></select>
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
                  <col style="width:85px"><col style="width:65px"><col style="width:80px">
                  <col style="width:90px"><col style="width:100px"><col style="width:75px">
                  <col style="width:55px"><col style="width:80px"><col style="width:70px">
                  <col style="width:55px"><col style="width:85px"><col style="width:90px"><col style="width:56px">
                </colgroup>
                <thead><tr>
                  <th>#</th><th>Product</th><th>Category</th><th>Variety</th><th>Grade</th><th>Batch No.</th>
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
              <div class="field"><label>Payment Method *</label>
                <select id="sn-paymethod" onchange="toggleSNSplitPayment()"><option>Cash</option><option>Bank Transfer</option><option>UPI</option><option>Cheque</option><option value="Split Payment">Split Payment</option></select>
              </div>
              <div class="field"><label>Amount Received (₹)</label><input type="number" id="sn-amountreceived" value="0" min="0" oninput="calcSaleNewTotals()"></div>
              <div id="sn-split-panel" style="display:none;background:var(--bg);border-radius:8px;padding:10px;margin-bottom:10px">
                <div id="sn-split-rows" style="display:flex;flex-direction:column;gap:8px"></div>
                <button type="button" class="btn btn-outline pne-small-btn" style="margin-top:8px" onclick="addSNSplitRow(false); syncSNSplitAutoRow();"><i class="fas fa-plus"></i> Add Split</button>
                <div id="sn-split-footer" class="pne-split-footer"></div>
                <div id="sn-split-mismatch" style="display:none;font-size:11px;color:#E65100;margin-top:4px"></div>
              </div>
              <div class="field"><label>Transaction No.</label><input id="sn-transactionno" placeholder="—"></div>
              <div class="field"><label>Payment Date *</label><input type="date" id="sn-paydate"></div>
              <div class="pne-charge-total"><span>Outstanding Amount</span><strong id="sn-outstanding-amount" style="color:#E53935">₹0.00</strong></div>
            </div>

            <div class="pne-card">
              <div class="pne-card-head pne-head-green"><span class="pne-num"><i class="fas fa-receipt"></i></span> Tax &amp; Invoice Summary</div>
              <div class="pne-summary-row"><span>Sub Total</span><strong id="sn-sum-subtotal">₹0.00</strong></div>
              <div class="pne-summary-row"><span>Discount</span><strong><input type="number" id="sn-discount" value="0" min="0" class="pne-inline-num" oninput="calcSaleNewTotals()"></strong></div>
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

          <!-- Attachments -->
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
            <div class="pne-kv"><span>Total Tax</span><strong id="sn-sb-tax">₹0.00</strong></div>
            <div class="pne-kv"><span>Invoice Value</span><strong id="sn-sb-invvalue">₹0.00</strong></div>
            <div class="pne-kv"><span>Net Payable</span><strong id="sn-sb-netpayable" style="color:var(--teal)">₹0.00</strong></div>
          </div>
          <div class="pne-card">
            <div class="pne-card-head pne-head-amber"><i class="fas fa-bolt"></i> Quick Actions</div>
            <div style="display:flex;flex-direction:column;gap:8px">
              <button class="btn btn-outline" onclick="window.location.href='/pages/sales/sales.php'">View Previous Sales</button>
              <button class="btn btn-outline" onclick="toast('🧾 Payment receipt creation — coming soon','info')">Create Payment Receipt</button>
              <button class="btn btn-outline" onclick="printCurrentSaleInvoice()">Print Invoice</button>
              <button class="btn btn-outline" onclick="toast('📤 Sharing via Email/WhatsApp — coming soon','info')">Share Invoice (Email/WhatsApp)</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ─────────── SALES LIST ─────────── -->
    <!-- Add/Edit Customer Modal -->
    <div class="modal-overlay" id="modal-addcustomer">
      <div class="modal" style="max-width:520px">
        <div class="modal-header">
          <span>Add New Customer</span>
          <button class="modal-close" onclick="closeModal('modal-addcustomer')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="field"><label>Customer Name *</label><input id="cus-name" placeholder="e.g. Patel Exports"></div>
            <div class="field"><label>Customer Type</label>
              <select id="cus-type"><option>Domestic</option><option>Exporter</option><option>Wholesaler</option><option>Retailer</option></select>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="field"><label>Mobile</label><input id="cus-mobile" placeholder="+91 XXXXX XXXXX"></div>
            <div class="field"><label>Email</label><input id="cus-email" type="email"></div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="field"><label>GSTIN</label><input id="cus-gstin" placeholder="Optional"></div>
            <div class="field"><label>State</label><input id="cus-state"></div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="field"><label>District</label><input id="cus-district"></div>
            <div class="field"><label>Credit Limit (₹)</label><input id="cus-creditlimit" type="number" value="0" min="0"></div>
          </div>
          <div class="field"><label>Billing Address</label><input id="cus-billing"></div>
          <div class="field"><label>Shipping Address</label><input id="cus-shipping"></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="field"><label>Payment Terms</label><input id="cus-paymentterms" placeholder="e.g. 15 Days"></div>
            <div class="field"><label>Sales Executive</label><input id="cus-salesexec" placeholder="Optional"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" onclick="closeModal('modal-addcustomer')">Cancel</button>
          <button class="btn btn-primary" id="cus-save-btn" onclick="saveCustomer()"><i class="fas fa-check"></i> Save Customer</button>
        </div>
      </div>
    </div>
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
