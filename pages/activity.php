<?php
// ================================================================
//  pages/activity.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.activity');
$user = currentUser();

$activePage = 'activity';
$pageTitle  = 'Activity Log';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div class="page-toolbar">
        <div class="toolbar-left">
          <input type="text" class="table-search" placeholder="Search activity…" oninput="filterActivity(this.value)" id="activity-search">
          <select class="table-filter" onchange="filterActivityType(this.value)" id="activity-type-filter">
            <option value="">All Events</option>
            <option value="invoice_created">📄 Invoice Created</option>
            <option value="invoice_edited">✏️ Invoice Edited</option>
            <option value="invoice_deleted">🗑️ Invoice Deleted</option>
            <option value="estimate_created">📋 Estimate Created</option>
            <option value="estimate_edited">📝 Estimate Edited</option>
            <option value="estimate_converted">🔁 Estimate Converted</option>
            <option value="payment_recorded">💰 Payment Recorded</option>
            <option value="status_changed">🔄 Status Changed</option>
            <option value="client_added">👤 Client Added</option>
            <option value="client_edited">✏️ Client Edited</option>
            <option value="client_deleted">🗑️ Client Deleted</option>
            <option value="reminder_sent">🔔 Reminder Sent</option>
            <option value="expense_added">💸 Expense Added</option>
          </select>
          <select class="table-filter" onchange="filterActivityDate(this.value)" id="activity-date-filter">
            <option value="">All Time</option>
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
          </select>
        </div>
        <div class="toolbar-right">
          <button class="btn btn-outline" onclick="refreshActivityLog()" id="activity-refresh-btn" title="Refresh log"><i class="fas fa-sync-alt"></i> Refresh</button>
          <button class="btn btn-outline" onclick="exportActivityCSV()"><i class="fas fa-download"></i> Export</button>
          <button class="btn btn-outline" onclick="clearActivityLog()"><i class="fas fa-trash"></i> Clear</button>
        </div>
      </div>
      <div id="activity-stats" style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap"></div>
      <div id="activity-timeline" style="display:flex;flex-direction:column;gap:0"></div>
      <div style="text-align:center;padding:16px" id="activity-load-more" style="display:none">
        <button class="btn btn-outline" onclick="loadMoreActivity()">Load More</button>
      </div>
    

    <!--


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/pages/shared-data.js"></script>
<script src="/assets/js/pages/activity.js"></script>
