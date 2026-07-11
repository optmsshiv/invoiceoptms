// ================================================================
//  assets/js/products.js
//  Requires: common.js, shared-data.js (loaded before this file).
//
//  MPA CHANGES from the SPA version:
//  1. addProductToInvoice() used to showPage('create') then push
//     into the in-memory formItems array on the create page. Since
//     create.php doesn't exist yet, this now redirects to
//     /pages/invoices/create.php?addProduct=ID — create.php will need to
//     read that param and add the matching product as a line item
//     once it's built.
//  2. updateServiceDropdown() (called after add/edit/delete/restore)
//     populates a dropdown on create.php. Guarded with a typeof
//     check so it no-ops here instead of throwing.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['invoices', 'clients', 'products', 'payments', 'settings']);
  renderProducts();
});

const PROD = { page: 1, per: 8, list: [], archived: false, archivedList: null };

// Category → default HSN/SAC code. Editable suggestions only — never
// overrides a value the user has already typed.
const HSN_DEFAULTS = {
  'Service': '998314', // IT design/development & other professional services (SAC)
  'Labour':  '998719', // Installation/maintenance/repair services (SAC)
  'Product': '',       // goods vary too much for a safe default
  'Other':   '',
};
function suggestHsnForCategory(cat) {
  if (HSN_DEFAULTS[cat] !== undefined) return HSN_DEFAULTS[cat];
  const match = (STATE.products || []).slice().reverse().find(p => p.category === cat && p.hsn);
  return match ? match.hsn : '998314';
}
function allKnownHsnCodes() {
  return [...new Set((STATE.products || []).map(p => p.hsn).filter(Boolean))];
}
function activeProdSource() { return PROD.archived ? (PROD.archivedList || []) : STATE.products; }
function renderProducts() { updateProductCatDropdowns(); PROD.list = [...activeProdSource()]; PROD.page = 1; _renderProdPage(); }

function filterProducts(v) {
  const s = v.toLowerCase(), cat = document.getElementById('productCatFilter')?.value || '';
  PROD.list = activeProdSource().filter(p => (!s || p.name.toLowerCase().includes(s) || p.category.toLowerCase().includes(s) || (p.hsn || '').toLowerCase().includes(s)) && (!cat || p.category === cat));
  PROD.page = 1; _renderProdPage();
}
function filterProductsCat(v) { filterProducts(document.getElementById('productSearch')?.value || ''); }

function updateProductCatDropdowns() {
  const opts = STATE.categories.map(c => `<option value="${c.name}">${c.name}</option>`).join('');
  document.querySelectorAll('.cat-select').forEach(el => { const cur = el.value; el.innerHTML = opts; el.value = cur; });
  const filter = document.getElementById('productCatFilter');
  if (filter) filter.innerHTML = `<option value="">All Categories</option>${opts}`;
}

function _renderProdPage() {
  const tbody = document.getElementById('productsTbody'); if (!tbody) return;
  const s = (PROD.page - 1) * PROD.per, e = s + PROD.per, pg = PROD.list.slice(s, e);
  tbody.innerHTML = pg.map((p, i) => {
    const catColor = getCatColor(p.category);
    const catTc = getCatTextColor(catColor);
    const actions = PROD.archived
      ? `<button class="act-btn" title="Restore" onclick="restoreProduct('${p.id}')"><i class="fas fa-rotate-left"></i></button>`
      : `<button class="act-btn" title="Add to Invoice" onclick="addProductToInvoice('${p.id}')"><i class="fas fa-plus"></i></button>
      <button class="act-btn" title="Clone" onclick="cloneProduct('${p.id}')"><i class="fas fa-copy"></i></button>
      <button class="act-btn" title="Edit" onclick="editProduct('${p.id}')"><i class="fas fa-edit"></i></button>
      <button class="act-btn del" title="Delete" onclick="deleteProduct('${p.id}')"><i class="fas fa-trash"></i></button>`;
    return `<tr data-id="${escHtml(p.id)}">
    <td>${s + i + 1}</td>
    <td><strong>${escHtml(p.name)}</strong></td>
    <td><span style="padding:3px 10px;border-radius:12px;background:${catColor};color:${catTc};font-size:11px;font-weight:700;letter-spacing:.2px;box-shadow:0 1px 3px ${catColor}55">${escHtml(p.category)}</span></td>
    <td><code style="font-family:var(--mono);color:var(--teal);font-weight:700">${fmt_money(p.rate)}</code></td>
    <td><code style="font-family:var(--mono)">${escHtml(p.hsn)}</code></td>
    <td><strong>${p.gst}%</strong></td>
    <td><div class="action-cell">${actions}</div></td>
  </tr>`;
  }).join('') || `<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--muted)">${PROD.archived ? 'No archived services' : 'No services found'}</td></tr>`;
  const tot = Math.ceil(PROD.list.length / PROD.per);
  const pg2 = document.getElementById('prodPagination');
  if (pg2) {
    let h = `<button class="pg-btn" onclick="prodPage(${PROD.page - 1})" ${PROD.page <= 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= tot; i++) h += `<button class="pg-btn ${i === PROD.page ? 'active' : ''}" onclick="prodPage(${i})">${i}</button>`;
    h += `<button class="pg-btn" onclick="prodPage(${PROD.page + 1})" ${PROD.page >= tot ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;
    pg2.innerHTML = h;
  }
  const inf = document.getElementById('prodInfo'); if (inf) inf.textContent = `${s + 1}–${Math.min(e, PROD.list.length)} of ${PROD.list.length}`;
  const ci = document.getElementById('prodCountInfo'); if (ci) ci.textContent = PROD.archived ? `${(PROD.archivedList || []).length} archived` : `${STATE.products.length} total`;
}
function prodPage(p) { const t = Math.ceil(PROD.list.length / PROD.per); if (p < 1 || p > t) return; PROD.page = p; _renderProdPage(); }

function editProduct(id) {
  // Product-mode businesses use the full product-new.php page (richer
  // fields: batch tracking, moisture limits, weighbridge-relevant
  // attrs, etc.) — service/both keep this lightweight inline-row edit.
  if (STATE.settings.businessType === 'product') {
    window.location.href = '/pages/products/product-new.php?id=' + id;
    return;
  }
  const p = STATE.products.find(x => x.id === id); if (!p) return;
  const catOpts = STATE.categories.map(c => `<option value="${c.name}" ${c.name === p.category ? 'selected' : ''}>${escHtml(c.name)}</option>`).join('');
  const row = document.querySelector(`#productsTbody tr[data-id="${CSS.escape(id)}"]`);
  if (!row) return;
  row.style.background = '#f0fdf4';
  row.innerHTML = `<td><span style="color:var(--teal);font-size:11px;font-weight:700">EDIT</span></td>
  <td><input id="ep-name" class="table-search" style="width:100%" value="${escHtml(p.name)}"></td>
  <td><select id="ep-cat" class="table-filter cat-select" style="min-width:120px" onchange="hsnPrefill('ep-cat','ep-hsn')">${catOpts}</select></td>
  <td><input id="ep-rate" type="number" class="table-search" style="width:90px" value="${p.rate}"></td>
  <td><input id="ep-hsn" class="table-search" style="width:75px" value="${escHtml(p.hsn)}" list="hsn-suggestions"></td>
  <td><select id="ep-gst" class="table-filter"><option value="0" ${p.gst == 0 ? 'selected' : ''}>0%</option><option value="5" ${p.gst == 5 ? 'selected' : ''}>5%</option><option value="12" ${p.gst == 12 ? 'selected' : ''}>12%</option><option value="18" ${p.gst == 18 ? 'selected' : ''}>18%</option><option value="28" ${p.gst == 28 ? 'selected' : ''}>28%</option></select></td>
  <td><div class="action-cell"><button id="ep-save-btn" class="btn btn-success" style="font-size:11px;padding:4px 10px" onclick="saveEditProd('${id}')"><i class="fas fa-check"></i></button><button class="btn btn-outline" style="font-size:11px;padding:4px 10px" onclick="renderProducts()"><i class="fas fa-times"></i></button></div></td>`;
  ensureHsnDatalist();
}

// Fills the HSN field with a suggested code on category change, but
// only if empty or still equal to a previous suggestion.
function hsnPrefill(catSelId, hsnInputId) {
  const catEl = document.getElementById(catSelId), hsnEl = document.getElementById(hsnInputId);
  if (!catEl || !hsnEl) return;
  if (!hsnEl.value.trim() || hsnEl.dataset.autofilled === '1') {
    const suggestion = suggestHsnForCategory(catEl.value);
    if (suggestion) { hsnEl.value = suggestion; hsnEl.dataset.autofilled = '1'; }
  }
}
function ensureHsnDatalist() {
  let dl = document.getElementById('hsn-suggestions');
  if (!dl) { dl = document.createElement('datalist'); dl.id = 'hsn-suggestions'; document.body.appendChild(dl); }
  dl.innerHTML = allKnownHsnCodes().map(h => `<option value="${escHtml(h)}">`).join('');
}

async function saveEditProd(id) {
  const idx = STATE.products.findIndex(x => x.id === id); if (idx < 0) return;
  const n = document.getElementById('ep-name')?.value?.trim();
  if (!n) { toast('Name required', 'warning'); return; }
  const btn = document.getElementById('ep-save-btn');
  if (btn) { if (btn.disabled) return; btn.disabled = true; }
  const payload = {
    name: n, category: document.getElementById('ep-cat')?.value || 'Other',
    rate: parseFloat(document.getElementById('ep-rate')?.value) || 0,
    hsn: document.getElementById('ep-hsn')?.value || '998314',
    gst: (document.getElementById('ep-gst')?.value !== undefined && document.getElementById('ep-gst')?.value !== '' ? parseInt(document.getElementById('ep-gst').value) : 18),
  };
  try {
    await api('api/products.php?id=' + (parseInt(id.replace('p', '')) || 0), 'PUT', payload);
    STATE.products[idx] = { ...STATE.products[idx], ...payload };
    renderProducts();
    if (typeof updateServiceDropdown === 'function') updateServiceDropdown();
    toast('✅ Updated!', 'success');
  } catch (e) { toast('❌ ' + e.message, 'error'); if (btn) btn.disabled = false; }
}

function openAddProductModal() {
  if (PROD.archived) return; // Adding isn't relevant while viewing archived services
  const existing = document.getElementById('add-product-row');
  if (existing) { existing.remove(); return; }
  _showAddProductRow();
}

// Builds the inline "add service" row. Pass `prefill` (an existing
// product) to use this for cloning.
function _showAddProductRow(prefill) {
  const tbody = document.getElementById('productsTbody');
  document.getElementById('add-product-row')?.remove();
  const row = document.createElement('tr');
  row.id = 'add-product-row';
  row.style.background = '#f0fdf4';
  const _namePlaceholder = STATE.settings.businessType === 'both' ? 'Item name *' : 'Service name *';
  row.innerHTML = `
    <td><span style="color:var(--teal);font-size:12px;font-weight:700">${prefill ? 'COPY' : 'NEW'}</span></td>
    <td><input id="np-name" class="table-search" style="width:100%;min-width:150px" placeholder="${_namePlaceholder}" value="${prefill ? escHtml(prefill.name + ' (Copy)') : ''}"></td>
    <td><select id="np-cat" class="table-filter cat-select" style="min-width:120px" onchange="hsnPrefill('np-cat','np-hsn')"></select></td>
    <td><input id="np-rate" type="number" class="table-search" style="width:100px" placeholder="Rate ₹" value="${prefill ? prefill.rate : 0}"></td>
    <td><input id="np-hsn" class="table-search" style="width:80px" placeholder="HSN" value="${prefill ? escHtml(prefill.hsn) : '998314'}" list="hsn-suggestions"></td>
    <td>
      <select id="np-gst" class="table-filter">
        <option value="0" ${prefill && prefill.gst == 0 ? 'selected' : ''}>0%</option><option value="5" ${prefill && prefill.gst == 5 ? 'selected' : ''}>5%</option><option value="12" ${prefill && prefill.gst == 12 ? 'selected' : ''}>12%</option><option value="18" ${!prefill || prefill.gst == 18 ? 'selected' : ''}>18%</option><option value="28" ${prefill && prefill.gst == 28 ? 'selected' : ''}>28%</option>
      </select>%
    </td>
    <td>
      <div class="action-cell">
        <button id="np-save-btn" class="btn btn-success" style="font-size:11px;padding:5px 12px" onclick="saveNewProduct()"><i class="fas fa-check"></i> Save</button>
        <button class="btn btn-outline" style="font-size:11px;padding:5px 10px" onclick="document.getElementById('add-product-row').remove()"><i class="fas fa-times"></i></button>
      </div>
    </td>`;
  tbody.insertBefore(row, tbody.firstChild);
  const npCat = document.getElementById('np-cat');
  if (npCat) {
    npCat.innerHTML = STATE.categories.map(c => `<option value="${c.name}">${escHtml(c.name)}</option>`).join('');
    if (prefill) npCat.value = prefill.category;
  }
  ensureHsnDatalist();
  const npHsn = document.getElementById('np-hsn');
  if (!prefill && npHsn && npCat && npCat.value) {
    npHsn.value = suggestHsnForCategory(npCat.value) || npHsn.value; npHsn.dataset.autofilled = '1';
  }
  const nameEl = document.getElementById('np-name');
  nameEl.focus();
  if (prefill) nameEl.select();
}

function cloneProduct(id) {
  const p = STATE.products.find(x => x.id === id); if (!p) return;
  _showAddProductRow(p);
  toast('📋 Cloned — tweak the details and save', 'info');
}

async function saveNewProduct() {
  const n = document.getElementById('np-name')?.value?.trim();
  if (!n) { toast('⚠️ Name required', 'warning'); return; }
  const btn = document.getElementById('np-save-btn');
  if (btn) { if (btn.disabled) return; btn.disabled = true; }
  const payload = {
    name: n, category: document.getElementById('np-cat')?.value || 'Other',
    rate: parseFloat(document.getElementById('np-rate')?.value) || 0,
    hsn: document.getElementById('np-hsn')?.value || '998314',
    gst: (document.getElementById('np-gst')?.value !== undefined && document.getElementById('np-gst')?.value !== '' ? parseInt(document.getElementById('np-gst').value) : 18),
  };
  try {
    await api('api/products.php', 'POST', payload);
    const r = await api('api/products.php');
    STATE.products = Array.isArray(r.data) ? r.data : STATE.products;
    document.getElementById('add-product-row')?.remove();
    renderProducts();
    if (typeof updateServiceDropdown === 'function') updateServiceDropdown();
    toast('✅ "' + n + '" added!', 'success');
  } catch (e) { toast('❌ ' + e.message, 'error'); if (btn) btn.disabled = false; }
}

function addProductToInvoice(id) {
  const p = STATE.products.find(x => x.id === id);
  if (!p) return;
  // create.php isn't built yet — it will need to read ?addProduct=ID
  // and push a matching line item once it exists.
  window.location.href = '/pages/invoices/create.php?addProduct=' + encodeURIComponent(id);
}

async function deleteProduct(id) {
  const p = STATE.products.find(x => x.id === id); if (!p) return;
  const result = await Swal.fire({
    title: 'Delete this service?',
    html: `<strong>${escHtml(p.name)}</strong> will be removed from your services list.`,
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete',
    confirmButtonColor: '#E53935', cancelButtonText: 'Cancel', customClass: { popup: 'swal-compact' },
  });
  if (!result.isConfirmed) return;
  const dbId = parseInt(id.replace('p', '')) || 0;
  try {
    await api('api/products.php?id=' + dbId, 'DELETE');
    STATE.products = STATE.products.filter(x => x.id !== id);
    renderProducts();
    if (typeof updateServiceDropdown === 'function') updateServiceDropdown();
    toast('🗑️ Deleted', 'info');
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

// Switches between the active catalog and the archived (soft-deleted)
// list. Archived services are fetched on demand rather than kept in
// STATE, since they're rarely needed.
async function toggleArchivedView() {
  document.getElementById('add-product-row')?.remove();
  PROD.archived = !PROD.archived;
  const btn = document.getElementById('prodArchiveToggleBtn');
  const addBtn = document.getElementById('prodAddBtn');
  if (PROD.archived) {
    if (btn) btn.innerHTML = '<i class="fas fa-box-open"></i> View Active';
    if (addBtn) addBtn.style.display = 'none';
    try {
      const r = await api('api/products.php?status=archived');
      PROD.archivedList = Array.isArray(r.data) ? r.data : [];
    } catch (e) { toast('❌ ' + e.message, 'error'); PROD.archivedList = []; }
  } else {
    if (btn) btn.innerHTML = '<i class="fas fa-box-archive"></i> View Archived';
    if (addBtn) addBtn.style.display = '';
  }
  const search = document.getElementById('productSearch'); if (search) search.value = '';
  renderProducts();
}

async function restoreProduct(id) {
  const p = (PROD.archivedList || []).find(x => x.id === id); if (!p) return;
  const dbId = parseInt(id.replace('p', '')) || 0;
  try {
    await api('api/products.php?action=restore&id=' + dbId, 'POST');
    PROD.archivedList = (PROD.archivedList || []).filter(x => x.id !== id);
    const r = await api('api/products.php');
    STATE.products = Array.isArray(r.data) ? r.data : STATE.products;
    if (typeof updateServiceDropdown === 'function') updateServiceDropdown();
    renderProducts();
    toast(`✅ "${p.name}" restored`, 'success');
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}
