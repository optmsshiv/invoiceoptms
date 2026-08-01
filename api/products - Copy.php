<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Product IDs are exposed to the frontend as "p12" (a "p"-prefixed string) —
// existing pages (Products list, Purchases item picker) already depend on this.
function cleanProductId($v) {
  if (empty($v)) return null;
  $n = (int) preg_replace('/\D/', '', (string)$v);
  return $n > 0 ? $n : null;
}

// Save a base64 data-URL image/file to disk, return its public path (or null).
function saveUpload($dataUrl, $subdir) {
  if (!$dataUrl || !preg_match('/^data:(image\/(png|jpe?g|webp)|application\/pdf);base64,(.+)$/', $dataUrl, $m)) return null;
  $ext  = str_contains($m[1], 'pdf') ? 'pdf' : ($m[2] === 'jpeg' ? 'jpg' : $m[2]);
  $blob = base64_decode($m[3]);
  if ($blob === false || strlen($blob) > (defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 5242880)) return null;
  $dir = rtrim(defined('UPLOAD_PATH') ? UPLOAD_PATH : (__DIR__ . '/../assets/uploads/'), '/') . '/' . $subdir;
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $fname = 'prd_' . bin2hex(random_bytes(8)) . '.' . $ext;
  file_put_contents($dir . '/' . $fname, $blob);
  return '/assets/uploads/' . $subdir . '/' . $fname;
}

// images/attachments/tags arrive as arrays. Images may be a mix of existing
// public paths (already-uploaded, kept as-is) and new data: URLs (freshly
// picked files, need saving to disk).
function processFileArray($items, $subdir) {
  $out = [];
  foreach ((array)$items as $it) {
    if (is_string($it) && str_starts_with($it, 'data:')) {
      $saved = saveUpload($it, $subdir);
      if ($saved) $out[] = $saved;
    } elseif (is_string($it) && $it !== '') {
      $out[] = $it; // already-saved path, kept as-is (e.g. unchanged on edit)
    }
  }
  return $out;
}

// Reconciles opening stock with batch tracking: writes the batch code onto
// the stock_ledger opening entry (that column already existed and was
// already used by Stock In / Stock Adjustments — this was the only place
// NOT setting it), and creates a matching product_batches record so
// "Manage Batches" shows it too. Both stay in sync from here on.
// Returns the batch code actually used (empty string if not applicable).
function syncOpeningBatch($db, $productId, $trackBatch, $batchCode, $qty, $userId) {
  $batchCode = trim((string)$batchCode);
  if (!$trackBatch || $batchCode === '' || $qty <= 0) return '';
  try {
    $db->exec("CREATE TABLE IF NOT EXISTS `product_batches` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `product_id` INT UNSIGNED NOT NULL,
      `batch_code` VARCHAR(60) NOT NULL, `qty` DECIMAL(12,3) NOT NULL DEFAULT 0,
      `remaining_qty` DECIMAL(12,3) NOT NULL DEFAULT 0, `mfg_date` DATE NULL, `expiry_date` DATE NULL,
      `purchase_id` INT UNSIGNED NULL, `notes` VARCHAR(255) DEFAULT NULL,
      `status` ENUM('active','depleted') NOT NULL DEFAULT 'active',
      `created_by` INT UNSIGNED DEFAULT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`), INDEX `idx_pb_product` (`product_id`), INDEX `idx_pb_expiry` (`expiry_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Remove any existing opening-stock batch for this product first (PUT
    // re-derives from scratch, same pattern products.php already uses for
    // the stock_ledger 'opening' entry itself) — identified by notes tag.
    $db->prepare("DELETE FROM product_batches WHERE product_id=? AND notes='Opening stock batch'")->execute([$productId]);

    $db->prepare(
      'INSERT INTO product_batches (product_id, batch_code, qty, remaining_qty, notes, created_by)
       VALUES (?,?,?,?,?,?)'
    )->execute([$productId, $batchCode, $qty, $qty, 'Opening stock batch', $userId]);
  } catch (Throwable $e) { /* non-fatal — stock_ledger.batch_no below still gets set either way */ }
  return $batchCode;
}

$FIELDS = [
  'name','category','rate','gst','unit_family',
  'sku','unit','brand','variety','grade','barcode','shelf_life_months','storage_type',
  'base_unit_label','sale_unit','purchase_unit','min_order_qty',
  'moisture_limit','foreign_matter_limit','broken_damage_limit','oil_content','admixture_limit',
  'color','aroma','shape_size','packing_type','packing_size',
  'purchase_rate','sale_rate','mrp','tax_type',
  'opening_stock','reorder_level','max_stock','default_warehouse','track_batch','track_serial',
  'short_description','detailed_description',
  'country_of_origin','manufacturer','fssai_license','iec_code',
];

try {
// Dynamically filter $FIELDS to only columns that exist in the live DB
// Handles both old schema (service: hsn_code, gst_rate) and new schema (product: hsn, gst)
$colStmt = $db->query("SHOW COLUMNS FROM products");
$existingCols = array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
$existingColsSet = array_flip($existingCols);

// Column name aliases: new_name => old_name (for legacy service DBs)
$COL_ALIASES = ['hsn' => 'hsn_code', 'gst' => 'gst_rate'];

$FIELDS = array_values(array_filter($FIELDS, fn($f) => isset($existingColsSet[$f])));
// Add back aliased columns: if 'hsn' not in DB but 'hsn_code' is, keep 'hsn' mapped
// handled in POST/PUT by rewriting the column name before building INSERT/UPDATE

switch ($method) {
  case 'GET':
    $status = $_GET['status'] ?? 'active';
    $stmt = $db->prepare('SELECT * FROM products WHERE status = ? ORDER BY name ASC');
    $stmt->execute([$status]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
      $r['id'] = 'p' . $r['id'];
      $r['tags'] = isset($r['tags']) && $r['tags'] ? json_decode($r['tags'], true) : [];
      $r['images'] = isset($r['images']) && $r['images'] ? json_decode($r['images'], true) : [];
      $r['attachments'] = isset($r['attachments']) && $r['attachments'] ? json_decode($r['attachments'], true) : [];
      // Map legacy column names to new names for JS compatibility
      if (!isset($r['hsn']) && isset($r['hsn_code']))   $r['hsn'] = $r['hsn_code'];
      if (!isset($r['gst']) && isset($r['gst_rate']))   $r['gst'] = $r['gst_rate'];
    }
    unset($r);
    jsonResponse(['data' => $rows]);
    break;

  case 'POST':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    if (($_GET['action'] ?? '') === 'restore' && !empty($_GET['id'])) {
      $stmt = $db->prepare('UPDATE products SET status = "active" WHERE id = ?');
      $stmt->execute([(int)$_GET['id']]);
      logActivity((int)$_SESSION['user_id'], 'restore', 'product', (int)$_GET['id'], 'Product restored');
      jsonResponse(['success' => true]);
      break;
    }

    if (empty($d['name'])) jsonResponse(['error' => 'Product name is required'], 400);

    $images      = processFileArray($d['images'] ?? [], 'products');
    $attachments = processFileArray($d['attachments'] ?? [], 'products');
    $tags        = is_array($d['tags'] ?? null) ? array_values(array_filter($d['tags'])) : [];

    $cols = array_map(fn($f) => isset($COL_ALIASES[$f]) && isset($existingColsSet[$COL_ALIASES[$f]]) ? $COL_ALIASES[$f] : $f, $FIELDS);
    $vals = array_map(fn($f) => $d[$f] ?? '', $FIELDS);
    // Only add json columns if they exist in this DB
    if (isset($existingColsSet['tags']))        { $cols[] = 'tags';        $vals[] = json_encode($tags); }
    if (isset($existingColsSet['images']))      { $cols[] = 'images';      $vals[] = json_encode($images); }
    if (isset($existingColsSet['attachments'])) { $cols[] = 'attachments'; $vals[] = json_encode($attachments); }
    $cols[] = 'status'; $vals[] = 'active';

    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $colList = implode(',', array_map(fn($c) => "`$c`", $cols));
    $stmt = $db->prepare("INSERT INTO products ($colList) VALUES ($placeholders)");
    $stmt->execute($vals);
    $id = $db->lastInsertId();

    // If opening_stock > 0, create a stock_ledger entry (only if table exists — product DBs only)
    $openingStock = (float)($d['opening_stock'] ?? 0);
    if ($openingStock != 0) {
        try {
            $qty       = abs($openingStock);
            $direction = $openingStock > 0 ? 'in' : 'out';
            $date      = date('Y-m-d');
            $warehouse = $d['default_warehouse'] ?? 'Main Warehouse';
            $batchNo   = syncOpeningBatch($db, $id, !empty($d['track_batch']), $d['opening_batch_code'] ?? '', $qty, (int)$_SESSION['user_id']);
            $db->prepare(
                'INSERT INTO stock_ledger (product_id, ref_type, ref_id, direction, qty, rate, balance_after, movement_date, notes, warehouse, batch_no)
                 VALUES (?, "opening", ?, ?, ?, 0, ?, ?, ?, ?, ?)'
            )->execute([$id, $id, $direction, $qty, $openingStock, $date, 'Opening Stock', $warehouse, $batchNo]);
        } catch (Throwable $e) { /* stock_ledger not present on this DB — skip */ }
    }

    logActivity((int)$_SESSION['user_id'], 'create', 'product', (int)$id, 'Product added: ' . $d['name']);
    jsonResponse(['success' => true, 'id' => 'p' . $id]);
    break;

  case 'PUT':
    $id = cleanProductId($_GET['id'] ?? '');
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $images      = processFileArray($d['images'] ?? [], 'products');
    $attachments = processFileArray($d['attachments'] ?? [], 'products');
    $tags        = is_array($d['tags'] ?? null) ? array_values(array_filter($d['tags'])) : [];

    $mappedFields = array_map(fn($f) => isset($COL_ALIASES[$f]) && isset($existingColsSet[$COL_ALIASES[$f]]) ? $COL_ALIASES[$f] : $f, $FIELDS);
    $setParts = array_map(fn($f) => "`$f`=?", $mappedFields);
    $vals = array_map(fn($f) => $d[$f] ?? '', $FIELDS);
    if (isset($existingColsSet['tags']))        { $setParts[] = 'tags=?';        $vals[] = json_encode($tags); }
    if (isset($existingColsSet['images']))      { $setParts[] = 'images=?';      $vals[] = json_encode($images); }
    if (isset($existingColsSet['attachments'])) { $setParts[] = 'attachments=?'; $vals[] = json_encode($attachments); }
    $setSql = implode(',', $setParts);
    $vals[] = $id;

    $db->prepare("UPDATE products SET $setSql WHERE id=?")->execute($vals);

    // Sync opening_stock change to stock_ledger (only if table exists — product DBs only)
    $openingStock = (float)($d['opening_stock'] ?? 0);
    try {
        $db->prepare('DELETE FROM stock_ledger WHERE product_id=? AND ref_type="opening"')->execute([$id]);
        if ($openingStock != 0) {
            $qty       = abs($openingStock);
            $direction = $openingStock > 0 ? 'in' : 'out';
            $warehouse = $d['default_warehouse'] ?? 'Main Warehouse';
            $batchNo   = syncOpeningBatch($db, $id, !empty($d['track_batch']), $d['opening_batch_code'] ?? '', $qty, (int)$_SESSION['user_id']);
            $db->prepare(
                'INSERT INTO stock_ledger (product_id, ref_type, ref_id, direction, qty, rate, balance_after, movement_date, notes, warehouse, batch_no)
                 VALUES (?, "opening", ?, ?, ?, 0, ?, ?, ?, ?, ?)'
            )->execute([$id, $id, $direction, $qty, $openingStock, date('Y-m-d'), 'Opening Stock', $warehouse, $batchNo]);
            rebalanceStockLedger($db, [$id]);
        } else {
            // Opening stock was removed/zeroed — clean up its matching batch too
            try { $db->prepare("DELETE FROM product_batches WHERE product_id=? AND notes='Opening stock batch'")->execute([$id]); } catch (Throwable $e) {}
        }
    } catch (Throwable $e) { /* stock_ledger table not present on this DB — skip */ }

    logActivity((int)$_SESSION['user_id'], 'update', 'product', $id, 'Product updated: ' . ($d['name'] ?? ''));
    jsonResponse(['success' => true]);
    break;

  case 'DELETE':
    $id = cleanProductId($_GET['id'] ?? '');
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);
    $db->prepare('UPDATE products SET status = "archived" WHERE id = ?')->execute([$id]);
    logActivity((int)$_SESSION['user_id'], 'archive', 'product', $id, 'Product archived');
    jsonResponse(['success' => true]);
    break;

  default:
    jsonResponse(['error' => 'Method not allowed'], 405);
}
} catch (Throwable $e) {
  jsonResponse(['error' => 'Products API error: ' . $e->getMessage()], 500);
}
