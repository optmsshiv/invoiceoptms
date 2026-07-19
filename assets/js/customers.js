// ================================================================
//  assets/js/customers.js
//  Requires: common.js, shared-data.js, edit-approval-shared.js
//  (loaded before this file).
//  For pages/customers/customers.php (list) AND customer-new.php
//  (add/edit form) — both load this file.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['customers', 'sales', 'settings']);
  await populateSalesExecDropdown(SERVER.user?.name || '');

  if (document.getElementById('custListTbody')) {
    // List page
    populateCustStateFilter();
    renderCustomersList();
  } else if (document.getElementById('cusn-title')) {
    // New/Edit form page
    const params = new URLSearchParams(window.location.search);
    const editId = params.get('id');
    if (editId) {
      await editCustomerRich(editId);
    } else {
      goToNewCustomerPage();
    }
  }
});

// ══════════════════════════════════════════
// ADD NEW CUSTOMER (full page)
// ══════════════════════════════════════════
// MPA NOTE: the old SPA's goToNewCustomerFromSale() navigated to this
// full page from Sale Entry's "+" button, relying on SPA-style
// view-switching to keep the in-progress sale form intact in memory.
// A real page navigation would lose that data. sale-new.php already
// uses the quick-add modal (openAddCustomerModal/saveCustomer) for
// its "+" button instead — same outcome, no navigation risk — so
// that function isn't needed here.
const CUSN = { editingId: null, docs: [] };

function populateCusnStateDropdowns() {
  ['cusn-state','cusn-shipstate'].forEach(id => {
    const sel = document.getElementById(id);
    if (!sel) return;
    sel.innerHTML = '<option value="">Select state</option>' + INDIA_STATES.map(s => `<option>${s}</option>`).join('');
  });
}

// NOTE: populateCusnSalesPersonDropdown() was removed here — it was
// dead code, populating the same #cusn-salesperson dropdown that
// populateSalesExecDropdown() (common.js) also populates right after
// it at every call site, immediately overwriting its work.

function onCusnSameAddrToggle() {
  const same = document.getElementById('cusn-sameaddr').checked;
  document.getElementById('cusn-shipping').disabled = same;
  document.getElementById('cusn-shipaddr-row').style.display = same ? 'none' : 'grid';
  if (same) document.getElementById('cusn-shipping').value = document.getElementById('cusn-billing').value;
}

function goToNewCustomerPage() {
  CUSN.editingId = null;
  CUSN.docs = [];
  document.getElementById('cusn-title').textContent = 'Add New Customer';
  document.getElementById('cusn-crumb').textContent = 'Add New Customer';
  document.getElementById('cusn-type').value = '';
  document.getElementById('cusn-code').value = '';
  document.getElementById('cusn-code').placeholder = 'Auto Generate';
  ['cusn-name','cusn-bizname','cusn-displayname','cusn-phone','cusn-altphone','cusn-email','cusn-whatsapp',
   'cusn-billing','cusn-shipping','cusn-city','cusn-district','cusn-pincode','cusn-shipcity','cusn-shippincode',
   'cusn-gst','cusn-pan','cusn-tan','cusn-iec','cusn-tradelicense','cusn-notes-inline','cusn-notes-sidebar']
   .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  document.getElementById('cusn-group').value = '';
  document.getElementById('cusn-status').value = 'Active';
  document.getElementById('cusn-creditlimit').value = 0;
  populateCusnStateDropdowns();
  document.getElementById('cusn-state').value = '';
  document.getElementById('cusn-shipstate').value = '';
  document.getElementById('cusn-biztype').value = '';
  document.getElementById('cusn-currency').value = 'INR';
  document.getElementById('cusn-paymentterms').value = '';
  populateSalesExecDropdown(SERVER.user?.name || '', 'cusn-salesperson');
  document.getElementById('cusn-openingbal').value = 0;
  document.getElementById('cusn-openingbaltype').value = 'Debit';
  document.getElementById('cusn-sameaddr').checked = true;
  onCusnSameAddrToggle();
  document.getElementById('cusn-docs-input').value = '';
  document.getElementById('cusn-sum-code').textContent = 'Auto Generate';
  document.getElementById('cusn-sum-status').textContent = 'Active';
  document.getElementById('cusn-sum-creditlimit').textContent = '₹0.00';
  document.getElementById('cusn-sum-openingbal').textContent = '₹0.00';
  document.getElementById('cusn-sum-currentbal').textContent = '₹0.00';
  document.getElementById('cusn-sum-paymentterms').textContent = 'Not Set';
  renderCusnDocs();
}

function cancelCustomerEntry() {
  window.location.href = '/pages/customers/customers.php';
}

async function editCustomerRich(id) {
  const c = STATE.customers.find(x => String(x.id) === String(id)); if (!c) return;
  CUSN.editingId = id;
  CUSN.docs = Array.isArray(c.documents) ? [...c.documents] : [];
  document.getElementById('cusn-title').textContent = 'Edit Customer';
  document.getElementById('cusn-crumb').textContent = c.name;
  const set = (id2, val) => { const el = document.getElementById(id2); if (el) el.value = val ?? ''; };
  set('cusn-type', c.customer_type); set('cusn-code', c.customer_code); set('cusn-name', c.name); set('cusn-bizname', c.business_name);
  set('cusn-displayname', c.display_name || c.name); set('cusn-group', c.group_name); set('cusn-status', c.status === 'archived' ? 'Inactive' : 'Active');
  set('cusn-creditlimit', c.credit_limit); set('cusn-phone', c.mobile); set('cusn-altphone', c.alternate_phone);
  set('cusn-email', c.email); set('cusn-whatsapp', c.whatsapp_no); set('cusn-billing', c.billing_address);
  set('cusn-shipping', c.shipping_address); set('cusn-city', c.billing_city); set('cusn-district', c.district); populateCusnStateDropdowns();
  set('cusn-state', c.state); set('cusn-pincode', c.billing_pincode); set('cusn-shipcity', c.shipping_city);
  set('cusn-shipstate', c.shipping_state); set('cusn-shippincode', c.shipping_pincode);
  set('cusn-gst', c.gstin); set('cusn-pan', c.pan_no); set('cusn-biztype', c.business_type); set('cusn-tan', c.tan_no);
  set('cusn-iec', c.iec_no); set('cusn-tradelicense', c.trade_license_no); set('cusn-currency', c.currency || 'INR');
  set('cusn-paymentterms', c.payment_terms); set('cusn-openingbal', c.opening_balance || 0); set('cusn-openingbaltype', c.opening_balance_type || 'Debit');
  populateSalesExecDropdown(c.sales_executive || '', 'cusn-salesperson'); set('cusn-notes-inline', c.notes); set('cusn-notes-sidebar', c.notes);
  document.getElementById('cusn-sameaddr').checked = (c.billing_address === c.shipping_address);
  onCusnSameAddrToggle();
  document.getElementById('cusn-sum-code').textContent = c.customer_code || '—';
  document.getElementById('cusn-sum-status').textContent = c.status === 'archived' ? 'Inactive' : 'Active';
  document.getElementById('cusn-sum-creditlimit').textContent = fmt_money(c.credit_limit||0);
  document.getElementById('cusn-sum-openingbal').textContent = fmt_money(c.opening_balance||0);
  document.getElementById('cusn-sum-paymentterms').textContent = c.payment_terms || 'Not Set';
  try {
    const r = await api('api/customers.php?summary_for=' + id);
    document.getElementById('cusn-sum-currentbal').textContent = fmt_money(r.data?.outstanding || 0);
  } catch(e) { /* non-fatal */ }
  renderCusnDocs();
}

function cusnFileToDataUrl(file) {
  return new Promise(resolve => {
    if (file.size > 5*1024*1024) { toast(`⚠️ "${file.name}" is over 5MB — skipped`, 'warning'); return resolve(null); }
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => resolve(null);
    reader.readAsDataURL(file);
  });
}
async function cusnAddDocs(files) {
  for (const f of Array.from(files)) { const url = await cusnFileToDataUrl(f); if (url) CUSN.docs.push({ name: f.name, url }); }
  document.getElementById('cusn-docs-input').value = '';
  renderCusnDocs();
}
function cusnRemoveDoc(idx) { CUSN.docs.splice(idx, 1); renderCusnDocs(); }
function renderCusnDocs() {
  document.getElementById('cusn-docs-list').innerHTML = CUSN.docs.map((d, i) => {
    const name = d.name || (typeof d === 'string' ? d.split('/').pop() : 'Document');
    const url = d.url || (typeof d === 'string' ? d : null);
    return `<div class="pp-attach-row"><span><i class="fas fa-file"></i> ${escHtml(name)}</span><span class="pp-attach-actions">${url?`<button class="pp-attach-view" onclick="window.open('${url}','_blank')" title="View"><i class="fas fa-eye"></i></button>`:''}<button onclick="cusnRemoveDoc(${i})" title="Remove"><i class="fas fa-times"></i></button></span></div>`;
  }).join('');
}

async function saveCustomerEntry(mode) {
  const name = document.getElementById('cusn-name').value.trim();
  if (!document.getElementById('cusn-type').value) { toast('⚠️ Select a customer type', 'warning'); return; }
  if (!name) { toast('⚠️ Customer name is required', 'warning'); return; }
  if (!document.getElementById('cusn-displayname').value.trim()) { toast('⚠️ Display name is required', 'warning'); return; }
  if (!document.getElementById('cusn-phone').value.trim()) { toast('⚠️ Phone number is required', 'warning'); return; }
  if (!document.getElementById('cusn-billing').value.trim()) { toast('⚠️ Billing address is required', 'warning'); return; }
  if (!document.getElementById('cusn-city').value.trim()) { toast('⚠️ City is required', 'warning'); return; }
  if (!document.getElementById('cusn-state').value) { toast('⚠️ State is required', 'warning'); return; }
  if (!document.getElementById('cusn-pincode').value.trim()) { toast('⚠️ Pincode is required', 'warning'); return; }

  const sameAddr = document.getElementById('cusn-sameaddr').checked;
  const payload = {
    name, customer_type: document.getElementById('cusn-type').value, customer_code: document.getElementById('cusn-code').value.trim(),
    business_name: document.getElementById('cusn-bizname').value.trim(), display_name: document.getElementById('cusn-displayname').value.trim(),
    group_name: document.getElementById('cusn-group').value, status: document.getElementById('cusn-status').value === 'Active' ? 'active' : 'archived',
    credit_limit: parseFloat(document.getElementById('cusn-creditlimit').value) || 0,
    mobile: document.getElementById('cusn-phone').value.trim(), alternate_phone: document.getElementById('cusn-altphone').value.trim(),
    email: document.getElementById('cusn-email').value.trim(), whatsapp_no: document.getElementById('cusn-whatsapp').value.trim(),
    billing_address: document.getElementById('cusn-billing').value.trim(),
    shipping_address: sameAddr ? document.getElementById('cusn-billing').value.trim() : document.getElementById('cusn-shipping').value.trim(),
    billing_city: document.getElementById('cusn-city').value.trim(), district: document.getElementById('cusn-district').value.trim(), state: document.getElementById('cusn-state').value, billing_pincode: document.getElementById('cusn-pincode').value.trim(),
    shipping_city: sameAddr ? document.getElementById('cusn-city').value.trim() : document.getElementById('cusn-shipcity').value.trim(),
    shipping_state: sameAddr ? document.getElementById('cusn-state').value : document.getElementById('cusn-shipstate').value,
    shipping_pincode: sameAddr ? document.getElementById('cusn-pincode').value.trim() : document.getElementById('cusn-shippincode').value.trim(),
    gstin: document.getElementById('cusn-gst').value.trim(), pan_no: document.getElementById('cusn-pan').value.trim(),
    business_type: document.getElementById('cusn-biztype').value, tan_no: document.getElementById('cusn-tan').value.trim(),
    iec_no: document.getElementById('cusn-iec').value.trim(), trade_license_no: document.getElementById('cusn-tradelicense').value.trim(),
    currency: document.getElementById('cusn-currency').value, payment_terms: document.getElementById('cusn-paymentterms').value,
    opening_balance: parseFloat(document.getElementById('cusn-openingbal').value) || 0, opening_balance_type: document.getElementById('cusn-openingbaltype').value,
    sales_executive: document.getElementById('cusn-salesperson').value, notes: document.getElementById('cusn-notes-inline').value.trim(),
    documents: CUSN.docs.map(d => d.url || d),
  };

  const btn = event?.target?.closest('button');
  if (btn) btn.disabled = true;
  try {
    let newId = CUSN.editingId;
    if (CUSN.editingId) {
      await api('api/customers.php?id=' + CUSN.editingId, 'PUT', payload);
      consumeEditApproval(); toast('✅ Customer updated!', 'success');
    } else {
      const res = await api('api/customers.php', 'POST', payload);
      newId = res.id;
      toast('✅ "' + name + '" added as ' + res.customer_code + '!', 'success');
    }
    const r = await api('api/customers.php');
    STATE.customers = Array.isArray(r.data) ? r.data : STATE.customers;

    if (mode === 'new') { goToNewCustomerPage(); } else { cancelCustomerEntry(); }
  } catch(e) { toast('❌ ' + e.message, 'error'); }
  finally { if (btn) btn.disabled = false; }
}

let CUST_LIST_SEARCH = '';
let CUST_LIST_PAGE = 1;
const CUST_LIST_PAGESIZE = 10;
function filterCustomersList(q) { CUST_LIST_SEARCH = q || ''; CUST_LIST_PAGE = 1; renderCustomersList(); }
function resetCustomersFilter() {
  document.getElementById('custSearch').value = ''; CUST_LIST_SEARCH = '';
  document.getElementById('custTypeFilter').value = '';
  document.getElementById('custStatusFilterList').value = '';
  document.getElementById('custStateFilter').value = '';
  CUST_LIST_PAGE = 1;
  renderCustomersList();
}

// Per-customer outstanding, computed from real Sales data (total minus
// amount_received on non-cancelled sales) — not fabricated.
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

function populateCustStateFilter() {
  const sel = document.getElementById('custStateFilter');
  if (!sel) return;
  const cur = sel.value;
  const states = [...new Set((STATE.customers||[]).map(c => c.state).filter(Boolean))].sort();
  sel.innerHTML = '<option value="">All States</option>' + states.map(s => `<option>${escHtml(s)}</option>`).join('');
  if (cur) sel.value = cur;
}

async function renderCustomersList() {
  try {
    // Fetch both active and archived so the Status filter and Restore
    // action have real data to work with (previously only active ones
    // were ever loaded, silently breaking the "Inactive" filter option).
    const [activeR, archivedR] = await Promise.all([
      api('api/customers.php'),
      api('api/customers.php?status=archived').catch(() => ({ data: [] })),
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

async function restoreCustomer(id) {
  const c = (STATE.customers||[]).find(x => String(x.id) === String(id)); if (!c) return;
  try {
    await api('api/customers.php?action=restore&id=' + id, 'POST');
    toast(`✅ "${c.name}" restored`, 'success');
    renderCustomersList();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

async function deleteCustomerRich(id) {
  if (!assertCanDelete('this customer')) return;
  const c = (STATE.customers||[]).find(x => String(x.id) === String(id)); if (!c) return;
  const conf = await Swal.fire({
    title: 'Archive this customer?', text: `"${c.name}" will be moved out of your active customer list.`,
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Archive', confirmButtonColor: '#E53935',
    customClass: { popup: 'swal-compact' }
  });
  if (!conf.isConfirmed) return;
  try {
    await api('api/customers.php?id=' + id, 'DELETE');
    toast('📦 Customer archived', 'info');
    renderCustomersList();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}


// ADD PRODUCT TO STOCK (STOCK IN) — manual multi-product stock inward
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
