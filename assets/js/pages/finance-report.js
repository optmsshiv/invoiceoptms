// ============================================================
// finance-report.js — page-specific JS for pages/finance-report.php
// Depends on: common.js, shared-data.js
// No existing pre-built file for this page — built fresh from the
// current SPA's api/finance_report.php-backed report.
// ============================================================
const BIZ_FROM_DATE = '2026-05-01';

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['settings']);
  renderFinanceReport();
});

async function renderFinanceReport() {
  if (!document.getElementById('fr-from').value) {
    document.getElementById('fr-from').value = frMonthStart();
    document.getElementById('fr-to').value = fmt_date(new Date());
  }
  const from = document.getElementById('fr-from').value;
  const to = document.getElementById('fr-to').value;
  const wh = document.getElementById('fr-warehouse').value;

  try {
    const params = new URLSearchParams({ date_from: from, date_to: to });
    if (wh) params.set('warehouse', wh);
    const r = await api('/api/finance_report.php?' + params.toString());

    const s = r.stats;
    const setStat = (id, val, chgId, chg) => {
      document.getElementById(id).textContent = fmt_money(val.value !== undefined ? val.value : val);
      const chgEl = document.getElementById(chgId);
      const pct = val.change ?? chg ?? 0;
      chgEl.innerHTML = `<i class="fas fa-arrow-${pct>=0?'up':'down'}"></i> ${Math.abs(pct)}% vs Previous Period`;
      chgEl.style.color = pct >= 0 ? '#00897B' : '#E53935';
    };
    setStat('fr-stat-sales', s.total_sales, 'fr-chg-sales');
    setStat('fr-stat-purchase', s.total_purchase, 'fr-chg-purchase');
    setStat('fr-stat-collections', s.total_collections, 'fr-chg-collections');
    setStat('fr-stat-payments', s.total_payments, 'fr-chg-payments');
    // Expenses KPI
    const expTotal = r.expenses?.total || 0;
    const expEl = document.getElementById('fr-stat-expenses');
    if (expEl) expEl.textContent = fmt_money(expTotal);
    const expChgEl = document.getElementById('fr-chg-expenses');
    if (expChgEl) expChgEl.textContent = (r.expenses?.count||0) + ' entries';
    setStat('fr-stat-profit', s.net_profit, 'fr-chg-profit');

    // Cash flow
    document.getElementById('fr-cf-collections').textContent = fmt_money(r.cash_flow.total_collections);
    document.getElementById('fr-cf-payments').textContent = fmt_money(r.cash_flow.total_payments);
    const netEl = document.getElementById('fr-cf-net');
    netEl.textContent = fmt_money(r.cash_flow.net_flow);
    netEl.style.color = r.cash_flow.net_flow >= 0 ? '#00897B' : '#E53935';

    // Income / Expense tables
    const incTotal = r.income_heads.reduce((s,h)=>s+h.amount, 0);
    document.getElementById('fr-income-tbody').innerHTML = r.income_heads.map((h,i) => `
      <tr><td>${i+1}</td><td>${escHtml(h.head)}</td><td>${fmt_money(h.amount)}</td><td>${incTotal?((h.amount/incTotal)*100).toFixed(1):'0.0'}%</td></tr>`).join('')
      + `<tr style="font-weight:700"><td colspan="2">Total</td><td>${fmt_money(incTotal)}</td><td>100.0%</td></tr>`;

    const expHeadTotal = r.expense_heads.reduce((s,h)=>s+h.amount, 0);
    document.getElementById('fr-expense-tbody').innerHTML = (r.expense_heads.length ? r.expense_heads.map((h,i) => `
      <tr><td>${i+1}</td><td>${escHtml(h.head)}</td><td>${fmt_money(h.amount)}</td><td>${expHeadTotal?((h.amount/expHeadTotal)*100).toFixed(1):'0.0'}%</td></tr>`).join('')
      : `<tr><td colspan="4" style="text-align:center;color:var(--muted);padding:16px">No purchases in this period</td></tr>`)
      + (expHeadTotal ? `<tr style="font-weight:700"><td colspan="2">Total</td><td>${fmt_money(expHeadTotal)}</td><td>100.0%</td></tr>` : '');

    // Payment mode summary
    const pmTotal = r.payment_modes.reduce((s,m)=>s+m.amount, 0);
    const pmColors = { 'Cash':'#2E7D32','Bank Transfer':'#1565C0','UPI':'#6A4C93','Cheque':'#E65100','Split Payment':'#455A64' };
    document.getElementById('fr-paymode-list').innerHTML = r.payment_modes.length ? r.payment_modes.map(m => `
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="display:flex;align-items:center;gap:8px"><span style="width:9px;height:9px;border-radius:50%;background:${pmColors[m.mode]||'#889'}"></span>${escHtml(m.mode)}</span>
        <span><strong>${fmt_money(m.amount)}</strong> <span style="color:var(--muted);font-size:11px">(${pmTotal?((m.amount/pmTotal)*100).toFixed(2):'0.00'}%)</span></span>
      </div>`).join('') + `<div style="display:flex;justify-content:space-between;border-top:1px dashed var(--border);padding-top:10px;margin-top:4px;font-weight:700">
        <span>Total</span><span>${fmt_money(pmTotal)} <span style="color:var(--muted);font-size:11px">(100%)</span></span></div>`
      : `<div style="color:var(--muted);font-size:12px">No payments recorded in this period</div>`;

    renderFRCharts(r);

    // ── Trade Summary ─────────────────────────────────────────────
    const ts = r.trade_summary || {};
    const tsCont = document.getElementById('fr-trade-summary');
    if (tsCont && ts.pur_qty !== undefined) {
      const kgFmt = v => parseFloat(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' Kg';
      const dhaltaPct = ts.gross_wt > 0 ? (ts.dhalta_kg/ts.gross_wt*100).toFixed(2) : '0.00';
      const hasCharges = (ts.transport_amt||0)+(ts.loading_amt||0)+(ts.packing_amt||0)+(ts.other_amt||0) > 0;
      const netWt = (ts.gross_wt||0)-(ts.tare_wt||0);

      tsCont.innerHTML = `
        <!-- Clean comparison: Purchase vs Sale as two key-value lists -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:14px">

          <!-- PURCHASE -->
          <div class="pne-card" style="padding:0;overflow:hidden;border-top:3px solid #E53935">
            <div style="padding:10px 14px;background:#FFF5F5;font-size:11.5px;font-weight:700;color:#E53935;display:flex;align-items:center;gap:6px;border-bottom:1px solid var(--border)">
              <i class="fas fa-cart-shopping"></i> PURCHASE
            </div>
            ${[
              ['Total Qty',   kgFmt(ts.pur_qty),      '', 'Billable qty across all purchases'],
              ['Dhalta',      kgFmt(ts.dhalta_kg),    '#E65100', 'Weight deducted at purchase'],
              ['Billable Wt', kgFmt(ts.billable_wt), '#00897B', 'Net − Dhalta (what you pay for)'],
              ['Total Value', fmt_money(ts.pur_value),'#E53935', 'Bill total incl. charges'],
            ].map(([l,v,c,sub],i)=>`
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 14px;${i%2===0?'background:var(--bg)':''}">
              <div>
                <div style="font-size:11.5px;color:var(--muted)">${l}</div>
                ${sub ? `<div style="font-size:10px;color:var(--muted);opacity:.7;margin-top:1px">${sub}</div>` : ''}
              </div>
              <span style="font-size:13px;font-weight:700;${c?'color:'+c:''}">${v}</span>
            </div>`).join('')}
          </div>

          <!-- SALE -->
          <div class="pne-card" style="padding:0;overflow:hidden;border-top:3px solid #00897B">
            <div style="padding:10px 14px;background:#F0FAF8;font-size:11.5px;font-weight:700;color:#00897B;display:flex;align-items:center;gap:6px;border-bottom:1px solid var(--border)">
              <i class="fas fa-file-invoice-dollar"></i> SALE
            </div>
            ${[
              ['Net Wt',      kgFmt((ts.sale_gross_wt||0)-(ts.sale_tare_wt||0)),                                          '', 'Gross − Tare'],
              ['Dhalta',      kgFmt(ts.sale_dhalta_kg||0),                                                                 '#E65100', 'Weight deducted at delivery'],
              ['Billable Wt', kgFmt(Math.max(0,(ts.sale_gross_wt||0)-(ts.sale_tare_wt||0)-(ts.sale_dhalta_kg||0))),      '#00897B', 'Net − Dhalta'],
              ['Total Value', fmt_money(ts.sale_value),                                                                    '#00897B', 'Sum of all sale invoices'],
            ].map(([l,v,c,sub],i)=>`
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 14px;${i%2===0?'background:var(--bg)':''}">
              <div>
                <div style="font-size:11.5px;color:var(--muted)">${l}</div>
                ${sub ? `<div style="font-size:10px;color:var(--muted);opacity:.7;margin-top:1px">${sub}</div>` : ''}
              </div>
              <span style="font-size:13px;font-weight:700;${c?'color:'+c:''}">${v}</span>
            </div>`).join('')}
          </div>
        </div>

        ${hasCharges ? `
        <div style="margin-bottom:14px">
          <div style="font-size:12px;font-weight:700;margin-bottom:8px;color:var(--muted);display:flex;align-items:center;gap:6px"><i class="fas fa-receipt"></i> PURCHASE BILL BREAKDOWN</div>
          <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px">
            <div class="pne-card" style="padding:11px 13px"><div style="font-size:10px;color:var(--muted);font-weight:700">ITEM VALUE</div><div style="font-size:13px;font-weight:800;margin-top:3px">${fmt_money(ts.pur_item_value)}</div></div>
            <div class="pne-card" style="padding:11px 13px"><div style="font-size:10px;color:var(--muted);font-weight:700">TRANSPORT</div><div style="font-size:13px;font-weight:800;margin-top:3px">${fmt_money(ts.transport_amt||0)}</div></div>
            <div class="pne-card" style="padding:11px 13px"><div style="font-size:10px;color:var(--muted);font-weight:700">LOADING</div><div style="font-size:13px;font-weight:800;margin-top:3px">${fmt_money(ts.loading_amt||0)}</div></div>
            <div class="pne-card" style="padding:11px 13px"><div style="font-size:10px;color:var(--muted);font-weight:700">PACKING</div><div style="font-size:13px;font-weight:800;margin-top:3px">${fmt_money(ts.packing_amt||0)}</div></div>
            <div class="pne-card" style="padding:11px 13px"><div style="font-size:10px;color:var(--muted);font-weight:700">OTHER</div><div style="font-size:13px;font-weight:800;margin-top:3px">${fmt_money(ts.other_amt||0)}</div></div>
          </div>
        </div>` : ''}
`;
    }
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function resetFinanceFilter() {
  document.getElementById('fr-from').value = frMonthStart();
  document.getElementById('fr-to').value = fmt_date(new Date());
  document.getElementById('fr-warehouse').value = '';
  renderFinanceReport();
}

function setFRAllTime() {
  document.getElementById('fr-from').value = '2000-01-01';
  document.getElementById('fr-to').value = fmt_date(new Date());
  renderFinanceReport();
}



function frMonthStart() { return BIZ_FROM_DATE; }

function renderFRCharts(r) {
  if (!window.Chart) return;

  // Trend
  const trendCtx = document.getElementById('fr-trend-chart');
  if (frTrendChart) frTrendChart.destroy();
  frTrendChart = new Chart(trendCtx, {
    type: 'line',
    data: {
      labels: r.trend.map(t => fmt_date_disp(t.date).slice(0,5)),
      datasets: [
        { label: 'Income', data: r.trend.map(t => t.income), borderColor: '#00897B', backgroundColor: 'rgba(0,137,123,.1)', fill: true, tension: .35 },
        { label: 'Purchase', data: r.trend.map(t => t.expense), borderColor: '#7B1FA2', backgroundColor: 'rgba(123,31,162,.08)', fill: true, tension: .35 },
        { label: 'Expenses', data: r.trend.map(t => t.biz_expense||0), borderColor: '#E53935', backgroundColor: 'rgba(229,57,53,.08)', fill: false, tension: .35, borderDash: [4,3] },
      ],
    },
    options: { plugins: { legend: { position: 'top', labels: { boxWidth: 10 } } }, scales: { y: { ticks: { callback: v => (v/1000)+'L' } } } },
  });

  // Income distribution donut
  const incCtx = document.getElementById('fr-income-chart');
  if (frIncomeChart) frIncomeChart.destroy();
  frIncomeChart = new Chart(incCtx, {
    type: 'doughnut',
    data: { labels: r.income_heads.map(h=>h.head), datasets: [{ data: r.income_heads.map(h=>h.amount), backgroundColor: ['#00897B','#1976D2','#6A4C93','#E65100','#2E7D32'] }] },
    options: { plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } } }, cutout: '65%' },
  });

  // Expense distribution donut
  const expCtx = document.getElementById('fr-expense-chart');
  if (frExpenseChart) frExpenseChart.destroy();
  frExpenseChart = new Chart(expCtx, {
    type: 'doughnut',
    data: { labels: r.expense_heads.map(h=>h.head), datasets: [{ data: r.expense_heads.map(h=>h.amount), backgroundColor: ['#2E7D32','#1976D2','#6A4C93','#C62828','#E65100'] }] },
    options: { plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } } }, cutout: '65%' },
  });
}

