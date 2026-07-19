// ============================================================
// stock-history.js — page-specific JS for pages/stock-history.php
// Depends on: common.js, app.js, stock-shared.js
//
// NOTE: viewStockTxnDetails() is gone from here — in the SPA it read the
// clicked row from an in-memory SH_LAST_ROWS cache. In the MPA, the "view"
// button now just navigates to stock-txn-details.php?ledger_id=X&product_id=Y,
// which fetches its own data fresh (see stock-txn-details.js).
// ============================================================

let SH_LAST_ROWS = [];

document.addEventListener('DOMContentLoaded', async () => {
  await bootStockPageState();
  await initStockHistoryPage();
});

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

function onSHProductChange() {
  const pid = document.getElementById('sh-f-product').value;
  const batchSel = document.getElementById('sh-f-batch');
  const batches = (STATE.stock||[]).filter(s => String(s.product_id) === String(pid).replace(/\D/g,'') && s.batch_no && s.batch_no !== '—').map(s => s.batch_no);
  const unique = [...new Set(batches)];
  batchSel.innerHTML = '<option value="">Select Batch / Lot</option>' + unique.map(b => `<option value="${escHtml(b)}">${escHtml(b)}</option>`).join('');
}

async function renderStockHistory() {
  populateSHProductDropdown();
  if (!document.getElementById('sh-f-from').value) {
    document.getElementById('sh-f-from').value = BIZ_FROM_DATE;
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

    const r = await api('/api/stock_history.php?' + params.toString());
    SH_LAST_ROWS = Array.isArray(r.data) ? r.data : [];
    SH_PAGE = 1;
    const stats = r.stats || {};

    const openingVal = stats.opening_stock || 0;
    const openingEl = document.getElementById('sh-stat-opening');
    openingEl.textContent = openingVal.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' Kg';
    openingEl.style.color = openingVal < 0 ? '#E53935' : '';
    document.getElementById('sh-stat-opening-date').innerHTML = 'as on ' + fmt_date_disp(document.getElementById('sh-f-from').value) + (pid ? '' : ' (sum across all products)')
      + (openingVal < 0 ? '<br><span style="color:#E53935"><i class="fas fa-triangle-exclamation"></i> Negative — click "All Time" to find the cause</span>' : '');
    document.getElementById('sh-stat-in').textContent = (stats.total_in||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' Kg';
    document.getElementById('sh-stat-out').textContent = (stats.total_out||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' Kg';
    document.getElementById('sh-stat-closing').textContent = (stats.closing_stock||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' Kg';
    document.getElementById('sh-stat-value').textContent = fmt_money(stats.current_stock_value||0);

    // ── Total Adjustments ────────────────────────────────────────
    const adjRows = SH_LAST_ROWS.filter(r => r.ref_type === 'adjustment');
    const adjNetKg = adjRows.reduce((s, r) => s + (r.direction==='in' ? parseFloat(r.qty||0) : -parseFloat(r.qty||0)), 0);
    document.getElementById('sh-stat-adj-count').textContent = adjRows.length;
    document.getElementById('sh-stat-adj-sub').textContent = (adjNetKg >= 0 ? '+' : '') + adjNetKg.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' Kg net';
    document.getElementById('sh-stat-adj-sub').style.color = adjRows.length > 5 ? '#E65100' : 'var(--muted)';

    // ── Avg Purchase Rate ────────────────────────────────────────
    const purRows = SH_LAST_ROWS.filter(r => r.ref_type === 'purchase' && r.direction === 'in');
    const purTotalKg  = purRows.reduce((s, r) => s + parseFloat(r.qty||0), 0);
    const purTotalVal = purRows.reduce((s, r) => s + parseFloat(r.qty||0) * parseFloat(r.rate||0), 0);
    const avgRate = purTotalKg > 0 ? purTotalVal / purTotalKg : 0;
    document.getElementById('sh-stat-avg-rate').textContent = avgRate > 0 ? fmt_money(avgRate) : '—';

    renderSHTable();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function resetSHFilter() {
  document.getElementById('sh-f-product').value = '';
  document.getElementById('sh-f-batch').innerHTML = '<option value="">Select Batch / Lot</option>';
  document.getElementById('sh-f-warehouse').value = '';
  document.getElementById('sh-f-from').value = BIZ_FROM_DATE;
  document.getElementById('sh-f-to').value = fmt_date(new Date());
  document.getElementById('sh-f-txntype').value = '';
  document.getElementById('sh-f-reftype').value = '';
  renderStockHistory();
}

function setSHAllTime() {
  document.getElementById('sh-f-from').value = '2000-01-01';
  document.getElementById('sh-f-to').value = fmt_date(new Date());
  renderStockHistory();
}

function populateSHProductDropdown() {
  const sel = document.getElementById('sh-f-product');
  if (!sel) return;
  const cur = sel.value;
  sel.innerHTML = '<option value="">All Products</option>' + STATE.products.map(p => `<option value="${p.id}">${escHtml(p.name)}</option>`).join('');
  if (cur) sel.value = cur;
}

function renderSHTable() {
  const tbody = document.getElementById('sh-history-tbody');
  const rows = SH_LAST_ROWS;
  if (!rows.length) {
    document.getElementById('sh-history-info').textContent = 'No entries';
    const pg = document.getElementById('sh-pagination'); if (pg) pg.innerHTML = '';
    tbody.innerHTML = `<tr><td colspan="12" style="text-align:center;color:var(--muted);padding:30px">No stock movement in this period</td></tr>`;
    return;
  }
  const typeLabel = { purchase: 'Stock In', stock_in: 'Stock In', sale: 'Stock Out', adjustment: 'Stock Adjustment' };
  const typeColor = { purchase: '#00897B', stock_in: '#00897B', sale: '#E53935', adjustment: '#E65100' };
  const refLabel  = { purchase: 'Purchase Entry', stock_in: 'Stock In Entry', sale: 'Sales Invoice', adjustment: 'Stock Adjustment' };
  // Fallback for any row with a missing ref_type (older data written before
  // this was consistently set) — infer it from the notes text instead of
  // just showing a blank cell.
  function shResolveRefType(row) {
    if (row.ref_type) return row.ref_type;
    const n = (row.notes || '').toLowerCase();
    if (n.startsWith('stock in')) return 'stock_in';
    if (n.startsWith('purchase')) return 'purchase';
    if (n.startsWith('sale')) return 'sale';
    return row.ref_type;
  }
  // reverse chronological for display (most recent first), matching the rest of the app
  const display = [...rows].reverse();

  // ── Pagination (20 per page) ───────────────────────────────
  const totalPages = Math.max(1, Math.ceil(display.length / SH_PAGESIZE));
  if (SH_PAGE > totalPages) SH_PAGE = totalPages;
  if (SH_PAGE < 1) SH_PAGE = 1;
  const start = (SH_PAGE - 1) * SH_PAGESIZE;
  const pageRows = display.slice(start, start + SH_PAGESIZE);
  document.getElementById('sh-history-info').textContent =
    `Showing ${start+1} to ${Math.min(start+SH_PAGESIZE, display.length)} of ${display.length} entries`;
  const pager = document.getElementById('sh-pagination');
  if (pager) {
    let h = `<button class="pg-btn" onclick="shPage(${SH_PAGE-1})" ${SH_PAGE<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= totalPages; i++) {
      if (totalPages > 8 && i > 3 && i < totalPages - 1 && Math.abs(i - SH_PAGE) > 1) {
        if (i === 4) h += `<span style="padding:0 4px;color:var(--muted)">…</span>`;
        continue;
      }
      h += `<button class="pg-btn ${i===SH_PAGE?'active':''}" onclick="shPage(${i})">${i}</button>`;
    }
    h += `<button class="pg-btn" onclick="shPage(${SH_PAGE+1})" ${SH_PAGE>=totalPages?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
    pager.innerHTML = h;
  }

  tbody.innerHTML = pageRows.map((row, idx) => {
    const refType = shResolveRefType(row);
    return `
    <tr>
      <td>${start + idx + 1}</td>
      <td>${fmt_date_disp(row.movement_date)}</td>
      <td><span style="font-size:10.5px;font-weight:700;color:${typeColor[refType]||'#889'};background:${typeColor[refType]||'#889'}18;padding:2px 8px;border-radius:10px">${typeLabel[refType]||'Unknown'}</span></td>
      <td>${refLabel[refType]||'Unknown'}</td>
      <td>${escHtml(row.reference_no||'—')}</td>
      <td>${escHtml(row.batch_no||'—')}</td>
      <td>${escHtml(row.warehouse||'Main Warehouse')}</td>
      <td style="color:#00897B;font-weight:600">${row.direction==='in'?parseFloat(row.qty).toFixed(2):'—'}</td>
      <td style="color:#E53935;font-weight:600">${row.direction==='out'?parseFloat(row.qty).toFixed(2):'—'}</td>
      <td><strong>${parseFloat(row.running_balance).toFixed(2)}</strong></td>
      <td style="color:var(--muted);font-size:11px">${escHtml((row.notes||'').replace(/^(Purchase|Sale|Stock In)\s*/,'').trim() || row.notes || '—')}</td>
      <td>${renderSHActionCell({...row, ref_type: refType}, refLabel)}</td>
    </tr>`;
  }).join('');
}

async function initStockHistoryPage() {
  populateSHProductDropdown();
  document.getElementById('sh-f-from').value = BIZ_FROM_DATE;
  document.getElementById('sh-f-to').value = fmt_date(new Date());
  // Arriving from another page's "Stock History" button, e.g. products.php
  // or dashboard.php, passes ?product_id=123 to pre-filter this page.
  const urlProductId = new URLSearchParams(window.location.search).get('product_id');
  if (urlProductId) {
    document.getElementById('sh-f-product').value = urlProductId.replace(/\D/g,'');
    onSHProductChange();
  }
  renderStockHistory();
}

function renderSHActionCell(row, refLabel) {
  // Viewing (eye) is the primary action for every ledger row — corrections
  // are handled through Stock Adjustment, not by editing history directly.
  const eye = `<button class="act-btn" title="View transaction details" onclick="window.location.href='/pages/stock-txn-details.php?ledger_id=${row.id}&product_id=${row.product_id}'"><i class="fas fa-eye"></i></button>`;
  if (row.ref_type === 'adjustment') {
    return eye + `<button class="act-btn" title="Delete adjustment" onclick="deleteStockAdjustment(${row.id}, '${row.product_id}', '${escHtml(row.product_name||'').replace(/'/g,"\\'")}')"><i class="fas fa-trash"></i></button>`;
  }
  return eye;
}

async function deleteStockAdjustment(id, productId, productName) {
  const conf = await Swal.fire({
    title: 'Delete this adjustment?', text: 'This manual stock entry will be removed.',
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#E53935',
    customClass: { popup: 'swal-compact' }
  });
  if (!conf.isConfirmed) return;
  try {
    await api('/api/stock.php?id=' + id, 'DELETE');
    toast('\ud83d\uddd1\ufe0f Adjustment deleted', 'info');
    renderStockHistory();
  } catch(e) { toast('\u274c ' + e.message, 'error'); }
}
