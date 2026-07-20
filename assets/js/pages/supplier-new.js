const INDIA_STATES = ['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat','Haryana',
  'Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram',
  'Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand',
  'West Bengal','Delhi','Jammu and Kashmir','Ladakh','Chandigarh','Puducherry'];



// ============================================================
// supplier-new.js — page-specific JS for pages/supplier-new.php
// Depends on: common.js, shared-data.js
//
// EDIT MODE: suppliers.js's editSupplierRich() redirects here with
// ?edit_id=X; loadSupplierForEdit() below does the field population.
// ============================================================
const SUPN = { editingId: null, docs: [] };

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['suppliers', 'settings']);
  const params = new URLSearchParams(window.location.search);
  const editId = params.get('edit_id');
  if (editId) {
    await loadSupplierForEdit(editId);
  } else {
    initNewSupplierPage();
  }
});

function initNewSupplierPage() {
  SUPN.editingId = null;
  SUPN.docs = [];
  document.getElementById('supn-title').textContent = 'Add Supplier / Farmer';
  document.getElementById('supn-crumb').textContent = 'Add New';
  document.getElementById('sup-type').value = '';
  ['sup-name','sup-contactperson','sup-mobile','sup-email','sup-website','sup-address','sup-city','sup-district','sup-pincode',
   'sup-gstin','sup-pan','sup-aadhaar','sup-statecode','sup-tan','sup-msme','sup-fssai','sup-bankname','sup-bankacc',
   'sup-ifsc','sup-accholder','sup-notes'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  document.getElementById('sup-regdate').value = '';
  document.getElementById('sup-bizNature').value = '';
  populateSupStateDropdown();
  document.getElementById('sup-state').value = '';
  document.getElementById('sup-country').value = 'India';
  document.getElementById('sup-creditlimit').value = '';
  document.getElementById('sup-openingbal').value = 0;
  document.getElementById('sup-paymentterms').value = '';
  document.getElementById('sup-pricelist').value = '';
  document.getElementById('sup-status').classList.add('on');
  document.getElementById('sup-docs-input').value = '';
  onSupplierTypeChangeRich();
  renderSupDocs();
}
function cancelSupplierEntry() {
  window.location.href = '/pages/suppliers.php';
}


function onSupplierTypeChangeRich() {
  const isFarmer = document.getElementById('sup-type').value === 'Farmer';
  document.getElementById('sup-gstin-wrap').style.display = isFarmer ? 'none' : 'grid';
  document.getElementById('sup-farmer-note').style.display = isFarmer ? 'block' : 'none';
  if (isFarmer) document.getElementById('sup-gstin').value = '';
}

async function saveSupplierEntry() {
  const name = document.getElementById('sup-name').value.trim();
  if (!document.getElementById('sup-type').value) { toast('⚠️ Select a supplier type', 'warning'); return; }
  if (!name) { toast('⚠️ Name / Company / Organization is required', 'warning'); return; }
  if (!document.getElementById('sup-contactperson').value.trim()) { toast('⚠️ Contact person is required', 'warning'); return; }
  if (!document.getElementById('sup-mobile').value.trim()) { toast('⚠️ Mobile number is required', 'warning'); return; }
  if (!document.getElementById('sup-address').value.trim()) { toast('⚠️ Address is required', 'warning'); return; }
  if (!document.getElementById('sup-city').value.trim()) { toast('⚠️ City is required', 'warning'); return; }
  if (!document.getElementById('sup-state').value) { toast('⚠️ State is required', 'warning'); return; }
  if (!document.getElementById('sup-pincode').value.trim()) { toast('⚠️ Pincode is required', 'warning'); return; }

  const payload = {
    name, supplier_type: document.getElementById('sup-type').value,
    contact_person: document.getElementById('sup-contactperson').value.trim(),
    phone: document.getElementById('sup-mobile').value.trim(), email: document.getElementById('sup-email').value.trim(),
    date_of_registration: document.getElementById('sup-regdate').value || null,
    business_nature: document.getElementById('sup-bizNature').value, website: document.getElementById('sup-website').value.trim(),
    address: document.getElementById('sup-address').value.trim(), city: document.getElementById('sup-city').value.trim(), district: document.getElementById('sup-district').value.trim(),
    state: document.getElementById('sup-state').value, pincode: document.getElementById('sup-pincode').value.trim(),
    country: document.getElementById('sup-country').value,
    gst_number: document.getElementById('sup-gstin').value.trim(), pan_no: document.getElementById('sup-pan').value.trim(),
    aadhaar_no: document.getElementById('sup-aadhaar').value.trim(), state_code: document.getElementById('sup-statecode').value.trim(),
    tan_no: document.getElementById('sup-tan').value.trim(), msme_no: document.getElementById('sup-msme').value.trim(),
    fssai_no: document.getElementById('sup-fssai').value.trim(),
    bank_name: document.getElementById('sup-bankname').value.trim(), bank_account_no: document.getElementById('sup-bankacc').value.trim(),
    ifsc_code: document.getElementById('sup-ifsc').value.trim(), account_holder_name: document.getElementById('sup-accholder').value.trim(),
    credit_limit: parseFloat(document.getElementById('sup-creditlimit').value) || 0,
    opening_balance: parseFloat(document.getElementById('sup-openingbal').value) || 0,
    payment_terms: document.getElementById('sup-paymentterms').value, default_price_list: document.getElementById('sup-pricelist').value,
    notes: document.getElementById('sup-notes').value.trim(),
    status: document.getElementById('sup-status').classList.contains('on') ? 'active' : 'archived',
    documents: SUPN.docs.map(d => d.url || d),
  };

  const btn = event?.target?.closest('button');
  if (btn) btn.disabled = true;
  try {
    if (SUPN.editingId) {
      await api('/api/suppliers.php?id=' + SUPN.editingId, 'PUT', payload);
      consumeEditApproval(); toast('✅ Supplier updated!', 'success');
    } else {
      await api('/api/suppliers.php', 'POST', payload);
      toast('✅ "' + name + '" added!', 'success');
    }
    const r = await api('/api/suppliers.php');
    STATE.suppliers = Array.isArray(r.data) ? r.data : STATE.suppliers;
    cancelSupplierEntry();
    renderSuppliers();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
  finally { if (btn) btn.disabled = false; }
}

async function supAddDocs(files) {
  for (const f of Array.from(files)) { const url = await supFileToDataUrl(f); if (url) SUPN.docs.push({ name: f.name, url }); }
  document.getElementById('sup-docs-input').value = '';
  renderSupDocs();
}

function supFileToDataUrl(file) {
  return new Promise(resolve => {
    if (file.size > 5*1024*1024) { toast(`⚠️ "${file.name}" is over 5MB — skipped`, 'warning'); return resolve(null); }
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => resolve(null);
    reader.readAsDataURL(file);
  });
}

function supRemoveDoc(idx) { SUPN.docs.splice(idx, 1); renderSupDocs(); }

function renderSupDocs() {
  document.getElementById('sup-docs-list').innerHTML = SUPN.docs.map((d, i) => {
    const name = d.name || (typeof d === 'string' ? d.split('/').pop() : 'Document');
    const url = d.url || (typeof d === 'string' ? d : null);
    return `<div class="pp-attach-row"><span><i class="fas fa-file"></i> ${escHtml(name)}</span><span class="pp-attach-actions">${url?`<button class="pp-attach-view" onclick="window.open('${url}','_blank')" title="View"><i class="fas fa-eye"></i></button>`:''}<button onclick="supRemoveDoc(${i})" title="Remove"><i class="fas fa-times"></i></button></span></div>`;
  }).join('');
}

function clearSupplierAutofill() {
  document.getElementById('pn-mobile').value = '';
  document.getElementById('pn-state').value = '';
  document.getElementById('pn-district').value = '';
  document.getElementById('pn-address').value = '';
  document.getElementById('pn-gstin').value = '';
}

async function loadSupplierForEdit(id) {
  const s = STATE.suppliers.find(x => String(x.id) === String(id)); if (!s) return;
  SUPN.editingId = id;
  SUPN.docs = Array.isArray(s.documents) ? [...s.documents] : [];
  document.getElementById('supn-title').textContent = 'Edit Supplier / Farmer';
  document.getElementById('supn-crumb').textContent = s.name;
  const set = (id2, val) => { const el = document.getElementById(id2); if (el) el.value = val ?? ''; };
  set('sup-type', s.supplier_type || ''); set('sup-name', s.name); set('sup-contactperson', s.contact_person);
  set('sup-mobile', s.phone); set('sup-email', s.email); set('sup-regdate', s.date_of_registration);
  set('sup-bizNature', s.business_nature); set('sup-website', s.website);
  populateSupStateDropdown();
  set('sup-address', s.address); set('sup-city', s.city); set('sup-district', s.district); set('sup-state', s.state); set('sup-pincode', s.pincode);
  set('sup-country', s.country || 'India');
  set('sup-gstin', s.gst_number); set('sup-pan', s.pan_no); set('sup-aadhaar', s.aadhaar_no);
  set('sup-statecode', s.state_code); set('sup-tan', s.tan_no); set('sup-msme', s.msme_no); set('sup-fssai', s.fssai_no);
  set('sup-bankname', s.bank_name); set('sup-bankacc', s.bank_account_no); set('sup-ifsc', s.ifsc_code);
  set('sup-accholder', s.account_holder_name); set('sup-creditlimit', s.credit_limit);
  set('sup-openingbal', s.opening_balance || 0); set('sup-paymentterms', s.payment_terms); set('sup-pricelist', s.default_price_list);
  set('sup-notes', s.notes);
  document.getElementById('sup-status').classList.toggle('on', (s.status||'active') === 'active');
  onSupplierTypeChangeRich();
  renderSupDocs();
}

function populateSupStateDropdown() {
  const sel = document.getElementById('sup-state');
  if (!sel) return;
  sel.innerHTML = '<option value="">Select state</option>' + INDIA_STATES.map(s => `<option>${s}</option>`).join('');
}
