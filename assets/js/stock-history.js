// ================================================================
//  assets/js/stock-history.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  For pages/stock/stock-history.php.
//
//  MPA CHANGE: old SPA's goToStockHistory(productId, productName)
//  was called from stock.php to jump here with a pre-filled product
//  filter, using showPage() view-switching. Since this is a real
//  navigation now, stock.php's "View History" button just does a
//  normal link to ?product=X&name=Y, and this page reads those
//  params on load instead of receiving function arguments.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['products', 'settings']);
  const stk = await api('api/stock.php').catch(() => ({ data: [] }));
  STATE.stock = Array.isArray(stk.data) ? stk.data : [];
  populateSHProductDropdown();

  const params = new URLSearchParams(window.location.search);
  const productId = params.get('product');

  if (!document.getElementById('sh-f-from').value) {
    document.getElementById('sh-f-from').value = fmt_date(new Date(Date.now() - 7*86400000));
    document.getElementById('sh-f-to').value = fmt_date(new Date());
  }
  if (productId) {
    document.getElementById('sh-f-product').value = String(productId).replace(/\D/g,'');
    onSHProductChange();
  }
  renderStockHistory();
});

function populateSHProductDropdown() {
  const sel = document.getElementById('sh-f-product');
  if (!sel) return;
  const cur = sel.value;
  sel.innerHTML = '<option value="">All Products</option>' + STATE.products.map(p => `<option value="${p.id}">${escHtml(p.name)}</option>`).join('');
  if (cur) sel.value = cur;
}

function onSHProductChange() {
  const pid = document.getElementById('sh-f-product').value;
  const batchSel = document.getElementById('sh-f-batch');
  const batches = (STATE.stock||[]).filter(s => String(s.product_id) === String(pid).replace(/\D/g,'') && s.batch_no && s.batch_no !== '—').map(s => s.batch_no);
  const unique = [...new Set(batches)];
  batchSel.innerHTML = '<option value="">Select Batch / Lot</option>' + unique.map(b => `<option value="${escHtml(b)}">${escHtml(b)}</option>`).join('');
}

function resetSHFilter() {
  document.getElementById('sh-f-product').value = '';
  document.getElementById('sh-f-batch').innerHTML = '<option value="">Select Batch / Lot</option>';
  document.getElementById('sh-f-warehouse').value = '';
  document.getElementById('sh-f-from').value = fmt_date(new Date(Date.now() - 7*86400000));
  document.getElementById('sh-f-to').value = fmt_date(new Date());
  document.getElementById('sh-f-txntype').value = '';
  document.getElementById('sh-f-reftype').value = '';
  renderStockHistory();
}

async function renderStockHistory() {
  populateSHProductDropdown();
  if (!document.getElementById('sh-f-from').value) {
    document.getElementById('sh-f-from').value = fmt_date(new Date(Date.now() - 7*86400000));
    document.getElementById('sh-f-to').value = fmt_date(new Date());
  }
  try {
    const params = new URLSearchParams({
      date_from: document.getElementById('sh-f-from').value,
      date_to: document.getElementById('sh-f-to').value,
    });
    const pid = document.getElementById('sh-f-product').value; if (pid) params.set('product_id', pid);
    const batch = document.getElementById('sh-f-batch').value; if (batch) params.set('batch_no', batch);
    const wh = document.getElementById('sh-f-warehouse').value; if (wh) params.set('warehouse', wh);
    const txn = document.getElementById('sh-f-txntype').value; if (txn) params.set('transaction_type', txn);
    const ref = document.getElementById('sh-f-reftype').value; if (ref) params.set('reference_type', ref);

    const r = await api('api/stock_history.php?' + params.toString());
    SH_LAST_ROWS = Array.isArray(r.data) ? r.data : [];
    const stats = r.stats || {};

    document.getElementById('sh-stat-opening').textContent = (stats.opening_stock||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' Kg';
    document.getElementById('sh-stat-opening-date').textContent = 'as on ' + fmt_date_disp(document.getElementById('sh-f-from').value);
    document.getElementById('sh-stat-in').textContent = (stats.total_in||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' Kg';
    document.getElementById('sh-stat-out').textContent = (stats.total_out||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' Kg';
    document.getElementById('sh-stat-closing').textContent = (stats.closing_stock||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' Kg';
    document.getElementById('sh-stat-value').textContent = fmt_money(stats.current_stock_value||0);

    renderSHTable();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function renderSHTable() {
  const tbody = document.getElementById('sh-history-tbody');
  const rows = SH_LAST_ROWS;
  document.getElementById('sh-history-info').textContent = `Showing 1 to ${rows.length} of ${rows.length} entries`;
  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="12" style="text-align:center;color:var(--muted);padding:30px">No stock movement in this period</td></tr>`;
    return;
  }
  const typeLabel = { purchase: 'Stock In', stock_in: 'Stock In', sale: 'Stock Out', adjustment: 'Stock Adjustment' };
  const typeColor = { purchase: '#00897B', stock_in: '#00897B', sale: '#E53935', adjustment: '#E65100' };
  const refLabel  = { purchase: 'Purchase Entry', stock_in: 'Stock In Entry', sale: 'Sales Invoice', adjustment: 'Stock Adjustment' };
  // reverse chronological for display (most recent first), matching the rest of the app
  const display = [...rows].reverse();
  tbody.innerHTML = display.map((row, idx) => `
    <tr>
      <td>${idx+1}</td>
      <td>${fmt_date_disp(row.movement_date)}</td>
      <td><span style="font-size:10.5px;font-weight:700;color:${typeColor[row.ref_type]||'#889'};background:${typeColor[row.ref_type]||'#889'}18;padding:2px 8px;border-radius:10px">${typeLabel[row.ref_type]||row.ref_type}</span></td>
      <td>${refLabel[row.ref_type]||row.ref_type}</td>
      <td>${escHtml(row.reference_no||'—')}</td>
      <td>${escHtml(row.batch_no||'—')}</td>
      <td>${escHtml(row.warehouse||'Main Warehouse')}</td>
      <td style="color:#00897B;font-weight:600">${row.direction==='in'?parseFloat(row.qty).toFixed(2):'—'}</td>
      <td style="color:#E53935;font-weight:600">${row.direction==='out'?parseFloat(row.qty).toFixed(2):'—'}</td>
      <td><strong>${parseFloat(row.running_balance).toFixed(2)}</strong></td>
      <td style="color:var(--muted);font-size:11px">${escHtml((row.notes||'').replace(/^(Purchase|Sale|Stock In)\s*/,'').trim() || row.notes || '—')}</td>
      <td>${row.ref_type==='adjustment' ? `<button class="act-btn" title="Delete adjustment" onclick="deleteStockAdjustment(${row.id}, '${row.product_id}', '${escHtml(row.product_name||'').replace(/'/g,"\\'")}')"><i class="fas fa-trash"></i></button>` : `<button class="act-btn" title="View" onclick="toast('ℹ️ ${escHtml((refLabel[row.ref_type]||'').replace(/'/g,"\\'"))}: ${escHtml((row.reference_no||'—').replace(/'/g,"\\'"))}','info')"><i class="fas fa-eye"></i></button>`}</td>
    </tr>`).join('');
}

function exportStockHistoryCsv() {
  if (!SH_LAST_ROWS.length) { toast('⚠️ Nothing to export', 'warning'); return; }
  const headers = ['Date','Transaction Type','Reference Type','Reference No.','Batch/Lot No.','Warehouse','In (Kg)','Out (Kg)','Balance (Kg)','Remarks'];
  const typeLabel = { purchase: 'Stock In', stock_in: 'Stock In', sale: 'Stock Out', adjustment: 'Stock Adjustment' };
  const refLabel  = { purchase: 'Purchase Entry', stock_in: 'Stock In Entry', sale: 'Sales Invoice', adjustment: 'Stock Adjustment' };
  const csvRows = SH_LAST_ROWS.map(r => [
    r.movement_date, typeLabel[r.ref_type]||r.ref_type, refLabel[r.ref_type]||r.ref_type, r.reference_no||'',
    r.batch_no||'', r.warehouse||'', r.direction==='in'?r.qty:'', r.direction==='out'?r.qty:'', r.running_balance, r.notes||'',
  ].map(v => `"${String(v).replace(/"/g,'""')}"`).join(','));
  const csv = [headers.join(','), ...csvRows].join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'stock-history-' + fmt_date(new Date()) + '.csv';
  a.click();
}


async function deleteStockAdjustment(id, productId, productName) {
  const conf = await Swal.fire({
    title: 'Delete this adjustment?', text: 'This manual stock entry will be removed.',
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#E53935',
    customClass: { popup: 'swal-compact' }
  });
  if (!conf.isConfirmed) return;
  try {
    await api('api/stock.php?id=' + id, 'DELETE');
    toast('🗑️ Adjustment deleted', 'info');
    renderStockHistory();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}
