// ================================================================
//  assets/js/shared-data.js
//  Loaded on any page that deals with invoices/clients/payments
//  (dashboard, invoices, reminders, whatsapp, reports, aging, tax…).
//  Extracted from the old index.php SPA's global STATE + helpers.
//
//  MPA CHANGE: the SPA had one loadAllData() that fetched every
//  data type on first load, since the whole app lived in one page.
//  That doesn't make sense per-page anymore (e.g. clients.php
//  doesn't need payments). loadCoreData() below fetches only the
//  keys a given page actually asks for.
// ================================================================

const STATE = {
  invoices: [], clients: [], products: [], payments: [],
  creditNotes: [], suppliers: [], purchases: [], activity: [], expenses: [],
  settings: {}, filteredInvoices: [],
  currentPage: 1, invoicesPerPage: 10, sortField: null, sortDir: 'asc',
  _clientFilter: '', activeMenuInvoiceId: null,
  // Defaults — overridden by whatever's saved in settings.product_categories
  // / settings.item_types once loadCoreData(['settings']) runs.
  itemTypes: [
    { name: 'Service', color: '#00897B' },
    { name: 'Product', color: '#1976D2' },
    { name: 'Labour',  color: '#E65100' },
    { name: 'Other',   color: '#757575' },
  ],
  categories: [
    { name: 'Web Development', color: '#1976D2' },
    { name: 'Mobile App',      color: '#7B1FA2' },
    { name: 'SEO / Marketing', color: '#F57F17' },
    { name: 'Design',          color: '#E53935' },
    { name: 'Hosting',         color: '#00897B' },
    { name: 'Consulting',      color: '#455A64' },
    { name: 'Other',           color: '#757575' },
  ],
  expenseCategories: [
    { name: 'Software / SaaS', color: '#1976D2' },
    { name: 'Hardware',        color: '#7B1FA2' },
    { name: 'Travel',          color: '#E65100' },
    { name: 'Office Supplies', color: '#388E3C' },
    { name: 'Marketing',       color: '#C62828' },
    { name: 'Salary',          color: '#455A64' },
    { name: 'Utilities',       color: '#F57F17' },
    { name: 'Other',           color: '#757575' },
  ],
};

// ── Locale / formatting helpers (unchanged from the SPA) ─────────
function _moneyLocale() {
  const sym = (STATE.settings && STATE.settings.currency) || '₹';
  if (sym === '$') return 'en-US';
  if (sym === '€') return 'de-DE';
  return 'en-IN';
}
function fmt_money(n, sym) {
  const s = sym !== undefined ? sym : ((STATE.settings && STATE.settings.currency) || '₹');
  return s + parseFloat(n || 0).toLocaleString(_moneyLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmt_date(d) { return d.toISOString().split('T')[0]; }
function fmt_date_disp(d) {
  if (!d) return '—';
  const dt = new Date(d);
  if (isNaN(dt)) return d;
  return String(dt.getDate()).padStart(2, '0') + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + dt.getFullYear();
}
function fmt_money_sym(n, sym) { return (sym || '₹') + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function fmt_date_l(dateStr, opts) {
  if (!dateStr) return '';
  opts = opts || { day: '2-digit', month: 'short' };
  return new Date(dateStr).toLocaleDateString(_moneyLocale(), opts);
}
function isValidImg(src) {
  if (!src || typeof src !== 'string') return false;
  const s = src.trim();
  return s.startsWith('data:image') || s.startsWith('http://') || s.startsWith('https://');
}
function getCatColor(name) {
  const cat = STATE.categories.find(c => c.name === name);
  return cat ? cat.color : '#757575';
}
function getCatTextColor(hex) {
  const r = parseInt(hex.slice(1, 3), 16), g = parseInt(hex.slice(3, 5), 16), b = parseInt(hex.slice(5, 7), 16);
  return (0.299 * r + 0.587 * g + 0.114 * b) > 160 ? '#222' : '#fff';
}
function pastelBg(hex) {
  hex = (hex || '#757575').replace('#', '');
  if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
  const r = parseInt(hex.substr(0, 2), 16) || 0, g = parseInt(hex.substr(2, 2), 16) || 0, b = parseInt(hex.substr(4, 2), 16) || 0;
  const mix = c => Math.round(c + (255 - c) * 0.85).toString(16).padStart(2, '0');
  return `#${mix(r)}${mix(g)}${mix(b)}`;
}
function getExpCatColor(name) {
  const cat = (STATE.expenseCategories || []).find(c => c.name === name);
  return cat ? cat.color : '#757575';
}

// Used by clients.js (client tags) and team.js (team member tags)
const TAG_PALETTE = [
  { bg: '#EDE9FE', text: '#5B21B6', border: '#C4B5FD' },
  { bg: '#E0F2FE', text: '#0369A1', border: '#BAE6FD' },
  { bg: '#DCFCE7', text: '#166534', border: '#BBF7D0' },
  { bg: '#FEF3C7', text: '#92400E', border: '#FDE68A' },
  { bg: '#FFE4E6', text: '#9F1239', border: '#FECDD3' },
  { bg: '#F0FDF4', text: '#14532D', border: '#86EFAC' },
  { bg: '#FFF7ED', text: '#9A3412', border: '#FDBA74' },
  { bg: '#EFF6FF', text: '#1E40AF', border: '#BFDBFE' },
];
function _tagColor(tag) {
  let hash = 0;
  for (let i = 0; i < tag.length; i++) hash = tag.charCodeAt(i) + ((hash << 5) - hash);
  return TAG_PALETTE[Math.abs(hash) % TAG_PALETTE.length];
}
// Escapes text before it's injected into innerHTML — prevents stored-XSS
// from product/client names, categories, HSN codes, etc.
function escHtml(v) {
  return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// ── Invoice normalization (unchanged from the SPA) ────────────────
function normalizeInvoice(inv) {
  if (!inv || typeof inv !== 'object') return inv;
  if (!inv.status || inv.status === '') {
    const num = inv.num || inv.invoice_number || '';
    const estPfx = STATE.settings.estPrefix || ('QT-' + new Date().getFullYear() + '-');
    inv.status = num.startsWith(estPfx) || num.startsWith('QT-') ? 'Estimate' : 'Draft';
  }
  if (inv.pdf_options && typeof inv.pdf_options === 'string') {
    try { inv.pdf_options = JSON.parse(inv.pdf_options); } catch (e) { inv.pdf_options = null; }
  }
  if (inv.items && typeof inv.items === 'string') {
    try { inv.items = JSON.parse(inv.items); } catch (e) { inv.items = []; }
  }
  if (!Array.isArray(inv.items)) inv.items = [];
  if (!inv.clientName && inv.client_name) inv.clientName = inv.client_name;
  if (!inv.client_name && inv.clientName) inv.client_name = inv.clientName;
  if (!inv.bank && inv.bank_details) inv.bank = inv.bank_details;
  if (!inv.tnc && inv.terms) inv.tnc = inv.terms;
  if (!inv.cancel_reason) inv.cancel_reason = inv.cancel_reason || '';
  if (!inv.notes) {
    const _defNotes = STATE.settings.defaultNotes || (STATE.settings.company ? `Thank you for choosing ${STATE.settings.company}.` : '');
    inv.notes = _defNotes.replace(/\{due_days\}/g, STATE.settings.dueDays || 15);
  }
  if (inv.status === 'Pending') {
    const dueField = inv.due || inv.due_date;
    if (dueField) {
      const dueDate = new Date(dueField);
      dueDate.setHours(23, 59, 59, 999);
      if (!isNaN(dueDate) && dueDate < new Date()) {
        inv.status = 'Overdue';
        inv._autoOverdue = true;
      }
    }
  }
  return inv;
}

// ── Persist auto-overdue status changes to DB (silent, best-effort) ──
async function syncOverdueToDb(invoices) {
  const toUpdate = invoices.filter(i => i._autoOverdue && i.id);
  if (!toUpdate.length) return;
  await Promise.allSettled(
    toUpdate.map(inv =>
      api('api/invoices.php?id=' + parseInt(inv.id), 'PATCH', { status: 'Overdue' })
        .then(() => { delete inv._autoOverdue; })
        .catch(() => {})
    )
  );
  console.log('[AutoOverdue] Synced ' + toUpdate.length + ' invoice(s) to Overdue in DB');
}

// ── WhatsApp message log helper (unchanged from the SPA) ─────────
const WA_LOG = {
  async fetchLog() {
    try {
      const r = await fetch('/api/wa_log.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const d = await r.json();
      return d.success ? (d.data || []) : [];
    } catch (e) {
      console.error('[WA Log] Fetch error:', e);
      toast('❌ Could not load WA logs: ' + e.message, 'error');
      return [];
    }
  },
  formatTimeRelative(ts) {
    if (!ts) return '';
    try {
      const diff = Date.now() - new Date(ts).getTime();
      const s = Math.floor(diff / 1000), m = Math.floor(s / 60), h = Math.floor(m / 60), days = Math.floor(h / 24);
      if (s < 60) return 'just now';
      if (m < 60) return m + ' min' + (m > 1 ? 's' : '') + ' ago';
      if (h < 24) return h + ' hour' + (h > 1 ? 's' : '') + ' ago';
      if (days < 6) return days + ' day' + (days > 1 ? 's' : '') + ' ago';
      return new Date(ts).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true, timeZone: 'Asia/Kolkata' });
    } catch (e) { return ts; }
  },
  // NOTE: refreshTable() called renderWALog(), which lives on the
  // msglog page — not defined here. Wire this up when msglog.js is built.
  refreshTable: async function () { if (typeof renderWALog === 'function') await renderWALog(); },
  async clearLogs() {
    const confirmDialog = await Swal.fire({
      title: 'Clear all logs?',
      text: 'This will permanently delete all WhatsApp message logs. This cannot be undone.',
      icon: 'warning', showCancelButton: true,
      confirmButtonText: 'Yes, clear all', cancelButtonText: 'Cancel',
      confirmButtonColor: '#E53935', customClass: { popup: 'swal-compact' },
    });
    if (!confirmDialog.isConfirmed) return;
    try {
      const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Kolkata' });
      const confirmCode = 'CLEAR_WA_LOG_' + today;
      const r = await fetch('/api/wa_log.php', {
        method: 'DELETE', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ confirm_code: confirmCode }),
      });
      const d = await r.json();
      if (d.success) { toast('✅ All logs cleared', 'success'); await WA_LOG.refreshTable(); }
      else toast('❌ Failed: ' + (d.error || 'Unknown error'), 'error');
    } catch (e) { toast('❌ Error: ' + e.message, 'error'); }
  },
};

// ── Activity log (unchanged from the SPA) ─────────────────────────
// Used across invoices/clients/payments pages whenever something
// changes, so it lives here rather than in any one page's JS.
function logActivity(type, label, detail, invoiceId) {
  const entry = {
    id: Date.now() + Math.random(), type, label,
    detail: detail || '', invoiceId: invoiceId || null,
    ts: new Date().toISOString(),
  };
  STATE.activity.unshift(entry);
  if (STATE.activity.length > 500) STATE.activity = STATE.activity.slice(0, 500);
  api('api/activity.php', 'POST', {
    type, label, detail: detail || '',
    invoice_id: invoiceId ? parseInt(invoiceId) : null,
  }).catch(e => console.warn('activity log write failed:', e.message));
  // NOTE: renderActivityLog() lives on the activity page, not here.
  if (typeof renderActivityLog === 'function' && document.getElementById('page-activity')?.classList.contains('active')) {
    renderActivityLog();
  }
}

// ══════════════════════════════════════════
// PER-PAGE DATA LOADER
// Pass only the keys the current page actually needs, e.g.:
//   await loadCoreData(['invoices', 'clients', 'payments', 'settings']);
// ══════════════════════════════════════════
const CORE_ENDPOINTS = {
  invoices:    'api/invoices.php',
  clients:     'api/clients.php',
  products:    'api/products.php',
  payments:    'api/payments.php',
  settings:    'api/settings.php',
  creditNotes: 'api/credit_notes.php',
  suppliers:   'api/suppliers.php',
  purchases:   'api/purchases.php',
  expenses:    'api/expenses.php',
};

async function loadCoreData(keys) {
  const fetches = keys.map(k => api(CORE_ENDPOINTS[k]).catch(() => ({ data: [] })));
  const results = await Promise.all(fetches);

  keys.forEach((key, idx) => {
    const payload = results[idx];
    if (key === 'invoices') {
      STATE.invoices = Array.isArray(payload.data) ? payload.data.map(normalizeInvoice) : [];
      STATE.filteredInvoices = [...STATE.invoices];
    } else if (key === 'settings') {
      if (payload.data) {
        const s = payload.data;
        STATE.settings.currency    = s.currency || STATE.settings.currency || '₹';
        STATE.settings.company     = s.company_name || STATE.settings.company;
        STATE.settings.estPrefix   = s.estimate_prefix || STATE.settings.estPrefix;
        STATE.settings.dueDays     = s.due_days || STATE.settings.dueDays;
        STATE.settings.defaultNotes = s.default_notes || STATE.settings.defaultNotes;
        // Additional fields used by receipts, reports, and other pages
        STATE.settings.gst          = s.gst_number || s.gst || '';
        STATE.settings.phone        = s.company_phone || s.phone || '';
        STATE.settings.email        = s.company_email || s.email || '';
        STATE.settings.website      = s.company_website || s.website || '';
        STATE.settings.prefix       = s.invoice_prefix || STATE.settings.prefix || 'OT-' + new Date().getFullYear() + '-';
        STATE.settings.upi          = s.upi_id || s.upi || '';
        STATE.settings.address      = s.company_address || s.address || '';
        STATE.settings.logo         = s.company_logo || s.logo || '';
        STATE.settings.sign         = s.company_sign || s.sign || '';
        STATE.settings.defaultBank  = s.default_bank || s.bank_details || '';
        STATE.settings.tnc          = s.default_tnc || s.tnc || '';
        STATE.settings.activeTemplate = s.active_template || '2';
        STATE.settings.defaultGST   = parseFloat(s.default_gst) || 18;
        // WA config — needed by the dashboard's WhatsApp card
        STATE.settings.wa = {
          token: s.wa_token || '', pid: s.wa_pid || '',
          msg_mode: s.wa_msg_mode || 'session',
          auto_inv: s.wa_auto_inv !== undefined ? s.wa_auto_inv : '0',
          auto_paid: s.wa_auto_paid !== undefined ? s.wa_auto_paid : '1',
          auto_partial: s.wa_auto_partial !== undefined ? s.wa_auto_partial : '1',
          auto_remind: s.wa_auto_remind !== undefined ? s.wa_auto_remind : '1',
          auto_overdue: s.wa_auto_overdue !== undefined ? s.wa_auto_overdue : '1',
          auto_followup: s.wa_auto_followup !== undefined ? s.wa_auto_followup : '0',
        };
        // Category / item-type lists — saved as JSON strings in settings.
        if (s.product_categories) {
          try { const cats = JSON.parse(s.product_categories); if (Array.isArray(cats) && cats.length) STATE.categories = cats; } catch (e) {}
        }
        if (s.item_types) {
          try { const iTypes = JSON.parse(s.item_types); if (Array.isArray(iTypes) && iTypes.length) STATE.itemTypes = iTypes; } catch (e) {}
        }
        if (s.expense_categories) {
          try { const eCats = JSON.parse(s.expense_categories); if (Array.isArray(eCats) && eCats.length) STATE.expenseCategories = eCats; } catch (e) {}
        }
      }
    } else {
      STATE[key] = Array.isArray(payload.data) ? payload.data : [];
    }
  });

  // Silently persist any Pending→Overdue changes, same as the SPA did
  if (keys.includes('invoices')) syncOverdueToDb(STATE.invoices);
}
