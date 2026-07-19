<?php
// ================================================================
//  pages/stock.php
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.stock');

$user = currentUser();

$activePage  = 'stock';
$pageTitle   = 'Stock Ledger';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/stock.js'];

include __DIR__ . '/../../includes/layout_header.php';
?>
      <div class="page-toolbar">
        <input type="text" class="table-search" placeholder="Search products…" oninput="filterStock(this.value)" id="stockSearch">
        <div style="flex:1"></div>
        <span id="stockCountInfo" style="font-size:12px;color:var(--muted);margin-right:8px"></span>
        <button class="btn btn-outline" onclick="window.location.href='/pages/stock/stock-adjust-new.php'"><i class="fas fa-sliders-h"></i> Adjust Stock</button>
        <button class="btn btn-primary" onclick="window.location.href='/pages/stock/stock-in-new.php'"><i class="fas fa-plus"></i> Add Stock</button>
      </div>
      <div class="table-card">
        <table class="data-table">
          <thead><tr><th>Product</th><th>Category</th><th>Current Stock</th><th>Last Movement</th><th>Actions</th></tr></thead>
          <tbody id="stockTbody"></tbody>
        </table>
        <div class="table-footer"><div class="tf-info" id="stockInfo"></div></div>
      </div>
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
