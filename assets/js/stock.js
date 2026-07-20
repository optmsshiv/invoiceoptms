// ================================================================
//  assets/js/stock.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  No MPA adjustments needed — self-contained in the original SPA.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['products']);
  renderStock();
});

let stockSearchTerm = '';

function filterStock(q) { stockSearchTerm = (q || '').toLowerCase(); renderStockTable(); }

async function renderStock() {
  try {
    const r = await api('api/stock.php');
    STATE.stock = Array.isArray(r.data) ? r.data : [];
    renderStockTable();
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

function renderStockTable() {
  const tbody = document.getElementById('stockTbody');
  if (!tbody) return;
  let list = STATE.stock || [];
  if (stockSearchTerm) list = list.filter(s => (s.name || '').toLowerCase().includes(stockSearchTerm) || (s.category || '').toLowerCase().includes(stockSearchTerm));
  document.getElementById('stockInfo').textContent = list.length + ' product' + (list.length === 1 ? '' : 's') + ' with stock movement';
  document.getElementById('stockCountInfo').textContent = (STATE.stock || []).length + ' tracked';
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:30px">No stock movement yet — record a Purchase or add a manual adjustment</td></tr>`;
    return;
  }
  tbody.innerHTML = list.map(s => {
    const stockNum = parseFloat(s.current_stock) || 0;
    const color = stockNum <= 0 ? '#E53935' : (stockNum < 10 ? '#E65100' : '#00897B');
    return `<tr>
      <td><strong>${escHtml(s.name)}</strong></td>
      <td>${escHtml(s.category || '—')}</td>
      <td><strong style="color:${color}">${stockNum.toLocaleString('en-IN')}</strong></td>
      <td>${fmt_date_disp(s.last_movement)}</td>
      <td>
        <div class="action-cell">
          <button class="act-btn" title="View History" onclick="viewStockHistory(${s.product_id}, '${escHtml(s.name).replace(/'/g, "\\'")}')"><i class="fas fa-history"></i></button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

function populateStockProductDropdown() {
  const sel = document.getElementById('adj-product');
  if (!sel) return;
  sel.innerHTML = '<option value="">Select product…</option>' +
    (STATE.products || []).map(p => `<option value="${p.id}">${escHtml(p.name)}</option>`).join('');
}

function openStockAdjustModal() {
  populateStockProductDropdown();
  document.getElementById('adj-direction').value = 'in';
  document.getElementById('adj-qty').value = '';
  document.getElementById('adj-date').value = fmt_date(new Date());
  document.getElementById('adj-rate').value = '';
  document.getElementById('adj-notes').value = '';
  openModal('modal-stockadjust');
}

async function saveStockAdjustment() {
  const productId = document.getElementById('adj-product').value;
  const qty = parseFloat(document.getElementById('adj-qty').value);
  if (!productId) { toast('⚠️ Select a product', 'warning'); return; }
  if (!qty || qty <= 0) { toast('⚠️ Enter a quantity greater than 0', 'warning'); return; }
  const btn = document.getElementById('adj-save-btn');
  if (btn) { if (btn.disabled) return; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…'; }
  const payload = {
    product_id: parseInt(productId),
    direction: document.getElementById('adj-direction').value,
    qty,
    rate: parseFloat(document.getElementById('adj-rate').value) || 0,
    movement_date: document.getElementById('adj-date').value,
    notes: document.getElementById('adj-notes').value.trim() || 'Manual adjustment',
  };
  try {
    await api('api/stock.php', 'POST', payload);
    toast('✅ Stock adjustment recorded', 'success');
    closeModal('modal-stockadjust');
    renderStock();
  } catch (e) { toast('❌ ' + e.message, 'error'); }
  finally { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Save Adjustment'; } }
}

async function viewStockHistory(productId, productName) {
  document.getElementById('sh-product-name').textContent = 'Stock History — ' + productName;
  const tbody = document.getElementById('sh-tbody');
  tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:20px">Loading…</td></tr>`;
  openModal('modal-stockhistory');
  try {
    const r = await api('api/stock.php?product_id=' + productId);
    const rows = Array.isArray(r.data) ? r.data : [];
    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:20px">No movement recorded</td></tr>`;
      return;
    }
    const sourceLabel = { purchase: '🛒 Purchase', sale: '📤 Sale', adjustment: '⚖️ Adjustment' };
    tbody.innerHTML = rows.map(row => `
      <tr>
        <td>${fmt_date_disp(row.movement_date)}</td>
        <td>${sourceLabel[row.ref_type] || row.ref_type}</td>
        <td style="color:${row.direction === 'in' ? '#00897B' : '#E53935'};font-weight:700">${row.direction === 'in' ? 'IN' : 'OUT'}</td>
        <td>${parseFloat(row.qty).toLocaleString('en-IN')}</td>
        <td>${row.rate ? fmt_money(row.rate) : '—'}</td>
        <td><strong>${parseFloat(row.running_balance).toLocaleString('en-IN')}</strong></td>
        <td style="color:var(--muted)">${escHtml(row.notes || '')}</td>
        <td>${row.ref_type === 'adjustment' ? `<button class="act-btn" title="Delete adjustment" onclick="deleteStockAdjustment(${row.id}, ${productId}, '${escHtml(productName).replace(/'/g, "\\'")}')"><i class="fas fa-trash"></i></button>` : ''}</td>
      </tr>`).join('');
  } catch (e) { tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:var(--red)">Failed to load: ${e.message}</td></tr>`; }
}

async function deleteStockAdjustment(id, productId, productName) {
  const conf = await Swal.fire({
    title: 'Delete this adjustment?', text: 'This manual stock entry will be removed.',
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#E53935',
    customClass: { popup: 'swal-compact' },
  });
  if (!conf.isConfirmed) return;
  try {
    await api('api/stock.php?id=' + id, 'DELETE');
    toast('🗑️ Adjustment deleted', 'info');
    viewStockHistory(productId, productName);
    renderStock();
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}
