// ================================================================
//  assets/js/products.js
//  Requires: common.js, shared-data.js (loaded before this file).
//
//  MPA CHANGES from the SPA version:
//  1. addProductToInvoice() used to showPage('create') then push
//     into the in-memory formItems array on the create page. Now
//     redirects to /pages/create.php?addProduct=ID — create.js's
//     boot handler calls pickProduct(id) to add the line item
//     (fixed during Phase 4 of the MPA conversion, once create.php
//     existed).
//  2. updateServiceDropdown() (called after add/edit/delete/restore)
//     populates a dropdown on create.php. Guarded with a typeof
//     check so it no-ops here instead of throwing.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['invoices', 'clients', 'products', 'payments', 'stock', 'settings']);
  const isProduct = ['product', 'both'].includes(SERVER.settings?.business_type || 'both');
  if (isProduct) {
    populateProductsListFilters();
    renderProductsList();
  } else {
    renderProducts();
  }
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
    await api('/api/products.php?id=' + (parseInt(id.replace('p', '')) || 0), 'PUT', payload);
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
  row.innerHTML = `
    <td><span style="color:var(--teal);font-size:12px;font-weight:700">${prefill ? 'COPY' : 'NEW'}</span></td>
    <td><input id="np-name" class="table-search" style="width:100%;min-width:150px" placeholder="Service name *" value="${prefill ? escHtml(prefill.name + ' (Copy)') : ''}"></td>
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
    await api('/api/products.php', 'POST', payload);
    const r = await api('/api/products.php');
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
  window.location.href = '/pages/create.php?addProduct=' + encodeURIComponent(id);
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
    await api('/api/products.php?id=' + dbId, 'DELETE');
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
      const r = await api('/api/products.php?status=archived');
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
    await api('/api/products.php?action=restore&id=' + dbId, 'POST');
    PROD.archivedList = (PROD.archivedList || []).filter(x => x.id !== id);
    const r = await api('/api/products.php');
    STATE.products = Array.isArray(r.data) ? r.data : STATE.products;
    if (typeof updateServiceDropdown === 'function') updateServiceDropdown();
    renderProducts();
    toast(`✅ "${p.name}" restored`, 'success');
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

// ============================================================
// The following were added during MPA conversion (Phase 4) for the
// rich, stock-tracked product-list view — used when
// business_type IN ('product', 'both'). See products.php for the
// server-side branch that decides which HTML (and therefore which
// of these vs. the functions above) actually runs.
// ============================================================
function renderProductsList() {
  const tbody = document.getElementById('prl-tbody');
  if (!tbody) return;
  populateProductsListFilters();
  const list = prlFilteredProducts();

  // ── Stats (over ALL products, independent of filters, like the reference) ──
  const all = STATE.products||[];
  let inStock = 0, lowStock = 0, outStock = 0;
  all.forEach(p => {
    const st = prlStock(p);
    const reorder = parseFloat(p.reorder_level) || 0;
    if (st <= 0) outStock++;
    else if (reorder > 0 && st <= reorder) lowStock++;
    else inStock++;
  });
  document.getElementById('prl-stat-total').textContent = all.length;
  document.getElementById('prl-stat-active').textContent = all.filter(p => (p.status||'active') === 'active').length;
  document.getElementById('prl-stat-instock').textContent = inStock;
  document.getElementById('prl-stat-lowstock').textContent = lowStock;
  document.getElementById('prl-stat-outstock').textContent = outStock;
  document.getElementById('prl-stat-inactive').textContent = all.filter(p => (p.status||'active') !== 'active').length;

  // ── Pagination ─────────────────────────────────────────────
  const totalPages = Math.max(1, Math.ceil(list.length / PRL_PAGESIZE));
  if (PRL_PAGE > totalPages) PRL_PAGE = totalPages;
  const start = (PRL_PAGE - 1) * PRL_PAGESIZE;
  const pageRows = list.slice(start, start + PRL_PAGESIZE);
  document.getElementById('prl-info').textContent = list.length
    ? `Showing ${start+1} to ${Math.min(start+PRL_PAGESIZE, list.length)} of ${list.length} entries`
    : 'No entries';
  const pager = document.getElementById('prl-pagination');
  if (pager) {
    let h = `<button class="pg-btn" onclick="prlPage(${PRL_PAGE-1})" ${PRL_PAGE<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= totalPages; i++) {
      if (totalPages > 8 && i > 3 && i < totalPages - 1 && Math.abs(i - PRL_PAGE) > 1) {
        if (i === 4) h += `<span style="padding:0 4px;color:var(--muted)">…</span>`;
        continue;
      }
      h += `<button class="pg-btn ${i===PRL_PAGE?'active':''}" onclick="prlPage(${i})">${i}</button>`;
    }
    h += `<button class="pg-btn" onclick="prlPage(${PRL_PAGE+1})" ${PRL_PAGE>=totalPages?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
    pager.innerHTML = h;
  }

  if (!pageRows.length) {
    tbody.innerHTML = `<tr><td colspan="11" style="text-align:center;color:var(--muted);padding:30px">No products found — click "Add New Product" to create one</td></tr>`;
    return;
  }

  tbody.innerHTML = pageRows.map((p, i) => {
    const stock = prlStock(p);
    const reorder = parseFloat(p.reorder_level) || 0;
    const stockColor = stock <= 0 ? '#E53935' : (reorder > 0 && stock <= reorder ? '#E65100' : 'var(--text)');
    const active = (p.status||'active') === 'active';
    return `
    <tr>
      <td>${start + i + 1}</td>
      <td><strong>${escHtml(p.name)}</strong></td>
      <td>${escHtml(p.sku||'—')}</td>
      <td>${escHtml(p.category||'—')}</td>
      <td>${escHtml(p.unit||'Kg')}</td>
      <td>${escHtml(p.hsn||'—')}</td>
      <td style="text-align:right">${(parseFloat(p.sale_rate ?? p.rate)||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
      <td style="text-align:right">${(parseFloat(p.purchase_rate)||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
      <td style="text-align:right;font-weight:600;color:${stockColor}">${stock.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})} ${escHtml(p.unit||'Kg')}</td>
      <td><span style="font-size:11px;font-weight:700;color:${active?'#00897B':'#889'};background:${active?'#00897B':'#889'}18;padding:2px 9px;border-radius:10px">${active?'Active':'Inactive'}</span></td>
      <td>
        <div class="action-cell" style="display:flex;gap:2px;align-items:center">
          <button class="act-btn" title="Edit" onclick="_editProductWithApproval('${p.id}',()=>editProductRich('${p.id}'))"><i class="fas fa-pen"></i></button>
          <button class="act-btn" title="Stock History" onclick="goToStockHistory('${p.id}', '${escHtml((p.name||'').replace(/'/g,"\\'"))}')"><i class="fas fa-eye"></i></button>
          <span class="act-menu-wrap">
            <button class="act-btn" title="More" onclick="toggleActMenu(event, this)"><i class="fas fa-ellipsis"></i></button>
            <div class="act-menu">
              <button onclick="goToStockHistory('${p.id}')"><i class="fas fa-clock-rotate-left" style="color:#00897B"></i> Stock History</button>
              ${_delItem("deleteProduct('"+p.id+"')")}
            </div>
          </span>
        </div>
      </td>
    </tr>`;
  }).join('');
}

function prlFilteredProducts() {
  const q = (document.getElementById('prl-f-search')?.value || '').trim().toLowerCase();
  const cat = document.getElementById('prl-f-category')?.value || '';
  const unit = document.getElementById('prl-f-unit')?.value || '';
  const status = document.getElementById('prl-f-status')?.value || '';
  const hsn = (document.getElementById('prl-f-hsn')?.value || '').trim().toLowerCase();
  const wh = document.getElementById('prl-f-warehouse')?.value || '';

  return (STATE.products||[]).filter(p => {
    if (q && !(p.name||'').toLowerCase().includes(q) && !(p.sku||'').toLowerCase().includes(q)) return false;
    if (cat && (p.category||'').trim() !== cat) return false;
    if (unit && (p.unit||'').trim() !== unit) return false;
    if (status && (p.status||'active') !== status) return false;
    if (hsn && !(p.hsn||'').toLowerCase().includes(hsn)) return false;
    if (wh && (p.default_warehouse||'Main Warehouse') !== wh) return false;
    return true;
  });
}

function prlPage(p) {
  const totalPages = Math.max(1, Math.ceil(prlFilteredProducts().length / PRL_PAGESIZE));
  if (p < 1 || p > totalPages) return;
  PRL_PAGE = p;
  renderProductsList();
}

function prlStock(p) { return snAvailableStockSafe(p.id); }

function populateProductsListFilters() {
  const catSel = document.getElementById('prl-f-category');
  if (catSel) {
    const cur = catSel.value;
    const cats = [...new Set((STATE.products||[]).map(p => (p.category||'').trim()).filter(Boolean))].sort();
    catSel.innerHTML = '<option value="">All Categories</option>' + cats.map(c => `<option ${c===cur?'selected':''}>${escHtml(c)}</option>`).join('');
  }
  const unitSel = document.getElementById('prl-f-unit');
  if (unitSel) {
    const cur = unitSel.value;
    const units = [...new Set((STATE.products||[]).map(p => (p.unit||'').trim()).filter(Boolean))].sort();
    unitSel.innerHTML = '<option value="">All Units</option>' + units.map(u => `<option ${u===cur?'selected':''}>${escHtml(u)}</option>`).join('');
  }
}

function resetProductsListFilter() {
  document.getElementById('prl-f-search').value = '';
  document.getElementById('prl-f-hsn').value = '';
  ['prl-f-category','prl-f-unit','prl-f-status','prl-f-warehouse'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  PRL_PAGE = 1;
  renderProductsList();
}

function exportProductsExcel() {
  const list = prlFilteredProducts();
  if (!list.length) { toast('⚠️ No products to export for the selected filters', 'warning'); return; }
  const rows = [['#','Product Name','SKU / Code','Category','Unit','HSN Code','Sale Rate','Purchase Rate','Current Stock','Reorder Level','Warehouse','Status']];
  list.forEach((p, i) => {
    rows.push([
      i+1, p.name||'', p.sku||'', p.category||'', p.unit||'Kg', p.hsn||'',
      (parseFloat(p.sale_rate ?? p.rate)||0).toFixed(2), (parseFloat(p.purchase_rate)||0).toFixed(2),
      prlStock(p).toFixed(2), (parseFloat(p.reorder_level)||0).toFixed(2),
      p.default_warehouse||'Main Warehouse', (p.status||'active') === 'active' ? 'Active' : 'Inactive'
    ]);
  });
  _downloadCSV(rows, 'product_list.csv');
  toast('✅ Exported ' + list.length + ' products', 'success');
}

function editProductRich(id) {
  const p = STATE.products.find(x => x.id === id); if (!p) return;
  PNP.editingId = id;
  PNP.images = Array.isArray(p.images) ? [...p.images] : [];
  PNP.attachments = Array.isArray(p.attachments) ? p.attachments.map(url => ({ name: url.split('/').pop(), url })) : [];
  PNP.tags = Array.isArray(p.tags) ? [...p.tags] : [];
  document.getElementById('pnp-title').textContent = 'Edit Product';
  document.getElementById('pnp-subtitle').textContent = p.name;
  populateProductCategoryDropdown();
  const set = (id2, val) => { const el = document.getElementById(id2); if (el) el.value = val ?? ''; };
  set('pp-name', p.name); set('pp-sku', p.sku); set('pp-unit', p.unit || 'Kg'); set('pp-brand', p.brand);
  set('pp-category', p.category); set('pp-hsn', p.hsn); set('pp-shelflife', p.shelf_life_months);
  set('pp-variety', p.variety); set('pp-barcode', p.barcode); set('pp-storagetype', p.storage_type || 'Dry');
  set('pp-grade', p.grade); set('pp-minorderqty', p.min_order_qty || 0);
  pnpSyncUnits();
  set('pp-moisture', p.moisture_limit); set('pp-foreignmatter', p.foreign_matter_limit);
  set('pp-brokendamage', p.broken_damage_limit); set('pp-oilcontent', p.oil_content); set('pp-admixture', p.admixture_limit);
  set('pp-color', p.color); set('pp-aroma', p.aroma); set('pp-shapesize', p.shape_size);
  set('pp-packingtype', p.packing_type || 'PP Bag'); set('pp-packingsize', p.packing_size);
  set('pp-purchaserate', p.purchase_rate || 0); set('pp-salerate', p.sale_rate || 0); set('pp-mrp', p.mrp || 0);
  set('pp-gst', p.gst ?? 18); set('pp-taxtype', p.tax_type || 'Intra-State (CGST+SGST)');
  set('pp-openingstock', p.opening_stock || 0); set('pp-reorderlevel', p.reorder_level || 0);
  set('pp-maxstock', p.max_stock || 0); set('pp-warehouse', p.default_warehouse || 'Main Warehouse');
  document.getElementById('pp-trackbatch').classList.toggle('on', !!parseInt(p.track_batch));
  document.getElementById('pp-trackserial').classList.toggle('on', !!parseInt(p.track_serial));
  set('pp-shortdesc', p.short_description); set('pp-detaildesc', p.detailed_description);
  pnpCharCount('pp-shortdesc','pp-shortdesc-count',200); pnpCharCount('pp-detaildesc','pp-detaildesc-count',500);
  set('pp-country', p.country_of_origin || 'India'); set('pp-manufacturer', p.manufacturer);
  set('pp-fssai', p.fssai_license); set('pp-iec', p.iec_code);
  document.getElementById('pp-status').classList.toggle('on', (p.status||'active') === 'active');
  document.getElementById('pp-status-label').textContent = (p.status||'active') === 'active' ? 'Active' : 'Inactive';
  document.getElementById('pp-status').onclick = toggleProductStatus;
  renderPNPImages(); renderPNPAttachments(); renderPNPTags();
  window.location.href = '/pages/product-new.php?edit_id=' + id;
  return;
}

function _editProductWithApproval(productId, editFn) {
  const p = (STATE.products || []).find(x => String(x.id) === String(productId));
  // Strip "p" prefix — API entity_id is integer, (int)"p12" = 0 → "Invalid entity"
  const numericId = String(productId).replace(/\D/g, '');
  editWithApproval('product', numericId, (p?.name || 'Product #' + numericId), editFn);
}

async function deleteProduct(id) {
  if (!assertCanDelete('this product')) return;
  const p = STATE.products.find(x => x.id === id); if (!p) return;
  const result = await Swal.fire({
    title: (SERVER.settings?.business_type || 'both') === 'product' ? 'Delete this product?' : 'Delete this service?',
    html: `<strong>${escHtml(p.name)}</strong> will be removed from your ${(SERVER.settings?.business_type || 'both') === 'product' ? 'product' : 'services'} list.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete',
    confirmButtonColor: '#E53935',
    cancelButtonText: 'Cancel',
    customClass: { popup: 'swal-compact' }
  });
  if (!result.isConfirmed) return;
  const dbId = parseInt(id.replace('p','')) || 0;
  try {
    await api('/api/products.php?id=' + dbId, 'DELETE');
    STATE.products = STATE.products.filter(x => x.id !== id);
    if (typeof renderProducts === 'function') renderProducts();
    if (typeof renderProductsList === 'function') renderProductsList();
    if (typeof updateServiceDropdown === 'function') updateServiceDropdown();
    toast('🗑️ Deleted', 'info');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function goToStockHistory(productId) {
  window.location.href = '/pages/stock-history.php' + (productId ? '?product_id=' + String(productId).replace(/\D/g,'') : '');
}


function snAvailableStockSafe(productId) {
  const s = (STATE.stock||[]).find(x => String(x.product_id) === String(productId).replace(/\D/g,''));
  return s ? parseFloat(s.current_stock ?? s.available_stock) || 0 : 0;
}