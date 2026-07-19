<?php
// ================================================================
//  pages/products.php — Products / Services (business-type gated)
//  'product' and 'both' tenants get the full stock-tracked product
//  list view; 'service' tenants get the simpler inline add-row view.
//  Both HTML blocks render server-side behind this check —
//  products.js contains both function sets from its original
//  service-only version plus the new product-list additions, and
//  only calls the one matching $businessType on load.
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.products');
$user = currentUser();

$activePage = 'products';
$pageTitle  = 'Services / Products';
require_once __DIR__ . '/../includes/layout_header.php';

$isProductView = in_array($businessType, ['product', 'both'], true);
?>

<?php if ($isProductView): ?>
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;flex-wrap:wrap;gap:10px">
        <div>
          <div style="font-size:20px;font-weight:800;color:var(--text)">Product List</div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px">Dashboard &gt; Inventory &gt; Product List</div>
        </div>
        <div style="display:flex;gap:8px">
          <a class="btn btn-primary" href="/pages/product-new.php"><i class="fas fa-plus"></i> Add New Product</a>
          <button class="btn btn-outline" onclick="exportProductsExcel()"><i class="fas fa-file-excel"></i> Export Excel</button>
        </div>
      </div>

      <!-- Filters -->
      <div class="pne-card" style="margin-bottom:16px">
        <div class="pne-grid5" style="align-items:end">
          <div class="field"><label>Search Product</label><input id="prl-f-search" placeholder="Search by product name, SKU / code" oninput="PRL_PAGE=1; renderProductsList()"></div>
          <div class="field"><label>Category</label><select id="prl-f-category"><option value="">All Categories</option></select></div>
          <div class="field"><label>Unit</label><select id="prl-f-unit"><option value="">All Units</option></select></div>
          <div class="field"><label>Status</label><select id="prl-f-status"><option value="">All Status</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
          <div class="field"><label>HSN Code</label><input id="prl-f-hsn" placeholder="Enter HSN Code"></div>
        </div>
        <div class="pne-grid5" style="align-items:end;margin-top:10px">
          <div class="field"><label>Warehouse</label><select id="prl-f-warehouse"><option value="">All Warehouses</option><option>Main Warehouse</option><option>Secondary Warehouse</option></select></div>
          <div class="field" style="display:flex;gap:8px">
            <button class="btn btn-primary" style="flex:1" onclick="PRL_PAGE=1; renderProductsList()"><i class="fas fa-magnifying-glass"></i> Search</button>
            <button class="btn btn-outline" onclick="resetProductsListFilter()"><i class="fas fa-rotate-left"></i> Reset</button>
          </div>
        </div>
      </div>

      <!-- Stat cards -->
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:16px" class="ps-stats-row">
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E8F5E9;color:#2E7D32;width:36px;height:36px"><i class="fas fa-boxes-stacked"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Products</div>
          <div style="font-size:18px;font-weight:800" id="prl-stat-total">0</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px"><span id="prl-stat-active">0</span> Active Products</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E3F2FD;color:#1976D2;width:36px;height:36px"><i class="fas fa-box-open"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">In Stock</div>
          <div style="font-size:18px;font-weight:800" id="prl-stat-instock">0</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Products</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFF3E0;color:#E65100;width:36px;height:36px"><i class="fas fa-triangle-exclamation"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Low Stock</div>
          <div style="font-size:18px;font-weight:800;color:#E65100" id="prl-stat-lowstock">0</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Below reorder level</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFEBEE;color:#C62828;width:36px;height:36px"><i class="fas fa-ban"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Out of Stock</div>
          <div style="font-size:18px;font-weight:800;color:#E53935" id="prl-stat-outstock">0</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Products</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#F3E8FF;color:#6A4C93;width:36px;height:36px"><i class="fas fa-box-archive"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Inactive Products</div>
          <div style="font-size:18px;font-weight:800" id="prl-stat-inactive">0</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Products</div>
        </div>
      </div>

      <!-- Table -->
      <div class="pne-card">
        <div class="pne-card-head pne-head-green" style="margin-bottom:12px"><i class="fas fa-table-list"></i> Products</div>
        <div class="table-card" style="overflow-x:auto">
          <table class="data-table" style="min-width:1020px">
            <thead><tr><th>#</th><th>Product Name</th><th>SKU / Code</th><th>Category</th><th>Unit</th><th>HSN Code</th><th style="text-align:right">Sale Rate (₹)</th><th style="text-align:right">Purchase Rate (₹)</th><th style="text-align:right">Current Stock</th><th>Status</th><th>Action</th></tr></thead>
            <tbody id="prl-tbody"></tbody>
          </table>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;flex-wrap:wrap;gap:8px">
          <div style="font-size:12px;color:var(--muted)" id="prl-info"></div>
          <div style="display:flex;gap:5px" id="prl-pagination"></div>
        </div>
      </div>

<?php else: ?>
      <div class="page-toolbar">
        <input type="text" class="table-search" placeholder="Search…" oninput="filterProducts(this.value)" id="productSearch">
        <select class="table-filter" onchange="filterProductsCat(this.value)" id="productCatFilter">
          <option value="">All Categories</option>
        </select>
        <div style="flex:1"></div>
        <span id="prodCountInfo" style="font-size:12px;color:var(--muted);margin-right:8px"></span>
        <button class="btn btn-outline" id="prodArchiveToggleBtn" onclick="toggleArchivedView()"><i class="fas fa-box-archive"></i> View Archived</button>
        <button class="btn btn-primary" id="prodAddBtn" onclick="openAddProductModal()"><i class="fas fa-plus"></i> <span id="prodAddBtnLabel">Add Service</span></button>
      </div>
      <div class="table-card">
        <table class="data-table">
          <thead><tr><th>#</th><th id="prodNameColLabel">Service Name</th><th>Category</th><th>Rate (₹)</th><th>HSN</th><th>GST%</th><th>Unit Type</th><th>Actions</th></tr></thead>
          <tbody id="productsTbody"></tbody>
        </table>
        <div class="table-footer">
          <div class="tf-info" id="prodInfo"></div>
          <div class="pagination" id="prodPagination"></div>
        </div>
      </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/shared-data.js"></script>
<script src="/assets/js/edit-approval-shared.js"></script>
<script src="/assets/js/pages/products.js"></script>
