<?php
// ================================================================
//  pages/aging.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.aging');
$user = currentUser();

$activePage = 'aging';
$pageTitle  = 'Aging Report';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;box-shadow:var(--shadow)">
        <span style="font-size:12px;font-weight:700;color:var(--muted)"><i class="fas fa-hourglass-half" style="color:var(--teal)"></i> Invoice Aging</span>
        <select id="aging-status-filter" class="table-filter" onchange="renderAgingReport()">
          <option value="">All Unpaid</option>
          <option value="Pending">Pending</option>
          <option value="Overdue">Overdue</option>
          <option value="Partial">Partial</option>
        </select>
        <button class="btn btn-outline" style="margin-left:auto;font-size:12px" onclick="exportAgingCSV()"><i class="fas fa-download"></i> Export CSV</button>
      </div>
      <!-- Bucket summary cards -->
      <div id="aging-buckets" style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:20px"></div>
      <!-- Aging table -->
      <div class="table-card">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
          <span style="font-weight:700;font-size:14px">Outstanding Invoices</span>
          <input type="text" class="table-search" placeholder="Search…" oninput="filterAgingTable(this.value)" id="aging-search" style="max-width:200px">
        </div>
        <table class="data-table"><thead><tr>
          <th>Invoice #</th><th>Client</th><th>Service</th><th>Issue Date</th><th>Due Date</th><th>Days Overdue</th><th>Total</th><th>Received</th><th>Outstanding</th><th>Bucket</th><th>Action</th>
        </tr></thead><tbody id="aging-tbody"></tbody></table>
        <div class="table-footer"><div class="tf-info" id="aging-info"></div></div>
      </div>
    

    <!--


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/shared-data.js"></script>
<script src="/assets/js/wa-shared.js"></script>
<script src="/assets/js/invoice-render-shared.js"></script>
<script src="/assets/js/pages/aging.js"></script>
