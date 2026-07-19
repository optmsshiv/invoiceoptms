// ================================================================
//  assets/js/purchase-new.js
//  Requires: common.js, shared-data.js, wa-shared.js,
//  edit-approval-shared.js, purchase-print-shared.js, suppliers.js
//  (loaded before this file — suppliers.js provides the quick-add
//  supplier modal's openAddSupplierModal()/saveSupplier()).
//  For pages/purchases/purchase-new.php.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['purchases', 'suppliers', 'products', 'settings']);
  const stk = await api('api/stock.php').catch(() => ({ data: [] }));
  STATE.stock = Array.isArray(stk.data) ? stk.data : [];

  const params = new URLSearchParams(window.location.search);
  const editId = params.get('id');
  if (editId) {
    await editPurchase(editId);
  } else {
    goToNewPurchase();
  }
});

const PNE = { editingId: null, items: [], attachmentDataUrl: null, attachmentExisting: null, deductions: [] };
let pnDeductionSeq = 1;

// ── Deductions (Purchase Entry sidebar — compact card list) ──
function addPNDeduction() {
  PNE.deductions.push({ id: pnDeductionSeq++, type: '', description: '', amount: 0 });
  renderPNDeductions();
}
function removePNDeduction(id) {
  PNE.deductions = PNE.deductions.filter(d => d.id !== id);
  renderPNDeductions();
}
function updatePNDeduction(id, field, val) {
  const d = PNE.deductions.find(x => x.id === id); if (!d) return;
  d[field] = field === 'amount' ? (parseFloat(val) || 0) : val;
  calcPurchaseNewTotals();
}
function renderPNDeductions() {
  const box = document.getElementById('pn-deductions-list');
  if (!box) return;
  box.innerHTML = PNE.deductions.length ? PNE.deductions.map(d => `
    <div style="background:var(--bg);border-radius:7px;padding:8px">
      <div style="display:flex;gap:6px;margin-bottom:6px">
        <input placeholder="Type" style="flex:1;font-size:11px;padding:5px 7px" value="${escHtml(d.type)}" oninput="updatePNDeduction(${d.id},'type',this.value)">
        <button class="item-del" style="width:24px;height:24px" onclick="removePNDeduction(${d.id})" title="Remove"><i class="fas fa-times" style="font-size:10px"></i></button>
      </div>
      <input placeholder="Description" style="width:100%;font-size:11px;padding:5px 7px;margin-bottom:6px" value="${escHtml(d.description)}" oninput="updatePNDeduction(${d.id},'description',this.value)">
      <input type="number" placeholder="Amount (₹)" style="width:100%;font-size:11px;padding:5px 7px" min="0" step="0.01" value="${d.amount}" oninput="updatePNDeduction(${d.id},'amount',this.value)">
    </div>`).join('') : `<div style="font-size:11px;color:var(--muted);text-align:center;padding:8px">No deductions added</div>`;
  calcPurchaseNewTotals();
}

let pneItemSeq = 1;

function goToStockIn() {
  window.location.href = '/pages/stock/stock-in-new.php';
}

function goToNewPurchase() {
  PNE.editingId = null;
  PNE.items = [pneEmptyItem()];
  PNE.deductions = [];
  PNE.attachmentDataUrl = null;
  PNE.attachmentExisting = null;
  document.getElementById('pne-title').textContent = 'New Purchase Entry';
  document.getElementById('pne-subtitle').textContent = 'Local Purchase — grains, spices & other produce';
  document.getElementById('pn-no').value = '';
  document.getElementById('pn-date').value = fmt_date(new Date());
  document.getElementById('pn-suppliertype').value = 'Farmer';
  document.getElementById('pn-refpo').value = '';
  document.getElementById('pn-weighingtype').value = 'Dharam Kanta';
  document.getElementById('pn-kantaname').value = '';
  document.getElementById('pn-slipno').value = '';
  document.getElementById('pn-weightdatetime').value = '';
  document.getElementById('pn-kanta-gross').value = '';
  document.getElementById('pn-kanta-tare').value = '';
  document.getElementById('pn-kanta-net').value = '';
  document.getElementById('pn-kanta-operator').value = '';
  document.getElementById('pn-kanta-slip-input').value = '';
  document.getElementById('pn-kanta-slip-label').innerHTML = '<i class="fas fa-cloud-upload-alt"></i><div style="text-align:left">Drag &amp; drop or click to upload<br><span style="font-size:10px">Supported: PDF, JPG, PNG (Max 5MB)</span></div>';
  PNE.kantaSlipDataUrl = null;
  document.getElementById('pn-q-moisture').value = '';
  document.getElementById('pn-q-impurity').value = '';
  document.getElementById('pn-q-dhaltapct').value = '';
  calcPNEKantaSummary();
  populatePNESupplierDropdown();
  document.getElementById('pn-supplier').value = '';
  clearSupplierAutofill();
  document.getElementById('pn-invno').value = '';
  document.getElementById('pn-transportmode').value = 'Road';
  document.getElementById('pn-vehicleno').value = '';
  document.getElementById('pn-drivername').value = '';
  document.getElementById('pn-warehouse').value = 'Main Warehouse';
  document.getElementById('pn-paymentterms').value = 'Immediate';
  document.getElementById('pn-paymenttype').value = 'Cash';
  document.getElementById('pn-remarks').value = '';
  setGstApplicable(false);
  document.getElementById('pn-transportcharge').value = 0;
  document.getElementById('pn-loadingcharge').value = 0;
  document.getElementById('pn-packingcharge').value = 0;
  document.getElementById('pn-othercharge').value = 0;

  document.getElementById('pn-tradediscpct').value = 0;
  document.getElementById('pn-cashdiscpct').value = 0;
  document.getElementById('pn-cdwithin').value = 'Same Day';
  renderPNDeductions();
  document.getElementById('pn-gst-pct').value = 0;
  document.getElementById('pn-paystatus').value = 'Pending';
  document.getElementById('pn-amountpaid').value = 0;
  document.getElementById('pn-paymode').value = 'Cash';
  document.getElementById('pne-split-panel').style.display = 'none';
  document.getElementById('pne-split-rows').innerHTML = '';
  document.getElementById('pn-transactionno').value = '';
  document.getElementById('pn-paydate').value = fmt_date(new Date());
  document.getElementById('pn-notes').value = '';
  document.getElementById('pn-attachment').value = '';
  document.getElementById('pne-supplier-summary').innerHTML = '<div class="pne-summary-empty">Select a supplier to see their purchase history.</div>';
  renderPNEItemsTable();
}

function cancelPurchaseEntry() {
  window.location.href = '/pages/purchases/purchases.php';
}

function clearSupplierAutofill() {
  document.getElementById('pn-mobile').value = '';
  document.getElementById('pn-state').value = '';
  document.getElementById('pn-district').value = '';
  document.getElementById('pn-address').value = '';
  document.getElementById('pn-gstin').value = '';
}

function populatePNESupplierDropdown() {
  const sel = document.getElementById('pn-supplier');
  if (!sel) return;
  const cur = sel.value;
  sel.innerHTML = '<option value="">Select or add supplier…</option>' +
    (STATE.suppliers||[]).map(s => `<option value="${s.id}">${escHtml(s.name)}</option>`).join('');
  if (cur) sel.value = cur;
}

function onSupplierTypeChange() {
  // Convenience default: farmer purchases are conventionally GST-exempt in mandi trade
  if (document.getElementById('pn-suppliertype').value === 'Farmer') setGstApplicable(false);
}

async function onSupplierPicked() {
  const id = document.getElementById('pn-supplier').value;
  if (!id) { clearSupplierAutofill(); document.getElementById('pne-supplier-summary').innerHTML = '<div class="pne-summary-empty">Select a supplier to see their purchase history.</div>'; return; }
  const s = STATE.suppliers.find(x => String(x.id) === String(id));
  if (s) {
    document.getElementById('pn-mobile').value   = s.phone || '';
    document.getElementById('pn-state').value    = s.state || '';
    document.getElementById('pn-district').value = s.district || '';
    document.getElementById('pn-address').value  = s.address || '';
    document.getElementById('pn-gstin').value    = s.gst_number || '';
    if (s.supplier_type) document.getElementById('pn-suppliertype').value = s.supplier_type;
  }
  try {
    const r = await api('api/suppliers.php?summary_for=' + id);
    const sm = r.data || {};
    document.getElementById('pne-supplier-summary').innerHTML = `
      <div class="pne-kv"><span>Supplier Name</span><strong>${escHtml(s?.name||'—')}</strong></div>
      <div class="pne-kv"><span>Supplier Type</span><strong>${escHtml(s?.supplier_type||'—')}</strong></div>
      <div class="pne-kv"><span>Mobile No.</span><strong>${escHtml(s?.phone||'—')}</strong></div>
      <div class="pne-kv"><span>State</span><strong>${escHtml(s?.state||'—')}</strong></div>
      <div class="pne-kv"><span>GSTIN</span><strong>${escHtml(s?.gst_number||'Not Applicable')}</strong></div>
      <div class="pne-kv" style="margin-top:8px;padding-top:8px;border-top:1px solid var(--border)"><span>Previous Purchases</span><strong>${fmt_money(sm.total_purchases||0)}</strong></div>
      <div class="pne-kv"><span>Total Paid</span><strong>${fmt_money(sm.total_paid||0)}</strong></div>
      <div class="pne-kv"><span>Outstanding</span><strong style="color:${(sm.outstanding||0)>0?'#E53935':'inherit'}">${fmt_money(sm.outstanding||0)}</strong></div>`;
  } catch(e) { /* non-fatal — sidebar just stays on basic info */ }
}

function setGstApplicable(applicable) {
  document.getElementById('pn-gst-no').classList.toggle('active', !applicable);
  document.getElementById('pn-gst-yes').classList.toggle('active', applicable);
  document.getElementById('pn-gstin').disabled = !applicable;
  document.getElementById('pn-supplytype').disabled = !applicable;
  document.getElementById('pn-gst-rate-wrap').style.display = applicable ? 'inline' : 'none';
  document.getElementById('pn-gst-note').style.display = applicable ? 'none' : 'block';
  calcPurchaseNewTotals();
}

function pneEmptyItem() {
  const mode = document.getElementById('pne-entry-mode')?.value || 'catalog';
  return { id: pneItemSeq++, mode, product_id: '', description: '', variety_grade: '', moisture_pct: '', quality_grade: '',
    gross_weight: 0, tare_weight: 0, dhalta_kg: 0, rate: 0, discount_pct: 0, editing: true };
}

function addPurchaseNewItem() {
  PNE.items.push(pneEmptyItem());
  // Reset all header weight + dhalta fields for the next product's kanta reading
  ['pn-kanta-gross','pn-kanta-tare','pn-kanta-net','pn-q-dhaltakg','pn-q-billable','pn-q-dhaltapct'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  calcPNEKantaSummary();
  renderPNEItemsTable();
  document.getElementById('pn-kanta-gross')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function removePNEItem(id) {
  if (PNE.items.length <= 1) { toast('⚠️ At least one item is required', 'warning'); return; }
  PNE.items = PNE.items.filter(i => i.id !== id);
  renderPNEItemsTable();
  calcPurchaseNewTotals();
  calcPNEKantaSummary();
}

function editPNEItem(id) {
  const it = PNE.items.find(i => i.id === id); if (!it) return;
  it.editing = true;
  // Restore this item's weight + dhalta back to the header fields
  const gEl  = document.getElementById('pn-kanta-gross');
  const tEl  = document.getElementById('pn-kanta-tare');
  const dEl  = document.getElementById('pn-q-dhaltakg');
  if (gEl) gEl.value = it.gross_weight || '';
  if (tEl) tEl.value = it.tare_weight  || '';
  if (dEl) dEl.value = it.dhalta_kg    || '';
  calcPNEKantaSummary();  // recalculates net
  calcPNEQualitySummary(); // recalculates dhalta% + billable
  renderPNEItemsTable();
}

function donePNEItem(id) {
  const it = PNE.items.find(i => i.id === id); if (!it) return;
  if (it.mode === 'freetext') {
    if (!it.description || !it.description.trim()) { toast('⚠️ Enter a description for this line', 'warning'); return; }
  } else if (!it.product_id) {
    toast('⚠️ Select a product for this line', 'warning'); return;
  }
  it.editing = false;
  // Reset header weight + dhalta fields — ready for next item's kanta reading
  ['pn-kanta-gross','pn-kanta-tare','pn-kanta-net','pn-q-dhaltakg','pn-q-billable','pn-q-dhaltapct'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  renderPNEItemsTable();
  calcPurchaseNewTotals();
}

// Dhalta Kg is the editable figure (matches how it's actually weighed at the
// mandi); Dhalta % is derived from it for display, not the other way round.
function pneCalcRow(it) {
  const gross = parseFloat(it.gross_weight) || 0;
  const tare  = parseFloat(it.tare_weight)  || 0;
  const net   = Math.max(0, gross - tare);
  const dhaltaKg  = Math.max(0, parseFloat(it.dhalta_kg) || 0);
  const dhaltaPct = net > 0 ? +(dhaltaKg / net * 100).toFixed(2) : 0;
  const billable  = Math.max(0, net - dhaltaKg);
  const rate      = parseFloat(it.rate) || 0;
  const discPct   = parseFloat(it.discount_pct) || 0;
  const grossAmt  = billable * rate;
  const discountAmt = +(grossAmt * discPct / 100).toFixed(2);
  const amount    = +(grossAmt - discountAmt).toFixed(2);
  return { net, dhaltaKg, dhaltaPct, billable, amount, discountAmt };
}

function renderPNEItemsTable() {
  const tbody = document.getElementById('pne-items-tbody');
  if (!tbody) return;
  tbody.innerHTML = PNE.items.map((it, idx) => {
    const c = pneCalcRow(it);
    if (!it.editing) {
      // ── View mode: plain values, pencil to edit, trash to remove ──
      const prod = STATE.products.find(p => String(p.id) === String(it.product_id));
      return `<tr data-row="${it.id}">
        <td class="pne-view-cell">${idx+1}</td>
        <td class="pne-view-cell" style="text-align:left"><strong>${escHtml(prod?.name || it.description || '—')}</strong></td>
        <td class="pne-view-cell">${escHtml(it.variety_grade || '—')}</td>
        <td class="pne-view-cell">${it.moisture_pct ? it.moisture_pct + '%' : '—'}</td>
        <td class="pne-view-cell">${escHtml(it.quality_grade || '—')}</td>
        <td class="pne-view-cell" id="pne-vgross-${it.id}">${it.gross_weight ? parseFloat(it.gross_weight).toFixed(2) : '—'}</td>
        <td class="pne-view-cell" id="pne-vtare-${it.id}">${it.tare_weight ? parseFloat(it.tare_weight).toFixed(2) : '—'}</td>
        <td class="pne-view-cell">${c.net.toFixed(2)}</td>
        <td class="pne-view-cell pne-dhpct-col">${c.dhaltaPct.toFixed(1)}%</td>
        <td class="pne-view-cell">${c.dhaltaKg.toFixed(2)}</td>
        <td class="pne-view-cell">${c.billable.toFixed(2)}</td>
        <td class="pne-view-cell">${(parseFloat(it.rate)||0).toFixed(2)}</td>
        <td class="pne-view-cell">${(parseFloat(it.discount_pct)||0).toFixed(2)}</td>
        <td class="pne-view-cell pne-amount-cell">${fmt_money(c.amount)}</td>
        <td class="pne-view-cell" style="padding:6px 8px">
          <div class="pne-row-actions">
            <button class="pne-icon-btn edit" onclick="editPNEItem(${it.id})" title="Edit"><i class="fas fa-pencil-alt" style="font-size:11px"></i></button>
            <button class="pne-icon-btn del" onclick="removePNEItem(${it.id})" title="Remove"><i class="fas fa-trash" style="font-size:11px"></i></button>
          </div>
        </td>
      </tr>`;
    }
    // ── Edit mode: live-computed inputs ──
    return `<tr data-row="${it.id}">
      <td>${idx+1}</td>
      <td>
        ${it.mode === 'freetext'
          ? `<input value="${escHtml(it.description)}" placeholder="e.g. Gunny bags, Labour advance" oninput="updatePNEItem(${it.id},'description',this.value,true)">`
          : `<select onchange="onPNEProductChange(${it.id}, this.value)">
               <option value="">Select product…</option>
               ${STATE.products.map(p => `<option value="${p.id}" ${String(it.product_id)===String(p.id)?'selected':''}>${escHtml(p.name)}</option>`).join('')}
             </select>`}
      </td>
      <td><input value="${escHtml(it.variety_grade)}" placeholder="e.g. Premium Grade" oninput="updatePNEItem(${it.id},'variety_grade',this.value,true)"></td>
      <td><input type="number" value="${it.moisture_pct}" min="0" max="100" step="0.1" oninput="updatePNEItem(${it.id},'moisture_pct',this.value)"></td>
      <td><input value="${escHtml(it.quality_grade)}" placeholder="e.g. A Grade" oninput="updatePNEItem(${it.id},'quality_grade',this.value,true)"></td>
      <td><input id="pne-gross-${it.id}" type="number" value="${it.gross_weight||''}" min="0" step="0.01" oninput="updatePNEItem(${it.id},'gross_weight',this.value)"></td>
      <td><input id="pne-tare-${it.id}" type="number" value="${it.tare_weight||''}" min="0" step="0.01" oninput="updatePNEItem(${it.id},'tare_weight',this.value)"></td>
      <td><span class="pne-computed" id="pne-net-${it.id}">${c.net.toFixed(2)}</span></td>
      <td class="pne-dhpct-col"><span class="pne-computed" id="pne-dhaltapct-${it.id}">${c.dhaltaPct.toFixed(2)}</span></td>
      <td><input id="pne-dkg-${it.id}" type="number" value="${it.dhalta_kg}" min="0" step="0.01" oninput="updatePNEItem(${it.id},'dhalta_kg',this.value)"></td>
      <td><span class="pne-computed" id="pne-billable-${it.id}">${c.billable.toFixed(2)}</span></td>
      <td><input type="number" value="${it.rate}" min="0" step="0.01" oninput="updatePNEItem(${it.id},'rate',this.value)"></td>
      <td><input type="number" value="${it.discount_pct}" min="0" max="100" step="0.01" oninput="updatePNEItem(${it.id},'discount_pct',this.value)"></td>
      <td class="pne-amount-cell" id="pne-amt-${it.id}">${fmt_money(c.amount)}</td>
      <td style="padding:6px 8px">
        <div class="pne-row-actions">
          <button class="pne-icon-btn done" onclick="donePNEItem(${it.id})" title="Done"><i class="fas fa-check" style="font-size:11px"></i></button>
          <button class="pne-icon-btn del" onclick="removePNEItem(${it.id})" title="Remove"><i class="fas fa-trash" style="font-size:11px"></i></button>
        </div>
      </td>
    </tr>`;
  }).join('');
  applyDhaltaPctVisibility();
  calcPurchaseNewTotals();
}

// Show/hide the Dhalta % sub-column per Settings → Company (show_dhalta_pct).
// The Kg column always stays — only the derived percentage is optional.
function applyDhaltaPctVisibility() {
  const show = (STATE.settings.showDhaltaPct ?? '1') !== '0';
  document.querySelectorAll('.pne-dhpct-col').forEach(el => { el.style.display = show ? '' : 'none'; });
  const g = document.getElementById('pne-th-dhalta-group');
  if (g) { g.colSpan = show ? 2 : 1; g.textContent = show ? 'Dhalta' : 'Dhalta (Kg)'; }

  // The colgroup assigns widths by DOM position, so when hiding Dhalta %
  // we physically remove its <col> — otherwise every column after it shifts
  // one slot and headers no longer align with cells.
  // IMPORTANT: renderPNEItemsTable() replaces the whole table, so after any
  // re-render a fresh <col id="pne-col-dhpct"> exists in the DOM. We must
  // always re-read from the DOM, never use a stale JS reference from a
  // previous render. The variable is intentionally dropped after each use.
  const colPct = document.getElementById('pne-col-dhpct');
  const colKg  = document.getElementById('pne-col-dhkg');

  if (!show && colPct) {
    // Store on the colKg node itself so it survives re-renders (colKg also
    // gets a stable id and is never removed, only width-adjusted)
    colKg._dhpctCol = colPct;
    colPct.remove();
  } else if (show && colKg) {
    // Restore: either the col is already there (show re-applied after render)
    // or we need to put the stashed one back
    const stash = colKg._dhpctCol;
    if (!document.getElementById('pne-col-dhpct') && stash) {
      colKg.parentNode.insertBefore(stash, colKg);
      colKg._dhpctCol = null;
    }
  }

  // Widen the Kg col when % is hidden (more room to type values like 1250.50)
  if (colKg) colKg.style.width = show ? '65px' : '100px';
}

function onPNEProductChange(id, productId) {
  const it = PNE.items.find(i => i.id === id); if (!it) return;
  it.product_id = productId || '';
  if (productId) {
    const p = STATE.products.find(x => String(x.id) === String(productId));
    if (p) {
      it.description = p.name;
      // Auto-fill from product master — only when the field is blank so a
      // user who changes product mid-entry doesn't silently lose what they typed.
      if (!it.rate)          it.rate = parseFloat(p.purchase_rate ?? p.rate) || 0;
      if (!it.variety_grade) it.variety_grade = p.variety || '';
      if (!it.quality_grade) it.quality_grade = p.grade || '';
      // moisture_limit is the product's stored expected moisture — editable per purchase lot
      if ((it.moisture_pct === null || it.moisture_pct === '' || it.moisture_pct === undefined) && p.moisture_limit)
        it.moisture_pct = parseFloat(p.moisture_limit);
    }
  }
  renderPNEItemsTable();
}

// Non-destructive update: recompute this row's derived cells (Net/Dhalta%/Billable/Amount)
// directly via DOM instead of re-rendering the whole table — keeps focus in the input
// the person is actively typing in (see the earlier "disables after one letter" fix).
function updatePNEItem(id, field, val, isText) {
  const it = PNE.items.find(i => i.id === id); if (!it) return;
  it[field] = val;
  const c = pneCalcRow(it);
  const netEl = document.getElementById('pne-net-' + id);      if (netEl) netEl.textContent = c.net.toFixed(2);
  const dpEl  = document.getElementById('pne-dhaltapct-' + id); if (dpEl) dpEl.textContent = c.dhaltaPct.toFixed(2);
  const bwEl  = document.getElementById('pne-billable-' + id); if (bwEl) bwEl.textContent = c.billable.toFixed(2);
  const amtEl = document.getElementById('pne-amt-' + id);      if (amtEl) amtEl.textContent = fmt_money(c.amount);
  calcPurchaseNewTotals();
}


function calcPurchaseNewTotals() {
  let totalNet = 0, totalDhalta = 0, totalBillable = 0, subtotal = 0, totalItemDiscount = 0;
  let sumGross = 0, sumTare = 0, moistureWeighted = 0, dhaltaPctWeighted = 0;
  PNE.items.forEach(it => {
    const c = pneCalcRow(it);
    totalNet += c.net; totalDhalta += c.dhaltaKg; totalBillable += c.billable; subtotal += c.amount; totalItemDiscount += c.discountAmt;
    sumGross += parseFloat(it.gross_weight) || 0;
    sumTare  += parseFloat(it.tare_weight)  || 0;
    moistureWeighted   += (parseFloat(it.moisture_pct) || 0) * c.net;
    dhaltaPctWeighted  += c.dhaltaPct * c.net;
  });
  document.getElementById('pne-total-net').textContent = totalNet.toFixed(2) + ' Kg';
  document.getElementById('pne-total-dhalta').textContent = totalDhalta.toFixed(2) + ' Kg';
  document.getElementById('pne-total-billable').textContent = totalBillable.toFixed(2) + ' Kg';
  document.getElementById('pne-total-amount').textContent = fmt_money(subtotal);
  document.getElementById('pne-sb-items').textContent = PNE.items.length;
  document.getElementById('pne-sb-net').textContent = totalNet.toFixed(2) + ' Kg';
  document.getElementById('pne-sb-dhalta').textContent = totalDhalta.toFixed(2) + ' Kg';
  document.getElementById('pne-sb-billable').textContent = totalBillable.toFixed(2) + ' Kg';
  document.getElementById('pne-sb-amount').textContent = fmt_money(subtotal);

  // ── Weight Summary sidebar — driven from PNE.items so it stays
  // correct even after the header fields reset on Done / row removed ──
  const fmt = v => v > 0 ? v.toFixed(2) + ' Kg' : '0.00 Kg';
  document.getElementById('pnk-sum-gross').textContent    = fmt(sumGross);
  document.getElementById('pnk-sum-tare').textContent     = fmt(sumTare);
  document.getElementById('pnk-sum-net').textContent      = fmt(totalNet);
  document.getElementById('pnk-sum-dhalta').textContent   = fmt(totalDhalta);
  document.getElementById('pnk-sum-billable').textContent = fmt(totalBillable);
  // Do NOT write back to pn-q-moisture / pn-q-dhaltapct / pn-q-dhaltakg / pn-q-billable here.
  // Those are per-item header fields for the current row being entered —
  // writing the accumulated total into them causes every new item to inherit
  // the previous rows' combined values. calcPNEQualitySummary() handles them
  // when the user is actively filling an item.

  const addCharges = (parseFloat(document.getElementById('pn-transportcharge').value)||0)
    + (parseFloat(document.getElementById('pn-loadingcharge').value)||0)
    + (parseFloat(document.getElementById('pn-packingcharge').value)||0)
    + (parseFloat(document.getElementById('pn-othercharge').value)||0);
  document.getElementById('pn-addcharges-total').textContent = fmt_money(addCharges);
  document.getElementById('pn-sum-subtotal').textContent = fmt_money(subtotal);
  document.getElementById('pn-sum-addcharges').textContent = fmt_money(addCharges);

  const headerDiscount = 0; // Discount removed — use Deductions section instead
  const totalDeductions = PNE.deductions.reduce((s,d) => s + (parseFloat(d.amount)||0), 0);
  document.getElementById('pn-deductions-total').textContent = fmt_money(totalDeductions);
  // Show in Tax & Amount Summary only when deductions exist
  const dedRow = document.getElementById('pn-sum-deductions-row');
  const dedVal = document.getElementById('pn-sum-deductions');
  if (dedRow) dedRow.style.display = totalDeductions > 0 ? '' : 'none';
  if (dedVal) dedVal.textContent = fmt_money(totalDeductions);

  const tradeDiscPct = parseFloat(document.getElementById('pn-tradediscpct').value) || 0;
  const cashDiscPct = parseFloat(document.getElementById('pn-cashdiscpct').value) || 0;
  const tradeDiscAmt = +(subtotal * tradeDiscPct / 100).toFixed(2);
  const cashDiscAmt = +(subtotal * cashDiscPct / 100).toFixed(2);
  document.getElementById('pn-cashdisc-amt').textContent = fmt_money(cashDiscAmt);
  document.getElementById('pn-cashdisc-note').textContent = `(${cashDiscPct}% of Total Gross Amount)`;

  const taxable = Math.max(0, subtotal + addCharges - headerDiscount - totalDeductions - tradeDiscAmt - cashDiscAmt);
  document.getElementById('pn-sum-taxable').textContent = fmt_money(taxable);

  document.getElementById('pne-sb-discount').textContent = fmt_money(totalItemDiscount);

  const gstApplicable = document.getElementById('pn-gst-yes').classList.contains('active');
  const gstPct = gstApplicable ? (parseFloat(document.getElementById('pn-gst-pct').value) || 0) : 0;
  const gstAmt = gstApplicable ? +(taxable * gstPct / 100).toFixed(2) : 0;
  document.getElementById('pn-sum-gst').textContent = fmt_money(gstAmt);

  const grand = +(taxable + gstAmt).toFixed(2);
  document.getElementById('pn-sum-grand').textContent = fmt_money(grand);

  // Keep Amount Paid sane if Payment Status is set to Paid
  const payStatus = document.getElementById('pn-paystatus').value;
  if (payStatus === 'Paid') document.getElementById('pn-amountpaid').value = grand.toFixed(2);

  updatePNEPartialCard(grand, payStatus);
  updatePNESplitMismatch();
}

function updatePNEPartialCard(grand, payStatus) {
  const card = document.getElementById('pn-partial-card');
  if (!card) return;
  const show = payStatus === 'Partial';
  card.style.display = show ? 'block' : 'none';
  if (!show) return;
  const paid = parseFloat(document.getElementById('pn-amountpaid').value) || 0;
  const remaining = Math.max(0, grand - paid);
  const pct = grand > 0 ? Math.min(100, (paid / grand) * 100) : 0;
  document.getElementById('pn-partial-total').textContent = fmt_money(grand);
  document.getElementById('pn-partial-received').textContent = fmt_money(paid);
  document.getElementById('pn-partial-remaining').textContent = fmt_money(remaining);
  document.getElementById('pn-partial-bar').style.width = pct.toFixed(1) + '%';
}

// ── Split Payment (Payment Information) ──────────────────────────
// Mirrors the existing invoice Record-Payment split pattern, scoped with a
// "pne" prefix so it doesn't collide with that modal's own split UI.
//
// Design: row 0 is always the AUTO row — its amount is never typed directly,
// it's continuously recomputed as (Amount Paid − sum of every other row).
// Rows 1+ are freely editable. This guarantees the split always reconciles
// to Amount Paid by construction, rather than hoping the user's manual
// entries happen to add up.
const PNE_SPLIT_COLORS = {
  'Cash': '#2E7D32', 'Bank Transfer': '#1565C0', 'UPI': '#6A4C93', 'Cheque': '#E65100',
};
function pneSplitColor(method) { return PNE_SPLIT_COLORS[method] || '#455A64'; }

function togglePNESplitPayment() {
  const isSplit = document.getElementById('pn-paymode').value === 'Split Payment';
  const panel = document.getElementById('pne-split-panel');
  panel.style.display = isSplit ? 'block' : 'none';
  if (isSplit && document.getElementById('pne-split-rows').children.length === 0) {
    addPNESplitRow(); addPNESplitRow();
    // syncPNESplitAutoRow fills row 1 with (Amount Paid − others) = the
    // full Amount Paid on a fresh open, and keeps rebalancing it live as
    // amounts are typed into the other rows.
    syncPNESplitAutoRow();
  }
}

// Auto-balance: the FIRST method row acts as the remainder — whenever you
// type an amount into any OTHER row, row 1 is recomputed as
// (Amount Paid − sum of all other rows), live. Editing row 1 directly is
// still allowed (that edit is respected as-is; the mismatch warning covers
// any resulting gap). `changedEl` is the input the user actually typed in.
function syncPNESplitAutoRow(changedEl) {
  const rows = Array.from(document.querySelectorAll('#pne-split-rows .pne-split-row'));
  if (rows.length > 1) {
    const firstAmt = rows[0].querySelector('.pne-split-amt');
    const editedFirstRow = changedEl && rows[0].contains(changedEl);
    if (!editedFirstRow && firstAmt) {
      const target = parseFloat(document.getElementById('pn-amountpaid').value) || 0;
      let othersSum = 0;
      for (let i = 1; i < rows.length; i++) othersSum += parseFloat(rows[i].querySelector('.pne-split-amt')?.value) || 0;
      firstAmt.value = Math.max(0, target - othersSum).toFixed(2);
    }
  }
  renderPNESplitFooter();
  updatePNESplitMismatch();
}

// Reconstructs real, editable rows from a saved "Split: Cash: ₹X + UPI: ₹Y"
// label — so editing a split-paid purchase shows exactly what was saved,
// not a text hint asking the person to re-type it from memory.
function restorePNESplitFromLabel(label) {
  const body = label.replace(/^Split:\s*/, '');
  const parts = body.split('+').map(s => s.trim()).filter(Boolean).map(s => {
    const m = s.match(/^(.+?):\s*₹\s*([\d,]+(?:\.\d+)?)$/);
    return m ? { method: m[1].trim(), amount: parseFloat(m[2].replace(/,/g, '')) } : null;
  }).filter(Boolean);

  if (parts.length === 0) { addPNESplitRow(); addPNESplitRow(); syncPNESplitAutoRow(); return; }
  parts.forEach((p, i) => {
    addPNESplitRow();
    setPNESplitRowMethod(i, p.method);
    const rows = document.querySelectorAll('#pne-split-rows .pne-split-row');
    rows[i].querySelector('.pne-split-amt').value = p.amount.toFixed(2);
  });
  // Pass row 1's input as the "changed" element so restore shows the exact
  // saved amounts rather than recomputing row 1 as a remainder.
  const firstRowAmt = document.querySelector('#pne-split-rows .pne-split-row .pne-split-amt');
  syncPNESplitAutoRow(firstRowAmt);
}
function setPNESplitRowMethod(rowIndex, method) {
  const rows = document.querySelectorAll('#pne-split-rows .pne-split-row');
  const sel = rows[rowIndex]?.querySelector('.pne-split-method');
  if (!sel) return;
  const match = Array.from(sel.options).find(o => o.value.split(' (')[0] === method);
  if (match) sel.value = match.value;
}

function addPNESplitRow() {
  const container = document.getElementById('pne-split-rows');
  const row = document.createElement('div');
  row.className = 'pne-split-row';
  row.innerHTML = `<select class="pne-split-method" onchange="renderPNESplitFooter()">
      <option>UPI (GPay/PhonePe/Paytm)</option><option>Cash</option><option>Bank Transfer (NEFT/RTGS)</option><option>Cheque</option>
    </select>
    <input type="number" class="pne-split-amt" placeholder="0.00" step="0.01" oninput="syncPNESplitAutoRow(this)">
    <button type="button" onclick="removePNESplitRow(this)"><i class="fas fa-times"></i></button>`;
  container.appendChild(row);
  renderPNESplitFooter();
}

function removePNESplitRow(btn) {
  const rows = document.querySelectorAll('#pne-split-rows .pne-split-row');
  if (rows.length <= 1) { toast('⚠️ Keep at least 1 split method', 'warning'); return; }
  btn.closest('.pne-split-row').remove();
  syncPNESplitAutoRow();
}

// Live Split Total + a pipe-separated colored breakdown bar, matching the
// reference design: "Total: ₹X | UPI: ₹X | Cash: ₹X"
function renderPNESplitFooter() {
  const rows = Array.from(document.querySelectorAll('#pne-split-rows .pne-split-row'));
  let splitTotal = 0;
  const parts = rows.map(r => {
    const method = r.querySelector('.pne-split-method')?.value || '';
    const amt = parseFloat(r.querySelector('.pne-split-amt')?.value) || 0;
    splitTotal += amt;
    const shortMethod = method.split(' (')[0];
    const color = pneSplitColor(shortMethod);
    return `<span style="color:${color};font-weight:700">${escHtml(shortMethod)}: ${fmt_money(amt)}</span>`;
  });
  const totalEl = document.getElementById('pne-split-total-amt');
  if (totalEl) totalEl.textContent = fmt_money(splitTotal);
  const footer = document.getElementById('pne-split-footer');
  if (footer) footer.innerHTML = parts.length
    ? `<strong>Total: ${fmt_money(splitTotal)}</strong>` + parts.map(p => ' &nbsp;|&nbsp; ' + p).join('')
    : '';
}

function updatePNESplitMismatch() {
  const warnEl = document.getElementById('pne-split-mismatch');
  if (!warnEl || document.getElementById('pn-paymode').value !== 'Split Payment') { if(warnEl) warnEl.style.display='none'; return; }
  const amts = Array.from(document.querySelectorAll('#pne-split-rows .pne-split-amt')).map(el => parseFloat(el.value)||0);
  const splitSum = amts.reduce((s,v) => s+v, 0);
  const amountPaid = parseFloat(document.getElementById('pn-amountpaid').value) || 0;
  if (amountPaid > 0 && Math.abs(splitSum - amountPaid) > 0.01) {
    warnEl.style.display = 'block';
    warnEl.textContent = splitSum > amountPaid
      ? `⚠️ Split total (${fmt_money(splitSum)}) exceeds Amount Paid`
      : `⚠️ Split total (${fmt_money(splitSum)}) is less than Amount Paid`;
  } else {
    warnEl.style.display = 'none';
  }
}

function getPNESplitLabel() {
  const rows = document.querySelectorAll('#pne-split-rows .pne-split-row');
  const parts = Array.from(rows).map(r => {
    const m = (r.querySelector('.pne-split-method')?.value || '').split(' (')[0];
    const a = parseFloat(r.querySelector('.pne-split-amt')?.value || 0);
    return a > 0 ? `${m}: ₹${a.toFixed(2)}` : null;
  }).filter(Boolean);
  return 'Split: ' + parts.join(' + ');
}


async function editPurchase(id) {
  try {
    const r = await api('api/purchases.php?id=' + id);
    const p = r.data;
    PNE.editingId = id;
    PNE.attachmentDataUrl = null;
    PNE.attachmentExisting = p.attachment_path || null;
    PNE.items = (p.items||[]).map(it => ({
      id: pneItemSeq++, mode: it.product_id ? 'catalog' : 'freetext', product_id: it.product_id ? 'p' + it.product_id : '', description: it.description,
      variety_grade: it.variety_grade || '', moisture_pct: it.moisture_pct || 0, quality_grade: it.quality_grade || '',
      gross_weight: it.gross_weight || 0, tare_weight: it.tare_weight || 0, dhalta_kg: it.dhalta_kg || 0,
      rate: it.rate || 0, discount_pct: it.discount_pct || 0, editing: false,
    }));
    document.getElementById('pne-title').textContent = 'Edit Purchase Entry';
    document.getElementById('pne-subtitle').textContent = p.purchase_no;
    document.getElementById('pn-no').value = p.purchase_no;
    document.getElementById('pn-date').value = p.purchase_date;
    document.getElementById('pn-suppliertype').value = p.supplier_type || 'Farmer';
    document.getElementById('pn-refpo').value = p.reference_po_no || '';
    document.getElementById('pn-weighingtype').value = p.weighing_type || 'Dharam Kanta';
    document.getElementById('pn-kantaname').value = p.kanta_name || '';
    document.getElementById('pn-slipno').value = p.weighbridge_slip_no || '';
    document.getElementById('pn-weightdatetime').value = p.weight_datetime ? p.weight_datetime.replace(' ', 'T').slice(0,16) : '';
    document.getElementById('pn-kanta-gross').value = p.kanta_gross_weight || '';
    document.getElementById('pn-kanta-tare').value = p.kanta_tare_weight || '';
    document.getElementById('pn-kanta-operator').value = p.kanta_operator_name || '';
    PNE.kantaSlipDataUrl = null;
    if (p.kanta_slip_path) {
      document.getElementById('pn-kanta-slip-label').innerHTML = `<i class="fas fa-file-alt" style="color:var(--teal)"></i><div style="text-align:left">Weight slip on file<br><span style="font-size:10px">Uploaded previously</span></div>`;
    }
    document.getElementById('pn-q-moisture').value = p.header_moisture_pct ?? '';
    document.getElementById('pn-q-impurity').value = p.header_impurity_pct ?? '';
    document.getElementById('pn-q-dhaltapct').value = p.header_dhalta_pct ?? '';
    calcPNEKantaSummary();
    populatePNESupplierDropdown();
    document.getElementById('pn-supplier').value = p.supplier_id;
    await onSupplierPicked();
    document.getElementById('pn-invno').value = p.supplier_invoice_ref || '';
    document.getElementById('pn-transportmode').value = p.transport_mode || 'Road';
    document.getElementById('pn-vehicleno').value = p.vehicle_no || '';
    document.getElementById('pn-drivername').value = p.driver_name || '';
    document.getElementById('pn-warehouse').value = p.warehouse || 'Main Warehouse';
    document.getElementById('pn-paymentterms').value = p.payment_terms || 'Immediate';
    document.getElementById('pn-paymenttype').value = p.payment_type || 'Cash';
    document.getElementById('pn-remarks').value = p.remarks || '';
    setGstApplicable(!!parseInt(p.gst_applicable));
    document.getElementById('pn-supplytype').value = p.supply_type || 'Intra-State';
    document.getElementById('pn-gst-pct').value = p.gst_pct || 0;
    document.getElementById('pn-transportcharge').value = p.transport_charge || 0;
    document.getElementById('pn-loadingcharge').value = p.loading_charge || 0;
    document.getElementById('pn-packingcharge').value = p.packing_charge || 0;
    document.getElementById('pn-othercharge').value = p.other_charges || 0;

    document.getElementById('pn-tradediscpct').value = p.trade_discount_pct || 0;
    document.getElementById('pn-cashdiscpct').value = p.cash_discount_pct || 0;
    document.getElementById('pn-cdwithin').value = p.cd_applicable_within || 'Same Day';
    PNE.deductions = (p.deductions||[]).map(d => ({ id: pnDeductionSeq++, type: d.type||'', description: d.description||'', amount: parseFloat(d.amount)||0 }));
    renderPNDeductions();
    document.getElementById('pn-paystatus').value = p.status || 'Pending';
    document.getElementById('pn-amountpaid').value = p.amount_paid || 0;
    const isSplitSaved = (p.payment_mode || '').startsWith('Split:');
    document.getElementById('pn-paymode').value = isSplitSaved ? 'Split Payment' : (p.payment_mode || 'Cash');
    document.getElementById('pne-split-panel').style.display = isSplitSaved ? 'block' : 'none';
    document.getElementById('pne-split-rows').innerHTML = '';
    if (isSplitSaved) {
      restorePNESplitFromLabel(p.payment_mode);
    }
    document.getElementById('pn-transactionno').value = p.transaction_no || '';
    document.getElementById('pn-paydate').value = p.payment_date || '';
    document.getElementById('pn-notes').value = p.notes || '';
    renderPNEItemsTable();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function calcPNEKantaSummary() {
  const gross = parseFloat(document.getElementById('pn-kanta-gross').value) || 0;
  const tare  = parseFloat(document.getElementById('pn-kanta-tare').value) || 0;
  const net   = Math.max(0, gross - tare);
  document.getElementById('pn-kanta-net').value = net > 0 ? net.toFixed(2) : '';

  // Apply dhalta% show/hide from settings (same toggle as table column)
  const showDhaltaPct = (STATE.settings.showDhaltaPct ?? '1') !== '0';
  const dhaltaPctHeader = document.querySelector('.pne-dhpct-col.field');
  if (dhaltaPctHeader) dhaltaPctHeader.style.display = showDhaltaPct ? '' : 'none';

  // ── Sync to item row gross/tare ──────────────────────────────
  // The header kanta reading IS the weight for the current item being filled.
  // Push gross/tare into the item data and update computed cells directly
  // WITHOUT re-rendering the whole table (which would cause an infinite loop
  // since the gross/tare inputs have oninput → this function).
  if (gross > 0 || tare > 0) {
    let target = null;
    if (PNE.items.length === 1) {
      target = PNE.items[0];
    } else {
      target = PNE.items.find(it => it.editing) || null;
    }
    if (target) {
      target.gross_weight = gross || 0;
      target.tare_weight  = tare  || 0;
      const c        = pneCalcRow(target);
      // Update edit-mode inputs (exist when row is in edit mode)
      const grossIn  = document.getElementById('pne-gross-'   + target.id);
      const tareIn   = document.getElementById('pne-tare-'    + target.id);
      // Update view-mode cells (exist when row is saved/view mode)
      const vGrossEl = document.getElementById('pne-vgross-'  + target.id);
      const vTareEl  = document.getElementById('pne-vtare-'   + target.id);
      const netEl    = document.getElementById('pne-net-'     + target.id);
      const billEl   = document.getElementById('pne-billable-'+ target.id);
      const amtEl    = document.getElementById('pne-amt-'     + target.id);
      // Edit mode inputs — update value without cursor interference
      // (these are the header→table sync, not user typing in the input)
      if (grossIn && document.activeElement !== grossIn) grossIn.value = gross || '';
      if (tareIn  && document.activeElement !== tareIn)  tareIn.value  = tare  || '';
      // View mode cells
      if (vGrossEl) vGrossEl.textContent = gross > 0 ? gross.toFixed(2) : '—';
      if (vTareEl)  vTareEl.textContent  = tare  > 0 ? tare.toFixed(2)  : '—';
      if (netEl)    netEl.textContent    = c.net.toFixed(2);
      if (billEl)   billEl.textContent   = c.billable.toFixed(2);
      if (amtEl)    amtEl.textContent    = fmt_money(c.amount);
      // Update footer totals directly — don't call calcPurchaseNewTotals()
      // because that calls calcPNEKantaSummary() creating an infinite loop.
      let footNet = 0, footDhalta = 0, footBillable = 0, footAmt = 0;
      PNE.items.forEach(i => { const r = pneCalcRow(i); footNet += r.net; footDhalta += r.dhaltaKg; footBillable += r.billable; footAmt += r.amount; });
      const _s = id => document.getElementById(id);
      if (_s('pne-total-net'))      _s('pne-total-net').textContent      = footNet.toFixed(2) + ' Kg';
      if (_s('pne-total-dhalta'))   _s('pne-total-dhalta').textContent   = footDhalta.toFixed(2) + ' Kg';
      if (_s('pne-total-billable')) _s('pne-total-billable').textContent = footBillable.toFixed(2) + ' Kg';
      if (_s('pne-total-amount'))   _s('pne-total-amount').textContent   = fmt_money(footAmt);
    }
  }

  // Update sidebar weight summary — accumulate from ALL saved items so
  // the summary stays correct after Done resets the header fields.
  let sumGross2 = 0, sumTare2 = 0, sumNet2 = 0, sumDhalta2 = 0, sumBillable2 = 0;
  PNE.items.forEach(it => {
    const c = pneCalcRow(it);
    sumGross2    += parseFloat(it.gross_weight) || 0;
    sumTare2     += parseFloat(it.tare_weight)  || 0;
    sumNet2      += c.net;
    sumDhalta2   += c.dhaltaKg;
    sumBillable2 += c.billable;
  });
  // If header has active values being filled, show current header net in summary
  const dispNet  = net  > 0 ? net  : sumNet2;
  const dispGross= gross> 0 ? gross: sumGross2;
  const dispTare = tare > 0 ? tare : sumTare2;

  const fmt = v => v > 0 ? v.toFixed(2) + ' Kg' : '0.00 Kg';
  document.getElementById('pnk-sum-gross').textContent    = fmt(dispGross);
  document.getElementById('pnk-sum-tare').textContent     = fmt(dispTare);
  document.getElementById('pnk-sum-net').textContent      = fmt(dispNet);
  document.getElementById('pnk-sum-dhalta').textContent   = fmt(sumDhalta2);
  document.getElementById('pnk-sum-billable').textContent = fmt(sumBillable2);
  calcPNEQualitySummary();
}

// Quality, Moisture & Dhalta header fields reflect the CURRENT item being
// entered — not a running total. Dhalta Kg and % show what was entered for
// this specific row; Billable = current item's net − its own dhalta.
function calcPNEQualitySummary() {
  const net = parseFloat(document.getElementById('pn-kanta-net').value) || 0;
  if (!net) return; // no weight entered yet — leave fields blank

  // Use only the current editing item's dhalta (per-row, not total)
  const editingItem = PNE.items.find(i => i.editing);
  const dhaltaKg = editingItem ? (parseFloat(editingItem.dhalta_kg) || 0) : 0;
  const dhaltaPct = net > 0 ? (dhaltaKg / net * 100) : 0;
  const billable  = Math.max(0, net - dhaltaKg);

  const pctEl = document.getElementById('pn-q-dhaltapct');
  const kgEl  = document.getElementById('pn-q-dhaltakg');
  const bilEl = document.getElementById('pn-q-billable');

  if (pctEl) pctEl.value = dhaltaPct > 0 ? dhaltaPct.toFixed(2) : '';
  if (kgEl && document.activeElement !== kgEl) kgEl.value = dhaltaKg > 0 ? dhaltaKg.toFixed(2) : '';
  if (bilEl) bilEl.value = billable  > 0 ? billable.toFixed(2)  : '';
}

// User manually types dhalta Kg in the header — push it back into the
// single item row (if only one exists) so table stays in sync.
function onPNEHeaderDhaltaKgInput(val) {
  const kg  = parseFloat(val) || 0;
  const net = parseFloat(document.getElementById('pn-kanta-net').value) || 0;

  // Find the current editing item (the one this header applies to)
  const target = PNE.items.length === 1
    ? PNE.items[0]
    : PNE.items.find(i => i.editing) || null;

  if (target) {
    // Update the item object — this is what donePNEItem reads when saving to view mode
    target.dhalta_kg = kg;
    const c = pneCalcRow(target);

    // Update edit-mode cells if the row is open
    const dkIn  = document.getElementById('pne-dkg-'      + target.id);
    const billEl= document.getElementById('pne-billable-' + target.id);
    const amtEl = document.getElementById('pne-amt-'      + target.id);
    const dhEl  = document.getElementById('pne-dhaltapct-'+ target.id);
    if (dkIn  && document.activeElement !== dkIn) dkIn.value = kg || '';
    if (billEl) billEl.textContent = c.billable.toFixed(2);
    if (amtEl)  amtEl.textContent  = fmt_money(c.amount);
    if (dhEl)   dhEl.textContent   = c.dhaltaPct.toFixed(2);
    // Update footer totals
    let footNet = 0, footDhalta = 0, footBillable = 0, footAmt = 0;
    PNE.items.forEach(i => { const r = pneCalcRow(i); footNet += r.net; footDhalta += r.dhaltaKg; footBillable += r.billable; footAmt += r.amount; });
    const _sf = id => document.getElementById(id);
    if (_sf('pne-total-dhalta'))   _sf('pne-total-dhalta').textContent   = footDhalta.toFixed(2) + ' Kg';
    if (_sf('pne-total-billable')) _sf('pne-total-billable').textContent = footBillable.toFixed(2) + ' Kg';
    if (_sf('pne-total-amount'))   _sf('pne-total-amount').textContent   = fmt_money(footAmt);
  }

  // Update billable and dhalta% in header
  const billable = Math.max(0, net - kg);
  const bilEl = document.getElementById('pn-q-billable');
  const pctEl = document.getElementById('pn-q-dhaltapct');
  if (bilEl) bilEl.value = billable > 0 ? billable.toFixed(2) : '';
  if (pctEl && net > 0) pctEl.value = kg > 0 ? (kg / net * 100).toFixed(2) : '';
}

function pneKantaSlipChange(file) {
  if (!file) return;
  if (file.size > 5*1024*1024) { toast('⚠️ Weight slip must be under 5MB', 'warning'); return; }
  const label = document.getElementById('pn-kanta-slip-label');
  label.innerHTML = `<i class="fas fa-file-alt" style="color:var(--teal)"></i><div style="text-align:left">${escHtml(file.name)}<br><span style="font-size:10px">${(file.size/1024).toFixed(0)} KB</span></div>`;
  const reader = new FileReader();
  reader.onload = () => { PNE.kantaSlipDataUrl = reader.result; };
  reader.readAsDataURL(file);
}

function pneReadAttachment() {
  return new Promise(resolve => {
    const f = document.getElementById('pn-attachment')?.files?.[0];
    if (!f) return resolve(null);
    if (f.size > 5*1024*1024) { toast('⚠️ Attachment must be under 5MB — skipping upload', 'warning'); return resolve(null); }
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => resolve(null);
    reader.readAsDataURL(f);
  });
}

async function savePurchaseEntry(mode) {
  const supplierId = document.getElementById('pn-supplier').value;
  if (!supplierId) { toast('⚠️ Select a supplier', 'warning'); return; }
  if (!document.getElementById('pn-date').value) { toast('⚠️ Purchase date is required', 'warning'); return; }
  if (!PNE.items.length) { toast('⚠️ Add at least one item', 'warning'); return; }
  const badItem = PNE.items.find(it => it.mode === 'freetext' ? !(it.description||'').trim() : !it.product_id);
  if (badItem) {
    toast('⚠️ Every item needs a product (or a description for free-text lines)', 'warning'); return;
  }

  const attachment = await pneReadAttachment();
  const gstApplicable = document.getElementById('pn-gst-yes').classList.contains('active');

  const payload = {
    purchase_no: document.getElementById('pn-no').value.trim(),
    supplier_id: parseInt(supplierId),
    purchase_date: document.getElementById('pn-date').value,
    supplier_type: document.getElementById('pn-suppliertype').value,
    reference_po_no: document.getElementById('pn-refpo').value.trim(),
    weighing_type: document.getElementById('pn-weighingtype').value,
    kanta_name: document.getElementById('pn-kantaname').value.trim(),
    weighbridge_slip_no: document.getElementById('pn-slipno').value.trim(),
    weight_datetime: document.getElementById('pn-weightdatetime').value || null,
    kanta_gross_weight: parseFloat(document.getElementById('pn-kanta-gross').value) || 0,
    kanta_tare_weight: parseFloat(document.getElementById('pn-kanta-tare').value) || 0,
    kanta_operator_name: document.getElementById('pn-kanta-operator').value.trim(),
    kanta_slip: PNE.kantaSlipDataUrl || undefined,
    header_moisture_pct: document.getElementById('pn-q-moisture').value || null,
    header_impurity_pct: document.getElementById('pn-q-impurity').value || null,
    header_dhalta_pct: document.getElementById('pn-q-dhaltapct').value || null,
    header_dhalta_kg: document.getElementById('pn-q-dhaltakg').value || null,
    header_billable_weight: document.getElementById('pn-q-billable').value || null,
    invoice_bill_no: document.getElementById('pn-invno').value.trim(),
    gst_applicable: gstApplicable,
    gst_pct: parseFloat(document.getElementById('pn-gst-pct').value) || 0,
    supply_type: document.getElementById('pn-supplytype').value,
    transport_mode: document.getElementById('pn-transportmode').value,
    vehicle_no: document.getElementById('pn-vehicleno').value.trim(),
    driver_name: document.getElementById('pn-drivername').value.trim(),
    warehouse: document.getElementById('pn-warehouse').value,
    payment_terms: document.getElementById('pn-paymentterms').value,
    payment_type: document.getElementById('pn-paymenttype').value,
    remarks: document.getElementById('pn-remarks').value.trim(),
    transport_charge: parseFloat(document.getElementById('pn-transportcharge').value) || 0,
    loading_charge: parseFloat(document.getElementById('pn-loadingcharge').value) || 0,
    packing_charge: parseFloat(document.getElementById('pn-packingcharge').value) || 0,
    other_charges: parseFloat(document.getElementById('pn-othercharge').value) || 0,
    discount_amount: 0,
    deductions: PNE.deductions.filter(d => (parseFloat(d.amount)||0) > 0).map(d => ({ type: d.type, description: d.description, amount: parseFloat(d.amount)||0 })),
    trade_discount_pct: parseFloat(document.getElementById('pn-tradediscpct').value) || 0,
    cash_discount_pct: parseFloat(document.getElementById('pn-cashdiscpct').value) || 0,
    cd_applicable_within: document.getElementById('pn-cdwithin').value,
    payment_status: document.getElementById('pn-paystatus').value,
    amount_paid: parseFloat(document.getElementById('pn-amountpaid').value) || 0,
    payment_mode: document.getElementById('pn-paymode').value === 'Split Payment' ? getPNESplitLabel() : document.getElementById('pn-paymode').value,
    transaction_no: document.getElementById('pn-transactionno').value.trim(),
    payment_date: document.getElementById('pn-paydate').value || null,
    notes: document.getElementById('pn-notes').value.trim(),
    attachment: attachment || undefined,
    items: PNE.items.map(it => ({
      product_id: it.product_id || null, description: it.description, hsn: '',
      variety_grade: it.variety_grade, moisture_pct: it.moisture_pct, quality_grade: it.quality_grade,
      gross_weight: parseFloat(it.gross_weight)||0, tare_weight: parseFloat(it.tare_weight)||0,
      dhalta_kg: parseFloat(it.dhalta_kg)||0, rate: parseFloat(it.rate)||0, discount_pct: parseFloat(it.discount_pct)||0,
    })),
  };

  const btn = event?.target?.closest('button');
  if (btn) { btn.disabled = true; }
  try {
    let savedId = PNE.editingId;
    if (PNE.editingId) {
      await api('api/purchases.php?id=' + PNE.editingId, 'PUT', payload);
      consumeEditApproval(); toast('✅ Purchase updated!', 'success');
    } else {
      const res = await api('api/purchases.php', 'POST', payload);
      savedId = res.id;
      consumeEditApproval(); toast('✅ Purchase saved!', 'success');
    }
    const [r, prd, stk] = await Promise.all([api('api/purchases.php'), api('api/products.php'), api('api/stock.php')]);
    STATE.purchases = Array.isArray(r.data) ? r.data : STATE.purchases;
    STATE.products  = Array.isArray(prd.data) ? prd.data : STATE.products;
    STATE.stock     = Array.isArray(stk.data) ? stk.data : STATE.stock;

    if (mode === 'print') {
      printPurchaseEntry(savedId);
      window.location.href = '/pages/purchases/purchases.php';
    } else if (mode === 'new') {
      goToNewPurchase();
    } else {
      window.location.href = '/pages/purchases/purchases.php';
    }
  } catch(e) { toast('❌ ' + e.message, 'error'); }
  finally { if (btn) btn.disabled = false; }
}
