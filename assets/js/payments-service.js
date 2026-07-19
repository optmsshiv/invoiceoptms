// ================================================================
//  assets/js/payments-service.js
//  Requires: common.js, shared-data.js, payment-receipt-shared.js
//  (loaded before this file).
//  For pages/payments/payments-service.php — shown for 'service'
//  and 'both' business types (everything except exactly 'product').
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['payments', 'settings']);
  renderPaymentsService();
});

const PMTS = { list: [], page: 1, per: 15 };

function renderPaymentsService() {
  PMTS.list = [...STATE.payments];
  PMTS.page = 1;
  _renderPmtsSvcPage(); _renderPmtsSvcSummary();
}
function filterPaymentsSvc(v){ const s=v.toLowerCase(); PMTS.list=STATE.payments.filter(p=>(!s||(p.inv&&p.inv.toLowerCase().includes(s))||(p.client&&p.client.toLowerCase().includes(s))||(p.txn&&p.txn.toLowerCase().includes(s)))); PMTS.page=1; _renderPmtsSvcPage(); }
function filterPaymentsSvcByMethod(v){ PMTS.list=v?STATE.payments.filter(p=>p.method===v):[...STATE.payments]; PMTS.page=1; _renderPmtsSvcPage(); }
function setPmtsSvcRange(r){
  const t=new Date(); let f=new Date(t), to=new Date(t);
  if(r==='today'){ /* f=to=today */ }
  else if(r==='week'){f=new Date(t);f.setDate(t.getDate()-t.getDay());to=new Date(f);to.setDate(f.getDate()+6);}
  else if(r==='month'){f=new Date(t.getFullYear(),t.getMonth(),1);to=new Date(t.getFullYear(),t.getMonth()+1,0);}
  const fs=fmt_date(f),ts=fmt_date(to);
  const pf=document.getElementById('pmtsFrom'),pt=document.getElementById('pmtsTo');
  if(pf)pf.value=fs; if(pt)pt.value=ts;
  ['pmtsToday','pmtsWeek','pmtsMonth'].forEach(id=>{const b=document.getElementById(id);if(b)b.classList.remove('active');});
  const bn=document.getElementById('pmts'+r.charAt(0).toUpperCase()+r.slice(1)); if(bn)bn.classList.add('active');
  filterPmtsSvcByDate();
}
function filterPmtsSvcByDate(){
  const f=document.getElementById('pmtsFrom')?.value||'', t=document.getElementById('pmtsTo')?.value||'';
  PMTS.list=STATE.payments.filter(p=>(!f||p.date>=f)&&(!t||p.date<=t));
  PMTS.page=1; _renderPmtsSvcPage();
}
function exportPmtsSvcCSV(){
  const h=['Date','Invoice','Client','Method','Txn ID','Amount','Status'];
  const r=STATE.payments.map(p=>[p.date,p.inv,p.client,p.method,p.txn||'',p.amount,p.status].map(v=>`"${v}"`).join(','));
  downloadFile('payments.csv',[h.join(','),...r].join('\n'),'text/csv');
  toast('✅ Exported!','success');
}
function _renderPmtsSvcSummary(){
  const el=document.getElementById('pmtsSummary'); if(!el) return;
  const tot=STATE.payments.reduce((s,p)=>s+p.amount,0);
  const upi=STATE.payments.filter(p=>p.method&&p.method.toLowerCase().includes('upi')).reduce((s,p)=>s+p.amount,0);
  const neft=STATE.payments.filter(p=>p.method&&(p.method.toLowerCase().includes('neft')||p.method.toLowerCase().includes('bank'))).reduce((s,p)=>s+p.amount,0);
  const tod=fmt_date(new Date()); const todAmt=STATE.payments.filter(p=>p.date===tod).reduce((s,p)=>s+p.amount,0);
  el.innerHTML=`
    <div class="stat-card"><div class="stat-icon" style="background:#e0f2f1;color:#00897B"><i class="fas fa-rupee-sign"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(tot)}</div><div class="stat-lbl">Total Collected</div><div class="stat-trend neutral">${STATE.payments.length} txns</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#e3f2fd;color:#1976D2"><i class="fas fa-mobile-alt"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(upi)}</div><div class="stat-lbl">Via UPI</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#fff8e1;color:#F9A825"><i class="fas fa-university"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(neft)}</div><div class="stat-lbl">Via Bank</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#e8f5e9;color:#388E3C"><i class="fas fa-calendar-day"></i></div><div class="stat-body"><div class="stat-val">${fmt_money(todAmt)}</div><div class="stat-lbl">Today</div></div></div>`;
}
function _renderPmtsSvcPage(){
  const tbody=document.getElementById('paymentsSvcTbody'); if(!tbody) return;
  const s=(PMTS.page-1)*PMTS.per, e=s+PMTS.per, pg=PMTS.list.slice(s,e);

  const invColors=['#455A64','#00695C','#1565C0','#6A1B9A','#4E342E','#37474F','#2E7D32','#283593','#B71C1C','#E65100'];
  const invNums=[...new Set(pg.map(p=>p.inv))];
  const invColorMap={};
  invNums.forEach((num,i)=>{ invColorMap[num]=invColors[i%invColors.length]; });
  const invCount={};
  pg.forEach(p=>{ invCount[p.inv]=(invCount[p.inv]||0)+1; });

  tbody.innerHTML=pg.map((p,i)=>{
    const df=p.date?new Date(p.date).toLocaleDateString(_moneyLocale(),{day:'2-digit',month:'short',year:'numeric'}):p.date;
    const mi=p.method&&p.method.toLowerCase().includes('upi')?'fa-mobile-alt':p.method&&p.method.toLowerCase().includes('cheque')?'fa-money-check':p.method&&p.method.toLowerCase().includes('cash')?'fa-money-bill-wave':'fa-university';
    const chipColor=invColorMap[p.inv]||'#455A64';
    const isMulti=invCount[p.inv]>1;
    const layerIcon=isMulti?`<i class="fas fa-layer-group" style="font-size:9px;opacity:.75;margin-right:3px"></i>`:'';
    const invChip=`<span style="display:inline-flex;align-items:center;padding:3px 9px;border-radius:10px;background:${chipColor};color:#fff;font-family:var(--mono);font-weight:700;font-size:12px;letter-spacing:.3px;box-shadow:0 1px 4px ${chipColor}55">${layerIcon}${p.inv}</span>`;
    const isDeleted = p._invoiceDeleted || p.invoice_deleted;
    const methodCell = renderPaymentMethodCell(p.method, mi);
    return `<tr style="${isDeleted ? 'background:#FFF5F5;opacity:.85;' : isMulti ? 'border-left:3px solid '+chipColor+';background:'+chipColor+'08' : ''}">
      <td style="font-size:12px">${df}</td>
      <td>${invChip}</td>
      <td><strong>${p.client}</strong></td>
      <td>${methodCell}</td>
      <td><code style="font-family:var(--mono);font-size:11px;color:var(--muted)">${p.txn||'—'}</code></td>
      <td><strong style="font-family:var(--mono);color:${isDeleted?'var(--muted)':'var(--green)'}${isDeleted?';text-decoration:line-through':''}">${fmt_money(p.amount)}</strong></td>
      <td><span class="badge ${isDeleted ? 'badge-cancelled' : 'badge-paid'}" style="${isDeleted ? 'background:#FFCDD2;color:#B71C1C' : ''}">${isDeleted ? '🗑️ Invoice Deleted' : p.status}</span></td>
      <td style="display:flex;gap:6px;align-items:center">
        <button class="act-btn" title="View Receipt" onclick="viewReceiptSvc(${s+i})"><i class="fas fa-receipt"></i></button>
        ${isDeleted ? `<button class="act-btn" title="Revert deleted flag" onclick="revertPaymentDelete(${s+i}, PMTS.list)" style="color:var(--teal);border-color:var(--teal-l)" ><i class="fas fa-undo"></i></button>` : ''}
      </td>
    </tr>`;
  }).join('')||'<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--muted)">No payments recorded</td></tr>';
  const tot=Math.ceil(PMTS.list.length/PMTS.per);
  const pg2=document.getElementById('pmtsPagination');
  if(pg2){let h=`<button class="pg-btn" onclick="pmtsSvcPage(${PMTS.page-1})" ${PMTS.page<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;for(let i=1;i<=tot;i++)h+=`<button class="pg-btn ${i===PMTS.page?'active':''}" onclick="pmtsSvcPage(${i})">${i}</button>`;h+=`<button class="pg-btn" onclick="pmtsSvcPage(${PMTS.page+1})" ${PMTS.page>=tot?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;pg2.innerHTML=h;}
  const inf=document.getElementById('pmtsInfo'); if(inf)inf.textContent=`${s+1}–${Math.min(e,PMTS.list.length)} of ${PMTS.list.length}`;
}
function pmtsSvcPage(p){const t=Math.ceil(PMTS.list.length/PMTS.per);if(p<1||p>t)return;PMTS.page=p;_renderPmtsSvcPage();}
