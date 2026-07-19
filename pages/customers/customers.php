<?php
// ================================================================
//  pages/customers/customers.php
//  Customer list — separate from Clients, used by the Sales/trading
//  side of the business (product/both business types).
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
// NOTE: the SPA has no dedicated menu.customers permission key — the
// Customers nav item is purely client-side gated by business_type,
// piggybacking on the same permission as Sales (which it's tightly
// coupled to: customers are used by the Sale entry customer picker).
// Inventing a new menu.customers catalog key here would recreate the
// exact "Access Denied for everyone" bug from missing catalog entries
// — see config/migrations/003_add_missing_menu_keys_master.sql.
requirePermission('menu.sales');

$user = currentUser();

// Customers is 'product' business type only — narrower than Sales,
// which also shows for 'both'. Matches the SPA's exact check
// (STATE.settings.businessType === 'product') rather than extending
// it, since that's what your source code actually does. Worth a
// second look if you'd prefer this to match Sales' broader gating.
$settingsRows = [];
try {
    $rows = getDB()->query('SELECT `key`, value FROM settings')->fetchAll();
    foreach ($rows as $r) $settingsRows[$r['key']] = $r['value'];
} catch (Exception $e) {
    error_log('Settings load error: ' . $e->getMessage());
}
if (($settingsRows['business_type'] ?? 'both') !== 'product') {
    header('Location: /dashboard.php');
    exit;
}

$activePage  = 'customers';
$pageTitle   = 'All Customers';
$pageScripts = [
    '/assets/js/shared-data.js',
    '/assets/js/wa-shared.js',
    '/assets/js/edit-approval-shared.js',
    '/assets/js/customers.js',
];

include __DIR__ . '/../../includes/layout_header.php';
?>
    <div id="page-customers-list" class="page">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px">
        <div>
          <div style="font-size:20px;font-weight:800;color:var(--text)">All Customers</div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px">Dashboard &gt; Customers &gt; All Customers</div>
        </div>
        <div style="display:flex;gap:8px">
          <button class="btn btn-outline" onclick="toast('🔧 Advanced filters — coming soon','info')"><i class="fas fa-filter"></i> Filters</button>
          <button class="btn btn-outline" onclick="exportCustomersCsv()"><i class="fas fa-download"></i> Export</button>
          <button class="btn btn-primary" onclick="goToNewCustomerPage()"><i class="fas fa-plus"></i> Add New Customer</button>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px" class="ps-stats-row">
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E8F5E9;color:#2E7D32;width:36px;height:36px"><i class="fas fa-users"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Customers</div>
          <div style="font-size:18px;font-weight:800" id="cust-stat-total">0</div>
          <div style="font-size:10.5px;color:var(--muted);margin-top:6px">Active Customers</div>
          <div style="font-size:13px;font-weight:700;color:#00897B" id="cust-stat-active">0</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#F3E8FF;color:#6A4C93;width:36px;height:36px"><i class="fas fa-building-columns"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Credit Limit</div>
          <div style="font-size:18px;font-weight:800" id="cust-stat-creditlimit">₹0.00</div>
          <div style="font-size:10.5px;color:var(--muted);margin-top:6px">Available Credit</div>
          <div style="font-size:13px;font-weight:700;color:#2E7D32" id="cust-stat-available">₹0.00</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFF3E0;color:#E65100;width:36px;height:36px"><i class="fas fa-file-invoice-dollar"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Outstanding</div>
          <div style="font-size:18px;font-weight:800" id="cust-stat-outstanding">₹0.00</div>
          <div style="font-size:10.5px;color:var(--muted);margin-top:6px">Overdue Amount</div>
          <div style="font-size:13px;font-weight:700;color:#E53935" id="cust-stat-overdue">₹0.00</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E3F2FD;color:#1976D2;width:36px;height:36px"><i class="fas fa-building"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Sales (This Month)</div>
          <div style="font-size:18px;font-weight:800" id="cust-stat-monthsales">₹0.00</div>
          <div style="font-size:10.5px;color:var(--muted);margin-top:6px">This Month Collections</div>
          <div style="font-size:13px;font-weight:700;color:#00897B" id="cust-stat-monthcoll">₹0.00</div>
        </div>
      </div>

      <div class="pne-card">
        <div class="pne-card-head" style="margin-bottom:14px">Customers List</div>
        <div class="pne-grid5" style="align-items:end">
          <div class="field" style="grid-column:span 2"><label>Search</label><input class="table-search" style="max-width:none" id="custSearch" placeholder="Search by name, code, phone, email…" oninput="filterCustomersList(this.value)"></div>
          <div class="field"><label>Customer Type</label>
            <select class="table-filter" style="max-width:none" onchange="renderCustomersList()" id="custTypeFilter">
              <option value="">All Types</option>
              <option>Domestic</option><option>Exporter</option><option>Wholesaler</option><option>Retailer</option>
            </select>
          </div>
          <div class="field"><label>Status</label>
            <select class="table-filter" style="max-width:none" onchange="renderCustomersList()" id="custStatusFilterList">
              <option value="">All Status</option><option value="active">Active</option><option value="archived">Inactive</option>
            </select>
          </div>
          <div class="field"><label>State</label>
            <select class="table-filter" style="max-width:none" onchange="renderCustomersList()" id="custStateFilter"><option value="">All States</option></select>
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:-6px;margin-bottom:12px">
          <button class="btn btn-outline" onclick="resetCustomersFilter()"><i class="fas fa-rotate-left"></i> Reset</button>
          <button class="btn pne-btn-save" onclick="renderCustomersList()"><i class="fas fa-filter"></i> Apply Filters</button>
        </div>

        <div style="overflow-x:auto">
          <table class="data-table ps-stock-table" style="min-width:1100px;table-layout:fixed">
            <colgroup>
              <col style="width:30px"><col style="width:95px"><col style="width:150px"><col style="width:85px"><col style="width:110px">
              <col style="width:150px"><col style="width:80px"><col style="width:95px"><col style="width:95px"><col style="width:70px"><col style="width:70px">
            </colgroup>
            <thead><tr>
              <th>#</th><th>Customer Code</th><th>Customer Name</th><th>Type</th><th>Phone</th>
              <th>Email</th><th>State</th><th>Credit Limit (₹)</th><th>Outstanding (₹)</th><th>Status</th><th>Action</th>
            </tr></thead>
            <tbody id="custListTbody"></tbody>
          </table>
        </div>
        <div class="table-footer"><div class="tf-info" id="custListInfo"></div><div class="pagination" id="custListPagination"></div></div>
      </div>
      <div style="padding:14px 0 30px;font-size:11px;color:var(--muted)"><i class="fas fa-circle-info"></i> Click on a customer to view detailed information, ledger, transactions and more.</div>
    </div>
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
