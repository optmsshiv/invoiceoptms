// ================================================================
//  assets/js/suppliers.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  No MPA adjustments needed — this page had no cross-page
//  dependencies in the original SPA either.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['suppliers']);
  renderSuppliers();
});

const SUP = { archived: false, archivedList: [], search: '', editingId: null };

function activeSupSource() {
  const list = SUP.archived ? (SUP.archivedList || []) : STATE.suppliers;
  if (!SUP.search) return list;
  const q = SUP.search.toLowerCase();
  return list.filter(s =>
    (s.name || '').toLowerCase().includes(q) ||
    (s.contact_person || '').toLowerCase().includes(q) ||
    (s.phone || '').toLowerCase().includes(q) ||
    (s.gst_number || '').toLowerCase().includes(q)
  );
}

function filterSuppliers(q) { SUP.search = q || ''; renderSuppliers(); }

function renderSuppliers() {
  const tbody = document.getElementById('suppliersTbody');
  if (!tbody) return;
  const list = activeSupSource();
  document.getElementById('supInfo').textContent = list.length + ' supplier' + (list.length === 1 ? '' : 's');
  document.getElementById('supCountInfo').textContent = STATE.suppliers.length + ' active';
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px">
      ${SUP.archived ? 'No archived suppliers' : 'No suppliers yet — click "Add Supplier" to get started'}</td></tr>`;
    return;
  }
  tbody.innerHTML = list.map((s, i) => `
    <tr>
      <td>${i + 1}</td>
      <td><strong>${escHtml(s.name)}</strong></td>
      <td>${escHtml(s.contact_person || '—')}</td>
      <td>${escHtml(s.phone || '—')}</td>
      <td>${escHtml(s.country || '—')}</td>
      <td>${escHtml(s.gst_number || '—')}</td>
      <td>
        <div class="action-cell">
          ${SUP.archived
            ? `<button class="act-btn" title="Restore" onclick="restoreSupplier(${s.id})"><i class="fas fa-rotate-left"></i></button>`
            : `<button class="act-btn" title="Edit" onclick="editSupplier(${s.id})"><i class="fas fa-pen"></i></button>
               <button class="act-btn" title="Archive" onclick="archiveSupplier(${s.id})"><i class="fas fa-box-archive"></i></button>`}
        </div>
      </td>
    </tr>`).join('');
}

function openAddSupplierModal() {
  SUP.editingId = null;
  document.querySelector('#modal-addsupplier .modal-header span').textContent = 'Add New Supplier';
  ['sup-name', 'sup-person', 'sup-phone', 'sup-email', 'sup-gst', 'sup-address', 'sup-terms', 'sup-notes'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  document.getElementById('sup-country').value = 'India';
  document.getElementById('sup-opening').value = '0';
  openModal('modal-addsupplier');
}

function editSupplier(id) {
  const s = STATE.suppliers.find(x => String(x.id) === String(id)); if (!s) return;
  SUP.editingId = id;
  document.querySelector('#modal-addsupplier .modal-header span').textContent = 'Edit Supplier';
  document.getElementById('sup-name').value    = s.name || '';
  document.getElementById('sup-person').value  = s.contact_person || '';
  document.getElementById('sup-phone').value   = s.phone || '';
  document.getElementById('sup-email').value   = s.email || '';
  document.getElementById('sup-gst').value     = s.gst_number || '';
  document.getElementById('sup-country').value = s.country || 'India';
  document.getElementById('sup-address').value = s.address || '';
  document.getElementById('sup-terms').value   = s.payment_terms || '';
  document.getElementById('sup-opening').value = s.opening_balance || 0;
  document.getElementById('sup-notes').value   = s.notes || '';
  openModal('modal-addsupplier');
}

async function saveSupplier() {
  const name = document.getElementById('sup-name')?.value?.trim();
  if (!name) { toast('⚠️ Supplier name required', 'warning'); return; }
  const btn = document.getElementById('sup-save-btn');
  if (btn) { if (btn.disabled) return; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…'; }
  const payload = {
    name,
    contact_person:  document.getElementById('sup-person').value.trim(),
    phone:           document.getElementById('sup-phone').value.trim(),
    email:           document.getElementById('sup-email').value.trim(),
    gst_number:      document.getElementById('sup-gst').value.trim(),
    country:         document.getElementById('sup-country').value.trim() || 'India',
    address:         document.getElementById('sup-address').value.trim(),
    payment_terms:   document.getElementById('sup-terms').value.trim(),
    opening_balance: parseFloat(document.getElementById('sup-opening').value) || 0,
    notes:           document.getElementById('sup-notes').value.trim(),
  };
  try {
    if (SUP.editingId) {
      await api('api/suppliers.php?id=' + SUP.editingId, 'PUT', payload);
      toast('✅ Supplier updated!', 'success');
    } else {
      await api('api/suppliers.php', 'POST', payload);
      toast('✅ "' + name + '" added!', 'success');
    }
    const r = await api('api/suppliers.php');
    STATE.suppliers = Array.isArray(r.data) ? r.data : STATE.suppliers;
    SUP.editingId = null;
    closeModal('modal-addsupplier');
    renderSuppliers();
  } catch (e) { toast('❌ ' + e.message, 'error'); }
  finally { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Save Supplier'; } }
}

async function archiveSupplier(id) {
  const s = STATE.suppliers.find(x => String(x.id) === String(id)); if (!s) return;
  const conf = await Swal.fire({
    title: 'Archive supplier?', text: `"${s.name}" will be moved to archived suppliers.`,
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Archive', customClass: { popup: 'swal-compact' },
  });
  if (!conf.isConfirmed) return;
  try {
    await api('api/suppliers.php?id=' + id, 'DELETE');
    STATE.suppliers = STATE.suppliers.filter(x => String(x.id) !== String(id));
    renderSuppliers();
    toast('🗑️ Archived', 'info');
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

async function restoreSupplier(id) {
  const s = (SUP.archivedList || []).find(x => String(x.id) === String(id)); if (!s) return;
  try {
    await api('api/suppliers.php?action=restore&id=' + id, 'POST');
    SUP.archivedList = (SUP.archivedList || []).filter(x => String(x.id) !== String(id));
    const r = await api('api/suppliers.php');
    STATE.suppliers = Array.isArray(r.data) ? r.data : STATE.suppliers;
    renderSuppliers();
    toast(`✅ "${s.name}" restored`, 'success');
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

async function toggleSupplierArchivedView() {
  SUP.archived = !SUP.archived;
  const btn = document.getElementById('supArchiveToggleBtn');
  if (SUP.archived) {
    if (btn) btn.innerHTML = '<i class="fas fa-box-open"></i> View Active';
    try {
      const r = await api('api/suppliers.php?status=archived');
      SUP.archivedList = Array.isArray(r.data) ? r.data : [];
    } catch (e) { toast('❌ ' + e.message, 'error'); SUP.archivedList = []; }
  } else {
    if (btn) btn.innerHTML = '<i class="fas fa-box-archive"></i> View Archived';
  }
  const search = document.getElementById('supplierSearch'); if (search) search.value = '';
  renderSuppliers();
}

// Used by purchases.php's supplier dropdown — guarded there via
// typeof check until purchases.js exists.
function populatePurchaseSupplierDropdown() {
  const sel = document.getElementById('pur-supplier');
  if (!sel) return;
  sel.innerHTML = '<option value="">Select supplier…</option>' +
    STATE.suppliers.map(s => `<option value="${s.id}">${escHtml(s.name)}</option>`).join('');
}
