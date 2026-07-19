<?php
// ================================================================
//  pages/suppliers.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.suppliers');
$user = currentUser();

$activePage = 'suppliers';
$pageTitle  = 'Suppliers';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;flex-wrap:wrap;gap:10px">
        <div>
          <div style="font-size:20px;font-weight:800;color:var(--text)">Supplier List</div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px">Dashboard &gt; Master &gt; Supplier List</div>
        </div>
        <div style="display:flex;gap:8px">
          <button class="btn btn-primary" onclick="goToNewSupplierPage()"><i class="fas fa-plus"></i> Add New Supplier</button>
          <button class="btn btn-outline" onclick="exportSuppliersExcel()"><i class="fas fa-file-excel"></i> Export Excel</button>
        </div>
      </div>

      <!-- Filters -->
      <div class="pne-card" style="margin-bottom:16px">
        <div class="pne-grid5" style="align-items:end">
          <div class="field"><label>Search Supplier</label><input id="spl-f-search" placeholder="Search by supplier name, mobile, email…" oninput="SPL_PAGE=1; renderSuppliers()"></div>
          <div class="field"><label>Supplier Type</label><select id="spl-f-type"><option value="">All Types</option></select></div>
          <div class="field"><label>State</label><select id="spl-f-state"><option value="">All States</option></select></div>
          <div class="field"><label>Status</label><select id="spl-f-status"><option value="">All Status</option><option value="active">Active</option><option value="archived">Inactive</option></select></div>
          <div class="field"><label>Payment Terms</label><select id="spl-f-terms"><option value="">All Payment Terms</option></select></div>
        </div>
        <div class="pne-grid5" style="align-items:end;margin-top:10px">
          <div class="field"><label>From Date</label><input type="date" id="spl-f-from"></div>
          <div class="field"><label>To Date</label><input type="date" id="spl-f-to"></div>
          <div class="field" style="display:flex;gap:8px">
            <button class="btn btn-primary" style="flex:1" onclick="SPL_PAGE=1; renderSuppliers()"><i class="fas fa-magnifying-glass"></i> Search</button>
            <button class="btn btn-outline" onclick="resetSuppliersFilter()"><i class="fas fa-rotate-left"></i> Reset</button>
          </div>
        </div>
      </div>

      <!-- Stat cards -->
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:16px" class="ps-stats-row">
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E8F5E9;color:#2E7D32;width:36px;height:36px"><i class="fas fa-users"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Suppliers</div>
          <div style="font-size:18px;font-weight:800" id="spl-stat-total">0</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">All Suppliers</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#E3F2FD;color:#1976D2;width:36px;height:36px"><i class="fas fa-user-check"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Active Suppliers</div>
          <div style="font-size:18px;font-weight:800" id="spl-stat-active">0</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Active</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#F3E8FF;color:#6A4C93;width:36px;height:36px"><i class="fas fa-user-xmark"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Inactive Suppliers</div>
          <div style="font-size:18px;font-weight:800" id="spl-stat-inactive">0</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Inactive</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFF3E0;color:#E65100;width:36px;height:36px"><i class="fas fa-cart-shopping"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Total Purchases</div>
          <div style="font-size:17px;font-weight:800" id="spl-stat-purchases">₹0.00</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px" id="spl-stat-range1">Filtered period</div>
        </div>
        <div class="pne-card" style="padding:14px 16px">
          <span class="sa-chip-icon" style="background:#FFEBEE;color:#C62828;width:36px;height:36px"><i class="fas fa-file-circle-exclamation"></i></span>
          <div style="margin-top:8px;font-size:11px;color:var(--muted)">Outstanding Amount</div>
          <div style="font-size:17px;font-weight:800;color:#E53935" id="spl-stat-out">₹0.00</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Filtered period</div>
        </div>
      </div>

      <!-- Table -->
      <div class="pne-card">
        <div class="pne-card-head pne-head-green" style="margin-bottom:12px"><i class="fas fa-users"></i> Suppliers</div>
        <div class="table-card" style="overflow-x:auto">
          <table class="data-table" style="min-width:1080px">
            <thead><tr><th>#</th><th>Supplier Name</th><th>Contact Person</th><th>Mobile No.</th><th>Email</th><th>State</th><th>Payment Terms</th><th style="text-align:right">Total Purchases (₹)</th><th style="text-align:right">Outstanding (₹)</th><th>Status</th><th>Action</th></tr></thead>
            <tbody id="suppliersTbody"></tbody>
          </table>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;flex-wrap:wrap;gap:8px">
          <div style="font-size:12px;color:var(--muted)" id="supInfo"></div>
          <div style="display:flex;gap:5px" id="spl-pagination"></div>
        </div>
      </div>

      <div id="spl-note-banner" style="margin-top:14px;background:#E8F5E9;border:1px solid #A5D6A7;border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;gap:12px">
        <div style="font-size:12px;color:#1B5E20"><i class="fas fa-circle-info"></i> <b>Note:</b> Click on <i class="fas fa-eye"></i> to view supplier details, <i class="fas fa-pen"></i> to edit supplier information.</div>
        <button style="background:none;border:none;color:#1B5E20;cursor:pointer;font-size:14px" onclick="document.getElementById('spl-note-banner').style.display='none'"><i class="fas fa-times"></i></button>
      </div>
    </div>

    <!-- Edit Approval Request Modal -->
    <div class="modal-overlay" id="modal-edit-approval">
      <div class="modal" style="max-width:480px">
        <div class="modal-header">
          <span style="display:flex;align-items:center;gap:8px"><i class="fas fa-shield-halved" style="color:var(--teal)"></i> Request Edit Permission</span>
          <button class="modal-close" onclick="cancelEditRequest()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding:20px">
          <div id="ear-request-view">
            <div style="font-size:13px;color:var(--text);margin-bottom:14px">
              You need permission to edit <strong id="ear-entity-label"></strong>. Describe why you need to make this change — your admin will be notified immediately.
            </div>
            <div class="field">
              <label>Reason for edit <span style="color:var(--red)">*</span></label>
              <textarea id="ear-reason" placeholder="e.g. Wrong supplier was selected, rate needs correction…" style="min-height:80px;resize:vertical"></textarea>
            </div>
            <button class="btn btn-primary" style="width:100%" onclick="submitEditRequest()"><i class="fas fa-paper-plane"></i> Send Request to Admin</button>
          </div>
          <div id="ear-waiting-view" style="display:none;text-align:center;padding:10px 0">
            <div style="font-size:36px;margin-bottom:12px">⏳</div>
            <div style="font-size:15px;font-weight:700;margin-bottom:6px">Waiting for approval…</div>
            <div style="font-size:12.5px;color:var(--muted);margin-bottom:16px" id="ear-waiting-sub">Your admin or manager has been notified. This window polls automatically — you don't need to do anything.</div>
            <div style="background:var(--bg);border-radius:10px;padding:12px;font-size:12px;color:var(--muted);margin-bottom:16px">
              <i class="fas fa-info-circle"></i> Once approved, you get <strong>one edit only</strong> — the approval expires immediately after you save.
            </div>
            <button class="btn btn-outline" style="width:100%" onclick="cancelEditRequest()">Cancel Request</button>
          </div>
          <div id="ear-approved-view" style="display:none;text-align:center;padding:10px 0">
            <div style="font-size:36px;margin-bottom:12px">✅</div>
            <div style="font-size:15px;font-weight:700;color:var(--green);margin-bottom:6px">Approved!</div>
            <div style="font-size:12.5px;color:var(--muted);margin-bottom:4px" id="ear-approved-by"></div>
            <div style="font-size:12.5px;color:var(--muted);margin-bottom:16px" id="ear-approved-note"></div>
            <div style="font-size:11px;color:var(--amber);margin-bottom:14px"><i class="fas fa-exclamation-triangle"></i> This approval is valid for <strong>one edit only</strong> — it expires as soon as you save</div>
            <button class="btn btn-primary" style="width:100%" onclick="proceedWithEdit()"><i class="fas fa-pen"></i> Proceed to Edit</button>
          </div>
          <div id="ear-rejected-view" style="display:none;text-align:center;padding:10px 0">
            <div style="font-size:36px;margin-bottom:12px">❌</div>
            <div style="font-size:15px;font-weight:700;color:var(--red);margin-bottom:6px">Request Declined</div>
            <div style="font-size:12.5px;color:var(--muted);margin-bottom:4px" id="ear-rejected-by"></div>
            <div style="font-size:12.5px;color:var(--muted);margin-bottom:16px" id="ear-rejected-note"></div>
            <button class="btn btn-outline" style="width:100%" onclick="cancelEditRequest()">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Admin: Pending Edit Requests shown in dashboard via renderPendingApprovalsCard() -->

    <!-- Supplier Profile modal -->
    <div class="modal-overlay" id="modal-supplier-profile">
      <div class="modal modal-md" style="overflow:hidden;position:relative">
        <button class="modal-close" onclick="closeModal('modal-supplier-profile')" style="position:absolute;top:14px;right:14px;z-index:2;background:rgba(255,255,255,.18);color:#fff"><i class="fas fa-times"></i></button>
        <div id="sp-profile-head" style="position:relative;padding:26px 24px 20px;background:linear-gradient(135deg,var(--teal) 0%,#00695C 100%);flex-shrink:0"></div>
        <div class="modal-body" id="sp-profile-body" style="padding:20px 22px;background:var(--bg)"></div>
        <div class="modal-footer" id="sp-profile-foot"></div>
      </div>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/pages/shared-data.js"></script>
<script src="/assets/js/pages/edit-approval-shared.js"></script>
<script src="/assets/js/pages/suppliers.js"></script>
