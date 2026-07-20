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

// ================================================================
// Added retroactively — these formatting/escaping utilities (escHtml,
// fmt_money, fmt_date, fmt_date_disp, fmt_money_sym, fmt_date_l) are
// used constantly across every page, but only ever lived in
// shared-data.js. Stock and Sales/Customers pages deliberately don't
// load shared-data.js (they use their own separate stock-shared.js/
// sales-shared.js STATE pattern, per an earlier decision to keep the
// two data-loading approaches independent) — so those pages had no
// access to these at all. Adding them here, since common.js loads on
// every single page regardless of which pattern it uses. Harmless
// duplicate on pages that already get these from shared-data.js
// (identical implementations, last one loaded just wins).
// ================================================================
function escHtml(v) {
  return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function _moneyLocale() {
  const sym = (typeof STATE !== 'undefined' && STATE.settings && STATE.settings.currency) || '₹';
  if (sym === '$') return 'en-US';
  if (sym === '€') return 'de-DE';
  return 'en-IN';
}
function fmt_money(n, sym) {
  const s = sym !== undefined ? sym : ((typeof STATE !== 'undefined' && STATE.settings && STATE.settings.currency) || '₹');
  return s + parseFloat(n || 0).toLocaleString(_moneyLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmt_date(d) { return d.toISOString().split('T')[0]; }
function fmt_date_disp(d) {
  if (!d) return '—';
  const dt = new Date(d);
  if (isNaN(dt)) return d;
  return String(dt.getDate()).padStart(2, '0') + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + dt.getFullYear();
}
function fmt_money_sym(n, sym) { return (sym || '₹') + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function fmt_date_l(dateStr, opts) {
  if (!dateStr) return '';
  opts = opts || { day: '2-digit', month: 'short' };
  return new Date(dateStr).toLocaleDateString(_moneyLocale(), opts);
}
