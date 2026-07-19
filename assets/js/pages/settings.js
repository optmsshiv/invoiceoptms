// ================================================================
//  assets/js/settings.js
//  Requires: common.js, shared-data.js (loaded before this file).
//
//  MPA CHANGES from the SPA version:
//  1. saveCompanySettings() called livePreview() (create.php's live
//     invoice preview) — guarded with typeof, no-ops here.
//  2. addItemType()/deleteItemType() called renderFormItems()
//     (create.php's line-item re-render) — guarded with typeof.
//  3. populateSettingsForm() in the original also filled email-setup
//     (SMTP) fields and called populateTemplateForm() — those belong
//     to email_setup.php and templates.php respectively, so this
//     version only touches the fields that actually exist on THIS
//     page (Company/Invoice/Catalog/Backup tabs).
//  4. clearAllData() — flagging an existing gap, not something
//     introduced here: in the original SPA this button only cleared
//     the in-memory STATE and re-rendered other pages' tables. It
//     never actually called a DELETE api on the server, so refreshing
//     the page brought all the data right back. Ported faithfully
//     (with typeof guards on the other pages' render calls), but
//     this button doesn't really "clear all data" — worth deciding
//     if that's intentional or worth wiring up to a real bulk-delete
//     endpoint later.
//  5. exportCSV() (Backup tab's "Export Invoices" button) is
//     invoices.js's function, not loaded here — renamed to
//     settingsExportCSV() with its own small local copy.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['invoices', 'clients', 'products', 'payments', 'settings']);
  // applyBusinessTypeLabels()/currentBizLabels() below use
  // STATE.settings.businessType (camelCase, runtime-only, for live
  // preview as the dropdown changes) — seed it from the real saved
  // value (business_type, snake_case, from the DB/API) so labels are
  // correct on first load, not just after the user touches the dropdown.
  STATE.settings.businessType = STATE.settings.business_type || 'both';
  populateSettingsForm();
});

// Added retroactively — currentBizLabels()/applyBusinessTypeLabels()
// below reference this; was missing entirely from the original
// extraction (defined earlier in the SPA than where these two
// functions were pulled from, so it wasn't picked up).
const BUSINESS_TYPE_LABELS = {
  service: { nav: 'Services',            addBtn: 'Add Service', nameCol: 'Service Name', namePlaceholder: 'Service name *', searchPlaceholder: 'Search services…' },
  product: { nav: 'Products',            addBtn: 'Add Product', nameCol: 'Product Name', namePlaceholder: 'Product name *', searchPlaceholder: 'Search products…' },
  both:    { nav: 'Services / Products', addBtn: 'Add Item',    nameCol: 'Item Name',    namePlaceholder: 'Item name *',    searchPlaceholder: 'Search…' },
};

function settingsTab(name, btn) {
  document.querySelectorAll('.stab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.stab-btn').forEach(b => b.classList.remove('active'));
  const pane = document.getElementById('stab-' + name);
  if (pane) pane.classList.add('active');
  if (btn) btn.classList.add('active');
  else { const b = document.querySelector(`.stab-btn[onclick*="'${name}'"]`); if (b) b.classList.add('active'); }
}

async function saveCompanySettings() {
  const payload = {
    company_name: document.getElementById('sc-name')?.value || '',
    company_gst: document.getElementById('sc-gst')?.value || '',
    company_phone: document.getElementById('sc-phone')?.value || '',
    company_email: document.getElementById('sc-email')?.value || '',
    company_website: document.getElementById('sc-web')?.value || '',
    invoice_prefix: document.getElementById('sc-prefix')?.value || STATE.settings.prefix || '',
    estimate_prefix: document.getElementById('sc-estimate-prefix')?.value || STATE.settings.estPrefix || '',
    company_upi: document.getElementById('sc-upi')?.value || '',
    company_address: document.getElementById('sc-addr')?.value || '',
    company_logo: document.getElementById('sc-logo')?.value || STATE.settings.logo || '',
    company_sign: document.getElementById('sc-sign')?.value || STATE.settings.signature || '',
    company_bank: document.getElementById('sc-bank')?.value || STATE.settings.defaultBank || '',
    default_currency: document.getElementById('sc-cur')?.value || STATE.settings.currency || '₹',
  };
  Object.assign(STATE.settings, {
    company: payload.company_name, gst: payload.company_gst, phone: payload.company_phone,
    email: payload.company_email, website: payload.company_website, prefix: payload.invoice_prefix,
    estPrefix: payload.estimate_prefix, upi: payload.company_upi, address: payload.company_address,
    logo: payload.company_logo || STATE.settings.logo,
    signature: payload.company_sign || STATE.settings.signature,
    defaultBank: payload.company_bank || STATE.settings.defaultBank,
    currency: payload.default_currency || STATE.settings.currency,
  });
  try {
    await api('/api/settings.php', 'POST', payload);
    if (typeof livePreview === 'function') livePreview();
    toast('✅ Settings saved!', 'success');
    if (payload.company_logo) {
      const lp = document.getElementById('sc-logo-preview');
      if (lp) lp.innerHTML = `<div style="display:inline-flex;align-items:center;gap:8px;padding:5px 10px;background:var(--teal-bg);border-radius:8px;border:1px solid var(--border);margin-top:4px"><img src="${payload.company_logo}" style="height:30px;max-width:110px;object-fit:contain"><span style="font-size:10px;color:var(--muted)">✓ Saved</span></div>`;
    }
    if (payload.company_sign) {
      const sp = document.getElementById('sc-sign-preview');
      if (sp) sp.innerHTML = `<div style="display:inline-flex;align-items:center;gap:8px;padding:5px 10px;background:#1a1a2e;border-radius:8px;border:1px solid var(--border);margin-top:4px"><img src="${payload.company_sign}" style="height:30px;max-width:110px;object-fit:contain"><span style="font-size:10px;color:#aaa">✓ Saved</span></div>`;
    }
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

async function saveInvoiceDefaults() {
  const payload = {
    default_gst: document.getElementById('sd-gst')?.value ?? '0',
    due_days: document.getElementById('sd-due')?.value || '15',
    active_template: document.getElementById('sd-tpl')?.value || '2',
    invoice_prefix: STATE.settings.prefix || 'OT-',
    estimate_prefix: STATE.settings.estPrefix || 'QT-',
    default_currency: document.getElementById('sd-currency')?.value || '₹',
    default_bank: STATE.settings.defaultBank || '',
    default_notes: document.getElementById('sd-notes')?.value || '',
    default_tnc: document.getElementById('sd-tnc')?.value || '',
  };
  STATE.settings.defaultGST = parseInt(payload.default_gst ?? '0');
  STATE.settings.dueDays = parseInt(payload.due_days);
  STATE.settings.activeTemplate = payload.active_template || STATE.settings.activeTemplate || '2';
  if (payload.default_notes !== undefined) STATE.settings.defaultNotes = payload.default_notes;
  if (payload.default_tnc !== undefined) STATE.settings.defaultTnC = payload.default_tnc;
  if (payload.default_currency) STATE.settings.currency = payload.default_currency;
  try {
    await api('/api/settings.php', 'POST', payload);
    toast('✅ Invoice defaults saved!', 'success');
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

// ── Category management ───────────────────────────────────────
function renderCategoryList() {
  const el = document.getElementById('cat-list'); if (!el) return;
  if (!STATE.categories.length) { el.innerHTML = '<span style="color:var(--muted);font-size:12px">No categories yet.</span>'; return; }
  el.innerHTML = STATE.categories.map((c, i) => {
    const tc = getCatTextColor(c.color);
    return `<div style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px 5px 12px;border-radius:20px;background:${c.color};color:${tc};font-size:12px;font-weight:700;box-shadow:0 1px 4px ${c.color}60">
      ${c.name}
      <button onclick="deleteCategory(${i})" style="background:none;border:none;cursor:pointer;color:${tc};opacity:.7;font-size:13px;line-height:1;padding:0 0 0 2px" title="Remove">×</button>
    </div>`;
  }).join('');
}
async function addCategory() {
  const nameEl = document.getElementById('cat-new-name');
  const colorEl = document.getElementById('cat-new-color');
  const name = nameEl?.value.trim();
  if (!name) { toast('⚠️ Enter a category name', 'warning'); return; }
  if (STATE.categories.find(c => c.name.toLowerCase() === name.toLowerCase())) { toast('⚠️ Category already exists', 'warning'); return; }
  STATE.categories.push({ name, color: colorEl?.value || '#00897B' });
  nameEl.value = '';
  renderCategoryList();
  updateProductCatDropdowns();
  await saveCategories();
  toast('✅ Category added!', 'success');
}
async function deleteCategory(idx) {
  STATE.categories.splice(idx, 1);
  renderCategoryList();
  updateProductCatDropdowns();
  await saveCategories();
  toast('🗑️ Category removed', 'info');
}
async function saveCategories() {
  try { await api('/api/settings.php', 'POST', { product_categories: JSON.stringify(STATE.categories) }); }
  catch (e) { console.warn('Cat save err', e); }
}
function updateProductCatDropdowns() {
  const opts = STATE.categories.map(c => `<option value="${c.name}">${c.name}</option>`).join('');
  document.querySelectorAll('.cat-select').forEach(el => { const cur = el.value; el.innerHTML = opts; el.value = cur; });
  const filter = document.getElementById('productCatFilter');
  if (filter) filter.innerHTML = `<option value="">All Categories</option>${opts}`;
}

// ── Expense category management ───────────────────────────────
function renderExpenseCategoryList() {
  const el = document.getElementById('exp-cat-list'); if (!el) return;
  const cats = STATE.expenseCategories || [];
  if (!cats.length) { el.innerHTML = '<span style="color:var(--muted);font-size:12px">No categories yet.</span>'; return; }
  el.innerHTML = cats.map((c, i) => {
    const bg = pastelBg(c.color);
    return `<div style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px 5px 12px;border-radius:20px;background:${bg};color:${c.color};font-size:12px;font-weight:700">
      ${c.name}
      <button onclick="deleteExpenseCategory(${i})" style="background:none;border:none;cursor:pointer;color:${c.color};opacity:.7;font-size:13px;line-height:1;padding:0 0 0 2px" title="Remove">×</button>
    </div>`;
  }).join('');
}
async function addExpenseCategory() {
  const nameEl = document.getElementById('exp-cat-new-name');
  const colorEl = document.getElementById('exp-cat-new-color');
  const name = nameEl?.value.trim();
  if (!name) { toast('⚠️ Enter a category name', 'warning'); return; }
  if (!STATE.expenseCategories) STATE.expenseCategories = [];
  if (STATE.expenseCategories.find(c => c.name.toLowerCase() === name.toLowerCase())) { toast('⚠️ Category already exists', 'warning'); return; }
  STATE.expenseCategories.push({ name, color: colorEl?.value || '#1976D2' });
  nameEl.value = '';
  renderExpenseCategoryList();
  updateExpenseCatDropdowns();
  await saveExpenseCategories();
  toast('✅ Category added!', 'success');
}
async function deleteExpenseCategory(idx) {
  STATE.expenseCategories.splice(idx, 1);
  renderExpenseCategoryList();
  updateExpenseCatDropdowns();
  await saveExpenseCategories();
  toast('🗑️ Category removed', 'info');
}
async function saveExpenseCategories() {
  try { await api('/api/settings.php', 'POST', { expense_categories: JSON.stringify(STATE.expenseCategories) }); }
  catch (e) { console.warn('Exp cat save err', e); }
}
function updateExpenseCatDropdowns() {
  const cats = STATE.expenseCategories || [];
  const opts = cats.map(c => `<option>${escHtml(c.name)}</option>`).join('');
  const sel = document.getElementById('exp-category');
  if (sel) { const cur = sel.value; sel.innerHTML = `<option value="">— Select —</option>${opts}`; sel.value = cur; }
  const filter = document.getElementById('exp-cat-filter');
  if (filter) { const cur = filter.value; filter.innerHTML = `<option value="">All Categories</option>${opts}`; filter.value = cur; }
}

// ── Item type management ──────────────────────────────────────
function renderItemTypeList() {
  const el = document.getElementById('item-type-list'); if (!el) return;
  const types = STATE.itemTypes || [];
  if (!types.length) { el.innerHTML = '<span style="color:var(--muted);font-size:12px">No types yet.</span>'; return; }
  el.innerHTML = types.map((t, i) => {
    const bg = t.color || '#757575';
    const tc = getCatTextColor(bg);
    const isDefault = ['Service', 'Product', 'Labour', 'Other'].includes(t.name);
    return `<div style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px 5px 12px;border-radius:20px;background:${bg};color:${tc};font-size:12px;font-weight:700;box-shadow:0 1px 4px ${bg}60">
      ${t.name}${isDefault ? ' <span style="font-size:9px;opacity:.7">(default)</span>' : ''}
      ${!isDefault ? `<button onclick="deleteItemType(${i})" style="background:none;border:none;cursor:pointer;color:${tc};opacity:.7;font-size:13px;line-height:1;padding:0 0 0 2px" title="Remove">×</button>` : ''}
    </div>`;
  }).join('');
}
async function addItemType() {
  const nameEl = document.getElementById('itype-new-name');
  const colorEl = document.getElementById('itype-new-color');
  const name = nameEl?.value.trim();
  if (!name) { toast('⚠️ Enter a type name', 'warning'); return; }
  if ((STATE.itemTypes || []).find(t => t.name.toLowerCase() === name.toLowerCase())) { toast('⚠️ Type already exists', 'warning'); return; }
  if (!STATE.itemTypes) STATE.itemTypes = [];
  STATE.itemTypes.push({ name, color: colorEl?.value || '#1976D2' });
  if (nameEl) nameEl.value = '';
  renderItemTypeList();
  await saveItemTypes();
  if (typeof renderFormItems === 'function') renderFormItems();
  toast('✅ Item type added!', 'success');
}
async function deleteItemType(idx) {
  const t = STATE.itemTypes[idx];
  if (['Service', 'Product', 'Labour', 'Other'].includes(t?.name)) { toast('⚠️ Default types cannot be deleted', 'warning'); return; }
  STATE.itemTypes.splice(idx, 1);
  renderItemTypeList();
  await saveItemTypes();
  if (typeof renderFormItems === 'function') renderFormItems();
  toast('🗑️ Item type removed', 'info');
}
async function saveItemTypes() {
  try { await api('/api/settings.php', 'POST', { item_types: JSON.stringify(STATE.itemTypes) }); }
  catch (e) { console.warn('ItemType save err', e); }
}

// ── Logo / signature upload ────────────────────────────────────
async function handleLogoUpload(input, targetId, previewId) {
  const file = input.files[0]; if (!file) return;
  if (file.size > 3 * 1024 * 1024) { toast('⚠️ Max 3MB', 'warning'); return; }
  const typeMap = { 'f-company-logo': 'logo', 'sc-logo': 'logo', 'f-signature': 'signature', 'sc-sign': 'signature', 'f-client-logo': 'client_logo', 'f-qr': 'qr' };
  const fd = new FormData();
  fd.append('file', file);
  fd.append('type', typeMap[targetId] || 'logo');
  try {
    const res = await fetch('api/upload.php', { method: 'POST', body: fd });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch (e) { throw new Error('Upload failed: server returned HTML'); }
    if (!data.success) throw new Error(data.error || 'Upload failed');
    const el = document.getElementById(targetId);
    if (el) { el.value = data.url; el.dispatchEvent(new Event('input')); }
    if (targetId === 'sc-logo' || targetId === 'f-company-logo') STATE.settings.logo = data.url;
    else if (targetId === 'sc-sign' || targetId === 'f-signature') STATE.settings.signature = data.url;
    if (previewId) {
      const prev = document.getElementById(previewId);
      if (prev) {
        const isSign = previewId.includes('sign');
        prev.innerHTML = `<div style="display:inline-flex;align-items:center;gap:8px;padding:6px 10px;background:${isSign ? '#1a1a2e' : 'var(--teal-bg)'};border-radius:8px;border:1px solid var(--border)">
          <img src="${data.url}" style="height:${isSign ? '36' : '32'}px;max-width:120px;object-fit:contain;border-radius:4px">
          <span style="font-size:11px;color:var(--muted)">${file.name}</span>
          <button onclick="clearLogoField('${targetId}','${previewId}')" style="border:none;background:none;cursor:pointer;color:var(--red);font-size:13px"><i class="fas fa-times"></i></button>
        </div>`;
      }
    }
    toast('✅ Uploaded!', 'success');
  } catch (e) {
    const reader = new FileReader();
    reader.onload = ev => {
      const el = document.getElementById(targetId);
      if (el) { el.value = ev.target.result; el.dispatchEvent(new Event('input')); }
      toast('✅ Image loaded', 'success');
    };
    reader.readAsDataURL(file);
    console.warn('Server upload failed, using base64:', e.message);
  }
}
function clearLogoField(targetId, previewId) {
  const el = document.getElementById(targetId); if (el) { el.value = ''; el.dispatchEvent(new Event('input')); }
  const prev = document.getElementById(previewId); if (prev) prev.innerHTML = '';
}

// ── Populate form from loaded settings ────────────────────────
function populateSettingsForm() {
  const s = STATE.settings;
  const set = (id, val) => { const e = document.getElementById(id); if (e && val !== undefined && val !== null) e.value = val; };
  set('sc-name', s.company); set('sc-gst', s.gst); set('sc-phone', s.phone);
  set('sc-email', s.email); set('sc-web', s.website);
  renderCategoryList(); renderItemTypeList(); renderExpenseCategoryList();
  set('sc-prefix', s.prefix); set('sc-estimate-prefix', s.estPrefix || '');
  set('sc-upi', s.upi); set('sc-addr', s.address);
  set('sc-logo', s.logo); set('sc-sign', s.signature);
  set('sc-bank', s.defaultBank || '');
  const _scCur = document.getElementById('sc-cur'); if (_scCur && s.currency) _scCur.value = s.currency;
  set('sd-due', s.dueDays);
  // NOTE: templates.php isn't built yet — populateTemplateForm() would
  // restore the invoice template color/logo customization UI, which
  // doesn't exist on this page.
  if (typeof populateTemplateForm === 'function') populateTemplateForm();
  if (s.logo) {
    const prev = document.getElementById('sc-logo-preview');
    if (prev) prev.innerHTML = `<div style="display:inline-flex;align-items:center;gap:8px;padding:6px 10px;background:var(--teal-bg);border-radius:8px;border:1px solid var(--border);margin-top:4px"><img src="${s.logo}" style="height:32px;max-width:120px;object-fit:contain;border-radius:4px"><span style="font-size:11px;color:var(--muted)">Current logo</span></div>`;
  }
  if (s.signature) {
    const sprev = document.getElementById('sc-sign-preview');
    if (sprev) sprev.innerHTML = `<div style="display:inline-flex;align-items:center;gap:8px;padding:6px 10px;background:#1a1a2e;border-radius:8px;border:1px solid var(--border);margin-top:4px"><img src="${s.signature}" style="height:36px;max-width:120px;object-fit:contain;border-radius:4px"><span style="font-size:11px;color:#aaa">Current signature</span></div>`;
  }
  ['sd-gst', 'sd-tpl', 'sd-currency'].forEach(id => {
    const e = document.getElementById(id); if (!e) return;
    if (id === 'sd-gst') e.value = String(s.defaultGST ?? 18);
    if (id === 'sd-tpl') e.value = String(s.activeTemplate || '2');
    if (id === 'sd-currency') e.value = s.currency || '₹';
  });
}

// ── Backup tab ─────────────────────────────────────────────────
function exportAllJSON() {
  const data = JSON.stringify({ invoices: STATE.invoices, clients: STATE.clients, products: STATE.products, payments: STATE.payments, settings: STATE.settings }, null, 2);
  downloadFile('optms_backup.json', data, 'application/json');
  toast('✅ Full backup exported!', 'success');
}

// Local copy — invoices.js's exportCSV() isn't loaded on this page.
function settingsExportCSV() {
  const headers = ['Invoice#', 'Client', 'Service', 'Issue Date', 'Due Date', 'Amount', 'Status'];
  const rows = STATE.invoices.map(inv => {
    const c = STATE.clients.find(x => x.id === inv.client);
    return [inv.num, c?.name || '', inv.service, inv.issued, inv.due, inv.amount, inv.status].map(v => `"${v}"`).join(',');
  });
  downloadFile('optms_invoices.csv', [headers.join(','), ...rows].join('\n'), 'text/csv');
  toast('✅ CSV exported!', 'success');
}

function importData() {
  toast('ℹ️ Import: paste JSON data or drag file. Feature coming soon!', 'info');
}

// NOTE (ported as-is, not an MPA regression): this only ever cleared
// STATE in-memory and re-rendered other pages' tables — it never
// called a real DELETE endpoint, so a page refresh brought all data
// back even in the original SPA. Flagging in case that's worth wiring
// up to a real bulk-delete API later.
async function clearAllData() {
  const result = await Swal.fire({ title: 'Delete ALL Data?', html: 'This will permanently delete <b>all invoices, clients, and payments</b>.<br>This cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, Delete Everything', cancelButtonText: 'Cancel', confirmButtonColor: '#E53935', customClass: { popup: 'swal-compact' } });
  if (result.isConfirmed) {
    STATE.invoices = []; STATE.clients = []; STATE.payments = [];
    if (typeof renderInvoicesTable === 'function') renderInvoicesTable();
    if (typeof renderClients === 'function') renderClients();
    if (typeof renderPayments === 'function') renderPayments();
    if (typeof renderDashRecent === 'function') renderDashRecent();
    if (typeof renderDonutChart === 'function') renderDonutChart();
    toast('🗑️ All data cleared!', 'warning');
  }
}

// ============================================================
// Added during MPA conversion (Phase 7) — settings.php also embeds
// a WhatsApp message log tab (renderWALog/exportMsgLog, both already
// in wa-shared.js/msglog page — settings.php loads wa-shared.js
// alongside this file) and an invoice CSV export button.
// ============================================================
function applyBusinessTypeLabels(type) {
  if (type) STATE.settings.businessType = type;
  const L = currentBizLabels();
  const navEl = document.getElementById('nav-products-label'); if (navEl) navEl.textContent = L.nav;
  const btnEl = document.getElementById('prodAddBtnLabel');    if (btnEl) btnEl.textContent = L.addBtn;
  const colEl = document.getElementById('prodNameColLabel');   if (colEl) colEl.textContent = L.nameCol;
  const searchEl = document.getElementById('productSearch');  if (searchEl) searchEl.placeholder = L.searchPlaceholder;
  const nameInput = document.getElementById('np-name');       if (nameInput) nameInput.placeholder = L.namePlaceholder;
  const salesNav = document.getElementById('nav-sales-item'); if (salesNav) salesNav.style.display = STATE.settings.businessType === 'product' ? 'flex' : 'none';
  const custNav = document.getElementById('nav-customers-item'); if (custNav) custNav.style.display = STATE.settings.businessType === 'product' ? 'flex' : 'none';
}

function currentBizLabels() {
  return BUSINESS_TYPE_LABELS[STATE.settings.businessType] || BUSINESS_TYPE_LABELS.both;
}