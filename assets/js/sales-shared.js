// ============================================================
// sales-shared.js — shared across Sales & Customers module pages
// (sales.php, sale-new.php, customers.php, customer-new.php)
//
// NOTE: _modalEdit / archiveMenuItem / _delItem / delMenuItem are generic
// list-row action-menu + edit-approval helpers, not really sales-specific.
// They're kept here for now since Sales/Customers is the first module that
// needs them; when Phase 4 (Purchases/Suppliers) needs the same "..." menu
// pattern, promote these into common.js instead of duplicating them.
// ============================================================
const STATE = { products: [], stock: [], suppliers: [], team: [], customers: [], sales: [], purchases: [], settings: {} };

async function bootSalesPageState() {
  STATE.settings = (window.SERVER && window.SERVER.settings) || {};
  try {
    const [prod, stk, sup, cust, sales] = await Promise.all([
      api('/api/products.php'),
      api('/api/stock.php'),
      api('/api/suppliers.php'),
      api('/api/customers.php'),
      api('/api/sales.php'),
    ]);
    STATE.products  = Array.isArray(prod.data)  ? prod.data  : [];
    STATE.stock     = Array.isArray(stk.data)   ? stk.data   : [];
    STATE.suppliers = Array.isArray(sup.data)   ? sup.data   : [];
    STATE.customers = Array.isArray(cust.data)  ? cust.data  : [];
    STATE.sales     = Array.isArray(sales.data) ? sales.data : [];
  } catch (e) {
    toast('❌ Failed to load sales/customer data: ' + e.message, 'error');
  }
}

function renderSales() {
  const tbody = document.getElementById('salesTbody');
  if (!tbody) return;
  populateSalesListFilters();
  const list = slFilteredSales();

  // ── Stats over the filtered set ────────────────────────────
  const totQty = list.reduce((a,s) => a + (parseFloat(s.total_qty)||0), 0);
  const totAmt = list.reduce((a,s) => a + (parseFloat(s.total)||0), 0);
  const totPaid = list.reduce((a,s) => a + (parseFloat(s.amount_received)||0), 0);
  const totOut = Math.max(0, totAmt - totPaid);
  document.getElementById('sl-stat-count').textContent = list.length;
  document.getElementById('sl-stat-qty').textContent = totQty.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' Kg';
  document.getElementById('sl-stat-amount').textContent = fmt_money(totAmt);
  document.getElementById('sl-stat-paid').textContent = fmt_money(totPaid);
  document.getElementById('sl-stat-out').textContent = fmt_money(totOut);
  const fromV = document.getElementById('sl-f-from')?.value, toV = document.getElementById('sl-f-to')?.value;
  document.getElementById('sl-stat-range1').textContent = (fromV && toV) ? fmt_date_disp(fromV) + ' – ' + fmt_date_disp(toV) : 'All time';

  // ── Pagination ─────────────────────────────────────────────
  const totalPages = Math.max(1, Math.ceil(list.length / SL_PAGESIZE));
  if (SL_PAGE > totalPages) SL_PAGE = totalPages;
  const start = (SL_PAGE - 1) * SL_PAGESIZE;
  const pageRows = list.slice(start, start + SL_PAGESIZE);
  document.getElementById('saleInfo').textContent = list.length
    ? `Showing ${start+1} to ${Math.min(start+SL_PAGESIZE, list.length)} of ${list.length} entries`
    : 'No entries';
  const pager = document.getElementById('sl-pagination');
  if (pager) {
    let h = `<button class="pg-btn" onclick="slPage(${SL_PAGE-1})" ${SL_PAGE<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= totalPages; i++) {
      if (totalPages > 8 && i > 3 && i < totalPages - 1 && Math.abs(i - SL_PAGE) > 1) {
        if (i === 4) h += `<span style="padding:0 4px;color:var(--muted)">…</span>`;
        continue;
      }
      h += `<button class="pg-btn ${i===SL_PAGE?'active':''}" onclick="slPage(${i})">${i}</button>`;
    }
    h += `<button class="pg-btn" onclick="slPage(${SL_PAGE+1})" ${SL_PAGE>=totalPages?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
    pager.innerHTML = h;
  }

  if (!pageRows.length) {
    tbody.innerHTML = `<tr><td colspan="10" style="text-align:center;color:var(--muted);padding:30px">No sales found for the selected filters</td></tr>`;
    return;
  }

  const payColor = {
    Paid:    { color:'#1B5E20', bg:'#E8F5E9' },
    Partial: { color:'#7B3F00', bg:'#FFF3E0' },
    Pending: { color:'#7B1FA2', bg:'#F3E8FF' },
  };
  const statusMap = { Confirmed: { label:'Completed', color:'#00897B' }, Draft: { label:'Draft', color:'#1976D2' } };
  tbody.innerHTML = pageRows.map((s, i) => {
    const st = statusMap[s.status||'Confirmed'] || { label: s.status||'—', color:'#889' };
    const pc = payColor[s.payment_status] || { color:'#555', bg:'#F5F5F5' };
    return `
    <tr>
      <td>${start + i + 1}</td>
      <td><strong>${escHtml(s.invoice_no)}</strong></td>
      <td>${fmt_date_disp(s.sale_date)}</td>
      <td>${escHtml(s.customer_name||'—')}</td>
      <td style="text-align:right">${(parseFloat(s.total_qty)||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
      <td style="text-align:right;font-weight:600">${(parseFloat(s.total)||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
      <td><span style="font-size:11px;font-weight:700;color:${pc.color};background:${pc.bg};padding:2px 9px;border-radius:10px">${escHtml(s.payment_status||'—')}</span></td>
      <td><span style="font-size:11px;font-weight:700;color:${st.color};background:${st.color}18;padding:2px 9px;border-radius:10px">${escHtml(st.label)}</span></td>
      <td>${escHtml(s.sales_executive||'—')}</td>
      <td>
        <div class="action-cell" style="display:flex;gap:2px;align-items:center">
          <button class="act-btn" title="View" onclick="viewSaleDetails(${s.id})"><i class="fas fa-eye"></i></button>
          <button class="act-btn" title="Print" onclick="printSaleEntry(${s.id})"><i class="fas fa-print"></i></button>
          <button class="act-btn" title="Download PDF" onclick="printSaleEntry(${s.id})"><i class="fas fa-download"></i></button>
          <span class="act-menu-wrap">
            <button class="act-btn" title="More" onclick="toggleActMenu(event, this)"><i class="fas fa-ellipsis"></i></button>
            <div class="act-menu">
              <button onclick="editWithApproval('sale',${s.id},'Invoice ${escHtml((s.invoice_no||'#'+s.id).replace(/'/g,"\\'"))}',()=>editSale(${s.id}))"><i class="fas fa-pen" style="color:#1976D2"></i> Edit</button>
              ${_delItem("deleteSale("+s.id+")")}
            </div>
          </span>
        </div>
      </td>
    </tr>`;
  }).join('');
}

async function deleteSale(id) {
  if (!assertCanDelete('this sale')) return;
  const s = (STATE.sales||[]).find(x => String(x.id) === String(id)); if (!s) return;
  const conf = await Swal.fire({
    title: 'Delete this sale?', text: `"${s.invoice_no}" and its stock-out entries will be permanently removed. This cannot be undone.`,
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#E53935',
    customClass: { popup: 'swal-compact' }
  });
  if (!conf.isConfirmed) return;
  try {
    await api('/api/sales.php?id=' + id, 'DELETE');
    STATE.sales = STATE.sales.filter(x => String(x.id) !== String(id));
    const stk = await api('/api/stock.php');
    STATE.stock = Array.isArray(stk.data) ? stk.data : STATE.stock;
    renderSales();
    toast('🗑️ Sale deleted', 'info');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function editSale(id) {
  window.location.href = '/pages/sale-new.php?edit_id=' + id;
}

async function viewSaleDetails(id) {
  openModal('modal-sale-details');
  document.getElementById('sd-head').innerHTML = `<div style="color:#fff;font-size:13px"><i class="fas fa-spinner fa-spin"></i> Loading…</div>`;
  document.getElementById('sd-body').innerHTML = '';
  document.getElementById('sd-foot').innerHTML = '';

  let s;
  try {
    const r = await api('/api/sales.php?id=' + id);
    s = r.data;
  } catch(e) {
    document.getElementById('sd-head').innerHTML = `<div style="color:#fff;font-size:13px">Could not load sale</div>`;
    return;
  }

  const total = parseFloat(s.total) || 0, received = parseFloat(s.amount_received) || 0, outstanding = Math.max(0, total - received);
  const payColor = { Paid: '#69F0AE', Partial: '#FFD180', Pending: '#FF8A80' }[s.payment_status] || '#fff';

  document.getElementById('sd-head').innerHTML = `
    <div style="display:flex;gap:14px;align-items:center;padding-right:30px">
      <div class="sp-avatar"><i class="fas fa-file-invoice-dollar"></i></div>
      <div style="min-width:0">
        <div style="font-size:17px;font-weight:800;color:#fff;overflow-wrap:break-word">${escHtml(s.invoice_no)}</div>
        <div style="font-size:12px;color:rgba(255,255,255,.8);margin-top:2px">${escHtml(s.customer_name||'—')} · ${fmt_date_disp(s.sale_date)}</div>
      </div>
    </div>
    <span style="position:absolute;top:26px;right:52px;font-size:10.5px;font-weight:700;color:#fff;background:rgba(255,255,255,.22);padding:3px 10px;border-radius:20px;letter-spacing:.3px">
      <i class="fas fa-circle" style="font-size:6px;margin-right:5px;color:${payColor}"></i>${escHtml((s.payment_status||'Pending').toUpperCase())}
    </span>
  `;

  const items = s.items || [];
  const grossWt    = parseFloat(s.kanta_gross_weight||0);
  const tareWt     = parseFloat(s.kanta_tare_weight||0);
  const netWt      = Math.max(0, grossWt - tareWt);
  const dhaltaKg   = parseFloat(s.kanta_dhalta_kg||0);
  const billableWt = Math.max(0, netWt - dhaltaKg);
  const hasWeight  = grossWt > 0 || tareWt > 0 || dhaltaKg > 0;
  const kgFmt = v => parseFloat(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' Kg';

  document.getElementById('sd-body').innerHTML = `
    <div style="display:flex;gap:10px">
      <div class="sp-stat-tile"><span class="sp-stat-icon" style="background:var(--teal-bg);color:var(--teal)"><i class="fas fa-indian-rupee-sign"></i></span>
        <div><div style="font-size:10.5px;color:var(--muted);font-weight:700">TOTAL AMOUNT</div><div style="font-size:16px;font-weight:800">${fmt_money(total)}</div></div></div>
      <div class="sp-stat-tile"><span class="sp-stat-icon" style="background:var(--green-bg);color:var(--green)"><i class="fas fa-check"></i></span>
        <div><div style="font-size:10.5px;color:var(--muted);font-weight:700">RECEIVED</div><div style="font-size:16px;font-weight:800;color:var(--green)">${fmt_money(received)}</div></div></div>
      <div class="sp-stat-tile"><span class="sp-stat-icon" style="background:${outstanding>0?'var(--red-bg)':'var(--teal-bg)'};color:${outstanding>0?'var(--red)':'var(--teal)'}"><i class="fas fa-scale-balanced"></i></span>
        <div><div style="font-size:10.5px;color:var(--muted);font-weight:700">OUTSTANDING</div><div style="font-size:16px;font-weight:800;color:${outstanding>0?'var(--red)':'var(--text)'}">${fmt_money(outstanding)}</div></div></div>
    </div>

    ${hasWeight ? `
    <div class="sp-section">
      <div class="sp-section-title"><i class="fas fa-weight-hanging"></i> Weight & Dhalta Details</div>
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px">
        <div class="pne-card" style="padding:10px 12px">
          <div style="font-size:10px;color:var(--muted);font-weight:700">GROSS WT</div>
          <div style="font-size:14px;font-weight:800;margin-top:3px">${kgFmt(grossWt)}</div>
        </div>
        <div class="pne-card" style="padding:10px 12px">
          <div style="font-size:10px;color:var(--muted);font-weight:700">TARE WT</div>
          <div style="font-size:14px;font-weight:800;margin-top:3px">${kgFmt(tareWt)}</div>
        </div>
        <div class="pne-card" style="padding:10px 12px">
          <div style="font-size:10px;color:var(--muted);font-weight:700">NET WT</div>
          <div style="font-size:14px;font-weight:800;margin-top:3px">${kgFmt(netWt)}</div>
        </div>
        <div class="pne-card" style="padding:10px 12px;border:1px solid #FFD180;background:#FFF8E1">
          <div style="font-size:10px;color:#E65100;font-weight:700">DHALTA</div>
          <div style="font-size:14px;font-weight:800;margin-top:3px;color:#E65100">${kgFmt(dhaltaKg)}</div>
          ${netWt > 0 ? `<div style="font-size:10px;color:var(--muted)">${(dhaltaKg/netWt*100).toFixed(2)}% of net</div>` : ''}
        </div>
        <div class="pne-card" style="padding:10px 12px;border:1px solid #C8E6C9;background:#E8F5E9">
          <div style="font-size:10px;color:#2E7D32;font-weight:700">BILLABLE WT</div>
          <div style="font-size:14px;font-weight:800;margin-top:3px;color:#2E7D32">${kgFmt(billableWt)}</div>
        </div>
      </div>
      ${s.kanta_name ? `<div style="font-size:11px;color:var(--muted);margin-top:8px"><i class="fas fa-building"></i> Kanta: ${escHtml(s.kanta_name)}${s.kanta_moisture_pct ? ' &nbsp;|&nbsp; <i class="fas fa-droplet"></i> Moisture: ' + parseFloat(s.kanta_moisture_pct).toFixed(2) + '%' : ''}</div>` : ''}
    </div>` : ''}

    <div class="sp-section">
      <div class="sp-section-title"><i class="fas fa-circle-info"></i> Details</div>
      <div class="sp-info-grid">
        <div class="sp-info-item"><i class="fas fa-warehouse"></i><div><div class="sp-label">Warehouse</div><div class="sp-val">${escHtml(s.warehouse||'Main Warehouse')}</div></div></div>
        <div class="sp-info-item"><i class="fas fa-user-tie"></i><div><div class="sp-label">Sales Executive</div><div class="sp-val">${escHtml(s.sales_executive||'—')}</div></div></div>
        <div class="sp-info-item"><i class="fas fa-handshake"></i><div><div class="sp-label">Payment Terms</div><div class="sp-val">${escHtml(s.payment_terms||'—')}</div></div></div>
        <div class="sp-info-item"><i class="fas fa-calendar-check"></i><div><div class="sp-label">Due Date</div><div class="sp-val">${s.due_date ? fmt_date_disp(s.due_date) : '—'}</div></div></div>
      </div>
    </div>

    <div class="sp-section">
      <div class="sp-section-title"><i class="fas fa-boxes-stacked"></i> Items (${items.length})</div>
      ${items.length ? `<div style="overflow-x:auto"><table class="data-table" style="min-width:600px">
        <thead><tr>
          <th>Product</th><th>Variety</th><th>Moisture %</th>
          <th style="text-align:right">Qty (Kg)</th>
          <th style="text-align:right">Rate (₹/Kg)</th>
          <th style="text-align:right">Disc %</th>
          <th style="text-align:right">Amount</th>
        </tr></thead>
        <tbody>${items.map(it => `<tr>
          <td><strong>${escHtml(it.product_name||it.description||'—')}</strong>${it.variety_grade ? `<div style="font-size:10.5px;color:var(--muted)">${escHtml(it.variety_grade)}</div>` : ''}</td>
          <td>${escHtml(it.variety_grade||'—')}</td>
          <td style="text-align:center">${it.moisture_pct ? parseFloat(it.moisture_pct).toFixed(2)+'%' : '—'}</td>
          <td style="text-align:right;font-weight:600">${parseFloat(it.qty||0).toFixed(2)}</td>
          <td style="text-align:right">${fmt_money(it.rate)}</td>
          <td style="text-align:right">${it.discount_pct ? parseFloat(it.discount_pct).toFixed(2)+'%' : '—'}</td>
          <td style="text-align:right;font-weight:700">${fmt_money(it.line_total)}</td>
        </tr>`).join('')}</tbody>
      </table></div>` : `<div class="sp-empty"><i class="fas fa-inbox"></i><div class="sp-empty-title">No items</div></div>`}
    </div>
  `;

  document.getElementById('sd-foot').innerHTML = `
    <button class="btn btn-outline" onclick="_modalEdit('sale',${s.id},()=>{closeModal('modal-sale-details');editSale(${s.id});})"><i class="fas fa-pen"></i> Edit</button>
    <button class="btn btn-outline" onclick="printSalePartyCopy(${s.id})"><i class="fas fa-copy"></i> Party Copy</button>
    <button class="btn btn-primary" onclick="printSaleEntry(${s.id})"><i class="fas fa-print"></i> Tax Invoice</button>
  `;
}

function slPage(p) {
  const totalPages = Math.max(1, Math.ceil(slFilteredSales().length / SL_PAGESIZE));
  if (p < 1 || p > totalPages) return;
  SL_PAGE = p;
  renderSales();
}

function populateSalesListFilters() {
  // Customer dropdown
  const custSel = document.getElementById('sl-f-customer');
  if (custSel && custSel.options.length <= 1) {
    custSel.innerHTML = '<option value="">All Customers</option>' +
      (STATE.customers||[]).map(c => `<option value="${c.id}">${escHtml(c.name)}</option>`).join('');
  }
  // Product dropdown
  const prodSel = document.getElementById('sl-f-product');
  if (prodSel && prodSel.options.length <= 1) {
    prodSel.innerHTML = '<option value="">All Products</option>' +
      (STATE.products||[]).map(p => `<option value="${String(p.id).replace(/\D/g,'')}">${escHtml(p.name)}</option>`).join('');
  }
  // Sales Executive dropdown — distinct values from actual sales
  const execSel = document.getElementById('sl-f-exec');
  if (execSel) {
    const cur = execSel.value;
    const execs = [...new Set((STATE.sales||[]).map(s => (s.sales_executive||'').trim()).filter(Boolean))].sort();
    execSel.innerHTML = '<option value="">All Sales Executive</option>' +
      execs.map(e => `<option ${e===cur?'selected':''}>${escHtml(e)}</option>`).join('');
  }
  // Default date range: this month (only when both fields are empty)
  const fromEl = document.getElementById('sl-f-from'), toEl = document.getElementById('sl-f-to');
  if (fromEl && toEl && !fromEl.value && !toEl.value) {
    fromEl.value = BIZ_FROM_DATE;
    toEl.value = fmt_date(new Date());
  }
}

function slFilteredSales() {
  const from = document.getElementById('sl-f-from')?.value || '';
  const to = document.getElementById('sl-f-to')?.value || '';
  const cust = document.getElementById('sl-f-customer')?.value || '';
  const wh = document.getElementById('sl-f-warehouse')?.value || '';
  const status = document.getElementById('sl-f-status')?.value || '';
  const pay = document.getElementById('sl-f-paystatus')?.value || '';
  const invno = (document.getElementById('sl-f-invno')?.value || '').trim().toLowerCase();
  const prod = document.getElementById('sl-f-product')?.value || '';
  const exec = document.getElementById('sl-f-exec')?.value || '';

  return (STATE.sales||[]).filter(s => {
    const d = (s.sale_date||'').slice(0,10);
    if (from && d < from) return false;
    if (to && d > to) return false;
    if (cust && String(s.customer_id) !== String(cust)) return false;
    if (wh && (s.warehouse||'Main Warehouse') !== wh) return false;
    if (status && (s.status||'Confirmed') !== status) return false;
    if (pay && s.payment_status !== pay) return false;
    if (invno && !(s.invoice_no||'').toLowerCase().includes(invno)) return false;
    if (prod) {
      const ids = String(s.product_ids||'').split(',').map(x => x.trim());
      if (!ids.includes(prod)) return false;
    }
    if (exec && (s.sales_executive||'').trim() !== exec) return false;
    return true;
  });
}

function _downloadCSV(rows, filename) {
  const csv = rows.map(r=>r.map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
  const blob = new Blob(['\uFEFF'+csv],{type:'text/csv;charset=utf-8'});
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a'); a.href=url; a.download=filename; document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
}

function populateSaleCustomerDropdown() {
  const sel = document.getElementById('sn-customer');
  if (!sel) return;
  const cur = sel.value;
  sel.innerHTML = '<option value="">Select or add customer…</option>' +
    (STATE.customers||[]).map(c => `<option value="${c.id}">${escHtml(c.name)}</option>`).join('');
  if (cur) sel.value = cur;
}

async function populateSalesExecDropdown(selected, selectId) {
  selectId = selectId || 'sn-salesexec';
  const sel = document.getElementById(selectId);
  if (!sel) return;
  if (!STATE.team || !STATE.team.length) {
    try {
      const r = await api('/api/team.php?action=list');
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

function printSaleInvoice(s) {
  const co = pneCompanyInfo();
  const items = s.items || [];
  const rows = items.map(it => `
    <tr>
      <td><strong>${escHtml(it.product_name||it.description||'')}</strong>${it.variety_grade?`<br><span class="muted">${escHtml(it.variety_grade)}</span>`:''}${it.batch_no?`<br><span class="muted">Batch: ${escHtml(it.batch_no)}</span>`:''}</td>
      <td class="r">${(it.moisture_pct!==null && it.moisture_pct!==undefined && it.moisture_pct!=='') ? parseFloat(it.moisture_pct).toFixed(2)+'%' : '—'}</td>
      <td class="r">${parseFloat(it.qty).toFixed(2)} ${escHtml(it.unit||'Kg')}</td>
      <td class="r">${fmt_money(it.rate)}</td>
      <td class="r">${parseFloat(it.discount_pct||0).toFixed(1)}%</td>
      <td class="r">${fmt_money((it.qty||0)*(it.rate||0)*(1-(it.discount_pct||0)/100))}</td>
      <td class="r">${parseFloat(it.gst_pct||0).toFixed(0)}%</td>
      <td class="r">${fmt_money(it.tax_amount)}</td>
      <td class="r"><strong>${fmt_money(it.line_total)}</strong></td>
    </tr>`).join('');
  const isInterstate = s.sales_type !== 'Local Sales';
  const addCharges = (parseFloat(s.transport_charge)||0)+(parseFloat(s.loading_charge)||0)+(parseFloat(s.packing_charge)||0)+(parseFloat(s.insurance_charge)||0)+(parseFloat(s.other_charges)||0);
  const deductions = Array.isArray(s.deductions) ? s.deductions : [];
  const deductionTotal = deductions.reduce((sum,d) => sum + (parseFloat(d.amount)||0), 0);

  const win = window.open('', '_blank');
  win.document.write(`<html><head><title>${escHtml(s.invoice_no)}</title><style>
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; color: #1a2b3c; padding: 26px 34px; font-size: 12.5px; position: relative; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0d3b2e; padding-bottom: 14px; margin-bottom: 16px; }
    .co-name { font-size: 19px; font-weight: 800; color: #0d3b2e; }
    .co-sub { font-size: 10.5px; color: #6b7c93; letter-spacing: .5px; }
    .co-meta { font-size: 10.5px; color: #445; margin-top: 6px; line-height: 1.6; }
    .badge-inv { border: 1.5px solid #0d3b2e; color: #0d3b2e; font-weight: 700; font-size: 12px; padding: 8px 16px; border-radius: 8px; text-align: center; }
    .badge-inv small { display: block; font-size: 9px; font-weight: 600; color: #6b7c93; }
    .inv-meta { text-align: right; font-size: 11px; color: #445; margin-top: 8px; line-height: 1.7; }
    .row2 { display: flex; gap: 16px; margin-bottom: 16px; }
    .box { flex: 1; border: 1px solid #dde3ea; border-radius: 8px; padding: 14px 16px; }
    .box h3 { font-size: 11.5px; color: #0d3b2e; margin: 0 0 10px; }
    .box .kv { font-size: 11px; color: #667; margin-bottom: 7px; }
    .box .kv b { display: block; font-size: 12.5px; color: #223; font-weight: 700; }
    table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 11px; }
    table.items th { background: #f3f5f7; color: #445; padding: 8px 7px; font-size: 10px; text-transform: uppercase; text-align: left; border-bottom: 2px solid #dde3ea; }
    table.items td { padding: 8px 7px; border-bottom: 1px solid #eef0f3; vertical-align: top; }
    table.items td.r, table.items th.r { text-align: right; }
    .muted { color: #99a; font-size: 10px; }
    .row3 { display: flex; gap: 16px; margin-bottom: 16px; }
    .tax-row { display: flex; justify-content: space-between; font-size: 11px; padding: 4px 0; color: #445; }
    .sum-row { display: flex; justify-content: space-between; font-size: 12px; padding: 5px 0; color: #445; }
    .grand { border: 2px solid #0d3b2e; color: #0d3b2e; border-radius: 8px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; margin-top: 8px; background: #fff; }
    .grand span { font-size: 11px; text-transform: uppercase; font-weight: 700; } .grand b { font-size: 20px; color: #0d3b2e; }
    .words { font-style: italic; color: #556; font-size: 11px; margin-top: 10px; }
    .sig-row { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 10px; }
    .sig { width: 30%; border-top: 1px solid #99a; text-align: center; font-size: 10px; color: #667; padding-top: 6px; text-transform: uppercase; letter-spacing: .5px; }
    .footer { margin-top: 30px; border-top: 1px solid #eef0f3; padding-top: 10px; display: flex; justify-content: space-between; font-size: 9.5px; color: #99a; }
  </style></head><body>
    <div class="head">
      <div style="display:flex;gap:12px;align-items:flex-start">
        ${co.logo ? `<img src="${co.logo}" alt="Logo" style="width:102px;height:102px;object-fit:contain;border-radius:6px">` : ''}
        <div>
          <div class="co-name">${escHtml(co.name)}</div>
          <div class="co-meta">
            ${co.address?escHtml(co.address)+'<br>':''}
            ${pneStatutoryLine(co)?`<strong>${pneStatutoryLine(co)}</strong><br>`:''}
            ${co.iec?`IEC: ${escHtml(co.iec)}<br>`:''}${co.phone?'Mobile: '+escHtml(co.phone):''}${co.email?(co.phone?' &nbsp;|&nbsp; ':'')+'Email: '+escHtml(co.email):''}
          </div>
        </div>
      </div>
      <div>
        <div class="badge-inv">TAX INVOICE<small>SALE ENTRY</small></div>
        <div class="inv-meta">Invoice No: ${escHtml(s.invoice_no)}<br>Invoice Date: ${fmt_date_disp(s.sale_date)}<br>${s.sales_type?escHtml(s.sales_type):''}</div>
      </div>
    </div>
    ${pnePaymentStamp(s.payment_status)}

    <div class="row2">
      <div class="box">
        <h3>BILL TO</h3>
        <div class="kv"><b>${escHtml(s.customer_name||'')}</b></div>
        <div class="kv">GSTIN<b>${escHtml(s.customer_gstin||'—')}</b></div>
        <div class="kv">Place of Supply<b>${escHtml(s.place_of_supply||'—')}</b></div>
      </div>
      <div class="box">
        <h3>PAYMENT</h3>
        <div class="kv">Status<b>${escHtml(s.payment_status||'—')}</b></div>
        <div class="kv">Method<b>${escHtml(s.payment_method||'—')}</b></div>
        ${s.payment_date ? `<div class="kv">Payment Date<b>${fmt_date_disp(s.payment_date)}</b></div>` : ''}
        <div class="kv">Balance Due<b style="color:${(s.total-s.amount_received)>0?'#c0392b':'#0d7a3f'}">${fmt_money((s.total||0)-(s.amount_received||0))}</b></div>
      </div>
    </div>

    <table class="items">
      <thead><tr><th>Product</th><th class="r">Moist%</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Disc %</th><th class="r">Amount</th><th class="r">GST %</th><th class="r">Tax</th><th class="r">Total</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>

    <div class="row3">
      <div class="box">
        <h3>TAX SUMMARY</h3>
        <div class="tax-row"><span>Taxable Value</span><span>${fmt_money(s.taxable_amount)}</span></div>
        ${isInterstate
          ? `<div class="tax-row"><span>IGST</span><span>${fmt_money(s.igst_amount)}</span></div>`
          : `<div class="tax-row"><span>CGST</span><span>${fmt_money(s.cgst_amount)}</span></div>
             <div class="tax-row"><span>SGST</span><span>${fmt_money(s.sgst_amount)}</span></div>`}
        ${addCharges > 0 ? `<div class="tax-row"><span>Additional Charges</span><span>${fmt_money(addCharges)}</span></div>` : ''}
      </div>
      <div class="box">
        <div class="sum-row"><span>Sub-Total</span><span>${fmt_money(s.subtotal)}</span></div>
        ${deductionTotal > 0 ? `<div class="sum-row" style="color:#c0392b"><span>Deductions</span><span>- ${fmt_money(deductionTotal)}</span></div>` : ''}
        ${(parseFloat(s.discount_amount)||0) > 0 ? `<div class="sum-row" style="color:#c0392b"><span>Less: Discount${s.discount_remarks?` (${escHtml(s.discount_remarks)})`:''}</span><span>- ${fmt_money(s.discount_amount)}</span></div>` : ''}
        ${(parseFloat(s.trade_discount_amount)||0) > 0 ? `<div class="sum-row" style="color:#c0392b"><span>Trade Discount (${parseFloat(s.trade_discount_pct||0).toFixed(1)}%)</span><span>- ${fmt_money(s.trade_discount_amount)}</span></div>` : ''}
        ${(parseFloat(s.cash_discount_amount)||0) > 0 ? `<div class="sum-row" style="color:#c0392b"><span>Cash Discount (${parseFloat(s.cash_discount_pct||0).toFixed(1)}% — ${escHtml(s.cd_applicable_within||'Same Day')})</span><span>- ${fmt_money(s.cash_discount_amount)}</span></div>` : ''}
        <div class="sum-row"><span>Total Tax</span><span>${fmt_money(s.total_tax)}</span></div>
        <div class="sum-row"><span>Round-off</span><span>${fmt_money(s.round_off)}</span></div>
        <div class="grand"><span>GRAND TOTAL</span><b>${fmt_money(s.total)}</b></div>
      </div>
    </div>
    <div class="words">Amount in Words: <strong>${numToWordsINR(s.total)}</strong></div>
    ${deductions.length ? `
    <div class="box" style="margin-top:12px">
      <h3>DEDUCTION DETAILS</h3>
      ${deductions.map(d => `<div class="tax-row"><span>${escHtml(d.type||'Deduction')}${d.description?` — ${escHtml(d.description)}`:''}</span><span>${fmt_money(d.amount)}</span></div>`).join('')}
    </div>` : ''}

    <div class="sig-row">
      <div class="sig">Customer Signature</div>
      <div class="sig">${escHtml(s.prepared_by||'Prepared By')}</div>
      <div class="sig" style="border-top-color:#0d3b2e;color:#0d3b2e;font-weight:700">Authorized Signatory</div>
    </div>
    <div class="footer">
      <span>${escHtml(s.invoice_no)} — This is a system generated document</span>
      <span>Printed on: ${fmt_date_disp(new Date())}</span>
    </div>
    <script>window.print();<\/script>
  </body></html>`);
  win.document.close();
}

async function printSalePartyCopy(id) {
  try {
    const r = await api('/api/sales.php?id=' + id);
    _printSalePartyCopy(r.data);
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function _printSalePartyCopy(s) {
  const co = pneCompanyInfo();
  const items = s.items || [];
  const grossWt  = parseFloat(s.kanta_gross_weight||0);
  const tareWt   = parseFloat(s.kanta_tare_weight||0);
  const netWt    = Math.max(0, grossWt - tareWt);
  const dhaltaKg = parseFloat(s.kanta_dhalta_kg||0);
  const billableWt = Math.max(0, netWt - dhaltaKg);
  const outstanding = Math.max(0, (parseFloat(s.total)||0) - (parseFloat(s.amount_received)||0));

  const win = window.open('', '_blank');
  win.document.write(`<html><head><title>Sale Invoice — ${escHtml(s.invoice_no)}</title><meta charset="utf-8"><style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; color: #1a1a2e; padding: 28px 36px; font-size: 12px; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0d3b2e; padding-bottom: 14px; margin-bottom: 16px; }
    .co-name { font-size: 20px; font-weight: 900; color: #0d3b2e; }
    .co-meta { font-size: 10px; color: #667; margin-top: 4px; line-height: 1.7; }
    .inv-title { text-align: right; }
    .inv-title .label { font-size: 22px; font-weight: 900; color: #0d3b2e; letter-spacing: 2px; }
    .inv-title .no { font-size: 13px; font-weight: 700; margin-top: 4px; }
    .inv-title .date { font-size: 11px; color: #667; }
    .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    .party-box { border: 1px solid #dde; border-radius: 8px; padding: 12px 14px; }
    .party-box h3 { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #889; margin-bottom: 8px; font-weight: 700; }
    .party-box .name { font-size: 14px; font-weight: 800; color: #0d3b2e; }
    .party-box .meta { font-size: 10.5px; color: #556; margin-top: 4px; line-height: 1.6; }
    ${grossWt > 0 ? `.kanta { display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:16px;padding:12px;background:#f5faf7;border:2px solid #0d3b2e;border-radius:8px; }
    .kanta h3 { font-size:9px;text-transform:uppercase;letter-spacing:1px;color:#0d3b2e;font-weight:800;grid-column:1/-1;margin-bottom:4px; }
    .kc { text-align:center;background:#fff;border-radius:6px;border:1px solid #d0e8dc;padding:8px 4px; }
    .kc.dh { border-color:#c0392b;background:#fff9f9; }
    .kc.bl { border-color:#0d7a3f;background:#f0faf5; }
    .kc .lbl { font-size:8px;text-transform:uppercase;color:#889;letter-spacing:.5px;margin-bottom:3px; }
    .kc .val { font-size:16px;font-weight:800; } .kc.dh .val{color:#c0392b;} .kc.bl .val{color:#0d7a3f;}
    .kc .unit { font-size:10px;font-weight:400; }` : ''}
    table { width:100%;border-collapse:collapse;margin-bottom:16px;font-size:11px; }
    th { background:#0d3b2e;color:#fff;padding:8px 7px;font-size:9px;text-transform:uppercase;letter-spacing:.5px;font-weight:700;text-align:left; }
    th.r, td.r { text-align:right; }
    td { padding:7px;border-bottom:1px solid #eef; }
    tfoot td { border-top:2px solid #0d3b2e;font-weight:700;background:#f5faf7; }
    .summary { display:grid;grid-template-columns:1fr 280px;gap:16px;margin-bottom:16px; }
    .pay-box { }
    .pay-row { display:flex;justify-content:space-between;padding:6px 0;font-size:11.5px;border-bottom:1px solid #eef; }
    .pay-grand { display:flex;justify-content:space-between;padding:10px 0;font-size:15px;font-weight:800;color:#0d3b2e;border-top:2px solid #0d3b2e;margin-top:4px; }
    .outstanding { background:#fff3f3;border:1px solid #ffcccc;border-radius:6px;padding:10px 14px;margin-top:8px;display:flex;justify-content:space-between;align-items:center; }
    .outstanding .lbl { font-size:11px;font-weight:700;color:#c0392b; }
    .outstanding .val { font-size:16px;font-weight:800;color:#c0392b; }
    .words { font-size:10.5px;color:#556;font-style:italic;padding:8px 0;border-top:1px dashed #dde;border-bottom:1px dashed #dde;margin-bottom:14px; }
    .sig-row { display:flex;justify-content:space-between;margin-top:40px; }
    .sig { width:28%;border-top:1px solid #aab;text-align:center;padding-top:6px;font-size:9px;text-transform:uppercase;letter-spacing:.5px;color:#778; }
    .footer { margin-top:20px;border-top:1px solid #eef;padding-top:8px;display:flex;justify-content:space-between;font-size:9px;color:#aab; }
    .party-copy-stamp { position:fixed;top:120px;right:40px;border:3px solid #1976D2;color:#1976D2;font-weight:900;font-size:18px;padding:5px 18px;border-radius:8px;transform:rotate(-12deg);opacity:.6; }
  </style></head><body>
    <div class="party-copy-stamp">PARTY COPY</div>
    <div class="head">
      <div>
        ${co.logo ? `<img src="${co.logo}" style="height:56px;margin-bottom:6px;display:block">` : ''}
        <div class="co-name">${escHtml(co.name)}</div>
        <div class="co-meta">${co.address ? escHtml(co.address)+'<br>' : ''}${co.phone ? 'Tel: '+escHtml(co.phone) : ''}${co.gst ? ' &nbsp;|&nbsp; GSTIN: '+escHtml(co.gst) : ''}</div>
      </div>
      <div class="inv-title">
        <div class="label">INVOICE</div>
        <div class="no">${escHtml(s.invoice_no)}</div>
        <div class="date">Date: ${fmt_date_disp(s.sale_date)}</div>
        ${s.due_date ? `<div class="date">Due: ${fmt_date_disp(s.due_date)}</div>` : ''}
      </div>
    </div>

    <div class="parties">
      <div class="party-box">
        <h3>Bill To</h3>
        <div class="name">${escHtml(s.customer_name||'')}</div>
        <div class="meta">${s.customer_gstin ? 'GSTIN: '+escHtml(s.customer_gstin)+'<br>' : ''}${s.customer_phone ? 'Tel: '+escHtml(s.customer_phone) : ''}</div>
      </div>
      <div class="party-box">
        <h3>Vehicle &amp; Delivery</h3>
        <div class="meta">${s.vehicle_no ? 'Vehicle: <strong>'+escHtml(s.vehicle_no)+'</strong><br>' : ''}${s.place_of_supply ? 'Place of Supply: '+escHtml(s.place_of_supply)+'<br>' : ''}${s.warehouse ? 'Warehouse: '+escHtml(s.warehouse) : ''}</div>
      </div>
    </div>

    ${grossWt > 0 ? `
    <div class="kanta">
      <h3>⚖ Kanta / Weight Details</h3>
      <div class="kc"><div class="lbl">Gross Wt</div><div class="val">${grossWt.toFixed(2)}<span class="unit"> Kg</span></div></div>
      <div class="kc"><div class="lbl">Tare Wt</div><div class="val">${tareWt.toFixed(2)}<span class="unit"> Kg</span></div></div>
      <div class="kc"><div class="lbl">Net Wt</div><div class="val">${netWt.toFixed(2)}<span class="unit"> Kg</span></div></div>
      <div class="kc dh"><div class="lbl">Dhalta</div><div class="val">${dhaltaKg.toFixed(2)}<span class="unit"> Kg</span></div></div>
      <div class="kc bl"><div class="lbl">Billable Wt</div><div class="val">${billableWt.toFixed(2)}<span class="unit"> Kg</span></div></div>
    </div>` : ''}

    <table>
      <thead><tr><th>#</th><th>Product</th><th>Variety / Grade</th><th class="r">Qty (Kg)</th><th class="r">Rate (₹/Kg)</th><th class="r">Amount</th></tr></thead>
      <tbody>${items.map((it,i) => `<tr>
        <td>${i+1}</td>
        <td><strong>${escHtml(it.product_name||it.description||'')}</strong></td>
        <td>${escHtml(it.variety_grade||'—')}</td>
        <td class="r">${parseFloat(it.qty||0).toFixed(2)}</td>
        <td class="r">${fmt_money(it.rate)}</td>
        <td class="r"><strong>${fmt_money(it.line_total)}</strong></td>
      </tr>`).join('')}</tbody>
      <tfoot><tr><td colspan="3"><strong>TOTAL</strong></td><td class="r"><strong>${items.reduce((s,i)=>s+parseFloat(i.qty||0),0).toFixed(2)}</strong></td><td></td><td class="r"><strong>${fmt_money(s.subtotal||s.total)}</strong></td></tr></tfoot>
    </table>

    <div class="summary">
      <div></div>
      <div class="pay-box">
        ${(parseFloat(s.transport_charge)||0) > 0 ? `<div class="pay-row"><span>Transport</span><span>${fmt_money(s.transport_charge)}</span></div>` : ''}
        ${(parseFloat(s.gst_amount)||0) > 0 ? `<div class="pay-row"><span>GST (${parseFloat(s.gst_pct||0).toFixed(0)}%)</span><span>${fmt_money(s.gst_amount)}</span></div>` : ''}
        <div class="pay-grand"><span>GRAND TOTAL</span><span>${fmt_money(s.total)}</span></div>
        ${outstanding > 0 ? `<div class="outstanding"><span class="lbl">Outstanding Balance</span><span class="val">${fmt_money(outstanding)}</span></div>` : ''}
      </div>
    </div>
    <div class="words">Amount in Words: <em>${numToWordsINR(s.total)}</em></div>

    <div class="sig-row">
      <div class="sig">Receiver's Signature</div>
      <div class="sig">Checked By</div>
      <div class="sig">For ${escHtml(co.name)}</div>
    </div>
    <div class="footer">
      <span>This is a computer generated invoice</span>
      <span>Printed: ${fmt_date_disp(new Date())}</span>
    </div>
  <script>window.print();<\/script>
  </body></html>`);
  win.document.close();
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

function pneCompanyInfo() {
  const s = STATE.settings || {};
  return {
    name: s.company || 'Your Company', gst: s.gst || '', phone: s.phone || '', email: s.email || '',
    address: s.address || '', fssai: s.fssai || '', iec: s.iec || '', logo: s.logo || '',
    pan: s.pan || '', apeda: s.apeda || '', cin: s.cin || '', msme: s.msme || '',
  };
}

function pnePaymentStamp(status) {
  const cfg = {
    'Paid':    { color:'#1B5E20', border:'#2E7D32', label:'PAID' },
    'Partial': { color:'#7B3F00', border:'#E65100', label:'PARTIAL' },
    'Pending': { color:'#4A148C', border:'#7B1FA2', label:'PENDING' },
  }[status];
  if (!cfg) return '';
  return `<div style="position:absolute;top:100px;right:60px;border:3px solid ${cfg.border};color:${cfg.color};font-weight:800;font-size:20px;padding:4px 22px;border-radius:8px;transform:rotate(-12deg);opacity:.85">${cfg.label}</div>`;
}

function pneStatutoryLine(co) {
  const parts = [];
  if (co.gst) parts.push('GSTIN: ' + escHtml(co.gst));
  if (co.pan) parts.push('PAN: ' + escHtml(co.pan));
  if (co.fssai) parts.push('FSSAI: ' + escHtml(co.fssai));
  return parts.join(' &nbsp;|&nbsp; ');
}

function splAllSuppliers() {
  // Active suppliers + archived ones (shown as "Inactive")
  return [...(STATE.suppliers||[]), ...((SUP.archivedList)||[])];
}

function _delItem(onclick, label='Delete') { return delMenuItem(onclick, label); }

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

function archiveMenuItem(onclick, label = 'Archive') {
  if (canDo('archive')) {
    return `<button onclick="${onclick}"><i class="fas fa-box-archive" style="color:#E65100"></i> ${label}</button>`;
  }
  return `<button disabled style="opacity:.45;cursor:not-allowed;pointer-events:none" title="Archive restricted by your role"><i class="fas fa-lock" style="color:var(--muted)"></i> ${label} <span style="font-size:10px;color:var(--muted)">(restricted)</span></button>`;
}

function delMenuItem(onclick, label = 'Delete') {
  if (canDo('delete')) {
    return `<button onclick="${onclick}"><i class="fas fa-trash" style="color:#E53935"></i> ${label}</button>`;
  }
  return `<button disabled style="opacity:.45;cursor:not-allowed;pointer-events:none" title="Delete restricted by your role"><i class="fas fa-lock" style="color:var(--muted)"></i> ${label} <span style="font-size:10px;color:var(--muted)">(restricted)</span></button>`;
}

async function renderCustomersList() {
  try {
    // Fetch both active and archived so the Status filter and Restore
    // action have real data to work with (previously only active ones
    // were ever loaded, silently breaking the "Inactive" filter option).
    const [activeR, archivedR] = await Promise.all([
      api('/api/customers.php'),
      api('/api/customers.php?status=archived').catch(() => ({ data: [] })),
    ]);
    STATE.customers = [...(Array.isArray(activeR.data) ? activeR.data : []), ...(Array.isArray(archivedR.data) ? archivedR.data : [])];
  } catch(e) { toast('❌ ' + e.message, 'error'); }

  const outstandingMap = custOutstandingMap();
  const totalCreditLimit = (STATE.customers||[]).reduce((s,c) => s + (parseFloat(c.credit_limit)||0), 0);
  const totalOutstanding = Object.values(outstandingMap).reduce((s,v) => s+v, 0);
  const monthStart = new Date(); monthStart.setDate(1);
  const monthStartStr = fmt_date(monthStart);
  const monthSales = (STATE.sales||[]).filter(s => s.status !== 'Cancelled' && s.sale_date >= monthStartStr);
  const monthSalesTotal = monthSales.reduce((s,x) => s + (parseFloat(x.total)||0), 0);
  const monthCollections = monthSales.reduce((s,x) => s + (parseFloat(x.amount_received)||0), 0);

  document.getElementById('cust-stat-total').textContent = (STATE.customers||[]).length;
  document.getElementById('cust-stat-active').textContent = (STATE.customers||[]).filter(c => c.status==='active').length;
  document.getElementById('cust-stat-creditlimit').textContent = fmt_money(totalCreditLimit);
  document.getElementById('cust-stat-available').textContent = fmt_money(Math.max(0, totalCreditLimit - totalOutstanding));
  document.getElementById('cust-stat-outstanding').textContent = fmt_money(totalOutstanding);
  document.getElementById('cust-stat-overdue').textContent = fmt_money(custOverdueTotal());
  document.getElementById('cust-stat-monthsales').textContent = fmt_money(monthSalesTotal);
  document.getElementById('cust-stat-monthcoll').textContent = fmt_money(monthCollections);

  populateCustStateFilter();

  const tbody = document.getElementById('custListTbody');
  if (!tbody) return;
  const typeF = document.getElementById('custTypeFilter')?.value || '';
  const statusF = document.getElementById('custStatusFilterList')?.value || '';
  const stateF = document.getElementById('custStateFilter')?.value || '';
  let list = STATE.customers || [];
  if (CUST_LIST_SEARCH) {
    const q = CUST_LIST_SEARCH.toLowerCase();
    list = list.filter(c => (c.name||'').toLowerCase().includes(q) || (c.customer_code||'').toLowerCase().includes(q) || (c.mobile||'').toLowerCase().includes(q) || (c.email||'').toLowerCase().includes(q));
  }
  if (typeF) list = list.filter(c => c.customer_type === typeF);
  if (statusF) list = list.filter(c => c.status === statusF);
  if (stateF) list = list.filter(c => c.state === stateF);

  const totalPages = Math.max(1, Math.ceil(list.length / CUST_LIST_PAGESIZE));
  CUST_LIST_PAGE = Math.min(CUST_LIST_PAGE, totalPages);
  const pageRows = list.slice((CUST_LIST_PAGE-1)*CUST_LIST_PAGESIZE, CUST_LIST_PAGE*CUST_LIST_PAGESIZE);
  document.getElementById('custListInfo').textContent = `Showing ${list.length?((CUST_LIST_PAGE-1)*CUST_LIST_PAGESIZE+1):0} to ${Math.min(CUST_LIST_PAGE*CUST_LIST_PAGESIZE,list.length)} of ${list.length} entries`;

  const typeColors = { Company:['#2E7D32','#E8F5E9'], Trader:['#1976D2','#E3F2FD'], Exporter:['#6A4C93','#F3E8FF'], Retailer:['#E65100','#FFF3E0'], Domestic:['#455A64','#ECEFF1'], Wholesaler:['#00897B','#E0F2F1'] };
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="11" style="text-align:center;color:var(--muted);padding:30px">No customers yet — click "Add New Customer" to create one</td></tr>`;
  } else {
    tbody.innerHTML = pageRows.map((c, i) => {
      const [tColor, tBg] = typeColors[c.customer_type] || ['#455A64','#ECEFF1'];
      const outstanding = outstandingMap[c.id] || 0;
      return `<tr>
        <td>${(CUST_LIST_PAGE-1)*CUST_LIST_PAGESIZE+i+1}</td>
        <td><code style="font-size:11px;color:var(--muted)">${escHtml(c.customer_code||'—')}</code></td>
        <td style="text-align:left"><strong>${escHtml(c.name)}</strong>${c.billing_city?`<br><span style="font-size:10.5px;color:var(--muted)">${escHtml(c.billing_city)}${c.state?', '+escHtml(c.state):''}</span>`:''}</td>
        <td><span style="font-size:10px;font-weight:700;color:${tColor};background:${tBg};padding:2px 7px;border-radius:9px">${escHtml(c.customer_type||'—')}</span></td>
        <td>${escHtml(c.mobile||'—')}</td>
        <td style="font-size:11px">${escHtml(c.email||'—')}</td>
        <td>${escHtml(c.state||'—')}</td>
        <td>${fmt_money(c.credit_limit||0)}</td>
        <td style="color:${outstanding>0?'#E53935':'#00897B'};font-weight:600">${fmt_money(outstanding)}</td>
        <td><span style="font-size:10px;font-weight:700;color:${c.status==='active'?'#00897B':'#889'};background:${c.status==='active'?'#E8F5E9':'#eee'};padding:2px 7px;border-radius:9px">${c.status==='active'?'Active':'Inactive'}</span></td>
        <td>
          <div class="action-cell" style="display:flex;gap:2px;align-items:center">
            <button class="act-btn" title="View profile" onclick="viewCustomerProfile(${c.id})"><i class="fas fa-eye"></i></button>
            ${c.status==='active' ? `<button class="act-btn" title="Edit" onclick="editWithApproval('customer',${c.id},'${escHtml((c.name||'Customer #'+c.id).replace(/'/g,"\\'"))}',()=>editCustomerRich(${c.id}))"><i class="fas fa-pen"></i></button>` : ''}
            <span class="act-menu-wrap">
              <button class="act-btn" title="More" onclick="toggleActMenu(event, this)"><i class="fas fa-ellipsis"></i></button>
              <div class="act-menu">
                ${c.status==='active'
                  ? _archiveItem("deleteCustomerRich("+c.id+")")
                  : `<button onclick="restoreCustomer(${c.id})"><i class="fas fa-rotate-left" style="color:#1976D2"></i> Restore</button>`}
              </div>
            </span>
          </div>
        </td>
      </tr>`;
    }).join('');
  }
  document.getElementById('custListPagination').innerHTML = Array.from({length: totalPages}, (_, i) => i+1).map(p => `
    <button class="pg-btn ${p===CUST_LIST_PAGE?'active':''}" onclick="CUST_LIST_PAGE=${p};renderCustomersList()">${p}</button>`).join('');
}