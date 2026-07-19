// ============================================================
// stock-txn-details.js — page-specific JS for pages/stock-txn-details.php
// Depends on: common.js, app.js, stock-shared.js
//
// NOTE: In the SPA, viewStockTxnDetails(ledgerId) read the clicked row from
// an in-memory SH_LAST_ROWS cache built by the Stock History page. In the
// MPA this page loads standalone, so it reads ledger_id + product_id from
// the URL (?ledger_id=X&product_id=Y, set by stock-history.php's link) and
// fetches the product's full ledger flow fresh — the target row is found
// inside that same fetch, so only one API call is needed.
// ============================================================

let STX_FLOW = [];
let STX_PAGE = 1;
let STX_LEDGER_ID = null;
const STX_PAGESIZE = 10;

document.addEventListener('DOMContentLoaded', async () => {
  await bootStockPageState();
  const params = new URLSearchParams(window.location.search);
  const ledgerId  = params.get('ledger_id');
  const productId = params.get('product_id');
  if (!ledgerId || !productId) {
    document.getElementById('stx-body').innerHTML =
      `<div class="pne-card" style="text-align:center;color:var(--muted);padding:30px">Missing transaction reference — go back to Stock History and try again.</div>`;
    return;
  }
  await initStockTxnDetailsPage(ledgerId, productId);
});

async function initStockTxnDetailsPage(ledgerId, productId) {
  const body = document.getElementById('stx-body');
  body.innerHTML = `<div class="pne-card" style="text-align:center;color:var(--muted);padding:30px"><i class="fas fa-spinner fa-spin"></i> Loading transaction…</div>`;

  let flow;
  try {
    const params = new URLSearchParams({ date_from: '2000-01-01', date_to: fmt_date(new Date()), product_id: productId });
    const r = await api('/api/stock_history.php?' + params.toString());
    flow = Array.isArray(r.data) ? r.data : [];
  } catch (e) {
    body.innerHTML = `<div class="pne-card" style="text-align:center;color:var(--muted);padding:30px">Could not load product history: ${escHtml(e.message)}</div>`;
    return;
  }

  const row = flow.find(r => String(r.id) === String(ledgerId));
  if (!row) {
    toast('❌ Transaction not found', 'error');
    body.innerHTML = `<div class="pne-card" style="text-align:center;color:var(--muted);padding:30px">Transaction not found — it may have been deleted.</div>`;
    return;
  }

  const typeLabel = { purchase: 'Stock In', stock_in: 'Stock In', sale: 'Stock Out', adjustment: 'Stock Adjustment' };
  const typeColor = { purchase: '#00897B', stock_in: '#00897B', sale: '#E53935', adjustment: '#E65100' };
  const refLabel  = { purchase: 'Purchase Entry', stock_in: 'Stock In Entry', sale: 'Sales Invoice', adjustment: 'Stock Adjustment' };
  const refType = row.ref_type || '';
  const prod = (STATE.products||[]).find(p => String(p.id).replace(/\D/g,'') === String(row.product_id)) || {};
  const rate = parseFloat(row.rate) || parseFloat(prod.rate) || 0;
  const qty = parseFloat(row.qty) || 0;
  const bal = parseFloat(row.running_balance) || 0;

  const kv = (label, val) => `<div style="min-width:140px"><div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:4px">${label}</div><div style="font-size:12.5px;font-weight:600">${val}</div></div>`;
  const chip = (label, val, color, bg, icon) => `
    <div style="flex:1;min-width:150px;border:1px solid var(--border);border-radius:10px;padding:14px 16px;display:flex;gap:12px;align-items:center">
      <span style="width:40px;height:40px;border-radius:9px;background:${bg};color:${color};display:flex;align-items:center;justify-content:center"><i class="fas fa-${icon}"></i></span>
      <div><div style="font-size:11px;font-weight:700;color:${color}">${label}</div><div style="font-size:16px;font-weight:800">${val}</div></div>
    </div>`;

  body.innerHTML = `
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px" class="stx-grid">
      <div class="pne-card">
        <div class="pne-card-head pne-head-green"><i class="fas fa-receipt"></i> Transaction Overview</div>
        <div style="display:flex;flex-wrap:wrap;gap:18px 28px">
          ${kv('Transaction Type', `<span style="font-size:11px;font-weight:700;color:${typeColor[refType]||'#889'};background:${typeColor[refType]||'#889'}18;padding:3px 10px;border-radius:10px">${typeLabel[refType]||'Unknown'}</span>`)}
          ${kv('Reference Type', escHtml(refLabel[refType]||'—'))}
          ${kv('Reference No.', escHtml(row.reference_no||'—'))}
          ${kv('Date', fmt_date_disp(row.movement_date))}
          ${kv('Batch / Lot No.', escHtml(row.batch_no||'—'))}
          ${kv('Warehouse', escHtml(row.warehouse||'Main Warehouse'))}
          ${kv('Remarks', escHtml(row.notes||'—'))}
        </div>
      </div>
      <div class="pne-card">
        <div class="pne-card-head pne-head-green"><i class="fas fa-box"></i> Product Information</div>
        <div class="pne-kv"><span>Product</span><strong>${escHtml(row.product_name||prod.name||'—')}</strong></div>
        <div class="pne-kv"><span>Category</span><strong>${escHtml(prod.category||'—')}</strong></div>
        <div class="pne-kv"><span>Variety</span><strong>${escHtml(prod.variety||'—')}</strong></div>
        <div class="pne-kv"><span>Grade</span><strong>${escHtml(prod.grade||'—')}</strong></div>
        <div class="pne-kv"><span>Unit</span><strong>Kg</strong></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px" class="stx-grid">
      <div class="pne-card">
        <div class="pne-card-head pne-head-green"><i class="fas fa-chart-line"></i> Quantity &amp; Value Summary</div>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          ${chip('Quantity In', row.direction==='in' ? qty.toFixed(2)+' Kg' : '—', '#00897B', '#E8F5E9', 'arrow-right-to-bracket')}
          ${chip('Quantity Out', row.direction==='out' ? qty.toFixed(2)+' Kg' : '—', '#E53935', '#FFEBEE', 'arrow-right-from-bracket')}
          ${chip('Balance Quantity', bal.toFixed(2)+' Kg', '#1976D2', '#E3F2FD', 'scale-balanced')}
        </div>
      </div>
      <div class="pne-card">
        <div class="pne-card-head pne-head-green"><i class="fas fa-indian-rupee-sign"></i> Financial Summary</div>
        <div class="pne-kv"><span>Rate (₹ / Kg)</span><strong>${rate > 0 ? fmt_money(rate) : '—'}</strong></div>
        <div class="pne-kv"><span>Transaction Value (₹)</span><strong>${rate > 0 ? fmt_money(qty * rate) : '—'}</strong></div>
        <div class="pne-kv"><span>Balance Value (₹)</span><strong>${rate > 0 ? fmt_money(bal * rate) : '—'}</strong></div>
      </div>
    </div>

    <div class="pne-card" style="margin-bottom:16px">
      <div class="pne-card-head pne-head-green"><i class="fas fa-timeline"></i> Stock Ledger Flow (This Product)</div>
      <div class="table-card" style="overflow-x:auto">
        <table class="data-table" style="min-width:820px">
          <thead><tr><th>#</th><th>Date</th><th>Transaction Type</th><th>Reference No.</th><th>In (Kg)</th><th>Out (Kg)</th><th>Balance (Kg)</th><th>Rate (₹/Kg)</th><th>Remarks</th></tr></thead>
          <tbody id="stx-flow-tbody"><tr><td colspan="9" style="text-align:center;color:var(--muted);padding:20px"><i class="fas fa-spinner fa-spin"></i> Loading product history…</td></tr></tbody>
        </table>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;flex-wrap:wrap;gap:8px">
        <div style="font-size:12px;color:var(--muted)" id="stx-flow-info"></div>
        <div style="display:flex;gap:6px;align-items:center" id="stx-flow-pager"></div>
      </div>
    </div>

    <div style="font-size:11px;color:var(--muted);background:var(--bg);border-radius:8px;padding:10px 14px"><i class="fas fa-circle-info"></i> <b>Note:</b> In (Kg) increases stock. Out (Kg) decreases stock. To correct any figure, create a Stock Adjustment — history rows are never edited directly.</div>
  `;

  STX_FLOW = flow;
  STX_LEDGER_ID = ledgerId;
  const idx = STX_FLOW.findIndex(f => String(f.id) === String(ledgerId));
  STX_PAGE = idx >= 0 ? Math.floor(idx / STX_PAGESIZE) + 1 : 1;
  renderSTXFlow();
}

function printStockTxnDetails() {
  const body = document.getElementById('stx-body');
  if (!body) return;
  const win = window.open('', '_blank');
  win.document.write(`<html><head><title>Stock Transaction Details</title><style>
    body { font-family: Arial, Helvetica, sans-serif; color: #1a2b3c; padding: 24px; font-size: 12px; }
    .pne-card { border: 1px solid #dde3ea; border-radius: 10px; padding: 14px 16px; margin-bottom: 14px; }
    .pne-card-head { font-weight: 800; color: #0d3b2e; margin-bottom: 12px; font-size: 13px; }
  </style></head><body>${body.innerHTML}</body></html>`);
  win.document.close();
  win.print();
}
function renderSTXFlow() {
  const tbody = document.getElementById('stx-flow-tbody');
  if (!tbody) return; // user navigated away
  const typeLabel = { purchase: 'Stock In', stock_in: 'Stock In', sale: 'Stock Out', adjustment: 'Stock Adjustment' };
  const typeColor = { purchase: '#00897B', stock_in: '#00897B', sale: '#E53935', adjustment: '#E65100' };
  const flow = STX_FLOW || [];
  const info = document.getElementById('stx-flow-info');
  const pager = document.getElementById('stx-flow-pager');

  if (!flow.length) {
    tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;color:var(--muted);padding:20px">No history for this product</td></tr>`;
    if (info) info.textContent = '';
    if (pager) pager.innerHTML = '';
    return;
  }

  const totalPages = Math.max(1, Math.ceil(flow.length / STX_PAGESIZE));
  if (STX_PAGE > totalPages) STX_PAGE = totalPages;
  if (STX_PAGE < 1) STX_PAGE = 1;
  const start = (STX_PAGE - 1) * STX_PAGESIZE;
  const pageRows = flow.slice(start, start + STX_PAGESIZE);

  if (info) info.textContent = `Showing ${start+1} to ${Math.min(start+STX_PAGESIZE, flow.length)} of ${flow.length} entries`;
  if (pager) {
    const opts = Array.from({length: totalPages}, (_, i) =>
      `<option value="${i+1}" ${i+1===STX_PAGE?'selected':''}>Page ${i+1}</option>`).join('');
    pager.innerHTML = `
      <button class="pg-btn" onclick="stxPage(${STX_PAGE-1})" ${STX_PAGE<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>
      <select onchange="stxPage(parseInt(this.value))" style="padding:5px 8px;border:1px solid var(--border);border-radius:8px;font-size:12px;background:var(--card)">${opts}</select>
      <span style="font-size:12px;color:var(--muted)">of ${totalPages}</span>
      <button class="pg-btn" onclick="stxPage(${STX_PAGE+1})" ${STX_PAGE>=totalPages?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
  }

  tbody.innerHTML = pageRows.map((f, i) => {
    const isThis = String(f.id) === String(STX_LEDGER_ID);
    const fType = f.ref_type || '';
    return `<tr style="${isThis ? 'background:#E8F5E9' : ''}">
      <td>${start + i + 1}</td>
      <td>${fmt_date_disp(f.movement_date)}${isThis ? ' <span style="font-size:9px;font-weight:800;color:#00897B">◀ THIS</span>' : ''}</td>
      <td><span style="font-size:10.5px;font-weight:700;color:${typeColor[fType]||'#889'};background:${typeColor[fType]||'#889'}18;padding:2px 8px;border-radius:10px">${typeLabel[fType]||'Unknown'}</span></td>
      <td>${escHtml(f.reference_no||'—')}</td>
      <td style="color:#00897B;font-weight:600">${f.direction==='in'?parseFloat(f.qty).toFixed(2):'—'}</td>
      <td style="color:#E53935;font-weight:600">${f.direction==='out'?parseFloat(f.qty).toFixed(2):'—'}</td>
      <td><strong>${parseFloat(f.running_balance).toFixed(2)}</strong></td>
      <td>${(parseFloat(f.rate)||0) > 0 ? parseFloat(f.rate).toFixed(2) : '—'}</td>
      <td style="color:var(--muted);font-size:11px">${escHtml(f.notes||'—')}</td>
    </tr>`;
  }).join('');
}
function stxPage(p) {
  const totalPages = Math.max(1, Math.ceil((STX_FLOW||[]).length / STX_PAGESIZE));
  if (p < 1 || p > totalPages) return;
  STX_PAGE = p;
  renderSTXFlow();
}
