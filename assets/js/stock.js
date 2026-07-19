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

// NOTE: openStockAdjustModal(), saveStockAdjustment(), and
// populateStockProductDropdown() were removed here — dead code.
// The SPA's real "Adjust Stock" flow is the full-page
// stock-adjust-new.php (goToNewStockAdjustment/saveStockAdjustmentEntry),
// not this modal — confirmed openStockAdjustModal() has zero callers
// anywhere else in the app.
//
// Also removed: the old in-modal viewStockHistory() implementation
// and deleteStockAdjustment() (that one now lives correctly on
// stock-history.php, where it's actually used — see stock-history.js).
// viewStockHistory() below is a thin wrapper matching the SPA's real
// behavior: navigate to the dedicated Stock History page.
function viewStockHistory(productId, productName) {
  window.location.href = '/pages/stock/stock-history.php?product=' + productId + '&name=' + encodeURIComponent(productName);
}
