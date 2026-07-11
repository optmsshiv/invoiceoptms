<?php
// ================================================================
//  pages/suppliers.php
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.suppliers');

$user = currentUser();

$activePage  = 'suppliers';
$pageTitle   = 'Suppliers';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/suppliers.js'];

include __DIR__ . '/../../includes/layout_header.php';
?>
      <div class="page-toolbar">
        <input type="text" class="table-search" placeholder="Search suppliers…" oninput="filterSuppliers(this.value)" id="supplierSearch">
        <div style="flex:1"></div>
        <span id="supCountInfo" style="font-size:12px;color:var(--muted);margin-right:8px"></span>
        <button class="btn btn-outline" id="supArchiveToggleBtn" onclick="toggleSupplierArchivedView()"><i class="fas fa-box-archive"></i> View Archived</button>
        <button class="btn btn-primary" onclick="openAddSupplierModal()"><i class="fas fa-plus"></i> Add Supplier</button>
      </div>
      <div class="table-card">
        <table class="data-table">
          <thead><tr><th>#</th><th>Supplier Name</th><th>Contact Person</th><th>Phone</th><th>Country</th><th>GST No.</th><th>Actions</th></tr></thead>
          <tbody id="suppliersTbody"></tbody>
        </table>
        <div class="table-footer">
          <div class="tf-info" id="supInfo"></div>
          <div class="pagination" id="supPagination"></div>
        </div>
      </div>

      <!-- Add/Edit Supplier Modal — suppliers-page-specific -->
      <div class="modal-overlay" id="modal-addsupplier">
        <div class="modal" style="max-width:520px">
          <div class="modal-header">
            <span>Add New Supplier</span>
            <button class="modal-close" onclick="closeModal('modal-addsupplier')"><i class="fas fa-times"></i></button>
          </div>
          <div class="modal-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
              <div class="field"><label>Supplier / Company Name *</label><input id="sup-name" placeholder="e.g. Sunrise Textiles Pvt Ltd"></div>
              <div class="field"><label>Contact Person</label><input id="sup-person" placeholder="e.g. Rajeev Kumar"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
              <div class="field"><label>Phone</label><input id="sup-phone" placeholder="+91 XXXXX XXXXX"></div>
              <div class="field"><label>Email</label><input id="sup-email" type="email" placeholder="supplier@example.com"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
              <div class="field"><label>GST Number</label><input id="sup-gst" placeholder="22AAAAA0000A1Z5"></div>
              <div class="field"><label>Country</label><input id="sup-country" value="India"></div>
            </div>
            <div class="field"><label>Address</label><input id="sup-address" placeholder="Full address"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
              <div class="field"><label>Payment Terms</label><input id="sup-terms" placeholder="e.g. Net 30, Advance"></div>
              <div class="field"><label>Opening Balance (₹)</label><input id="sup-opening" type="number" value="0"></div>
            </div>
            <div class="field"><label>Notes</label><input id="sup-notes" placeholder="Optional"></div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-addsupplier')">Cancel</button>
            <button class="btn btn-primary" id="sup-save-btn" onclick="saveSupplier()"><i class="fas fa-check"></i> Save Supplier</button>
          </div>
        </div>
      </div>
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
