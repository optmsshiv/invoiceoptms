<?php
// ================================================================
//  pages/credit-notes.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.credit_notes');
$user = currentUser();

$activePage = 'credit-notes';
$pageTitle  = 'Credit Notes';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div class="page-toolbar">
        <div class="toolbar-left">
          <input type="text" class="table-search" placeholder="Search credit notes…" oninput="filterCreditNotes(this.value)" id="cn-search">
          <select class="table-filter" onchange="renderCreditNotes()" id="cn-status-filter">
            <option value="">All Status</option>
            <option>Draft</option><option>Issued</option><option>Applied</option><option>Void</option>
          </select>
        </div>
        <div class="toolbar-right">
          <button class="btn btn-outline" onclick="exportCreditNotesCSV()"><i class="fas fa-download"></i> Export</button>
          <button class="btn btn-primary" onclick="openCreditNoteModal(null)"><i class="fas fa-plus"></i> New Credit Note</button>
        </div>
      </div>
      <!-- Summary cards -->
      <div id="cn-summary" style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px"></div>
      <!-- Table -->
      <div class="table-card">
        <table class="data-table"><thead><tr>
          <th>CN #</th><th>Invoice #</th><th>Client</th><th>Date</th><th>Amount</th><th>Reason</th><th>Status</th><th>Actions</th>
        </tr></thead><tbody id="cn-tbody"></tbody></table>
        <div class="table-footer"><div class="tf-info" id="cn-info"></div></div>
      </div>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/shared-data.js"></script>
<script src="/assets/js/pages/credit-notes.js"></script>
