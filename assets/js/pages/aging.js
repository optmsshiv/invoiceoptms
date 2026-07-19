// ================================================================
//  assets/js/aging.js
//  Requires: common.js, shared-data.js (loaded before this file).
//
//  NOTE: the "Pay" button calls openPaidModal(inv.id) — now resolved,
//  see invoice-render-shared.js (built in Phase 3). aging.php loads
//  that file alongside this one.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['invoices', 'clients', 'payments', 'settings']);
  renderAgingReport();
});

let _agingAll = [], _agingFiltered = [];

function renderAgingReport() {
  const statusF = document.getElementById('aging-status-filter')?.value || '';
  const today = new Date(); today.setHours(0, 0, 0, 0);

  const unpaidStatuses = ['Pending', 'Overdue', 'Partial'];
  _agingAll = STATE.invoices.filter(inv => {
    if (statusF) return inv.status === statusF;
    return unpaidStatuses.includes(inv.status);
  }).map(inv => {
    const pmts = STATE.payments.filter(p => String(p.invoice_id) === String(inv.id));
    const received = pmts.reduce((s, p) => s + parseFloat(p.amount || 0), 0);
    const outstanding = Math.max(0, (inv.amount || 0) - received);
    const dueDate = inv.due ? new Date(inv.due) : null;
    const daysOver = dueDate ? Math.floor((today - dueDate) / 864e5) : 0;
    let bucket;
    if (daysOver <= 0) bucket = 'Current';
    else if (daysOver <= 30) bucket = '1–30 days';
    else if (daysOver <= 60) bucket = '31–60 days';
    else if (daysOver <= 90) bucket = '61–90 days';
    else bucket = '90+ days';
    const client = STATE.clients.find(c => String(c.id) === String(inv.client)) || {};
    return { inv, client, received, outstanding, daysOver, bucket, dueDate };
  });

  _agingFiltered = [..._agingAll];
  _renderAgingBuckets();
  _renderAgingTable(_agingFiltered);
}

function _renderAgingBuckets() {
  const buckets = ['Current', '1–30 days', '31–60 days', '61–90 days', '90+ days'];
  const colors = ['#00897B', '#F9A825', '#E65100', '#C62828', '#7B1FA2'];
  const el = document.getElementById('aging-buckets');
  if (!el) return;
  el.innerHTML = buckets.map((b, i) => {
    const items = _agingAll.filter(r => r.bucket === b);
    const total = items.reduce((s, r) => s + r.outstanding, 0);
    return `<div style="background:var(--card);border-radius:var(--r);padding:14px 16px;border:2px solid ${colors[i]}22;box-shadow:var(--shadow)">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
        <div style="width:10px;height:10px;border-radius:50%;background:${colors[i]}"></div>
        <span style="font-size:11px;font-weight:700;color:${colors[i]}">${b}</span>
      </div>
      <div style="font-size:20px;font-weight:800;color:var(--text);font-family:var(--mono)">${fmt_money(total)}</div>
      <div style="font-size:11px;color:var(--muted);margin-top:3px">${items.length} invoice${items.length !== 1 ? 's' : ''}</div>
    </div>`;
  }).join('');
}

function _renderAgingTable(rows) {
  const tbody = document.getElementById('aging-tbody');
  const info = document.getElementById('aging-info');
  if (!tbody) return;
  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="11" style="padding:40px;text-align:center;color:var(--muted)"><i class="fas fa-check-circle" style="font-size:28px;opacity:.2;display:block;margin-bottom:8px"></i>No outstanding invoices</td></tr>`;
    if (info) info.textContent = '0 invoices';
    return;
  }
  const bucketColor = { 'Current': '#00897B', '1–30 days': '#F9A825', '31–60 days': '#E65100', '61–90 days': '#C62828', '90+ days': '#7B1FA2' };
  tbody.innerHTML = rows.map(r => {
    const { inv, client, received, outstanding, daysOver, bucket } = r;
    const sym = inv.currency || '₹';
    const overTxt = daysOver > 0 ? `<span style="color:#C62828;font-weight:700">${daysOver}d overdue</span>` : `<span style="color:#00897B">Not due</span>`;
    const bc = bucketColor[bucket] || '#888';
    return `<tr>
      <td><strong style="font-family:var(--mono)">${inv.num || inv.invoice_number || ''}</strong></td>
      <td>${client.name || inv.client_name || '—'}</td>
      <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${inv.service || inv.service_type || '—'}</td>
      <td>${inv.issued || '—'}</td>
      <td>${inv.due || '—'}</td>
      <td>${overTxt}</td>
      <td style="font-family:var(--mono)">${fmt_money(inv.amount || 0, sym)}</td>
      <td style="font-family:var(--mono);color:#2E7D32">${fmt_money(received, sym)}</td>
      <td style="font-family:var(--mono);color:#C62828;font-weight:700">${fmt_money(outstanding, sym)}</td>
      <td><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:${bc}15;color:${bc};border:1px solid ${bc}30">${bucket}</span></td>
      <td><button onclick="openPaidModal('${inv.id}')" style="padding:4px 10px;background:var(--teal-bg);color:var(--teal);border:1px solid var(--teal);border-radius:6px;cursor:pointer;font-size:11px;font-weight:600"><i class="fas fa-rupee-sign"></i> Pay</button></td>
    </tr>`;
  }).join('');
  if (info) info.textContent = `${rows.length} invoice${rows.length !== 1 ? 's' : ''}  ·  Outstanding: ${fmt_money(rows.reduce((s, r) => s + r.outstanding, 0))}`;
}

function filterAgingTable(val) {
  const s = val.toLowerCase();
  _agingFiltered = s ? _agingAll.filter(r =>
    (r.inv.num || '').toLowerCase().includes(s) ||
    (r.client.name || '').toLowerCase().includes(s) ||
    (r.inv.service || '').toLowerCase().includes(s)
  ) : [..._agingAll];
  _renderAgingTable(_agingFiltered);
}

function exportAgingCSV() {
  const rows = [['Invoice#', 'Client', 'Service', 'Issue Date', 'Due Date', 'Days Overdue', 'Total', 'Received', 'Outstanding', 'Bucket']];
  _agingFiltered.forEach(r => rows.push([
    r.inv.num || '', r.client.name || '', r.inv.service || '',
    r.inv.issued || '', r.inv.due || '', r.daysOver,
    r.inv.amount || 0, r.received.toFixed(2), r.outstanding.toFixed(2), r.bucket,
  ]));
  _downloadCSV(rows, 'aging_report.csv');
}

// Local fallback so this page doesn't depend on invoices.js being loaded.
if (typeof _downloadCSV !== 'function') {
  window._downloadCSV = function (rows, filename) {
    const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = filename;
    document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
  };
}
