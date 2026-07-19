<?php
// ================================================================
//  pages/sales/sales.php
//  Sales list — only relevant for product/trading businesses.
//  Gated server-side by business_type (settings), in addition to
//  the usual menu.sales permission.
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.sales');

$user = currentUser();

// ── Business-type guard ────────────────────────────────────────
// Sales only makes sense for 'product' or 'both' business types.
// A pure-service business gets redirected back to the dashboard.
$settingsRows = [];
try {
    $rows = getDB()->query('SELECT `key`, value FROM settings')->fetchAll();
    foreach ($rows as $r) $settingsRows[$r['key']] = $r['value'];
} catch (Exception $e) {
    error_log('Settings load error: ' . $e->getMessage());
}
$businessType = $settingsRows['business_type'] ?? 'both';
if (!in_array($businessType, ['product', 'both'], true)) {
    header('Location: /dashboard.php');
    exit;
}

$activePage  = 'sales';
$pageTitle   = 'Sales';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/sales.js'];

include __DIR__ . '/../../includes/layout_header.php';
?>
      <div class="page-toolbar">
        <input type="text" class="table-search" placeholder="Search sales…" oninput="filterSales(this.value)" id="saleSearch">
        <select class="table-filter" onchange="renderSales()" id="saleStatusFilter">
          <option value="">All Status</option>
          <option>Pending</option><option>Partial</option><option>Paid</option>
        </select>
        <div style="flex:1"></div>
        <span id="saleCountInfo" style="font-size:12px;color:var(--muted);margin-right:8px"></span>
        <button class="btn btn-primary" onclick="goToNewSale()"><i class="fas fa-plus"></i> Add Sale</button>
      </div>
      <div class="table-card">
        <table class="data-table">
          <thead><tr><th>Invoice No.</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody id="salesTbody"></tbody>
        </table>
        <div class="table-footer">
          <div class="tf-info" id="saleInfo"></div>
        </div>
      </div>
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
