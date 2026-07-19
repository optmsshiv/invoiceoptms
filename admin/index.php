<?php
// ================================================================
//  OPTMS Super Admin Panel — admin/index.php
//  Accessible only to super_admin role
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();

$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin — OPTMS</title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&family=Hanken+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --ink:#0B1522; --ink-2:#16233A; --ink-border:#25344D;
  --surface:#f8faf6; --card:#ffffff; --border:#e5e7eb; --border-soft:#eef1ed;
  --text:#191c1b; --text-soft:#3f4944; --text-mute:#6f7974;
  --accent:#0f5a46; --accent-dark:#004131; --accent-soft:#e3f5ec;
  --danger:#ba1a1a; --danger-soft:#ffdad6;
  --warning:#765b04; --warning-soft:#fed97c;
  --shadow-sm:0 4px 12px rgba(0,0,0,.03);
  --shadow-md:0 12px 32px rgba(0,0,0,.08);
  --radius:18px;

  --tb-surface:#ffffff; --tb-surface-low:#f2f4f1; --tb-surface-tint:#eceeeb;
  --tb-on-surface:#191c1b; --tb-on-surface-variant:#3f4944;
  --tb-outline:#6f7974; --tb-outline-variant:#bfc9c3;
  --tb-primary:#004131; --tb-primary-container:#0f5a46; --tb-on-primary:#ffffff;
  --tb-secondary-container:#fed97c; --tb-on-secondary-container:#785d07;
  --tb-error:#ba1a1a;
  --tb-shadow-1:0 4px 12px rgba(0,0,0,.03);
  --tb-shadow-2:0 12px 32px rgba(0,0,0,.08);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--surface);min-height:100vh;color:var(--text);font-size:14px;-webkit-font-smoothing:antialiased}
code,.mono{font-family:'JetBrains Mono',monospace}

/* Topbar */
.topbar{background:var(--tb-surface);color:var(--tb-on-surface);position:sticky;top:0;z-index:100;border-bottom:1px solid var(--tb-outline-variant);box-shadow:var(--tb-shadow-1)}
.topbar-main{padding:0 24px;height:64px;display:flex;align-items:center;justify-content:space-between;gap:20px}
.topbar-left{display:flex;align-items:center;gap:10px;flex-shrink:0}
.topbar-brand{font-family:'Hanken Grotesk',sans-serif;font-size:16px;font-weight:600;color:var(--tb-on-surface);display:flex;align-items:center;gap:9px;letter-spacing:-.01em;white-space:nowrap}
.brand-mark{width:28px;height:28px;border-radius:8px;background:var(--tb-primary);display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--tb-on-primary);flex-shrink:0}
.v-tag{font-family:'JetBrains Mono',monospace;font-size:10px;font-weight:500;color:var(--tb-on-surface-variant);background:var(--tb-surface-tint);padding:2px 7px;border-radius:5px;margin-left:4px}
.role-pill{font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:500;color:var(--tb-on-secondary-container);background:var(--tb-secondary-container);padding:3px 9px;border-radius:20px;white-space:nowrap}
.topbar-search{flex:1;max-width:420px;position:relative}
.topbar-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--tb-on-surface-variant);font-size:13px;pointer-events:none}
.topbar-search input{width:100%;height:36px;background:var(--tb-surface);border:1px solid var(--tb-outline-variant);border-radius:8px;color:var(--tb-on-surface);font-family:'Inter',sans-serif;font-size:13px;padding:0 30px 0 34px;transition:.15s}
.topbar-search input::placeholder{color:var(--tb-on-surface-variant)}
.topbar-search input:focus{outline:none;border-color:var(--tb-primary);box-shadow:0 0 0 3px rgba(0,65,49,.10)}
.search-clear{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:18px;height:18px;border:none;background:none;color:var(--tb-on-surface-variant);cursor:pointer;display:none;align-items:center;justify-content:center;font-size:11px}
.search-clear:hover{color:var(--tb-on-surface)}
.topbar-right{display:flex;align-items:center;gap:8px;font-size:13px;flex-shrink:0}
.icon-btn{position:relative;width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--tb-on-surface-variant);background:transparent;text-decoration:none;transition:.15s;border:none;cursor:pointer;font-size:14px}
.icon-btn:hover{background:var(--tb-surface-tint);color:var(--tb-on-surface)}
.notif-dot{position:absolute;top:6px;right:7px;width:6px;height:6px;border-radius:50%;background:var(--tb-error)}
.topbar-divider{width:1px;height:22px;background:var(--tb-outline-variant);margin:0 2px}
.user-menu{position:relative}
.user-chip{display:flex;align-items:center;gap:8px;padding:4px 8px 4px 4px;background:var(--tb-surface-low);border:none;border-radius:20px;cursor:pointer;transition:.15s}
.user-chip:hover{background:var(--tb-surface-tint)}
.avatar-sm{width:24px;height:24px;border-radius:50%;background:var(--tb-primary);color:var(--tb-on-primary);font-size:10px;font-weight:600;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.user-chip span.uname{font-family:'Inter',sans-serif;color:var(--tb-on-surface);font-weight:500;font-size:12.5px}
.user-chip i.ti-chevron-down{color:var(--tb-on-surface-variant);font-size:12px;transition:.15s}
.user-menu.open .user-chip i.ti-chevron-down{transform:rotate(180deg)}
.user-dropdown{position:absolute;top:calc(100% + 8px);right:0;background:var(--tb-surface);border:1px solid var(--tb-outline-variant);border-radius:12px;min-width:190px;box-shadow:var(--tb-shadow-2);display:none;overflow:hidden;z-index:200}
.user-menu.open .user-dropdown{display:block}
.user-dropdown a,.user-dropdown button{display:flex;align-items:center;gap:9px;width:100%;padding:10px 14px;font-family:'Inter',sans-serif;font-size:13px;color:var(--tb-on-surface);text-decoration:none;background:none;border:none;cursor:pointer;text-align:left}
.user-dropdown a:hover,.user-dropdown button:hover{background:var(--tb-surface-low)}
.user-dropdown i{font-size:14px;color:var(--tb-on-surface-variant);width:14px}
.user-dropdown .logout-item{color:var(--tb-error)}
.user-dropdown .logout-item i{color:var(--tb-error)}
.user-dropdown .divider{height:1px;background:var(--tb-outline-variant);margin:4px 0}
.topbar-crumb-strip{padding:9px 24px;display:flex;align-items:center;gap:8px;font-family:'Inter',sans-serif;font-size:12px;color:var(--tb-on-surface-variant);background:var(--tb-surface-low);border-top:1px solid var(--tb-outline-variant)}
.topbar-crumb-strip a{color:var(--tb-on-surface-variant);text-decoration:none}
.topbar-crumb-strip a:hover{color:var(--tb-on-surface)}
.topbar-crumb-strip i{font-size:10px;color:var(--tb-outline)}
.topbar-crumb-strip .current{color:var(--tb-on-surface);font-weight:600}
@media (max-width:900px){.topbar-search{display:none}}

/* Layout */
.container{max-width:1240px;margin:0 auto;padding:32px 24px 60px}
.page-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:26px;gap:16px;flex-wrap:wrap}
.eyebrow{font-size:11px;font-weight:700;color:var(--accent-dark);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px}
.page-title{font-family:'Hanken Grotesk',sans-serif;font-size:24px;font-weight:600;letter-spacing:-.01em;color:var(--text)}
.page-sub{font-size:13px;color:var(--text-mute);margin-top:4px}

.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:28px}
.stat-card{background:var(--card);border-radius:var(--radius);padding:18px 20px;border:1px solid var(--border);display:flex;align-items:center;gap:14px;box-shadow:var(--shadow-sm)}
.stat-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.stat-card .val{font-family:'JetBrains Mono',monospace;font-size:22px;font-weight:600;color:var(--text);line-height:1;font-variant-numeric:tabular-nums}
.stat-card .lbl{font-size:11.5px;color:var(--text-mute);font-weight:600;margin-top:5px}

.card{background:var(--card);border-radius:var(--radius);border:1px solid var(--border);margin-bottom:24px;overflow:hidden;box-shadow:var(--shadow-sm)}
.card-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.card-header h3{font-family:'Hanken Grotesk',sans-serif;font-size:15px;font-weight:600}
.card-header .sub{font-size:12px;color:var(--text-mute);margin-top:2px;font-weight:500}

/* Buttons */
.btn{padding:9px 16px;border-radius:8px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;border:1px solid transparent;transition:.15s;display:inline-flex;align-items:center;gap:7px;line-height:1}
.btn-primary{background:var(--accent);color:#fff;box-shadow:0 1px 2px rgba(15,90,70,.35)}
.btn-primary:hover{background:var(--accent-dark)}
.btn-danger{background:var(--danger-soft);color:var(--danger);border-color:#F7D3D0}
.btn-danger:hover{background:var(--danger);color:#fff;border-color:var(--danger)}
.btn-outline{background:#fff;border-color:var(--border);color:var(--text-soft)}
.btn-outline:hover{border-color:var(--accent);color:var(--accent-dark)}
.btn-sm{padding:6px 11px;font-size:12px}
.btn-icon{width:32px;height:32px;padding:0;justify-content:center;border-radius:8px}
.action-group{display:inline-flex;border:1px solid var(--border);border-radius:8px;overflow:hidden}
.action-group .btn{border-radius:0;border:none;border-right:1px solid var(--border)}
.action-group .btn:last-child{border-right:none}

/* Table */
table{width:100%;border-collapse:collapse}
th{padding:11px 20px;font-size:10.5px;font-weight:700;color:var(--text-mute);text-transform:uppercase;letter-spacing:.6px;text-align:left;border-bottom:1px solid var(--border);background:#FAFBFC}
td{padding:14px 20px;font-size:13px;border-bottom:1px solid var(--border-soft);vertical-align:middle}
tr:last-child td{border-bottom:none}
tbody tr{transition:background .1s}
tbody tr:hover td{background:#FAFBFD}
.cell-primary{display:flex;align-items:center;gap:11px}
.avatar-md{width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,var(--accent-soft),#c9ece0);color:var(--accent-dark);font-size:12.5px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.cell-primary .name{font-weight:700;color:var(--text);font-size:13.3px}
.cell-primary .meta{font-size:11.5px;color:var(--text-mute);margin-top:1px}
.id-stack .slug{font-size:12px;font-weight:600;color:var(--text-soft)}
.id-stack .dbn{font-size:10.5px;color:var(--text-mute);margin-top:2px}
.muted-date{font-size:12px;color:var(--text-mute)}
.count-pill{display:inline-flex;align-items:center;gap:5px;font-weight:700;font-size:12.5px;color:var(--text-soft)}
.count-pill i{color:var(--text-mute);font-size:11px}

/* Badges */
.badge{font-family:'JetBrains Mono',monospace;display:inline-flex;align-items:center;gap:6px;padding:4px 10px 4px 8px;border-radius:20px;font-size:10.5px;font-weight:500;white-space:nowrap}
.badge::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor;flex-shrink:0}
.badge-active{background:#e3f5ec;color:#0f5a46}
.badge-suspended{background:var(--danger-soft);color:var(--danger)}
.badge-trial{background:#F1EDFC;color:#6D3EC7}
.badge-pro{background:#EAF1FE;color:#1D5FE0}
.badge-basic{background:var(--warning-soft);color:var(--warning)}
.badge-enterprise{background:#0E2A44;color:#8FD6FF}
.badge-owner{background:#E4F3FC;color:#0A6CA8}
.badge-admin{background:#F3E9FE;color:#7A2EC2}
.badge-manager{background:#E7F7EF;color:#0B8A4E}
.badge-accountant{background:#FDEEE1;color:#B45309}
.badge-sales{background:#EAF8EA;color:#2E8B39}
.badge-viewer{background:#F1F2F4;color:var(--text-mute)}
.verified-tag{font-size:10px;font-weight:700;padding:3px 9px;border-radius:8px;background:#E4F3FC;color:#0A6CA8;letter-spacing:.3px}
.override-tag{font-size:9.5px;font-weight:700;color:var(--warning);background:var(--warning-soft);padding:2px 7px;border-radius:10px;letter-spacing:.3px}

/* Modals */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(25,28,27,.5);backdrop-filter:blur(2px);z-index:1000;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:18px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 20px 60px rgba(25,28,27,.25)}
.modal-close{position:absolute;top:18px;right:18px;width:30px;height:30px;border-radius:8px;border:none;background:var(--surface);color:var(--text-mute);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:.15s}
.modal-close:hover{background:var(--border);color:var(--text)}
.modal-head{padding:24px 26px 16px;display:flex;gap:13px;align-items:flex-start;border-bottom:1px solid var(--border-soft)}
.modal-icon{width:38px;height:38px;border-radius:10px;background:var(--accent-soft);color:var(--accent-dark);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.modal-head h3{font-family:'Hanken Grotesk',sans-serif;font-size:16.5px;font-weight:600;letter-spacing:-.01em}
.modal-head p{font-size:12.5px;color:var(--text-mute);margin-top:3px;line-height:1.5}
.modal-body{padding:20px 26px 26px}
.modal-foot{display:flex;gap:10px;justify-content:flex-end;padding:16px 26px 22px;border-top:1px solid var(--border-soft);margin-top:6px}
.field{margin-bottom:14px}
.field label{display:block;font-size:11px;font-weight:700;color:var(--text-soft);margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px}
.field input,.field select{width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13px;outline:none;color:var(--text);background:#fff;transition:.12s}
.field input:focus,.field select:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.section-label{font-size:11px;font-weight:700;color:var(--text-mute);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px}
.divider{height:1px;background:var(--border-soft);margin:18px 0;border:none}
.alert{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;line-height:1.5}
.alert-success{background:var(--accent-soft);color:var(--accent-dark);border:1px solid #b7e3d0}
.alert-error{background:var(--danger-soft);color:var(--danger);border:1px solid #F7D3D0}

/* Toggle switch */
.switch{position:relative;display:inline-block;width:38px;height:22px;flex-shrink:0;cursor:pointer}
.switch input{opacity:0;width:0;height:0}
.switch .slider{position:absolute;inset:0;background:#D7DCE3;border-radius:22px;transition:.15s}
.switch .slider::before{content:'';position:absolute;width:16px;height:16px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.15s;box-shadow:0 1px 2px rgba(16,24,40,.25)}
.switch input:checked + .slider{background:var(--accent)}
.switch input:checked + .slider::before{transform:translateX(16px)}
.switch input:focus-visible + .slider{box-shadow:0 0 0 3px var(--accent-soft)}

/* Plan tabs */
.plan-tabs{display:flex;gap:4px;background:var(--surface);padding:4px;border-radius:10px;border:1px solid var(--border-soft)}
.plan-tab{flex:1;padding:8px 6px;border-radius:7px;border:none;background:transparent;font-size:12.5px;font-weight:700;color:var(--text-mute);cursor:pointer;transition:.15s;font-family:inherit}
.plan-tab.active{background:#fff;color:var(--text);box-shadow:var(--shadow-sm)}
.plan-tab:hover:not(.active){color:var(--text-soft)}

/* Permission group cards */
.perm-card{border:1px solid var(--border);border-radius:10px;margin-bottom:12px;overflow:hidden}
.perm-card-head{padding:10px 14px;background:var(--surface);font-size:11px;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:.5px;display:flex;justify-content:space-between;align-items:center}
.perm-card-head .count{font-size:10.5px;font-weight:700;color:var(--text-mute);text-transform:none;letter-spacing:0}
.perm-row{display:flex;align-items:center;justify-content:space-between;padding:11px 14px;border-top:1px solid var(--border-soft)}
.perm-row .lbl{font-size:13px;color:var(--text-soft);font-weight:500;display:flex;align-items:center;gap:8px}
.info-strip{display:flex;align-items:center;justify-content:space-between;gap:10px;background:var(--surface);border:1px solid var(--border-soft);border-radius:8px;padding:9px 12px;font-size:12px;color:var(--text-mute);margin-bottom:14px}
.info-strip strong{color:var(--text);font-weight:700}
.search-box{position:relative;margin-bottom:14px}
.search-box i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-mute);font-size:12px}
.search-box input{width:100%;padding:9px 12px 9px 32px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13px;outline:none}
.search-box input:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
.user-row-card{display:flex;align-items:center;gap:12px;padding:12px 4px;border-bottom:1px solid var(--border-soft);flex-wrap:wrap}
.user-row-card:last-child{border-bottom:none}
.contact-cell{display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--text-soft)}
.contact-cell i{color:var(--text-mute);font-size:11px;width:12px}
</style>
</head>
<body>
<div class="topbar">
  <div class="topbar-main">
    <div class="topbar-left">
      <div class="topbar-brand">
        <span class="brand-mark"><i class="fas fa-shield-alt"></i></span>
        OPTMS <span class="v-tag">v<?= APP_VERSION ?></span>
      </div>
      <span class="role-pill">Super Admin</span>
    </div>

    <div class="topbar-search">
      <i class="fas fa-search"></i>
      <input type="text" id="topbar-search-input" placeholder="Search tenants, owners, slugs..." autocomplete="off">
      <button class="search-clear" id="topbar-search-clear" title="Clear"><i class="fas fa-times"></i></button>
    </div>

    <div class="topbar-right">
      <button class="icon-btn" id="notif-btn" title="Notifications">
        <i class="fas fa-bell"></i>
        <span class="notif-dot"></span>
      </button>
      <div class="topbar-divider"></div>
      <div class="user-menu" id="user-menu">
        <button class="user-chip" id="user-chip-btn">
          <span class="avatar-sm"><?= strtoupper(substr($user['name'] ?? 'S', 0, 1)) ?></span>
          <span class="uname"><?= htmlspecialchars($user['name'] ?? 'Super Admin') ?></span>
          <i class="fas fa-chevron-down"></i>
        </button>
        <div class="user-dropdown">
          <a href="/dashboard.php"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
          <a href="/profile.php"><i class="fas fa-user"></i> Profile</a>
          <div class="divider"></div>
          <a href="/auth/logout.php" class="logout-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
      </div>
    </div>
  </div>
  <div class="topbar-crumb-strip">
    <a href="/dashboard.php">Dashboard</a>
    <i class="fas fa-chevron-right"></i>
    <span class="current">Tenant Management</span>
  </div>
</div>

<div class="container">
  <div class="page-head">
    <div>
      <div class="eyebrow">Super Admin</div>
      <div class="page-title">Tenant Management</div>
      <div class="page-sub">Provision, monitor, and manage every tenant workspace on the platform.</div>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row" id="stats-row">
    <div class="stat-card">
      <div class="stat-icon" style="background:var(--accent-soft);color:var(--accent-dark)"><i class="fas fa-building"></i></div>
      <div><div class="val" id="stat-total">—</div><div class="lbl">Total Tenants</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#E7F7EF;color:#0B8A4E"><i class="fas fa-circle-check"></i></div>
      <div><div class="val" id="stat-active">—</div><div class="lbl">Active</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:var(--danger-soft);color:var(--danger)"><i class="fas fa-pause"></i></div>
      <div><div class="val" id="stat-suspended">—</div><div class="lbl">Suspended</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#EAF1FE;color:#1D5FE0"><i class="fas fa-users"></i></div>
      <div><div class="val" id="stat-users">—</div><div class="lbl">Total Users</div></div>
    </div>
  </div>

  <!-- Tenants Table -->
  <div class="card">
    <div class="card-header">
      <div>
        <h3>All Tenants</h3>
        <div class="sub" id="tenants-subtitle">Loading tenant workspaces…</div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-outline" onclick="openPlanDefaults()">
          <i class="fas fa-sliders-h"></i> Plan Defaults
        </button>
        <button class="btn btn-outline" onclick="openAttachExisting()">
          <i class="fas fa-plug"></i> Attach Existing Database
        </button>
        <button class="btn btn-primary" onclick="openCreateTenant()">
          <i class="fas fa-plus"></i> New Tenant
        </button>
      </div>
    </div>
    <div id="tenants-table-wrap" style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>Company</th><th>Contact</th><th>Identifiers</th><th>Plan</th><th>Status</th>
            <th>Users</th><th>Created</th><th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody id="tenants-tbody">
          <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-mute)">Loading…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Create Tenant Modal -->
<div class="modal-overlay" id="modal-create-tenant">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-create-tenant')"><i class="fas fa-times"></i></button>
    <div class="modal-head">
      <div class="modal-icon"><i class="fas fa-plus"></i></div>
      <div>
        <h3>New Tenant</h3>
        <p>Provisions a dedicated database and creates the owner account.</p>
      </div>
    </div>
    <div class="modal-body">
      <div id="create-alert"></div>
      <div class="field-row">
        <div class="field">
          <label>Company Name *</label>
          <input id="t-company" placeholder="Acme Corp">
        </div>
        <div class="field">
          <label>Slug (auto)</label>
          <input id="t-slug" class="mono" placeholder="acme_corp">
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Owner Name *</label>
          <input id="t-owner-name" placeholder="Rahul Shah">
        </div>
        <div class="field">
          <label>Owner Email *</label>
          <input id="t-owner-email" type="email" placeholder="rahul@acme.com">
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Phone</label>
          <input id="t-phone" placeholder="9876543210">
        </div>
        <div class="field">
          <label>Plan</label>
          <select id="t-plan">
            <option value="trial">Trial</option>
            <option value="basic">Basic</option>
            <option value="pro" selected>Pro</option>
            <option value="enterprise">Enterprise</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label>Business Type <span style="font-weight:400;font-size:11px;color:var(--text-mute)">(sets wording on their Products page)</span></label>
        <select id="t-business-type">
          <option value="service">Services (consulting, web dev, ERP…)</option>
          <option value="product">Products (trading, import/export, retail…)</option>
          <option value="both" selected>Both / Mixed</option>
        </select>
      </div>
      <div class="field">
        <label>Temp Password (leave blank to auto-generate)</label>
        <input id="t-password" class="mono" placeholder="Auto-generated if blank">
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-create-tenant')">Cancel</button>
      <button class="btn btn-primary" onclick="createTenant()">
        <i class="fas fa-database"></i> Provision &amp; Create
      </button>
    </div>
  </div>
</div>

<!-- Attach Existing Database Modal -->
<div class="modal-overlay" id="modal-attach-existing">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-attach-existing')"><i class="fas fa-times"></i></button>
    <div class="modal-head">
      <div class="modal-icon"><i class="fas fa-plug"></i></div>
      <div>
        <h3>Attach Existing Database</h3>
        <p>Registers a database that already has data (e.g. an older deployment) as a tenant — no schema is created and no existing data is modified.</p>
      </div>
    </div>
    <div class="modal-body">
      <div id="attach-alert"></div>
      <div class="field">
        <label>Database Name *</label>
        <input id="a-dbname" class="mono" placeholder="e.g. edrppymy_oldclient">
        <div style="font-size:11px;color:var(--text-mute);margin-top:4px">Exact name from cPanel → MySQL Databases</div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Company Name *</label>
          <input id="a-company" placeholder="Old Client Pvt Ltd">
        </div>
        <div class="field">
          <label>Slug (auto)</label>
          <input id="a-slug" class="mono" placeholder="old_client">
        </div>
      </div>
      <div class="field">
        <label>Owner Email * <span style="font-weight:400;font-size:11px;color:var(--text-mute)">(must match an existing user's email inside that database)</span></label>
        <input id="a-owner-email" type="email" placeholder="owner@oldclient.com">
      </div>
      <div class="field">
        <label>Business Type</label>
        <select id="a-business-type">
          <option value="service" selected>Services (Invoices, Clients, Payments)</option>
          <option value="product">Products (Sales, Purchases, Stock)</option>
          <option value="both">Both / Mixed</option>
        </select>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Phone</label>
          <input id="a-phone" placeholder="9876543210">
        </div>
        <div class="field">
          <label>Plan</label>
          <select id="a-plan">
            <option value="trial">Trial</option>
            <option value="basic">Basic</option>
            <option value="pro" selected>Pro</option>
            <option value="enterprise">Enterprise</option>
          </select>
        </div>
      </div>
      <div style="font-size:11.5px;color:var(--text-mute);background:var(--bg);border-radius:8px;padding:10px 12px;margin-top:4px">
        <i class="fas fa-circle-info"></i> Existing users keep their current passwords — nothing is reset. Users whose email is already used by another tenant will be skipped and listed after attaching, for you to resolve manually.
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-attach-existing')">Cancel</button>
      <button class="btn btn-primary" onclick="attachExistingTenant()">
        <i class="fas fa-plug"></i> Attach Database
      </button>
    </div>
  </div>
</div>
<div class="modal-overlay" id="modal-users">
  <div class="modal" style="max-width:680px">
    <button class="modal-close" onclick="closeModal('modal-users')"><i class="fas fa-times"></i></button>
    <div class="modal-head">
      <div class="modal-icon"><i class="fas fa-users"></i></div>
      <div>
        <h3 id="users-modal-title">Tenant Users</h3>
        <p id="users-modal-sub">Manage who has access to this tenant's workspace.</p>
      </div>
    </div>
    <div class="modal-body">
      <div id="users-alert"></div>
      <div class="search-box">
        <i class="fas fa-search"></i>
        <input id="users-search" placeholder="Search by name or email…" oninput="renderUsersList()">
      </div>
      <div id="users-list" style="margin-bottom:20px"></div>
      <div class="divider"></div>
      <div class="section-label">Add New User</div>
      <div class="field-row">
        <div class="field"><label>Name</label><input id="u-name" placeholder="User Name"></div>
        <div class="field"><label>Email *</label><input id="u-email" type="email" placeholder="user@company.com"></div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Role</label>
          <select id="u-role">
            <option value="admin">Admin</option>
            <option value="manager">Manager</option>
            <option value="accountant">Accountant</option>
            <option value="sales" selected>Sales</option>
            <option value="viewer">Viewer</option>
          </select>
        </div>
        <div class="field"><label>Password (blank = auto)</label><input id="u-password" class="mono" placeholder="Auto-generated"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-users')">Close</button>
      <button class="btn btn-primary" onclick="addUser()"><i class="fas fa-user-plus"></i> Add User</button>
    </div>
  </div>
</div>

<!-- ══ Edit Verification / License Modal ══════════════════════════ -->
<div class="modal-overlay" id="modal-edit-license">
  <div class="modal" style="max-width:440px">
    <button class="modal-close" onclick="closeModal('modal-edit-license')"><i class="fas fa-times"></i></button>
    <div class="modal-head">
      <div class="modal-icon"><i class="fas fa-id-card"></i></div>
      <div>
        <h3>Verification &amp; License</h3>
        <p id="el-user-label"></p>
      </div>
    </div>
    <div class="modal-body">
      <div id="el-alert"></div>
      <div class="field" style="display:flex;align-items:center;gap:9px;background:var(--surface);border:1px solid var(--border-soft);border-radius:8px;padding:10px 12px">
        <input type="checkbox" id="el-verified" style="width:16px;height:16px;accent-color:var(--accent)">
        <label style="margin:0;text-transform:none;font-size:13px;font-weight:600;color:var(--text)" for="el-verified">Mark as Verified</label>
      </div>
      <div class="field" style="margin-top:16px">
        <label>License Number</label>
        <input id="el-license-no" class="mono" placeholder="e.g. LIC-2026-0042">
      </div>
      <div class="field">
        <label>License Expiry Date</label>
        <input id="el-license-expiry" type="date">
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-edit-license')">Cancel</button>
      <button class="btn btn-primary" onclick="saveVerification()"><i class="fas fa-save"></i> Save</button>
    </div>
  </div>
</div>

<!-- ══ Plan Defaults Modal ══════════════════════════════════════ -->
<div class="modal-overlay" id="modal-plan-defaults">
  <div class="modal" style="max-width:620px">
    <button class="modal-close" onclick="closeModal('modal-plan-defaults')"><i class="fas fa-times"></i></button>
    <div class="modal-head">
      <div class="modal-icon"><i class="fas fa-sliders-h"></i></div>
      <div>
        <h3>Plan Defaults</h3>
        <p>Sets what every tenant on a plan gets by default. Individual tenants can still be overridden via their own Permissions button.</p>
      </div>
    </div>
    <div class="modal-body">
      <div id="plan-defaults-alert"></div>
      <div class="plan-tabs" id="pd-plan-tabs" style="margin-bottom:16px">
        <button type="button" class="plan-tab" data-plan="trial" onclick="selectPlanTab('trial')">Trial</button>
        <button type="button" class="plan-tab" data-plan="basic" onclick="selectPlanTab('basic')">Basic</button>
        <button type="button" class="plan-tab active" data-plan="pro" onclick="selectPlanTab('pro')">Pro</button>
        <button type="button" class="plan-tab" data-plan="enterprise" onclick="selectPlanTab('enterprise')">Enterprise</button>
      </div>
      <select id="pd-plan" style="display:none">
        <option value="trial">Trial</option>
        <option value="basic">Basic</option>
        <option value="pro" selected>Pro</option>
        <option value="enterprise">Enterprise</option>
      </select>
      <div id="plan-defaults-list" style="max-height:420px;overflow-y:auto">Loading…</div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-plan-defaults')">Close</button>
    </div>
  </div>
</div>

<!-- ══ Tenant Permissions Modal ══════════════════════════════════ -->
<div class="modal-overlay" id="modal-tenant-perms">
  <div class="modal" style="max-width:600px">
    <button class="modal-close" onclick="closeModal('modal-tenant-perms')"><i class="fas fa-times"></i></button>
    <div class="modal-head">
      <div class="modal-icon"><i class="fas fa-key"></i></div>
      <div>
        <h3 id="tenant-perms-title">Tenant Permissions</h3>
        <p>Toggles marked <strong>Override</strong> differ from this tenant's plan default. Click "Reset" to fall back to the plan.</p>
      </div>
    </div>
    <div class="modal-body">
      <div id="tenant-perms-alert"></div>
      <div id="tenant-perms-list" style="max-height:440px;overflow-y:auto">Loading…</div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-tenant-perms')">Close</button>
    </div>
  </div>
</div>

<script>
let TENANTS  = [];
let ACTIVE_TENANT_ID = null;
let PENDING_RESUME = null;

// ── Topbar search ───────────────────────────────────────────────
function filterTenants(query) {
  const q = query.trim().toLowerCase();
  const clearBtn = document.getElementById('topbar-search-clear');
  clearBtn.style.display = q ? 'flex' : 'none';
  if (!q) { renderTenants(TENANTS); return; }
  const matches = TENANTS.filter(t => [t.company_name, t.owner_email, t.slug, t.db_name]
    .some(v => (v || '').toLowerCase().includes(q)));
  renderTenants(matches);
}

(function initTopbarSearch() {
  const input = document.getElementById('topbar-search-input');
  const clearBtn = document.getElementById('topbar-search-clear');
  let debounce;
  input.addEventListener('input', () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => filterTenants(input.value), 150);
  });
  clearBtn.addEventListener('click', () => {
    input.value = '';
    filterTenants('');
    input.focus();
  });
})();

// ── User menu dropdown ──────────────────────────────────────────
(function initUserMenu() {
  const menu = document.getElementById('user-menu');
  const btn  = document.getElementById('user-chip-btn');
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    menu.classList.toggle('open');
  });
  document.addEventListener('click', (e) => {
    if (!menu.contains(e.target)) menu.classList.remove('open');
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') menu.classList.remove('open');
  });
})();

// Notifications: no backend endpoint exists yet for this — placeholder only.
document.getElementById('notif-btn')?.addEventListener('click', () => {
  console.log('Notifications: no backend wired yet.');
});

// ── Load tenants ────────────────────────────────────────────────
async function loadTenants() {
  const r    = await fetch('/api/tenant.php?action=list');
  const data = await r.json();
  TENANTS = data.data || [];

  const active    = TENANTS.filter(t => t.status === 'active').length;
  const suspended = TENANTS.filter(t => t.status === 'suspended').length;
  const users     = TENANTS.reduce((s, t) => s + parseInt(t.user_count || 0), 0);

  document.getElementById('stat-total').textContent     = TENANTS.length;
  document.getElementById('stat-active').textContent    = active;
  document.getElementById('stat-suspended').textContent = suspended;
  document.getElementById('stat-users').textContent     = users;

  renderTenants(TENANTS);
}

function renderTenants(list) {
  const tbody = document.getElementById('tenants-tbody');
  const subtitle = document.getElementById('tenants-subtitle');
  const q = (document.getElementById('topbar-search-input')?.value || '').trim();

  if (!TENANTS.length) {
    subtitle.textContent = 'No tenants yet';
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-mute)">No tenants yet — create one above</td></tr>';
    return;
  }
  if (q && !list.length) {
    subtitle.textContent = `No matches for "${q}"`;
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-mute)">No tenants match your search</td></tr>';
    return;
  }
  const totalUsers = list.reduce((s, t) => s + parseInt(t.user_count || 0), 0);
  subtitle.textContent = q
    ? `${list.length} of ${TENANTS.length} tenants matching "${q}"`
    : `${list.length} tenant${list.length===1?'':'s'} · ${totalUsers} user${totalUsers===1?'':'s'} total`;

  tbody.innerHTML = list.map(t => `
    <tr>
      <td>
        <div class="cell-primary">
          <div class="avatar-md">${esc(initials(t.company_name))}</div>
          <div>
            <div class="name">${esc(t.company_name)}</div>
            <div class="meta">${esc(t.owner_email)}</div>
          </div>
        </div>
      </td>
      <td>
        ${t.phone ? `<div class="contact-cell"><i class="fas fa-phone"></i> ${esc(t.phone)}</div>` : '<span class="muted-date">—</span>'}
      </td>
      <td>
        <div class="id-stack">
          <div class="slug mono">${esc(t.slug)}</div>
          <div class="dbn mono">${esc(t.db_name)}</div>
        </div>
      </td>
      <td><span class="badge badge-${t.plan}">${t.plan}</span></td>
      <td><span class="badge badge-${t.status}">${t.status}</span></td>
      <td><span class="count-pill"><i class="fas fa-user"></i> ${t.user_count || 0}</span></td>
      <td><span class="muted-date">${t.created_at ? t.created_at.slice(0,10) : '—'}</span></td>
      <td>
        <div class="action-group">
          <button class="btn btn-icon" onclick="openUsers(${t.id}, '${esc(t.company_name)}')" title="Users">
            <i class="fas fa-users"></i>
          </button>
          <button class="btn btn-icon" onclick="openTenantPermissions(${t.id}, '${esc(t.company_name)}')" title="Permissions">
            <i class="fas fa-key"></i>
          </button>
          ${t.status === 'active'
            ? `<button class="btn btn-icon" style="color:var(--warning)" onclick="suspendTenant(${t.id})" title="Suspend"><i class="fas fa-pause"></i></button>`
            : `<button class="btn btn-icon" style="color:#0B8A4E" onclick="activateTenant(${t.id})" title="Activate"><i class="fas fa-play"></i></button>`
          }
        </div>
      </td>
    </tr>`).join('');
}

// ── Create tenant ───────────────────────────────────────────────
function openCreateTenant() {
  document.getElementById('create-alert').innerHTML = '';
  PENDING_RESUME = null;
  document.getElementById('modal-create-tenant').classList.add('open');
}

document.getElementById('t-company').addEventListener('input', function() {
  const slug = this.value.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'');
  document.getElementById('t-slug').value = slug;
});

async function createTenant() {
  const payload = {
    company_name: document.getElementById('t-company').value.trim(),
    slug:         document.getElementById('t-slug').value.trim(),
    owner_name:   document.getElementById('t-owner-name').value.trim(),
    owner_email:  document.getElementById('t-owner-email').value.trim(),
    phone:        document.getElementById('t-phone').value.trim(),
    plan:         document.getElementById('t-plan').value,
    business_type:document.getElementById('t-business-type').value,
    password:     document.getElementById('t-password').value.trim() || undefined,
  };
  if (!payload.company_name || !payload.owner_email) {
    showAlert('create-alert', 'Company name and owner email are required', 'error');
    return;
  }
  const btn = event.target;
  btn.disabled = true; btn.textContent = 'Provisioning DB…';
  try {
    const r    = await fetch('/api/tenant.php?action=create', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const data = await r.json();
    if (data.success) {
      showAlert('create-alert',
        `✅ Tenant created!<br>
         <strong>Login:</strong> ${data.owner_email}<br>
         <strong>Password:</strong> <code>${data.temp_pass}</code><br>
         <strong>DB:</strong> ${data.db_name}<br>
         <em>Copy these credentials — password shown only once.</em>`, 'success');
      loadTenants();
    } else if (data.needs_manual_grant) {
      PENDING_RESUME = data.resume_payload;
      document.getElementById('create-alert').innerHTML = `
        <div class="alert alert-error">
          ⚠️ Database <code>${esc(data.db_name)}</code> was created, but your hosting provider
          blocked the automatic privilege grant. Finish it manually:
          <ol style="margin:8px 0 10px 18px;padding:0">
            <li>In cPanel → <strong>MySQL® Databases</strong> → "Add User To Database"</li>
            <li>User: select your master DB user (e.g. <code>edrppymy_optms_master</code>), Database: <code>${esc(data.db_name)}</code>, click Add</li>
            <li>Check <strong>ALL PRIVILEGES</strong> → Make Changes</li>
          </ol>
          Then click below to finish:
        </div>
        <button class="btn btn-primary" style="width:100%" onclick="finishProvision()">
          <i class="fas fa-check"></i> I've granted privileges — Finish Setup
        </button>`;
    } else {
      showAlert('create-alert', data.error || 'Failed', 'error');
    }
  } catch(e) {
    showAlert('create-alert', 'Network error: ' + e.message, 'error');
  } finally {
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-database"></i> Provision & Create';
  }
}

// ── Finish provisioning after a manual cPanel privilege grant ──────
async function finishProvision() {
  if (!PENDING_RESUME) {
    showAlert('create-alert', 'Nothing pending to finish — try creating the tenant again.', 'error');
    return;
  }
  const btn = event.target.closest('button');
  btn.disabled = true; btn.textContent = 'Finishing setup…';
  try {
    const r    = await fetch('/api/tenant.php?action=finish_provision', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify(PENDING_RESUME)
    });
    const data = await r.json();
    if (data.success) {
      showAlert('create-alert',
        `✅ Tenant created!<br>
         <strong>Login:</strong> ${data.owner_email}<br>
         <strong>Password:</strong> <code>${data.temp_pass}</code><br>
         <strong>DB:</strong> ${data.db_name}<br>
         <em>Copy these credentials — password shown only once.</em>`, 'success');
      PENDING_RESUME = null;
      loadTenants();
    } else {
      showAlert('create-alert', data.error || 'Still failed — check that privileges were granted in cPanel.', 'error');
      btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> I\'ve granted privileges — Finish Setup';
    }
  } catch(e) {
    showAlert('create-alert', 'Network error: ' + e.message, 'error');
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> I\'ve granted privileges — Finish Setup';
  }
}

// ── Suspend / Activate ──────────────────────────────────────────
function openAttachExisting() {
  document.getElementById('attach-alert').innerHTML = '';
  ['a-dbname','a-company','a-slug','a-owner-email','a-phone'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('modal-attach-existing').classList.add('open');
}

document.getElementById('a-company').addEventListener('input', function() {
  const slug = this.value.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'');
  document.getElementById('a-slug').value = slug;
});

async function attachExistingTenant() {
  const payload = {
    db_name:      document.getElementById('a-dbname').value.trim(),
    company_name: document.getElementById('a-company').value.trim(),
    slug:         document.getElementById('a-slug').value.trim(),
    owner_email:  document.getElementById('a-owner-email').value.trim(),
    business_type:document.getElementById('a-business-type').value,
    phone:        document.getElementById('a-phone').value.trim(),
    plan:         document.getElementById('a-plan').value,
  };
  if (!payload.db_name || !payload.company_name || !payload.owner_email) {
    showAlert('attach-alert', 'Database name, company name and owner email are required', 'error');
    return;
  }
  const btn = event.target.closest('button');
  btn.disabled = true; btn.textContent = 'Attaching…';
  try {
    const r = await fetch('/api/tenant.php?action=attach_existing', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const data = await r.json();
    if (data.success) {
      let html = `✅ ${esc(data.message)}<br><strong>Login:</strong> ${esc(data.owner_email)}<br><strong>DB:</strong> ${esc(data.db_name)}<br><em>Their existing password still works — nothing was reset.</em>`;
      if (data.migrated_users?.length) {
        html += `<br><br><strong>Migrated (${data.migrated_users.length}):</strong><br>` +
          data.migrated_users.map(u => `${esc(u.email)} (${esc(u.role)})`).join('<br>');
      }
      if (data.skipped_users?.length) {
        html += `<br><br><strong style="color:#c0392b">Needs manual review (${data.skipped_users.length}):</strong><br>` +
          data.skipped_users.map(u => `${esc(u.email||'id '+u.old_id)} — ${esc(u.reason)}`).join('<br>');
      }
      showAlert('attach-alert', html, 'success');
      loadTenants();
    } else {
      showAlert('attach-alert', data.error || 'Failed', 'error');
    }
  } catch(e) {
    showAlert('attach-alert', 'Network error: ' + e.message, 'error');
  } finally {
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-plug"></i> Attach Database';
  }
}

async function suspendTenant(id) {
  if (!confirm('Suspend this tenant? Their users will not be able to log in.')) return;
  await fetch('/api/tenant.php?action=suspend', {
    method: 'PATCH', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({id})
  });
  loadTenants();
}
async function activateTenant(id) {
  await fetch('/api/tenant.php?action=activate', {
    method: 'PATCH', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({id})
  });
  loadTenants();
}

// ── Users modal ─────────────────────────────────────────────────
async function openUsers(tenantId, companyName) {
  ACTIVE_TENANT_ID = tenantId;
  document.getElementById('users-modal-title').textContent = `Users — ${companyName}`;
  document.getElementById('users-modal-sub').textContent = 'Manage who has access to this tenant\'s workspace.';
  document.getElementById('users-alert').innerHTML = '';
  document.getElementById('users-search').value = '';
  document.getElementById('modal-users').classList.add('open');
  await loadUsers();
}

async function loadUsers() {
  const r    = await fetch(`/api/tenant.php?action=users&tenant_id=${ACTIVE_TENANT_ID}`);
  const data = await r.json();
  CURRENT_USERS = data.data || []; // cache for openEditLicense lookup and search
  document.getElementById('users-modal-sub').textContent =
    `${CURRENT_USERS.length} user${CURRENT_USERS.length===1?'':'s'} with access to this workspace`;
  renderUsersList();
}

function renderUsersList() {
  const q = (document.getElementById('users-search').value || '').toLowerCase().trim();
  const users = CURRENT_USERS.filter(u =>
    !q || (u.name||'').toLowerCase().includes(q) || (u.email||'').toLowerCase().includes(q));
  const wrap  = document.getElementById('users-list');
  if (!CURRENT_USERS.length) {
    wrap.innerHTML = '<p style="color:var(--text-mute);font-size:13px;padding:8px 0">No users yet</p>'; return;
  }
  if (!users.length) {
    wrap.innerHTML = '<p style="color:var(--text-mute);font-size:13px;padding:8px 0">No users match your search</p>'; return;
  }
  wrap.innerHTML = `<div style="overflow-x:auto"><table>
    <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Last Login</th><th>Verified</th><th>License</th><th style="text-align:right">Actions</th></tr></thead>
    <tbody>` +
    users.map(u => {
      const licenseText = u.license_no
        ? `${esc(u.license_no)}${u.license_expiry ? ' · ' + u.license_expiry.slice(0,10) : ''}`
        : '<span style="color:var(--text-mute)">—</span>';
      return `<tr>
      <td>
        <div class="cell-primary">
          <div class="avatar-md" style="width:30px;height:30px;font-size:11px">${esc(initials(u.name))}</div>
          <div>
            <div class="name" style="font-size:12.8px">${esc(u.name)}</div>
            <div class="meta">${esc(u.email)}</div>
          </div>
        </div>
      </td>
      <td><span class="badge badge-${u.role}">${u.role}</span></td>
      <td><span class="badge badge-${u.status}">${u.status}</span></td>
      <td><span class="muted-date">${u.last_login ? u.last_login.slice(0,10) : 'Never'}</span></td>
      <td>${u.is_verified == 1
        ? '<span class="verified-tag">VERIFIED</span>'
        : '<span style="font-size:11px;color:var(--text-mute)">Unverified</span>'}</td>
      <td style="font-size:11.5px" class="mono">${licenseText}</td>
      <td style="white-space:nowrap;text-align:right">
        <div class="action-group" style="display:inline-flex">
          <button class="btn btn-icon" onclick="openEditLicense(${u.id})" title="Edit verification & license"><i class="fas fa-id-card"></i></button>
          <button class="btn btn-icon" style="color:var(--danger)" onclick="removeUser(${u.id})" title="Deactivate"><i class="fas fa-times"></i></button>
        </div>
      </td>
    </tr>`;
    }).join('') +
    '</tbody></table></div>';
}

// ── Edit verification / license ─────────────────────────────────
let CURRENT_USERS = [];
let ACTIVE_EDIT_USER_ID = null;

function openEditLicense(userId) {
  const u = CURRENT_USERS.find(x => x.id == userId);
  if (!u) return;
  ACTIVE_EDIT_USER_ID = userId;
  document.getElementById('el-user-label').textContent = `${u.name} (${u.email})`;
  document.getElementById('el-verified').checked = u.is_verified == 1;
  document.getElementById('el-license-no').value = u.license_no || '';
  document.getElementById('el-license-expiry').value = u.license_expiry ? u.license_expiry.slice(0,10) : '';
  document.getElementById('el-alert').innerHTML = '';
  document.getElementById('modal-edit-license').classList.add('open');
}

async function saveVerification() {
  const payload = {
    user_id: ACTIVE_EDIT_USER_ID,
    is_verified: document.getElementById('el-verified').checked ? 1 : 0,
    license_no: document.getElementById('el-license-no').value.trim(),
    license_expiry: document.getElementById('el-license-expiry').value.trim(),
  };
  const r    = await fetch('/api/tenant.php?action=update_verification', {
    method: 'PATCH', headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });
  const data = await r.json();
  if (data.success) {
    closeModal('modal-edit-license');
    loadUsers();
  } else {
    showAlert('el-alert', data.error || 'Failed to save', 'error');
  }
}

async function addUser() {
  const payload = {
    tenant_id: ACTIVE_TENANT_ID,
    name:      document.getElementById('u-name').value.trim(),
    email:     document.getElementById('u-email').value.trim(),
    role:      document.getElementById('u-role').value,
    password:  document.getElementById('u-password').value.trim() || undefined,
  };
  if (!payload.email) { showAlert('users-alert', 'Email is required', 'error'); return; }
  const r    = await fetch('/api/tenant.php?action=add_user', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });
  const data = await r.json();
  if (data.success) {
    showAlert('users-alert',
      `✅ User added! Password: <code>${data.temp_pass}</code>`, 'success');
    loadUsers(); loadTenants();
  } else {
    showAlert('users-alert', data.error || 'Failed', 'error');
  }
}

async function removeUser(id) {
  if (!confirm('Deactivate this user?')) return;
  await fetch('/api/tenant.php?action=remove_user', {
    method: 'PATCH', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({user_id: id})
  });
  loadUsers(); loadTenants();
}

// ── Helpers ─────────────────────────────────────────────────────
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}
function showAlert(id, msg, type) {
  document.getElementById(id).innerHTML =
    `<div class="alert alert-${type}">${msg}</div>`;
}
function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                       .replace(/"/g,'&quot;');
}
function initials(name) {
  const parts = String(name||'').trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return '?';
  return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
}

// ── Category labels for grouping permission rows ────────────────
const PERM_CATEGORY_LABEL = { menu: 'Menu Items', action: 'Actions' };

function _groupPermsByCategory(rows) {
  const groups = {};
  rows.forEach(r => {
    if (!groups[r.category]) groups[r.category] = [];
    groups[r.category].push(r);
  });
  return groups;
}

// ══ Plan Defaults ════════════════════════════════════════════════
function openPlanDefaults() {
  document.getElementById('plan-defaults-alert').innerHTML = '';
  selectPlanTab('pro');
  document.getElementById('modal-plan-defaults').classList.add('open');
}

function selectPlanTab(plan) {
  document.getElementById('pd-plan').value = plan;
  document.querySelectorAll('#pd-plan-tabs .plan-tab').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.plan === plan);
  });
  loadPlanDefaults();
}

async function loadPlanDefaults() {
  const plan = document.getElementById('pd-plan').value;
  const list = document.getElementById('plan-defaults-list');
  list.innerHTML = 'Loading…';
  try {
    const r = await fetch(`/api/permissions.php?action=plan&plan=${plan}`);
    const data = await r.json();
    if (!data.success) { list.innerHTML = `<div class="alert alert-error">${esc(data.error||'Failed to load')}</div>`; return; }

    const groups = _groupPermsByCategory(data.data);
    list.innerHTML = Object.keys(groups).map(cat => {
      const rows = groups[cat];
      const enabledCount = rows.filter(p => p.enabled).length;
      return `
      <div class="perm-card">
        <div class="perm-card-head">
          <span>${esc(PERM_CATEGORY_LABEL[cat] || cat)}</span>
          <span class="count">${enabledCount}/${rows.length} enabled</span>
        </div>
        ${rows.map(p => `
          <div class="perm-row">
            <span class="lbl">${esc(p.label)}</span>
            <label class="switch">
              <input type="checkbox" ${p.enabled ? 'checked' : ''}
                     onchange="setPlanPermission('${esc(p.key)}', this.checked)">
              <span class="slider"></span>
            </label>
          </div>
        `).join('')}
      </div>`;
    }).join('');
  } catch(e) {
    list.innerHTML = `<div class="alert alert-error">Network error: ${esc(e.message)}</div>`;
  }
}

async function setPlanPermission(key, enabled) {
  const plan = document.getElementById('pd-plan').value;
  try {
    const r = await fetch('/api/permissions.php?action=set_plan', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ plan, permission_key: key, enabled })
    });
    const data = await r.json();
    if (!data.success) showAlert('plan-defaults-alert', data.error || 'Failed to save', 'error');
  } catch(e) {
    showAlert('plan-defaults-alert', 'Network error: ' + e.message, 'error');
  }
}

// ══ Tenant Permissions ═══════════════════════════════════════════
let ACTIVE_PERMS_TENANT_ID = null;

function openTenantPermissions(tenantId, companyName) {
  ACTIVE_PERMS_TENANT_ID = tenantId;
  document.getElementById('tenant-perms-title').textContent = `Permissions — ${companyName}`;
  document.getElementById('tenant-perms-alert').innerHTML = '';
  document.getElementById('modal-tenant-perms').classList.add('open');
  loadTenantPermissions();
}

async function loadTenantPermissions() {
  const list = document.getElementById('tenant-perms-list');
  list.innerHTML = 'Loading…';
  try {
    const r = await fetch(`/api/permissions.php?action=tenant&tenant_id=${ACTIVE_PERMS_TENANT_ID}`);
    const data = await r.json();
    if (!data.success) { list.innerHTML = `<div class="alert alert-error">${esc(data.error||'Failed to load')}</div>`; return; }

    const groups = _groupPermsByCategory(data.data);
    const total = data.data.length;
    const overrideCount = data.data.filter(p => p.is_override).length;
    const enabledCount = data.data.filter(p => p.effective).length;
    list.innerHTML = `
      <div class="info-strip">
        <span>Plan: <strong>${esc(data.tenant.plan)}</strong></span>
        <span><strong>${enabledCount}</strong>/${total} enabled</span>
        <span>${overrideCount ? `<strong style="color:var(--warning)">${overrideCount}</strong> override${overrideCount===1?'':'s'}` : 'No overrides'}</span>
      </div>
      ` + Object.keys(groups).map(cat => {
        const rows = groups[cat];
        return `
      <div class="perm-card">
        <div class="perm-card-head">
          <span>${esc(PERM_CATEGORY_LABEL[cat] || cat)}</span>
          <span class="count">${rows.filter(p=>p.effective).length}/${rows.length} enabled</span>
        </div>
        ${rows.map(p => `
          <div class="perm-row">
            <span class="lbl">
              ${esc(p.label)}
              ${p.is_override ? '<span class="override-tag">OVERRIDE</span>' : ''}
            </span>
            <div style="display:flex;align-items:center;gap:12px">
              ${p.is_override ? `<button onclick="clearTenantOverride('${esc(p.key)}')" style="font-size:11px;color:var(--accent-dark);background:none;border:none;cursor:pointer;text-decoration:underline;font-weight:600">Reset</button>` : ''}
              <label class="switch">
                <input type="checkbox" ${p.effective ? 'checked' : ''}
                       onchange="setTenantOverride('${esc(p.key)}', this.checked)">
                <span class="slider"></span>
              </label>
            </div>
          </div>
        `).join('')}
      </div>`;
      }).join('');
  } catch(e) {
    list.innerHTML = `<div class="alert alert-error">Network error: ${esc(e.message)}</div>`;
  }
}

async function setTenantOverride(key, enabled) {
  try {
    const r = await fetch('/api/permissions.php?action=set_tenant_override', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ tenant_id: ACTIVE_PERMS_TENANT_ID, permission_key: key, enabled })
    });
    const data = await r.json();
    if (!data.success) { showAlert('tenant-perms-alert', data.error || 'Failed to save', 'error'); return; }
    loadTenantPermissions(); // refresh to show the OVERRIDE badge
  } catch(e) {
    showAlert('tenant-perms-alert', 'Network error: ' + e.message, 'error');
  }
}

async function clearTenantOverride(key) {
  try {
    const r = await fetch('/api/permissions.php?action=clear_tenant_override', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ tenant_id: ACTIVE_PERMS_TENANT_ID, permission_key: key })
    });
    const data = await r.json();
    if (!data.success) { showAlert('tenant-perms-alert', data.error || 'Failed to reset', 'error'); return; }
    loadTenantPermissions();
  } catch(e) {
    showAlert('tenant-perms-alert', 'Network error: ' + e.message, 'error');
  }
}

// ── Init ─────────────────────────────────────────────────────────
loadTenants();

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', e => {
    if (e.target === el) el.classList.remove('open');
  });
});
</script>
</body>
</html>