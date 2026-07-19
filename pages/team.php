<?php
// ================================================================
//  pages/team.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.team');
$user = currentUser();

$activePage = 'team';
$pageTitle  = 'Team';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div class="page-toolbar">
        <input type="text" class="table-search" placeholder="Search team…" id="teamSearch" oninput="filterTeam(this.value)">
        <div style="flex:1"></div>
        <span id="teamCountInfo" style="font-size:12px;color:var(--muted);margin-right:8px"></span>
        <button class="btn btn-primary" onclick="openAddTeamModal()"><i class="fas fa-user-plus"></i> Add Team Member</button>
      </div>
      <div class="table-card">
        <table class="data-table">
          <thead><tr>
            <th style="width:44px"></th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th>
          </tr></thead>
          <tbody id="teamTbody"></tbody>
        </table>
        <div class="table-footer">
          <div class="tf-info" id="teamInfo"></div>
        </div>
      </div>
    

    <!--


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/pages/shared-data.js"></script>
<script src="/assets/js/pages/team.js"></script>
