<?php
// ================================================================
//  pages/portal.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.portal');
$user = currentUser();

$activePage = 'portal';
$pageTitle  = 'Client Portal';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <!-- Info banner — keep as is -->
      <div style="display:flex;align-items:flex-start;gap:14px;background:linear-gradient(135deg,#e0f2f1,#e3f2fd);border-radius:12px;padding:16px 20px;margin-bottom:18px;border:1px solid #b2dfdb">
        <div style="font-size:24px;line-height:1">&#128279;</div>
        <div>
          <div style="font-weight:700;font-size:14px;color:#00695C;margin-bottom:4px">Portal links are auto-generated</div>
          <div style="font-size:12px;color:#555;line-height:1.6">Every new invoice gets a unique secure link automatically when saved. Links for existing invoices are generated on first page load. Clients can view invoice details, status &amp; payment info &#8212; no login needed.</div>
        </div>
      </div>
      <!-- Base URL config -->
      <div class="settings-block" style="margin-bottom:18px;padding:14px 18px">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <div style="font-weight:600;font-size:13px;white-space:nowrap"><i class="fas fa-globe" style="color:var(--teal);margin-right:6px"></i>Portal Base URL</div>
          <input id="portal-base-url" placeholder="https://invcs.optms.co.in/portal/" value="https://invcs.optms.co.in/portal/" style="flex:1;min-width:200px">
          <button class="btn btn-outline" onclick="_renderPortalTable()" style="white-space:nowrap"><i class="fas fa-sync-alt"></i> Refresh</button>
          <div id="portal-autogen-status" style="font-size:11px;color:var(--muted)"></div>
        </div>
      </div>
      <!-- Stats bar -->
      <div id="portal-stats" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px"></div>
      <!-- Toolbar — all filters in one row -->
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap">
        <input type="text" class="table-search" placeholder="Search invoice, client…" oninput="_portalPage=1;_renderPortalTable(this.value)" id="portal-search" style="width:190px">
        <select class="table-filter" id="portal-status-filter" onchange="_portalPage=1;_renderPortalTable()">
          <option value="">All status</option>
          <option value="Pending">Pending</option>
          <option value="Overdue">Overdue</option>
          <option value="Partial">Partial</option>
          <option value="Paid">Paid</option>
          <option value="Draft">Draft</option>
        </select>
        <select class="table-filter" id="portal-link-filter" onchange="_portalPage=1;_renderPortalTable()">
          <option value="">All links</option>
          <option value="never">Never viewed</option>
          <option value="viewed">Viewed</option>
          <option value="expired">Expired</option>
        </select>
      </div>
      <!-- Full width portal table with pagination in footer -->
      <div class="settings-block" style="padding:0;overflow:hidden">
        <table class="data-table"><thead><tr>
          <th>Invoice #</th>
          <th>Client</th>
          <th>Amount</th>
          <th>Due date</th>
          <th>Status</th>
          <th>Portal link</th>
          <th>Views</th>
          <th>Expiry</th>
          <th>Actions</th>
        </tr></thead><tbody id="portal-tbody"></tbody></table>
        <!-- Table footer — pagination lives inside card -->
        <div id="portal-pagination" style="display:none;align-items:center;justify-content:space-between;padding:10px 16px;border-top:1px solid var(--border);background:var(--bg)">
          <span id="portal-page-info" style="font-size:12px;color:var(--muted)"></span>
          <div id="portal-page-btns" style="display:flex;gap:5px;align-items:center"></div>
        </div>
      </div>
    
        <!--


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/shared-data.js"></script>
<script src="/assets/js/pages/portal.js"></script>
