// ================================================================
//  assets/js/common.js
//  Shared helpers loaded on every page (see layout_footer.php).
//  Extracted from the old index.php SPA — functions that don't
//  depend on the in-memory STATE object were kept as-is; a couple
//  were adjusted for MPA and are flagged below with NOTE:.
// ================================================================

// ── API helper (unchanged from the SPA) ──────────────────────────
async function api(endpoint, method, body) {
  method = method || 'GET';
  // MPA FIX: every api() call site across the app was written as a
  // relative path (e.g. 'api/team.php') back when everything lived
  // at the site root under the old SPA. Now that pages live at nested
  // paths like /pages/admin/team.php, that same relative path resolves
  // against the CURRENT PAGE's directory, not the site root — hitting
  // a URL that doesn't exist, silently falling through to whatever
  // catch-all routing exists there instead of the real API endpoint.
  // Normalizing here (root-relative unless already absolute or a full
  // URL) fixes every call site in one place instead of editing 151
  // individual api() calls across 27 files.
  if (!/^(https?:)?\//.test(endpoint)) {
    endpoint = '/' + endpoint;
  }
  const opts = { method, headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
  if (body) opts.body = JSON.stringify(body);
  const res = await fetch(endpoint, opts);
  const text = await res.text();
  let data;
  try { data = JSON.parse(text); }
  catch (e) {
    console.error('API response not JSON from', endpoint, '\nResponse:', text.substring(0, 300));
    throw new Error('Server returned non-JSON response. Check PHP error logs.');
  }
  if (res.status === 401) { window.location.href = '/auth/login.php'; throw new Error('Not authenticated'); }
  if (!res.ok) throw new Error(data.error || 'API error ' + res.status);
  return data;
}

// ══════════════════════════════════════════
// TOAST
// ══════════════════════════════════════════
function toast(msg, type = 'success') {
  const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle' };
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<i class="fas ${icons[type] || 'fa-check-circle'}"></i><span>${msg}</span>`;
  document.getElementById('toastContainer').appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateY(10px)'; setTimeout(() => el.remove(), 300); }, 3200);
}

// ══════════════════════════════════════════
// UTILS
// ══════════════════════════════════════════
function getInitials(name) {
  const words = (name || '').split(/\s+/).filter(Boolean);
  if (words.length >= 2) return (words[0][0] + words[words.length - 1][0]).toUpperCase();
  return (name || '?').slice(0, 2).toUpperCase();
}

function downloadFile(name, content, type) {
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([content], { type }));
  a.download = name;
  a.click();
}

function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

// ══════════════════════════════════════════
// SIDEBAR
// NOTE: the SPA only toggled a class — collapse state reset on
// every "page" switch anyway since it never actually reloaded.
// In the MPA every nav is a real page load, so without persistence
// the sidebar would snap back open on every click. Added
// localStorage so the collapsed state now survives navigation.
// ══════════════════════════════════════════
function toggleSidebar() {
  const sb  = document.getElementById('sidebar');
  const btn = document.getElementById('sidebarToggle');
  sb.classList.toggle('collapsed');
  const collapsed = sb.classList.contains('collapsed');
  btn.style.left = collapsed ? '63px' : (parseInt(getComputedStyle(document.documentElement).getPropertyValue('--sidebar-w')) || 240) - 1 + 'px';
  btn.querySelector('i').className = collapsed ? 'fas fa-chevron-right' : 'fas fa-bars';
  localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
}

(function restoreSidebarState() {
  document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('sidebarCollapsed') === '1') {
      const sb  = document.getElementById('sidebar');
      const btn = document.getElementById('sidebarToggle');
      if (!sb || !btn) return;
      sb.classList.add('collapsed');
      btn.style.left = '63px';
      const icon = btn.querySelector('i');
      if (icon) icon.className = 'fas fa-chevron-right';
    }
  });
})();

// ══════════════════════════════════════════
// USER DROPDOWN (topbar chip)
// ══════════════════════════════════════════
window.toggleUserDropdown = function (e) {
  e.stopPropagation();
  const panel   = document.getElementById('userDropdown');
  const chevron = document.getElementById('userChipChevron');
  const isOpen  = panel.classList.contains('open');
  document.getElementById('notifPanel')?.classList.remove('open');
  panel.classList.toggle('open', !isOpen);
  if (chevron) chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
};
window.closeUserDropdown = function () {
  document.getElementById('userDropdown')?.classList.remove('open');
  const chev = document.getElementById('userChipChevron');
  if (chev) chev.style.transform = '';
};
document.addEventListener('click', function (e) {
  if (!document.getElementById('userChipBtn')?.contains(e.target)) closeUserDropdown();
});

// ══════════════════════════════════════════
// LOGOUT
// NOTE: unchanged — already pointed at a real URL
// (/auth/logout.php), so it needed no MPA adjustment.
// ══════════════════════════════════════════
window.confirmLogout = async function () {
  const r = await Swal.fire({
    title: 'Sign out?',
    text:  'You will be returned to the login page.',
    icon:  'question',
    showCancelButton:   true,
    confirmButtonText:  'Sign Out',
    cancelButtonText:   'Stay',
    confirmButtonColor: '#E53935',
  });
  if (r.isConfirmed) window.location.href = '/auth/logout.php';
};

// ══════════════════════════════════════════
// NOTIFICATIONS PANEL
// ══════════════════════════════════════════
function toggleNotifPanel(e) {
  if (e) e.stopPropagation();
  const panel = document.getElementById('notifPanel');
  panel.classList.toggle('open');
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
// GLOBAL SEARCH (topbar)
// NOTE: in the SPA this searched an in-memory STATE.invoices /
// STATE.clients array that had already been loaded for the whole
// app. That global STATE doesn't exist per-page in the MPA, and
// there's no api/search.php endpoint yet to back this properly.
// Left as a safe no-op for now rather than porting code that would
// throw on every keystroke — flagging this as a small follow-up
// task (either a dedicated search endpoint, or drop the topbar
// search until one exists).
// ══════════════════════════════════════════
function globalSearchFn(val) {
  const el = document.getElementById('searchResults');
  if (!el) return;
  el.classList.remove('open');
  // TODO: wire up to a real api/search.php endpoint.
}

// ══════════════════════════════════════════
// PRINT HELPERS — shared by Sales, Purchases (and eventually Invoices)
// print/PDF views. Moved here from the old SPA's Sales module block
// since Purchases printing needs them too and previously didn't have
// them in purchases.js (a pre-existing gap in the MPA build, not
// something introduced here — flagging so purchase-invoice printing
// can be wired up using these same helpers).
// ══════════════════════════════════════════
function pneCompanyInfo() {
  const s = STATE.settings || {};
  return {
    name: s.company || 'Your Company', gst: s.gst || '', phone: s.phone || '', email: s.email || '',
    address: s.address || '', fssai: s.fssai || '', iec: s.iec || '', logo: s.logo || '',
    pan: s.pan || '', apeda: s.apeda || '', cin: s.cin || '', msme: s.msme || '',
  };
}

function numToWordsINR(amount) {
  amount = Math.round(parseFloat(amount) || 0);
  if (amount === 0) return 'Zero Rupees Only';
  const ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
  const tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
  function two(n) { return n < 20 ? ones[n] : tens[Math.floor(n/10)] + (n%10 ? ' ' + ones[n%10] : ''); }
  function three(n) { return (n >= 100 ? ones[Math.floor(n/100)] + ' Hundred ' : '') + two(n % 100); }
  let n = amount, parts = [];
  const crore = Math.floor(n / 10000000); n %= 10000000;
  const lakh = Math.floor(n / 100000); n %= 100000;
  const thousand = Math.floor(n / 1000); n %= 1000;
  if (crore) parts.push(three(crore) + ' Crore');
  if (lakh) parts.push(three(lakh) + ' Lakh');
  if (thousand) parts.push(three(thousand) + ' Thousand');
  if (n) parts.push(three(n));
  return (parts.join(' ').trim() || 'Zero') + ' Rupees Only';
}

// ══════════════════════════════════════════
// LOGO / SIGNATURE UPLOAD — shared by create.php, settings.php, and
// whatsapp.php (festival campaign image). Consolidated here since it
// was duplicated identically in settings.js and whatsapp.js.
// ══════════════════════════════════════════
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

// ══════════════════════════════════════════
// ACTION PERMISSION HELPERS (generic, used across every list page)
// canDo() reads from SERVER.canDelete/canArchive/canEdit/canCreate,
// which are set server-side (layout_header.php) from role_permissions
// at page load — can't be faked client-side.
// ══════════════════════════════════════════
function canDo(action) {
  if (action === 'delete')  return SERVER.canDelete  === true;
  if (action === 'archive') return SERVER.canArchive === true;
  if (action === 'edit')    return SERVER.canEdit    === true;
  if (action === 'create')  return SERVER.canCreate  === true;
  return true;
}

// Render a delete button — shows a lock icon with tooltip when restricted
function delBtn(onclick, title = 'Delete') {
  if (canDo('delete')) {
    return `<button onclick="${onclick}" class="act-btn" style="color:var(--red)" title="${title}"><i class="fas fa-trash"></i></button>`;
  }
  return `<button disabled class="act-btn" style="color:var(--muted);cursor:not-allowed" title="Delete restricted by your role"><i class="fas fa-lock"></i></button>`;
}

// Render a delete menu item — grayed out with lock when restricted
function delMenuItem(onclick, label = 'Delete') {
  if (canDo('delete')) {
    return `<button onclick="${onclick}"><i class="fas fa-trash" style="color:#E53935"></i> ${label}</button>`;
  }
  return `<button disabled style="opacity:.45;cursor:not-allowed;pointer-events:none" title="Delete restricted by your role"><i class="fas fa-lock" style="color:var(--muted)"></i> ${label} <span style="font-size:10px;color:var(--muted)">(restricted)</span></button>`;
}

// Render an archive menu item — grayed out with lock when restricted
function archiveMenuItem(onclick, label = 'Archive') {
  if (canDo('archive')) {
    return `<button onclick="${onclick}"><i class="fas fa-box-archive" style="color:#E65100"></i> ${label}</button>`;
  }
  return `<button disabled style="opacity:.45;cursor:not-allowed;pointer-events:none" title="Archive restricted by your role"><i class="fas fa-lock" style="color:var(--muted)"></i> ${label} <span style="font-size:10px;color:var(--muted)">(restricted)</span></button>`;
}

// Guard: call before executing any delete — shows a clear error if restricted
function assertCanDelete(entityName = 'this record') {
  if (!canDo('delete')) {
    Swal.fire({ title: 'Permission Denied', html: `You don't have permission to delete ${entityName}.<br><small style="color:var(--muted)">Ask your Admin or Owner to grant delete access via Team Settings.</small>`, icon: 'error', confirmButtonColor: 'var(--teal)', customClass: { popup: 'swal-compact' } });
    return false;
  }
  return true;
}

function assertCanArchive(entityName = 'this record') {
  if (!canDo('archive')) {
    Swal.fire({ title: 'Permission Denied', html: `You don't have permission to archive ${entityName}.<br><small style="color:var(--muted)">Ask your Admin or Owner to grant archive access via Team Settings.</small>`, icon: 'error', confirmButtonColor: 'var(--teal)', customClass: { popup: 'swal-compact' } });
    return false;
  }
  return true;
}

// Short-form helpers for use inside template literals — avoids nested backtick
// syntax which breaks the outer template literal in JS row builders.
// These are called with a plain string like _delItem("deleteX("+id+")")
function _delItem(onclick, label='Delete') { return delMenuItem(onclick, label); }
function _archiveItem(onclick, label='Archive') { return archiveMenuItem(onclick, label); }

function toggleActMenu(ev, btn) {
  ev.stopPropagation();
  const menu = btn.parentElement.querySelector('.act-menu');
  const wasOpen = menu.classList.contains('open');
  document.querySelectorAll('.act-menu.open').forEach(m => { m.classList.remove('open'); m.classList.remove('act-menu-up'); });
  if (!wasOpen) {
    menu.classList.add('open');
    // Two things can clip this menu: the browser viewport, and the
    // .table-card ancestor itself (overflow:hidden, for its rounded
    // corners) — the table card's own bottom edge is usually what's
    // actually cutting it off, not the viewport. Check both and flip
    // upward if either would clip it.
    const rect = btn.getBoundingClientRect();
    const menuHeight = menu.offsetHeight || 160;
    const clipAncestor = btn.closest('.table-card, .pne-card') || document.body;
    const clipRect = clipAncestor.getBoundingClientRect();
    const spaceBelowViewport = window.innerHeight - rect.bottom;
    const spaceBelowCard = clipRect.bottom - rect.bottom;
    if (Math.min(spaceBelowViewport, spaceBelowCard) < menuHeight + 12) {
      menu.classList.add('act-menu-up');
    }
  }
}
document.addEventListener('click', () => {
  document.querySelectorAll('.act-menu.open').forEach(m => { m.classList.remove('open'); m.classList.remove('act-menu-up'); });
});

// ══════════════════════════════════════════
// NOTIFICATION BELL — populates the topbar panel (toggleNotifPanel/
// clearNotifs already existed; this is what fills in the content,
// including pending edit-approval requests for admins/owners).
// ══════════════════════════════════════════
function renderNotifications() {
  const today  = new Date();
  const items  = [];

  // ── Pending edit approval requests (admin/owner only) ─────────
  // Shown at the top of the bell panel so they're impossible to miss
  // regardless of which page the admin is currently on.
  if (SERVER.canApproveEdits && EAR_ADMIN_PENDING.length) {
    EAR_ADMIN_PENDING.forEach(req => {
      items.push({
        type: 'approval',
        id: req.id,
        text: `<b>${escHtml(req.requester_name)}</b> wants to edit <b>${escHtml(req.entity_label || req.entity_type + ' #' + req.entity_id)}</b>`,
        reason: req.reason,
      });
    });
  }

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
    const dueDate = new Date(inv.due).toLocaleDateString(_moneyLocale(),{day:'2-digit',month:'short'});
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
      el.innerHTML = items.map(n => {
        if (n.type === 'approval') {
          return `<div class="np-item" style="background:var(--amber-bg);border-left:3px solid var(--amber);flex-direction:column;align-items:flex-start;gap:6px">
            <div style="display:flex;gap:8px;align-items:flex-start">
              <i class="fas fa-shield-halved" style="color:var(--amber);margin-top:2px;flex-shrink:0"></i>
              <div>
                <div style="font-size:12.5px">${n.text}</div>
                ${n.reason ? `<div style="font-size:11px;color:var(--muted);font-style:italic;margin-top:2px">"${escHtml(n.reason)}"</div>` : ''}
              </div>
            </div>
            <div style="display:flex;gap:6px;padding-left:20px;width:100%">
              <button class="btn btn-primary" style="flex:1;font-size:11px;padding:4px 0" onclick="approveEditRequest(${n.id});toggleNotifPanel(event)"><i class="fas fa-check"></i> Approve</button>
              <button class="btn btn-outline" style="flex:1;font-size:11px;padding:4px 0;color:var(--red);border-color:var(--red)" onclick="rejectEditRequest(${n.id});toggleNotifPanel(event)"><i class="fas fa-times"></i> Reject</button>
            </div>
          </div>`;
        }
        return `<div class="np-item ${n.type==='warn'?'np-warn':'np-info'}">
          <i class="fas ${n.type==='warn'?'fa-exclamation-circle':'fa-info-circle'}"></i>
          <div>${n.text}</div>
        </div>`;
      }).join('');
    }
  }

  // Update bell count — includes approvals
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
    active_template: document.getElementById('sd-tpl')?.value     || '2',
    invoice_prefix:  document.getElementById('sd-prefix')?.value  || STATE.settings.prefix || 'OT-',
    estimate_prefix: document.getElementById('sd-estimate-prefix')?.value || STATE.settings.estPrefix || 'QT-',
    default_currency:document.getElementById('sd-currency')?.value|| '₹',
    default_bank:    document.getElementById('sd-bank')?.value    || '',
    default_notes:   document.getElementById('sd-notes')?.value   || '',
    default_tnc:     document.getElementById('sd-tnc')?.value     || '',
    generated_by:    document.getElementById('f-generated-by')?.value || '',
  };
  // Also update STATE
  STATE.settings.defaultGST     = parseInt(payload.default_gst ?? '0');
  STATE.settings.dueDays        = parseInt(payload.due_days);
  STATE.settings.activeTemplate = payload.active_template || STATE.settings.activeTemplate || '2';
  STATE.settings.activeTemplate = payload.active_template || STATE.settings.activeTemplate || '2';
  if (payload.invoice_prefix)                       STATE.settings.prefix      = payload.invoice_prefix;
  if (payload.estimate_prefix !== undefined && payload.estimate_prefix !== null) STATE.settings.estPrefix = payload.estimate_prefix;
  if (payload.default_notes  !== undefined) STATE.settings.defaultNotes  = payload.default_notes;
  if (payload.default_tnc    !== undefined) STATE.settings.defaultTnC    = payload.default_tnc;
  if (payload.default_currency)             STATE.settings.currency       = payload.default_currency;
  if (payload.generated_by   !== undefined) STATE.settings.generatedBy   = payload.generated_by;
  try {
    await api('api/settings.php', 'POST', payload);
    toast('✅ Invoice defaults saved!', 'success');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
};

// ── Category Management ──────────────────────────────────────────

// ══════════════════════════════════════════
// SALES EXECUTIVE DROPDOWN — shared by Sale entry, quick-add
// Customer, and full Customer entry forms. Defaults to the logged-in
// user (matches who's actually doing the work most of the time).
// Team members load once into STATE.team and are cached.
// ══════════════════════════════════════════
async function populateSalesExecDropdown(selected, selectId) {
  selectId = selectId || 'sn-salesexec';
  const sel = document.getElementById(selectId);
  if (!sel) return;
  if (!STATE.team || !STATE.team.length) {
    try {
      const r = await api('api/team.php?action=list');
      STATE.team = Array.isArray(r.data) ? r.data : [];
    } catch(e) { STATE.team = STATE.team || []; }
  }
  const active = (STATE.team || []).filter(u => u.status === 'active');
  let names = [...new Set(active.map(u => u.name).filter(Boolean))].sort();
  // Older records may have a free-text name that isn't (or is no longer) a
  // team member — keep it selectable rather than silently losing the data.
  if (selected && !names.includes(selected)) names.unshift(selected);
  const placeholder = selectId === 'cusn-salesperson' ? 'Select Sales Person' : '— Select —';
  sel.innerHTML = `<option value="">${placeholder}</option>` +
    names.map(n => `<option value="${escHtml(n)}" ${n===selected?'selected':''}>${escHtml(n)}</option>`).join('');
  if (!selected) sel.value = '';
}

// ══════════════════════════════════════════
// PAYMENT STATUS STAMP — shared print overlay (PAID/PARTIAL/PENDING
// diagonal stamp) used by both Sale and Purchase print templates.
// ══════════════════════════════════════════
function pnePaymentStamp(status) {
  const cfg = {
    'Paid':    { color:'#1B5E20', border:'#2E7D32', label:'PAID' },
    'Partial': { color:'#7B3F00', border:'#E65100', label:'PARTIAL' },
    'Pending': { color:'#4A148C', border:'#7B1FA2', label:'PENDING' },
  }[status];
  if (!cfg) return '';
  return `<div style="position:absolute;top:100px;right:60px;border:3px solid ${cfg.border};color:${cfg.color};font-weight:800;font-size:20px;padding:4px 22px;border-radius:8px;transform:rotate(-12deg);opacity:.85">${cfg.label}</div>`;
}
