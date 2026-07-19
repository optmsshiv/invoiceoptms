// ============================================================
// purchases.js — page-specific JS for pages/purchases.php
// Depends on: common.js, shared-data.js, edit-approval-shared.js
//
// This REPLACES the old assets/js/purchases.js in this repo — same
// staleness issue as suppliers.js (old modal-based add/edit flow,
// current SPA has a full purchase-new.php page instead). Rebuilt
// fresh from the current SPA.
// ============================================================
let PL_PAGE = 1;
const PL_PAGESIZE = 10;

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['purchases', 'products', 'suppliers', 'settings']);
  populatePurchaseListFilters();
  renderPurchases();
});

function renderPurchases() {
  const tbody = document.getElementById('purchasesTbody');
  if (!tbody) return;
  populatePurchaseListFilters();
  const list = plFilteredPurchases();

  // ── Stats over the filtered set ────────────────────────────
  const totQty = list.reduce((a,p) => a + (parseFloat(p.total_qty)||0), 0);
  const totAmt = list.reduce((a,p) => a + (parseFloat(p.total)||0), 0);
  const totPaid = list.reduce((a,p) => a + (parseFloat(p.amount_paid)||0), 0);
  const totOut = Math.max(0, totAmt - totPaid);
  document.getElementById('pl-stat-count').textContent = list.length;
  document.getElementById('pl-stat-qty').textContent = totQty.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' Kg';
  document.getElementById('pl-stat-amount').textContent = fmt_money(totAmt);
  document.getElementById('pl-stat-paid').textContent = fmt_money(totPaid);
  document.getElementById('pl-stat-out').textContent = fmt_money(totOut);
  const fromV = document.getElementById('pl-f-from')?.value, toV = document.getElementById('pl-f-to')?.value;
  document.getElementById('pl-stat-range1').textContent = (fromV && toV) ? fmt_date_disp(fromV) + ' – ' + fmt_date_disp(toV) : 'All time';

  // ── Pagination ─────────────────────────────────────────────
  const totalPages = Math.max(1, Math.ceil(list.length / PL_PAGESIZE));
  if (PL_PAGE > totalPages) PL_PAGE = totalPages;
  const start = (PL_PAGE - 1) * PL_PAGESIZE;
  const pageRows = list.slice(start, start + PL_PAGESIZE);
  document.getElementById('purInfo').textContent = list.length
    ? `Showing ${start+1} to ${Math.min(start+PL_PAGESIZE, list.length)} of ${list.length} entries`
    : 'No entries';
  const pager = document.getElementById('pl-pagination');
  if (pager) {
    let h = `<button class="pg-btn" onclick="plPage(${PL_PAGE-1})" ${PL_PAGE<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= totalPages; i++) {
      if (totalPages > 8 && i > 3 && i < totalPages - 1 && Math.abs(i - PL_PAGE) > 1) {
        if (i === 4) h += `<span style="padding:0 4px;color:var(--muted)">…</span>`;
        continue;
      }
      h += `<button class="pg-btn ${i===PL_PAGE?'active':''}" onclick="plPage(${i})">${i}</button>`;
    }
    h += `<button class="pg-btn" onclick="plPage(${PL_PAGE+1})" ${PL_PAGE>=totalPages?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
    pager.innerHTML = h;
  }

  if (!pageRows.length) {
    tbody.innerHTML = `<tr><td colspan="10" style="text-align:center;color:var(--muted);padding:30px">No purchases found for the selected filters</td></tr>`;
    return;
  }

  const payColor = {
    Paid:    { color:'#1B5E20', bg:'#E8F5E9' },
    Partial: { color:'#7B3F00', bg:'#FFF3E0' },
    Pending: { color:'#7B1FA2', bg:'#F3E8FF' },
    Received:{ color:'#7B1FA2', bg:'#F3E8FF' },
  };
  tbody.innerHTML = pageRows.map((p, i) => {
    const doc = plDocStatus(p);
    const docColor = doc === 'Completed' ? '#00897B' : '#1976D2';
    const payLabel = p.status === 'Received' ? 'Pending' : (p.status||'—');
    const pc = payColor[p.status] || { color:'#555', bg:'#F5F5F5' };
    return `
    <tr>
      <td>${start + i + 1}</td>
      <td><strong>${escHtml(p.purchase_no)}</strong></td>
      <td>${fmt_date_disp(p.purchase_date)}</td>
      <td>${escHtml(p.supplier_name||'—')}</td>
      <td style="text-align:right">${(parseFloat(p.total_qty)||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
      <td style="text-align:right;font-weight:600">${(parseFloat(p.total)||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
      <td><span style="font-size:11px;font-weight:700;color:${pc.color};background:${pc.bg};padding:2px 9px;border-radius:10px">${escHtml(payLabel)}</span></td>
      <td><span style="font-size:11px;font-weight:700;color:${docColor};background:${docColor}18;padding:2px 9px;border-radius:10px">${doc}</span></td>
      <td>${escHtml(p.payment_type||'—')}</td>
      <td>
        <div class="action-cell" style="display:flex;gap:2px;align-items:center">
          <button class="act-btn" title="View" onclick="viewPurchaseDetails(${p.id})"><i class="fas fa-eye"></i></button>
          <button class="act-btn" title="Print" onclick="printPurchaseEntry(${p.id})"><i class="fas fa-print"></i></button>
          <button class="act-btn" title="Download PDF" onclick="printPurchaseEntry(${p.id})"><i class="fas fa-download"></i></button>
          <span class="act-menu-wrap">
            <button class="act-btn" title="More" onclick="toggleActMenu(event, this)"><i class="fas fa-ellipsis"></i></button>
            <div class="act-menu">
              <button onclick="editWithApproval('purchase',${p.id},'Purchase ${escHtml((p.purchase_no||'#'+p.id).replace(/'/g,"\\'"))}',()=>editPurchase(${p.id}))"><i class="fas fa-pen" style="color:#1976D2"></i> Edit</button>
              ${_delItem("deletePurchase("+p.id+")")}
            </div>
          </span>
        </div>
      </td>
    </tr>`;
  }).join('');
}

function resetPurchasesFilter() {
  document.getElementById('pl-f-from').value = BIZ_FROM_DATE;
  document.getElementById('pl-f-to').value = fmt_date(new Date());
  ['pl-f-supplier','pl-f-warehouse','pl-f-status','pl-f-paystatus','pl-f-product','pl-f-paytype'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  document.getElementById('pl-f-invno').value = '';
  PL_PAGE = 1;
  renderPurchases();
}

function exportPurchasesExcel() {
  const list = plFilteredPurchases();
  if (!list.length) { toast('⚠️ No purchases to export for the selected filters', 'warning'); return; }
  const rows = [['#','Invoice No.','Invoice Date','Supplier','Qty (Kg)','Net Amount','Amount Paid','Outstanding','Payment Status','Status','Payment Type','Warehouse']];
  list.forEach((p, i) => {
    const total = parseFloat(p.total)||0, paid = parseFloat(p.amount_paid)||0;
    rows.push([
      i+1, p.purchase_no||'', p.purchase_date||'', p.supplier_name||'',
      (parseFloat(p.total_qty)||0).toFixed(2), total.toFixed(2), paid.toFixed(2), Math.max(0, total-paid).toFixed(2),
      p.status === 'Received' ? 'Pending' : (p.status||''), plDocStatus(p), p.payment_type||'', p.warehouse||'Main Warehouse'
    ]);
  });
  _downloadCSV(rows, 'purchase_list.csv');
  toast('✅ Exported ' + list.length + ' purchases', 'success');
}


function plFilteredPurchases() {
  const from = document.getElementById('pl-f-from')?.value || '';
  const to = document.getElementById('pl-f-to')?.value || '';
  const sup = document.getElementById('pl-f-supplier')?.value || '';
  const wh = document.getElementById('pl-f-warehouse')?.value || '';
  const status = document.getElementById('pl-f-status')?.value || '';
  const pay = document.getElementById('pl-f-paystatus')?.value || '';
  const invno = (document.getElementById('pl-f-invno')?.value || '').trim().toLowerCase();
  const prod = document.getElementById('pl-f-product')?.value || '';
  const ptype = document.getElementById('pl-f-paytype')?.value || '';

  return (STATE.purchases||[]).filter(p => {
    const d = (p.purchase_date||'').slice(0,10);
    if (from && d < from) return false;
    if (to && d > to) return false;
    if (sup && String(p.supplier_id) !== String(sup)) return false;
    if (wh && (p.warehouse||'Main Warehouse') !== wh) return false;
    if (status && plDocStatus(p) !== status) return false;
    if (pay && (p.status === 'Received' ? 'Pending' : p.status) !== pay) return false;
    if (invno && !(p.purchase_no||'').toLowerCase().includes(invno) && !(p.supplier_invoice_ref||'').toLowerCase().includes(invno)) return false;
    if (prod) {
      const ids = String(p.product_ids||'').split(',').map(x => x.trim());
      if (!ids.includes(prod)) return false;
    }
    if (ptype && (p.payment_type||'').trim() !== ptype) return false;
    return true;
  });
}

function plDocStatus(p) {
  return (p.status === 'Paid' || p.status === 'Partial' || p.status === 'Received') ? 'Completed' : 'Pending';
}

function plPage(p) {
  const totalPages = Math.max(1, Math.ceil(plFilteredPurchases().length / PL_PAGESIZE));
  if (p < 1 || p > totalPages) return;
  PL_PAGE = p;
  renderPurchases();
}

function populatePurchaseListFilters() {
  const supSel = document.getElementById('pl-f-supplier');
  if (supSel && supSel.options.length <= 1) {
    supSel.innerHTML = '<option value="">All Suppliers</option>' +
      (STATE.suppliers||[]).map(su => `<option value="${su.id}">${escHtml(su.name)}</option>`).join('');
  }
  const prodSel = document.getElementById('pl-f-product');
  if (prodSel && prodSel.options.length <= 1) {
    prodSel.innerHTML = '<option value="">All Products</option>' +
      (STATE.products||[]).map(p => `<option value="${String(p.id).replace(/\D/g,'')}">${escHtml(p.name)}</option>`).join('');
  }
  const ptSel = document.getElementById('pl-f-paytype');
  if (ptSel) {
    const cur = ptSel.value;
    const types = [...new Set((STATE.purchases||[]).map(p => (p.payment_type||'').trim()).filter(Boolean))].sort();
    ptSel.innerHTML = '<option value="">All Payment Types</option>' +
      types.map(t => `<option ${t===cur?'selected':''}>${escHtml(t)}</option>`).join('');
  }
  const fromEl = document.getElementById('pl-f-from'), toEl = document.getElementById('pl-f-to');
  if (fromEl && toEl && !fromEl.value && !toEl.value) {
    fromEl.value = BIZ_FROM_DATE;
    toEl.value = fmt_date(new Date());
  }
}

async function editPurchase(id) {
  window.location.href = '/pages/purchase-new.php?edit_id=' + id;
}

async function deletePurchase(id) {
  if (!assertCanDelete('this purchase')) return;
  const p = (STATE.purchases||[]).find(x => String(x.id) === String(id)); if (!p) return;
  const conf = await Swal.fire({
    title: 'Delete this purchase?',
    text: `"${p.purchase_no}" and its stock-in entries will be permanently removed. This cannot be undone.`,
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#E53935',
    customClass: { popup: 'swal-compact' }
  });
  if (!conf.isConfirmed) return;
  try {
    await api('/api/purchases.php?id=' + id, 'DELETE');
    STATE.purchases = STATE.purchases.filter(x => String(x.id) !== String(id));
    renderPurchases();
    toast('🗑️ Purchase deleted', 'info');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

async function viewPurchaseDetails(id) {
  openModal('modal-purchase-details');
  document.getElementById('pd-head').innerHTML = `<div style="color:#fff;font-size:13px"><i class="fas fa-spinner fa-spin"></i> Loading…</div>`;
  document.getElementById('pd-body').innerHTML = '';
  document.getElementById('pd-foot').innerHTML = '';

  let p;
  try {
    const r = await api('/api/purchases.php?id=' + id);
    p = r.data;
  } catch(e) {
    document.getElementById('pd-head').innerHTML = `<div style="color:#fff;font-size:13px">Could not load purchase</div>`;
    return;
  }

  const total = parseFloat(p.total) || 0, paid = parseFloat(p.amount_paid) || 0, outstanding = Math.max(0, total - paid);
  const payColor = { Paid: '#69F0AE', Partial: '#FFD180', Pending: '#FF8A80' }[p.status] || '#fff';

  document.getElementById('pd-head').innerHTML = `
    <div style="display:flex;gap:14px;align-items:center;padding-right:30px">
      <div class="sp-avatar"><i class="fas fa-cart-shopping"></i></div>
      <div style="min-width:0">
        <div style="font-size:17px;font-weight:800;color:#fff;overflow-wrap:break-word">${escHtml(p.purchase_no)}</div>
        <div style="font-size:12px;color:rgba(255,255,255,.8);margin-top:2px">${escHtml(p.supplier_name||'—')} · ${fmt_date_disp(p.purchase_date)}</div>
      </div>
    </div>
    <span style="position:absolute;top:26px;right:52px;font-size:10.5px;font-weight:700;color:#fff;background:rgba(255,255,255,.22);padding:3px 10px;border-radius:20px;letter-spacing:.3px">
      <i class="fas fa-circle" style="font-size:6px;margin-right:5px;color:${payColor}"></i>${escHtml((p.status||'Pending').toUpperCase())}
    </span>
  `;

  const items = p.items || [];
  document.getElementById('pd-body').innerHTML = `
    <div style="display:flex;gap:10px">
      <div class="sp-stat-tile"><span class="sp-stat-icon" style="background:var(--teal-bg);color:var(--teal)"><i class="fas fa-indian-rupee-sign"></i></span>
        <div><div style="font-size:10.5px;color:var(--muted);font-weight:700">TOTAL AMOUNT</div><div style="font-size:16px;font-weight:800">${fmt_money(total)}</div></div></div>
      <div class="sp-stat-tile"><span class="sp-stat-icon" style="background:var(--green-bg);color:var(--green)"><i class="fas fa-check"></i></span>
        <div><div style="font-size:10.5px;color:var(--muted);font-weight:700">PAID</div><div style="font-size:16px;font-weight:800;color:var(--green)">${fmt_money(paid)}</div></div></div>
      <div class="sp-stat-tile"><span class="sp-stat-icon" style="background:${outstanding>0?'var(--red-bg)':'var(--teal-bg)'};color:${outstanding>0?'var(--red)':'var(--teal)'}"><i class="fas fa-scale-balanced"></i></span>
        <div><div style="font-size:10.5px;color:var(--muted);font-weight:700">OUTSTANDING</div><div style="font-size:16px;font-weight:800;color:${outstanding>0?'var(--red)':'var(--text)'}">${fmt_money(outstanding)}</div></div></div>
    </div>

    <div class="sp-section">
      <div class="sp-section-title"><i class="fas fa-circle-info"></i> Details</div>
      <div class="sp-info-grid">
        <div class="sp-info-item"><i class="fas fa-warehouse"></i><div><div class="sp-label">Warehouse</div><div class="sp-val">${escHtml(p.warehouse||'Main Warehouse')}</div></div></div>
        <div class="sp-info-item"><i class="fas fa-file-invoice"></i><div><div class="sp-label">Supplier Invoice Ref.</div><div class="sp-val">${escHtml(p.supplier_invoice_ref||'—')}</div></div></div>
        <div class="sp-info-item"><i class="fas fa-handshake"></i><div><div class="sp-label">Payment Terms</div><div class="sp-val">${escHtml(p.payment_terms||'—')}</div></div></div>
        <div class="sp-info-item"><i class="fas fa-credit-card"></i><div><div class="sp-label">Payment Type</div><div class="sp-val">${escHtml(p.payment_type||'—')}</div></div></div>
      </div>
    </div>

    <div class="sp-section">
      <div class="sp-section-title"><i class="fas fa-boxes-stacked"></i> Items (${items.length})</div>
      ${items.length ? `<div style="overflow-x:auto"><table class="data-table" style="min-width:520px">
        <thead><tr><th>Product</th><th style="text-align:right">Qty</th><th style="text-align:right">Rate</th><th style="text-align:right">Amount</th></tr></thead>
        <tbody>${items.map(it => `<tr>
          <td>${escHtml(it.description||'—')}</td>
          <td style="text-align:right">${parseFloat(it.qty||0).toFixed(2)} ${escHtml(it.unit||'Kg')}</td>
          <td style="text-align:right">${fmt_money(it.rate)}</td>
          <td style="text-align:right;font-weight:600">${fmt_money(it.amount)}</td>
        </tr>`).join('')}</tbody>
      </table></div>` : `<div class="sp-empty"><i class="fas fa-inbox"></i><div class="sp-empty-title">No items</div></div>`}
    </div>
  `;

  document.getElementById('pd-foot').innerHTML = `
    <button class="btn btn-outline" onclick="_modalEdit('purchase',${p.id},()=>{closeModal('modal-purchase-details');editPurchase(${p.id});})"><i class="fas fa-pen"></i> Edit</button>
    <button class="btn btn-primary" onclick="printPurchaseEntry(${p.id})"><i class="fas fa-print"></i> Print</button>
  `;
}
