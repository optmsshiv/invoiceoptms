<?php
// ================================================================
//  includes/layout_header.php
//  Shared <head> + sidebar + topbar for every MPA page.
//
//  REQUIRES the calling page to have already done, before including
//  this file:
//    require_once __DIR__ . '/../config/db.php';
//    require_once __DIR__ . '/../includes/auth.php';
//    requireLogin();
//    requirePermission('menu.xxx');   // <-- the real server-side gate
//    $user = currentUser();
//
//  Everything else (settings, perms, badges) is computed here so
//  individual pages stay short.
// ================================================================

if (empty($user)) {
    // Defensive: a page forgot to load the user before including this.
    header('Location: /auth/login.php');
    exit;
}

$perms = getEffectivePermissions($user['tenant_id'] ?? null, $user['role'] ?? 'viewer');

// ── Load company settings (shared by every page) ─────────────────
$settings = [];
try {
    $rows = getDB()->query('SELECT `key`, value FROM settings')->fetchAll();
    foreach ($rows as $r) $settings[$r['key']] = $r['value'];
} catch (Exception $e) {
    error_log('Settings load error: ' . $e->getMessage());
}

$companyName    = $settings['company_name']    ?? 'OPTMS Tech';
$companyLogo    = $settings['company_logo']    ?? '';
$prefix         = $settings['invoice_prefix']  ?? 'OT-' . date('Y') . '-';
$estPrefix      = $settings['estimate_prefix'] ?? 'QT-' . date('Y') . '-';
$firmName       = $user['company_name'] ?? ($settings['company_name'] ?? 'OPTMS Tech');

// ── Role badge (topbar) ────────────────────────────────────────
$ROLE_BADGE_COLORS = [
    'owner'       => ['bg' => '#E0F2F1', 'text' => '#00695C'],
    'admin'       => ['bg' => '#FFF8E1', 'text' => '#F57F17'],
    'manager'     => ['bg' => '#E3F2FD', 'text' => '#1565C0'],
    'accountant'  => ['bg' => '#E8F5E9', 'text' => '#2E7D32'],
    'sales'       => ['bg' => '#F3E5F5', 'text' => '#7B1FA2'],
    'viewer'      => ['bg' => '#F5F5F5', 'text' => '#616161'],
    'super_admin' => ['bg' => '#FFEBEE', 'text' => '#C62828'],
];
$userRole       = $user['role'] ?? 'viewer';
$isSuperAdmin   = $userRole === 'super_admin';
$roleBadgeCol   = $ROLE_BADGE_COLORS[$userRole] ?? ['bg' => '#F5F5F5', 'text' => '#616161'];
$roleBadgeLabel = $isSuperAdmin ? 'Super Admin' : ucfirst($userRole);

if (!defined('ADMIN_PANEL_URL')) define('ADMIN_PANEL_URL', '/admin/');

// Each page can set this before including the header to mark its
// own sidebar link active, e.g.  $activePage = 'invoices';
$activePage = $activePage ?? 'dashboard';

// Each page can set $pageTitle before including this file, shown in
// the <title> tag and used as the topbar breadcrumb default.
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($companyName) ?> — <?= htmlspecialchars($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/app.css">
<link rel="stylesheet" href="/assets/css/app-core.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
</head>
<body>

<!-- PHP → JS bridge: inject server data into window globals -->
<script>
const SERVER = {
  user:      <?= json_encode(['id' => (int)$user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role'], 'avatar' => $user['avatar'] ?? '']) ?>,
  settings:  <?= json_encode($settings) ?>,
  prefix:    <?= json_encode($prefix) ?>,
  estPrefix: <?= json_encode($estPrefix) ?>,
  appUrl:    '<?= rtrim(APP_URL, '/') ?>',
  year:      <?= date('Y') ?>,
  page:      <?= json_encode($activePage) ?>
};
</script>

<!-- ══════════════════════════════════════════
     SIDEBAR
══════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logo">
      <?php if (!empty($companyLogo)): ?>
        <img src="<?= htmlspecialchars($companyLogo) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">
      <?php else: ?>
        <?= strtoupper(substr($companyName, 0, 2)) ?>
      <?php endif; ?>
    </div>
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
    <?php
    // page => [icon, label, href, permission key]
    $businessType = $settings['business_type'] ?? 'both';
    $navMain = [
        'dashboard'     => ['fas fa-th-large',          'Dashboard',           '/pages/dashboard.php',    'menu.dashboard'],
        'invoices'      => ['fas fa-file-invoice',      'Invoices',            '/pages/invoices.php',     'menu.invoices'],
        'create'        => ['fas fa-plus-circle',       'New Invoice',         '/pages/create.php',       'menu.create'],
        'clients'       => ['fas fa-users',              'Clients',            '/pages/clients.php',      'menu.clients'],
        'customers'     => ['fas fa-address-book',       'Customers',          '/pages/customers.php',    'menu.customers', ['product','both']],
        'sales'         => ['fas fa-cash-register',      'Sales',              '/pages/sales.php',        'menu.sales', ['product','both']],
        'products'      => ['fas fa-box',                'Services / Products','/pages/products.php',     'menu.products'],
        'suppliers'     => ['fas fa-truck-loading',      'Suppliers',          '/pages/suppliers.php',    'menu.suppliers'],
        'purchases'     => ['fas fa-dolly',               'Purchases',         '/pages/purchases.php',    'menu.purchases'],
        'stock'         => ['fas fa-warehouse',          'Stock Ledger',        '/pages/stock.php',        'menu.stock'],
        'stock-history' => ['fas fa-clock-rotate-left',   'Stock History',       '/pages/stock-history.php', 'menu.stock_history'],
        'payments'      => ['fas fa-credit-card',        'Payments',           '/pages/payments.php',      'menu.payments'],
        'credit-notes'  => ['fas fa-file-circle-minus',  'Credit Notes',        '/pages/credit-notes.php', 'menu.credit_notes'],
        'reports'       => ['fas fa-chart-bar',          'Reports',            '/pages/reports.php',       'menu.reports'],
        'aging'         => ['fas fa-hourglass-half',     'Aging Report',        '/pages/aging.php',        'menu.aging'],
        'expenses'      => ['fas fa-wallet',              'Expenses',          '/pages/expenses.php',     'menu.expenses'],
        'tax'           => ['fas fa-landmark',            'Tax Summary',       '/pages/tax.php',           'menu.tax'],
    ];
    foreach ($navMain as $key => $navItem):
        [$icon, $label, $href, $perm] = $navItem;
        $allowedBizTypes = $navItem[4] ?? null; // null = visible for every business_type
        if ($allowedBizTypes !== null && !in_array($businessType, $allowedBizTypes, true)) continue;
        if (!($perms[$perm] ?? true)) continue;
        $active = $activePage === $key ? ' active' : '';
    ?>
    <a class="nav-item<?= $active ?>" href="<?= $href ?>">
      <i class="<?= $icon ?>"></i><span><?= $label ?></span>
      <?php if (in_array($key, ['invoices', 'credit-notes'], true)): ?>
      <span class="nav-badge" id="badge-<?= $key ?>" style="display:none">0</span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>

    <div class="nav-section-label">TOOLS</div>
    <?php
    $navTools = [
        'reminders'    => ['fas fa-bell',          'Reminders',      '/pages/reminders.php',    'menu.reminders'],
        'recurring'    => ['fas fa-sync-alt',      'Recurring',      '/pages/recurring.php',    'menu.recurring'],
        'portal'       => ['fas fa-link',          'Client Portal',  '/pages/portal.php',       'menu.portal'],
        'activity'     => ['fas fa-history',       'Activity Log',   '/pages/activity.php',     'menu.activity'],
        'templates'    => ['fas fa-palette',       'PDF Templates',  '/pages/templates.php',    'menu.templates'],
        'whatsapp'     => ['fab fa-whatsapp',      'WhatsApp Setup', '/pages/whatsapp.php',      'menu.whatsapp'],
        'email-setup'  => ['fas fa-envelope',      'Email Setup',    '/pages/email-setup.php',  'menu.email_setup'],
    ];
    foreach ($navTools as $key => [$icon, $label, $href, $perm]):
        if (!($perms[$perm] ?? true)) continue;
        $active = $activePage === $key ? ' active' : '';
    ?>
    <a class="nav-item<?= $active ?>" href="<?= $href ?>">
      <i class="<?= $icon ?>"></i><span><?= $label ?></span>
      <?php if (in_array($key, ['reminders', 'recurring'], true)): ?>
      <span class="nav-badge" id="badge-<?= $key ?>" style="display:none">0</span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>

    <div class="nav-section-label">ACCOUNT</div>
    <?php if ($perms['menu.team'] ?? ($userRole === 'owner' || $userRole === 'super_admin')): ?>
    <a class="nav-item<?= $activePage === 'team' ? ' active' : '' ?>" href="/pages/team.php">
      <i class="fas fa-user-friends"></i><span>Team</span>
    </a>
    <?php endif; ?>
    <?php if ($perms['menu.settings'] ?? true): ?>
    <a class="nav-item<?= $activePage === 'settings' ? ' active' : '' ?>" href="/pages/settings.php">
      <i class="fas fa-cog"></i><span>Settings</span>
    </a>
    <?php endif; ?>
    <?php if ($perms['menu.msglog'] ?? true): ?>
    <a class="nav-item<?= $activePage === 'msglog' ? ' active' : '' ?>" href="/pages/msglog.php">
      <i class="fas fa-comments"></i><span>Message Log</span>
      <span class="nav-badge" id="badge-msglog" style="display:none">0</span>
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <a class="sidebar-user" href="/pages/profile.php" title="My Profile" style="cursor:pointer;border-radius:10px;padding:6px 8px;margin:-6px -8px;transition:.18s;text-decoration:none">
      <div class="user-avatar" style="flex-shrink:0;border:2px solid rgba(255,255,255,.15)">
        <?php if (!empty($user['avatar'])): ?><img src="<?= htmlspecialchars($user['avatar']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:6px"><?php else: ?><?= strtoupper(substr($user['name'], 0, 2)) ?><?php endif; ?>
      </div>
      <div class="user-info" style="flex:1;min-width:0">
        <span class="user-name" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($user['name']) ?></span>
        <span class="user-role"><?= ucfirst($user['role']) ?></span>
      </div>
      <i class="fas fa-chevron-right" style="color:rgba(255,255,255,.3);font-size:11px;flex-shrink:0"></i>
    </a>
  </div>
</aside>

<!-- ══════════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════════ -->
<div class="main-wrap" id="mainWrap">

  <header class="topbar">
    <div class="topbar-left" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <div class="page-breadcrumb" id="breadcrumb"><?= htmlspecialchars($pageTitle) ?></div>
    </div>
    <div class="topbar-right">
      <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px 4px 10px;border-radius:20px;background:var(--bg);border:1px solid var(--border);font-size:14px;font-weight:700;color:var(--text2)" title="<?= htmlspecialchars($firmName) ?>">
        <i class="fas fa-building" style="font-size:11px;color:var(--muted)"></i>
        <span style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($firmName) ?></span>
      </span>
      <span id="topbarRoleBadge"
            style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;background:<?= $roleBadgeCol['bg'] ?>;color:<?= $roleBadgeCol['text'] ?>;font-size:12px;font-weight:600;<?= $isSuperAdmin ? 'cursor:pointer' : '' ?>"
            <?php if ($isSuperAdmin): ?>onclick="window.location.href='<?= ADMIN_PANEL_URL ?>'" title="Go to Admin Panel"<?php endif; ?>>
        <?php if ($isSuperAdmin): ?><i class="fas fa-shield-halved" style="font-size:11px"></i><?php endif; ?>
        <?= htmlspecialchars($roleBadgeLabel) ?>
      </span>
      <div class="search-bar">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search invoices, clients…" id="globalSearch" oninput="globalSearchFn(this.value)">
        <div class="search-results" id="searchResults"></div>
      </div>
      <?php if ($perms['menu.create'] ?? true): ?>
      <button class="topbar-btn" onclick="window.location.href='/pages/create.php'" title="New Invoice"><i class="fas fa-plus"></i></button>
      <?php endif; ?>
      <button class="wa-queued-pill" id="waQueuedPill" style="display:none" onclick="window.location.href='/pages/reminders.php'" title="WA reminders queued">
        <i class="fab fa-telegram"></i>
        <span id="waQueuedCount">0</span> WA reminders queued
      </button>
      <div class="notif-wrap" style="position:relative">
        <button class="notif-bell-btn" id="notifBellBtn" onclick="toggleNotifPanel(event)">
          <i class="fas fa-bell"></i>
          <span class="bell-dot" id="bellCount">3</span>
        </button>
        <div class="notif-panel" id="notifPanel">
          <div class="np-title">Notifications <span style="font-size:11px;font-weight:400;color:var(--muted)" id="notifTime"></span></div>
          <div id="notifItems"><div style="padding:12px 16px;color:var(--muted);font-size:13px;text-align:center">Loading notifications…</div></div>
          <div style="padding:10px 16px;text-align:center"><button class="btn btn-outline" style="font-size:11px;padding:5px 12px" onclick="clearNotifs()">Mark all read</button></div>
        </div>
      </div>

      <div class="notif-wrap" style="position:relative">
        <button class="user-chip" id="userChipBtn" onclick="toggleUserDropdown(event)">
          <div class="user-chip-avatar" id="chipAvatar">
            <?php if (!empty($user['avatar'])): ?><img src="<?= htmlspecialchars($user['avatar']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?><?= strtoupper(substr($user['name'], 0, 2)) ?><?php endif; ?>
          </div>
          <span class="user-chip-name"><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></span>
          <i class="fas fa-chevron-down user-chip-chevron" id="userChipChevron"></i>
        </button>
        <div class="user-dropdown" id="userDropdown">
          <div class="user-dropdown-header">
            <div class="udh-avatar" id="dropdownAvatar">
              <?php if (!empty($user['avatar'])): ?><img src="<?= htmlspecialchars($user['avatar']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?><?= strtoupper(substr($user['name'], 0, 2)) ?><?php endif; ?>
            </div>
            <div style="min-width:0">
              <div class="udh-name"><?= htmlspecialchars($user['name']) ?></div>
              <div class="udh-email"><?= htmlspecialchars($user['email']) ?></div>
              <span class="udh-role"><?= ucfirst($user['role']) ?></span>
            </div>
          </div>
          <div class="user-dropdown-body">
            <button class="ud-item" onclick="window.location.href='/pages/profile.php'">
              <i class="fas fa-user-edit"></i> My Profile
            </button>
            <button class="ud-item" onclick="window.location.href='/pages/settings.php'">
              <i class="fas fa-cog"></i> Settings
            </button>
            <div class="ud-divider"></div>
            <button class="ud-item danger" onclick="confirmLogout()">
              <i class="fas fa-sign-out-alt"></i> Sign Out
            </button>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- PAGE CONTENT -->
  <div class="pages-container">
    <div class="page active">
