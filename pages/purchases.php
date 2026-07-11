<?php
// ================================================================
//  pages/purchases.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requirePermission('menu.purchases');

$user = currentUser();

$activePage  = 'purchases';
$pageTitle   = 'Purchases';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/purchases.js'];

include __DIR__ . '/../includes/layout_header.php';
?>
      <div class="page-toolbar">
        <input type="text" class="table-search" placeholder="Search purchases…" oninput="filterPurchases(this.value)" id="purchaseSearch">
        <select class="table-filter" onchange="renderPurchases()" id="purStatusFilter">
          <option value="">All Status</option>
          <option>Pending</option><option>Received</option><option>Partial</option><option>Paid</option>
        </select>
        <div style="flex:1"></div>
        <span id="purCountInfo" style="font-size:12px;color:var(--muted);margin-right:8px"></span>
        <button class="btn btn-primary" onclick="openAddPurchaseModal()"><i class="fas fa-plus"></i> Add Purchase</button>
      </div>
      <div class="table-card">
        <table class="data-table">
          <thead><tr><th>PO No.</th><th>Supplier</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody id="purchasesTbody"></tbody>
        </table>
        <div class="table-footer">
          <div class="tf-info" id="purInfo"></div>
          <div class="pagination" id="purPagination"></div>
        </div>
      </div>

      <!-- Add/Edit Purchase Modal — purchases-page-specific -->
      <div class="modal-overlay" id="modal-addpurchase">
        <div class="modal" style="max-width:820px">
          <div class="modal-header">
            <span>Add New Purchase</span>
            <button class="modal-close" onclick="closeModal('modal-addpurchase')"><i class="fas fa-times"></i></button>
          </div>
          <div class="modal-body">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
              <div class="field"><label>Supplier *</label>
                <select id="pur-supplier"><option value="">Select supplier…</option></select>
              </div>
              <div class="field"><label>Purchase Date *</label><input type="date" id="pur-date"></div>
              <div class="field"><label>Supplier Invoice Ref</label><input id="pur-invref" placeholder="Their bill/invoice no."></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
              <div class="field"><label>PO Number <span style="font-weight:400;color:var(--muted)">(auto if blank)</span></label><input id="pur-no" placeholder="Auto-generated"></div>
              <div class="field"><label>Currency</label>
                <select id="pur-currency" onchange="calcPurchaseTotals()">
                  <option value="INR">INR (₹)</option><option value="USD">USD ($)</option>
                  <option value="EUR">EUR (€)</option><option value="GBP">GBP (£)</option>
                </select>
              </div>
              <div class="field"><label>Exchange Rate (→ ₹)</label><input type="number" id="pur-fx" value="1" min="0" step="0.0001" oninput="calcPurchaseTotals()"></div>
            </div>

            <!-- Line items -->
            <div style="margin-top:6px">
              <table class="data-table" style="font-size:12px">
                <thead><tr>
                  <th style="width:26%">Product</th><th>HSN</th><th>Qty</th><th>Unit</th><th>Rate</th><th>GST%</th><th>Amount</th><th></th>
                </tr></thead>
                <tbody id="pur-items-tbody"></tbody>
              </table>
              <button class="btn btn-outline" style="font-size:12px;margin-top:8px" onclick="addPurchaseItem()"><i class="fas fa-plus"></i> Add Item</button>
            </div>

            <!-- Totals -->
            <div style="display:flex;justify-content:flex-end;margin-top:12px">
              <div style="width:260px;font-size:13px">
                <div style="display:flex;justify-content:space-between;padding:4px 0"><span>Subtotal</span><strong id="pur-subtotal">₹0.00</strong></div>
                <div style="display:flex;justify-content:space-between;padding:4px 0"><span>GST</span><strong id="pur-gst">₹0.00</strong></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--border);font-size:15px"><span>Total</span><strong id="pur-total">₹0.00</strong></div>
              </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:6px">
              <div class="field"><label>Status</label>
                <select id="pur-status">
                  <option>Pending</option><option>Received</option><option>Partial</option><option>Paid</option>
                </select>
              </div>
              <div class="field"><label>Notes</label><input id="pur-notes" placeholder="Optional"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-addpurchase')">Cancel</button>
            <button class="btn btn-primary" id="pur-save-btn" onclick="savePurchase()"><i class="fas fa-check"></i> Save Purchase</button>
          </div>
        </div>
      </div>
<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
