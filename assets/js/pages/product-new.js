const PP_GRADE_VARIETY_MAP = { 'Grade-1': 'Premium', 'Grade-2': 'SBD', 'Grade-3': 'BD', 'Grade-4': 'CD', 'Grade-5': 'RBD' };
const PP_VARIETY_GRADE_MAP = Object.fromEntries(Object.entries(PP_GRADE_VARIETY_MAP).map(([g,v]) => [v,g]));

// ============================================================
// product-new.js — page-specific JS for pages/product-new.php
// Depends on: common.js, shared-data.js
//
// Only relevant for business_type IN ('product','both') — the
// 'service' branch of products.php uses the existing simple
// add-row pattern in products.js instead of this full page.
//
// EDIT MODE: products.js's editProductRich() redirects here with
// ?edit_id=X; loadProductForEdit() below does the field population
// (moved out of editProductRich, same pattern as Phases 2-4).
// ============================================================
const PNP = { editingId: null, images: [], attachments: [], tags: [] };

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['products', 'settings']);
  const params = new URLSearchParams(window.location.search);
  const editId = params.get('edit_id');
  if (editId) {
    await loadProductForEdit(editId);
  } else {
    initNewProductPage();
  }
});

function initNewProductPage() {
  PNP.editingId = null;
  PNP.images = []; PNP.attachments = []; PNP.tags = [];
  document.getElementById('pnp-title').textContent = 'New Product';
  document.getElementById('pnp-subtitle').textContent = 'Add a product to your catalog';
  ['pp-name','pp-sku','pp-brand','pp-hsn','pp-variety','pp-barcode','pp-color','pp-aroma','pp-shapesize','pp-packingsize',
   'pp-manufacturer','pp-fssai','pp-iec','pp-shortdesc','pp-detaildesc'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  document.getElementById('pp-unit').value = 'Kg'; pnpSyncUnits();
  populateProductCategoryDropdown();
  document.getElementById('pp-shelflife').value = '';
  document.getElementById('pp-storagetype').value = 'Dry';
  document.getElementById('pp-grade').value = '';
  document.getElementById('pp-minorderqty').value = 0;
  ['pp-moisture','pp-foreignmatter','pp-brokendamage','pp-oilcontent','pp-admixture'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('pp-packingtype').value = 'PP Bag';
  document.getElementById('pp-purchaserate').value = 0;
  document.getElementById('pp-salerate').value = 0;
  document.getElementById('pp-mrp').value = 0;
  document.getElementById('pp-gst').value = '18';
  document.getElementById('pp-taxtype').value = 'Intra-State (CGST+SGST)';
  document.getElementById('pp-openingstock').value = 0;
  document.getElementById('pp-reorderlevel').value = 0;
  document.getElementById('pp-maxstock').value = 0;
  document.getElementById('pp-warehouse').value = 'Main Warehouse';
  document.getElementById('pp-trackbatch').classList.remove('on');
  document.getElementById('pp-trackserial').classList.remove('on');
  document.getElementById('pp-country').value = 'India';
  document.getElementById('pp-status').classList.add('on');
  document.getElementById('pp-status-label').textContent = 'Active';
  document.getElementById('pp-status').onclick = toggleProductStatus;
  document.getElementById('pp-shortdesc-count').textContent = 0;
  document.getElementById('pp-detaildesc-count').textContent = 0;
  document.getElementById('pp-images-input').value = '';
  document.getElementById('pp-attachments-input').value = '';
  renderPNPImages(); renderPNPAttachments(); renderPNPTags();
}

function cancelProductEntry() {
  window.location.href = '/pages/products.php';
}


async function saveProductEntry(mode) {
  const name = document.getElementById('pp-name').value.trim();
  const sku  = document.getElementById('pp-sku').value.trim();
  if (!name) { toast('⚠️ Product name is required', 'warning'); return; }
  if (!sku)  { toast('⚠️ Product Code / SKU is required', 'warning'); return; }
  if (!document.getElementById('pp-moisture').value) { toast('⚠️ Moisture Limit (%) is required', 'warning'); return; }

  const payload = {
    name, sku, category: document.getElementById('pp-category').value || 'Other',
    unit: document.getElementById('pp-unit').value, brand: document.getElementById('pp-brand').value.trim(),
    hsn: document.getElementById('pp-hsn').value.trim(),
    base_unit_label: document.getElementById('pp-baseunit').value, shelf_life_months: document.getElementById('pp-shelflife').value || null,
    variety: document.getElementById('pp-variety').value.trim(), barcode: document.getElementById('pp-barcode').value.trim(),
    sale_unit: document.getElementById('pp-saleunit').value, storage_type: document.getElementById('pp-storagetype').value,
    grade: document.getElementById('pp-grade').value, purchase_unit: document.getElementById('pp-purchaseunit').value,
    min_order_qty: parseFloat(document.getElementById('pp-minorderqty').value) || 0,
    moisture_limit: parseFloat(document.getElementById('pp-moisture').value) || 0,
    foreign_matter_limit: parseFloat(document.getElementById('pp-foreignmatter').value) || 0,
    broken_damage_limit: parseFloat(document.getElementById('pp-brokendamage').value) || 0,
    oil_content: document.getElementById('pp-oilcontent').value ? parseFloat(document.getElementById('pp-oilcontent').value) : null,
    admixture_limit: parseFloat(document.getElementById('pp-admixture').value) || 0,
    color: document.getElementById('pp-color').value.trim(), aroma: document.getElementById('pp-aroma').value.trim(),
    shape_size: document.getElementById('pp-shapesize').value.trim(), packing_type: document.getElementById('pp-packingtype').value,
    packing_size: document.getElementById('pp-packingsize').value.trim(),
    purchase_rate: parseFloat(document.getElementById('pp-purchaserate').value) || 0,
    sale_rate: parseFloat(document.getElementById('pp-salerate').value) || 0,
    rate: parseFloat(document.getElementById('pp-salerate').value) || 0, // legacy 'rate' column mirrors sale rate for compatibility
    mrp: parseFloat(document.getElementById('pp-mrp').value) || 0,
    gst: parseInt(document.getElementById('pp-gst').value) || 0,
    tax_type: document.getElementById('pp-taxtype').value,
    opening_stock: parseFloat(document.getElementById('pp-openingstock').value) || 0,
    reorder_level: parseFloat(document.getElementById('pp-reorderlevel').value) || 0,
    max_stock: parseFloat(document.getElementById('pp-maxstock').value) || 0,
    default_warehouse: document.getElementById('pp-warehouse').value,
    track_batch: document.getElementById('pp-trackbatch').classList.contains('on') ? 1 : 0,
    track_serial: document.getElementById('pp-trackserial').classList.contains('on') ? 1 : 0,
    short_description: document.getElementById('pp-shortdesc').value.trim(),
    detailed_description: document.getElementById('pp-detaildesc').value.trim(),
    country_of_origin: document.getElementById('pp-country').value.trim() || 'India',
    manufacturer: document.getElementById('pp-manufacturer').value.trim(),
    fssai_license: document.getElementById('pp-fssai').value.trim(), iec_code: document.getElementById('pp-iec').value.trim(),
    unit_family: 'weight', // AgriTrade-style products are always weight-tracked (Kg base) for Stock Ledger purposes
    status: document.getElementById('pp-status').classList.contains('on') ? 'active' : 'inactive',
    tags: PNP.tags, images: PNP.images, attachments: PNP.attachments.map(a => a.url),
  };

  // Loading state — product payloads can be large (base64 images/attachments),
  // so make it obvious that the save is in progress rather than frozen.
  const payloadSizeMB = JSON.stringify(payload).length / (1024 * 1024);
  if (payloadSizeMB > 8) {
    toast(`⚠️ Attachments/images total ~${payloadSizeMB.toFixed(1)} MB — upload may take a while or be rejected by the server. Consider smaller images.`, 'warning');
  }
  const btn = event?.target?.closest('button');
  const allBtns = ['pp-btn-cancel','pp-btn-savenew','pp-btn-save'].map(id => document.getElementById(id)).filter(Boolean);
  const origHtml = btn ? btn.innerHTML : '';
  allBtns.forEach(b => b.disabled = true);
  if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
  const loadBar = document.getElementById('pp-loading-bar');
  if (loadBar) loadBar.style.display = 'block';
  try {
    if (PNP.editingId) {
      await api('/api/products.php?id=' + PNP.editingId, 'PUT', payload);
      consumeEditApproval(); toast('✅ Product updated!', 'success');
    } else {
      await api('/api/products.php', 'POST', payload);
      consumeEditApproval(); toast('✅ Product saved!', 'success');
    }
    const r = await api('/api/products.php');
    STATE.products = Array.isArray(r.data) ? r.data : STATE.products;
    updateServiceDropdown();
    if (mode === 'new') {
      window.location.href = '/pages/product-new.php';
    } else {
      window.location.href = '/pages/products.php';
    }
  } catch(e) { toast('❌ ' + e.message, 'error'); }
  finally {
    allBtns.forEach(b => b.disabled = false);
    if (btn) btn.innerHTML = origHtml;
    if (loadBar) loadBar.style.display = 'none';
  }
}

function onPPGradeChange() {
  const grade = document.getElementById('pp-grade').value;
  const variety = PP_GRADE_VARIETY_MAP[grade];
  if (variety) document.getElementById('pp-variety').value = variety;
}

function onPPVarietyChange() {
  const variety = document.getElementById('pp-variety').value;
  const grade = PP_VARIETY_GRADE_MAP[variety];
  if (grade) document.getElementById('pp-grade').value = grade;
}

async function pnpAddAttachments(files) {
  for (const f of Array.from(files)) {
    const url = await pnpFileToDataUrl(f);
    if (url) PNP.attachments.push({ name: f.name, url });
  }
  document.getElementById('pp-attachments-input').value = '';
  renderPNPAttachments();
}

async function pnpAddImages(files) {
  for (const f of Array.from(files)) {
    const url = await pnpFileToDataUrl(f);
    if (url) PNP.images.push(url);
  }
  document.getElementById('pp-images-input').value = '';
  renderPNPImages();
}

function pnpCharCount(fieldId, countId, max) {
  const val = document.getElementById(fieldId).value || '';
  document.getElementById(countId).textContent = Math.min(val.length, max);
}

function pnpSyncUnits() {
  const u = document.getElementById('pp-unit').value;
  document.getElementById('pp-baseunit').value = u;
  document.getElementById('pp-saleunit').value = u;
  document.getElementById('pp-purchaseunit').value = u;
}

function pnpTagKeydown(e) {
  if (e.key !== 'Enter') return;
  e.preventDefault();
  const val = e.target.value.trim();
  if (!val) return;
  if (!PNP.tags.includes(val)) PNP.tags.push(val);
  e.target.value = '';
  renderPNPTags();
}

function pnpFileToDataUrl(file) {
  return new Promise(resolve => {
    if (file.size > 5*1024*1024) { toast(`⚠️ "${file.name}" is over 5MB — skipped`, 'warning'); return resolve(null); }
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => resolve(null);
    reader.readAsDataURL(file);
  });
}

function pnpRemoveAttachment(idx) { PNP.attachments.splice(idx, 1); renderPNPAttachments(); }

function pnpRemoveImage(idx) { PNP.images.splice(idx, 1); renderPNPImages(); }

function pnpRemoveTag(i) { PNP.tags.splice(i, 1); renderPNPTags(); }

function renderPNPAttachments() {
  document.getElementById('pp-attachments-list').innerHTML = PNP.attachments.map((a, i) => `
    <div class="pp-attach-row"><span><i class="fas fa-file"></i> ${escHtml(a.name)}</span><span class="pp-attach-actions">${a.url?`<button class="pp-attach-view" onclick="window.open('${a.url}','_blank')" title="View"><i class="fas fa-eye"></i></button>`:''}<button onclick="pnpRemoveAttachment(${i})" title="Remove"><i class="fas fa-times"></i></button></span></div>`).join('');
}

function renderPNPImages() {
  document.getElementById('pp-images-preview').innerHTML = PNP.images.map((src, i) => `
    <div class="pp-thumb"><img src="${src}"><button class="pp-thumb-remove" onclick="pnpRemoveImage(${i})">✕</button></div>`).join('');
}

function renderPNPTags() {
  document.getElementById('pp-tags-chips').innerHTML = PNP.tags.map((t, i) => `
    <span class="pp-tag-chip">${escHtml(t)} <button onclick="pnpRemoveTag(${i})">✕</button></span>`).join('');
}

function populateProductCategoryDropdown() {
  const sel = document.getElementById('pp-category');
  if (!sel) return;
  const cur = sel.value;
  sel.innerHTML = (STATE.categories||[]).map(c => `<option value="${escHtml(c.name)}">${escHtml(c.name)}</option>`).join('') || '<option value="Other">Other</option>';
  if (cur) sel.value = cur;
}async function loadProductForEdit(id) {
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
}
