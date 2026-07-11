<?php
// ================================================================
//  pages/products.php
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.products');

$user = currentUser();

// Same wording map as includes/layout_header.php's nav label — kept
// in sync manually since this file needs it before that include runs.
$settingsRows = [];
try {
    $rows = getDB()->query('SELECT `key`, value FROM settings')->fetchAll();
    foreach ($rows as $r) $settingsRows[$r['key']] = $r['value'];
} catch (Exception $e) {
    error_log('Settings load error: ' . $e->getMessage());
}
$__bizType = $settingsRows['business_type'] ?? 'both';
$__bizLabels = [
    'service' => ['title' => 'Services',            'nameCol' => 'Service Name', 'search' => 'Search services…', 'addBtn' => 'Add Service'],
    'product' => ['title' => 'Products',             'nameCol' => 'Product Name', 'search' => 'Search products…', 'addBtn' => 'Add Product'],
    'both'    => ['title' => 'Services / Products',  'nameCol' => 'Item Name',    'search' => 'Search…',          'addBtn' => 'Add Item'],
][$__bizType] ?? ['title' => 'Services / Products', 'nameCol' => 'Item Name', 'search' => 'Search…', 'addBtn' => 'Add Item'];

$activePage  = 'products';
$pageTitle   = $__bizLabels['title'];
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/products.js'];

include __DIR__ . '/../../includes/layout_header.php';
?>
      <div class="page-toolbar">
        <input type="text" class="table-search" placeholder="<?= htmlspecialchars($__bizLabels['search']) ?>" oninput="filterProducts(this.value)" id="productSearch">
        <select class="table-filter" onchange="filterProductsCat(this.value)" id="productCatFilter">
          <option value="">All Categories</option>
        </select>
        <div style="flex:1"></div>
        <span id="prodCountInfo" style="font-size:12px;color:var(--muted);margin-right:8px"></span>
        <button class="btn btn-outline" id="prodArchiveToggleBtn" onclick="toggleArchivedView()"><i class="fas fa-box-archive"></i> View Archived</button>
        <?php if ($businessType === 'product'): ?>
        <button class="btn btn-primary" id="prodAddBtn" onclick="window.location.href='/pages/products/product-new.php'"><i class="fas fa-plus"></i> Add Product</button>
        <?php else: ?>
        <button class="btn btn-primary" id="prodAddBtn" onclick="openAddProductModal()"><i class="fas fa-plus"></i> <?= htmlspecialchars($__bizLabels['addBtn']) ?></button>
        <?php endif; ?>
      </div>
      <div class="table-card">
        <table class="data-table">
          <thead><tr><th>#</th><th><?= htmlspecialchars($__bizLabels['nameCol']) ?></th><th>Category</th><th>Rate (₹)</th><th>HSN</th><th>GST%</th><th>Actions</th></tr></thead>
          <tbody id="productsTbody"></tbody>
        </table>
        <div class="table-footer">
          <div class="tf-info" id="prodInfo"></div>
          <div class="pagination" id="prodPagination"></div>
        </div>
      </div>
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
