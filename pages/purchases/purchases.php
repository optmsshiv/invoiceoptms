<?php
// ================================================================
//  pages/purchases.php
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.purchases');

$user = currentUser();

$activePage  = 'purchases';
$pageTitle   = 'Purchases';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/purchases.js'];

include __DIR__ . '/../../includes/layout_header.php';
?>
      <div class="page-toolbar">
        <input type="text" class="table-search" placeholder="Search purchases…" oninput="filterPurchases(this.value)" id="purchaseSearch">
        <select class="table-filter" onchange="renderPurchases()" id="purStatusFilter">
          <option value="">All Status</option>
          <option>Pending</option><option>Received</option><option>Partial</option><option>Paid</option>
        </select>
        <div style="flex:1"></div>
        <span id="purCountInfo" style="font-size:12px;color:var(--muted);margin-right:8px"></span>
        <button class="btn btn-primary" onclick="window.location.href='/pages/purchases/purchase-new.php'"><i class="fas fa-plus"></i> Add Purchase</button>
      </div>
      <div class="table-card">
        <table class="data-table">
          <thead><tr><th>PO No.</th><th>Supplier</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody id="purchasesTbody"></tbody>
        </table>
        <div class="table-footer">
          <div class="tf-info" id="purInfo"></div>
          <div class="pagination" id="purPagination"></div>
        </div>
      </div>

      </div>
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
