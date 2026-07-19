<?php
// ================================================================
//  dashboard.php — lives at project ROOT (fixed during MPA cutover;
//  this file's own comment used to say pages/dashboard.php and its
//  includes used '../' paths as if it lived in a subdirectory, which
//  would have fatal-errored the moment anyone loaded it at root).
// ================================================================
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
requirePermission('menu.dashboard');

$user = currentUser();

$hourNow  = (int)date('G');
$greeting = $hourNow < 12 ? 'Good morning' : ($hourNow < 17 ? 'Good afternoon' : 'Good evening');

$activePage = 'dashboard';
$pageTitle  = 'Dashboard';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/wa-shared.js', '/assets/js/invoice-render-shared.js', '/assets/js/dashboard.js'];

include __DIR__ . '/includes/layout_header.php';
?>
      <!-- Greeting Header -->
      <div style="margin-bottom:16px">
        <div style="font-size:21px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px">
          <?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>! <span>👋</span>
        </div>
        <div style="font-size:12.5px;color:var(--muted);margin-top:4px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <span><?= htmlspecialchars($firmName) ?> · <?= date('l, d M Y') ?></span>
        </div>
      </div>
      <!-- Quick Actions -->
      <?php
        // Role-tailored quick-action order. Falls back to the default order
        // for any role not explicitly listed. Each button is still gated by
        // $perms so it always matches what that role can actually access.
        $qaCatalog = [
          'create'   => ['icon' => 'fas fa-plus',         'label' => 'New Invoice',   'page' => 'create',   'style' => 'btn-primary', 'perm' => 'menu.create'],
          'clients'  => ['icon' => 'fas fa-users',        'label' => 'Clients',       'page' => 'clients',  'style' => 'btn-outline', 'perm' => 'menu.clients'],
          'payments' => ['icon' => 'fas fa-credit-card',  'label' => 'Payments',      'page' => 'payments', 'style' => 'btn-outline', 'perm' => 'menu.payments'],
          'reports'  => ['icon' => 'fas fa-chart-bar',    'label' => 'Reports',       'page' => 'reports',  'style' => 'btn-outline', 'perm' => 'menu.reports'],
          'tax'      => ['icon' => 'fas fa-landmark',     'label' => 'Tax Summary',   'page' => 'tax',      'style' => 'btn-outline', 'perm' => 'menu.tax'],
          'expenses' => ['icon' => 'fas fa-wallet',       'label' => 'Expenses',      'page' => 'expenses', 'style' => 'btn-outline', 'perm' => 'menu.expenses'],
        ];
        $qaOrderByRole = [
          'accountant' => ['payments', 'tax', 'expenses', 'clients'],
        ];
        $qaOrder = $qaOrderByRole[$user['role'] ?? ''] ?? ['create', 'clients', 'payments', 'reports'];
      ?>
      <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
        <?php foreach ($qaOrder as $qaKey):
          $qa = $qaCatalog[$qaKey];
          if (!($perms[$qa['perm']] ?? true)) continue;
        ?>
        <a class="btn <?= $qa['style'] ?>" href="/pages/<?= $qa['page'] ?>.php"><i class="<?= $qa['icon'] ?>"></i> <?= $qa['label'] ?></a>
        <?php endforeach; ?>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <span id="dashOverdueAlert" style="display:none;padding:5px 12px;border-radius:20px;background:var(--red-bg);color:var(--red);font-size:12px;font-weight:700"></span>
          <span id="dashDueSoonAlert" style="display:none;padding:5px 12px;border-radius:20px;background:var(--amber-bg);color:var(--amber);font-size:12px;font-weight:700"></span>
          <a id="dashDraftAlert" style="display:none;padding:5px 12px;border-radius:20px;background:#F5F5F5;color:#616161;font-size:12px;font-weight:700;cursor:pointer;text-decoration:none" href="/pages/invoices.php?filter=draft"></a>
          <!-- NOTE: invoices.js must read ?filter=draft from the URL on load and
               preselect the status dropdown — this replaces the old
               showPage()+setTimeout() hack from the SPA. -->
        </div>
      </div>
      <!-- WhatsApp mini-KPI row: follows role permission only, shown on every
           plan including Pro (the plan exclusion below only affects the
           glowing automation banner beneath it). -->
      <?php if ($perms['menu.whatsapp'] ?? true): ?>
      <div id="dashWAKpiRow" style="margin-bottom:16px"></div>
      <?php endif; ?>
      <!-- WhatsApp Automation banner (finance/accountant roles don't need this;
           also hidden on the Pro plan specifically — a plan-tier decision,
           independent of role permissions, so it doesn't touch the
           WhatsApp Setup sidebar link, the KPI row above, or any other
           role's access) -->
      <?php $hideWACardForPlan = (($user['plan'] ?? '') === 'pro'); ?>
      <?php if (($perms['menu.whatsapp'] ?? true) && !$hideWACardForPlan): ?>
      <div id="dashWACard" style="margin-bottom:16px"></div>
      <?php endif; ?>
      <?php unset($hideWACardForPlan); ?>
      <div id="dashPartialCard" style="margin-bottom:16px"></div>
      <!-- Revenue Card (60%) + WA Activity Card (40%) — WA column collapses if role can't see WhatsApp -->
      <div style="display:grid;grid-template-columns:<?= ($perms['menu.whatsapp'] ?? true) ? '60fr 40fr' : '1fr' ?>;gap:14px;margin-bottom:16px;">
        <div id="s-revenue-card" style="background:var(--card);border-radius:14px;padding:16px 20px;box-shadow:var(--shadow)"></div>
        <div id="s-outstanding-card" style="display:none"></div>
        <?php if ($perms['menu.whatsapp'] ?? true): ?>
        <div id="dashWAActivityCard" style="background:var(--card);border-radius:14px;padding:16px 20px;box-shadow:var(--shadow)">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <span style="font-size:14px;font-weight:600;display:flex;align-items:center;gap:7px"><i class="fab fa-whatsapp" style="color:#25D366"></i> WA Activity</span>
            <span style="font-size:11px;color:var(--muted)" id="waActivityDate">Today</span>
          </div>
          <div id="waActivityRows"></div>
        </div>
        <?php endif; ?>
      </div>
      <div class="dash-stats-row">
        <div class="stat-card" data-color="amber">
          <div class="stat-icon" style="background:#fff8e1;color:#F9A825"><i class="fas fa-clock"></i></div>
          <div class="stat-body">
            <div class="stat-val" id="s-pending">₹0</div>
            <div class="stat-lbl">Pending</div>
            <div class="stat-trend neutral" id="s-pending-trend"><i class="fas fa-minus"></i> 0 invoices</div>
          </div>
        </div>
        <div class="stat-card" data-color="red">
          <div class="stat-icon" style="background:#fce4ec;color:#e53935"><i class="fas fa-exclamation-circle"></i></div>
          <div class="stat-body">
            <div class="stat-val" id="s-overdue">₹0</div>
            <div class="stat-lbl">Overdue</div>
            <div class="stat-trend down" id="s-overdue-trend"><i class="fas fa-arrow-down"></i> 0 invoices</div>
          </div>
        </div>
        <div class="stat-card" data-color="blue">
          <div class="stat-icon" style="background:#e3f2fd;color:#1976D2"><i class="fas fa-file-invoice"></i></div>
          <div class="stat-body">
            <div class="stat-val" id="s-total">0</div>
            <div class="stat-lbl">Total Invoices</div>
            <div class="stat-trend up" id="s-total-trend"><i class="fas fa-arrow-up"></i> 0 this month</div>
          </div>
        </div>
        <div class="stat-card" data-color="green">
          <div class="stat-icon" style="background:#e8f5e9;color:#388E3C"><i class="fas fa-users"></i></div>
          <div class="stat-body">
            <div class="stat-val" id="s-clients">0</div>
            <div class="stat-lbl">Active Clients</div>
            <div class="stat-trend up" id="s-clients-trend"><i class="fas fa-arrow-up"></i> 0 total</div>
          </div>
        </div>
        <?php if ($perms['menu.whatsapp'] ?? true): ?>
        <div class="stat-card" data-color="teal">
          <div class="stat-icon" style="background:#e8f5e9;color:#2E7D32"><i class="fab fa-whatsapp"></i></div>
          <div class="stat-body">
            <div class="stat-val" id="s-wa-today">0</div>
            <div class="stat-lbl">WA Sent Today</div>
            <div class="stat-trend neutral" id="s-wa-today-trend"><i class="fas fa-minus"></i> 0 failed</div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Row 1: Revenue Overview + Invoice Calendar + Status Split (all in one row) -->
      <div style="display:flex;gap:16px;margin-bottom:24px;align-items:stretch">
        <!-- Revenue Chart -->
        <div class="dash-card" style="flex:2;min-width:0">
          <div class="card-header">
            <span class="card-title">Revenue Overview</span>
            <div class="chart-filter">
              <button class="cf-btn active" onclick="switchChart('monthly',this)">Monthly</button>
              <button class="cf-btn" onclick="switchChart('weekly',this)">Weekly</button>
              <button class="cf-btn" onclick="switchChart('yearly',this)">Yearly</button>
            </div>
          </div>
          <div class="chart-wrap"><canvas id="revenueChart"></canvas></div>
        </div>
        <!-- Invoice Calendar -->
        <div class="dash-card" style="flex:1.2;min-width:0">
          <div class="card-header">
            <span class="card-title">Invoice Calendar</span>
            <div style="display:flex;gap:6px">
              <button class="cf-btn" onclick="calPrev()"><i class="fas fa-chevron-left"></i></button>
              <button class="cf-btn" onclick="calNext()"><i class="fas fa-chevron-right"></i></button>
            </div>
          </div>
          <div id="calendarWidget"></div>
          <div class="cal-legend">
            <span class="cal-dot" style="background:#F9A825"></span>Due
            <span class="cal-dot" style="background:#e53935;margin-left:10px"></span>Overdue
            <span class="cal-dot" style="background:#1976D2;margin-left:10px"></span>Paid
          </div>
        </div>
        <!-- Status Split Donut -->
        <div class="dash-card" style="flex:0 0 220px;min-width:0">
          <div class="card-header"><span class="card-title">Status Split</span></div>
          <div style="position:relative;height:160px"><canvas id="donutChart"></canvas></div>
          <div id="donutLegend" style="margin-top:6px"></div>
        </div>
      </div>

      <!-- Row 2: Quick Insights + Recent Activity + Top Clients -->
      <div style="display:flex;gap:16px;margin-bottom:24px;align-items:stretch">
        <!-- Quick KPIs -->
        <div class="dash-card" style="flex:0 0 200px;min-width:0">
          <div class="card-header"><span class="card-title">Quick Insights</span></div>
          <div id="dashQuickKpis"></div>
        </div>
        <!-- Recent Activity -->
        <div class="dash-card" style="flex:1;min-width:0">
          <div class="card-header">
            <span class="card-title">Recent Activity</span>
            <a class="cf-btn" href="/pages/invoices.php">View All</a>
          </div>
          <div id="dashRecentList"></div>
        </div>
        <!-- Top Clients -->
        <div class="dash-card" style="flex:0 0 210px;min-width:0">
          <div class="card-header"><span class="card-title">Top Clients</span></div>
          <div id="dashTopClients"></div>
        </div>
      </div>
<?php include __DIR__ . '/includes/layout_footer.php'; ?>
