<?php
// ================================================================
//  pages/expenses.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.expenses');
$user = currentUser();

$activePage = 'expenses';
$pageTitle  = 'Expenses';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div class="page-toolbar">
        <div class="toolbar-left">
          <input type="text" class="table-search" placeholder="Search expenses…" oninput="filterExpenses(this.value)">
          <select class="table-filter" onchange="filterExpensesCat(this.value)" id="exp-cat-filter">
            <option value="">All Categories</option>
          </select>
          <select class="table-filter" onchange="filterExpensesMonth(this.value)" id="exp-month-filter">
            <option value="">All Time</option>
          </select>
        </div>
        <div class="toolbar-right">
          <button class="btn btn-outline" onclick="exportExpensesCSV()"><i class="fas fa-download"></i> Export</button>
          <button class="btn btn-primary" onclick="openAddExpenseModal()"><i class="fas fa-plus"></i> Add Expense</button>
        </div>
      </div>
      <!-- Summary cards -->
      <div id="exp-summary-cards" style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px"></div>
      <!-- Mixed chart: bars = monthly total, stacked per category -->
      <div class="pne-card" style="margin-bottom:18px" id="exp-charts-row">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px">
          <div style="font-size:13px;font-weight:700">Monthly Amount &amp; Category Breakdown</div>
          <div id="exp-mix-legend" style="display:flex;gap:10px;font-size:11px;flex-wrap:wrap"></div>
        </div>
        <div style="height:250px;position:relative"><canvas id="exp-mix-chart"></canvas></div>
      </div>
      <!-- Expense table -->
      <div class="table-card">
        <table class="data-table"><thead><tr>
          <th>Date</th><th>Category</th><th>Vendor / Description</th><th>Payment Method</th><th>Amount</th><th>Notes</th><th>Action</th>
        </tr></thead><tbody id="exp-tbody"></tbody></table>
        <div class="table-footer"><div class="tf-info" id="exp-info"></div><div class="pagination" id="exp-pagination"></div></div>
      </div>
    

    <!--

    <!-- Add/Edit Expense Modal (relocated from SPA global-modals tail section) -->
<div class="modal-overlay" id="modal-expense">
  <div class="modal" style="max-width:500px;max-height:90vh;display:flex;flex-direction:column">
    <div class="modal-header" style="padding:14px 20px;flex-shrink:0">
      <div style="display:flex;align-items:center;gap:9px">
        <div style="width:30px;height:30px;border-radius:8px;background:#fff3e0;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fas fa-wallet" style="color:#E65100;font-size:13px"></i>
        </div>
        <div style="font-size:14px;font-weight:700;color:var(--text)" id="exp-modal-title">Add Expense</div>
      </div>
      <button class="modal-close" onclick="closeModal('modal-expense')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" style="overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column;gap:10px">
      <input type="hidden" id="exp-edit-id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="field" style="margin:0"><label>Date *</label><input type="date" id="exp-date" style="width:100%"></div>
        <div class="field" style="margin:0"><label>Amount (₹) *</label><input type="number" id="exp-amount" placeholder="0.00" style="width:100%"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="field" style="margin:0"><label>Category *</label>
          <select id="exp-category" style="width:100%">
            <option value="">— Select —</option>
          </select>
        </div>
        <div class="field" style="margin:0"><label>Payment Method</label>
          <select id="exp-method" style="width:100%">
            <option>UPI</option><option>Bank Transfer</option><option>Cash</option>
            <option>Credit Card</option><option>Cheque</option>
          </select>
        </div>
      </div>
      <div class="field" style="margin:0"><label>Vendor / Description *</label>
        <input id="exp-vendor" placeholder="e.g. AWS, Zomato, Office Rent" style="width:100%">
      </div>
      <div class="field" style="margin:0"><label>Notes <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
        <input id="exp-notes" placeholder="Additional details…" style="width:100%">
      </div>
    </div>
    <div class="modal-footer" style="padding:12px 20px;flex-shrink:0">
      <button class="btn btn-success" onclick="saveExpense()" style="flex:1"><i class="fas fa-save"></i> Save Expense</button>
      <button class="btn btn-outline" onclick="closeModal('modal-expense')">Cancel</button>
    </div>
  </div>
</div>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/pages/shared-data.js"></script>
<script src="/assets/js/pages/expenses.js"></script>
