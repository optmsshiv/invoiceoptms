// ================================================================
//  assets/js/portal.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  No MPA adjustments needed — self-contained in the SPA too.
//  (sharePortalWA/waOC use direct wa.me links, not the deferred
//  sendWAForInvoice() subsystem, so this page needs no WA deferral.)
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['invoices', 'clients', 'payments']);
  renderPortal();
});

// _portalTokenCache lives in shared-data.js now — also needed by
// wa-shared.js (sendWA/sendWAForInvoice resolve portal links too).
let _portalTokenMap = {};

function renderPortal() {
  _renderPortalTable();
  _autoGenMissingPortalLinks();
}

async function _autoGenMissingPortalLinks() {
  const statusEl = document.getElementById('portal-autogen-status');
  try {
    const res = await api('/api/portal.php');
    if (!res.success || !Array.isArray(res.data)) return;
    const existing = new Set(res.data.map(t => String(t.invoice_id)));
    const missing = STATE.invoices.filter(i => !existing.has(String(i.id)) && i.status !== 'Cancelled');
    if (!missing.length) { if (statusEl) statusEl.textContent = '✅ All links up to date'; return; }
    if (statusEl) statusEl.textContent = `⏳ Generating ${missing.length} link${missing.length > 1 ? 's' : ''}…`;
    let done = 0;
    for (const inv of missing) {
      try {
        const r = await api('/api/portal.php', 'POST', { invoice_id: parseInt(inv.id) });
        if (r && r.token) { _portalTokenCache[String(inv.id)] = r.token; done++; }
      } catch (e) { }
    }
    if (statusEl) statusEl.textContent = `✅ ${done} link${done > 1 ? 's' : ''} generated`;
    _renderPortalTable();
    setTimeout(() => { if (statusEl) statusEl.textContent = ''; }, 4000);
  } catch (e) { }
}

// _portalBaseURL lives in shared-data.js now — wa-shared.js needs it
// too (message templates embed the portal link).
function _buildPortalURL(token) { return `${_portalBaseURL()}?t=${token}`; }

function copyPortalLink() {
  const url = document.getElementById('portal-link-url')?.textContent;
  if (!url || url.startsWith('⏳') || url.startsWith('❌')) return;
  navigator.clipboard.writeText(url)
    .then(() => toast('✅ Link copied!', 'success'))
    .catch(() => {
      const ta = document.createElement('textarea');
      ta.value = url; document.body.appendChild(ta); ta.select();
      document.execCommand('copy'); document.body.removeChild(ta);
      toast('✅ Link copied!', 'success');
    });
}

async function revokePortalLink(invId) {
  const result = await Swal.fire({ title: 'Revoke Portal Link?', text: 'The client will no longer be able to access this portal link.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Revoke', cancelButtonText: 'Cancel', confirmButtonColor: '#E53935', customClass: { popup: 'swal-compact' } });
  if (!result.isConfirmed) return;
  try {
    await api('/api/portal.php?invoice_id=' + invId, 'DELETE');
    delete _portalTokenCache[String(invId)];
    toast('🗑️ Link revoked', 'info');
    _renderPortalTable();
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

function filterPortalTable(val) { _renderPortalTable(val); }

async function _setPortalExpiry(token, invNum) {
  const { value: days, isConfirmed } = await Swal.fire({
    title: `Set Link Expiry`,
    html: `<div style="text-align:left;font-size:13px;color:var(--text2);margin-bottom:8px">
             How many days should the link for <b>${invNum}</b> remain active?
           </div>
           <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
             ${[3, 7, 14, 30].map(d => `<button type="button" onclick="document.getElementById('swal-expiry-days').value=${d};this.parentNode.querySelectorAll('button').forEach(b=>b.style.background='var(--bg)');this.style.background='var(--teal-bg)'"
               style="padding:5px 14px;border:1px solid var(--teal);border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;color:var(--teal);background:var(--bg)">${d} days</button>`).join('')}
             <button type="button" onclick="document.getElementById('swal-expiry-days').value='';document.getElementById('swal-expiry-days').focus()"
               style="padding:5px 14px;border:1px solid var(--border);border-radius:6px;cursor:pointer;font-size:12px;color:var(--muted);background:var(--bg)">Custom</button>
           </div>
           <input id="swal-expiry-days" type="number" min="1" max="365" placeholder="Days from today…"
             style="width:100%;padding:8px 10px;border:1.5px solid var(--border2);border-radius:8px;font-size:13px;box-sizing:border-box">
           <div style="margin-top:8px;font-size:11px;color:var(--muted)">Leave blank or set 0 to remove expiry (link never expires)</div>`,
    showCancelButton: true, confirmButtonText: 'Set Expiry', cancelButtonText: 'Cancel', confirmButtonColor: '#00897B',
    customClass: { popup: 'swal-compact' },
    preConfirm: () => parseInt(document.getElementById('swal-expiry-days').value) || 0,
  });
  if (!isConfirmed) return;
  try {
    await api('/api/portal.php', 'PATCH', { token, expiry_days: days });
    toast(days > 0 ? `⏰ Link expires in ${days} days` : '♾ Expiry removed', 'success');
    _renderPortalTable();
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

async function _renderPortalTable(search) {
  if (!window._portalPage) window._portalPage = 1;
  const PER_PAGE = 10;
  const tbody = document.getElementById('portal-tbody');
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="9" style="padding:20px;text-align:center;color:var(--muted)"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>`;

  try {
    const res = await api('/api/portal.php');
    if (res.success && Array.isArray(res.data)) {
      _portalTokenMap = {};
      res.data.forEach(t => { _portalTokenMap[String(t.invoice_id)] = t; });
    }
  } catch (e) { _portalTokenMap = {}; }

  const s = (search || document.getElementById('portal-search')?.value || '').toLowerCase();
  const statusF = document.getElementById('portal-status-filter')?.value || '';
  const linkF = document.getElementById('portal-link-filter')?.value || '';

  let rows = STATE.invoices.filter(inv => {
    const cl = STATE.clients.find(x => String(x.id) === String(inv.client)) || {};
    const name = (cl.name || inv.clientName || inv.client_name || '').toLowerCase();
    const matchS = !s || (inv.num || '').toLowerCase().includes(s) || name.includes(s);
    const matchSt = !statusF || inv.status === statusF;
    const t = _portalTokenMap[String(inv.id)];
    const views = t ? (parseInt(t.views) || 0) : null;
    const expiresAt = t && t.expires_at ? new Date(t.expires_at) : null;
    const isExpired = expiresAt && expiresAt < new Date();
    let matchL = true;
    if (linkF === 'never') matchL = t && views === 0 && !isExpired;
    if (linkF === 'viewed') matchL = t && views > 0;
    if (linkF === 'expired') matchL = isExpired;
    return matchS && matchSt && matchL;
  });

  const statsEl = document.getElementById('portal-stats');
  if (statsEl) {
    const now = new Date();
    const allT = Object.values(_portalTokenMap);
    const activeLinks = allT.length;
    const totalViews = allT.reduce((s, t) => s + (parseInt(t.views) || 0), 0);
    const neverViewed = allT.filter(t => (parseInt(t.views) || 0) === 0 && !(t.expires_at && new Date(t.expires_at) < now)).length;
    const expired = allT.filter(t => t.expires_at && new Date(t.expires_at) < now).length;
    const card = (ico, bg, bdr, color, label, val) =>
      `<div style="background:var(--bg2,var(--bg));border:1px solid var(--border);border-radius:10px;padding:12px 14px;display:flex;align-items:center;gap:12px">
        <div style="width:36px;height:36px;border-radius:8px;background:${bg};color:${color};border:1px solid ${bdr};display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0"><i class="fas ${ico}"></i></div>
        <div>
          <div style="font-size:20px;font-weight:700;color:${color};line-height:1.1">${val}</div>
          <div style="font-size:11px;color:var(--muted);margin-top:2px;text-transform:uppercase;letter-spacing:.4px">${label}</div>
        </div>
      </div>`;
    statsEl.innerHTML =
      card('fa-link', '#EEF5FF', '#B5D4F4', '#185FA5', 'Active links', activeLinks) +
      card('fa-eye', '#EDFAF0', '#C0DD97', '#1E7E34', 'Total views', totalViews) +
      card('fa-eye-slash', '#FFF4E5', '#FBBF24', '#B45309', 'Never viewed', neverViewed) +
      card('fa-clock', '#FEF0EF', '#F7C1C1', '#C0392B', 'Expired', expired);
  }

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="9" style="padding:30px;text-align:center;color:var(--muted)">No invoices found</td></tr>`;
    const pgDiv = document.getElementById('portal-pagination');
    if (pgDiv) pgDiv.style.display = 'none';
    return;
  }

  const SB = {
    Paid: { bg: '#EDFAF0', color: '#1E7E34', bdr: '#C0DD97', icon: 'fa-check-circle' },
    Pending: { bg: '#FFF4E5', color: '#B45309', bdr: '#FBBF24', icon: 'fa-clock' },
    Overdue: { bg: '#FEF0EF', color: '#C0392B', bdr: '#F7C1C1', icon: 'fa-exclamation-triangle' },
    Partial: { bg: '#FFF4E5', color: '#B45309', bdr: '#FBBF24', icon: 'fa-bolt' },
    Draft: { bg: '#F5F5F5', color: '#888', bdr: '#ddd', icon: 'fa-file' },
    Cancelled: { bg: '#F5F5F5', color: '#888', bdr: '#ddd', icon: 'fa-ban' },
    Estimate: { bg: '#F0EFFD', color: '#5B52C7', bdr: '#AFA9EC', icon: 'fa-file-alt' },
  };

  const _pClr = {
    amber: { bg: '#FFF4E5', bdr: '#FBBF24', clr: '#B45309', hbg: '#F59E0B', hclr: '#fff' },
    green: { bg: '#EDFAF0', bdr: '#C0DD97', clr: '#1E7E34', hbg: '#25D366', hclr: '#fff' },
    blue: { bg: '#EEF5FF', bdr: '#B5D4F4', clr: '#185FA5', hbg: '#1565C0', hclr: '#fff' },
    red: { bg: '#FEF0EF', bdr: '#F7C1C1', clr: '#C0392B', hbg: '#E53935', hclr: '#fff' },
    '': { bg: 'var(--bg)', bdr: 'var(--border)', clr: 'var(--text)', hbg: 'var(--bg2)', hclr: 'var(--text)' },
  };
  const actBtn = (cls, icon, title, onclick) => {
    const p = _pClr[cls] || _pClr[''];
    return `<button onclick="${onclick}" title="${title}"
      onmouseover="this.style.background='${p.hbg}';this.style.color='${p.hclr}';this.style.transform='scale(1.1)'"
      onmouseout="this.style.background='${p.bg}';this.style.color='${p.clr}';this.style.transform='scale(1)'"
      style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;cursor:pointer;font-size:13px;border:1px solid ${p.bdr};background:${p.bg};color:${p.clr};transition:all .15s ease">
      <i class="${icon.startsWith('fab ') ? icon : 'fas ' + icon}"></i></button>`;
  };

  const _portalPage = window._portalPage || 1;
  const pagedRows = rows.slice((_portalPage - 1) * PER_PAGE, _portalPage * PER_PAGE);

  tbody.innerHTML = pagedRows.map(inv => {
    const cl = STATE.clients.find(x => String(x.id) === String(inv.client)) || {};
    const cName = cl.name || inv.clientName || inv.client_name || '—';
    const t = _portalTokenMap[String(inv.id)];
    const url = t ? _buildPortalURL(t.token) : '';
    const views = t ? (parseInt(t.views) || 0) : null;
    const lastViewed = t && t.last_viewed ? new Date(t.last_viewed).toLocaleDateString(_moneyLocale(), { day: '2-digit', month: 'short' }) : null;
    const expiresAt = t && t.expires_at ? new Date(t.expires_at) : null;
    const isExpired = expiresAt && expiresAt < new Date();
    const daysLeft = expiresAt && !isExpired ? Math.ceil((expiresAt - new Date()) / 86400000) : null;

    const sb = SB[inv.status] || { bg: '#F5F5F5', color: '#888', bdr: '#ddd', icon: 'fa-circle' };
    const statusPill = `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:600;background:${sb.bg};color:${sb.color};border:1px solid ${sb.bdr};white-space:nowrap"><i class="fas ${sb.icon}" style="font-size:10px"></i>${inv.status}</span>`;

    const dueColor = inv.status === 'Overdue' ? '#C0392B' : 'var(--text)';
    const dueStr = inv.due || inv.due_date || '—';

    const linkCell = url
      ? `<div style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:6px;background:#EEF5FF;border:1px solid #B5D4F4;font-size:11px;font-family:var(--mono);color:#185FA5;max-width:220px;cursor:pointer;transition:all .15s"
          onmouseover="this.style.background='#DBEAFE';this.style.borderColor='#93C5FD'"
          onmouseout="this.style.background='#EEF5FF';this.style.borderColor='#B5D4F4'"
          onclick="navigator.clipboard.writeText('${url}').then(()=>toast('✅ Link copied!','success'))" title="Click to copy">
          <i class="fas fa-link" style="font-size:10px;flex-shrink:0"></i>
          <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1">${url.replace(/^https?:\/\/[^/]+\//, '')}</span>
          <i class="fas fa-copy" style="font-size:10px;flex-shrink:0;opacity:.7"></i>
        </div>`
      : `<span style="color:var(--muted);font-size:12px;font-style:italic">No link yet</span>`;

    let viewsCell;
    if (views === null) { viewsCell = `<span style="font-size:12px;color:var(--muted)">—</span>`; }
    else if (views === 0) { viewsCell = `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:600;background:#FFF4E5;color:#B45309;border:1px solid #FBBF24;white-space:nowrap"><i class="fas fa-eye-slash" style="font-size:10px"></i> Never viewed</span>`; }
    else {
      viewsCell = `<div>
        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:600;background:#EDFAF0;color:#1E7E34;border:1px solid #C0DD97;white-space:nowrap"><i class="fas fa-eye" style="font-size:10px"></i> ${views} view${views !== 1 ? 's' : ''}</span>
        ${lastViewed ? `<div style="font-size:11px;color:var(--muted);margin-top:3px">Last: ${lastViewed}</div>` : ''}
      </div>`;
    }

    let expiryCell;
    if (isExpired) { expiryCell = `<span style="display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:#FEF0EF;color:#C0392B;border:1px solid #F7C1C1;white-space:nowrap"><i class="fas fa-ban" style="font-size:9px"></i> Expired</span>`; }
    else if (daysLeft !== null) {
      const expBg = daysLeft <= 3 ? '#FEF0EF' : '#FFF4E5', expClr = daysLeft <= 3 ? '#C0392B' : '#B45309', expBdr = daysLeft <= 3 ? '#F7C1C1' : '#FBBF24';
      expiryCell = `<span style="padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:${expBg};color:${expClr};border:1px solid ${expBdr};white-space:nowrap">${daysLeft}d left</span>`;
    } else { expiryCell = `<span style="font-size:11px;color:var(--muted)">—</span>`; }

    const genOC = t
      ? `(async(btn)=>{const ok=await Swal.fire({title:'Regenerate link?',text:'The old link will stop working.',icon:'warning',showCancelButton:true,confirmButtonText:'Regenerate',confirmButtonColor:'#E53935',customClass:{popup:'swal-compact'}});if(!ok.isConfirmed)return;btn.disabled=true;btn.innerHTML='<i class=\\'fas fa-spinner fa-spin\\'></i>';try{const r=await api('/api/portal.php','POST',{invoice_id:parseInt(${inv.id})});if(r&&r.token){_portalTokenMap[String(${inv.id})]=r;toast('🔗 Link regenerated!','success');_renderPortalTable();}else{toast('❌ Failed','error');console.error(r);}}catch(e){toast('❌ '+e.message,'error');}btn.disabled=false;btn.innerHTML='<i class=\\'fas fa-sync-alt\\'></i>';})(this)`
      : `(async(btn)=>{btn.disabled=true;btn.innerHTML='<i class=\\'fas fa-spinner fa-spin\\'></i>';try{const r=await api('/api/portal.php','POST',{invoice_id:parseInt(${inv.id})});if(r&&r.token){_portalTokenMap[String(${inv.id})]=r;toast('🔗 Link generated!','success');_renderPortalTable();}else{toast('❌ Failed','error');console.error(r);}}catch(e){toast('❌ '+e.message,'error');}btn.disabled=false;btn.innerHTML='<i class=\\'fas fa-sync-alt\\'></i>';})(this)`;

    const waPhone = (cl.wa || cl.whatsapp || cl.phone || '').replace(/\D/g, '');
    const waMsg = encodeURIComponent(`Hi ${cName},\n\nYour invoice ${inv.num || ''} is ready. Amount: ${fmt_money(inv.amount || 0)}\n\nView & pay here:\n${url}\n\nThank you!`);
    const waOC = url && waPhone ? `window.open('https://wa.me/${waPhone}?text=${waMsg}','_blank')` : `toast('⚠️ No phone number for this client','warning')`;

    return `<tr style="${isExpired ? 'opacity:.6' : ''}">
      <td><strong style="font-family:var(--mono);font-size:12px">${inv.num || inv.invoice_number || ''}</strong></td>
      <td>
        <div style="font-size:13px;font-weight:600;color:var(--text)">${cName}</div>
        ${cl.email ? `<div style="font-size:11px;color:var(--muted)">${cl.email}</div>` : ''}
      </td>
      <td style="font-family:var(--mono);font-size:12px">${fmt_money(inv.amount || 0)}</td>
      <td style="font-size:12px;font-weight:500;color:${dueColor};white-space:nowrap">${dueStr}</td>
      <td>${statusPill}</td>
      <td>${linkCell}</td>
      <td>${viewsCell}</td>
      <td>${expiryCell}</td>
      <td style="white-space:nowrap">
        <div style="display:flex;gap:4px">
          ${actBtn('amber', 'fa-clock', 'Set expiry', `_setPortalExpiry('${t?.token || ''}','${inv.num || inv.invoice_number || ''}')`)}
          ${actBtn('green', 'fab fa-whatsapp', 'Share on WhatsApp', waOC)}
          ${url ? actBtn('blue', 'fa-external-link-alt', 'Preview', `window.open('${url}','_blank')`) : ''}
          ${actBtn('', 'fa-sync-alt', t ? 'Regenerate link' : 'Generate link', genOC)}
          ${url ? actBtn('red', 'fa-trash', 'Revoke link', `revokePortalLink(${inv.id})`) : ''}
        </div>
      </td>
    </tr>`;
  }).join('');

  const total = rows.length;
  const pages = Math.ceil(total / PER_PAGE);
  const pgDiv = document.getElementById('portal-pagination');
  const pgInfo = document.getElementById('portal-page-info');
  const pgBtns = document.getElementById('portal-page-btns');

  if (pgInfo) pgInfo.textContent = `Showing ${((_portalPage - 1) * PER_PAGE) + 1}–${Math.min(_portalPage * PER_PAGE, total)} of ${total}`;

  if (pgDiv) {
    pgDiv.style.display = 'flex';
    if (pages <= 1) { if (pgBtns) pgBtns.innerHTML = ''; return; }
    const bS = (act, dis) => `width:30px;height:30px;border-radius:7px;border:1px solid ${act ? 'var(--teal)' : 'var(--border)'};background:${act ? 'var(--teal)' : 'var(--bg)'};color:${act ? '#fff' : 'var(--text)'};cursor:${dis ? 'default' : 'pointer'};font-size:12px;font-weight:${act ? '700' : '400'};opacity:${dis ? '.4' : '1'};transition:all .15s`;
    let html = `<button onclick="if(window._portalPage>1){window._portalPage--;_renderPortalTable();}" style="${bS(false, _portalPage === 1)}"><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= pages; i++) { html += `<button onclick="window._portalPage=${i};_renderPortalTable()" style="${bS(i === _portalPage, false)}">${i}</button>`; }
    html += `<button onclick="if(window._portalPage<${pages}){window._portalPage++;_renderPortalTable();}" style="${bS(false, _portalPage === pages)}"><i class="fas fa-chevron-right"></i></button>`;
    if (pgBtns) pgBtns.innerHTML = html;
  }
}
