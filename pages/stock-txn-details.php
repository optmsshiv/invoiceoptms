<?php
// ================================================================
//  pages/stock-txn-details.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.stock_history');
$user = currentUser();

$activePage = 'stock-history';
$pageTitle  = 'Stock Transaction Details';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
        <div>
          <h1 style="margin:0">Stock Transaction Details</h1>
          <div style="font-size:11.5px;color:var(--muted);margin-top:3px">Dashboard › Inventory › Stock History › Transaction Details</div>
        </div>
        <div style="display:flex;gap:8px">
          <a class="btn btn-outline" href="/pages/stock-history.php"><i class="fas fa-arrow-left"></i> Back to Stock History</a>
          <button class="btn btn-outline" onclick="printStockTxnDetails()"><i class="fas fa-print"></i> Print</button>
        </div>
      </div>
      <div id="stx-body"></div>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/stock-shared.js"></script>
<script src="/assets/js/pages/stock-txn-details.js"></script>
