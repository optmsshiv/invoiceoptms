<?php
// ================================================================
//  pages/reports.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.reports');
$user = currentUser();

$activePage = 'reports';
$pageTitle  = 'Reports';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <!-- Date range filter bar -->
      <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;box-shadow:var(--shadow)">
        <strong style="font-size:12px;color:var(--muted);margin-right:4px"><i class="fas fa-calendar-alt" style="color:var(--teal)"></i> Period:</strong>
        <button class="cf-btn" onclick="setRptRange('today')" id="rpt-today">Today</button>
        <button class="cf-btn active" onclick="setRptRange('month')" id="rpt-month">This Month</button>
        <button class="cf-btn" onclick="setRptRange('quarter')" id="rpt-quarter">Quarter</button>
        <button class="cf-btn" onclick="setRptRange('year')" id="rpt-year">This Year</button>
        <button class="cf-btn" onclick="setRptRange('all')" id="rpt-all">All Time</button>
        <input type="date" class="table-filter" id="rptFrom" onchange="applyRptFilter()" style="max-width:130px;margin-left:8px">
        <span style="color:var(--muted);font-size:12px">–</span>
        <input type="date" class="table-filter" id="rptTo" onchange="applyRptFilter()" style="max-width:130px">
        <button class="btn btn-outline" style="margin-left:auto;font-size:12px" onclick="exportRptCSV()"><i class="fas fa-download"></i> Export</button>
      </div>
      <!-- Dynamic stat cards -->
      <div class="dash-stats-row" id="rptStatCards" style="grid-template-columns:repeat(5,1fr);margin-bottom:18px"></div>
      <!-- Charts row -->
      <div class="dash-row-2" style="margin-bottom:18px">
        <div class="dash-card" style="flex:1">
          <div class="card-header"><span class="card-title">Revenue by Service Type</span></div>
          <div class="reports-chart-wrap-lg"><canvas id="serviceChart"></canvas></div>
        </div>
        <div class="dash-card" style="flex:1">
          <div class="card-header"><span class="card-title">Monthly Trend</span></div>
          <div class="reports-chart-wrap-lg"><canvas id="compareChart"></canvas></div>
        </div>
      </div>
      <!-- Transactions table -->
      <div class="table-card">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid var(--border)">
          <span style="font-weight:700;font-size:14px">Transaction Details</span>
          <input type="text" class="table-search" placeholder="Search…" oninput="filterRptTable(this.value)" style="max-width:200px">
        </div>
        <table class="data-table">
          <thead><tr>
            <th>Invoice #</th><th>Client</th><th>Service</th>
            <th>Issue Date</th><th>Amount</th><th>Status</th>
          </tr></thead>
          <tbody id="rptTbody"></tbody>
        </table>
        <div class="table-footer">
          <div class="tf-info" id="rptInfo"></div>
          <div class="pagination" id="rptPagination"></div>
        </div>
      </div>
    

    <!--


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/shared-data.js"></script>
<script src="/assets/js/pages/reports.js"></script>
