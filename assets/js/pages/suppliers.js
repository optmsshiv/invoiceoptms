const BIZ_FROM_DATE = '2026-05-01';

// ============================================================
// suppliers.js — page-specific JS for pages/suppliers.php
// Depends on: common.js, shared-data.js, edit-approval-shared.js
//
// This REPLACES the old assets/js/suppliers.js in this repo, which
// was built for a simpler modal-based add/edit flow that your
// current SPA has since replaced with the full supplier-new.php
// page (verified: old file used 9 basic fields via a quick modal;
// current SPA's supplier-new page has 35+ fields). Rebuilt fresh
// from the current SPA to match.
// ============================================================
const SUP = { archived: false, archivedList: [], search: '', editingId: null };
let SPL_PAGE = 1;
const SPL_PAGESIZE = 10;
let SPL_ARCH_REQUESTED = false; // archived suppliers fetched once, lazily

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['suppliers', 'purchases', 'settings']);
  populateSuppliersFilters();
  renderSuppliers();
});

async function renderSuppliers() {
  const tbody = document.getElementById('suppliersTbody');
  if (!tbody) return;

  // Lazily fetch archived (inactive) suppliers once so Status filter and
  // stats can cover them — active list is already loaded at bootstrap.
  if (!SPL_ARCH_REQUESTED) {
    SPL_ARCH_REQUESTED = true;
    try {
      const r = await api('/api/suppliers.php?status=archived');
      SUP.archivedList = Array.isArray(r.data) ? r.data : [];
    } catch(e) { SUP.archivedList = SUP.archivedList || []; }
  }

  populateSuppliersFilters();
  const list = splFilteredSuppliers();
  const totals = splPurchaseTotals();

  // ── Stats ──────────────────────────────────────────────────
  const all = splAllSuppliers();
  document.getElementById('spl-stat-total').textContent = all.length;
  document.getElementById('spl-stat-active').textContent = all.filter(s => (s.status||'active') === 'active').length;
  document.getElementById('spl-stat-inactive').textContent = all.filter(s => (s.status||'active') !== 'active').length;
  const grandTotal = Object.values(totals).reduce((a,t) => a + t.total, 0);
  const grandOut = Object.values(totals).reduce((a,t) => a + t.outstanding, 0);
  document.getElementById('spl-stat-purchases').textContent = fmt_money(grandTotal);
  document.getElementById('spl-stat-out').textContent = fmt_money(grandOut);
  const fromV = document.getElementById('spl-f-from')?.value, toV = document.getElementById('spl-f-to')?.value;
  document.getElementById('spl-stat-range1').textContent = (fromV && toV) ? fmt_date_disp(fromV) + ' – ' + fmt_date_disp(toV) : 'All time';

  // ── Pagination ─────────────────────────────────────────────
  const totalPages = Math.max(1, Math.ceil(list.length / SPL_PAGESIZE));
  if (SPL_PAGE > totalPages) SPL_PAGE = totalPages;
  const start = (SPL_PAGE - 1) * SPL_PAGESIZE;
  const pageRows = list.slice(start, start + SPL_PAGESIZE);
  document.getElementById('supInfo').textContent = list.length
    ? `Showing ${start+1} to ${Math.min(start+SPL_PAGESIZE, list.length)} of ${list.length} entries`
    : 'No entries';
  const pager = document.getElementById('spl-pagination');
  if (pager) {
    let h = `<button class="pg-btn" onclick="splPage(${SPL_PAGE-1})" ${SPL_PAGE<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= totalPages; i++) {
      if (totalPages > 8 && i > 3 && i < totalPages - 1 && Math.abs(i - SPL_PAGE) > 1) {
        if (i === 4) h += `<span style="padding:0 4px;color:var(--muted)">…</span>`;
        continue;
      }
      h += `<button class="pg-btn ${i===SPL_PAGE?'active':''}" onclick="splPage(${i})">${i}</button>`;
    }
    h += `<button class="pg-btn" onclick="splPage(${SPL_PAGE+1})" ${SPL_PAGE>=totalPages?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
    pager.innerHTML = h;
  }

  if (!pageRows.length) {
    tbody.innerHTML = `<tr><td colspan="11" style="text-align:center;color:var(--muted);padding:30px">No suppliers found — click "Add New Supplier" to get started</td></tr>`;
    return;
  }

  tbody.innerHTML = pageRows.map((s, i) => {
    const t = totals[String(s.id)] || { total: 0, outstanding: 0 };
    const active = (s.status||'active') === 'active';
    return `
    <tr>
      <td>${start + i + 1}</td>
      <td><strong>${escHtml(s.name)}</strong></td>
      <td>${escHtml(s.contact_person||'—')}</td>
      <td>${escHtml(s.phone||'—')}</td>
      <td>${escHtml(s.email||'—')}</td>
      <td>${escHtml(s.state||'—')}</td>
      <td>${escHtml(s.payment_terms||'—')}</td>
      <td style="text-align:right">${t.total.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
      <td style="text-align:right;font-weight:600;color:${t.outstanding > 0 ? '#E53935' : 'var(--text)'}">${t.outstanding.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
      <td><span style="font-size:11px;font-weight:700;color:${active?'#00897B':'#E53935'};background:${active?'#00897B':'#E53935'}18;padding:2px 9px;border-radius:10px">${active?'Active':'Inactive'}</span></td>
      <td>
        <div class="action-cell" style="display:flex;gap:2px;align-items:center">
          <button class="act-btn" title="View supplier profile" onclick="viewSupplierProfile(${s.id})"><i class="fas fa-eye"></i></button>
          ${active ? `<button class="act-btn" title="Edit" onclick="editWithApproval('supplier',${s.id},'${escHtml((s.name||'Supplier #'+s.id).replace(/'/g,"\\'"))}',()=>editSupplierRich(${s.id}))"><i class="fas fa-pen"></i></button>` : ''}
          <span class="act-menu-wrap">
            <button class="act-btn" title="More" onclick="toggleActMenu(event, this)"><i class="fas fa-ellipsis"></i></button>
            <div class="act-menu">
              <button onclick="viewSupplierPdf(${s.id})"><i class="fas fa-file-pdf" style="color:#00897B"></i> View PDF</button>
              ${active
                ? _archiveItem("archiveSupplier("+s.id+")")
                : `<button onclick="restoreSupplier(${s.id})"><i class="fas fa-rotate-left" style="color:#1976D2"></i> Restore</button>`}
              ${_delItem("deleteSupplierPermanent("+s.id+")")}
            </div>
          </span>
        </div>
      </td>
    </tr>`;
  }).join('');
}

function resetSuppliersFilter() {
  document.getElementById('spl-f-search').value = '';
  ['spl-f-type','spl-f-state','spl-f-status','spl-f-terms'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  document.getElementById('spl-f-from').value = BIZ_FROM_DATE;
  document.getElementById('spl-f-to').value = fmt_date(new Date());
  SPL_PAGE = 1;
  renderSuppliers();
}

function exportSuppliersExcel() {
  const list = splFilteredSuppliers();
  if (!list.length) { toast('⚠️ No suppliers to export for the selected filters', 'warning'); return; }
  const totals = splPurchaseTotals();
  const rows = [['#','Supplier Name','Contact Person','Mobile No.','Email','State','Payment Terms','GST Number','Total Purchases','Outstanding','Status']];
  list.forEach((s, i) => {
    const t = totals[String(s.id)] || { total: 0, outstanding: 0 };
    rows.push([
      i+1, s.name||'', s.contact_person||'', s.phone||'', s.email||'', s.state||'', s.payment_terms||'', s.gst_number||'',
      t.total.toFixed(2), t.outstanding.toFixed(2), (s.status||'active') === 'active' ? 'Active' : 'Inactive'
    ]);
  });
  _downloadCSV(rows, 'supplier_list.csv');
  toast('✅ Exported ' + list.length + ' suppliers', 'success');
}


function splFilteredSuppliers() {
  const q = (document.getElementById('spl-f-search')?.value || '').trim().toLowerCase();
  const type = document.getElementById('spl-f-type')?.value || '';
  const state = document.getElementById('spl-f-state')?.value || '';
  const status = document.getElementById('spl-f-status')?.value || '';
  const terms = document.getElementById('spl-f-terms')?.value || '';

  return splAllSuppliers().filter(s => {
    if (q && !(s.name||'').toLowerCase().includes(q) && !(s.phone||'').toLowerCase().includes(q) && !(s.email||'').toLowerCase().includes(q)) return false;
    if (type && (s.supplier_type||'').trim() !== type) return false;
    if (state && (s.state||'').trim() !== state) return false;
    if (status && (s.status||'active') !== status) return false;
    if (terms && (s.payment_terms||'').trim() !== terms) return false;
    return true;
  });
}

function splAllSuppliers() {
  // Active suppliers + archived ones (shown as "Inactive")
  return [...(STATE.suppliers||[]), ...((SUP.archivedList)||[])];
}

function splPurchaseTotals() {
  const from = document.getElementById('spl-f-from')?.value || '';
  const to = document.getElementById('spl-f-to')?.value || '';
  const map = {};
  (STATE.purchases||[]).forEach(p => {
    const d = (p.purchase_date||'').slice(0,10);
    if (from && d < from) return;
    if (to && d > to) return;
    const sid = String(p.supplier_id);
    if (!map[sid]) map[sid] = { total: 0, outstanding: 0 };
    const total = parseFloat(p.total)||0, paid = parseFloat(p.amount_paid)||0;
    map[sid].total += total;
    map[sid].outstanding += Math.max(0, total - paid);
  });
  return map;
}

function splPage(p) {
  const totalPages = Math.max(1, Math.ceil(splFilteredSuppliers().length / SPL_PAGESIZE));
  if (p < 1 || p > totalPages) return;
  SPL_PAGE = p;
  renderSuppliers();
}

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

function _delItem(onclick, label='Delete') { return delMenuItem(onclick, label); }

function cancelEditRequest() {
  clearInterval(EAR.pollTimer);
  closeModal('modal-edit-approval');
}

async function submitEditRequest() {
  const reason = document.getElementById('ear-reason').value.trim();
  if (!reason) { toast('⚠️ Please describe why you need to edit this record', 'warning'); return; }
  try {
    const r = await api('/api/edit_approvals.php?action=request', 'POST', {
      entity_type: EAR.entityType, entity_id: EAR.entityId,
      entity_label: EAR.entityLabel, reason
    });
    EAR.requestId = r.id;
    _earShowView('waiting');
    _earStartPolling();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function populateSuppliersFilters() {
  const all = splAllSuppliers();
  const fill = (id, values, allLabel) => {
    const sel = document.getElementById(id); if (!sel) return;
    const cur = sel.value;
    sel.innerHTML = `<option value="">${allLabel}</option>` +
      values.map(v => `<option ${v===cur?'selected':''}>${escHtml(v)}</option>`).join('');
  };
  fill('spl-f-type',  [...new Set(all.map(s => (s.supplier_type||'').trim()).filter(Boolean))].sort(), 'All Types');
  fill('spl-f-state', [...new Set(all.map(s => (s.state||'').trim()).filter(Boolean))].sort(), 'All States');
  fill('spl-f-terms', [...new Set(all.map(s => (s.payment_terms||'').trim()).filter(Boolean))].sort(), 'All Payment Terms');
  const fromEl = document.getElementById('spl-f-from'), toEl = document.getElementById('spl-f-to');
  if (fromEl && toEl && !fromEl.value && !toEl.value) {
    fromEl.value = BIZ_FROM_DATE;
    toEl.value = fmt_date(new Date());
  }
}

async function archiveSupplier(id) {
  if (!assertCanArchive('this supplier')) return;
  const s = STATE.suppliers.find(x => String(x.id) === String(id)); if (!s) return;
  const conf = await Swal.fire({
    title: 'Archive supplier?', text: `"${s.name}" will be moved to archived suppliers.`,
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Archive', customClass: { popup: 'swal-compact' }
  });
  if (!conf.isConfirmed) return;
  try {
    await api('/api/suppliers.php?id=' + id, 'DELETE');
    STATE.suppliers = STATE.suppliers.filter(x => String(x.id) !== String(id));
    SUP.archivedList = SUP.archivedList || [];
    SUP.archivedList.push({ ...s, status: 'archived' });
    renderSuppliers();
    toast('🗑️ Archived', 'info');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

async function restoreSupplier(id) {
  const s = (SUP.archivedList||[]).find(x => String(x.id) === String(id)); if (!s) return;
  try {
    await api('/api/suppliers.php?action=restore&id=' + id, 'POST');
    SUP.archivedList = (SUP.archivedList||[]).filter(x => String(x.id) !== String(id));
    const r = await api('/api/suppliers.php');
    STATE.suppliers = Array.isArray(r.data) ? r.data : STATE.suppliers;
    renderSuppliers();
    toast(`✅ "${s.name}" restored`, 'success');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

async function deleteSupplierPermanent(id) {
  if (!assertCanDelete('this supplier')) return;
  const s = splAllSuppliers().find(x => String(x.id) === String(id));
  const conf = await Swal.fire({
    title: 'Permanently delete this supplier?',
    html: `"<b>${escHtml(s?.name||'')}</b>" will be removed forever. This cannot be undone.<br><br><span style="font-size:12px;color:#888">Suppliers with purchase history cannot be deleted — use Archive for those.</span>`,
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete Permanently', confirmButtonColor: '#E53935',
    customClass: { popup: 'swal-compact' }
  });
  if (!conf.isConfirmed) return;
  try {
    await api('/api/suppliers.php?id=' + id + '&permanent=1', 'DELETE');
    STATE.suppliers = (STATE.suppliers||[]).filter(x => String(x.id) !== String(id));
    SUP.archivedList = (SUP.archivedList||[]).filter(x => String(x.id) !== String(id));
    toast('🗑️ Supplier permanently deleted', 'info');
    renderSuppliers();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function viewSupplierProfile(id) {
  const s = splAllSuppliers().find(x => String(x.id) === String(id));
  if (!s) { toast('❌ Supplier not found', 'error'); return; }

  const totals = splPurchaseTotals();
  const t = totals[String(s.id)] || { total: 0, outstanding: 0 };
  const active = (s.status || 'active') === 'active';
  const initials = (s.name || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();

  const recentPurchases = (STATE.purchases || [])
    .filter(p => String(p.supplier_id) === String(s.id))
    .sort((a, b) => (b.purchase_date || '').localeCompare(a.purchase_date || ''))
    .slice(0, 5);

  // ── Header band ──
  document.getElementById('sp-profile-head').innerHTML = `
    <div style="display:flex;gap:14px;align-items:center;padding-right:30px">
      <div class="sp-avatar">${escHtml(initials)}</div>
      <div style="min-width:0">
        <div style="font-size:17px;font-weight:800;color:#fff;overflow-wrap:break-word">${escHtml(s.name)}</div>
        <div style="font-size:12px;color:rgba(255,255,255,.8);margin-top:2px">${escHtml(s.supplier_type || 'Supplier')}${s.city ? ' · ' + escHtml(s.city) : ''}${s.state ? ', ' + escHtml(s.state) : ''}</div>
      </div>
    </div>
    <span style="position:absolute;top:26px;right:52px;font-size:10.5px;font-weight:700;color:#fff;background:${active?'rgba(255,255,255,.22)':'rgba(0,0,0,.25)'};padding:3px 10px;border-radius:20px;letter-spacing:.3px">
      <i class="fas fa-circle" style="font-size:6px;margin-right:5px;color:${active?'#69F0AE':'#FF8A80'}"></i>${active?'ACTIVE':'INACTIVE'}
    </span>
  `;

  // ── Body: stats, contact, recent purchases ──
  const infoItem = (icon, label, val) => val ? `
    <div class="sp-info-item"><i class="fas fa-${icon}"></i><div><div class="sp-label">${escHtml(label)}</div><div class="sp-val">${escHtml(String(val))}</div></div></div>` : '';

  document.getElementById('sp-profile-body').innerHTML = `
    <div style="display:flex;gap:10px">
      <div class="sp-stat-tile">
        <span class="sp-stat-icon" style="background:var(--teal-bg);color:var(--teal)"><i class="fas fa-cart-shopping"></i></span>
        <div><div style="font-size:10.5px;color:var(--muted);font-weight:700">TOTAL PURCHASES</div><div style="font-size:17px;font-weight:800">${fmt_money(t.total)}</div></div>
      </div>
      <div class="sp-stat-tile">
        <span class="sp-stat-icon" style="background:${t.outstanding>0?'var(--red-bg)':'var(--teal-bg)'};color:${t.outstanding>0?'var(--red)':'var(--teal)'}"><i class="fas fa-scale-balanced"></i></span>
        <div><div style="font-size:10.5px;color:var(--muted);font-weight:700">OUTSTANDING</div><div style="font-size:17px;font-weight:800;color:${t.outstanding>0?'var(--red)':'var(--text)'}">${fmt_money(t.outstanding)}</div></div>
      </div>
    </div>

    <div class="sp-section">
      <div class="sp-section-title"><i class="fas fa-address-card"></i> Contact &amp; Registration</div>
      <div class="sp-info-grid">
        ${infoItem('user', 'Contact Person', s.contact_person)}
        ${infoItem('phone', 'Mobile', s.phone)}
        ${infoItem('envelope', 'Email', s.email)}
        ${infoItem('file-invoice', 'GST Number', s.gst_number)}
        ${infoItem('id-card', 'PAN', s.pan_no)}
        ${infoItem('handshake', 'Payment Terms', s.payment_terms)}
        ${infoItem('building-columns', 'Bank', s.bank_name ? s.bank_name + (s.bank_account_no ? ' · ' + s.bank_account_no : '') : '')}
        ${infoItem('map-pin', 'Address', [s.address, s.city, s.state].filter(Boolean).join(', '))}
      </div>
    </div>

    <div class="sp-section">
      <div class="sp-section-title"><i class="fas fa-clock-rotate-left"></i> Recent Purchases</div>
      ${recentPurchases.length ? recentPurchases.map(p => `
        <div class="sp-purchase-row">
          <div><strong style="font-size:12.5px">${escHtml(p.purchase_no)}</strong><div style="font-size:11px;color:var(--muted);margin-top:1px">${fmt_date_disp(p.purchase_date)}</div></div>
          <div style="font-weight:700;font-size:13px">${fmt_money(p.total)}</div>
        </div>`).join('') : `
        <div class="sp-empty">
          <i class="fas fa-inbox"></i>
          <div class="sp-empty-title">No purchases yet</div>
          <div class="sp-empty-sub">Record one from Purchases → New Purchase Invoice</div>
        </div>`}
    </div>

    <div class="sp-section">
      <div class="sp-section-title"><i class="fas fa-book-open"></i> Party Ledger</div>
      ${(() => {
        const allSupPur = (STATE.purchases||[]).filter(p => String(p.supplier_id) === String(s.id)).sort((a,b) => a.purchase_date.localeCompare(b.purchase_date));
        if (!allSupPur.length) return '<div class="sp-empty"><i class="fas fa-inbox"></i><div class="sp-empty-title">No transactions</div></div>';
        let balance = 0;
        const rows = allSupPur.map(p => {
          const billed = parseFloat(p.total)||0;
          const paid2  = parseFloat(p.amount_paid||0);
          balance += billed - paid2;
          const status = p.status || (balance <= 0 ? 'Paid' : 'Pending');
          return `<tr>
            <td style="font-size:11px">${fmt_date_disp(p.purchase_date)}</td>
            <td style="font-size:11.5px;font-weight:600">${escHtml(p.purchase_no)}</td>
            <td style="text-align:right;color:#E53935">${fmt_money(billed)}</td>
            <td style="text-align:right;color:var(--green)">${paid2 > 0 ? fmt_money(paid2) : '—'}</td>
            <td style="text-align:right;font-weight:700;color:${balance > 0 ? '#E65100' : 'var(--green)'}">${fmt_money(balance)}</td>
            <td><span style="font-size:10px;padding:2px 7px;border-radius:8px;font-weight:700;background:${status==='Paid'?'#e3f6ea':status==='Partial'?'#FFF3E0':'#FFEBEE'};color:${status==='Paid'?'#0d7a3f':status==='Partial'?'#E65100':'#E53935'}">${status}</span></td>
          </tr>`;
        }).join('');
        const totalBilled = allSupPur.reduce((s,p)=>s+(parseFloat(p.total)||0),0);
        const totalPaid2  = allSupPur.reduce((s,p)=>s+(parseFloat(p.amount_paid||0)),0);
        return `<div style="overflow-x:auto"><table class="data-table" style="font-size:11.5px">
          <thead><tr><th>Date</th><th>Purchase No.</th><th style="text-align:right">Billed</th><th style="text-align:right">Paid</th><th style="text-align:right">Balance</th><th>Status</th></tr></thead>
          <tbody>${rows}</tbody>
          <tfoot><tr style="font-weight:700;background:var(--bg)"><td colspan="2">Total Payable</td><td style="text-align:right;color:#E53935">${fmt_money(totalBilled)}</td><td style="text-align:right;color:var(--green)">${fmt_money(totalPaid2)}</td><td style="text-align:right;color:#E65100;font-size:13px">${fmt_money(t.outstanding)}</td><td></td></tr></tfoot>
        </table></div>`;
      })()}
    </div>
  `;

  // ── Footer actions ──
  document.getElementById('sp-profile-foot').innerHTML = `
    ${active ? `<button class="btn btn-outline" onclick="_modalEdit('supplier',${s.id},()=>{closeModal('modal-supplier-profile');editSupplierRich(${s.id});})"><i class="fas fa-pen"></i> Edit Supplier</button>` : ''}
    <button class="btn btn-primary" onclick="viewSupplierPdf(${s.id})"><i class="fas fa-file-pdf"></i> View / Print PDF</button>
  `;

  openModal('modal-supplier-profile');
}

function viewSupplierPdf(id) {
  const s = splAllSuppliers().find(x => String(x.id) === String(id));
  if (!s) { toast('❌ Supplier not found', 'error'); return; }
  const co = pneCompanyInfo();
  const kv = (label, val) => val ? `<div class="kv"><span>${label}</span><b>${escHtml(String(val))}</b></div>` : '';
  const win = window.open('', '_blank');
  win.document.write(`<html><head><title>${escHtml(s.name)} — Supplier Profile</title><style>
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; color: #1a2b3c; padding: 26px 34px; font-size: 12.5px; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0d3b2e; padding-bottom: 14px; margin-bottom: 16px; }
    .co-name { font-size: 17px; font-weight: 800; color: #0d3b2e; }
    .badge { border: 1.5px solid #0d3b2e; color: #0d3b2e; font-weight: 700; font-size: 12px; padding: 8px 16px; border-radius: 8px; text-align: center; }
    h2 { font-size: 16px; margin: 0 0 2px; }
    .sub { font-size: 11px; color: #6b7c93; margin-bottom: 14px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .box { border: 1px solid #dde3ea; border-radius: 8px; padding: 14px 16px; }
    .box h3 { font-size: 11px; color: #0d3b2e; margin: 0 0 10px; text-transform: uppercase; letter-spacing: .5px; }
    .kv { display: flex; justify-content: space-between; gap: 12px; font-size: 11.5px; padding: 4px 0; color: #667; }
    .kv b { color: #223; text-align: right; }
    .footer { margin-top: 26px; border-top: 1px solid #eef0f3; padding-top: 10px; font-size: 9.5px; color: #99a; display: flex; justify-content: space-between; }
  </style></head><body>
    <div class="head">
      <div style="display:flex;gap:12px;align-items:center">
        ${co.logo ? `<img src="${co.logo}" alt="Logo" style="width:52px;height:52px;object-fit:contain;border-radius:6px">` : ''}
        <div class="co-name">${escHtml(co.name)}</div>
      </div>
      <div class="badge">SUPPLIER PROFILE</div>
    </div>
    <h2>${escHtml(s.name)}</h2>
    <div class="sub">${escHtml(s.supplier_type||'')}${s.status==='archived'?' · ARCHIVED':''}</div>
    <div class="grid">
      <div class="box"><h3>Contact</h3>
        ${kv('Contact Person', s.contact_person)}${kv('Phone', s.phone)}${kv('Email', s.email)}
        ${kv('Address', s.address)}${kv('City', s.city)}${kv('State', s.state)}${kv('Country', s.country)}${kv('PIN Code', s.pincode)}
      </div>
      <div class="box"><h3>Tax &amp; Registration</h3>
        ${kv('GSTIN', s.gst_number)}${kv('PAN No.', s.pan_no)}${kv('Aadhaar No.', s.aadhaar_no)}
        ${kv('FSSAI License', s.fssai_no)}${kv('Payment Terms', s.payment_terms)}
      </div>
      <div class="box"><h3>Bank Details</h3>
        ${kv('Bank Name', s.bank_name)}${kv('Account No.', s.bank_account_no)}${kv('IFSC Code', s.bank_ifsc)}${kv('Branch', s.bank_branch)}
      </div>
      <div class="box"><h3>Other</h3>
        ${kv('Opening Balance', s.opening_balance ? fmt_money(s.opening_balance) : '')}
        ${kv('Notes', s.notes)}
      </div>
    </div>
    <div class="footer"><span>Supplier profile — system generated</span><span>Printed on: ${fmt_date_disp(new Date())}</span></div>
    <script>window.print();<\/script>
  </body></html>`);
  win.document.close();
}

async function editSupplierRich(id) {
  window.location.href = '/pages/supplier-new.php?edit_id=' + id;
}


function _archiveItem(onclick, label='Archive') { return archiveMenuItem(onclick, label); }

function _modalEdit(entityType, entityId, editFn) {
  let label = entityType + ' #' + entityId;
  const id = String(entityId);
  if (entityType === 'customer') {
    const c = (STATE.customers||[]).find(x => String(x.id) === id);
    if (c) label = c.name || label;
  } else if (entityType === 'sale') {
    const s = (STATE.sales||[]).find(x => String(x.id) === id);
    if (s) label = 'Invoice ' + (s.invoice_no || label);
  } else if (entityType === 'purchase') {
    const p = (STATE.purchases||[]).find(x => String(x.id) === id);
    if (p) label = 'Purchase ' + (p.purchase_no || label);
  } else if (entityType === 'supplier') {
    const s = splAllSuppliers().find(x => String(x.id) === id);
    if (s) label = s.name || label;
  }
  editWithApproval(entityType, entityId, label, editFn);
}

