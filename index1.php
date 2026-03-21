<?php
// ═══════════════════════════════════════════════════════
//  OPTMS Invoice Manager — Main Application Entry
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$user = currentUser();
if (!$user) { doLogout(); header('Location: ' . APP_URL . '/auth/login.php'); exit; }

// Load company settings
$settings = [];
$db = getDB();
$rows = $db->query('SELECT `key`, value FROM settings')->fetchAll();
foreach ($rows as $r) $settings[$r['key']] = $r['value'];

$companyName = $settings['company_name'] ?? 'OPTMS Tech';
$prefix      = $settings['invoice_prefix'] ?? 'OT-' . date('Y') . '-';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($companyName) ?> — Invoice Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<!-- PHP injects server-side data into JS globals -->
<script>
const SERVER = {
  user: <?= json_encode(['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role'],'avatar'=>$user['avatar']]) ?>,
  settings: <?= json_encode($settings) ?>,
  prefix: <?= json_encode($prefix) ?>,
  appUrl: <?= json_encode(APP_URL) ?>,
  year: <?= date('Y') ?>
};
</script>

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logo"><?= strtoupper(substr($companyName,0,2)) ?></div>
    <div class="brand-text">
      <span class="brand-name"><?= htmlspecialchars($companyName) ?></span>
      <span class="brand-tagline">Invoice Manager</span>
    </div>
  </div>
  <button class="sidebar-toggle-btn" id="sidebarToggle" onclick="toggleSidebar()" title="Toggle Sidebar">
    <i class="fas fa-bars" id="toggleIcon"></i>
  </button>
  <nav class="sidebar-nav">
    <div class="nav-section-label">MAIN</div>
    <a class="nav-item active" data-page="dashboard" onclick="showPage('dashboard',this)"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
    <a class="nav-item" data-page="invoices" onclick="showPage('invoices',this)"><i class="fas fa-file-invoice"></i><span>Invoices</span><span class="nav-badge" id="badge-invoices">0</span></a>
    <a class="nav-item" data-page="create" onclick="showPage('create',this)"><i class="fas fa-plus-circle"></i><span>New Invoice</span></a>
    <a class="nav-item" data-page="clients" onclick="showPage('clients',this)"><i class="fas fa-users"></i><span>Clients</span></a>
    <a class="nav-item" data-page="products" onclick="showPage('products',this)"><i class="fas fa-box"></i><span>Services / Products</span></a>
    <a class="nav-item" data-page="payments" onclick="showPage('payments',this)"><i class="fas fa-credit-card"></i><span>Payments</span></a>
    <a class="nav-item" data-page="reports" onclick="showPage('reports',this)"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
    <div class="nav-section-label">TOOLS</div>
    <a class="nav-item" data-page="templates" onclick="showPage('templates',this)"><i class="fas fa-palette"></i><span>PDF Templates</span></a>
    <a class="nav-item" data-page="whatsapp" onclick="showPage('whatsapp',this)"><i class="fab fa-whatsapp"></i><span>WhatsApp Setup</span><span class="nav-dot dot-green"></span></a>
    <a class="nav-item" data-page="email-setup" onclick="showPage('email-setup',this)"><i class="fas fa-envelope"></i><span>Email Setup</span></a>
    <div class="nav-section-label">ACCOUNT</div>
    <a class="nav-item" data-page="settings" onclick="showPage('settings',this)"><i class="fas fa-cog"></i><span>Settings</span></a>
    <a class="nav-item" data-page="backup" onclick="showPage('backup',this)"><i class="fas fa-database"></i><span>Backup & Export</span></a>
    <a class="nav-item" href="auth/logout.php" style="margin-top:8px"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($user['name'],0,2)) ?></div>
      <div class="user-info">
        <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
        <span class="user-role"><?= ucfirst($user['role']) ?></span>
      </div>
    </div>
  </div>
</aside>

<!-- ══ MAIN CONTENT ══ -->
<div class="main-wrap" id="mainWrap">
  <header class="topbar">
    <div class="topbar-left">
      <div class="page-breadcrumb" id="breadcrumb">Dashboard</div>
    </div>
    <div class="topbar-right">
      <div class="search-bar">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search invoices, clients…" id="globalSearch" oninput="globalSearchFn(this.value)">
        <div class="search-results" id="searchResults"></div>
      </div>
      <button class="topbar-btn" onclick="showPage('create',null)" title="New Invoice"><i class="fas fa-plus"></i></button>
      <div class="notif-wrap" style="position:relative">
        <button class="notif-bell-btn" id="notifBellBtn" onclick="toggleNotifPanel(event)">
          <i class="fas fa-bell"></i>
          <span class="bell-dot" id="bellCount">0</span>
        </button>
        <div class="notif-panel" id="notifPanel">
          <div class="np-title">Notifications</div>
          <div id="notifList"><div style="padding:16px;text-align:center;color:#aaa;font-size:13px">No notifications</div></div>
          <div style="padding:10px 16px;text-align:center">
            <button class="btn btn-outline" style="font-size:11px;padding:5px 12px" onclick="clearNotifs()">Mark all read</button>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Pages container — loads from separate PHP page files via AJAX -->
  <div class="pages-container" id="pagesContainer">
    <div id="pageLoader" style="display:flex;align-items:center;justify-content:center;height:60vh">
      <div style="text-align:center;color:#9CA3AF">
        <i class="fas fa-circle-notch fa-spin" style="font-size:32px;margin-bottom:12px;display:block"></i>
        Loading…
      </div>
    </div>
  </div>
</div>

<!-- Modals injected by pages -->
<div id="modalContainer"></div>

<!-- Toast notifications -->
<div class="toast-container" id="toastContainer"></div>

<!-- Row context menu -->
<div class="row-menu" id="rowMenu"></div>

<!-- App JS -->
<script src="assets/js/app.js"></script>
<script>
// Bootstrap: apply server settings to STATE on load
document.addEventListener('DOMContentLoaded', () => {
  // Merge server settings into STATE
  if (window.STATE && SERVER.settings) {
    STATE.settings.company = SERVER.settings.company_name || STATE.settings.company;
    STATE.settings.gst     = SERVER.settings.company_gst  || STATE.settings.gst;
    STATE.settings.phone   = SERVER.settings.company_phone || STATE.settings.phone;
    STATE.settings.email   = SERVER.settings.company_email || STATE.settings.email;
    STATE.settings.website = SERVER.settings.company_website || STATE.settings.website;
    STATE.settings.prefix  = SERVER.settings.invoice_prefix  || STATE.settings.prefix;
    STATE.settings.upi     = SERVER.settings.company_upi     || STATE.settings.upi;
    STATE.settings.address = SERVER.settings.company_address || STATE.settings.address;
    STATE.settings.logo    = SERVER.settings.company_logo    || '';
    STATE.settings.signature = SERVER.settings.company_sign  || '';
    STATE.settings.activeTemplate = parseInt(SERVER.settings.active_template)||1;
    STATE.settings.defaultGST     = parseInt(SERVER.settings.default_gst)||18;
    STATE.settings.dueDays        = parseInt(SERVER.settings.due_days)||15;
  }
  // Load data from API then initialise
  loadAllData().then(() => {
    appInit();
  });
});

// Load all data from PHP API
async function loadAllData() {
  try {
    const [inv, clients, products, payments] = await Promise.all([
      fetch('api/invoices.php').then(r=>r.json()),
      fetch('api/clients.php').then(r=>r.json()),
      fetch('api/products.php').then(r=>r.json()),
      fetch('api/payments.php').then(r=>r.json()),
    ]);
    if (inv.data)      STATE.invoices  = inv.data;
    if (clients.data)  STATE.clients   = clients.data;
    if (products.data) STATE.products  = products.data;
    if (payments.data) STATE.payments  = payments.data;
  } catch(e) {
    console.warn('API load error, using sample data:', e);
  }
}
</script>

</body>
</html>
