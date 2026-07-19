<?php
// ================================================================
//  pages/sales.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.sales');
$user = currentUser();

$activePage = 'sales';
$pageTitle  = 'Sales';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;flex-wrap:wrap;gap:10px">
        <div>
          <div style="font-size:20px;font-weight:800;color:var(--text)">Sales List</div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px">Dashboard &gt; Sales &gt; Sales List</div>
        </div>
        <div style="display:flex;gap:8px">
          <a class="btn btn-primary" href="/pages/sale-new.php"><i class="fas fa-plus"></i> New Sale Invoice</a>
          <button class="btn btn-outline" onclick="exportSalesExcel()"><i class="fas fa-file-excel"></i> Export Excel</button>
        </div>
      </div>

      <!-- Filters -->
      <div class="pne-card" style="margin-bottom:16px">
        <div class="pne-grid5" style="align-items:end">
          <div class="field"><label>From Date</label><input type="date" id="sl-f-from"></div>
          <div class="field"><label>To Date</label><input type="date" id="sl-f-to"></div>
          <div class="field"><label>Customer</label><select id="sl-f-customer"><option value="">All Customers</option></select></div>
          <div class="field"><label>Warehouse</label><select id="sl-f-warehouse"><option value="">All Warehouses</option><option>Main Warehouse</option><option>Secondary Warehouse</option></select></div>
          <div class="field"><label>Status</label><select id="sl-f-status"><option value="">All Status</option><option>Confirmed</option><option>Draft</option></select></div>
        </div>
        <div class="pne-grid5" style="align-items:end;margin-top:10px">
          <div class="field"><label>Payment Status</label><select id="sl-f-paystatus"><option value="">All Payment Status</option><option>Paid</option><option>Partial</option><option>Pending</option></select></div>
          <div class="field"><label>Invoice No.</label><input id="sl-f-invno" placeholder="Enter Invoice No."></div>
          <div class="field"><label>Product</label><select id="sl-f-product"><option value="">All Products</option></select></div>
          <div class="field"><label>Sales Executive</label><select id="sl-f-exec"><option value="">All Sales Executive</option></select></div>
          <div class="field" style="display:flex;gap:8px">
            <button class="btn btn-primary" style="flex:1" onclick="SL_PAGE=1; renderSales()"><i class="fas fa-magnifying-glass"></i> Search</button>
            <button class="btn btn-outline" onclick="resetSalesFilter()"><i class="fas fa-rotate-left"></i> Reset</button>
          </div>
        </div>
      </div>

      <!-- Stat cards -->
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:16px" class="ps-stats-row">
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E8F5E9;color:#2E7D32;width:36px;height:36px"><i class="fas fa-file-invoice"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Invoices</div>
          <div style="font-size:18px;font-weight:800" id="sl-stat-count">0</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px" id="sl-stat-range1">Filtered period</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E3F2FD;color:#1976D2;width:36px;height:36px"><i class="fas fa-weight-hanging"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Quantity</div>
          <div style="font-size:18px;font-weight:800" id="sl-stat-qty">0.00 Kg</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Filtered period</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFF3E0;color:#E65100;width:36px;height:36px"><i class="fas fa-indian-rupee-sign"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Sales Amount</div>
          <div style="font-size:17px;font-weight:800" id="sl-stat-amount">₹0.00</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Filtered period</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#F3E8FF;color:#6A4C93;width:36px;height:36px"><i class="fas fa-hand-holding-dollar"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Paid Amount</div>
          <div style="font-size:17px;font-weight:800;color:#2E7D32" id="sl-stat-paid">₹0.00</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Filtered period</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFEBEE;color:#C62828;width:36px;height:36px"><i class="fas fa-file-circle-exclamation"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Outstanding Amount</div>
          <div style="font-size:17px;font-weight:800;color:#E53935" id="sl-stat-out">₹0.00</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Filtered period</div>
        </div>
      </div>

      <!-- Table -->
      <div class="pne-card">
        <div class="pne-card-head pne-head-green" style="margin-bottom:12px"><i class="fas fa-table-list"></i> Sales Invoices</div>
        <div class="table-card" style="overflow-x:auto">
          <table class="data-table" style="min-width:980px">
            <thead><tr><th>#</th><th>Invoice No.</th><th>Invoice Date</th><th>Customer</th><th style="text-align:right">Qty (Kg)</th><th style="text-align:right">Net Amount (₹)</th><th>Payment Status</th><th>Status</th><th>Sales Executive</th><th>Action</th></tr></thead>
            <tbody id="salesTbody"></tbody>
          </table>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;flex-wrap:wrap;gap:8px">
          <div style="font-size:12px;color:var(--muted)" id="saleInfo"></div>
          <div style="display:flex;gap:5px" id="sl-pagination"></div>
        </div>
      </div>

      <div id="sl-note-banner" style="margin-top:14px;background:#E8F5E9;border:1px solid #A5D6A7;border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;gap:12px">
        <div style="font-size:12px;color:#1B5E20"><i class="fas fa-circle-info"></i> <b>Note:</b> You can view, print, download or share invoices using the action buttons.</div>
        <button style="background:none;border:none;color:#1B5E20;cursor:pointer;font-size:14px" onclick="document.getElementById('sl-note-banner').style.display='none'"><i class="fas fa-times"></i></button>
      </div>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/sales-shared.js"></script>
<script src="/assets/js/edit-approval-shared.js"></script>
<script src="/assets/js/pages/sales.js"></script>
