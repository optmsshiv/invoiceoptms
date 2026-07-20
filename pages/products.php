<?php
// ================================================================
//  pages/products.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requirePermission('menu.products');

$user = currentUser();

$activePage  = 'products';
$pageTitle   = 'Services / Products';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/products.js'];

include __DIR__ . '/../includes/layout_header.php';
?>
      <div class="page-toolbar">
        <input type="text" class="table-search" placeholder="Search services…" oninput="filterProducts(this.value)" id="productSearch">
        <select class="table-filter" onchange="filterProductsCat(this.value)" id="productCatFilter">
          <option value="">All Categories</option>
        </select>
        <div style="flex:1"></div>
        <span id="prodCountInfo" style="font-size:12px;color:var(--muted);margin-right:8px"></span>
        <button class="btn btn-outline" id="prodArchiveToggleBtn" onclick="toggleArchivedView()"><i class="fas fa-box-archive"></i> View Archived</button>
        <button class="btn btn-primary" id="prodAddBtn" onclick="openAddProductModal()"><i class="fas fa-plus"></i> Add Service</button>
      </div>
      <div class="table-card">
        <table class="data-table">
          <thead><tr><th>#</th><th>Service Name</th><th>Category</th><th>Rate (₹)</th><th>HSN</th><th>GST%</th><th>Actions</th></tr></thead>
          <tbody id="productsTbody"></tbody>
        </table>
        <div class="table-footer">
          <div class="tf-info" id="prodInfo"></div>
          <div class="pagination" id="prodPagination"></div>
        </div>
      </div>
<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
