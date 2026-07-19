// ================================================================
//  assets/js/payments-product.js
//  Requires: common.js, shared-data.js, payment-receipt-shared.js
//  (loaded before this file).
//  For pages/payments/payments-product.php — shown only when
//  business_type='product' exactly.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['payments', 'purchases', 'sales', 'settings']);
  await renderPayments();
});

// ══════════════════════════════════════════
// PAYMENTS
// ══════════════════════════════════════════
const PMT = { page:1, per:10, list:[] };
function buildMergedPaymentsList() {
  const invoicePmts = STATE.payments.map(p => ({
    ...p, source: 'invoice', party_type: 'Customer', payment_for: 'Invoice ' + (p.inv||''), direction: 'in',
  }));
  const purchasePmts = (STATE.purchases||[]).filter(p => (parseFloat(p.amount_paid)||0) > 0).map(p => ({
    id: 'pur-' + p.id, date: p.purchase_date, inv: p.purchase_no, client: p.supplier_name || '—',
    method: p.payment_mode || '—', txn: p.transaction_no || '',  amount: p.amount_paid,
    // Purchases keep their payment state in `status` (there is no payment_status
    // column on purchases) — 'Received' is a legacy value meaning nothing paid yet.
    status: (p.status === 'Received' ? 'Pending' : p.status) || 'Pending',
    source: 'purchase', party_type: 'Supplier', payment_for: 'Purchase Bill ' + (p.purchase_no||''), direction: 'out',
  }));
  const salePmts = (STATE.sales||[]).filter(s => (parseFloat(s.amount_received)||0) > 0).map(s => ({
    id: 'sale-' + s.id, date: s.sale_date, inv: s.invoice_no, client: s.customer_name || '—',
    method: s.payment_method || '—', txn: s.transaction_no || '', amount: s.amount_received, status: s.payment_status || 'Pending',
    source: 'sale', party_type: 'Customer', payment_for: 'Invoice ' + (s.invoice_no||''), direction: 'in',
  }));
  const voucherPmts = (STATE.paymentVouchers||[]).map(v => ({
    id: 'pv-' + v.id, date: v.payment_date, inv: v.reference_no, client: v.party_name,
    method: v.payment_mode || '—', txn: '', amount: v.amount, status: v.status || 'Paid',
    source: 'voucher', party_type: v.party_type || 'Vendor', payment_for: v.payment_for || '', direction: v.direction || 'out',
  }));
  return [...invoicePmts, ...purchasePmts, ...salePmts, ...voucherPmts].sort((a,b) => new Date(b.date) - new Date(a.date));
}

async function renderPayments() {
  try {
    const r = await api('api/payment_vouchers.php');
    STATE.paymentVouchers = Array.isArray(r.data) ? r.data : [];
  } catch(e) { STATE.paymentVouchers = STATE.paymentVouchers || []; }
  PMT.list = applyPmtFilters(buildMergedPaymentsList());
  PMT.page = 1;
  _renderPmtPage(); _renderPmtSummary();
}

function applyPmtFilters(all) {
  const q = (document.getElementById('pmtSearch')?.value || '').toLowerCase();
  const typeF = document.getElementById('pmtTypeFilter')?.value || '';
  const methodF = document.getElementById('pmtMethodFilter')?.value || '';
  const partyTypeF = document.getElementById('pmtPartyTypeFilter')?.value || '';
  const statusF = document.getElementById('pmtStatusFilter')?.value || '';
  return all.filter(p => {
    if (q && !((p.inv&&p.inv.toLowerCase().includes(q)) || (p.client&&p.client.toLowerCase().includes(q)) || (p.method&&p.method.toLowerCase().includes(q)))) return false;
    if (typeF && p.direction !== typeF) return false;
    if (methodF && !(p.method===methodF || (p.method&&p.method.startsWith('Split:')&&p.method.includes(methodF)))) return false;
    if (partyTypeF && p.party_type !== partyTypeF) return false;
    if (statusF && p.status !== statusF) return false;
    return true;
  });
}
function resetPmtFilters() {
  document.getElementById('pmtSearch').value = '';
  document.getElementById('pmtTypeFilter').value = '';
  document.getElementById('pmtMethodFilter').value = '';
  document.getElementById('pmtPartyTypeFilter').value = '';
  document.getElementById('pmtStatusFilter').value = '';
  renderPayments();
}
function filterPayments(){ PMT.list = applyPmtFilters(buildMergedPaymentsList()); PMT.page=1; _renderPmtPage(); }
function filterPaymentsByMethod(){ filterPayments(); }
function setPmtRange(r){
  const t=new Date(); let f=new Date(),to=new Date();
  if(r==='today'){f=new Date(t);to=new Date(t);}
  else if(r==='week'){f=new Date(t);f.setDate(t.getDate()-t.getDay());to=new Date(f);to.setDate(f.getDate()+6);}
  else if(r==='month'){f=new Date(t.getFullYear(),t.getMonth(),1);to=new Date(t.getFullYear(),t.getMonth()+1,0);}
  const fs=fmt_date(f),ts=fmt_date(to);
  const pf=document.getElementById('pmtFrom'),pt=document.getElementById('pmtTo');
  if(pf)pf.value=fs; if(pt)pt.value=ts;
  ['pmtToday','pmtWeek','pmtMonth'].forEach(id=>{const b=document.getElementById(id);if(b)b.classList.remove('active');});
  const bn=document.getElementById('pmt'+r.charAt(0).toUpperCase()+r.slice(1)); if(bn)bn.classList.add('active');
  filterPmtByDate();
}
function filterPmtByDate(){
  const f=document.getElementById('pmtFrom')?.value||'', t=document.getElementById('pmtTo')?.value||'';
  PMT.list=buildMergedPaymentsList().filter(p=>(!f||p.date>=f)&&(!t||p.date<=t));
  PMT.page=1; _renderPmtPage();
}
function exportPmtCSV(){
  const h=['Payment Date','Reference No.','Party Name','Party Type','Payment For','Payment Mode','Amount','Status'];
  const list = PMT.list && PMT.list.length ? PMT.list : buildMergedPaymentsList();
  const r=list.map(p=>[p.date,p.inv,p.client,p.party_type,p.payment_for,p.method,p.amount,p.status].map(v=>`"${String(v??'').replace(/"/g,'""')}"`).join(','));
  downloadFile('payments.csv',[h.join(','),...r].join('\n'),'text/csv');
  toast('✅ Exported!','success');
}

function openMakePaymentModal() {
  document.getElementById('mp-date').value = fmt_date(new Date());
  document.getElementById('mp-direction').value = 'out';
  document.getElementById('mp-partytype').value = 'Vendor';
  document.getElementById('mp-partyname').value = '';
  document.getElementById('mp-paymentfor').value = '';
  document.getElementById('mp-mode').value = 'Cash';
  document.getElementById('mp-amount').value = '';
  document.getElementById('mp-status').value = 'Paid';
  document.getElementById('mp-refno').value = '';
  document.getElementById('mp-notes').value = '';
  openModal('modal-makepayment');
}

async function saveMakePayment() {
  const partyName = document.getElementById('mp-partyname').value.trim();
  const paymentFor = document.getElementById('mp-paymentfor').value.trim();
  const amount = parseFloat(document.getElementById('mp-amount').value) || 0;
  if (!partyName) { toast('⚠️ Party name is required', 'warning'); return; }
  if (!paymentFor) { toast('⚠️ Payment For is required', 'warning'); return; }
  if (amount <= 0) { toast('⚠️ Enter an amount greater than 0', 'warning'); return; }
  if (!document.getElementById('mp-date').value) { toast('⚠️ Payment date is required', 'warning'); return; }

  const payload = {
    payment_date: document.getElementById('mp-date').value,
    direction: document.getElementById('mp-direction').value,
    party_type: document.getElementById('mp-partytype').value,
    party_name: partyName,
    payment_for: paymentFor,
    payment_mode: document.getElementById('mp-mode').value,
    amount,
    status: document.getElementById('mp-status').value,
    reference_no: document.getElementById('mp-refno').value.trim(),
    notes: document.getElementById('mp-notes').value.trim(),
  };

  const btn = document.getElementById('mp-save-btn');
  if (btn) { if (btn.disabled) return; btn.disabled = true; }
  try {
    const res = await api('api/payment_vouchers.php', 'POST', payload);
    toast('✅ Payment recorded as ' + res.reference_no + '!', 'success');
    closeModal('modal-makepayment');
    renderPayments();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
  finally { if (btn) btn.disabled = false; }
}

async function deletePaymentVoucher(id) {
  const conf = await Swal.fire({
    title: 'Delete this payment?', text: 'This payment record will be permanently removed.',
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#E53935',
    customClass: { popup: 'swal-compact' }
  });
  if (!conf.isConfirmed) return;
  try {
    await api('api/payment_vouchers.php?id=' + id, 'DELETE');
    toast('🗑️ Payment deleted', 'info');
    renderPayments();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}
function _renderPmtSummary(){
  const all = buildMergedPaymentsList();
  const now = new Date();
  const thisMonthStart = fmt_date(new Date(now.getFullYear(), now.getMonth(), 1));
  const lastMonthStart = fmt_date(new Date(now.getFullYear(), now.getMonth()-1, 1));
  const lastMonthEnd   = fmt_date(new Date(now.getFullYear(), now.getMonth(), 0));

  const thisMonth = all.filter(p => p.date >= thisMonthStart);
  const lastMonth = all.filter(p => p.date >= lastMonthStart && p.date <= lastMonthEnd);

  const sum = (list, pred) => list.filter(pred).reduce((s,p) => s+(parseFloat(p.amount)||0), 0);

  const isIn  = p => p.direction === 'in';
  const isOut = p => p.direction === 'out';
  const isPending = p => p.status === 'Pending' || p.status === 'Partial';

  // Outstanding = total invoiced to customers − what's been received
  const totalInvoiced = (STATE.sales||[]).reduce((s,x) => s+(parseFloat(x.total)||0), 0);
  const totalCollected = (STATE.sales||[]).reduce((s,x) => s+(parseFloat(x.amount_received)||0), 0);
  const outstanding = Math.max(0, totalInvoiced - totalCollected);

  // Payable = total purchased − what's been paid
  const totalPurchased = (STATE.purchases||[]).reduce((s,x) => s+(parseFloat(x.total)||0), 0);
  const totalPaidOut   = (STATE.purchases||[]).reduce((s,x) => s+(parseFloat(x.amount_paid)||0), 0);
  const payable = Math.max(0, totalPurchased - totalPaidOut);

  const pctChange = (cur, prev) => prev===0 ? (cur>0?100:0) : Math.round(((cur-prev)/prev)*1000)/10;
  const setCard = (id, chgId, val, curM, prevM) => {
    const el = document.getElementById(id); if (el) el.textContent = fmt_money(val);
    const chgEl = document.getElementById(chgId);
    if (chgEl) {
      const pct = pctChange(curM, prevM);
      chgEl.innerHTML = `<i class="fas fa-arrow-${pct>=0?'up':'down'}"></i> ${Math.abs(pct)}% vs last month`;
      chgEl.style.color = pct >= 0 ? '#2E7D32' : '#E53935';
    }
  };

  setCard('pmt-stat-collected',  'pmt-chg-collected',  sum(all,isIn),  sum(thisMonth,isIn),  sum(lastMonth,isIn));
  setCard('pmt-stat-paidout',    'pmt-chg-paidout',    sum(all,isOut), sum(thisMonth,isOut), sum(lastMonth,isOut));
  setCard('pmt-stat-outstanding','pmt-chg-outstanding', outstanding, 0, 0);
  setCard('pmt-stat-payable',    'pmt-chg-payable',    payable, 0, 0);

  const cashPred   = p => (p.method||'').toLowerCase().includes('cash');
  const digitalPred = p => /upi|bank|neft|rtgs|imps/i.test(p.method||'');
  setCard('pmt-stat-cash',    'pmt-chg-cash',    sum(all,cashPred),    sum(thisMonth,cashPred),    sum(lastMonth,cashPred));
  setCard('pmt-stat-digital', 'pmt-chg-digital', sum(all,digitalPred), sum(thisMonth,digitalPred), sum(lastMonth,digitalPred));

  _renderPmtCharts(all);
}

function _renderPmtCharts(all) {
  // ── Chart 1: Daily trend (last 30 days) — Collections vs Paid Out ──
  const days = 30;
  const dayMap = {};
  for (let i = days-1; i >= 0; i--) {
    const d = new Date(); d.setDate(d.getDate()-i);
    dayMap[fmt_date(d)] = { in: 0, out: 0 };
  }
  all.forEach(p => { if (dayMap[p.date]) { const amt = parseFloat(p.amount)||0; if (p.direction==='in') dayMap[p.date].in += amt; else dayMap[p.date].out += amt; } });
  const labels  = Object.keys(dayMap).map(d => { const dt=new Date(d); return (dt.getMonth()+1)+'/'+dt.getDate(); });
  const inVals  = Object.values(dayMap).map(v => v.in);
  const outVals = Object.values(dayMap).map(v => v.out);

  const trendCtx = document.getElementById('pmt-trend-chart');
  if (trendCtx) {
    if (window._pmtTrendChart) window._pmtTrendChart.destroy();
    window._pmtTrendChart = new Chart(trendCtx, {
      type: 'bar',
      data: { labels, datasets: [
        { label: 'Collected', data: inVals, backgroundColor: '#00897B55', borderColor: '#00897B', borderWidth: 1.5 },
        { label: 'Paid Out', data: outVals, backgroundColor: '#E5393555', borderColor: '#E53935', borderWidth: 1.5 },
      ]},
      options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'top', labels:{ font:{size:10}, boxWidth:10 } } },
        scales:{ x:{ grid:{display:false}, ticks:{font:{size:9}, maxTicksLimit:10} }, y:{ grid:{color:'#f0f0f0'}, ticks:{font:{size:9}, callback:v=>'₹'+v.toLocaleString('en-IN')} } } }
    });
  }

  // ── Chart 2: Payment mode donut ──
  const modeMap = {};
  all.forEach(p => {
    let m = p.method || 'Unknown';
    if (m.startsWith('Split:')) m = 'Split';
    modeMap[m] = (modeMap[m]||0) + (parseFloat(p.amount)||0);
  });
  const modeEntries = Object.entries(modeMap).filter(([,v])=>v>0).sort((a,b)=>b[1]-a[1]).slice(0,6);
  const modeColors = ['#00897B','#1976D2','#7B1FA2','#E65100','#E53935','#888'];
  const modeCtx = document.getElementById('pmt-mode-chart');
  if (modeCtx && modeEntries.length) {
    if (window._pmtModeChart) window._pmtModeChart.destroy();
    window._pmtModeChart = new Chart(modeCtx, {
      type: 'doughnut',
      data: { labels: modeEntries.map(([m])=>m), datasets:[{ data: modeEntries.map(([,v])=>v), backgroundColor: modeColors, borderWidth:2, borderColor:'#fff' }] },
      options: { responsive:true, maintainAspectRatio:false, cutout:'60%', plugins:{ legend:{display:false}, tooltip:{callbacks:{label:ctx=>` ${ctx.label}: ${fmt_money(ctx.raw)}`}} } }
    });
    const total = modeEntries.reduce((s,[,v])=>s+v,0);
    const lgEl = document.getElementById('pmt-mode-legend');
    if (lgEl) lgEl.innerHTML = modeEntries.map(([m,v],i)=>
      `<div style="display:flex;align-items:center;gap:5px;margin-bottom:3px"><span style="width:8px;height:8px;border-radius:2px;background:${modeColors[i]};flex-shrink:0"></span><span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escHtml(m)}</span><span style="font-weight:600">${total>0?((v/total)*100).toFixed(1)+'%':''}</span></div>`
    ).join('');
  }

  // ── Chart 3: Status breakdown bars ──
  const sdEl = document.getElementById('pmt-status-breakdown');
  if (sdEl) {
    const inTotal  = all.filter(p=>p.direction==='in').reduce((s,p)=>s+(parseFloat(p.amount)||0),0);
    const outTotal = all.filter(p=>p.direction==='out').reduce((s,p)=>s+(parseFloat(p.amount)||0),0);
    const pendingIn  = all.filter(p=>p.direction==='in'  && (p.status==='Pending'||p.status==='Partial')).reduce((s,p)=>s+(parseFloat(p.amount)||0),0);
    const pendingOut = all.filter(p=>p.direction==='out' && (p.status==='Pending'||p.status==='Partial')).reduce((s,p)=>s+(parseFloat(p.amount)||0),0);
    const paidIn  = inTotal - pendingIn;
    const paidOut = outTotal - pendingOut;
    const bar = (label, paid, pending, color) => {
      const total = paid + pending;
      const paidPct = total>0 ? (paid/total*100).toFixed(0) : 0;
      const pendPct = total>0 ? (pending/total*100).toFixed(0) : 0;
      return `<div>
        <div style="display:flex;justify-content:space-between;font-size:11.5px;font-weight:600;margin-bottom:4px">
          <span>${escHtml(label)}</span><span style="color:var(--muted);font-weight:400">${fmt_money(total)}</span>
        </div>
        <div style="height:8px;border-radius:4px;background:var(--border);overflow:hidden;display:flex">
          <div style="width:${paidPct}%;background:${color};border-radius:4px 0 0 4px;transition:.4s"></div>
          <div style="width:${pendPct}%;background:${color}55;border-radius:0 4px 4px 0;transition:.4s"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--muted);margin-top:3px">
          <span><span style="color:${color}">■</span> Received/Paid ${fmt_money(paid)}</span>
          <span><span style="color:${color}55">■</span> Pending ${fmt_money(pending)}</span>
        </div>
      </div>`;
    };
    sdEl.innerHTML = bar('Collections (IN)', paidIn, pendingIn, '#00897B') + bar('Payments (OUT)', paidOut, pendingOut, '#E53935');
  }
}
function renderPaymentMethodCell(method, iconClass) {
  if (method && method.startsWith('Split:')) {
    const body = method.replace(/^Split:\s*/, '');
    const parts = body.split('+').map(s => s.trim()).filter(Boolean).map(s => {
      const m = s.match(/^(.+?):\s*₹\s*([\d,]+(?:\.\d+)?)$/);
      return m ? { method: m[1].trim(), amount: parseFloat(m[2].replace(/,/g, '')) } : null;
    }).filter(Boolean);
    if (parts.length) {
      const colors = { 'Cash':'#2E7D32','Bank Transfer':'#1565C0','UPI':'#6A4C93','Cheque':'#E65100' };
      return `<div style="display:flex;flex-wrap:wrap;gap:4px">` + parts.map(p => {
        const c = colors[p.method] || '#455A64';
        return `<span style="font-size:10px;font-weight:700;color:${c};background:${c}18;padding:2px 7px;border-radius:9px">${escHtml(p.method)}: ${fmt_money(p.amount)}</span>`;
      }).join('') + `</div>`;
    }
  }
  return `<span style="display:flex;align-items:center;gap:5px"><i class="fas ${iconClass}" style="color:var(--muted2);font-size:11px"></i>${escHtml(method||'—')}</span>`;
}

function _renderPmtPage(){
  const tbody=document.getElementById('paymentsTbody'); if(!tbody) return;
  const s=(PMT.page-1)*PMT.per, e=s+PMT.per, pg=PMT.list.slice(s,e);

  const partyTypeColors = { Customer:['#2E7D32','#E8F5E9'], Supplier:['#E65100','#FFF3E0'], Transporter:['#1976D2','#E3F2FD'], Vendor:['#6A4C93','#F3E8FF'], Other:['#455A64','#ECEFF1'] };
  const modeColors = { Cash:['#2E7D32','#E8F5E9'], UPI:['#6A4C93','#F3E8FF'], NEFT:['#1976D2','#E3F2FD'], RTGS:['#E65100','#FFF3E0'], Cheque:['#455A64','#ECEFF1'] };

  tbody.innerHTML=pg.map((p,i)=>{
    const df=p.date?new Date(p.date).toLocaleDateString(_moneyLocale(),{day:'2-digit',month:'short',year:'numeric'}):p.date;
    const isDeleted = p._invoiceDeleted || p.invoice_deleted;
    const [ptColor, ptBg] = partyTypeColors[p.party_type] || partyTypeColors.Other;
    const modeShort = (p.method||'').startsWith('Split:') ? 'Split' : (p.method||'—').split(' (')[0];
    const [mColor, mBg] = modeColors[modeShort] || ['#455A64','#ECEFF1'];
    const statusColor = p.status === 'Paid' ? ['#00897B','#E8F5E9'] : ['#E65100','#FFF3E0'];
    return `<tr style="${isDeleted ? 'background:#FFF5F5;opacity:.85;' : ''}">
      <td>${s+i+1}</td>
      <td style="font-size:12px">${df}</td>
      <td><code style="font-size:11px;color:var(--muted)">${escHtml(p.inv||'—')}</code></td>
      <td style="text-align:left"><strong>${escHtml(p.client||'—')}</strong></td>
      <td><span style="font-size:10px;font-weight:700;color:${ptColor};background:${ptBg};padding:2px 7px;border-radius:9px">${escHtml(p.party_type||'—')}</span></td>
      <td style="font-size:11.5px">${escHtml(p.payment_for||'—')}</td>
      <td><span style="font-size:10px;font-weight:700;color:${mColor};background:${mBg};padding:2px 7px;border-radius:9px">${escHtml(modeShort)}</span></td>
      <td><strong style="color:${isDeleted?'var(--muted)':(p.direction==='in'?'#2E7D32':'#223')}${isDeleted?';text-decoration:line-through':''}">${fmt_money(p.amount)}</strong></td>
      <td><span style="font-size:10px;font-weight:700;color:${isDeleted?'#B71C1C':statusColor[0]};background:${isDeleted?'#FFCDD2':statusColor[1]};padding:2px 7px;border-radius:9px">${isDeleted ? 'Deleted' : escHtml(p.status)}</span></td>
      <td style="display:flex;gap:5px;align-items:center">
        <button class="act-btn" title="View Receipt" onclick="viewReceipt(${s+i})"><i class="fas fa-eye"></i></button>
        ${isDeleted ? `<button class="act-btn" title="Revert deleted flag" onclick="revertPaymentDelete(${s+i})" style="color:var(--teal);border-color:var(--teal-l)"><i class="fas fa-undo"></i></button>` : (p.source==='voucher' ? `<button class="act-btn" title="Delete" onclick="deletePaymentVoucher(${String(p.id).replace('pv-','')})"><i class="fas fa-trash"></i></button>` : '')}
      </td>
    </tr>`;
  }).join('')||'<tr><td colspan="10" style="text-align:center;padding:30px;color:var(--muted)">No payments recorded</td></tr>';
  const tot=Math.ceil(PMT.list.length/PMT.per);
  const pg2=document.getElementById('pmtPagination');
  if(pg2){let h=`<button class="pg-btn" onclick="pmtPage(${PMT.page-1})" ${PMT.page<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;for(let i=1;i<=tot;i++)h+=`<button class="pg-btn ${i===PMT.page?'active':''}" onclick="pmtPage(${i})">${i}</button>`;h+=`<button class="pg-btn" onclick="pmtPage(${PMT.page+1})" ${PMT.page>=tot?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;pg2.innerHTML=h;}
  const inf=document.getElementById('pmtInfo'); if(inf)inf.textContent=`Showing ${PMT.list.length?s+1:0}–${Math.min(e,PMT.list.length)} of ${PMT.list.length} entries`;
}
function pmtPage(p){const t=Math.ceil(PMT.list.length/PMT.per);if(p<1||p>t)return;PMT.page=p;_renderPmtPage();}

