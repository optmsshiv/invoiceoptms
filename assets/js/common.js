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
    name: s.company || 'Your Company', gst: s.gst || '', phone: s.phone || '',
    address: s.address || '', fssai: s.fssai || '', iec: s.iec || '',
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
