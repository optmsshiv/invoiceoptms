// ================================================================
//  assets/js/tax.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  No MPA adjustments needed — self-contained in the SPA too.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['invoices', 'settings']);
  renderTaxSummary();
});

let _taxInvoices = [];
let taxMonthlyChartInst = null, taxRateChartInst = null;

function setTaxRange(r) {
  const now = new Date();
  const fmt = d => d.toISOString().slice(0, 10);
  let from, to = fmt(now);
  ['year', 'quarter', 'month', 'all'].forEach(b => {
    const btn = document.getElementById('tax-btn-' + b);
    if (btn) btn.classList.toggle('active', b === r);
  });
  if (r === 'year') { from = `${now.getFullYear()}-01-01`; }
  else if (r === 'quarter') { const qStart = new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1); from = fmt(qStart); }
  else if (r === 'month') { from = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`; }
  else { from = ''; to = ''; }
  const fi = document.getElementById('tax-from'), ti = document.getElementById('tax-to');
  if (fi) fi.value = from; if (ti) ti.value = to;
  _applyTaxData(from, to);
}

function applyTaxFilter() {
  const from = document.getElementById('tax-from')?.value || '';
  const to = document.getElementById('tax-to')?.value || '';
  ['year', 'quarter', 'month', 'all'].forEach(b => { const btn = document.getElementById('tax-btn-' + b); if (btn) btn.classList.remove('active'); });
  _applyTaxData(from, to);
}

function renderTaxSummary() { setTaxRange('year'); }

function _applyTaxData(from, to) {
  _taxInvoices = STATE.invoices.filter(inv => {
    if (inv.status === 'Draft' || inv.status === 'Cancelled') return false;
    if (!from && !to) return true;
    const d = inv.issued || inv.date || '';
    if (from && d < from) return false;
    if (to && d > to) return false;
    return true;
  });
  _renderTaxStatCards();
  _renderTaxRateTable();
  _renderTaxMonthlyTable();
  _renderTaxCharts();
}

function _getTaxBreakdown(inv) {
  const gstRate = parseFloat(inv.gst || inv.gst_rate || STATE.settings.defaultGST || 18);
  const amount = parseFloat(inv.amount || 0);
  const taxable = parseFloat((amount / (1 + gstRate / 100)).toFixed(2));
  const gstTotal = parseFloat((amount - taxable).toFixed(2));
  const cgst = parseFloat((gstTotal / 2).toFixed(2));
  const sgst = parseFloat((gstTotal / 2).toFixed(2));
  return { gstRate, taxable, gstTotal, cgst, sgst, igst: 0 };
}

function _renderTaxStatCards() {
  const el = document.getElementById('tax-stat-cards');
  if (!el) return;
  const totalGross = _taxInvoices.reduce((s, i) => s + parseFloat(i.amount || 0), 0);
  const totalTaxable = _taxInvoices.reduce((s, i) => s + _getTaxBreakdown(i).taxable, 0);
  const totalGST = _taxInvoices.reduce((s, i) => s + _getTaxBreakdown(i).gstTotal, 0);
  const totalCGST = _taxInvoices.reduce((s, i) => s + _getTaxBreakdown(i).cgst, 0);
  const totalSGST = _taxInvoices.reduce((s, i) => s + _getTaxBreakdown(i).sgst, 0);
  const paidGST = _taxInvoices.filter(i => i.status === 'Paid').reduce((s, i) => s + _getTaxBreakdown(i).gstTotal, 0);
  const cards = [
    { l: 'Gross Revenue', v: fmt_money(totalGross), ic: 'fa-rupee-sign', col: 'var(--teal)', bg: '#e0f2f1' },
    { l: 'Taxable Value', v: fmt_money(totalTaxable), ic: 'fa-calculator', col: 'var(--blue)', bg: '#e3f2fd' },
    { l: 'Total GST Collected', v: fmt_money(totalGST), ic: 'fa-landmark', col: 'var(--purple)', bg: '#f3e5f5' },
    { l: 'CGST Collected', v: fmt_money(totalCGST), ic: 'fa-arrow-right', col: 'var(--orange)', bg: '#fbe9e7' },
    { l: 'SGST Collected', v: fmt_money(totalSGST), ic: 'fa-arrow-left', col: 'var(--green)', bg: '#e8f5e9' },
    { l: 'GST on Paid Invoices', v: fmt_money(paidGST), ic: 'fa-check-circle', col: 'var(--green)', bg: '#e8f5e9' },
  ];
  el.innerHTML = cards.map(c => `<div class="stat-card">
    <div class="stat-icon" style="background:${c.bg};color:${c.col}"><i class="fas ${c.ic}"></i></div>
    <div class="stat-body"><div class="stat-val" style="font-size:18px">${c.v}</div><div class="stat-lbl">${c.l}</div></div>
  </div>`).join('');
}

function _renderTaxRateTable() {
  const tbody = document.getElementById('tax-rate-tbody');
  if (!tbody) return;
  const rateMap = {};
  _taxInvoices.forEach(inv => {
    const b = _getTaxBreakdown(inv);
    const k = b.gstRate + '%';
    if (!rateMap[k]) rateMap[k] = { rate: k, gstRate: b.gstRate, taxable: 0, cgst: 0, sgst: 0, igst: 0, total: 0, count: 0 };
    rateMap[k].taxable += b.taxable; rateMap[k].cgst += b.cgst; rateMap[k].sgst += b.sgst;
    rateMap[k].igst += b.igst; rateMap[k].total += b.gstTotal; rateMap[k].count++;
  });
  const rows = Object.values(rateMap).sort((a, b) => parseFloat(b.rate) - parseFloat(a.rate));
  if (!rows.length) { tbody.innerHTML = `<tr><td colspan="7" style="padding:24px;text-align:center;color:var(--muted)">No data</td></tr>`; return; }
  tbody.innerHTML = rows.map(r => {
    const half = (r.gstRate / 2).toFixed(r.gstRate % 2 === 0 ? 0 : 1);
    const halfLabel = `<span style="font-size:10px;font-weight:600;color:var(--muted);margin-left:4px">(${half}%)</span>`;
    return `<tr>
      <td><span style="padding:2px 8px;border-radius:10px;font-size:12px;font-weight:700;background:var(--purple-bg);color:var(--purple)">${r.rate}</span></td>
      <td style="font-family:var(--mono)">${fmt_money(r.taxable)}</td>
      <td style="font-family:var(--mono)">${fmt_money(r.cgst)}${halfLabel}</td>
      <td style="font-family:var(--mono)">${fmt_money(r.sgst)}${halfLabel}</td>
      <td style="font-family:var(--mono);color:var(--muted)">${fmt_money(r.igst)}</td>
      <td style="font-family:var(--mono);font-weight:700;color:var(--purple)">${fmt_money(r.total)}</td>
      <td style="text-align:center">${r.count}</td>
    </tr>`;
  }).join('');
}

function _renderTaxMonthlyTable() {
  const tbody = document.getElementById('tax-monthly-tbody');
  if (!tbody) return;
  const monthMap = {};
  _taxInvoices.forEach(inv => {
    const m = (inv.issued || inv.date || '').slice(0, 7);
    if (!m) return;
    if (!monthMap[m]) monthMap[m] = { month: m, count: 0, gross: 0, taxable: 0, cgst: 0, sgst: 0, gst: 0, paid: 0 };
    const b = _getTaxBreakdown(inv);
    monthMap[m].count++; monthMap[m].gross += parseFloat(inv.amount || 0);
    monthMap[m].taxable += b.taxable; monthMap[m].cgst += b.cgst; monthMap[m].sgst += b.sgst;
    monthMap[m].gst += b.gstTotal;
    if (inv.status === 'Paid') monthMap[m].paid += b.gstTotal;
  });
  const rows = Object.values(monthMap).sort((a, b) => b.month.localeCompare(a.month));
  if (!rows.length) { tbody.innerHTML = `<tr><td colspan="8" style="padding:24px;text-align:center;color:var(--muted)">No data</td></tr>`; return; }
  tbody.innerHTML = rows.map(r => {
    const d = new Date(r.month + '-01');
    const label = d.toLocaleDateString(_moneyLocale(), { month: 'long', year: 'numeric' });
    const allPaid = Math.abs(r.paid - r.gst) < 1;
    return `<tr>
      <td style="font-weight:600">${label}</td>
      <td style="text-align:center">${r.count}</td>
      <td style="font-family:var(--mono)">${fmt_money(r.gross)}</td>
      <td style="font-family:var(--mono)">${fmt_money(r.taxable)}</td>
      <td style="font-family:var(--mono);color:var(--orange)">${fmt_money(r.cgst)}</td>
      <td style="font-family:var(--mono);color:var(--green)">${fmt_money(r.sgst)}</td>
      <td style="font-family:var(--mono);font-weight:700;color:var(--purple)">${fmt_money(r.gst)}</td>
      <td><span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;background:${allPaid ? '#e8f5e9' : '#fff8e1'};color:${allPaid ? '#388E3C' : '#F9A825'}">${allPaid ? 'Collected' : 'Partial'}</span></td>
    </tr>`;
  }).join('');
}

function _renderTaxCharts() {
  const monthMap = {};
  _taxInvoices.forEach(inv => {
    const m = (inv.issued || inv.date || '').slice(0, 7); if (!m) return;
    if (!monthMap[m]) monthMap[m] = 0;
    monthMap[m] += _getTaxBreakdown(inv).gstTotal;
  });
  const months = Object.keys(monthMap).sort().slice(-12);
  const ctx1 = document.getElementById('taxMonthlyChart');
  if (ctx1) {
    if (taxMonthlyChartInst) taxMonthlyChartInst.destroy();
    taxMonthlyChartInst = new Chart(ctx1, {
      type: 'bar', data: {
        labels: months.map(m => { const d = new Date(m + '-01'); return d.toLocaleDateString(_moneyLocale(), { month: 'short', year: '2-digit' }); }),
        datasets: [{ label: 'GST Collected', data: months.map(m => Math.round(monthMap[m] || 0)), backgroundColor: '#7B1FA220', borderColor: '#7B1FA2', borderWidth: 2, borderRadius: 6 }],
      }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { ticks: { callback: v => (STATE.settings.currency || '₹') + v.toLocaleString(_moneyLocale()) } } } },
    });
  }
  const rateMap = {};
  _taxInvoices.forEach(inv => {
    const b = _getTaxBreakdown(inv); const k = b.gstRate + '%';
    rateMap[k] = (rateMap[k] || 0) + b.gstTotal;
  });
  const ctx2 = document.getElementById('taxRateChart');
  if (ctx2) {
    if (taxRateChartInst) taxRateChartInst.destroy();
    const keys = Object.keys(rateMap); const cols = ['#7B1FA2', '#1976D2', '#00897B', '#E65100', '#C62828'];
    taxRateChartInst = new Chart(ctx2, {
      type: 'doughnut', data: {
        labels: keys, datasets: [{ data: keys.map(k => Math.round(rateMap[k] || 0)), backgroundColor: keys.map((_, i) => cols[i % cols.length]), borderWidth: 2, borderColor: '#fff' }],
      }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 8 } } } },
    });
  }
}

function exportTaxCSV() {
  const rows = [['Month', 'Invoices', 'Gross Revenue', 'Taxable Value', 'CGST', 'SGST', 'Total GST']];
  const monthMap = {};
  _taxInvoices.forEach(inv => {
    const m = (inv.issued || inv.date || '').slice(0, 7); if (!m) return;
    if (!monthMap[m]) monthMap[m] = { count: 0, gross: 0, taxable: 0, cgst: 0, sgst: 0, gst: 0 };
    const b = _getTaxBreakdown(inv);
    monthMap[m].count++; monthMap[m].gross += parseFloat(inv.amount || 0);
    monthMap[m].taxable += b.taxable; monthMap[m].cgst += b.cgst; monthMap[m].sgst += b.sgst;
    monthMap[m].gst += b.gstTotal;
  });
  Object.entries(monthMap).sort((a, b) => a[0].localeCompare(b[0])).forEach(([m, r]) => {
    rows.push([m, r.count, r.gross.toFixed(2), r.taxable.toFixed(2), r.cgst.toFixed(2), r.sgst.toFixed(2), r.gst.toFixed(2)]);
  });
  _downloadCSV(rows, 'tax_summary.csv');
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
