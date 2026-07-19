<?php
// ================================================================
//  pages/invoices.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.invoices');
$user = currentUser();

$activePage = 'invoices';
$pageTitle  = 'Invoices';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <!-- Bulk action bar (shown when rows are selected) -->
      <div id="bulkBar" style="display:none;align-items:center;gap:10px;background:var(--teal-bg);border:1.5px solid var(--teal);border-radius:10px;padding:10px 16px;margin-bottom:12px">
        <span id="bulkCount" style="font-size:13px;font-weight:700;color:var(--teal)">0 selected</span>
        <button class="btn btn-outline" style="font-size:12px;padding:5px 12px;color:#25D366;border-color:#25D366" onclick="bulkSendWA()"><i class="fab fa-whatsapp"></i> Send WhatsApp</button>
        <button class="btn btn-outline" style="font-size:12px;padding:5px 12px" onclick="bulkExportCSV()"><i class="fas fa-download"></i> Export Selected</button>
        <button class="btn btn-outline" style="font-size:12px;padding:5px 12px;color:var(--red);border-color:var(--red)" onclick="bulkDelete()"><i class="fas fa-trash"></i> Delete Selected</button>
        <button onclick="clearBulkSelection()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:var(--muted);font-size:18px" title="Clear selection">×</button>
      </div>
      <div class="page-toolbar">
        <div class="toolbar-left">
          <input type="text" class="table-search" placeholder="Search invoices…" oninput="filterInvoices(this.value)" id="invSearch">
          <select class="table-filter" onchange="filterByStatus(this.value)" id="statusFilter">
            <option value="">All Status</option>
            <option>Paid</option><option>Pending</option><option>Partial</option><option>Overdue</option><option>Draft</option><option>Estimate</option><option>Cancelled</option>
          </select>
          <select class="table-filter" onchange="filterByClient(this.value)" id="clientFilter">
            <option value="">All Clients</option>
          </select>
          <select class="table-filter" onchange="filterByService(this.value)" id="serviceFilter">
            <option value="">All Services</option>
            <option>Website Development</option><option>School ERP</option>
            <option>Mobile App</option><option>Maintenance</option>
            <option>Consultation</option><option>Domain & Hosting</option>
          </select>
          <input type="date" class="table-filter" id="dateFrom" onchange="filterByDate()" placeholder="From">
          <input type="date" class="table-filter" id="dateTo" onchange="filterByDate()" placeholder="To">
        </div>
        <div class="toolbar-right">
          <button class="btn btn-outline" id="inv-refresh-btn" onclick="refreshInvoices()" title="Refresh invoices"><i class="fas fa-sync-alt"></i> Refresh</button>
          <button class="btn btn-outline" onclick="exportCSV()"><i class="fas fa-download"></i> Export CSV</button>
          <a class="btn btn-primary" href="/pages/create.php"><i class="fas fa-plus"></i> New Invoice</a>
        </div>
      </div>

      <div class="table-card">
        <table class="data-table" id="invoicesTable">
          <thead>
            <tr>
              <th><input type="checkbox" id="selectAll" onchange="selectAllInv(this)"></th>
              <th onclick="sortTable('num')" class="sortable">Invoice # <i class="fas fa-sort"></i></th>
              <th onclick="sortTable('client')" class="sortable">Client <i class="fas fa-sort"></i></th>
              <th onclick="sortTable('service')" class="sortable">Service <i class="fas fa-sort"></i></th>
              <th onclick="sortTable('issued')" class="sortable">Issue Date <i class="fas fa-sort"></i></th>
              <th onclick="sortTable('due')" class="sortable">Due Date <i class="fas fa-sort"></i></th>
              <th onclick="sortTable('amount')" class="sortable">Amount <i class="fas fa-sort"></i></th>
              <th style="text-align:center">Paid</th>
              <th onclick="sortTable('status')" class="sortable">Status <i class="fas fa-sort"></i></th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="invoicesTbody"></tbody>
        </table>
        <div class="table-footer">
          <div class="tf-info" id="tfInfo">Showing 1–10 of 34</div>
          <div class="pagination" id="pagination"></div>
        </div>
      </div>

    <!-- Delete Confirmation Modal (relocated from SPA global-modals tail section) -->
<div class="modal-overlay" id="modal-delete">
  <div class="modal modal-sm">
    <div class="modal-header"><span>Delete Invoice</span><button class="modal-close" onclick="closeModal('modal-delete')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body" style="padding:24px;text-align:center">
      <i class="fas fa-trash" style="font-size:40px;color:#e53935;margin-bottom:12px"></i>
      <p>Are you sure you want to delete <strong id="del-inv-num"></strong>?<br>This action cannot be undone.</p>
    </div>
    <div class="modal-footer">
      <button class="btn" style="background:#e53935;color:#fff" onclick="confirmDelete()"><i class="fas fa-trash"></i> Delete</button>
      <button class="btn btn-outline" onclick="closeModal('modal-delete')">Cancel</button>
    </div>
  </div>
</div>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/shared-data.js"></script>
<script src="/assets/js/wa-shared.js"></script>
<script src="/assets/js/invoice-render-shared.js"></script>
<script src="/assets/js/pages/invoices.js"></script>
