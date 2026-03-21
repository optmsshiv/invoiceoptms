/* ═══════════════════════════════════════════════════════
   OPTMS Tech Invoice Manager — app.js (PHP/MySQL build)
   All original JS from the standalone HTML goes here.
   This file adds the API save/load layer on top.
═══════════════════════════════════════════════════════

   SETUP INSTRUCTIONS:
   1. Copy all <script> content from optms_invoice_manager_v6.html
      into this file (or keep it inline in index.php).
   2. The functions below OVERRIDE the in-memory save functions
      to persist data to the PHP API instead.
   3. Paste these overrides AFTER the main app code.

═══════════════════════════════════════════════════════ */

// ── API Helper ────────────────────────────────────────
async function api(endpoint, method = 'GET', body = null) {
  const opts = {
    method,
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  };
  if (body) opts.body = JSON.stringify(body);
  const res  = await fetch(endpoint, opts);
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'API error');
  return data;
}

// ── Override: saveInvoice → POST/PUT to API ───────────
const _origSaveInvoice = window.saveInvoice;
window.saveInvoice = async function () {
  const d = getFormData();
  if (!d.cname || d.cname === 'Client Name') { toast('⚠️ Please enter client name', 'warning'); return; }
  if (formItems.length === 0) { toast('⚠️ Add at least one line item', 'warning'); return; }

  const clientSel = document.getElementById('f-client-select')?.value;
  const clientId  = clientSel ? parseInt(clientSel) : null;

  const payload = {
    invoice_number: d.num,
    client_id:      clientId,
    client_name:    d.cname,
    service_type:   d.svc,
    issued_date:    d.date,
    due_date:       d.due,
    status:         d.status,
    currency:       d.sym,
    subtotal:       d.sub,
    discount_pct:   d.disc,
    discount_amt:   d.discAmt,
    gst_amount:     d.gstAmt,
    grand_total:    d.grand,
    notes:          d.notes,
    bank_details:   d.bank,
    terms:          d.tnc,
    company_logo:   d.companyLogo,
    client_logo:    d.clientLogo,
    signature:      d.signature,
    qr_code:        d.qrUrl,
    template_id:    d.tpl,
    generated_by:   d.generatedBy,
    show_generated: d.showGeneratedBy ? 1 : 0,
    pdf_options:    d.popt,
    items: formItems.map(i => ({ desc: i.desc, qty: i.qty, rate: i.rate, gst: i.gst || 18 })),
  };

  try {
    let result;
    if (STATE.editingInvoiceId) {
      // Editing — find DB id
      const inv = STATE.invoices.find(i => i.id === STATE.editingInvoiceId);
      const dbId = inv?._dbId || inv?.id;
      result = await api(`api/invoices.php?id=${dbId}`, 'PUT', payload);
      toast('✅ Invoice updated!', 'success');
    } else {
      result = await api('api/invoices.php', 'POST', payload);
      toast(`✅ Invoice ${d.num} saved!`, 'success');
    }
    // Reload invoices from server
    const res = await api('api/invoices.php');
    if (res.data) STATE.invoices = res.data;
    STATE.filteredInvoices = [...STATE.invoices];
    renderInvoicesTable();
    renderDashRecent();
    renderDonutChart();
    document.getElementById('badge-invoices').textContent = STATE.invoices.length;
    updateDashStats();
  } catch (e) {
    toast('❌ Save failed: ' + e.message, 'error');
  }
};

// ── Override: confirmPaid → also POST payment to API ──
const _origConfirmPaid = window.confirmPaid;
window.confirmPaid = async function () {
  const inv = STATE.invoices.find(i => i.id === STATE.activeMenuInvoiceId);
  if (!inv) { _origConfirmPaid?.(); return; }

  const paymentDate = document.getElementById('paid-date').value;
  const method      = document.getElementById('paid-method').value;
  const txn         = document.getElementById('paid-txn').value;
  const amount      = document.getElementById('paid-amt').value;

  const payload = {
    invoice_id:     inv._dbId || parseInt(inv.id),
    invoice_number: inv.num,
    client_name:    (STATE.clients.find(c => c.id === inv.client) || {}).name || '',
    amount:         parseFloat(amount) || inv.amount,
    payment_date:   paymentDate,
    method,
    transaction_id: txn,
    status:         'Success',
  };

  try {
    await api('api/payments.php', 'POST', payload);
    // Reload data
    const [invRes, pmtRes] = await Promise.all([
      api('api/invoices.php'), api('api/payments.php')
    ]);
    if (invRes.data) STATE.invoices = invRes.data;
    if (pmtRes.data) STATE.payments = pmtRes.data;
    inv.status = 'Paid';
    renderInvoicesTable(); renderDonutChart(); renderDashRecent();
    renderPayments(); renderPaymentSummary();
    toast('✅ Invoice marked paid & payment recorded!', 'success');
  } catch (e) {
    toast('❌ ' + e.message, 'error');
  }
  closeModal('modal-paid');
};

// ── Override: saveNewClient → POST to API ─────────────
const _origSaveNewClient = window.saveNewClient;
window.saveNewClient = async function () {
  const name = (document.getElementById('nc-name')?.value || '').trim();
  if (!name) { toast('⚠️ Enter organization name', 'warning'); return; }

  const payload = {
    name,
    person:  document.getElementById('nc-person')?.value || '',
    email:   document.getElementById('nc-email')?.value  || '',
    wa:      document.getElementById('nc-wa')?.value     || '',
    gst:     document.getElementById('nc-gst')?.value    || '',
    color:   document.getElementById('nc-color')?.value  || '#00897B',
    addr:    document.getElementById('nc-addr')?.value   || '',
  };

  try {
    const editId = STATE._editCid;
    if (editId) {
      const inv = STATE.clients.find(c => c.id === editId);
      const dbId = inv?._dbId || parseInt(editId);
      await api(`api/clients.php?id=${dbId}`, 'PUT', payload);
      toast(`✅ Client updated!`, 'success');
      STATE._editCid = null;
    } else {
      await api('api/clients.php', 'POST', payload);
      toast(`✅ Client "${name}" added!`, 'success');
    }
    const res = await api('api/clients.php');
    if (res.data) STATE.clients = res.data;
    updateClientDropdown(); renderClients();
    closeModal('modal-addclient');
    ['nc-name','nc-person','nc-wa','nc-email','nc-gst','nc-addr'].forEach(id => {
      const e = document.getElementById(id); if (e) e.value = '';
    });
  } catch (e) {
    toast('❌ ' + e.message, 'error');
  }
};

// ── Override: deleteProduct → DELETE on API ───────────
const _origDeleteProduct = window.deleteProduct;
window.deleteProduct = async function (id) {
  const p = STATE.products.find(x => x.id === id); if (!p) return;
  const dbId = p._dbId || id.replace('p','');
  try {
    await api(`api/products.php?id=${dbId}`, 'DELETE');
    STATE.products = STATE.products.filter(x => x.id !== id);
    renderProducts();
    toast('🗑️ Service deleted', 'info');
  } catch (e) {
    toast('❌ ' + e.message, 'error');
  }
};

// ── Override: saveEditProd → PUT to API ───────────────
const _origSaveEditProd = window.saveEditProd;
window.saveEditProd = async function (id) {
  const idx = STATE.products.findIndex(x => x.id === id); if (idx < 0) return;
  const name = document.getElementById('ep-name')?.value?.trim();
  if (!name) { toast('Name required','warning'); return; }
  const payload = {
    name,
    category:    document.getElementById('ep-cat')?.value || 'Other',
    rate:        parseFloat(document.getElementById('ep-rate')?.value) || 0,
    hsn:         document.getElementById('ep-hsn')?.value || '998314',
    gst:         parseInt(document.getElementById('ep-gst')?.value) || 18,
  };
  const p     = STATE.products[idx];
  const dbId  = p._dbId || id.replace('p','');
  try {
    await api(`api/products.php?id=${dbId}`, 'PUT', payload);
    STATE.products[idx] = { ...p, ...payload, gst: payload.gst };
    renderProducts();
    toast('✅ Updated!', 'success');
  } catch (e) {
    toast('❌ ' + e.message, 'error');
  }
};

// ── Override: saveNewProduct → POST to API ────────────
const _origSaveNewProduct = window.saveNewProduct;
window.saveNewProduct = async function () {
  const name = document.getElementById('np-name')?.value?.trim();
  if (!name) { toast('⚠️ Service name required', 'warning'); return; }
  const payload = {
    name,
    category:    document.getElementById('np-cat')?.value || 'Other',
    rate:        parseFloat(document.getElementById('np-rate')?.value) || 0,
    hsn:         document.getElementById('np-hsn')?.value || '998314',
    gst:         parseInt(document.getElementById('np-gst')?.value) || 18,
  };
  try {
    const res = await api('api/products.php', 'POST', payload);
    const pRes = await api('api/products.php');
    if (pRes.data) STATE.products = pRes.data;
    document.getElementById('add-product-row')?.remove();
    renderProducts();
    toast(`✅ "${name}" added!`, 'success');
  } catch (e) {
    toast('❌ ' + e.message, 'error');
  }
};

// ── Override: saveCompanySettings → POST to API ───────
const _origSaveSettings = window.saveCompanySettings;
window.saveCompanySettings = async function () {
  const payload = {
    company_name:    document.getElementById('sc-name')?.value || '',
    company_gst:     document.getElementById('sc-gst')?.value  || '',
    company_phone:   document.getElementById('sc-phone')?.value|| '',
    company_email:   document.getElementById('sc-email')?.value|| '',
    company_website: document.getElementById('sc-web')?.value  || '',
    invoice_prefix:  document.getElementById('sc-prefix')?.value|| '',
    company_upi:     document.getElementById('sc-upi')?.value  || '',
    company_address: document.getElementById('sc-addr')?.value || '',
    company_logo:    document.getElementById('sc-logo')?.value || STATE.settings.logo || '',
    company_sign:    document.getElementById('sc-sign')?.value || STATE.settings.signature || '',
  };
  // Update local STATE
  STATE.settings.company   = payload.company_name;
  STATE.settings.gst       = payload.company_gst;
  STATE.settings.phone     = payload.company_phone;
  STATE.settings.email     = payload.company_email;
  STATE.settings.website   = payload.company_website;
  STATE.settings.prefix    = payload.invoice_prefix;
  STATE.settings.upi       = payload.company_upi;
  STATE.settings.address   = payload.company_address;
  STATE.settings.logo      = payload.company_logo;
  STATE.settings.signature = payload.company_sign;
  try {
    await api('api/settings.php', 'POST', payload);
    livePreview();
    toast('✅ Settings saved!', 'success');
  } catch (e) {
    toast('❌ ' + e.message, 'error');
  }
};

// ── Override: confirmDelete → DELETE on API ───────────
const _origConfirmDelete = window.confirmDelete;
window.confirmDelete = async function () {
  const inv = STATE.invoices.find(i => i.id === STATE.activeMenuInvoiceId);
  if (!inv) { closeModal('modal-delete'); return; }
  const dbId = inv._dbId || parseInt(inv.id);
  try {
    await api(`api/invoices.php?id=${dbId}`, 'DELETE');
    STATE.invoices = STATE.invoices.filter(i => i.id !== STATE.activeMenuInvoiceId);
    STATE.filteredInvoices = STATE.filteredInvoices.filter(i => i.id !== STATE.activeMenuInvoiceId);
    toast('🗑️ Invoice deleted', 'info');
    renderInvoicesTable(); renderDashRecent(); renderDonutChart();
  } catch (e) {
    toast('❌ ' + e.message, 'error');
  }
  closeModal('modal-delete');
};

// ── Upload helper: Server upload instead of base64 ────
window.handleLogoUpload = async function (input, targetId, previewId) {
  const file = input.files[0]; if (!file) return;
  if (file.size > 3 * 1024 * 1024) { toast('⚠️ Max 3MB', 'warning'); return; }

  const typeMap = {
    'f-company-logo': 'logo', 'sc-logo': 'logo',
    'f-signature':    'signature', 'sc-sign': 'signature',
    'f-client-logo':  'client_logo', 'f-qr': 'qr',
  };
  const type = typeMap[targetId] || 'logo';

  const formData = new FormData();
  formData.append('file', file);
  formData.append('type', type);

  try {
    const res  = await fetch('api/upload.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) throw new Error(data.error);

    const el = document.getElementById(targetId);
    if (el) { el.value = data.url; el.dispatchEvent(new Event('input')); }
    if (targetId === 'sc-logo')  STATE.settings.logo      = data.url;
    if (targetId === 'sc-sign')  STATE.settings.signature = data.url;

    // Show preview
    if (previewId) {
      const prev = document.getElementById(previewId);
      if (prev) {
        const isSign = previewId.includes('sign');
        prev.innerHTML = `<div style="display:inline-flex;align-items:center;gap:8px;padding:6px 10px;background:${isSign?'#1a1a2e':'var(--teal-bg)'};border-radius:8px;border:1px solid var(--border)">
          <img src="${data.url}" style="height:${isSign?'36px':'32px'};max-width:120px;object-fit:contain;border-radius:4px">
          <span style="font-size:11px;color:var(--muted)">${file.name}</span>
          <button onclick="clearLogoField('${targetId}','${previewId}')" style="border:none;background:none;cursor:pointer;color:var(--red);font-size:13px"><i class="fas fa-times"></i></button>
        </div>`;
      }
    }
    toast('✅ Uploaded!', 'success');
  } catch (e) {
    // Fallback to base64 if server upload fails
    const reader = new FileReader();
    reader.onload = ev => {
      const el = document.getElementById(targetId);
      if (el) { el.value = ev.target.result; el.dispatchEvent(new Event('input')); }
      toast('✅ Image loaded (base64 mode)', 'success');
    };
    reader.readAsDataURL(file);
  }
};

console.log('OPTMS Invoice Manager — PHP/MySQL mode active');
