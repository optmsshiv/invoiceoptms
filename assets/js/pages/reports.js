// ================================================================
//  assets/js/reports.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  No MPA adjustments needed — self-contained in the SPA too.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['invoices', 'clients', 'settings']);
  renderReports();
});

let serviceChartInst = null, compareChartInst = null;
const RPT = { page: 1, per: 10, list: [], from: '', to: '' };

function renderReports() { setRptRange('all'); }

function setRptRange(r) {
  const t = new Date(); let f = new Date(), to = new Date();
  if (r === 'today') { f = new Date(t); to = new Date(t); }
  else if (r === 'week') { f = new Date(t); f.setDate(t.getDate() - t.getDay()); to = new Date(f); to.setDate(f.getDate() + 6); }
  else if (r === 'month') { f = new Date(t.getFullYear(), t.getMonth(), 1); to = new Date(t.getFullYear(), t.getMonth() + 1, 0); }
  else if (r === 'quarter') { const q = Math.floor(t.getMonth() / 3); f = new Date(t.getFullYear(), q * 3, 1); to = new Date(t.getFullYear(), q * 3 + 3, 0); }
  else if (r === 'year') { f = new Date(t.getFullYear(), 0, 1); to = new Date(t.getFullYear(), 11, 31); }
  else { f = null; to = null; }
  RPT.from = f ? fmt_date(f) : ''; RPT.to = to ? fmt_date(to) : '';
  const rf = document.getElementById('rptFrom'), rt = document.getElementById('rptTo');
  if (rf) rf.value = RPT.from; if (rt) rt.value = RPT.to;
  ['today', 'month', 'quarter', 'year', 'all'].forEach(x => { const b = document.getElementById('rpt-' + x); if (b) b.classList.remove('active'); });
  const bn = document.getElementById('rpt-' + r); if (bn) bn.classList.add('active');
  applyRptFilter();
}

function applyRptFilter() {
  const f = document.getElementById('rptFrom')?.value || RPT.from;
  const t = document.getElementById('rptTo')?.value || RPT.to;
  RPT.from = f; RPT.to = t;
  RPT.list = STATE.invoices.filter(i => (!f || i.issued >= f) && (!t || i.issued <= t));
  RPT.page = 1; _renderRptStats(); _renderRptTable(); _renderRptCharts();
}

function filterRptTable(v) {
  const s = v.toLowerCase();
  RPT.list = STATE.invoices.filter(i => {
    const c = STATE.clients.find(x => x.id === i.client);
    if (RPT.from && i.issued < RPT.from) return false;
    if (RPT.to && i.issued > RPT.to) return false;
    return i.num.toLowerCase().includes(s) || (c && c.name.toLowerCase().includes(s)) || i.service.toLowerCase().includes(s);
  });
  RPT.page = 1; _renderRptTable();
}

function exportRptCSV() {
  const h = ['Invoice', 'Client', 'Service', 'Date', 'Amount', 'Status'];
  const r = RPT.list.map(i => { const c = STATE.clients.find(x => x.id === i.client) || { name: i.client_name || i.clientName || 'One-Time' }; return [i.num, c.name, i.service, i.issued, i.amount, i.status].map(v => `"${v}"`).join(','); });
  downloadFile('report.csv', [h.join(','), ...r].join('\n'), 'text/csv');
  toast('✅ Exported!', 'success');
}

function _renderRptStats() {
  const el = document.getElementById('rptStatCards'); if (!el) return;
  const inv = RPT.list;
  const tot = inv.reduce((s, i) => s + i.amount, 0);
  const paid = inv.filter(i => i.status === 'Paid').reduce((s, i) => s + i.amount, 0);
  const pend = inv.filter(i => i.status === 'Pending').reduce((s, i) => s + i.amount, 0);
  const over = inv.filter(i => i.status === 'Overdue').length;
  const rate = tot > 0 ? Math.round(paid / tot * 100) : 0;
  const top = STATE.clients.map(c => ({ ...c, r: inv.filter(i => i.client === c.id && i.status === 'Paid').reduce((s, i) => s + i.amount, 0) })).sort((a, b) => b.r - a.r)[0];
  el.innerHTML = `
    <div class="stat-card"><div class="stat-icon" style="background:#e0f2f1;color:#00897B"><i class="fas fa-rupee-sign"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(tot)}</div><div class="stat-lbl">Total Revenue</div><div class="stat-trend neutral">${inv.length} invoices</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#e8f5e9;color:#388E3C"><i class="fas fa-check-circle"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(paid)}</div><div class="stat-lbl">Collected</div><div class="stat-trend up">${rate}%</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#fff8e1;color:#F9A825"><i class="fas fa-clock"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(pend)}</div><div class="stat-lbl">Pending</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#fce4ec;color:#e53935"><i class="fas fa-exclamation-circle"></i></div><div class="stat-body"><div class="stat-val">${over}</div><div class="stat-lbl">Overdue</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#f3e5f5;color:#7B1FA2"><i class="fas fa-award"></i></div><div class="stat-body"><div class="stat-val" style="font-size:13px;line-height:1.3">${top ? top.name : '—'}</div><div class="stat-lbl">Top Client</div></div></div>`;
}

function _renderRptTable() {
  const tbody = document.getElementById('rptTbody'); if (!tbody) return;
  const s = (RPT.page - 1) * RPT.per, e = s + RPT.per, pg = RPT.list.slice(s, e);
  tbody.innerHTML = pg.map(inv => {
    const c = STATE.clients.find(x => x.id === inv.client) || { name: inv.client_name || inv.clientName || 'One-Time' };
    const df = inv.issued ? new Date(inv.issued).toLocaleDateString(_moneyLocale(), { day: '2-digit', month: 'short', year: 'numeric' }) : inv.issued;
    return `<tr><td><code style="font-family:var(--mono);color:var(--teal);font-weight:700">${inv.num}</code></td><td><strong>${c.name}</strong></td><td>${inv.service}</td><td style="font-size:12px">${df}</td><td><strong style="font-family:var(--mono)">${fmt_money(inv.amount)}</strong></td><td><span class="badge badge-${inv.status.toLowerCase()}">${inv.status}</span></td></tr>`;
  }).join('') || '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--muted)">No transactions in this period</td></tr>';
  const tot = Math.ceil(RPT.list.length / RPT.per);
  const pg2 = document.getElementById('rptPagination');
  if (pg2) {
    let h = `<button class="pg-btn" onclick="rptPage(${RPT.page - 1})" ${RPT.page <= 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= tot; i++) h += `<button class="pg-btn ${i === RPT.page ? 'active' : ''}" onclick="rptPage(${i})">${i}</button>`;
    h += `<button class="pg-btn" onclick="rptPage(${RPT.page + 1})" ${RPT.page >= tot ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;
    pg2.innerHTML = h;
  }
  const inf = document.getElementById('rptInfo'); if (inf) inf.textContent = `${s + 1}–${Math.min(e, RPT.list.length)} of ${RPT.list.length} transactions`;
}
function rptPage(p) { const t = Math.ceil(RPT.list.length / RPT.per); if (p < 1 || p > t) return; RPT.page = p; _renderRptTable(); }

function _renderRptCharts() {
  const c1 = document.getElementById('serviceChart'), c2 = document.getElementById('compareChart');
  if (!c1 || !c2) return;
  if (serviceChartInst) { serviceChartInst.destroy(); serviceChartInst = null; }
  if (compareChartInst) { compareChartInst.destroy(); compareChartInst = null; }
  const svc = {}; RPT.list.forEach(i => { svc[i.service] = (svc[i.service] || 0) + i.amount; });
  const cols = ['#00897B', '#1976D2', '#AB47BC', '#E64A19', '#F9A825', '#388E3C', '#E53935', '#0097A7', '#795548'];
  serviceChartInst = new Chart(c1, { type: 'bar', data: { labels: Object.keys(svc), datasets: [{ label: 'Revenue', data: Object.values(svc), backgroundColor: cols, borderRadius: 6 }] }, options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => (STATE.settings.currency || '₹') + (v >= 1000 ? (v / 1000) + 'K' : v) } } } } });
  const trendNow = new Date(), tYear = trendNow.getFullYear();
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const curYr = Array(12).fill(0), prevYr = Array(12).fill(0);
  STATE.invoices.forEach(inv => {
    if (!inv.issued) return;
    const d = new Date(inv.issued), yr = d.getFullYear(), m = d.getMonth(), amt = parseFloat(inv.amount) || 0;
    if (yr === tYear) curYr[m] += amt;
    if (yr === tYear - 1) prevYr[m] += amt;
  });
  compareChartInst = new Chart(c2, {
    type: 'line', data: {
      labels: months, datasets: [
        { label: String(tYear - 1), data: prevYr, borderColor: '#BDBDBD', tension: .4, borderDash: [5, 5], pointRadius: 3 },
        { label: String(tYear), data: curYr, borderColor: '#00897B', tension: .4, fill: true, backgroundColor: 'rgba(0,137,123,.08)', pointRadius: 3 },
      ],
    }, options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true, ticks: { callback: v => (STATE.settings.currency || '₹') + (v >= 1000 ? (v / 1000) + 'K' : v) } } } },
  });
}
