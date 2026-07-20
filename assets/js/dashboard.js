// ================================================================
//  assets/js/dashboard.js
//  Requires: common.js, shared-data.js (loaded before this file —
//  see layout_footer.php / dashboard.php's $pageScript).
//
//  MPA CHANGES from the original SPA version:
//  1. showPage('whatsapp', null) calls → window.location.href
//     (no more client-side router to hand off to).
//  2. The old renderDashboard() also called _buildReminderQueue()
//     to update the topbar "WA reminders queued" pill — that
//     function belongs to the Reminders page (reads reminder
//     settings + promise-to-pay state) and shouldn't be duplicated
//     here. Left out for now; the pill will just stay hidden until
//     reminders.js exists and we decide how it should update
//     cross-page (likely a small dedicated stat endpoint later).
//  3. openPreviewModal(...) and sendWAForInvoice(...) are called
//     from the Recent Activity list below but are NOT defined in
//     this file — they belong to invoices.js, not built yet.
//     Clicking those before invoices.js exists will do nothing.
// ================================================================

const _nd = new Date();
let calYear = _nd.getFullYear(), calMonth = _nd.getMonth();
const CAL_EVENTS = [];
let donutChartInstance = null;
let revenueChartInstance = null;

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['invoices', 'clients', 'payments', 'settings']);
  renderDashboard();

  // Product Business section only exists in the DOM when
  // business_type IN ('product','both') — see dashboard.php's
  // $showProduct check. Fetch its data and render only then.
  if (document.getElementById('pdb-from')) {
    await loadCoreData(['sales', 'purchases', 'products', 'stock']);
    const from = document.getElementById('pdb-from'), to = document.getElementById('pdb-to');
    if (from && !from.value) {
      const d = new Date(); d.setDate(d.getDate() - 30);
      from.value = fmt_date(d);
      to.value = fmt_date(new Date());
    }
    renderProductDashboard();
  }
});

function renderDashboard() {
  renderRevenueChart('monthly');
  renderDonutChart();
  renderCalendar();
  renderDashRecent();
  renderDashKpis();
  renderDashTopClients();
  renderDashAlerts();
  renderNotifications();
  updateDashStats();
  renderDashWAActivity();
}

function renderDashWAActivity() {
  const el = document.getElementById('waActivityRows');
  if (!el) return;
  el.innerHTML = `<div style="font-size:12px;color:var(--muted);text-align:center;padding:12px">Loading…</div>`;
  WA_LOG.fetchLog().then(logs => {
    const todayStr  = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Kolkata' });
    const today     = logs.filter(l => l.ts && new Date(l.ts).toLocaleDateString('en-CA', { timeZone: 'Asia/Kolkata' }) === todayStr);
    const sent      = today.length;
    const viaApi    = today.filter(l => ['sent_api', 'delivered', 'read'].includes(l.status)).length;
    const delivered = today.filter(l => ['delivered', 'read'].includes(l.status)).length;
    const read      = today.filter(l => l.status === 'read').length;
    const failed    = today.filter(l => l.status === 'failed').length;
    const rows = [
      { icon: 'fa-paper-plane',  label: 'Sent',      val: sent,      red: false },
      { icon: 'fa-plug',         label: 'Via API',   val: viaApi,    red: false },
      { icon: 'fa-check-double', label: 'Delivered', val: delivered, red: false },
      { icon: 'fa-eye',          label: 'Read',      val: read,      red: false },
      { icon: 'fa-times-circle', label: 'Failed',    val: failed,    red: true  },
    ];
    el.innerHTML = rows.map(r => `<div class="wa-act-row">
      <span class="wa-act-lbl"><i class="fas ${r.icon}" style="width:14px;text-align:center"></i> ${r.label}</span>
      <span class="wa-act-val ${r.red && r.val > 0 ? 'fail' : ''}">${r.val}</span>
    </div>`).join('');
  }).catch(() => {
    el.innerHTML = `<div style="font-size:12px;color:var(--muted);text-align:center;padding:12px">Could not load</div>`;
  });
}

function updateDashStats() {
  const e = id => document.getElementById(id);
  const now = new Date();
  const thisMonth = now.getMonth(), thisYear = now.getFullYear();
  const lastMonth = thisMonth === 0 ? 11 : thisMonth - 1;
  const lastYear  = thisMonth === 0 ? thisYear - 1 : thisYear;

  const paid = STATE.payments
    .filter(p => { const inv = STATE.invoices.find(i => String(i.id) === String(p.invoice_id)); return inv && inv.status === 'Paid'; })
    .reduce((s, p) => s + (parseFloat(p.amount) || 0), 0);
  const partialReceived = STATE.payments
    .filter(p => { const inv = STATE.invoices.find(i => String(i.id) === String(p.invoice_id)); return inv && inv.status !== 'Paid'; })
    .reduce((s, p) => s + (parseFloat(p.amount) || 0), 0);
  const totalRevenue = paid + partialReceived;
  const pend = STATE.invoices.filter(i => i.status === 'Pending').reduce((s, i) => s + (parseFloat(i.amount) || 0), 0);
  const over = STATE.invoices.filter(i => i.status === 'Overdue').reduce((s, i) => s + (parseFloat(i.amount) || 0), 0);
  const partialRemaining = STATE.invoices.filter(i => i.status === 'Partial').reduce((s, i) => {
    const pmts = STATE.payments.filter(p => String(p.invoice_id) === String(i.id));
    const alreadyPaid = pmts.reduce((a, p) => a + parseFloat(p.amount || 0), 0);
    return s + Math.max(0, (parseFloat(i.amount) || 0) - alreadyPaid);
  }, 0);
  const pendCnt = STATE.invoices.filter(i => i.status === 'Pending').length;
  const overCnt = STATE.invoices.filter(i => i.status === 'Overdue').length;
  const partialCnt = STATE.invoices.filter(i => i.status === 'Partial').length;

  const revThisM = STATE.invoices.filter(i => {
    if (!i.issued) return false;
    const d = new Date(i.issued);
    return d.getMonth() === thisMonth && d.getFullYear() === thisYear && i.status === 'Paid';
  }).reduce((s, i) => s + (parseFloat(i.amount) || 0), 0);
  const revLastM = STATE.invoices.filter(i => {
    if (!i.issued) return false;
    const d = new Date(i.issued);
    return d.getMonth() === lastMonth && d.getFullYear() === lastYear && i.status === 'Paid';
  }).reduce((s, i) => s + (parseFloat(i.amount) || 0), 0);
  const revChange = revLastM > 0 ? Math.round((revThisM - revLastM) / revLastM * 100) : 0;

  const invThisM = STATE.invoices.filter(i => {
    if (!i.issued) return false;
    const d = new Date(i.issued);
    return d.getMonth() === thisMonth && d.getFullYear() === thisYear;
  }).length;

  if (e('s-revenue')) e('s-revenue').textContent = fmt_money(totalRevenue);
  if (e('s-pending')) e('s-pending').textContent = fmt_money(pend);
  if (e('s-overdue')) e('s-overdue').textContent = fmt_money(over);
  const _realInvCount = STATE.invoices.filter(i => !['Draft', 'Cancelled', 'Estimate'].includes(i.status)).length;
  const _draftCount   = STATE.invoices.filter(i => i.status === 'Draft').length;
  if (e('s-total')) {
    e('s-total').textContent = _realInvCount;
    const _trendEl = document.getElementById('s-total-trend');
    if (_draftCount > 0 && _trendEl) {
      _trendEl.innerHTML = `<i class='fas fa-arrow-up'></i> ${invThisM} this month `
        + `<span style='color:#9E9E9E;font-weight:600;margin-left:4px'>(${_draftCount} draft${_draftCount > 1 ? 's' : ''})</span>`;
    }
  }
  if (e('s-clients')) e('s-clients').textContent = STATE.clients.length;

  const grossRevenue = STATE.invoices
    .filter(i => i.status !== 'Draft' && i.status !== 'Cancelled')
    .reduce((s, i) => s + (parseFloat(i.amount) || 0), 0);
  const totalSettleDisc = STATE.payments
    .filter(p => { const inv = STATE.invoices.find(i => String(i.id) === String(p.invoice_id)); return inv && inv.status === 'Paid'; })
    .reduce((s, p) => s + parseFloat(p.settlement_discount || 0), 0);
  const netRevenue = paid + partialReceived;
  const recoveryRate = grossRevenue > 0 ? Math.round((netRevenue / grossRevenue) * 100) : 0;
  const barPct = Math.min(100, recoveryRate);

  const revEl = e('s-revenue-card');
  if (revEl) {
    revEl.innerHTML = `
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px">
        <div style="display:flex;align-items:flex-start;gap:10px">
          <div style="width:36px;height:36px;border-radius:9px;background:#C6EFCF;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fas fa-chart-line" style="color:#1B6B34;font-size:14px"></i>
          </div>
          <div>
            <div style="font-size:11px;color:#5A7A62;margin-bottom:2px">Gross Revenue</div>
            <div style="font-size:22px;font-weight:800;color:#1B6B34;line-height:1;font-family:var(--mono)">${fmt_money(grossRevenue)}</div>
            <div style="font-size:11px;color:#7DA88A;margin-top:3px">total billed (excl. draft &amp; cancelled)</div>
          </div>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <div style="font-size:10px;color:#5A7A62;margin-bottom:3px">Net Revenue</div>
          <div style="font-size:15px;font-weight:800;color:#1B6B34;font-family:var(--mono)">${fmt_money(netRevenue)}</div>
          <div style="font-size:10px;font-weight:700;background:#C6EFCF;color:#1B6B34;padding:2px 8px;border-radius:20px;border:1px solid #A8DDB8;margin-top:4px;display:inline-block">${recoveryRate}% collected</div>
        </div>
      </div>
      <div style="background:#C6EFCF;border-radius:4px;height:7px;overflow:hidden;margin-bottom:5px">
        <div style="height:100%;border-radius:4px;background:#2E9E54;width:${barPct}%"></div>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:12px">
        <span style="font-size:10px;color:#2E9E54">Net collected — ${fmt_money(netRevenue)}</span>
        ${totalSettleDisc > 0 ? `<span style="font-size:10px;color:#8B6914">Written off — ${fmt_money(totalSettleDisc)}</span>` : ''}
      </div>
      <div style="border-top:1px solid #C6EFCF;padding-top:10px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr))">
        <div style="padding-right:8px;border-right:1px solid #C6EFCF">
          <div style="font-size:10px;color:#5A7A62;margin-bottom:3px">Net Revenue</div>
          <div style="font-size:13px;font-weight:700;color:#1B6B34;font-family:var(--mono)">${fmt_money(netRevenue)}</div>
          <div style="font-size:9px;color:#7DA88A;margin-top:2px">cash collected</div>
        </div>
        <div style="padding:0 8px;border-right:1px solid #C6EFCF">
          <div style="font-size:10px;color:#5A7A62;margin-bottom:3px">Settlement Disc.</div>
          <div style="font-size:13px;font-weight:700;color:${totalSettleDisc > 0 ? '#8B6914' : 'var(--muted)'};font-family:var(--mono)">${totalSettleDisc > 0 ? '−' + fmt_money(totalSettleDisc) : '—'}</div>
          <div style="font-size:9px;color:#7DA88A;margin-top:2px">written off</div>
        </div>
        <div style="padding:0 8px;border-right:1px solid #C6EFCF">
          <div style="font-size:10px;color:#5A7A62;margin-bottom:3px">Still Pending</div>
          <div style="font-size:13px;font-weight:700;color:${(pend + over + partialRemaining) > 0 ? '#B85C0A' : 'var(--muted)'};font-family:var(--mono)">${(pend + over + partialRemaining) > 0 ? fmt_money(pend + over + partialRemaining) : '—'}</div>
          <div style="font-size:9px;color:#7DA88A;margin-top:2px">yet to collect</div>
        </div>
        <div style="padding-left:8px">
          <div style="font-size:10px;color:#5A7A62;margin-bottom:3px">Partial Payment</div>
          <div style="font-size:13px;font-weight:700;color:${partialRemaining > 0 ? '#4A2A9E' : 'var(--muted)'};font-family:var(--mono)">${partialRemaining > 0 ? fmt_money(partialRemaining) : '—'}</div>
          <div style="font-size:9px;color:#7DA88A;margin-top:2px">${partialCnt} invoice${partialCnt !== 1 ? 's' : ''}</div>
        </div>
      </div>`;
  }

  const combinedOutstanding = pend + over + partialRemaining;
  const combinedCount = pendCnt + overCnt + partialCnt;
  const outEl = e('s-outstanding-card');
  if (outEl) {
    outEl.innerHTML = `
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:12px">
        <div>
          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:#B85C0A;margin-bottom:5px">Total Outstanding</div>
          <div style="font-size:22px;font-weight:800;color:#B85C0A;line-height:1;font-family:var(--mono)">${fmt_money(combinedOutstanding)}</div>
          <div style="font-size:11px;color:#C8844A;margin-top:4px">${combinedCount} invoice${combinedCount !== 1 ? 's' : ''} need attention</div>
        </div>
        <div style="width:36px;height:36px;border-radius:9px;background:#FDDCB5;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fas fa-exclamation-circle" style="color:#B85C0A;font-size:14px"></i>
        </div>
      </div>
      <div style="border-top:1px solid #F9C49A;padding-top:12px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px">
        <div style="text-align:center;padding:9px 6px;background:#FFF8E1;border:1.5px solid #F5D07A;border-radius:10px">
          <div style="font-size:14px;font-weight:700;color:#7A5800;font-family:var(--mono);margin-bottom:4px">${fmt_money(pend)}</div>
          <div style="font-size:10px;color:#8B6914;display:flex;align-items:center;justify-content:center;gap:4px">
            <span style="width:7px;height:7px;border-radius:50%;background:#F5D07A;border:1px solid #D4A817;display:inline-block"></span>
            Pending (${pendCnt})
          </div>
        </div>
        <div style="text-align:center;padding:9px 6px;background:#FFEBEE;border:1.5px solid #F5ABAB;border-radius:10px">
          <div style="font-size:14px;font-weight:700;color:#8B1A1A;font-family:var(--mono);margin-bottom:4px">${fmt_money(over)}</div>
          <div style="font-size:10px;color:#B82929;display:flex;align-items:center;justify-content:center;gap:4px">
            <span style="width:7px;height:7px;border-radius:50%;background:#F5ABAB;border:1px solid #E05555;display:inline-block"></span>
            Overdue (${overCnt})
          </div>
        </div>
        <div style="text-align:center;padding:9px 6px;background:#F3EFFE;border:1.5px solid #C5B3F0;border-radius:10px">
          <div style="font-size:14px;font-weight:700;color:#4A2A9E;font-family:var(--mono);margin-bottom:4px">${fmt_money(partialRemaining)}</div>
          <div style="font-size:10px;color:#6B3FBF;display:flex;align-items:center;justify-content:center;gap:4px">
            <span style="width:7px;height:7px;border-radius:50%;background:#C5B3F0;border:1px solid #8B6ADE;display:inline-block"></span>
            Partial (${partialCnt})
          </div>
        </div>
      </div>`;
  }

  if (e('s-revenue-trend')) {
    const sign = revChange >= 0 ? '+' : '';
    e('s-revenue-trend').innerHTML = `<i class="fas fa-${revChange >= 0 ? 'arrow-up' : 'arrow-down'}"></i> ${sign}${revChange}% vs last month`;
    e('s-revenue-trend').className = `stat-trend ${revChange >= 0 ? 'up' : 'down'}`;
  }
  if (e('s-pending-trend'))  e('s-pending-trend').innerHTML  = `<i class="fas fa-minus"></i> ${pendCnt} invoice${pendCnt !== 1 ? 's' : ''}`;
  if (e('s-overdue-trend'))  e('s-overdue-trend').innerHTML  = `<i class="fas fa-exclamation-circle"></i> ${overCnt} invoice${overCnt !== 1 ? 's' : ''}`;
  if (e('s-total-trend'))    e('s-total-trend').innerHTML    = `<i class="fas fa-file-invoice"></i> ${invThisM} this month`;
  if (e('s-clients-trend'))  e('s-clients-trend').innerHTML  = `<i class="fas fa-users"></i> ${STATE.clients.length} total`;

  WA_LOG.fetchLog().then(logs => {
    const todayStr = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Kolkata' });
    const todayLogs = logs.filter(l => {
      if (!l.ts) return false;
      return new Date(l.ts).toLocaleDateString('en-CA', { timeZone: 'Asia/Kolkata' }) === todayStr;
    });
    const waSentToday = todayLogs.length;
    const waFailed    = todayLogs.filter(l => l.status === 'failed').length;
    if (e('s-wa-today'))       e('s-wa-today').textContent = waSentToday;
    if (e('s-wa-today-trend')) {
      e('s-wa-today-trend').innerHTML = waFailed > 0
        ? `<i class="fas fa-times-circle"></i> ${waFailed} failed`
        : `<i class="fas fa-check-circle"></i> all delivered`;
      e('s-wa-today-trend').className = `stat-trend ${waFailed > 0 ? 'down' : 'up'}`;
    }
  }).catch(() => {});
}

function buildLiveChartData(mode) {
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const now = new Date();
  if (mode === 'monthly') {
    const year = now.getFullYear();
    const paid = Array(12).fill(0), pend = Array(12).fill(0), over = Array(12).fill(0), part = Array(12).fill(0), draft = Array(12).fill(0), canc = Array(12).fill(0);
    STATE.invoices.forEach(inv => {
      if (!inv.issued) return;
      const d = new Date(inv.issued);
      if (d.getFullYear() !== year) return;
      const m = d.getMonth(), a = parseFloat(inv.amount) || 0;
      if (inv.status === 'Paid')      paid[m]  += a;
      if (inv.status === 'Pending')   pend[m]  += a;
      if (inv.status === 'Overdue')   over[m]  += a;
      if (inv.status === 'Partial')   part[m]  += a;
      if (inv.status === 'Draft')     draft[m] += a;
      if (inv.status === 'Cancelled') canc[m]  += a;
    });
    return { labels: months, paid, pending: pend, overdue: over, partial: part, draft, cancelled: canc };
  }
  if (mode === 'weekly') {
    const weeks = ['W1', 'W2', 'W3', 'W4', 'W5', 'W6', 'W7', 'W8'];
    const paid = Array(8).fill(0), pend = Array(8).fill(0), over = Array(8).fill(0), part = Array(8).fill(0), draft = Array(8).fill(0), canc = Array(8).fill(0);
    const baseDate = new Date(now.getFullYear(), now.getMonth(), 1);
    STATE.invoices.forEach(inv => {
      if (!inv.issued) return;
      const d = new Date(inv.issued);
      const wk = Math.min(Math.max(Math.floor((d - baseDate) / 86400000 / 7), 0), 7), a = parseFloat(inv.amount) || 0;
      if (inv.status === 'Paid')      paid[wk]  += a;
      if (inv.status === 'Pending')   pend[wk]  += a;
      if (inv.status === 'Overdue')   over[wk]  += a;
      if (inv.status === 'Partial')   part[wk]  += a;
      if (inv.status === 'Draft')     draft[wk] += a;
      if (inv.status === 'Cancelled') canc[wk]  += a;
    });
    return { labels: weeks, paid, pending: pend, overdue: over, partial: part, draft, cancelled: canc };
  }
  const curYear = now.getFullYear();
  const years = [curYear - 3, curYear - 2, curYear - 1, curYear].map(String);
  const paid = Array(4).fill(0), pend = Array(4).fill(0), over = Array(4).fill(0), part = Array(4).fill(0), draft = Array(4).fill(0), canc = Array(4).fill(0);
  STATE.invoices.forEach(inv => {
    if (!inv.issued) return;
    const idx = years.indexOf(String(new Date(inv.issued).getFullYear())), a = parseFloat(inv.amount) || 0;
    if (idx < 0) return;
    if (inv.status === 'Paid')      paid[idx]  += a;
    if (inv.status === 'Pending')   pend[idx]  += a;
    if (inv.status === 'Overdue')   over[idx]  += a;
    if (inv.status === 'Partial')   part[idx]  += a;
    if (inv.status === 'Draft')     draft[idx] += a;
    if (inv.status === 'Cancelled') canc[idx]  += a;
  });
  return { labels: years, paid, pending: pend, overdue: over, partial: part, draft, cancelled: canc };
}

function renderRevenueChart(mode) {
  const ctx = document.getElementById('revenueChart');
  if (!ctx) return;
  const d = buildLiveChartData(mode);
  if (revenueChartInstance) revenueChartInstance.destroy();
  revenueChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: d.labels,
      datasets: [
        { label: 'Paid',      data: d.paid,      backgroundColor: 'rgba(0,137,123,.80)',  borderRadius: 4, borderSkipped: false },
        { label: 'Pending',   data: d.pending,   backgroundColor: 'rgba(249,168,37,.70)', borderRadius: 4, borderSkipped: false },
        { label: 'Overdue',   data: d.overdue,   backgroundColor: 'rgba(229,57,53,.65)',  borderRadius: 4, borderSkipped: false },
        { label: 'Partial',   data: d.partial,   backgroundColor: 'rgba(102,187,106,.70)', borderRadius: 4, borderSkipped: false },
        { label: 'Draft',     data: d.draft,     backgroundColor: 'rgba(189,189,189,.60)', borderRadius: 4, borderSkipped: false },
        { label: 'Cancelled', data: d.cancelled, backgroundColor: 'rgba(120,144,156,.55)', borderRadius: 4, borderSkipped: false },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false, animation: { duration: 600 },
      plugins: { legend: { position: 'top', labels: { font: { family: "'Public Sans'", size: 11 }, boxWidth: 10 } } },
      scales: {
        x: { stacked: true, grid: { display: false }, ticks: { font: { family: "'Public Sans'", size: 10 }, maxRotation: 0 } },
        y: {
          stacked: true, grid: { color: '#F0F0F0' }, beginAtZero: true,
          ticks: { font: { family: "'Public Sans'", size: 10 }, callback: v => (STATE.settings.currency || '₹') + (v >= 1000 ? (v / 1000).toFixed(v % 1000 ? 1 : 0) + 'K' : v) },
          afterDataLimits(scale) { if (scale.max === 0) scale.max = 1000; },
        },
      },
    },
  });
}

function switchChart(mode, btn) {
  document.querySelectorAll('.cf-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  renderRevenueChart(mode);
}

function renderDonutChart() {
  const ctx = document.getElementById('donutChart');
  if (!ctx) return;
  const paid      = STATE.invoices.filter(i => i.status === 'Paid').length;
  const pending   = STATE.invoices.filter(i => i.status === 'Pending').length;
  const overdue   = STATE.invoices.filter(i => i.status === 'Overdue').length;
  const partial   = STATE.invoices.filter(i => i.status === 'Partial').length;
  const draft     = STATE.invoices.filter(i => i.status === 'Draft').length;
  const estimate  = STATE.invoices.filter(i => i.status === 'Estimate').length;
  const cancelled = STATE.invoices.filter(i => i.status === 'Cancelled').length;
  const total     = STATE.invoices.length;
  const labels = ['Paid', 'Pending', 'Overdue', 'Partial', 'Draft', 'Estimate', 'Cancelled'];
  const vals   = [paid, pending, overdue, partial, draft, estimate, cancelled];
  const colors = ['#00897B', '#FFA726', '#EF5350', '#66BB6A', '#BDBDBD', '#3949AB', '#78909C'];
  const centerPlugin = {
    id: 'donutCenter',
    afterDraw(chart) {
      const { ctx: c, chartArea: { top, bottom, left, right } } = chart;
      const cx = (left + right) / 2, cy = (top + bottom) / 2;
      c.save();
      c.textAlign = 'center'; c.textBaseline = 'middle';
      c.font = "bold 22px 'Public Sans',sans-serif"; c.fillStyle = '#1a1a1a';
      c.fillText(total, cx, cy - 8);
      c.font = "11px 'Public Sans',sans-serif"; c.fillStyle = '#9e9e9e';
      c.fillText('invoices', cx, cy + 12);
      c.restore();
    },
  };
  if (donutChartInstance) donutChartInstance.destroy();
  donutChartInstance = new Chart(ctx, {
    type: 'doughnut',
    data: { labels, datasets: [{ data: vals, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } },
    plugins: [centerPlugin],
  });
  const legend = document.getElementById('donutLegend');
  if (!legend) return;
  legend.innerHTML = labels.map((l, i) => `<div class="dl-item"><div class="dl-dot" style="background:${colors[i]}"></div><span class="dl-label">${l}</span><span class="dl-val">${vals[i]}</span></div>`).join('');
}

function renderDashRecent() {
  const el = document.getElementById('dashRecentList');
  if (!el) return;
  const recent = [...STATE.invoices]
    .filter(i => i.status !== 'Cancelled')
    .sort((a, b) => new Date(b.issued || b.created_at || 0) - new Date(a.issued || a.created_at || 0))
    .slice(0, 8);
  if (!recent.length) { el.innerHTML = '<div style="text-align:center;padding:24px;color:var(--muted)">No invoices yet</div>'; return; }
  el.innerHTML = recent.map(inv => {
    const c = STATE.clients.find(x => x.id === inv.client) || { name: inv.client_name || inv.clientName || inv.client || 'Unknown', color: '#00897B' };
    const initials = getInitials(c.name);
    const pmt = STATE.payments.find(p => p.inv === inv.num);
    const pmtTag = pmt ? `<span style="font-size:9px;padding:1px 5px;border-radius:4px;background:var(--teal-bg);color:var(--teal);font-weight:700;margin-left:4px">${pmt.method.split(' ')[0]}</span>` : '';
    const df = d => d ? fmt_date_l(d, { day: '2-digit', month: 'short' }) : '';
    const canSendWA = ['Pending', 'Overdue', 'Partial'].includes(inv.status);
    // NOTE: sendWAForInvoice() and openPreviewModal() are defined in
    // invoices.js, not this file — see header comment.
    const waBtn = canSendWA ? `<button class="dri-act-btn wa" onclick="event.stopPropagation();sendWAForInvoice(STATE.invoices.find(x=>x.id==='${inv.id}'))"><i class="fab fa-whatsapp"></i> Send WA</button>` : '';
    return `<div class="dash-recent-item" style="cursor:pointer" onclick="openPreviewModal('${inv.id}')">
      <div class="dri-avatar" style="background:${c.color}">${isValidImg(c.image) ? `<img src="${c.image}" style="width:100%;height:100%;object-fit:cover;border-radius:8px" onerror="this.style.display='none'">` : initials}</div>
      <div class="dri-info">
        <div class="dri-name">${inv.num}${pmtTag}</div>
        <div class="dri-meta">${c.name} · ${inv.service}</div>
        <div class="dri-meta" style="margin-top:1px;font-size:10px"><i class="fas fa-calendar-alt" style="color:var(--muted2);width:10px"></i> ${df(inv.issued)} &nbsp;·&nbsp; Due: <span style="color:${inv.status === 'Overdue' ? 'var(--red)' : 'inherit'}">${df(inv.due)}</span></div>
        <div class="dri-actions">${waBtn}</div>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <div class="dri-amount">${fmt_money(inv.amount)}</div>
        <span class="badge badge-${inv.status.toLowerCase()}">${inv.status}</span>
        ${inv.status === 'Paid' ? (() => {
          const invId = String(inv.id);
          const lastPmt = STATE.payments.filter(p => p.invoice_id && String(p.invoice_id) === invId)
            .sort((a, b) => new Date(b.date || b.payment_date || 0) - new Date(a.date || a.payment_date || 0))[0];
          const pd = lastPmt ? (lastPmt.date || lastPmt.payment_date || '') : '';
          return pd ? `<div style="font-size:10px;color:#1565C0;font-weight:600;margin-top:3px;white-space:nowrap"><i class="fas fa-calendar-check" style="font-size:9px"></i> ${fmt_date_l(pd, { day: '2-digit', month: 'short' })}</div>` : '';
        })() : ''}
      </div>
    </div>`;
  }).join('');
}

function renderCalendar() {
  const el = document.getElementById('calendarWidget');
  if (!el) return;
  const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  const days = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
  const firstDay = new Date(calYear, calMonth, 1).getDay();
  const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
  const today = new Date();
  const evMap = {};
  STATE.invoices.forEach(inv => {
    const dueFld = inv.due || inv.due_date;
    if (!dueFld) return;
    let t;
    if (inv.status === 'Paid') {
      t = 'paid';
    } else if (inv.status === 'Overdue') {
      t = 'overdue';
    } else {
      const dueD = new Date(dueFld); dueD.setHours(23, 59, 59, 999);
      t = (!isNaN(dueD) && dueD < today) ? 'overdue' : 'due';
    }
    if (!evMap[dueFld]) evMap[dueFld] = [];
    evMap[dueFld].push({ type: t, label: inv.num });
  });
  CAL_EVENTS.forEach(e => { if (!evMap[e.date]) evMap[e.date] = []; evMap[e.date].push(e); });
  let html = `<div class="cal-month-title">${monthNames[calMonth]} ${calYear}</div><div class="cal-grid">`;
  days.forEach(d => { html += `<div class="cal-day-name">${d}</div>`; });
  for (let i = 0; i < firstDay; i++) html += '<div class="cal-day other-month"></div>';
  for (let d = 1; d <= daysInMonth; d++) {
    const ds = `${calYear}-${String(calMonth + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    const evs = evMap[ds] || [];
    const isToday = today.getFullYear() === calYear && today.getMonth() === calMonth && today.getDate() === d;
    const hasOverdue = evs.some(e => e.type === 'overdue');
    const hasDue     = evs.some(e => e.type === 'due');
    const hasPaid    = evs.some(e => e.type === 'paid');
    let cls = 'cal-day';
    if (isToday) cls += ' today';
    if (hasOverdue) cls += ' has-overdue';
    else if (hasDue) cls += ' has-due';
    else if (hasPaid) cls += ' has-paid';
    const tip = evs.map(e => e.label).join(', ');
    const dotColor = isToday ? '#fff' : hasOverdue ? 'var(--red)' : hasDue ? 'var(--amber)' : 'var(--green)';
    const dot = evs.length > 1 ? `<span style="position:absolute;top:1px;right:2px;font-size:7px;font-weight:800;color:${dotColor}">${evs.length}</span>` : '';
    html += `<div class="${cls}" title="${tip}" style="position:relative">${d}${dot}</div>`;
  }
  html += '</div>';
  el.innerHTML = html;
}
function calPrev() { calMonth--; if (calMonth < 0) { calMonth = 11; calYear--; } renderCalendar(); }
function calNext() { calMonth++; if (calMonth > 11) { calMonth = 0; calYear++; } renderCalendar(); }

function renderDashKpis() {
  const el = document.getElementById('dashQuickKpis');
  if (!el) return;
  const tot   = STATE.invoices.reduce((s, i) => s + i.amount, 0);
  const paid  = STATE.invoices.filter(i => i.status === 'Paid').reduce((s, i) => s + i.amount, 0);
  const over  = STATE.invoices.filter(i => i.status === 'Overdue').length;
  const tm    = new Date().getMonth();
  const mInv  = STATE.invoices.filter(i => i.issued && new Date(i.issued).getMonth() === tm).length;
  const rate  = tot > 0 ? Math.round(paid / tot * 100) : 0;
  el.innerHTML = [
    { l: 'Collection Rate', v: rate + '%',             ic: 'fa-percent',            col: '#00897B', bg: '#E0F2F1' },
    { l: 'This Month',      v: mInv + ' inv',           ic: 'fa-file-invoice',       col: '#1565C0', bg: '#E3F2FD' },
    { l: 'Overdue',         v: over,                    ic: 'fa-exclamation-circle', col: '#e53935', bg: '#FFEBEE' },
    { l: 'Clients',         v: STATE.clients.length,    ic: 'fa-users',              col: '#6A1B9A', bg: '#F3E5F5' },
    { l: 'Avg Invoice',     v: STATE.invoices.length ? fmt_money(Math.round(tot / STATE.invoices.length)) : '₹0', ic: 'fa-chart-line', col: '#E65100', bg: '#FBE9E7' },
  ].map(k => `<div style="display:flex;align-items:center;gap:9px;padding:7px 0;border-bottom:1px solid var(--border)">
    <div style="width:32px;height:32px;border-radius:9px;background:${k.bg};display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <i class="fas ${k.ic}" style="color:${k.col};font-size:13px"></i>
    </div>
    <div><div style="font-size:10px;color:var(--muted)">${k.l}</div><div style="font-weight:700;font-size:13px">${k.v}</div></div>
  </div>`).join('');

  const waKpiEl = document.getElementById('dashWAKpiRow');
  if (waKpiEl) {
    const pendWA      = STATE.invoices.filter(i => i.status === 'Pending' || i.status === 'Overdue').length;
    const overWA      = STATE.invoices.filter(i => i.status === 'Overdue').length;
    const paidTM      = STATE.invoices.filter(i => {
      const d = new Date();
      return i.status === 'Paid' && i.issued &&
             new Date(i.issued).getMonth() === d.getMonth() &&
             new Date(i.issued).getFullYear() === d.getFullYear();
    }).length;
    const waClients   = STATE.clients.filter(c => c.wa || c.whatsapp || c.phone).length;
    const partialInvs = STATE.invoices.filter(i => i.status === 'Partial').length;
    const splitPmts   = STATE.payments.filter(p => (p.method || '').startsWith('Split')).length;
    const miniCards = [
      { ic: 'fa-paper-plane',          col: '#25D366', label: 'Need Follow-up',  val: pendWA,      sub: 'pending/overdue' },
      { ic: 'fa-exclamation-triangle', col: '#e53935', label: 'Overdue Alerts',  val: overWA,      sub: 'send now' },
      { ic: 'fa-check-circle',         col: '#00897B', label: 'Paid This Month', val: paidTM,      sub: 'receipts sent' },
      { ic: 'fa-clock',                col: '#E65100', label: 'Partial Invoices', val: partialInvs, sub: 'awaiting balance' },
      { ic: 'fa-code-branch',          col: '#7B1FA2', label: 'Split Payments',  val: splitPmts,   sub: 'recorded' },
      { ic: 'fa-address-book',         col: '#1565C0', label: 'WA-Ready Clients', val: waClients,  sub: 'have phone #' },
    ].map(c => `<div onclick="window.location.href='/pages/whatsapp.php'" style="flex:1;min-width:110px;background:${c.col}0f;border:1.5px solid ${c.col}28;border-radius:10px;padding:9px 11px;cursor:pointer;transition:.2s" onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow=''">
      <div style="display:flex;align-items:center;gap:5px;margin-bottom:3px">
        <i class="fas ${c.ic}" style="color:${c.col};font-size:11px"></i>
        <span style="font-size:9px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.4px">${c.label}</span>
      </div>
      <div style="font-size:22px;font-weight:800;color:${c.col};line-height:1">${c.val}</div>
      <div style="font-size:9px;color:var(--muted);margin-top:1px">${c.sub}</div>
    </div>`).join('');

    waKpiEl.innerHTML = `<div style="display:flex;gap:8px;flex-wrap:wrap">${miniCards}</div>`;
  }

  const waEl = document.getElementById('dashWACard');
  if (waEl) {
    const wa     = STATE.settings.wa || {};
    const hasAPI = !!(wa.token && wa.pid);
    const mode   = wa.msg_mode === 'template' ? '✅ Template Mode' : '💬 Session Mode';
    const onCount = [wa.auto_inv === '1', wa.auto_estimate === '1', wa.auto_paid !== '0', wa.auto_partial !== '0', wa.auto_remind !== '0', wa.auto_overdue !== '0', wa.auto_followup === '1'].filter(Boolean).length;
    const toggles = [
      { key: 'auto_inv',      label: 'New Invoice',  icon: '📄', val: wa.auto_inv === '1' },
      { key: 'auto_paid',     label: 'Receipt',      icon: '✅', val: wa.auto_paid !== '0' },
      { key: 'auto_partial',  label: 'Partial',      icon: '💛', val: wa.auto_partial !== '0' },
      { key: 'auto_remind',   label: 'Due Reminder', icon: '🔔', val: wa.auto_remind !== '0' },
      { key: 'auto_overdue',  label: 'Overdue Alert', icon: '⚠️', val: wa.auto_overdue !== '0' },
      { key: 'auto_followup', label: 'Follow-up',    icon: '📋', val: wa.auto_followup === '1' },
    ];
    const pillsHTML = toggles.map(t => `<div onclick="window.location.href='/pages/whatsapp.php'" style="display:flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;cursor:pointer;flex-shrink:0;background:${t.val ? '#25D36612' : 'var(--bg)'};border:1px solid ${t.val ? '#25D36630' : 'var(--border)'}">
      <span>${t.icon}</span>
      <span style="font-size:11px;font-weight:600;color:${t.val ? '#1a7a3c' : 'var(--muted)'}">${t.label}</span>
      <span style="width:5px;height:5px;border-radius:50%;flex-shrink:0;background:${t.val ? '#25D366' : '#ccc'}"></span>
    </div>`).join('');

    waEl.innerHTML = `
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:#e8f5e9;border:1.5px solid #25D366;border-radius:10px;padding:10px 14px;box-shadow:0 0 12px #25D36640,0 0 28px #25D36618;animation:waGlow 2.5s ease-in-out infinite">
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
          <div style="width:32px;height:32px;background:#25D366;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px">📱</div>
          <div>
            <div style="color:#1b5e20;font-size:13px;font-weight:800;line-height:1.2">WhatsApp</div>
            <div style="color:#388E3C;font-size:10px">${mode}</div>
          </div>
        </div>
        <div style="padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;flex-shrink:0;background:${hasAPI ? '#25D36615' : '#f5f5f5'};color:${hasAPI ? '#1a7a3c' : '#999'};border:1px solid ${hasAPI ? '#25D36635' : '#e0e0e0'}">
          ${hasAPI ? '● Connected' : '○ No API'}
        </div>
        <div style="width:1px;height:28px;background:var(--border);flex-shrink:0"></div>
        ${pillsHTML}
        <div style="margin-left:auto;display:flex;align-items:center;gap:8px;flex-shrink:0">
          <span style="font-size:11px;color:#2e7d32;font-weight:600">${onCount}/6 active</span>
          <button onclick="window.location.href='/pages/whatsapp.php'" style="padding:5px 12px;background:#25D36615;color:#1a7a3c;border:1px solid #25D36635;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit">
            <i class="fas fa-cog"></i> Manage
          </button>
        </div>
      </div>`;
  }

  const partEl = document.getElementById('dashPartialCard');
  if (partEl) {
    const partials = STATE.invoices.filter(i => i.status === 'Partial');
    if (partials.length === 0) { partEl.innerHTML = ''; }
    else {
      const rows = partials.map(inv => {
        const c       = STATE.clients.find(x => String(x.id) === String(inv.client)) || {};
        const pmts    = STATE.payments.filter(p => p.invoice_id && String(p.invoice_id) === String(inv.id));
        const paidAmt = pmts.reduce((s, p) => s + parseFloat(p.amount || 0), 0);
        const remAmt  = Math.max(0, (inv.amount || 0) - paidAmt);
        const pct     = inv.amount > 0 ? Math.round(paidAmt / inv.amount * 100) : 0;
        // NOTE: openPreviewModal() is defined in invoices.js, not this file.
        return `<div onclick="openPreviewModal('${inv.id}')" style="cursor:pointer;padding:10px 14px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:center" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
          <div style="flex:1;min-width:0">
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:3px">
              <span style="font-weight:700;font-size:13px">${inv.num || inv.invoice_number || ''}</span>
              <span style="font-size:11px;padding:2px 7px;border-radius:10px;background:#FFF3E0;color:#E65100;font-weight:700">Partial</span>
            </div>
            <div style="font-size:11px;color:var(--muted)">${c.name || inv.clientName || ''} · ${inv.service || inv.service_type || ''}</div>
            <div style="margin-top:6px;background:var(--border);border-radius:4px;height:5px;overflow:hidden">
              <div style="height:100%;width:${pct}%;background:linear-gradient(90deg,#4CAF50,#8BC34A);border-radius:4px;transition:.4s"></div>
            </div>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <div style="font-size:11px;color:#388E3C;font-weight:700">${fmt_money(paidAmt, inv.currency || '₹')} paid</div>
            <div style="font-size:12px;color:#E65100;font-weight:800">${fmt_money(remAmt, inv.currency || '₹')} due</div>
            <div style="font-size:10px;color:var(--muted)">${pmts.length} instalment${pmts.length !== 1 ? 's' : ''}</div>
          </div>
        </div>`;
      }).join('');
      partEl.innerHTML = `<div class="dash-card" style="padding:0;overflow:hidden">
        <div class="card-header" style="padding:12px 16px">
          <span class="card-title">⚡ Partial Payments</span>
          <span style="font-size:11px;color:#E65100;font-weight:700">${partials.length} invoice${partials.length !== 1 ? 's' : ''} pending clearance</span>
        </div>
        ${rows}
      </div>`;
    }
  }
}

function renderDashTopClients() {
  const el = document.getElementById('dashTopClients'); if (!el) return;
  const top = STATE.clients.map(c => ({ ...c, rev: STATE.invoices.filter(i => i.client === c.id && i.status === 'Paid').reduce((s, i) => s + i.amount, 0) })).sort((a, b) => b.rev - a.rev).slice(0, 5);
  const mx = top[0]?.rev || 1;
  el.innerHTML = top.map(c => `<div style="margin-bottom:9px">
    <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:2px"><span style="font-weight:600">${c.name}</span><span style="color:var(--muted);font-family:var(--mono)">${fmt_money(c.rev)}</span></div>
    <div style="height:5px;background:var(--border);border-radius:3px"><div style="height:100%;width:${Math.round(c.rev / mx * 100)}%;background:${c.color};border-radius:3px"></div></div>
  </div>`).join('') || '<div style="color:var(--muted);font-size:11px;text-align:center;padding:16px">No data yet</div>';
}

function renderDashAlerts() {
  const over = STATE.invoices.filter(i => i.status === 'Overdue');
  const soon = STATE.invoices.filter(i => {
    if (i.status !== 'Pending' || !i.due) return false;
    const d = (new Date(i.due) - new Date()) / 864e5;
    return d >= 0 && d <= 3;
  });
  const threeDaysAgo = new Date(); threeDaysAgo.setDate(threeDaysAgo.getDate() - 3);
  const staleDrafts = STATE.invoices.filter(i => {
    if (i.status !== 'Draft') return false;
    const issued = new Date(i.issued || i.created_at || 0);
    return issued < threeDaysAgo;
  });

  const oa = document.getElementById('dashOverdueAlert');
  const da = document.getElementById('dashDueSoonAlert');
  const dr = document.getElementById('dashDraftAlert');

  if (oa) { oa.style.display = over.length ? '' : 'none'; if (over.length) oa.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${over.length} Overdue`; }
  if (da) { da.style.display = soon.length ? '' : 'none'; if (soon.length) da.innerHTML = `<i class="fas fa-clock"></i> ${soon.length} Due Soon`; }
  if (dr) {
    dr.style.display = staleDrafts.length ? '' : 'none';
    if (staleDrafts.length) dr.innerHTML = `<i class="fas fa-file-alt"></i> ${staleDrafts.length} Unsent Draft${staleDrafts.length > 1 ? 's' : ''}`;
    dr.title = staleDrafts.length ? `${staleDrafts.length} draft invoice${staleDrafts.length > 1 ? 's have' : ' has'} not been sent for 3+ days — click to view` : '';
    // dr's href already points to /pages/invoices.php?filter=draft (set in dashboard.php)
  }
}

function renderNotifications() {
  const today = new Date();
  const items = [];

  STATE.invoices.filter(i => i.status === 'Overdue').slice(0, 3).forEach(inv => {
    const c = STATE.clients.find(x => x.id === inv.client) || {};
    items.push({ type: 'warn', text: `<b>${c.name || inv.clientName || inv.client}</b> invoice ${inv.num} is overdue` });
  });

  STATE.invoices.filter(i => {
    if (i.status !== 'Pending' || !i.due) return false;
    const diff = (new Date(i.due) - today) / 86400000;
    return diff >= 0 && diff <= 3;
  }).slice(0, 3).forEach(inv => {
    const c = STATE.clients.find(x => x.id === inv.client) || {};
    const dueDate = new Date(inv.due).toLocaleDateString(_moneyLocale(), { day: '2-digit', month: 'short' });
    items.push({ type: 'info', text: `<b>${c.name || inv.clientName || inv.client}</b> — ${inv.num} due ${dueDate}` });
  });

  STATE.payments.slice(0, 2).forEach(p => {
    items.push({ type: 'info', text: `Payment received from <b>${p.client}</b> — ${fmt_money(p.amount)}` });
  });

  const el = document.getElementById('notifItems');
  if (el) {
    if (!items.length) {
      el.innerHTML = '<div style="padding:14px 16px;color:var(--muted);font-size:13px;text-align:center">No new notifications</div>';
    } else {
      el.innerHTML = items.map(n =>
        `<div class="np-item ${n.type === 'warn' ? 'np-warn' : 'np-info'}">
          <i class="fas ${n.type === 'warn' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
          <div>${n.text}</div>
        </div>`
      ).join('');
    }
  }

  const bell = document.getElementById('bellCount');
  if (bell) {
    const count = items.length;
    bell.textContent = count;
    bell.style.display = count > 0 ? 'flex' : 'none';
  }
}

// ================================================================
// Product Business Dashboard — added when building the business-type
// aware dashboard (see dashboard.php's $showProduct section). Fresh
// build following this file's existing visual conventions, not a
// literal port of the SPA's renderProductDashboard() (different DOM
// entirely — see that function's header comment for why).
// ================================================================
let pdbTrendChartInstance = null;

function renderProductDashboard() {
  const from = document.getElementById('pdb-from')?.value;
  const to = document.getElementById('pdb-to')?.value;
  if (!from || !to) return;

  const sales = (STATE.sales || []).filter(s => {
    const d = (s.sale_date || '').slice(0, 10);
    return d >= from && d <= to && s.status !== 'Cancelled';
  });
  const purchases = (STATE.purchases || []).filter(p => {
    const d = (p.purchase_date || '').slice(0, 10);
    return d >= from && d <= to;
  });

  const salesTotal = sales.reduce((sum, s) => sum + (parseFloat(s.total) || 0), 0);
  const purchasesTotal = purchases.reduce((sum, p) => sum + (parseFloat(p.total) || 0), 0);

  const stockValue = (STATE.stock || []).reduce((sum, s) => {
    const qty = parseFloat(s.current_stock ?? s.available_stock) || 0;
    const prod = (STATE.products || []).find(p => String(p.id) === String(s.product_id));
    const rate = parseFloat(prod?.sale_rate ?? prod?.rate) || 0;
    return sum + qty * rate;
  }, 0);
  const lowStockCount = (STATE.stock || []).filter(s => {
    const qty = parseFloat(s.current_stock ?? s.available_stock) || 0;
    const reorder = parseFloat(s.reorder_level) || 0;
    return reorder > 0 && qty <= reorder;
  }).length;

  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  set('pdb-sales-total', fmt_money(salesTotal));
  set('pdb-sales-count', sales.length + ' sale' + (sales.length !== 1 ? 's' : ''));
  set('pdb-purchases-total', fmt_money(purchasesTotal));
  set('pdb-purchases-count', purchases.length + ' purchase' + (purchases.length !== 1 ? 's' : ''));
  set('pdb-stock-value', fmt_money(stockValue));
  set('pdb-stock-count', (STATE.stock || []).length + ' items');
  set('pdb-lowstock-count', lowStockCount);

  renderPdbTrendChart(sales, purchases, from, to);
  renderPdbRecentSales(sales);
  renderPdbTopProducts(sales);
}

function renderPdbTrendChart(sales, purchases, from, to) {
  const ctx = document.getElementById('pdbTrendChart');
  if (!ctx) return;

  // Bucket by day if the range is short, otherwise by week
  const fromD = new Date(from), toD = new Date(to);
  const days = Math.max(1, Math.round((toD - fromD) / 86400000));
  const bucketByWeek = days > 45;
  const buckets = [];
  const salesByBucket = {}, purchasesByBucket = {};

  const bucketKey = d => {
    const dt = new Date(d);
    if (bucketByWeek) {
      const weekStart = new Date(dt);
      weekStart.setDate(dt.getDate() - dt.getDay());
      return fmt_date(weekStart);
    }
    return fmt_date(dt);
  };

  for (let d = new Date(fromD); d <= toD; d.setDate(d.getDate() + (bucketByWeek ? 7 : 1))) {
    const key = bucketKey(d);
    if (!buckets.includes(key)) buckets.push(key);
  }
  buckets.forEach(k => { salesByBucket[k] = 0; purchasesByBucket[k] = 0; });
  sales.forEach(s => { const k = bucketKey(s.sale_date); if (k in salesByBucket) salesByBucket[k] += parseFloat(s.total) || 0; });
  purchases.forEach(p => { const k = bucketKey(p.purchase_date); if (k in purchasesByBucket) purchasesByBucket[k] += parseFloat(p.total) || 0; });

  if (pdbTrendChartInstance) pdbTrendChartInstance.destroy();
  pdbTrendChartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: buckets.map(k => fmt_date_l(k, { day: '2-digit', month: 'short' })),
      datasets: [
        { label: 'Sales', data: buckets.map(k => salesByBucket[k]), borderColor: '#00897B', backgroundColor: 'rgba(0,137,123,.12)', fill: true, tension: 0.3 },
        { label: 'Purchases', data: buckets.map(k => purchasesByBucket[k]), borderColor: '#1976D2', backgroundColor: 'rgba(25,118,210,.10)', fill: true, tension: 0.3 },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false, animation: { duration: 500 },
      plugins: { legend: { position: 'top', labels: { font: { family: "'Public Sans'", size: 11 }, boxWidth: 10 } } },
      scales: {
        x: { grid: { display: false }, ticks: { font: { family: "'Public Sans'", size: 10 }, maxRotation: 0 } },
        y: { grid: { color: '#F0F0F0' }, beginAtZero: true, ticks: { font: { family: "'Public Sans'", size: 10 }, callback: v => (STATE.settings.currency || '₹') + (v >= 1000 ? (v / 1000).toFixed(v % 1000 ? 1 : 0) + 'K' : v) } },
      },
    },
  });
}

function renderPdbRecentSales(sales) {
  const el = document.getElementById('pdbRecentSales');
  if (!el) return;
  const recent = [...sales].sort((a, b) => new Date(b.sale_date || 0) - new Date(a.sale_date || 0)).slice(0, 6);
  if (!recent.length) { el.innerHTML = '<div style="text-align:center;padding:24px;color:var(--muted)">No sales in this period</div>'; return; }
  el.innerHTML = recent.map(s => {
    const cust = (STATE.clients || []).find(c => String(c.id) === String(s.customer_id));
    const name = cust?.name || s.customer_name || 'Walk-in';
    return `<div class="dash-recent-item" style="cursor:pointer" onclick="window.location.href='/pages/sales.php'">
      <div class="dri-avatar" style="background:#00897B">${getInitials(name)}</div>
      <div class="dri-info">
        <div class="dri-name">${escHtml(s.invoice_no || '')}</div>
        <div class="dri-meta">${escHtml(name)}</div>
        <div class="dri-meta" style="margin-top:1px;font-size:10px"><i class="fas fa-calendar-alt" style="color:var(--muted2);width:10px"></i> ${fmt_date_l(s.sale_date, { day: '2-digit', month: 'short' })}</div>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <div class="dri-amount">${fmt_money(s.total)}</div>
      </div>
    </div>`;
  }).join('');
}

function renderPdbTopProducts(sales) {
  const el = document.getElementById('pdbTopProducts');
  if (!el) return;
  const byProduct = {};
  sales.forEach(s => {
    (s.items || []).forEach(item => {
      const key = item.product_id || item.name;
      if (!key) return;
      byProduct[key] = byProduct[key] || { name: item.name || 'Item', qty: 0, total: 0 };
      byProduct[key].qty += parseFloat(item.qty) || 0;
      byProduct[key].total += parseFloat(item.amount ?? item.total) || 0;
    });
  });
  const top = Object.values(byProduct).sort((a, b) => b.total - a.total).slice(0, 6);
  if (!top.length) { el.innerHTML = '<div style="text-align:center;padding:16px;color:var(--muted);font-size:12px">No data yet</div>'; return; }
  el.innerHTML = top.map(p => `
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);font-size:12px">
      <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:110px">${escHtml(p.name)}</div>
      <div style="text-align:right;color:var(--muted)">${p.qty.toFixed(0)}</div>
    </div>
  `).join('');
}
