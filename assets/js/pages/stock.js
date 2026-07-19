// ============================================================
// stock.js — page-specific JS for pages/stock.php
// Depends on: common.js, app.js (api/toast/escHtml/fmt_*), stock-shared.js
// ============================================================

function exportProductStockCsv() {
  const list = PS.tab === 'summary' ? psGroupedForSummaryTab() : PS.rows;
  if (!list.length) { toast('⚠️ Nothing to export', 'warning'); return; }
  const headers = ['Product','Variety/Grade','Warehouse','Batch/Lot No.','Available Stock (Kg)','Reserved (Kg)','In Transit (Kg)','Total Stock (Kg)','Avg Cost (₹/Kg)','Stock Value (₹)','Last Inward Date'];
  const csvRows = list.map(r => [r.name, r.variety||'', r.warehouse||'', r.batch_no||'', r.available_stock, r.reserved_stock, r.in_transit, r.total_stock, r.avg_cost, r.stock_value, r.last_inward_date||''].map(v => `"${String(v).replace(/"/g,'""')}"`).join(','));
  const csv = [headers.join(','), ...csvRows].join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'product-stock-' + fmt_date(new Date()) + '.csv';
  a.click();
}



async function renderProductStock() {
  populatePSProductFilter();
  document.getElementById('ps-asof').textContent = new Date().toLocaleString('en-IN', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
  try {
    const params = new URLSearchParams();
    const pid = document.getElementById('ps-f-product')?.value; if (pid) params.set('product_id', pid);
    const wh  = document.getElementById('ps-f-warehouse')?.value; if (wh) params.set('warehouse', wh);
    const batch = document.getElementById('ps-f-batch')?.value; if (batch) params.set('batch_no', batch);
    const r = await api('/api/product_stock.php?' + params.toString());
    PS.rows = Array.isArray(r.data) ? r.data : [];
    STATE.stock = PS.rows; // keep legacy consumers (Sale Entry's Available Stock lookup) working
    const s = r.stats || {};
    document.getElementById('ps-stat-products').textContent = s.total_products || 0;
    document.getElementById('ps-stat-stock').textContent = (s.total_stock||0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('ps-stat-value').textContent = fmt_money(s.total_value||0);
    document.getElementById('ps-stat-instock').textContent = s.in_stock || 0;
    document.getElementById('ps-stat-lowstock').textContent = s.low_stock || 0;
    PS.page = 1;
    renderPSTable();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
  renderPSMovementAndTrend();
}



function switchProductStockTab(tab) {
  PS.tab = tab; PS.page = 1;
  document.getElementById('ps-tab-summary').classList.toggle('active', tab === 'summary');
  document.getElementById('ps-tab-batch').classList.toggle('active', tab === 'batch');
  renderPSTable();
}



function populatePSProductFilter() {
  const sel = document.getElementById('ps-f-product');
  if (!sel) return;
  const cur = sel.value;
  sel.innerHTML = '<option value="">All Products</option>' + STATE.products.map(p => `<option value="${p.id}">${escHtml(p.name)}</option>`).join('');
  if (cur) sel.value = cur;
}











function psGroupedForSummaryTab() {
  // Stock Summary tab: same data, collapsed across batches (summed per product+warehouse)
  const map = {};
  PS.rows.forEach(r => {
    const key = r.product_id + '|' + r.warehouse;
    if (!map[key]) map[key] = { ...r, batch_no: '—', available_stock: 0, reserved_stock: 0, in_transit: 0, total_stock: 0, stock_value: 0, _costSum: 0, _qtySum: 0 };
    const g = map[key];
    g.available_stock += parseFloat(r.available_stock)||0;
    g.reserved_stock  += parseFloat(r.reserved_stock)||0;
    g.in_transit      += parseFloat(r.in_transit)||0;
    g.total_stock     += parseFloat(r.total_stock)||0;
    g.stock_value     += parseFloat(r.stock_value)||0;
    if (r.last_inward_date && (!g.last_inward_date || r.last_inward_date > g.last_inward_date)) g.last_inward_date = r.last_inward_date;
  });
  return Object.values(map).map(g => ({ ...g, avg_cost: g.available_stock > 0 ? g.stock_value / g.available_stock : 0 }));
}

async function renderPSMovementAndTrend() {
  try {
    const r = await api('/api/product_stock.php?movement_summary=1');
    const rows = Array.isArray(r.data) ? r.data : [];
    document.getElementById('ps-movement-tbody').innerHTML = rows.map(m => `
      <tr>
        <td>${fmt_date_disp(m.date)}</td>
        <td>${m.opening_stock.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
        <td style="color:#00897B">${m.stock_in.toFixed(2)}</td>
        <td style="color:#E53935">${m.stock_out.toFixed(2)}</td>
        <td>${m.adjustment > 0 ? '-'+m.adjustment.toFixed(2) : '-'}</td>
        <td><strong>${m.closing_stock.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}</strong></td>
      </tr>`).join('');

    const ctx = document.getElementById('ps-trend-chart');
    if (ctx && window.Chart) {
      if (psTrendChart) psTrendChart.destroy();
      psTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: rows.map(m => fmt_date_disp(m.date).slice(0,5)),
          datasets: [{
            label: 'Closing Stock (Kg)', data: rows.map(m => m.closing_stock),
            borderColor: '#00897B', backgroundColor: 'rgba(0,137,123,.12)', fill: true, tension: .35, pointBackgroundColor: '#00897B',
          }],
        },
        options: {
          plugins: { legend: { display: false } },
          scales: { y: { ticks: { callback: v => v.toLocaleString('en-IN') } } },
        },
      });
    }
  } catch(e) { /* non-fatal */ }
}

function renderPSTable() {
  const tbody = document.getElementById('ps-tbody');
  if (!tbody) return;
  const list = PS.tab === 'summary' ? psGroupedForSummaryTab() : PS.rows;
  const totalPages = Math.max(1, Math.ceil(list.length / PS.pageSize));
  PS.page = Math.min(PS.page, totalPages);
  const pageRows = list.slice((PS.page-1)*PS.pageSize, PS.page*PS.pageSize);

  document.getElementById('ps-info').textContent = `Showing ${list.length?((PS.page-1)*PS.pageSize+1):0} to ${Math.min(PS.page*PS.pageSize,list.length)} of ${list.length} entries`;

  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="13" style="text-align:center;color:var(--muted);padding:30px">No stock movement yet — record a Purchase, Stock In, or manual adjustment</td></tr>`;
  } else {
    tbody.innerHTML = pageRows.map((r, i) => {
      const avail = parseFloat(r.available_stock)||0;
      const color = avail <= 0 ? '#E53935' : ((r.reorder_level && avail < parseFloat(r.reorder_level)) ? '#E65100' : '#00897B');
      return `<tr>
        <td>${(PS.page-1)*PS.pageSize+i+1}</td>
        <td style="text-align:left"><strong>${escHtml(r.name)}</strong></td>
        <td>${escHtml(r.variety||'—')}</td>
        <td>${escHtml(r.warehouse||'Main Warehouse')}</td>
        <td>${escHtml(r.batch_no||'—')}</td>
        <td><strong style="color:${color}">${avail.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}</strong></td>
        <td>${(parseFloat(r.reserved_stock)||0).toFixed(2)}</td>
        <td>${(parseFloat(r.in_transit)||0).toFixed(2)}</td>
        <td>${(parseFloat(r.total_stock)||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
        <td>${fmt_money(r.avg_cost||0)}</td>
        <td>${fmt_money(r.stock_value||0)}</td>
        <td>${r.last_inward_date ? fmt_date_disp(r.last_inward_date) : '—'}</td>
        <td><button class="act-btn" title="View History" onclick="viewStockHistory(${r.product_id}, '${escHtml(r.name).replace(/'/g,"\\'")}')"><i class="fas fa-eye"></i></button></td>
      </tr>`;
    }).join('');
  }

  document.getElementById('ps-pagination').innerHTML = Array.from({length: totalPages}, (_, i) => i+1).map(p => `
    <button class="pg-btn ${p===PS.page?'active':''}" onclick="PS.page=${p};renderPSTable()">${p}</button>`).join('');
}






