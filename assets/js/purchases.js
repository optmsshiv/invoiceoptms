// ================================================================
//  assets/js/purchases.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  No MPA adjustments needed — self-contained in the original SPA.
//
//  NOTE: populatePurchaseSupplierDropdown() also lives in
//  suppliers.js (identical copy in the SPA, called from there too).
//  Kept here as well since this page needs it independently and
//  suppliers.js may not be loaded on this page.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['suppliers', 'products', 'purchases', 'settings']);
  renderPurchases();
});

const PUR = { search: '', editingId: null, items: [] };
let purItemSeq = 1;

function filterPurchases(q) { PUR.search = q || ''; renderPurchases(); }

function renderPurchases() {
  const tbody = document.getElementById('purchasesTbody');
  if (!tbody) return;
  const statusF = document.getElementById('purStatusFilter')?.value || '';
  let list = STATE.purchases || [];
  if (PUR.search) {
    const q = PUR.search.toLowerCase();
    list = list.filter(p => (p.purchase_no || '').toLowerCase().includes(q) || (p.supplier_name || '').toLowerCase().includes(q) || (p.supplier_invoice_ref || '').toLowerCase().includes(q));
  }
  if (statusF) list = list.filter(p => p.status === statusF);
  document.getElementById('purInfo').textContent = list.length + ' purchase' + (list.length === 1 ? '' : 's');
  document.getElementById('purCountInfo').textContent = (STATE.purchases || []).length + ' total';
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px">No purchases yet — click "Add Purchase" to record one</td></tr>`;
    return;
  }
  const statusColor = { Pending: '#FFA000', Received: '#1976D2', Partial: '#E65100', Paid: '#00897B' };
  tbody.innerHTML = list.map(p => `
    <tr>
      <td><strong>${escHtml(p.purchase_no)}</strong></td>
      <td>${escHtml(p.supplier_name || '—')}</td>
      <td>${fmt_date_disp(p.purchase_date)}</td>
      <td>${p.item_count ?? ''}</td>
      <td>${fmt_money_sym(p.total, p.currency === 'INR' ? '₹' : (p.currency === 'USD' ? '$' : (p.currency === 'EUR' ? '€' : (p.currency === 'GBP' ? '£' : ''))))}</td>
      <td><span style="font-size:11px;font-weight:700;color:${statusColor[p.status] || '#888'};background:${statusColor[p.status] || '#888'}18;padding:2px 8px;border-radius:10px">${escHtml(p.status)}</span></td>
      <td>
        <div class="action-cell">
          <button class="act-btn" title="Edit" onclick="editPurchase(${p.id})"><i class="fas fa-pen"></i></button>
          <button class="act-btn" title="Delete" onclick="deletePurchase(${p.id})"><i class="fas fa-trash"></i></button>
        </div>
      </td>
    </tr>`).join('');
}

function populatePurchaseSupplierDropdown() {
  const sel = document.getElementById('pur-supplier');
  if (!sel) return;
  const cur = sel.value;
  sel.innerHTML = '<option value="">Select supplier…</option>' +
    (STATE.suppliers || []).map(s => `<option value="${s.id}">${escHtml(s.name)}</option>`).join('');
  if (cur) sel.value = cur;
}

function addPurchaseItem(prefill) {
  PUR.items.push(Object.assign({ id: purItemSeq++, product_id: '', description: '', hsn: '', qty: 1, unit: 'pcs', rate: 0, gst_pct: STATE.settings.defaultGST ?? 18 }, prefill || {}));
  renderPurchaseItems();
}

function removePurchaseItem(id) {
  PUR.items = PUR.items.filter(i => i.id !== id);
  renderPurchaseItems();
}

function renderPurchaseItems() {
  const tbody = document.getElementById('pur-items-tbody');
  if (!tbody) return;
  tbody.innerHTML = PUR.items.map(it => {
    const amt = (it.qty || 0) * (it.rate || 0);
    return `<tr>
      <td>
        <select style="width:100%;font-size:12px" onchange="onPurItemProductChange(${it.id}, this.value)">
          <option value="">— free text —</option>
          ${STATE.products.map(p => `<option value="${p.id}" ${String(it.product_id) === String(p.id) ? 'selected' : ''}>${escHtml(p.name)}</option>`).join('')}
        </select>
        ${!it.product_id ? `<input style="width:100%;font-size:12px;margin-top:4px" placeholder="Item description" value="${escHtml(it.description)}" oninput="updatePurItem(${it.id},'description',this.value)">` : ''}
      </td>
      <td><input style="width:70px;font-size:12px" value="${escHtml(it.hsn)}" oninput="updatePurItem(${it.id},'hsn',this.value)"></td>
      <td><input type="number" style="width:60px;font-size:12px" value="${it.qty}" min="0" step="0.001" oninput="updatePurItem(${it.id},'qty',this.value)"></td>
      <td><input style="width:55px;font-size:12px" value="${escHtml(it.unit)}" oninput="updatePurItem(${it.id},'unit',this.value)"></td>
      <td><input type="number" style="width:80px;font-size:12px" value="${it.rate}" min="0" step="0.01" oninput="updatePurItem(${it.id},'rate',this.value)"></td>
      <td><input type="number" style="width:55px;font-size:12px" value="${it.gst_pct}" min="0" step="0.01" oninput="updatePurItem(${it.id},'gst_pct',this.value)"></td>
      <td style="font-weight:600;white-space:nowrap">${fmt_money(amt)}</td>
      <td><button class="item-del" onclick="removePurchaseItem(${it.id})" title="Remove"><i class="fas fa-times"></i></button></td>
    </tr>`;
  }).join('');
  calcPurchaseTotals();
}

function onPurItemProductChange(id, productId) {
  const it = PUR.items.find(i => i.id === id); if (!it) return;
  it.product_id = productId || '';
  if (productId) {
    const p = STATE.products.find(x => String(x.id) === String(productId));
    if (p) { it.description = p.name; it.hsn = p.hsn || it.hsn; it.rate = parseFloat(p.rate) || it.rate; it.gst_pct = (p.gst !== undefined ? p.gst : it.gst_pct); }
  }
  renderPurchaseItems();
}

function updatePurItem(id, field, val) {
  const it = PUR.items.find(i => i.id === id); if (!it) return;
  it[field] = (field === 'description' || field === 'hsn' || field === 'unit') ? val : (parseFloat(val) || 0);
  renderPurchaseItems();
}

function calcPurchaseTotals() {
  const sym = { INR: '₹', USD: '$', EUR: '€', GBP: '£' }[document.getElementById('pur-currency')?.value] || '₹';
  let subtotal = 0, gst = 0;
  PUR.items.forEach(it => {
    const amt = (it.qty || 0) * (it.rate || 0);
    subtotal += amt;
    gst += amt * ((it.gst_pct || 0) / 100);
  });
  document.getElementById('pur-subtotal').textContent = fmt_money_sym(subtotal, sym);
  document.getElementById('pur-gst').textContent       = fmt_money_sym(gst, sym);
  document.getElementById('pur-total').textContent     = fmt_money_sym(subtotal + gst, sym);
}

function openAddPurchaseModal() {
  PUR.editingId = null;
  PUR.items = [];
  document.querySelector('#modal-addpurchase .modal-header span').textContent = 'Add New Purchase';
  populatePurchaseSupplierDropdown();
  document.getElementById('pur-supplier').value = '';
  document.getElementById('pur-date').value = fmt_date(new Date());
  document.getElementById('pur-invref').value = '';
  document.getElementById('pur-no').value = '';
  document.getElementById('pur-currency').value = 'INR';
  document.getElementById('pur-fx').value = '1';
  document.getElementById('pur-status').value = 'Pending';
  document.getElementById('pur-notes').value = '';
  addPurchaseItem();
  openModal('modal-addpurchase');
}

async function editPurchase(id) {
  try {
    const r = await api('api/purchases.php?id=' + id);
    const p = r.data;
    PUR.editingId = id;
    PUR.items = (p.items || []).map(it => ({ id: purItemSeq++, product_id: it.product_id || '', description: it.description, hsn: it.hsn, qty: parseFloat(it.qty), unit: it.unit, rate: parseFloat(it.rate), gst_pct: parseFloat(it.gst_pct) }));
    document.querySelector('#modal-addpurchase .modal-header span').textContent = 'Edit Purchase';
    populatePurchaseSupplierDropdown();
    document.getElementById('pur-supplier').value = p.supplier_id;
    document.getElementById('pur-date').value = p.purchase_date;
    document.getElementById('pur-invref').value = p.supplier_invoice_ref || '';
    document.getElementById('pur-no').value = p.purchase_no;
    document.getElementById('pur-currency').value = p.currency || 'INR';
    document.getElementById('pur-fx').value = p.exchange_rate || 1;
    document.getElementById('pur-status').value = p.status;
    document.getElementById('pur-notes').value = p.notes || '';
    renderPurchaseItems();
    openModal('modal-addpurchase');
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

async function savePurchase() {
  const supplierId = document.getElementById('pur-supplier').value;
  if (!supplierId) { toast('⚠️ Select a supplier', 'warning'); return; }
  if (!PUR.items.length) { toast('⚠️ Add at least one item', 'warning'); return; }
  const btn = document.getElementById('pur-save-btn');
  if (btn) { if (btn.disabled) return; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…'; }
  const payload = {
    supplier_id: parseInt(supplierId),
    purchase_no: document.getElementById('pur-no').value.trim(),
    supplier_invoice_ref: document.getElementById('pur-invref').value.trim(),
    purchase_date: document.getElementById('pur-date').value,
    currency: document.getElementById('pur-currency').value,
    exchange_rate: parseFloat(document.getElementById('pur-fx').value) || 1,
    status: document.getElementById('pur-status').value,
    notes: document.getElementById('pur-notes').value.trim(),
    items: PUR.items.map(it => ({ product_id: it.product_id || null, description: it.description, hsn: it.hsn, qty: it.qty, unit: it.unit, rate: it.rate, gst_pct: it.gst_pct })),
  };
  try {
    if (PUR.editingId) {
      await api('api/purchases.php?id=' + PUR.editingId, 'PUT', payload);
      toast('✅ Purchase updated!', 'success');
    } else {
      await api('api/purchases.php', 'POST', payload);
      toast('✅ Purchase recorded!', 'success');
    }
    const [r, prd] = await Promise.all([api('api/purchases.php'), api('api/products.php')]);
    STATE.purchases = Array.isArray(r.data) ? r.data : STATE.purchases;
    STATE.products  = Array.isArray(prd.data) ? prd.data : STATE.products;
    PUR.editingId = null;
    closeModal('modal-addpurchase');
    renderPurchases();
  } catch (e) { toast('❌ ' + e.message, 'error'); }
  finally { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Save Purchase'; } }
}

async function deletePurchase(id) {
  const p = (STATE.purchases || []).find(x => String(x.id) === String(id)); if (!p) return;
  const conf = await Swal.fire({
    title: 'Delete this purchase?',
    text: `"${p.purchase_no}" and its stock-in entries will be permanently removed. This cannot be undone.`,
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#E53935',
    customClass: { popup: 'swal-compact' },
  });
  if (!conf.isConfirmed) return;
  try {
    await api('api/purchases.php?id=' + id, 'DELETE');
    STATE.purchases = STATE.purchases.filter(x => String(x.id) !== String(id));
    renderPurchases();
    toast('🗑️ Purchase deleted', 'info');
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}
