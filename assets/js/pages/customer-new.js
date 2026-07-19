// ============================================================
// customer-new.js — page-specific JS for pages/customer-new.php
// Depends on: common.js, app.js, sales-shared.js, edit-approval-shared.js
//
// Reached two ways:
//   /pages/customer-new.php                    — plain "add customer"
//   /pages/customer-new.php?return_to=sale-new  — opened from the Sale
//     form's "Add New Customer" button (see sale-new.js's snSaveDraft());
//     on save/cancel this sends the user back to sale-new.php?restore=1
//     so their in-progress sale is restored.
// ============================================================
const CUSN = { editingId: null, docs: [], returnToSale: false };

document.addEventListener('DOMContentLoaded', async () => {
  await bootSalesPageState();
  const params = new URLSearchParams(window.location.search);
  CUSN.returnToSale = params.get('return_to') === 'sale-new';
  const editId = params.get('edit_id');
  if (editId) {
    await loadCustomerForEdit(editId);
  } else {
    await initNewCustomerDefaults();
  }
});

function cancelCustomerEntry() {
  if (CUSN.returnToSale) {
    window.location.href = '/pages/sale-new.php?restore=1';
    return;
  }
  window.location.href = '/pages/customers.php';
}

async function cusnAddDocs(files) {
  for (const f of Array.from(files)) { const url = await cusnFileToDataUrl(f); if (url) CUSN.docs.push({ name: f.name, url }); }
  document.getElementById('cusn-docs-input').value = '';
  renderCusnDocs();
}

function onCusnSameAddrToggle() {
  const same = document.getElementById('cusn-sameaddr').checked;
  document.getElementById('cusn-shipping').disabled = same;
  document.getElementById('cusn-shipaddr-row').style.display = same ? 'none' : 'grid';
  if (same) document.getElementById('cusn-shipping').value = document.getElementById('cusn-billing').value;
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
      await api('/api/customers.php?id=' + CUSN.editingId, 'PUT', payload);
      consumeEditApproval(); toast('✅ Customer updated!', 'success');
    } else {
      const res = await api('/api/customers.php', 'POST', payload);
      newId = res.id;
      toast('✅ "' + name + '" added as ' + res.customer_code + '!', 'success');
    }
    const r = await api('/api/customers.php');
    STATE.customers = Array.isArray(r.data) ? r.data : STATE.customers;

    if (CUSN.returnToSale && mode !== 'new') {
      window.location.href = '/pages/sale-new.php?restore=1&new_customer_id=' + newId;
      return;
    }
    if (mode === 'new') {
      window.location.href = '/pages/customer-new.php';
    } else {
      window.location.href = '/pages/customers.php';
    }
  } catch(e) { toast('❌ ' + e.message, 'error'); }
  finally { if (btn) btn.disabled = false; }
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

function renderCusnDocs() {
  document.getElementById('cusn-docs-list').innerHTML = CUSN.docs.map((d, i) => {
    const name = d.name || (typeof d === 'string' ? d.split('/').pop() : 'Document');
    const url = d.url || (typeof d === 'string' ? d : null);
    return `<div class="pp-attach-row"><span><i class="fas fa-file"></i> ${escHtml(name)}</span><span class="pp-attach-actions">${url?`<button class="pp-attach-view" onclick="window.open('${url}','_blank')" title="View"><i class="fas fa-eye"></i></button>`:''}<button onclick="cusnRemoveDoc(${i})" title="Remove"><i class="fas fa-times"></i></button></span></div>`;
  }).join('');
}

function cusnRemoveDoc(idx) { CUSN.docs.splice(idx, 1); renderCusnDocs(); }

async function populateCusnSalesPersonDropdown() {
  const sel = document.getElementById('cusn-salesperson');
  if (!sel) return;
  try {
    if (!STATE.team || !STATE.team.length) {
      const r = await api('/api/team.php?action=list');
      STATE.team = Array.isArray(r.data) ? r.data : [];
    }
    sel.innerHTML = '<option value="">Select Sales Person</option>' + STATE.team.map(u => `<option value="${escHtml(u.name)}">${escHtml(u.name)}</option>`).join('');
  } catch(e) { sel.innerHTML = '<option value="">Select Sales Person</option>'; }
}

function populateCusnStateDropdowns() {
  ['cusn-state','cusn-shipstate'].forEach(id => {
    const sel = document.getElementById(id);
    if (!sel) return;
    sel.innerHTML = '<option value="">Select state</option>' + INDIA_STATES.map(s => `<option>${s}</option>`).join('');
  });
}

async function loadCustomerForEdit(id) {
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
  await populateCusnSalesPersonDropdown();
  populateSalesExecDropdown(c.sales_executive || '', 'cusn-salesperson'); set('cusn-notes-inline', c.notes); set('cusn-notes-sidebar', c.notes);
  document.getElementById('cusn-sameaddr').checked = (c.billing_address === c.shipping_address);
  onCusnSameAddrToggle();
  document.getElementById('cusn-sum-code').textContent = c.customer_code || '—';
  document.getElementById('cusn-sum-status').textContent = c.status === 'archived' ? 'Inactive' : 'Active';
  document.getElementById('cusn-sum-creditlimit').textContent = fmt_money(c.credit_limit||0);
  document.getElementById('cusn-sum-openingbal').textContent = fmt_money(c.opening_balance||0);
  document.getElementById('cusn-sum-paymentterms').textContent = c.payment_terms || 'Not Set';
  try {
    const r = await api('/api/customers.php?summary_for=' + id);
    document.getElementById('cusn-sum-currentbal').textContent = fmt_money(r.data?.outstanding || 0);
  } catch(e) { /* non-fatal */ }
  renderCusnDocs();
}

async function initNewCustomerDefaults() {
  document.getElementById('cusn-status').value = 'Active';
  document.getElementById('cusn-currency').value = 'INR';
  document.getElementById('cusn-openingbaltype').value = 'Debit';
  populateCusnStateDropdowns();
  populateSalesExecDropdown(SERVER.user?.name || '', 'cusn-salesperson');
  await populateCusnSalesPersonDropdown();
  document.getElementById('cusn-sameaddr').checked = true;
  onCusnSameAddrToggle();
  renderCusnDocs();
}
