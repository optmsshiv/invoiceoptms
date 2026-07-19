// ============================================================
// sale-new.js — page-specific JS for pages/sale-new.php
// Depends on: common.js, app.js, sales-shared.js, edit-approval-shared.js
//
// DRAFT SAVE/RESTORE: "Add New Customer" from this page used to open
// customer-new as an SPA overlay with this form still alive underneath.
// In the MPA that's a real navigation, so before leaving we snapshot the
// whole form (SN state + every #sn-* field + the split-payment rows,
// which aren't backed by a JS array) into sessionStorage, and restore it
// when we land back here with ?restore=1.
//
// EDIT MODE: sales.php's "Edit" button now redirects here with
// ?edit_id=X instead of populating this page's DOM from a different page.
// loadSaleForEdit() below does the actual field population.
// ============================================================

const SN = { editingId: null, items: [], attachments: [], deductions: [] };
let snItemSeq = 1;
let snDeductionSeq = 1;
const SN_SPLIT_COLORS = { 'Cash': '#2E7D32', 'Bank Transfer': '#1565C0', 'UPI': '#6A4C93', 'Cheque': '#E65100' };
const SN_DRAFT_KEY = 'sn_draft_v1';

function snSaveDraft() {
  const fields = {};
  document.querySelectorAll('input[id^="sn-"], select[id^="sn-"], textarea[id^="sn-"]').forEach(el => {
    if (el.type !== 'file') fields[el.id] = el.value;
  });
  const splitRows = Array.from(document.querySelectorAll('#sn-split-rows .pne-split-row')).map(row => ({
    method: row.querySelector('.pne-split-method')?.value,
    amount: row.querySelector('.pne-split-amt')?.value,
  }));
  const splitPanelVisible = document.getElementById('sn-split-panel')?.style.display !== 'none';
  try {
    sessionStorage.setItem(SN_DRAFT_KEY, JSON.stringify({ SN, fields, splitRows, splitPanelVisible }));
  } catch (e) { /* storage full/unavailable — non-fatal, worst case draft isn't restored */ }
}

function snClearDraft() {
  try { sessionStorage.removeItem(SN_DRAFT_KEY); } catch (e) {}
}

function snRestoreDraft() {
  let draft;
  try { draft = JSON.parse(sessionStorage.getItem(SN_DRAFT_KEY) || 'null'); } catch (e) { draft = null; }
  if (!draft) return false;

  Object.assign(SN, draft.SN);
  renderSNItemsTable();
  renderSNDeductionsTable();
  renderSNAttachments();

  Object.keys(draft.fields || {}).forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = draft.fields[id];
  });

  const container = document.getElementById('sn-split-rows');
  if (container && draft.splitRows) {
    container.innerHTML = '';
    draft.splitRows.forEach(r => {
      addSNSplitRow();
      const row = container.lastElementChild;
      if (row.querySelector('.pne-split-method')) row.querySelector('.pne-split-method').value = r.method;
      if (row.querySelector('.pne-split-amt')) row.querySelector('.pne-split-amt').value = r.amount;
    });
    if (draft.splitPanelVisible) document.getElementById('sn-split-panel').style.display = '';
    renderSNSplitFooter();
  }

  if (draft.fields['sn-customer']) onCustomerPicked();
  calcSaleNewTotals();
  calcSNWeightSummary();
  snClearDraft();
  return true;
}

document.addEventListener('DOMContentLoaded', async () => {
  await bootSalesPageState();
  populateSaleCustomerDropdown();
  populateSalesExecDropdown();
  const params = new URLSearchParams(window.location.search);

  const editId = params.get('edit_id');
  if (editId) {
    await loadSaleForEdit(editId);
    return;
  }

  const restored = params.get('restore') === '1' && snRestoreDraft();
  if (!restored) {
    await initNewSaleDefaults();
  }
  const newCustomerId = params.get('new_customer_id');
  if (newCustomerId) {
    document.getElementById('sn-customer').value = newCustomerId;
    onCustomerPicked();
  }
});

function addSNDeduction() {
  SN.deductions.push({ id: snDeductionSeq++, type: '', description: '', amount: 0 });
  renderSNDeductionsTable();
}

function addSNSplitRow() {
  const container = document.getElementById('sn-split-rows');
  const row = document.createElement('div');
  row.className = 'pne-split-row';
  row.innerHTML = `<select class="pne-split-method" onchange="renderSNSplitFooter()">
      <option>UPI (GPay/PhonePe/Paytm)</option><option>Cash</option><option>Bank Transfer (NEFT/RTGS)</option><option>Cheque</option>
    </select>
    <input type="number" class="pne-split-amt" placeholder="0.00" step="0.01" oninput="syncSNSplitAutoRow(this)">
    <button type="button" onclick="removeSNSplitRow(this)"><i class="fas fa-times"></i></button>`;
  container.appendChild(row);
  renderSNSplitFooter();
}

function addSaleNewItem() { SN.items.push(snEmptyItem()); renderSNItemsTable(); }

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
  document.getElementById('sn-sb-addcharges').textContent = fmt_money(addCharges);

  const totalDeductions = SN.deductions.reduce((s,d) => s + (parseFloat(d.amount)||0), 0);
  document.getElementById('sn-deductions-total').textContent = fmt_money(totalDeductions);
  document.getElementById('sn-sum-deductions').textContent = fmt_money(totalDeductions);
  document.getElementById('sn-sb-deductions').textContent = fmt_money(totalDeductions);

  const tradeDiscPct = parseFloat(document.getElementById('sn-tradediscpct').value) || 0;
  const cashDiscPct = parseFloat(document.getElementById('sn-cashdiscpct').value) || 0;
  const tradeDiscAmt = +(subtotal * tradeDiscPct / 100).toFixed(2);
  const cashDiscAmt = +(subtotal * cashDiscPct / 100).toFixed(2);
  document.getElementById('sn-cashdisc-amt').textContent = fmt_money(cashDiscAmt);
  document.getElementById('sn-cashdisc-note').textContent = `(${cashDiscPct}% of Total Gross Amount)`;
  document.getElementById('sn-sum-tradedisc').textContent = fmt_money(tradeDiscAmt);
  document.getElementById('sn-sum-cashdisc').textContent = fmt_money(cashDiscAmt);

  const discount = parseFloat(document.getElementById('sn-discount').value) || 0;
  const taxable = Math.max(0, subtotal + addCharges - discount - totalDeductions - tradeDiscAmt - cashDiscAmt);
  document.getElementById('sn-sum-taxable').textContent = fmt_money(taxable);
  document.getElementById('sn-sb-taxable').textContent = fmt_money(taxable);

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
  document.getElementById('sn-sb-paidamount').textContent = fmt_money(received);
  document.getElementById('sn-sb-netpayable').textContent = fmt_money(balance);
  updateSNPartialCard(grand, payStatus);
  syncSNSplitAutoRow();
}

function cancelSaleEntry() {
  window.location.href = '/pages/sales.php';
}

function goToNewCustomerFromSale() {
  snSaveDraft();
  window.location.href = '/pages/customer-new.php?return_to=sale-new';
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
    if (c.sales_executive) populateSalesExecDropdown(c.sales_executive);
    if (c.payment_terms) document.getElementById('sn-paymentterms').value = c.payment_terms;
  }
  calcSaleNewTotals();
  try {
    const r = await api('/api/customers.php?summary_for=' + id);
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

function onSalesTypeChange() {
  calcSaleNewTotals();
}

function printCurrentSaleInvoice() {
  if (SN.editingId) { printSaleEntry(SN.editingId); return; }
  toast('⚠️ Save this sale first, then Print Invoice will open the actual invoice', 'warning');
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
    discount_remarks: document.getElementById('sn-discount-remarks').value.trim(),
    deductions: SN.deductions.filter(d => (parseFloat(d.amount)||0) > 0).map(d => ({ type: d.type, description: d.description, amount: parseFloat(d.amount)||0 })),
    trade_discount_pct: parseFloat(document.getElementById('sn-tradediscpct').value) || 0,
    cash_discount_pct: parseFloat(document.getElementById('sn-cashdiscpct').value) || 0,
    cd_applicable_within: document.getElementById('sn-cdwithin').value,
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
      batch_no: it.batch_no, moisture_pct: (it.moisture_pct === '' || it.moisture_pct === null || it.moisture_pct === undefined) ? null : parseFloat(it.moisture_pct),
      warehouse: it.warehouse, qty: parseFloat(it.qty)||0, unit: it.unit,
      rate: parseFloat(it.rate)||0, discount_pct: parseFloat(it.discount_pct)||0, gst_pct: parseFloat(it.gst_pct)||0,
    })),
  };

  const btn = event?.target?.closest('button');
  if (btn) btn.disabled = true;
  try {
    let savedId = SN.editingId;
    if (SN.editingId) {
      await api('/api/sales.php?id=' + SN.editingId, 'PUT', payload);
      consumeEditApproval(); toast('✅ Sale updated!', 'success');
    } else {
      const res = await api('/api/sales.php', 'POST', payload);
      savedId = res.id;
      consumeEditApproval(); toast('✅ Sale saved!', 'success');
    }
    const [r, stk] = await Promise.all([api('/api/sales.php'), api('/api/stock.php')]);
    STATE.sales = Array.isArray(r.data) ? r.data : STATE.sales;
    STATE.stock = Array.isArray(stk.data) ? stk.data : STATE.stock;

    if (mode === 'print') { printSaleEntry(savedId); }
    snClearDraft();
    setTimeout(() => window.location.href = '/pages/sales.php', mode === 'print' ? 1200 : 700);
  } catch(e) { toast('❌ ' + e.message, 'error'); }
  finally { if (btn) btn.disabled = false; }
}

async function snAddAttachments(files) {
  for (const f of Array.from(files)) { const url = await snFileToDataUrl(f); if (url) SN.attachments.push({ name: f.name, url }); }
  document.getElementById('sn-attachments-input').value = '';
  renderSNAttachments();
}

function syncSNInvoiceDateToPayment() {
  const payDate = document.getElementById('sn-paydate').value;
  if (payDate) document.getElementById('sn-invdate').value = payDate;
}

function toggleSNSplitPayment() {
  const isSplit = document.getElementById('sn-paymethod').value === 'Split Payment';
  const panel = document.getElementById('sn-split-panel');
  panel.style.display = isSplit ? 'block' : 'none';
  if (isSplit && document.getElementById('sn-split-rows').children.length === 0) {
    addSNSplitRow(); addSNSplitRow();
    syncSNSplitAutoRow();
  }
}

function clearCustomerAutofill() {
  document.getElementById('sn-mobile').value = '';
  document.getElementById('sn-gstin').value = '';
  document.getElementById('sn-state').value = '';
  document.getElementById('sn-district').value = '';
  document.getElementById('sn-billing').value = '';
}

function getSNSplitLabel() {
  const rows = document.querySelectorAll('#sn-split-rows .pne-split-row');
  const parts = Array.from(rows).map(r => {
    const m = (r.querySelector('.pne-split-method')?.value || '').split(' (')[0];
    const a = parseFloat(r.querySelector('.pne-split-amt')?.value || 0);
    return a > 0 ? `${m}: ₹${a.toFixed(2)}` : null;
  }).filter(Boolean);
  return 'Split: ' + parts.join(' + ');
}

function removeSNSplitRow(btn) {
  const rows = document.querySelectorAll('#sn-split-rows .pne-split-row');
  if (rows.length <= 1) { toast('⚠️ Keep at least 1 split method', 'warning'); return; }
  btn.closest('.pne-split-row').remove();
  syncSNSplitAutoRow();
}

function renderSNAttachments() {
  document.getElementById('sn-attachments-list').innerHTML = SN.attachments.map((a, i) => `
    <div class="pp-attach-row"><span><i class="fas fa-file"></i> ${escHtml(a.name)}</span><span class="pp-attach-actions">${a.url?`<button class="pp-attach-view" onclick="window.open('${a.url}','_blank')" title="View"><i class="fas fa-eye"></i></button>`:''}<button onclick="snRemoveAttachment(${i})" title="Remove"><i class="fas fa-times"></i></button></span></div>`).join('');
}

function renderSNDeductionsTable() {
  const tbody = document.getElementById('sn-deductions-tbody');
  if (!tbody) return;
  if (!SN.deductions.length) {
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:14px">No deductions added</td></tr>`;
  } else {
    tbody.innerHTML = SN.deductions.map((d, i) => `
      <tr>
        <td>${i+1}</td>
        <td><input value="${escHtml(d.type)}" placeholder="e.g. Tractor Charge" oninput="updateSNDeduction(${d.id},'type',this.value)"></td>
        <td><input value="${escHtml(d.description)}" placeholder="Optional" oninput="updateSNDeduction(${d.id},'description',this.value)"></td>
        <td><input type="number" value="${d.amount}" min="0" step="0.01" oninput="updateSNDeduction(${d.id},'amount',this.value)"></td>
        <td><button class="item-del" onclick="removeSNDeduction(${d.id})" title="Remove"><i class="fas fa-times"></i></button></td>
      </tr>`).join('');
  }
  calcSaleNewTotals();
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
      <td>${escHtml(prod?.variety || it.variety_grade || '—')}</td>
      <td>${escHtml(prod?.grade || '—')}</td>
      <td><input value="${escHtml(it.batch_no)}" placeholder="Optional" oninput="updateSNItem(${it.id},'batch_no',this.value,true)"></td>
      <td><input type="number" value="${it.moisture_pct ?? ''}" min="0" max="100" step="0.01" placeholder="—" oninput="updateSNItem(${it.id},'moisture_pct',this.value)"></td>
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

function renderSNSplitFooter() {
  const rows = Array.from(document.querySelectorAll('#sn-split-rows .pne-split-row'));
  let splitTotal = 0;
  const parts = rows.map(r => {
    const method = r.querySelector('.pne-split-method')?.value || '';
    const amt = parseFloat(r.querySelector('.pne-split-amt')?.value) || 0;
    splitTotal += amt;
    const shortMethod = method.split(' (')[0];
    const color = snSplitColor(shortMethod);
    return `<span style="color:${color};font-weight:700">${escHtml(shortMethod)}: ${fmt_money(amt)}</span>`;
  });
  const totalEl = document.getElementById('sn-split-total-amt');
  if (totalEl) totalEl.textContent = fmt_money(splitTotal);
  const footer = document.getElementById('sn-split-footer');
  if (footer) footer.innerHTML = parts.length
    ? `<strong>Total: ${fmt_money(splitTotal)}</strong>` + parts.map(p => ' &nbsp;|&nbsp; ' + p).join('')
    : '';
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

function snEmptyItem() {
  return { id: snItemSeq++, product_id: '', description: '', variety_grade: '', batch_no: '', moisture_pct: null,
    warehouse: 'Main Warehouse', qty: 0, unit: 'Kg', rate: 0, discount_pct: 0, gst_pct: 18 };
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

function syncSNSplitAutoRow(changedEl) {
  const rows = Array.from(document.querySelectorAll('#sn-split-rows .pne-split-row'));
  if (rows.length > 1) {
    const firstAmt = rows[0].querySelector('.pne-split-amt');
    const editedFirstRow = changedEl && rows[0].contains(changedEl);
    if (!editedFirstRow && firstAmt) {
      const target = parseFloat(document.getElementById('sn-amountreceived').value) || 0;
      let othersSum = 0;
      for (let i = 1; i < rows.length; i++) othersSum += parseFloat(rows[i].querySelector('.pne-split-amt')?.value) || 0;
      firstAmt.value = Math.max(0, target - othersSum).toFixed(2);
    }
  }
  renderSNSplitFooter();
  updateSNSplitMismatch();
}

function updateSNPartialCard(grand, payStatus) {
  const card = document.getElementById('sn-partial-card');
  if (!card) return;
  const show = payStatus === 'Partial';
  card.style.display = show ? 'block' : 'none';
  if (!show) return;
  const received = parseFloat(document.getElementById('sn-amountreceived').value) || 0;
  const remaining = Math.max(0, grand - received);
  const pct = grand > 0 ? Math.min(100, (received / grand) * 100) : 0;
  document.getElementById('sn-partial-total').textContent = fmt_money(grand);
  document.getElementById('sn-partial-received').textContent = fmt_money(received);
  document.getElementById('sn-partial-remaining').textContent = fmt_money(remaining);
  document.getElementById('sn-partial-bar').style.width = pct.toFixed(1) + '%';
}

async function printSaleEntry(id) {
  try {
    const r = await api('/api/sales.php?id=' + id);
    printSaleInvoice(r.data);
  } catch(e) { toast('❌ Could not open print view: ' + e.message, 'error'); }
}

function onSNProductChange(id, productId) {
  const it = SN.items.find(i => i.id === id); if (!it) return;
  it.product_id = productId || '';
  if (productId) {
    const p = STATE.products.find(x => String(x.id) === String(productId));
    if (p) {
      it.description = p.name; it.rate = parseFloat(p.sale_rate || p.rate) || it.rate; it.gst_pct = p.gst !== undefined ? p.gst : it.gst_pct;
      it.variety_grade = p.variety || it.variety_grade;
    }
  }
  renderSNItemsTable();
}

function removeSNDeduction(id) {
  SN.deductions = SN.deductions.filter(d => d.id !== id);
  renderSNDeductionsTable();
}

function removeSNItem(id) {
  if (SN.items.length <= 1) { toast('⚠️ At least one item is required', 'warning'); return; }
  SN.items = SN.items.filter(i => i.id !== id);
  renderSNItemsTable();
}

function updateSNDeduction(id, field, val) {
  const d = SN.deductions.find(x => x.id === id); if (!d) return;
  d[field] = field === 'amount' ? (parseFloat(val) || 0) : val;
  calcSaleNewTotals();
  // keep the total footer fresh without a full re-render on every keystroke
  document.getElementById('sn-deductions-total').textContent = fmt_money(SN.deductions.reduce((s,x)=>s+(parseFloat(x.amount)||0),0));
}

function updateSNItem(id, field, val, isText) {
  const it = SN.items.find(i => i.id === id); if (!it) return;
  it[field] = val;
  const c = snCalcRow(it);
  const taxEl = document.getElementById('sn-tax-' + id);     if (taxEl) taxEl.textContent = fmt_money(c.taxAmount);
  const totEl = document.getElementById('sn-total-' + id);   if (totEl) totEl.textContent = fmt_money(c.lineTotal);
  calcSaleNewTotals();
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

function snSplitColor(method) { return SN_SPLIT_COLORS[method] || '#455A64'; }

function snAvailableStock(productId) {
  const s = (STATE.stock||[]).find(x => String(x.product_id) === String(productId).replace(/\D/g,''));
  // STATE.stock is populated by two different endpoints depending on which
  // page was last visited (api/stock.php uses "current_stock", the newer
  // api/product_stock.php uses "available_stock") — check both so this
  // doesn't silently show 0 depending on navigation history.
  return s ? parseFloat(s.current_stock ?? s.available_stock) || 0 : 0;
}

function restoreSNSplitFromLabel(label) {
  const body = label.replace(/^Split:\s*/, '');
  const parts = body.split('+').map(s => s.trim()).filter(Boolean).map(s => {
    const m = s.match(/^(.+?):\s*₹\s*([\d,]+(?:\.\d+)?)$/);
    return m ? { method: m[1].trim(), amount: parseFloat(m[2].replace(/,/g, '')) } : null;
  }).filter(Boolean);

  if (parts.length === 0) { addSNSplitRow(); addSNSplitRow(); syncSNSplitAutoRow(); return; }
  parts.forEach((p, i) => {
    addSNSplitRow();
    setSNSplitRowMethod(i, p.method);
    const rows = document.querySelectorAll('#sn-split-rows .pne-split-row');
    rows[i].querySelector('.pne-split-amt').value = p.amount.toFixed(2);
  });
  const firstSNRowAmt = document.querySelector('#sn-split-rows .pne-split-row .pne-split-amt');
  syncSNSplitAutoRow(firstSNRowAmt);
}

function setSNSplitRowMethod(rowIndex, method) {
  const rows = document.querySelectorAll('#sn-split-rows .pne-split-row');
  const sel = rows[rowIndex]?.querySelector('.pne-split-method');
  if (!sel) return;
  const match = Array.from(sel.options).find(o => o.value.split(' (')[0] === method);
  if (match) sel.value = match.value;
}

function snRemoveAttachment(idx) { SN.attachments.splice(idx, 1); renderSNAttachments(); }

async function loadSaleForEdit(id) {
  try {
    const r = await api('/api/sales.php?id=' + id);
    const s = r.data;
    SN.editingId = id;
    SN.attachments = (s.attachments||[]).map(url => ({ name: url.split('/').pop(), url }));
    SN.items = (s.items||[]).map(it => ({
      id: snItemSeq++, product_id: it.product_id ? 'p' + it.product_id : '', description: it.description, variety_grade: it.variety_grade || '',
      batch_no: it.batch_no || '', moisture_pct: it.moisture_pct ?? null, warehouse: it.warehouse || 'Main Warehouse', qty: it.qty || 0, unit: it.unit || 'Kg',
      rate: it.rate || 0, discount_pct: it.discount_pct || 0, gst_pct: it.gst_pct || 0,
    }));
    SN.deductions = (s.deductions||[]).map(d => ({ id: snDeductionSeq++, type: d.type||'', description: d.description||'', amount: parseFloat(d.amount)||0 }));
    document.getElementById('psn-title').textContent = 'Edit Sale Entry';
    document.getElementById('psn-subtitle').textContent = s.invoice_no;
    populateSaleCustomerDropdown();
    document.getElementById('sn-customer').value = s.customer_id;
    await onCustomerPicked();
    document.getElementById('sn-shipping').value = s.shipping_address || document.getElementById('sn-shipping').value;
    populateSalesExecDropdown(s.sales_executive || '');
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
    document.getElementById('sn-discount-remarks').value = s.discount_remarks || '';
    document.getElementById('sn-tradediscpct').value = s.trade_discount_pct || 0;
    document.getElementById('sn-cashdiscpct').value = s.cash_discount_pct || 0;
    document.getElementById('sn-cdwithin').value = s.cd_applicable_within || 'Same Day';
    document.getElementById('sn-paystatus').value = s.payment_status || 'Pending';
    const isSNSplitSaved = (s.payment_method || '').startsWith('Split:');
    document.getElementById('sn-paymethod').value = isSNSplitSaved ? 'Split Payment' : (s.payment_method || 'Cash');
    document.getElementById('sn-split-panel').style.display = isSNSplitSaved ? 'block' : 'none';
    document.getElementById('sn-split-rows').innerHTML = '';
    if (isSNSplitSaved) restoreSNSplitFromLabel(s.payment_method);
    document.getElementById('sn-amountreceived').value = s.amount_received || 0;
    document.getElementById('sn-transactionno').value = s.transaction_no || '';
    document.getElementById('sn-paydate').value = s.payment_date || '';
    if (s.payment_date) syncSNInvoiceDateToPayment();
    document.getElementById('sn-customernotes').value = s.customer_notes || '';
    document.getElementById('sn-internalnotes').value = s.internal_notes || '';
    document.getElementById('sn-deliveryinstructions').value = s.delivery_instructions || '';
    document.getElementById('sn-preparedby').value = s.prepared_by || '';
    document.getElementById('sn-checkedby').value = s.checked_by || '';
    document.getElementById('sn-approvedby').value = s.approved_by || '';
    renderSNItemsTable(); renderSNAttachments(); renderSNDeductionsTable();
    api('/api/stock.php').then(r => {
      if (Array.isArray(r.data)) { STATE.stock = r.data; renderSNItemsTable(); }
    }).catch(() => {});
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}
async function initNewSaleDefaults() {
  populateSalesExecDropdown(SERVER.user?.name || '');
  document.getElementById('sn-invdate').value = fmt_date(new Date());
  document.getElementById('sn-paymentterms').value = 'Immediate';
  document.getElementById('sn-salestype').value = 'Local Sales';
  document.getElementById('sn-weighingtype').value = 'Dharam Kanta';
  document.getElementById('sn-cdwithin').value = 'Same Day';
  document.getElementById('sn-paystatus').value = 'Pending';
  document.getElementById('sn-paymethod').value = 'Cash';
  document.getElementById('sn-paydate').value = fmt_date(new Date());
  document.getElementById('sn-preparedby').value = SERVER.user?.name || '';
  document.getElementById('sn-customer-summary').innerHTML = '<div class="pne-summary-empty">Select a customer to see their sales history.</div>';
  SN.items = [snEmptyItem()];
  renderSNItemsTable();
}
