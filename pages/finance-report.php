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
$pageTitle  = 'Finance Report';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px">
        <div>
          <div style="font-size:20px;font-weight:800;color:var(--text)">Finance Report</div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px">Dashboard &gt; Reports &gt; Finance Report</div>
        </div>
        <div style="display:flex;gap:8px">
          <button class="btn btn-outline" onclick="toast('📤 Export — coming soon','info')"><i class="fas fa-download"></i> Export</button>
        </div>
      </div>

      <div class="pne-card" style="margin-top:16px">
        <div class="pne-grid4">
          <div class="field"><label>Report Type</label><select id="fr-type"><option>Finance Summary</option></select></div>
          <div class="field"><label>Date Range</label>
            <div style="display:flex;gap:6px">
              <input type="date" id="fr-from" class="table-search" style="max-width:none;flex:1">
              <input type="date" id="fr-to" class="table-search" style="max-width:none;flex:1">
              <button class="btn btn-outline" style="white-space:nowrap" title="See totals across everything, not just the current range" onclick="setFRAllTime()">All Time</button>
            </div>
          </div>
          <div class="field"><label>Warehouse</label><select id="fr-warehouse"><option value="">All Warehouses</option><option>Main Warehouse</option><option>Secondary Warehouse</option></select></div>
          <div class="field" style="display:flex;align-items:flex-end;gap:8px">
            <button class="btn pne-btn-save" style="flex:1" onclick="renderFinanceReport()">Apply Filter</button>
            <button class="btn btn-outline" onclick="resetFinanceFilter()"><i class="fas fa-rotate-left"></i></button>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-top:16px" class="ps-stats-row">
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E3F2FD;color:#1976D2;width:34px;height:34px"><i class="fas fa-chart-line"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Sales (₹)</div>
          <div style="font-size:17px;font-weight:800" id="fr-stat-sales">₹0.00</div>
          <div style="font-size:10.5px;color:#00897B" id="fr-chg-sales"></div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#F3E8FF;color:#6A4C93;width:34px;height:34px"><i class="fas fa-cart-shopping"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Purchase (₹)</div>
          <div style="font-size:17px;font-weight:800" id="fr-stat-purchase">₹0.00</div>
          <div style="font-size:10.5px;color:#00897B" id="fr-chg-purchase"></div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E8F5E9;color:#2E7D32;width:34px;height:34px"><i class="fas fa-wallet"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Collections (₹)</div>
          <div style="font-size:17px;font-weight:800" id="fr-stat-collections">₹0.00</div>
          <div style="font-size:10.5px;color:#00897B" id="fr-chg-collections"></div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFF3E0;color:#E65100;width:34px;height:34px"><i class="fas fa-file-invoice-dollar"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Payments (₹)</div>
          <div style="font-size:17px;font-weight:800" id="fr-stat-payments">₹0.00</div>
          <div style="font-size:10.5px;color:#00897B" id="fr-chg-payments"></div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFEBEE;color:#E53935;width:34px;height:34px"><i class="fas fa-wallet"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Business Expenses (₹)</div>
          <div style="font-size:17px;font-weight:800;color:#E53935" id="fr-stat-expenses">₹0.00</div>
          <div style="font-size:10.5px;color:var(--muted)" id="fr-chg-expenses"></div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E8F5E9;color:#00897B;width:34px;height:34px"><i class="fas fa-piggy-bank"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Net Profit (₹) <span title="Sales − Purchase − Expenses" style="cursor:help">ⓘ</span></div>
          <div style="font-size:17px;font-weight:800" id="fr-stat-profit">₹0.00</div>
          <div style="font-size:10.5px;color:#00897B" id="fr-chg-profit"></div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:16px;margin-top:16px;align-items:start" class="fr-mid-grid">
        <div class="pne-card">
          <div class="pne-card-head">Income vs Expense Trend</div>
          <canvas id="fr-trend-chart" height="230"></canvas>
        </div>
        <div class="pne-card">
          <div class="pne-card-head">Income Distribution (₹)</div>
          <canvas id="fr-income-chart" height="230"></canvas>
        </div>
        <div class="pne-card">
          <div class="pne-card-head">Cash Flow Summary</div>
          <div class="pne-kv"><span>Total Collections</span><strong style="color:#00897B" id="fr-cf-collections">₹0.00</strong></div>
          <div class="pne-kv"><span>Total Payments</span><strong style="color:#E53935" id="fr-cf-payments">₹0.00</strong></div>
          <div class="pne-kv" style="border-top:1px dashed var(--border);margin-top:8px;padding-top:10px"><span>Net Cash Flow</span><strong id="fr-cf-net" style="font-size:15px">₹0.00</strong></div>
          <div style="font-size:10.5px;color:var(--muted);margin-top:10px;line-height:1.5"><i class="fas fa-circle-info"></i> Opening/Closing balances need bank &amp; cash account tracking, not yet built — showing period cash movement only.</div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-top:16px;align-items:start" class="fr-mid-grid">
        <div class="pne-card">
          <div class="pne-card-head">Top Income Heads</div>
          <table class="data-table" style="font-size:12px">
            <thead><tr><th>#</th><th>Income Head</th><th>Amount (₹)</th><th>%</th></tr></thead>
            <tbody id="fr-income-tbody"></tbody>
          </table>
        </div>
        <div class="pne-card">
          <div class="pne-card-head">Top Expense Heads</div>
          <table class="data-table" style="font-size:12px">
            <thead><tr><th>#</th><th>Expense Head</th><th>Amount (₹)</th><th>%</th></tr></thead>
            <tbody id="fr-expense-tbody"></tbody>
          </table>
        </div>
        <div class="pne-card">
          <div class="pne-card-head">Expense Summary (₹)</div>
          <canvas id="fr-expense-chart" height="220"></canvas>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr;gap:16px;margin-top:16px">
        <div class="pne-card">
          <div class="pne-card-head">Payment Mode Summary</div>
          <div id="fr-paymode-list" style="display:flex;flex-direction:column;gap:10px"></div>
        </div>
      </div>

      <div class="pne-card" style="margin-top:16px">
        <div class="pne-card-head pne-head-blue"><i class="fas fa-weight-hanging"></i> Trade Summary — Quantity &amp; Dhalta Report</div>
        <div id="fr-trade-summary"><div style="color:var(--muted);font-size:13px;padding:20px;text-align:center"><i class="fas fa-spinner fa-spin"></i> Loading trade summary…</div></div>
      </div>

      <div style="padding:14px 0 30px;font-size:11px;color:var(--muted)"><i class="fas fa-circle-info"></i> All amounts are in INR (₹)</div>
    

    <!--


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/shared-data.js"></script>
<script src="/assets/js/pages/finance-report.js"></script>
