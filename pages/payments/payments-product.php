<?php
// ================================================================
//  pages/payments/payments-product.php
//  Payments dashboard for product/trading businesses — merges
//  invoice payments, purchase payments, sale payments, and
//  standalone payment vouchers into one view with KPIs and charts.
//  Shown only when business_type='product' exactly.
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.payments');

$user = currentUser();

$settingsRows = [];
try {
    $rows = getDB()->query('SELECT `key`, value FROM settings')->fetchAll();
    foreach ($rows as $r) $settingsRows[$r['key']] = $r['value'];
} catch (Exception $e) {
    error_log('Settings load error: ' . $e->getMessage());
}
if (($settingsRows['business_type'] ?? 'both') !== 'product') {
    header('Location: /pages/payments/payments-service.php');
    exit;
}

$activePage  = 'payments';
$pageTitle   = 'Payments';
$pageScripts = [
    '/assets/js/shared-data.js',
    '/assets/js/payment-receipt-shared.js',
    '/assets/js/payments-product.js',
];

include __DIR__ . '/../../includes/layout_header.php';
?>
    <div id="page-payments-product" class="page">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px">
        <div>
          <div style="font-size:20px;font-weight:800;color:var(--text)">Payments</div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px">Dashboard &gt; Payments</div>
        </div>
        <div style="display:flex;gap:8px">
          <button class="btn btn-outline" onclick="toast('🔧 Advanced filters — coming soon','info')"><i class="fas fa-filter"></i> Filters</button>
          <button class="btn btn-outline" onclick="exportPmtCSV()"><i class="fas fa-download"></i> Export</button>
          <button class="btn btn-primary" onclick="openMakePaymentModal()"><i class="fas fa-plus"></i> Make Payment</button>
        </div>
      </div>

      <!-- ── 6 KPI cards: IN vs OUT clearly separated ── -->
      <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin:16px 0" class="ps-stats-row">
        <div class="pne-card" style="padding:14px 16px;border-top:3px solid #00897B">
          <span class="sa-chip-icon" style="background:#E8F5E9;color:#00897B;width:32px;height:32px"><i class="fas fa-arrow-down-to-bracket"></i></span>
          <div style="margin-top:8px;font-size:10.5px;color:var(--muted);font-weight:700">TOTAL COLLECTED</div>
          <div style="font-size:15px;font-weight:800;color:#00897B" id="pmt-stat-collected">₹0.00</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">From customers</div>
          <div style="font-size:10px;font-weight:700;margin-top:2px" id="pmt-chg-collected"></div>
        </div>
        <div class="pne-card" style="padding:14px 16px;border-top:3px solid #E53935">
          <span class="sa-chip-icon" style="background:#FFEBEE;color:#E53935;width:32px;height:32px"><i class="fas fa-arrow-up-from-bracket"></i></span>
          <div style="margin-top:8px;font-size:10.5px;color:var(--muted);font-weight:700">TOTAL PAID OUT</div>
          <div style="font-size:15px;font-weight:800;color:#E53935" id="pmt-stat-paidout">₹0.00</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">To suppliers</div>
          <div style="font-size:10px;font-weight:700;margin-top:2px" id="pmt-chg-paidout"></div>
        </div>
        <div class="pne-card" style="padding:14px 16px;border-top:3px solid #7B1FA2">
          <span class="sa-chip-icon" style="background:#F3E8FF;color:#7B1FA2;width:32px;height:32px"><i class="fas fa-hand-holding-dollar"></i></span>
          <div style="margin-top:8px;font-size:10.5px;color:var(--muted);font-weight:700">OUTSTANDING</div>
          <div style="font-size:15px;font-weight:800;color:#7B1FA2" id="pmt-stat-outstanding">₹0.00</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Customers owe you</div>
          <div style="font-size:10px;font-weight:700;margin-top:2px" id="pmt-chg-outstanding"></div>
        </div>
        <div class="pne-card" style="padding:14px 16px;border-top:3px solid #E65100">
          <span class="sa-chip-icon" style="background:#FFF3E0;color:#E65100;width:32px;height:32px"><i class="fas fa-file-circle-exclamation"></i></span>
          <div style="margin-top:8px;font-size:10.5px;color:var(--muted);font-weight:700">PAYABLE</div>
          <div style="font-size:15px;font-weight:800;color:#E65100" id="pmt-stat-payable">₹0.00</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">You owe suppliers</div>
          <div style="font-size:10px;font-weight:700;margin-top:2px" id="pmt-chg-payable"></div>
        </div>
        <div class="pne-card" style="padding:14px 16px;border-top:3px solid #1976D2">
          <span class="sa-chip-icon" style="background:#E3F2FD;color:#1976D2;width:32px;height:32px"><i class="fas fa-money-bill-wave"></i></span>
          <div style="margin-top:8px;font-size:10.5px;color:var(--muted);font-weight:700">CASH</div>
          <div style="font-size:15px;font-weight:800" id="pmt-stat-cash">₹0.00</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Cash transactions</div>
          <div style="font-size:10px;font-weight:700;margin-top:2px" id="pmt-chg-cash"></div>
        </div>
        <div class="pne-card" style="padding:14px 16px;border-top:3px solid #6A4C93">
          <span class="sa-chip-icon" style="background:#F3E8FF;color:#6A4C93;width:32px;height:32px"><i class="fas fa-building-columns"></i></span>
          <div style="margin-top:8px;font-size:10.5px;color:var(--muted);font-weight:700">UPI / BANK</div>
          <div style="font-size:15px;font-weight:800" id="pmt-stat-digital">₹0.00</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Digital transactions</div>
          <div style="font-size:10px;font-weight:700;margin-top:2px" id="pmt-chg-digital"></div>
        </div>
      </div>

      <!-- ── Charts row ── -->
      <div style="display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:14px;margin-bottom:16px">
        <div class="pne-card">
          <div style="font-size:13px;font-weight:700;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center">
            Collections vs Payments (30 days)
            <span style="font-size:11px;font-weight:400;color:var(--muted)">Daily trend</span>
          </div>
          <div style="height:160px;position:relative"><canvas id="pmt-trend-chart"></canvas></div>
        </div>
        <div class="pne-card">
          <div style="font-size:13px;font-weight:700;margin-bottom:12px">Payment Mode Split</div>
          <div style="height:130px;position:relative"><canvas id="pmt-mode-chart"></canvas></div>
          <div id="pmt-mode-legend" style="margin-top:8px;font-size:11px"></div>
        </div>
        <div class="pne-card">
          <div style="font-size:13px;font-weight:700;margin-bottom:12px">Status Breakdown</div>
          <div id="pmt-status-breakdown" style="display:flex;flex-direction:column;gap:8px;margin-top:4px"></div>
        </div>
      </div>

      <div class="pne-card">
        <div class="pne-grid5" style="align-items:end">
          <div class="field" style="grid-column:span 1"><label>Search</label><input class="table-search" style="max-width:none" placeholder="Search by reference no., party name, payment mode…" oninput="filterPayments(this.value)" id="pmtSearch"></div>
          <div class="field"><label>Payment Type</label><select class="table-filter" style="max-width:none" id="pmtTypeFilter" onchange="renderPayments()"><option value="">All</option><option value="in">Received</option><option value="out">Paid Out</option></select></div>
          <div class="field"><label>Payment Mode</label>
            <select class="table-filter" style="max-width:none" onchange="renderPayments()" id="pmtMethodFilter">
              <option value="">All</option>
              <option>Bank Transfer (NEFT/RTGS)</option><option>UPI (GPay/PhonePe/Paytm)</option>
              <option>Cash</option><option>Cheque</option><option>NEFT</option><option>RTGS</option>
            </select>
          </div>
          <div class="field"><label>Party Type</label><select class="table-filter" style="max-width:none" id="pmtPartyTypeFilter" onchange="renderPayments()"><option value="">All</option><option>Customer</option><option>Supplier</option><option>Transporter</option><option>Vendor</option></select></div>
          <div class="field"><label>Status</label><select class="table-filter" style="max-width:none" id="pmtStatusFilter" onchange="renderPayments()"><option value="">All</option><option>Paid</option><option>Partial</option><option>Pending</option></select></div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:-6px;margin-bottom:10px">
          <button class="btn btn-outline" onclick="resetPmtFilters()"><i class="fas fa-rotate-left"></i> Reset</button>
        </div>

        <div style="overflow-x:auto">
          <table class="data-table ps-stock-table" style="min-width:1150px;table-layout:fixed">
            <colgroup>
              <col style="width:30px"><col style="width:90px"><col style="width:100px"><col style="width:130px"><col style="width:85px">
              <col style="width:160px"><col style="width:95px"><col style="width:90px"><col style="width:70px"><col style="width:75px">
            </colgroup>
            <thead><tr>
              <th>#</th><th>Payment Date</th><th>Reference No.</th><th>Party Name</th><th>Party Type</th>
              <th>Payment For</th><th>Payment Mode</th><th>Amount (₹)</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody id="paymentsTbody"></tbody>
          </table>
        </div>
        <div class="table-footer">
          <div class="tf-info" id="pmtInfo"></div>
          <div class="pagination" id="pmtPagination"></div>
        </div>
      </div>
      <div style="padding:14px 0 30px;font-size:11px;color:var(--muted)"><i class="fas fa-circle-info"></i> Payments are made to suppliers, vendors, transporters and other parties.</div>
    </div>

    <!-- Make Payment Modal -->
    <div class="modal-overlay" id="modal-makepayment">
      <div class="modal" style="max-width:480px">
        <div class="modal-header">
          <span>Make Payment</span>
          <button class="modal-close" onclick="closeModal('modal-makepayment')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="field"><label>Payment Date *</label><input type="date" id="mp-date"></div>
            <div class="field"><label>Direction *</label><select id="mp-direction"><option value="out">Paid Out (to Vendor/Transporter/Supplier)</option><option value="in">Received (from Customer)</option></select></div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="field"><label>Party Type *</label><select id="mp-partytype"><option>Vendor</option><option>Transporter</option><option>Supplier</option><option>Customer</option><option>Other</option></select></div>
            <div class="field"><label>Party Name *</label><input id="mp-partyname" placeholder="e.g. Bharat Transport"></div>
          </div>
          <div class="field"><label>Payment For *</label><input id="mp-paymentfor" placeholder="e.g. Transport Charges - May 2024"></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="field"><label>Payment Mode *</label><select id="mp-mode"><option>Cash</option><option>UPI</option><option>Bank Transfer</option><option>NEFT</option><option>RTGS</option><option>Cheque</option></select></div>
            <div class="field"><label>Amount (₹) *</label><input type="number" id="mp-amount" min="0" step="0.01"></div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="field"><label>Status</label><select id="mp-status"><option>Paid</option><option>Pending</option></select></div>
            <div class="field"><label>Reference No.</label><input id="mp-refno" placeholder="Auto-generated"></div>
          </div>
          <div class="field"><label>Notes</label><textarea id="mp-notes" style="min-height:44px" placeholder="Optional"></textarea></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" onclick="closeModal('modal-makepayment')">Cancel</button>
          <button class="btn btn-primary" id="mp-save-btn" onclick="saveMakePayment()"><i class="fas fa-check"></i> Save Payment</button>
        </div>
      </div>
    </div>

    <!-- ─────────── PAYMENTS (Service businesses — simple list) ─────────── -->
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
