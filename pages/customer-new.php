<?php
// ================================================================
//  pages/customers.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.customers');
$user = currentUser();

$activePage = 'customers';
$pageTitle  = 'New Customer';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div style="padding:14px 24px 0"><span style="font-size:12px;color:var(--muted)">Dashboard &gt; Customers &gt; <strong style="color:var(--text)" id="cusn-crumb">Add New Customer</strong></span></div>
      <div class="pne-topbar">
        <div><div class="pne-title" id="cusn-title">Add New Customer</div></div>
        <div class="pne-actions">
          <button class="btn btn-outline" onclick="cancelCustomerEntry()">Cancel</button>
          <button class="btn btn-outline" onclick="saveCustomerEntry('new')">Save &amp; New</button>
          <button class="btn pne-btn-save" onclick="saveCustomerEntry('close')">Save Customer</button>
        </div>
      </div>

      <div class="pne-layout">
        <div class="pne-main">

          <!-- 1. Basic Information -->
          <div class="pne-card">
            <div class="pne-card-head"><span class="pne-num"><i class="fas fa-id-card"></i></span> Basic Information</div>
            <div class="pne-grid4">
              <div class="field"><label>Customer Type *</label>
                <select id="cusn-type"><option value="">Select Type</option><option>Domestic</option><option>Exporter</option><option>Wholesaler</option><option>Retailer</option></select>
              </div>
              <div class="field"><label>Customer Code *</label><input id="cusn-code" placeholder="Auto Generate"><span style="font-size:10px;color:var(--muted)">Automatically generated code</span></div>
              <div class="field"><label>Customer Name *</label><input id="cusn-name" placeholder="Enter customer name"></div>
              <div class="field"><label>Business Name</label><input id="cusn-bizname" placeholder="Enter business name (if any)"></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Display Name *</label><input id="cusn-displayname" placeholder="Enter display name"><span style="font-size:10px;color:var(--muted)">Name will be used in documents</span></div>
              <div class="field"><label>Group</label><select id="cusn-group"><option value="">Select Group</option><option>Retail</option><option>Wholesale</option><option>VIP</option></select></div>
              <div class="field"><label>Status *</label><select id="cusn-status"><option>Active</option><option>Inactive</option></select></div>
              <div class="field"><label>Credit Limit (₹)</label><input type="number" id="cusn-creditlimit" min="0" step="0.01" value="0"><span style="font-size:10px;color:var(--muted)">Set credit limit for this customer</span></div>
            </div>
          </div>

          <!-- 2. Contact Details -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-blue"><span class="pne-num"><i class="fas fa-address-book"></i></span> Contact Details</div>
            <div class="pne-grid4">
              <div class="field"><label>Phone Number *</label><input id="cusn-phone" placeholder="Enter phone number"></div>
              <div class="field"><label>Alternate Phone</label><input id="cusn-altphone" placeholder="Enter alternate number"></div>
              <div class="field"><label>Email Address</label><input id="cusn-email" type="email" placeholder="Enter email address"></div>
              <div class="field"><label>WhatsApp No.</label><input id="cusn-whatsapp" placeholder="Enter WhatsApp number"></div>
            </div>
            <div class="pne-grid2">
              <div class="field"><label>Billing Address *</label><textarea id="cusn-billing" style="min-height:60px" placeholder="Enter complete billing address"></textarea></div>
              <div class="field">
                <label style="display:flex;justify-content:space-between;align-items:center">Shipping Address
                  <span style="font-weight:400;text-transform:none;font-size:11px;display:flex;align-items:center;gap:5px"><input type="checkbox" id="cusn-sameaddr" checked onchange="onCusnSameAddrToggle()"> Same as Billing Address</span>
                </label>
                <textarea id="cusn-shipping" style="min-height:60px" placeholder="Enter shipping address (if different)" disabled></textarea>
              </div>
            </div>
            <div class="pne-grid3">
              <div class="field"><label>City *</label><input id="cusn-city" placeholder="Enter city"></div>
              <div class="field"><label>District</label><input id="cusn-district" placeholder="Enter district"></div>
              <div class="field"><label>State *</label><select id="cusn-state"><option value="">Select state</option></select></div>
            </div>
            <div class="pne-grid2">
              <div class="field"><label>Pincode *</label><input id="cusn-pincode" placeholder="Enter pincode"></div>
            </div>
            <div class="pne-grid3" id="cusn-shipaddr-row" style="display:none">
              <div class="field"><label>City</label><input id="cusn-shipcity" placeholder="Enter city"></div>
              <div class="field"><label>State</label><select id="cusn-shipstate"><option value="">Select state</option></select></div>
              <div class="field"><label>Pincode</label><input id="cusn-shippincode" placeholder="Enter pincode"></div>
            </div>
          </div>

          <!-- 3. Business Information -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-green"><span class="pne-num"><i class="fas fa-briefcase"></i></span> Business Information</div>
            <div class="pne-grid4">
              <div class="field"><label>GST No.</label><input id="cusn-gst" placeholder="Enter GST number"></div>
              <div class="field"><label>PAN No.</label><input id="cusn-pan" placeholder="Enter PAN number"></div>
              <div class="field"><label>Business Type</label><select id="cusn-biztype"><option value="">Select Business Type</option><option>Proprietorship</option><option>Partnership</option><option>Pvt Ltd</option><option>LLP</option><option>Other</option></select></div>
              <div class="field"><label>TAN No.</label><input id="cusn-tan" placeholder="Enter TAN number"></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>IEC No.</label><input id="cusn-iec" placeholder="Enter IEC number"></div>
              <div class="field"><label>Trade License No.</label><input id="cusn-tradelicense" placeholder="Enter trade license number"></div>
              <div class="field"><label>Currency</label><select id="cusn-currency"><option value="INR">INR - Indian Rupee</option><option value="USD">USD - US Dollar</option><option value="EUR">EUR - Euro</option></select></div>
              <div class="field"><label>Default Payment Terms</label><select id="cusn-paymentterms"><option value="">Select Payment Terms</option><option>Immediate</option><option>7 Days</option><option>15 Days</option><option>30 Days</option><option>Advance</option></select></div>
            </div>
          </div>

          <!-- 4. Additional Information -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-amber"><span class="pne-num"><i class="fas fa-circle-info"></i></span> Additional Information (Optional)</div>
            <div class="pne-grid4">
              <div class="field"><label>Opening Balance (₹)</label><input type="number" id="cusn-openingbal" min="0" step="0.01" value="0"><span style="font-size:10px;color:var(--muted)">If customer has opening balance</span></div>
              <div class="field"><label>Opening Balance Type</label><select id="cusn-openingbaltype"><option>Debit</option><option>Credit</option></select></div>
              <div class="field"><label>Preferred Sales Person</label><select id="cusn-salesperson"><option value="">Select Sales Person</option></select></div>
              <div class="field"><label>Notes</label><textarea id="cusn-notes-inline" style="min-height:44px" placeholder="Enter any additional notes"></textarea></div>
            </div>
          </div>
        </div>

        <!-- Right sidebar -->
        <div class="pne-sidebar">
          <div class="pne-card">
            <div class="pne-card-head"><i class="fas fa-user-circle"></i> Customer Summary</div>
            <div class="pne-kv"><span>Customer Code</span><strong id="cusn-sum-code">Auto Generate</strong></div>
            <div class="pne-kv"><span>Status</span><strong id="cusn-sum-status" style="color:#00897B">Active</strong></div>
            <div class="pne-kv"><span>Credit Limit</span><strong id="cusn-sum-creditlimit">₹0.00</strong></div>
            <div class="pne-kv"><span>Opening Balance</span><strong id="cusn-sum-openingbal">₹0.00</strong></div>
            <div class="pne-kv"><span>Current Balance</span><strong id="cusn-sum-currentbal">₹0.00</strong></div>
            <div class="pne-kv"><span>Payment Terms</span><strong id="cusn-sum-paymentterms">Not Set</strong></div>
          </div>
          <div class="pne-card">
            <div class="pne-card-head pne-head-blue"><i class="fas fa-cloud-upload-alt"></i> Upload Documents <span style="font-weight:400;font-size:11px;color:var(--muted)">(Optional)</span></div>
            <label class="pp-dropzone" for="cusn-docs-input">
              <i class="fas fa-cloud-upload-alt"></i>
              <div>Drag &amp; drop files here or<br><span style="color:var(--teal);font-weight:600">Browse Files</span></div>
            </label>
            <input type="file" id="cusn-docs-input" accept="application/pdf,image/png,image/jpeg" multiple style="display:none" onchange="cusnAddDocs(this.files)">
            <div style="font-size:10px;color:var(--muted);margin-top:6px">Supports: JPG, PNG, PDF (Max 5MB)</div>
            <div id="cusn-docs-list" style="margin-top:8px;display:flex;flex-direction:column;gap:6px"></div>
          </div>
          <div class="pne-card">
            <div class="pne-card-head pne-head-amber"><i class="fas fa-note-sticky"></i> Notes</div>
            <textarea id="cusn-notes-sidebar" style="min-height:80px" placeholder="Add any notes about this customer…" oninput="document.getElementById('cusn-notes-inline').value=this.value"></textarea>
          </div>
        </div>
      </div>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/sales-shared.js"></script>
<script src="/assets/js/edit-approval-shared.js"></script>
<script src="/assets/js/pages/customer-new.js"></script>
