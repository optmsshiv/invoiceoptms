<?php
// ================================================================
//  pages/payments.php
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.payments');

$user = currentUser();

$activePage  = 'payments';
$pageTitle   = 'Payments';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/payments.js'];

include __DIR__ . '/../../includes/layout_header.php';
?>
      <!-- Summary cards -->
      <div class="dash-stats-row" style="grid-template-columns:repeat(4,1fr);margin-bottom:18px" id="pmtSummary"></div>
      <!-- Toolbar -->
      <div class="page-toolbar" style="flex-wrap:wrap;gap:8px;margin-bottom:14px">
        <input type="text" class="table-search" placeholder="Search payments…" oninput="filterPayments(this.value)" id="pmtSearch">
        <select class="table-filter" onchange="filterPaymentsByMethod(this.value)" id="pmtMethodFilter">
          <option value="">All Methods</option>
          <option>Bank Transfer (NEFT/RTGS)</option>
          <option>UPI (GPay/PhonePe/Paytm)</option>
          <option>Cash</option><option>Cheque</option><option>Credit Card</option>
        </select>
        <button class="cf-btn" onclick="setPmtRange('today')" id="pmtToday">Today</button>
        <button class="cf-btn" onclick="setPmtRange('week')" id="pmtWeek">This Week</button>
        <button class="cf-btn" onclick="setPmtRange('month')" id="pmtMonth">This Month</button>
        <input type="date" class="table-filter" id="pmtFrom" onchange="filterPmtByDate()" style="max-width:130px">
        <input type="date" class="table-filter" id="pmtTo" onchange="filterPmtByDate()" style="max-width:130px">
        <div style="flex:1"></div>
        <button class="btn btn-outline" onclick="exportPmtCSV()"><i class="fas fa-download"></i> Export</button>
      </div>
      <!-- Table -->
      <div class="table-card">
        <table class="data-table">
          <thead><tr>
            <th>Date</th><th>Invoice #</th><th>Client</th>
            <th>Method</th><th>Txn ID</th><th>Amount</th><th>Status</th><th>Action</th>
          </tr></thead>
          <tbody id="paymentsTbody"></tbody>
        </table>
        <div style="padding:6px 14px 2px;font-size:11px;color:var(--muted);display:flex;align-items:center;gap:6px">
          <i class="fas fa-layer-group" style="font-size:10px"></i>
          <span>Rows sharing the same invoice number share a colour chip. <i class="fas fa-layer-group" style="font-size:9px"></i> icon = multiple payments (partial instalments).</span>
        </div>
        <div class="table-footer">
          <div class="tf-info" id="pmtInfo"></div>
          <div class="pagination" id="pmtPagination"></div>
        </div>
      </div>

      <!-- Payment Receipt modal — payments-page-specific -->
      <div class="modal-overlay" id="modal-receipt">
        <div class="modal modal-md">
          <div class="modal-header"><span>Payment Receipt</span><button class="modal-close" onclick="closeModal('modal-receipt')"><i class="fas fa-times"></i></button></div>
          <div class="modal-body" id="receiptBody" style="padding:24px;max-height:70vh;overflow-y:auto"></div>
          <div class="modal-footer">
            <button class="btn btn-primary" onclick="printReceiptModal()"><i class="fas fa-print"></i> Print Receipt</button>
            <button class="btn btn-outline" onclick="closeModal('modal-receipt')">Close</button>
          </div>
        </div>
      </div>
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
