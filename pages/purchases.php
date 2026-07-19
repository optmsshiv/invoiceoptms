<?php
// ================================================================
//  pages/purchases.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.purchases');
$user = currentUser();

$activePage = 'purchases';
$pageTitle  = 'Purchases';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;flex-wrap:wrap;gap:10px">
        <div>
          <div style="font-size:20px;font-weight:800;color:var(--text)">Purchase List</div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px">Dashboard &gt; Purchase &gt; Purchase List</div>
        </div>
        <div style="display:flex;gap:8px">
          <a class="btn btn-primary" href="/pages/purchase-new.php"><i class="fas fa-plus"></i> New Purchase Invoice</a>
          <button class="btn btn-outline" onclick="exportPurchasesExcel()"><i class="fas fa-file-excel"></i> Export Excel</button>
        </div>
      </div>

      <!-- Filters -->
      <div class="pne-card" style="margin-bottom:16px">
        <div class="pne-grid5" style="align-items:end">
          <div class="field"><label>From Date</label><input type="date" id="pl-f-from"></div>
          <div class="field"><label>To Date</label><input type="date" id="pl-f-to"></div>
          <div class="field"><label>Supplier</label><select id="pl-f-supplier"><option value="">All Suppliers</option></select></div>
          <div class="field"><label>Warehouse</label><select id="pl-f-warehouse"><option value="">All Warehouses</option><option>Main Warehouse</option><option>Secondary Warehouse</option></select></div>
          <div class="field"><label>Status</label><select id="pl-f-status"><option value="">All Status</option><option>Completed</option><option>Pending</option></select></div>
        </div>
        <div class="pne-grid5" style="align-items:end;margin-top:10px">
          <div class="field"><label>Payment Status</label><select id="pl-f-paystatus"><option value="">All Payment Status</option><option>Paid</option><option>Partial</option><option>Pending</option></select></div>
          <div class="field"><label>Invoice No.</label><input id="pl-f-invno" placeholder="Enter Invoice No."></div>
          <div class="field"><label>Product</label><select id="pl-f-product"><option value="">All Products</option></select></div>
          <div class="field"><label>Payment Type</label><select id="pl-f-paytype"><option value="">All Payment Types</option></select></div>
          <div class="field" style="display:flex;gap:8px">
            <button class="btn btn-primary" style="flex:1" onclick="PL_PAGE=1; renderPurchases()"><i class="fas fa-magnifying-glass"></i> Search</button>
            <button class="btn btn-outline" onclick="resetPurchasesFilter()"><i class="fas fa-rotate-left"></i> Reset</button>
          </div>
        </div>
      </div>

      <!-- Stat cards -->
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:16px" class="ps-stats-row">
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E8F5E9;color:#2E7D32;width:36px;height:36px"><i class="fas fa-cart-shopping"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Purchases</div>
          <div style="font-size:18px;font-weight:800" id="pl-stat-count">0</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px" id="pl-stat-range1">Filtered period</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E3F2FD;color:#1976D2;width:36px;height:36px"><i class="fas fa-weight-hanging"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Quantity</div>
          <div style="font-size:18px;font-weight:800" id="pl-stat-qty">0.00 Kg</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Filtered period</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFF3E0;color:#E65100;width:36px;height:36px"><i class="fas fa-indian-rupee-sign"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Purchase Amount</div>
          <div style="font-size:17px;font-weight:800" id="pl-stat-amount">₹0.00</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Filtered period</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#F3E8FF;color:#6A4C93;width:36px;height:36px"><i class="fas fa-hand-holding-dollar"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Paid Amount</div>
          <div style="font-size:17px;font-weight:800;color:#2E7D32" id="pl-stat-paid">₹0.00</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Filtered period</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFEBEE;color:#C62828;width:36px;height:36px"><i class="fas fa-file-circle-exclamation"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Outstanding Amount</div>
          <div style="font-size:17px;font-weight:800;color:#E53935" id="pl-stat-out">₹0.00</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Filtered period</div>
        </div>
      </div>

      <!-- Table -->
      <div class="pne-card">
        <div class="pne-card-head pne-head-green" style="margin-bottom:12px"><i class="fas fa-table-list"></i> Purchase Invoices</div>
        <div class="table-card" style="overflow-x:auto">
          <table class="data-table" style="min-width:980px">
            <thead><tr><th>#</th><th>Invoice No.</th><th>Invoice Date</th><th>Supplier</th><th style="text-align:right">Qty (Kg)</th><th style="text-align:right">Net Amount (₹)</th><th>Payment Status</th><th>Status</th><th>Payment Type</th><th>Action</th></tr></thead>
            <tbody id="purchasesTbody"></tbody>
          </table>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;flex-wrap:wrap;gap:8px">
          <div style="font-size:12px;color:var(--muted)" id="purInfo"></div>
          <div style="display:flex;gap:5px" id="pl-pagination"></div>
        </div>
      </div>

      <div id="pl-note-banner" style="margin-top:14px;background:#E8F5E9;border:1px solid #A5D6A7;border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;gap:12px">
        <div style="font-size:12px;color:#1B5E20"><i class="fas fa-circle-info"></i> <b>Note:</b> You can view, print, download or share purchase invoices using the action buttons.</div>
        <button style="background:none;border:none;color:#1B5E20;cursor:pointer;font-size:14px" onclick="document.getElementById('pl-note-banner').style.display='none'"><i class="fas fa-times"></i></button>
      </div>

    <!-- Purchase Details Modal (relocated from suppliers section — was
         positioned there in the SPA by document order only) -->
    <div class="modal-overlay" id="modal-purchase-details">
      <div class="modal modal-xl" style="overflow:hidden;position:relative">
        <button class="modal-close" onclick="closeModal('modal-purchase-details')" style="position:absolute;top:14px;right:14px;z-index:2;background:rgba(255,255,255,.18);color:#fff"><i class="fas fa-times"></i></button>
        <div id="pd-head" style="position:relative;padding:26px 24px 20px;background:linear-gradient(135deg,var(--teal) 0%,#00695C 100%);flex-shrink:0"></div>
        <div class="modal-body" id="pd-body" style="padding:20px 22px;background:var(--bg)"></div>
        <div class="modal-footer" id="pd-foot"></div>
      </div>
    </div>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/pages/shared-data.js"></script>
<script src="/assets/js/pages/edit-approval-shared.js"></script>
<script src="/assets/js/pages/purchases.js"></script>
