<?php
// ================================================================
//  OPTMS Tech Invoice Manager — index.php (Production, domain root)
//  Works at: http://inv.optms.co.in/
// ================================================================

// ── Error handling: suppress display, log to file ──────────────
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ── Load config + auth ─────────────────────────────────────────
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$user = currentUser();
if (!$user) { doLogout(); header('Location: /auth/login.php'); exit; }

// ── Load company settings ──────────────────────────────────────
$settings = [];
try {
    $db   = getDB();
    $rows = $db->query('SELECT `key`, value FROM settings')->fetchAll();
    foreach ($rows as $r) $settings[$r['key']] = $r['value'];
} catch (Exception $e) {
    error_log('Settings load error: ' . $e->getMessage());
}

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
<style>

/* ══════════════════════════════════════════
   OPTMS Tech Invoice Manager – style.css
   Light Material + Public Sans
══════════════════════════════════════════ */

@import url('https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=JetBrains+Mono:wght@400;500;600&display=swap');

:root {
  --font: 'Public Sans', -apple-system, 'Segoe UI', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif;
  --mono: 'JetBrains Mono', 'Courier New', monospace;
  /* Palette */
  --teal:       #00897B;
  --teal-l:     #4DB6AC;
  --teal-bg:    #E0F2F1;
  --amber:      #F9A825;
  --amber-bg:   #FFF8E1;
  --red:        #E53935;
  --red-bg:     #FFEBEE;
  --blue:       #1976D2;
  --blue-l:     #42A5F5;
  --blue-bg:    #E3F2FD;
  --green:      #388E3C;
  --green-bg:   #E8F5E9;
  --purple:     #7B1FA2;
  --purple-bg:  #F3E5F5;
  --orange:     #E64A19;
  --orange-bg:  #FBE9E7;
  /* Neutrals */
  --bg:         #F5F6FA;
  --bg2:        #FFFFFF;
  --card:       #FFFFFF;
  --border:     #E8EAED;
  --border2:    #D1D5DB;
  --text:       #1A1A2E;
  --text2:      #374151;
  --muted:      #6B7280;
  --muted2:     #9CA3AF;
  --sidebar-w:  240px;
  --sidebar-bg: #1A2332;
  --sidebar-c:  rgba(255,255,255,.78);
  --sidebar-ac: #FFFFFF;
  --topbar-h:   60px;
  --r:          10px;
  --shadow:     0 1px 4px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.04);
  --shadow-md:  0 4px 20px rgba(0,0,0,.12);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 15px; }
body { font-family: var(--font); background: var(--bg); color: var(--text); min-height: 100vh; display: flex; overflow-x: hidden; }

/* ── SCROLLBAR ── */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }

/* Fix chart containers - prevent auto-scroll */
canvas { max-width: 100% !important; }

/* ══════════════════════════════════════════
   SIDEBAR
══════════════════════════════════════════ */
.sidebar {
  width: var(--sidebar-w);
  background: var(--sidebar-bg);
  display: flex;
  flex-direction: column;
  height: 100vh;
  position: fixed;
  left: 0; top: 0;
  z-index: 100;
  transition: width .25s cubic-bezier(.4,0,.2,1);
  overflow: hidden;
}
.sidebar.collapsed { width: 64px; }
.sidebar.collapsed .brand-text,
.sidebar.collapsed .nav-section-label,
.sidebar.collapsed .nav-item span,
.sidebar.collapsed .nav-badge,
.sidebar.collapsed .nav-dot,
.sidebar.collapsed .user-info { display: none; }
.sidebar.collapsed .nav-item { justify-content: center; padding: 11px; }
.sidebar.collapsed .brand-logo { margin: 0 auto; }

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 18px 16px;
  border-bottom: 1px solid rgba(255,255,255,.08);
  min-height: 68px;
}
.brand-logo {
  width: 36px; height: 36px;
  background: linear-gradient(135deg, var(--teal), var(--teal-l));
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font); font-weight: 800; font-size: 14px; color: #fff;
  flex-shrink: 0;
}
.brand-name { font-weight: 700; font-size: 15px; color: #fff; display: block; }
.brand-tagline { font-size: 10px; color: rgba(255,255,255,.4); display: block; }
.sidebar-collapse {
  margin-left: auto; background: none; border: none; color: rgba(255,255,255,.4);
  cursor: pointer; padding: 6px; border-radius: 6px; font-size: 14px;
  flex-shrink: 0; transition: .2s;
}
.sidebar-collapse:hover { color: #fff; background: rgba(255,255,255,.08); }

.sidebar-nav { flex: 1; overflow-y: auto; padding: 12px 8px; }
.nav-section-label {
  font-size: 9.5px; font-weight: 700; letter-spacing: 1.2px;
  color: rgba(255,255,255,.3); padding: 14px 10px 6px;
  text-transform: uppercase;
}
.nav-item {
  display: flex; align-items: center; gap: 11px;
  padding: 10px 12px; border-radius: 8px; cursor: pointer;
  color: var(--sidebar-c); font-size: 13px; font-weight: 500;
  transition: .2s; text-decoration: none; position: relative;
  margin-bottom: 2px; white-space: nowrap;
}
.nav-item i { width: 18px; text-align: center; font-size: 14px; flex-shrink: 0; }
.nav-item:hover { background: rgba(255,255,255,.07); color: var(--sidebar-ac); }
.nav-item.active { background: var(--teal); color: #fff; }
.nav-badge {
  margin-left: auto; background: rgba(255,255,255,.15);
  border-radius: 10px; padding: 2px 7px; font-size: 10px; font-weight: 700;
}
.nav-item.active .nav-badge { background: rgba(255,255,255,.25); }
.nav-dot { width: 7px; height: 7px; border-radius: 50%; margin-left: auto; }
.dot-green { background: #4CAF50; }

.sidebar-footer {
  padding: 12px 12px 16px;
  border-top: 1px solid rgba(255,255,255,.08);
}
.sidebar-user { display: flex; align-items: center; gap: 10px; }
.user-avatar {
  width: 34px; height: 34px; border-radius: 8px;
  background: var(--teal); color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700; flex-shrink: 0;
}
.user-name { font-size: 13px; font-weight: 600; color: #fff; display: block; }
.user-role { font-size: 10px; color: rgba(255,255,255,.4); display: block; }

/* ══════════════════════════════════════════
   MAIN WRAP
══════════════════════════════════════════ */
.main-wrap {
  margin-left: var(--sidebar-w);
  flex: 1;
  display: flex;
  flex-direction: column;
  transition: margin-left .25s cubic-bezier(.4,0,.2,1);
  min-height: 100vh;
}
.sidebar.collapsed ~ .main-wrap { margin-left: 64px; }

/* ── TOPBAR ── */
.topbar {
  height: var(--topbar-h);
  background: var(--card);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 28px;
  position: sticky; top: 0; z-index: 50;
  box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.page-breadcrumb { font-weight: 700; font-size: 16px; color: var(--text); }
.topbar-right { display: flex; align-items: center; gap: 12px; }
.search-bar {
  position: relative; display: flex; align-items: center;
  background: var(--bg); border: 1.5px solid var(--border); border-radius: 8px;
  padding: 7px 12px; gap: 8px; width: 260px;
}
.search-bar i { color: var(--muted2); font-size: 13px; }
.search-bar input { border: none; background: transparent; font-family: var(--font); font-size: 13px; width: 100%; outline: none; color: var(--text); }
.search-results {
  position: absolute; top: calc(100% + 6px); left: 0; right: 0;
  background: var(--card); border: 1px solid var(--border); border-radius: 8px;
  box-shadow: var(--shadow-md); z-index: 200; max-height: 280px; overflow-y: auto;
  display: none;
}
.search-results.open { display: block; }
.sr-item {
  padding: 10px 14px; cursor: pointer; display: flex; align-items: center; gap: 10px;
  font-size: 13px; border-bottom: 1px solid var(--border);
}
.sr-item:last-child { border: none; }
.sr-item:hover { background: var(--bg); }

.topbar-btn {
  width: 36px; height: 36px; border-radius: 8px;
  border: 1.5px solid var(--border); background: transparent;
  cursor: pointer; font-size: 14px; color: var(--muted); transition: .2s;
}
.topbar-btn:hover { background: var(--teal-bg); border-color: var(--teal); color: var(--teal); }
.notif-bell { position: relative; cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; }
.notif-bell i { font-size: 16px; color: var(--muted); }
.bell-dot {
  position: absolute; top: 2px; right: 2px; width: 18px; height: 18px;
  background: var(--red); border-radius: 50%; font-size: 10px; font-weight: 700;
  color: #fff; display: flex; align-items: center; justify-content: center; border: 2px solid var(--card);
}
.notif-panel {
  position: absolute; top: 54px; right: 16px; width: 300px;
  background: var(--card); border: 1px solid var(--border); border-radius: 12px;
  box-shadow: var(--shadow-md); z-index: 200; display: none; overflow: hidden;
}
.notif-panel.open { display: block; }
.np-title { padding: 14px 16px; font-weight: 700; font-size: 14px; border-bottom: 1px solid var(--border); }
.np-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 16px; font-size: 12.5px; border-bottom: 1px solid var(--border);
}
.np-item:last-child { border: none; }
.np-warn i { color: var(--amber); }
.np-info i { color: var(--teal); }

/* ══════════════════════════════════════════
   PAGES
══════════════════════════════════════════ */
.pages-container { flex: 1; overflow-y: auto; }
.page { display: none; padding: 24px 28px; }
.page.active { display: block; }

/* ══════════════════════════════════════════
   DASHBOARD
══════════════════════════════════════════ */
.dash-stats-row {
  display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px;
}
.stat-card {
  background: var(--card); border-radius: var(--r); padding: 18px 16px;
  border: 1px solid var(--border); display: flex; align-items: flex-start; gap: 14px;
  box-shadow: var(--shadow); transition: .2s;
}
.stat-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); }
.stat-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.stat-val { font-size: 22px; font-weight: 800; color: var(--text); line-height: 1; margin-bottom: 4px; }
.stat-lbl { font-size: 12px; color: var(--muted); margin-bottom: 6px; }
.stat-trend { font-size: 11px; font-weight: 600; }
.stat-trend.up { color: var(--green); }
.stat-trend.down { color: var(--red); }
.stat-trend.neutral { color: var(--muted); }

.dash-row-2 { display: flex; gap: 16px; margin-bottom: 24px; }
.dash-row-3 { display: flex; gap: 16px; }
.dash-card {
  background: var(--card); border-radius: var(--r); padding: 20px;
  border: 1px solid var(--border); box-shadow: var(--shadow);
}
.dash-chart-card { flex: 2; }
.dash-calendar-card { flex: 1.2; }
/* Fix chart height - prevent overflow/scroll */
.dash-chart-card canvas,
.dash-calendar-card canvas { display: block; }
.dash-chart-card .chart-wrap,
.reports-chart-wrap { position: relative; height: 220px; overflow: hidden; }
.reports-chart-wrap-lg { position: relative; height: 240px; overflow: hidden; }
.card-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 16px;
}
.card-title { font-size: 14px; font-weight: 700; color: var(--text); }
.chart-filter { display: flex; gap: 4px; }
.cf-btn {
  padding: 5px 12px; border-radius: 6px; border: 1.5px solid var(--border);
  background: transparent; font-family: var(--font); font-size: 11px; font-weight: 600;
  cursor: pointer; color: var(--muted); transition: .2s;
}
.cf-btn.active, .cf-btn:hover { background: var(--teal-bg); border-color: var(--teal); color: var(--teal); }

/* Calendar */
.cal-grid { display: grid; grid-template-columns: repeat(7,1fr); gap: 2px; }
.cal-day-name { text-align: center; font-size: 10px; font-weight: 700; color: var(--muted); padding: 4px 0; }
.cal-day {
  aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
  font-size: 11px; border-radius: 6px; cursor: default; position: relative;
  font-weight: 500; color: var(--text2);
}
.cal-day.today { background: var(--teal); color: #fff; font-weight: 800; border-radius: 6px; }
.cal-day.has-due { border: 2px solid var(--teal); border-radius: 6px; color: var(--teal); font-weight: 700; animation: statusGlow-due 2s ease-in-out infinite; }
.cal-day.has-overdue { border: 2px solid var(--red); border-radius: 6px; color: var(--red); font-weight: 700; background: var(--red-bg); animation: statusGlow-overdue 2s ease-in-out infinite; }
.cal-day.has-paid { border: 2px solid var(--green); border-radius: 6px; color: var(--green); font-weight: 600; animation: statusGlow-paid 2s ease-in-out infinite; }
.cal-day.has-due::after { content:''; position:absolute; bottom:2px; left:50%; transform:translateX(-50%); width:4px; height:4px; background:var(--teal); border-radius:50%; }
.cal-day.has-overdue::after { background: var(--red); content:''; position:absolute; bottom:2px; left:50%; transform:translateX(-50%); width:4px; height:4px; border-radius:50%; }
.cal-day.has-paid::after { content:'✓'; position:absolute; bottom:0px; left:50%; transform:translateX(-50%); font-size:7px; font-weight:900; color:var(--green); line-height:1; }
.cal-day.other-month { color: var(--muted2); }
.cal-month-title { text-align: center; font-weight: 700; font-size: 13px; margin-bottom: 10px; color: var(--text); }
.cal-legend { display: flex; align-items: center; margin-top: 10px; font-size: 11px; color: var(--muted); }
.cal-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 4px; }

/* Dashboard Recent */
.dash-recent-item {
  display: flex; align-items: center; gap: 12px; padding: 10px 0;
  border-bottom: 1px solid var(--border); font-size: 13px;
}
.dash-recent-item:last-child { border: none; }
.dri-avatar { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0; }
.dri-info { flex: 1; }
.dri-name { font-weight: 600; font-size: 13px; }
.dri-meta { font-size: 11px; color: var(--muted); }
.dri-amount { font-weight: 700; font-family: var(--mono); font-size: 13px; }

/* ══════════════════════════════════════════
   TOOLBAR & TABLE
══════════════════════════════════════════ */
.page-toolbar {
  display: flex; align-items: center; gap: 8px;
  margin-bottom: 18px; flex-wrap: nowrap; overflow-x: auto;
  padding-bottom: 4px;
}
.page-toolbar .toolbar-left {
  display: flex; align-items: center; gap: 8px; flex-wrap: nowrap;
}
.page-toolbar .toolbar-right {
  display: flex; align-items: center; gap: 8px; margin-left: auto; flex-shrink: 0;
}
.table-search {
  padding: 8px 12px; border-radius: 8px; border: 1.5px solid var(--border);
  background: var(--card); font-family: var(--font); font-size: 13px;
  color: var(--text); outline: none; min-width: 160px; max-width: 200px; transition: .2s; flex-shrink: 0;
}
.table-search:focus { border-color: var(--teal); }
.table-filter {
  padding: 8px 10px; border-radius: 8px; border: 1.5px solid var(--border);
  background: var(--card); font-family: var(--font); font-size: 12px;
  color: var(--text2); outline: none; cursor: pointer; transition: .2s; flex-shrink: 0;
  max-width: 140px;
}
.table-filter:focus { border-color: var(--teal); }

.table-card {
  background: var(--card); border-radius: var(--r);
  border: 1px solid var(--border); box-shadow: var(--shadow); overflow: hidden;
}
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead { background: var(--bg); }
.data-table th {
  padding: 11px 14px; text-align: left; font-size: 11px; font-weight: 700;
  color: var(--muted); text-transform: uppercase; letter-spacing: .7px;
  border-bottom: 1px solid var(--border); white-space: nowrap;
}
.data-table th.sortable { cursor: pointer; user-select: none; }
.data-table th.sortable:hover { color: var(--teal); }
.data-table td {
  padding: 12px 14px; border-bottom: 1px solid var(--border);
  font-size: 13px; color: var(--text2); vertical-align: middle;
}
.data-table tbody tr:last-child td { border: none; }
.data-table tbody tr:hover { background: #fafafa; }
.data-table input[type="checkbox"] { accent-color: var(--teal); width: 15px; height: 15px; cursor: pointer; }

/* Client avatar in table */
.client-cell { display: flex; align-items: center; gap: 10px; }
.cc-avatar {
  width: 32px; height: 32px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; color: #fff; flex-shrink: 0;
  overflow: hidden;
}
.cc-avatar img { width: 100%; height: 100%; object-fit: cover; }
.cc-name { font-weight: 600; }
.cc-sub { font-size: 11px; color: var(--muted); }

/* Actions cell */
.action-cell { display: flex; align-items: center; gap: 6px; }
.act-btn {
  width: 30px; height: 30px; border-radius: 7px; border: none;
  background: var(--bg); cursor: pointer; font-size: 12px; color: var(--muted);
  display: flex; align-items: center; justify-content: center; transition: .2s;
}
.act-btn:hover { background: var(--teal-bg); color: var(--teal); }
.act-btn.del:hover { background: var(--red-bg); color: var(--red); }
.act-btn.menu-btn { position: relative; }

/* Row menu */
.row-menu {
  position: fixed; background: var(--card); border: 1px solid var(--border);
  border-radius: 10px; box-shadow: var(--shadow-md); z-index: 500;
  min-width: 180px; display: none; overflow: hidden;
}
.row-menu.open { display: block; }
.rm-item {
  padding: 10px 16px; cursor: pointer; font-size: 13px;
  display: flex; align-items: center; gap: 10px; transition: .15s;
  border-bottom: 1px solid var(--border);
}
.rm-item:last-child { border: none; }
.rm-item:hover { background: var(--bg); color: var(--teal); }
.rm-danger:hover { background: var(--red-bg); color: var(--red); }
.rm-item i { width: 16px; }

/* Table footer */
.table-footer {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px; border-top: 1px solid var(--border); background: var(--bg);
}
.tf-info { font-size: 12px; color: var(--muted); }
.pagination { display: flex; gap: 4px; }
.pg-btn {
  min-width: 32px; height: 32px; border-radius: 7px; border: 1.5px solid var(--border);
  background: transparent; cursor: pointer; font-size: 12px; font-weight: 600;
  color: var(--text2); padding: 0 8px; transition: .2s; font-family: var(--font);
}
.pg-btn:hover { background: var(--teal-bg); border-color: var(--teal); color: var(--teal); }
.pg-btn.active { background: var(--teal); border-color: var(--teal); color: #fff; }
.pg-btn:disabled { opacity: .4; cursor: not-allowed; }

/* Badges */
.badge {
  padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
  letter-spacing: .3px; font-family: var(--font); display: inline-block;
}
.badge-paid      { background: #E8F5E9; color: #2E7D32; }
.badge-pending   { background: #FFF8E1; color: #F57F17; }
.badge-partial   { background: #FFF3E0; color: #E65100; font-weight:700; }
.badge-overdue   { background: #FFEBEE; color: #C62828; }
.badge-draft     { background: #F5F5F5; color: #616161; }
.badge-cancelled { background: #FFCDD2; color: #B71C1C; font-weight:700; }

/* ══════════════════════════════════════════
   CREATE INVOICE
══════════════════════════════════════════ */
.create-layout { display: grid; grid-template-columns: 1fr 400px; gap: 0; min-height: calc(100vh - var(--topbar-h)); margin: -24px -28px; }
.create-form { padding: 28px 28px; overflow-y: auto; border-right: 1px solid var(--border); max-height: calc(100vh - var(--topbar-h)); }
.create-preview { display: flex; flex-direction: column; max-height: calc(100vh - var(--topbar-h)); background: #f0f2f5; }

.form-section {
  background: var(--card); border-radius: var(--r); padding: 20px;
  border: 1px solid var(--border); margin-bottom: 16px; box-shadow: var(--shadow);
}
.fs-title {
  font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px;
  color: var(--teal); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
  padding-bottom: 10px; border-bottom: 1px solid var(--border);
}

.form-grid { display: grid; gap: 14px; }
.g1 { grid-template-columns: 1fr; }
.g2 { grid-template-columns: 1fr 1fr; }
.g3 { grid-template-columns: 1fr 1fr 1fr; }
.g-full { grid-column: 1 / -1; }

.field { display: flex; flex-direction: column; gap: 5px; }
.field label { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .7px; }
input, select, textarea {
  padding: 9px 12px; border-radius: 8px; border: 1.5px solid var(--border);
  background: var(--bg); font-family: var(--font); font-size: 13.5px;
  color: var(--text); transition: .2s; width: 100%; outline: none;
}
input:focus, select:focus, textarea:focus { border-color: var(--teal); background: #fff; box-shadow: 0 0 0 3px rgba(0,137,123,.1); }
textarea { resize: vertical; min-height: 72px; }
select { cursor: pointer; }

/* Status radio pills */
.status-toggle-row { display: flex; gap: 8px; flex-wrap: wrap; }
.status-radio { cursor: pointer; }
.status-radio input { display: none; }
.sr-pill {
  padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 700;
  border: 2px solid; transition: .2s; display: inline-block; font-family: var(--font);
}
.sr-pill.draft      { border-color: #9E9E9E; color: #757575; }
.sr-pill.pending    { border-color: var(--amber); color: #795548; }
.sr-pill.paid       { border-color: var(--green); color: var(--green); }
.sr-pill.overdue    { border-color: var(--red); color: var(--red); }
.sr-pill.cancelled  { border-color: #B71C1C; color: #B71C1C; }
.status-radio input:checked + .sr-pill.draft      { background: #9E9E9E; color: #fff; }
.status-radio input:checked + .sr-pill.pending    { background: var(--amber); color: #fff; }
.status-radio input:checked + .sr-pill.paid       { background: var(--green); color: #fff; }
.status-radio input:checked + .sr-pill.overdue    { background: var(--red); color: #fff; }
.status-radio input:checked + .sr-pill.cancelled  { background: #B71C1C; color: #fff; }

/* ── REDESIGNED LINE ITEMS ── */
.items-head-row {
  display: grid;
  grid-template-columns: minmax(140px,1fr) minmax(90px,110px) 62px minmax(80px,95px) minmax(90px,105px) 76px minmax(90px,105px) 36px;
  gap: 0;
  padding: 0;
  background: #EEF0F4;
  border-radius: 10px 10px 0 0;
  margin-bottom: 0;
  font-size: 9.5px; font-weight: 700; color: #6B7280;
  text-transform: uppercase; letter-spacing: .9px;
  border: 1px solid var(--border);
  overflow: hidden;
}
.items-head-row span {
  padding: 9px 10px;
  border-right: 1px solid var(--border2);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.items-head-row span:last-child { border-right: none; }

#itemsList { border: 1px solid var(--border); border-top: none; border-radius: 0 0 10px 10px; overflow: hidden; }

.item-row {
  display: grid;
  grid-template-columns: minmax(140px,1fr) minmax(90px,110px) 62px minmax(80px,95px) minmax(90px,105px) 76px minmax(90px,105px) 36px;
  gap: 0;
  align-items: stretch;
  padding: 0;
  border-bottom: 1px solid var(--border);
  background: var(--card);
  transition: background .15s;
  position: relative;
  min-height: 40px;
}
.item-row:last-child { border-bottom: none; }
.item-row:hover { background: #f7f9ff; }
.item-row::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 3px;
  background: transparent;
  transition: background .2s;
  z-index: 1;
}
.item-row:hover::before { background: var(--teal); }

.item-row input,
.item-row select {
  border: none;
  border-radius: 0;
  background: transparent;
  padding: 10px 10px;
  font-size: 12.5px;
  height: 100%;
  width: 100%;
  min-width: 0;
  outline: none;
  transition: background .15s;
  box-sizing: border-box;
}
.item-row input:focus,
.item-row select:focus {
  background: #f0faf9;
  box-shadow: inset 0 0 0 2px rgba(0,137,123,.18);
}
.item-row select { cursor: pointer; font-size: 12px; }

.item-desc  { border-right: 1px solid var(--border); min-width: 0; overflow: hidden; }
.item-desc input { font-weight: 500; padding-left: 14px; }
.item-type  { border-right: 1px solid var(--border); min-width: 0; overflow: hidden; }
.item-type select { padding: 10px 6px; }
.item-qty   { border-right: 1px solid var(--border); }
.item-qty input { text-align: center; padding: 10px 6px; }
.item-rate  { border-right: 1px solid var(--border); }
.item-rate input { text-align: center; padding: 10px 12px; }

.item-amount {
  font-weight: 600; font-family: var(--mono); font-size: 12px;
  color: var(--text2); text-align: right;
  padding: 0 12px; border-right: 1px solid var(--border);
  display: flex; align-items: center; justify-content: flex-end;
  background: #F8F9FA;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.item-gst { border-right: 1px solid var(--border); min-width: 0; }
.item-gst select { text-align: center; padding: 10px 10px; font-size: 11.5px; width: 100%; }

.item-total {
  font-weight: 700; font-family: var(--mono); font-size: 12px;
  color: var(--teal); text-align: right;
  padding: 0 10px; border-right: 1px solid var(--border);
  display: flex; align-items: center; justify-content: flex-end;
  background: #E8F5F3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.item-del {
  width: 36px; border-radius: 0;
  border: none; background: transparent;
  color: var(--muted2); cursor: pointer; font-size: 11px;
  transition: .2s;
  display: flex; align-items: center; justify-content: center;
}
.item-del:hover { background: var(--red-bg); color: var(--red); }

.items-actions {
  display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap;
}
.add-line-btn {
  padding: 8px 16px;
  border: 1.5px dashed var(--border2);
  background: transparent;
  color: var(--muted);
  border-radius: 8px; cursor: pointer; font-size: 12.5px;
  font-weight: 600; font-family: var(--font); transition: .2s;
}
.add-line-btn:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-bg); border-style: solid; }

/* Totals panel */
.totals-panel {
  margin-top: 16px; background: var(--bg); border-radius: 8px;
  padding: 14px 16px; border: 1px solid var(--border);
}
.tp-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 6px 0; font-size: 13px; border-bottom: 1px solid var(--border);
}
.tp-row:last-child { border: none; }
.tp-row.grand { font-size: 16px; font-weight: 800; color: var(--teal); padding-top: 10px; margin-top: 4px; }
.tp-row code { font-family: var(--mono); font-size: 13px; font-weight: 600; }
.tp-row.grand code { font-size: 17px; }
.tp-row code.neg { color: var(--red); }
.tp-row code.pos { color: var(--green); }
.inline-num { width: 56px; padding: 3px 6px; display: inline-block; margin-left: 6px; }
.inline-sel { width: 70px; padding: 3px 6px; display: inline-block; margin-left: 6px; }

/* Preview panel */
.preview-toolbar {
  padding: 12px 16px; background: var(--card); border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;
}
.preview-label { font-size: 12px; font-weight: 700; color: var(--teal); display: flex; align-items: center; gap: 7px; }
.preview-scroll { flex: 1; overflow: auto; padding: 16px; background: #e8eaed; }
.preview-scroll-inner { width: 560px; margin: 0 auto; min-height: 200px; }
.preview-invoice-wrap { 
  transform-origin: top left;
  width: 794px;
}
.mini-select { padding: 5px 8px; border-radius: 6px; border: 1.5px solid var(--border); font-family: var(--font); font-size: 11px; background: var(--bg); cursor: pointer; }
.mini-btn { padding: 6px 10px; border-radius: 6px; border: 1.5px solid var(--border); background: transparent; cursor: pointer; font-size: 12px; color: var(--muted); transition: .2s; }
.mini-btn:hover { border-color: var(--teal); color: var(--teal); }

.preview-actions { padding: 14px 16px; background: var(--card); border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; }
.btn-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.w100 { width: 100%; }

/* ══════════════════════════════════════════
   BUTTONS
══════════════════════════════════════════ */
.btn {
  padding: 9px 18px; border-radius: 8px; font-family: var(--font);
  font-size: 13px; font-weight: 600; cursor: pointer; border: none;
  display: inline-flex; align-items: center; justify-content: center; gap: 7px;
  transition: .2s; text-decoration: none; white-space: nowrap;
}
.btn-primary   { background: var(--blue); color: #fff; }
.btn-primary:hover { background: #1565C0; }
.btn-success   { background: var(--teal); color: #fff; }
.btn-success:hover { background: var(--teal-d, #00695C); filter: brightness(1.05); }
.btn-outline   { background: transparent; border: 1.5px solid var(--border2); color: var(--text2); }
.btn-outline:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-bg); }
.btn-whatsapp  { background: #25D366; color: #fff; }
.btn-whatsapp:hover { background: #1DA851; }
@keyframes waGlow {
  0%,100% { box-shadow: 0 0 8px #25D36638, 0 0 18px #25D36615; border-color: #25D36688; }
  50%      { box-shadow: 0 0 16px #25D36670, 0 0 32px #25D36630; border-color: #25D366; }
}
@keyframes statusGlow-due {
  0%,100% { box-shadow: 0 0 6px #00897B55; } 50% { box-shadow: 0 0 14px #00897B99; }
}
@keyframes statusGlow-overdue {
  0%,100% { box-shadow: 0 0 6px #C6282855; } 50% { box-shadow: 0 0 14px #C6282899; }
}
@keyframes statusGlow-paid {
  0%,100% { box-shadow: 0 0 6px #388E3C55; } 50% { box-shadow: 0 0 14px #388E3C99; }
}
.btn-email     { background: var(--blue-bg); color: var(--blue); border: 1.5px solid #90CAF9; }
.btn-email:hover { background: var(--blue); color: #fff; }
.btn-danger    { background: var(--red-bg); color: var(--red); border: 1.5px solid #FFCDD2; }
.btn-danger:hover { background: var(--red); color: #fff; }

/* ══════════════════════════════════════════
   CLIENTS
══════════════════════════════════════════ */
.clients-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 16px; }
.client-card {
  background: var(--card); border-radius: var(--r); padding: 20px;
  border: 1px solid var(--border); box-shadow: var(--shadow); cursor: pointer;
  transition: .2s; position: relative; overflow: hidden;
}
.client-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
.client-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.cc-head { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.cc-big-avatar {
  width: 48px; height: 48px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; font-weight: 800; color: #fff; overflow: hidden; flex-shrink: 0;
}
.cc-big-avatar img { width: 100%; height: 100%; object-fit: cover; }
.cc-org { font-weight: 700; font-size: 15px; color: var(--text); }
.cc-contact { font-size: 12px; color: var(--muted); margin-top: 2px; }
.cc-stats { display: flex; gap: 0; background: var(--bg); border-radius: 8px; overflow: hidden; }
.cc-stat { flex: 1; padding: 10px; text-align: center; border-right: 1px solid var(--border); }
.cc-stat:last-child { border: none; }
.cc-stat-val { font-weight: 800; font-size: 16px; }
.cc-stat-lbl { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }
.cc-footer { margin-top: 12px; display: flex; gap: 8px; }

/* ══════════════════════════════════════════
   SETTINGS
══════════════════════════════════════════ */
.settings-wrap { max-width: 760px; }
.settings-block {
  background: var(--card); border-radius: var(--r); padding: 22px;
  border: 1px solid var(--border); box-shadow: var(--shadow); margin-bottom: 18px;
}
.sb-title {
  font-size: 14px; font-weight: 700; color: var(--text);
  margin-bottom: 18px; display: flex; align-items: center; gap: 8px;
  padding-bottom: 12px; border-bottom: 1px solid var(--border);
}
.toggle-list { margin-top: 16px; }
.toggle-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
.toggle-item:last-child { border: none; }
.tog {
  width: 42px; height: 24px; background: var(--border2); border-radius: 12px;
  position: relative; cursor: pointer; transition: .3s; flex-shrink: 0;
}
.tog.on { background: var(--teal); }
.tog.saving { box-shadow: 0 0 0 3px rgba(0,137,123,.35); transition: box-shadow .2s; }
.tog::after {
  content: ''; position: absolute; width: 18px; height: 18px; background: #fff;
  border-radius: 50%; top: 3px; left: 3px; transition: .3s;
  box-shadow: 0 1px 4px rgba(0,0,0,.2);
}
.tog.on::after { left: 21px; }
.backup-actions { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; }
.backup-btn {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 10px; padding: 20px; border-radius: 10px; border: 2px dashed var(--border2);
  background: var(--bg); cursor: pointer; font-size: 12.5px; font-weight: 600;
  font-family: var(--font); color: var(--muted); transition: .2s;
}
.backup-btn i { font-size: 24px; }
.backup-btn:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-bg); }

/* ══════════════════════════════════════════
   TEMPLATES GRID
══════════════════════════════════════════ */
.templates-intro { font-size: 13.5px; color: var(--muted); margin-bottom: 20px; }
.templates-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
.tpl-card {
  background: var(--card); border-radius: var(--r); border: 2px solid var(--border);
  overflow: hidden; cursor: pointer; transition: .2s; box-shadow: var(--shadow);
}
.tpl-card:hover { border-color: var(--teal); box-shadow: var(--shadow-md); }
.tpl-card.active-tpl { border-color: var(--teal); }
.tpl-thumb { height: 140px; display: flex; align-items: center; justify-content: center; font-size: 11px; }
.tpl-info { padding: 12px 14px; border-top: 1px solid var(--border); }
.tpl-name { font-weight: 700; font-size: 13px; margin-bottom: 6px; }
.tpl-btns { display: flex; gap: 6px; }

/* ══════════════════════════════════════════
   MODALS
══════════════════════════════════════════ */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 300;
  display: none; align-items: center; justify-content: center; padding: 20px;
  backdrop-filter: blur(3px);
}
.modal-overlay.open { display: flex; }
.modal {
  background: var(--card); border-radius: 14px; width: 100%;
  box-shadow: 0 20px 60px rgba(0,0,0,.2); animation: modalIn .2s ease;
  display: flex; flex-direction: column;
}
@keyframes modalIn { from { opacity:0; transform:scale(.95) translateY(10px); } to { opacity:1; transform:none; } }
.modal-sm  { max-width: 420px; }
.modal-md  { max-width: 580px; }
.modal-xl  { max-width: 860px; }
.modal-header {
  padding: 18px 22px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  font-size: 15px; font-weight: 700;
}
.modal-close {
  width: 32px; height: 32px; border-radius: 7px; border: none;
  background: var(--bg); cursor: pointer; font-size: 14px; color: var(--muted); transition: .2s;
}
.modal-close:hover { background: var(--red-bg); color: var(--red); }
.modal-body { flex: 1; }
.modal-footer {
  padding: 14px 22px; border-top: 1px solid var(--border);
  display: flex; gap: 10px; justify-content: flex-end;
}

/* ══════════════════════════════════════════
   TOAST
══════════════════════════════════════════ */
.toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
.toast {
  background: var(--card); border: 1px solid var(--border); border-radius: 10px;
  padding: 13px 18px; display: flex; align-items: center; gap: 10px;
  font-size: 13px; font-weight: 600; box-shadow: var(--shadow-md);
  animation: toastIn .3s ease;
  min-width: 280px; max-width: 360px;
}
@keyframes toastIn { from { opacity:0; transform:translateY(20px) scale(.95); } to { opacity:1; transform:none; } }
.toast.success { border-left: 4px solid var(--teal); }
.toast.error   { border-left: 4px solid var(--red); }
.toast.info    { border-left: 4px solid var(--blue); }
.toast.warning { border-left: 4px solid var(--amber); }
.toast i { font-size: 16px; }
.toast.success i { color: var(--teal); }
.toast.error i   { color: var(--red); }
.toast.info i    { color: var(--blue); }
.toast.warning i { color: var(--amber); }

/* ══════════════════════════════════════════
   PRODUCT PICKER
══════════════════════════════════════════ */
.pp-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px; border-radius: 8px; border: 1.5px solid var(--border);
  margin-bottom: 8px; cursor: pointer; transition: .2s;
}
.pp-item:hover { border-color: var(--teal); background: var(--teal-bg); }
.pp-name { font-weight: 600; font-size: 13px; }
.pp-rate { font-family: var(--mono); font-size: 13px; font-weight: 700; color: var(--teal); }

/* ══════════════════════════════════════════
   INVOICE PRINT CSS (injected into print window)
══════════════════════════════════════════ */
/* Used only in print window — defined in JS */

/* Sidebar external toggle button */
.sidebar-toggle-btn {
  position: fixed;
  left: calc(var(--sidebar-w) - 1px);
  top: 16px;
  width: 28px; height: 28px;
  background: var(--sidebar-bg);
  border: 1.5px solid rgba(255,255,255,.15);
  border-left: none;
  color: rgba(255,255,255,.6);
  border-radius: 0 7px 7px 0;
  cursor: pointer;
  z-index: 101;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px;
  transition: left .25s cubic-bezier(.4,0,.2,1), background .2s;
}
.sidebar-toggle-btn:hover { background: var(--teal); color: #fff; }
.sidebar.collapsed ~ * .sidebar-toggle-btn,
.sidebar-toggle-btn.collapsed-pos { left: 63px; }

/* PDF opts grid */
.pdf-opts-grid {
  display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px;
}
.pdf-opt {
  display: flex; align-items: center; gap: 7px;
  padding: 8px 10px; border-radius: 7px; border: 1.5px solid var(--border);
  background: var(--bg); cursor: pointer; font-size: 12px; font-weight: 500;
  transition: .2s; white-space: nowrap;
}
.pdf-opt:hover { border-color: var(--teal); background: var(--teal-bg); }
.pdf-opt input { accent-color: var(--teal); cursor: pointer; }

/* Notification bell button */
.notif-bell-btn {
  position: relative; width: 36px; height: 36px;
  border: 1.5px solid var(--border); border-radius: 8px;
  background: transparent; cursor: pointer; font-size: 15px;
  color: var(--muted); display: flex; align-items: center; justify-content: center;
  transition: .2s;
}
.notif-bell-btn:hover { background: var(--amber-bg); border-color: var(--amber); color: var(--amber); }
.notif-bell-btn .bell-dot {
  position: absolute; top: -4px; right: -4px; width: 18px; height: 18px;
  background: var(--red); border-radius: 50%; font-size: 10px; font-weight: 700;
  color: #fff; display: flex; align-items: center; justify-content: center;
  border: 2px solid var(--card);
}
.notif-panel {
  position: absolute; top: calc(100% + 8px); right: 0; width: 310px;
  background: var(--card); border: 1px solid var(--border); border-radius: 12px;
  box-shadow: var(--shadow-md); z-index: 600; display: none; overflow: hidden;
}
.notif-panel.open { display: block; }
.dl-item { display: flex; align-items: center; gap: 8px; font-size: 12px; margin-bottom: 6px; }
.dl-dot { width: 10px; height: 10px; border-radius: 3px; }
.dl-label { flex: 1; color: var(--muted); }
.dl-val { font-weight: 700; font-family: var(--mono); }


/* ── PHP-build extras ── */
.nav-item[href*="logout"] { color: rgba(255,100,100,.75) !important; }
.nav-item[href*="logout"]:hover { color: #ff6b6b !important; background:rgba(255,80,80,.1) !important; }
</style>
</head>
<body>

<!-- PHP → JS bridge: inject server data into window globals -->
<script>
const SERVER = {
  user:     <?= json_encode(['id'=>(int)$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role'],'avatar'=>$user['avatar']??'']) ?>,
  settings: <?= json_encode($settings) ?>,
  prefix:   <?= json_encode($prefix) ?>,
  appUrl:   '<?= rtrim(APP_URL, '/') ?>',
  year:     <?= date('Y') ?>,
  // WA settings pre-loaded from DB for instant toggle restore
  wa: {
    token:         <?= json_encode($settings['wa_token']        ?? '') ?>,
    pid:           <?= json_encode($settings['wa_pid']          ?? '') ?>,
    bid:           <?= json_encode($settings['wa_bid']          ?? '') ?>,
    test_phone:    <?= json_encode($settings['wa_test_phone']   ?? '') ?>,
    remind_days:   <?= json_encode($settings['wa_remind_days']  ?? '3') ?>,
    max_followup:  <?= json_encode($settings['wa_max_followup'] ?? '3') ?>,
    tpl_inv:       <?= json_encode($settings['wa_tpl_inv']      ?? '') ?>,
    tpl_paid:      <?= json_encode($settings['wa_tpl_paid']     ?? '') ?>,
    tpl_partial:   <?= json_encode($settings['wa_tpl_partial']  ?? '') ?>,
    tpl_remind:    <?= json_encode($settings['wa_tpl_remind']   ?? '') ?>,
    tpl_overdue:   <?= json_encode($settings['wa_tpl_overdue']  ?? '') ?>,
    tpl_followup:  <?= json_encode($settings['wa_tpl_followup'] ?? '') ?>,
    tpl_festival:  <?= json_encode($settings['wa_tpl_festival'] ?? '') ?>,
    auto_inv:      <?= json_encode($settings['wa_auto_inv']     ?? '0') ?>,
    auto_paid:     <?= json_encode($settings['wa_auto_paid']    ?? '1') ?>,
    auto_partial:  <?= json_encode($settings['wa_auto_partial'] ?? '1') ?>,
    auto_remind:   <?= json_encode($settings['wa_auto_remind']  ?? '1') ?>,
    auto_overdue:  <?= json_encode($settings['wa_auto_overdue'] ?? '1') ?>,
    auto_followup: <?= json_encode($settings['wa_auto_followup']?? '0') ?>,
  }
};
</script>

<!-- ══════════════════════════════════════════
     SIDEBAR
══════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logo"><?= strtoupper(substr($companyName,0,2)) ?></div>
    <div class="brand-text">
      <span class="brand-name"><?= htmlspecialchars($companyName) ?></span>
      <span class="brand-tagline">Invoice Manager</span>
    </div>
  </div>
  <!-- Sidebar toggle OUTSIDE brand so always visible -->
  <button class="sidebar-toggle-btn" id="sidebarToggle" onclick="toggleSidebar()" title="Toggle Sidebar">
    <i class="fas fa-bars" id="toggleIcon"></i>
  </button>

  <nav class="sidebar-nav">
    <div class="nav-section-label">MAIN</div>
    <a class="nav-item active" data-page="dashboard" onclick="showPage('dashboard',this)">
      <i class="fas fa-th-large"></i><span>Dashboard</span>
    </a>
    <a class="nav-item" data-page="invoices" onclick="showPage('invoices',this)">
      <i class="fas fa-file-invoice"></i><span>Invoices</span>
      <span class="nav-badge" id="badge-invoices">34</span>
    </a>
    <a class="nav-item" data-page="create" onclick="showPage('create',this)">
      <i class="fas fa-plus-circle"></i><span>New Invoice</span>
    </a>
    <a class="nav-item" data-page="clients" onclick="showPage('clients',this)">
      <i class="fas fa-users"></i><span>Clients</span>
    </a>
    <a class="nav-item" data-page="products" onclick="showPage('products',this)">
      <i class="fas fa-box"></i><span>Services / Products</span>
    </a>
    <a class="nav-item" data-page="payments" onclick="showPage('payments',this)">
      <i class="fas fa-credit-card"></i><span>Payments</span>
    </a>
    <a class="nav-item" data-page="reports" onclick="showPage('reports',this)">
      <i class="fas fa-chart-bar"></i><span>Reports</span>
    </a>
    <div class="nav-section-label">TOOLS</div>
    <a class="nav-item" data-page="templates" onclick="showPage('templates',this)">
      <i class="fas fa-palette"></i><span>PDF Templates</span>
    </a>
    <a class="nav-item" data-page="whatsapp" onclick="showPage('whatsapp',this)">
      <i class="fab fa-whatsapp"></i><span>WhatsApp Setup</span>
      <span class="nav-dot dot-green"></span>
    </a>
    <a class="nav-item" data-page="email-setup" onclick="showPage('email-setup',this)">
      <i class="fas fa-envelope"></i><span>Email Setup</span>
    </a>
    <div class="nav-section-label">ACCOUNT</div>
    <a class="nav-item" data-page="settings" onclick="showPage('settings',this)">
      <i class="fas fa-cog"></i><span>Settings</span>
    </a>
    <a class="nav-item" data-page="backup" onclick="showPage('backup',this)">
      <i class="fas fa-database"></i><span>Backup & Export</span>
    </a>
    <a class="nav-item" data-page="msglog" onclick="showPage('msglog',this)">
      <i class="fas fa-comments"></i><span>Message Log</span>
      <span class="nav-badge" id="badge-msglog" style="display:none">0</span>
    </a>
    <a class="nav-item" href="/auth/logout.php" style="margin-top:6px;padding-top:10px;border-top:1px solid rgba(255,255,255,.1)"><i class="fas fa-sign-out-alt" style="color:#ff8a80"></i><span style="color:#ff8a80">Logout</span></a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($user['name'],0,2)) ?></div>
      <div class="user-info"><span class="user-name"><?= htmlspecialchars($user['name']) ?></span><span class="user-role"><?= ucfirst($user['role']) ?></span></div>
    </div>
  </div>
</aside>

<!-- ══════════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════════ -->
<div class="main-wrap" id="mainWrap">

  <!-- Top Bar -->
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
          <span class="bell-dot" id="bellCount">3</span>
        </button>
        <div class="notif-panel" id="notifPanel">
          <div class="np-title">Notifications <span style="font-size:11px;font-weight:400;color:var(--muted)" id="notifTime"></span></div>
          <div id="notifItems"><div style="padding:12px 16px;color:var(--muted);font-size:13px;text-align:center">Loading notifications…</div></div>
          <div style="padding:10px 16px;text-align:center"><button class="btn btn-outline" style="font-size:11px;padding:5px 12px" onclick="clearNotifs()">Mark all read</button></div>
        </div>
      </div>
    </div>
  </header>

  <!-- PAGES -->
  <div class="pages-container">

    <!-- ─────────── DASHBOARD ─────────── -->
    <div id="page-dashboard" class="page active">
      <!-- Quick Actions -->
      <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
        <button class="btn btn-primary" onclick="showPage('create',null)"><i class="fas fa-plus"></i> New Invoice</button>
        <button class="btn btn-outline" onclick="showPage('clients',null)"><i class="fas fa-users"></i> Clients</button>
        <button class="btn btn-outline" onclick="showPage('payments',null)"><i class="fas fa-credit-card"></i> Payments</button>
        <button class="btn btn-outline" onclick="showPage('reports',null)"><i class="fas fa-chart-bar"></i> Reports</button>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <span id="dashOverdueAlert" style="display:none;padding:5px 12px;border-radius:20px;background:var(--red-bg);color:var(--red);font-size:12px;font-weight:700"></span>
          <span id="dashDueSoonAlert" style="display:none;padding:5px 12px;border-radius:20px;background:var(--amber-bg);color:var(--amber);font-size:12px;font-weight:700"></span>
        </div>
      </div>
      <!-- WhatsApp Automation Card -->
      <div id="dashWACard" style="margin-bottom:16px"></div>
      <div id="dashPartialCard" style="margin-bottom:16px"></div>
      <!-- Combined Outstanding Card -->
      <div id="s-outstanding-card" style="margin-bottom:16px;background:var(--card);border:2px solid rgba(183,28,28,.18);border-radius:14px;padding:16px 20px;box-shadow:0 2px 12px rgba(183,28,28,.07)"></div>
      <div class="dash-stats-row">
        <div class="stat-card" data-color="teal">
          <div class="stat-icon" style="background:#e0f2f1;color:#00897B"><i class="fas fa-rupee-sign"></i></div>
          <div class="stat-body">
            <div class="stat-val" id="s-revenue">₹0</div>
            <div class="stat-lbl">Total Revenue</div>
            <div class="stat-trend up" id="s-revenue-trend"><i class="fas fa-arrow-up"></i> incl. partial received</div>
          </div>
        </div>
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
            <span class="cal-dot" style="background:#00897B"></span>Due
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
            <button class="cf-btn" onclick="showPage('invoices',null)">View All</button>
          </div>
          <div id="dashRecentList"></div>
        </div>
        <!-- Top Clients -->
        <div class="dash-card" style="flex:0 0 210px;min-width:0">
          <div class="card-header"><span class="card-title">Top Clients</span></div>
          <div id="dashTopClients"></div>
        </div>
      </div>
    </div>

    <!-- ─────────── INVOICES LIST ─────────── -->
    <div id="page-invoices" class="page">
      <div class="page-toolbar">
        <div class="toolbar-left">
          <input type="text" class="table-search" placeholder="Search invoices…" oninput="filterInvoices(this.value)" id="invSearch">
          <select class="table-filter" onchange="filterByStatus(this.value)" id="statusFilter">
            <option value="">All Status</option>
            <option>Paid</option><option>Pending</option><option>Partial</option><option>Overdue</option><option>Draft</option><option>Cancelled</option>
          </select>
          <select class="table-filter" onchange="filterByService(this.value)" id="serviceFilter">
            <option value="">All Services</option>
            <option>Website Development</option><option>School ERP</option>
            <option>Mobile App</option><option>Maintenance</option>
            <option>Consultation</option><option>Domain & Hosting</option>
          </select>
          <input type="date" class="table-filter" id="dateFrom" onchange="filterByDate()" placeholder="From">
          <input type="date" class="table-filter" id="dateTo" onchange="filterByDate()" placeholder="To">
        </div>
        <div class="toolbar-right">
          <button class="btn btn-outline" onclick="exportCSV()"><i class="fas fa-download"></i> Export CSV</button>
          <button class="btn btn-primary" onclick="showPage('create',null)"><i class="fas fa-plus"></i> New Invoice</button>
        </div>
      </div>

      <div class="table-card">
        <table class="data-table" id="invoicesTable">
          <thead>
            <tr>
              <th><input type="checkbox" id="selectAll" onchange="selectAllInv(this)"></th>
              <th onclick="sortTable('num')" class="sortable">Invoice # <i class="fas fa-sort"></i></th>
              <th onclick="sortTable('client')" class="sortable">Client <i class="fas fa-sort"></i></th>
              <th onclick="sortTable('service')" class="sortable">Service <i class="fas fa-sort"></i></th>
              <th onclick="sortTable('issued')" class="sortable">Issue Date <i class="fas fa-sort"></i></th>
              <th onclick="sortTable('due')" class="sortable">Due Date <i class="fas fa-sort"></i></th>
              <th onclick="sortTable('amount')" class="sortable">Amount <i class="fas fa-sort"></i></th>
              <th onclick="sortTable('status')" class="sortable">Status <i class="fas fa-sort"></i></th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="invoicesTbody"></tbody>
        </table>
        <div class="table-footer">
          <div class="tf-info" id="tfInfo">Showing 1–10 of 34</div>
          <div class="pagination" id="pagination"></div>
        </div>
      </div>
    </div>

    <!-- ─────────── CREATE / EDIT INVOICE ─────────── -->
    <div id="page-create" class="page">
      <div class="create-layout">
        <!-- FORM SIDE -->
        <div class="create-form">

          <!-- Invoice Meta -->
          <div class="form-section">
            <div class="fs-title"><i class="fas fa-hashtag"></i> Invoice Details</div>
            <div class="form-grid g2">
              <div class="field"><label>Invoice #</label><input id="f-num" value="" placeholder="Auto-generated" oninput="livePreview()"></div>
              <div class="field"><label>Service Type</label>
                <select id="f-service" onchange="livePreview()">
                  <option>Website Development</option><option>School ERP</option>
                  <option>Mobile App</option><option>Maintenance</option>
                  <option>Consultation</option><option>Domain & Hosting</option><option>Other</option>
                </select>
              </div>
              <div class="field"><label>Issue Date</label><input type="date" id="f-date" oninput="updateDueFromIssue();livePreview()"></div>
              <div class="field"><label>Due Date</label><input type="date" id="f-due" oninput="livePreview()"></div>
              <div class="field"><label>Currency</label>
                <select id="f-currency" onchange="livePreview()">
                  <option value="₹">INR (₹)</option><option value="$">USD ($)</option><option value="€">EUR (€)</option>
                </select>
              </div>
              <div class="field"><label>PDF Template</label>
                <select id="f-template" onchange="livePreview()">
                  <option value="1">1 – Classic Pro</option>
                  <option value="2">2 – Modern Teal</option>
                  <option value="3">3 – Bold Dark</option>
                  <option value="4">4 – Minimal Clean</option>
                  <option value="5">5 – Corporate Blue</option>
                  <option value="6">6 – Warm Orange</option>
                  <option value="7">7 – Purple Gradient</option>
                  <option value="8">8 – Green Leaf</option>
                  <option value="9">9 – Red Executive</option>
                </select>
              </div>
            </div>
            <div style="margin-top:14px">
              <label style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:8px">Payment Status</label>
              <div class="status-toggle-row">
                <label class="status-radio"><input type="radio" name="inv-status" value="Draft" checked onchange="livePreview()"><span class="sr-pill draft">Draft</span></label>
                <label class="status-radio"><input type="radio" name="inv-status" value="Pending" onchange="livePreview()"><span class="sr-pill pending">Pending</span></label>
                <label class="status-radio"><input type="radio" name="inv-status" value="Paid" onchange="livePreview()"><span class="sr-pill paid">Paid</span></label>
                <label class="status-radio"><input type="radio" name="inv-status" value="Overdue" onchange="livePreview()"><span class="sr-pill overdue">Overdue</span></label>
              </div>
            </div>
          </div>

          <!-- Client -->
          <div class="form-section">
            <div class="fs-title"><i class="fas fa-user"></i> Client Information</div>
            <div class="form-grid g1" style="margin-bottom:12px">
              <div class="field"><label>Quick Select Client</label>
                <select id="f-client-select" onchange="fillClientForm(this.value)">
                  <option value="">-- Quick Select Client --</option>
                </select>
              </div>
            </div>
            <div class="form-grid g2">
              <div class="field g-full"><label>Organization / Client Name *</label><input id="f-cname" placeholder="Organization / Client Name" oninput="livePreview()"></div>
              <div class="field"><label>Contact Person</label><input id="f-cperson" placeholder="Full Name" oninput="livePreview()"></div>
              <div class="field"><label>WhatsApp Number</label><input id="f-cwa" placeholder="+91 9876543210" oninput="livePreview()"></div>
              <div class="field"><label>Email Address</label><input id="f-cemail" type="email" placeholder="client@domain.com" oninput="livePreview()"></div>
              <div class="field"><label>GST Number</label><input id="f-cgst" placeholder="22AAAAA0000A1Z5" oninput="livePreview()"></div>
              <div class="field g-full"><label>Billing Address</label><textarea id="f-caddr" placeholder="Full address with city, state, PIN" oninput="livePreview()"></textarea></div>
            </div>
          </div>

          <!-- Items -->
          <div class="form-section">
            <div class="fs-title"><i class="fas fa-list-ul"></i> Line Items</div>
            <div class="items-head-row">
              <span>Description</span>
              <span>Type</span>
              <span style="text-align:center">Qty</span>
              <span style="text-align:right">Rate</span>
              <span style="text-align:right">Amount</span>
              <span style="text-align:center">GST%</span>
              <span style="text-align:right">Total</span>
              <span></span>
            </div>
            <div id="itemsList"></div>
            <div class="items-actions">
              <button class="add-line-btn" onclick="addItem()"><i class="fas fa-plus"></i> Add Line Item</button>
              <button class="add-line-btn" style="border-color:#1976D2;color:#1976D2" onclick="openProductPicker()"><i class="fas fa-box"></i> Pick from Services</button>
            </div>

            <!-- Totals -->
            <div class="totals-panel">
              <div class="tp-row">
                <span>Subtotal</span>
                <code id="tp-sub">₹0.00</code>
              </div>
              <div class="tp-row">
                <span>Discount <input type="number" id="f-disc" value="0" min="0" class="inline-num" oninput="calcTotals()"> <select id="f-disc-type" onchange="calcTotals()" style="font-size:12px;padding:2px 4px;border:1px solid var(--border);border-radius:5px;background:var(--card);color:var(--text)"><option value="pct">%</option><option value="fixed">₹</option></select></span>
                <code class="neg" id="tp-disc">-₹0.00</code>
              </div>
              <div class="tp-row">
                <span style="font-weight:700">Amount</span>
                <code id="tp-amount" style="font-weight:700">₹0.00</code>
              </div>
              <div class="tp-row">
                <span style="display:flex;flex-direction:column;gap:2px">
                  <span style="font-size:11px;color:var(--muted);font-weight:600">Total GST</span>
                  <span id="tp-gst-breakdown" style="font-size:10px;color:var(--muted)"></span>
                </span>
                <code class="pos" id="tp-gst">+₹0.00</code>
              </div>
              <div class="tp-row grand">
                <span>Grand Total</span>
                <code id="tp-grand">₹0.00</code>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="form-section">
            <div class="fs-title"><i class="fas fa-sticky-note"></i> Notes & Payment Info</div>
            <div class="form-grid g2">
              <div class="field g-full"><label>Notes to Client</label><textarea id="f-notes" oninput="livePreview(); debounceSaveInvoiceDraft()">Thank you for choosing OPTMS Tech. Payment is due within 15 days of invoice date. Late payments may incur a 2% monthly interest charge.</textarea></div>
              <div class="field g-full"><label>Bank Account Details</label><textarea id="f-bank" oninput="livePreview(); debounceSaveInvoiceDraft()"></textarea></div>
              <div class="field g-full"><label>Terms & Conditions</label><textarea id="f-tnc" oninput="livePreview(); debounceSaveInvoiceDraft()" style="min-height:72px" placeholder="Enter terms and conditions..."></textarea></div>
              <div class="field g-full">
                <label>Invoice Generated By <span style="font-size:10px;color:var(--muted)">(shown at bottom of invoice)</span></label>
                <div style="display:flex;gap:8px;align-items:center">
                  <input id="f-generated-by" placeholder="e.g. OPTMS Tech Invoice Manager · optmstech.in" oninput="livePreview()" value="OPTMS Tech Invoice Manager · optmstech.in" style="flex:1">
                  <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;white-space:nowrap">
                    <input type="checkbox" id="f-show-generated" checked onchange="livePreview()" style="accent-color:var(--teal)"> Show in PDF
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Logo Options -->
          <div class="form-section">
            <div class="fs-title"><i class="fas fa-image"></i> Logo & Branding</div>
            <div class="form-grid g2">

              <!-- Company Logo -->
              <div class="field">
                <label>Company Logo</label>
                <div style="display:flex;gap:6px;align-items:stretch">
                  <input id="f-company-logo" placeholder="https://… or upload →" oninput="livePreview()" style="flex:1;min-width:0">
                  <label style="display:flex;align-items:center;gap:5px;padding:0 12px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap;transition:.2s" onmouseover="this.style.borderColor='var(--teal)';this.style.color='var(--teal)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                    <i class="fas fa-upload"></i> Upload
                    <input type="file" accept="image/*" style="display:none" onchange="handleLogoUpload(this,'f-company-logo','f-logo-preview')">
                  </label>
                </div>
                <div id="f-logo-preview" style="margin-top:6px;min-height:0"></div>
              </div>

              <!-- Client Logo -->
              <div class="field">
                <label>Client Logo <span style="font-size:10px;color:var(--muted)">(optional)</span></label>
                <div style="display:flex;gap:6px;align-items:stretch">
                  <input id="f-client-logo" placeholder="https://… or upload →" oninput="livePreview()" style="flex:1;min-width:0">
                  <label style="display:flex;align-items:center;gap:5px;padding:0 12px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap;transition:.2s" onmouseover="this.style.borderColor='var(--teal)';this.style.color='var(--teal)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                    <i class="fas fa-upload"></i> Upload
                    <input type="file" accept="image/*" style="display:none" onchange="handleLogoUpload(this,'f-client-logo','f-client-logo-preview')">
                  </label>
                </div>
                <div id="f-client-logo-preview" style="margin-top:6px;min-height:0"></div>
              </div>

              <!-- Signature -->
              <div class="field">
                <label>Authorised Signature</label>
                <div style="display:flex;gap:6px;align-items:stretch">
                  <input id="f-signature" placeholder="https://… or upload →" oninput="livePreview()" style="flex:1;min-width:0">
                  <label style="display:flex;align-items:center;gap:5px;padding:0 12px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap;transition:.2s" onmouseover="this.style.borderColor='var(--teal)';this.style.color='var(--teal)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                    <i class="fas fa-pen-nib"></i> Upload Signature
                    <input type="file" accept="image/*" style="display:none" onchange="handleLogoUpload(this,'f-signature','f-sign-preview')">
                  </label>
                </div>
                <div id="f-sign-preview" style="margin-top:6px;min-height:0"></div>
                <div style="margin-top:6px;font-size:11px;color:var(--muted)">Upload a transparent PNG of your signature for PDF invoices</div>
              </div>

              <!-- QR Code -->
              <div class="field">
                <label>Payment QR Code <span style="font-size:10px;color:var(--muted)">(optional)</span></label>
                <div style="display:flex;gap:6px;align-items:stretch">
                  <input id="f-qr" placeholder="https://… or upload →" oninput="livePreview()" style="flex:1;min-width:0">
                  <label style="display:flex;align-items:center;gap:5px;padding:0 12px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap;transition:.2s" onmouseover="this.style.borderColor='var(--teal)';this.style.color='var(--teal)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                    <i class="fas fa-qrcode"></i> Upload QR
                    <input type="file" accept="image/*" style="display:none" onchange="handleLogoUpload(this,'f-qr','f-qr-preview')">
                  </label>
                </div>
                <div id="f-qr-preview" style="margin-top:6px;min-height:0"></div>
              </div>

            </div>
          </div>

          <!-- PDF Visibility Options -->
          <div class="form-section">
            <div class="fs-title"><i class="fas fa-eye"></i> PDF Show / Hide Options</div>
            <div class="pdf-opts-grid">
              <label class="pdf-opt"><input type="checkbox" id="popt-bank" checked onchange="livePreview()"><span>Bank Details</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-qr" checked onchange="livePreview()"><span>QR Code</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-sign" checked onchange="livePreview()"><span>Signature</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-logo" checked onchange="livePreview()"><span>Company Logo</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-client-logo" onchange="livePreview()"><span>Client Logo</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-notes" checked onchange="livePreview()"><span>Notes</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-tnc" checked onchange="livePreview()"><span>Terms & Conditions</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-gst-col" checked onchange="livePreview()"><span>GST Column</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-footer" checked onchange="livePreview()"><span>Footer Bar</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-watermark" onchange="livePreview()"><span>Paid Watermark</span></label>
            </div>
          </div>

        </div>

        <!-- PREVIEW SIDE -->
        <div class="create-preview">
          <div class="preview-toolbar">
            <span class="preview-label"><i class="fas fa-eye"></i> Live Preview</span>
            <div style="display:flex;gap:8px">
              <select id="prevTplSelect" class="mini-select" onchange="document.getElementById('f-template').value=this.value;livePreview()">
                <option value="1">Template 1</option><option value="2">Template 2</option>
                <option value="3">Template 3</option><option value="4">Template 4</option>
                <option value="5">Template 5</option><option value="6">Template 6</option>
                <option value="7">Template 7</option><option value="8">Template 8</option>
                <option value="9">Template 9</option>
              </select>
              <button class="mini-btn" onclick="livePreview()"><i class="fas fa-sync"></i></button>
            </div>
          </div>
          <div class="preview-scroll">
            <div class="preview-scroll-inner">
              <div id="invoicePreviewWrap"></div>
            </div>
          </div>
          <div class="preview-actions">
            <button class="btn btn-success w100" onclick="saveInvoice()"><i class="fas fa-save"></i> Save Invoice</button>
            <div class="btn-row-2">
              <button class="btn btn-primary" onclick="printCurrentInvoice()"><i class="fas fa-print"></i> Print / PDF</button>
              <button class="btn btn-whatsapp" onclick="sendWAFromForm()"><i class="fab fa-whatsapp"></i> WhatsApp</button>
            </div>
            <div class="btn-row-2">
              <button class="btn btn-email" onclick="sendEmailFromForm()"><i class="fas fa-envelope"></i> Email</button>
              <button class="btn btn-outline" onclick="markFormPaid()"><i class="fas fa-check"></i> Mark Paid</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ─────────── CLIENTS ─────────── -->
    <div id="page-clients" class="page">
      <div class="page-toolbar">
        <input type="text" class="table-search" placeholder="Search clients…" oninput="filterClients(this.value)">
        <div style="flex:1"></div>
        <button class="btn btn-primary" onclick="openAddClientModal()"><i class="fas fa-plus"></i> Add Client</button>
      </div>
      <div class="clients-grid" id="clientsGrid"></div>
    </div>

    <!-- ─────────── SERVICES / PRODUCTS ─────────── -->
    <div id="page-products" class="page">
      <div class="page-toolbar">
        <input type="text" class="table-search" placeholder="Search services…" oninput="filterProducts(this.value)" id="productSearch">
        <select class="table-filter" onchange="filterProductsCat(this.value)" id="productCatFilter">
          <option value="">All Categories</option>
        </select>
        <div style="flex:1"></div>
        <span id="prodCountInfo" style="font-size:12px;color:var(--muted);margin-right:8px"></span>
        <button class="btn btn-primary" onclick="openAddProductModal()"><i class="fas fa-plus"></i> Add Service</button>
      </div>
      <div class="table-card">
        <table class="data-table">
          <thead><tr><th>#</th><th>Service Name</th><th>Category</th><th>Rate (₹)</th><th>HSN</th><th>GST%</th><th>Actions</th></tr></thead>
          <tbody id="productsTbody"></tbody>
        </table>
        <div class="table-footer">
          <div class="tf-info" id="prodInfo"></div>
          <div class="pagination" id="prodPagination"></div>
        </div>
      </div>
    </div>

    <!-- ─────────── PAYMENTS ─────────── -->
    <div id="page-payments" class="page">
      <!-- Summary cards -->
      <div class="dash-stats-row" style="grid-template-columns:repeat(4,1fr);margin-bottom:18px" id="pmtSummary"></div>
      <!-- Toolbar -->
      <div class="page-toolbar" style="flex-wrap:wrap;gap:8px;margin-bottom:14px">
        <input type="text" class="table-search" placeholder="Search payments…" oninput="filterPayments(this.value)" id="pmtSearch">
        <select class="table-filter" onchange="filterPaymentsByMethod(this.value)" id="pmtMethodFilter">
          <option value="">All Methods</option>
          <option>Bank Transfer (NEFT/RTGS)</option>
          <option>UPI (GPay/PhonePe/Paytm)</option>
          <option>Cash</option><option>Cheque</option><option>Credit Card</option>
        </select>
        <button class="cf-btn" onclick="setPmtRange('today')" id="pmtToday">Today</button>
        <button class="cf-btn" onclick="setPmtRange('week')" id="pmtWeek">This Week</button>
        <button class="cf-btn" onclick="setPmtRange('month')" id="pmtMonth">This Month</button>
        <input type="date" class="table-filter" id="pmtFrom" onchange="filterPmtByDate()" style="max-width:130px">
        <input type="date" class="table-filter" id="pmtTo" onchange="filterPmtByDate()" style="max-width:130px">
        <div style="flex:1"></div>
        <button class="btn btn-outline" onclick="exportPmtCSV()"><i class="fas fa-download"></i> Export</button>
      </div>
      <!-- Table -->
      <div class="table-card">
        <table class="data-table">
          <thead><tr>
            <th>Date</th><th>Invoice #</th><th>Client</th>
            <th>Method</th><th>Txn ID</th><th>Amount</th><th>Status</th><th>Action</th>
          </tr></thead>
          <tbody id="paymentsTbody"></tbody>
        </table>
        <div style="padding:6px 14px 2px;font-size:11px;color:var(--muted);display:flex;align-items:center;gap:6px">
          <i class="fas fa-layer-group" style="font-size:10px"></i>
          <span>Rows sharing the same invoice number share a colour chip. <i class="fas fa-layer-group" style="font-size:9px"></i> icon = multiple payments (partial instalments).</span>
        </div>
        <div class="table-footer">
          <div class="tf-info" id="pmtInfo"></div>
          <div class="pagination" id="pmtPagination"></div>
        </div>
      </div>
    </div>

    <!-- ─────────── REPORTS ─────────── -->
    <div id="page-reports" class="page">
      <!-- Date range filter bar -->
      <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;box-shadow:var(--shadow)">
        <strong style="font-size:12px;color:var(--muted);margin-right:4px"><i class="fas fa-calendar-alt" style="color:var(--teal)"></i> Period:</strong>
        <button class="cf-btn" onclick="setRptRange('today')" id="rpt-today">Today</button>
        <button class="cf-btn active" onclick="setRptRange('month')" id="rpt-month">This Month</button>
        <button class="cf-btn" onclick="setRptRange('quarter')" id="rpt-quarter">Quarter</button>
        <button class="cf-btn" onclick="setRptRange('year')" id="rpt-year">This Year</button>
        <button class="cf-btn" onclick="setRptRange('all')" id="rpt-all">All Time</button>
        <input type="date" class="table-filter" id="rptFrom" onchange="applyRptFilter()" style="max-width:130px;margin-left:8px">
        <span style="color:var(--muted);font-size:12px">–</span>
        <input type="date" class="table-filter" id="rptTo" onchange="applyRptFilter()" style="max-width:130px">
        <button class="btn btn-outline" style="margin-left:auto;font-size:12px" onclick="exportRptCSV()"><i class="fas fa-download"></i> Export</button>
      </div>
      <!-- Dynamic stat cards -->
      <div class="dash-stats-row" id="rptStatCards" style="grid-template-columns:repeat(5,1fr);margin-bottom:18px"></div>
      <!-- Charts row -->
      <div class="dash-row-2" style="margin-bottom:18px">
        <div class="dash-card" style="flex:1">
          <div class="card-header"><span class="card-title">Revenue by Service Type</span></div>
          <div class="reports-chart-wrap-lg"><canvas id="serviceChart"></canvas></div>
        </div>
        <div class="dash-card" style="flex:1">
          <div class="card-header"><span class="card-title">Monthly Trend</span></div>
          <div class="reports-chart-wrap-lg"><canvas id="compareChart"></canvas></div>
        </div>
      </div>
      <!-- Transactions table -->
      <div class="table-card">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid var(--border)">
          <span style="font-weight:700;font-size:14px">Transaction Details</span>
          <input type="text" class="table-search" placeholder="Search…" oninput="filterRptTable(this.value)" style="max-width:200px">
        </div>
        <table class="data-table">
          <thead><tr>
            <th>Invoice #</th><th>Client</th><th>Service</th>
            <th>Issue Date</th><th>Amount</th><th>Status</th>
          </tr></thead>
          <tbody id="rptTbody"></tbody>
        </table>
        <div class="table-footer">
          <div class="tf-info" id="rptInfo"></div>
          <div class="pagination" id="rptPagination"></div>
        </div>
      </div>
    </div>

    <!-- ─────────── PDF TEMPLATES ─────────── -->
    <div id="page-templates" class="page">
      <div class="templates-intro">Choose a PDF template. <strong>Preview</strong> shows it live below. <strong>Set Active</strong> uses it as default.</div>
      <div class="templates-grid" id="templatesGrid"></div>

      <!-- Inline Preview Panel -->
      <div id="tplPreviewPanel" style="display:none;margin-top:24px">
        <div class="dash-card">
          <div class="card-header">
            <span class="card-title" id="tplPreviewLabel">Template Preview</span>
            <button class="cf-btn" onclick="document.getElementById('tplPreviewPanel').style.display='none'"><i class="fas fa-times"></i> Close</button>
          </div>
          <div style="background:#e8eaed;border-radius:8px;padding:16px;overflow:auto;text-align:center;min-height:200px">
            <div id="tplPreviewInner" style="display:inline-block;text-align:left"></div>
          </div>
        </div>
      </div>

      <!-- Template Customization -->
      <div class="dash-card" style="margin-top:24px">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-paint-brush" style="color:var(--teal)"></i> Customize Active Template</span>
          <span style="font-size:12px;color:var(--muted)">Changes apply to new invoices</span>
        </div>
        <div style="padding:0 4px">
          <div class="form-grid g2" style="margin-bottom:16px">
            <div class="field">
              <label>Primary Color <span style="font-size:10px;color:var(--muted)">(header background)</span></label>
              <div style="display:flex;gap:8px;align-items:center">
                <input type="color" id="tpl-color1" value="#1A2332" style="width:44px;height:38px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;padding:2px">
                <input id="tpl-color1-hex" value="#1A2332" placeholder="#1A2332" style="flex:1;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:var(--mono);font-size:13px" oninput="document.getElementById('tpl-color1').value=this.value">
                <div style="display:flex;gap:4px;flex-wrap:wrap">
                  <span onclick="setTplColor('tpl-color1','#1A2332')" style="width:20px;height:20px;background:#1A2332;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color1','#00897B')" style="width:20px;height:20px;background:#00897B;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color1','#1565C0')" style="width:20px;height:20px;background:#1565C0;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color1','#B71C1C')" style="width:20px;height:20px;background:#B71C1C;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color1','#4A148C')" style="width:20px;height:20px;background:#4A148C;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color1','#1B5E20')" style="width:20px;height:20px;background:#1B5E20;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color1','#E64A19')" style="width:20px;height:20px;background:#E64A19;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color1','#0F172A')" style="width:20px;height:20px;background:#0F172A;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                </div>
              </div>
            </div>
            <div class="field">
              <label>Accent Color <span style="font-size:10px;color:var(--muted)">(invoice number, totals)</span></label>
              <div style="display:flex;gap:8px;align-items:center">
                <input type="color" id="tpl-color2" value="#4DB6AC" style="width:44px;height:38px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;padding:2px">
                <input id="tpl-color2-hex" value="#4DB6AC" placeholder="#4DB6AC" style="flex:1;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:var(--mono);font-size:13px" oninput="document.getElementById('tpl-color2').value=this.value">
                <div style="display:flex;gap:4px;flex-wrap:wrap">
                  <span onclick="setTplColor('tpl-color2','#4DB6AC')" style="width:20px;height:20px;background:#4DB6AC;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color2','#FFD54F')" style="width:20px;height:20px;background:#FFD54F;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color2','#42A5F5')" style="width:20px;height:20px;background:#42A5F5;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color2','#EF9A9A')" style="width:20px;height:20px;background:#EF9A9A;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color2','#A5D6A7')" style="width:20px;height:20px;background:#A5D6A7;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color2','#CE93D8')" style="width:20px;height:20px;background:#CE93D8;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color2','#FF8A65')" style="width:20px;height:20px;background:#FF8A65;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color2','#ffffff')" style="width:20px;height:20px;background:#fff;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                </div>
              </div>
            </div>
            <div class="field">
              <label>Company Name Size <span style="font-size:10px;color:var(--muted)">(px)</span></label>
              <div style="display:flex;gap:8px;align-items:center">
                <input type="range" id="tpl-r-name-size" min="16" max="48" value="28" style="flex:1" oninput="(document.getElementById('tpl-r-name-size-val')||{}).textContent=this.value+'px'; (document.getElementById('tpl-name-size')||{value:0}).value=this.value">
                <span id="tpl-r-name-size-val" style="width:36px;font-size:12px;font-weight:700;color:var(--teal)">28px</span>
              </div>
            </div>
            <div class="field">
              <label>Company Name Color</label>
              <div style="display:flex;gap:8px;align-items:center">
                <input type="color" id="tpl-r-name-color" value="#ffffff" style="width:44px;height:36px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;padding:2px">
                <div style="display:flex;gap:4px">
                  <span onclick="document.getElementById('tpl-name-color').value='#ffffff'" style="width:20px;height:20px;background:#fff;border-radius:4px;cursor:pointer;border:1px solid #ddd"></span>
                  <span onclick="document.getElementById('tpl-name-color').value='#FFD54F'" style="width:20px;height:20px;background:#FFD54F;border-radius:4px;cursor:pointer;border:1px solid #ddd"></span>
                  <span onclick="document.getElementById('tpl-name-color').value='#4DB6AC'" style="width:20px;height:20px;background:#4DB6AC;border-radius:4px;cursor:pointer;border:1px solid #ddd"></span>
                  <span onclick="document.getElementById('tpl-name-color').value='#1A2332'" style="width:20px;height:20px;background:#1A2332;border-radius:4px;cursor:pointer;border:1px solid #ddd"></span>
                  <span onclick="document.getElementById('tpl-name-color').value='#E53935'" style="width:20px;height:20px;background:#E53935;border-radius:4px;cursor:pointer;border:1px solid #ddd"></span>
                </div>
              </div>
            </div>
            <div class="field">
              <label>Company Name Style</label>
              <select id="tpl-r-name-style">
                <option value="800">Extra Bold</option>
                <option value="700" selected>Bold</option>
                <option value="600">Semi-Bold</option>
                <option value="400">Normal</option>
                <option value="300">Light</option>
              </select>
            </div>
            <div class="field">
              <label>Logo Position</label>
              <select id="tpl-r-logo-pos">
                <option value="left">Left (Default)</option>
                <option value="center">Center</option>
                <option value="right">Right</option>
              </select>
            </div>
            <div class="field">
              <label>Font Family</label>
              <select id="tpl-font">
                <option value="'Public Sans',sans-serif">Public Sans (Default)</option>
                <option value="'Roboto',sans-serif">Roboto</option>
                <option value="'Inter',sans-serif">Inter</option>
                <option value="'Poppins',sans-serif">Poppins</option>
                <option value="'Montserrat',sans-serif">Montserrat</option>
                <option value="'Lato',sans-serif">Lato</option>
                <option value="Arial,sans-serif">Arial</option>
                <option value="Georgia,serif">Georgia (Serif)</option>
              </select>
            </div>
            <div class="field">
              <label>Invoice Header Style</label>
              <select id="tpl-header-style">
                <option value="gradient">Gradient Background</option>
                <option value="solid">Solid Color</option>
                <option value="minimal">Minimal (White + Border)</option>
                <option value="split">Split Layout</option>
              </select>
            </div>
            <div class="field">
              <label>Table Header Style</label>
              <select id="tpl-table-style">
                <option value="dark">Dark Header (Default)</option>
                <option value="accent">Accent Color Header</option>
                <option value="light">Light Gray Header</option>
                <option value="outline">Outline / Borderless</option>
              </select>
            </div>
            <div class="field">
              <label>Footer Text</label>
              <input id="tpl-footer-text" placeholder="e.g. Thank you for your business!">
            </div>
            <div class="field">
              <label>Invoice Tagline <span style="font-size:10px;color:var(--muted)">(shown near company name)</span></label>
              <input id="tpl-tagline" placeholder="e.g. Tax Invoice · GST Registered">
            </div>
            <div class="field">
              <label>Page Watermark Text <span style="font-size:10px;color:var(--muted)">(on paid invoices)</span></label>
              <input id="tpl-watermark-text" value="PAID" placeholder="PAID">
            </div>
            <div class="field">
              <label>Company Name Size (px)</label>
              <input type="number" id="tpl-name-size" value="28" min="14" max="56" placeholder="28">
            </div>
            <div class="field">
              <label>Company Name Color</label>
              <div style="display:flex;gap:8px;align-items:center">
                <input type="color" id="tpl-name-color" value="#ffffff" style="width:44px;height:38px;border:1.5px solid var(--border);border-radius:8px;padding:2px">
                <input id="tpl-name-color-hex" value="#ffffff" style="flex:1;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:var(--mono);font-size:13px" oninput="document.getElementById('tpl-name-color').value=this.value">
              </div>
            </div>
            <div class="field">
              <label>Company Name Font Weight</label>
              <select id="tpl-name-weight">
                <option value="400">Regular (400)</option>
                <option value="500">Medium (500)</option>
                <option value="600">SemiBold (600)</option>
                <option value="700">Bold (700)</option>
                <option value="800" selected>ExtraBold (800)</option>
                <option value="900">Black (900)</option>
              </select>
            </div>
            <div class="field">
              <label>Company Name Font Style</label>
              <select id="tpl-name-style">
                <option value="normal" selected>Normal</option>
                <option value="italic">Italic</option>
                <option value="oblique">Oblique</option>
              </select>
            </div>
            <div class="field">
              <label>Logo Position</label>
              <select id="tpl-logo-pos">
                <option value="left" selected>Left (default)</option>
                <option value="center">Center</option>
                <option value="right">Right</option>
              </select>
            </div>
          </div>

          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button class="btn btn-primary" onclick="applyTplCustomization()"><i class="fas fa-magic"></i> Apply &amp; Preview</button>
            <button class="btn btn-success" onclick="saveTplCustomization()"><i class="fas fa-save"></i> Save Customization</button>
            <button class="btn btn-outline" onclick="resetTplCustomization()"><i class="fas fa-undo"></i> Reset to Default</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ─────────── WHATSAPP SETUP ─────────── -->
    <div id="page-whatsapp" class="page">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start">

        <!-- API Credentials -->
        <div class="settings-block">
          <div class="sb-title"><i class="fab fa-whatsapp" style="color:#25D366"></i> WhatsApp Business API</div>
          <div class="form-grid g2">
            <div class="field"><label>API Token</label><input type="password" id="wa-token" placeholder="Bearer token from Meta Developer Console" value="<?= htmlspecialchars($settings['wa_token']??'') ?>"></div>
            <div class="field"><label>Phone Number ID</label><input id="wa-pid" placeholder="123456789012345" value="<?= htmlspecialchars($settings['wa_pid']??'') ?>"></div>
            <div class="field"><label>Business Account ID</label><input id="wa-bid" placeholder="Your WABA ID" value="<?= htmlspecialchars($settings['wa_bid']??'') ?>"></div>
            <div class="field"><label>Test Phone Number</label><input id="wa-test-phone" placeholder="+91 XXXXX XXXXX" value="<?= htmlspecialchars($settings['wa_test_phone']??'') ?>"></div>
          </div>
          <div style="display:flex;gap:10px;margin-top:14px">
            <button class="btn btn-whatsapp" onclick="testWA()"><i class="fab fa-whatsapp"></i> Send Test Message</button>
            <button class="btn btn-primary" onclick="saveWASettings()"><i class="fas fa-save"></i> Save Credentials</button>
          </div>
        </div>

        <!-- Automation Triggers -->
        <div class="settings-block">
          <div class="sb-title"><i class="fas fa-robot"></i> Automation Triggers</div>
          <div class="toggle-list">
            <div class="toggle-item" style="flex-wrap:wrap;gap:6px">
              <span style="flex:1"><strong>New Invoice</strong> — auto-send when invoice created</span>
              <div class="tog <?= (($settings['wa_auto_inv']??'0')==='1')?'on':'' ?>" id="twa1" onclick="this.classList.toggle('on'); saveWAToggle('wa_auto_inv', this)"></div>
            </div>
            <div style="padding:8px 12px;margin:-4px 0 8px;background:var(--teal-bg);border-radius:0 0 8px 8px;font-size:11px;color:var(--teal)" id="twa1-hint">
              When ON: sends invoice details, amount, due date, UPI, and item list to client automatically
            </div>
            <div class="toggle-item"><span><strong>Payment Receipt</strong> — send when marked fully paid</span><div class="tog <?= (($settings['wa_auto_paid']??'1')!=='0')?'on':'' ?>" id="twa2" onclick="this.classList.toggle('on'); saveWAToggle('wa_auto_paid', this)"></div></div>
            <div class="toggle-item"><span><strong>Partial Payment</strong> — send receipt when partial payment recorded</span><div class="tog <?= (($settings['wa_auto_partial']??'1')!=='0')?'on':'' ?>" id="twa6" onclick="this.classList.toggle('on'); saveWAToggle('wa_auto_partial', this)"></div></div>
            <div class="toggle-item"><span><strong>Due Soon</strong> — reminder 3 days before due date</span><div class="tog <?= (($settings['wa_auto_remind']??'1')!=='0')?'on':'' ?>" id="twa3" onclick="this.classList.toggle('on'); saveWAToggle('wa_auto_remind', this)"></div></div>
            <div class="toggle-item"><span><strong>Overdue Alert</strong> — send on due date if unpaid</span><div class="tog <?= (($settings['wa_auto_overdue']??'1')!=='0')?'on':'' ?>" id="twa4" onclick="this.classList.toggle('on'); saveWAToggle('wa_auto_overdue', this)"></div></div>
            <div class="toggle-item"><span><strong>Overdue Follow-up</strong> — repeat every 7 days while overdue</span><div class="tog <?= (($settings['wa_auto_followup']??'0')==='1')?'on':'' ?>" id="twa5" onclick="this.classList.toggle('on'); saveWAToggle('wa_auto_followup', this)"></div></div>
          </div>
          <div class="form-grid g2" style="margin-top:14px">
            <div class="field"><label>Reminder Days Before Due</label>
              <input type="number" id="wa-remind-days" value="3" min="1" max="30" placeholder="3">
            </div>
            <div class="field"><label>Max Overdue Follow-ups</label>
              <input type="number" id="wa-max-followup" value="3" min="1" max="10" placeholder="3">
            </div>
          </div>
        </div>

        <!-- Message Templates -->
        <div class="settings-block">
          <div class="sb-title"><i class="fas fa-comment-alt"></i> Message Templates</div>
          <div style="background:var(--teal-bg);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--teal);margin-bottom:16px">
            <strong>Variables:</strong> {client_name} {invoice_no} {amount} {currency} {due_date} {issue_date} {service} {company_name} {company_phone} {upi} {bank_details} {days_overdue} {item_list} {status}
          </div>
          <div class="form-grid g1">
            <div class="field"><label>📄 New Invoice Template</label>
              <textarea id="wa-tpl-inv" style="min-height:80px">Hi {client_name}! 👋 Invoice #{invoice_no} for ₹{amount} from {company_name} is ready. Due: {due_date}. Pay to UPI: {upi}. Thanks! 🙏</textarea>
            </div>
            <div class="field"><label>✅ Payment Receipt Template</label>
              <textarea id="wa-tpl-paid" style="min-height:80px">Hi {client_name}! ✅ Payment of ₹{amount} for invoice #{invoice_no} received. Thank you for choosing {company_name}! 🎉</textarea>
          <div class="field g-full"><label>Partial Payment Template <span style="font-size:10px;color:var(--muted)">— sent when partial payment recorded</span></label>
            <textarea id="wa-tpl-partial" style="min-height:90px" oninput="saveWASettings()"></textarea>
          </div>
            </div>
            <div class="field"><label>⏰ Payment Reminder Template (before due)</label>
              <textarea id="wa-tpl-remind" style="min-height:80px">Hi {client_name}! 🔔 Gentle reminder: Invoice #{invoice_no} for ₹{amount} is due on {due_date}. Please arrange payment. UPI: {upi} 🙏</textarea>
            </div>
            <div class="field"><label>🔴 Overdue Alert Template</label>
              <textarea id="wa-tpl-overdue" style="min-height:80px">Hi {client_name}! ⚠️ Invoice #{invoice_no} for ₹{amount} was due on {due_date} and is now overdue. Please pay immediately. UPI: {upi}. Contact us if any issue.</textarea>
            </div>
            <div class="field"><label>📋 Overdue Follow-up Template</label>
              <textarea id="wa-tpl-followup" style="min-height:80px">Hi {client_name}, this is a follow-up regarding invoice #{invoice_no} (₹{amount}), overdue by {days_overdue} days. Please clear this at earliest. UPI: {upi}.</textarea>
            </div>
          </div>
          <button class="btn btn-primary" style="margin-top:14px" onclick="saveWASettings()"><i class="fas fa-save"></i> Save All Templates</button>
        </div>

        <!-- Festival / Bulk Messaging -->
        <div class="settings-block">
          <div class="sb-title"><i class="fas fa-star"></i> Festival &amp; Bulk Messaging</div>
          <div style="background:var(--amber-bg);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--amber);margin-bottom:16px">
            ✨ Send personalised festival greetings to all or selected clients. Requires WhatsApp Business API.
          </div>
          <div class="form-grid g2">
            <div class="field"><label>Festival / Occasion Name</label>
              <select id="wa-festival">
                <option value="diwali">Diwali 🪔</option>
                <option value="holi">Holi 🎨</option>
                <option value="eid">Eid Mubarak 🌙</option>
                <option value="christmas">Christmas 🎄</option>
                <option value="newyear">New Year 🎊</option>
                <option value="independence">Independence Day 🇮🇳</option>
                <option value="custom">Custom Occasion</option>
              </select>
            </div>
            <div class="field"><label>Custom Occasion Name</label>
              <input id="wa-festival-custom" placeholder="e.g. Our Anniversary Sale">
            </div>
            <div class="field"><label>Festival Image URL</label>
              <div style="display:flex;gap:6px">
                <input id="wa-festival-img" placeholder="https://... (optional image)" style="flex:1">
                <label style="display:flex;align-items:center;gap:4px;padding:0 10px;border-radius:8px;border:1.5px solid var(--border);cursor:pointer;font-size:11px;color:var(--muted);white-space:nowrap;background:var(--bg)">
                  <i class="fas fa-upload"></i>
                  <input type="file" accept="image/*" style="display:none" onchange="handleLogoUpload(this,'wa-festival-img','wa-festival-img-preview')">
                </label>
              </div>
              <div id="wa-festival-img-preview" style="margin-top:6px"></div>
            </div>
            <div class="field"><label>Send To</label>
              <select id="wa-send-to">
                <option value="all">All Active Clients</option>
                <option value="paid">Clients with Paid Invoices</option>
                <option value="active">Clients with Recent Activity (90 days)</option>
              </select>
            </div>
          </div>
          <div class="field" style="margin-top:12px"><label>Festival Message</label>
            <textarea id="wa-tpl-festival" style="min-height:90px">Hi {client_name}! 🌟 Wishing you and your family a very Happy Diwali! 🪔✨ May this festival bring you joy, prosperity, and success. Thank you for your continued trust in {company_name}! 🙏</textarea>
          </div>
          <!-- Schedule options -->
          <div class="form-grid g2" style="margin-top:12px">
            <div class="field">
              <label>Schedule Date &amp; Time <span style="font-size:10px;color:var(--muted)">(leave blank to send now)</span></label>
              <input type="datetime-local" id="wa-festival-schedule" style="width:100%">
            </div>
            <div class="field">
              <label>Repeat <span style="font-size:10px;color:var(--muted)">(for recurring campaigns)</span></label>
              <select id="wa-festival-repeat">
                <option value="">No repeat (one-time)</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly (annual festival)</option>
              </select>
            </div>
          </div>
          <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap">
            <button class="btn btn-outline" onclick="previewFestivalMsg()"><i class="fas fa-eye"></i> Preview</button>
            <button class="btn btn-primary" onclick="saveFestivalCampaign()"><i class="fas fa-save"></i> Save Campaign</button>
            <button class="btn btn-whatsapp" onclick="sendFestivalBulk()"><i class="fab fa-whatsapp"></i> Send Now</button>
          </div>
          <div id="wa-bulk-log" style="margin-top:14px;max-height:150px;overflow-y:auto;background:var(--bg);border-radius:8px;padding:10px;font-size:12px;color:var(--muted);display:none"></div>
          <!-- Saved campaigns -->
          <div id="wa-campaigns-list" style="margin-top:12px"></div>
        </div>

        <!-- Approved Template Configuration -->
        <div class="settings-block">
          <div class="sb-title"><i class="fas fa-check-circle" style="color:#25D366"></i> Approved Message Templates</div>
          <div style="background:#E8F5E9;border-radius:8px;padding:12px 16px;font-size:13px;color:#1B5E20;margin-bottom:16px;line-height:1.7">
            <strong>📋 How it works:</strong><br>
            • <strong>Session mode</strong> (default) — sends free-form text. Works only within 24h of client messaging you.<br>
            • <strong>Template mode</strong> — uses Meta-approved templates. Works anytime for any number. Requires template approval from Meta first.
          </div>

          <div class="field" style="margin-bottom:16px">
            <label>Message Sending Mode</label>
            <div style="display:flex;gap:12px;margin-top:6px">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 16px;border:1.5px solid var(--border);border-radius:9px;flex:1;transition:.2s" id="mode-session-lbl">
                <input type="radio" name="wa-msg-mode" value="session" id="mode-session" onchange="setWAMode('session')" style="accent-color:var(--teal)">
                <div><div style="font-weight:700;font-size:13px">💬 Session Messages</div><div style="font-size:11px;color:var(--muted)">Free-form text, 24h window</div></div>
              </label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 16px;border:1.5px solid var(--border);border-radius:9px;flex:1;transition:.2s" id="mode-template-lbl">
                <input type="radio" name="wa-msg-mode" value="template" id="mode-template" onchange="setWAMode('template')" style="accent-color:var(--teal)">
                <div><div style="font-weight:700;font-size:13px">✅ Approved Templates</div><div style="font-size:11px;color:var(--muted)">Any time, any number</div></div>
              </label>
            </div>
          </div>

          <div id="tpl-names-section" style="display:none">
            <div style="font-size:12px;color:var(--muted);margin-bottom:12px">
              Enter the exact template names as approved in your <a href="https://business.facebook.com/wa/manage/message-templates/" target="_blank" style="color:var(--teal)">Meta Business Manager</a>.
              Language code is usually <code>en</code> or <code>en_US</code>.
            </div>
            <div class="form-grid g2">
              <div class="field">
                <label>New Invoice Template Name</label>
                <div style="display:flex;gap:6px">
                  <input id="tpl-name-invoice" placeholder="e.g. invoice_created" style="flex:1">
                  <input id="tpl-lang-invoice" placeholder="en" style="width:48px;text-align:center">
                </div>
                <div style="font-size:10px;color:var(--muted);margin-top:3px">Params: {{1}}name {{2}}invoice# {{3}}amount {{4}}due_date {{5}}upi {{6}}company</div>
              </div>
              <div class="field">
                <label>Payment Reminder Template Name</label>
                <div style="display:flex;gap:6px">
                  <input id="tpl-name-reminder" placeholder="e.g. payment_reminder" style="flex:1">
                  <input id="tpl-lang-reminder" placeholder="en" style="width:48px;text-align:center">
                </div>
                <div style="font-size:10px;color:var(--muted);margin-top:3px">Params: {{1}}name {{2}}invoice# {{3}}amount {{4}}due_date {{5}}upi {{6}}company</div>
              </div>
              <div class="field">
                <label>Overdue Alert Template Name</label>
                <div style="display:flex;gap:6px">
                  <input id="tpl-name-overdue" placeholder="e.g. payment_overdue" style="flex:1">
                  <input id="tpl-lang-overdue" placeholder="en" style="width:48px;text-align:center">
                </div>
                <div style="font-size:10px;color:var(--muted);margin-top:3px">Params: {{1}}name {{2}}invoice# {{3}}amount {{4}}days_overdue {{5}}upi {{6}}company</div>
              </div>
              <div class="field">
                <label>Payment Received Template Name</label>
                <div style="display:flex;gap:6px">
                  <input id="tpl-name-paid" placeholder="e.g. payment_received" style="flex:1">
                  <input id="tpl-lang-paid" placeholder="en" style="width:48px;text-align:center">
                </div>
                <div style="font-size:10px;color:var(--muted);margin-top:3px">Params: {{1}}name {{2}}invoice# {{3}}amount {{4}}date {{5}}company</div>
              </div>
              <div class="field">
                <label>Follow-up Template Name</label>
                <div style="display:flex;gap:6px">
                  <input id="tpl-name-followup" placeholder="e.g. invoice_followup" style="flex:1">
                  <input id="tpl-lang-followup" placeholder="en" style="width:48px;text-align:center">
                </div>
                <div style="font-size:10px;color:var(--muted);margin-top:3px">Params: {{1}}name {{2}}invoice# {{3}}amount {{4}}days_overdue {{5}}upi {{6}}phone</div>
              </div>
              <div class="field">
                <label>Partial Payment Template Name</label>
                <div style="display:flex;gap:6px">
                  <input id="tpl-name-partial" placeholder="e.g. partial_payment" style="flex:1">
                  <input id="tpl-lang-partial" placeholder="en" style="width:48px;text-align:center">
                </div>
                <div style="font-size:10px;color:var(--muted);margin-top:3px">Params: {{1}}name {{2}}invoice# {{3}}paid_amount {{4}}remaining {{5}}due_date</div>
              </div>
              <div class="field">
                <label>Festival Greeting Template Name</label>
                <div style="display:flex;gap:6px">
                  <input id="tpl-name-festival" placeholder="e.g. festival_greeting" style="flex:1">
                  <input id="tpl-lang-festival" placeholder="en" style="width:48px;text-align:center">
                </div>
                <div style="font-size:10px;color:var(--muted);margin-top:3px">Params: {{1}}name {{2}}company {{3}}phone</div>
              </div>
            </div>

            <!-- Template samples to submit to Meta -->
            <div style="margin-top:16px;background:var(--bg);border-radius:8px;padding:14px;border:1px solid var(--border)">
              <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">📝 Suggested Template Content for Meta Approval</div>
              <details style="margin-bottom:8px"><summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--teal)">invoice_created — UTILITY</summary>
              <pre style="font-size:12px;background:#fff;padding:10px;border-radius:6px;margin-top:6px;white-space:pre-wrap;border:1px solid var(--border)">Hi {{1}},

Your invoice #{{2}} for ₹{{3}} has been created.
Due Date: {{4}}
Pay via UPI: {{5}}

Thank you for choosing {{6}}!</pre></details>
              <details style="margin-bottom:8px"><summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--amber)">payment_reminder — UTILITY</summary>
              <pre style="font-size:12px;background:#fff;padding:10px;border-radius:6px;margin-top:6px;white-space:pre-wrap;border:1px solid var(--border)">Hi {{1}},

Friendly reminder: Invoice #{{2}} for ₹{{3}} is due on {{4}}.
Pay via UPI: {{5}}

Thank you, {{6}}</pre></details>
              <details style="margin-bottom:8px"><summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--red)">payment_overdue — UTILITY</summary>
              <pre style="font-size:12px;background:#fff;padding:10px;border-radius:6px;margin-top:6px;white-space:pre-wrap;border:1px solid var(--border)">Hi {{1}},

Invoice #{{2}} for ₹{{3}} is overdue by {{4}} days.
Please pay immediately via UPI: {{5}}

Contact {{6}} for any queries.</pre></details>
              <details style="margin-bottom:8px"><summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--blue)">payment_received — UTILITY</summary>
              <pre style="font-size:12px;background:#fff;padding:10px;border-radius:6px;margin-top:6px;white-space:pre-wrap;border:1px solid var(--border)">Hi {{1}},

Payment received for Invoice #{{2}}!
Amount: ₹{{3}}
Date: {{4}}

Thank you for your prompt payment! — {{5}}</pre></details>
            </div>
            <button class="btn btn-primary" style="margin-top:14px" onclick="saveWASettings()"><i class="fas fa-save"></i> Save Template Settings</button>
          </div>
        </div>

        <!-- Send Manual Message -->
        <div class="settings-block">
          <div class="sb-title"><i class="fas fa-paper-plane"></i> Send Manual Message</div>
          <div class="form-grid g2">
            <div class="field"><label>Client</label>
              <select id="wa-manual-client" onchange="fillWaManualPhone()">
                <option value="">-- Select Client --</option>
              </select>
            </div>
            <div class="field"><label>WhatsApp Number</label>
              <input id="wa-manual-phone" placeholder="+91 XXXXX XXXXX">
            </div>
          </div>
          <div class="field"><label>Message</label>
            <textarea id="wa-manual-msg" style="min-height:80px" placeholder="Type your message here..."></textarea>
          </div>
          <button class="btn btn-whatsapp" style="margin-top:12px" onclick="sendManualWA()"><i class="fab fa-whatsapp"></i> Send Message</button>
        </div>

      </div>
    </div>

    <!-- ─────────── EMAIL SETUP ─────────── -->
    <div id="page-email-setup" class="page">
      <div class="settings-wrap">
        <div class="settings-block">
          <div class="sb-title"><i class="fas fa-envelope" style="color:#1976D2"></i> SMTP Configuration</div>
          <div class="form-grid g2">
            <div class="field"><label>From Email</label><input id="em-from" value="invoices@optmstech.in"></div>
            <div class="field"><label>From Name</label><input id="em-name" value="OPTMS Tech Invoices"></div>
            <div class="field"><label>SMTP Host</label><input id="em-host" placeholder="smtp.gmail.com"></div>
            <div class="field"><label>Port</label><input id="em-port" value="587"></div>
            <div class="field"><label>Username</label><input id="em-user" placeholder="your@gmail.com"></div>
            <div class="field"><label>App Password</label><input type="password" id="em-pass" placeholder="Gmail App Password"></div>
          </div>
          <div class="form-grid g1" style="margin-top:12px">
            <div class="field"><label>Subject Template</label><input id="em-subj" value="Invoice #{invoice_no} from OPTMS Tech – ₹{amount}"></div>
            <div class="field"><label>Email Body Template</label>
              <textarea id="em-body" style="min-height:120px">Dear {client_name},

Please find attached your invoice #{invoice_no} for ₹{amount} due on {due_date}.

Service: {service}

Bank Transfer: {bank_details}

Thank you for your business!

Best regards,
OPTMS Tech
optmstech.in | +91 XXXXX XXXXX</textarea>
            </div>
          </div>
          <div class="toggle-list">
            <div class="toggle-item"><span>Attach PDF to email</span><div class="tog on" onclick="this.classList.toggle('on')"></div></div>
            <div class="toggle-item"><span>CC yourself</span><div class="tog" onclick="this.classList.toggle('on')"></div></div>
          </div>
          <div style="display:flex;gap:10px;margin-top:16px">
            <button class="btn btn-email" onclick="testEmail()"><i class="fas fa-envelope"></i> Send Test Email</button>
            <button class="btn btn-primary" onclick="saveEmailSettings()"><i class="fas fa-save"></i> Save</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ─────────── SETTINGS ─────────── -->
    <div id="page-settings" class="page">
      <div class="settings-wrap">

        <!-- Admin Profile -->
        <div class="settings-block">
          <div class="sb-title"><i class="fas fa-user-circle"></i> Admin Profile</div>
          <div class="form-grid g2">
            <div class="field">
              <label>Profile Photo</label>
              <div style="display:flex;gap:12px;align-items:center">
                <div id="profile-avatar-preview" style="width:64px;height:64px;border-radius:12px;background:var(--teal);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:#fff;flex-shrink:0;overflow:hidden">
                  <?= strtoupper(substr($user['name'],0,2)) ?>
                </div>
                <div>
                  <label style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;font-size:12px;font-weight:600;color:var(--muted)">
                    <i class="fas fa-upload"></i> Upload Photo
                    <input type="file" accept="image/*" style="display:none" onchange="uploadProfilePhoto(this)">
                  </label>
                  <div style="font-size:10px;color:var(--muted);margin-top:4px">JPG, PNG, WebP — max 2MB</div>
                </div>
              </div>
            </div>
            <div></div>
            <div class="field"><label>Full Name</label><input id="profile-name" value="<?= htmlspecialchars($user['name']) ?>"></div>
            <div class="field"><label>Email</label><input type="email" id="profile-email" value="<?= htmlspecialchars($user['email']) ?>"></div>
            <div class="field"><label>New Password <span style="font-size:10px;font-weight:400;color:var(--muted)">(leave blank to keep current)</span></label>
              <input type="password" id="profile-pass" placeholder="New password (min 6 chars)" autocomplete="new-password">
            </div>
            <div class="field"><label>Confirm Password</label>
              <input type="password" id="profile-pass2" placeholder="Repeat new password" autocomplete="new-password">
            </div>
          </div>
          <button class="btn btn-primary" style="margin-top:14px" onclick="saveProfile()">
            <i class="fas fa-save"></i> Update Profile
          </button>
        </div>

        <div class="settings-block">
          <div class="sb-title"><i class="fas fa-building"></i> Company Profile</div>
          <div class="form-grid g2">
            <div class="field"><label>Company Name</label><input id="sc-name" value="OPTMS Tech"></div>
            <div class="field"><label>GST Number</label><input id="sc-gst" value="22AAAAA0000A1Z5"></div>
            <div class="field"><label>Phone</label><input id="sc-phone" value="+91 98765 43210"></div>
            <div class="field"><label>Email</label><input id="sc-email" value="optmstech@gmail.com"></div>
            <div class="field"><label>Website</label><input id="sc-web" value="www.optmstech.in"></div>
            <div class="field"><label>Invoice Prefix</label><input id="sc-prefix" value="OT-2025-"></div>
            <div class="field"><label>UPI ID</label><input id="sc-upi" value="optmstech@upi"></div>
            <div class="field g-full"><label>Default Bank Account Details <span style="font-size:10px;color:var(--muted)">(pre-fills in new invoices)</span></label>
              <textarea id="sc-bank" style="min-height:64px" placeholder="Bank: SBI | A/C: XXXXXXXXX | IFSC: SBIN0001234 | Name: Your Company | UPI: yourname@upi"></textarea>
            </div>
            <div class="field"><label>Default Currency</label>
              <select id="sc-cur"><option value="₹">INR (₹)</option><option value="$">USD ($)</option></select>
            </div>
            <div class="field g-full"><label>Address</label><textarea id="sc-addr">Patna, Bihar, India – 800001</textarea></div>
            <div class="field">
              <label>Company Logo</label>
              <div style="display:flex;gap:6px;align-items:stretch">
                <input id="sc-logo" placeholder="https://… or upload →" oninput="livePreview()" style="flex:1;min-width:0">
                <label style="display:flex;align-items:center;gap:5px;padding:0 12px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap;transition:.2s" onmouseover="this.style.borderColor='var(--teal)';this.style.color='var(--teal)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                  <i class="fas fa-upload"></i> Upload
                  <input type="file" accept="image/*" style="display:none" onchange="handleLogoUpload(this,'sc-logo','sc-logo-preview')">
                </label>
              </div>
              <div id="sc-logo-preview" style="margin-top:8px;min-height:0"></div>
            </div>
            <div class="field">
              <label>Authorised Signature</label>
              <div style="display:flex;gap:6px;align-items:stretch">
                <input id="sc-sign" placeholder="https://… or upload →" style="flex:1;min-width:0">
                <label style="display:flex;align-items:center;gap:5px;padding:0 12px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap;transition:.2s" onmouseover="this.style.borderColor='var(--teal)';this.style.color='var(--teal)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                  <i class="fas fa-pen-nib"></i> Upload Signature
                  <input type="file" accept="image/*" style="display:none" onchange="handleLogoUpload(this,'sc-sign','sc-sign-preview')">
                </label>
              </div>
              <div id="sc-sign-preview" style="margin-top:6px;min-height:0"></div>
              <div style="font-size:10px;color:var(--muted);margin-top:4px">Transparent PNG recommended for best results in PDF</div>
            </div>
          </div>
          <button class="btn btn-primary" style="margin-top:16px" onclick="saveCompanySettings()"><i class="fas fa-save"></i> Save Settings</button>
        </div>
        <div class="settings-block">
          <div class="sb-title"><i class="fas fa-sliders-h"></i> Invoice Defaults</div>
          <div class="form-grid g2">
            <div class="field"><label>Default GST Rate</label>
              <select id="sd-gst">
                <option value="0">0%</option><option value="5">5%</option><option value="12">12%</option>
                <option value="18" selected>18%</option><option value="28">28%</option>
              </select>
            </div>
            <div class="field"><label>Payment Due (days)</label>
              <input type="number" id="sd-due" value="15" min="1" max="365">
            </div>
            <div class="field"><label>Default Template</label>
              <select id="sd-tpl">
                <option value="1">1 – Classic Pro</option><option value="2">2 – Modern Teal</option>
                <option value="3">3 – Bold Dark</option><option value="4">4 – Minimal Clean</option>
                <option value="5">5 – Corporate Blue</option><option value="6">6 – Warm Orange</option>
                <option value="7">7 – Purple Gradient</option><option value="8">8 – Green Leaf</option>
                <option value="9">9 – Red Executive</option>
              </select>
            </div>
            <div class="field"><label>Invoice Number Prefix</label>
              <input id="sd-prefix" placeholder="OT-2025-" value="">
            </div>
            <div class="field"><label>Default Currency</label>
              <select id="sd-currency">
                <option value="₹">INR (₹)</option><option value="$">USD ($)</option><option value="€">EUR (€)</option>
              </select>
            </div>
            <div class="field" style="grid-column:1/-1"><label>Default Bank Details</label>
              <textarea id="sd-bank" style="min-height:60px" placeholder="Bank name, account, IFSC, UPI..."></textarea>
            </div>
            <div class="field" style="grid-column:1/-1"><label>Default Terms &amp; Conditions</label>
              <textarea id="sd-tnc" style="min-height:80px" placeholder="Enter default terms and conditions for all invoices..."></textarea>
            </div>
          </div>
          <button class="btn btn-primary" style="margin-top:14px" onclick="saveInvoiceDefaults()">
            <i class="fas fa-save"></i> Save Invoice Defaults
          </button>
        </div>
        <div class="settings-block">
          <div class="sb-title"><i class="fas fa-tags"></i> Service / Product Categories</div>
          <p style="font-size:12px;color:var(--muted);margin-bottom:12px">Create and color-code categories to organise your services and products.</p>
          <div id="cat-list" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px"></div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input id="cat-new-name" class="table-search" placeholder="Category name…" style="flex:1;min-width:140px;max-width:220px">
            <input type="color" id="cat-new-color" value="#00897B" style="width:36px;height:36px;border:1.5px solid var(--border);border-radius:7px;padding:2px;cursor:pointer;background:var(--card)">
            <button class="btn btn-primary" style="padding:6px 14px;font-size:13px" onclick="addCategory()"><i class="fas fa-plus"></i> Add</button>
          </div>
        </div>

        <!-- Item Types Manager -->
        <div class="settings-block">
          <div class="sb-title"><i class="fas fa-layer-group"></i> Line Item Types</div>
          <p style="font-size:12px;color:var(--muted);margin-bottom:12px">Manage item types shown in the invoice line-item "Type" dropdown. These are saved to the database and available across all invoices.</p>
          <div id="item-type-list" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px"></div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input id="itype-new-name" class="table-search" placeholder="Type name e.g. Subscription…" style="flex:1;min-width:160px;max-width:240px">
            <input type="color" id="itype-new-color" value="#1976D2" style="width:36px;height:36px;border:1.5px solid var(--border);border-radius:7px;padding:2px;cursor:pointer;background:var(--card)">
            <button class="btn btn-primary" style="padding:6px 14px;font-size:13px" onclick="addItemType()"><i class="fas fa-plus"></i> Add Type</button>
          </div>
          <p style="font-size:11px;color:var(--muted);margin-top:10px"><i class="fas fa-info-circle"></i> Default types (Service, Product, Labour, Other) are always available even if deleted — they will be recreated on page reload.</p>
        </div>
      </div>
    </div>

    <!-- ─────────── BACKUP ─────────── -->
    <div id="page-backup" class="page">
      <div class="settings-wrap">
        <div class="settings-block">
          <div class="sb-title"><i class="fas fa-database"></i> Backup & Export</div>
          <div class="backup-actions">
            <button class="backup-btn" onclick="exportAllJSON()"><i class="fas fa-file-code"></i><span>Export All Data (JSON)</span></button>
            <button class="backup-btn" onclick="exportCSV()"><i class="fas fa-file-csv"></i><span>Export Invoices (CSV)</span></button>
            <button class="backup-btn" onclick="importData()"><i class="fas fa-file-upload"></i><span>Import Data (JSON)</span></button>
            <button class="backup-btn" onclick="clearAllData()"><i class="fas fa-trash"></i><span>Clear All Data</span></button>
          </div>
          <div class="field" style="margin-top:16px"><label>Last Backup</label><input value="Never" readonly style="background:#f5f5f5"></div>
        </div>
      </div>
    </div><!-- /page-backup -->

    <!-- ─────────── MESSAGE LOG ─────────── -->
    <div id="page-msglog" class="page">
      <div class="page-toolbar">
        <div class="toolbar-left">
          <input id="msglog-search" type="text" placeholder="Search by client, invoice, type…" style="padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;width:260px;background:var(--card);color:var(--text)" oninput="renderMsgLog()">
          <select id="msglog-filter-type" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:var(--card);color:var(--text)" onchange="renderMsgLog()">
            <option value="">All Types</option>
            <option value="invoice_created">📄 New Invoice</option>
            <option value="payment_received">✅ Payment Receipt</option>
            <option value="partial_payment">💛 Partial Receipt</option>
            <option value="payment_overdue">🔴 Overdue Alert</option>
            <option value="payment_reminder">🔔 Due Reminder</option>
            <option value="split_payment">⚡ Split Payment</option>
            <option value="invoice_followup">📋 Follow-up</option>
          </select>
          <select id="msglog-filter-status" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:var(--card);color:var(--text)" onchange="renderMsgLog()">
            <option value="">All Status</option>
            <option value="sent_api">✅ Sent (API)</option>
            <option value="sent_web">📱 Sent (wa.me)</option>
            <option value="failed">❌ Failed</option>
            <option value="sending">⏳ Sending</option>
          </select>
        </div>
        <div class="toolbar-right">
          <button class="btn btn-outline" onclick="clearMsgLog()"><i class="fas fa-trash"></i> Clear Log</button>
          <button class="btn btn-outline" onclick="exportMsgLog()"><i class="fas fa-download"></i> Export CSV</button>
        </div>
      </div>
      <!-- Stats row -->
      <div id="msglog-stats" style="display:flex;gap:12px;flex-wrap:wrap;padding:0 0 16px"></div>
      <!-- Log table -->
      <div style="background:var(--card);border-radius:12px;border:1px solid var(--border);overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <thead>
            <tr style="background:var(--bg);border-bottom:2px solid var(--border)">
              <th style="padding:10px 14px;text-align:left;font-weight:700;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px">Time</th>
              <th style="padding:10px 14px;text-align:left;font-weight:700;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px">Type</th>
              <th style="padding:10px 14px;text-align:left;font-weight:700;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px">Client</th>
              <th style="padding:10px 14px;text-align:left;font-weight:700;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px">Invoice</th>
              <th style="padding:10px 14px;text-align:left;font-weight:700;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px">Status</th>
              <th style="padding:10px 14px;text-align:left;font-weight:700;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px">Message</th>
              <th style="padding:10px 14px;text-align:center;font-weight:700;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px">Action</th>
            </tr>
          </thead>
          <tbody id="msglog-tbody">
            <tr><td colspan="7" style="padding:40px;text-align:center;color:var(--muted)"><i class="fas fa-comments" style="font-size:32px;opacity:.2;display:block;margin-bottom:8px"></i>No messages logged yet</td></tr>
          </tbody>
        </table>
      </div>
    </div><!-- /page-msglog -->

  </div><!-- /pages-container -->
</div><!-- /main-wrap -->

<!-- ══════════════════════════════════════════
     MODALS
══════════════════════════════════════════ -->

<!-- Invoice Preview Modal -->
<div class="modal-overlay" id="modal-preview">
  <div class="modal modal-xl">
    <div class="modal-header"><span id="mp-title">Invoice Preview</span><button class="modal-close" onclick="closeModal('modal-preview')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body" id="mp-body" style="padding:24px;overflow-y:auto;max-height:75vh"></div>
    <div class="modal-footer">
      <button class="btn btn-primary" onclick="printFromModal()"><i class="fas fa-print"></i> Print / Save PDF</button>
      <button class="btn btn-whatsapp" onclick="sendWAFromModal()"><i class="fab fa-whatsapp"></i> WhatsApp</button>
      <button class="btn btn-email" onclick="sendEmailFromModal()"><i class="fas fa-envelope"></i> Email</button>
      <button class="btn btn-outline" onclick="closeModal('modal-preview')">Close</button>
    </div>
  </div>
</div>

<!-- Mark Paid Modal -->
<div class="modal-overlay" id="modal-paid">
  <div class="modal modal-sm">
    <div class="modal-header"><span>Mark Invoice as Paid</span><button class="modal-close" onclick="closeModal('modal-paid')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body" style="padding:24px">
      <!-- Invoice summary -->
      <div id="paid-inv-summary" style="background:var(--teal-bg);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div><span id="paid-inv-num" style="font-weight:700"></span> · <span id="paid-inv-client"></span></div>
          <div>Total: <strong id="paid-inv-total" style="color:var(--teal);font-family:var(--mono)"></strong></div>
        </div>
        <div id="paid-inv-remaining-row" style="display:none;margin-top:5px;padding-top:5px;border-top:1px solid var(--border);display:flex;justify-content:space-between">
          <span style="color:#E65100;font-size:11px">Already Paid: <strong id="paid-inv-already" style="font-family:var(--mono)"></strong></span>
          <span style="color:#C62828;font-size:11px;font-weight:700">Remaining Due: <strong id="paid-inv-remaining" style="font-family:var(--mono)"></strong></span>
        </div>
      </div>
      <div class="form-grid g2" style="gap:10px">
        <div class="field"><label>Payment Date</label><input type="date" id="paid-date"></div>
        <div class="field"><label>Payment Method</label>
          <select id="paid-method" onchange="toggleSplitPayment()">
            <option>UPI (GPay/PhonePe/Paytm)</option>
            <option>Bank Transfer (NEFT/RTGS)</option>
            <option>Cash</option>
            <option>Cheque</option>
            <option>Credit Card</option>
            <option value="Split">⚡ Split Payment (multiple methods)</option>
          </select>
        </div>
        <div class="field" id="paid-amt-field"><label>Amount Received (₹)</label>
          <input type="number" id="paid-amt" placeholder="0.00" oninput="onPaidAmtInput()">
        </div>
        <div class="field"><label>Transaction ID / UTR</label>
          <input id="paid-txn" placeholder="Ref / UTR Number">
        </div>
      </div>
      <!-- Remaining amount info (shown only for partial) -->
      <div id="paid-remaining-box" style="display:none;margin-top:12px;border-radius:12px;overflow:hidden;border:1.5px solid #FFD54F;box-shadow:0 2px 12px rgba(230,81,0,.1)">
        <!-- Header bar -->
        <div style="background:linear-gradient(135deg,#FF8F00,#FFA000);padding:10px 16px;display:flex;align-items:center;gap:8px">
          <i class="fas fa-exclamation-triangle" style="color:#fff;font-size:14px"></i>
          <span style="color:#fff;font-weight:700;font-size:13px">Partial Payment Detected</span>
        </div>
        <!-- Stats row -->
        <div style="background:#FFFDE7;padding:12px 16px">
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px">
            <div style="background:#fff;border-radius:8px;padding:10px 12px;border:1px solid #FFE082;text-align:center">
              <div style="font-size:9px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px">Invoice Total</div>
              <div id="paid-rem-total" style="font-size:14px;font-weight:800;color:#333;font-family:var(--mono)">₹0.00</div>
            </div>
            <div style="background:#fff;border-radius:8px;padding:10px 12px;border:1px solid #A5D6A7;text-align:center">
              <div style="font-size:9px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px">Received</div>
              <div id="paid-rem-received" style="font-size:14px;font-weight:800;color:#2E7D32;font-family:var(--mono)">₹0.00</div>
            </div>
            <div style="background:#fff;border-radius:8px;padding:10px 12px;border:1px solid #FFCDD2;text-align:center">
              <div style="font-size:9px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px">Remaining</div>
              <div id="paid-rem-due" style="font-size:14px;font-weight:800;color:#C62828;font-family:var(--mono)">₹0.00</div>
            </div>
          </div>
          <!-- Progress bar -->
          <div style="height:6px;background:#FFE082;border-radius:3px;margin-bottom:12px;overflow:hidden">
            <div id="paid-rem-bar" style="height:100%;background:linear-gradient(90deg,#43A047,#66BB6A);border-radius:3px;width:0%;transition:width .4s"></div>
          </div>
          <!-- Checkbox action -->
          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;background:#fff;border-radius:8px;padding:10px 12px;border:1.5px solid #FFD54F">
            <input type="checkbox" id="paid-collect-remaining" style="accent-color:#E65100;width:16px;height:16px;flex-shrink:0;margin-top:1px">
            <div>
              <div style="font-size:12px;font-weight:700;color:#E65100">Record as partial payment</div>
              <div style="font-size:11px;color:#795548;margin-top:2px">Invoice stays active — you can collect the remaining amount later. If unchecked, invoice will be marked Paid.</div>
            </div>
          </label>
        </div>
      </div>
      <!-- Split Payment Panel -->
      <div id="split-payment-panel" style="display:none;margin-top:10px;background:#F3F4F6;border-radius:10px;padding:12px;border:1.5px solid #E65100">
        <div style="font-size:12px;font-weight:700;color:#E65100;margin-bottom:10px">⚡ Split Payment — Enter amount per method</div>
        <div style="display:flex;flex-direction:column;gap:8px" id="split-rows">
          <div class="split-row" style="display:flex;gap:8px;align-items:center">
            <select class="split-method" style="flex:1;padding:7px 8px;border-radius:8px;border:1px solid var(--border);font-size:12px" onchange="renderSplitBreakdown()">
              <option>UPI (GPay/PhonePe/Paytm)</option>
              <option>Bank Transfer (NEFT/RTGS)</option>
              <option>Cash</option><option>Cheque</option><option>Credit Card</option>
            </select>
            <input type="number" class="split-amt" placeholder="0.00" value="" style="width:100px;padding:7px 8px;border-radius:8px;border:1px solid var(--border);font-size:12px" oninput="updateSplitTotal()">
            <button onclick="removeSplitRow(this)" style="padding:6px 10px;background:#FFEBEE;color:#C62828;border:none;border-radius:7px;cursor:pointer;font-size:12px">✕</button>
          </div>
          <div class="split-row" style="display:flex;gap:8px;align-items:center">
            <select class="split-method" style="flex:1;padding:7px 8px;border-radius:8px;border:1px solid var(--border);font-size:12px" onchange="renderSplitBreakdown()">
              <option>Cash</option>
              <option>UPI (GPay/PhonePe/Paytm)</option>
              <option>Bank Transfer (NEFT/RTGS)</option>
              <option>Cheque</option><option>Credit Card</option>
            </select>
            <input type="number" class="split-amt" placeholder="0.00" value="" style="width:100px;padding:7px 8px;border-radius:8px;border:1px solid var(--border);font-size:12px" oninput="updateSplitTotal()">
            <button onclick="removeSplitRow(this)" style="padding:6px 10px;background:#FFEBEE;color:#C62828;border:none;border-radius:7px;cursor:pointer;font-size:12px">✕</button>
          </div>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
          <button onclick="addSplitRow()" style="padding:6px 12px;background:#E8F5E9;color:#2E7D32;border:1.5px solid #A5D6A7;border-radius:7px;cursor:pointer;font-size:12px;font-weight:600">+ Add Method</button>
          <div style="font-size:12px">Split Total: <strong id="split-total" style="color:#E65100;font-family:var(--mono)">₹0.00</strong></div>
        </div>
        <!-- Breakdown bar: Total | Mode1 | Mode2 -->
        <div id="split-breakdown-bar" style="display:none;flex-wrap:wrap;gap:8px;align-items:center;margin-top:8px;padding:7px 10px;background:#fff;border-radius:7px;border:1px solid #e0e0e0;font-size:12px"></div>
        <div id="split-mismatch-warn" style="display:none;margin-top:8px;font-size:11px;color:#C62828;background:#FFEBEE;border-radius:6px;padding:6px 10px;font-weight:600"></div>
      </div>
      <div class="field" style="margin-top:10px"><label>Notes (optional)</label>
        <input id="paid-notes" placeholder="e.g. First instalment received">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-success" onclick="confirmPaid()"><i class="fas fa-check"></i> Confirm Payment</button>
      <button class="btn btn-outline" onclick="closeModal('modal-paid')">Cancel</button>
    </div>
  </div>
</div>

<!-- Add Client Modal -->
<div class="modal-overlay" id="modal-addclient">
  <div class="modal modal-md">
    <div class="modal-header"><span>Add New Client</span><button class="modal-close" onclick="closeModal('modal-addclient')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body" style="padding:24px">
      <div class="form-grid g2">
        <div class="field g-full"><label>Organization Name *</label><input id="nc-name" placeholder="Company or school name"></div>
        <div class="field"><label>Contact Person</label><input id="nc-person"></div>
        <div class="field"><label>WhatsApp</label><input id="nc-wa" placeholder="+91 XXXXX XXXXX"></div>
        <div class="field"><label>Email</label><input id="nc-email" type="email"></div>
        <div class="field"><label>GST Number</label><input id="nc-gst"></div>
        <div class="field"><label>Avatar Color</label><input type="color" id="nc-color" value="#00897B"></div>
        <div class="field g-full"><label>Address</label><textarea id="nc-addr"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary" onclick="saveNewClient()">Add Client</button>
      <button class="btn btn-outline" onclick="closeModal('modal-addclient')">Cancel</button>
    </div>
  </div>
</div>

<!-- Product Picker Modal -->
<div class="modal-overlay" id="modal-products">
  <div class="modal modal-md">
    <div class="modal-header"><span>Pick Service / Product</span><button class="modal-close" onclick="closeModal('modal-products')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body" style="padding:24px;max-height:60vh;overflow-y:auto">
      <input type="text" class="table-search" placeholder="Search services…" oninput="filterProductPicker(this.value)" style="margin-bottom:14px;width:100%">
      <div id="productPickerList"></div>
    </div>
  </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal-overlay" id="modal-delete">
  <div class="modal modal-sm">
    <div class="modal-header"><span>Delete Invoice</span><button class="modal-close" onclick="closeModal('modal-delete')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body" style="padding:24px;text-align:center">
      <i class="fas fa-trash" style="font-size:40px;color:#e53935;margin-bottom:12px"></i>
      <p>Are you sure you want to delete <strong id="del-inv-num"></strong>?<br>This action cannot be undone.</p>
    </div>
    <div class="modal-footer">
      <button class="btn" style="background:#e53935;color:#fff" onclick="confirmDelete()"><i class="fas fa-trash"></i> Delete</button>
      <button class="btn btn-outline" onclick="closeModal('modal-delete')">Cancel</button>
    </div>
  </div>
</div>

<!-- Receipt Modal -->

<!-- Receipt Modal (PHP build) -->
<div class="modal-overlay" id="modal-receipt">
  <div class="modal modal-md">
    <div class="modal-header"><span>Payment Receipt</span><button class="modal-close" onclick="closeModal('modal-receipt')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body" id="receiptBody" style="padding:24px;max-height:70vh;overflow-y:auto"></div>
    <div class="modal-footer">
      <button class="btn btn-primary" onclick="printReceiptModal()"><i class="fas fa-print"></i> Print Receipt</button>
      <button class="btn btn-outline" onclick="closeModal('modal-receipt')">Close</button>
    </div>
  </div>
</div>

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Row context menu -->
<div class="row-menu" id="rowMenu"></div>

<!-- ══ MAIN APP JS (embedded) ══ -->
<script>

// ══════════════════════════════════════════
// OPTMS Tech – data.js  (App State & Sample Data)
// ══════════════════════════════════════════

const STATE = {
  invoices: [],
  clients: [],
  products: [],
  payments: [],
  itemTypes: [
    {name:'Service',  color:'#00897B'},
    {name:'Product',  color:'#1976D2'},
    {name:'Labour',   color:'#E65100'},
    {name:'Other',    color:'#757575'},
  ],
  categories: [
    {name:'Web Development', color:'#1976D2'},
    {name:'Mobile App',      color:'#7B1FA2'},
    {name:'SEO / Marketing', color:'#F57F17'},
    {name:'Design',          color:'#E53935'},
    {name:'Hosting',         color:'#00897B'},
    {name:'Consulting',      color:'#455A64'},
    {name:'Other',           color:'#757575'},
  ],
  settings: {
    company: 'OPTMS Tech',
    gst: '22AAAAA0000A1Z5',
    phone: '+91 98765 43210',
    email: 'optmstech@gmail.com',
    website: 'www.optmstech.in',
    prefix: 'OT-2025-',
    upi: 'optmstech@upi',
    address: 'Patna, Bihar, India – 800001',
    logo: '',
    waToken: '',
    waPid: '',
    activeTemplate: 1,
    defaultGST: 18,
    dueDays: 15
  },
  currentPage: 1,
  invoicesPerPage: 10,
  filteredInvoices: [],
  activeMenuInvoiceId: null,
  editingInvoiceId: null,
  sortField: 'num',
  sortDir: 'desc'
};

// ── CLIENTS (loaded from DB via API) ──
STATE.clients = [];

// ── PRODUCTS (loaded from DB via API) ──
STATE.products = [];

// ── INVOICES (loaded from DB via API) ──
STATE.invoices = [];
STATE.filteredInvoices = [];

// ── PAYMENTS (loaded from DB via API) ──
STATE.payments = [];

// ── CHART DATA ──
const CHART_DATA = {
  monthly: {
    labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    revenue: [115000,110000,165000,245800,0,0,0,0,0,0,0,0],
    paid:    [115000,110000,145000,185000,0,0,0,0,0,0,0,0],
    pending: [0,0,20000,60800,0,0,0,0,0,0,0,0]
  },
  weekly: {
    labels: ['W1','W2','W3','W4','W5','W6','W7','W8'],
    revenue: [38000,52000,61000,70000,45000,82000,55000,66000],
    paid:    [38000,45000,55000,65000,40000,75000,50000,60000],
    pending: [0,7000,6000,5000,5000,7000,5000,6000]
  },
  yearly: {
    labels: ['2021','2022','2023','2024','2025'],
    revenue: [320000,580000,870000,1020000,635800],
    paid:    [300000,550000,840000,990000,550000],
    pending: [20000,30000,30000,30000,85800]
  }
};

// ── Calendar Events ──
const CAL_EVENTS = [
  { date: '2025-04-24', type: 'due',     label: '#OT-2025-034' },
  { date: '2025-04-22', type: 'due',     label: '#OT-2025-033' },
  { date: '2025-04-20', type: 'due',     label: '#OT-2025-032' },
  { date: '2025-04-18', type: 'overdue', label: '#OT-2025-031' },
  { date: '2025-04-16', type: 'due',     label: '#OT-2025-030' },
  { date: '2025-04-14', type: 'due',     label: '#OT-2025-029' },
  { date: '2025-04-09', type: 'paid',    label: '#OT-2025-034' },
  { date: '2025-04-05', type: 'paid',    label: '#OT-2025-032' },
];



// ══════════════════════════════════════════
// OPTMS Tech – app.js   (All Features Working)
// ══════════════════════════════════════════

// ── Items state for create form
let formItems = [];
const _nd=new Date(); let calYear=_nd.getFullYear(), calMonth=_nd.getMonth();
let revenueChartInstance = null;
let donutChartInstance = null;

// ══════════════════════════════════════════
// INIT
// ══════════════════════════════════════════
window.addEventListener('DOMContentLoaded', () => {
  setTodayDates();
  addItem();
  updateClientDropdown();
  renderDashboard();
  renderInvoicesTable();
  renderClients();
  renderProducts();
  renderPayments();
  renderTemplatesGrid();
  setTimeout(livePreview, 100);
  STATE.filteredInvoices = [...STATE.invoices];
  document.addEventListener('click', closeAllDropdowns);
});

function setTodayDates() {
  const today = new Date();
  const dueDays = parseInt(STATE.settings.dueDays) || 15;
  const due = new Date(); due.setDate(today.getDate() + dueDays);
  document.getElementById('f-date').value = fmt_date(today);
  document.getElementById('f-due').value  = fmt_date(due);
  document.getElementById('paid-date').value = fmt_date(today);
}
function updateDueFromIssue() {
  const dateEl = document.getElementById('f-date');
  const dueEl  = document.getElementById('f-due');
  if (!dateEl || !dueEl) return;
  const issueDate = new Date(dateEl.value);
  if (isNaN(issueDate)) return;
  const dueDays = parseInt(STATE.settings.dueDays) || 15;
  const due = new Date(issueDate);
  due.setDate(issueDate.getDate() + dueDays);
  dueEl.value = fmt_date(due);
}
function fmt_date(d) { return d.toISOString().split('T')[0]; }
function fmt_money(n, sym='₹') { return sym + parseFloat(n||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }

// ══════════════════════════════════════════
// SIDEBAR & PAGE NAVIGATION
// ══════════════════════════════════════════
function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const btn = document.getElementById('sidebarToggle');
  sb.classList.toggle('collapsed');
  const collapsed = sb.classList.contains('collapsed');
  btn.style.left = collapsed ? '63px' : (parseInt(getComputedStyle(document.documentElement).getPropertyValue('--sidebar-w'))||240) - 1 + 'px';
  btn.querySelector('i').className = collapsed ? 'fas fa-chevron-right' : 'fas fa-bars';
}

const breadcrumbs = {
  dashboard:'Dashboard', invoices:'Invoices', create:'Create Invoice',
  clients:'Clients', products:'Services & Products', payments:'Payments',
  reports:'Reports', templates:'PDF Templates', whatsapp:'WhatsApp Setup',
  'email-setup':'Email Setup', settings:'Settings', backup:'Backup & Export',
  msglog:'Message Log'
};

function showPage(name, el) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const page = document.getElementById('page-' + name);
  if (page) page.classList.add('active');
  if (el) el.classList.add('active');
  else {
    const nav = document.querySelector(`.nav-item[data-page="${name}"]`);
    if (nav) nav.classList.add('active');
  }
  document.getElementById('breadcrumb').textContent = breadcrumbs[name] || name;
  if (name === 'reports') renderReports();
  if (name === 'create') { if (!STATE._editingNext) { STATE.editingInvoiceId = null; resetCreateForm(); setTimeout(livePreview,50); } STATE._editingNext = false; }
  if (name === 'payments') renderPayments();
  if (name === 'products') renderProducts();
  if (name === 'clients') { updateClientDropdown(); renderClients(); }
  if (name === 'dashboard') renderDashboard();
  if (name === 'templates') { renderTemplatesGrid(); setTimeout(populateTemplateForm,100); }
  if (name === 'whatsapp')  { setTimeout(populateWAPage, 100); setTimeout(renderFestivalCampaigns, 200); }
  if (name === 'settings')  populateSettingsForm();
  if (name === 'msglog')    renderMsgLog();
}

// ══════════════════════════════════════════
// DASHBOARD
// ══════════════════════════════════════════
function renderDashboard() {
  renderRevenueChart('monthly');
  renderDonutChart();
  renderCalendar();
  renderDashRecent();
  renderDashKpis();
  renderDashTopClients();
  renderDashAlerts();
  renderNotifications();
  updateDashStats();
}
function updateDashStats() {
  const e = id => document.getElementById(id);
  const now = new Date();
  const thisMonth = now.getMonth(), thisYear = now.getFullYear();
  const lastMonth = thisMonth === 0 ? 11 : thisMonth - 1;
  const lastYear  = thisMonth === 0 ? thisYear - 1 : thisYear;

  const paid    = STATE.invoices.filter(i=>i.status==='Paid').reduce((s,i)=>s+(parseFloat(i.amount)||0),0);
  // Include partial payments actually received (from payments table)
  const partialReceived = STATE.payments
    .filter(p => { const inv = STATE.invoices.find(i=>String(i.id)===String(p.invoice_id)); return inv && inv.status !== 'Paid'; })
    .reduce((s,p)=>s+(parseFloat(p.amount)||0),0);
  const totalRevenue = paid + partialReceived;
  const pend    = STATE.invoices.filter(i=>i.status==='Pending').reduce((s,i)=>s+(parseFloat(i.amount)||0),0);
  const over    = STATE.invoices.filter(i=>i.status==='Overdue').reduce((s,i)=>s+(parseFloat(i.amount)||0),0);
  // Partial remaining (unpaid portion of partial invoices)
  const partialRemaining = STATE.invoices.filter(i=>i.status==='Partial').reduce((s,i)=>{
    const pmts = STATE.payments.filter(p=>String(p.invoice_id)===String(i.id));
    const alreadyPaid = pmts.reduce((a,p)=>a+parseFloat(p.amount||0),0);
    return s + Math.max(0, (parseFloat(i.amount)||0) - alreadyPaid);
  },0);
  const pendCnt = STATE.invoices.filter(i=>i.status==='Pending').length;
  const overCnt = STATE.invoices.filter(i=>i.status==='Overdue').length;
  const partialCnt = STATE.invoices.filter(i=>i.status==='Partial').length;

  // This month vs last month revenue
  const revThisM = STATE.invoices.filter(i=>{
    if(!i.issued) return false;
    const d=new Date(i.issued);
    return d.getMonth()===thisMonth && d.getFullYear()===thisYear && i.status==='Paid';
  }).reduce((s,i)=>s+(parseFloat(i.amount)||0),0);
  const revLastM = STATE.invoices.filter(i=>{
    if(!i.issued) return false;
    const d=new Date(i.issued);
    return d.getMonth()===lastMonth && d.getFullYear()===lastYear && i.status==='Paid';
  }).reduce((s,i)=>s+(parseFloat(i.amount)||0),0);
  const revChange = revLastM > 0 ? Math.round((revThisM-revLastM)/revLastM*100) : 0;

  const invThisM = STATE.invoices.filter(i=>{
    if(!i.issued) return false;
    const d=new Date(i.issued);
    return d.getMonth()===thisMonth && d.getFullYear()===thisYear;
  }).length;

  if(e('s-revenue')) e('s-revenue').textContent = fmt_money(totalRevenue);
  if(e('s-pending')) e('s-pending').textContent = fmt_money(pend);
  if(e('s-overdue')) e('s-overdue').textContent = fmt_money(over);
  if(e('s-total'))   e('s-total').textContent   = STATE.invoices.length;
  if(e('s-clients')) e('s-clients').textContent = STATE.clients.length;

  // Combined outstanding card (pending + overdue + partial remaining)
  const combinedOutstanding = pend + over + partialRemaining;
  const combinedCount = pendCnt + overCnt + partialCnt;
  const outEl = e('s-outstanding-card');
  if (outEl) {
    const urgColor = over > 0 ? '#B71C1C' : combinedOutstanding > 0 ? '#E65100' : '#388E3C';
    outEl.innerHTML = `
      <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <div style="flex:1;min-width:160px">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:${urgColor};opacity:.8;margin-bottom:2px">Total Outstanding</div>
          <div style="font-size:26px;font-weight:900;color:${urgColor};font-family:var(--mono);line-height:1">${fmt_money(combinedOutstanding)}</div>
          <div style="font-size:11px;color:var(--muted);margin-top:3px">${combinedCount} invoice${combinedCount!==1?'s':''} need attention</div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <div style="text-align:center;padding:8px 14px;background:rgba(230,81,0,.08);border:1.5px solid rgba(230,81,0,.2);border-radius:10px">
            <div style="font-size:18px;font-weight:800;color:#E65100">${fmt_money(pend)}</div>
            <div style="font-size:10px;color:var(--muted);font-weight:700">🕐 Pending (${pendCnt})</div>
          </div>
          <div style="text-align:center;padding:8px 14px;background:rgba(183,28,28,.08);border:1.5px solid rgba(183,28,28,.2);border-radius:10px">
            <div style="font-size:18px;font-weight:800;color:#B71C1C">${fmt_money(over)}</div>
            <div style="font-size:10px;color:var(--muted);font-weight:700">🔴 Overdue (${overCnt})</div>
          </div>
          <div style="text-align:center;padding:8px 14px;background:rgba(255,152,0,.08);border:1.5px solid rgba(255,152,0,.25);border-radius:10px">
            <div style="font-size:18px;font-weight:800;color:#E65100">${fmt_money(partialRemaining)}</div>
            <div style="font-size:10px;color:var(--muted);font-weight:700">💛 Partial Due (${partialCnt})</div>
          </div>
        </div>
      </div>`;
  }

  // Update trend texts
  if(e('s-revenue-trend')) {
    const sign = revChange >= 0 ? '+' : '';
    e('s-revenue-trend').innerHTML = `<i class="fas fa-${revChange>=0?'arrow-up':'arrow-down'}"></i> ${sign}${revChange}% vs last month`;
    e('s-revenue-trend').className = `stat-trend ${revChange>=0?'up':'down'}`;
  }
  if(e('s-pending-trend'))  e('s-pending-trend').innerHTML  = `<i class="fas fa-minus"></i> ${pendCnt} invoice${pendCnt!==1?'s':''}`;
  if(e('s-overdue-trend'))  e('s-overdue-trend').innerHTML  = `<i class="fas fa-exclamation-circle"></i> ${overCnt} invoice${overCnt!==1?'s':''}`;
  if(e('s-total-trend'))    e('s-total-trend').innerHTML    = `<i class="fas fa-file-invoice"></i> ${invThisM} this month`;
  if(e('s-clients-trend'))  e('s-clients-trend').innerHTML  = `<i class="fas fa-users"></i> ${STATE.clients.length} total`;
}

function buildLiveChartData(mode) {
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const now = new Date();
  if (mode === 'monthly') {
    const year = now.getFullYear();
    const paid = Array(12).fill(0), pend = Array(12).fill(0);
    STATE.invoices.forEach(inv => {
      if (!inv.issued) return;
      const d = new Date(inv.issued);
      if (d.getFullYear() !== year) return;
      const m = d.getMonth();
      if (inv.status === 'Paid')    paid[m] += parseFloat(inv.amount)||0;
      if (inv.status === 'Pending') pend[m] += parseFloat(inv.amount)||0;
    });
    return { labels: months, paid, pending: pend };
  }
  if (mode === 'weekly') {
    const weeks = ['W1','W2','W3','W4','W5','W6','W7','W8'];
    const paid = Array(8).fill(0), pend = Array(8).fill(0);
    const baseDate = new Date(now.getFullYear(), now.getMonth(), 1);
    STATE.invoices.forEach(inv => {
      if (!inv.issued) return;
      const d = new Date(inv.issued);
      const diffDays = Math.floor((d - baseDate) / 86400000);
      const wk = Math.min(Math.max(Math.floor(diffDays / 7), 0), 7);
      if (inv.status === 'Paid')    paid[wk] += parseFloat(inv.amount)||0;
      if (inv.status === 'Pending') pend[wk] += parseFloat(inv.amount)||0;
    });
    return { labels: weeks, paid, pending: pend };
  }
  // yearly
  const curYear = now.getFullYear();
  const years = [curYear-3, curYear-2, curYear-1, curYear].map(String);
  const paid = Array(4).fill(0), pend = Array(4).fill(0);
  STATE.invoices.forEach(inv => {
    if (!inv.issued) return;
    const yr = new Date(inv.issued).getFullYear();
    const idx = years.indexOf(String(yr));
    if (idx < 0) return;
    if (inv.status === 'Paid')    paid[idx] += parseFloat(inv.amount)||0;
    if (inv.status === 'Pending') pend[idx] += parseFloat(inv.amount)||0;
  });
  return { labels: years, paid, pending: pend };
}

function renderRevenueChart(mode) {
  const ctx = document.getElementById('revenueChart');
  if (!ctx) return;
  const d = buildLiveChartData(mode);
  if (revenueChartInstance) revenueChartInstance.destroy();
  revenueChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: d.labels,
      datasets: [
        { label: 'Paid', data: d.paid, backgroundColor: 'rgba(0,137,123,.75)', borderRadius: 5, borderSkipped: false },
        { label: 'Pending', data: d.pending, backgroundColor: 'rgba(249,168,37,.65)', borderRadius: 5, borderSkipped: false }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false, animation: { duration: 600 },
      plugins: { legend: { position:'top', labels:{ font:{family:"'Public Sans'",size:11}, boxWidth:10 } } },
      scales: {
        x: { stacked:true, grid:{display:false}, ticks:{font:{family:"'Public Sans'",size:10},maxRotation:0} },
        y: { stacked:true, grid:{color:'#F0F0F0'}, beginAtZero:true,
             ticks:{font:{family:"'Public Sans'",size:10}, callback:v=>'₹'+(v>=1000?(v/1000)+'K':v)} }
      }
    }
  });
}

function switchChart(mode, btn) {
  document.querySelectorAll('.cf-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  renderRevenueChart(mode);
}

function renderDonutChart() {
  const ctx = document.getElementById('donutChart');
  if (!ctx) return;
  const paid    = STATE.invoices.filter(i=>i.status==='Paid').length;
  const pending = STATE.invoices.filter(i=>i.status==='Pending').length;
  const overdue = STATE.invoices.filter(i=>i.status==='Overdue').length;
  const draft   = STATE.invoices.filter(i=>i.status==='Draft').length;
  if (donutChartInstance) donutChartInstance.destroy();
  donutChartInstance = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Paid','Pending','Overdue','Draft'],
      datasets: [{ data: [paid,pending,overdue,draft], backgroundColor: ['#4CAF50','#FFA726','#EF5350','#BDBDBD'], borderWidth: 2, borderColor: '#fff' }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '65%',
      plugins: { legend: { display: false } }
    }
  });
  const legend = document.getElementById('donutLegend');
  if (!legend) return;
  const colors = ['#4CAF50','#FFA726','#EF5350','#BDBDBD'];
  const vals   = [paid,pending,overdue,draft];
  const labels = ['Paid','Pending','Overdue','Draft'];
  legend.innerHTML = labels.map((l,i) => `<div class="dl-item"><div class="dl-dot" style="background:${colors[i]}"></div><span class="dl-label">${l}</span><span class="dl-val">${vals[i]}</span></div>`).join('');
}

function renderDashRecent() {
  const el = document.getElementById('dashRecentList');
  if (!el) return;
  const recent = [...STATE.invoices].reverse().slice(0,8);
  if (!recent.length) { el.innerHTML='<div style="text-align:center;padding:24px;color:var(--muted)">No invoices yet</div>'; return; }
  el.innerHTML = recent.map(inv => {
    const c = STATE.clients.find(x=>x.id===inv.client)||{name:inv.clientName||inv.client||'Unknown',color:'#00897B'};
    const initials = getInitials(c.name);
    const pmt = STATE.payments.find(p=>p.inv===inv.num);
    const pmtTag = pmt ? `<span style="font-size:9px;padding:1px 5px;border-radius:4px;background:var(--teal-bg);color:var(--teal);font-weight:700;margin-left:4px">${pmt.method.split(' ')[0]}</span>` : '';
    const df = d => d ? new Date(d).toLocaleDateString('en-IN',{day:'2-digit',month:'short'}) : '';
    return `<div class="dash-recent-item">
      <div class="dri-avatar" style="background:${c.color}">${c.image?`<img src="${c.image}" style="width:100%;height:100%;object-fit:cover;border-radius:8px">`:initials}</div>
      <div class="dri-info">
        <div class="dri-name">${inv.num}${pmtTag}</div>
        <div class="dri-meta">${c.name} · ${inv.service}</div>
        <div class="dri-meta" style="margin-top:1px;font-size:10px"><i class="fas fa-calendar-alt" style="color:var(--muted2);width:10px"></i> ${df(inv.issued)} &nbsp;·&nbsp; Due: <span style="color:${inv.status==='Overdue'?'var(--red)':'inherit'}">${df(inv.due)}</span></div>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <div class="dri-amount">${fmt_money(inv.amount)}</div>
        <span class="badge badge-${inv.status.toLowerCase()}">${inv.status}</span>
      </div>
    </div></div>`;
  }).join('');
}

// ── CALENDAR ──
function renderCalendar() {
  const el = document.getElementById('calendarWidget');
  if (!el) return;
  const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  const days = ['Su','Mo','Tu','We','Th','Fr','Sa'];
  const firstDay = new Date(calYear, calMonth, 1).getDay();
  const daysInMonth = new Date(calYear, calMonth+1, 0).getDate();
  const today = new Date();
  // Build events from live invoices
  const evMap = {};
  STATE.invoices.forEach(inv => {
    if (!inv.due) return;
    if (!evMap[inv.due]) evMap[inv.due] = [];
    const t = inv.status==='Paid'?'paid':inv.status==='Overdue'?'overdue':'due';
    evMap[inv.due].push({type:t, label:inv.num});
  });
  CAL_EVENTS.forEach(e => { if (!evMap[e.date]) evMap[e.date]=[]; evMap[e.date].push(e); });
  let html = `<div class="cal-month-title">${monthNames[calMonth]} ${calYear}</div><div class="cal-grid">`;
  days.forEach(d => { html += `<div class="cal-day-name">${d}</div>`; });
  for (let i=0; i<firstDay; i++) html += '<div class="cal-day other-month"></div>';
  for (let d=1; d<=daysInMonth; d++) {
    const ds = `${calYear}-${String(calMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const evs = evMap[ds]||[];
    const isToday = today.getFullYear()===calYear && today.getMonth()===calMonth && today.getDate()===d;
    const evType = evs[0]?.type||'';
    let cls = 'cal-day';
    if (isToday) cls += ' today';
    else if (evType==='due') cls += ' has-due';
    else if (evType==='overdue') cls += ' has-overdue';
    else if (evType==='paid') cls += ' has-paid';
    const tip = evs.map(e=>e.label).join(', ');
    const dot = evs.length>1 ? `<span style="position:absolute;top:1px;right:2px;font-size:7px;font-weight:800;color:${isToday?'#fff':'var(--teal)'}">${evs.length}</span>` : '';
    html += `<div class="${cls}" title="${tip}" style="position:relative">${d}${dot}</div>`;
  }
  html += '</div>';
  el.innerHTML = html;
}
function calPrev() { calMonth--; if (calMonth < 0) { calMonth=11; calYear--; } renderCalendar(); }
function calNext() { calMonth++; if (calMonth > 11) { calMonth=0; calYear++; } renderCalendar(); }

// ══════════════════════════════════════════
// INVOICES TABLE
// ══════════════════════════════════════════
function renderInvoicesTable() {
  STATE.filteredInvoices = [...STATE.invoices];
  applyFiltersAndRender();
}

function applyFiltersAndRender() {
  const tbody = document.getElementById('invoicesTbody');
  if (!tbody) return;
  const start = (STATE.currentPage - 1) * STATE.invoicesPerPage;
  const end   = start + STATE.invoicesPerPage;
  const page  = STATE.filteredInvoices.slice(start, end);

  tbody.innerHTML = page.map(inv => {
    const c = STATE.clients.find(x=>x.id===inv.client) || { name:'Unknown', color:'#607D8B' };
    const initials = getInitials(c.name);
    const avatar = c.image
      ? `<div class="cc-avatar" style="background:${c.color}"><img src="${c.image}" alt="${c.name}"></div>`
      : `<div class="cc-avatar" style="background:${c.color}">${initials}</div>`;
    return `<tr data-id="${inv.id}">
      <td><input type="checkbox" class="inv-check" value="${inv.id}"></td>
      <td><code style="font-family:var(--mono);color:var(--teal);font-weight:600">${inv.num}</code></td>
      <td><div class="client-cell">${avatar}<div><div class="cc-name">${c.name}</div><div class="cc-sub">${c.person||''}</div></div></div></td>
      <td>${inv.service}</td>
      <td>${inv.issued}</td>
      <td>${inv.due}</td>
      <td><strong style="font-family:var(--mono)">${fmt_money(inv.amount)}</strong></td>
      <td><span class="badge badge-${inv.status.toLowerCase()}">${inv.status}</span></td>
      <td>
        <div class="action-cell">
          <button class="act-btn" title="Preview" onclick="openPreviewModal('${inv.id}')"><i class="fas fa-eye"></i></button>
          <button class="act-btn del" title="Delete" onclick="openDeleteModal('${inv.id}')"><i class="fas fa-trash"></i></button>
          <button class="act-btn menu-btn" title="More" onclick="openRowMenu(event,'${inv.id}')"><i class="fas fa-ellipsis-v"></i></button>
        </div>
      </td>
    </tr>`;
  }).join('') || `<tr><td colspan="9" style="text-align:center;padding:40px;color:var(--muted)"><i class="fas fa-file-invoice" style="font-size:32px;margin-bottom:12px;display:block;opacity:.3"></i>No invoices found</td></tr>`;

  renderPagination();
  const info = document.getElementById('tfInfo');
  if (info) info.textContent = `Showing ${start+1}–${Math.min(end, STATE.filteredInvoices.length)} of ${STATE.filteredInvoices.length}`;
}

function renderPagination() {
  const pg = document.getElementById('pagination');
  if (!pg) return;
  const total = Math.ceil(STATE.filteredInvoices.length / STATE.invoicesPerPage);
  let html = `<button class="pg-btn" onclick="gotoPage(${STATE.currentPage-1})" ${STATE.currentPage<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
  for (let i = 1; i <= Math.min(total, 10); i++) {
    html += `<button class="pg-btn ${i===STATE.currentPage?'active':''}" onclick="gotoPage(${i})">${i}</button>`;
  }
  html += `<button class="pg-btn" onclick="gotoPage(${STATE.currentPage+1})" ${STATE.currentPage>=total?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
  pg.innerHTML = html;
}
function gotoPage(p) {
  const total = Math.ceil(STATE.filteredInvoices.length / STATE.invoicesPerPage);
  if (p < 1 || p > total) return;
  STATE.currentPage = p;
  applyFiltersAndRender();
}

function filterInvoices(val) {
  const v = val.toLowerCase();
  STATE.filteredInvoices = STATE.invoices.filter(inv => {
    const c = STATE.clients.find(x=>x.id===inv.client);
    return inv.num.toLowerCase().includes(v) ||
      (c && c.name.toLowerCase().includes(v)) ||
      inv.service.toLowerCase().includes(v) ||
      inv.status.toLowerCase().includes(v);
  });
  const sf = document.getElementById('statusFilter');
  const sv = sf ? sf.value : '';
  if (sv) STATE.filteredInvoices = STATE.filteredInvoices.filter(i => i.status === sv);
  STATE.currentPage = 1;
  applyFiltersAndRender();
}

function filterByStatus(val) {
  STATE.filteredInvoices = val
    ? STATE.invoices.filter(i => i.status === val)
    : [...STATE.invoices];
  const sv = document.getElementById('invSearch')?.value;
  if (sv) filterInvoices(sv); else { STATE.currentPage=1; applyFiltersAndRender(); }
}

function filterByService(val) {
  STATE.filteredInvoices = val
    ? STATE.invoices.filter(i => i.service === val)
    : [...STATE.invoices];
  STATE.currentPage = 1;
  applyFiltersAndRender();
}

function filterByDate() {
  const from = document.getElementById('dateFrom')?.value;
  const to   = document.getElementById('dateTo')?.value;
  STATE.filteredInvoices = STATE.invoices.filter(i => {
    if (from && i.issued < from) return false;
    if (to && i.issued > to) return false;
    return true;
  });
  STATE.currentPage = 1;
  applyFiltersAndRender();
}

function sortTable(field) {
  if (STATE.sortField === field) STATE.sortDir = STATE.sortDir === 'asc' ? 'desc' : 'asc';
  else { STATE.sortField = field; STATE.sortDir = 'asc'; }
  STATE.filteredInvoices.sort((a, b) => {
    let av = a[field], bv = b[field];
    if (field === 'amount') { av = +av; bv = +bv; }
    if (field === 'client') {
      av = (STATE.clients.find(c=>c.id===a.client)||{}).name||'';
      bv = (STATE.clients.find(c=>c.id===b.client)||{}).name||'';
    }
    if (av < bv) return STATE.sortDir==='asc' ? -1 : 1;
    if (av > bv) return STATE.sortDir==='asc' ? 1 : -1;
    return 0;
  });
  applyFiltersAndRender();
}

function selectAllInv(cb) {
  document.querySelectorAll('.inv-check').forEach(c => c.checked = cb.checked);
}

// ══════════════════════════════════════════
// ROW MENU
// ══════════════════════════════════════════
function openRowMenu(e, id) {
  e.stopPropagation();
  STATE.activeMenuInvoiceId = id;
  const inv = STATE.invoices.find(i=>String(i.id)===String(id));
  const st  = inv ? inv.status : '';
  const isPaid       = st === 'Paid';
  const isDraft      = st === 'Draft';
  const isCancelled  = st === 'Cancelled';
  // Edit only allowed for Draft invoices
  const canEdit      = isDraft;
  const editDisabled = !canEdit;
  const editReason   = isPaid ? '(paid)' : isCancelled ? '(cancelled)' : !isDraft ? '(locked)' : '';
  // Mark paid not allowed for Paid, Cancelled, or Draft (must be made Pending first)
  const canMarkPaid  = !isPaid && !isCancelled && !isDraft;
  // Cancel: not for Paid or already Cancelled
  const canCancel    = !isPaid && !isCancelled;
  const menu = document.getElementById('rowMenu');
  const _editOnclick = editDisabled ? '' : "rowMenuAction('edit')";
  const _paidOnclick = canMarkPaid  ? "rowMenuAction('paid')" : '';
  menu.innerHTML = `
    <div class="rm-item" onclick="rowMenuAction('preview')"><i class="fas fa-eye"></i> Preview</div>
    <div class="rm-item ${editDisabled?'rm-disabled':''}" onclick="${_editOnclick}" style="${editDisabled?'opacity:.4;cursor:not-allowed;':''}">
      <i class="fas fa-edit"></i> Edit Invoice ${editDisabled?`<small style="font-size:9px">${editReason}</small>`:''}
    </div>
    ${isDraft ? `<div class="rm-item" onclick="rowMenuAction('make-pending')" style="color:#E65100"><i class="fas fa-paper-plane"></i> Make Pending</div>` : ''}
    <div class="rm-item" onclick="rowMenuAction('download')"><i class="fas fa-download"></i> Download PDF</div>
    <div class="rm-item" onclick="rowMenuAction('duplicate')"><i class="fas fa-copy"></i> Duplicate</div>
    <div class="rm-item" onclick="rowMenuAction('wa')"><i class="fab fa-whatsapp"></i> Send WhatsApp</div>
    <div class="rm-item" onclick="rowMenuAction('email')"><i class="fas fa-envelope"></i> Send Email</div>
    <div class="rm-item ${canMarkPaid?'':'rm-disabled'}" onclick="${_paidOnclick}" style="${canMarkPaid?'':'opacity:.4;cursor:not-allowed'}">
      <i class="fas fa-check-circle"></i> Mark as Paid ${isPaid?'(already paid)':isCancelled?'(cancelled)':isDraft?'(make pending first)':''}
    </div>
    ${canCancel ? `<div class="rm-item" onclick="rowMenuAction('cancel')" style="color:#E65100"><i class="fas fa-ban"></i> Cancel Invoice</div>` : ''}
    <div class="rm-item rm-danger" onclick="rowMenuAction('delete')"><i class="fas fa-trash"></i> Delete</div>`;
  // Smart positioning: flip upward if near screen bottom
  menu.style.visibility = 'hidden';
  menu.style.display = 'block';
  const menuH = menu.offsetHeight || 320;
  const menuW = menu.offsetWidth  || 190;
  menu.style.display = '';
  menu.style.visibility = '';
  const spaceBelow = window.innerHeight - e.clientY;
  const top  = spaceBelow < menuH + 10 ? Math.max(4, e.clientY - menuH - 4) : e.clientY + 4;
  const left = Math.min(e.clientX - 160, window.innerWidth - menuW - 8);
  menu.style.top  = top  + 'px';
  menu.style.left = Math.max(4, left) + 'px';
  menu.classList.add('open');
}

function rowMenuAction(action) {
  const id = STATE.activeMenuInvoiceId;
  const inv = STATE.invoices.find(i=>String(i.id)===String(id));
  closeAllDropdowns();
  if (!inv) return;
  if (action === 'preview' || action === 'download') { openPreviewModal(id); return; }
  if (action === 'edit')         { editInvoice(id); return; }
  if (action === 'duplicate')    { duplicateInvoice(id); return; }
  if (action === 'wa')           { sendWAForInvoice(inv); return; }
  if (action === 'email')        { sendEmailForInvoice(inv); return; }
  if (action === 'paid')         { openPaidModal(id); return; }
  if (action === 'delete')       { openDeleteModal(id); return; }
  if (action === 'make-pending') { changeInvoiceStatus(id, 'Pending'); return; }
  if (action === 'cancel')       { confirmCancelInvoice(id); return; }
}

function closeAllDropdowns(e) {
  // Don't close if clicking inside notif panel or bell button
  const notifPanel = document.getElementById('notifPanel');
  const bellBtn = document.getElementById('notifBellBtn');
  if (notifPanel && !notifPanel.contains(e?.target) && !bellBtn?.contains(e?.target)) {
    notifPanel.classList.remove('open');
  }
  if (!e?.target?.closest('.act-btn.menu-btn')) {
    document.getElementById('rowMenu').classList.remove('open');
  }
  const sr = document.getElementById('searchResults');
  if (sr && !sr.contains(e?.target) && !document.getElementById('globalSearch')?.contains(e?.target)) {
    sr.classList.remove('open');
  }
}

// ══════════════════════════════════════════
// CREATE / EDIT INVOICE
// ══════════════════════════════════════════
function resetCreateForm() {
  const prefix = STATE.settings.prefix || 'INV-';
  // Find highest existing numeric suffix for this prefix to avoid collisions
  let nextSeq = 1;
  if (STATE.invoices.length > 0) {
    STATE.invoices.forEach(inv => {
      const num = inv.num || inv.invoice_number || '';
      if (num.startsWith(prefix)) {
        const suffix = num.slice(prefix.length);
        const n = parseInt(suffix, 10);
        if (!isNaN(n) && n >= nextSeq) nextSeq = n + 1;
      }
    });
    // If no invoices matched the current prefix, still fall back to total count + 1
    if (nextSeq === 1 && STATE.invoices.length > 0) nextSeq = STATE.invoices.length + 1;
  }
  document.getElementById('f-num').value = prefix + String(nextSeq).padStart(3,'0');
  // Load defaults from settings
  const bankEl = document.getElementById('f-bank');
  if (bankEl) bankEl.value = STATE.settings.defaultBank || '';
  const gstEl = document.getElementById('f-gst');
  if (gstEl) gstEl.value = String(STATE.settings.defaultGST ?? 18);
  const tncEl = document.getElementById('f-tnc');
  if (tncEl) tncEl.value = STATE.settings.defaultTnC ||
    '1. All prices are inclusive of applicable taxes.\n2. Disputes must be raised within 7 days.\n3. Computer-generated invoice. Subject to Patna jurisdiction.';
  setTodayDates();
  // Clear client fields
  document.getElementById('f-cname').value = '';
  document.getElementById('f-cperson').value = '';
  document.getElementById('f-cwa').value = '';
  document.getElementById('f-cemail').value = '';
  document.getElementById('f-cgst').value = '';
  document.getElementById('f-caddr').value = '';
  // Clear other form fields
  document.getElementById('f-disc').value = '0';
  const discTypeEl = document.getElementById('f-disc-type'); if (discTypeEl) discTypeEl.value = 'pct';
  document.getElementById('f-gst').value = String(STATE.settings.defaultGST ?? 18);
  const notesEl = document.getElementById('f-notes'); if (notesEl) notesEl.value = '';
  const svcEl = document.getElementById('f-service'); if (svcEl) svcEl.value = '';
  const currEl = document.getElementById('f-currency'); if (currEl) currEl.value = '₹';
  const tplEl = document.getElementById('f-template'); if (tplEl) tplEl.value = String(STATE.settings.activeTemplate || 1);
  const clientSelEl = document.getElementById('f-client'); if (clientSelEl) clientSelEl.value = '';
  // Reset company logo, qr to defaults
  const qrEl = document.getElementById('f-qr'); if (qrEl) qrEl.value = '';
  document.querySelectorAll('input[name="inv-status"]')[0].checked = true;
  formItems = [];
  addItem();
}

function addItem() {
  const fgst = document.getElementById('f-gst');
  const gstVal = fgst ? fgst.value : String(STATE.settings.defaultGST ?? 18);
  const defaultGst = (gstVal !== '' && gstVal !== null) ? parseInt(gstVal) : (STATE.settings.defaultGST ?? 18);
  formItems.push({ id: Date.now(), desc: '', itemType: 'Service', qty: 1, gst: defaultGst, rate: 0 });
  renderFormItems();
}

function renderFormItems() {
  const el = document.getElementById('itemsList');
  if (!el) return;
  el.innerHTML = formItems.map(item => {
    const base     = (item.qty||1)*(item.rate||0);
    const gstRate  = parseFloat(item.gst ?? 0);
    const gstAmt   = base * gstRate / 100;
    const lineTotal = base + gstAmt;   // GST-inclusive total
    const itemType = item.itemType || 'Service';
    return `
    <div class="item-row" id="item-${item.id}">
      <div class="item-desc"><input value="${item.desc}" placeholder="Service / item description" oninput="updateItem(${item.id},'desc',this.value)"></div>
      <div class="item-type"><select onchange="updateItem(${item.id},'itemType',this.value)">
        ${(STATE.itemTypes||[{name:'Service'},{name:'Product'},{name:'Labour'},{name:'Other'}]).map(t=>`<option value="${t.name}" ${itemType===t.name?'selected':''}>${t.name}</option>`).join('')}
      </select></div>
      <div class="item-qty"><input type="number" value="${item.qty}" min="1" oninput="updateItem(${item.id},'qty',this.value)"></div>
      <div class="item-rate"><input type="number" value="${item.rate}" min="0" placeholder="0" oninput="updateItem(${item.id},'rate',this.value)"></div>
      <div class="item-amount" id="iamt-${item.id}" title="Amount (excl. GST)">${fmt_money(base)}</div>
      <div class="item-gst"><select onchange="updateItem(${item.id},'gst',this.value)">
        <option value="0" ${item.gst==0?'selected':''}>0%</option>
        <option value="5" ${item.gst==5?'selected':''}>5%</option>
        <option value="12" ${item.gst==12?'selected':''}>12%</option>
        <option value="18" ${item.gst==18?'selected':''}>18%</option>
        <option value="28" ${item.gst==28?'selected':''}>28%</option>
      </select></div>
      <div class="item-total" id="itot-${item.id}" title="Total (incl. GST)">${fmt_money(lineTotal)}</div>
      <button class="item-del" onclick="removeItem(${item.id})" title="Remove"><i class="fas fa-times"></i></button>
    </div>`;
  }).join('');
  calcTotals();
}

function updateItem(id, field, val) {
  const item = formItems.find(i=>i.id===id);
  if (!item) return;
  if (field === 'gst') {
    item.gst = (val !== '' && val !== null && val !== undefined) ? parseFloat(val) : 0;
  } else if (field === 'itemType') {
    item.itemType = val;
  } else {
    item[field] = field==='desc' ? val : (parseFloat(val)||0);
  }
  const base    = (item.qty||1)*(item.rate||0);
  const gstAmt  = base * (parseFloat(item.gst ?? 0)/100);
  const amt = document.getElementById('iamt-'+id);
  if (amt) amt.textContent = fmt_money(base);
  const tot = document.getElementById('itot-'+id);
  if (tot) tot.textContent = fmt_money(base + gstAmt);  // GST-inclusive
  calcTotals();
}

function removeItem(id) {
  formItems = formItems.filter(i=>i.id!==id);
  renderFormItems();
}

function calcTotals() {
  // Per-item GST calculation
  let sub = 0, gstAmt = 0;
  formItems.forEach(item => {
    const lineAmt = (item.qty||1)*(item.rate||0);
    sub += lineAmt;
    const gstRate = parseFloat(item.gst ?? 0);
    gstAmt += lineAmt * gstRate / 100;
  });
  const disc    = parseFloat(document.getElementById('f-disc')?.value) || 0;
  const discType = document.getElementById('f-disc-type')?.value || 'pct';
  const discAmt = discType === 'fixed' ? Math.min(disc, sub) : sub * disc / 100;
  const discPct = sub > 0 ? (discAmt / sub * 100) : 0;
  // Recalculate GST after discount proportionally
  const discFactor = sub > 0 ? (1 - discAmt/sub) : 1;
  const gstAfterDisc = gstAmt * discFactor;
  const grand = sub - discAmt + gstAfterDisc;

  const set = (id, val) => { const e = document.getElementById(id); if(e) e.textContent = val; };
  set('tp-sub',    fmt_money(sub));
  set('tp-disc',   '-'+fmt_money(discAmt)+(discType==='fixed'?' (₹ fixed)':disc>0?' ('+disc+'%)':''));
  set('tp-amount', fmt_money(sub - discAmt));
  set('tp-gst',    '+'+fmt_money(gstAfterDisc));
  // Show GST breakdown per item
  const bd = document.getElementById('tp-gst-breakdown');
  if (bd) {
    const rates = [...new Set(formItems.filter(i=>parseFloat(i.gst??0)>0).map(i=>parseFloat(i.gst??0)))];
    if (rates.length <= 1) {
      bd.textContent = rates.length ? rates[0]+'% on subtotal' : '';
    } else {
      bd.textContent = formItems.filter(i=>parseFloat(i.gst??0)>0)
        .map(i => { const b=(i.qty||1)*(i.rate||0); return parseFloat(i.gst)+'% on '+fmt_money(b); })
        .join(' + ');
    }
  }
  set('tp-grand', fmt_money(grand));

  // Update the global GST selector display (show blended or first item rate)
  livePreview();
  return { sub, discAmt, gstAmt: gstAfterDisc, grand };
}

function fillClientForm(val) {
  const c = STATE.clients.find(x => x.id === val);
  if (!c) return;
  document.getElementById('f-cname').value   = c.name;
  document.getElementById('f-cperson').value = c.person;
  document.getElementById('f-cwa').value     = c.wa;
  document.getElementById('f-cemail').value  = c.email;
  document.getElementById('f-cgst').value    = c.gst;
  document.getElementById('f-caddr').value   = c.addr;
  livePreview();
}

// ══════════════════════════════════════════
// LIVE PREVIEW + 9 PDF TEMPLATES
// ══════════════════════════════════════════
function getFormData() {
  const tpl     = parseInt(document.getElementById('f-template')?.value)||1;
  const num     = document.getElementById('f-num')?.value||(STATE.settings.prefix||'INV-')+String(STATE.invoices.length+1).padStart(3,'0');
  const date    = document.getElementById('f-date')?.value||'';
  const due     = document.getElementById('f-due')?.value||'';
  const svc     = document.getElementById('f-service')?.value||'';
  const cname   = document.getElementById('f-cname')?.value||'Client Name';
  const cperson = document.getElementById('f-cperson')?.value||'';
  const cemail  = document.getElementById('f-cemail')?.value||'';
  const cwa     = document.getElementById('f-cwa')?.value||'';
  const cgst    = document.getElementById('f-cgst')?.value||'';
  const caddr   = document.getElementById('f-caddr')?.value||'';
  const disc    = parseFloat(document.getElementById('f-disc')?.value) || 0;
  const discType = document.getElementById('f-disc-type')?.value || 'pct';
  const notes   = document.getElementById('f-notes')?.value||'';
  const bank    = document.getElementById('f-bank')?.value||'';
  const tnc     = document.getElementById('f-tnc')?.value||'';
  const generatedBy = document.getElementById('f-generated-by')?.value || 'OPTMS Tech Invoice Manager';
  const showGeneratedBy = document.getElementById('f-show-generated')?.checked !== false;
  const status  = document.querySelector('input[name="inv-status"]:checked')?.value||'Draft';
  const sym     = document.getElementById('f-currency')?.value||'₹';
  // Logos
  const companyLogo = document.getElementById('f-company-logo')?.value || STATE.settings.logo || '';
  // Ensure STATE.settings.logo is always up to date
  if (companyLogo && !STATE.settings.logo) STATE.settings.logo = companyLogo;
  const clientLogo  = document.getElementById('f-client-logo')?.value||'';
  const signature   = document.getElementById('f-signature')?.value || STATE.settings.signature || '';
  const qrUrl       = document.getElementById('f-qr')?.value||'';
  // PDF options
  const popt = {
    bank:       document.getElementById('popt-bank')?.checked !== false,
    qr:         document.getElementById('popt-qr')?.checked || false,
    sign:       document.getElementById('popt-sign')?.checked || false,
    logo:       document.getElementById('popt-logo')?.checked !== false,
    clientLogo: document.getElementById('popt-client-logo')?.checked || false,
    notes:      document.getElementById('popt-notes')?.checked !== false,
    tnc:        document.getElementById('popt-tnc')?.checked !== false,
    gstCol:     document.getElementById('popt-gst-col')?.checked !== false,
    footer:     document.getElementById('popt-footer')?.checked !== false,
    watermark:  document.getElementById('popt-watermark')?.checked || false,
  };

  // Per-item GST totals
  let sub = 0, gstAmt = 0;
  formItems.forEach(item => {
    const line = (item.qty||1)*(item.rate||0);
    sub += line;
    gstAmt += line * (parseFloat(item.gst)||0) / 100;
  });
  const discAmt      = discType === 'fixed' ? Math.min(disc, sub) : sub * disc / 100;
  const discPct      = sub > 0 ? (discAmt / sub * 100) : 0;
  const discFactor   = sub > 0 ? (1 - discAmt/sub) : 1;
  const gstAfterDisc = gstAmt * discFactor;
  const grand        = sub - discAmt + gstAfterDisc;

  const invId = STATE.editingInvoiceId ? String(STATE.editingInvoiceId) : '';
  return { tpl, num, date, due, svc, cname, cperson, cemail, cwa, cgst, caddr, disc: discPct, discRaw: disc, discType, notes, bank, tnc, status, sym, sub, discAmt, gstAmt: gstAfterDisc, grand, companyLogo, clientLogo, signature, qrUrl, popt, generatedBy, showGeneratedBy, invId };
}

function livePreview() {
  const wrap = document.getElementById('invoicePreviewWrap');
  if (!wrap) return;
  try {
    const d = getFormData();
    const scale = 0.685;
    const scaledH = Math.round(1123 * scale);
    // Set the wrapper dimensions so the preview-scroll knows the height
    wrap.style.cssText = `width:545px;height:${scaledH}px;overflow:hidden;position:relative;border-radius:6px;box-shadow:0 2px 16px rgba(0,0,0,.12);background:#fff`;
    const inner = document.createElement('div');
    inner.style.cssText = `width:794px;transform:scale(${scale});transform-origin:top left;position:absolute;top:0;left:0;pointer-events:none`;
    inner.innerHTML = buildInvoiceHTML(d, false);
    wrap.innerHTML = '';
    wrap.appendChild(inner);
    // Sync template dropdowns
    const ps = document.getElementById('prevTplSelect');
    if (ps && ps.value !== String(d.tpl)) ps.value = d.tpl;
  } catch(e) {
    console.warn('livePreview error:', e);
    const wrap2 = document.getElementById('invoicePreviewWrap');
    if (wrap2) wrap2.innerHTML = `<div style="padding:20px;color:#e53935;font-size:12px">Preview error: ${e.message}</div>`;
  }
}

function buildInvoiceHTML(d, forPrint) {
  const sc = STATE.settings;
  // Build items HTML with GST column
  const showGstCol = d.popt ? d.popt.gstCol : true;
  const itemsHTML = formItems.length
    ? formItems.map(i => {
        const line    = (i.qty||1)*(i.rate||0);
        const itemGst = parseFloat(i.gst ?? 0);
        const gstAmt  = line * itemGst / 100;
        const lineInclGst = line + gstAmt;
        const itype = i.itemType||'Service';
        return `<tr>
          <td style="padding:9px 12px;border-bottom:1px solid #eee">${i.desc||'—'}</td>
          <td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee;font-size:11px;color:#888">${itype}</td>
          <td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee">${i.qty}</td>
          <td style="padding:9px 12px;text-align:right;border-bottom:1px solid #eee">${fmt_money(i.rate,d.sym)}</td>
          <td style="padding:9px 12px;text-align:right;border-bottom:1px solid #eee">${fmt_money(line,d.sym)}</td>
          ${showGstCol ? `<td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee">${itemGst}%</td>` : ''}
          <td style="padding:9px 12px;text-align:right;font-weight:700;border-bottom:1px solid #eee">${fmt_money(lineInclGst,d.sym)}</td>
        </tr>`;
      }).join('')
    : `<tr><td colspan="${showGstCol?7:6}" style="padding:20px;text-align:center;color:#aaa">No items added</td></tr>`;

  const gstColHeader = showGstCol ? `<th style="padding:10px 12px;text-align:center">GST%</th>` : '';

  const templates = { 1:buildTpl1, 2:buildTpl2, 3:buildTpl3, 4:buildTpl4, 5:buildTpl5, 6:buildTpl6, 7:buildTpl7, 8:buildTpl8, 9:buildTpl9 };
  const fn = templates[d.tpl] || buildTpl1;
  return fn(d, sc, itemsHTML, gstColHeader);
}

// ── Shared helpers for templates ──
function tplLogoHTML(d, sc) {
  const C        = window.TPL_CUSTOM || {};
  const font      = C.font             || "'Public Sans',sans-serif";
  const tagline   = C.tagline          || '';
  const nameSize  = (C.companyNameSize  ? parseInt(C.companyNameSize) : 28) + 'px';
  const nameColor = C.companyNameColor  || '#ffffff';
  const nameWt    = C.companyNameWeight || '800';
  const company   = sc.company || STATE.settings.company || '';
  const logo      = d.companyLogo || d.logo || sc.logo || STATE.settings.logo || '';
  const showLogo  = !d.popt || d.popt.logo !== false;

  const nameDiv = `<div style="font-size:${nameSize};font-weight:${nameWt};color:${nameColor};letter-spacing:-0.5px;font-family:${font};line-height:1.1;margin-top:6px">${company}</div>`;
  const tagDiv  = tagline ? `<div style="font-size:11px;opacity:.65;margin-top:3px;font-family:${font};color:${nameColor}">${tagline}</div>` : '';

  if (showLogo && logo) {
    return `<div>
      <img src="${logo}" style="height:52px;max-width:200px;object-fit:contain;display:block" onerror="this.style.display='none'">
      ${nameDiv}${tagDiv}
    </div>`;
  }
  return `<div>${nameDiv}${tagDiv}</div>`;
}
function tplClientLogoHTML(d) {
  if (!d.popt.clientLogo || !d.clientLogo) return '';
  return `<img src="${d.clientLogo}" style="height:36px;max-width:120px;object-fit:contain;display:block;margin-bottom:6px" onerror="this.style.display='none'">`;
}
// Full company info block for PDF header (used by all templates)
function tplCompanyInfoHTML(sc, textColor='rgba(255,255,255,.65)', smallColor='rgba(255,255,255,.45)') {
  const co = sc.company||STATE.settings.company||'';
  const ph = sc.phone||STATE.settings.phone||'';
  const em = sc.email||STATE.settings.email||'';
  const ws = sc.website||STATE.settings.website||'';
  const gst = sc.gst||STATE.settings.gst||'';
  const addr = sc.address||STATE.settings.address||'';
  return (co?`<div style="color:${textColor};font-size:11px;font-weight:600;margin-top:7px">${co}</div>`:'')
       + (ph?`<div style="color:${smallColor};font-size:10px;margin-top:3px">📞 ${ph}</div>`:'')
       + (em?`<div style="color:${smallColor};font-size:10px;margin-top:2px">✉ ${em}</div>`:'')
       + (ws?`<div style="color:${smallColor};font-size:10px;margin-top:2px">${ws}</div>`:'')
       + (gst?`<div style="color:${smallColor};font-size:10px;margin-top:2px">GST: ${gst}</div>`:'')
       + (addr?`<div style="color:${smallColor};font-size:10px;margin-top:3px;line-height:1.5;max-width:200px">${addr.replace(/\n/g,'<br>')}</div>`:'');
}

function tplWatermark(d) {
  // Determine watermark text and color based on status
  let wText = '', wColor = '';
  if (d.status === 'Paid') {
    wText  = (window.TPL_CUSTOM && TPL_CUSTOM.watermarkText) ? TPL_CUSTOM.watermarkText : 'PAID';
    wColor = 'rgba(0,150,0,.12)';
    if (!d.popt || !d.popt.watermark) return '';
  } else if (d.status === 'Cancelled') {
    wText = 'CANCELLED'; wColor = 'rgba(183,28,28,.15)';
  } else if (d.status === 'Partial') {
    wText = 'PARTIAL'; wColor = 'rgba(255,152,0,.13)';
  } else if (d.status === 'Pending') {
    wText = 'PENDING'; wColor = 'rgba(255,152,0,.10)';
  } else if (d.status === 'Overdue') {
    wText = 'OVERDUE'; wColor = 'rgba(229,57,53,.12)';
  } else if (d.status === 'Draft') {
    wText = 'DRAFT'; wColor = 'rgba(0,0,0,.07)';
  } else {
    return '';
  }
  return `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-35deg);font-size:80px;font-weight:900;color:${wColor};z-index:0;pointer-events:none;white-space:nowrap;letter-spacing:8px">${wText}</div>`;
}
function tplBankHTML(d, color='#00695C', bg='#e0f2f1', border='') {
  if (d.status === 'Paid') return '';  // Hide bank details on paid invoices
  const bankText = d.bank || STATE.settings.defaultBank || '';
  if (!bankText) return '';
  if (d.popt && d.popt.bank === false) return '';
  const sc  = STATE.settings;
  const upi = sc.upi || '';
  const upiLine = upi
    ? `<div style="margin-top:8px;background:rgba(0,0,0,.06);border-radius:6px;padding:8px 10px;font-size:13px;font-weight:800;letter-spacing:.5px">📲 UPI: ${upi}</div>`
    : '';
  return `<div style="margin-top:16px;background:${bg};border-radius:8px;padding:12px 14px;font-size:11px;color:${color};line-height:1.8;${border}">
    <div style="font-weight:700;font-size:12px;margin-bottom:4px">💳 Payment Details:</div>
    <div>${bankText.replace(/\|/g,'<span style="opacity:.4;margin:0 4px">|</span>')}</div>
    ${upiLine}
  </div>`;
}
function tplQrHTML(d) {
  if (!d.popt.qr || !d.qrUrl) return '';
  return `<div style="margin-top:12px;display:flex;align-items:center;gap:12px"><img src="${d.qrUrl}" style="width:80px;height:80px;border-radius:6px;border:1px solid #eee" onerror="this.style.display='none'"><div style="font-size:11px;color:#888">Scan QR to pay via UPI<br><strong>${STATE.settings.upi}</strong></div></div>`;
}
function tplSignHTML(d, sc_arg, label='Authorised Signatory') {
  // sc_arg is optional - for backwards compat where called with just (d)
  const sc = (sc_arg && typeof sc_arg === 'object' && sc_arg.company) ? sc_arg : STATE.settings;
  if (!d.popt.sign) return '';
  const sig = d.signature || sc.signature || '';
  const sigImg = sig
    ? `<img src="${sig}" style="height:52px;max-width:180px;object-fit:contain;display:block;margin-left:auto" onerror="this.style.display='none'">`
    : `<div style="width:160px;border-bottom:1.5px solid #bbb;margin-left:auto;height:44px"></div>`;
  return `<div style="margin-top:24px;display:flex;justify-content:space-between;align-items:flex-end">
    <div style="font-size:10px;color:#999;line-height:1.8">
      ${sc.phone ? `<span style="margin-right:12px"><i style="font-family:sans-serif">📞</i> ${sc.phone}</span>` : ''}
      ${sc.email ? `<span><i style="font-family:sans-serif">✉</i> ${sc.email}</span>` : ''}
    </div>
    <div style="text-align:right">
      ${sigImg}
      <div style="font-size:10px;color:#aaa;margin-top:5px;font-weight:600">${label}</div>
      <div style="font-size:10px;color:#bbb">${sc.company}</div>
    </div>
  </div>`;
}
function tplNotesHTML(d, color='#795548', bg='#fff8e1') {
  if (!d.popt || !d.popt.notes) return '';
  const isPaid = d.status === 'Paid';
  if (isPaid) {
    // Show positive thank-you message for paid invoices instead of notes
    const sc = STATE.settings;
    return `<div style="margin-top:10px;background:linear-gradient(135deg,#E8F5E9,#F1F8E9);border-radius:8px;padding:12px 14px;font-size:11px;color:#2E7D32;line-height:1.8;border-left:3px solid #4CAF50">
      <div style="font-weight:800;font-size:13px;margin-bottom:4px">🎉 Thank You for Your Payment!</div>
      <div>We appreciate your prompt payment and continued trust in <strong>${sc.company||''}</strong>. Your account is now clear and up to date.</div>
      <div style="margin-top:6px;opacity:.8">We look forward to serving you again. For any queries, reach us at ${sc.phone||sc.email||''}.</div>
    </div>`;
  }
  if (!d.notes) return '';
  return `<div style="margin-top:10px;background:${bg};border-radius:8px;padding:10px 14px;font-size:11px;color:${color};line-height:1.6">${d.notes}</div>`;
}
function tplTncHTML(d, color='#888') {
  if (!d.popt.tnc || !d.tnc) return '';
  return `<div style="margin-top:12px;border-top:1px solid #eee;padding-top:10px"><div style="font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#aaa;margin-bottom:5px">Terms & Conditions</div><div style="font-size:10.5px;color:${color};line-height:1.7">${d.tnc}</div></div>`;
}

// ── TEMPLATE 1: Classic Pro ──
function buildTpl1(d, sc, itemsHTML, gstColHeader) {
  sc = resolveCompany(sc);
  const showGst = d.popt ? d.popt.gstCol : true;
  const colSpan = showGst ? 5 : 4;
  return `<div style="font-family:'Public Sans',sans-serif;background:#fff;width:794px;min-height:1123px;position:relative;overflow:hidden">
  ${tplWatermark(d)}
  <div style="background:linear-gradient(135deg,${(window.TPL_CUSTOM&&TPL_CUSTOM.color1)||'#1A2332'} 0%,${(window.TPL_CUSTOM&&TPL_CUSTOM.color1+'dd')||'#263348'} 100%);padding:40px 48px 32px;color:#fff;display:flex;justify-content:space-between;align-items:flex-start;font-family:${(window.TPL_CUSTOM&&TPL_CUSTOM.font)||'inherit'}">
    <div>
      ${tplLogoHTML(d,sc)}
      <div style="color:rgba(255,255,255,.75);font-size:12px;font-weight:600;margin-top:8px">${sc.company||STATE.settings.company}</div>
      ${(sc.phone||STATE.settings.phone)?`<div style="color:rgba(255,255,255,.55);font-size:11px;margin-top:3px">📞 ${sc.phone||STATE.settings.phone}</div>`:''}
      ${(sc.email||STATE.settings.email)?`<div style="color:rgba(255,255,255,.55);font-size:11px;margin-top:2px">✉ ${sc.email||STATE.settings.email}</div>`:''}
      ${(sc.website||STATE.settings.website)?`<div style="color:rgba(255,255,255,.45);font-size:10px;margin-top:2px">${sc.website||STATE.settings.website}</div>`:''}
      ${(sc.gst||STATE.settings.gst)?`<div style="color:rgba(255,255,255,.45);font-size:10px;margin-top:2px">GST: ${sc.gst||STATE.settings.gst}</div>`:''}
      ${(sc.address||STATE.settings.address)?`<div style="color:rgba(255,255,255,.4);font-size:10px;margin-top:3px;max-width:200px;line-height:1.5">${(sc.address||STATE.settings.address).replace(/\n/g,'<br>')}</div>`:''}
    </div>
    <div style="text-align:right">
      <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:6px">Tax Invoice</div>
      <div style="font-size:26px;font-weight:800;color:${(window.TPL_CUSTOM&&TPL_CUSTOM.color2)||'#4DB6AC'};font-family:monospace">#${d.num}</div>
      <div style="margin-top:8px;background:${statusColor(d.status)};color:#fff;padding:5px 14px;border-radius:20px;font-size:11px;font-weight:700;display:inline-block">${d.status.toUpperCase()}</div>
    </div>
  </div>
  <div style="padding:32px 48px">
    <div style="display:flex;gap:20px;margin-bottom:26px">
      <div style="flex:1;background:#f8f9fa;border-radius:10px;padding:16px">
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:8px">Billed To</div>
        ${tplClientLogoHTML(d)}
        <div style="font-weight:700;font-size:15px;color:#1A2332">${d.cname}</div>
        ${d.cperson?`<div style="color:#666;font-size:12px;margin-top:3px">${d.cperson}</div>`:''}
        ${d.cemail?`<div style="color:#888;font-size:11px;margin-top:2px">${d.cemail}</div>`:''}
        ${d.cwa?`<div style="color:#888;font-size:11px;margin-top:2px">${d.cwa}</div>`:''}
        ${d.caddr?`<div style="color:#888;font-size:11px;margin-top:4px;line-height:1.6">${d.caddr.replace(/\n/g,'<br>')}</div>`:''}
        ${d.cgst?`<div style="color:#888;font-size:11px;margin-top:4px">GST: ${d.cgst}</div>`:''}
      </div>
      <div style="flex:1;background:#f8f9fa;border-radius:10px;padding:16px">
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:8px">Invoice Details</div>
        ${[['Issue Date',d.date],['Due Date',d.due],['Service',d.svc]].map(([k,v])=>v?`<div style="display:flex;justify-content:space-between;font-size:12px;padding:4px 0;border-bottom:1px solid #eee"><span style="color:#888">${k}</span><span style="font-weight:600;color:#1A2332">${v}</span></div>`:'').join('')}
        <div style="margin-top:12px;text-align:right"><span style="font-size:11px;color:#888">Grand Total</span><br><span style="font-size:22px;font-weight:800;color:${(window.TPL_CUSTOM&&TPL_CUSTOM.color2)||'#4DB6AC'};font-family:monospace">${fmt_money(d.grand,d.sym)}</span></div>
      </div>
    </div>
    <table style="width:100%;border-collapse:collapse;margin-bottom:18px">
      <thead><tr style="background:${(window.TPL_CUSTOM&&TPL_CUSTOM.color1)||'#1A2332'}">
        <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:rgba(255,255,255,.8);text-transform:uppercase">Description</th>
        <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:700;color:rgba(255,255,255,.8)">Type</th>
        <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:700;color:rgba(255,255,255,.8)">Qty</th>
        <th style="padding:10px 12px;text-align:right;font-size:11px;font-weight:700;color:rgba(255,255,255,.8)">Rate</th>
        <th style="padding:10px 12px;text-align:right;font-size:11px;font-weight:700;color:rgba(255,255,255,.8)">Amount</th>
        ${gstColHeader.replace(/style="([^"]+)"/,'style="$1;color:rgba(255,255,255,.8);font-size:11px;font-weight:700;text-transform:uppercase"')||''}
        <th style="padding:10px 12px;text-align:right;font-size:11px;font-weight:700;color:rgba(255,255,255,.8)">Total</th>
      </tr></thead>
      <tbody>${itemsHTML}</tbody>
    </table>
    <div style="display:flex;gap:20px;align-items:flex-start">
      <div style="flex:1">
        ${tplBankHTML(d,'#00695C','#e0f2f1')}
        ${tplQrHTML(d)}
        ${tplNotesHTML(d)}
      </div>
      <div style="width:260px;background:#f8f9fa;border-radius:10px;padding:16px;flex-shrink:0">
        ${totalsRows(d,'#1A2332')}
      </div>
    </div>
    ${tplSignHTML(d)}
    ${tplTncHTML(d)}
  </div>
  ${d.popt.footer!==false?`<div style="background:#1A2332;padding:14px 48px;display:flex;justify-content:space-between;align-items:center"><span style="color:rgba(255,255,255,.4);font-size:10px">${(d.showGeneratedBy!==false&&d.generatedBy)?d.generatedBy:"Generated by OPTMS Tech Invoice Manager"}</span><span style="color:rgba(255,255,255,.4);font-size:10px">${sc.phone} · ${sc.email}</span></div>`:''}
  </div>`;
}

// ── Helper: totals rows ──
function totalsRows(d, accentColor, borderColor='#eee', mainColor='#000', mutedColor='#666') {
  // Only show paid/remaining for invoices that are explicitly Partial status
  // Match payments ONLY by numeric invoice_id — never by invoice_number string
  // (number strings are not unique enough and cause false matches)
  const invId = d.invId ? String(d.invId) : '';
  const isPartialStatus = d.status === 'Partial';
  const isPaidStatus = d.status === 'Paid';
  const showInstalmentsSection = isPartialStatus || isPaidStatus;

  let totalPaid = 0, paymentsForInv = [], remaining = 0;
  if (showInstalmentsSection && invId && invId !== '0' && invId !== '') {
    paymentsForInv = STATE.payments.filter(p =>
      p.invoice_id && String(p.invoice_id) === invId
    );
    totalPaid  = paymentsForInv.reduce((s,p) => s + parseFloat(p.amount||0), 0);
    remaining  = Math.max(0, (d.grand||0) - totalPaid);
  }

  const showPaidRow = showInstalmentsSection && totalPaid > 0.01;
  const showRemRow  = isPartialStatus && remaining  > 0.01;

  const discRow = d.disc > 0 || d.discAmt > 0 ? `
    <div style="display:flex;justify-content:space-between;font-size:12px;padding:5px 0;border-bottom:1px solid ${borderColor}">
      <span style="color:${mutedColor}">Discount${d.discType==='fixed'?' (₹)':d.disc>0?' ('+Math.round(d.disc*100)/100+'%)':''}</span>
      <span style="font-family:monospace;font-weight:600;color:#E53935">-${fmt_money(d.discAmt||0,d.sym)}</span>
    </div>` : '';

  const gstRow = d.gstAmt > 0 ? `
    <div style="display:flex;justify-content:space-between;font-size:12px;padding:5px 0;border-bottom:1px solid ${borderColor}">
      <span style="color:${mutedColor}">GST</span>
      <span style="font-family:monospace;font-weight:600;color:#2E7D32">+${fmt_money(d.gstAmt||0,d.sym)}</span>
    </div>` : '';

  // Build individual instalment rows when more than one payment
  const instalmentRows = (showPaidRow && paymentsForInv.length > 0)
    ? paymentsForInv.map((p,i) => {
        const dt  = p.date||p.payment_date||'';
        const dtF = dt ? new Date(dt).toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'}) : '';
        const meth= p.method||'';
        const isSplit = meth.startsWith('Split');
        return `<div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0;border-bottom:1px dashed ${borderColor}">
          <span style="color:#388E3C">
            ${isSplit?'⚡':'✓'} Instalment ${i+1}${dtF?' · '+dtF:''}${meth?' · '+meth.replace('Split: ','').substring(0,30):''}
          </span>
          <span style="font-family:monospace;font-weight:600;color:#388E3C">-${fmt_money(parseFloat(p.amount||0),d.sym)}</span>
        </div>`;
      }).join('')
    : '';
  const paidRow = showPaidRow ? `
    <div style="margin-top:4px">
      <div style="display:flex;justify-content:space-between;font-size:12px;padding:5px 0;${paymentsForInv.length>1?'border-bottom:2px solid #A5D6A7':'border-bottom:1px solid '+borderColor}">
        <span style="color:#388E3C;font-weight:700">${isPaidStatus?'✅':'💚'} ${isPaidStatus?'Paid in Full':'Total Paid'}${paymentsForInv.length>1?' ('+paymentsForInv.length+' instalments)':''}</span>
        <span style="font-family:monospace;font-weight:800;color:#388E3C">-${fmt_money(totalPaid,d.sym)}</span>
      </div>
      ${paymentsForInv.length>1 ? '<div style="background:#F1F8E9;border-radius:6px;padding:4px 8px;margin-top:4px">'+instalmentRows+'</div>' : ''}
    </div>` : '';

  const remainRow = showRemRow ? `
    <div style="display:flex;justify-content:space-between;font-size:14px;font-weight:800;padding:8px 10px;margin-top:6px;
         background:#FFF8E1;border-radius:7px;border:2px solid #FFB300;color:#E65100">
      <span>⚠ Remaining Due</span>
      <span style="font-family:monospace">${fmt_money(remaining,d.sym)}</span>
    </div>` : '';

  return `
    <div style="display:flex;justify-content:space-between;font-size:12px;padding:5px 0;border-bottom:1px solid ${borderColor}">
      <span style="color:${mutedColor}">Subtotal</span>
      <span style="font-family:monospace;font-weight:600;color:${mainColor}">${fmt_money(d.sub,d.sym)}</span>
    </div>
    ${discRow}
    <div style="display:flex;justify-content:space-between;font-size:12px;padding:5px 0;border-bottom:1px solid ${borderColor}">
      <span style="font-weight:700;color:${mainColor}">Amount</span>
      <span style="font-family:monospace;font-weight:700;color:${mainColor}">${fmt_money((d.sub||0)-(d.discAmt||0),d.sym)}</span>
    </div>
    ${gstRow}
    <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:800;padding:8px 0 0;margin-top:4px;color:${accentColor}">
      <span>Grand Total</span><span style="font-family:monospace">${fmt_money(d.grand||0,d.sym)}</span>
    </div>
    ${paidRow}${remainRow}`;
}

function footerBar(d, sc, bg='#1A2332', col='rgba(255,255,255,.4)') {
  if (d.popt && d.popt.footer===false) return '';
  const txt = (window.TPL_CUSTOM&&TPL_CUSTOM.footerText) ? TPL_CUSTOM.footerText
            : (d.showGeneratedBy!==false && d.generatedBy) ? d.generatedBy : 'OPTMS Tech Invoice Manager';
  const bgColor = (window.TPL_CUSTOM&&TPL_CUSTOM.color1) ? TPL_CUSTOM.color1 : bg;
  const font    = (window.TPL_CUSTOM&&TPL_CUSTOM.font)   ? TPL_CUSTOM.font   : 'inherit';
  const phone   = sc.phone||STATE.settings.phone||'';
  const email   = sc.email||STATE.settings.email||'';
  return `<div style="background:${bgColor};padding:12px 40px;display:flex;justify-content:space-between;align-items:center;font-family:${font}">
    <span style="color:${col};font-size:10px">${txt}</span>
    <span style="color:${col};font-size:10px">${phone}${phone&&email?' · ':''}${email}</span>
  </div>`;
}

function statusColor(s) {
  return { Paid:'#388E3C', Pending:'#F57F17', Overdue:'#C62828', Draft:'#757575', Partial:'#E65100', Cancelled:'#B71C1C' }[s] || '#757575';
}

// ── Helper: resolve company settings (merge STATE if sc is sparse) ──
function resolveCompany(sc) {
  const S = STATE.settings;
  return {
    company: sc.company||S.company||'',
    phone:   sc.phone||S.phone||'',
    email:   sc.email||S.email||'',
    website: sc.website||S.website||'',
    gst:     sc.gst||S.gst||'',
    address: sc.address||S.address||'',
    logo:    sc.logo||S.logo||'',
    signature: sc.signature||S.signature||'',
    upi:     sc.upi||S.upi||''
  };
}

// ── TEMPLATE 2: Modern Teal ──
function buildTpl2(d, sc, itemsHTML, gstColHeader) {
  sc = resolveCompany(sc);
  return `<div style="font-family:'Public Sans',sans-serif;background:#fff;width:794px;min-height:1123px;position:relative;overflow:hidden">
  ${tplWatermark(d)}
  <div style="background:linear-gradient(135deg,#00897B,#26A69A);padding:36px 44px 28px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <div>${tplLogoHTML(d,sc)}${tplCompanyInfoHTML(sc)}</div>
      <div style="text-align:right">
        <div style="background:rgba(255,255,255,.15);border-radius:12px;padding:14px 20px;display:inline-block">
          <div style="color:rgba(255,255,255,.8);font-size:10px;text-transform:uppercase;letter-spacing:1px">INVOICE</div>
          <div style="color:#fff;font-size:24px;font-weight:800;font-family:monospace">#${d.num}</div>
          <div style="margin-top:6px;background:${statusColor(d.status)};color:#fff;padding:3px 10px;border-radius:10px;font-size:10px;font-weight:700;display:inline-block">${d.status}</div>
        </div>
      </div>
    </div>
  </div>
  <div style="padding:28px 44px">
    <div style="display:flex;gap:16px;margin-bottom:24px">
      <div style="flex:1;border:2px solid #e0f2f1;border-radius:10px;padding:14px">
        <div style="font-size:10px;font-weight:700;color:#00897B;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">Bill To</div>
        ${tplClientLogoHTML(d)}
        <div style="font-weight:700;font-size:14px">${d.cname}</div>
        ${d.cperson?`<div style="color:#666;font-size:12px;margin-top:2px">${d.cperson}</div>`:''}
        ${d.cemail?`<div style="color:#888;font-size:11px">${d.cemail}</div>`:''}
        ${d.cwa?`<div style="color:#888;font-size:11px">${d.cwa}</div>`:''}
        ${d.caddr?`<div style="color:#888;font-size:11px;margin-top:3px">${d.caddr.replace(/\n/g,'<br>')}</div>`:''}
        ${d.cgst?`<div style="color:#888;font-size:11px">GST: ${d.cgst}</div>`:''}
      </div>
      <div style="flex:0 0 200px;border:2px solid #e0f2f1;border-radius:10px;padding:14px">
        <div style="font-size:10px;font-weight:700;color:#00897B;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">Details</div>
        ${[['Issue Date',d.date],['Due Date',d.due],['Service',d.svc],['GST No.',sc.gst]].map(([k,v])=>v?`<div style="display:flex;justify-content:space-between;font-size:11px;padding:3px 0;border-bottom:1px solid #f0f0f0"><span style="color:#888">${k}</span><span style="font-weight:600">${v}</span></div>`:'').join('')}
        <div style="margin-top:10px;background:#e0f2f1;border-radius:8px;padding:10px;text-align:center">
          <div style="font-size:10px;color:#00897B">Grand Total</div>
          <div style="font-size:20px;font-weight:800;color:#00897B;font-family:monospace">${fmt_money(d.grand,d.sym)}</div>
        </div>
      </div>
    </div>
    <table style="width:100%;border-collapse:collapse;margin-bottom:16px">
      <thead><tr style="background:#00897B"><th style="padding:9px 12px;text-align:left;color:#fff;font-size:11px">Description</th><th style="padding:9px 12px;text-align:center;color:#fff;font-size:11px">Type</th><th style="padding:9px 12px;text-align:center;color:#fff;font-size:11px">Qty</th><th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Rate</th><th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Amount</th>${gstColHeader.replace('style="','style="color:#fff;font-size:11px;')}<th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Total</th></tr></thead>
      <tbody>${itemsHTML}</tbody>
    </table>
    <div style="display:flex;gap:16px;align-items:flex-start">
      <div style="flex:1">${tplBankHTML(d,'#00695C','#e0f2f1')}${tplQrHTML(d)}${tplNotesHTML(d)}</div>
      <div style="width:220px;border:2px solid #e0f2f1;border-radius:10px;padding:14px">${totalsRows(d,'#00897B','#e0f2f1')}</div>
    </div>
    ${tplSignHTML(d)}${tplTncHTML(d)}
  </div>
  ${footerBar(d,sc,'#00897B','rgba(255,255,255,.6)')}
  </div>`;
}

// ── TEMPLATE 3: Bold Dark ──
function buildTpl3(d, sc, itemsHTML, gstColHeader) {
  sc = resolveCompany(sc);
  return `<div style="font-family:'Public Sans',sans-serif;background:#0F172A;color:#fff;width:794px;min-height:1123px;position:relative;overflow:hidden">
  ${tplWatermark(d)}
  <div style="padding:40px 48px 28px;border-bottom:2px solid rgba(56,189,248,.2)">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <div>${tplLogoHTML(d,sc)}<div style="color:rgba(255,255,255,.4);font-size:11px;margin-top:5px">${sc.website} · ${sc.address}</div></div>
      <div style="text-align:right">
        <div style="color:#38BDF8;font-size:10px;text-transform:uppercase;letter-spacing:2px;margin-bottom:4px">Tax Invoice</div>
        <div style="color:#38BDF8;font-size:28px;font-weight:900;font-family:monospace">${d.num}</div>
        <div style="margin-top:6px;background:${statusColor(d.status)};padding:4px 12px;border-radius:20px;font-size:10px;font-weight:700;display:inline-block">${d.status}</div>
      </div>
    </div>
  </div>
  <div style="padding:28px 48px">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
      <div style="background:rgba(255,255,255,.04);border:1px solid rgba(56,189,248,.15);border-radius:10px;padding:14px">
        <div style="font-size:10px;font-weight:700;color:#38BDF8;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">Billed To</div>
        ${tplClientLogoHTML(d)}
        <div style="font-weight:700;font-size:14px;color:#fff">${d.cname}</div>
        ${d.cperson?`<div style="color:rgba(255,255,255,.5);font-size:12px;margin-top:2px">${d.cperson}</div>`:''}
        ${d.cemail?`<div style="color:rgba(255,255,255,.4);font-size:11px">${d.cemail}</div>`:''}
        ${d.cwa?`<div style="color:rgba(255,255,255,.4);font-size:11px">${d.cwa}</div>`:''}
        ${d.caddr?`<div style="color:rgba(255,255,255,.35);font-size:11px;margin-top:3px">${d.caddr.replace(/\n/g,'<br>')}</div>`:''}
        ${d.cgst?`<div style="color:rgba(255,255,255,.35);font-size:11px">GST: ${d.cgst}</div>`:''}
      </div>
      <div style="background:rgba(255,255,255,.04);border:1px solid rgba(56,189,248,.15);border-radius:10px;padding:14px">
        <div style="font-size:10px;font-weight:700;color:#38BDF8;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">Invoice Details</div>
        ${[['Issue Date',d.date],['Due Date',d.due],['Service',d.svc],['GST No.',sc.gst]].map(([k,v])=>v?`<div style="display:flex;justify-content:space-between;font-size:11px;padding:3px 0;border-bottom:1px solid rgba(255,255,255,.07)"><span style="color:rgba(255,255,255,.45)">${k}</span><span style="color:#fff;font-weight:600">${v}</span></div>`:'').join('')}
        <div style="margin-top:12px;text-align:right"><span style="color:rgba(255,255,255,.4);font-size:11px">Grand Total</span><br><span style="font-size:22px;font-weight:800;color:#38BDF8;font-family:monospace">${fmt_money(d.grand,d.sym)}</span></div>
      </div>
    </div>
    <table style="width:100%;border-collapse:collapse;margin-bottom:16px">
      <thead><tr style="background:rgba(56,189,248,.12);border-bottom:1px solid rgba(56,189,248,.3)">
        <th style="padding:10px 12px;text-align:left;color:#38BDF8;font-size:11px">Description</th>
        <th style="padding:10px 12px;text-align:center;color:#38BDF8;font-size:11px">Type</th>
        <th style="padding:10px 12px;text-align:center;color:#38BDF8;font-size:11px">Qty</th>
        <th style="padding:10px 12px;text-align:right;color:#38BDF8;font-size:11px">Rate</th>
        <th style="padding:10px 12px;text-align:right;color:#38BDF8;font-size:11px">Amount</th>
        ${gstColHeader.replace('style="','style="color:#38BDF8;font-size:11px;')}
        <th style="padding:10px 12px;text-align:right;color:#38BDF8;font-size:11px">Total</th>
      </tr></thead>
      <tbody>${itemsHTML.replace(/border-bottom:1px solid #eee/g,'border-bottom:1px solid rgba(255,255,255,.07)').replace(/color:#[0-9a-fA-F]{3,6}/g,'color:rgba(255,255,255,.8)')}</tbody>
    </table>
    <div style="display:flex;gap:16px;align-items:flex-start">
      <div style="flex:1">${tplBankHTML(d,'#38BDF8','rgba(56,189,248,.1)','border:1px solid rgba(56,189,248,.2)')}${tplQrHTML(d)}${tplNotesHTML(d,'#94A3B8','rgba(255,255,255,.04)')}</div>
      <div style="width:220px;background:rgba(56,189,248,.06);border:1px solid rgba(56,189,248,.2);border-radius:10px;padding:14px">${totalsRows(d,'#38BDF8','rgba(255,255,255,.1)','#fff','rgba(255,255,255,.45)')}</div>
    </div>
    ${tplSignHTML(d)}${tplTncHTML(d,'rgba(255,255,255,.35)')}
  </div>
  ${footerBar(d,sc,'rgba(56,189,248,.1)','rgba(255,255,255,.3)')}
  </div>`;
}

// ── TEMPLATE 4: Minimal Clean ──
function buildTpl4(d, sc, itemsHTML, gstColHeader) {
  sc = resolveCompany(sc);
  return `<div style="font-family:'Public Sans',sans-serif;background:#fff;width:794px;min-height:1123px;position:relative;overflow:hidden">
  ${tplWatermark(d)}
  <div style="padding:0">
    <!-- Header: dark band for B&W print visibility -->
    <div style="background:#1A2332;padding:36px 52px 28px;display:flex;justify-content:space-between;align-items:flex-start">
      <div>
        ${tplLogoHTML(d,sc)}
        <div style="color:rgba(255,255,255,.85);font-size:12px;font-weight:600;margin-top:6px">${sc.company||''}</div>
        ${(sc.phone)?`<div style="color:rgba(255,255,255,.65);font-size:11px;margin-top:2px">📞 ${sc.phone}</div>`:''}
        ${(sc.email)?`<div style="color:rgba(255,255,255,.65);font-size:11px;margin-top:2px">✉ ${sc.email}</div>`:''}
        ${(sc.website)?`<div style="color:rgba(255,255,255,.5);font-size:10px;margin-top:2px">${sc.website}</div>`:''}
        ${(sc.gst)?`<div style="color:rgba(255,255,255,.5);font-size:10px;margin-top:2px">GST: ${sc.gst}</div>`:''}
        ${(sc.address)?`<div style="color:rgba(255,255,255,.45);font-size:10px;margin-top:3px;max-width:220px;line-height:1.5">${sc.address.replace(/\n/g,'<br>')}</div>`:''}
      </div>
      <div style="text-align:right">
        <div style="font-size:9px;letter-spacing:3px;color:rgba(255,255,255,.5);text-transform:uppercase;margin-bottom:6px">Tax Invoice</div>
        <div style="font-size:30px;font-weight:900;color:#fff;letter-spacing:-1px;font-family:monospace">#${d.num}</div>
        <div style="margin-top:8px;background:${statusColor(d.status)};color:#fff;padding:4px 14px;border-radius:20px;font-size:10px;font-weight:700;display:inline-block">${d.status}</div>
      </div>
    </div>
    <!-- Sub-bar: issue/due/service details -->
    <div style="background:#f0f2f5;padding:10px 52px;display:flex;gap:28px;border-bottom:1px solid #ddd">
      ${[['Issue Date',d.date],['Due Date',d.due],['Service',d.svc]].map(([k,v])=>v?`<div><span style="font-size:9px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.8px">${k}</span><br><span style="font-size:12px;font-weight:700;color:#1A2332">${v}</span></div>`:'').join('')}
    </div>
  </div>
  <div style="padding:28px 52px">
    <div style="display:flex;gap:20px;margin-bottom:24px">
      <div style="flex:1;border:1.5px solid #e0e0e0;border-radius:10px;padding:14px">
        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#aaa;margin-bottom:8px">Billed To</div>
        ${tplClientLogoHTML(d)}
        <div style="font-weight:700;font-size:14px;color:#111">${d.cname}</div>
        ${d.cperson?`<div style="color:#555;font-size:12px;margin-top:2px">${d.cperson}</div>`:''}
        ${d.cemail?`<div style="color:#888;font-size:11px">${d.cemail}</div>`:''}
        ${d.cwa?`<div style="color:#888;font-size:11px">${d.cwa}</div>`:''}
        ${d.caddr?`<div style="color:#888;font-size:11px;margin-top:3px">${d.caddr.replace(/\n/g,'<br>')}</div>`:''}
        ${d.cgst?`<div style="color:#888;font-size:11px">GST: ${d.cgst}</div>`:''}
      </div>
      <div style="flex:0 0 190px;text-align:right;border-left:2px solid #1A2332;padding-left:20px">
        <div style="font-size:9px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:6px">Amount Due</div>
        <div style="font-size:26px;font-weight:900;color:#1A2332;font-family:monospace">${fmt_money(d.grand,d.sym)}</div>
      </div>
    </div>
    <table style="width:100%;border-collapse:collapse;margin-bottom:16px">
      <thead><tr style="background:#1A2332">
        <th style="padding:9px 12px;text-align:left;font-size:10px;font-weight:700;color:rgba(255,255,255,.8);text-transform:uppercase">Description</th>
        <th style="padding:9px 12px;text-align:center;font-size:10px;font-weight:700;color:rgba(255,255,255,.8);text-transform:uppercase">Type</th>
        <th style="padding:9px 12px;text-align:center;font-size:10px;font-weight:700;color:rgba(255,255,255,.8);text-transform:uppercase">Qty</th>
        <th style="padding:9px 12px;text-align:right;font-size:10px;font-weight:700;color:rgba(255,255,255,.8);text-transform:uppercase">Rate</th>
        <th style="padding:9px 12px;text-align:right;font-size:10px;font-weight:700;color:rgba(255,255,255,.8);text-transform:uppercase">Amount</th>
        ${gstColHeader.replace('style="','style="font-size:10px;font-weight:700;color:rgba(255,255,255,.8);text-transform:uppercase;')}
        <th style="padding:9px 12px;text-align:right;font-size:10px;font-weight:700;color:rgba(255,255,255,.8);text-transform:uppercase">Total</th>
      </tr></thead>
      <tbody>${itemsHTML}</tbody>
    </table>
    <div style="display:flex;gap:16px;align-items:flex-start">
      <div style="flex:1">${tplBankHTML(d,'#1A2332','#f0f2f5')}${tplQrHTML(d)}${tplNotesHTML(d,'#444','#f8f8f8')}</div>
      <div style="width:210px;border-top:2px solid #1A2332;padding-top:12px">${totalsRows(d,'#1A2332','#e0e0e0')}</div>
    </div>
    ${tplSignHTML(d)}${tplTncHTML(d)}
    <div style="height:24px"></div>
  </div>
  ${footerBar(d,sc,'#1A2332','rgba(255,255,255,.5)')}
  </div>`;
}

// ── TEMPLATE 5: Corporate Blue ──
function buildTpl5(d, sc, itemsHTML, gstColHeader) {
  sc = resolveCompany(sc);
  return `<div style="font-family:'Public Sans',sans-serif;background:#fff;width:794px;min-height:1123px;position:relative;overflow:hidden">
  ${tplWatermark(d)}
  <div style="background:linear-gradient(135deg,#1565C0,#1976D2);padding:36px 44px 28px;display:flex;justify-content:space-between;align-items:flex-start">
    <div>${tplLogoHTML(d,sc)}${tplCompanyInfoHTML(sc)}</div>
    <div style="text-align:right">
      <div style="color:rgba(255,255,255,.7);font-size:10px;text-transform:uppercase;letter-spacing:1.5px">Tax Invoice</div>
      <div style="color:#fff;font-size:26px;font-weight:800;font-family:monospace;margin-top:2px">#${d.num}</div>
      <div style="margin-top:6px;background:${statusColor(d.status)};padding:4px 12px;border-radius:20px;font-size:10px;font-weight:700;color:#fff;display:inline-block">${d.status}</div>
    </div>
  </div>
  <div style="background:#E3F2FD;padding:14px 44px;display:flex;gap:24px">
    ${[['Issue Date',d.date],['Due Date',d.due],['Service',d.svc],['Phone',sc.phone]].map(([k,v])=>v?`<div><span style="font-size:9px;color:#1565C0;font-weight:700;text-transform:uppercase">${k}</span><br><span style="font-size:12px;font-weight:700;color:#1A2332">${v}</span></div>`:'').join('')}
  </div>
  <div style="padding:24px 44px">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px">
      <div style="border:1px solid #E3F2FD;border-radius:10px;padding:14px">
        <div style="font-size:10px;font-weight:700;color:#1565C0;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Billed To</div>
        ${tplClientLogoHTML(d)}
        <div style="font-weight:700;font-size:13px">${d.cname}</div>
        ${d.cperson?`<div style="color:#555;font-size:11px">${d.cperson}</div>`:''}
        ${d.cemail?`<div style="color:#888;font-size:11px">${d.cemail}</div>`:''}
        ${d.cwa?`<div style="color:#888;font-size:11px">${d.cwa}</div>`:''}
        ${d.caddr?`<div style="color:#888;font-size:11px">${d.caddr.replace(/\n/g,'<br>')}</div>`:''}
        ${d.cgst?`<div style="color:#888;font-size:11px">GST: ${d.cgst}</div>`:''}
      </div>
      <div style="background:#E3F2FD;border-radius:10px;padding:14px;text-align:center;display:flex;flex-direction:column;justify-content:center">
        <div style="font-size:10px;font-weight:700;color:#1565C0;text-transform:uppercase;letter-spacing:1px">Total Amount</div>
        <div style="font-size:28px;font-weight:900;color:#1565C0;font-family:monospace;margin:6px 0">${fmt_money(d.grand,d.sym)}</div>
        <div style="font-size:10px;color:#1976D2">Including GST · ${d.sym}</div>
      </div>
    </div>
    <table style="width:100%;border-collapse:collapse;margin-bottom:16px">
      <thead><tr style="background:#1565C0"><th style="padding:9px 12px;text-align:left;color:#fff;font-size:11px">Description</th><th style="padding:9px 12px;text-align:center;color:#fff;font-size:11px">Type</th><th style="padding:9px 12px;text-align:center;color:#fff;font-size:11px">Qty</th><th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Rate</th><th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Amount</th>${gstColHeader.replace('style="','style="color:#fff;font-size:11px;')}<th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Total</th></tr></thead>
      <tbody>${itemsHTML}</tbody>
    </table>
    <div style="display:flex;gap:16px;align-items:flex-start">
      <div style="flex:1">${tplBankHTML(d,'#1565C0','#E3F2FD')}${tplQrHTML(d)}${tplNotesHTML(d,'#1565C0','#E3F2FD')}</div>
      <div style="width:220px;background:#E3F2FD;border-radius:10px;padding:14px">${totalsRows(d,'#1565C0','rgba(21,101,192,.15)')}</div>
    </div>
    ${tplSignHTML(d)}${tplTncHTML(d)}
  </div>
  ${footerBar(d,sc,'#1565C0','rgba(255,255,255,.6)')}
  </div>`;
}

// ── TEMPLATE 6: Warm Orange ──
function buildTpl6(d, sc, itemsHTML, gstColHeader) {
  sc = resolveCompany(sc);
  return `<div style="font-family:'Public Sans',sans-serif;background:#fff;width:794px;min-height:1123px;position:relative;overflow:hidden">
  ${tplWatermark(d)}
  <div style="background:linear-gradient(135deg,#BF360C,#E64A19);padding:36px 44px 28px;display:flex;justify-content:space-between;align-items:flex-start">
    <div>${tplLogoHTML(d,sc)}${tplCompanyInfoHTML(sc)}</div>
    <div style="text-align:right">
      <div style="color:rgba(255,255,255,.7);font-size:10px;text-transform:uppercase;letter-spacing:1.5px">Tax Invoice</div>
      <div style="color:#FFD54F;font-size:26px;font-weight:800;font-family:monospace;margin-top:2px">#${d.num}</div>
      <div style="margin-top:6px;background:${statusColor(d.status)};padding:4px 12px;border-radius:20px;font-size:10px;font-weight:700;color:#fff;display:inline-block">${d.status}</div>
    </div>
  </div>
  <div style="padding:24px 44px">
    <div style="display:flex;gap:14px;margin-bottom:20px">
      <div style="flex:1;border-left:4px solid #E64A19;padding:12px 16px;background:#FBE9E7;border-radius:0 8px 8px 0">
        <div style="font-size:10px;font-weight:700;color:#BF360C;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Bill To</div>
        ${tplClientLogoHTML(d)}
        <div style="font-weight:700;font-size:13px">${d.cname}</div>
        ${d.cperson?`<div style="color:#555;font-size:11px">${d.cperson}</div>`:''}
        ${d.cemail?`<div style="color:#888;font-size:11px">${d.cemail}</div>`:''}
        ${d.cwa?`<div style="color:#888;font-size:11px">${d.cwa}</div>`:''}
        ${d.caddr?`<div style="color:#888;font-size:11px">${d.caddr.replace(/\n/g,'<br>')}</div>`:''}
        ${d.cgst?`<div style="color:#888;font-size:11px">GST: ${d.cgst}</div>`:''}
      </div>
      <div style="flex:0 0 200px">
        ${[['Issue Date',d.date],['Due Date',d.due],['Service',d.svc],['GST No.',sc.gst]].map(([k,v])=>v?`<div style="display:flex;justify-content:space-between;font-size:11px;padding:5px 8px;border-bottom:1px solid #FBE9E7"><span style="color:#888">${k}</span><span style="font-weight:600">${v}</span></div>`:'').join('')}
        <div style="margin-top:10px;background:#E64A19;color:#fff;border-radius:8px;padding:10px;text-align:center">
          <div style="font-size:9px;text-transform:uppercase;opacity:.8">Total</div>
          <div style="font-size:20px;font-weight:800;font-family:monospace">${fmt_money(d.grand,d.sym)}</div>
        </div>
      </div>
    </div>
    <table style="width:100%;border-collapse:collapse;margin-bottom:16px">
      <thead><tr style="background:#E64A19"><th style="padding:9px 12px;text-align:left;color:#fff;font-size:11px">Description</th><th style="padding:9px 12px;text-align:center;color:#fff;font-size:11px">Type</th><th style="padding:9px 12px;text-align:center;color:#fff;font-size:11px">Qty</th><th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Rate</th><th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Amount</th>${gstColHeader.replace('style="','style="color:#fff;font-size:11px;')}<th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Total</th></tr></thead>
      <tbody>${itemsHTML}</tbody>
    </table>
    <div style="display:flex;gap:16px;align-items:flex-start">
      <div style="flex:1">${tplBankHTML(d,'#BF360C','#FBE9E7')}${tplQrHTML(d)}${tplNotesHTML(d,'#BF360C','#FBE9E7')}</div>
      <div style="width:210px;border:2px solid #E64A19;border-radius:10px;padding:14px">${totalsRows(d,'#E64A19','#FBE9E7')}</div>
    </div>
    ${tplSignHTML(d)}${tplTncHTML(d)}
  </div>
  ${footerBar(d,sc,'#E64A19','rgba(255,255,255,.6)')}
  </div>`;
}

// ── TEMPLATE 7: Purple Gradient ──
function buildTpl7(d, sc, itemsHTML, gstColHeader) {
  sc = resolveCompany(sc);
  return `<div style="font-family:'Public Sans',sans-serif;background:#fff;width:794px;min-height:1123px;position:relative;overflow:hidden">
  ${tplWatermark(d)}
  <div style="background:linear-gradient(135deg,#4A148C,#7B1FA2,#AB47BC);padding:36px 44px 28px;display:flex;justify-content:space-between;align-items:flex-start">
    <div>${tplLogoHTML(d,sc)}${tplCompanyInfoHTML(sc)}</div>
    <div style="text-align:right">
      <div style="color:rgba(255,255,255,.7);font-size:10px;text-transform:uppercase;letter-spacing:1.5px">Tax Invoice</div>
      <div style="color:#E1BEE7;font-size:26px;font-weight:800;font-family:monospace;margin-top:2px">#${d.num}</div>
      <div style="margin-top:6px;background:${statusColor(d.status)};padding:4px 12px;border-radius:20px;font-size:10px;font-weight:700;color:#fff;display:inline-block">${d.status}</div>
    </div>
  </div>
  <div style="padding:24px 44px">
    <div style="display:flex;gap:14px;margin-bottom:20px">
      <div style="flex:1;border:2px solid #E1BEE7;border-radius:10px;padding:14px">
        <div style="font-size:10px;font-weight:700;color:#7B1FA2;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Bill To</div>
        ${tplClientLogoHTML(d)}
        <div style="font-weight:700;font-size:13px">${d.cname}</div>
        ${d.cperson?`<div style="color:#555;font-size:11px">${d.cperson}</div>`:''}
        ${d.cemail?`<div style="color:#888;font-size:11px">${d.cemail}</div>`:''}
        ${d.cwa?`<div style="color:#888;font-size:11px">${d.cwa}</div>`:''}
        ${d.caddr?`<div style="color:#888;font-size:11px">${d.caddr.replace(/\n/g,'<br>')}</div>`:''}
        ${d.cgst?`<div style="color:#888;font-size:11px">GST: ${d.cgst}</div>`:''}
      </div>
      <div style="flex:0 0 200px">
        ${[['Issue Date',d.date],['Due Date',d.due],['Service',d.svc],['GST No.',sc.gst]].map(([k,v])=>v?`<div style="display:flex;justify-content:space-between;font-size:11px;padding:5px 0;border-bottom:1px solid #F3E5F5"><span style="color:#888">${k}</span><span style="font-weight:600">${v}</span></div>`:'').join('')}
        <div style="margin-top:10px;background:linear-gradient(135deg,#7B1FA2,#AB47BC);color:#fff;border-radius:8px;padding:10px;text-align:center">
          <div style="font-size:9px;text-transform:uppercase;opacity:.8">Total</div>
          <div style="font-size:20px;font-weight:800;font-family:monospace">${fmt_money(d.grand,d.sym)}</div>
        </div>
      </div>
    </div>
    <table style="width:100%;border-collapse:collapse;margin-bottom:16px">
      <thead><tr style="background:linear-gradient(135deg,#7B1FA2,#AB47BC)"><th style="padding:9px 12px;text-align:left;color:#fff;font-size:11px">Description</th><th style="padding:9px 12px;text-align:center;color:#fff;font-size:11px">Type</th><th style="padding:9px 12px;text-align:center;color:#fff;font-size:11px">Qty</th><th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Rate</th><th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Amount</th>${gstColHeader.replace('style="','style="color:#fff;font-size:11px;')}<th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Total</th></tr></thead>
      <tbody>${itemsHTML}</tbody>
    </table>
    <div style="display:flex;gap:16px;align-items:flex-start">
      <div style="flex:1">${tplBankHTML(d,'#4A148C','#F3E5F5')}${tplQrHTML(d)}${tplNotesHTML(d,'#4A148C','#F3E5F5')}</div>
      <div style="width:210px;background:#F3E5F5;border-radius:10px;padding:14px">${totalsRows(d,'#7B1FA2','#E1BEE7')}</div>
    </div>
    ${tplSignHTML(d)}${tplTncHTML(d)}
  </div>
  ${footerBar(d,sc,'#4A148C','rgba(255,255,255,.5)')}
  </div>`;
}

// ── TEMPLATE 8: Green Leaf ──
function buildTpl8(d, sc, itemsHTML, gstColHeader) {
  sc = resolveCompany(sc);
  return `<div style="font-family:'Public Sans',sans-serif;background:#fff;width:794px;min-height:1123px;position:relative;overflow:hidden">
  ${tplWatermark(d)}
  <div style="background:linear-gradient(135deg,#1B5E20,#2E7D32,#388E3C);padding:36px 44px 28px;display:flex;justify-content:space-between;align-items:flex-start">
    <div>${tplLogoHTML(d,sc)}${tplCompanyInfoHTML(sc)}</div>
    <div style="text-align:right">
      <div style="color:rgba(255,255,255,.7);font-size:10px;text-transform:uppercase;letter-spacing:1.5px">Tax Invoice</div>
      <div style="color:#A5D6A7;font-size:26px;font-weight:800;font-family:monospace;margin-top:2px">#${d.num}</div>
      <div style="margin-top:6px;background:${statusColor(d.status)};padding:4px 12px;border-radius:20px;font-size:10px;font-weight:700;color:#fff;display:inline-block">${d.status}</div>
    </div>
  </div>
  <div style="padding:24px 44px">
    <div style="display:flex;gap:14px;margin-bottom:20px">
      <div style="flex:1;border-left:4px solid #388E3C;padding:12px 16px;background:#E8F5E9;border-radius:0 8px 8px 0">
        <div style="font-size:10px;font-weight:700;color:#1B5E20;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Billed To</div>
        ${tplClientLogoHTML(d)}
        <div style="font-weight:700;font-size:13px">${d.cname}</div>
        ${d.cperson?`<div style="color:#555;font-size:11px">${d.cperson}</div>`:''}
        ${d.cemail?`<div style="color:#888;font-size:11px">${d.cemail}</div>`:''}
        ${d.cwa?`<div style="color:#888;font-size:11px">${d.cwa}</div>`:''}
        ${d.caddr?`<div style="color:#888;font-size:11px">${d.caddr.replace(/\n/g,'<br>')}</div>`:''}
        ${d.cgst?`<div style="color:#888;font-size:11px">GST: ${d.cgst}</div>`:''}
      </div>
      <div style="flex:0 0 200px">
        ${[['Issue Date',d.date],['Due Date',d.due],['Service',d.svc],['GST No.',sc.gst]].map(([k,v])=>v?`<div style="display:flex;justify-content:space-between;font-size:11px;padding:5px 0;border-bottom:1px solid #E8F5E9"><span style="color:#888">${k}</span><span style="font-weight:600">${v}</span></div>`:'').join('')}
        <div style="margin-top:10px;background:#388E3C;color:#fff;border-radius:8px;padding:10px;text-align:center">
          <div style="font-size:9px;text-transform:uppercase;opacity:.8">Total</div>
          <div style="font-size:20px;font-weight:800;font-family:monospace">${fmt_money(d.grand,d.sym)}</div>
        </div>
      </div>
    </div>
    <table style="width:100%;border-collapse:collapse;margin-bottom:16px">
      <thead><tr style="background:#388E3C"><th style="padding:9px 12px;text-align:left;color:#fff;font-size:11px">Description</th><th style="padding:9px 12px;text-align:center;color:#fff;font-size:11px">Type</th><th style="padding:9px 12px;text-align:center;color:#fff;font-size:11px">Qty</th><th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Rate</th><th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Amount</th>${gstColHeader.replace('style="','style="color:#fff;font-size:11px;')}<th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Total</th></tr></thead>
      <tbody>${itemsHTML}</tbody>
    </table>
    <div style="display:flex;gap:16px;align-items:flex-start">
      <div style="flex:1">${tplBankHTML(d,'#1B5E20','#E8F5E9')}${tplQrHTML(d)}${tplNotesHTML(d,'#1B5E20','#E8F5E9')}</div>
      <div style="width:210px;background:#E8F5E9;border-radius:10px;padding:14px">${totalsRows(d,'#388E3C','#C8E6C9')}</div>
    </div>
    ${tplSignHTML(d)}${tplTncHTML(d)}
  </div>
  ${footerBar(d,sc,'#1B5E20','rgba(255,255,255,.5)')}
  </div>`;
}

// ── TEMPLATE 9: Red Executive ──
function buildTpl9(d, sc, itemsHTML, gstColHeader) {
  sc = resolveCompany(sc);
  return `<div style="font-family:'Public Sans',sans-serif;background:#fff;width:794px;min-height:1123px;position:relative;overflow:hidden">
  ${tplWatermark(d)}
  <div style="background:linear-gradient(135deg,#7F0000,#B71C1C,#C62828);padding:36px 44px 28px;display:flex;justify-content:space-between;align-items:flex-start">
    <div>${tplLogoHTML(d,sc)}${tplCompanyInfoHTML(sc)}</div>
    <div style="text-align:right">
      <div style="color:rgba(255,255,255,.7);font-size:10px;text-transform:uppercase;letter-spacing:1.5px">Tax Invoice</div>
      <div style="color:#EF9A9A;font-size:26px;font-weight:800;font-family:monospace;margin-top:2px">#${d.num}</div>
      <div style="margin-top:6px;background:${statusColor(d.status)};padding:4px 12px;border-radius:20px;font-size:10px;font-weight:700;color:#fff;display:inline-block;border:1px solid rgba(255,255,255,.3)">${d.status}</div>
    </div>
  </div>
  <div style="background:#FFEBEE;padding:12px 44px;display:flex;gap:24px">
    ${[['Issue Date',d.date],['Due Date',d.due],['Service',d.svc],['Phone',sc.phone]].map(([k,v])=>v?`<div><span style="font-size:9px;color:#B71C1C;font-weight:700;text-transform:uppercase">${k}</span><br><span style="font-size:12px;font-weight:700;color:#1A2332">${v}</span></div>`:'').join('')}
  </div>
  <div style="padding:20px 44px">
    <div style="display:flex;gap:14px;margin-bottom:20px">
      <div style="flex:1;border:1.5px solid #FFCDD2;border-radius:10px;padding:14px">
        <div style="font-size:10px;font-weight:700;color:#B71C1C;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Billed To</div>
        ${tplClientLogoHTML(d)}
        <div style="font-weight:700;font-size:13px">${d.cname}</div>
        ${d.cperson?`<div style="color:#555;font-size:11px">${d.cperson}</div>`:''}
        ${d.cemail?`<div style="color:#888;font-size:11px">${d.cemail}</div>`:''}
        ${d.cwa?`<div style="color:#888;font-size:11px">${d.cwa}</div>`:''}
        ${d.caddr?`<div style="color:#888;font-size:11px">${d.caddr.replace(/\n/g,'<br>')}</div>`:''}
        ${d.cgst?`<div style="color:#888;font-size:11px">GST: ${d.cgst}</div>`:''}
      </div>
      <div style="flex:0 0 200px;background:#FFEBEE;border-radius:10px;padding:14px">
        <div style="font-size:10px;font-weight:700;color:#B71C1C;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;text-align:center">Amount Due</div>
        <div style="font-size:26px;font-weight:900;color:#B71C1C;font-family:monospace;text-align:center">${fmt_money(d.grand,d.sym)}</div>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid #FFCDD2">
          ${[['GST No.',sc.gst]].map(([k,v])=>v?`<div style="display:flex;justify-content:space-between;font-size:10px"><span style="color:#888">${k}</span><span style="font-weight:600">${v}</span></div>`:'').join('')}
        </div>
      </div>
    </div>
    <table style="width:100%;border-collapse:collapse;margin-bottom:16px">
      <thead><tr style="background:#B71C1C"><th style="padding:9px 12px;text-align:left;color:#fff;font-size:11px">Description</th><th style="padding:9px 12px;text-align:center;color:#fff;font-size:11px">Type</th><th style="padding:9px 12px;text-align:center;color:#fff;font-size:11px">Qty</th><th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Rate</th><th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Amount</th>${gstColHeader.replace('style="','style="color:#fff;font-size:11px;')}<th style="padding:9px 12px;text-align:right;color:#fff;font-size:11px">Total</th></tr></thead>
      <tbody>${itemsHTML}</tbody>
    </table>
    <div style="display:flex;gap:16px;align-items:flex-start">
      <div style="flex:1">${tplBankHTML(d,'#7F0000','#FFEBEE')}${tplQrHTML(d)}${tplNotesHTML(d,'#7F0000','#FFEBEE')}</div>
      <div style="width:210px;background:#FFEBEE;border-radius:10px;padding:14px">${totalsRows(d,'#B71C1C','#FFCDD2')}</div>
    </div>
    ${tplSignHTML(d)}${tplTncHTML(d)}
  </div>
  ${footerBar(d,sc,'#7F0000','rgba(255,255,255,.5)')}
  </div>`;
}

// ══════════════════════════════════════════
// PRINT INVOICE (proper print layout)
// ══════════════════════════════════════════
function printCurrentInvoice() {
  const d = getFormData();
  openPrintWindow(d, formItems);
}

function printInvoiceData(inv) {
  // Restore formItems from invoice data temporarily
  const savedItems = [...formItems];
  formItems = inv.items.map(i => ({ id: Date.now() + Math.random(), desc: i.desc||i.description||'', itemType: i.itemType||i.item_type||'Service', qty: parseFloat(i.qty||i.quantity)||1, gst: (i.gst!==undefined&&i.gst!==null&&i.gst!==''?parseFloat(i.gst):i.gstRate!==undefined&&i.gstRate!==null&&i.gstRate!==''?parseFloat(i.gstRate):i.gst_rate!==undefined&&i.gst_rate!==''?parseFloat(i.gst_rate):18), rate: parseFloat(i.rate)||0 }));
  const d = getFormData();
  openPrintWindow(d, formItems);
  formItems = savedItems;
}

function openPrintWindow(d, items) {
  const showGst = d.popt ? d.popt.gstCol : true;
  const itemsHTML = items.length
    ? items.map(i => {
        const line = (i.qty||1)*(i.rate||0);
        const gstR = parseFloat(i.gst)||0;
        const gstAmt = line * gstR / 100;
        const lineInclGst = line + gstAmt;
        const itype = i.itemType||'Service';
        return `<tr>
          <td style="padding:10px 12px;border-bottom:1px solid #eee">${i.desc||'—'}</td>
          <td style="padding:10px 12px;text-align:center;border-bottom:1px solid #eee;font-size:11px;color:#888">${itype}</td>
          <td style="padding:10px 12px;text-align:center;border-bottom:1px solid #eee">${i.qty}</td>
          <td style="padding:10px 12px;text-align:right;border-bottom:1px solid #eee">${fmt_money(i.rate,d.sym)}</td>
          <td style="padding:10px 12px;text-align:right;border-bottom:1px solid #eee">${fmt_money(line,d.sym)}</td>
          ${showGst ? `<td style="padding:10px 12px;text-align:center;border-bottom:1px solid #eee">${gstR}%</td>` : ''}
          <td style="padding:10px 12px;text-align:right;font-weight:700;border-bottom:1px solid #eee">${fmt_money(lineInclGst,d.sym)}</td>
        </tr>`;
      }).join('')
    : `<tr><td colspan="${showGst?7:6}" style="padding:20px;text-align:center;color:#aaa">No items</td></tr>`;
  const gstColHeader = showGst ? `<th style="padding:10px 12px;text-align:center">GST%</th>` : '';
  const templates = { 1:buildTpl1,2:buildTpl2,3:buildTpl3,4:buildTpl4,5:buildTpl5,6:buildTpl6,7:buildTpl7,8:buildTpl8,9:buildTpl9 };
  const fn = templates[d.tpl]||buildTpl1;
  const html = fn(d, STATE.settings, itemsHTML, gstColHeader);
  const w = window.open('','_blank','width=900,height=700');
  w.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8">
    <title>Invoice ${d.num} – OPTMS Tech</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>*{box-sizing:border-box;margin:0;padding:0}body{background:#fff;font-family:'Public Sans',sans-serif}
    .no-print{background:#f5f5f5;padding:10px 20px;display:flex;gap:12px;align-items:center;font-family:'Public Sans',sans-serif;font-size:13px;border-bottom:1px solid #ddd;position:sticky;top:0;z-index:99}
    @page{margin:0;size:A4}@media print{.no-print{display:none!important}body{margin:0;padding:0}}</style>
  </head><body>
  <div class="no-print">
    <button onclick="window.print()" style="padding:8px 20px;background:#00897B;color:#fff;border:none;border-radius:7px;cursor:pointer;font-weight:700;font-family:inherit">🖨️ Print / Save PDF</button>
    <button onclick="window.close()" style="padding:8px 16px;background:#fff;border:1.5px solid #ddd;border-radius:7px;cursor:pointer;font-family:inherit">Close</button>
    <span style="color:#888;font-size:12px">Tip: Set margins to "None" in print dialog for best result</span>
  </div>
  ${html}
  </body></html>`);
  w.document.close();
}

function printFromModal() {
  const titleEl = document.getElementById('mp-title');
  const id = titleEl?.dataset?.invId;
  if (id) {
    const inv = STATE.invoices.find(i=>String(i.id)===String(id));
    if (inv) { printInvoiceById(inv); return; }
  }
  printCurrentInvoice();
}

function printInvoiceById(inv) {
  const c   = STATE.clients.find(x=>String(x.id)===String(inv.client)) || {};
  const sc  = STATE.settings;
  const items = (inv.items||[]);
  const showGst = true;
  const sym = inv.currency || '₹';
  const itemsHTML = items.length
    ? items.map(i=>{
        const qty  = parseFloat(i.qty||i.quantity||1);
        const rate = parseFloat(i.rate||0);
        const gst         = (i.gst!==undefined&&i.gst!==null&&i.gst!==''?parseFloat(i.gst):i.gst_rate!==undefined&&i.gst_rate!==''?parseFloat(i.gst_rate):18);
        const line        = qty*rate;
        const gstAmt      = line * gst / 100;
        const lineInclGst = line + gstAmt;
        const itype       = i.itemType||i.item_type||'Service';
        return `<tr>
          <td style="padding:10px 12px;border-bottom:1px solid #eee">${i.desc||i.description||'—'}</td>
          <td style="padding:10px 12px;text-align:center;border-bottom:1px solid #eee;font-size:11px;color:#888">${itype}</td>
          <td style="padding:10px 12px;text-align:center;border-bottom:1px solid #eee">${qty}</td>
          <td style="padding:10px 12px;text-align:right;border-bottom:1px solid #eee">${fmt_money(rate,sym)}</td>
          <td style="padding:10px 12px;text-align:right;border-bottom:1px solid #eee">${fmt_money(line,sym)}</td>
          <td style="padding:10px 12px;text-align:center;border-bottom:1px solid #eee">${gst}%</td>
          <td style="padding:10px 12px;text-align:right;font-weight:700;border-bottom:1px solid #eee">${fmt_money(lineInclGst,sym)}</td>
        </tr>`;
      }).join('')
    : `<tr><td colspan="7" style="padding:20px;text-align:center;color:#aaa">No items</td></tr>`;
  const gstHdr = `<th style="padding:10px 12px;text-align:center">GST%</th>`;
  const d = {
    tpl: parseInt(inv.template)||STATE.settings.activeTemplate||1,
    num: inv.num||inv.invoice_number, date: inv.issued||inv.issued_date,
    due: inv.due||inv.due_date, svc: inv.service||inv.service_type,
    cname: c.name||inv.clientName||inv.client_name||'',
    cperson:c.person||'', cemail:c.email||'', cwa:c.wa||c.whatsapp||'',
    cgst:c.gst||c.gst_number||'', caddr:c.addr||c.address||'',
    disc:parseFloat(inv.disc||inv.discount_pct)||0, discAmt:parseFloat(inv.discount_amt)||0,
    notes:inv.notes||'', bank:inv.bank||inv.bank_details||'',
    tnc:inv.tnc||inv.terms||'', status:inv.status, sym,
    sub:parseFloat(inv.subtotal)||0, gstAmt:parseFloat(inv.gst_amount)||0,
    grand:parseFloat(inv.amount||inv.grand_total)||0,
    invId: String(inv.id||''),
    companyLogo:inv.company_logo||sc.logo||'',
    clientLogo:inv.client_logo||'', signature:inv.signature||sc.signature||'',
    qrUrl:inv.qr_code||'', generatedBy:inv.generated_by||'OPTMS Tech Invoice Manager',
    showGeneratedBy:true,
    popt:{bank:true,qr:!!(inv.qr_code),sign:!!(inv.signature||sc.signature),
          logo:true,clientLogo:false,notes:true,tnc:true,gstCol:true,footer:true,
          watermark:inv.status==='Paid'}
  };
  const tpls={1:buildTpl1,2:buildTpl2,3:buildTpl3,4:buildTpl4,5:buildTpl5,
              6:buildTpl6,7:buildTpl7,8:buildTpl8,9:buildTpl9};
  const fn = tpls[d.tpl]||buildTpl1;
  const html = fn(d, sc, itemsHTML, gstHdr);
  const w = window.open('','_blank','width=900,height=700');
  w.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8">
    <title>Invoice ${d.num}</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>*{box-sizing:border-box;margin:0;padding:0}body{background:#fff;font-family:'Public Sans',sans-serif}
    .np{background:#f5f5f5;padding:10px 20px;display:flex;gap:12px;align-items:center;font-family:'Public Sans',sans-serif;font-size:13px;border-bottom:1px solid #ddd;position:sticky;top:0;z-index:99}
    @page{margin:0;size:A4}@media print{.np{display:none!important}body{margin:0;padding:0}}</style>
  </head><body>
  <div class="np">
    <button onclick="window.print()" style="padding:8px 20px;background:#00897B;color:#fff;border:none;border-radius:7px;cursor:pointer;font-weight:700;font-family:inherit">🖨️ Print / Save PDF</button>
    <button onclick="window.close()" style="padding:8px 16px;background:#fff;border:1.5px solid #ddd;border-radius:7px;cursor:pointer;font-family:inherit">Close</button>
    <span style="color:#888;font-size:12px">Set print margins to "None" for best result</span>
  </div>
  ${html}
  </body></html>`);
  w.document.close();
}

// ══════════════════════════════════════════
// SAVE INVOICE
// ══════════════════════════════════════════
async function saveInvoice() {
  const d = getFormData();
  if (!d.cname || d.cname === 'Client Name') { toast('⚠️ Please enter client name', 'warning'); return; }
  if (formItems.length === 0) { toast('⚠️ Add at least one line item', 'warning'); return; }
  const selVal = document.getElementById('f-client-select')?.value;
  const payload = {
    invoice_number: d.num, client_id: selVal ? parseInt(selVal) : null,
    client_name: d.cname, service_type: d.svc, issued_date: d.date, due_date: d.due,
    status: d.status, currency: d.sym, subtotal: d.sub,
    discount_pct: d.disc, discount_amt: d.discAmt, gst_amount: d.gstAmt, grand_total: d.grand,
    notes: d.notes || '', bank_details: d.bank || '', terms: d.tnc || '',
    company_logo: d.companyLogo, client_logo: d.clientLogo,
    signature: d.signature, qr_code: d.qrUrl,
    template_id: d.tpl, generated_by: d.generatedBy, show_generated: d.showGeneratedBy ? 1 : 0,
    pdf_options: d.popt,
    items: formItems.map(i => ({ desc: i.desc, itemType: i.itemType||'Service', qty: parseFloat(i.qty)||1, rate: parseFloat(i.rate)||0, gst: (i.gst !== undefined && i.gst !== null && i.gst !== '') ? parseFloat(i.gst) : 18 }))
  };
  try {
    if (STATE.editingInvoiceId) {
      const inv = STATE.invoices.find(i => String(i.id) === String(STATE.editingInvoiceId));
      const dbId = inv?._dbId || parseInt(inv?.id) || 0;
      await api('api/invoices.php?id=' + dbId, 'PUT', payload);
      toast('✅ Invoice updated!', 'success');
    } else {
      await api('api/invoices.php', 'POST', payload);
      toast('✅ Invoice ' + d.num + ' saved!', 'success');
    showPage('invoices', document.querySelector('.nav-item[data-page="invoices"]'));
    }
    const r = await api('api/invoices.php');
    STATE.invoices = Array.isArray(r.data) ? r.data : [];
    STATE.filteredInvoices = [...STATE.invoices];
    STATE.editingInvoiceId = null;
    renderInvoicesTable(); renderDashRecent(); renderDonutChart(); updateDashStats();
    const badge = document.getElementById('badge-invoices');
    if (badge) badge.textContent = STATE.invoices.length;
    // Auto-send WA if automation toggle is ON
    const wa = STATE.settings.wa || {};
    if (wa.auto_inv === '1') {
      const saved = STATE.invoices.find(i => (i.num||i.invoice_number) === d.num);
      if (saved) {
        const c = STATE.clients.find(x => String(x.id) === String(saved.client)) || {};
        const phone = (c.wa || c.whatsapp || c.phone || '').replace(/\D/g,'');
        if (phone) {
          const tpl = wa.tpl_inv || getDefaultWATpl('inv');
          const msg = formatWAMsg(tpl, saved, c, STATE.settings);
          logWAMessage({ inv: saved, client: c, type: 'invoice_created', msg, status: 'sending' });
          sendWA(phone, msg, 'invoice_created', saved, c)
            .then(r => logWAMessage({ inv: saved, client: c, type: 'invoice_created', msg, status: r ? 'sent_api' : 'sent_web' }))
            .catch(e => { logWAMessage({ inv: saved, client: c, type: 'invoice_created', msg, status: 'failed', error: e.message }); console.warn('WA send failed:', e.message); });
        }
      }
    }
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

// ══════════════════════════════════════════
// PREVIEW MODAL
// ══════════════════════════════════════════
function openPreviewModal(id) {
  // Handle both string and numeric IDs from DB
  const inv = STATE.invoices.find(i=>String(i.id)===String(id));
  if (!inv) return;
  STATE.activeMenuInvoiceId = id;
  const c = STATE.clients.find(x=>x.id===inv.client) || {};
  const sc = STATE.settings;
  // Build data object directly from invoice — no form manipulation needed
  const d = {
    tpl: parseInt(inv.template) || STATE.settings.activeTemplate || 1,
    num: inv.num || inv.invoice_number,
    date: inv.issued,
    due: inv.due,
    svc: inv.service,
    cname: c.name || inv.clientName || '',
    cperson: c.person || '',
    cemail: c.email || '',
    cwa: c.wa || '',
    cgst: c.gst || '',
    caddr: c.addr || '',
    disc: inv.disc || 0,
    discAmt: inv.subtotal ? inv.subtotal * (inv.disc||0) / 100 : 0,
    notes: (inv.notes||'').replace(/\s*\|?\s*Partial payment received\..*$/i,'').trim(),
    bank: inv.bank || inv.bank_details || STATE.settings.defaultBank || '',
    tnc: inv.tnc || inv.terms || '',
    status: inv.status,
    sym: inv.currency || '₹',
    sub: inv.subtotal || inv.amount,
    gstAmt: 0,
    grand: inv.amount,
    companyLogo: STATE.settings.logo || sc.logo || '',
    clientLogo: '',
    signature: sc.signature || STATE.settings.signature || '',
    qrUrl: '',
    invId: String(inv.id || ''),
    popt: { bank:true, qr:false, sign:true, logo:true, clientLogo:false, notes:true, tnc:true, gstCol:true, footer:true, watermark:true },
    generatedBy: 'OPTMS Tech Invoice Manager · optmstech.in',
    showGeneratedBy: true
  };
  // Recalculate totals from items if available
  if (inv.items && inv.items.length) {
    let sub=0, gstAmt=0;
    inv.items.forEach(it => { const line=((it.qty||it.quantity)||1)*(it.rate||0); sub+=line; gstAmt+=line*((it.gstRate!==undefined?parseFloat(it.gstRate):it.gst!==undefined&&it.gst!==null&&it.gst!==''?parseFloat(it.gst):it.gstRate!==undefined&&it.gstRate!==''?parseFloat(it.gstRate):18)/100); });
    const disc=inv.disc||0; const discAmt=sub*disc/100; const discF=sub>0?(1-disc/100):1;
    d.sub=sub; d.discAmt=discAmt; d.gstAmt=gstAmt*discF; d.grand=sub-discAmt+gstAmt*discF;
  }
  // Build items HTML
  const invItems = (inv.items||[]);
  const previewItemsHTML = invItems.length
    ? invItems.map(i => {
        const qty  = parseFloat(i.qty||i.quantity||1);
        const rate = parseFloat(i.rate||0);
        const gstR = (i.gst!==undefined&&i.gst!==null&&i.gst!==''?parseFloat(i.gst):i.gstRate!==undefined&&i.gstRate!==''?parseFloat(i.gstRate):i.gst_rate!==undefined&&i.gst_rate!==''?parseFloat(i.gst_rate):18);
        const desc = i.desc||i.description||'—';
        const line        = qty*rate;
        const gstAmt      = line * gstR / 100;
        const lineInclGst = line + gstAmt;
        const itype       = i.itemType||i.item_type||'Service';
        return `<tr>
          <td style="padding:9px 12px;border-bottom:1px solid #eee">${desc}</td>
          <td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee;font-size:11px;color:#888">${itype}</td>
          <td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee">${qty}</td>
          <td style="padding:9px 12px;text-align:right;border-bottom:1px solid #eee">${fmt_money(rate,d.sym)}</td>
          <td style="padding:9px 12px;text-align:right;border-bottom:1px solid #eee">${fmt_money(line,d.sym)}</td>
          <td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee">${gstR}%</td>
          <td style="padding:9px 12px;text-align:right;font-weight:700;border-bottom:1px solid #eee">${fmt_money(lineInclGst,d.sym)}</td>
        </tr>`;
      }).join('')
    : `<tr><td colspan="7" style="padding:20px;text-align:center;color:#aaa">No items</td></tr>`;
  const gstColHeader = `<th style="padding:10px 12px;text-align:center">GST%</th>`;
  const templates = { 1:buildTpl1,2:buildTpl2,3:buildTpl3,4:buildTpl4,5:buildTpl5,6:buildTpl6,7:buildTpl7,8:buildTpl8,9:buildTpl9 };
  const fn = templates[d.tpl] || buildTpl1;
  const scale = 0.72;
  const scaledH = Math.round(1123*scale);
  const previewWrap = `<div style="width:${Math.round(794*scale)}px;height:${scaledH}px;overflow:hidden;position:relative;margin:0 auto"><div style="width:794px;transform:scale(${scale});transform-origin:top left;position:absolute;top:0;left:0">${fn(d, sc, previewItemsHTML, gstColHeader)}</div></div>`;
  document.getElementById('mp-body').innerHTML = previewWrap;
  const titleEl = document.getElementById('mp-title');
  titleEl.textContent = `Invoice ${inv.num} — ${c.name||''}`;
  titleEl.dataset.invId = id;
  openModal('modal-preview');
}

function loadInvoiceIntoForm(inv) {
  const c = STATE.clients.find(x=>x.id===inv.client);
  document.getElementById('f-num').value      = inv.num || inv.invoice_number || '';
  document.getElementById('f-service').value  = inv.service || '';
  document.getElementById('f-date').value     = inv.issued;
  document.getElementById('f-due').value      = inv.due;
  document.getElementById('f-disc').value     = inv.disc||0;
  document.getElementById('f-notes').value    = (inv.notes||'').replace(/\s*\|?\s*Partial payment received\..*$/i,'').trim();
  const _bankEl = document.getElementById('f-bank'); if(_bankEl) _bankEl.value = inv.bank||inv.bank_details||STATE.settings.defaultBank||'';
  const _tncEl  = document.getElementById('f-tnc');  if(_tncEl)  _tncEl.value  = inv.tnc||inv.terms||STATE.settings.defaultTnC||'';
  // f-bank and f-tnc set above
  document.getElementById('f-template').value = inv.template||1;
  document.getElementById('f-currency').value = inv.currency||'₹';
  document.getElementById('f-cname').value    = c ? c.name : (inv.clientName||'');
  document.getElementById('f-cperson').value  = c ? c.person : '';
  document.getElementById('f-cwa').value      = c ? c.wa : '';
  document.getElementById('f-cemail').value   = c ? c.email : '';
  document.getElementById('f-cgst').value     = c ? c.gst : '';
  document.getElementById('f-caddr').value    = c ? c.addr : '';
  const sr = document.querySelectorAll('input[name="inv-status"]');
  sr.forEach(r => r.checked = r.value === inv.status);
  formItems = inv.items.map(i => ({ id: Date.now() + Math.random(), desc: i.desc||i.description||'', itemType: i.itemType||i.item_type||'Service', qty: parseFloat(i.qty||i.quantity)||1, gst: (i.gst!==undefined&&i.gst!==null&&i.gst!==''?parseFloat(i.gst):i.gstRate!==undefined&&i.gstRate!==null&&i.gstRate!==''?parseFloat(i.gstRate):i.gst_rate!==undefined&&i.gst_rate!==''?parseFloat(i.gst_rate):18), rate: parseFloat(i.rate)||0 }));
  livePreview();
}

// ══════════════════════════════════════════
// WHATSAPP
// ══════════════════════════════════════════
function sendWAFromForm() {
  const wa   = document.getElementById('f-cwa').value;
  const name = document.getElementById('f-cname').value;
  const num  = document.getElementById('f-num').value;
  const d    = getFormData();
  sendWAMessage(wa, name, num, fmt_money(d.grand, d.sym), d.due);
}

function sendWAFromModal() {
  const id  = document.getElementById('mp-title').dataset.invId;
  const inv = STATE.invoices.find(i=>String(i.id)===String(id));
  if (!inv) { toast('⚠️ Invoice not found','warning'); return; }
  sendWAForInvoice(inv).catch(e => { if(e) toast('❌ '+e.message,'error'); });
}


function sendWAMessage(wa, name, num, amount, due) {
  if (!wa) { toast('⚠️ No WhatsApp number for this client', 'warning'); return; }
  const token = document.getElementById('wa-token')?.value;
  if (token) {
    toast('📱 Sending via WhatsApp Business API…', 'info');
    setTimeout(()=>toast(`✅ WhatsApp sent to ${name}!`, 'success'), 1500);
  } else {
    const tpl = document.getElementById('wa-tpl-inv')?.value || 'Hi {client_name}! Invoice #{invoice_no} for {amount} from OPTMS Tech. Due: {due_date}.';
    const msg = tpl.replace('{client_name}',name).replace('{invoice_no}',num).replace('{amount}',amount).replace('{due_date}',due).replace('{upi}',STATE.settings.upi);
    const num2 = wa.replace(/\D/g,'');
    window.open(`https://wa.me/${num2}?text=${encodeURIComponent(msg)}`,'_blank');
    toast(`📱 Opening WhatsApp for ${name}`, 'success');
  }
}

// ══════════════════════════════════════════
// EMAIL
// ══════════════════════════════════════════
function sendEmailFromForm() {
  const email = document.getElementById('f-cemail').value;
  const name  = document.getElementById('f-cname').value;
  const num   = document.getElementById('f-num').value;
  const d     = getFormData();
  sendEmailForClient(email, name, num, fmt_money(d.grand, d.sym), d.due, d.svc, d);
}

function sendEmailFromModal() {
  const id  = document.getElementById('mp-title').dataset.invId;
  const inv = STATE.invoices.find(i=>String(i.id)===String(id));
  if (!inv) { toast('⚠️ Invoice not found','warning'); return; }
  const c   = STATE.clients.find(x=>String(x.id)===String(inv.client)) || {};
  const email = c.email || '';
  if (!email) { toast('⚠️ No email for this client','warning'); return; }
  const sc  = STATE.settings;
  const subj= encodeURIComponent(`Invoice #${inv.num||inv.invoice_number} from ${sc.company||'OPTMS Tech'} – ${fmt_money(inv.amount)}`);
  const bdy = encodeURIComponent(`Dear ${c.name||'Client'},

Invoice #: ${inv.num||inv.invoice_number}
Service: ${inv.service||''}
Amount: ${fmt_money(inv.amount)}
Due: ${inv.due||''}

UPI: ${sc.upi||''}

Thank you,
${sc.company||''}
${sc.phone||''}`);
  window.open(`mailto:${email}?subject=${subj}&body=${bdy}`,'_blank');
  toast('📧 Opening email client...','info');
}

function sendEmailForInvoice(inv) {
  const c = STATE.clients.find(x=>String(x.id)===String(inv.client)) || {};
  const num = inv.num || inv.invoice_number || '';
  const amt = fmt_money(inv.amount || inv.grand_total || 0, inv.currency||'₹');
  const due = inv.due || inv.due_date || '';
  const svc = inv.service || inv.service_type || '';
  const d   = {
    bank: inv.bank || inv.bank_details || STATE.settings.defaultBank || '',
    notes: inv.notes || '', tnc: inv.tnc || inv.terms || '',
    sym: inv.currency||'₹', grand: inv.amount||0,
    companyLogo: STATE.settings.logo||''
  };
  sendEmailForClient(c.email||'', c.name||'Client', num, amt, due, svc, d);
}

function sendEmailForClient(email, name, num, amount, due, service, d) {
  if (!email) { toast('⚠️ No email address for this client', 'warning'); return; }
  const sc      = STATE.settings;
  const company = sc.company || '';
  const phone   = sc.phone   || '';
  const upi     = sc.upi     || '';
  const bank    = (d && d.bank) || sc.defaultBank || '';
  const subjTpl = document.getElementById('em-subj')?.value || 'Invoice #{invoice_no} from {company_name}';
  const bodyTpl = document.getElementById('em-body')?.value ||
    'Dear {client_name},\n\nPlease find Invoice #{invoice_no} for {amount} due on {due_date}.\n\nService: {service}\n\nPay via UPI: {upi}\n{bank_details}\n\nThank you!\n{company_name}\n{company_phone}';
  const subj = subjTpl
    .replace(/{invoice_no}/g, num).replace(/{amount}/g, amount)
    .replace(/{client_name}/g, name).replace(/{company_name}/g, company)
    .replace(/{due_date}/g, due).replace(/{service}/g, service);
  const body = bodyTpl
    .replace(/{invoice_no}/g, num).replace(/{amount}/g, amount)
    .replace(/{client_name}/g, name).replace(/{company_name}/g, company)
    .replace(/{due_date}/g, due).replace(/{service}/g, service)
    .replace(/{upi}/g, upi).replace(/{bank_details}/g, bank)
    .replace(/{company_phone}/g, phone);
  const mailto = `mailto:${email}?subject=${encodeURIComponent(subj)}&body=${encodeURIComponent(body)}`;
  window.open(mailto, '_blank');
  toast('📧 Email client opened for ' + name, 'success');
}

// ══════════════════════════════════════════
// MARK PAID
// ══════════════════════════════════════════
function markFormPaid() { openPaidModal(null); }
function openPaidModal(id) {
  STATE.activeMenuInvoiceId = String(id || STATE.activeMenuInvoiceId);
  document.getElementById('paid-date').value = fmt_date(new Date());
  document.getElementById('paid-txn').value  = '';
  document.getElementById('paid-notes').value = '';
  document.getElementById('paid-remaining-box').style.display = 'none';
  // Reset split payment panel — clear amounts to zero, hide panel
  const splitPanel = document.getElementById('split-payment-panel');
  if (splitPanel) splitPanel.style.display = 'none';
  document.querySelectorAll('#split-rows .split-amt').forEach(el => { el.value = ''; });
  const splitTotal = document.getElementById('split-total');
  if (splitTotal) splitTotal.textContent = '₹0.00';
  const methodSel = document.getElementById('paid-method');
  if (methodSel) methodSel.selectedIndex = 0;
  // Re-enable amount field (may have been dimmed by split mode)
  const amtFld = document.getElementById('paid-amt-field');
  if (amtFld) amtFld.style.opacity = '1';

  const inv = STATE.invoices.find(i=>String(i.id)===String(STATE.activeMenuInvoiceId));
  const c   = inv ? (STATE.clients.find(x=>String(x.id)===String(inv.client))||{}) : {};
  const amt = inv ? parseFloat(inv.amount||0) : parseFloat(getFormData().grand||0);
  const sym = inv ? (inv.currency||'₹') : '₹';

  // Calculate already paid for this invoice
  const alreadyPaid = STATE.payments
    .filter(p => p.invoice_id && String(p.invoice_id) === STATE.activeMenuInvoiceId)
    .reduce((s,p) => s + parseFloat(p.amount||0), 0);
  const remaining = Math.max(0, amt - alreadyPaid);

  // Pre-fill amount with what's still due
  document.getElementById('paid-amt').value = (remaining > 0 ? remaining : amt).toFixed(2);

  // Show already-paid + remaining in summary bar
  const remRow = document.getElementById('paid-inv-remaining-row');
  const alreadyEl = document.getElementById('paid-inv-already');
  const remainingEl = document.getElementById('paid-inv-remaining');
  if (remRow) {
    if (alreadyPaid > 0.01) {
      remRow.style.display = 'flex';
      if (alreadyEl) alreadyEl.textContent = fmt_money(alreadyPaid, sym);
      if (remainingEl) remainingEl.textContent = fmt_money(remaining, sym);
    } else {
      remRow.style.display = 'none';
    }
  }

  // If already partially paid, show partial box with checkbox pre-checked
  if (alreadyPaid > 0.01 && remaining > 0.01) {
    const rb = document.getElementById('paid-remaining-box');
    if (rb) {
      rb.style.display = 'block';
      const rt = document.getElementById('paid-rem-total');
      const rr = document.getElementById('paid-rem-received');
      const rd = document.getElementById('paid-rem-due');
      if (rt) rt.textContent = fmt_money(amt, sym);
      if (rr) rr.textContent = fmt_money(alreadyPaid, sym);
      if (rd) rd.textContent = fmt_money(remaining, sym);
      const cb = document.getElementById('paid-collect-remaining');
      if (cb) cb.checked = true;
    }
  }

  // Summary bar
  const numEl = document.getElementById('paid-inv-num');
  const cliEl = document.getElementById('paid-inv-client');
  const totEl = document.getElementById('paid-inv-total');
  if (numEl) numEl.textContent = inv ? (inv.num||inv.invoice_number||'') : '';
  if (cliEl) cliEl.textContent = c.name || (inv&&inv.client_name) || '';
  if (totEl) totEl.textContent = fmt_money(amt, sym);

  const hdr = document.querySelector('#modal-paid .modal-header span');
  if (hdr) hdr.textContent = inv&&inv.status==='Partial' ? 'Collect Remaining Payment' : 'Mark Invoice as Paid';
  openModal('modal-paid');
}

// Called when user types directly in Amount Received — ignored when split mode is active
function onPaidAmtInput() {
  const isSplit = document.getElementById('paid-method')?.value === 'Split';
  if (isSplit) {
    // When total amount changes in split mode, redistribute: fill row 0 with full amount, clear row 1 so user re-adjusts
    const totalAmt = parseFloat(document.getElementById('paid-amt')?.value) || 0;
    const rows = document.querySelectorAll('#split-rows .split-amt');
    if (rows.length >= 2) {
      rows[0].value = totalAmt > 0 ? totalAmt.toFixed(2) : '';
      rows[1].value = '';
    }
    updateSplitTotal();
    renderSplitBreakdown();
    return;
  }
  updatePaidRemaining();
}

function updatePaidRemaining() {
  const mid  = STATE.activeMenuInvoiceId;
  const inv  = STATE.invoices.find(i=>String(i.id)===mid);
  if (!inv)  return;
  const sym       = inv.currency || '₹';
  const total     = parseFloat(inv.amount || 0);
  const received  = parseFloat(document.getElementById('paid-amt').value) || 0;
  const prevPaid  = STATE.payments
    .filter(p => p.invoice_id && String(p.invoice_id) === mid)
    .reduce((s,p) => s + parseFloat(p.amount||0), 0);
  const totalReceived = prevPaid + received;
  const remaining     = Math.max(0, total - totalReceived);
  const remBox        = document.getElementById('paid-remaining-box');
  const hasPartialSituation = prevPaid > 0.01 || (received > 0 && remaining > 0.01);
  if (hasPartialSituation) {
    remBox.style.display = 'block';
    const el = id => document.getElementById(id);
    el('paid-rem-total').textContent    = fmt_money(total, sym);
    el('paid-rem-received').textContent = fmt_money(totalReceived, sym);
    el('paid-rem-due').textContent      = fmt_money(remaining, sym);
    const pct = total > 0 ? Math.min(100, Math.round(totalReceived / total * 100)) : 0;
    const bar = el('paid-rem-bar');
    if (bar) bar.style.width = pct + '%';
  } else {
    remBox.style.display = 'none';
  }
}

function confirmPaid() {
  const mid = String(STATE.activeMenuInvoiceId);
  const inv = STATE.invoices.find(i=>String(i.id)===mid);
  if (!inv) { closeModal('modal-paid'); return; }
  // Block save if split payment amounts don't match Amount Received
  const isSplitMethod = document.getElementById('paid-method')?.value === 'Split';
  if (isSplitMethod) {
    const splitRows = document.querySelectorAll('#split-rows .split-amt');
    const splitSum  = Array.from(splitRows).reduce((s,el) => s + (parseFloat(el.value)||0), 0);
    const amtFld    = parseFloat(document.getElementById('paid-amt').value) || 0;
    if (splitSum < 0.01) { toast('⚠️ Enter split amounts for each method', 'warning'); return; }
    if (Math.abs(splitSum - amtFld) > 0.01) {
      toast(`⚠️ Split total (${fmt_money(splitSum,'₹')}) must equal Amount Received (${fmt_money(amtFld,'₹')})`, 'warning');
      return;
    }
  }
  const amtReceived = parseFloat(document.getElementById('paid-amt').value)||parseFloat(inv.amount)||0;
  const totalAmt    = parseFloat(inv.amount||0);
  // Total paid including ALL previous partial payments + this payment
  const prevPaid = STATE.payments
    .filter(p => p.invoice_id && String(p.invoice_id) === mid)
    .reduce((s,p) => s + parseFloat(p.amount||0), 0);
  const cumulativePaid = prevPaid + amtReceived;
  const remaining = Math.max(0, totalAmt - cumulativePaid);
  // ALERT: if amount < total and checkbox not checked, warn user
  if (remaining > 0.01) {
    const partialCheckEl = document.getElementById('paid-collect-remaining');
    if (!partialCheckEl || !partialCheckEl.checked) {
      const remBox = document.getElementById('paid-remaining-box');
      if (remBox) { remBox.style.display = 'block'; remBox.style.border = '2px solid #E53935'; remBox.style.background = '#FFF3F3'; setTimeout(()=>{ remBox.style.border='1.5px solid #FFD54F'; remBox.style.background='#FFF8E1'; },2500); }
      toast(`⚠️ Amount received (${fmt_money(amtReceived,'₹')}) is less than invoice total (${fmt_money(totalAmt,'₹')}). Please tick "Record as partial" checkbox to keep invoice active for the remaining ${fmt_money(remaining,'₹')}, or enter the full amount.`, 'warning');
      return;
    }
  }
  const isPartial = remaining > 0.01 &&
                    document.getElementById('paid-collect-remaining')?.checked;
  const payload = {
    invoice_id:     parseInt(mid)||null,
    invoice_number: inv.num||inv.invoice_number||'',
    client_name:    (STATE.clients.find(c=>String(c.id)===String(inv.client))||{}).name||inv.client_name||'',
    amount:         amtReceived,
    payment_date:   document.getElementById('paid-date').value,
    method: (document.getElementById('paid-method').value === 'Split')
              ? getSplitMethodLabel()
              : document.getElementById('paid-method').value,
    transaction_id: document.getElementById('paid-txn').value,
    notes:          document.getElementById('paid-notes')?.value || '',
    status:         'Success',
    partial:        isPartial ? 1 : 0,
    remaining_amt:  isPartial ? remaining : 0,
  };
  api('api/payments.php','POST',payload)
    .then(() => Promise.all([api('api/invoices.php'),api('api/payments.php')]))
    .then(([ir,pr]) => {
      if (ir&&ir.data) { STATE.invoices=ir.data; STATE.filteredInvoices=[...ir.data]; }
      if (pr&&pr.data)   STATE.payments=pr.data;
      renderInvoicesTable(); renderDonutChart(); renderDashRecent(); renderPayments(); updateDashStats(); renderDashKpis();
      const partialCheck = document.getElementById('paid-collect-remaining');
      const wasPartial = partialCheck && partialCheck.checked && payload.partial;
      if (wasPartial) {
        toast(`✅ Partial payment (${fmt_money(payload.amount,'₹')}) recorded! Invoice remains active for remaining ${fmt_money(payload.remaining_amt,'₹')}.`,'success');
      } else {
        toast('✅ Invoice marked paid & payment recorded!','success');
      }
      // Auto-send WA receipt if toggle ON
      const waP = STATE.settings.wa || {};
      // Determine if we should send: paid uses auto_paid, partial uses auto_partial
      const shouldSendWA = wasPartial ? (waP.auto_partial !== '0') : (waP.auto_paid !== '0');
      if (shouldSendWA) {
        const paidInv = STATE.invoices.find(i => String(i.id) === String(mid));
        if (paidInv) {
          const cP     = STATE.clients.find(x => String(x.id) === String(paidInv.client)) || {};
          const phoneP = (cP.wa || cP.whatsapp || cP.phone || '').replace(/\D/g,'');
          if (phoneP) {
            // Choose template based on payment type
            const isSplitPmt = payload.method && payload.method.startsWith('Split');
            let tplKey, tplDefault, tplName;
            if (wasPartial) {
              tplKey = waP.tpl_partial; tplDefault = getDefaultWATpl('partial_receipt');
              tplName = 'partial_payment';
            } else if (isSplitPmt) {
              tplKey = waP.tpl_split || waP.tpl_paid; tplDefault = getDefaultWATpl('split_receipt');
              tplName = 'split_payment';
            } else {
              tplKey = waP.tpl_paid; tplDefault = getDefaultWATpl('paid');
              tplName = 'payment_received';
            }
            const tplP = tplKey || tplDefault;
            // Enrich inv with payment-specific data for template variables
            const invWithPmt = Object.assign({}, paidInv, {
              _paidAmt:      payload.amount,
              _remainingAmt: payload.remaining_amt || 0,
              _payMethod:    payload.method,
              _instalmentNo: pr&&pr.data ? pr.data.filter(p=>String(p.invoice_id)===mid).length : 1,
            });
            const msgP = formatWAMsg(tplP, invWithPmt, cP, STATE.settings);
            logWAMessage({ inv: invWithPmt, client: cP, type: tplName, msg: msgP, status: 'sending' });
            sendWA(phoneP, msgP, tplName, invWithPmt, cP)
              .then(r => logWAMessage({ inv: invWithPmt, client: cP, type: tplName, msg: msgP, status: r ? 'sent_api' : 'sent_web' }))
              .catch(e => { logWAMessage({ inv: invWithPmt, client: cP, type: tplName, msg: msgP, status: 'failed', error: e.message }); console.warn('WA payment msg failed:', e.message); });
          }
        }
      }
    })
    .catch(e => toast('❌ '+e.message,'error'));
  closeModal('modal-paid');
}

// ══════════════════════════════════════════
// DELETE
// ══════════════════════════════════════════
function openDeleteModal(id) {
  STATE.activeMenuInvoiceId = id;
  const inv = STATE.invoices.find(i=>String(i.id)===String(id));
  document.getElementById('del-inv-num').textContent = inv ? inv.num : '';
  openModal('modal-delete');
}

function confirmDelete() {
  const mid = String(STATE.activeMenuInvoiceId);
  const inv = STATE.invoices.find(i => String(i.id) === mid);
  if (!inv) { closeModal('modal-delete'); return; }
  api('api/invoices.php?id=' + (parseInt(mid) || 0), 'DELETE')
    .then(() => {
      STATE.invoices = STATE.invoices.filter(i => String(i.id) !== mid);
      STATE.filteredInvoices = STATE.filteredInvoices.filter(i => String(i.id) !== mid);
      toast('🗑️ Invoice ' + (inv.num || inv.invoice_number || '') + ' deleted', 'info');
      renderInvoicesTable(); renderDashRecent(); renderDonutChart(); updateDashStats();
    })
    .catch(e => toast('❌ Delete failed: ' + e.message, 'error'));
  closeModal('modal-delete');
}


// ══════════════════════════════════════════
// STATUS CHANGE (Make Pending / Cancel)
// ══════════════════════════════════════════
async function changeInvoiceStatus(id, newStatus) {
  const inv = STATE.invoices.find(i=>String(i.id)===String(id));
  if (!inv) return;
  const label = newStatus === 'Pending' ? '📤 Made Pending' : newStatus === 'Cancelled' ? '🚫 Cancelled' : newStatus;
  try {
    await api('api/invoices.php?id=' + parseInt(id), 'PATCH', { status: newStatus });
    inv.status = newStatus;
    STATE.filteredInvoices = [...STATE.invoices];
    renderInvoicesTable(); renderDonutChart(); renderDashRecent(); updateDashStats();
    toast(`${label}: ${inv.num||inv.invoice_number}`, 'success');
  } catch(e) { toast('❌ Failed: ' + e.message, 'error'); }
}

function confirmCancelInvoice(id) {
  const inv = STATE.invoices.find(i=>String(i.id)===String(id));
  if (!inv) return;
  if (!confirm(`Cancel invoice ${inv.num||inv.invoice_number}?\n\nThis will mark the invoice as Cancelled and add a CANCELLED watermark. This action cannot be undone easily.`)) return;
  changeInvoiceStatus(id, 'Cancelled');
}
function duplicateInvoice(id) {
  const inv = STATE.invoices.find(i=>String(i.id)===String(id));
  if (!inv) return;
  const newNum = STATE.settings.prefix + (STATE.invoices.length + 1).toString().padStart(3,'0');
  const dup = { ...inv, id:'i'+Date.now(), num:newNum, status:'Draft', issued:fmt_date(new Date()) };
  STATE.invoices.push(dup);
  STATE.filteredInvoices = [...STATE.invoices];
  renderInvoicesTable();
  toast(`📋 Duplicated as ${newNum}`, 'success');
}

// ══════════════════════════════════════════
// CLIENTS
// ══════════════════════════════════════════
function renderClients() {
  const grid = document.getElementById('clientsGrid');
  if (!grid) return;
  grid.innerHTML = STATE.clients.map(c => {
    const initials = getInitials(c.name);
    const rev = STATE.invoices.filter(i=>i.client===c.id && i.status==='Paid').reduce((s,i)=>s+i.amount,0);
    const cnt = STATE.invoices.filter(i=>i.client===c.id).length;
    return `<div class="client-card" style="--c:${c.color}">
      <div style="position:absolute;top:0;left:0;right:0;height:4px;background:${c.color}"></div>
      <div class="cc-head">
        <div class="cc-big-avatar" style="background:${c.color}">
          ${c.image ? `<img src="${c.image}" alt="${c.name}">` : initials}
        </div>
        <div>
          <div class="cc-org">${c.name}</div>
          <div class="cc-contact">${c.person}</div>
          <div class="cc-contact">${c.email}</div>
        </div>
      </div>
      <div class="cc-stats">
        <div class="cc-stat"><div class="cc-stat-val" style="color:${c.color}">${cnt}</div><div class="cc-stat-lbl">Invoices</div></div>
        <div class="cc-stat"><div class="cc-stat-val" style="color:${c.color}">${fmt_money(rev)}</div><div class="cc-stat-lbl">Revenue</div></div>
        <div class="cc-stat"><div class="cc-stat-val" style="color:${c.color}">${c.wa||'—'}</div><div class="cc-stat-lbl">WhatsApp</div></div>
      </div>
      <div class="cc-footer">
        <button class="btn btn-outline" style="flex:1;font-size:12px" onclick="createInvoiceForClient('${c.id}')"><i class="fas fa-plus"></i> Invoice</button>
        <button class="btn btn-whatsapp" style="flex:1;font-size:12px" onclick="sendWAMessage('${c.wa}','${c.name}','','','')"><i class="fab fa-whatsapp"></i> Message</button>
        <button class="btn btn-outline" style="padding:9px 12px" onclick="editClient('${c.id}')"><i class="fas fa-edit"></i></button>
      </div>
    </div>`;
  }).join('');
}

function filterClients(val) {
  const v = val.toLowerCase();
  document.querySelectorAll('.client-card').forEach(card => {
    card.style.display = card.textContent.toLowerCase().includes(v) ? '' : 'none';
  });
}

function createInvoiceForClient(id) {
  showPage('create', null);
  setTimeout(()=>{ updateClientDropdown(); fillClientForm(id); const s=document.getElementById('f-client-select');if(s)s.value=id; livePreview(); },80);
}

function openAddClientModal() {
  STATE._editCid=null;
  ['nc-name','nc-person','nc-wa','nc-email','nc-gst','nc-addr'].forEach(id=>{const e=document.getElementById(id);if(e)e.value='';});
  const col=document.getElementById('nc-color');if(col)col.value='#00897B';
  const hdr=document.querySelector('#modal-addclient .modal-header span');if(hdr)hdr.textContent='Add New Client';
  const btn=document.querySelector('#modal-addclient .modal-footer .btn-primary');if(btn)btn.textContent='Add Client';
  openModal('modal-addclient');
}

async function saveNewClient() {
  const name = (document.getElementById('nc-name')?.value || '').trim();
  if (!name) { toast('⚠️ Enter name', 'warning'); return; }
  const payload = {
    name,
    person: document.getElementById('nc-person')?.value || '',
    email:  document.getElementById('nc-email')?.value  || '',
    wa:     document.getElementById('nc-wa')?.value     || '',
    gst:    document.getElementById('nc-gst')?.value    || '',
    color:  document.getElementById('nc-color')?.value  || '#00897B',
    addr:   document.getElementById('nc-addr')?.value   || ''
  };
  try {
    if (STATE._editCid) {
      const c = STATE.clients.find(x => x.id === STATE._editCid);
      await api('api/clients.php?id=' + (parseInt(c?.id) || 0), 'PUT', payload);
      toast('✅ Client updated!', 'success');
      STATE._editCid = null;
      const hdr = document.querySelector('#modal-addclient .modal-header span');
      if (hdr) hdr.textContent = 'Add New Client';
    } else {
      await api('api/clients.php', 'POST', payload);
      toast('✅ "' + name + '" added!', 'success');
    }
    const r = await api('api/clients.php');
    STATE.clients = Array.isArray(r.data) ? r.data : STATE.clients;
    updateClientDropdown(); renderClients(); populateWAClientDropdown();
    closeModal('modal-addclient');
    ['nc-name','nc-person','nc-wa','nc-email','nc-gst','nc-addr'].forEach(id => {
      const e = document.getElementById(id); if (e) e.value = '';
    });
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function editClient(id) {
  const c=STATE.clients.find(x=>x.id===id); if(!c) return;
  STATE._editCid=id;
  ['nc-name','nc-person','nc-wa','nc-email','nc-gst','nc-addr'].forEach(fid=>{
    const f=document.getElementById(fid); if(f) f.value=c[{'nc-name':'name','nc-person':'person','nc-wa':'wa','nc-email':'email','nc-gst':'gst','nc-addr':'addr'}[fid]]||'';
  });
  const col=document.getElementById('nc-color'); if(col) col.value=c.color||'#00897B';
  const hdr=document.querySelector('#modal-addclient .modal-header span'); if(hdr) hdr.textContent='Edit Client';
  const btn=document.querySelector('#modal-addclient .modal-footer .btn-primary'); if(btn) btn.textContent='Update Client';
  openModal('modal-addclient');
}

// ══════════════════════════════════════════
// PRODUCTS
// ══════════════════════════════════════════
const PROD = { page:1, per:8, list:[] };
function renderProducts() { updateProductCatDropdowns(); PROD.list=[...STATE.products]; PROD.page=1; _renderProdPage(); }
function filterProducts(v) { const s=v.toLowerCase(), cat=document.getElementById('productCatFilter')?.value||''; PROD.list=STATE.products.filter(p=>(!s||p.name.toLowerCase().includes(s)||p.category.toLowerCase().includes(s))&&(!cat||p.category===cat)); PROD.page=1; _renderProdPage(); }
function filterProductsCat(v) { filterProducts(document.getElementById('productSearch')?.value||''); }
function _renderProdPage() {
  const tbody=document.getElementById('productsTbody'); if(!tbody) return;
  const s=(PROD.page-1)*PROD.per, e=s+PROD.per, pg=PROD.list.slice(s,e);
  tbody.innerHTML = pg.map((p,i)=>{
    const catColor = getCatColor(p.category);
    const catTc = getCatTextColor(catColor);
    return `<tr>
    <td>${s+i+1}</td>
    <td><strong>${p.name}</strong></td>
    <td><span style="padding:3px 10px;border-radius:12px;background:${catColor};color:${catTc};font-size:11px;font-weight:700;letter-spacing:.2px;box-shadow:0 1px 3px ${catColor}55">${p.category}</span></td>
    <td><code style="font-family:var(--mono);color:var(--teal);font-weight:700">${fmt_money(p.rate)}</code></td>
    <td><code style="font-family:var(--mono)">${p.hsn}</code></td>
    <td><strong>${p.gst}%</strong></td>
    <td><div class="action-cell">
      <button class="act-btn" title="Add to Invoice" onclick="addProductToInvoice('${p.id}')"><i class="fas fa-plus"></i></button>
      <button class="act-btn" title="Edit" onclick="editProduct('${p.id}')"><i class="fas fa-edit"></i></button>
      <button class="act-btn del" title="Delete" onclick="deleteProduct('${p.id}')"><i class="fas fa-trash"></i></button>
    </div></td>
  </tr>`}).join('')||'<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--muted)">No services found</td></tr>';
  const tot=Math.ceil(PROD.list.length/PROD.per);
  const pg2=document.getElementById('prodPagination');
  if(pg2){let h=`<button class="pg-btn" onclick="prodPage(${PROD.page-1})" ${PROD.page<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;for(let i=1;i<=tot;i++)h+=`<button class="pg-btn ${i===PROD.page?'active':''}" onclick="prodPage(${i})">${i}</button>`;h+=`<button class="pg-btn" onclick="prodPage(${PROD.page+1})" ${PROD.page>=tot?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;pg2.innerHTML=h;}
  const inf=document.getElementById('prodInfo'); if(inf)inf.textContent=`${s+1}–${Math.min(e,PROD.list.length)} of ${PROD.list.length}`;
  const ci=document.getElementById('prodCountInfo'); if(ci)ci.textContent=`${STATE.products.length} total`;
}
function prodPage(p){const t=Math.ceil(PROD.list.length/PROD.per);if(p<1||p>t)return;PROD.page=p;_renderProdPage();}
function editProduct(id){
  const p=STATE.products.find(x=>x.id===id); if(!p) return;
  const catOpts=STATE.categories.map(c=>`<option value="${c.name}" ${c.name===p.category?'selected':''}>${c.name}</option>`).join('');
  document.querySelectorAll('#productsTbody tr').forEach(row=>{
    if(row.innerHTML.includes(`editProduct('${id}')`)){
      row.style.background='#f0fdf4';
      row.innerHTML=`<td><span style="color:var(--teal);font-size:11px;font-weight:700">EDIT</span></td>
      <td><input id="ep-name" class="table-search" style="width:100%" value="${p.name}"></td>
      <td><select id="ep-cat" class="table-filter cat-select" style="min-width:120px">${catOpts}</select></td>
      <td><input id="ep-rate" type="number" class="table-search" style="width:90px" value="${p.rate}"></td>
      <td><input id="ep-hsn" class="table-search" style="width:75px" value="${p.hsn}"></td>
      <td><select id="ep-gst" class="table-filter"><option value="0" ${p.gst==0?'selected':''}>0%</option><option value="5" ${p.gst==5?'selected':''}>5%</option><option value="12" ${p.gst==12?'selected':''}>12%</option><option value="18" ${p.gst==18?'selected':''}>18%</option><option value="28" ${p.gst==28?'selected':''}>28%</option></select></td>
      <td><div class="action-cell"><button class="btn btn-success" style="font-size:11px;padding:4px 10px" onclick="saveEditProd('${id}')"><i class="fas fa-check"></i></button><button class="btn btn-outline" style="font-size:11px;padding:4px 10px" onclick="renderProducts()"><i class="fas fa-times"></i></button></div></td>`;
    }
  });
}
async function saveEditProd(id) {
  const idx = STATE.products.findIndex(x => x.id === id); if (idx < 0) return;
  const n = document.getElementById('ep-name')?.value?.trim();
  if (!n) { toast('Name required', 'warning'); return; }
  const payload = { name:n, category:document.getElementById('ep-cat')?.value||'Other',
    rate:parseFloat(document.getElementById('ep-rate')?.value)||0,
    hsn:document.getElementById('ep-hsn')?.value||'998314',
    gst:(document.getElementById('ep-gst')?.value!==undefined&&document.getElementById('ep-gst')?.value!==''?parseInt(document.getElementById('ep-gst').value):18) };
  try {
    await api('api/products.php?id=' + (parseInt(id.replace('p',''))||0), 'PUT', payload);
    STATE.products[idx] = { ...STATE.products[idx], ...payload };
    renderProducts(); toast('✅ Updated!', 'success');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function openAddProductModal() {
  // Create inline edit row or show a proper add form
  const tbody = document.getElementById('productsTbody');
  // Remove any existing add-row
  const existing = document.getElementById('add-product-row');
  if (existing) { existing.remove(); return; }
  const row = document.createElement('tr');
  row.id = 'add-product-row';
  row.style.background = '#f0fdf4';
  row.innerHTML = `
    <td><span style="color:var(--teal);font-size:12px;font-weight:700">NEW</span></td>
    <td><input id="np-name" class="table-search" style="width:100%;min-width:150px" placeholder="Service name *" value=""></td>
    <td><select id="np-cat" class="table-filter cat-select" style="min-width:120px"></select></td>
    <td><input id="np-rate" type="number" class="table-search" style="width:100px" placeholder="Rate ₹" value="0"></td>
    <td><input id="np-hsn" class="table-search" style="width:80px" placeholder="HSN" value="998314"></td>
    <td>
      <select id="np-gst" class="table-filter">
        <option value="0">0%</option><option value="5">5%</option><option value="12">12%</option><option value="18" selected>18%</option><option value="28">28%</option>
      </select>%
    </td>
    <td>
      <div class="action-cell">
        <button class="btn btn-success" style="font-size:11px;padding:5px 12px" onclick="saveNewProduct()"><i class="fas fa-check"></i> Save</button>
        <button class="btn btn-outline" style="font-size:11px;padding:5px 10px" onclick="document.getElementById('add-product-row').remove()"><i class="fas fa-times"></i></button>
      </div>
    </td>`;
  tbody.insertBefore(row, tbody.firstChild);
  // Populate category dropdown
  const npCat = document.getElementById('np-cat');
  if (npCat) { npCat.innerHTML = STATE.categories.map(c=>`<option value="${c.name}">${c.name}</option>`).join(''); }
  document.getElementById('np-name').focus();
}

async function saveNewProduct() {
  const n = document.getElementById('np-name')?.value?.trim();
  if (!n) { toast('⚠️ Name required', 'warning'); return; }
  const payload = { name:n, category:document.getElementById('np-cat')?.value||'Other',
    rate:parseFloat(document.getElementById('np-rate')?.value)||0,
    hsn:document.getElementById('np-hsn')?.value||'998314',
    gst:(document.getElementById('np-gst')?.value!==undefined&&document.getElementById('np-gst')?.value!==''?parseInt(document.getElementById('np-gst').value):18) };
  try {
    await api('api/products.php', 'POST', payload);
    const r = await api('api/products.php');
    STATE.products = Array.isArray(r.data) ? r.data : STATE.products;
    document.getElementById('add-product-row')?.remove();
    renderProducts(); toast('✅ "' + n + '" added!', 'success');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function addProductToInvoice(id) {
  const p = STATE.products.find(x=>x.id===id);
  if (!p) return;
  showPage('create', null);
  setTimeout(() => {
    formItems.push({ id:Date.now(), desc:p.name, itemType: p.category||'Service', qty:1, gst:(p.gst!==undefined&&p.gst!==null&&p.gst!==''?parseFloat(p.gst):18), rate:p.rate });
    renderFormItems();
    livePreview();
    toast(`✅ "${p.name}" added to invoice`, 'success');
  }, 60);
}

async function deleteProduct(id) {
  const p = STATE.products.find(x => x.id === id); if (!p) return;
  const dbId = parseInt(id.replace('p','')) || 0;
  try {
    await api('api/products.php?id=' + dbId, 'DELETE');
    STATE.products = STATE.products.filter(x => x.id !== id);
    renderProducts(); toast('🗑️ Deleted', 'info');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function openProductPicker() {
  const list = document.getElementById('productPickerList');
  if (!STATE.products.length) {
    list.innerHTML = '<div style="text-align:center;padding:30px;color:var(--muted)"><i class="fas fa-box-open" style="font-size:28px;display:block;margin-bottom:8px;opacity:.3"></i>No services yet. Add from Services/Products page.</div>';
  } else {
    list.innerHTML = STATE.products.map(p => `<div class="pp-item" onclick="pickProduct('${p.id}')">
      <div><div class="pp-name">${p.name}</div><div style="font-size:11px;color:var(--muted)">${p.category} · GST:${p.gst}%</div></div>
      <div class="pp-rate">${fmt_money(p.rate)}</div>
    </div>`).join('');
  }
  openModal('modal-products');
}

function filterProductPicker(val) {
  const v = val.toLowerCase();
  document.querySelectorAll('#productPickerList .pp-item').forEach(el => {
    el.style.display = el.textContent.toLowerCase().includes(v) ? '' : 'none';
  });
}

function pickProduct(id) {
  const p = STATE.products.find(x=>x.id===id);
  if (!p) return;
  formItems.push({ id:Date.now(), desc:p.name, itemType: p.category||'Service', qty:1, gst:(p.gst!==undefined&&p.gst!==null&&p.gst!==''?parseFloat(p.gst):18), rate:p.rate });
  renderFormItems();
  livePreview();
  closeModal('modal-products');
  toast(`✅ "${p.name}" added`, 'success');
}

// ══════════════════════════════════════════
// PAYMENTS
// ══════════════════════════════════════════
const PMT = { page:1, per:10, list:[] };
function renderPayments() { PMT.list=[...STATE.payments]; PMT.page=1; _renderPmtPage(); _renderPmtSummary(); }
function filterPayments(v){ const s=v.toLowerCase(); PMT.list=STATE.payments.filter(p=>(!s||(p.inv&&p.inv.toLowerCase().includes(s))||(p.client&&p.client.toLowerCase().includes(s))||(p.txn&&p.txn.toLowerCase().includes(s)))); PMT.page=1; _renderPmtPage(); }
function filterPaymentsByMethod(v){ PMT.list=v?STATE.payments.filter(p=>p.method===v):[...STATE.payments]; PMT.page=1; _renderPmtPage(); }
function setPmtRange(r){
  const t=new Date(); let f=new Date(),to=new Date();
  if(r==='today'){f=new Date(t);to=new Date(t);}
  else if(r==='week'){f=new Date(t);f.setDate(t.getDate()-t.getDay());to=new Date(f);to.setDate(f.getDate()+6);}
  else if(r==='month'){f=new Date(t.getFullYear(),t.getMonth(),1);to=new Date(t.getFullYear(),t.getMonth()+1,0);}
  const fs=fmt_date(f),ts=fmt_date(to);
  const pf=document.getElementById('pmtFrom'),pt=document.getElementById('pmtTo');
  if(pf)pf.value=fs; if(pt)pt.value=ts;
  ['pmtToday','pmtWeek','pmtMonth'].forEach(id=>{const b=document.getElementById(id);if(b)b.classList.remove('active');});
  const bn=document.getElementById('pmt'+r.charAt(0).toUpperCase()+r.slice(1)); if(bn)bn.classList.add('active');
  filterPmtByDate();
}
function filterPmtByDate(){
  const f=document.getElementById('pmtFrom')?.value||'', t=document.getElementById('pmtTo')?.value||'';
  PMT.list=STATE.payments.filter(p=>(!f||p.date>=f)&&(!t||p.date<=t));
  PMT.page=1; _renderPmtPage();
}
function exportPmtCSV(){
  const h=['Date','Invoice','Client','Method','Txn ID','Amount','Status'];
  const r=STATE.payments.map(p=>[p.date,p.inv,p.client,p.method,p.txn||'',p.amount,p.status].map(v=>`"${v}"`).join(','));
  downloadFile('payments.csv',[h.join(','),...r].join('\n'),'text/csv');
  toast('✅ Exported!','success');
}
function _renderPmtSummary(){
  const el=document.getElementById('pmtSummary'); if(!el) return;
  const tot=STATE.payments.reduce((s,p)=>s+p.amount,0);
  const upi=STATE.payments.filter(p=>p.method&&p.method.toLowerCase().includes('upi')).reduce((s,p)=>s+p.amount,0);
  const neft=STATE.payments.filter(p=>p.method&&(p.method.toLowerCase().includes('neft')||p.method.toLowerCase().includes('bank'))).reduce((s,p)=>s+p.amount,0);
  const tod=fmt_date(new Date()); const todAmt=STATE.payments.filter(p=>p.date===tod).reduce((s,p)=>s+p.amount,0);
  el.innerHTML=`
    <div class="stat-card"><div class="stat-icon" style="background:#e0f2f1;color:#00897B"><i class="fas fa-rupee-sign"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(tot)}</div><div class="stat-lbl">Total Collected</div><div class="stat-trend neutral">${STATE.payments.length} txns</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#e3f2fd;color:#1976D2"><i class="fas fa-mobile-alt"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(upi)}</div><div class="stat-lbl">Via UPI</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#fff8e1;color:#F9A825"><i class="fas fa-university"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(neft)}</div><div class="stat-lbl">Via Bank</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#e8f5e9;color:#388E3C"><i class="fas fa-calendar-day"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(todAmt)}</div><div class="stat-lbl">Today</div></div></div>`;
}
function _renderPmtPage(){
  const tbody=document.getElementById('paymentsTbody'); if(!tbody) return;
  const s=(PMT.page-1)*PMT.per, e=s+PMT.per, pg=PMT.list.slice(s,e);

  // Assign matte color per unique invoice number for visual grouping
  const invColors=['#455A64','#00695C','#1565C0','#6A1B9A','#4E342E','#37474F','#2E7D32','#283593','#B71C1C','#E65100'];
  const invNums=[...new Set(pg.map(p=>p.inv))];
  const invColorMap={};
  invNums.forEach((num,i)=>{ invColorMap[num]=invColors[i%invColors.length]; });
  const invCount={};
  pg.forEach(p=>{ invCount[p.inv]=(invCount[p.inv]||0)+1; });

  tbody.innerHTML=pg.map((p,i)=>{
    const df=p.date?new Date(p.date).toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'}):p.date;
    const mi=p.method&&p.method.toLowerCase().includes('upi')?'fa-mobile-alt':p.method&&p.method.toLowerCase().includes('cheque')?'fa-money-check':p.method&&p.method.toLowerCase().includes('cash')?'fa-money-bill-wave':'fa-university';
    const chipColor=invColorMap[p.inv]||'#455A64';
    const isMulti=invCount[p.inv]>1;
    const layerIcon=isMulti?`<i class="fas fa-layer-group" style="font-size:9px;opacity:.75;margin-right:3px"></i>`:'';
    const invChip=`<span style="display:inline-flex;align-items:center;padding:3px 9px;border-radius:10px;background:${chipColor};color:#fff;font-family:var(--mono);font-weight:700;font-size:12px;letter-spacing:.3px;box-shadow:0 1px 4px ${chipColor}55">${layerIcon}${p.inv}</span>`;
    return `<tr style="${isMulti?'border-left:3px solid '+chipColor+';background:'+chipColor+'08':''}">
      <td style="font-size:12px">${df}</td>
      <td>${invChip}</td>
      <td><strong>${p.client}</strong></td>
      <td><span style="display:flex;align-items:center;gap:5px"><i class="fas ${mi}" style="color:var(--muted2);font-size:11px"></i>${p.method}</span></td>
      <td><code style="font-family:var(--mono);font-size:11px;color:var(--muted)">${p.txn||'—'}</code></td>
      <td><strong style="font-family:var(--mono);color:var(--green)">${fmt_money(p.amount)}</strong></td>
      <td><span class="badge badge-paid">${p.status}</span></td>
      <td><button class="act-btn" title="View Receipt" onclick="viewReceipt(${s+i})"><i class="fas fa-receipt"></i></button></td>
    </tr>`;
  }).join('')||'<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--muted)">No payments recorded</td></tr>';
  const tot=Math.ceil(PMT.list.length/PMT.per);
  const pg2=document.getElementById('pmtPagination');
  if(pg2){let h=`<button class="pg-btn" onclick="pmtPage(${PMT.page-1})" ${PMT.page<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;for(let i=1;i<=tot;i++)h+=`<button class="pg-btn ${i===PMT.page?'active':''}" onclick="pmtPage(${i})">${i}</button>`;h+=`<button class="pg-btn" onclick="pmtPage(${PMT.page+1})" ${PMT.page>=tot?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;pg2.innerHTML=h;}
  const inf=document.getElementById('pmtInfo'); if(inf)inf.textContent=`${s+1}–${Math.min(e,PMT.list.length)} of ${PMT.list.length}`;
}
function pmtPage(p){const t=Math.ceil(PMT.list.length/PMT.per);if(p<1||p>t)return;PMT.page=p;_renderPmtPage();}
function viewReceipt(i){
  const p=PMT.list[i]; if(!p) return;
  const sc=STATE.settings;
  const df=p.date?new Date(p.date).toLocaleDateString('en-IN',{day:'2-digit',month:'long',year:'numeric'}):p.date;
  document.getElementById('receiptBody').innerHTML=`
    <div style="text-align:center;margin-bottom:18px">
      <div style="font-size:20px;font-weight:800;color:var(--teal)">${sc.company}</div>
      <div style="font-size:11px;color:var(--muted)">${sc.address} · ${sc.phone}</div>
    </div>
    <div style="border:2px dashed var(--teal);border-radius:10px;padding:18px;margin-bottom:16px;text-align:center">
      <div style="font-size:36px;color:#388E3C">✓</div>
      <div style="font-weight:700;margin-bottom:4px">Payment Received</div>
      <div style="font-size:28px;font-weight:800;color:var(--teal);font-family:var(--mono)">${fmt_money(p.amount)}</div>
    </div>
    <table style="width:100%;border-collapse:collapse">
      ${[['Date',df],['Invoice #',p.inv],['Client',p.client],['Method',p.method],['Txn ID',p.txn||'—'],['Status',p.status]].map(([k,v])=>`<tr><td style="padding:8px 12px;border-bottom:1px solid var(--border);color:var(--muted);font-size:13px;width:40%">${k}</td><td style="padding:8px 12px;border-bottom:1px solid var(--border);font-weight:600;font-size:13px">${v}</td></tr>`).join('')}
    </table>
    <div style="margin-top:14px;text-align:center;font-size:10px;color:var(--muted)">Computer-generated receipt · OPTMS Tech Invoice Manager</div>`;
  STATE._rcptIdx=i;
  openModal('modal-receipt');
}
function printReceiptModal(){
  const p=PMT.list[STATE._rcptIdx]; if(!p) return;
  const sc=STATE.settings;
  const w=window.open('','_blank','width=600,height=700');
  const df=p.date?new Date(p.date).toLocaleDateString('en-IN',{day:'2-digit',month:'long',year:'numeric'}):p.date;
  w.document.write(`<!DOCTYPE html><html><head><title>Receipt</title><style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:sans-serif;padding:40px}.no-print{display:flex;gap:10px;margin-bottom:20px;padding:10px;background:#f5f5f5;border-radius:8px}@media print{.no-print{display:none!important}}</style></head><body>
  <div class="no-print"><button onclick="window.print()" style="padding:8px 20px;background:#00897B;color:#fff;border:none;border-radius:7px;cursor:pointer;font-weight:bold">Print</button><button onclick="window.close()" style="padding:8px 16px;border:1px solid #ddd;border-radius:7px;cursor:pointer">Close</button></div>
  <div style="max-width:480px;margin:0 auto;border:1px solid #eee;border-radius:12px;overflow:hidden">
    <div style="background:#00897B;color:#fff;padding:20px;text-align:center"><h2>${sc.company}</h2><p style="font-size:12px;opacity:.8">${sc.address}</p></div>
    <div style="padding:24px;text-align:center"><div style="font-size:40px;color:#388E3C">✓</div><div style="font-weight:700">Payment Received</div><div style="font-size:28px;font-weight:800;color:#00897B">${fmt_money(p.amount)}</div></div>
    <table style="width:100%;border-collapse:collapse;padding:0 24px 24px">
      ${[['Date',df],['Invoice',p.inv],['Client',p.client],['Method',p.method],['Txn ID',p.txn||'—']].map(([k,v])=>`<tr><td style="padding:8px 24px;border-bottom:1px solid #eee;color:#666">${k}</td><td style="padding:8px 24px;border-bottom:1px solid #eee;font-weight:600">${v}</td></tr>`).join('')}
    </table>
  </div></body></html>`);
  w.document.close();
}

let serviceChartInst=null, compareChartInst=null;
const RPT={page:1,per:10,list:[],from:'',to:''};
function renderReports(){ setRptRange('all'); }
function setRptRange(r){
  const t=new Date();let f=new Date(),to=new Date();
  if(r==='today'){f=new Date(t);to=new Date(t);}
  else if(r==='week'){f=new Date(t);f.setDate(t.getDate()-t.getDay());to=new Date(f);to.setDate(f.getDate()+6);}
  else if(r==='month'){f=new Date(t.getFullYear(),t.getMonth(),1);to=new Date(t.getFullYear(),t.getMonth()+1,0);}
  else if(r==='quarter'){const q=Math.floor(t.getMonth()/3);f=new Date(t.getFullYear(),q*3,1);to=new Date(t.getFullYear(),q*3+3,0);}
  else if(r==='year'){f=new Date(t.getFullYear(),0,1);to=new Date(t.getFullYear(),11,31);}
  else{f=null;to=null;}
  RPT.from=f?fmt_date(f):''; RPT.to=to?fmt_date(to):'';
  const rf=document.getElementById('rptFrom'),rt=document.getElementById('rptTo');
  if(rf)rf.value=RPT.from; if(rt)rt.value=RPT.to;
  ['today','month','quarter','year','all'].forEach(x=>{const b=document.getElementById('rpt-'+x);if(b)b.classList.remove('active');});
  const bn=document.getElementById('rpt-'+r);if(bn)bn.classList.add('active');
  applyRptFilter();
}
function applyRptFilter(){
  const f=document.getElementById('rptFrom')?.value||RPT.from;
  const t=document.getElementById('rptTo')?.value||RPT.to;
  RPT.from=f;RPT.to=t;
  RPT.list=STATE.invoices.filter(i=>(!f||i.issued>=f)&&(!t||i.issued<=t));
  RPT.page=1; _renderRptStats(); _renderRptTable(); _renderRptCharts();
}
function filterRptTable(v){
  const s=v.toLowerCase();
  RPT.list=STATE.invoices.filter(i=>{
    const c=STATE.clients.find(x=>x.id===i.client);
    if(RPT.from&&i.issued<RPT.from)return false;
    if(RPT.to&&i.issued>RPT.to)return false;
    return i.num.toLowerCase().includes(s)||(c&&c.name.toLowerCase().includes(s))||i.service.toLowerCase().includes(s);
  });
  RPT.page=1;_renderRptTable();
}
function exportRptCSV(){
  const h=['Invoice','Client','Service','Date','Amount','Status'];
  const r=RPT.list.map(i=>{const c=STATE.clients.find(x=>x.id===i.client)||{name:'Unknown'};return[i.num,c.name,i.service,i.issued,i.amount,i.status].map(v=>`"${v}"`).join(',');});
  downloadFile('report.csv',[h.join(','),...r].join('\n'),'text/csv');
  toast('✅ Exported!','success');
}
function _renderRptStats(){
  const el=document.getElementById('rptStatCards');if(!el)return;
  const inv=RPT.list;
  const tot=inv.reduce((s,i)=>s+i.amount,0);
  const paid=inv.filter(i=>i.status==='Paid').reduce((s,i)=>s+i.amount,0);
  const pend=inv.filter(i=>i.status==='Pending').reduce((s,i)=>s+i.amount,0);
  const over=inv.filter(i=>i.status==='Overdue').length;
  const rate=tot>0?Math.round(paid/tot*100):0;
  const top=STATE.clients.map(c=>({...c,r:inv.filter(i=>i.client===c.id&&i.status==='Paid').reduce((s,i)=>s+i.amount,0)})).sort((a,b)=>b.r-a.r)[0];
  el.innerHTML=`
    <div class="stat-card"><div class="stat-icon" style="background:#e0f2f1;color:#00897B"><i class="fas fa-rupee-sign"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(tot)}</div><div class="stat-lbl">Total Revenue</div><div class="stat-trend neutral">${inv.length} invoices</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#e8f5e9;color:#388E3C"><i class="fas fa-check-circle"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(paid)}</div><div class="stat-lbl">Collected</div><div class="stat-trend up">${rate}%</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#fff8e1;color:#F9A825"><i class="fas fa-clock"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(pend)}</div><div class="stat-lbl">Pending</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#fce4ec;color:#e53935"><i class="fas fa-exclamation-circle"></i></div><div class="stat-body"><div class="stat-val">${over}</div><div class="stat-lbl">Overdue</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#f3e5f5;color:#7B1FA2"><i class="fas fa-award"></i></div><div class="stat-body"><div class="stat-val" style="font-size:13px;line-height:1.3">${top?top.name:'—'}</div><div class="stat-lbl">Top Client</div></div></div>`;
}
function _renderRptTable(){
  const tbody=document.getElementById('rptTbody');if(!tbody)return;
  const s=(RPT.page-1)*RPT.per,e=s+RPT.per,pg=RPT.list.slice(s,e);
  tbody.innerHTML=pg.map(inv=>{
    const c=STATE.clients.find(x=>x.id===inv.client)||{name:'Unknown'};
    const df=inv.issued?new Date(inv.issued).toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'}):inv.issued;
    return `<tr><td><code style="font-family:var(--mono);color:var(--teal);font-weight:700">${inv.num}</code></td><td><strong>${c.name}</strong></td><td>${inv.service}</td><td style="font-size:12px">${df}</td><td><strong style="font-family:var(--mono)">${fmt_money(inv.amount)}</strong></td><td><span class="badge badge-${inv.status.toLowerCase()}">${inv.status}</span></td></tr>`;
  }).join('')||'<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--muted)">No transactions in this period</td></tr>';
  const tot=Math.ceil(RPT.list.length/RPT.per);
  const pg2=document.getElementById('rptPagination');
  if(pg2){let h=`<button class="pg-btn" onclick="rptPage(${RPT.page-1})" ${RPT.page<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;for(let i=1;i<=tot;i++)h+=`<button class="pg-btn ${i===RPT.page?'active':''}" onclick="rptPage(${i})">${i}</button>`;h+=`<button class="pg-btn" onclick="rptPage(${RPT.page+1})" ${RPT.page>=tot?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;pg2.innerHTML=h;}
  const inf=document.getElementById('rptInfo');if(inf)inf.textContent=`${s+1}–${Math.min(e,RPT.list.length)} of ${RPT.list.length} transactions`;
}
function rptPage(p){const t=Math.ceil(RPT.list.length/RPT.per);if(p<1||p>t)return;RPT.page=p;_renderRptTable();}
function _renderRptCharts(){
  const c1=document.getElementById('serviceChart'),c2=document.getElementById('compareChart');
  if(!c1||!c2)return;
  if(serviceChartInst){serviceChartInst.destroy();serviceChartInst=null;}
  if(compareChartInst){compareChartInst.destroy();compareChartInst=null;}
  const svc={};RPT.list.forEach(i=>{svc[i.service]=(svc[i.service]||0)+i.amount;});
  const cols=['#00897B','#1976D2','#AB47BC','#E64A19','#F9A825','#388E3C','#E53935','#0097A7','#795548'];
  serviceChartInst=new Chart(c1,{type:'bar',data:{labels:Object.keys(svc),datasets:[{label:'Revenue',data:Object.values(svc),backgroundColor:cols,borderRadius:6}]},options:{responsive:true,maintainAspectRatio:false,animation:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:v=>'₹'+(v>=1000?(v/1000)+'K':v)}}}}});
    const trendNow=new Date(), tYear=trendNow.getFullYear();
  const months=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const curYr=Array(12).fill(0), prevYr=Array(12).fill(0);
  STATE.invoices.forEach(inv=>{
    if(!inv.issued) return;
    const d=new Date(inv.issued),yr=d.getFullYear(),m=d.getMonth(),amt=parseFloat(inv.amount)||0;
    if(yr===tYear)   curYr[m]+=amt;
    if(yr===tYear-1) prevYr[m]+=amt;
  });
  compareChartInst=new Chart(c2,{type:'line',data:{labels:months,datasets:[
    {label:String(tYear-1),data:prevYr,borderColor:'#BDBDBD',tension:.4,borderDash:[5,5],pointRadius:3},
    {label:String(tYear),data:curYr,borderColor:'#00897B',tension:.4,fill:true,backgroundColor:'rgba(0,137,123,.08)',pointRadius:3}
  ]},options:{responsive:true,maintainAspectRatio:false,animation:false,plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true,ticks:{callback:v=>'₹'+(v>=1000?(v/1000)+'K':v)}}}}});
}

// ══════════════════════════════════════════
// TEMPLATES GRID
// ══════════════════════════════════════════
const tplNames = ['Classic Pro','Modern Teal','Bold Dark','Minimal Clean','Corporate Blue','Warm Orange','Purple Gradient','Green Leaf','Red Executive'];
const tplColors = ['#1A2332','#00897B','#0F172A','#000000','#1565C0','#E64A19','#7B1FA2','#1B5E20','#B71C1C'];
const tplAccents = ['#4DB6AC','#26A69A','#38BDF8','#CCCCCC','#42A5F5','#FF7043','#AB47BC','#A5D6A7','#EF9A9A'];

function renderTemplatesGrid() {
  const grid = document.getElementById('templatesGrid');
  if (!grid) return;
  grid.innerHTML = tplNames.map((name, i) => {
    const n = i + 1;
    return `<div class="tpl-card ${STATE.settings.activeTemplate===n?'active-tpl':''}" id="tpl-card-${n}">
      <div class="tpl-thumb" style="background:${tplColors[i]}">
        <div style="width:120px;background:rgba(255,255,255,.95);border-radius:4px;padding:10px;box-shadow:0 2px 8px rgba(0,0,0,.3)">
          <div style="height:6px;background:${tplColors[i]};border-radius:3px;margin-bottom:4px"></div>
          <div style="height:3px;background:${tplAccents[i]};width:60%;border-radius:2px;margin-bottom:6px"></div>
          <div style="display:flex;gap:4px;margin-bottom:4px">${[0,0,0].map(()=>`<div style="flex:1;height:12px;background:#f0f0f0;border-radius:2px"></div>`).join('')}</div>
          <div style="height:2px;background:#eee;margin-bottom:3px"></div>
          <div style="height:2px;background:#eee;margin-bottom:3px"></div>
          <div style="height:4px;background:${tplColors[i]};width:40%;border-radius:2px;margin-top:6px;margin-left:auto"></div>
        </div>
      </div>
      <div class="tpl-info">
        <div class="tpl-name">${n}. ${name}</div>
        <div class="tpl-btns">
          <button class="btn btn-outline" style="font-size:11px;padding:5px 10px" onclick="previewTemplate(${n})">Preview</button>
          <button class="btn btn-success" style="font-size:11px;padding:5px 10px" onclick="setActiveTemplate(${n})">${STATE.settings.activeTemplate===n?'✓ Active':'Set Active'}</button>
        </div>
      </div>
    </div>`;
  }).join('');
}

function previewTemplate(n) {
  const panel=document.getElementById('tplPreviewPanel');
  const inner=document.getElementById('tplPreviewInner');
  const label=document.getElementById('tplPreviewLabel');
  if(!panel||!inner){return;}
  label.textContent=`Template ${n}: ${tplNames[n-1]}`;
  const sc=STATE.settings;
  const sd={tpl:n,num:'OT-DEMO-001',date:'2025-04-10',due:'2025-04-25',svc:'Website Development',
    cname:'Sample Client Ltd',cperson:'Contact Person',cemail:'client@example.com',cwa:'+91 9876543210',
    cgst:'22AAAAA0000A1Z5',caddr:'Your City, State, India',disc:0,discAmt:0,
    notes:'Thank you for choosing OPTMS Tech.',
    bank:'SBI | A/C: 12345678901 | IFSC: SBIN0001234 | UPI: optmstech@upi',
    tnc:'All prices inclusive of taxes. Subject to Patna jurisdiction.',
    status:'Paid',sym:'₹',sub:88500,gstAmt:15930,grand:104430,invId:'',
    companyLogo:sc.logo||'',clientLogo:'',signature:'',qrUrl:'',
    popt:{bank:true,qr:false,sign:true,logo:true,clientLogo:false,notes:true,tnc:true,gstCol:true,footer:true,watermark:true}};
  const iHTML=`<tr><td style="padding:9px 12px;border-bottom:1px solid #eee">Website Development Premium</td><td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee;font-size:11px;color:#666">Service</td><td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee">1</td><td style="padding:9px 12px;text-align:right;border-bottom:1px solid #eee">₹75,000.00</td><td style="padding:9px 12px;text-align:right;border-bottom:1px solid #eee">₹75,000.00</td><td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee">18%</td><td style="padding:9px 12px;text-align:right;font-weight:700;border-bottom:1px solid #eee">₹88,500.00</td></tr><tr><td style="padding:9px 12px;border-bottom:1px solid #eee">Domain & Hosting</td><td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee;font-size:11px;color:#666">Product</td><td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee">1</td><td style="padding:9px 12px;text-align:right;border-bottom:1px solid #eee">₹4,500.00</td><td style="padding:9px 12px;text-align:right;border-bottom:1px solid #eee">₹4,500.00</td><td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee">18%</td><td style="padding:9px 12px;text-align:right;font-weight:700;border-bottom:1px solid #eee">₹5,310.00</td></tr>`;
  const gH=`<th style="padding:10px 12px;text-align:center">GST%</th>`;
  const tpls={1:buildTpl1,2:buildTpl2,3:buildTpl3,4:buildTpl4,5:buildTpl5,6:buildTpl6,7:buildTpl7,8:buildTpl8,9:buildTpl9};
  const fn=tpls[n]||buildTpl1;
  const scale=Math.min(0.78,(window.innerWidth-280)/794);
  const sh=Math.round(1123*scale);
  inner.innerHTML=`<div style="width:${Math.round(794*scale)}px;height:${sh}px;overflow:hidden;position:relative;border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.2)"><div style="width:794px;transform:scale(${scale});transform-origin:top left;position:absolute;top:0;left:0">${fn(sd,sc,iHTML,gH)}</div></div>`;
  panel.style.display='block';
  panel.scrollIntoView({behavior:'smooth',block:'start'});
  toast(`👁️ Template ${n}: ${tplNames[n-1]}`, 'info');
}

function setActiveTemplate(n) {
  STATE.settings.activeTemplate = n;
  document.getElementById('f-template').value = n;
  document.getElementById('sd-tpl').value = n;
  renderTemplatesGrid();
  livePreview();
  toast(`✅ Template ${n} set as default`, 'success');
}

// ══════════════════════════════════════════
// SETTINGS SAVE
// ══════════════════════════════════════════
async function saveCompanySettings() {
  const payload = {
    company_name:    document.getElementById('sc-name')?.value    || '',
    company_gst:     document.getElementById('sc-gst')?.value     || '',
    company_phone:   document.getElementById('sc-phone')?.value   || '',
    company_email:   document.getElementById('sc-email')?.value   || '',
    company_website: document.getElementById('sc-web')?.value     || '',
    invoice_prefix:  document.getElementById('sc-prefix')?.value  || '',
    company_upi:     document.getElementById('sc-upi')?.value     || '',
    company_address: document.getElementById('sc-addr')?.value    || '',
    company_logo:    document.getElementById('sc-logo')?.value    || STATE.settings.logo || '',
    company_sign:    document.getElementById('sc-sign')?.value    || STATE.settings.signature || '',
    company_bank:    document.getElementById('sc-bank')?.value    || STATE.settings.defaultBank  || '',
  };
  Object.assign(STATE.settings, {
    company: payload.company_name, gst: payload.company_gst, phone: payload.company_phone,
    email: payload.company_email, website: payload.company_website, prefix: payload.invoice_prefix,
    upi: payload.company_upi, address: payload.company_address,
    logo: payload.company_logo || STATE.settings.logo,
    signature: payload.company_sign || STATE.settings.signature,
    defaultBank: payload.company_bank || STATE.settings.defaultBank
  });
  // Also refresh bank field in create form if open
  const bankEl = document.getElementById('f-bank');
  if (bankEl && !bankEl.value) bankEl.value = STATE.settings.defaultBank || '';
  try {
    await api('api/settings.php', 'POST', payload);
    livePreview();
    toast('✅ Settings saved!', 'success');
    // Show logo/sign preview if uploaded
    if (payload.company_logo) {
      const lp=document.getElementById('sc-logo-preview');
      if(lp) lp.innerHTML=`<div style="display:inline-flex;align-items:center;gap:8px;padding:5px 10px;background:var(--teal-bg);border-radius:8px;border:1px solid var(--border);margin-top:4px"><img src="${payload.company_logo}" style="height:30px;max-width:110px;object-fit:contain"><span style="font-size:10px;color:var(--muted)">✓ Saved</span></div>`;
    }
    if (payload.company_sign) {
      const sp=document.getElementById('sc-sign-preview');
      if(sp) sp.innerHTML=`<div style="display:inline-flex;align-items:center;gap:8px;padding:5px 10px;background:#1a1a2e;border-radius:8px;border:1px solid var(--border);margin-top:4px"><img src="${payload.company_sign}" style="height:30px;max-width:110px;object-fit:contain"><span style="font-size:10px;color:#aaa">✓ Saved</span></div>`;
    }
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

// saveWASettings: see // saveWASettings is defined below as // Auto-save a single WA toggle immediately when clicked
async function saveWAToggle(key, el) {
  const val = el.classList.contains('on') ? '1' : '0';
  // Update STATE immediately
  if (!STATE.settings.wa) STATE.settings.wa = {};
  STATE.settings.wa[key.replace('wa_', '')] = val;
  // Save single key to DB
  try {
    await api('api/settings.php', 'POST', { [key]: val });
    // Brief visual feedback on the toggle
    el.style.boxShadow = '0 0 0 3px rgba(0,137,123,.35)';
    setTimeout(() => { el.style.boxShadow = ''; }, 700);
  } catch(e) {
    // Revert on error
    el.classList.toggle('on');
    toast('❌ Failed to save: ' + e.message, 'error');
  }
}

window.saveWASettings = async function() {
  // Save all WA settings (credentials + templates) to DB
  const tog = id => document.getElementById(id)?.classList.contains('on') ? '1' : '0';
  const val = id => document.getElementById(id)?.value || '';
  const payload = {
    wa_token:         val('wa-token'),
    wa_pid:           val('wa-pid'),
    wa_bid:           val('wa-bid'),
    wa_test_phone:    val('wa-test-phone'),
    wa_remind_days:   val('wa-remind-days') || '3',
    wa_max_followup:  val('wa-max-followup') || '3',
    wa_tpl_inv:       val('wa-tpl-inv'),
    wa_tpl_paid:      val('wa-tpl-paid'),
    wa_tpl_partial:   val('wa-tpl-partial'),
    wa_tpl_remind:    val('wa-tpl-remind'),
    wa_tpl_overdue:   val('wa-tpl-overdue'),
    wa_tpl_followup:  val('wa-tpl-followup'),
    wa_tpl_festival:  val('wa-tpl-festival'),
    wa_auto_inv:      tog('twa1'),
    wa_auto_paid:     tog('twa2'),
    wa_auto_partial:  tog('twa6'),
    wa_auto_remind:   tog('twa3'),
    wa_auto_overdue:  tog('twa4'),
    wa_auto_followup: tog('twa5'),
  };
  // Update STATE immediately with all keys
  if (!STATE.settings.wa) STATE.settings.wa = {};
  Object.assign(STATE.settings.wa, {
    token: payload.wa_token, pid: payload.wa_pid, bid: payload.wa_bid,
    test_phone: payload.wa_test_phone,
    remind_days: payload.wa_remind_days, max_followup: payload.wa_max_followup,
    tpl_inv: payload.wa_tpl_inv, tpl_paid: payload.wa_tpl_paid,
    tpl_partial: payload.wa_tpl_partial,
    tpl_remind: payload.wa_tpl_remind, tpl_overdue: payload.wa_tpl_overdue,
    tpl_followup: payload.wa_tpl_followup, tpl_festival: payload.wa_tpl_festival,
    auto_inv: payload.wa_auto_inv, auto_paid: payload.wa_auto_paid,
    auto_partial: payload.wa_auto_partial,
    auto_remind: payload.wa_auto_remind, auto_overdue: payload.wa_auto_overdue,
    auto_followup: payload.wa_auto_followup,
  });
  try {
    await api('api/settings.php', 'POST', payload);
    toast('✅ WhatsApp settings saved!', 'success');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
};

function saveEmailSettings() {
  toast('✅ Email settings saved!', 'success');
}


// Format WA message with rich invoice card variables
function formatWAMsg(tpl, inv, client, settings) {
  const sc = settings || STATE.settings;
  const c  = client || STATE.clients.find(x=>String(x.id)===String(inv?.client)) || {};
  const today = new Date().toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'});
  const dueDate = inv.due || inv.due_date || '';
  const dueFmt  = dueDate ? new Date(dueDate).toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'}) : '';
  const issuedFmt = (inv.issued||inv.issued_date) ? new Date(inv.issued||inv.issued_date).toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'}) : today;
  const sym       = inv.currency || '₹';
  const grandTotal = parseFloat(inv.amount||inv.grand_total)||0;
  const amount    = fmt_money(grandTotal, sym);
  const daysOverdue = dueDate ? Math.max(0,Math.floor((new Date()-new Date(dueDate))/86400000)) : 0;
  // Item list with GST-inclusive line totals
  const items = (inv.items||[]).map(i=>{
    const qty  = parseFloat(i.qty||i.quantity||1);
    const rate = parseFloat(i.rate||0);
    const gst  = parseFloat(i.gst||i.gst_rate||i.gstRate||0);
    const line = qty * rate;
    const lineInclGst = line + (line * gst / 100);
    return `  • ${i.desc||i.description||''}: ${fmt_money(lineInclGst, sym)}`;
  }).join('\n');

  // Resolve _paidAmt and _remainingAmt for partial/paid invoices when not explicitly set
  // (e.g. when sending from action menu for an already-Partial invoice)
  let paidAmt      = inv._paidAmt;
  let remainingAmt = inv._remainingAmt;
  if (paidAmt === undefined || remainingAmt === undefined) {
    // Try every possible invoice ID field
    const invId = String(inv.id || inv._dbId || inv.invId || '');
    const invNum = String(inv.num || inv.invoice_number || '');
    if (STATE.payments && (invId || invNum)) {
      // Match by invoice_id (numeric) first; fall back to invoice_number string match.
      // Use only ONE match strategy per payment to prevent double-counting.
      let pmts = invId
        ? STATE.payments.filter(p => p.invoice_id && String(p.invoice_id) === invId)
        : [];
      if (pmts.length === 0 && invNum) {
        pmts = STATE.payments.filter(p => p.invoice_number && String(p.invoice_number) === invNum);
      }
      const totalPaidFromDB = pmts.reduce((s,p) => s + parseFloat(p.amount||0), 0);
      paidAmt      = paidAmt      !== undefined ? paidAmt      : totalPaidFromDB;
      remainingAmt = remainingAmt !== undefined ? remainingAmt : Math.max(0, grandTotal - totalPaidFromDB);
    } else {
      paidAmt      = paidAmt      !== undefined ? paidAmt      : 0;
      remainingAmt = remainingAmt !== undefined ? remainingAmt : grandTotal;
    }
  }

  return (tpl||'')
    .replace(/{client_name}/g,  c.name||inv.clientName||inv.client_name||'Valued Client')
    .replace(/{invoice_no}/g,   inv.num||inv.invoice_number||'')
    .replace(/{amount}/g,       amount)
    .replace(/{currency}/g,     sym)
    .replace(/{due_date}/g,     dueFmt)
    .replace(/{issue_date}/g,   issuedFmt)
    .replace(/{service}/g,      inv.service||inv.service_type||'')
    .replace(/{company_name}/g, sc.company||'')
    .replace(/{company_phone}/g,sc.phone||'')
    .replace(/{company_email}/g,sc.email||'')
    .replace(/{upi}/g,          sc.upi||'')
    .replace(/{bank_details}/g, sc.defaultBank||'')
    .replace(/{days_overdue}/g, String(daysOverdue))
    .replace(/{item_list}/g,    items||'')
    .replace(/{status}/g,       inv.status||'')
    .replace(/{invoice_link}/g, '')
    .replace(/{paid_amount}/g,      fmt_money(paidAmt, sym))
    .replace(/{remaining_amount}/g, fmt_money(remainingAmt, sym))
    .replace(/{payment_method}/g,   inv._payMethod   || '')
    .replace(/{instalment_no}/g,    String(inv._instalmentNo || ''));
}




// ══════════════════════════════════════════
// MESSAGE LOG
// ══════════════════════════════════════════
const MSG_LOG_KEY = 'optms_msg_log';
const MSG_LOG_MAX = 500;

function getMsgLog() {
  try { return JSON.parse(localStorage.getItem(MSG_LOG_KEY) || '[]'); } catch(e) { return []; }
}
function saveMsgLog(log) {
  try { localStorage.setItem(MSG_LOG_KEY, JSON.stringify(log.slice(-MSG_LOG_MAX))); } catch(e) {}
}

function logWAMessage({ inv, client, type, msg, status, error }) {
  const log = getMsgLog();
  const entry = {
    id:       Date.now() + '_' + Math.random().toString(36).slice(2,6),
    ts:       new Date().toISOString(),
    type:     type || 'unknown',
    status:   status || 'sent_web',
    client:   (client && client.name) || (inv && (inv.clientName||inv.client_name)) || '—',
    phone:    (client && (client.wa||client.whatsapp||client.phone)) || '—',
    inv_num:  inv ? (inv.num||inv.invoice_number||'') : '',
    inv_amt:  inv ? fmt_money(parseFloat(inv.amount||inv.grand_total||0), inv.currency||'₹') : '',
    inv_status: inv ? (inv.status||'') : '',
    msg:      msg || '',
    error:    error || '',
  };
  log.push(entry);
  saveMsgLog(log);
  // Update badge
  const failed = log.filter(e=>e.status==='failed').length;
  const badge = document.getElementById('badge-msglog');
  if (badge) {
    if (failed > 0) { badge.style.display=''; badge.textContent = failed; badge.style.background='var(--red)'; }
    else { badge.style.display='none'; }
  }
}

const MSG_TYPE_META = {
  invoice_created:  { icon:'📄', label:'New Invoice',      color:'#1565C0' },
  payment_received: { icon:'✅', label:'Payment Receipt',  color:'#2E7D32' },
  partial_payment:  { icon:'💛', label:'Partial Receipt',  color:'#E65100' },
  payment_overdue:  { icon:'🔴', label:'Overdue Alert',    color:'#C62828' },
  payment_reminder: { icon:'🔔', label:'Due Reminder',     color:'#F57F17' },
  split_payment:    { icon:'⚡', label:'Split Payment',    color:'#7B1FA2' },
  invoice_followup: { icon:'📋', label:'Follow-up',        color:'#546E7A' },
  unknown:          { icon:'💬', label:'Message',          color:'#757575' },
};
const MSG_STATUS_META = {
  sent_api:  { icon:'✅', label:'Sent (API)',  color:'#2E7D32' },
  sent_web:  { icon:'📱', label:'Opened wa.me',color:'#1565C0' },
  failed:    { icon:'❌', label:'Failed',      color:'#C62828' },
  sending:   { icon:'⏳', label:'Sending…',   color:'#F57F17' },
};

function renderMsgLog() {
  const log   = getMsgLog();
  const tbody = document.getElementById('msglog-tbody');
  const stats = document.getElementById('msglog-stats');
  if (!tbody) return;

  const search  = (document.getElementById('msglog-search')?.value||'').toLowerCase();
  const fType   = document.getElementById('msglog-filter-type')?.value  || '';
  const fStatus = document.getElementById('msglog-filter-status')?.value || '';

  // Stats bar
  if (stats) {
    const total   = log.length;
    const sentApi = log.filter(e=>e.status==='sent_api').length;
    const sentWeb = log.filter(e=>e.status==='sent_web').length;
    const failed  = log.filter(e=>e.status==='failed').length;
    const today   = log.filter(e=>e.ts && e.ts.startsWith(new Date().toISOString().slice(0,10))).length;
    const statItems = [
      { icon:'💬', label:'Total Sent',    val:total,   col:'var(--teal)' },
      { icon:'✅', label:'Via API',       val:sentApi, col:'#2E7D32' },
      { icon:'📱', label:'Via wa.me',     val:sentWeb, col:'#1565C0' },
      { icon:'❌', label:'Failed',        val:failed,  col:'#C62828' },
      { icon:'📅', label:"Today",         val:today,   col:'#7B1FA2' },
    ];
    stats.innerHTML = statItems.map(s=>`
      <div style="display:flex;align-items:center;gap:10px;background:var(--card);border:1.5px solid var(--border);border-radius:10px;padding:10px 18px;min-width:120px">
        <span style="font-size:20px">${s.icon}</span>
        <div><div style="font-size:20px;font-weight:800;color:${s.col};line-height:1">${s.val}</div><div style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px">${s.label}</div></div>
      </div>`).join('');
  }

  // Filter
  let filtered = [...log].reverse().filter(e => {
    if (fType   && e.type   !== fType)   return false;
    if (fStatus && e.status !== fStatus) return false;
    if (search) {
      const hay = (e.client+e.inv_num+e.type+e.status+e.msg).toLowerCase();
      if (!hay.includes(search)) return false;
    }
    return true;
  });

  if (!filtered.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--muted)">
      <i class="fas fa-search" style="font-size:28px;opacity:.2;display:block;margin-bottom:8px"></i>No messages match filters</td></tr>`;
    return;
  }

  tbody.innerHTML = filtered.map(e => {
    const tm = MSG_TYPE_META[e.type]   || MSG_TYPE_META.unknown;
    const sm = MSG_STATUS_META[e.status] || { icon:'?', label:e.status, color:'#999' };
    const ts = e.ts ? new Date(e.ts).toLocaleString('en-IN',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit',hour12:true}) : '—';
    const shortMsg = e.msg ? (e.msg.length>80 ? e.msg.slice(0,80)+'…' : e.msg) : '—';
    const errBadge = e.error ? `<div style="font-size:10px;color:var(--red);margin-top:2px">⚠ ${e.error.slice(0,60)}</div>` : '';
    return `<tr style="border-bottom:1px solid var(--border)" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
      <td style="padding:10px 14px;color:var(--muted);font-size:12px;white-space:nowrap">${ts}</td>
      <td style="padding:10px 14px">
        <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;background:${tm.color}15;color:${tm.color}">
          ${tm.icon} ${tm.label}
        </span>
      </td>
      <td style="padding:10px 14px;font-weight:600">${e.client}<div style="font-size:10px;color:var(--muted)">${e.phone||''}</div></td>
      <td style="padding:10px 14px">
        <span style="font-weight:700;font-family:var(--mono)">${e.inv_num||'—'}</span>
        <div style="font-size:10px;color:var(--muted)">${e.inv_amt||''} ${e.inv_status?'· '+e.inv_status:''}</div>
      </td>
      <td style="padding:10px 14px">
        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;background:${sm.color}15;color:${sm.color}">
          ${sm.icon} ${sm.label}
        </span>
        ${errBadge}
      </td>
      <td style="padding:10px 14px;font-size:12px;color:var(--muted);max-width:260px">
        <div style="cursor:pointer" onclick="this.style.whiteSpace=this.style.whiteSpace?'':'pre-wrap';this.title=this.style.whiteSpace?'Click to collapse':'Click to expand'"
          title="Click to expand" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${shortMsg}</div>
      </td>
      <td style="padding:10px 14px;text-align:center">
        <button onclick="resendMsgLogEntry('${e.id}')" title="Resend" style="background:none;border:1.5px solid var(--border);border-radius:7px;padding:5px 10px;cursor:pointer;color:var(--teal);font-size:11px">↩ Resend</button>
      </td>
    </tr>`;
  }).join('');
}

function clearMsgLog() {
  if (!confirm('Clear all message log entries? This cannot be undone.')) return;
  localStorage.removeItem(MSG_LOG_KEY);
  renderMsgLog();
  const badge = document.getElementById('badge-msglog');
  if (badge) badge.style.display = 'none';
  toast('🗑️ Message log cleared', 'info');
}

function exportMsgLog() {
  const log = getMsgLog();
  if (!log.length) { toast('⚠️ No messages to export', 'warning'); return; }
  const header = ['Time','Type','Client','Phone','Invoice','Amount','Inv Status','Msg Status','Message','Error'];
  const rows   = log.map(e => [
    e.ts ? new Date(e.ts).toLocaleString('en-IN') : '',
    e.type, e.client, e.phone, e.inv_num, e.inv_amt, e.inv_status, e.status,
    '"'+(e.msg||'').replace(/"/g,'""')+'"',
    '"'+(e.error||'').replace(/"/g,'""')+'"',
  ].join(','));
  const csv  = [header.join(','), ...rows].join('\n');
  const blob = new Blob([csv], { type:'text/csv' });
  const a    = document.createElement('a');
  a.href     = URL.createObjectURL(blob);
  a.download = 'message_log_' + new Date().toISOString().slice(0,10) + '.csv';
  a.click();
  toast('📥 Message log exported', 'success');
}

function resendMsgLogEntry(id) {
  const log   = getMsgLog();
  const entry = log.find(e => e.id === id);
  if (!entry) { toast('⚠️ Entry not found', 'warning'); return; }
  const inv = STATE.invoices.find(i => (i.num||i.invoice_number) === entry.inv_num) ||
              STATE.invoices.find(i => String(i.id) === String(entry.inv_id));
  if (!inv) { toast('⚠️ Original invoice not found — sending message manually', 'warning'); }
  // Re-use message text and open wa.me with it
  const phone = entry.phone ? entry.phone.replace(/\D/g,'') : '';
  if (!phone) { toast('⚠️ No phone number to resend to', 'warning'); return; }
  const clean = phone.length === 10 ? '91' + phone : phone;
  window.open('https://wa.me/' + clean + '?text=' + encodeURIComponent(entry.msg || ''), '_blank');
  logWAMessage({ inv: inv||{num:entry.inv_num,amount:0}, client:{name:entry.client,wa:entry.phone}, type: entry.type, msg: entry.msg, status:'sent_web' });
  toast('📱 Resend opened in WhatsApp', 'success');
  renderMsgLog();
}

// Send message via Meta WhatsApp Business API

// Send WA for an invoice (called from rowMenuAction 'wa')

// Send WA for payment reminder (called on mark-paid or manual trigger)

// Manual WA send button
// sendManualWA: see below




function testEmail() {
  const from = document.getElementById('em-from')?.value;
  if (!from) { toast('⚠️ Enter email settings first', 'warning'); return; }
  toast('📧 Test email sent to ' + from, 'success');
}

// ══════════════════════════════════════════
// BACKUP & EXPORT
// ══════════════════════════════════════════
function exportAllJSON() {
  const data = JSON.stringify({ invoices: STATE.invoices, clients: STATE.clients, products: STATE.products, payments: STATE.payments, settings: STATE.settings }, null, 2);
  downloadFile('optms_backup.json', data, 'application/json');
  toast('✅ Full backup exported!', 'success');
}

function exportCSV() {
  const headers = ['Invoice#','Client','Service','Issue Date','Due Date','Amount','Status'];
  const rows = STATE.invoices.map(inv => {
    const c = STATE.clients.find(x=>x.id===inv.client);
    return [inv.num, c?.name||'', inv.service, inv.issued, inv.due, inv.amount, inv.status].map(v=>`"${v}"`).join(',');
  });
  downloadFile('optms_invoices.csv', [headers.join(','),...rows].join('\n'), 'text/csv');
  toast('✅ CSV exported!', 'success');
}

function importData() {
  toast('ℹ️ Import: paste JSON data or drag file. Feature coming soon!', 'info');
}

function clearAllData() {
  if (confirm('Are you sure? This will delete ALL invoices, clients, and data.')) {
    STATE.invoices = [];
    STATE.clients  = [];
    STATE.payments = [];
    renderInvoicesTable();
    renderClients();
    renderPayments();
    renderDashRecent();
    renderDonutChart();
    toast('🗑️ All data cleared!', 'warning');
  }
}

function downloadFile(name, content, type) {
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([content], { type }));
  a.download = name;
  a.click();
}

// ══════════════════════════════════════════
// GLOBAL SEARCH
// ══════════════════════════════════════════
function globalSearchFn(val) {
  const el = document.getElementById('searchResults');
  if (!val || val.length < 2) { el.classList.remove('open'); return; }
  const v = val.toLowerCase();
  const results = STATE.invoices.filter(i => {
    const c = STATE.clients.find(x=>x.id===i.client);
    return i.num.toLowerCase().includes(v) || (c&&c.name.toLowerCase().includes(v)) || i.service.toLowerCase().includes(v);
  }).slice(0,6);
  if (!results.length) { el.classList.remove('open'); return; }
  el.innerHTML = results.map(inv => {
    const c = STATE.clients.find(x=>x.id===inv.client);
    return `<div class="sr-item" onclick="openPreviewModal('${inv.id}');document.getElementById('globalSearch').value='';document.getElementById('searchResults').classList.remove('open')">
      <i class="fas fa-file-invoice" style="color:var(--teal)"></i>
      <div><strong>${inv.num}</strong> – ${c?.name||'Unknown'}<br><small style="color:var(--muted)">${inv.service} · ${fmt_money(inv.amount)} · ${inv.status}</small></div>
    </div>`;
  }).join('');
  el.classList.add('open');
}

// ══════════════════════════════════════════
// MODALS & NOTIFICATIONS
// ══════════════════════════════════════════
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function toggleNotifPanel(e) {
  if (e) e.stopPropagation();
  const panel = document.getElementById('notifPanel');
  panel.classList.toggle('open');
  // Update time
  const t = document.getElementById('notifTime');
  if (t) t.textContent = new Date().toLocaleTimeString();
}

function clearNotifs() {
  const bc = document.getElementById('bellCount');
  if (bc) bc.style.display = 'none';
  document.getElementById('notifPanel').classList.remove('open');
  toast('✓ All notifications marked as read', 'success');
}

// ══════════════════════════════════════════
// TOAST
// ══════════════════════════════════════════
function toast(msg, type='success') {
  const icons = { success:'fa-check-circle', error:'fa-times-circle', info:'fa-info-circle', warning:'fa-exclamation-triangle' };
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<i class="fas ${icons[type]||'fa-check-circle'}"></i><span>${msg}</span>`;
  document.getElementById('toastContainer').appendChild(el);
  setTimeout(() => { el.style.opacity='0'; el.style.transform='translateY(10px)'; setTimeout(()=>el.remove(),300); }, 3200);
}

// ══════════════════════════════════════════
// UTILS
// ══════════════════════════════════════════
function getInitials(name) {
  const words = (name||'').split(/\s+/).filter(Boolean);
  if (words.length >= 2) return (words[0][0] + words[words.length-1][0]).toUpperCase();
  return (name||'?').slice(0,2).toUpperCase();
}


// ══════════════════════════════════════════
// NEW FEATURE FUNCTIONS
// ══════════════════════════════════════════
function updateClientDropdown() {
  const s=document.getElementById('f-client-select'); if(!s) return;
  s.innerHTML='<option value="">-- Quick Select Client --</option>'+STATE.clients.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');
}

function editInvoice(id) {
  const inv = STATE.invoices.find(i=>String(i.id)===String(id)); if(!inv) return;
  STATE._editingNext = true;           // tell showPage not to resetCreateForm
  STATE.editingInvoiceId = id;
  showPage('create', null);
  setTimeout(() => {
    updateClientDropdown();
    loadInvoiceIntoForm(inv);
    const s = document.getElementById('f-client-select');
    if (s) s.value = inv.client;
    livePreview();
    toast(`✏️ Editing ${inv.num||inv.invoice_number}`, 'info');
  }, 80);
}

function viewClientInvoices(id) {
  const c=STATE.clients.find(x=>x.id===id); if(!c) return;
  showPage('invoices',null);
  STATE.filteredInvoices=STATE.invoices.filter(i=>i.client===id);
  STATE.currentPage=1; applyFiltersAndRender();
  toast(`Showing invoices for ${c.name}`,'info');
}

function renderDashKpis() {
  const el = document.getElementById('dashQuickKpis');
  if (!el) return;
  const tot   = STATE.invoices.reduce((s,i) => s + i.amount, 0);
  const paid  = STATE.invoices.filter(i => i.status==='Paid').reduce((s,i) => s + i.amount, 0);
  const over  = STATE.invoices.filter(i => i.status==='Overdue').length;
  const tm    = new Date().getMonth();
  const mInv  = STATE.invoices.filter(i => i.issued && new Date(i.issued).getMonth()===tm).length;
  const rate  = tot > 0 ? Math.round(paid/tot*100) : 0;
  el.innerHTML = [
    {l:'Collection Rate', v:rate+'%',                 ic:'fa-percent',        col:'var(--teal)'},
    {l:'This Month',      v:mInv+' inv',              ic:'fa-file-invoice',   col:'var(--blue)'},
    {l:'Overdue',         v:over,                     ic:'fa-exclamation-circle', col:'var(--red)'},
    {l:'Clients',         v:STATE.clients.length,     ic:'fa-users',          col:'var(--purple)'},
    {l:'Avg Invoice',     v:STATE.invoices.length ? fmt_money(Math.round(tot/STATE.invoices.length)) : '₹0', ic:'fa-chart-line', col:'var(--amber)'},
  ].map(k => `<div style="display:flex;align-items:center;gap:9px;padding:7px 0;border-bottom:1px solid var(--border)">
    <div style="width:30px;height:30px;border-radius:7px;background:${k.col}20;display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <i class="fas ${k.ic}" style="color:${k.col};font-size:12px"></i>
    </div>
    <div><div style="font-size:10px;color:var(--muted)">${k.l}</div><div style="font-weight:700;font-size:13px">${k.v}</div></div>
  </div>`).join('');

  // ── WhatsApp card ──────────────────────────────────────────────
  const waEl = document.getElementById('dashWACard');
  if (!waEl) return;

  const wa     = STATE.settings.wa || {};
  const hasAPI = !!(wa.token && wa.pid);
  const mode   = wa.msg_mode === 'template' ? '✅ Template Mode' : '💬 Session Mode';
  const onCount = [wa.auto_inv==='1', wa.auto_paid!=='0', wa.auto_partial!=='0', wa.auto_remind!=='0', wa.auto_overdue!=='0', wa.auto_followup==='1'].filter(Boolean).length;

  const pendWA   = STATE.invoices.filter(i => i.status==='Pending' || i.status==='Overdue').length;
  const overWA   = STATE.invoices.filter(i => i.status==='Overdue').length;
  const paidTM   = STATE.invoices.filter(i => {
    const d = new Date();
    return i.status==='Paid' && i.issued &&
           new Date(i.issued).getMonth()===d.getMonth() &&
           new Date(i.issued).getFullYear()===d.getFullYear();
  }).length;
  const waClients = STATE.clients.filter(c => c.wa || c.whatsapp || c.phone).length;

  const partialInvs = STATE.invoices.filter(i => i.status === 'Partial').length;
  const splitPmts   = STATE.payments.filter(p => (p.method||'').startsWith('Split')).length;
  const miniCards = [
    {ic:'fa-paper-plane',         col:'#25D366', label:'Need Follow-up',    val:pendWA,      sub:'pending/overdue'},
    {ic:'fa-exclamation-triangle',col:'#e53935', label:'Overdue Alerts',    val:overWA,      sub:'send now'},
    {ic:'fa-check-circle',        col:'#00897B', label:'Paid This Month',   val:paidTM,      sub:'receipts sent'},
    {ic:'fa-clock',               col:'#E65100', label:'Partial Invoices',  val:partialInvs, sub:'awaiting balance'},
    {ic:'fa-code-branch',         col:'#7B1FA2', label:'Split Payments',    val:splitPmts,   sub:'recorded'},
    {ic:'fa-address-book',        col:'#1565C0', label:'WA-Ready Clients',  val:waClients,   sub:'have phone #'},
  ].map(c => `<div onclick="showPage('whatsapp',null)" style="flex:1;min-width:110px;background:${c.col}0f;border:1.5px solid ${c.col}28;border-radius:10px;padding:9px 11px;cursor:pointer;transition:.2s" onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow=''">
    <div style="display:flex;align-items:center;gap:5px;margin-bottom:3px">
      <i class="fas ${c.ic}" style="color:${c.col};font-size:11px"></i>
      <span style="font-size:9px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.4px">${c.label}</span>
    </div>
    <div style="font-size:22px;font-weight:800;color:${c.col};line-height:1">${c.val}</div>
    <div style="font-size:9px;color:var(--muted);margin-top:1px">${c.sub}</div>
  </div>`).join('');

  const toggles = [
    {key:'auto_inv',      label:'New Invoice',     icon:'📄', val: wa.auto_inv==='1'},
    {key:'auto_paid',     label:'Receipt',         icon:'✅', val: wa.auto_paid!=='0'},
    {key:'auto_partial',  label:'Partial',         icon:'💛', val: wa.auto_partial!=='0'},
    {key:'auto_remind',   label:'Due Reminder',    icon:'🔔', val: wa.auto_remind!=='0'},
    {key:'auto_overdue',  label:'Overdue Alert',   icon:'⚠️', val: wa.auto_overdue!=='0'},
    {key:'auto_followup', label:'Follow-up',       icon:'📋', val: wa.auto_followup==='1'},
  ];
  const pillsHTML = toggles.map(t => `<div onclick="showPage('whatsapp',null)" style="display:flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;cursor:pointer;flex-shrink:0;background:${t.val?'#25D36612':'var(--bg)'};border:1px solid ${t.val?'#25D36630':'var(--border)'}">
    <span>${t.icon}</span>
    <span style="font-size:11px;font-weight:600;color:${t.val?'#1a7a3c':'var(--muted)'}">${t.label}</span>
    <span style="width:5px;height:5px;border-radius:50%;flex-shrink:0;background:${t.val?'#25D366':'#ccc'}"></span>
  </div>`).join('');

  waEl.innerHTML = `
    <div style="margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap">${miniCards}</div>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:#e8f5e9;border:1.5px solid #25D366;border-radius:10px;padding:10px 14px;box-shadow:0 0 12px #25D36640,0 0 28px #25D36618;animation:waGlow 2.5s ease-in-out infinite">
      <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
        <div style="width:32px;height:32px;background:#25D366;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px">📱</div>
        <div>
          <div style="color:#1b5e20;font-size:13px;font-weight:800;line-height:1.2">WhatsApp</div>
          <div style="color:#388E3C;font-size:10px">${mode}</div>
        </div>
      </div>
      <div style="padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;flex-shrink:0;background:${hasAPI?'#25D36615':'#f5f5f5'};color:${hasAPI?'#1a7a3c':'#999'};border:1px solid ${hasAPI?'#25D36635':'#e0e0e0'}">
        ${hasAPI ? '● Connected' : '○ No API'}
      </div>
      <div style="width:1px;height:28px;background:var(--border);flex-shrink:0"></div>
      ${pillsHTML}
      <div style="margin-left:auto;display:flex;align-items:center;gap:8px;flex-shrink:0">
        <span style="font-size:11px;color:#2e7d32;font-weight:600">${onCount}/6 active</span>
        <button onclick="showPage('whatsapp',null)" style="padding:5px 12px;background:#25D36615;color:#1a7a3c;border:1px solid #25D36635;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit">
          <i class="fas fa-cog"></i> Manage
        </button>
      </div>
    </div>`;


  // ── Partial payments card ──────────────────────────────
  const partEl = document.getElementById('dashPartialCard');
  if (partEl) {
    const partials = STATE.invoices.filter(i => i.status === 'Partial');
    if (partials.length === 0) { partEl.innerHTML = ''; }
    else {
      const rows = partials.map(inv => {
        const c       = STATE.clients.find(x=>String(x.id)===String(inv.client)) || {};
        const pmts    = STATE.payments.filter(p=>p.invoice_id && String(p.invoice_id)===String(inv.id));
        const paidAmt = pmts.reduce((s,p)=>s+parseFloat(p.amount||0),0);
        const remAmt  = Math.max(0,(inv.amount||0)-paidAmt);
        const pct     = inv.amount > 0 ? Math.round(paidAmt/inv.amount*100) : 0;
        return `<div onclick="openPreviewModal('${inv.id}')" style="cursor:pointer;padding:10px 14px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:center" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
          <div style="flex:1;min-width:0">
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:3px">
              <span style="font-weight:700;font-size:13px">${inv.num||inv.invoice_number||''}</span>
              <span style="font-size:11px;padding:2px 7px;border-radius:10px;background:#FFF3E0;color:#E65100;font-weight:700">Partial</span>
            </div>
            <div style="font-size:11px;color:var(--muted)">${c.name||inv.clientName||''} · ${inv.service||inv.service_type||''}</div>
            <div style="margin-top:6px;background:var(--border);border-radius:4px;height:5px;overflow:hidden">
              <div style="height:100%;width:${pct}%;background:linear-gradient(90deg,#4CAF50,#8BC34A);border-radius:4px;transition:.4s"></div>
            </div>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <div style="font-size:11px;color:#388E3C;font-weight:700">${fmt_money(paidAmt,inv.currency||'₹')} paid</div>
            <div style="font-size:12px;color:#E65100;font-weight:800">${fmt_money(remAmt,inv.currency||'₹')} due</div>
            <div style="font-size:10px;color:var(--muted)">${pmts.length} instalment${pmts.length!==1?'s':''}</div>
          </div>
        </div>`;
      }).join('');
      partEl.innerHTML = `<div class="dash-card" style="padding:0;overflow:hidden">
        <div class="card-header" style="padding:12px 16px">
          <span class="card-title">⚡ Partial Payments</span>
          <span style="font-size:11px;color:#E65100;font-weight:700">${partials.length} invoice${partials.length!==1?'s':''} pending clearance</span>
        </div>
        ${rows}
      </div>`;
    }
  }

}


function renderDashTopClients() {
  const el=document.getElementById('dashTopClients'); if(!el) return;
  const top=STATE.clients.map(c=>({...c,rev:STATE.invoices.filter(i=>i.client===c.id&&i.status==='Paid').reduce((s,i)=>s+i.amount,0)})).sort((a,b)=>b.rev-a.rev).slice(0,5);
  const mx=top[0]?.rev||1;
  el.innerHTML=top.map(c=>`<div style="margin-bottom:9px">
    <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:2px"><span style="font-weight:600">${c.name}</span><span style="color:var(--muted);font-family:var(--mono)">${fmt_money(c.rev)}</span></div>
    <div style="height:5px;background:var(--border);border-radius:3px"><div style="height:100%;width:${Math.round(c.rev/mx*100)}%;background:${c.color};border-radius:3px"></div></div>
  </div>`).join('')||'<div style="color:var(--muted);font-size:11px;text-align:center;padding:16px">No data yet</div>';
}

function renderDashAlerts() {
  const over=STATE.invoices.filter(i=>i.status==='Overdue');
  const soon=STATE.invoices.filter(i=>{if(i.status!=='Pending'||!i.due)return false;const d=(new Date(i.due)-new Date())/(864e5);return d>=0&&d<=3;});
  const oa=document.getElementById('dashOverdueAlert'),da=document.getElementById('dashDueSoonAlert');
  if(oa){oa.style.display=over.length?'':'none';if(over.length)oa.innerHTML=`<i class="fas fa-exclamation-circle"></i> ${over.length} Overdue`;}
  if(da){da.style.display=soon.length?'':'none';if(soon.length)da.innerHTML=`<i class="fas fa-clock"></i> ${soon.length} Due Soon`;}
}

async function handleLogoUpload(input, targetId, previewId) {
  const file = input.files[0]; if (!file) return;
  if (file.size > 3*1024*1024) { toast('⚠️ Max 3MB', 'warning'); return; }
  const typeMap = {
    'f-company-logo':'logo','sc-logo':'logo',
    'f-signature':'signature','sc-sign':'signature',
    'f-client-logo':'client_logo','f-qr':'qr'
  };
  const fd = new FormData();
  fd.append('file', file);
  fd.append('type', typeMap[targetId] || 'logo');
  try {
    const res  = await fetch('api/upload.php', { method:'POST', body:fd });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch(e) { throw new Error('Upload failed: server returned HTML'); }
    if (!data.success) throw new Error(data.error || 'Upload failed');
    const el = document.getElementById(targetId);
    if (el) { el.value = data.url; el.dispatchEvent(new Event('input')); }
    if (targetId === 'sc-logo' || targetId === 'f-company-logo') {
      STATE.settings.logo = data.url;
    } else if (targetId === 'sc-sign' || targetId === 'f-signature') {
      STATE.settings.signature = data.url;
    }
    // Set the hidden input value so getFormData picks it up
    const _tgtInput = document.getElementById(targetId);
    if (_tgtInput && _tgtInput.tagName === 'INPUT') _tgtInput.value = data.url;
    if (previewId) {
      const prev = document.getElementById(previewId);
      if (prev) {
        const isSign = previewId.includes('sign');
        prev.innerHTML = `<div style="display:inline-flex;align-items:center;gap:8px;padding:6px 10px;background:${isSign?'#1a1a2e':'var(--teal-bg)'};border-radius:8px;border:1px solid var(--border)">
          <img src="${data.url}" style="height:${isSign?'36':'32'}px;max-width:120px;object-fit:contain;border-radius:4px">
          <span style="font-size:11px;color:var(--muted)">${file.name}</span>
          <button onclick="clearLogoField('${targetId}','${previewId}')" style="border:none;background:none;cursor:pointer;color:var(--red);font-size:13px"><i class="fas fa-times"></i></button>
        </div>`;
      }
    }
    toast('✅ Uploaded!', 'success');
  } catch(e) {
    // Fallback: use base64
    const reader = new FileReader();
    reader.onload = ev => {
      const el = document.getElementById(targetId);
      if (el) { el.value = ev.target.result; el.dispatchEvent(new Event('input')); }
      toast('✅ Image loaded', 'success');
    };
    reader.readAsDataURL(file);
    console.warn('Server upload failed, using base64:', e.message);
  }
}

function clearLogoField(targetId, previewId) {
  const el = document.getElementById(targetId); if (el) { el.value = ''; el.dispatchEvent(new Event('input')); }
  const prev = document.getElementById(previewId); if (prev) prev.innerHTML = '';
}

// Close dropdowns on outside click
document.addEventListener('click', e => closeAllDropdowns(e));


</script>

<!-- ══ PHP-API BRIDGE: override save functions to persist to MySQL ══ -->
<script>
// ── Apply server settings to STATE ────────────────────────────
(function() {
  if (!window.STATE || !window.SERVER) return;
  const s = SERVER.settings || {};
  STATE.settings.company   = s.company_name    || STATE.settings.company;
  STATE.settings.gst       = s.company_gst     || STATE.settings.gst;
  STATE.settings.phone     = s.company_phone   || STATE.settings.phone;
  STATE.settings.email     = s.company_email   || STATE.settings.email;
  STATE.settings.website   = s.company_website || STATE.settings.website;
  STATE.settings.prefix    = s.invoice_prefix  || STATE.settings.prefix;
  STATE.settings.upi       = s.company_upi     || STATE.settings.upi;
  STATE.settings.address   = s.company_address || STATE.settings.address;
  STATE.settings.logo      = s.company_logo    || '';
  STATE.settings.signature = s.company_sign    || '';
  STATE.settings.activeTemplate = parseInt(s.active_template) || 1;
  STATE.settings.defaultGST     = (s.default_gst !== undefined && s.default_gst !== null && s.default_gst !== '') ? parseInt(s.default_gst) : 18;
  STATE.settings.dueDays        = parseInt(s.due_days) || 15;
  // Apply WA settings from PHP-rendered SERVER.wa (guaranteed accurate)
  if (SERVER.wa) {
    STATE.settings.wa = Object.assign({}, SERVER.wa);
  }
})();

// ── API helper ──────────────────────────────────────────────────
async function api(endpoint, method, body) {
  method = method || 'GET';
  const opts = { method, headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
  if (body) opts.body = JSON.stringify(body);
  const res = await fetch(endpoint, opts);
  const text = await res.text();
  let data;
  try { data = JSON.parse(text); }
  catch(e) {
    console.error('API response not JSON from', endpoint, '\nResponse:', text.substring(0,300));
    throw new Error('Server returned non-JSON response. Check PHP error logs.');
  }
  if (res.status === 401) { window.location.href = '/auth/login.php'; throw new Error('Not authenticated'); }
  if (!res.ok) throw new Error(data.error || 'API error ' + res.status);
  return data;
}

// ── Load all data from API on page load ────────────────────────
async function loadAllData() {
  try {
    const [inv, cls, prd, pmt, cfg] = await Promise.all([
      api('api/invoices.php'),
      api('api/clients.php'),
      api('api/products.php'),
      api('api/payments.php'),
      api('api/settings.php'),
    ]);
    STATE.invoices  = Array.isArray(inv.data)  ? inv.data  : [];
    STATE.clients   = Array.isArray(cls.data)  ? cls.data  : [];
    STATE.products  = Array.isArray(prd.data)  ? prd.data  : [];
    STATE.payments  = Array.isArray(pmt.data)  ? pmt.data  : [];
    STATE.filteredInvoices = [...STATE.invoices];
    // Merge latest server settings into STATE.settings
    if (cfg.data) {
      const s = cfg.data;
      // Parse WA settings into nested object
      STATE.settings.wa = {
        token:         s.wa_token        || '',
        pid:           s.wa_pid          || '',
        bid:           s.wa_bid          || '',
        test_phone:    s.wa_test_phone   || '',
        remind_days:   s.wa_remind_days  || '3',
        max_followup:  s.wa_max_followup || '3',
        tpl_inv:       s.wa_tpl_inv      || '',
        tpl_paid:      s.wa_tpl_paid     || '',
        tpl_partial:   s.wa_tpl_partial  || '',
        tpl_remind:    s.wa_tpl_remind   || '',
        tpl_overdue:   s.wa_tpl_overdue  || '',
        tpl_followup:  s.wa_tpl_followup || '',
        tpl_festival:  s.wa_tpl_festival || '',
        auto_inv:      s.wa_auto_inv      !== undefined ? s.wa_auto_inv      : '0',
        auto_paid:     s.wa_auto_paid     !== undefined ? s.wa_auto_paid     : '1',
        auto_partial:  s.wa_auto_partial  !== undefined ? s.wa_auto_partial  : '1',
        auto_remind:   s.wa_auto_remind   !== undefined ? s.wa_auto_remind   : '1',
        auto_overdue:  s.wa_auto_overdue  !== undefined ? s.wa_auto_overdue  : '1',
        auto_followup: s.wa_auto_followup !== undefined ? s.wa_auto_followup : '0',
        msg_mode:      s.wa_msg_mode || 'session',
        // Template names
        tpl_name_invoice:  s.wa_tpl_name_invoice  || '',
        tpl_lang_invoice:  s.wa_tpl_lang_invoice  || 'en',
        tpl_name_reminder: s.wa_tpl_name_reminder || '',
        tpl_lang_reminder: s.wa_tpl_lang_reminder || 'en',
        tpl_name_overdue:  s.wa_tpl_name_overdue  || '',
        tpl_lang_overdue:  s.wa_tpl_lang_overdue  || 'en',
        tpl_name_paid:     s.wa_tpl_name_paid     || '',
        tpl_lang_paid:     s.wa_tpl_lang_paid     || 'en',
        tpl_name_followup: s.wa_tpl_name_followup || '',
        tpl_lang_followup: s.wa_tpl_lang_followup || 'en',
        tpl_name_festival: s.wa_tpl_name_festival || '',
        tpl_lang_festival: s.wa_tpl_lang_festival || 'en',
      };
      // Parse TPL_CUSTOM settings
      if (window.TPL_CUSTOM) {
        if (s.tpl_color1)        TPL_CUSTOM.color1         = s.tpl_color1;
        if (s.tpl_color2)        TPL_CUSTOM.color2         = s.tpl_color2;
        if (s.tpl_font)          TPL_CUSTOM.font           = s.tpl_font;
        if (s.tpl_name_size)     TPL_CUSTOM.companyNameSize= String(parseInt(s.tpl_name_size)||28);
        if (s.tpl_name_color)    TPL_CUSTOM.companyNameColor=s.tpl_name_color;
        if (s.tpl_name_weight)   TPL_CUSTOM.companyNameWeight=s.tpl_name_weight;
        if (s.tpl_name_style)    TPL_CUSTOM.companyNameStyle=s.tpl_name_style||'normal';
        if (s.tpl_logo_position) TPL_CUSTOM.logoPosition   = s.tpl_logo_position;
        if (s.tpl_footer_text)   TPL_CUSTOM.footerText     = s.tpl_footer_text;
        if (s.tpl_tagline)       TPL_CUSTOM.tagline        = s.tpl_tagline;
        if (s.tpl_watermark_text)TPL_CUSTOM.watermarkText  = s.tpl_watermark_text;
        // Sync UI controls to restored values (done after DOM ready)
        setTimeout(() => {
          const sync = (id, val) => { const e=document.getElementById(id); if(e) e.value=val; };
          sync('tpl-color1',      TPL_CUSTOM.color1);
          sync('tpl-color1-hex',  TPL_CUSTOM.color1);
          sync('tpl-color2',      TPL_CUSTOM.color2);
          sync('tpl-color2-hex',  TPL_CUSTOM.color2);
          sync('tpl-font',        TPL_CUSTOM.font);
          sync('tpl-name-size',   TPL_CUSTOM.companyNameSize);
          sync('tpl-name-color',  TPL_CUSTOM.companyNameColor);
          sync('tpl-name-style',  TPL_CUSTOM.companyNameWeight);
          sync('tpl-header-style',TPL_CUSTOM.headerStyle);
          sync('tpl-table-style', TPL_CUSTOM.tableStyle);
          sync('tpl-footer-text', TPL_CUSTOM.footerText);
          sync('tpl-tagline',     TPL_CUSTOM.tagline);
          sync('tpl-watermark-text', TPL_CUSTOM.watermarkText);
          const sv = document.getElementById('tpl-name-size-val');
          if (sv) sv.textContent = TPL_CUSTOM.companyNameSize + 'px';
        }, 200);
      }
      STATE.settings.company   = s.company_name    || STATE.settings.company;
      STATE.settings.gst       = s.company_gst     || STATE.settings.gst;
      STATE.settings.phone     = s.company_phone   || STATE.settings.phone;
      STATE.settings.email     = s.company_email   || STATE.settings.email;
      STATE.settings.website   = s.company_website || STATE.settings.website;
      STATE.settings.prefix    = s.invoice_prefix  || STATE.settings.prefix;
      STATE.settings.upi       = s.company_upi     || STATE.settings.upi;
      STATE.settings.address   = s.company_address || STATE.settings.address;
      STATE.settings.logo      = s.company_logo    || '';
      STATE.settings.signature = s.company_sign    || '';
      STATE.settings.activeTemplate = parseInt(s.active_template)||1;
      STATE.settings.defaultGST     = (s.default_gst !== undefined && s.default_gst !== '') ? parseInt(s.default_gst) : 18;
      STATE.settings.dueDays        = parseInt(s.due_days)||15;
      STATE.settings.defaultBank    = s.default_bank || '';
      STATE.settings.defaultTnC     = s.default_tnc  || '';
      // Load categories from settings JSON if saved
      if (s.product_categories) {
        try { const cats = JSON.parse(s.product_categories); if (Array.isArray(cats) && cats.length) STATE.categories = cats; } catch(e) {}
      }
      // Load item types from settings JSON if saved
      if (s.item_types) {
        try { const iTypes = JSON.parse(s.item_types); if (Array.isArray(iTypes) && iTypes.length) STATE.itemTypes = iTypes; } catch(e) {}
      }
      // Restore TPL_CUSTOM from saved settings
      if (s.tpl_color1)      TPL_CUSTOM.color1          = s.tpl_color1;
      if (s.tpl_color2)      TPL_CUSTOM.color2          = s.tpl_color2;
      if (s.tpl_font)        TPL_CUSTOM.font            = s.tpl_font;
      if (s.tpl_name_size)   TPL_CUSTOM.companyNameSize = s.tpl_name_size;
      if (s.tpl_name_color)  TPL_CUSTOM.companyNameColor= s.tpl_name_color;
      if (s.tpl_name_weight) TPL_CUSTOM.companyNameWeight=s.tpl_name_weight;
      if (s.tpl_name_style)  TPL_CUSTOM.companyNameStyle=s.tpl_name_style;
      if (s.tpl_footer_text) TPL_CUSTOM.footerText      = s.tpl_footer_text;
      if (s.tpl_tagline)     TPL_CUSTOM.tagline         = s.tpl_tagline;
      if (s.tpl_watermark_text) TPL_CUSTOM.watermarkText= s.tpl_watermark_text;
      if (s.tpl_header_style) TPL_CUSTOM.headerStyle    = s.tpl_header_style;
      if (s.tpl_table_style)  TPL_CUSTOM.tableStyle     = s.tpl_table_style;
    }
    console.log('Loaded:', STATE.invoices.length,'invoices,', STATE.clients.length,'clients');
  } catch(e) {
    console.error('loadAllData failed:', e.message);
    toast('⚠️ Could not load data: ' + e.message, 'warning');
  }
}

// ── Override: saveInvoice ───────────────────────────────────────

// window.saveInvoice: now handled by the function declaration above
;

// ── Override: confirmPaid ───────────────────────────────────────
// confirmPaid: now handled by direct function
// ── Override: confirmDelete ─────────────────────────────────────
// confirmDelete: now handled by direct function
// ── Override: saveNewClient ─────────────────────────────────────

// window.saveNewClient: now handled by the function declaration above
;

// ── Override: saveCompanySettings ──────────────────────────────

// window.saveCompanySettings: now handled by the function declaration above
;

// ── Override: saveEditProd ──────────────────────────────────────

// window.saveEditProd: now handled by the function declaration above
;

// ── Override: saveNewProduct ────────────────────────────────────

// window.saveNewProduct: now handled by the function declaration above
;

// ── Override: deleteProduct ─────────────────────────────────────

// window.deleteProduct: now handled by the function declaration above
;

// ── Override: handleLogoUpload → server upload ──────────────────

// window.handleLogoUpload: now handled by the function declaration above
;

// ── Bootstrap: load DB data then init app ──────────────────────
document.addEventListener('DOMContentLoaded', function() {
  loadAllData().then(function() {
    // Run the full app initialisation
    try {
      setTodayDates();
      addItem();
      updateClientDropdown();
      renderDashboard();
      renderInvoicesTable();
      renderClients();
      renderProducts();
      renderPayments();
      renderTemplatesGrid();
      populateTemplateForm();
      renderNotifications();
      populateSettingsForm();
      populateWAPage();
      renderFestivalCampaigns();
      resetCreateForm();
      STATE.filteredInvoices = [...STATE.invoices];
      document.getElementById('badge-invoices').textContent = STATE.invoices.length;
      setTimeout(livePreview, 100);
      document.addEventListener('click', closeAllDropdowns);
    } catch(initErr) {
      console.error('App init error:', initErr);
    }
  });
});
// ── Populate notification bell from live data ──────────────────
function renderNotifications() {
  const today  = new Date();
  const items  = [];

  // Overdue invoices
  STATE.invoices.filter(i => i.status === 'Overdue').slice(0,3).forEach(inv => {
    const c = STATE.clients.find(x => x.id === inv.client) || {};
    items.push({ type:'warn', text:`<b>${c.name || inv.clientName || inv.client}</b> invoice ${inv.num} is overdue` });
  });

  // Due in next 3 days
  STATE.invoices.filter(i => {
    if (i.status !== 'Pending' || !i.due) return false;
    const diff = (new Date(i.due) - today) / 86400000;
    return diff >= 0 && diff <= 3;
  }).slice(0,3).forEach(inv => {
    const c = STATE.clients.find(x => x.id === inv.client) || {};
    const dueDate = new Date(inv.due).toLocaleDateString('en-IN',{day:'2-digit',month:'short'});
    items.push({ type:'info', text:`<b>${c.name || inv.clientName || inv.client}</b> — ${inv.num} due ${dueDate}` });
  });

  // Recent payments (last 2)
  STATE.payments.slice(0,2).forEach(p => {
    items.push({ type:'info', text:`Payment received from <b>${p.client}</b> — ${fmt_money(p.amount)}` });
  });

  const el = document.getElementById('notifItems');
  if (el) {
    if (!items.length) {
      el.innerHTML = '<div style="padding:14px 16px;color:var(--muted);font-size:13px;text-align:center">No new notifications</div>';
    } else {
      el.innerHTML = items.map(n =>
        `<div class="np-item ${n.type==='warn'?'np-warn':'np-info'}">
          <i class="fas ${n.type==='warn'?'fa-exclamation-circle':'fa-info-circle'}"></i>
          <div>${n.text}</div>
        </div>`
      ).join('');
    }
  }

  // Update bell count
  const bell = document.getElementById('bellCount');
  if (bell) {
    const count = items.length;
    bell.textContent = count;
    bell.style.display = count > 0 ? 'flex' : 'none';
  }
}


// ── saveInvoiceDefaults ─────────────────────────────────────────
window.saveInvoiceDefaults = async function() {
  const payload = {
    default_gst:     document.getElementById('sd-gst')?.value ?? '0',
    due_days:        document.getElementById('sd-due')?.value     || '15',
    active_template: document.getElementById('sd-tpl')?.value     || '1',
    invoice_prefix:  document.getElementById('sd-prefix')?.value  || STATE.settings.prefix || 'OT-',
    default_currency:document.getElementById('sd-currency')?.value|| '₹',
    default_bank:    document.getElementById('sd-bank')?.value    || '',
    default_tnc:     document.getElementById('sd-tnc')?.value     || '',
  };
  // Also update STATE
  STATE.settings.defaultGST     = parseInt(payload.default_gst ?? '0');
  STATE.settings.dueDays        = parseInt(payload.due_days);
  STATE.settings.activeTemplate = parseInt(payload.active_template);
  if (payload.invoice_prefix) STATE.settings.prefix = payload.invoice_prefix;
  if (payload.default_tnc !== undefined) STATE.settings.defaultTnC = payload.default_tnc;
  try {
    await api('api/settings.php', 'POST', payload);
    toast('✅ Invoice defaults saved!', 'success');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
};

// ── Category Management ──────────────────────────────────────────
function getCatColor(name) {
  const cat = STATE.categories.find(c => c.name === name);
  return cat ? cat.color : '#757575';
}
function getCatTextColor(hex) {
  // Return black or white based on background luminance
  const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
  return (0.299*r + 0.587*g + 0.114*b) > 160 ? '#222' : '#fff';
}
function renderCategoryList() {
  const el = document.getElementById('cat-list'); if (!el) return;
  if (!STATE.categories.length) { el.innerHTML = '<span style="color:var(--muted);font-size:12px">No categories yet.</span>'; return; }
  el.innerHTML = STATE.categories.map((c,i) => {
    const tc = getCatTextColor(c.color);
    return `<div style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px 5px 12px;border-radius:20px;background:${c.color};color:${tc};font-size:12px;font-weight:700;box-shadow:0 1px 4px ${c.color}60">
      ${c.name}
      <button onclick="deleteCategory(${i})" style="background:none;border:none;cursor:pointer;color:${tc};opacity:.7;font-size:13px;line-height:1;padding:0 0 0 2px" title="Remove">×</button>
    </div>`;
  }).join('');
}
async function addCategory() {
  const nameEl = document.getElementById('cat-new-name');
  const colorEl = document.getElementById('cat-new-color');
  const name = nameEl?.value.trim();
  if (!name) { toast('⚠️ Enter a category name', 'warning'); return; }
  if (STATE.categories.find(c => c.name.toLowerCase() === name.toLowerCase())) { toast('⚠️ Category already exists', 'warning'); return; }
  STATE.categories.push({ name, color: colorEl?.value || '#00897B' });
  nameEl.value = '';
  renderCategoryList();
  updateProductCatDropdowns();
  await saveCategories();
  toast('✅ Category added!', 'success');
}
async function deleteCategory(idx) {
  STATE.categories.splice(idx, 1);
  renderCategoryList();
  updateProductCatDropdowns();
  await saveCategories();
  toast('🗑️ Category removed', 'info');
}
async function saveCategories() {
  try { await api('api/settings.php','POST',{ product_categories: JSON.stringify(STATE.categories) }); } catch(e) { console.warn('Cat save err',e); }
}
function updateProductCatDropdowns() {
  // Update all category dropdowns in the products page / filter
  const opts = STATE.categories.map(c => `<option value="${c.name}">${c.name}</option>`).join('');
  document.querySelectorAll('.cat-select').forEach(el => {
    const cur = el.value;
    el.innerHTML = opts;
    el.value = cur;
  });
  const filter = document.getElementById('productCatFilter');
  if (filter) filter.innerHTML = `<option value="">All Categories</option>${opts}`;
}

// ── Item Type Management ─────────────────────────────────────────
function renderItemTypeList() {
  const el = document.getElementById('item-type-list'); if (!el) return;
  const types = STATE.itemTypes || [];
  if (!types.length) { el.innerHTML = '<span style="color:var(--muted);font-size:12px">No types yet.</span>'; return; }
  el.innerHTML = types.map((t,i) => {
    const bg = t.color || '#757575';
    const r = parseInt(bg.slice(1,3),16)||0, g = parseInt(bg.slice(3,5),16)||0, b = parseInt(bg.slice(5,7),16)||0;
    const tc = (0.299*r + 0.587*g + 0.114*b) > 160 ? '#222' : '#fff';
    const isDefault = ['Service','Product','Labour','Other'].includes(t.name);
    return `<div style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px 5px 12px;border-radius:20px;background:${bg};color:${tc};font-size:12px;font-weight:700;box-shadow:0 1px 4px ${bg}60">
      ${t.name}${isDefault ? ' <span style="font-size:9px;opacity:.7">(default)</span>' : ''}
      ${!isDefault ? `<button onclick="deleteItemType(${i})" style="background:none;border:none;cursor:pointer;color:${tc};opacity:.7;font-size:13px;line-height:1;padding:0 0 0 2px" title="Remove">×</button>` : ''}
    </div>`;
  }).join('');
}
async function addItemType() {
  const nameEl  = document.getElementById('itype-new-name');
  const colorEl = document.getElementById('itype-new-color');
  const name = nameEl?.value.trim();
  if (!name) { toast('⚠️ Enter a type name', 'warning'); return; }
  if ((STATE.itemTypes||[]).find(t => t.name.toLowerCase() === name.toLowerCase())) { toast('⚠️ Type already exists', 'warning'); return; }
  if (!STATE.itemTypes) STATE.itemTypes = [];
  STATE.itemTypes.push({ name, color: colorEl?.value || '#1976D2' });
  if (nameEl) nameEl.value = '';
  renderItemTypeList();
  await saveItemTypes();
  renderFormItems(); // refresh open invoice form if any
  toast('✅ Item type added!', 'success');
}
async function deleteItemType(idx) {
  const t = STATE.itemTypes[idx];
  if (['Service','Product','Labour','Other'].includes(t?.name)) { toast('⚠️ Default types cannot be deleted', 'warning'); return; }
  STATE.itemTypes.splice(idx, 1);
  renderItemTypeList();
  await saveItemTypes();
  renderFormItems();
  toast('🗑️ Item type removed', 'info');
}
async function saveItemTypes() {
  try { await api('api/settings.php','POST',{ item_types: JSON.stringify(STATE.itemTypes) }); } catch(e) { console.warn('ItemType save err',e); }
}

// ── populateSettingsForm: load saved settings into the form fields ──
function populateSettingsForm() {
  const s = STATE.settings;
  const set = (id, val) => { const e=document.getElementById(id); if(e && val) e.value=val; };
  set('sc-name',    s.company);
  set('sc-gst',     s.gst);
  set('sc-phone',   s.phone);
  set('sc-email',   s.email);
  renderCategoryList();
  renderItemTypeList();
  set('sc-prefix',  s.prefix);
  set('sc-upi',     s.upi);
  set('sc-addr',    s.address);
  set('sc-logo',    s.logo);
  set('sc-sign',    s.signature);
  set('sc-bank',    s.defaultBank || STATE.settings.defaultBank || '');
  // Invoice defaults
  set('sd-prefix',  s.prefix);
  set('sd-due',     s.dueDays);
  set('sd-bank',    s.defaultBank || '');
  set('sd-tnc',     s.defaultTnC  || '');
  // Restore template customization
  const setV = (id,v) => { const e=document.getElementById(id); if(e&&v!==undefined&&v!=='')e.value=v; };
  if (TPL_CUSTOM.color1)          { setV('tpl-color1',TPL_CUSTOM.color1); setV('tpl-color1-hex',TPL_CUSTOM.color1); }
  if (TPL_CUSTOM.color2)          { setV('tpl-color2',TPL_CUSTOM.color2); setV('tpl-color2-hex',TPL_CUSTOM.color2); }
  setV('tpl-font',         TPL_CUSTOM.font);
  setV('tpl-watermark-text',TPL_CUSTOM.watermarkText||'PAID');
  setV('tpl-footer-text',  TPL_CUSTOM.footerText);
  setV('tpl-tagline',      TPL_CUSTOM.tagline);
  setV('tpl-name-size',    TPL_CUSTOM.companyNameSize||'28');
  if (TPL_CUSTOM.companyNameColor) { setV('tpl-name-color',TPL_CUSTOM.companyNameColor); setV('tpl-name-color-hex',TPL_CUSTOM.companyNameColor); }
  setV('tpl-name-weight',  TPL_CUSTOM.companyNameWeight||'800');
  setV('tpl-name-style',   TPL_CUSTOM.companyNameStyle||'normal');
  setV('tpl-logo-pos',     TPL_CUSTOM.logoPosition||'left');
  // Show logo preview if set
  // Show logo preview
  if (s.logo || STATE.settings.logo) {
    const logoUrl = s.logo || STATE.settings.logo;
    const prev = document.getElementById('sc-logo-preview');
    if (prev) prev.innerHTML = `<div style="display:inline-flex;align-items:center;gap:8px;padding:6px 10px;background:var(--teal-bg);border-radius:8px;border:1px solid var(--border);margin-top:4px"><img src="${logoUrl}" style="height:32px;max-width:120px;object-fit:contain;border-radius:4px"><span style="font-size:11px;color:var(--muted)">Current logo</span></div>`;
    const scLogoEl = document.getElementById('sc-logo'); if(scLogoEl&&!scLogoEl.value) scLogoEl.value = logoUrl;
  }
  // Show sign preview
  if (s.signature || STATE.settings.signature) {
    const signUrl = s.signature || STATE.settings.signature;
    const sprev = document.getElementById('sc-sign-preview');
    if (sprev) sprev.innerHTML = `<div style="display:inline-flex;align-items:center;gap:8px;padding:6px 10px;background:#1a1a2e;border-radius:8px;border:1px solid var(--border);margin-top:4px"><img src="${signUrl}" style="height:36px;max-width:120px;object-fit:contain;border-radius:4px"><span style="font-size:11px;color:#aaa">Current signature</span></div>`;
    const scSignEl = document.getElementById('sc-sign'); if(scSignEl&&!scSignEl.value) scSignEl.value = signUrl;
  }
  // Set select values
  ['sd-gst','sd-tpl','sd-currency'].forEach(id => {
    const e=document.getElementById(id);
    if (!e) return;
    if (id==='sd-gst')      e.value = String(s.defaultGST ?? 18);
    if (id==='sd-tpl')      e.value = String(s.activeTemplate||1);
    if (id==='sd-currency') e.value = '₹';
  });
}


// ── Admin Profile ──────────────────────────────────────────────
window.uploadProfilePhoto = async function(input) {
  const file = input.files[0]; if (!file) return;
  if (file.size > 2*1024*1024) { toast('⚠️ Max 2MB', 'warning'); return; }
  const fd = new FormData(); fd.append('file', file); fd.append('type', 'avatar');
  try {
    const res  = await fetch('api/upload.php', { method:'POST', body:fd });
    const data = await res.json();
    if (!data.success) throw new Error(data.error);
    const prev = document.getElementById('profile-avatar-preview');
    if (prev) prev.innerHTML = `<img src="${data.url}" style="width:100%;height:100%;object-fit:cover">`;
    SERVER.user = SERVER.user || {};
    SERVER.user._avatarUrl = data.url;
    toast('✅ Photo uploaded!', 'success');
  } catch(e) {
    // fallback base64
    const reader = new FileReader();
    reader.onload = ev => {
      const prev = document.getElementById('profile-avatar-preview');
      if (prev) prev.innerHTML = `<img src="${ev.target.result}" style="width:100%;height:100%;object-fit:cover">`;
      SERVER.user._avatarUrl = ev.target.result;
    };
    reader.readAsDataURL(file);
  }
};

window.saveProfile = async function() {
  const name  = document.getElementById('profile-name')?.value?.trim();
  const email = document.getElementById('profile-email')?.value?.trim();
  const pass  = document.getElementById('profile-pass')?.value  || '';
  const pass2 = document.getElementById('profile-pass2')?.value || '';
  if (!name || !email) { toast('⚠️ Name and email required', 'warning'); return; }
  if (pass && pass.length < 6) { toast('⚠️ Password min 6 characters', 'warning'); return; }
  if (pass && pass !== pass2)  { toast('⚠️ Passwords do not match', 'warning'); return; }
  const payload = { name, email, password: pass || null, avatar: SERVER.user?._avatarUrl || null };
  try {
    const res = await api('api/profile.php', 'POST', payload);
    // Update sidebar display
    const nameEl = document.querySelector('.user-name'); if (nameEl) nameEl.textContent = name;
    const avaEl  = document.querySelector('.user-avatar');
    if (avaEl && payload.avatar) avaEl.innerHTML = `<img src="${payload.avatar}" style="width:100%;height:100%;object-fit:cover;border-radius:8px">`;
    if (pass) { document.getElementById('profile-pass').value = ''; document.getElementById('profile-pass2').value = ''; }
    toast('✅ Profile updated!', 'success');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
};


// ── WhatsApp Functions ─────────────────────────────────────────;


;


// ══════════════════════════════════════════════════════════════
//  WHATSAPP — Clean unified implementation (snake_case throughout)
// ══════════════════════════════════════════════════════════════

// ── Populate WA page from STATE.settings.wa ──────────────────
function populateWAPage() {
  populateWAClientDropdown();
  const wa = STATE.settings.wa || {};

  const setV = (id, v) => {
    const e = document.getElementById(id);
    if (e && v !== undefined && v !== null && v !== '') e.value = v;
  };
  const setTog = (id, on) => {
    const e = document.getElementById(id);
    if (!e) return;
    if (on) e.classList.add('on'); else e.classList.remove('on');
  };

  // Credentials
  setV('wa-token',       wa.token || '');
  setV('wa-pid',         wa.pid   || '');
  setV('wa-bid',         wa.bid   || '');
  setV('wa-test-phone',  wa.test_phone || '');
  setV('wa-remind-days', wa.remind_days || '3');
  setV('wa-max-followup',wa.max_followup || '3');

  // Templates
  setV('wa-tpl-inv',     wa.tpl_inv     || getDefaultWATpl('inv'));
  setV('wa-tpl-paid',    wa.tpl_paid    || getDefaultWATpl('paid'));
  setV('wa-tpl-remind',  wa.tpl_remind  || getDefaultWATpl('remind'));
  setV('wa-tpl-overdue', wa.tpl_overdue || getDefaultWATpl('overdue'));
  setV('wa-tpl-followup',wa.tpl_followup|| getDefaultWATpl('followup'));
  setV('wa-tpl-partial', wa.tpl_partial || getDefaultWATpl('partial_receipt'));
  setV('wa-tpl-festival',wa.tpl_festival|| getDefaultWATpl('festival'));

  // Toggles
  setTog('twa1', wa.auto_inv     === '1');
  setTog('twa2', wa.auto_paid    !== '0');
  setTog('twa6', wa.auto_partial !== '0');
  setTog('twa3', wa.auto_remind  !== '0');
  setTog('twa4', wa.auto_overdue !== '0');
  setTog('twa5', wa.auto_followup === '1');

  // Template mode + names
  const mode  = wa.msg_mode || 'session';
  const radio = document.querySelector('input[name="wa-msg-mode"][value="' + mode + '"]');
  if (radio) { radio.checked = true; setWAMode(mode); }
  const tpls  = ['invoice','reminder','overdue','paid','followup','festival'];
  tpls.forEach(t => {
    const nEl = document.getElementById('tpl-name-' + t);
    const lEl = document.getElementById('tpl-lang-' + t);
    if (nEl) nEl.value = wa['tpl_name_' + t] || '';
    if (lEl) lEl.value = wa['tpl_lang_' + t] || 'en';
  });
}



function populateWADropdown() { populateWAClientDropdown(); }
function populateWAClientDropdown() {
  const sel = document.getElementById('wa-manual-client');
  if (!sel) return;
  sel.innerHTML = '<option value="">-- Select Client --</option>' +
    STATE.clients.map(c => {
      const ph = c.wa || c.whatsapp || c.phone || '';
      return `<option value="${c.id}">${c.name}${ph ? ' (' + ph + ')' : ''}</option>`;
    }).join('');
}

// ── Default rich message templates ───────────────────────────
function getDefaultWATpl(type) {
  const d = {
    inv: `Hi {client_name}! 👋

*Invoice #{invoice_no}* from {company_name}

📋 *Details:*
• Service: {service}
• Issue Date: {issue_date}
• Due Date: {due_date}
• Amount: *{currency}{amount}*

{item_list}

💳 *Pay via UPI:* {upi}
🏦 {bank_details}

Thank you for choosing {company_name}!
📞 {company_phone} | ✉ {company_email}`,

    paid: `Hi {client_name}! ✅

Payment received for *Invoice #{invoice_no}*

💰 Amount: *{currency}{amount}*
📅 Date: {issue_date}
📋 Service: {service}

Thank you for the prompt payment! 🙏
We look forward to serving you again.

— {company_name}
📞 {company_phone}`,

    remind: `Hi {client_name}! 🔔 *Payment Reminder*

*Invoice #{invoice_no}* is due on *{due_date}*

📋 Service: {service}
💰 Amount Due: *{currency}{amount}*

Please arrange payment at your earliest convenience.

💳 *UPI:* {upi}
🏦 {bank_details}

{company_name} | 📞 {company_phone}`,

    overdue: `Hi {client_name}! ⚠️ *Overdue Notice*

*Invoice #{invoice_no}* was due on *{due_date}*
Overdue by: *{days_overdue} days*

📋 Service: {service}
💰 Outstanding: *{currency}{amount}*

Please clear this payment immediately to avoid any inconvenience.

💳 *UPI:* {upi}
🏦 {bank_details}

{company_name} | 📞 {company_phone}`,

    followup: `Hi {client_name},

This is a follow-up regarding *Invoice #{invoice_no}* ({currency}{amount}).

⚠️ Still overdue by *{days_overdue} days*
📋 Service: {service}

Kindly process the payment immediately or contact us to discuss.

💳 *UPI:* {upi}

{company_name} | 📞 {company_phone} | ✉ {company_email}`,

    partial_receipt: `Hi {client_name}! 💚

*Partial Payment Received* for Invoice #{invoice_no}

✅ Paid: *{paid_amount}*
⏳ Remaining: *{remaining_amount}*
📋 Invoice Total: {amount}
📅 Date: {issue_date}
📋 Service: {service}

Please clear the remaining balance by *{due_date}*.
💳 UPI: {upi}
🏦 {bank_details}

Thank you! — {company_name}
📞 {company_phone}`,

    split_receipt: `Hi {client_name}! ⚡

*Split Payment Recorded* for Invoice #{invoice_no}

💰 Amount: *{currency}{amount}*
📋 Payment split across multiple methods
📅 Date: {issue_date}
📋 Service: {service}

Your account is now clear. Thank you! 🙏
— {company_name} | 📞 {company_phone}`,

    festival: `Hi {client_name}! 🎉

Warm *festival greetings* from the entire team at *{company_name}*!

May this occasion bring you joy, prosperity, and success. 🌟

Thank you for your continued trust and support. We are grateful for your partnership. 🙏

*{company_name}*
📞 {company_phone} | ✉ {company_email}`
  };
  return d[type] || '';
}

// ── Save WA settings to DB + STATE ───────────────────────────

// saveWASettings: defined above

// ── Send via Meta WhatsApp Business API ──────────────────────
async function sendWABusinessMsg(toPhone, message, token, pid, tplOpts) {
  // tplOpts = { name, lang, params[] } for approved templates
  // If no tplOpts, sends as free-form text (requires active 24h session)
  const body = tplOpts
    ? { token, pid, to: toPhone, type: 'template',
        template_name: tplOpts.name, template_lang: tplOpts.lang || 'en',
        template_params: tplOpts.params || [], message }
    : { token, pid, to: toPhone, type: 'text', message };

  const res  = await fetch('api/wa_send.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  const text = await res.text();
  let data;
  try { data = JSON.parse(text); }
  catch(e) { throw new Error('Server error: ' + text.substring(0,200)); }
  if (!res.ok || data.error) throw new Error(data.error || 'API error ' + res.status);
  return data;
}

// Build template params from invoice data
function buildWATplParams(tplName, inv, client, settings) {
  const sc  = settings || STATE.settings;
  const c   = client  || {};
  const dueDate   = inv.due || inv.due_date || '';
  const dueFmt    = dueDate ? new Date(dueDate).toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'}) : '';
  const issueFmt  = (inv.issued||inv.issued_date) ? new Date(inv.issued||inv.issued_date).toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'}) : '';
  const amount    = String(parseFloat(inv.amount||inv.grand_total)||0);
  const daysOver  = dueDate ? String(Math.max(0,Math.floor((new Date()-new Date(dueDate))/86400000))) : '0';

  // Common params used across most templates
  const common = {
    client_name:    c.name || inv.client_name || 'Valued Client',
    invoice_no:     inv.num || inv.invoice_number || '',
    amount,
    currency:       inv.currency || '₹',
    due_date:       dueFmt,
    issue_date:     issueFmt,
    service:        inv.service || inv.service_type || '',
    company_name:   sc.company || '',
    upi:            sc.upi || '',
    company_phone:  sc.phone || '',
    days_overdue:   daysOver,
  };

  // Map template name to ordered params list
  const maps = {
    invoice_created:   ['client_name','invoice_no','amount','due_date','upi','company_name'],
    payment_reminder:  ['client_name','invoice_no','amount','due_date','upi','company_name'],
    payment_overdue:   ['client_name','invoice_no','amount','days_overdue','upi','company_name'],
    payment_received:  ['client_name','invoice_no','amount','issue_date','company_name'],
    invoice_followup:  ['client_name','invoice_no','amount','days_overdue','upi','company_phone'],
    festival_greeting: ['client_name','company_name','company_phone'],
  };

  const paramKeys = maps[tplName] || Object.keys(common);
  return paramKeys.map(k => common[k] || '');
}

// ── Send WA (API first, wa.me fallback) ──────────────────────
async function sendWA(phone, message, tplName, inv, client) {
  const wa    = STATE.settings.wa || {};
  const token = wa.token || '';
  const pid   = wa.pid   || '';
  const clean = String(phone).replace(/\D/g, '');
  if (!clean) throw new Error('No phone number');
  if (token && pid) {
    // Use approved template if name configured AND mode is template
    const useTemplate = wa.msg_mode === 'template' && tplName && wa['tpl_name_' + tplName];
    const tplOpts = useTemplate ? {
      name:   wa['tpl_name_' + tplName],
      lang:   wa['tpl_lang_' + tplName] || 'en',
      params: inv ? buildWATplParams(wa['tpl_name_' + tplName], inv, client, STATE.settings) : [],
    } : null;
    return await sendWABusinessMsg(clean, message, token, pid, tplOpts);
  }
  // Fallback: open wa.me
  window.open('https://wa.me/' + (clean.length===10?'91'+clean:clean) + '?text=' + encodeURIComponent(message), '_blank');
  return null;
}

// ── Test Message ──────────────────────────────────────────────
function testWA() {
  const wa    = STATE.settings.wa || {};
  const token = document.getElementById('wa-token')?.value || wa.token || '';
  const pid   = document.getElementById('wa-pid')?.value   || wa.pid   || '';
  const phone = (document.getElementById('wa-test-phone')?.value || '').replace(/\D/g, '');
  if (!phone) { toast('⚠️ Enter test phone number first', 'warning'); return; }

  const sampleInv = {
    num: 'TEST-001', invoice_number: 'TEST-001',
    issued: new Date().toISOString().split('T')[0],
    due:    new Date(Date.now()+15*86400000).toISOString().split('T')[0],
    amount: 15000, currency: '₹', service: 'Web Development',
    service_type: 'Web Development', status: 'Pending',
    items: [{desc:'Website Design', qty:1, rate:10000},{desc:'SEO Setup', qty:1, rate:5000}]
  };
  const tplRaw = document.getElementById('wa-tpl-inv')?.value || wa.tpl_inv || getDefaultWATpl('inv');
  const msg    = formatWAMsg(tplRaw, sampleInv, {name: 'Test Client'}, STATE.settings);

  if (token && pid) {
    sendWABusinessMsg(phone, msg, token, pid)
      .then(()  => toast('✅ Test message sent via WhatsApp Business API!', 'success'))
      .catch(err => {
        console.error('WA API error:', err);
        toast('❌ API Error: ' + err.message, 'error');
      });
  } else {
    const clean = phone.length===10 ? '91'+phone : phone;
    window.open('https://wa.me/' + clean + '?text=' + encodeURIComponent(msg), '_blank');
    toast('📱 Opened WhatsApp (enter API credentials to send directly)', 'info');
  }
}

// ── Send WA for an invoice ────────────────────────────────────
async function sendWAForInvoice(inv) {
  // Try client lookup by multiple field names
  const clientId = inv.client || inv.client_id;
  const c = STATE.clients.find(x => String(x.id) === String(clientId)) || {};
  const cByName = !c.id ? (STATE.clients.find(x => x.name === (inv.clientName||inv.client_name)) || {}) : c;
  const client = c.id ? c : cByName;
  const phone = (client.wa || client.whatsapp || client.phone || '').replace(/\D/g, '');
  if (!phone) { toast('⚠️ No WhatsApp number for client "' + (client.name||'Unknown') + '"', 'warning'); return; }
  // Pick the correct template based on invoice status
  // (same logic as auto-send, so manual send always matches automated send)
  const wa = STATE.settings.wa || {};
  let tplKey, tplDefault, tplName, statusLabel;
  const status = inv.status || '';
  if (status === 'Paid') {
    tplKey = wa.tpl_paid; tplDefault = getDefaultWATpl('paid');
    tplName = 'payment_received'; statusLabel = 'Payment Receipt';
  } else if (status === 'Partial') {
    tplKey = wa.tpl_partial; tplDefault = getDefaultWATpl('partial_receipt');
    tplName = 'partial_payment'; statusLabel = 'Partial Receipt';
  } else if (status === 'Overdue') {
    tplKey = wa.tpl_overdue; tplDefault = getDefaultWATpl('overdue');
    tplName = 'payment_overdue'; statusLabel = 'Overdue Alert';
  } else {
    tplKey = wa.tpl_inv; tplDefault = getDefaultWATpl('inv');
    tplName = 'invoice_created'; statusLabel = 'Invoice';
  }
  const tpl = tplKey || tplDefault;
  const msg = formatWAMsg(tpl, inv, client, STATE.settings);
  // Log message
  logWAMessage({ inv, client, type: tplName, msg, status: 'sending' });
  try {
    const result = await sendWA(phone, msg, tplName, inv, client);
    logWAMessage({ inv, client, type: tplName, msg, status: result ? 'sent_api' : 'sent_web' });
    toast(result ? `✅ ${statusLabel} sent to ${client.name}!` : `📱 WhatsApp opened for ${client.name}`, 'success');
  } catch(e) {
    logWAMessage({ inv, client, type: tplName, msg, status: 'failed', error: e.message });
    toast('❌ ' + e.message, 'error');
  }
}

// ── Manual send from WA page ──────────────────────────────────
window.sendManualWA = async function() {
  const phone = (document.getElementById('wa-manual-phone')?.value || '').replace(/\D/g, '');
  const msg   = document.getElementById('wa-manual-msg')?.value || '';
  if (!phone) { toast('⚠️ Enter phone number', 'warning'); return; }
  if (!msg)   { toast('⚠️ Enter message text', 'warning'); return; }
  try {
    const result = await sendWA(phone, msg);
    toast(result ? '✅ Message sent via API!' : '📱 WhatsApp opened', 'success');
    if (result) { document.getElementById('wa-manual-msg').value = ''; }
  } catch(e) { toast('❌ ' + e.message, 'error'); }
};

// ── Auto-fill phone when client selected ─────────────────────
window.fillWaManualPhone = function() {
  const sel = document.getElementById('wa-manual-client');
  const c   = STATE.clients.find(x => String(x.id) === String(sel?.value));
  if (!c) return;
  const ph  = document.getElementById('wa-manual-phone');
  if (ph)   ph.value = c.wa || c.whatsapp || c.phone || '';
  const msg = document.getElementById('wa-manual-msg');
  if (msg && !msg.value) msg.value = `Hi ${c.name}! `;
};

// ── Festival bulk sender ──────────────────────────────────────
window.sendFestivalBulk = async function() {
  const sendTo  = document.getElementById('wa-send-to')?.value || 'all';
  const tpl     = document.getElementById('wa-tpl-festival')?.value || getDefaultWATpl('festival');
  const imgUrl  = document.getElementById('wa-festival-img')?.value || '';
  const wa      = STATE.settings.wa || {};

  let targets = [...STATE.clients].filter(c => c.wa || c.whatsapp || c.phone);
  if (sendTo === 'paid') {
    const paidIds = new Set(STATE.invoices.filter(i=>i.status==='Paid').map(i=>String(i.client)));
    targets = targets.filter(c => paidIds.has(String(c.id)));
  }
  if (sendTo === 'active') {
    const cutoff  = new Date(); cutoff.setDate(cutoff.getDate()-90);
    const actIds  = new Set(STATE.invoices.filter(i=>i.issued&&new Date(i.issued)>cutoff).map(i=>String(i.client)));
    targets = targets.filter(c => actIds.has(String(c.id)));
  }

  const log = document.getElementById('wa-bulk-log');
  if (log) { log.style.display = 'block'; log.innerHTML = `<b>Sending to ${targets.length} clients...</b><br>`; }

  let sent = 0, failed = 0;
  for (const client of targets) {
    const phone = (client.wa || client.whatsapp || client.phone || '').replace(/\D/g,'');
    const msg   = formatWAMsg(tpl, {}, client, STATE.settings);
    try {
      const result = await sendWA(phone, msg);
      sent++;
      if (log) log.innerHTML += `<div style="color:var(--green)">✓ ${client.name}${result?' (API)':' (web)'}</div>`;
      if (!result) await new Promise(r => setTimeout(r, 500));
    } catch(e) {
      failed++;
      if (log) log.innerHTML += `<div style="color:var(--red)">✗ ${client.name}: ${e.message}</div>`;
    }
  }
  toast(`📱 Done: ${sent} sent, ${failed} failed`, sent > 0 ? 'success' : 'warning');
};

// ── Preview festival message ──────────────────────────────────
window.previewFestivalMsg = function() {
  const tpl     = document.getElementById('wa-tpl-festival')?.value || getDefaultWATpl('festival');
  const preview = formatWAMsg(tpl, {}, {name:'[Client Name]'}, STATE.settings);
  toast('📱 ' + preview.substring(0,120) + (preview.length>120?'…':''), 'info');
};

// previewFestivalMsg: defined above

;

window.fillWaManualPhone = function() {
  const sel = document.getElementById('wa-manual-client');
  const c   = STATE.clients.find(x=>String(x.id)===String(sel?.value));
  const ph  = document.getElementById('wa-manual-phone');
  if (!c) return;
  if (ph) ph.value = c.wa || c.whatsapp || c.phone || '';
  // Also auto-fill a greeting in the message box
  const msgEl = document.getElementById('wa-manual-msg');
  if (msgEl && !msgEl.value) {
    msgEl.value = `Hi ${c.name}! `;
  }
};

window.sendManualWA = function() {
  const phone = (document.getElementById('wa-manual-phone')?.value||'').replace(/\D/g,'');
  const msg   = document.getElementById('wa-manual-msg')?.value || '';
  if (!phone) { toast('⚠️ Enter phone number', 'warning'); return; }
  if (!msg)   { toast('⚠️ Enter message', 'warning'); return; }
  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`, '_blank');
  toast('📱 Opening WhatsApp...', 'success');
};

// Populate WA client dropdown when navigating to WA page


// ── Template Customization ─────────────────────────────────────
const TPL_CUSTOM = {
  color1: '#1A2332', color2: '#4DB6AC',
  font: "'Public Sans',sans-serif",
  headerStyle: 'gradient', tableStyle: 'dark',
  footerText: '', tagline: '', watermarkText: 'PAID',
  companyNameSize: '28', companyNameColor: '#ffffff',
  companyNameWeight: '800', companyNameStyle: 'normal',
  logoPosition: 'left'
};

function setTplColor(inputId, color) {
  const colorInput = document.getElementById(inputId);
  const hexInput   = document.getElementById(inputId + '-hex');
  if (colorInput) colorInput.value = color;
  if (hexInput)   hexInput.value   = color;
}

// Sync TPL_CUSTOM → template customization form fields on page load
function populateTemplateForm() {
  const C = window.TPL_CUSTOM || {};
  const setV = (id,v) => { const e=document.getElementById(id); if(e&&v!==undefined) e.value=String(v); };
  setV('tpl-color1',       C.color1        || '#1A2332');
  setV('tpl-color1-hex',   C.color1        || '#1A2332');
  setV('tpl-color2',       C.color2        || '#4DB6AC');
  setV('tpl-color2-hex',   C.color2        || '#4DB6AC');
  setV('tpl-font',         C.font          || "'Public Sans',sans-serif");
  setV('tpl-header-style', C.headerStyle   || 'gradient');
  setV('tpl-table-style',  C.tableStyle    || 'dark');
  setV('tpl-footer-text',  C.footerText    || '');
  setV('tpl-tagline',      C.tagline       || '');
  setV('tpl-watermark-text',C.watermarkText|| 'PAID');
  setV('tpl-name-size',    C.companyNameSize   || '28');
  setV('tpl-name-color',   C.companyNameColor  || '#ffffff');
  setV('tpl-name-style',   C.companyNameWeight || '800');
  setV('tpl-logo-pos',     C.logoPosition  || 'left');
  // Also populate user-visible (renamed) panel
  setV('tpl-r-name-size',  C.companyNameSize   || '28');
  setV('tpl-r-name-color', C.companyNameColor  || '#ffffff');
  setV('tpl-r-name-style', C.companyNameWeight || '800');
  setV('tpl-r-logo-pos',   C.logoPosition      || 'left');
  const rsLbl = document.getElementById('tpl-r-name-size-val');
  if (rsLbl) rsLbl.textContent = (C.companyNameSize||'28')+'px';
}

window.applyTplCustomization = function() {
  // Read color from hex input first (most reliably updated), fallback to color picker
  const readColor = (hexId, pickerId) => {
    const hex = document.getElementById(hexId);
    const pick = document.getElementById(pickerId);
    const v = (hex && hex.value && hex.value.match(/^#[0-9a-fA-F]{3,6}$/)) ? hex.value : (pick ? pick.value : '');
    if (hex && v) hex.value = v;
    if (pick && v) pick.value = v;
    return v;
  };
  TPL_CUSTOM.color1       = readColor('tpl-color1-hex', 'tpl-color1') || TPL_CUSTOM.color1;
  TPL_CUSTOM.color2       = readColor('tpl-color2-hex', 'tpl-color2') || TPL_CUSTOM.color2;
  TPL_CUSTOM.font         = document.getElementById('tpl-font')?.value        || TPL_CUSTOM.font;
  TPL_CUSTOM.headerStyle  = document.getElementById('tpl-header-style')?.value|| TPL_CUSTOM.headerStyle;
  TPL_CUSTOM.tableStyle   = document.getElementById('tpl-table-style')?.value || TPL_CUSTOM.tableStyle;
  TPL_CUSTOM.footerText   = document.getElementById('tpl-footer-text')?.value ?? '';
  TPL_CUSTOM.tagline      = document.getElementById('tpl-tagline')?.value     ?? '';
  TPL_CUSTOM.watermarkText= document.getElementById('tpl-watermark-text')?.value || 'PAID';
  // Read from user-visible panel (tpl-r-*) preferentially
  const _rSizeEl = document.getElementById('tpl-r-name-size');
  TPL_CUSTOM.companyNameSize  = (_rSizeEl?.value) || document.getElementById('tpl-name-size')?.value || '28';
  const _rColorEl = document.getElementById('tpl-r-name-color');
  TPL_CUSTOM.companyNameColor = (_rColorEl?.value) || document.getElementById('tpl-name-color')?.value || '#ffffff';
  const _rStyleEl = document.getElementById('tpl-r-name-style');
  TPL_CUSTOM.companyNameWeight= (_rStyleEl?.value) || document.getElementById('tpl-name-style')?.value || '800';
  // Sync range slider label
  const _rSlideLbl = document.getElementById('tpl-r-name-size-val');
  if (_rSlideLbl) _rSlideLbl.textContent = TPL_CUSTOM.companyNameSize + 'px';
  TPL_CUSTOM.companyNameStyle = document.getElementById('tpl-name-style')?.value || 'normal';
  const _rLogoPosEl = document.getElementById('tpl-r-logo-pos');
  TPL_CUSTOM.logoPosition = (_rLogoPosEl?.value) || document.getElementById('tpl-logo-pos')?.value || 'left';
  // Sync all UI controls
  const sync = (id, val) => { const e=document.getElementById(id); if(e) e.value=val; };
  sync('tpl-color1',     TPL_CUSTOM.color1); sync('tpl-color1-hex', TPL_CUSTOM.color1);
  sync('tpl-color2',     TPL_CUSTOM.color2); sync('tpl-color2-hex', TPL_CUSTOM.color2);
  const sizeVal = document.getElementById('tpl-name-size-val');
  if (sizeVal) sizeVal.textContent = TPL_CUSTOM.companyNameSize + 'px';
  const sizeRange = document.getElementById('tpl-name-size');
  if (sizeRange) sizeRange.value = TPL_CUSTOM.companyNameSize;
  const nc = document.getElementById('tpl-name-color');
  if (nc) nc.value = TPL_CUSTOM.companyNameColor;
  // Preview
  const n = STATE.settings.activeTemplate || 1;
  previewTemplate(n);
  if (document.getElementById('invoicePreviewWrap')) livePreview();
  toast('✅ Customization applied! Click Save to persist.', 'success');
};

window.saveTplCustomization = async function() {
  applyTplCustomization();
  const payload = {
    tpl_color1:        TPL_CUSTOM.color1,
    tpl_color2:        TPL_CUSTOM.color2,
    tpl_font:          TPL_CUSTOM.font,
    tpl_header_style:  TPL_CUSTOM.headerStyle,
    tpl_table_style:   TPL_CUSTOM.tableStyle,
    tpl_footer_text:   TPL_CUSTOM.footerText,
    tpl_tagline:       TPL_CUSTOM.tagline,
    tpl_watermark_text:TPL_CUSTOM.watermarkText,
    tpl_name_size:     TPL_CUSTOM.companyNameSize,
    tpl_name_color:    TPL_CUSTOM.companyNameColor,
    tpl_name_weight:   TPL_CUSTOM.companyNameWeight,
    tpl_logo_pos:      TPL_CUSTOM.logoPosition,
  };
  try {
    await api('api/settings.php', 'POST', payload);
    toast('✅ Template customization saved!', 'success');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
};

window.resetTplCustomization = function() {
  TPL_CUSTOM.color1 = '#1A2332'; TPL_CUSTOM.color2 = '#4DB6AC';
  TPL_CUSTOM.font   = "'Public Sans',sans-serif";
  TPL_CUSTOM.headerStyle='gradient'; TPL_CUSTOM.tableStyle='dark';
  TPL_CUSTOM.footerText=''; TPL_CUSTOM.tagline=''; TPL_CUSTOM.watermarkText='PAID';
  setTplColor('tpl-color1','#1A2332'); setTplColor('tpl-color2','#4DB6AC');
  const tplFont=document.getElementById('tpl-font'); if(tplFont)tplFont.value="'Public Sans',sans-serif";
  toast('↩️ Reset to defaults', 'info');
  previewTemplate(STATE.settings.activeTemplate||1);
};

// Override tplLogoHTML to use custom font
const _origTplLogoHTML = window.tplLogoHTML;

// tplLogoHTML: see function declaration above
;


// ── WA Template Mode ───────────────────────────────────────────
function setWAMode(mode) {
  const sec = document.getElementById('tpl-names-section');
  const sLbl = document.getElementById('mode-session-lbl');
  const tLbl = document.getElementById('mode-template-lbl');
  if (sec)  sec.style.display  = mode === 'template' ? 'block' : 'none';
  if (sLbl) sLbl.style.borderColor = mode === 'session'  ? 'var(--teal)' : 'var(--border)';
  if (tLbl) tLbl.style.borderColor = mode === 'template' ? 'var(--teal)' : 'var(--border)';
  if (!STATE.settings.wa) STATE.settings.wa = {};
  STATE.settings.wa.msg_mode = mode;
}



// ── Festival Campaign Save & Schedule ─────────────────────────
window.saveFestivalCampaign = async function() {
  const payload = {
    wa_festival_tpl:      document.getElementById('wa-tpl-festival')?.value   || '',
    wa_festival_sendto:   document.getElementById('wa-send-to')?.value        || 'all',
    wa_festival_img:      document.getElementById('wa-festival-img')?.value   || '',
    wa_festival_schedule: document.getElementById('wa-festival-schedule')?.value || '',
    wa_festival_repeat:   document.getElementById('wa-festival-repeat')?.value || '',
    wa_festival_name:     document.getElementById('wa-festival')?.value        || 'custom',
  };
  try {
    await api('api/settings.php', 'POST', payload);
    // Store locally
    if (!STATE.settings.wa) STATE.settings.wa = {};
    STATE.settings.wa.festival_schedule = payload.wa_festival_schedule;
    STATE.settings.wa.festival_repeat   = payload.wa_festival_repeat;
    // Show confirmation
    const schedTime = payload.wa_festival_schedule
      ? ' — scheduled for ' + new Date(payload.wa_festival_schedule).toLocaleString('en-IN',{dateStyle:'medium',timeStyle:'short'})
      : '';
    toast('✅ Campaign saved!' + schedTime, 'success');
    renderFestivalCampaigns();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
};

function renderFestivalCampaigns() {
  const el = document.getElementById('wa-campaigns-list');
  if (!el) return;
  const wa = STATE.settings.wa || {};
  if (!wa.festival_schedule && !wa.festival_repeat) { el.innerHTML = ''; return; }
  const schedTime = wa.festival_schedule
    ? new Date(wa.festival_schedule).toLocaleString('en-IN',{dateStyle:'medium',timeStyle:'short'})
    : 'Not scheduled';
  el.innerHTML = `<div style="background:var(--teal-bg);border-radius:8px;padding:10px 14px;font-size:12px;border:1px solid var(--teal);margin-top:4px">
    <div style="font-weight:700;color:var(--teal);margin-bottom:4px"><i class="fas fa-calendar-check"></i> Saved Campaign</div>
    <div>📅 Schedule: <strong>${schedTime}</strong></div>
    ${wa.festival_repeat ? `<div>🔁 Repeat: <strong>${wa.festival_repeat}</strong></div>` : ''}
    <div>👥 Send to: <strong>${wa.festival_sendto || 'all clients'}</strong></div>
    <button onclick="clearFestivalCampaign()" style="margin-top:8px;font-size:11px;padding:4px 10px;border:1px solid var(--red);color:var(--red);background:none;border-radius:6px;cursor:pointer">
      <i class="fas fa-times"></i> Clear Campaign
    </button>
  </div>`;
}

window.clearFestivalCampaign = async function() {
  try {
    await api('api/settings.php','POST',{wa_festival_schedule:'',wa_festival_repeat:''});
    if (STATE.settings.wa) { STATE.settings.wa.festival_schedule=''; STATE.settings.wa.festival_repeat=''; }
    document.getElementById('wa-festival-schedule').value = '';
    document.getElementById('wa-festival-repeat').value   = '';
    renderFestivalCampaigns();
    toast('Campaign cleared','info');
  } catch(e) { toast('❌ '+e.message,'error'); }
};


// ── Auto-save invoice draft (tnc / notes / bank changes) ──────
let _draftSaveTimer = null;
function debounceSaveInvoiceDraft() {
  // Show glow feedback immediately regardless of editing state
  ['f-tnc','f-notes','f-bank'].forEach(id => {
    const el = document.getElementById(id);
    if (el && document.activeElement === el) {
      el.style.borderColor = 'var(--teal)';
      el.style.boxShadow   = '0 0 0 3px rgba(0,137,123,.15)';
      setTimeout(() => { el.style.borderColor=''; el.style.boxShadow=''; }, 1500);
    }
  });
  // Only auto-save if editing an existing invoice
  if (!STATE.editingInvoiceId) return;
  clearTimeout(_draftSaveTimer);
  _draftSaveTimer = setTimeout(() => {
    const d = getFormData();
    const payload = { notes: d.notes, bank_details: d.bank, terms: d.tnc };
    api('api/invoices.php?id=' + parseInt(STATE.editingInvoiceId), 'PATCH', payload)
      .then(() => {
        // Brief teal glow on the textarea
        ['f-tnc','f-notes','f-bank'].forEach(id => {
          const el = document.getElementById(id);
          if (el && document.activeElement === el) {
            el.style.borderColor = 'var(--teal)';
            el.style.boxShadow   = '0 0 0 3px rgba(0,137,123,.12)';
            setTimeout(() => { el.style.borderColor=''; el.style.boxShadow=''; }, 1200);
          }
        });
        // Refresh STATE
        const idx = STATE.invoices.findIndex(i => String(i.id)===String(STATE.editingInvoiceId));
        if (idx > -1) {
          STATE.invoices[idx].notes        = d.notes;
          STATE.invoices[idx].bank         = d.bank;
          STATE.invoices[idx].bank_details = d.bank;
          STATE.invoices[idx].tnc          = d.tnc;
          STATE.invoices[idx].terms        = d.tnc;
        }
      })
      .catch(e => console.warn('Draft auto-save failed:', e.message));
  }, 1500);
}

// ── Split Payment UI ──────────────────────────────────────
function toggleSplitPayment() {
  const sel    = document.getElementById('paid-method');
  const panel  = document.getElementById('split-payment-panel');
  const amtFld = document.getElementById('paid-amt-field');
  if (!panel) return;
  const isSplit = sel?.value === 'Split';
  panel.style.display = isSplit ? 'block' : 'none';
  if (amtFld) amtFld.style.opacity = isSplit ? '0.6' : '1';
  if (isSplit) {
    // Pre-fill first row with full amount received, second row 0 — user adjusts
    const totalAmt = parseFloat(document.getElementById('paid-amt')?.value) || 0;
    const rows = document.querySelectorAll('#split-rows .split-amt');
    if (rows.length >= 2) {
      rows[0].value = totalAmt > 0 ? totalAmt.toFixed(2) : '';
      rows[1].value = '';
    }
    updateSplitTotal();
    // Show partial info box if amount < invoice total
    updatePaidRemaining();
  }
}

function updateSplitTotal() {
  const rows = document.querySelectorAll('#split-rows .split-amt');
  const amts = Array.from(rows).map(el => parseFloat(el.value)||0);
  const splitSum = amts.reduce((s,v) => s+v, 0);
  const el = document.getElementById('split-total');
  if (el) el.textContent = fmt_money(splitSum, '₹');

  // Auto-fill last row with remainder when first row changes
  // Only when exactly 2 rows and user typed in row 0
  const totalAmt = parseFloat(document.getElementById('paid-amt')?.value) || 0;
  if (rows.length === 2 && totalAmt > 0) {
    // Identify which row triggered the input — the one with focus
    const focusedRow = document.activeElement?.closest('.split-row');
    const focusedIdx = focusedRow ? Array.from(document.querySelectorAll('#split-rows .split-row')).indexOf(focusedRow) : -1;
    if (focusedIdx === 0) {
      const remainder = Math.max(0, totalAmt - (parseFloat(rows[0].value)||0));
      rows[1].value = remainder > 0 ? remainder.toFixed(2) : '';
    } else if (focusedIdx === 1) {
      const remainder = Math.max(0, totalAmt - (parseFloat(rows[1].value)||0));
      rows[0].value = remainder > 0 ? remainder.toFixed(2) : '';
    }
  }

  // Re-calc split sum after auto-fill
  const finalAmts = Array.from(document.querySelectorAll('#split-rows .split-amt')).map(el => parseFloat(el.value)||0);
  const finalSum = finalAmts.reduce((s,v) => s+v, 0);
  if (el) el.textContent = fmt_money(finalSum, '₹');

  // Show mismatch warning if split total differs from Amount Received
  const amtReceived = parseFloat(document.getElementById('paid-amt')?.value) || 0;
  const warnEl = document.getElementById('split-mismatch-warn');
  if (warnEl) {
    if (amtReceived > 0 && Math.abs(finalSum - amtReceived) > 0.01) {
      warnEl.style.display = 'block';
      warnEl.textContent = finalSum > amtReceived
        ? `⚠️ Split total (${fmt_money(finalSum,'₹')}) exceeds Amount Received`
        : `⚠️ Split total (${fmt_money(finalSum,'₹')}) is less than Amount Received`;
    } else {
      warnEl.style.display = 'none';
    }
  }

  // Update split breakdown bar
  renderSplitBreakdown();

  // Keep partial info box in sync
  updatePaidRemaining();
}

function renderSplitBreakdown() {
  const barEl = document.getElementById('split-breakdown-bar');
  if (!barEl) return;
  const totalAmt = parseFloat(document.getElementById('paid-amt')?.value) || 0;
  const rows = document.querySelectorAll('#split-rows .split-row');
  const parts = Array.from(rows).map((row, i) => {
    const method = row.querySelector('.split-method')?.value || '';
    const amt    = parseFloat(row.querySelector('.split-amt')?.value) || 0;
    const shortM = method.split(' ')[0]; // UPI, Bank, Cash, Cheque, Credit
    const colors = ['#1565C0','#2E7D32','#E65100','#6A1B9A','#B71C1C'];
    const col = colors[i % colors.length];
    return `<span style="display:inline-flex;align-items:center;gap:4px">
      <span style="font-weight:700;color:${col}">${shortM}:</span>
      <span style="font-family:var(--mono);color:${col}">${fmt_money(amt,'₹')}</span>
    </span>`;
  });
  barEl.innerHTML = `
    <span style="display:inline-flex;align-items:center;gap:4px">
      <span style="font-weight:700;color:var(--teal)">Total:</span>
      <span style="font-family:var(--mono);color:var(--teal)">${fmt_money(totalAmt,'₹')}</span>
    </span>
    <span style="color:var(--muted2)">|</span>
    ${parts.join('<span style="color:var(--muted2)">|</span>')}`;
  barEl.style.display = rows.length > 0 ? 'flex' : 'none';
}

function addSplitRow() {
  const container = document.getElementById('split-rows');
  const row = document.createElement('div');
  row.className = 'split-row';
  row.style.cssText = 'display:flex;gap:8px;align-items:center';
  row.innerHTML = `<select class="split-method" style="flex:1;padding:7px 8px;border-radius:8px;border:1px solid var(--border);font-size:12px" onchange="renderSplitBreakdown()">
    <option>UPI (GPay/PhonePe/Paytm)</option>
    <option>Bank Transfer (NEFT/RTGS)</option>
    <option>Cash</option><option>Cheque</option><option>Credit Card</option>
  </select>
  <input type="number" class="split-amt" placeholder="0.00" style="width:100px;padding:7px 8px;border-radius:8px;border:1px solid var(--border);font-size:12px" oninput="updateSplitTotal()">
  <button onclick="removeSplitRow(this)" style="padding:6px 10px;background:#FFEBEE;color:#C62828;border:none;border-radius:7px;cursor:pointer;font-size:12px">✕</button>`;
  container.appendChild(row);
  renderSplitBreakdown();
}

function removeSplitRow(btn) {
  const rows = document.querySelectorAll('#split-rows .split-row');
  if (rows.length <= 2) { toast('⚠️ Keep at least 2 split methods', 'warning'); return; }
  btn.closest('.split-row').remove();
  updateSplitTotal();
  renderSplitBreakdown();
}

function getSplitMethodLabel() {
  const rows = document.querySelectorAll('#split-rows .split-row');
  const parts = Array.from(rows).map(r => {
    const m = r.querySelector('.split-method')?.value || '';
    const a = parseFloat(r.querySelector('.split-amt')?.value || 0);
    return a > 0 ? `${m.split(' ')[0]}: ₹${a.toFixed(0)}` : null;
  }).filter(Boolean);
  return 'Split: ' + parts.join(' + ');
}

</script>

</body>
</html>