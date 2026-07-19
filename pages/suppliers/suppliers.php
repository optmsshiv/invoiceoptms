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
        <button class="btn btn-primary" onclick="window.location.href='/pages/suppliers/supplier-new.php'"><i class="fas fa-plus"></i> Add Supplier</button>
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

      </div>
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
