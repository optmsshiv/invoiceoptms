// ============================================================
// customers.js — page-specific JS for pages/customers.php
// Depends on: common.js, app.js, sales-shared.js, edit-approval-shared.js
// (goToNewCustomerPage() and editCustomerRich()'s field-population logic
// moved to customer-new.php via ?edit_id= — see customer-new.js)
// ============================================================

document.addEventListener('DOMContentLoaded', async () => {
  await bootSalesPageState();
  populateCustStateFilter();
  resetCustomersFilter();
});

function exportCustomersCsv() {
  const list = STATE.customers || [];
  if (!list.length) { toast('⚠️ Nothing to export', 'warning'); return; }
  const outstandingMap = custOutstandingMap();
  const headers = ['Customer Code','Name','Type','Phone','Email','State','Credit Limit','Outstanding','Status'];
  const rows = list.map(c => [c.customer_code, c.name, c.customer_type, c.mobile, c.email, c.state, c.credit_limit, outstandingMap[c.id]||0, c.status].map(v => `"${String(v??'').replace(/"/g,'""')}"`).join(','));
  const csv = [headers.join(','), ...rows].join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'customers-' + fmt_date(new Date()) + '.csv';
  a.click();
}

function filterCustomersList(q) { CUST_LIST_SEARCH = q || ''; CUST_LIST_PAGE = 1; renderCustomersList(); }


function resetCustomersFilter() {
  document.getElementById('custSearch').value = ''; CUST_LIST_SEARCH = '';
  document.getElementById('custTypeFilter').value = '';
  document.getElementById('custStatusFilterList').value = '';
  document.getElementById('custStateFilter').value = '';
  CUST_LIST_PAGE = 1;
  renderCustomersList();
}

function custOutstandingMap() {
  const map = {};
  (STATE.sales||[]).forEach(s => {
    if (s.status === 'Cancelled') return;
    const bal = (parseFloat(s.total)||0) - (parseFloat(s.amount_received)||0);
    if (bal <= 0) return;
    map[s.customer_id] = (map[s.customer_id] || 0) + bal;
  });
  return map;
}

function custOverdueTotal() {
  const today = fmt_date(new Date());
  return (STATE.sales||[]).reduce((sum, s) => {
    if (s.status === 'Cancelled' || !s.due_date || s.due_date >= today) return sum;
    const bal = (parseFloat(s.total)||0) - (parseFloat(s.amount_received)||0);
    return bal > 0 ? sum + bal : sum;
  }, 0);
}

function deleteCustomerRich(id) {
  if (!assertCanDelete('this customer')) return;
  const c = (STATE.customers||[]).find(x => String(x.id) === String(id)); if (!c) return;
  const conf = await Swal.fire({
    title: 'Archive this customer?', text: `"${c.name}" will be moved out of your active customer list.`,
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Archive', confirmButtonColor: '#E53935',
    customClass: { popup: 'swal-compact' }
  });
  if (!conf.isConfirmed) return;
  try {
    await api('/api/customers.php?id=' + id, 'DELETE');
    toast('📦 Customer archived', 'info');
    renderCustomersList();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function editCustomerRich(id) {
  window.location.href = '/pages/customer-new.php?edit_id=' + id;
}

function populateCustStateFilter() {
  const sel = document.getElementById('custStateFilter');
  if (!sel) return;
  const cur = sel.value;
  const states = [...new Set((STATE.customers||[]).map(c => c.state).filter(Boolean))].sort();
  sel.innerHTML = '<option value="">All States</option>' + states.map(s => `<option>${escHtml(s)}</option>`).join('');
  if (cur) sel.value = cur;
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

function viewCustomerProfile(id) {
  const c = (STATE.customers||[]).find(x => String(x.id) === String(id));
  if (!c) { toast('❌ Customer not found', 'error'); return; }

  const outstanding = (custOutstandingMap())[c.id] || 0;
  const active = (c.status || 'active') === 'active';
  const initials = (c.name || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();

  const recentSales = (STATE.sales || [])
    .filter(s => String(s.customer_id) === String(c.id))
    .sort((a, b) => (b.sale_date || '').localeCompare(a.sale_date || ''))
    .slice(0, 5);
  const ytdTotal = (STATE.sales||[]).filter(s => String(s.customer_id) === String(c.id) && s.status !== 'Cancelled' && (s.sale_date||'').slice(0,4) === String(new Date().getFullYear()))
    .reduce((sum,s) => sum + (parseFloat(s.total)||0), 0);

  document.getElementById('cp-head').innerHTML = `
    <div style="display:flex;gap:14px;align-items:center;padding-right:30px">
      <div class="sp-avatar">${escHtml(initials)}</div>
      <div style="min-width:0">
        <div style="font-size:17px;font-weight:800;color:#fff;overflow-wrap:break-word">${escHtml(c.name)}</div>
        <div style="font-size:12px;color:rgba(255,255,255,.8);margin-top:2px">${escHtml(c.customer_type || 'Customer')}${c.billing_city ? ' · ' + escHtml(c.billing_city) : ''}${c.state ? ', ' + escHtml(c.state) : ''}</div>
      </div>
    </div>
    <span style="position:absolute;top:26px;right:52px;font-size:10.5px;font-weight:700;color:#fff;background:${active?'rgba(255,255,255,.22)':'rgba(0,0,0,.25)'};padding:3px 10px;border-radius:20px;letter-spacing:.3px">
      <i class="fas fa-circle" style="font-size:6px;margin-right:5px;color:${active?'#69F0AE':'#FF8A80'}"></i>${active?'ACTIVE':'INACTIVE'}
    </span>
  `;

  const infoItem = (icon, label, val) => val ? `
    <div class="sp-info-item"><i class="fas fa-${icon}"></i><div><div class="sp-label">${escHtml(label)}</div><div class="sp-val">${escHtml(String(val))}</div></div></div>` : '';

  document.getElementById('cp-body').innerHTML = `
    <div style="display:flex;gap:10px">
      <div class="sp-stat-tile"><span class="sp-stat-icon" style="background:var(--teal-bg);color:var(--teal)"><i class="fas fa-chart-line"></i></span>
        <div><div style="font-size:10.5px;color:var(--muted);font-weight:700">SALES THIS YEAR</div><div style="font-size:17px;font-weight:800">${fmt_money(ytdTotal)}</div></div></div>
      <div class="sp-stat-tile"><span class="sp-stat-icon" style="background:${outstanding>0?'var(--red-bg)':'var(--teal-bg)'};color:${outstanding>0?'var(--red)':'var(--teal)'}"><i class="fas fa-scale-balanced"></i></span>
        <div><div style="font-size:10.5px;color:var(--muted);font-weight:700">OUTSTANDING</div><div style="font-size:17px;font-weight:800;color:${outstanding>0?'var(--red)':'var(--text)'}">${fmt_money(outstanding)}</div></div></div>
    </div>

    <div class="sp-section">
      <div class="sp-section-title"><i class="fas fa-address-card"></i> Contact &amp; Registration</div>
      <div class="sp-info-grid">
        ${infoItem('id-badge', 'Customer Code', c.customer_code)}
        ${infoItem('phone', 'Mobile', c.mobile)}
        ${infoItem('envelope', 'Email', c.email)}
        ${infoItem('file-invoice', 'GSTIN', c.gstin)}
        ${infoItem('id-card', 'PAN', c.pan_no)}
        ${infoItem('handshake', 'Payment Terms', c.payment_terms)}
        ${infoItem('user-tie', 'Sales Executive', c.sales_executive)}
        ${infoItem('map-pin', 'Billing Address', [c.billing_address, c.billing_city, c.state].filter(Boolean).join(', '))}
      </div>
    </div>

    <div class="sp-section">
      <div class="sp-section-title"><i class="fas fa-clock-rotate-left"></i> Recent Sales</div>
      ${recentSales.length ? recentSales.map(s => `
        <div class="sp-purchase-row">
          <div><strong style="font-size:12.5px">${escHtml(s.invoice_no)}</strong><div style="font-size:11px;color:var(--muted);margin-top:1px">${fmt_date_disp(s.sale_date)}</div></div>
          <div style="font-weight:700;font-size:13px">${fmt_money(s.total)}</div>
        </div>`).join('') : `
        <div class="sp-empty">
          <i class="fas fa-inbox"></i>
          <div class="sp-empty-title">No sales yet</div>
          <div class="sp-empty-sub">Record one from Sales → New Sale Invoice</div>
        </div>`}
    </div>

    <div class="sp-section">
      <div class="sp-section-title"><i class="fas fa-book-open"></i> Party Ledger</div>
      ${(() => {
        const allCustSales = (STATE.sales||[]).filter(s => String(s.customer_id) === String(c.id) && s.status !== 'Cancelled').sort((a,b) => a.sale_date.localeCompare(b.sale_date));
        if (!allCustSales.length) return '<div class="sp-empty"><i class="fas fa-inbox"></i><div class="sp-empty-title">No transactions</div></div>';
        let balance = 0;
        const rows = allCustSales.map(s => {
          const invoiced = parseFloat(s.total)||0;
          const received = parseFloat(s.amount_received)||0;
          balance += invoiced - received;
          const status = s.payment_status || (balance <= 0 ? 'Paid' : 'Pending');
          return `<tr>
            <td style="font-size:11px">${fmt_date_disp(s.sale_date)}</td>
            <td style="font-size:11.5px;font-weight:600">${escHtml(s.invoice_no)}</td>
            <td style="text-align:right;color:var(--green)">${fmt_money(invoiced)}</td>
            <td style="text-align:right;color:#1976D2">${received > 0 ? fmt_money(received) : '—'}</td>
            <td style="text-align:right;font-weight:700;color:${balance > 0 ? '#E53935' : 'var(--green)'}">${fmt_money(balance)}</td>
            <td><span style="font-size:10px;padding:2px 7px;border-radius:8px;font-weight:700;background:${status==='Paid'?'#e3f6ea':status==='Partial'?'#FFF3E0':'#FFEBEE'};color:${status==='Paid'?'#0d7a3f':status==='Partial'?'#E65100':'#E53935'}">${status}</span></td>
          </tr>`;
        }).join('');
        return `<div style="overflow-x:auto"><table class="data-table" style="font-size:11.5px">
          <thead><tr><th>Date</th><th>Invoice No.</th><th style="text-align:right">Invoiced</th><th style="text-align:right">Received</th><th style="text-align:right">Balance</th><th>Status</th></tr></thead>
          <tbody>${rows}</tbody>
          <tfoot><tr style="font-weight:700;background:var(--bg)"><td colspan="2">Total Outstanding</td><td style="text-align:right;color:var(--green)">${fmt_money(allCustSales.reduce((s,x)=>s+(parseFloat(x.total)||0),0))}</td><td style="text-align:right;color:#1976D2">${fmt_money(allCustSales.reduce((s,x)=>s+(parseFloat(x.amount_received)||0),0))}</td><td style="text-align:right;color:#E53935;font-size:13px">${fmt_money(outstanding)}</td><td></td></tr></tfoot>
        </table></div>`;
      })()}
    </div>
  `;

  document.getElementById('cp-foot').innerHTML = `
    ${active ? `<button class="btn btn-outline" onclick="_modalEdit('customer',${c.id},()=>{closeModal('modal-customer-profile');editCustomerRich(${c.id});})"><i class="fas fa-pen"></i> Edit Customer</button>` : ''}
    ${active
      ? `<button class="btn btn-primary" onclick="closeModal('modal-customer-profile'); deleteCustomerRich(${c.id})"><i class="fas fa-box-archive"></i> Archive</button>`
      : `<button class="btn btn-primary" onclick="closeModal('modal-customer-profile'); restoreCustomer(${c.id})"><i class="fas fa-rotate-left"></i> Restore</button>`}
  `;

  openModal('modal-customer-profile');
}

function restoreCustomer(id) {
  const c = (STATE.customers||[]).find(x => String(x.id) === String(id)); if (!c) return;
  try {
    await api('/api/customers.php?action=restore&id=' + id, 'POST');
    toast(`✅ "${c.name}" restored`, 'success');
    renderCustomersList();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function _archiveItem(onclick, label='Archive') { return archiveMenuItem(onclick, label); }
