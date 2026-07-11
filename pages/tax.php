<?php
// ================================================================
//  pages/tax.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requirePermission('menu.tax');

$user = currentUser();

$activePage  = 'tax';
$pageTitle   = 'Tax Summary';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/tax.js'];

include __DIR__ . '/../includes/layout_header.php';
?>
      <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;box-shadow:var(--shadow)">
        <span style="font-size:12px;font-weight:700;color:var(--muted)"><i class="fas fa-landmark" style="color:var(--teal)"></i> Period:</span>
        <button class="cf-btn active" onclick="setTaxRange('year')" id="tax-btn-year">This Year</button>
        <button class="cf-btn" onclick="setTaxRange('quarter')" id="tax-btn-quarter">This Quarter</button>
        <button class="cf-btn" onclick="setTaxRange('month')" id="tax-btn-month">This Month</button>
        <button class="cf-btn" onclick="setTaxRange('all')" id="tax-btn-all">All Time</button>
        <input type="date" class="table-filter" id="tax-from" onchange="applyTaxFilter()" style="max-width:130px;margin-left:8px">
        <span style="color:var(--muted);font-size:12px">–</span>
        <input type="date" class="table-filter" id="tax-to" onchange="applyTaxFilter()" style="max-width:130px">
        <button class="btn btn-outline" style="margin-left:auto;font-size:12px" onclick="exportTaxCSV()"><i class="fas fa-download"></i> Export CSV</button>
      </div>
      <div id="tax-stat-cards" style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px"></div>
      <div style="display:flex;gap:16px;margin-bottom:20px">
        <div class="dash-card" style="flex:1">
          <div class="card-header"><span class="card-title">Monthly GST Collected</span></div>
          <div style="position:relative;height:220px"><canvas id="taxMonthlyChart"></canvas></div>
        </div>
        <div class="dash-card" style="flex:0 0 280px">
          <div class="card-header"><span class="card-title">GST Rate Breakdown</span></div>
          <div style="position:relative;height:220px"><canvas id="taxRateChart"></canvas></div>
        </div>
      </div>
      <div class="table-card" style="margin-bottom:18px">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border)"><span style="font-weight:700;font-size:14px">GST Rate-wise Summary</span></div>
        <table class="data-table"><thead><tr>
          <th>GST Rate</th><th>Taxable Amount</th><th>CGST (½ rate)</th><th>SGST (½ rate)</th><th>IGST</th><th>Total GST</th><th>Invoice Count</th>
        </tr></thead><tbody id="tax-rate-tbody"></tbody></table>
      </div>
      <div class="table-card">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border)"><span style="font-weight:700;font-size:14px">Month-wise GST Detail</span></div>
        <table class="data-table"><thead><tr>
          <th>Month</th><th>Invoices</th><th>Gross Revenue</th><th>Taxable Value</th><th>CGST</th><th>SGST</th><th>Total GST</th><th>Status</th>
        </tr></thead><tbody id="tax-monthly-tbody"></tbody></table>
      </div>
<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
