<?php
// ================================================================
//  pages/clients.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.clients');
$user = currentUser();

$activePage = 'clients';
$pageTitle  = 'Clients';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div class="page-toolbar">
        <input type="text" class="table-search" placeholder="Search clients…" oninput="filterClients(this.value)">
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);cursor:pointer;user-select:none;white-space:nowrap">
          <input type="checkbox" id="show-inactive-toggle" onchange="renderClients()" style="cursor:pointer">
          Show Inactive
          <span id="inactive-count-badge" style="display:none;background:#F9A825;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:700"></span>
        </label>
        <div style="flex:1"></div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <input type="text" id="client-search" placeholder="Search clients..." oninput="renderClients()" style="padding:7px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;min-width:160px">
          <select id="client-tag-filter" onchange="renderClients()" style="padding:7px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg)">
            <option value="">All Tags</option>
          </select>
        </div>
        <button class="btn btn-primary" onclick="openAddClientModal()"><i class="fas fa-plus"></i> Add Client</button>
      </div>
      <div class="clients-grid" id="clientsGrid"></div>
    

    <!--


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/pages/shared-data.js"></script>
<script src="/assets/js/pages/edit-approval-shared.js"></script>
<script src="/assets/js/pages/clients.js"></script>
