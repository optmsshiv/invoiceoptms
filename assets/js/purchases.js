// ================================================================
//  assets/js/purchases.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  No MPA adjustments needed — self-contained in the original SPA.
//
//  NOTE: populatePurchaseSupplierDropdown() also lives in
//  suppliers.js (identical copy in the SPA, called from there too).
//  Kept here as well since this page needs it independently and
//  suppliers.js may not be loaded on this page.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['suppliers', 'products', 'purchases', 'settings']);
  renderPurchases();
});

const PUR = { search: '' };

function filterPurchases(q) { PUR.search = q || ''; renderPurchases(); }

function renderPurchases() {
  const tbody = document.getElementById('purchasesTbody');
  if (!tbody) return;
  const statusF = document.getElementById('purStatusFilter')?.value || '';
  let list = STATE.purchases || [];
  if (PUR.search) {
    const q = PUR.search.toLowerCase();
    list = list.filter(p => (p.purchase_no || '').toLowerCase().includes(q) || (p.supplier_name || '').toLowerCase().includes(q) || (p.supplier_invoice_ref || '').toLowerCase().includes(q));
  }
  if (statusF) list = list.filter(p => p.status === statusF);
  document.getElementById('purInfo').textContent = list.length + ' purchase' + (list.length === 1 ? '' : 's');
  document.getElementById('purCountInfo').textContent = (STATE.purchases || []).length + ' total';
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px">No purchases yet — click "Add Purchase" to record one</td></tr>`;
    return;
  }
  const statusColor = { Pending: '#FFA000', Received: '#1976D2', Partial: '#E65100', Paid: '#00897B' };
  tbody.innerHTML = list.map(p => `
    <tr>
      <td><strong>${escHtml(p.purchase_no)}</strong></td>
      <td>${escHtml(p.supplier_name || '—')}</td>
      <td>${fmt_date_disp(p.purchase_date)}</td>
      <td>${p.item_count ?? ''}</td>
      <td>${fmt_money_sym(p.total, p.currency === 'INR' ? '₹' : (p.currency === 'USD' ? '$' : (p.currency === 'EUR' ? '€' : (p.currency === 'GBP' ? '£' : ''))))}</td>
      <td><span style="font-size:11px;font-weight:700;color:${statusColor[p.status] || '#888'};background:${statusColor[p.status] || '#888'}18;padding:2px 8px;border-radius:10px">${escHtml(p.status)}</span></td>
      <td>
        <div class="action-cell">
          <button class="act-btn" title="Edit" onclick="editPurchase(${p.id})"><i class="fas fa-pen"></i></button>
          <button class="act-btn" title="Delete" onclick="deletePurchase(${p.id})"><i class="fas fa-trash"></i></button>
        </div>
      </td>
    </tr>`).join('');
}

// NOTE: openAddPurchaseModal(), savePurchase(), and their supporting
// item-table functions (addPurchaseItem, removePurchaseItem,
// renderPurchaseItems, onPurItemProductChange, updatePurItem,
// calcPurchaseTotals, populatePurchaseSupplierDropdown, and the PUR
// state object) were removed here — dead code. The SPA's real "Add
// Purchase" flow was always the full-page purchase-new.php
// (goToNewPurchase/editPurchase/savePurchaseEntry), not this modal —
// confirmed zero references to modal-addpurchase or
// openAddPurchaseModal anywhere else in the app.

function editPurchase(id) {
  window.location.href = '/pages/purchases/purchase-new.php?id=' + id;
}

async function deletePurchase(id) {
  if (!assertCanDelete('this purchase')) return;
  const p = (STATE.purchases || []).find(x => String(x.id) === String(id)); if (!p) return;
  const conf = await Swal.fire({
    title: 'Delete this purchase?',
    text: `"${p.purchase_no}" and its stock-in entries will be permanently removed. This cannot be undone.`,
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#E53935',
    customClass: { popup: 'swal-compact' },
  });
  if (!conf.isConfirmed) return;
  try {
    await api('api/purchases.php?id=' + id, 'DELETE');
    STATE.purchases = STATE.purchases.filter(x => String(x.id) !== String(id));
    renderPurchases();
    toast('🗑️ Purchase deleted', 'info');
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}
