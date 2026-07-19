<?php
// ================================================================
//  pages/suppliers.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.suppliers');
$user = currentUser();

$activePage = 'suppliers';
$pageTitle  = 'New Supplier';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div style="padding:14px 24px 0"><span style="font-size:12px;color:var(--muted)">Dashboard &gt; Masters &gt; Supplier / Farmer &gt; <strong style="color:var(--text)" id="supn-crumb">Add New</strong></span></div>
      <div class="pne-topbar">
        <div><div class="pne-title" id="supn-title">Add Supplier / Farmer</div></div>
        <div class="pne-actions">
          <button class="btn btn-outline" onclick="cancelSupplierEntry()">Cancel</button>
          <button class="btn pne-btn-save" onclick="saveSupplierEntry()">Save</button>
        </div>
      </div>

      <div class="pne-layout" style="grid-template-columns:1fr">
        <div class="pne-main">

          <!-- 1. Basic Information -->
          <div class="pne-card">
            <div class="pne-card-head"><span class="pne-num"><i class="fas fa-id-card"></i></span> Basic Information</div>
            <div class="pne-grid5">
              <div class="field"><label>Type *</label>
                <select id="sup-type" onchange="onSupplierTypeChangeRich()">
                  <option value="">Select Type</option>
                  <option>Farmer</option><option>Trader</option><option>Company</option><option>Cooperative</option><option>Other</option>
                </select>
              </div>
              <div class="field"><label>Name / Company / Organization *</label><input id="sup-name" placeholder="Enter name"></div>
              <div class="field"><label>Contact Person *</label><input id="sup-contactperson" placeholder="Enter contact person"></div>
              <div class="field"><label>Mobile No. *</label><input id="sup-mobile" placeholder="Enter mobile number"></div>
              <div class="field"><label>Email ID</label><input id="sup-email" type="email" placeholder="Enter email id"></div>
            </div>
            <div class="pne-grid5">
              <div class="field"><label>Date of Registration</label><input type="date" id="sup-regdate"></div>
              <div class="field"><label>Business Nature</label>
                <select id="sup-bizNature"><option value="">Select business nature</option><option>Wholesale</option><option>Retail</option><option>Farming</option><option>Processing</option><option>Export/Import</option></select>
              </div>
              <div class="field"><label>Website</label><input id="sup-website" placeholder="Enter website (optional)"></div>
            </div>
          </div>

          <div class="pne-row3" style="grid-template-columns:1fr 1fr">
            <!-- 2. Address Information -->
            <div class="pne-card">
              <div class="pne-card-head pne-head-blue"><span class="pne-num"><i class="fas fa-location-dot"></i></span> Address Information</div>
              <div class="field"><label>Address *</label><textarea id="sup-address" style="min-height:60px" placeholder="Enter full address"></textarea></div>
              <div class="pne-grid2">
                <div class="field"><label>City *</label><input id="sup-city" placeholder="Enter city"></div>
                <div class="field"><label>District</label><input id="sup-district" placeholder="Enter district"></div>
              </div>
              <div class="pne-grid2">
                <div class="field"><label>State *</label><select id="sup-state"><option value="">Select state</option></select></div>
                <div class="field"><label>Pincode *</label><input id="sup-pincode" placeholder="Enter pincode"></div>
              </div>
              <div class="pne-grid2">
                <div class="field"><label>Country</label><select id="sup-country"><option>India</option></select></div>
              </div>
            </div>

            <!-- 3. Tax & Registration -->
            <div class="pne-card">
              <div class="pne-card-head pne-head-green"><span class="pne-num"><i class="fas fa-file-invoice"></i></span> Tax &amp; Registration</div>
              <div class="pne-grid2" id="sup-gstin-wrap">
                <div class="field"><label>GSTIN / ID No.</label><input id="sup-gstin" placeholder="Enter GSTIN or ID number"></div>
                <div class="field"><label>PAN No.</label><input id="sup-pan" placeholder="Enter PAN number"></div>
              </div>
              <div class="pne-grid2">
                <div class="field"><label>Aadhaar No.</label><input id="sup-aadhaar" placeholder="Enter Aadhaar number"></div>
                <div class="field"><label>State Code</label><input id="sup-statecode" placeholder="Enter state code"></div>
              </div>
              <div class="pne-grid2">
                <div class="field"><label>TAN No.</label><input id="sup-tan" placeholder="Enter TAN number"></div>
                <div class="field"><label>MSME No.</label><input id="sup-msme" placeholder="Enter MSME number"></div>
              </div>
              <div class="field"><label>FSSAI No.</label><input id="sup-fssai" placeholder="Enter FSSAI number (if any)"></div>
              <div id="sup-farmer-note" class="pne-note" style="display:none;background:var(--blue-bg);color:var(--blue);border-radius:7px;padding:8px 12px;font-style:normal;margin-top:6px">
                <i class="fas fa-info-circle"></i> Farmer purchases are typically GST-exempt — GSTIN hidden for this supplier type.
              </div>
            </div>
          </div>

          <div class="pne-row3" style="grid-template-columns:1fr 1fr">
            <!-- 4. Additional Information -->
            <div class="pne-card">
              <div class="pne-card-head pne-head-amber"><span class="pne-num"><i class="fas fa-building-columns"></i></span> Additional Information</div>
              <div class="pne-grid2">
                <div class="field"><label>Bank Name</label><input id="sup-bankname" placeholder="Enter bank name"></div>
                <div class="field"><label>Bank Account No.</label><input id="sup-bankacc" placeholder="Enter account number"></div>
              </div>
              <div class="pne-grid2">
                <div class="field"><label>IFSC Code</label><input id="sup-ifsc" placeholder="Enter IFSC code"></div>
                <div class="field"><label>Account Holder Name</label><input id="sup-accholder" placeholder="Enter account holder name"></div>
              </div>
              <div class="pne-grid2">
                <div class="field"><label>Credit Limit (₹)</label><input type="number" id="sup-creditlimit" min="0" step="0.01" placeholder="Enter credit limit"></div>
                <div class="field"><label>Opening Balance (₹)</label><input type="number" id="sup-openingbal" min="0" step="0.01" value="0"></div>
              </div>
              <div class="pne-grid2">
                <div class="field"><label>Payment Terms</label>
                  <select id="sup-paymentterms"><option value="">Select payment terms</option><option>Immediate</option><option>Net 7</option><option>Net 15</option><option>Net 30</option><option>Advance</option></select>
                </div>
                <div class="field"><label>Default Price List</label>
                  <select id="sup-pricelist"><option value="">Select price list</option><option>Standard</option><option>Wholesale</option><option>Premium</option></select>
                </div>
              </div>
              <div class="field"><label>Notes</label><textarea id="sup-notes" style="min-height:44px" placeholder="Enter notes (optional)"></textarea></div>
            </div>

            <!-- 5. Status & Documents -->
            <div class="pne-card">
              <div class="pne-card-head pne-head-purple"><span class="pne-num"><i class="fas fa-shield-halved"></i></span> Status &amp; Documents</div>
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
                <div class="tog on" id="sup-status" onclick="this.classList.toggle('on')"></div>
                <span style="font-size:13px;font-weight:600">Active</span>
              </div>
              <label>Upload Documents</label>
              <label class="pp-dropzone" for="sup-docs-input" style="margin-top:6px">
                <i class="fas fa-cloud-upload-alt"></i>
                <div>Drag &amp; drop files here<br>or click to upload</div>
              </label>
              <input type="file" id="sup-docs-input" accept="application/pdf,image/png,image/jpeg" multiple style="display:none" onchange="supAddDocs(this.files)">
              <div style="font-size:10px;color:var(--muted);margin-top:6px">Supported formats: PDF, JPG, PNG (Max 5MB)</div>
              <div id="sup-docs-list" style="margin-top:10px;display:flex;flex-direction:column;gap:6px"></div>
            </div>
          </div>

          <div style="font-size:11.5px;color:var(--muted)"><strong style="color:var(--text)">Note:</strong> Fields marked with * are mandatory</div>
          <div style="display:flex;gap:8px;justify-content:flex-end">
            <button class="btn btn-outline" onclick="cancelSupplierEntry()">Cancel</button>
            <button class="btn pne-btn-save" onclick="saveSupplierEntry()">Save</button>
          </div>
        </div>
      </div>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/shared-data.js"></script>
<script src="/assets/js/pages/supplier-new.js"></script>
