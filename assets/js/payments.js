// ================================================================
//  assets/js/payments.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  No MPA adjustments needed — this list/view page was already
//  self-contained in the SPA. (Recording a NEW payment happens via
//  openPaidModal() on the invoices page — deferred there, not here.)
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['payments', 'invoices', 'settings']);
  renderPayments();
});

const PMT = { page: 1, per: 10, list: [] };

function renderPayments() { PMT.list = [...STATE.payments]; PMT.page = 1; _renderPmtPage(); _renderPmtSummary(); }

function filterPayments(v) {
  const s = v.toLowerCase();
  PMT.list = STATE.payments.filter(p => (!s || (p.inv && p.inv.toLowerCase().includes(s)) || (p.client && p.client.toLowerCase().includes(s)) || (p.txn && p.txn.toLowerCase().includes(s))));
  PMT.page = 1; _renderPmtPage();
}

function filterPaymentsByMethod(v) { PMT.list = v ? STATE.payments.filter(p => p.method === v) : [...STATE.payments]; PMT.page = 1; _renderPmtPage(); }

function setPmtRange(r) {
  const t = new Date(); let f = new Date(), to = new Date();
  if (r === 'today') { f = new Date(t); to = new Date(t); }
  else if (r === 'week') { f = new Date(t); f.setDate(t.getDate() - t.getDay()); to = new Date(f); to.setDate(f.getDate() + 6); }
  else if (r === 'month') { f = new Date(t.getFullYear(), t.getMonth(), 1); to = new Date(t.getFullYear(), t.getMonth() + 1, 0); }
  const fs = fmt_date(f), ts = fmt_date(to);
  const pf = document.getElementById('pmtFrom'), pt = document.getElementById('pmtTo');
  if (pf) pf.value = fs; if (pt) pt.value = ts;
  ['pmtToday', 'pmtWeek', 'pmtMonth'].forEach(id => { const b = document.getElementById(id); if (b) b.classList.remove('active'); });
  const bn = document.getElementById('pmt' + r.charAt(0).toUpperCase() + r.slice(1)); if (bn) bn.classList.add('active');
  filterPmtByDate();
}

function filterPmtByDate() {
  const f = document.getElementById('pmtFrom')?.value || '', t = document.getElementById('pmtTo')?.value || '';
  PMT.list = STATE.payments.filter(p => (!f || p.date >= f) && (!t || p.date <= t));
  PMT.page = 1; _renderPmtPage();
}

function exportPmtCSV() {
  const h = ['Date', 'Invoice', 'Client', 'Method', 'Txn ID', 'Amount', 'Status'];
  const r = STATE.payments.map(p => [p.date, p.inv, p.client, p.method, p.txn || '', p.amount, p.status].map(v => `"${v}"`).join(','));
  downloadFile('payments.csv', [h.join(','), ...r].join('\n'), 'text/csv');
  toast('✅ Exported!', 'success');
}

function _renderPmtSummary() {
  const el = document.getElementById('pmtSummary'); if (!el) return;
  const tot = STATE.payments.reduce((s, p) => s + p.amount, 0);
  const upi = STATE.payments.filter(p => p.method && p.method.toLowerCase().includes('upi')).reduce((s, p) => s + p.amount, 0);
  const neft = STATE.payments.filter(p => p.method && (p.method.toLowerCase().includes('neft') || p.method.toLowerCase().includes('bank'))).reduce((s, p) => s + p.amount, 0);
  const tod = fmt_date(new Date()); const todAmt = STATE.payments.filter(p => p.date === tod).reduce((s, p) => s + p.amount, 0);
  el.innerHTML = `
    <div class="stat-card"><div class="stat-icon" style="background:#e0f2f1;color:#00897B"><i class="fas fa-rupee-sign"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(tot)}</div><div class="stat-lbl">Total Collected</div><div class="stat-trend neutral">${STATE.payments.length} txns</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#e3f2fd;color:#1976D2"><i class="fas fa-mobile-alt"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(upi)}</div><div class="stat-lbl">Via UPI</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#fff8e1;color:#F9A825"><i class="fas fa-university"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(neft)}</div><div class="stat-lbl">Via Bank</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#e8f5e9;color:#388E3C"><i class="fas fa-calendar-day"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(todAmt)}</div><div class="stat-lbl">Today</div></div></div>`;
}

function _renderPmtPage() {
  const tbody = document.getElementById('paymentsTbody'); if (!tbody) return;
  const s = (PMT.page - 1) * PMT.per, e = s + PMT.per, pg = PMT.list.slice(s, e);

  // Assign matte color per unique invoice number for visual grouping
  const invColors = ['#455A64', '#00695C', '#1565C0', '#6A1B9A', '#4E342E', '#37474F', '#2E7D32', '#283593', '#B71C1C', '#E65100'];
  const invNums = [...new Set(pg.map(p => p.inv))];
  const invColorMap = {};
  invNums.forEach((num, i) => { invColorMap[num] = invColors[i % invColors.length]; });
  const invCount = {};
  pg.forEach(p => { invCount[p.inv] = (invCount[p.inv] || 0) + 1; });

  tbody.innerHTML = pg.map((p, i) => {
    const df = p.date ? new Date(p.date).toLocaleDateString(_moneyLocale(), { day: '2-digit', month: 'short', year: 'numeric' }) : p.date;
    const mi = p.method && p.method.toLowerCase().includes('upi') ? 'fa-mobile-alt' : p.method && p.method.toLowerCase().includes('cheque') ? 'fa-money-check' : p.method && p.method.toLowerCase().includes('cash') ? 'fa-money-bill-wave' : 'fa-university';
    const chipColor = invColorMap[p.inv] || '#455A64';
    const isMulti = invCount[p.inv] > 1;
    const layerIcon = isMulti ? `<i class="fas fa-layer-group" style="font-size:9px;opacity:.75;margin-right:3px"></i>` : '';
    const invChip = `<span style="display:inline-flex;align-items:center;padding:3px 9px;border-radius:10px;background:${chipColor};color:#fff;font-family:var(--mono);font-weight:700;font-size:12px;letter-spacing:.3px;box-shadow:0 1px 4px ${chipColor}55">${layerIcon}${p.inv}</span>`;
    const isDeleted = p._invoiceDeleted || p.invoice_deleted;
    return `<tr style="${isDeleted ? 'background:#FFF5F5;opacity:.85;' : isMulti ? 'border-left:3px solid ' + chipColor + ';background:' + chipColor + '08' : ''}">
      <td style="font-size:12px">${df}</td>
      <td>${invChip}</td>
      <td><strong>${p.client}</strong></td>
      <td><span style="display:flex;align-items:center;gap:5px"><i class="fas ${mi}" style="color:var(--muted2);font-size:11px"></i>${p.method}</span></td>
      <td><code style="font-family:var(--mono);font-size:11px;color:var(--muted)">${p.txn || '—'}</code></td>
      <td><strong style="font-family:var(--mono);color:${isDeleted ? 'var(--muted)' : 'var(--green)'}${isDeleted ? ';text-decoration:line-through' : ''}">${fmt_money(p.amount)}</strong></td>
      <td><span class="badge ${isDeleted ? 'badge-cancelled' : 'badge-paid'}" style="${isDeleted ? 'background:#FFCDD2;color:#B71C1C' : ''}">${isDeleted ? '🗑️ Invoice Deleted' : p.status}</span></td>
      <td style="display:flex;gap:6px;align-items:center">
        <button class="act-btn" title="View Receipt" onclick="viewReceipt(${s + i})"><i class="fas fa-receipt"></i></button>
        ${isDeleted ? `<button class="act-btn" title="Revert deleted flag" onclick="revertPaymentDelete(${s + i})" style="color:var(--teal);border-color:var(--teal-l)"><i class="fas fa-undo"></i></button>` : ''}
      </td>
    </tr>`;
  }).join('') || '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--muted)">No payments recorded</td></tr>';
  const tot = Math.ceil(PMT.list.length / PMT.per);
  const pg2 = document.getElementById('pmtPagination');
  if (pg2) {
    let h = `<button class="pg-btn" onclick="pmtPage(${PMT.page - 1})" ${PMT.page <= 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= tot; i++) h += `<button class="pg-btn ${i === PMT.page ? 'active' : ''}" onclick="pmtPage(${i})">${i}</button>`;
    h += `<button class="pg-btn" onclick="pmtPage(${PMT.page + 1})" ${PMT.page >= tot ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;
    pg2.innerHTML = h;
  }
  const inf = document.getElementById('pmtInfo'); if (inf) inf.textContent = `${s + 1}–${Math.min(e, PMT.list.length)} of ${PMT.list.length}`;
}
function pmtPage(p) { const t = Math.ceil(PMT.list.length / PMT.per); if (p < 1 || p > t) return; PMT.page = p; _renderPmtPage(); }

async function revertPaymentDelete(idx) {
  const p = PMT.list[idx];
  if (!p || !p.id) return;
  const result = await Swal.fire({ title: 'Revert Payment Flag?', html: 'This will mark the payment as <b>active</b> again.', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, Revert', cancelButtonText: 'Cancel', confirmButtonColor: '#00897B', customClass: { popup: 'swal-compact' } });
  if (!result.isConfirmed) return;
  try {
    await api('api/payments.php?id=' + parseInt(p.id), 'PATCH', { invoice_deleted: false });
    const sp = STATE.payments.find(x => String(x.id) === String(p.id));
    if (sp) { sp._invoiceDeleted = false; sp.invoice_deleted = false; }
    toast('↩ Payment flag reverted — now showing as active', 'success');
    renderPayments();
  } catch (e) { toast('❌ Revert failed: ' + e.message, 'error'); }
}

function viewReceipt(i) {
  const p = PMT.list[i]; if (!p) return;
  const sc = STATE.settings;
  const df = p.date ? new Date(p.date).toLocaleDateString(_moneyLocale(), { day: '2-digit', month: 'long', year: 'numeric' }) : p.date;
  document.getElementById('receiptBody').innerHTML = `
    <div style="text-align:center;margin-bottom:18px">
      <div style="font-size:20px;font-weight:800;color:var(--teal)">${sc.company}</div>
      <div style="font-size:11px;color:var(--muted)">${sc.address} · ${sc.phone}</div>
    </div>
    <div style="border:2px dashed var(--teal);border-radius:10px;padding:18px;margin-bottom:16px;text-align:center">
      <div style="font-size:36px;color:#388E3C">✓</div>
      <div style="font-weight:700;margin-bottom:4px">Payment Received</div>
      <div style="font-size:28px;font-weight:800;color:var(--teal);font-family:var(--mono)">${fmt_money(p.amount)}</div>
    </div>
    <table style="width:100%;border-collapse:collapse">
      ${[['Date', df], ['Invoice #', p.inv], ['Client', p.client], ['Method', p.method], ['Txn ID', p.txn || '—'], ['Status', p.status]].map(([k, v]) => `<tr><td style="padding:8px 12px;border-bottom:1px solid var(--border);color:var(--muted);font-size:13px;width:40%">${k}</td><td style="padding:8px 12px;border-bottom:1px solid var(--border);font-weight:600;font-size:13px">${v}</td></tr>`).join('')}
    </table>
    <div style="margin-top:14px;text-align:center;font-size:10px;color:var(--muted)">Computer-generated receipt · ${STATE.settings.company || 'Invoice Manager'}</div>`;
  STATE._rcptIdx = i;
  openModal('modal-receipt');
}

function printReceiptModal() {
  const p = PMT.list[STATE._rcptIdx]; if (!p) return;
  const sc = STATE.settings;
  const w = window.open('', '_blank', 'width=600,height=700');
  const df = p.date ? new Date(p.date).toLocaleDateString(_moneyLocale(), { day: '2-digit', month: 'long', year: 'numeric' }) : p.date;
  w.document.write(`<!DOCTYPE html><html><head><title>Receipt</title><style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:sans-serif;padding:40px}.no-print{display:flex;gap:10px;margin-bottom:20px;padding:10px;background:#f5f5f5;border-radius:8px}@media print{.no-print{display:none!important}}</style></head><body>
  <div class="no-print"><button onclick="window.print()" style="padding:8px 20px;background:#00897B;color:#fff;border:none;border-radius:7px;cursor:pointer;font-weight:bold">Print</button><button onclick="window.close()" style="padding:8px 16px;border:1px solid #ddd;border-radius:7px;cursor:pointer">Close</button></div>
  <div style="max-width:480px;margin:0 auto;border:1px solid #eee;border-radius:12px;overflow:hidden">
    <div style="background:#00897B;color:#fff;padding:20px;text-align:center"><h2>${sc.company}</h2><p style="font-size:12px;opacity:.8">${sc.address}</p></div>
    <div style="padding:24px;text-align:center"><div style="font-size:40px;color:#388E3C">✓</div><div style="font-weight:700">Payment Received</div><div style="font-size:28px;font-weight:800;color:#00897B">${fmt_money(p.amount)}</div></div>
    <table style="width:100%;border-collapse:collapse;padding:0 24px 24px">
      ${[['Date', df], ['Invoice', p.inv], ['Client', p.client], ['Method', p.method], ['Txn ID', p.txn || '—']].map(([k, v]) => `<tr><td style="padding:8px 24px;border-bottom:1px solid #eee;color:#666">${k}</td><td style="padding:8px 24px;border-bottom:1px solid #eee;font-weight:600">${v}</td></tr>`).join('')}
    </table>
  </div></body></html>`);
  w.document.close();
}
