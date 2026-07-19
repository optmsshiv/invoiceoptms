// ============================================================
// invoice-render-shared.js — shared PDF/print rendering engine
// Used by create.php (print/preview while editing) and invoices.php
// (preview button, download PDF, mark-paid modal).
// This is the "invoice-render-shared.js" module referenced in your
// original folder-structure notes — reconstructed here from the
// current SPA's buildTpl2/buildTplF template engine.
// ============================================================
function buildTpl2(d, sc, itemsHTML, gstColHeader, rowNumHeader='') {
  sc = resolveCompany(sc);
  const tid = (window.TPL_CUSTOM && TPL_CUSTOM.colorTheme) ? parseInt(TPL_CUSTOM.colorTheme)||1 : 1;
  const T = _MATTE_THEMES[tid] || _MATTE_THEMES[1];

  // Status pill colors
  const pillMap = { Paid: T.pillpaid, Pending: T.pillpending, Overdue: T.pilloverdue, Draft: T.pilldraft, Partial: T.pillpending, Cancelled: '#fff|#991B1B', Estimate: '#fff|#3949AB' };
  const [ptxt, pbg] = (pillMap[d.status]||T.pilldraft).split('|');

  // Color band stripes at top — changes per invoice status
  const statusBands = {
    Paid:      '#166534,#16A34A,#4ADE80',
    Overdue:   '#991B1B,#DC2626,#F87171',
    Cancelled: '#374151,#6B7280,#D1D5DB',
    Draft:     '#1E3A5F,#2563EB,#93C5FD',
    Partial:   '#D97706,#F59E0B,#FDE68A',
    Pending:   T.band
  };
  const activeBand = statusBands[d.status] || T.band;
  const [b1, b2] = activeBand.split(',');
  const accentStrip = b2 || b1; // use mid-tone so Partial amber is warm not muddy-brown

  const thStyle = `padding:10px 10px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:${T.thtext};text-align:left`;
  const thr = `${thStyle};text-align:right`;
  const initials = (sc.company || '?').replace(/[^A-Za-z]/g,'').substring(0,2).toUpperCase() || '?';

  return `<div style="font-family:'Public Sans',sans-serif;background:#fff;width:794px;min-height:1123px;position:relative;overflow:hidden;border:1.5px solid ${T.metabr};border-radius:0">
  ${tplWatermark(d)}

  <!-- ACCENT STRIP -->
  <div style="height:5px;background:${accentStrip}"></div>

  <!-- HEADER: dark logo sidebar + white content panel (canonical design — matches PDF download) -->
  <div style="display:flex">
    <div style="background:#2A3580;width:86px;flex-shrink:0;display:flex;align-items:center;justify-content:center;padding:18px 8px">
      ${sc.logo
        ? `<img src="${sc.logo}" style="max-width:54px;max-height:44px;object-fit:contain;display:block" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
           <div style="display:none;width:40px;height:40px;border-radius:9px;background:rgba(255,255,255,.15);color:#fff;font-size:15px;font-weight:800;align-items:center;justify-content:center">${initials}</div>`
        : `<div style="width:40px;height:40px;border-radius:9px;background:rgba(255,255,255,.15);color:#fff;font-size:15px;font-weight:800;display:flex;align-items:center;justify-content:center">${initials}</div>`}
    </div>
    <div style="flex:1;background:#fff;padding:18px 26px;min-width:0">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px">
        <div style="min-width:0">
          <div style="font-size:16px;font-weight:800;color:#1A1A2E">${sc.company}</div>
          ${sc.tagline?`<div style="font-size:9px;color:#9CA3AF;letter-spacing:1.2px;text-transform:uppercase;margin-top:2px;font-weight:600">${sc.tagline}</div>`:''}
          ${sc.address?`<div style="font-size:10px;color:#9CA3AF;margin-top:3px;line-height:1.6">${sc.address.replace(/\n/g,', ')}</div>`:''}
        </div>
        <div style="text-align:right;white-space:nowrap;flex-shrink:0">
          <div style="font-size:18px;font-weight:800;color:#1A1A2E;font-family:monospace">#${d.num}</div>
          <div style="margin-top:6px">
            <span style="display:inline-block;padding:3px 10px;border-radius:10px;font-size:8.5px;font-weight:800;letter-spacing:.6px;background:#F3F4F6;color:#4B5563">${d.status==='Estimate'?'ESTIMATE':'TAX INVOICE'}</span>
            <span style="display:inline-block;padding:3px 10px;border-radius:10px;font-size:8.5px;font-weight:800;letter-spacing:.6px;background:${pbg};color:${ptxt};margin-left:5px">${d.status.toUpperCase()}</span>
          </div>
        </div>
      </div>
      <div style="height:1px;background:#F0F1F3;margin:13px 0 11px"></div>
      <div style="display:flex;gap:28px;flex-wrap:wrap">
        ${sc.phone?`<div><div style="font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:#9CA3AF;margin-bottom:2px">Phone</div><div style="font-size:11px;font-weight:600;color:#1A1A2E">${sc.phone}</div></div>`:''}
        ${sc.email?`<div><div style="font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:#9CA3AF;margin-bottom:2px">Email</div><div style="font-size:11px;font-weight:600;color:#1A1A2E">${sc.email}</div></div>`:''}
        ${sc.website?`<div><div style="font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:#9CA3AF;margin-bottom:2px">Website</div><div style="font-size:11px;font-weight:600;color:#1A1A2E">${sc.website}</div></div>`:''}
        ${sc.gst?`<div><div style="font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:#9CA3AF;margin-bottom:2px">GSTIN</div><div style="font-size:11px;font-weight:600;color:#1A1A2E;font-family:monospace">${sc.gst}</div></div>`:''}
      </div>
    </div>
  </div>

  <!-- META STRIP -->
  <div style="display:flex;background:${T.metabg};border-bottom:1.5px solid ${T.metabr}">
    ${[['Issue Date',d.date],['Due Date',d.due],['Service',d.svc||'—'],['Grand Total',fmt_money(d.grand,d.sym)]].map((pair,i,arr)=>`
    <div style="flex:1;padding:12px 24px;${i<arr.length-1?`border-right:1px solid ${T.metabr}`:''}">
      <div style="font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:${T.metalbl};margin-bottom:4px">${pair[0]}</div>
      <div style="font-size:${pair[0]==='Grand Total'?'15':'13'}px;font-weight:${pair[0]==='Grand Total'?'800':'700'};color:${pair[0]==='Grand Total'?T.metalbl:T.metaval};font-family:monospace">${pair[1]||'—'}</div>
    </div>`).join('')}
  </div>

  <!-- PARTIES -->
  <div style="display:flex;border-bottom:1.5px solid ${T.metabr}">
    <div style="flex:1;padding:18px 24px;background:#F0FDF4;border-right:1.5px solid #86EFAC">
      <div style="font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:${T.billlbl};margin-bottom:8px">Billed To</div>
      ${tplClientLogoHTML(d)}
      <div style="font-size:14px;font-weight:800;color:#111;margin-bottom:2px">${d.cname}</div>
      ${d.cperson?`<div style="font-size:11px;color:#555;line-height:1.8">${d.cperson}</div>`:''}
      ${d.cemail?`<div style="font-size:11px;color:#555;line-height:1.8">${d.cemail}</div>`:''}
      ${d.cwa?`<div style="font-size:11px;color:#555;line-height:1.8">${d.cwa}</div>`:''}
      ${d.caddr?`<div style="font-size:11px;color:#555;line-height:1.7;margin-top:3px">${d.caddr.replace(/\n/g,'<br>')}</div>`:''}
      ${d.cgst?`<div style="font-size:11px;color:#555;margin-top:4px;font-weight:600">GSTIN: ${d.cgst}</div>`:''}
    </div>
    <div style="flex:1;padding:18px 24px;background:${T.issbg}">
      <div style="font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:${T.isslbl};margin-bottom:8px">Issued By</div>
      <div style="font-size:14px;font-weight:800;color:#111;margin-bottom:2px">${sc.company}</div>
      ${sc.email?`<div style="font-size:11px;color:#555;line-height:1.8">${sc.email}</div>`:''}
      ${sc.phone?`<div style="font-size:11px;color:#555;line-height:1.8">${sc.phone}</div>`:''}
      ${sc.address?`<div style="font-size:11px;color:#555;line-height:1.7;margin-top:3px">${sc.address.replace(/\n/g,'<br>')}</div>`:''}
      ${sc.gst?`<div style="font-size:11px;color:#555;margin-top:4px;font-weight:600">GSTIN: ${sc.gst}</div>`:''}
    </div>
  </div>

  <!-- LINE ITEMS -->
  <div style="padding:0 1px;border-bottom:1.5px solid ${T.metabr}">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr style="background:${T.thbg}">
        <th style="${thStyle};width:26px">#</th>
        <th style="${thStyle}">Description</th>
        <th style="${thStyle};text-align:center">Type</th>
        <th style="${thr}">Qty</th>
        <th style="${thr}">Rate</th>
        <th style="${thr}">Amount</th>
        ${gstColHeader?`<th style="${thr}">GST</th>`:''}
        <th style="${thr}">Total</th>
      </tr></thead>
      <tbody>${itemsHTML.replace(/border-bottom:1px solid #eee/g,`border-bottom:1px solid ${T.metabr}`)}</tbody>
    </table>
  </div>

  <!-- BOTTOM: BANK → NOTES → TnC stacked, then TOTALS -->
  <div style="display:flex;border-top:1.5px solid ${T.metabr}">

    <!-- LEFT: stacked vertically — Bank Details, Notes, Terms & Conditions — warm amber bg -->
    <div style="flex:1;padding:18px 24px;border-right:1.5px solid #FDE68A;background:#FFFBEB;display:flex;flex-direction:column;gap:0">

      <!-- BANK DETAILS -->
      ${(()=>{
        const _sc2 = (typeof STATE !== 'undefined' ? STATE.settings : {});
        const bankText = d.bank || _sc2.defaultBank || '';
        const upi = d.upi || _sc2.upi || '';
        if (d.popt && d.popt.bank === false) return '';
        if (d.status === 'Paid') return '';
        if (!bankText && !upi) return '';
        const leftCol = bankText
          ? `<div style="flex:1;min-width:0">
              <div style="font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#92400E;margin-bottom:6px">💳 Bank Details</div>
              <div style="font-size:10.5px;line-height:1.9;color:#78350F">
                ${bankText.split('|').map(s=>s.trim()).filter(Boolean).map(s=>`<div>${s}</div>`).join('')}
              </div>
            </div>`
          : '';
        const qrImg = (d.popt && d.popt.qr && d.qrUrl)
          ? `<div style="margin-top:6px;text-align:center"><img src="${d.qrUrl}" style="width:70px;height:70px;border-radius:6px;border:1px solid #FDE68A;display:block;margin:0 auto" onerror="this.style.display='none'"><div style="font-size:9px;color:#92400E;margin-top:3px">Scan to Pay</div></div>`
          : '';
        const upiBlock = upi
          ? `<div>
              <div style="font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#92400E;margin-bottom:6px">📲 UPI</div>
              <div style="background:#FEF3C7;border-radius:6px;padding:5px 8px;font-size:12px;font-weight:800;letter-spacing:.4px;color:#92400E;text-align:center">${upi}</div>
              ${qrImg}
            </div>`
          : '';
        const divider = bankText && upi ? `<div style="width:1px;background:#FDE68A;margin:0 14px;align-self:stretch"></div>` : '';
        return `<div style="display:flex;align-items:flex-start;gap:0;padding-bottom:14px;border-bottom:1px solid #FDE68A;margin-bottom:14px">
          ${leftCol}${divider}${upiBlock}
        </div>`;
      })()}

      <!-- NOTES -->
      ${(()=>{
        if (!d.popt || !d.popt.notes) return '';
        if (d.status === 'Paid') {
          const _sc3 = (typeof STATE !== 'undefined' ? STATE.settings : {});
          return `<div style="padding-bottom:14px;border-bottom:1px solid #FDE68A;margin-bottom:14px">
            <div style="font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#92400E;margin-bottom:6px">Notes</div>
            <div style="background:linear-gradient(135deg,#E8F5E9,#F1F8E9);border-radius:8px;padding:10px 12px;font-size:11px;color:#2E7D32;line-height:1.8;border-left:3px solid #4CAF50">
              <div style="font-weight:800;font-size:12px;margin-bottom:3px">🎉 Thank You for Your Payment!</div>
              <div>We appreciate your prompt payment and continued trust in <strong>${_sc3.company||''}</strong>. Your account is now clear and up to date.</div>
              <div style="margin-top:5px;opacity:.85">We look forward to serving you again. For any queries, reach us at ${_sc3.phone||_sc3.email||''}.</div>
            </div>
          </div>`;
        }
        if (!d.notes) return '';
        return `<div style="padding-bottom:14px;border-bottom:1px solid #FDE68A;margin-bottom:14px">
          <div style="font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#92400E;margin-bottom:6px">Notes</div>
          <div style="font-size:10.5px;color:#78350F;line-height:1.7">${d.notes.replace(/\n/g,'<br>')}</div>
        </div>`;
      })()}

      <!-- TERMS & CONDITIONS -->
      ${(()=>{
        if (!d.popt || !d.popt.tnc) return '';
        const tnc = (d.tnc || '').trim();
        if (!tnc) return '';
        return `<div>
          <div style="font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#92400E;margin-bottom:6px">Terms &amp; Conditions</div>
          <div style="font-size:10.5px;color:#92400E;line-height:1.7">${tnc.replace(/\n/g,'<br>')}</div>
        </div>`;
      })()}
    </div>

    <!-- RIGHT: Totals (with correct order + partial history) -->
    <div style="width:260px;flex-shrink:0;display:flex;flex-direction:column;background:${T.totbg}">
      <!-- Subtotal -->
      <div style="display:flex;justify-content:space-between;padding:10px 22px;border-bottom:1px solid ${T.totbr};font-size:12px">
        <span style="font-weight:700;text-transform:uppercase;font-size:10px;letter-spacing:.5px;color:${T.totlbl}">Subtotal</span>
        <span style="font-family:monospace;font-weight:700;color:${T.totval}">${fmt_money(d.sub,d.sym)}</span>
      </div>
      <!-- Discount (if any) -->
      ${d.discAmt>0?`
      <div style="display:flex;justify-content:space-between;padding:10px 22px;border-bottom:1px solid ${T.totbr};font-size:12px">
        <span style="font-weight:700;text-transform:uppercase;font-size:10px;letter-spacing:.5px;color:${T.totlbl}">Discount${d.discType==='fixed'?' (₹)':d.disc>0?' ('+Math.round(d.disc*100)/100+'%)':''}</span>
        <span style="font-family:monospace;font-weight:700;color:#DC2626">−${fmt_money(d.discAmt,d.sym)}</span>
      </div>`:''}
      <!-- Amount (after discount, before GST) -->
      <div style="display:flex;justify-content:space-between;padding:10px 22px;border-bottom:1px solid ${T.totbr};font-size:12px">
        <span style="font-weight:700;text-transform:uppercase;font-size:10px;letter-spacing:.5px;color:${T.totlbl}">Amount</span>
        <span style="font-family:monospace;font-weight:700;color:${T.totval}">${fmt_money((d.sub||0)-(d.discAmt||0),d.sym)}</span>
      </div>
      <!-- GST -->
      <div style="display:flex;justify-content:space-between;padding:10px 22px;border-bottom:1px solid ${T.totbr};font-size:12px">
        <span style="font-weight:700;text-transform:uppercase;font-size:10px;letter-spacing:.5px;color:${T.totlbl}">GST</span>
        <span style="font-family:monospace;font-weight:700;color:${T.totval}">${d.gstAmt>0?'+'+fmt_money(d.gstAmt,d.sym):fmt_money(0,d.sym)}</span>
      </div>
      <!-- Grand Total -->
      <div style="background:${T.grandbg};padding:14px 22px;display:flex;justify-content:space-between;align-items:center">
        <span style="color:${T.grandtext};font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px">Grand Total</span>
        <span style="color:${T.grandtext};font-family:monospace;font-size:19px;font-weight:800;letter-spacing:-1px">${fmt_money(d.grand,d.sym)}</span>
      </div>
      <!-- Partial payment history + settlement discount (instalments + remaining due) -->
      ${(()=>{
        const invId2 = d.invId ? String(d.invId) : '';
        const isPartial2   = d.status === 'Partial';
        const isPaid2      = d.status === 'Paid';
        const isCancelled2 = d.status === 'Cancelled';
        if (!(isPartial2 || isPaid2 || isCancelled2) || !invId2 || invId2 === '0') return '';
        const pays2 = (typeof STATE !== 'undefined' ? STATE.payments : []).filter(p => p.invoice_id && String(p.invoice_id) === invId2)
          .sort((a,b) => {
            const da = new Date(a.date||a.payment_date||0);
            const db = new Date(b.date||b.payment_date||0);
            if (da - db !== 0) return da - db;
            return (parseInt(a.id)||0) - (parseInt(b.id)||0);
          });
        const totalPaid2   = pays2.reduce((s,p) => s + parseFloat(p.amount||0), 0);
        const totalSettle2 = pays2.reduce((s,p) => s + parseFloat(p.settlement_discount||0), 0);
        const remaining2   = Math.max(0, (d.grand||0) - totalPaid2 - totalSettle2);
        if (totalPaid2 < 0.01) return '';
        const instalRows2 = pays2.map((p,i) => {
          const dtF = p.date||p.payment_date ? new Date(p.date||p.payment_date).toLocaleDateString(_moneyLocale(),{day:'2-digit',month:'short',year:'numeric'}) : '';
          const meth = p.method||'';
          const pSettle2 = parseFloat(p.settlement_discount||0);
          return `<div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0;border-bottom:1px dashed ${T.totbr}">
            <span style="color:#388E3C">${meth.startsWith('Split')?'⚡':'✓'} Instalment ${i+1}${dtF?' · '+dtF:''}${meth?' · '+meth.replace('Split: ','').substring(0,28):''}${pSettle2>0?' (incl. '+fmt_money(pSettle2,d.sym)+' disc)':''}</span>
            <span style="font-family:monospace;font-weight:600;color:#388E3C">-${fmt_money(parseFloat(p.amount||0),d.sym)}</span>
          </div>`;
        }).join('');
        const settleRow2 = totalSettle2 > 0.001
          ? `<div style="display:flex;justify-content:space-between;font-size:12px;padding:6px 0;border-bottom:1px solid ${T.totbr}">
              <span style="color:#E65100;font-weight:700">✂ Settlement Discount</span>
              <span style="font-family:monospace;font-weight:700;color:#E65100">-${fmt_money(totalSettle2,d.sym)}</span>
            </div>`
          : '';
        const paidLabel = isPaid2 ? '✅ Paid in Full' : `💚 Total Paid${pays2.length>1?' ('+pays2.length+' instalments)':''}`;
        // Show payment date for single full payment
        const _singlePaidDate = (isPaid2 && pays2.length === 1)
          ? (() => {
              const dt = pays2[0].date || pays2[0].payment_date || '';
              if (!dt) return '';
              const dtF = new Date(dt).toLocaleDateString(_moneyLocale(), {day:'2-digit', month:'short', year:'numeric'});
              const meth = pays2[0].method || '';
              const txn  = pays2[0].txn || '';
              return `<div style="font-size:10px;color:#4CAF50;margin-top:2px;font-weight:600">
                ${dtF}${meth ? ' · ' + meth : ''}${txn ? ' · ' + txn : ''}
              </div>`;
            })()
          : '';
        const paidRow2 = `<div style="padding:8px 22px;border-top:1px solid ${T.totbr}">
          ${isCancelled2?`<div style="font-size:9.5px;font-weight:700;color:#B71C1C;text-transform:uppercase;letter-spacing:.8px;padding:4px 0 2px">⚠ Payment received before cancellation</div>`:''}
          ${settleRow2}
          <div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0;${pays2.length>1?'border-bottom:2px solid #A5D6A7':''}">
            <div><span style="color:#388E3C;font-weight:700">${paidLabel}</span>${_singlePaidDate}</div>
            <span style="font-family:monospace;font-weight:800;color:#388E3C">-${fmt_money(totalPaid2,d.sym)}</span>
          </div>
          ${pays2.length>1?`<div style="background:#F1F8E9;border-radius:6px;padding:4px 8px;margin-top:4px">${instalRows2}</div>`:''}
        </div>`;
        const remRow2 = ((isPartial2 || isCancelled2) && remaining2 > 0.01)
          ? `<div style="margin:6px 14px 10px;display:flex;justify-content:space-between;font-size:13px;font-weight:800;padding:8px 10px;background:${isCancelled2?'#FFEBEE':'#FFF8E1'};border-radius:7px;border:2px solid ${isCancelled2?'#FFCDD2':'#FFB300'};color:${isCancelled2?'#B71C1C':'#E65100'}">
              <span>${isCancelled2?'🚫 Unpaid at Cancellation':'⚠ Remaining Due'}</span>
              <span style="font-family:monospace">${fmt_money(remaining2,d.sym)}</span>
            </div>`
          : '';
        return paidRow2 + remRow2;
      })()}
      <!-- Signature -->
      ${d.popt.sign?(()=>{const sig=d.signature||STATE.settings.signature||'';return `<div style="padding:14px 22px;border-top:1px solid ${T.totbr};text-align:right">${sig?`<img src="${sig}" style="height:44px;max-width:160px;object-fit:contain;display:block;margin-left:auto" onerror="this.style.display='none'">`:'<div style="width:140px;border-bottom:1.5px solid #bbb;margin-left:auto;height:36px"></div>'}<div style="font-size:10px;color:#aaa;margin-top:5px;font-weight:600">Authorised Signatory</div><div style="font-size:10px;color:#bbb">${sc.company}</div></div>`;})():''}
    </div>
  </div>

  <!-- PREVIOUS DUE -->
  ${previousDueBlock(d,'#92400E','rgba(146,64,14,0.06)','rgba(146,64,14,0.25)')}

  <div style="margin-top:24px"></div>
  <!-- FOOTER -->
  ${d.popt.footer!==false?`
  <div style="padding:12px 24px;background:${T.footbg};display:flex;justify-content:space-between;align-items:center">
    <div>
      <div style="font-size:10px;color:${T.foottext};letter-spacing:.5px;line-height:1.8;font-weight:600">${sc.company}${sc.gst?' · GSTIN: '+sc.gst:''}</div>
      <div style="font-size:10px;color:${T.foottext};letter-spacing:.3px">Computer-generated invoice · No physical signature required</div>
    </div>
  </div>`:''}
  </div>`;
}

function buildTplF(d, sc, itemsHTML, gstColHeader, rowNumHeader='') {
  d.popt = d.popt || {};
  sc = resolveCompany(sc);
  const sym      = d.sym || '₹';
  const useSerif = (window.TPL_CUSTOM && TPL_CUSTOM.font && TPL_CUSTOM.font.includes('serif'))
                   ? TPL_CUSTOM.font : "'Georgia','Times New Roman',serif";
  const sans     = "'Public Sans','Segoe UI',sans-serif";

  // ── Status badge (outlined, no fill) ──────────────────────────────────────
  const statusBorder = { Paid:'#166534', Pending:'#92400E', Overdue:'#991B1B', Draft:'#374151', Partial:'#92400E', Cancelled:'#6B7280', Estimate:'#1E40AF' };
  const sBdr = statusBorder[d.status] || '#374151';

  // ── Payment info for "Paid" status ────────────────────────────────────────
  const isPaid    = d.status === 'Paid';
  const isPartial = d.status === 'Partial';
  let paidDateStr = '';
  let paidSummaryHTML = '';
  if ((isPaid || isPartial) && typeof STATE !== 'undefined') {
    const invIdStr = d.invId ? String(d.invId) : '';
    const pmts = (STATE.payments || [])
      .filter(p => p.invoice_id && String(p.invoice_id) === invIdStr)
      .sort((a, b) => new Date(a.date || a.payment_date || 0) - new Date(b.date || b.payment_date || 0));
    if (pmts.length) {
      const lastPmt = pmts[pmts.length - 1];
      const dt = lastPmt.date || lastPmt.payment_date || '';
      paidDateStr = dt ? new Date(dt).toLocaleDateString(_moneyLocale(), { day:'2-digit', month:'short', year:'numeric' }) : '';
      const totalPaid = pmts.reduce((s, p) => s + parseFloat(p.amount || 0), 0);
      const pmtRows = pmts.map(p => {
        const pDt  = p.date || p.payment_date || '';
        const pDtF = pDt ? new Date(pDt).toLocaleDateString(_moneyLocale(), { day:'2-digit', month:'short', year:'numeric' }) : '—';
        return `<tr>
          <td style="padding:5px 6px;font-size:10px;border-bottom:0.5px solid #e5e5e5;font-family:${sans}">${pDtF}</td>
          <td style="padding:5px 6px;font-size:10px;border-bottom:0.5px solid #e5e5e5;font-family:${sans}">${p.method || '—'}</td>
          <td style="padding:5px 6px;font-size:10px;border-bottom:0.5px solid #e5e5e5;font-family:monospace">${p.txn || '—'}</td>
          <td style="padding:5px 6px;font-size:10px;text-align:right;font-weight:700;border-bottom:0.5px solid #e5e5e5;font-family:monospace">${fmt_money(parseFloat(p.amount||0), sym)}</td>
        </tr>`;
      }).join('');
      paidSummaryHTML = `
      <div style="margin-top:18px;padding-top:14px;border-top:1px solid #ccc">
        <div style="font-size:8px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#555;margin-bottom:8px;font-family:${sans}">
          ${isPaid ? 'Payment Record' : 'Partial Payment Record'}
        </div>
        <table style="width:100%;border-collapse:collapse;border-top:1.5px solid #333;border-bottom:1px solid #333">
          <thead><tr style="border-bottom:1px solid #333">
            <th style="padding:5px 6px;font-size:8px;letter-spacing:1px;text-transform:uppercase;text-align:left;font-family:${sans}">Date</th>
            <th style="padding:5px 6px;font-size:8px;letter-spacing:1px;text-transform:uppercase;text-align:left;font-family:${sans}">Mode</th>
            <th style="padding:5px 6px;font-size:8px;letter-spacing:1px;text-transform:uppercase;text-align:left;font-family:${sans}">Ref / Txn ID</th>
            <th style="padding:5px 6px;font-size:8px;letter-spacing:1px;text-transform:uppercase;text-align:right;font-family:${sans}">Amount</th>
          </tr></thead>
          <tbody>${pmtRows}</tbody>
        </table>
        ${isPartial ? `<div style="margin-top:6px;font-size:10px;font-family:${sans};color:#92400E">
          Balance outstanding: <strong>${fmt_money(Math.max(0,(d.grand||0)-totalPaid), sym)}</strong>
        </div>` : ''}
      </div>`;
    }
  }

  // ── Totals block with discount row ────────────────────────────────────────
  const sub      = d.sub     || 0;
  const discAmt  = d.discAmt || 0;
  const disc     = d.disc    || 0;
  const discType = d.discType|| 'percent';
  const gstAmt   = d.gstAmt  || 0;
  const grand    = d.grand   || 0;
  const afterDisc = sub - discAmt;

  const trStyle  = `display:flex;justify-content:space-between;font-size:10px;padding:4px 0;border-bottom:0.5px solid #ddd;font-family:${sans}`;
  const valStyle = `font-family:monospace;font-weight:600`;

  const totalsHTML = `
  <div style="display:flex;justify-content:flex-end;margin-top:12px">
    <div style="min-width:210px">
      <div style="${trStyle}"><span>Subtotal</span><span style="${valStyle}">${fmt_money(sub,sym)}</span></div>
      ${discAmt > 0 ? `<div style="${trStyle}"><span>Discount${discType==='fixed'?' (fixed)':disc>0?' ('+Math.round(disc*100)/100+'%)':''}</span><span style="${valStyle};color:#b91c1c">− ${fmt_money(discAmt,sym)}</span></div>
      <div style="${trStyle}"><span>After Discount</span><span style="${valStyle}">${fmt_money(afterDisc,sym)}</span></div>` : ''}
      ${gstAmt > 0 ? `<div style="${trStyle}"><span>GST</span><span style="${valStyle}">+ ${fmt_money(gstAmt,sym)}</span></div>` : ''}
      <div style="display:flex;justify-content:space-between;padding:8px 0;margin-top:4px;border-top:1.5px solid #333;font-family:${sans}">
        <span style="font-size:12px;font-weight:700">Total Due</span>
        <span style="font-family:monospace;font-weight:800;font-size:14px">${fmt_money(grand,sym)}</span>
      </div>
      ${isPaid ? `<div style="display:flex;justify-content:space-between;padding:4px 0;border-top:0.5px solid #ddd;font-size:10px;color:#166534;font-family:${sans}">
        <span>Paid on ${paidDateStr}</span>
        <span style="font-family:monospace;font-weight:700">${fmt_money(grand,sym)}</span>
      </div>` : ''}
    </div>
  </div>`;

  // ── Items table (adapt shared itemsHTML for ruled style) ──────────────────
  const ruledItems = itemsHTML
    .replace(/border-bottom:1px solid #eee/g, 'border-bottom:0.5px solid #ddd')
    .replace(/padding:9px 8px/g, 'padding:7px 6px')
    .replace(/font-size:11px/g, 'font-size:10.5px');

  return `<div style="font-family:${useSerif};background:#fff;width:794px;min-height:1123px;position:relative;color:#1a1a1a">
  ${tplWatermark(d)}

  <!-- LETTERHEAD -->
  <div style="text-align:center;padding:36px 48px 18px;border-bottom:2px solid #1a1a1a">
    ${d.popt.logo !== false && sc.logo
      ? `<img src="${sc.logo}" style="height:52px;max-width:220px;object-fit:contain;display:block;margin:0 auto 10px" onerror="this.style.display='none';this.nextElementSibling.style.display='block'"><div style="display:none;font-size:20px;font-weight:700;letter-spacing:2px;text-transform:uppercase">${sc.company}</div>`
      : `<div style="font-size:20px;font-weight:700;letter-spacing:2px;text-transform:uppercase">${sc.company}</div>`}
    <div style="font-size:9px;letter-spacing:1px;color:#555;line-height:2;margin-top:4px;font-family:${sans}">
      ${sc.address ? `${sc.address.replace(/\n/g,' · ')} &nbsp;|&nbsp; ` : ''}
      ${sc.gst ? `GSTIN: ${sc.gst} &nbsp;|&nbsp; ` : ''}
      ${sc.phone ? `${sc.phone}` : ''}
      ${sc.email ? ` &nbsp;|&nbsp; ${sc.email}` : ''}
    </div>
  </div>

  <!-- REF BLOCK -->
  <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:18px 48px 14px;border-bottom:0.5px solid #ccc">
    <div style="font-size:10px;line-height:2.2;color:#444;font-family:${sans}">
      <div>Ref. No. &nbsp;<strong style="color:#1a1a1a;font-family:monospace">${d.status==='Estimate'?'EST':'INV'}/${new Date().getFullYear()}/${String(d.num||'').replace(/[^0-9]/g,'').padStart(4,'0') || d.num}</strong></div>
      <div>Issue Date &nbsp;<strong style="color:#1a1a1a">${d.date||'—'}</strong></div>
      <div>Due Date &nbsp;&nbsp;<strong style="color:#1a1a1a">${d.due||'—'}</strong></div>
      ${isPaid && paidDateStr ? `<div>Paid On &nbsp;&nbsp;&nbsp;&nbsp;<strong style="color:#166534">${paidDateStr}</strong></div>` : ''}
    </div>
    <div style="text-align:right">
      <div style="font-size:9px;letter-spacing:2px;text-transform:uppercase;color:#888;font-family:${sans}">${d.status==='Estimate'?'Estimate':'Invoice'}</div>
      <div style="font-size:20px;font-weight:700;font-family:monospace;letter-spacing:1px;color:#1a1a1a">#${d.num}</div>
      <div style="font-size:9px;color:#888;margin-top:2px;font-family:${sans}">${sc.currency||'INR'}</div>
      <span style="display:inline-block;margin-top:6px;padding:2px 10px;border:1px solid ${sBdr};font-size:8px;letter-spacing:2px;text-transform:uppercase;color:${sBdr};font-family:${sans}">${d.status.toUpperCase()}</span>
    </div>
  </div>

  <!-- BILL TO / FROM -->
  <div style="display:flex;gap:0;padding:14px 48px 14px;border-bottom:0.5px solid #ccc">
    <div style="flex:1;padding-right:24px;border-right:0.5px solid #ccc">
      <div style="font-size:8px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#888;margin-bottom:6px;font-family:${sans}">Billed By</div>
      <div style="font-size:12px;font-weight:700;color:#1a1a1a">${sc.company}</div>
      <div style="font-size:10px;color:#555;line-height:1.9;margin-top:3px;font-family:${sans}">
        ${sc.gst ? `<div>GSTIN: ${sc.gst}</div>` : ''}
        ${sc.address ? `<div>${sc.address.replace(/\n/g,', ')}</div>` : ''}
      </div>
    </div>
    <div style="flex:1;padding-left:24px">
      <div style="font-size:8px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#888;margin-bottom:6px;font-family:${sans}">Billed To</div>
      ${tplClientLogoHTML(d)}
      <div style="font-size:12px;font-weight:700;color:#1a1a1a">${d.cname}</div>
      <div style="font-size:10px;color:#555;line-height:1.9;margin-top:3px;font-family:${sans}">
        ${d.cperson ? `<div>${d.cperson}</div>` : ''}
        ${d.cemail  ? `<div>${d.cemail}</div>`  : ''}
        ${d.cwa     ? `<div>${d.cwa}</div>`     : ''}
        ${d.caddr   ? `<div>${d.caddr.replace(/\n/g,', ')}</div>` : ''}
        ${d.cgst    ? `<div>GSTIN: ${d.cgst}</div>` : ''}
      </div>
    </div>
  </div>

  <!-- ITEMS TABLE -->
  <div style="padding:18px 48px 0">
    <table style="width:100%;border-collapse:collapse;font-family:${sans}">
      <thead>
        <tr style="border-top:1.5px solid #1a1a1a;border-bottom:1px solid #1a1a1a">
          <th style="padding:7px 6px;font-size:8px;letter-spacing:1.5px;text-transform:uppercase;font-weight:700;text-align:left;width:24px">#</th>
          <th style="padding:7px 6px;font-size:8px;letter-spacing:1.5px;text-transform:uppercase;font-weight:700;text-align:left">Description</th>
          <th style="padding:7px 6px;font-size:8px;letter-spacing:1.5px;text-transform:uppercase;font-weight:700;text-align:center">Type</th>
          <th style="padding:7px 6px;font-size:8px;letter-spacing:1.5px;text-transform:uppercase;font-weight:700;text-align:right">Qty</th>
          <th style="padding:7px 6px;font-size:8px;letter-spacing:1.5px;text-transform:uppercase;font-weight:700;text-align:right">Rate</th>
          <th style="padding:7px 6px;font-size:8px;letter-spacing:1.5px;text-transform:uppercase;font-weight:700;text-align:right">Amount</th>
          ${gstColHeader ? `<th style="padding:7px 6px;font-size:8px;letter-spacing:1.5px;text-transform:uppercase;font-weight:700;text-align:center">GST%</th>` : ''}
          <th style="padding:7px 6px;font-size:8px;letter-spacing:1.5px;text-transform:uppercase;font-weight:700;text-align:right">Total</th>
        </tr>
      </thead>
      <tbody>${ruledItems}</tbody>
    </table>

    ${totalsHTML}
  </div>

  <!-- BANK + NOTES + PAYMENT RECORD + SIGNATURE -->
  <div style="padding:18px 48px 0;display:flex;gap:32px">
    <div style="flex:1">
      ${tplBankHTML(d,'#333','#fafafa','border:0.5px solid #ccc;border-radius:0')}
      ${tplNotesHTML(d,'#555','#fafafa')}
      ${tplTncHTML(d,'#888')}
      ${paidSummaryHTML}
    </div>
    <div style="width:200px">
      ${tplQrHTML(d)}
      ${tplSignHTML(d,'','Authorised Signatory')}
    </div>
  </div>

  <!-- FOOTER RULE -->
  <div style="margin:18px 48px 0;padding-top:12px;border-top:0.5px solid #ccc;display:flex;justify-content:space-between;align-items:center">
    <span style="font-size:9px;color:#999;font-family:${sans}">${d.generatedBy||sc.company||''}</span>
    <span style="font-size:9px;color:#999;font-family:${sans}">${sc.website||sc.email||''}</span>
  </div>
  <div style="height:3px;background:#1a1a1a;margin-top:10px"></div>
</div>`;
}

function buildInvoiceHTML(d, forPrint) {
  const sc = STATE.settings;
  d.popt = d.popt || {};  // safety guard — popt must always be an object
  // Build items HTML with GST column
  const showGstCol = d.popt ? d.popt.gstCol : true;
  const itemsHTML = formItems.length
    ? formItems.map((i, idx) => {
        const line    = (i.qty||1)*(i.rate||0);
        const itemGst = parseFloat(i.gst ?? 0);
        const gstAmt  = line * itemGst / 100;
        const lineInclGst = line + gstAmt;
        const itype = i.itemType||'Service';
        // GST badge colors
        const gstBadge = itemGst === 0
          ? `<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:#F1F5F9;color:#475569;border:1px solid #CBD5E1">${itemGst}%</span>`
          : itemGst <= 5
          ? `<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:#F0FDF4;color:#166534;border:1px solid #86EFAC">${itemGst}%</span>`
          : itemGst <= 12
          ? `<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:#FEF3C7;color:#92400E;border:1px solid #FDE68A">${itemGst}%</span>`
          : `<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:#FEE2E2;color:#991B1B;border:1px solid #FECACA">${itemGst}%</span>`;
        return `<tr>
          <td style="padding:9px 8px;border-bottom:1px solid #eee;font-size:11px;color:#111;font-family:monospace;font-weight:700">${String(idx+1).padStart(2,'0')}</td>
          <td style="padding:9px 8px;border-bottom:1px solid #eee;font-weight:700;color:#111">${i.desc||'—'}</td>
          <td style="padding:9px 8px;text-align:center;border-bottom:1px solid #eee"><span style="font-size:10px;font-weight:700;background:#F1F5F9;color:#475569;padding:2px 8px;border-radius:4px;border:1px solid #E2E8F0">${itype}</span></td>
          <td style="padding:9px 8px;text-align:right;border-bottom:1px solid #eee;font-family:monospace">${i.qty}</td>
          <td style="padding:9px 8px;text-align:right;border-bottom:1px solid #eee;font-family:monospace">${fmt_money(i.rate,d.sym)}</td>
          <td style="padding:9px 8px;text-align:right;border-bottom:1px solid #eee;font-family:monospace">${fmt_money(line,d.sym)}</td>
          ${showGstCol ? `<td style="padding:9px 8px;text-align:center;border-bottom:1px solid #eee">${gstBadge}</td>` : ''}
          <td style="padding:9px 8px;text-align:right;font-weight:800;border-bottom:1px solid #eee;font-family:monospace;color:#111">${fmt_money(lineInclGst,d.sym)}</td>
        </tr>`;
      }).join('')
    : `<tr><td colspan="${showGstCol?8:7}" style="padding:20px;text-align:center;color:#aaa">No items added</td></tr>`;

  const gstColHeader = showGstCol ? `<th style="padding:10px 8px;text-align:center">GST%</th>` : '';
  const rowNumHeader = `<th style="padding:10px 8px;text-align:left;width:28px">#</th>`;

  const _tplMap = {'2':buildTpl2,'F':buildTplF}; // Only these two are ported into pdf.php — keep in sync if either changes
  const fn = _tplMap[String(d.tpl)] || buildTpl2;
  return fn(d, sc, itemsHTML, gstColHeader, rowNumHeader);
}

function tplBankHTML(d, color='#00695C', bg='#e0f2f1', border='') {
  if (!d.popt || d.popt.bank === false) return '';
  if (d.status === 'Paid') return '';  // Hide bank details on paid invoices
  const _sc = (typeof STATE !== 'undefined' ? STATE.settings : {});
  const bankText = d.bank || _sc.defaultBank || '';
  const sc  = _sc;
  const upi = d.upi || sc.upi || '';
  const hasBank = !!bankText;
  const hasUpi  = !!upi;
  const hasQr   = !!(d.popt && d.popt.qr && d.qrUrl);

  if (!hasBank && !hasUpi && !hasQr) return '';

  // Left column: bank details
  const leftCol = hasBank
    ? `<div style="flex:1;min-width:0">
        <div style="font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.7px;margin-bottom:6px;color:${color}">💳 Bank Details</div>
        <div style="font-size:10.5px;line-height:1.9;color:${color}">
          ${bankText.split('|').map(s=>s.trim()).filter(Boolean).map(s=>`<div>${s}</div>`).join('')}
        </div>
      </div>`
    : '';

  // Right column: UPI id + QR
  const qrImg = hasQr
    ? `<div style="margin-top:8px;text-align:center"><img src="${d.qrUrl}" style="width:76px;height:76px;border-radius:6px;border:1px solid #cde8e4;display:block;margin:0 auto" onerror="this.style.display='none'"><div style="font-size:9px;color:#888;margin-top:3px">Scan to Pay</div></div>`
    : '';
  const upiBlock = hasUpi
    ? `<div style="font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.7px;margin-bottom:6px;color:${color}">📲 UPI</div>
       <div style="background:rgba(0,0,0,.06);border-radius:6px;padding:6px 8px;font-size:12px;font-weight:800;letter-spacing:.4px;color:${color};text-align:center">${upi}</div>
       ${qrImg}`
    : '';
  const rightCol = (hasUpi || hasQr)
    ? `<div style="flex-shrink:0;min-width:110px;max-width:140px;text-align:center">${upiBlock}</div>`
    : '';

  const divider = hasBank && (hasUpi || hasQr)
    ? `<div style="width:1px;background:rgba(0,0,0,.1);margin:0 12px;align-self:stretch"></div>`
    : '';

  return `<div style="margin-top:16px;background:${bg};border-radius:8px;padding:12px 14px;display:flex;align-items:flex-start;gap:0;${border}">
    ${leftCol}${divider}${rightCol}
  </div>`;
}

function tplClientLogoHTML(d) {
  if (!d.popt || !d.popt.clientLogo || !d.clientLogo) return '';
  return `<img src="${d.clientLogo}" style="height:36px;max-width:120px;object-fit:contain;display:block;margin-bottom:6px" onerror="this.style.display='none'">`;
}

function tplNotesHTML(d, color='#795548', bg='#fff8e1') {
  if (!d.popt || !d.popt.notes) return '';
  const isPaid = d.status === 'Paid';
  if (isPaid) {
    // Show positive thank-you message for paid invoices instead of notes
    const sc = STATE.settings;
    return `<div style="margin-top:10px;background:linear-gradient(135deg,#E8F5E9,#F1F8E9);border-radius:8px;padding:12px 14px;font-size:11px;color:#2E7D32;line-height:1.8;border-left:3px solid #4CAF50">
      <div style="font-weight:800;font-size:13px;margin-bottom:4px">🎉 Thank You for Your Payment!</div>
      <div>We appreciate your prompt payment and continued trust in <strong>${sc.company||''}</strong>. Your account is now clear and up to date.</div>
      <div style="margin-top:6px;opacity:.8">We look forward to serving you again. For any queries, reach us at ${sc.phone||sc.email||''}.</div>
    </div>`;
  }
  if (!d.notes) return '';
  const notesHtml = d.notes.replace(/\n/g, '<br>');
  return `<div style="margin-top:10px;background:${bg};border-radius:8px;padding:10px 14px;font-size:11px;color:${color};line-height:1.6">${notesHtml}</div>`;
}

function tplQrHTML(d) {
  // QR is now embedded inside tplBankHTML when bank is shown; standalone fallback when no bank
  if (!d.popt.qr || !d.qrUrl) return '';
  const bankText = d.bank || STATE.settings.defaultBank || '';
  if (bankText && d.popt && d.popt.bank !== false) return ''; // already rendered inside tplBankHTML
  const _upi = d.upi || (typeof STATE !== 'undefined' ? STATE.settings.upi : '') || '';
  return `<div style="margin-top:12px;display:flex;align-items:center;gap:12px"><img src="${d.qrUrl}" style="width:80px;height:80px;border-radius:6px;border:1px solid #eee" onerror="this.style.display='none'"><div style="font-size:11px;color:#888">Scan QR to pay via UPI<br><strong>${_upi}</strong></div></div>`;
}

function tplSignHTML(d, sc_arg, label='Authorised Signatory') {
  // sc_arg is optional - for backwards compat where called with just (d)
  const _stateSettings = (typeof STATE !== 'undefined' ? STATE.settings : {});
  const sc = (sc_arg && typeof sc_arg === 'object' && sc_arg.company) ? sc_arg : _stateSettings;
  if (!d.popt.sign) return '';
  const sig = d.signature || sc.signature || '';
  const sigImg = sig
    ? `<img src="${sig}" style="height:52px;max-width:180px;object-fit:contain;display:block;margin-left:auto" onerror="this.style.display='none'">`
    : `<div style="width:160px;border-bottom:1.5px solid #bbb;margin-left:auto;height:44px"></div>`;
  return `<div style="margin-top:24px;display:flex;justify-content:space-between;align-items:flex-end">
    <div style="font-size:10px;color:#999;line-height:1.8">
      ${sc.phone ? `<span style="margin-right:12px"><i style="font-family:sans-serif">📞</i> ${sc.phone}</span>` : ''}
      ${sc.email ? `<span><i style="font-family:sans-serif">✉</i> ${sc.email}</span>` : ''}
    </div>
    <div style="text-align:right">
      ${sigImg}
      <div style="font-size:10px;color:#aaa;margin-top:5px;font-weight:600">${label}</div>
      <div style="font-size:10px;color:#bbb">${sc.company}</div>
    </div>
  </div>`;
}

function tplTncHTML(d, color='#888') {
  if (!d.popt || !d.popt.tnc) return '';
  const tnc = (d.tnc || '').trim();
  if (!tnc) return '';
  const tncHtml = tnc.replace(/\n/g, '<br>');
  return `<div style="margin-top:12px;border-top:1px solid #eee;padding-top:10px;width:100%"><div style="font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#aaa;margin-bottom:5px">Terms &amp; Conditions</div><div style="font-size:10.5px;color:${color};line-height:1.7">${tncHtml}</div></div>`;
}

function tplWatermark(d) {
  // Always respect the watermark toggle — for all statuses
  if (!d.popt || !d.popt.watermark) return '';

  let wText = '', wColor = '';
  if (d.status === 'Paid') {
    wText  = (window.TPL_CUSTOM && TPL_CUSTOM.watermarkText) ? TPL_CUSTOM.watermarkText : 'PAID';
    wColor = 'rgba(0,150,0,.12)';
  } else if (d.status === 'Cancelled') {
    wText = 'CANCELLED'; wColor = 'rgba(183,28,28,.15)';
  } else if (d.status === 'Partial') {
    wText = 'PARTIAL'; wColor = 'rgba(255,152,0,.13)';
  } else if (d.status === 'Pending') {
    wText = 'PENDING'; wColor = 'rgba(255,152,0,.10)';
  } else if (d.status === 'Overdue') {
    wText = 'OVERDUE'; wColor = 'rgba(229,57,53,.12)';
  } else if (d.status === 'Draft') {
    wText = 'DRAFT'; wColor = 'rgba(0,0,0,.07)';
  } else if (d.status === 'Estimate') {
    wText = 'ESTIMATE'; wColor = 'rgba(57,73,171,.10)';
  } else {
    return '';
  }
  return `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-35deg);font-size:80px;font-weight:900;color:${wColor};z-index:999;pointer-events:none;white-space:nowrap;letter-spacing:8px;user-select:none">${wText}</div>`;
}

function previousDueBlock(d, accentColor, bgColor, borderColor) {
  if (d.popt && d.popt.previousDue === false) return '';
  const clientId = d.clientId || d.client_id || '';
  const currentNum = String(d.num || '');
  if (!clientId || typeof STATE === 'undefined') return '';

  // Find other unpaid invoices for same client (exclude current)
  const outstanding = (STATE.invoices || []).filter(inv => {
    if (String(inv.client) !== String(clientId)) return false;
    if (String(inv.num || inv.invoice_number || '') === currentNum) return false;
    return ['Pending', 'Overdue', 'Partial'].includes(inv.status);
  });

  if (!outstanding.length) return '';

  const sym = d.sym || (STATE.settings && STATE.settings.currency) || '₹';

  // Calculate remaining balance for each invoice
  const rows = outstanding.map(inv => {
    const invId    = String(inv.id || '');
    const totalPaid = (STATE.payments || [])
      .filter(p => String(p.invoice_id) === invId && !p.invoice_deleted)
      .reduce((s, p) => s + parseFloat(p.amount || 0) + parseFloat(p.settlement_discount || 0), 0);
    const grand    = parseFloat(inv.amount || inv.grand_total || 0);
    const balance  = Math.max(0, grand - totalPaid);
    const num      = inv.num || inv.invoice_number || '—';
    const due      = inv.due || inv.due_date || '';
    const dueDate  = due ? new Date(due) : null;
    const today    = new Date(); today.setHours(0,0,0,0);
    const diffDays = dueDate ? Math.round((dueDate - today) / 86400000) : null;
    const dueF     = dueDate ? dueDate.toLocaleDateString(_moneyLocale(), {day:'2-digit',month:'short',year:'numeric'}) : '—';
    const status   = inv.status;

    // Per-status colors
    let badgeBg, badgeColor, rowBg, rowBorder, amountColor, dueLabelColor, dueLabel;
    if (status === 'Overdue') {
      badgeBg = '#FEE2E2'; badgeColor = '#B91C1C';
      rowBg = '#FFF5F5'; rowBorder = '0.5px solid #FCA5A5';
      amountColor = '#B91C1C';
      dueLabelColor = '#B91C1C';
      dueLabel = diffDays !== null ? `${Math.abs(diffDays)} day${Math.abs(diffDays)!==1?'s':''} overdue` : '';
    } else if (status === 'Partial') {
      badgeBg = '#FEF3C7'; badgeColor = '#B45309';
      rowBg = '#FAFAF9'; rowBorder = '0.5px solid #E5E7EB';
      amountColor = '#1A1A2E';
      dueLabelColor = '#D97706';
      dueLabel = diffDays !== null && diffDays >= 0 ? `${diffDays} day${diffDays!==1?'s':''} remaining` : (diffDays < 0 ? `${Math.abs(diffDays)} day${Math.abs(diffDays)!==1?'s':''} overdue` : '');
    } else {
      badgeBg = '#FEF3C7'; badgeColor = '#92400E';
      rowBg = '#FAFAF9'; rowBorder = '0.5px solid #E5E7EB';
      amountColor = '#1A1A2E';
      dueLabelColor = '#D97706';
      dueLabel = diffDays !== null && diffDays >= 0 ? `${diffDays} day${diffDays!==1?'s':''} remaining` : (diffDays < 0 ? `${Math.abs(diffDays)} day${Math.abs(diffDays)!==1?'s':''} overdue` : '');
    }

    // Progress bar for Partial
    const progressBar = status === 'Partial' ? (() => {
      const pct = grand > 0 ? Math.min(100, Math.round((totalPaid / grand) * 100)) : 0;
      return `
        <div style="margin-top:8px">
          <div style="height:4px;background:#E5E7EB;border-radius:99px;overflow:hidden">
            <div style="width:${pct}%;height:100%;background:#D97706;border-radius:99px"></div>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:9px;color:#94A3B8;margin-top:3px">
            <span>${sym}${totalPaid.toLocaleString('en-IN', {minimumFractionDigits:2,maximumFractionDigits:2})} paid</span>
            <span style="color:#D97706">${sym}${balance.toLocaleString('en-IN', {minimumFractionDigits:2,maximumFractionDigits:2})} remaining</span>
          </div>
        </div>`;
    })() : '';

    // Amount display — Partial shows balance + "balance of grand"
    const amountHTML = status === 'Partial'
      ? `<div style="text-align:right">
           <div style="font-size:13px;font-weight:700;font-family:monospace;color:${amountColor}">${fmt_money(balance, sym)}</div>
           <div style="font-size:9px;color:#94A3B8">balance of ${fmt_money(grand, sym)}</div>
         </div>`
      : `<span style="font-size:13px;font-weight:700;font-family:monospace;color:${amountColor}">${fmt_money(balance, sym)}</span>`;

    return { html: `
      <div style="background:${rowBg};border-radius:8px;padding:10px 12px;margin-bottom:8px;border:${rowBorder}">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div style="display:flex;align-items:center;gap:10px">
            <span style="font-size:10px;background:${badgeBg};color:${badgeColor};padding:2px 7px;border-radius:4px;font-weight:700">${status.toUpperCase()}</span>
            <span style="font-size:12px;font-weight:700;font-family:monospace;color:#0F172A">#${num}</span>
          </div>
          ${amountHTML}
        </div>
        ${progressBar}
        <div style="margin-top:6px;font-size:11px;color:#64748B;display:flex;gap:16px;align-items:center">
          <span>&#128197; Due ${dueF}</span>
          ${dueLabel ? `<span style="color:${dueLabelColor};font-weight:600">${dueLabel}</span>` : ''}
        </div>
      </div>`, balance };
  });

  const totalOutstanding = rows.reduce((s, r) => s + r.balance, 0);
  // If current invoice is unpaid, add it to total
  const grandToAdd = d.status !== 'Paid' ? (parseFloat(d.grand) || 0) : 0;
  const totalPayable = totalOutstanding + grandToAdd;

  // Header + bar color: red if any overdue, else amber/brown
  const hasOverdue = outstanding.some(inv => inv.status === 'Overdue');
  const headerColor  = hasOverdue ? '#B91C1C' : '#92400E';
  const badgeCountBg = hasOverdue ? '#FEE2E2' : '#FEF3C7';
  const outerBorder  = hasOverdue ? '0.5px solid #FCA5A5' : '0.5px solid #FCD34D';
  const barBg        = hasOverdue ? '#B91C1C' : '#92400E';
  const dividerColor = hasOverdue ? '#FCA5A5' : '#FCD34D';

  const rowsHTML = rows.map(r => r.html).join('');

  // This Invoice row — only if current invoice is not paid
  const thisInvoiceRow = d.status !== 'Paid' ? `
    <div style="display:flex;justify-content:space-between;font-size:10px;color:#64748B;padding:3px 0">
      <span>This Invoice</span>
      <span style="font-family:monospace;font-weight:600">${fmt_money(d.grand || 0, sym)}</span>
    </div>` : '';

  const invoiceCount = rows.length + (d.status !== 'Paid' ? 1 : 0);

  return `
  <div style="margin:10px 0 0;padding:14px 16px;background:#fff;border-radius:10px;border:${outerBorder}">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <span style="font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:${headerColor}">⚠ Other Outstanding Invoices</span>
      <span style="font-size:11px;background:${badgeCountBg};color:${headerColor};padding:2px 8px;border-radius:6px;font-weight:700">${rows.length} invoice${rows.length>1?'s':''}</span>
    </div>
    ${rowsHTML}
    <div style="border-top:0.5px solid ${dividerColor};padding-top:10px">
      ${thisInvoiceRow}
      <div style="display:flex;justify-content:space-between;align-items:center;background:${barBg};border-radius:6px;padding:7px 10px;${thisInvoiceRow ? 'margin-top:5px' : ''}">
        <span style="font-size:11px;font-weight:800;color:#fff">Total Payable</span>
        <span style="font-family:monospace;font-size:13px;font-weight:800;color:#fff">${fmt_money(totalPayable, sym)}</span>
      </div>
      <div style="margin-top:6px;font-size:10px;color:#94A3B8;text-align:center">Please clear this at your earliest convenience. 🙏</div>
    </div>
    <div style="margin-top:8px;font-size:9px;color:#94A3B8">* Includes ${invoiceCount} separate invoice${invoiceCount>1?'s':''}. Please reference invoice numbers when paying.</div>
  </div>`;
}

function resolveCompany(sc) {
  const S = (typeof STATE !== 'undefined' ? STATE.settings : {});
  return {
    company: sc.company||S.company||'',
    phone:   sc.phone||S.phone||'',
    email:   sc.email||S.email||'',
    website: sc.website||S.website||'',
    gst:     sc.gst||S.gst||'',
    address: sc.address||S.address||'',
    logo:    sc.logo||S.logo||'',
    signature: sc.signature||S.signature||'',
    upi:     sc.upi||S.upi||''
  };
}

function printInvoiceData(inv) {
  // Restore formItems from invoice data temporarily
  const savedItems = [...formItems];
  formItems = inv.items.map(i => ({ id: Date.now() + Math.random(), desc: i.desc||i.description||'', itemType: i.itemType||i.item_type||'Service', qty: parseFloat(i.qty||i.quantity)||1, gst: (i.gst!==undefined&&i.gst!==null&&i.gst!==''?parseFloat(i.gst):i.gstRate!==undefined&&i.gstRate!==null&&i.gstRate!==''?parseFloat(i.gstRate):i.gst_rate!==undefined&&i.gst_rate!==''?parseFloat(i.gst_rate):18), rate: parseFloat(i.rate)||0 }));
  const d = getFormData();
  openPrintWindow(d, formItems);
  formItems = savedItems;
}

function openPrintWindow(d, items) {
  const showGst = d.popt ? d.popt.gstCol : true;
  const buildGstBadge = (rate) => {
    const r = parseFloat(rate)||0;
    const [bg, color, border] = r === 0
      ? ['#F1F5F9','#475569','#CBD5E1']
      : r <= 5
      ? ['#F0FDF4','#166534','#86EFAC']
      : r <= 12
      ? ['#FEF3C7','#92400E','#FDE68A']
      : ['#FEE2E2','#991B1B','#FECACA'];
    return `<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:${bg};color:${color};border:1px solid ${border}">${r}%</span>`;
  };
  const itemsHTML = items.length
    ? items.map(i => {
        const line = (i.qty||1)*(i.rate||0);
        const gstR = parseFloat(i.gst)||0;
        const gstAmt = line * gstR / 100;
        const lineInclGst = line + gstAmt;
        const itype = i.itemType||'Service';
        const pidx = items.indexOf(i);
        return `<tr>
          <td style="padding:10px 8px;border-bottom:1px solid #eee;font-size:11px;color:#111;font-family:monospace;font-weight:700">${String(pidx+1).padStart(2,'0')}</td>
          <td style="padding:10px 12px;border-bottom:1px solid #eee">${i.desc||'—'}</td>
          <td style="padding:10px 12px;text-align:center;border-bottom:1px solid #eee;font-size:11px;color:#888">${itype}</td>
          <td style="padding:10px 12px;text-align:center;border-bottom:1px solid #eee">${i.qty}</td>
          <td style="padding:10px 12px;text-align:right;border-bottom:1px solid #eee">${fmt_money(i.rate,d.sym)}</td>
          <td style="padding:10px 12px;text-align:right;border-bottom:1px solid #eee">${fmt_money(line,d.sym)}</td>
          ${showGst ? `<td style="padding:10px 12px;text-align:center;border-bottom:1px solid #eee">${buildGstBadge(gstR)}</td>` : ''}
          <td style="padding:10px 12px;text-align:right;font-weight:700;border-bottom:1px solid #eee">${fmt_money(lineInclGst,d.sym)}</td>
        </tr>`;
      }).join('')
    : `<tr><td colspan="${showGst?8:7}" style="padding:20px;text-align:center;color:#aaa">No items</td></tr>`;
  const gstColHeader = showGst ? `<th style="padding:10px 12px;text-align:center">GST%</th>` : '';
  const rowNumHeader = `<th style="padding:10px 8px;text-align:left;width:28px">#</th>`;
  const _tplMap = {'2':buildTpl2,'F':buildTplF}; // Only these two are ported into pdf.php — keep in sync if either changes
  const fn = _tplMap[String(d.tpl)] || buildTpl2;
  // Ensure d has sym set (fallback for when called from create form)
  if (!d.sym) d.sym = '₹';
  // Ensure d has discType set
  if (!d.discType) d.discType = 'percent';
  // Snapshot STATE — preserve invoices/payments for previousDueBlock
  const _printSc = Object.assign({}, STATE.settings);
  const _origStatePrint = window.STATE;
  window.STATE = Object.assign({}, STATE, {
    settings: _printSc,
    invoices: STATE.invoices || [],
    payments: STATE.payments || [],
  });
  const html = fn(d, _printSc, itemsHTML, gstColHeader, rowNumHeader);
  window.STATE = _origStatePrint;
  const w = window.open('','_blank','width=920,height=750');
  if (!w) { toast('⚠️ Pop-up blocked — please allow pop-ups for this site', 'warning'); return; }
  w.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Invoice ${d.num} – ${STATE.settings.company || 'Invoice'}</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
      *{box-sizing:border-box;margin:0;padding:0;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color-adjust:exact!important}
      body{background:#f0f0f0;font-family:'Public Sans',sans-serif;padding:0}
      .no-print{background:#fff;padding:10px 20px;display:flex;gap:12px;align-items:center;font-family:'Public Sans',sans-serif;font-size:13px;border-bottom:1px solid #ddd;position:sticky;top:0;z-index:99;box-shadow:0 1px 4px rgba(0,0,0,.1)}
      .print-wrap{padding:20px;display:flex;justify-content:center}
      @page{margin:0;size:A4}
      @media print{.no-print{display:none!important}body{background:#fff;padding:0}.print-wrap{padding:0;display:block}}
    </style>
  </head><body>
  <div class="no-print">
    <button onclick="window.print()" style="padding:8px 20px;background:#00897B;color:#fff;border:none;border-radius:7px;cursor:pointer;font-weight:700;font-family:inherit">🖨️ Print / Save PDF</button>
    <button onclick="window.close()" style="padding:8px 16px;background:#fff;border:1.5px solid #ddd;border-radius:7px;cursor:pointer;font-family:inherit">✕ Close</button>
    <span style="color:#888;font-size:12px">💡 Set margins to "None" in print dialog for best result</span>
  </div>
  <div class="print-wrap">${html}</div>
  </body></html>`);
  w.document.close();
}

function hexToRgba(hex, alpha) {
  const h = hex.replace('#','');
  const r = parseInt(h.length===3 ? h[0]+h[0] : h.slice(0,2),16);
  const g = parseInt(h.length===3 ? h[1]+h[1] : h.slice(2,4),16);
  const b = parseInt(h.length===3 ? h[2]+h[2] : h.slice(4,6),16);
  return `rgba(${r},${g},${b},${alpha})`;
}

function openPreviewModal(id) {
  // Handle both string and numeric IDs from DB
  const inv = STATE.invoices.find(i=>String(i.id)===String(id));
  if (!inv) return;
  STATE.activeMenuInvoiceId = id;
  const c = STATE.clients.find(x=>x.id===inv.client) || {};
  const sc = STATE.settings;
  // Build data object directly from invoice — no form manipulation needed
  const d = {
    tpl: inv.template || inv.template_id || STATE.settings.activeTemplate || '2',
    clientId: String(inv.client || inv.client_id || ''),
    num: inv.num || inv.invoice_number,
    date: inv.issued,
    due: inv.due,
    svc: inv.service,
    cname: c.name || inv.clientName || '',
    cperson: c.person || '',
    cemail: c.email || '',
    cwa: c.wa || '',
    cgst: c.gst || '',
    caddr: c.addr || '',
    disc: inv.disc || inv.discount_pct || 0,
    discType: inv.discount_type || (inv.discount_amt > 0 && !(inv.disc > 0) ? 'fixed' : 'percent'),
    discAmt: parseFloat(inv.discount_amt) > 0 ? parseFloat(inv.discount_amt) : (inv.subtotal ? inv.subtotal * (parseFloat(inv.disc||inv.discount_pct)||0) / 100 : 0),
    notes: (inv.notes||'').replace(/\s*\|?\s*Partial payment received\..*$/i,'').trim(),
    bank: inv.bank || inv.bank_details || STATE.settings.defaultBank || '',
    tnc: inv.tnc || inv.terms || STATE.settings.defaultTnC || '',
    status: inv.status,
    sym: inv.currency || '₹',
    sub: inv.subtotal || inv.amount,
    gstAmt: 0,
    grand: inv.amount,
    companyLogo: STATE.settings.logo || sc.logo || '',
    clientLogo: '',
    signature: sc.signature || STATE.settings.signature || '',
    qrUrl: inv.qr_code || '',
    invId: String(inv.id || ''),
    popt: (function(){ var saved=inv.pdf_options||inv.popt||null; if(saved&&typeof saved==='string'){try{saved=JSON.parse(saved);}catch(e){saved=null;}} return Object.assign({bank:true,qr:!!(inv.qr_code),sign:!!(sc.signature||STATE.settings.signature),logo:true,clientLogo:false,notes:true,tnc:true,gstCol:true,footer:true,watermark:true},saved||{}); })(),
    generatedBy: inv.generated_by || STATE.settings.generatedBy || (STATE.settings.company ? STATE.settings.company + ' Invoice Manager' : 'Invoice Manager'),
    showGeneratedBy: true
  };
  // Recalculate totals from items if available
  if (inv.items && inv.items.length) {
    let sub=0, gstAmt=0;
    inv.items.forEach(it => { const line=((it.qty||it.quantity)||1)*(it.rate||0); sub+=line; gstAmt+=line*((it.gstRate!==undefined?parseFloat(it.gstRate):it.gst!==undefined&&it.gst!==null&&it.gst!==''?parseFloat(it.gst):it.gstRate!==undefined&&it.gstRate!==''?parseFloat(it.gstRate):18)/100); });
    const disc=parseFloat(inv.disc||inv.discount_pct)||0;
    const discAmt=parseFloat(inv.discount_amt)>0?parseFloat(inv.discount_amt):(d.discType==='fixed'?Math.min(disc,sub):sub*disc/100);
    const discF=sub>0?(1-discAmt/sub):1;
    d.sub=sub; d.discAmt=discAmt; d.gstAmt=gstAmt*discF; d.grand=sub-discAmt+gstAmt*discF;
  }
  // Build items HTML
  const invItems = (inv.items||[]);
  const previewItemsHTML = invItems.length
    ? invItems.map((i, idx) => {
        const qty  = parseFloat(i.qty||i.quantity||1);
        const rate = parseFloat(i.rate||0);
        const gstR = (i.gst!==undefined&&i.gst!==null&&i.gst!==''?parseFloat(i.gst):i.gstRate!==undefined&&i.gstRate!==''?parseFloat(i.gstRate):i.gst_rate!==undefined&&i.gst_rate!==''?parseFloat(i.gst_rate):18);
        const desc = i.desc||i.description||'—';
        const line        = qty*rate;
        const gstAmt      = line * gstR / 100;
        const lineInclGst = line + gstAmt;
        const itype       = i.itemType||i.item_type||'Service';
        const gstBadge = gstR === 0
          ? `<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:#F1F5F9;color:#475569;border:1px solid #CBD5E1">${gstR}%</span>`
          : gstR <= 5
          ? `<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:#F0FDF4;color:#166534;border:1px solid #86EFAC">${gstR}%</span>`
          : gstR <= 12
          ? `<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:#FEF3C7;color:#92400E;border:1px solid #FDE68A">${gstR}%</span>`
          : `<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:#FEE2E2;color:#991B1B;border:1px solid #FECACA">${gstR}%</span>`;
        return `<tr>
          <td style="padding:9px 8px;border-bottom:1px solid #eee;font-size:11px;color:#111;font-family:monospace;font-weight:700">${String(idx+1).padStart(2,'0')}</td>
          <td style="padding:9px 12px;border-bottom:1px solid #eee;font-weight:700;color:#111">${desc}</td>
          <td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee"><span style="font-size:10px;font-weight:700;background:#F1F5F9;color:#475569;padding:2px 8px;border-radius:4px;border:1px solid #E2E8F0">${itype}</span></td>
          <td style="padding:9px 12px;text-align:right;border-bottom:1px solid #eee;font-family:monospace">${qty}</td>
          <td style="padding:9px 12px;text-align:right;border-bottom:1px solid #eee;font-family:monospace">${fmt_money(rate,d.sym)}</td>
          <td style="padding:9px 12px;text-align:right;border-bottom:1px solid #eee;font-family:monospace">${fmt_money(line,d.sym)}</td>
          <td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee">${gstBadge}</td>
          <td style="padding:9px 12px;text-align:right;font-weight:800;border-bottom:1px solid #eee;font-family:monospace;color:#111">${fmt_money(lineInclGst,d.sym)}</td>
        </tr>`;
      }).join('')
    : `<tr><td colspan="8" style="padding:20px;text-align:center;color:#aaa">No items</td></tr>`;
  const gstColHeader = `<th style="padding:10px 12px;text-align:center">GST%</th>`;
  const rowNumHeader = `<th style="padding:10px 8px;text-align:left;width:28px">#</th>`;
  const _tplMap = {'2':buildTpl2,'F':buildTplF}; // Only these two are ported into pdf.php — keep in sync if either changes
  const fn = _tplMap[String(d.tpl)] || buildTpl2;
  const scale = 0.72;
  const innerHtml = fn(d, sc, previewItemsHTML, gstColHeader, rowNumHeader);
  const mpBody = document.getElementById('mp-body');
  // Measure the real, unscaled content height first — a fixed one-A4-page
  // height would clip invoices with enough items/notes to run past one page.
  mpBody.innerHTML = `<div id="mp-measure" style="width:794px;position:relative">${innerHtml}</div>`;
  const naturalH = Math.max(1123, document.getElementById('mp-measure').scrollHeight);
  const scaledH = Math.round(naturalH * scale);
  const previewWrap = `<div style="width:${Math.round(794*scale)}px;height:${scaledH}px;overflow:hidden;position:relative;margin:0 auto"><div style="width:794px;transform:scale(${scale});transform-origin:top left;position:absolute;top:0;left:0">${innerHtml}</div></div>`;
  mpBody.innerHTML = previewWrap;
  const titleEl = document.getElementById('mp-title');
  titleEl.textContent = `Invoice ${inv.num} — ${c.name||''}`;
  titleEl.dataset.invId = id;
  openModal('modal-preview');
}

function openPaidModal(id) {
  STATE.activeMenuInvoiceId = String(id || STATE.activeMenuInvoiceId);

  // ── ADD THIS: reset confirm button in case previous payment left it in loading state ──
  const confirmBtn = document.getElementById('btn-confirm-paid');
  if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Payment'; }

  // Reset payment form
  document.getElementById('paid-date').value = fmt_date(new Date());
  const _now = new Date();
  document.getElementById('paid-time').value = String(_now.getHours()).padStart(2,'0') + ':' + String(_now.getMinutes()).padStart(2,'0');
  const _timeDisp = document.getElementById('paid-time-display');
  if (_timeDisp) {
    const _h12 = ((_now.getHours() % 12) || 12);
    const _ampm = _now.getHours() < 12 ? 'AM' : 'PM';
    _timeDisp.innerHTML = '<i class="fas fa-clock" style="margin-right:4px;opacity:.7"></i>' + _h12 + ':' + String(_now.getMinutes()).padStart(2,'0') + ' ' + _ampm;
  }
  document.getElementById('paid-txn').value  = '';
  document.getElementById('paid-notes').value = '';
  const sdEl = document.getElementById('paid-settle-disc'); if (sdEl) { sdEl.value = '0'; sdEl.dataset.wasApplied = ''; }
  const sdtEl = document.getElementById('paid-settle-disc-type'); if (sdtEl) sdtEl.value = 'pct';
  const sdDisp = document.getElementById('paid-settle-disc-display'); if (sdDisp) { sdDisp.style.display='none'; sdDisp.textContent=''; }
  const sdInfo = document.getElementById('paid-settle-disc-info'); if (sdInfo) { sdInfo.style.display='none'; sdInfo.textContent=''; }
  document.getElementById('paid-remaining-box').style.display = 'none';
  // Reset split payment panel — clear amounts to zero, hide panel
  const splitPanel = document.getElementById('split-payment-panel');
  if (splitPanel) splitPanel.style.display = 'none';
  document.querySelectorAll('#split-rows .split-amt').forEach(el => { el.value = ''; });
  const splitTotal = document.getElementById('split-total');
  if (splitTotal) splitTotal.textContent = '₹0.00';
  const methodSel = document.getElementById('paid-method');
  if (methodSel) methodSel.selectedIndex = 0;
  // Re-enable amount field (may have been dimmed by split mode)
  const amtFld = document.getElementById('paid-amt-field');
  if (amtFld) amtFld.style.opacity = '1';

  const inv = STATE.invoices.find(i=>String(i.id)===String(STATE.activeMenuInvoiceId));
  const c   = inv ? (STATE.clients.find(x=>String(x.id)===String(inv.client))||{}) : {};
  const amt = inv ? parseFloat(inv.amount||0) : parseFloat(getFormData().grand||0);
  const sym = inv ? (inv.currency||'₹') : '₹';

  // Calculate already paid for this invoice
  const alreadyPaid = STATE.payments
    .filter(p => p.invoice_id && String(p.invoice_id) === STATE.activeMenuInvoiceId)
    .reduce((s,p) => s + parseFloat(p.amount||0), 0);
  const remaining = Math.max(0, amt - alreadyPaid);

  // Pre-fill amount with what's still due; clear user-edited flag so discount can auto-fill
  const amtFieldEl = document.getElementById('paid-amt');
  amtFieldEl.value = (remaining > 0 ? remaining : amt).toFixed(2);
  amtFieldEl.dataset.userEdited = '';
  amtFieldEl.dataset.autoValue = amtFieldEl.value;

  // Show already-paid + remaining in summary bar
  const remRow = document.getElementById('paid-inv-remaining-row');
  const alreadyEl = document.getElementById('paid-inv-already');
  const remainingEl = document.getElementById('paid-inv-remaining');
  if (remRow) {
    if (alreadyPaid > 0.01) {
      remRow.style.display = 'flex';
      if (alreadyEl) alreadyEl.textContent = fmt_money(alreadyPaid, sym);
      if (remainingEl) remainingEl.textContent = fmt_money(remaining, sym);
    } else {
      remRow.style.display = 'none';
    }
  }

  // If already partially paid, show partial box with checkbox pre-checked
  if (alreadyPaid > 0.01 && remaining > 0.01) {
    const rb = document.getElementById('paid-remaining-box');
    if (rb) {
      rb.style.display = 'block';
      const rt = document.getElementById('paid-rem-total');
      const rr = document.getElementById('paid-rem-received');
      const rd = document.getElementById('paid-rem-due');
      if (rt) rt.textContent = fmt_money(amt, sym);
      if (rr) rr.textContent = fmt_money(alreadyPaid, sym);
      if (rd) rd.textContent = fmt_money(remaining, sym);
      // Fix: also update pct badge and progress bar on modal open
      const openPct = amt > 0 ? Math.min(100, Math.round(alreadyPaid / amt * 100)) : 0;
      const pctEl = document.getElementById('paid-rem-pct');
      const barEl = document.getElementById('paid-rem-bar');
      if (pctEl) pctEl.textContent = openPct + '%';
      if (barEl) barEl.style.width = openPct + '%';
      const cb = document.getElementById('paid-collect-remaining');
      if (cb) cb.checked = true;
    }
  }

  // Summary bar
  const numEl = document.getElementById('paid-inv-num');
  const cliEl = document.getElementById('paid-inv-client');
  const totEl = document.getElementById('paid-inv-total');
  if (numEl) numEl.textContent = inv ? (inv.num||inv.invoice_number||'') : '';
  if (cliEl) cliEl.textContent = c.name || (inv&&inv.client_name) || '';
  if (totEl) totEl.textContent = fmt_money(amt, sym);

  const hdr = document.getElementById('paid-inv-subtitle');
  if (hdr) hdr.textContent = inv&&inv.status==='Partial' ? 'Collect remaining payment' : 'Mark invoice as paid';
  openModal('modal-paid');
}