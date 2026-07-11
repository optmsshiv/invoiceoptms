// ================================================================
//  assets/js/sales.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  For pages/sales/sales.php (the Sales list view).
//
//  MPA CHANGE: in the old SPA, "Edit" on a row called editSale(id)
//  which loaded data into the same in-page form and used showPage()
//  to switch views. Sales now has its own real page (sale-new.php),
//  so "Edit" just navigates there with ?id=; sale-new.php loads and
//  populates the form itself on page load.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['sales', 'customers', 'settings']);
  renderSales();
});

let SALES_SEARCH = '';
function filterSales(q) { SALES_SEARCH = q || ''; renderSales(); }

function renderSales() {
  const tbody = document.getElementById('salesTbody');
  if (!tbody) return;
  const statusF = document.getElementById('saleStatusFilter')?.value || '';
  let list = STATE.sales || [];
  if (SALES_SEARCH) {
    const q = SALES_SEARCH.toLowerCase();
    list = list.filter(s => (s.invoice_no || '').toLowerCase().includes(q) || (s.customer_name || '').toLowerCase().includes(q));
  }
  if (statusF) list = list.filter(s => s.payment_status === statusF);
  document.getElementById('saleInfo').textContent = list.length + ' sale' + (list.length === 1 ? '' : 's');
  document.getElementById('saleCountInfo').textContent = (STATE.sales || []).length + ' total';
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px">No sales yet — click "Add Sale" to create one</td></tr>`;
    return;
  }
  const statusColor = { Pending: '#FFA000', Partial: '#E65100', Paid: '#00897B' };
  tbody.innerHTML = list.map(s => `
    <tr>
      <td><strong>${escHtml(s.invoice_no)}</strong></td>
      <td>${escHtml(s.customer_name || '—')}</td>
      <td>${fmt_date_disp(s.sale_date)}</td>
      <td>${s.item_count ?? ''}</td>
      <td>${fmt_money(s.total)}</td>
      <td><span style="font-size:11px;font-weight:700;color:${statusColor[s.payment_status] || '#888'};background:${statusColor[s.payment_status] || '#888'}18;padding:2px 8px;border-radius:10px">${escHtml(s.payment_status)}</span></td>
      <td>
        <div class="action-cell">
          <button class="act-btn" title="Edit" onclick="goToEditSale(${s.id})"><i class="fas fa-pen"></i></button>
          <button class="act-btn" title="Delete" onclick="deleteSale(${s.id})"><i class="fas fa-trash"></i></button>
        </div>
      </td>
    </tr>`).join('');
}

function goToNewSale() { window.location.href = '/pages/sales/sale-new.php'; }
function goToEditSale(id) { window.location.href = '/pages/sales/sale-new.php?id=' + id; }

async function deleteSale(id) {
  const s = (STATE.sales || []).find(x => String(x.id) === String(id)); if (!s) return;
  const conf = await Swal.fire({
    title: 'Delete this sale?', text: `"${s.invoice_no}" and its stock-out entries will be permanently removed. This cannot be undone.`,
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#E53935',
    customClass: { popup: 'swal-compact' }
  });
  if (!conf.isConfirmed) return;
  try {
    await api('api/sales.php?id=' + id, 'DELETE');
    STATE.sales = STATE.sales.filter(x => String(x.id) !== String(id));
    const stk = await api('api/stock.php');
    STATE.stock = Array.isArray(stk.data) ? stk.data : STATE.stock;
    renderSales();
    toast('🗑️ Sale deleted', 'info');
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}
