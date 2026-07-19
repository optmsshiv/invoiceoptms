<?php
// ================================================================
//  pages/stock.php — Stock Summary (Phase 1: Stock module)
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.stock');
$user = currentUser();

$activePage = 'stock';
$pageTitle  = 'Stock Ledger';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div style="padding:14px 24px 0"><span style="font-size:12px;color:var(--muted)">Dashboard &gt; Inventory &gt; Product Stock</span></div>
      <div class="page-toolbar" style="padding:14px 24px 0;align-items:flex-end">
        <div class="field" style="min-width:160px"><label>Product</label><select id="ps-f-product" class="table-filter" onchange="renderProductStock()"><option value="">All Products</option></select></div>
        <div class="field" style="min-width:160px"><label>Warehouse</label><select id="ps-f-warehouse" class="table-filter" onchange="renderProductStock()"><option value="">All Warehouses</option><option>Main Warehouse</option><option>Secondary Warehouse</option></select></div>
        <div class="field" style="min-width:160px"><label>Batch / Lot No.</label><input class="table-search" id="ps-f-batch" placeholder="Enter batch / lot no." oninput="renderProductStock()"></div>
        <div class="field"><label>Date</label><input type="date" class="table-search" id="ps-f-date"></div>
        <div style="flex:1"></div>
        <a class="btn btn-outline" href="/pages/stock-adjust-new.php"><i class="fas fa-sliders-h"></i> Adjust Stock</a>
        <button class="btn btn-outline" onclick="toast('🔧 Advanced filters — coming soon','info')"><i class="fas fa-filter"></i> Filters</button>
        <button class="btn btn-outline" onclick="exportProductStockCsv()"><i class="fas fa-download"></i> Export</button>
        <a class="btn btn-primary" href="/pages/stock-in-new.php"><i class="fas fa-plus"></i> Add Stock</a>
      </div>
      <div style="padding:6px 24px 0;text-align:right;font-size:11.5px;color:var(--muted)">
        Stock as on: <span id="ps-asof"></span> <i class="fas fa-rotate" style="cursor:pointer" onclick="renderProductStock()"></i>
      </div>

      <div class="ps-stats-row" style="padding:14px 24px 0;display:grid;grid-template-columns:repeat(5,1fr);gap:14px">
        <div class="pne-card" style="display:flex;align-items:center;gap:12px;padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E3F2FD;color:#1976D2;width:38px;height:38px"><i class="fas fa-clipboard-list"></i></span>
          <div><span style="display:block;font-size:11px;color:var(--muted)">Total Products</span><strong id="ps-stat-products" style="font-size:18px">0</strong></div>
        </div>
        <div class="pne-card" style="display:flex;align-items:center;gap:12px;padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E8F5E9;color:#2E7D32;width:38px;height:38px"><i class="fas fa-cubes"></i></span>
          <div><span style="display:block;font-size:11px;color:var(--muted)">Total Stock (Kg)</span><strong id="ps-stat-stock" style="font-size:18px">0.00</strong></div>
        </div>
        <div class="pne-card" style="display:flex;align-items:center;gap:12px;padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFF3E0;color:#E65100;width:38px;height:38px"><i class="fas fa-sack-dollar"></i></span>
          <div><span style="display:block;font-size:11px;color:var(--muted)">Total Value (₹)</span><strong id="ps-stat-value" style="font-size:18px">₹0.00</strong></div>
        </div>
        <div class="pne-card" style="display:flex;align-items:center;gap:12px;padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E8F5E9;color:#00897B;width:38px;height:38px"><i class="fas fa-clipboard-check"></i></span>
          <div><span style="display:block;font-size:11px;color:var(--muted)">In Stock</span><strong id="ps-stat-instock" style="font-size:18px">0</strong></div>
        </div>
        <div class="pne-card" style="display:flex;align-items:center;gap:12px;padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFEBEE;color:#C62828;width:38px;height:38px"><i class="fas fa-triangle-exclamation"></i></span>
          <div><span style="display:block;font-size:11px;color:var(--muted)">Low Stock Items</span><strong id="ps-stat-lowstock" style="font-size:18px">0</strong></div>
        </div>
      </div>

      <div style="padding:18px 24px 0">
        <div style="display:flex;gap:22px;border-bottom:1px solid var(--border)">
          <span class="ps-tab active" id="ps-tab-summary" onclick="switchProductStockTab('summary')">Stock Summary</span>
          <span class="ps-tab" id="ps-tab-batch" onclick="switchProductStockTab('batch')">Batch Wise Stock</span>
        </div>
      </div>

      <div style="padding:14px 24px 0">
        <div class="table-card" style="overflow-x:auto">
          <table class="data-table ps-stock-table" style="min-width:1100px;table-layout:fixed">
            <colgroup>
              <col style="width:30px"><col style="width:130px"><col style="width:85px"><col style="width:95px"><col style="width:85px">
              <col style="width:90px"><col style="width:85px"><col style="width:75px"><col style="width:85px">
              <col style="width:80px"><col style="width:90px"><col style="width:85px"><col style="width:45px">
            </colgroup>
            <thead><tr>
              <th>#</th><th>Product</th><th>Variety / Grade</th><th>Warehouse</th><th id="ps-batch-col">Batch / Lot No.</th>
              <th>Available Stock (Kg)</th><th>Reserved Stock (Kg)</th><th>In Transit (Kg)</th><th>Total Stock (Kg)</th>
              <th>Avg. Cost (₹/Kg)</th><th>Stock Value (₹)</th><th>Last Inward Date</th><th>Action</th>
            </tr></thead>
            <tbody id="ps-tbody"></tbody>
          </table>
          <div class="table-footer">
            <div class="tf-info" id="ps-info"></div>
            <div class="pagination" id="ps-pagination"></div>
          </div>
        </div>
      </div>

      <div class="ps-bottom-grid" style="padding:20px 24px 0;display:grid;grid-template-columns:1.3fr 1fr;gap:18px;align-items:start">
        <div class="pne-card">
          <div class="pne-card-head">Stock Movement Summary (Last 7 Days)</div>
          <div style="overflow-x:auto">
            <table class="data-table" style="font-size:12.5px;min-width:640px">
              <thead><tr><th>Date</th><th>Opening Stock (Kg)</th><th>Stock In (Kg)</th><th>Stock Out (Kg)</th><th>Adjustment (Kg) <i class="fas fa-circle-info" title="Losses from Stock Adjustments (moisture/damage/cleaning)" style="color:var(--muted)"></i></th><th>Closing Stock (Kg)</th></tr></thead>
              <tbody id="ps-movement-tbody"></tbody>
            </table>
          </div>
        </div>
        <div class="pne-card">
          <div class="pne-card-head">Stock Trend (Last 7 Days)</div>
          <canvas id="ps-trend-chart" height="220"></canvas>
        </div>
      </div>

      <div style="padding:14px 24px 30px;font-size:11px;color:var(--muted)"><strong style="color:var(--text)">Note:</strong> Stock values are calculated based on average cost method.</div>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/stock-shared.js"></script>
<script src="/assets/js/pages/stock.js"></script>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  await bootStockPageState();
  populatePSProductFilter();
  await renderProductStock();
});
</script>
