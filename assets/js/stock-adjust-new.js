// ================================================================
//  assets/js/stock-adjust-new.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  For pages/stock/stock-adjust-new.php.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['products', 'suppliers', 'team', 'settings']);
  const stk = await api('api/stock.php').catch(() => ({ data: [] }));
  STATE.stock = Array.isArray(stk.data) ? stk.data : [];
  goToNewStockAdjustment();
});

// ══════════════════════════════════════════
// STOCK ADJUSTMENT / MOISTURE ADJUSTMENT (full page)
// ══════════════════════════════════════════
const SA = { attachmentDataUrl: null };

function populateSAProductDropdown() {
  const sel = document.getElementById('sa-product');
  if (!sel) return;
  sel.innerHTML = '<option value="">Select product…</option>' +
    STATE.products.map(p => `<option value="${p.id}">${escHtml(p.name)}</option>`).join('');
}
function populateSASupplierDropdown() {
  const sel = document.getElementById('sa-supplier');
  if (!sel) return;
  sel.innerHTML = '<option value="">Select or —</option>' +
    (STATE.suppliers||[]).map(s => `<option value="${s.id}">${escHtml(s.name)}</option>`).join('');
}
async function populateSAApprovedByDropdown() {
  const sel = document.getElementById('sa-approvedby');
  if (!sel) return;
  try {
    if (!STATE.team || !STATE.team.length) {
      const r = await api('api/team.php?action=list');
      STATE.team = Array.isArray(r.data) ? r.data : [];
    }
    sel.innerHTML = '<option value="">Select…</option>' +
      STATE.team.map(u => `<option value="${escHtml(u.name)}">${escHtml(u.name)}</option>`).join('');
  } catch(e) { sel.innerHTML = '<option value="">Select…</option>'; }
}

function goToNewStockAdjustment() {
  SA.attachmentDataUrl = null;
  document.getElementById('sa-no').value = '';
  document.getElementById('sa-date').value = fmt_date(new Date());
  document.getElementById('sa-type').value = 'Moisture Loss';
  document.getElementById('sa-direction').value = 'out';
  onSADirectionChange();
  document.getElementById('sa-warehouse').value = 'Main Warehouse';
  document.getElementById('sa-refno').value = '';
  document.getElementById('sa-refdate').value = '';
  populateSAProductDropdown();
  document.getElementById('sa-product').value = '';
  document.getElementById('sa-variety').value = '';
  document.getElementById('sa-grade').value = '';
  document.getElementById('sa-unit').value = 'Kg';
  document.getElementById('sa-batchno').value = '';
  document.getElementById('sa-mfgdate').value = '';
  document.getElementById('sa-expdate').value = '';
  populateSASupplierDropdown();
  document.getElementById('sa-supplier').value = '';
  document.getElementById('sa-openingstock').value = '';
  document.getElementById('sa-moistbefore').value = '';
  document.getElementById('sa-moistafter').value = '';
  document.getElementById('sa-moistloss').value = '';
  document.getElementById('sa-weightloss').value = '';
  document.getElementById('sa-finalstock').value = '';
  document.getElementById('sa-reason').value = 'Drying / Moisture Loss';
  document.getElementById('sa-remarks').value = '';
  document.getElementById('sa-attachment-input').value = '';
  document.getElementById('sa-attachment-label').innerHTML = '<i class="fas fa-cloud-upload-alt"></i><div>Drag &amp; drop files here<br><span style="font-size:10px">Supported: PDF, JPG, PNG (Max 5MB)</span></div>';
  populateSAApprovedByDropdown();
  document.getElementById('sa-approvaldate').value = fmt_date(new Date());
  document.getElementById('sa-notes').value = '';
  document.getElementById('sa-imp-warehouse').textContent = 'Main Warehouse';
  document.getElementById('sa-imp-product').textContent = '—';
  document.getElementById('sa-imp-batch').textContent = '—';
  calcStockAdjustment();
  renderSARecentAdjustments();
}

function cancelStockAdjustment() {
  window.location.href = '/pages/stock/stock.php';
}

function onSAProductChange() {
  const id = document.getElementById('sa-product').value;
  const p = STATE.products.find(x => String(x.id) === String(id));
  document.getElementById('sa-imp-product').textContent = p ? p.name : '—';
  if (p) {
    document.getElementById('sa-unit').value = (p.unit_family === 'volume') ? 'Ltr' : (p.unit_family === 'count' ? 'Pcs' : 'Kg');
    if (p.variety) document.getElementById('sa-variety').value = p.variety;
    if (p.grade) document.getElementById('sa-grade').value = p.grade;
    // Pull current stock as the default Opening Stock
    const s = (STATE.stock||[]).find(x => String(x.product_id) === String(id).replace(/\D/g,''));
    document.getElementById('sa-openingstock').value = s ? (parseFloat(s.current_stock ?? s.available_stock) || 0).toFixed(2) : '0.00';
  }
  calcStockAdjustment();
}

// Direction switch: relabels the quantity field/summary and recalculates.
// "Increase" is for recounts that find MORE stock than the system shows,
// stock returned after processing, etc.
function onSADirectionChange() {
  const dir = document.getElementById('sa-direction').value;
  const isIn     = dir === 'in';
  const isAdjust = dir === 'adjust';
  document.getElementById('sa-qty-label').textContent = isIn ? 'Weight Gain (Kg) *' : 'Weight Loss (Kg) *';
  document.getElementById('sa-sum-loss-label').textContent = isIn ? 'Weight Gain (Kg)' : isAdjust ? 'Adjustment (Kg)' : 'Weight Loss (Kg)';
  document.getElementById('sa-sum-op').textContent = isIn ? '+' : isAdjust ? '±' : '−';
  // Show/hide weight loss vs adjust-to fields
  const wlRow  = document.getElementById('sa-weightloss')?.parentElement;
  const adjRow = document.getElementById('sa-adjustto-row');
  if (wlRow)  wlRow.style.display  = isAdjust ? 'none' : '';
  if (adjRow) adjRow.style.display = isAdjust ? '' : 'none';
  // Auto-fill Opening Stock Correction type when adjust selected
  if (isAdjust) {
    const typeEl = document.getElementById('sa-type');
    if (typeEl) typeEl.value = 'Opening Stock Correction';
  }
  calcStockAdjustment();
}

function calcStockAdjustment() {
  const dir      = document.getElementById('sa-direction')?.value || 'out';
  const isIn     = dir === 'in';
  const isAdjust = dir === 'adjust';
  const opening  = parseFloat(document.getElementById('sa-openingstock').value) || 0;
  const before   = document.getElementById('sa-moistbefore').value;
  const after    = document.getElementById('sa-moistafter').value;
  const moistLoss = (before !== '' && after !== '') ? (parseFloat(before) - parseFloat(after)) : null;
  document.getElementById('sa-moistloss').value = moistLoss !== null ? moistLoss.toFixed(2) : '';

  let weightLoss, finalStock;
  if (isAdjust) {
    // User enters target stock — we compute the required delta
    const target = parseFloat(document.getElementById('sa-adjustto').value);
    if (!isNaN(target)) {
      const diff = target - opening;
      weightLoss = Math.abs(diff);
      finalStock = target;
      // Auto-set actual direction based on whether we need to add or remove
      // (stored separately so the API knows which ledger direction to use)
      document.getElementById('sa-weightloss').value = weightLoss.toFixed(2);
    } else {
      weightLoss = 0;
      finalStock = opening;
    }
  } else {
    weightLoss = parseFloat(document.getElementById('sa-weightloss').value) || 0;
    finalStock = isIn ? opening + weightLoss : Math.max(0, opening - weightLoss);
  }

  document.getElementById('sa-finalstock').value = finalStock.toFixed(2);
  document.getElementById('sa-sum-opening').textContent = opening.toFixed(2);
  document.getElementById('sa-sum-loss').textContent = weightLoss.toFixed(2);
  document.getElementById('sa-sum-final').textContent = finalStock.toFixed(2);
  document.getElementById('sa-sum-mbefore').textContent = (parseFloat(before)||0).toFixed(2) + ' %';
  document.getElementById('sa-sum-mafter').textContent = (parseFloat(after)||0).toFixed(2) + ' %';
  document.getElementById('sa-sum-mloss').textContent = (moistLoss !== null ? moistLoss : 0).toFixed(2) + ' %';
  document.getElementById('sa-imp-warehouse').textContent = document.getElementById('sa-warehouse').value;
  document.getElementById('sa-imp-batch').textContent = document.getElementById('sa-batchno').value || '—';
}

function saAttachmentChange(file) {
  if (!file) return;
  if (file.size > 5*1024*1024) { toast('⚠️ Attachment must be under 5MB', 'warning'); return; }
  document.getElementById('sa-attachment-label').innerHTML = `<i class="fas fa-file-alt" style="color:var(--teal)"></i><div>${escHtml(file.name)}<br><span style="font-size:10px">${(file.size/1024).toFixed(0)} KB</span></div>`;
  const reader = new FileReader();
  reader.onload = () => { SA.attachmentDataUrl = reader.result; };
  reader.readAsDataURL(file);
}

async function saveStockAdjustmentEntry() {
  const productId = document.getElementById('sa-product').value;
  const dir = document.getElementById('sa-direction').value;
  const isAdjust = dir === 'adjust';
  if (!productId) { toast('⚠️ Select a product', 'warning'); return; }
  if (!document.getElementById('sa-date').value) { toast('⚠️ Adjustment date is required', 'warning'); return; }
  if (!document.getElementById('sa-openingstock').value) { toast('⚠️ Opening Stock is required', 'warning'); return; }
  if (isAdjust && document.getElementById('sa-adjustto').value === '') { toast('⚠️ Target stock value is required', 'warning'); return; }
  if (!isAdjust && !document.getElementById('sa-weightloss').value) { toast('⚠️ Weight Loss/Gain is required', 'warning'); return; }
  if (!document.getElementById('sa-reason').value) { toast('⚠️ Reason / Description is required', 'warning'); return; }

  const opening    = parseFloat(document.getElementById('sa-openingstock').value) || 0;
  const target     = isAdjust ? parseFloat(document.getElementById('sa-adjustto').value) : null;
  const diff       = isAdjust ? (target - opening) : null;
  // For adjust mode: determine actual in/out direction from the diff
  const actualDir  = isAdjust ? (diff >= 0 ? 'in' : 'out') : dir;
  const weightLoss = isAdjust ? Math.abs(diff) : (parseFloat(document.getElementById('sa-weightloss').value) || 0);

  const payload = {
    adjustment_no: document.getElementById('sa-no').value.trim(),
    adjustment_date: document.getElementById('sa-date').value,
    adjustment_type: document.getElementById('sa-type').value,
    direction: actualDir,
    is_exact_correction: isAdjust ? 1 : 0,
    target_stock: target,
    warehouse: document.getElementById('sa-warehouse').value,
    reference_no: document.getElementById('sa-refno').value.trim(),
    reference_date: document.getElementById('sa-refdate').value || null,
    product_id: productId,
    variety_grade: document.getElementById('sa-variety').value.trim(),
    grade: document.getElementById('sa-grade').value.trim(),
    unit: document.getElementById('sa-unit').value,
    batch_no: document.getElementById('sa-batchno').value.trim(),
    manufacture_date: document.getElementById('sa-mfgdate').value || null,
    expiry_date: document.getElementById('sa-expdate').value || null,
    supplier_id: document.getElementById('sa-supplier').value || null,
    opening_stock: opening,
    moisture_before_pct: document.getElementById('sa-moistbefore').value || '',
    moisture_after_pct: document.getElementById('sa-moistafter').value || '',
    weight_loss_kg: weightLoss,
    reason: document.getElementById('sa-reason').value,
    remarks: document.getElementById('sa-remarks').value.trim(),
    attachment: SA.attachmentDataUrl || undefined,
    approved_by: document.getElementById('sa-approvedby').value,
    approval_date: document.getElementById('sa-approvaldate').value || null,
    notes: document.getElementById('sa-notes').value.trim(),
  };

  const btn = event?.target?.closest('button');
  if (btn) btn.disabled = true;
  try {
    await api('api/stock_adjustments.php', 'POST', payload);
    toast('✅ Stock adjustment saved!', 'success');
    const stk = await api('api/stock.php');
    STATE.stock = Array.isArray(stk.data) ? stk.data : STATE.stock;
    cancelStockAdjustment();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
  finally { if (btn) btn.disabled = false; }
}

async function renderSARecentAdjustments() {
  const box = document.getElementById('sa-recent-list');
  if (!box) return;
  box.innerHTML = '<div style="font-size:12px;color:var(--muted)">Loading…</div>';
  try {
    const r = await api('api/stock_adjustments.php?limit=5');
    const rows = Array.isArray(r.data) ? r.data : [];
    if (!rows.length) { box.innerHTML = '<div style="font-size:12px;color:var(--muted)">No adjustments yet</div>'; return; }
    const typeColor = { 'Moisture Loss':'#E65100', 'Damage Loss':'#C62828', 'Cleaning Loss':'#1976D2', 'Recount':'#6A4C93', 'Other':'#455A64' };
    box.innerHTML = rows.map(r => `
      <div>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <strong style="font-size:12.5px">${escHtml(r.adjustment_no)}</strong>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:3px">
          <span style="font-size:11px;color:var(--muted)">${escHtml(r.adjustment_type)}</span>
          <span style="font-size:10.5px;font-weight:700;color:${typeColor[r.adjustment_type]||'#455A64'};background:${typeColor[r.adjustment_type]||'#455A64'}18;padding:2px 8px;border-radius:10px">${parseFloat(r.weight_loss_kg).toFixed(2)} Kg</span>
        </div>
        <div style="font-size:10px;color:var(--muted);margin-top:2px">${fmt_date_disp(r.adjustment_date)}</div>
      </div>`).join('');
  } catch(e) { box.innerHTML = '<div style="font-size:12px;color:var(--muted)">Could not load</div>'; }
}
