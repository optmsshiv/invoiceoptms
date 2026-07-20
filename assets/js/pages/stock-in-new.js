const PP_GRADE_VARIETY_MAP = { 'Grade-1': 'Premium', 'Grade-2': 'SBD', 'Grade-3': 'BD', 'Grade-4': 'CD', 'Grade-5': 'RBD' };
const PP_VARIETY_GRADE_MAP = Object.fromEntries(Object.entries(PP_GRADE_VARIETY_MAP).map(([g,v]) => [v,g]));
const STI_RECONCILE_TOLERANCE_PCT = 2;

// ============================================================
// stock-in-new.js — page-specific JS for pages/stock-in-new.php
// Depends on: common.js, app.js, stock-shared.js
// initStockInPage() replaces the old goToNewStockIn() — same reasoning
// as stock-adjust-new.js.
// ============================================================

document.addEventListener('DOMContentLoaded', async () => {
  await bootStockPageState();
  await initStockInPage();
});

async function initStockInPage() {
  STI.editingId = null;
  document.getElementById('sti-page-title').textContent = 'Add Product to Stock (Stock In)';
  STI.items = []; STI.attachments = []; STI.slipDataUrl = null;
  document.getElementById('sti-refno').value = '';
  document.getElementById('sti-refdate').value = fmt_date(new Date());
  document.getElementById('sti-warehouse').value = 'Main Warehouse';
  document.getElementById('sti-type').value = 'Purchase';
  document.getElementById('sti-remarks').value = '';
  populateSTIProductDropdown();
  document.getElementById('sti-p-product').value = '';
  document.getElementById('sti-p-variety').value = '';
  document.getElementById('sti-p-grade').value = '';
  document.getElementById('sti-p-category').value = '';
  document.getElementById('sti-p-unit').value = '';
  document.getElementById('sti-p-batchno').value = '';
  document.getElementById('sti-p-mfgdate').value = '';
  document.getElementById('sti-p-expdate').value = '';
  document.getElementById('sti-p-qty').value = '';
  document.getElementById('sti-p-rate').value = '';
  document.getElementById('sti-weighingtype').value = 'Own Weighbridge';
  document.getElementById('sti-weighbridgename').value = '';
  document.getElementById('sti-slipno').value = '';
  document.getElementById('sti-weightdatetime').value = '';
  document.getElementById('sti-gross').value = '';
  document.getElementById('sti-tare').value = '';
  document.getElementById('sti-net').value = '';
  document.getElementById('sti-operator').value = '';
  document.getElementById('sti-slip-input').value = '';
  { const b = document.getElementById('sti-reconcile-banner'); if (b) b.style.display = 'none'; }
  document.getElementById('sti-slip-label').innerHTML = '<i class="fas fa-cloud-upload-alt"></i><div style="text-align:left">Drag &amp; drop or click to upload<br><span style="font-size:10px">Supported: JPG, PNG, PDF (Max 5MB)</span></div>';
  populateSTISupplierDropdown();
  document.getElementById('sti-supplier').value = '';
  document.getElementById('sti-challanno').value = '';
  document.getElementById('sti-challandate').value = '';
  document.getElementById('sti-vehicleno').value = '';
  document.getElementById('sti-drivername').value = '';
  document.getElementById('sti-attachments-input').value = '';
  document.getElementById('sti-sum-product').textContent = '—';
  document.getElementById('sti-sum-batch').textContent = '—';
  document.getElementById('sti-sum-warehouse').textContent = 'Main Warehouse';
  document.getElementById('sti-sum-before').textContent = '0.00 Kg';
  document.getElementById('sti-sum-inward').textContent = '0.00 Kg';
  document.getElementById('sti-sum-after').textContent = '0.00 Kg';
  renderSTIItemsTable(); renderSTIAttachments(); renderSTIRecentHistory();
}

function addSTIProduct() {
  const productId = document.getElementById('sti-p-product').value;
  const qty = parseFloat(document.getElementById('sti-p-qty').value) || 0;
  if (!productId) { toast('⚠️ Select a product', 'warning'); return; }
  if (qty <= 0) { toast('⚠️ Enter a quantity greater than 0', 'warning'); return; }
  const p = STATE.products.find(x => String(x.id) === String(productId));
  const rate = parseFloat(document.getElementById('sti-p-rate').value) || 0;
  const item = {
    id: stiItemSeq++, product_id: productId, product_name: p ? p.name : '',
    variety: document.getElementById('sti-p-variety').value,
    grade: document.getElementById('sti-p-grade').value,
    batch_no: document.getElementById('sti-p-batchno').value.trim(),
    mfg_date: document.getElementById('sti-p-mfgdate').value,
    expiry_date: document.getElementById('sti-p-expdate').value,
    qty, rate, amount: +(qty*rate).toFixed(2),
  };
  STI.items.push(item);

  // Update Stock Summary sidebar for this most-recently-added product
  const before = snAvailableStockSafe(productId);
  document.getElementById('sti-sum-product').textContent = item.product_name;
  document.getElementById('sti-sum-batch').textContent = item.batch_no || '—';
  document.getElementById('sti-sum-warehouse').textContent = document.getElementById('sti-warehouse').value;
  document.getElementById('sti-sum-before').textContent = before.toFixed(2) + ' Kg';
  document.getElementById('sti-sum-inward').textContent = qty.toFixed(2) + ' Kg';
  document.getElementById('sti-sum-after').textContent = (before + qty).toFixed(2) + ' Kg';

  // Clear the entry row for the next product
  document.getElementById('sti-p-product').value = '';
  document.getElementById('sti-p-variety').value = '';
  document.getElementById('sti-p-grade').value = '';
  document.getElementById('sti-p-category').value = '';
  document.getElementById('sti-p-unit').value = '';
  document.getElementById('sti-p-batchno').value = '';
  document.getElementById('sti-p-mfgdate').value = '';
  document.getElementById('sti-p-expdate').value = '';
  document.getElementById('sti-p-qty').value = '';
  document.getElementById('sti-p-rate').value = '';
  renderSTIItemsTable();
}

function calcSTIWeight() {
  const gross = parseFloat(document.getElementById('sti-gross').value) || 0;
  const tare = parseFloat(document.getElementById('sti-tare').value) || 0;
  document.getElementById('sti-net').value = Math.max(0, gross-tare).toFixed(2);
  checkSTIReconciliation();
}


function onSTIGradeChange() {
  const grade = document.getElementById('sti-p-grade').value;
  const variety = PP_GRADE_VARIETY_MAP[grade];
  if (variety) document.getElementById('sti-p-variety').value = variety;
}

function onSTIVarietyChange() {
  const variety = document.getElementById('sti-p-variety').value;
  const grade = PP_VARIETY_GRADE_MAP[variety];
  if (grade) document.getElementById('sti-p-grade').value = grade;
}

async function saveStockInEntry(mode) {
  if (!document.getElementById('sti-refdate').value) { toast('⚠️ Reference date is required', 'warning'); return; }
  if (!STI.items.length) { toast('⚠️ Add at least one product', 'warning'); return; }

  const payload = {
    reference_no: document.getElementById('sti-refno').value.trim(),
    reference_date: document.getElementById('sti-refdate').value,
    warehouse: document.getElementById('sti-warehouse').value,
    stock_in_type: document.getElementById('sti-type').value,
    remarks: document.getElementById('sti-remarks').value.trim(),
    weighing_type: document.getElementById('sti-weighingtype').value,
    weighbridge_name: document.getElementById('sti-weighbridgename').value.trim(),
    weighbridge_slip_no: document.getElementById('sti-slipno').value.trim(),
    weight_datetime: document.getElementById('sti-weightdatetime').value || null,
    gross_weight: parseFloat(document.getElementById('sti-gross').value) || 0,
    tare_weight: parseFloat(document.getElementById('sti-tare').value) || 0,
    operator_name: document.getElementById('sti-operator').value.trim(),
    slip: STI.slipDataUrl || undefined,
    supplier_id: document.getElementById('sti-supplier').value || null,
    challan_no: document.getElementById('sti-challanno').value.trim(),
    challan_date: document.getElementById('sti-challandate').value || null,
    vehicle_no: document.getElementById('sti-vehicleno').value.trim(),
    driver_name: document.getElementById('sti-drivername').value.trim(),
    attachments: STI.attachments.map(a => a.url),
    items: STI.items.map(it => ({
      product_id: it.product_id, variety: it.variety, grade: it.grade, batch_no: it.batch_no,
      mfg_date: it.mfg_date || null, expiry_date: it.expiry_date || null, qty: it.qty, rate: it.rate,
    })),
  };

  const btn = event?.target?.closest('button');
  if (btn) btn.disabled = true;
  try {
    if (STI.editingId) {
      await api('/api/stock_in.php?id=' + STI.editingId, 'PUT', payload);
      toast('✅ Stock-in entry updated!', 'success');
    } else {
      await api('/api/stock_in.php', 'POST', payload);
      toast('✅ Stock added!', 'success');
    }
    const stk = await api('/api/stock.php');
    STATE.stock = Array.isArray(stk.data) ? stk.data : STATE.stock;
    if (mode === 'new') {
      // "Save & Add Another" — a fresh page load resets the form cleanly
      window.location.href = '/pages/stock-in-new.php';
    } else {
      setTimeout(() => window.location.href = '/pages/stock.php', 700);
    }
  } catch(e) { toast('❌ ' + e.message, 'error'); }
  finally { if (btn) btn.disabled = false; }
}

async function stiAddAttachments(files) {
  for (const f of Array.from(files)) { const url = await stiFileToDataUrl(f); if (url) STI.attachments.push({ name: f.name, url }); }
  document.getElementById('sti-attachments-input').value = '';
  renderSTIAttachments();
}

function stiSlipChange(file) {
  if (!file) return;
  if (file.size > 5*1024*1024) { toast('⚠️ File must be under 5MB', 'warning'); return; }
  document.getElementById('sti-slip-label').innerHTML = `<i class="fas fa-file-alt" style="color:var(--teal)"></i><div style="text-align:left">${escHtml(file.name)}<br><span style="font-size:10px">${(file.size/1024).toFixed(0)} KB</span></div>`;
  const reader = new FileReader();
  reader.onload = () => { STI.slipDataUrl = reader.result; };
  reader.readAsDataURL(file);
}



function checkSTIReconciliation() {
  const banner = document.getElementById('sti-reconcile-banner');
  if (!banner) return;
  const totalQty = STI.items.reduce((s,i) => s+i.qty, 0);
  const net = parseFloat(document.getElementById('sti-net')?.value) || 0;
  if (totalQty <= 0 || net <= 0) { banner.style.display = 'none'; return; }

  const diff = totalQty - net;
  const pctDiff = (Math.abs(diff) / net) * 100;

  if (pctDiff <= STI_RECONCILE_TOLERANCE_PCT) {
    banner.style.display = 'block';
    banner.style.background = 'var(--green-bg)'; banner.style.color = 'var(--green)';
    banner.innerHTML = `<i class="fas fa-circle-check"></i> Total Quantity (${totalQty.toFixed(2)} Kg) matches Net Weight (${net.toFixed(2)} Kg)`;
  } else {
    const short = diff < 0; // total qty entered is LESS than what the truck weighed
    banner.style.display = 'block';
    banner.style.background = 'var(--amber-bg)'; banner.style.color = '#8A6D00';
    banner.innerHTML = `<i class="fas fa-triangle-exclamation"></i> Total Quantity (${totalQty.toFixed(2)} Kg) is ${short?'less':'more'} than Net Weight (${net.toFixed(2)} Kg) by ${Math.abs(diff).toFixed(2)} Kg (${pctDiff.toFixed(1)}%) — double-check before saving, or this may be expected (e.g. moisture loss).`;
  }
}

function renderSTIAttachments() {
  document.getElementById('sti-attachments-list').innerHTML = STI.attachments.map((a, i) => `
    <div class="pp-attach-row"><span><i class="fas fa-file"></i> ${escHtml(a.name)}</span><span class="pp-attach-actions">${a.url?`<button class="pp-attach-view" onclick="window.open('${a.url}','_blank')" title="View"><i class="fas fa-eye"></i></button>`:''}<button onclick="stiRemoveAttachment(${i})" title="Remove"><i class="fas fa-times"></i></button></span></div>`).join('');
}

function renderSTIItemsTable() {
  const tbody = document.getElementById('sti-items-tbody');
  if (!tbody) return;
  if (!STI.items.length) {
    tbody.innerHTML = `<tr><td colspan="11" style="text-align:center;color:var(--muted);padding:20px">No products added yet</td></tr>`;
  } else {
    tbody.innerHTML = STI.items.map((it, idx) => `
      <tr>
        <td>${idx+1}</td><td style="text-align:left">${escHtml(it.product_name)}</td><td>${escHtml(it.variety||'—')}</td><td>${escHtml(it.grade||'—')}</td>
        <td>${escHtml(it.batch_no||'—')}</td><td>${it.mfg_date?fmt_date_disp(it.mfg_date):'—'}</td><td>${it.expiry_date?fmt_date_disp(it.expiry_date):'—'}</td>
        <td><input type="number" min="0" step="0.01" value="${it.qty.toFixed(2)}" style="width:85px;text-align:right" oninput="updateSTIItem(${it.id},'qty',this.value)"></td>
        <td><input type="number" min="0" step="0.01" value="${it.rate.toFixed(2)}" style="width:80px;text-align:right" oninput="updateSTIItem(${it.id},'rate',this.value)"></td>
        <td class="pne-amount-cell" id="sti-item-amt-${it.id}">${fmt_money(it.amount)}</td>
        <td><button class="item-del" onclick="removeSTIItem(${it.id})" title="Remove"><i class="fas fa-times"></i></button></td>
      </tr>`).join('');
  }
  updateSTIItemsTotals();
}

function stiFileToDataUrl(file) {
  return new Promise(resolve => {
    if (file.size > 5*1024*1024) { toast(`⚠️ "${file.name}" is over 5MB — skipped`, 'warning'); return resolve(null); }
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => resolve(null);
    reader.readAsDataURL(file);
  });
}



function populateSTIProductDropdown() {
  const sel = document.getElementById('sti-p-product');
  if (!sel) return;
  sel.innerHTML = '<option value="">Select product…</option>' +
    STATE.products.map(p => `<option value="${p.id}">${escHtml(p.name)}</option>`).join('');
}
function populateSTISupplierDropdown() {
  const sel = document.getElementById('sti-supplier');
  if (!sel) return;
  sel.innerHTML = '<option value="">Select or —</option>' +
    (STATE.suppliers||[]).map(s => `<option value="${s.id}">${escHtml(s.name)}</option>`).join('');
}
async function renderSTIRecentHistory() {
  const box = document.getElementById('sti-recent-list');
  if (!box) return;
  box.innerHTML = '<div style="font-size:12px;color:var(--muted)">Loading…</div>';
  try {
    const r = await api('/api/stock_in.php?limit=' + STI_HISTORY_LIMIT);
    const rows = Array.isArray(r.data) ? r.data : [];
    if (!rows.length) { box.innerHTML = '<div style="font-size:12px;color:var(--muted)">No stock-in entries yet</div>'; return; }
    box.innerHTML = rows.map(r => `
      <div style="border-bottom:1px solid var(--border);padding-bottom:10px;display:flex;justify-content:space-between;align-items:center;gap:10px">
        <div style="min-width:0">
          <strong style="font-size:12.5px;display:block">${escHtml(r.reference_no)}</strong>
          <div style="font-size:11px;color:var(--muted);margin-top:2px">${escHtml(r.stock_in_type)}</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">${fmt_date_disp(r.reference_date)}</div>
        </div>
        <span style="font-size:11px;font-weight:700;color:#00897B;background:#E8F5E9;padding:4px 10px;border-radius:8px;white-space:nowrap;flex-shrink:0">${parseFloat(r.total_quantity).toFixed(2)} Kg</span>
      </div>`).join('');
  } catch(e) { box.innerHTML = '<div style="font-size:12px;color:var(--muted)">Could not load</div>'; }
}

let STI_HISTORY_LIMIT = 5;

function expandSTIHistory() {
  STI_HISTORY_LIMIT = STI_HISTORY_LIMIT > 5 ? 5 : 20;
  const link = document.getElementById('sti-viewall-link');
  if (link) link.textContent = STI_HISTORY_LIMIT > 5 ? 'Show Less' : 'View All';
  renderSTIRecentHistory();
}
