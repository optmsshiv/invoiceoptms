<?php
// ================================================================
//  pages/stock-history.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.stock_history');
$user = currentUser();

$activePage = 'stock-history';
$pageTitle  = 'Stock History';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div style="font-size:20px;font-weight:800;color:var(--text)">Stock History</div>
      <div style="font-size:12px;color:var(--muted);margin-top:2px;margin-bottom:16px">Dashboard &gt; Inventory &gt; Stock History</div>

      <div class="pne-card">
        <div class="pne-grid4" style="grid-template-columns:repeat(3,1fr)">
          <div class="field"><label>Product</label><select id="sh-f-product" onchange="onSHProductChange()"><option value="">All Products</option></select></div>
          <div class="field"><label>Batch / Lot No.</label><select id="sh-f-batch"><option value="">Select Batch / Lot</option></select></div>
          <div class="field"><label>Warehouse</label><select id="sh-f-warehouse"><option value="">All Warehouses</option><option>Main Warehouse</option><option>Secondary Warehouse</option></select></div>
        </div>
        <div class="pne-grid4" style="grid-template-columns:repeat(3,1fr)">
          <div class="field"><label>Date Range</label>
            <div style="display:flex;gap:6px">
              <input type="date" id="sh-f-from" class="table-search" style="max-width:none;flex:1">
              <input type="date" id="sh-f-to" class="table-search" style="max-width:none;flex:1">
              <button class="btn btn-outline" style="white-space:nowrap" title="See full history — useful for tracing a negative Opening Stock back to its source" onclick="setSHAllTime()">All Time</button>
            </div>
          </div>
          <div class="field"><label>Transaction Type</label><select id="sh-f-txntype"><option value="">All</option><option value="in">Stock In</option><option value="out">Stock Out</option><option value="adjustment">Stock Adjustment</option></select></div>
          <div class="field"><label>Reference Type</label><select id="sh-f-reftype"><option value="">All</option><option value="purchase">Purchase Entry</option><option value="stock_in">Stock In Entry</option><option value="sale">Sales Invoice</option><option value="adjustment">Stock Adjustment</option></select></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px">
          <button class="btn btn-outline" onclick="resetSHFilter()"><i class="fas fa-rotate-left"></i> Reset</button>
          <button class="btn pne-btn-save" onclick="renderStockHistory()"><i class="fas fa-filter"></i> Apply Filter</button>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-top:16px" class="ps-stats-row">
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#F3E8FF;color:#6A4C93;width:34px;height:34px"><i class="fas fa-box"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Opening Stock</div>
          <div style="font-size:16px;font-weight:800" id="sh-stat-opening">0.00 Kg</div>
          <div style="font-size:10px;color:var(--muted)" id="sh-stat-opening-date"></div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E8F5E9;color:#2E7D32;width:34px;height:34px"><i class="fas fa-right-to-bracket"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Stock In</div>
          <div style="font-size:16px;font-weight:800" id="sh-stat-in">0.00 Kg</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFEBEE;color:#C62828;width:34px;height:34px"><i class="fas fa-right-from-bracket"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Stock Out</div>
          <div style="font-size:16px;font-weight:800" id="sh-stat-out">0.00 Kg</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E3F2FD;color:#1976D2;width:34px;height:34px"><i class="fas fa-boxes-packing"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Closing Stock</div>
          <div style="font-size:16px;font-weight:800" id="sh-stat-closing">0.00 Kg</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFF3E0;color:#E65100;width:34px;height:34px"><i class="fas fa-coins"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Current Stock Value</div>
          <div style="font-size:16px;font-weight:800" id="sh-stat-value">₹0.00</div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-top:10px">
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFF8E1;color:#F9A825;width:34px;height:34px"><i class="fas fa-sliders"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Adjustments</div>
          <div style="font-size:16px;font-weight:800" id="sh-stat-adj-count">0</div>
          <div style="font-size:10px;color:var(--muted)" id="sh-stat-adj-sub">0.00 Kg net</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E8EAF6;color:#3949AB;width:34px;height:34px"><i class="fas fa-indian-rupee-sign"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Avg Purchase Rate</div>
          <div style="font-size:16px;font-weight:800" id="sh-stat-avg-rate">—</div>
          <div style="font-size:10px;color:var(--muted)">per Kg (purchase entries only)</div>
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;margin-bottom:10px">
        <div style="display:flex;gap:22px;border-bottom:1px solid var(--border)">
          <span class="ps-tab active">Stock History</span>
          <a class="ps-tab" href="/pages/stock.php" style="text-decoration:none">Stock Summary</a>
        </div>
        <button class="btn btn-outline" onclick="exportStockHistoryCsv()"><i class="fas fa-file-excel"></i> Export Excel</button>
      </div>

      <div class="table-card" style="overflow-x:auto">
        <table class="data-table sh-history-table" style="min-width:1100px;table-layout:fixed">
          <colgroup>
            <col style="width:30px"><col style="width:110px"><col style="width:100px"><col style="width:100px"><col style="width:95px">
            <col style="width:90px"><col style="width:90px"><col style="width:75px"><col style="width:75px"><col style="width:80px">
            <col style="width:130px"><col style="width:45px">
          </colgroup>
          <thead><tr>
            <th>#</th><th>Date &amp; Time</th><th>Transaction Type</th><th>Reference Type</th><th>Reference No.</th>
            <th>Batch / Lot No.</th><th>Warehouse</th><th>In (Kg)</th><th>Out (Kg)</th><th>Balance (Kg)</th>
            <th>Remarks</th><th>Action</th>
          </tr></thead>
          <tbody id="sh-history-tbody"></tbody>
        </table>
        <div class="table-footer" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px"><div class="tf-info" id="sh-history-info"></div><div style="display:flex;gap:5px" id="sh-pagination"></div></div>
      </div>

      <div style="padding:16px 0 30px">
        <div style="background:var(--blue-bg);color:var(--blue);border-radius:8px;padding:10px 16px;font-size:11.5px;border-left:3px solid var(--blue)">
          <i class="fas fa-circle-info"></i> <strong>Note:</strong> In (Kg) increases stock. Out (Kg) decreases stock.
        </div>
      </div>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/pages/stock-shared.js"></script>
<script src="/assets/js/pages/stock-history.js"></script>
