// ================================================================
//  assets/js/sale-new.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  For pages/sales/sale-new.php (New/Edit Sale Entry — a real page
//  now, not a modal or in-SPA view switch).
//
//  MPA CHANGE: page load checks the URL for ?id= — if present it
//  loads and populates the form for editing (old SPA's editSale());
//  if absent it initializes a blank form (old SPA's goToNewSale()).
//  Both no longer call showPage() since this IS the page.
//  "Cancel"/"Save" now navigate to /pages/sales/sales.php via a
//  real redirect instead of an in-page view toggle.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['sales', 'customers', 'products', 'stock', 'settings']);

  const params = new URLSearchParams(window.location.search);
  const editId = params.get('id');
  if (editId) {
    await editSale(editId);
  } else {
    resetSaleForm();
  }
});

// ══════════════════════════════════════════
// SALES MODULE (full page) — new & separate from the original Invoices
// system, gated behind business_type='product'. Writes stock OUT via
// api/sales.php, closing the loop with Purchases' stock IN.
// ══════════════════════════════════════════
const SN = { editingId: null, items: [], attachments: [] };
let snItemSeq = 1;

function snEmptyItem() {
  return { id: snItemSeq++, product_id: '', description: '', variety_grade: '', batch_no: '',
    warehouse: 'Main Warehouse', qty: 0, unit: 'Kg', rate: 0, discount_pct: 0, gst_pct: 18 };
}


function resetSaleForm() {
  SN.editingId = null;
  SN.items = [snEmptyItem()];
  SN.attachments = [];
  document.getElementById('psn-title').textContent = 'New Sale Entry';
  document.getElementById('psn-subtitle').textContent = 'Create an export / local sale invoice';
  populateSaleCustomerDropdown();
  document.getElementById('sn-customer').value = '';
  clearCustomerAutofill();
  document.getElementById('sn-customertype').value = 'Domestic';
  document.getElementById('sn-shipping').value = '';
  document.getElementById('sn-salesexec').value = '';
  document.getElementById('sn-invno').value = '';
  document.getElementById('sn-invdate').value = fmt_date(new Date());
  document.getElementById('sn-duedate').value = '';
  document.getElementById('sn-paymentterms').value = 'Immediate';
  document.getElementById('sn-salestype').value = 'Local Sales';
  document.getElementById('sn-placeofsupply').value = '';
  document.getElementById('sn-currency').value = 'INR';
  document.getElementById('sn-weighingtype').value = 'Dharam Kanta';
  document.getElementById('sn-kantaname').value = '';
  document.getElementById('sn-slipno').value = '';
  document.getElementById('sn-weightdatetime').value = '';
  document.getElementById('sn-kantaoperator').value = '';
  document.getElementById('sn-kanta-gross').value = '';
  document.getElementById('sn-kanta-tare').value = '';
  document.getElementById('sn-kanta-net').value = '';
  document.getElementById('sn-kanta-moisture').value = '';
  document.getElementById('sn-kanta-dhaltakg').value = '';
  document.getElementById('sn-kanta-billable').value = '';
  calcSNWeightSummary();
  document.getElementById('sn-transportcharge').value = 0;
  document.getElementById('sn-loadingcharge').value = 0;
  document.getElementById('sn-packingcharge').value = 0;
  document.getElementById('sn-insurance').value = 0;
  document.getElementById('sn-othercharge').value = 0;
  document.getElementById('sn-roundoff').value = 0;
  document.getElementById('sn-discount').value = 0;
  document.getElementById('sn-paystatus').value = 'Pending';
  document.getElementById('sn-paymethod').value = 'Cash';
  document.getElementById('sn-split-panel').style.display = 'none';
  document.getElementById('sn-split-rows').innerHTML = '';
  document.getElementById('sn-amountreceived').value = 0;
  document.getElementById('sn-transactionno').value = '';
  document.getElementById('sn-paydate').value = fmt_date(new Date());
  document.getElementById('sn-customernotes').value = '';
  document.getElementById('sn-internalnotes').value = '';
  document.getElementById('sn-deliveryinstructions').value = '';
  document.getElementById('sn-preparedby').value = STATE.user?.name || '';
  document.getElementById('sn-checkedby').value = '';
  document.getElementById('sn-approvedby').value = '';
  document.getElementById('sn-attachments-input').value = '';
  document.getElementById('sn-customer-summary').innerHTML = '<div class="pne-summary-empty">Select a customer to see their sales history.</div>';
  renderSNItemsTable(); renderSNAttachments();
}

function cancelSaleEntry() {
  window.location.href = '/pages/sales/sales.php';
}

function clearCustomerAutofill() {
  document.getElementById('sn-mobile').value = '';
  document.getElementById('sn-gstin').value = '';
  document.getElementById('sn-state').value = '';
  document.getElementById('sn-district').value = '';
  document.getElementById('sn-billing').value = '';
}

function populateSaleCustomerDropdown() {
  const sel = document.getElementById('sn-customer');
  if (!sel) return;
  const cur = sel.value;
  sel.innerHTML = '<option value="">Select or add customer…</option>' +
    (STATE.customers||[]).map(c => `<option value="${c.id}">${escHtml(c.name)}</option>`).join('');
  if (cur) sel.value = cur;
}

function onSalesTypeChange() {
  calcSaleNewTotals();
}

async function onCustomerPicked() {
  const id = document.getElementById('sn-customer').value;
  if (!id) { clearCustomerAutofill(); document.getElementById('sn-customer-summary').innerHTML = '<div class="pne-summary-empty">Select a customer to see their sales history.</div>'; return; }
  const c = STATE.customers.find(x => String(x.id) === String(id));
  if (c) {
    document.getElementById('sn-mobile').value = c.mobile || '';
    document.getElementById('sn-gstin').value = c.gstin || '';
    document.getElementById('sn-state').value = c.state || '';
    document.getElementById('sn-district').value = c.district || '';
    document.getElementById('sn-billing').value = c.billing_address || '';
    document.getElementById('sn-shipping').value = c.shipping_address || c.billing_address || '';
    document.getElementById('sn-placeofsupply').value = c.state || '';
    if (c.customer_type) document.getElementById('sn-customertype').value = c.customer_type;
    if (c.sales_executive) document.getElementById('sn-salesexec').value = c.sales_executive;
    if (c.payment_terms) document.getElementById('sn-paymentterms').value = c.payment_terms;
  }
  calcSaleNewTotals();
  try {
    const r = await api('api/customers.php?summary_for=' + id);
    const sm = r.data || {};
    const outColor = (sm.outstanding||0) > 0 ? '#E53935' : 'inherit';
    document.getElementById('sn-customer-summary').innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
        <strong>${escHtml(c?.name||'—')}</strong>
        <span class="badge-verified" style="background:#E8F5E9;color:#2E7D32">Active</span>
      </div>
      <div class="pne-kv"><span>Type</span><strong>${escHtml(c?.customer_type||'—')}</strong></div>
      <div class="pne-kv"><span>Credit Limit</span><strong>${fmt_money(c?.credit_limit||0)}</strong></div>
      <div class="pne-kv"><span>Outstanding Balance</span><strong style="color:${outColor}">${fmt_money(sm.outstanding||0)}</strong></div>
      <div class="pne-kv"><span>Last Invoice</span><strong>${escHtml(sm.last_invoice_no||'—')}</strong></div>
      <div class="pne-kv"><span>Total Sales (YTD)</span><strong>${fmt_money(sm.total_sales_ytd||0)}</strong></div>
      <div class="pne-kv"><span>Avg. Payment Days</span><strong>${sm.avg_payment_days ?? '—'}</strong></div>`;
  } catch(e) { /* non-fatal */ }
}

function openAddCustomerModal() {
  ['cus-name','cus-mobile','cus-email','cus-gstin','cus-state','cus-district','cus-billing','cus-shipping','cus-paymentterms','cus-salesexec']
    .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  document.getElementById('cus-type').value = 'Domestic';
  document.getElementById('cus-creditlimit').value = 0;
  openModal('modal-addcustomer');
}

async function saveCustomer() {
  const name = document.getElementById('cus-name').value.trim();
  if (!name) { toast('⚠️ Customer name is required', 'warning'); return; }
  const payload = {
    name, customer_type: document.getElementById('cus-type').value,
    mobile: document.getElementById('cus-mobile').value.trim(), email: document.getElementById('cus-email').value.trim(),
    gstin: document.getElementById('cus-gstin').value.trim(), state: document.getElementById('cus-state').value.trim(),
    district: document.getElementById('cus-district').value.trim(), billing_address: document.getElementById('cus-billing').value.trim(),
    shipping_address: document.getElementById('cus-shipping').value.trim(),
    credit_limit: parseFloat(document.getElementById('cus-creditlimit').value) || 0,
    payment_terms: document.getElementById('cus-paymentterms').value.trim(), sales_executive: document.getElementById('cus-salesexec').value.trim(),
  };
  try {
    const res = await api('api/customers.php', 'POST', payload);
    const r = await api('api/customers.php');
    STATE.customers = Array.isArray(r.data) ? r.data : STATE.customers;
    closeModal('modal-addcustomer');
    populateSaleCustomerDropdown();
    document.getElementById('sn-customer').value = res.id;
    onCustomerPicked();
    toast('✅ "' + name + '" added!', 'success');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function addSaleNewItem() { SN.items.push(snEmptyItem()); renderSNItemsTable(); }
function removeSNItem(id) {
  if (SN.items.length <= 1) { toast('⚠️ At least one item is required', 'warning'); return; }
  SN.items = SN.items.filter(i => i.id !== id);
  renderSNItemsTable();
}

function snAvailableStock(productId) {
  const s = (STATE.stock||[]).find(x => String(x.product_id) === String(productId).replace(/\D/g,''));
  return s ? parseFloat(s.current_stock)||0 : 0;
}

function snCalcRow(it) {
  const qty = parseFloat(it.qty) || 0;
  const rate = parseFloat(it.rate) || 0;
  const disc = parseFloat(it.discount_pct) || 0;
  const gst = parseFloat(it.gst_pct) || 0;
  const lineSubtotal = +(qty * rate * (1 - disc/100)).toFixed(2);
  const taxAmount = +(lineSubtotal * gst / 100).toFixed(2);
  const lineTotal = +(lineSubtotal + taxAmount).toFixed(2);
  return { lineSubtotal, taxAmount, lineTotal };
}

function renderSNItemsTable() {
  const tbody = document.getElementById('sn-items-tbody');
  if (!tbody) return;
  tbody.innerHTML = SN.items.map((it, idx) => {
    const c = snCalcRow(it);
    const prod = STATE.products.find(p => String(p.id) === String(it.product_id));
    const avail = it.product_id ? snAvailableStock(it.product_id) : 0;
    return `<tr data-row="${it.id}">
      <td>${idx+1}</td>
      <td>
        <select onchange="onSNProductChange(${it.id}, this.value)">
          <option value="">Select product…</option>
          ${STATE.products.map(p => `<option value="${p.id}" ${String(it.product_id)===String(p.id)?'selected':''}>${escHtml(p.name)}</option>`).join('')}
        </select>
      </td>
      <td>${escHtml(prod?.category || '—')}</td>
      <td><input value="${escHtml(it.variety_grade)}" oninput="updateSNItem(${it.id},'variety_grade',this.value,true)"></td>
      <td>${escHtml(prod?.grade || '—')}</td>
      <td><input value="${escHtml(it.batch_no)}" placeholder="Optional" oninput="updateSNItem(${it.id},'batch_no',this.value,true)"></td>
      <td><span class="pne-computed" style="color:${avail<=0?'#E53935':(avail<(parseFloat(it.qty)||0)?'#E65100':'#00897B')}">${avail.toFixed(2)}</span></td>
      <td><input value="${escHtml(it.warehouse)}" oninput="updateSNItem(${it.id},'warehouse',this.value,true)"></td>
      <td><input type="number" value="${it.qty}" min="0" step="0.01" oninput="updateSNItem(${it.id},'qty',this.value)"></td>
      <td><input value="${escHtml(it.unit)}" oninput="updateSNItem(${it.id},'unit',this.value,true)"></td>
      <td><input type="number" value="${it.rate}" min="0" step="0.01" oninput="updateSNItem(${it.id},'rate',this.value)"></td>
      <td><input type="number" value="${it.discount_pct}" min="0" max="100" step="0.01" oninput="updateSNItem(${it.id},'discount_pct',this.value)"></td>
      <td><input type="number" value="${it.gst_pct}" min="0" max="28" step="0.01" oninput="updateSNItem(${it.id},'gst_pct',this.value)"></td>
      <td class="pne-computed" id="sn-tax-${it.id}">${fmt_money(c.taxAmount)}</td>
      <td class="pne-amount-cell" id="sn-total-${it.id}">${fmt_money(c.lineTotal)}</td>
      <td><button class="item-del" onclick="removeSNItem(${it.id})" title="Remove"><i class="fas fa-times"></i></button></td>
    </tr>`;
  }).join('');
  calcSaleNewTotals();
}

function onSNProductChange(id, productId) {
  const it = SN.items.find(i => i.id === id); if (!it) return;
  it.product_id = productId || '';
  if (productId) {
    const p = STATE.products.find(x => String(x.id) === String(productId));
    if (p) { it.description = p.name; it.rate = parseFloat(p.sale_rate || p.rate) || it.rate; it.gst_pct = p.gst !== undefined ? p.gst : it.gst_pct; }
  }
  renderSNItemsTable();
}

function updateSNItem(id, field, val, isText) {
  const it = SN.items.find(i => i.id === id); if (!it) return;
  it[field] = val;
  const c = snCalcRow(it);
  const taxEl = document.getElementById('sn-tax-' + id);     if (taxEl) taxEl.textContent = fmt_money(c.taxAmount);
  const totEl = document.getElementById('sn-total-' + id);   if (totEl) totEl.textContent = fmt_money(c.lineTotal);
  calcSaleNewTotals();
}

function calcSNWeightSummary() {
  const gross = parseFloat(document.getElementById('sn-kanta-gross').value) || 0;
  const tare  = parseFloat(document.getElementById('sn-kanta-tare').value) || 0;
  const net   = Math.max(0, gross - tare);
  const dhaltaKg = Math.max(0, parseFloat(document.getElementById('sn-kanta-dhaltakg').value) || 0);
  const billable = Math.max(0, net - dhaltaKg);
  document.getElementById('sn-kanta-net').value = net.toFixed(2);
  document.getElementById('sn-kanta-billable').value = billable.toFixed(2);
}

function calcSaleNewTotals() {
  let totalQty = 0, subtotal = 0, itemsTax = 0;
  SN.items.forEach(it => { const c = snCalcRow(it); totalQty += parseFloat(it.qty)||0; subtotal += c.lineSubtotal; itemsTax += c.taxAmount; });

  document.getElementById('sn-total-items').textContent = SN.items.length;
  document.getElementById('sn-total-qty').textContent = totalQty.toFixed(2) + ' Kg';
  document.getElementById('sn-sb-items').textContent = SN.items.length;
  document.getElementById('sn-sb-qty').textContent = totalQty.toFixed(2) + ' Kg';

  const addCharges = (parseFloat(document.getElementById('sn-transportcharge').value)||0)
    + (parseFloat(document.getElementById('sn-loadingcharge').value)||0)
    + (parseFloat(document.getElementById('sn-packingcharge').value)||0)
    + (parseFloat(document.getElementById('sn-insurance').value)||0)
    + (parseFloat(document.getElementById('sn-othercharge').value)||0);
  document.getElementById('sn-addcharges-total').textContent = fmt_money(addCharges);
  document.getElementById('sn-sum-addcharges2').textContent = fmt_money(addCharges);
  document.getElementById('sn-sum-subtotal').textContent = fmt_money(subtotal);

  const discount = parseFloat(document.getElementById('sn-discount').value) || 0;
  const taxable = Math.max(0, subtotal + addCharges - discount);
  document.getElementById('sn-sum-taxable').textContent = fmt_money(taxable);

  const totalTax = subtotal > 0 ? +(itemsTax * (taxable / subtotal)).toFixed(2) : 0;
  const isInterstate = document.getElementById('sn-salestype').value !== 'Local Sales';
  document.getElementById('sn-cgst-row').style.display = isInterstate ? 'none' : 'flex';
  document.getElementById('sn-sgst-row').style.display = isInterstate ? 'none' : 'flex';
  document.getElementById('sn-igst-row').style.display = isInterstate ? 'flex' : 'none';
  const cgst = isInterstate ? 0 : +(totalTax/2).toFixed(2);
  const sgst = isInterstate ? 0 : +(totalTax/2).toFixed(2);
  const igst = isInterstate ? totalTax : 0;
  document.getElementById('sn-sum-cgst').textContent = fmt_money(cgst);
  document.getElementById('sn-sum-sgst').textContent = fmt_money(sgst);
  document.getElementById('sn-sum-igst').textContent = fmt_money(igst);
  document.getElementById('sn-sum-totaltax').textContent = fmt_money(totalTax);

  const roundOff = parseFloat(document.getElementById('sn-roundoff').value) || 0;
  document.getElementById('sn-sum-roundoff').textContent = fmt_money(roundOff);

  const grand = +(taxable + totalTax + roundOff).toFixed(2);
  document.getElementById('sn-sum-grand').textContent = fmt_money(grand);

  const payStatus = document.getElementById('sn-paystatus').value;
  if (payStatus === 'Paid') document.getElementById('sn-amountreceived').value = grand.toFixed(2);
  const received = parseFloat(document.getElementById('sn-amountreceived').value) || 0;
  const balance = Math.max(0, grand - received);
  document.getElementById('sn-sum-received').textContent = fmt_money(received);
  document.getElementById('sn-sum-balance').textContent = fmt_money(balance);
  document.getElementById('sn-outstanding-amount').textContent = fmt_money(balance);

  document.getElementById('sn-sb-tax').textContent = fmt_money(totalTax);
  document.getElementById('sn-sb-invvalue').textContent = fmt_money(grand);
  document.getElementById('sn-sb-netpayable').textContent = fmt_money(grand);
  syncSNSplitAutoRow();
}

// ── Split Payment (Sale Entry Payment Information) ────────────────
// Mirrors the Purchase Entry split-payment design exactly: row 0 is
// always the AUTO row, recomputed as (Amount Received − every other row),
// so the split always reconciles by construction.
const SN_SPLIT_COLORS = { 'Cash': '#2E7D32', 'Bank Transfer': '#1565C0', 'UPI': '#6A4C93', 'Cheque': '#E65100' };
function snSplitColor(method) { return SN_SPLIT_COLORS[method] || '#455A64'; }

function toggleSNSplitPayment() {
  const isSplit = document.getElementById('sn-paymethod').value === 'Split Payment';
  const panel = document.getElementById('sn-split-panel');
  panel.style.display = isSplit ? 'block' : 'none';
  if (isSplit && document.getElementById('sn-split-rows').children.length === 0) {
    addSNSplitRow(true);
    addSNSplitRow(false);
    syncSNSplitAutoRow();
  }
}

function syncSNSplitAutoRow() {
  updateSNSplitMismatch();
  const rows = document.querySelectorAll('#sn-split-rows .pne-split-row');
  if (document.getElementById('sn-paymethod')?.value !== 'Split Payment' || rows.length < 2) return;
  const target = parseFloat(document.getElementById('sn-amountreceived').value) || 0;
  let othersSum = 0;
  for (let i = 1; i < rows.length; i++) othersSum += parseFloat(rows[i].querySelector('.pne-split-amt')?.value) || 0;
  const remainder = target - othersSum;
  const autoInput = rows[0].querySelector('.pne-split-amt');
  autoInput.value = target > 0 || othersSum > 0 ? Math.max(0, remainder).toFixed(2) : '';
  rows[0].classList.toggle('pne-split-over', remainder < -0.005);
  renderSNSplitFooter();
}

function addSNSplitRow(isAuto) {
  const container = document.getElementById('sn-split-rows');
  const row = document.createElement('div');
  row.className = 'pne-split-row' + (isAuto ? ' pne-split-row-auto' : '');
  row.innerHTML = `<span class="pne-split-dot" style="background:${snSplitColor('Cash')}"></span>
    <select class="pne-split-method" onchange="this.previousElementSibling.style.background=snSplitColor(this.value); renderSNSplitFooter()">
      <option>Cash</option><option>Bank Transfer</option><option>UPI</option><option>Cheque</option>
    </select>
    <input type="number" class="pne-split-amt" placeholder="0.00" ${isAuto ? 'readonly title="Auto-calculated: Amount Received minus the other methods"' : ''}
      oninput="syncSNSplitAutoRow()">
    ${isAuto ? '<span class="pne-split-auto-tag"><i class="fas fa-bolt"></i> Auto</span>' : '<button type="button" onclick="removeSNSplitRow(this)"><i class="fas fa-times"></i></button>'}`;
  container.appendChild(row);
  renderSNSplitFooter();
}

function removeSNSplitRow(btn) {
  const rows = document.querySelectorAll('#sn-split-rows .pne-split-row');
  if (rows.length <= 2) { toast('⚠️ Keep at least 2 split methods', 'warning'); return; }
  btn.closest('.pne-split-row').remove();
  syncSNSplitAutoRow();
}

function renderSNSplitFooter() {
  const target = parseFloat(document.getElementById('sn-amountreceived').value) || 0;
  const rows = Array.from(document.querySelectorAll('#sn-split-rows .pne-split-row'));
  const breakdown = rows.map(r => {
    const method = r.querySelector('.pne-split-method')?.value || '';
    const amt = parseFloat(r.querySelector('.pne-split-amt')?.value) || 0;
    const color = snSplitColor(method);
    return `<span class="pne-split-chip" style="background:${color}18;color:${color}">${escHtml(method)}: <strong>${fmt_money(amt)}</strong></span>`;
  }).join('');
  const footer = document.getElementById('sn-split-footer');
  if (footer) {
    footer.innerHTML = `
      <div class="pne-split-footer-total">Amount Received <strong>${fmt_money(target)}</strong></div>
      <div class="pne-split-footer-breakdown">${breakdown}</div>`;
  }
}

function updateSNSplitMismatch() {
  const warnEl = document.getElementById('sn-split-mismatch');
  if (!warnEl || document.getElementById('sn-paymethod')?.value !== 'Split Payment') { if(warnEl) warnEl.style.display='none'; return; }
  const amts = Array.from(document.querySelectorAll('#sn-split-rows .pne-split-amt')).map(el => parseFloat(el.value)||0);
  const splitSum = amts.reduce((s,v) => s+v, 0);
  const received = parseFloat(document.getElementById('sn-amountreceived').value) || 0;
  if (received > 0 && Math.abs(splitSum - received) > 0.01) {
    warnEl.style.display = 'block';
    warnEl.textContent = splitSum > received
      ? `⚠️ Split total (${fmt_money(splitSum)}) exceeds Amount Received`
      : `⚠️ Split total (${fmt_money(splitSum)}) is less than Amount Received`;
  } else {
    warnEl.style.display = 'none';
  }
}

function getSNSplitLabel() {
  const rows = document.querySelectorAll('#sn-split-rows .pne-split-row');
  const parts = Array.from(rows).map(r => {
    const m = r.querySelector('.pne-split-method')?.value || '';
    const a = parseFloat(r.querySelector('.pne-split-amt')?.value || 0);
    return a > 0 ? `${m}: ₹${a.toFixed(0)}` : null;
  }).filter(Boolean);
  return 'Split: ' + parts.join(' + ');
}

function restoreSNSplitFromLabel(label) {
  const body = label.replace(/^Split:\s*/, '');
  const parts = body.split('+').map(s => s.trim()).filter(Boolean).map(s => {
    const m = s.match(/^(.+?):\s*₹\s*([\d,]+(?:\.\d+)?)$/);
    return m ? { method: m[1].trim(), amount: parseFloat(m[2].replace(/,/g, '')) } : null;
  }).filter(Boolean);

  if (parts.length === 0) { addSNSplitRow(true); addSNSplitRow(false); syncSNSplitAutoRow(); return; }

  addSNSplitRow(true);
  setSNSplitRowMethod(0, parts[0].method);
  for (let i = 1; i < parts.length; i++) {
    addSNSplitRow(false);
    setSNSplitRowMethod(i, parts[i].method);
    const rows = document.querySelectorAll('#sn-split-rows .pne-split-row');
    rows[i].querySelector('.pne-split-amt').value = parts[i].amount.toFixed(2);
  }
  if (parts.length === 1) addSNSplitRow(false);
  syncSNSplitAutoRow();
}
function setSNSplitRowMethod(rowIndex, method) {
  const rows = document.querySelectorAll('#sn-split-rows .pne-split-row');
  const sel = rows[rowIndex]?.querySelector('.pne-split-method');
  if (sel && Array.from(sel.options).some(o => o.value === method)) {
    sel.value = method;
    const dot = rows[rowIndex]?.querySelector('.pne-split-dot');
    if (dot) dot.style.background = snSplitColor(method);
  }
}

function snFileToDataUrl(file) {
  return new Promise(resolve => {
    if (file.size > 5*1024*1024) { toast(`⚠️ "${file.name}" is over 5MB — skipped`, 'warning'); return resolve(null); }
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => resolve(null);
    reader.readAsDataURL(file);
  });
}
async function snAddAttachments(files) {
  for (const f of Array.from(files)) { const url = await snFileToDataUrl(f); if (url) SN.attachments.push({ name: f.name, url }); }
  document.getElementById('sn-attachments-input').value = '';
  renderSNAttachments();
}
function snRemoveAttachment(idx) { SN.attachments.splice(idx, 1); renderSNAttachments(); }
function renderSNAttachments() {
  document.getElementById('sn-attachments-list').innerHTML = SN.attachments.map((a, i) => `
    <div class="pp-attach-row"><span><i class="fas fa-file"></i> ${escHtml(a.name)}</span><button onclick="snRemoveAttachment(${i})"><i class="fas fa-times"></i></button></div>`).join('');
}

async function saveSaleEntry(mode) {
  const customerId = document.getElementById('sn-customer').value;
  if (!customerId) { toast('⚠️ Select a customer', 'warning'); return; }
  if (!document.getElementById('sn-invdate').value) { toast('⚠️ Invoice date is required', 'warning'); return; }
  if (SN.items.some(it => !it.product_id)) { toast('⚠️ Every item needs a product selected', 'warning'); return; }

  const payload = {
    invoice_no: document.getElementById('sn-invno').value.trim(),
    customer_id: parseInt(customerId),
    sale_date: document.getElementById('sn-invdate').value,
    due_date: document.getElementById('sn-duedate').value || null,
    sales_executive: document.getElementById('sn-salesexec').value.trim(),
    payment_terms: document.getElementById('sn-paymentterms').value,
    sales_type: document.getElementById('sn-salestype').value,
    weighing_type: document.getElementById('sn-weighingtype').value,
    kanta_name: document.getElementById('sn-kantaname').value.trim(),
    weighbridge_slip_no: document.getElementById('sn-slipno').value.trim(),
    weight_datetime: document.getElementById('sn-weightdatetime').value || null,
    kanta_operator_name: document.getElementById('sn-kantaoperator').value.trim(),
    kanta_gross_weight: parseFloat(document.getElementById('sn-kanta-gross').value) || 0,
    kanta_tare_weight: parseFloat(document.getElementById('sn-kanta-tare').value) || 0,
    kanta_moisture_pct: document.getElementById('sn-kanta-moisture').value || null,
    kanta_dhalta_kg: parseFloat(document.getElementById('sn-kanta-dhaltakg').value) || 0,
    place_of_supply: document.getElementById('sn-placeofsupply').value,
    currency: document.getElementById('sn-currency').value,
    is_interstate: document.getElementById('sn-salestype').value !== 'Local Sales',
    transport_charge: parseFloat(document.getElementById('sn-transportcharge').value) || 0,
    loading_charge: parseFloat(document.getElementById('sn-loadingcharge').value) || 0,
    packing_charge: parseFloat(document.getElementById('sn-packingcharge').value) || 0,
    insurance_charge: parseFloat(document.getElementById('sn-insurance').value) || 0,
    other_charges: parseFloat(document.getElementById('sn-othercharge').value) || 0,
    round_off: parseFloat(document.getElementById('sn-roundoff').value) || 0,
    discount_amount: parseFloat(document.getElementById('sn-discount').value) || 0,
    payment_status: document.getElementById('sn-paystatus').value,
    payment_method: document.getElementById('sn-paymethod').value === 'Split Payment' ? getSNSplitLabel() : document.getElementById('sn-paymethod').value,
    amount_received: parseFloat(document.getElementById('sn-amountreceived').value) || 0,
    transaction_no: document.getElementById('sn-transactionno').value.trim(),
    payment_date: document.getElementById('sn-paydate').value || null,
    customer_notes: document.getElementById('sn-customernotes').value.trim(),
    internal_notes: document.getElementById('sn-internalnotes').value.trim(),
    delivery_instructions: document.getElementById('sn-deliveryinstructions').value.trim(),
    prepared_by: document.getElementById('sn-preparedby').value.trim(),
    checked_by: document.getElementById('sn-checkedby').value.trim(),
    approved_by: document.getElementById('sn-approvedby').value.trim(),
    status: mode === 'draft' ? 'Draft' : 'Confirmed',
    attachments: SN.attachments.map(a => a.url),
    items: SN.items.map(it => ({
      product_id: it.product_id || null, description: it.description, variety_grade: it.variety_grade,
      batch_no: it.batch_no, warehouse: it.warehouse, qty: parseFloat(it.qty)||0, unit: it.unit,
      rate: parseFloat(it.rate)||0, discount_pct: parseFloat(it.discount_pct)||0, gst_pct: parseFloat(it.gst_pct)||0,
    })),
  };

  const btn = event?.target?.closest('button');
  if (btn) btn.disabled = true;
  try {
    let savedId = SN.editingId;
    if (SN.editingId) {
      await api('api/sales.php?id=' + SN.editingId, 'PUT', payload);
      toast('✅ Sale updated!', 'success');
    } else {
      const res = await api('api/sales.php', 'POST', payload);
      savedId = res.id;
      toast('✅ Sale saved!', 'success');
    }
    const [r, stk] = await Promise.all([api('api/sales.php'), api('api/stock.php')]);
    STATE.sales = Array.isArray(r.data) ? r.data : STATE.sales;
    STATE.stock = Array.isArray(stk.data) ? stk.data : STATE.stock;

    if (mode === 'print') { printSaleEntry(savedId); cancelSaleEntry(); }
    else { cancelSaleEntry(); }
  } catch(e) { toast('❌ ' + e.message, 'error'); }
  finally { if (btn) btn.disabled = false; }
}

async function editSale(id) {
  try {
    const r = await api('api/sales.php?id=' + id);
    const s = r.data;
    SN.editingId = id;
    SN.attachments = (s.attachments||[]).map(url => ({ name: url.split('/').pop(), url }));
    SN.items = (s.items||[]).map(it => ({
      id: snItemSeq++, product_id: it.product_id || '', description: it.description, variety_grade: it.variety_grade || '',
      batch_no: it.batch_no || '', warehouse: it.warehouse || 'Main Warehouse', qty: it.qty || 0, unit: it.unit || 'Kg',
      rate: it.rate || 0, discount_pct: it.discount_pct || 0, gst_pct: it.gst_pct || 0,
    }));
    document.getElementById('psn-title').textContent = 'Edit Sale Entry';
    document.getElementById('psn-subtitle').textContent = s.invoice_no;
    populateSaleCustomerDropdown();
    document.getElementById('sn-customer').value = s.customer_id;
    await onCustomerPicked();
    document.getElementById('sn-shipping').value = s.shipping_address || document.getElementById('sn-shipping').value;
    document.getElementById('sn-salesexec').value = s.sales_executive || '';
    document.getElementById('sn-invno').value = s.invoice_no;
    document.getElementById('sn-invdate').value = s.sale_date;
    document.getElementById('sn-duedate').value = s.due_date || '';
    document.getElementById('sn-paymentterms').value = s.payment_terms || 'Immediate';
    document.getElementById('sn-salestype').value = s.sales_type || 'Local Sales';
    document.getElementById('sn-weighingtype').value = s.weighing_type || 'Dharam Kanta';
    document.getElementById('sn-kantaname').value = s.kanta_name || '';
    document.getElementById('sn-slipno').value = s.weighbridge_slip_no || '';
    document.getElementById('sn-weightdatetime').value = s.weight_datetime ? s.weight_datetime.replace(' ', 'T').slice(0,16) : '';
    document.getElementById('sn-kantaoperator').value = s.kanta_operator_name || '';
    document.getElementById('sn-kanta-gross').value = s.kanta_gross_weight || '';
    document.getElementById('sn-kanta-tare').value = s.kanta_tare_weight || '';
    document.getElementById('sn-kanta-moisture').value = s.kanta_moisture_pct ?? '';
    document.getElementById('sn-kanta-dhaltakg').value = s.kanta_dhalta_kg || '';
    calcSNWeightSummary();
    document.getElementById('sn-placeofsupply').value = s.place_of_supply || '';
    document.getElementById('sn-currency').value = s.currency || 'INR';
    document.getElementById('sn-transportcharge').value = s.transport_charge || 0;
    document.getElementById('sn-loadingcharge').value = s.loading_charge || 0;
    document.getElementById('sn-packingcharge').value = s.packing_charge || 0;
    document.getElementById('sn-insurance').value = s.insurance_charge || 0;
    document.getElementById('sn-othercharge').value = s.other_charges || 0;
    document.getElementById('sn-roundoff').value = s.round_off || 0;
    document.getElementById('sn-discount').value = s.discount_amount || 0;
    document.getElementById('sn-paystatus').value = s.payment_status || 'Pending';
    const isSNSplitSaved = (s.payment_method || '').startsWith('Split:');
    document.getElementById('sn-paymethod').value = isSNSplitSaved ? 'Split Payment' : (s.payment_method || 'Cash');
    document.getElementById('sn-split-panel').style.display = isSNSplitSaved ? 'block' : 'none';
    document.getElementById('sn-split-rows').innerHTML = '';
    if (isSNSplitSaved) restoreSNSplitFromLabel(s.payment_method);
    document.getElementById('sn-amountreceived').value = s.amount_received || 0;
    document.getElementById('sn-transactionno').value = s.transaction_no || '';
    document.getElementById('sn-paydate').value = s.payment_date || '';
    document.getElementById('sn-customernotes').value = s.customer_notes || '';
    document.getElementById('sn-internalnotes').value = s.internal_notes || '';
    document.getElementById('sn-deliveryinstructions').value = s.delivery_instructions || '';
    document.getElementById('sn-preparedby').value = s.prepared_by || '';
    document.getElementById('sn-checkedby').value = s.checked_by || '';
    document.getElementById('sn-approvedby').value = s.approved_by || '';
    renderSNItemsTable(); renderSNAttachments();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function printCurrentSaleInvoice() {
  if (SN.editingId) { printSaleEntry(SN.editingId); return; }
  toast('⚠️ Save this sale first, then Print Invoice will open the actual invoice', 'warning');
}

async function printSaleEntry(id) {
  try {
    const r = await api('api/sales.php?id=' + id);
    printSaleInvoice(r.data);
  } catch(e) { toast('❌ Could not open print view: ' + e.message, 'error'); }
}

function printSaleInvoice(s) {
  const co = pneCompanyInfo();
  const items = s.items || [];
  const rows = items.map(it => `
    <tr>
      <td><strong>${escHtml(it.product_name||it.description||'')}</strong>${it.variety_grade?`<br><span class="muted">${escHtml(it.variety_grade)}</span>`:''}${it.batch_no?`<br><span class="muted">Batch: ${escHtml(it.batch_no)}</span>`:''}</td>
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

  const win = window.open('', '_blank');
  win.document.write(`<html><head><title>${escHtml(s.invoice_no)}</title><style>
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; color: #1a2b3c; padding: 26px 34px; font-size: 12.5px; }
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
    .grand { background: #0d3b2e; color: #fff; border-radius: 8px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
    .grand span { font-size: 11px; text-transform: uppercase; } .grand b { font-size: 20px; }
    .words { font-style: italic; color: #556; font-size: 11px; margin-top: 10px; }
    .sig-row { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 10px; }
    .sig { width: 30%; border-top: 1px solid #99a; text-align: center; font-size: 10px; color: #667; padding-top: 6px; text-transform: uppercase; letter-spacing: .5px; }
    .footer { margin-top: 30px; border-top: 1px solid #eef0f3; padding-top: 10px; display: flex; justify-content: space-between; font-size: 9.5px; color: #99a; }
  </style></head><body>
    <div class="head">
      <div>
        <div class="co-name">${escHtml(co.name)}</div>
        <div class="co-sub">AGRICULTURE ERP SOLUTIONS</div>
        <div class="co-meta">
          ${co.address?escHtml(co.address)+'<br>':''}
          ${co.gst?`<strong>GSTIN: ${escHtml(co.gst)}</strong><br>`:''}
          ${co.fssai?`FSSAI: ${escHtml(co.fssai)} &nbsp; `:''}${co.iec?`IEC: ${escHtml(co.iec)}`:''}
        </div>
      </div>
      <div>
        <div class="badge-inv">TAX INVOICE<small>SALE ENTRY</small></div>
        <div class="inv-meta">Invoice No: ${escHtml(s.invoice_no)}<br>Date: ${fmt_date_disp(s.sale_date)}<br>${s.sales_type?escHtml(s.sales_type):''}</div>
      </div>
    </div>

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
        <div class="kv">Balance Due<b style="color:${(s.total-s.amount_received)>0?'#c0392b':'#0d7a3f'}">${fmt_money((s.total||0)-(s.amount_received||0))}</b></div>
      </div>
    </div>

    <table class="items">
      <thead><tr><th>Product</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Disc %</th><th class="r">Amount</th><th class="r">GST %</th><th class="r">Tax</th><th class="r">Total</th></tr></thead>
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
        <div class="sum-row"><span>Total Tax</span><span>${fmt_money(s.total_tax)}</span></div>
        <div class="sum-row"><span>Round-off</span><span>${fmt_money(s.round_off)}</span></div>
        <div class="grand"><span>GRAND TOTAL</span><b>${fmt_money(s.total)}</b></div>
      </div>
    </div>
    <div class="words">Amount in Words: <strong>${numToWordsINR(s.total)}</strong></div>

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
