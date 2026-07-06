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
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Public Sans',sans-serif;background:#F0F2F5;min-height:100vh;color:#1A2332}
.topbar{background:#1A2332;color:#fff;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar-brand{font-size:16px;font-weight:800;display:flex;align-items:center;gap:10px}
.topbar-brand span{background:linear-gradient(135deg,#00897B,#4DB6AC);padding:4px 10px;border-radius:6px;font-size:12px;font-weight:700}
.topbar-right{display:flex;align-items:center;gap:16px;font-size:13px}
.topbar-right a{color:#9CA3AF;text-decoration:none}
.topbar-right a:hover{color:#fff}
.container{max-width:1200px;margin:0 auto;padding:28px 20px}
.page-title{font-size:22px;font-weight:800;margin-bottom:24px}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:28px}
.stat-card{background:#fff;border-radius:12px;padding:18px 20px;border:1px solid #E5E7EB}
.stat-card .val{font-size:28px;font-weight:800;color:#1A2332}
.stat-card .lbl{font-size:12px;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}
.card{background:#fff;border-radius:12px;border:1px solid #E5E7EB;margin-bottom:24px;overflow:hidden}
.card-header{padding:16px 20px;border-bottom:1px solid #E5E7EB;display:flex;align-items:center;justify-content:space-between}
.card-header h3{font-size:15px;font-weight:700}
.btn{padding:9px 18px;border-radius:8px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;border:none;transition:.15s}
.btn-primary{background:#00897B;color:#fff}
.btn-primary:hover{background:#00695C}
.btn-danger{background:#FEE2E2;color:#DC2626;border:1px solid #FECACA}
.btn-danger:hover{background:#FCA5A5;color:#fff}
.btn-outline{background:#fff;border:1.5px solid #E5E7EB;color:#374151}
.btn-outline:hover{border-color:#00897B;color:#00897B}
.btn-sm{padding:5px 12px;font-size:12px}
table{width:100%;border-collapse:collapse}
th{padding:10px 16px;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;text-align:left;border-bottom:1px solid #F3F4F6;background:#F9FAFB}
td{padding:12px 16px;font-size:13px;border-bottom:1px solid #F3F4F6;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#FAFAFA}
.badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700}
.badge-active{background:#D1FAE5;color:#065F46}
.badge-suspended{background:#FEE2E2;color:#DC2626}
.badge-trial{background:#EDE9FE;color:#5B21B6}
.badge-pro{background:#DBEAFE;color:#1D4ED8}
.badge-basic{background:#FEF3C7;color:#D97706}
.badge-owner{background:#E0F2FE;color:#0369A1}
.badge-admin{background:#F3E8FF;color:#7E22CE}
.badge-manager{background:#ECFDF5;color:#065F46}
.badge-accountant{background:#FFF7ED;color:#C2410C}
.badge-sales{background:#F0FDF4;color:#16A34A}
.badge-viewer{background:#F9FAFB;color:#6B7280;border:1px solid #E5E7EB}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:16px;padding:28px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto}
.modal h3{font-size:17px;font-weight:800;margin-bottom:20px}
.field{margin-bottom:14px}
.field label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px}
.field input,.field select{width:100%;padding:10px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-family:inherit;font-size:13px;outline:none}
.field input:focus,.field select:focus{border-color:#00897B}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.alert{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
.alert-success{background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7}
.alert-error{background:#FEE2E2;color:#DC2626;border:1px solid #FECACA}
</style>
</head>
<body>
<div class="topbar">
  <div class="topbar-brand">
    <i class="fas fa-shield-alt"></i>
    OPTMS Super Admin
    <span>v<?= APP_VERSION ?></span>
  </div>
  <div class="topbar-right">
    <span>👤 <?= htmlspecialchars($user['name'] ?? 'Super Admin') ?></span>
    <a href="/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>
</div>

<div class="container">
  <div class="page-title">Tenant Management</div>

  <!-- Stats -->
  <div class="stats-row" id="stats-row">
    <div class="stat-card"><div class="val" id="stat-total">—</div><div class="lbl">Total Tenants</div></div>
    <div class="stat-card"><div class="val" id="stat-active" style="color:#00897B">—</div><div class="lbl">Active</div></div>
    <div class="stat-card"><div class="val" id="stat-suspended" style="color:#E53935">—</div><div class="lbl">Suspended</div></div>
    <div class="stat-card"><div class="val" id="stat-users" style="color:#1565C0">—</div><div class="lbl">Total Users</div></div>
  </div>

  <!-- Tenants Table -->
  <div class="card">
    <div class="card-header">
      <h3>All Tenants</h3>
      <div style="display:flex;gap:8px">
        <button class="btn btn-outline" onclick="openPlanDefaults()">
          <i class="fas fa-sliders-h"></i> Plan Defaults
        </button>
        <button class="btn btn-primary" onclick="openCreateTenant()">
          <i class="fas fa-plus"></i> New Tenant
        </button>
      </div>
    </div>
    <div id="tenants-table-wrap">
      <table>
        <thead>
          <tr>
            <th>Company</th><th>Slug</th><th>Plan</th><th>Status</th>
            <th>Users</th><th>DB Name</th><th>Created</th><th>Actions</th>
          </tr>
        </thead>
        <tbody id="tenants-tbody">
          <tr><td colspan="8" style="text-align:center;padding:30px;color:#9CA3AF">Loading…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Create Tenant Modal -->
<div class="modal-overlay" id="modal-create-tenant">
  <div class="modal">
    <h3>➕ New Tenant</h3>
    <div id="create-alert"></div>
    <div class="field-row">
      <div class="field">
        <label>Company Name *</label>
        <input id="t-company" placeholder="Acme Corp">
      </div>
      <div class="field">
        <label>Slug (auto)</label>
        <input id="t-slug" placeholder="acme_corp">
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
      <label>Temp Password (leave blank to auto-generate)</label>
      <input id="t-password" placeholder="Auto-generated if blank">
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
      <button class="btn btn-outline" onclick="closeModal('modal-create-tenant')">Cancel</button>
      <button class="btn btn-primary" onclick="createTenant()">
        <i class="fas fa-database"></i> Provision & Create
      </button>
    </div>
  </div>
</div>

<!-- Tenant Users Modal -->
<div class="modal-overlay" id="modal-users">
  <div class="modal" style="max-width:640px">
    <h3 id="users-modal-title">Tenant Users</h3>
    <div id="users-alert"></div>
    <div id="users-list" style="margin-bottom:20px"></div>
    <hr style="margin-bottom:16px;border:none;border-top:1px solid #E5E7EB">
    <div style="font-size:13px;font-weight:700;margin-bottom:10px;color:#374151">Add New User</div>
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
      <div class="field"><label>Password (blank = auto)</label><input id="u-password" placeholder="Auto-generated"></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button class="btn btn-outline" onclick="closeModal('modal-users')">Close</button>
      <button class="btn btn-primary" onclick="addUser()"><i class="fas fa-user-plus"></i> Add User</button>
    </div>
  </div>
</div>

<!-- ══ Edit Verification / License Modal ══════════════════════════ -->
<div class="modal-overlay" id="modal-edit-license">
  <div class="modal" style="max-width:420px">
    <h3><i class="fas fa-id-card"></i> Verification &amp; License</h3>
    <p id="el-user-label" style="font-size:12.5px;color:#6B7280;margin:-8px 0 14px"></p>
    <div id="el-alert"></div>
    <div class="field" style="margin-bottom:14px;display:flex;align-items:center;gap:8px">
      <input type="checkbox" id="el-verified" style="width:16px;height:16px">
      <label style="margin:0" for="el-verified">Mark as Verified</label>
    </div>
    <div class="field" style="margin-bottom:14px">
      <label>License Number</label>
      <input id="el-license-no" placeholder="e.g. LIC-2026-0042">
    </div>
    <div class="field" style="margin-bottom:16px">
      <label>License Expiry Date</label>
      <input id="el-license-expiry" type="date">
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button class="btn btn-outline" onclick="closeModal('modal-edit-license')">Cancel</button>
      <button class="btn btn-primary" onclick="saveVerification()"><i class="fas fa-save"></i> Save</button>
    </div>
  </div>
</div>

<!-- ══ Plan Defaults Modal ══════════════════════════════════════ -->
<div class="modal-overlay" id="modal-plan-defaults">
  <div class="modal" style="max-width:600px">
    <h3><i class="fas fa-sliders-h"></i> Plan Defaults</h3>
    <p style="font-size:12.5px;color:#6B7280;margin:-8px 0 14px">
      Sets what every tenant on a plan gets by default. Individual tenants can still be
      overridden separately via their own Permissions button.
    </p>
    <div id="plan-defaults-alert"></div>
    <div class="field" style="margin-bottom:14px">
      <label>Plan</label>
      <select id="pd-plan" onchange="loadPlanDefaults()">
        <option value="trial">Trial</option>
        <option value="basic">Basic</option>
        <option value="pro" selected>Pro</option>
        <option value="enterprise">Enterprise</option>
      </select>
    </div>
    <div id="plan-defaults-list" style="max-height:420px;overflow-y:auto;margin-bottom:16px">Loading…</div>
    <div style="display:flex;justify-content:flex-end">
      <button class="btn btn-outline" onclick="closeModal('modal-plan-defaults')">Close</button>
    </div>
  </div>
</div>

<!-- ══ Tenant Permissions Modal ══════════════════════════════════ -->
<div class="modal-overlay" id="modal-tenant-perms">
  <div class="modal" style="max-width:600px">
    <h3 id="tenant-perms-title"><i class="fas fa-key"></i> Tenant Permissions</h3>
    <p style="font-size:12.5px;color:#6B7280;margin:-8px 0 14px">
      Green toggles marked <strong>Override</strong> differ from this tenant's plan default.
      Click "Reset" to remove an override and fall back to the plan.
    </p>
    <div id="tenant-perms-alert"></div>
    <div id="tenant-perms-list" style="max-height:440px;overflow-y:auto;margin-bottom:16px">Loading…</div>
    <div style="display:flex;justify-content:flex-end">
      <button class="btn btn-outline" onclick="closeModal('modal-tenant-perms')">Close</button>
    </div>
  </div>
</div>

<script>
let TENANTS  = [];
let ACTIVE_TENANT_ID = null;
let PENDING_RESUME = null;

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

  const tbody = document.getElementById('tenants-tbody');
  if (!TENANTS.length) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:#9CA3AF">No tenants yet — create one above</td></tr>';
    return;
  }
  tbody.innerHTML = TENANTS.map(t => `
    <tr>
      <td><strong>${esc(t.company_name)}</strong><br><span style="font-size:11px;color:#9CA3AF">${esc(t.owner_email)}</span></td>
      <td><code style="font-size:11px;background:#F3F4F6;padding:2px 6px;border-radius:4px">${esc(t.slug)}</code></td>
      <td><span class="badge badge-${t.plan}">${t.plan}</span></td>
      <td><span class="badge badge-${t.status}">${t.status}</span></td>
      <td style="text-align:center">${t.user_count || 0}</td>
      <td><code style="font-size:10px;color:#9CA3AF">${esc(t.db_name)}</code></td>
      <td style="font-size:11px;color:#9CA3AF">${t.created_at ? t.created_at.slice(0,10) : '—'}</td>
      <td>
        <div style="display:flex;gap:6px">
          <button class="btn btn-outline btn-sm" onclick="openUsers(${t.id}, '${esc(t.company_name)}')">
            <i class="fas fa-users"></i>
          </button>
          <button class="btn btn-outline btn-sm" onclick="openTenantPermissions(${t.id}, '${esc(t.company_name)}')" title="Permissions">
            <i class="fas fa-key"></i>
          </button>
          ${t.status === 'active'
            ? `<button class="btn btn-sm" style="background:#FEF3C7;color:#D97706;border:1px solid #FDE68A" onclick="suspendTenant(${t.id})"><i class="fas fa-pause"></i></button>`
            : `<button class="btn btn-sm" style="background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7" onclick="activateTenant(${t.id})"><i class="fas fa-play"></i></button>`
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
  document.getElementById('users-alert').innerHTML = '';
  document.getElementById('modal-users').classList.add('open');
  await loadUsers();
}

async function loadUsers() {
  const r    = await fetch(`/api/tenant.php?action=users&tenant_id=${ACTIVE_TENANT_ID}`);
  const data = await r.json();
  const users = data.data || [];
  CURRENT_USERS = users; // cache for openEditLicense lookup
  const wrap  = document.getElementById('users-list');
  if (!users.length) {
    wrap.innerHTML = '<p style="color:#9CA3AF;font-size:13px">No users yet</p>'; return;
  }
  wrap.innerHTML = `<table>
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Verified</th><th>License</th><th></th></tr></thead>
    <tbody>` +
    users.map(u => {
      const licenseText = u.license_no
        ? `${esc(u.license_no)}${u.license_expiry ? ' · ' + u.license_expiry.slice(0,10) : ''}`
        : '<span style="color:#9CA3AF">—</span>';
      return `<tr>
      <td>${esc(u.name)}</td>
      <td style="font-size:12px">${esc(u.email)}</td>
      <td><span class="badge badge-${u.role}">${u.role}</span></td>
      <td><span class="badge badge-${u.status}">${u.status}</span></td>
      <td style="font-size:11px;color:#9CA3AF">${u.last_login ? u.last_login.slice(0,10) : 'Never'}</td>
      <td>${u.is_verified == 1
        ? '<span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px;background:#E3F2FD;color:#1565C0">VERIFIED</span>'
        : '<span style="font-size:10px;color:#9CA3AF">Unverified</span>'}</td>
      <td style="font-size:11.5px">${licenseText}</td>
      <td style="white-space:nowrap">
        <button class="btn btn-outline btn-sm" onclick="openEditLicense(${u.id})" title="Edit verification & license"><i class="fas fa-id-card"></i></button>
        <button class="btn btn-danger btn-sm" onclick="removeUser(${u.id})"><i class="fas fa-times"></i></button>
      </td>
    </tr>`;
    }).join('') +
    '</tbody></table>';
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
  document.getElementById('modal-plan-defaults').classList.add('open');
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
    list.innerHTML = Object.keys(groups).map(cat => `
      <div style="margin-bottom:14px">
        <div style="font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">
          ${esc(PERM_CATEGORY_LABEL[cat] || cat)}
        </div>
        ${groups[cat].map(p => `
          <label style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F3F4F6;cursor:pointer">
            <span style="font-size:13px;color:#374151">${esc(p.label)}</span>
            <input type="checkbox" ${p.enabled ? 'checked' : ''}
                   onchange="setPlanPermission('${esc(p.key)}', this.checked)"
                   style="width:18px;height:18px;accent-color:#059669;cursor:pointer">
          </label>
        `).join('')}
      </div>
    `).join('');
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
  document.getElementById('tenant-perms-title').innerHTML =
    `<i class="fas fa-key"></i> Permissions — ${esc(companyName)}`;
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
    list.innerHTML = `
      <div style="font-size:11px;color:#9CA3AF;margin-bottom:10px">
        Plan: <strong style="color:#374151">${esc(data.tenant.plan)}</strong>
      </div>
      ` + Object.keys(groups).map(cat => `
      <div style="margin-bottom:14px">
        <div style="font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">
          ${esc(PERM_CATEGORY_LABEL[cat] || cat)}
        </div>
        ${groups[cat].map(p => `
          <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F3F4F6">
            <span style="font-size:13px;color:#374151;display:flex;align-items:center;gap:8px">
              ${esc(p.label)}
              ${p.is_override ? '<span style="font-size:10px;font-weight:700;color:#B45309;background:#FEF3C7;padding:1px 7px;border-radius:10px">OVERRIDE</span>' : ''}
            </span>
            <div style="display:flex;align-items:center;gap:10px">
              ${p.is_override ? `<button onclick="clearTenantOverride('${esc(p.key)}')" style="font-size:11px;color:#2563EB;background:none;border:none;cursor:pointer;text-decoration:underline">Reset</button>` : ''}
              <input type="checkbox" ${p.effective ? 'checked' : ''}
                     onchange="setTenantOverride('${esc(p.key)}', this.checked)"
                     style="width:18px;height:18px;accent-color:#059669;cursor:pointer">
            </div>
          </div>
        `).join('')}
      </div>
    `).join('');
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