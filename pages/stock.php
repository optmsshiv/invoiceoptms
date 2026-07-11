<?php
// ================================================================
//  pages/stock.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requirePermission('menu.stock');

$user = currentUser();

$activePage  = 'stock';
$pageTitle   = 'Stock Ledger';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/stock.js'];

include __DIR__ . '/../includes/layout_header.php';
?>
      <div class="page-toolbar">
        <input type="text" class="table-search" placeholder="Search products…" oninput="filterStock(this.value)" id="stockSearch">
        <div style="flex:1"></div>
        <span id="stockCountInfo" style="font-size:12px;color:var(--muted);margin-right:8px"></span>
        <button class="btn btn-outline" onclick="openStockAdjustModal()"><i class="fas fa-sliders-h"></i> Adjust Stock</button>
      </div>
      <div class="table-card">
        <table class="data-table">
          <thead><tr><th>Product</th><th>Category</th><th>Current Stock</th><th>Last Movement</th><th>Actions</th></tr></thead>
          <tbody id="stockTbody"></tbody>
        </table>
        <div class="table-footer"><div class="tf-info" id="stockInfo"></div></div>
      </div>

      <!-- Stock Adjustment Modal — stock-page-specific -->
      <div class="modal-overlay" id="modal-stockadjust">
        <div class="modal" style="max-width:460px">
          <div class="modal-header">
            <span>Adjust Stock</span>
            <button class="modal-close" onclick="closeModal('modal-stockadjust')"><i class="fas fa-times"></i></button>
          </div>
          <div class="modal-body">
            <div class="field"><label>Product *</label>
              <select id="adj-product"><option value="">Select product…</option></select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
              <div class="field"><label>Direction *</label>
                <select id="adj-direction">
                  <option value="in">Stock In (+)</option>
                  <option value="out">Stock Out (−)</option>
                </select>
              </div>
              <div class="field"><label>Quantity *</label><input type="number" id="adj-qty" min="0" step="0.001" placeholder="0"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
              <div class="field"><label>Date *</label><input type="date" id="adj-date"></div>
              <div class="field"><label>Rate (optional)</label><input type="number" id="adj-rate" min="0" step="0.01" placeholder="0.00"></div>
            </div>
            <div class="field"><label>Reason / Notes</label><input id="adj-notes" placeholder="e.g. Damaged in transit, physical recount"></div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-stockadjust')">Cancel</button>
            <button class="btn btn-primary" id="adj-save-btn" onclick="saveStockAdjustment()"><i class="fas fa-check"></i> Save Adjustment</button>
          </div>
        </div>
      </div>

      <!-- Stock History Modal — stock-page-specific -->
      <div class="modal-overlay" id="modal-stockhistory">
        <div class="modal" style="max-width:640px">
          <div class="modal-header">
            <span id="sh-product-name">Stock History</span>
            <button class="modal-close" onclick="closeModal('modal-stockhistory')"><i class="fas fa-times"></i></button>
          </div>
          <div class="modal-body">
            <table class="data-table" style="font-size:12px">
              <thead><tr><th>Date</th><th>Source</th><th>Direction</th><th>Qty</th><th>Rate</th><th>Balance</th><th>Notes</th><th></th></tr></thead>
              <tbody id="sh-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>
<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
