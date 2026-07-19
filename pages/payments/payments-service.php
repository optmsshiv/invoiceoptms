<?php
// ================================================================
//  pages/payments/payments-service.php
//  Simple payments list for service/both business types (everything
//  except business_type='product' exactly, which gets the richer
//  payments-product.php instead).
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.payments');

$user = currentUser();

$settingsRows = [];
try {
    $rows = getDB()->query('SELECT `key`, value FROM settings')->fetchAll();
    foreach ($rows as $r) $settingsRows[$r['key']] = $r['value'];
} catch (Exception $e) {
    error_log('Settings load error: ' . $e->getMessage());
}
if (($settingsRows['business_type'] ?? 'both') === 'product') {
    header('Location: /pages/payments/payments-product.php');
    exit;
}

$activePage  = 'payments';
$pageTitle   = 'Payments';
$pageScripts = [
    '/assets/js/shared-data.js',
    '/assets/js/payment-receipt-shared.js',
    '/assets/js/payments-service.js',
];

include __DIR__ . '/../../includes/layout_header.php';
?>
    <div id="page-payments-service" class="page">
      <!-- Summary cards -->
      <div class="dash-stats-row" style="grid-template-columns:repeat(4,1fr);margin-bottom:18px" id="pmtsSummary"></div>
      <!-- Toolbar -->
      <div class="page-toolbar" style="flex-wrap:wrap;gap:8px;margin-bottom:14px">
        <input type="text" class="table-search" placeholder="Search payments…" oninput="filterPaymentsSvc(this.value)" id="pmtsSearch">
        <select class="table-filter" onchange="filterPaymentsSvcByMethod(this.value)" id="pmtsMethodFilter">
          <option value="">All Methods</option>
          <option>Bank Transfer (NEFT/RTGS)</option>
          <option>UPI (GPay/PhonePe/Paytm)</option>
          <option>Cash</option><option>Cheque</option><option>Credit Card</option>
        </select>
        <button class="cf-btn" onclick="setPmtsSvcRange('today')" id="pmtsToday">Today</button>
        <button class="cf-btn" onclick="setPmtsSvcRange('week')" id="pmtsWeek">This Week</button>
        <button class="cf-btn" onclick="setPmtsSvcRange('month')" id="pmtsMonth">This Month</button>
        <input type="date" class="table-filter" id="pmtsFrom" onchange="filterPmtsSvcByDate()" style="max-width:130px">
        <input type="date" class="table-filter" id="pmtsTo" onchange="filterPmtsSvcByDate()" style="max-width:130px">
        <div style="flex:1"></div>
        <button class="btn btn-outline" onclick="exportPmtsSvcCSV()"><i class="fas fa-download"></i> Export</button>
      </div>
      <!-- Table -->
      <div class="table-card">
        <table class="data-table">
          <thead><tr>
            <th>Date</th><th>Invoice #</th><th>Client</th>
            <th>Method</th><th>Txn ID</th><th>Amount</th><th>Status</th><th>Action</th>
          </tr></thead>
          <tbody id="paymentsSvcTbody"></tbody>
        </table>
        <div style="padding:6px 14px 2px;font-size:11px;color:var(--muted);display:flex;align-items:center;gap:6px">
          <i class="fas fa-layer-group" style="font-size:10px"></i>
          <span>Rows sharing the same invoice number share a colour chip. <i class="fas fa-layer-group" style="font-size:9px"></i> icon = multiple payments (partial instalments).</span>
        </div>
        <div class="table-footer">
          <div class="tf-info" id="pmtsInfo"></div>
          <div class="pagination" id="pmtsPagination"></div>
        </div>
      </div>
    </div>

    <!-- ─────────── REPORTS ─────────── -->
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
