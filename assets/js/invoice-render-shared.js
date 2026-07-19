// ================================================================
//  assets/js/invoice-render-shared.js
//  Requires: common.js, shared-data.js (loaded before this file).
//
//  The invoice PDF-template rendering engine (all 5 templates:
//  Colorful Matte=2, Clean Minimal=A, Corporate Split=B, Dark
//  Header=E, Formal Letterhead=F) plus the print-window builder.
//  Genuinely shared — not duplicated per page:
//    - pages/invoices/create.php uses it for the live preview and
//      "Print" (via its own printInvoiceData(), which wraps the
//      current unsaved form's data and calls openPrintWindow() here)
//    - pages/comms/templates.php will use it for template previews
//    - pages/invoices/invoices.php's list-page preview/print modal
//      (openPreviewModal, printInvoiceById) also needs this — those
//      functions aren't wired up yet (pre-existing gap, not
//      introduced here); adding this file to that page's pageScripts
//      would be most of the fix once you're ready for it.
// ================================================================

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


// ── Shared helpers for templates ──
function tplLogoHTML(d, sc) {
  const C        = window.TPL_CUSTOM || {};
  const font      = C.font             || "'Public Sans',sans-serif";
  const tagline   = C.tagline          || '';
  const nameSize  = (C.companyNameSize  ? parseInt(C.companyNameSize) : 28) + 'px';
  const nameColor = C.companyNameColor  || '#ffffff';
  const nameWt    = C.companyNameWeight || '800';
  const _S2 = (typeof STATE !== 'undefined' ? STATE.settings : {});
  const company   = sc.company || _S2.company || '';
  const logo      = d.companyLogo || d.logo || sc.logo || _S2.logo || '';
  const showLogo  = !d.popt || d.popt.logo !== false;

  const nameDiv = `<div style="font-size:${nameSize};font-weight:${nameWt};color:${nameColor};letter-spacing:-0.5px;font-family:${font};line-height:1.1;margin-top:6px">${company}</div>`;
  const tagDiv  = tagline ? `<div style="font-size:11px;opacity:.65;margin-top:3px;font-family:${font};color:${nameColor}">${tagline}</div>` : '';

  if (showLogo && logo) {
    return `<div>
      <img src="${logo}" style="height:52px;max-width:200px;object-fit:contain;display:block;border-radius:12px;border:2px solid rgba(0,0,0,0.12);padding:4px;background:#fff" onerror="this.style.display='none'">
      ${tagDiv}
    </div>`;
  }
  return `<div>${nameDiv}${tagDiv}</div>`;
}
function tplClientLogoHTML(d) {
  if (!d.popt || !d.popt.clientLogo || !d.clientLogo) return '';
  return `<img src="${d.clientLogo}" style="height:36px;max-width:120px;object-fit:contain;display:block;margin-bottom:6px" onerror="this.style.display='none'">`;
}
// Full company info block for PDF header (used by all templates)
function tplCompanyInfoHTML(sc, textColor='rgba(255,255,255,.65)', smallColor='rgba(255,255,255,.45)') {
  const _S3 = (typeof STATE !== 'undefined' ? STATE.settings : {});
  const co = sc.company||_S3.company||'';
  const ph = sc.phone||_S3.phone||'';
  const em = sc.email||_S3.email||'';
  const ws = sc.website||_S3.website||'';
  const gst = sc.gst||_S3.gst||'';
  const addr = sc.address||_S3.address||'';
  // Company name is rendered by tplLogoHTML — only show contact/address info here
  return ''
       + (ph?`<div style="color:${smallColor};font-size:10px;margin-top:3px">📞 ${ph}</div>`:'')
       + (em?`<div style="color:${smallColor};font-size:10px;margin-top:2px">✉ ${em}</div>`:'')
       + (ws?`<div style="color:${smallColor};font-size:10px;margin-top:2px">${ws}</div>`:'')
       + (gst?`<div style="color:${smallColor};font-size:10px;margin-top:2px">GST: ${gst}</div>`:'')
       + (addr?`<div style="color:${smallColor};font-size:10px;margin-top:3px;line-height:1.5;max-width:200px">${addr.replace(/\n/g,'<br>')}</div>`:'');
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
function tplTncHTML(d, color='#888') {
  if (!d.popt || !d.popt.tnc) return '';
  const tnc = (d.tnc || '').trim();
  if (!tnc) return '';
  const tncHtml = tnc.replace(/\n/g, '<br>');
  return `<div style="margin-top:12px;border-top:1px solid #eee;padding-top:10px;width:100%"><div style="font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#aaa;margin-bottom:5px">Terms &amp; Conditions</div><div style="font-size:10.5px;color:${color};line-height:1.7">${tncHtml}</div></div>`;
}

function footerBar(d, sc, bg='#1A2332', col='rgba(255,255,255,.4)') {
  if (d.popt && d.popt.footer===false) return '';
  const txt = (window.TPL_CUSTOM&&TPL_CUSTOM.footerText) ? TPL_CUSTOM.footerText
            : (d.showGeneratedBy!==false && d.generatedBy) ? d.generatedBy : (STATE.settings.company ? STATE.settings.company + ' Invoice Manager' : 'Invoice Manager');
  const bgColor = (window.TPL_CUSTOM&&TPL_CUSTOM.color1) ? TPL_CUSTOM.color1 : bg;
  const font    = (window.TPL_CUSTOM&&TPL_CUSTOM.font)   ? TPL_CUSTOM.font   : 'inherit';
  const phone   = sc.phone||STATE.settings.phone||'';
  const email   = sc.email||STATE.settings.email||'';
  return `<div style="background:${bgColor};padding:12px 40px;display:flex;justify-content:space-between;align-items:center;font-family:${font}">
    <span style="color:${col};font-size:10px">${txt}</span>
    <span style="color:${col};font-size:10px">${phone}${phone&&email?' · ':''}${email}</span>
  </div>`;
}

function statusColor(s) {
  return { Paid:'#388E3C', Pending:'#F57F17', Overdue:'#C62828', Draft:'#757575', Partial:'#E65100', Cancelled:'#B71C1C', Estimate:'#3949AB' }[s] || '#757575';
}

// ── Helper: resolve company settings (merge STATE if sc is sparse) ──
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

// ── TEMPLATE 2: Colorful Matte — 8 themes ──
// Theme is picked from TPL_CUSTOM.colorTheme (1–8) or defaults to 1 (Indigo)
const _MATTE_THEMES = {
  1:{ name:'Indigo',    hbg:'#2D3A8C', htext:'#fff', htag:'#A5B4FC', hnum:'#fff', metabg:'#EEF2FF', metabr:'#C7D2FE', metalbl:'#4338CA', metaval:'#1E1B4B', billbg:'#EEF2FF', billbr:'#C7D2FE', billlbl:'#4338CA', issbg:'#F0F4FF', issbr:'#C7D2FE', isslbl:'#3730A3', thbg:'#2D3A8C', thtext:'#fff', notesbg:'#EEF2FF', notesbr:'#C7D2FE', noteslbl:'#4338CA', totbg:'#EEF2FF', totbr:'#C7D2FE', totlbl:'#4338CA', totval:'#1E1B4B', grandbg:'#2D3A8C', grandtext:'#fff', footbg:'#2D3A8C', foottext:'rgba(165,180,252,.8)', pillpaid:'#166534|#DCFCE7', pillpending:'#92400E|#FEF3C7', pilloverdue:'#991B1B|#FEE2E2', pilldraft:'#3730A3|#EEF2FF', band:'#2D3A8C,#6366F1,#818CF8' },
  2:{ name:'Emerald',   hbg:'#065F46', htext:'#fff', htag:'#6EE7B7', hnum:'#fff', metabg:'#ECFDF5', metabr:'#A7F3D0', metalbl:'#059669', metaval:'#064E3B', billbg:'#ECFDF5', billbr:'#A7F3D0', billlbl:'#059669', issbg:'#F0FDF4', issbr:'#A7F3D0', isslbl:'#047857', thbg:'#065F46', thtext:'#fff', notesbg:'#ECFDF5', notesbr:'#A7F3D0', noteslbl:'#059669', totbg:'#ECFDF5', totbr:'#A7F3D0', totlbl:'#059669', totval:'#064E3B', grandbg:'#065F46', grandtext:'#fff', footbg:'#065F46', foottext:'rgba(110,231,183,.8)', pillpaid:'#166534|#DCFCE7', pillpending:'#92400E|#FEF3C7', pilloverdue:'#991B1B|#FEE2E2', pilldraft:'#065F46|#ECFDF5', band:'#065F46,#059669,#34D399' },
  3:{ name:'Rose',      hbg:'#881337', htext:'#fff', htag:'#FDA4AF', hnum:'#fff', metabg:'#FFF1F2', metabr:'#FECDD3', metalbl:'#BE185D', metaval:'#4C0519', billbg:'#FFF1F2', billbr:'#FECDD3', billlbl:'#BE185D', issbg:'#FFF0F3', issbr:'#FECDD3', isslbl:'#9F1239', thbg:'#881337', thtext:'#fff', notesbg:'#FFF1F2', notesbr:'#FECDD3', noteslbl:'#BE185D', totbg:'#FFF1F2', totbr:'#FECDD3', totlbl:'#BE185D', totval:'#4C0519', grandbg:'#881337', grandtext:'#fff', footbg:'#881337', foottext:'rgba(253,164,175,.8)', pillpaid:'#166534|#DCFCE7', pillpending:'#92400E|#FEF3C7', pilloverdue:'#991B1B|#FEE2E2', pilldraft:'#881337|#FFF1F2', band:'#881337,#E11D48,#FDA4AF' },
  4:{ name:'Amber',     hbg:'#78350F', htext:'#fff', htag:'#FCD34D', hnum:'#fff', metabg:'#FFFBEB', metabr:'#FDE68A', metalbl:'#B45309', metaval:'#451A03', billbg:'#FFFBEB', billbr:'#FDE68A', billlbl:'#B45309', issbg:'#FFFDF5', issbr:'#FDE68A', isslbl:'#92400E', thbg:'#78350F', thtext:'#fff', notesbg:'#FFFBEB', notesbr:'#FDE68A', noteslbl:'#B45309', totbg:'#FFFBEB', totbr:'#FDE68A', totlbl:'#B45309', totval:'#451A03', grandbg:'#78350F', grandtext:'#fff', footbg:'#78350F', foottext:'rgba(252,211,77,.8)', pillpaid:'#166534|#DCFCE7', pillpending:'#92400E|#FEF3C7', pilloverdue:'#991B1B|#FEE2E2', pilldraft:'#78350F|#FFFBEB', band:'#78350F,#D97706,#FCD34D' },
  5:{ name:'Ocean',     hbg:'#0C4A6E', htext:'#fff', htag:'#7DD3FC', hnum:'#fff', metabg:'#F0F9FF', metabr:'#BAE6FD', metalbl:'#0369A1', metaval:'#0C4A6E', billbg:'#F0F9FF', billbr:'#BAE6FD', billlbl:'#0369A1', issbg:'#E0F2FE', issbr:'#BAE6FD', isslbl:'#075985', thbg:'#0C4A6E', thtext:'#fff', notesbg:'#F0F9FF', notesbr:'#BAE6FD', noteslbl:'#0369A1', totbg:'#F0F9FF', totbr:'#BAE6FD', totlbl:'#0369A1', totval:'#0C4A6E', grandbg:'#0C4A6E', grandtext:'#fff', footbg:'#0C4A6E', foottext:'rgba(125,211,252,.8)', pillpaid:'#166534|#DCFCE7', pillpending:'#92400E|#FEF3C7', pilloverdue:'#991B1B|#FEE2E2', pilldraft:'#0C4A6E|#F0F9FF', band:'#0C4A6E,#0369A1,#38BDF8' },
  6:{ name:'Violet',    hbg:'#4C1D95', htext:'#fff', htag:'#C4B5FD', hnum:'#fff', metabg:'#F5F3FF', metabr:'#DDD6FE', metalbl:'#7C3AED', metaval:'#2E1065', billbg:'#F5F3FF', billbr:'#DDD6FE', billlbl:'#7C3AED', issbg:'#EDE9FE', issbr:'#DDD6FE', isslbl:'#6D28D9', thbg:'#4C1D95', thtext:'#fff', notesbg:'#F5F3FF', notesbr:'#DDD6FE', noteslbl:'#7C3AED', totbg:'#F5F3FF', totbr:'#DDD6FE', totlbl:'#7C3AED', totval:'#2E1065', grandbg:'#4C1D95', grandtext:'#fff', footbg:'#4C1D95', foottext:'rgba(196,181,253,.8)', pillpaid:'#166534|#DCFCE7', pillpending:'#92400E|#FEF3C7', pilloverdue:'#991B1B|#FEE2E2', pilldraft:'#4C1D95|#F5F3FF', band:'#4C1D95,#7C3AED,#A78BFA' },
  7:{ name:'Slate',     hbg:'#1E293B', htext:'#fff', htag:'#94A3B8', hnum:'#fff', metabg:'#F1F5F9', metabr:'#CBD5E1', metalbl:'#475569', metaval:'#0F172A', billbg:'#F1F5F9', billbr:'#CBD5E1', billlbl:'#475569', issbg:'#E2E8F0', issbr:'#CBD5E1', isslbl:'#334155', thbg:'#1E293B', thtext:'#fff', notesbg:'#F1F5F9', notesbr:'#CBD5E1', noteslbl:'#475569', totbg:'#F1F5F9', totbr:'#CBD5E1', totlbl:'#475569', totval:'#0F172A', grandbg:'#1E293B', grandtext:'#fff', footbg:'#1E293B', foottext:'rgba(148,163,184,.8)', pillpaid:'#166534|#DCFCE7', pillpending:'#92400E|#FEF3C7', pilloverdue:'#991B1B|#FEE2E2', pilldraft:'#1E293B|#F1F5F9', band:'#1E293B,#334155,#64748B' },
  8:{ name:'Crimson',   hbg:'#7F1D1D', htext:'#fff', htag:'#FCA5A5', hnum:'#fff', metabg:'#FEF2F2', metabr:'#FECACA', metalbl:'#DC2626', metaval:'#450A0A', billbg:'#FEF2F2', billbr:'#FECACA', billlbl:'#DC2626', issbg:'#FFF5F5', issbr:'#FECACA', isslbl:'#B91C1C', thbg:'#7F1D1D', thtext:'#fff', notesbg:'#FEF2F2', notesbr:'#FECACA', noteslbl:'#DC2626', totbg:'#FEF2F2', totbr:'#FECACA', totlbl:'#DC2626', totval:'#450A0A', grandbg:'#7F1D1D', grandtext:'#fff', footbg:'#7F1D1D', foottext:'rgba(252,165,165,.8)', pillpaid:'#166534|#DCFCE7', pillpending:'#92400E|#FEF3C7', pilloverdue:'#991B1B|#FEE2E2', pilldraft:'#7F1D1D|#FEF2F2', band:'#7F1D1D,#DC2626,#FCA5A5' }
};


// ── Shared totals block for Templates A, B, E ───────────────────────────────
function totalsRows(d, accentColor, borderColor) {
  const sym       = d.sym || '₹';
  const accent    = accentColor || '#1E293B';
  const border    = borderColor || '#E2E8F0';
  const sub       = d.sub       || 0;
  const discAmt   = d.discAmt   || 0;
  const gstAmt    = d.gstAmt    || 0;
  const grand     = d.grand     || 0;
  const discType  = d.discType  || 'percent';
  const disc      = d.disc      || 0;
  const afterDisc = sub - discAmt;
  const rowStyle  = `display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:11px;color:#64748B;border-bottom:0.5px solid ${border}`;
  const valStyle  = `font-family:monospace;font-weight:600;color:#0F172A`;
  const grandStyle= `display:flex;justify-content:space-between;align-items:center;padding:9px 12px;margin-top:6px;border-radius:6px;background:${accent}`;

  // ── Smart text color: detect if accent is light or dark ──────────────────
  const hexClean = accent.replace('#','');
  let txtColor = '#fff';
  if (/^[0-9a-fA-F]{6}$/.test(hexClean)) {
    const r = parseInt(hexClean.slice(0,2),16);
    const g = parseInt(hexClean.slice(2,4),16);
    const b = parseInt(hexClean.slice(4,6),16);
    const luminance = (0.299*r + 0.587*g + 0.114*b) / 255;
    txtColor = luminance > 0.55 ? '#0F172A' : '#fff';
  }

  // ── Paid date from payments ───────────────────────────────────────────────
  const isPaid    = d.status === 'Paid';
  const isPartial = d.status === 'Partial';
  let paidDateStr = '';
  let totalPaid   = 0;
  if ((isPaid || isPartial) && typeof STATE !== 'undefined') {
    const invIdStr = d.invId ? String(d.invId) : '';
    const pmts = (STATE.payments || [])
      .filter(p => p.invoice_id && String(p.invoice_id) === invIdStr)
      .sort((a,b) => new Date(a.date||a.payment_date||0) - new Date(b.date||b.payment_date||0));
    if (pmts.length) {
      totalPaid = pmts.reduce((s,p) => s + parseFloat(p.amount||0), 0);
      const lastPmt = pmts[pmts.length-1];
      const dt = lastPmt.date || lastPmt.payment_date || '';
      paidDateStr = dt ? new Date(dt).toLocaleDateString(_moneyLocale(),{day:'2-digit',month:'short',year:'numeric'}) : '';
    }
  }

  return `<div style="padding:14px;background:#F8FAFC;border:0.5px solid ${border};border-radius:8px">
    <div style="${rowStyle}"><span>Subtotal</span><span style="${valStyle}">${fmt_money(sub,sym)}</span></div>
    ${discAmt>0?`
    <div style="${rowStyle}"><span>Discount${discType==='fixed'?' (fixed)':disc>0?' ('+Math.round(disc*100)/100+'%)':''}</span><span style="font-family:monospace;font-weight:600;color:#DC2626">−${fmt_money(discAmt,sym)}</span></div>
    <div style="${rowStyle}"><span>After Discount</span><span style="${valStyle}">${fmt_money(afterDisc,sym)}</span></div>`:''}
    ${gstAmt>0?`<div style="${rowStyle}"><span>GST</span><span style="${valStyle}">+${fmt_money(gstAmt,sym)}</span></div>`:''}
    <div style="${grandStyle}">
      <span style="font-size:12px;font-weight:700;color:${txtColor}">Total Due</span>
      <span style="font-family:monospace;font-weight:800;font-size:15px;color:${txtColor}">${fmt_money(grand,sym)}</span>
    </div>
    ${isPaid && paidDateStr?`<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:10px;color:#166534;border-top:0.5px solid ${border};margin-top:4px"><span>✓ Paid on ${paidDateStr}</span><span style="font-family:monospace;font-weight:700">${fmt_money(totalPaid,sym)}</span></div>`:''}
    ${isPartial && paidDateStr?`<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:10px;color:#92400E;border-top:0.5px solid ${border};margin-top:4px"><span>⚡ Part paid on ${paidDateStr}</span><span style="font-family:monospace;font-weight:700">${fmt_money(totalPaid,sym)}</span></div>`:''}
  </div>`;
}

// ── Previous Due Block — other outstanding invoices for same client ──────────
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

// ── TEMPLATE A: Clean Minimal ────────────────────────────────────────────────
function buildTplA(d, sc, itemsHTML, gstColHeader, rowNumHeader='') {
  d.popt = d.popt || {};
  sc = resolveCompany(sc);
  const sym = d.sym || '₹';
  const accent = (window.TPL_CUSTOM && TPL_CUSTOM.color1) ? TPL_CUSTOM.color1 : '#1E293B';
  const font   = (window.TPL_CUSTOM && TPL_CUSTOM.font)   ? TPL_CUSTOM.font   : "'Public Sans',sans-serif";

  const statusColors = { Paid:'#166534|#DCFCE7', Pending:'#92400E|#FEF3C7', Overdue:'#991B1B|#FEE2E2', Draft:'#374151|#F3F4F6', Partial:'#92400E|#FFF7ED', Cancelled:'#6B7280|#F9FAFB', Estimate:'#1E40AF|#DBEAFE' };
  const [stxt, sbg] = (statusColors[d.status] || '#374151|#F3F4F6').split('|');

  const thS = `padding:8px 0;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:${accent};border-bottom:1.5px solid ${accent}`;
  const tdS = `padding:9px 0;font-size:11px;color:#444;border-bottom:0.5px solid #F1F5F9`;
  const tdR = `${tdS};text-align:right;font-family:monospace;font-weight:600;color:#1E293B`;

  return `<div style="font-family:${font};background:#fff;width:794px;min-height:1123px;position:relative;overflow:hidden">
  ${tplWatermark(d)}
  <div style="border-left:5px solid ${accent};padding:32px 40px 24px 36px;display:flex;justify-content:space-between;align-items:flex-start">
    <div>
      ${sc.logo?`<img src="${sc.logo}" style="height:56px;max-width:200px;object-fit:contain;display:block;margin-bottom:10px" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">`:''}
      <div style="font-size:22px;font-weight:700;color:${accent};letter-spacing:-.5px;line-height:1;${sc.logo?'display:none':''}">${sc.company}</div>
      <div style="margin-top:8px;font-size:10px;color:#94A3B8;line-height:2">
        ${sc.gst?`<span>GSTIN: ${sc.gst}</span><br>`:''}
        ${sc.phone?`<span>${sc.phone}</span>${sc.email?' &nbsp;·&nbsp; ':''}`:''}
        ${sc.email?`<span>${sc.email}</span>`:''}
        ${sc.address?`<br><span>${sc.address.replace(/\n/g,', ')}</span>`:''}
      </div>
    </div>
    <div style="text-align:right">
      <div style="font-size:28px;font-weight:300;color:${accent};letter-spacing:-1.5px;line-height:1">${d.status==='Estimate'?'ESTIMATE':'INVOICE'}</div>
      <div style="font-size:13px;font-weight:700;color:${accent};margin-top:4px;font-family:monospace">#${d.num}</div>
      <span style="display:inline-block;margin-top:8px;padding:3px 12px;font-size:9px;font-weight:700;letter-spacing:1px;text-transform:uppercase;border-radius:20px;background:${sbg};color:${stxt}">${d.status.toUpperCase()}</span>
    </div>
  </div>

  <div style="display:flex;gap:0;background:#F8FAFC;border-top:0.5px solid #E2E8F0;border-bottom:0.5px solid #E2E8F0;padding:16px 40px">
    <div style="flex:1.5">
      <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94A3B8;margin-bottom:5px">Billed To</div>
      ${tplClientLogoHTML(d)}
      <div style="font-size:13px;font-weight:700;color:#0F172A">${d.cname}</div>
      ${d.cperson?`<div style="font-size:10px;color:#64748B;margin-top:1px">${d.cperson}</div>`:''}
      ${d.cemail?`<div style="font-size:10px;color:#64748B">${d.cemail}</div>`:''}
      ${d.cwa?`<div style="font-size:10px;color:#64748B">${d.cwa}</div>`:''}
      ${d.caddr?`<div style="font-size:10px;color:#64748B;margin-top:3px">${d.caddr.replace(/\n/g,'<br>')}</div>`:''}
      ${d.cgst?`<div style="font-size:10px;color:#64748B;font-weight:600;margin-top:3px">GSTIN: ${d.cgst}</div>`:''}
    </div>
    <div style="display:flex;gap:32px;align-items:flex-start">
      <div>
        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94A3B8;margin-bottom:5px">Issue Date</div>
        <div style="font-size:12px;font-weight:600;color:#0F172A">${d.date||'—'}</div>
      </div>
      <div>
        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94A3B8;margin-bottom:5px">Due Date</div>
        <div style="font-size:12px;font-weight:600;color:#0F172A">${d.due||'—'}</div>
      </div>
      <div style="text-align:right">
        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94A3B8;margin-bottom:5px">Amount Due</div>
        <div style="font-size:18px;font-weight:700;color:${accent};font-family:monospace">${fmt_money(d.grand,sym)}</div>
      </div>
    </div>
  </div>

  <div style="padding:24px 40px">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr>
        <th style="${thS};width:28px">#</th>
        <th style="${thS}">Description</th>
        <th style="${thS};text-align:center">Type</th>
        <th style="${thS};text-align:right">Qty</th>
        <th style="${thS};text-align:right">Rate</th>
        <th style="${thS};text-align:right">Line</th>
        ${gstColHeader?`<th style="${thS};text-align:center">GST%</th>`:''}
        <th style="${thS};text-align:right">Amount</th>
      </tr></thead>
      <tbody>${itemsHTML.replace(/border-bottom:1px solid #eee/g,'border-bottom:0.5px solid #F1F5F9').replace(/padding:9px 8px/g,'padding:9px 12px')}</tbody>
    </table>
  </div>

  <div style="display:flex;padding:0 40px 36px;gap:24px">
    <div style="flex:1">
      ${tplBankHTML(d,'#475569','#F8FAFC','border:0.5px solid #E2E8F0;border-radius:8px')}
      ${tplNotesHTML(d,'#475569','#F8FAFC')}
      ${tplTncHTML(d,'#94A3B8')}
      ${paymentReceivedBlock(d,'#BBF7D0','#F0FFF4','#166534')}
    </div>
    <div style="width:220px">
      ${totalsRows(d,accent,'#E2E8F0')}
      ${previousDueBlock(d,'#92400E','#FFFBEB','#FCD34D')}
      ${tplSignHTML(d)}
    </div>
  </div>

  <div style="border-top:0.5px solid #E2E8F0;padding:12px 40px;display:flex;justify-content:space-between;align-items:center">
    <span style="font-size:10px;color:#CBD5E1">${d.generatedBy||sc.company||''}</span>
    <span style="font-size:10px;color:#CBD5E1">${sc.website||sc.email||''}</span>
  </div>
  <div style="height:4px;background:${accent}"></div>
</div>`;
}

// ── TEMPLATE B: Corporate Split ──────────────────────────────────────────────
function buildTplB(d, sc, itemsHTML, gstColHeader, rowNumHeader='') {
  d.popt = d.popt || {};
  sc = resolveCompany(sc);
  const sym = d.sym || '₹';
  const primary = (window.TPL_CUSTOM && TPL_CUSTOM.color1) ? TPL_CUSTOM.color1 : '#1565C0';
  const font     = (window.TPL_CUSTOM && TPL_CUSTOM.font)   ? TPL_CUSTOM.font   : "'Public Sans',sans-serif";

  // Derive light tint from primary color dynamically
  const _hex = primary.replace('#','');
  const _r = parseInt(_hex.slice(0,2),16)||21;
  const _g = parseInt(_hex.slice(2,4),16)||101;
  const _b = parseInt(_hex.slice(4,6),16)||192;
  const lightBg  = `rgba(${_r},${_g},${_b},0.06)`;
  const lightBdr = `rgba(${_r},${_g},${_b},0.18)`;

  const statusColors = { Paid:'#166534|#DCFCE7', Pending:'#92400E|#FEF3C7', Overdue:'#991B1B|#FEE2E2', Draft:'#374151|#F3F4F6', Partial:'#92400E|#FFF7ED', Cancelled:'#6B7280|#F9FAFB', Estimate:'#1E40AF|#DBEAFE' };
  const [stxt, sbg] = (statusColors[d.status] || '#374151|#F3F4F6').split('|');

  const thS = `padding:9px 12px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#fff;text-align:left;background:${primary}`;
  const thR = `${thS};text-align:right`;

  return `<div style="font-family:${font};background:#fff;width:794px;min-height:1123px;position:relative;overflow:hidden">
  ${tplWatermark(d)}

  <div style="display:flex;min-height:130px">
    <div style="width:44%;background:${primary};padding:28px 28px;display:flex;flex-direction:column;justify-content:center">
      ${sc.logo?`<img src="${sc.logo}" style="height:56px;max-width:180px;object-fit:contain;display:block;margin-bottom:10px;filter:brightness(0) invert(1)" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">`:''}
      <div style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-.5px;line-height:1;${sc.logo?'display:none':''}">${sc.company}</div>
      <div style="margin-top:6px;font-size:10px;color:rgba(255,255,255,0.6);line-height:2">
        ${sc.gst?`<div>GSTIN: ${sc.gst}</div>`:''}
        ${sc.phone?`<div>${sc.phone}</div>`:''}
        ${sc.email?`<div>${sc.email}</div>`:''}
        ${sc.address?`<div>${sc.address.replace(/\n/g,', ')}</div>`:''}
      </div>
    </div>
    <div style="flex:1;padding:28px 32px;display:flex;flex-direction:column;justify-content:center;align-items:flex-end">
      <div style="font-size:32px;font-weight:800;color:${primary};letter-spacing:-2px;line-height:1">${d.status==='Estimate'?'ESTIMATE':'INVOICE'}</div>
      <div style="font-size:14px;font-weight:700;color:${primary};font-family:monospace;margin-top:4px">#${d.num}</div>
      <span style="display:inline-block;margin-top:8px;padding:4px 14px;font-size:9px;font-weight:800;letter-spacing:1px;text-transform:uppercase;border-radius:4px;background:${sbg};color:${stxt}">${d.status.toUpperCase()}</span>
    </div>
  </div>

  <div style="display:flex;background:${lightBg};border-top:1.5px solid ${lightBdr};border-bottom:1.5px solid ${lightBdr};padding:14px 28px;gap:40px;align-items:flex-start">
    <div style="flex:1.5">
      <div style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:${primary};margin-bottom:5px">Billed To</div>
      ${tplClientLogoHTML(d)}
      <div style="font-size:13px;font-weight:800;color:#0D1B2A">${d.cname}</div>
      ${d.cperson?`<div style="font-size:10px;color:#64748B;margin-top:1px">${d.cperson}</div>`:''}
      ${d.cemail?`<div style="font-size:10px;color:#64748B">${d.cemail}</div>`:''}
      ${d.cwa?`<div style="font-size:10px;color:#64748B">${d.cwa}</div>`:''}
      ${d.caddr?`<div style="font-size:10px;color:#64748B;margin-top:3px">${d.caddr.replace(/\n/g,'<br>')}</div>`:''}
      ${d.cgst?`<div style="font-size:10px;color:#64748B;font-weight:700;margin-top:3px">GSTIN: ${d.cgst}</div>`:''}
    </div>
    <div style="display:flex;gap:28px">
      <div>
        <div style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:${primary};margin-bottom:4px">Issue Date</div>
        <div style="font-size:12px;font-weight:600;color:#0D1B2A">${d.date||'—'}</div>
      </div>
      <div>
        <div style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:${primary};margin-bottom:4px">Due Date</div>
        <div style="font-size:12px;font-weight:600;color:#0D1B2A">${d.due||'—'}</div>
      </div>
    </div>
    <div style="text-align:right">
      <div style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:${primary};margin-bottom:4px">Total Due</div>
      <div style="font-size:22px;font-weight:800;color:${primary};font-family:monospace">${fmt_money(d.grand,sym)}</div>
    </div>
  </div>

  <div style="padding:22px 28px 0">
    <table style="width:100%;border-collapse:collapse;border-radius:6px;overflow:hidden">
      <thead><tr>
        <th style="${thS};width:28px;border-radius:0">#</th>
        <th style="${thS}">Description</th>
        <th style="${thS};text-align:center">Type</th>
        <th style="${thR}">Qty</th>
        <th style="${thR}">Rate</th>
        <th style="${thR}">Line</th>
        ${gstColHeader?`<th style="${thR}">GST%</th>`:''}
        <th style="${thR}">Amount</th>
      </tr></thead>
      <tbody>${itemsHTML.replace(/border-bottom:1px solid #eee/g,`border-bottom:0.5px solid ${lightBdr}`).replace(/padding:9px 8px/g,'padding:9px 12px')}</tbody>
    </table>
  </div>

  <div style="display:flex;padding:16px 28px 36px;gap:24px">
    <div style="flex:1">
      ${tplBankHTML(d,primary,lightBg,`border:1px solid ${lightBdr};border-radius:8px`)}
      ${tplNotesHTML(d,'#475569',lightBg)}
      ${tplTncHTML(d,'#94A3B8')}
      ${paymentReceivedBlock(d,'#BBF7D0','#F0FFF4','#166534')}
    </div>
    <div style="width:230px">
      ${totalsRows(d,primary,lightBdr)}
      ${previousDueBlock(d,primary,lightBg,lightBdr)}
      ${tplSignHTML(d)}
    </div>
  </div>

  <div style="background:${primary};padding:12px 28px;display:flex;justify-content:space-between;align-items:center">
    <span style="font-size:10px;color:rgba(255,255,255,0.5)">${d.generatedBy||sc.company||''}</span>
    <span style="font-size:10px;color:rgba(255,255,255,0.5)">${sc.phone||''}${sc.phone&&sc.email?' · ':''}${sc.email||''}</span>
  </div>
</div>`;
}

// ── TEMPLATE E: Dark Header Full Width ───────────────────────────────────────
function buildTplE(d, sc, itemsHTML, gstColHeader, rowNumHeader='') {
  d.popt = d.popt || {};
  sc = resolveCompany(sc);
  const sym    = d.sym || '₹';
  const dark   = (window.TPL_CUSTOM && TPL_CUSTOM.color1) ? TPL_CUSTOM.color1 : '#0F172A';
  const accent = (window.TPL_CUSTOM && TPL_CUSTOM.color2) ? TPL_CUSTOM.color2 : '#38BDF8';
  const font   = (window.TPL_CUSTOM && TPL_CUSTOM.font)   ? TPL_CUSTOM.font   : "'Public Sans',sans-serif";
  const meta   = '#1E293B';

  const statusColors = { Paid:'#4ADE80|rgba(74,222,128,0.15)', Pending:'#FCD34D|rgba(252,211,77,0.15)', Overdue:'#F87171|rgba(248,113,113,0.15)', Draft:'#94A3B8|rgba(148,163,184,0.15)', Partial:'#FCD34D|rgba(252,211,77,0.15)', Cancelled:'#94A3B8|rgba(148,163,184,0.15)', Estimate:'#818CF8|rgba(129,140,248,0.15)' };
  const [stxt, sbg] = (statusColors[d.status]||'#94A3B8|rgba(148,163,184,0.15)').split('|');

  const thS = `padding:10px 12px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1.2px;color:#94A3B8;text-align:left;background:#F8FAFC;border-bottom:2px solid ${dark}`;
  const thR = `${thS};text-align:right`;

  return `<div style="font-family:${font};background:#fff;width:794px;min-height:1123px;position:relative;overflow:hidden">
  ${tplWatermark(d)}

  <div style="background:${dark};padding:28px 36px;display:flex;justify-content:space-between;align-items:center">
    <div>
      ${sc.logo?`<img src="${sc.logo}" style="height:56px;max-width:200px;object-fit:contain;display:block;margin-bottom:8px;filter:brightness(0) invert(1)" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">`:''}
      <div style="font-size:22px;font-weight:800;color:#fff;letter-spacing:-.5px;${sc.logo?'display:none':''}">${sc.company}</div>
      <div style="font-size:10px;color:rgba(255,255,255,0.4);margin-top:4px;line-height:2">
        ${sc.gst?`<span>GSTIN: ${sc.gst}</span><br>`:''}
        ${sc.phone?`<span>${sc.phone}</span>${sc.email?' &nbsp;·&nbsp; ':''}`:''}${sc.email?`<span>${sc.email}</span>`:''}
        ${sc.address?`<br><span>${sc.address.replace(/\n/g,', ')}</span>`:''}
      </div>
    </div>
    <div style="text-align:right">
      <div style="font-size:10px;font-weight:800;letter-spacing:3px;text-transform:uppercase;color:${accent}">${d.status==='Estimate'?'ESTIMATE':'INVOICE'}</div>
      <div style="font-size:28px;font-weight:800;color:#fff;font-family:monospace;letter-spacing:-1px;line-height:1.1;margin-top:2px">#${d.num}</div>
      <span style="display:inline-block;margin-top:6px;padding:4px 12px;font-size:9px;font-weight:800;letter-spacing:1px;text-transform:uppercase;border-radius:4px;background:${sbg};color:${stxt}">${d.status.toUpperCase()}</span>
    </div>
  </div>

  <div style="background:${meta};padding:12px 36px;display:flex;gap:0;border-bottom:2px solid ${dark}">
    <div style="flex:1.5;padding-right:24px;border-right:1px solid rgba(255,255,255,0.08)">
      <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#475569;margin-bottom:4px">Billed To</div>
      ${tplClientLogoHTML(d)}
      <div style="font-size:13px;font-weight:700;color:#E2E8F0">${d.cname}</div>
      ${d.cperson?`<div style="font-size:10px;color:#64748B">${d.cperson}</div>`:''}
      ${d.cemail?`<div style="font-size:10px;color:#64748B">${d.cemail}</div>`:''}
      ${d.cwa?`<div style="font-size:10px;color:#64748B">${d.cwa}</div>`:''}
      ${d.caddr?`<div style="font-size:10px;color:#64748B;margin-top:3px">${d.caddr.replace(/\n/g,'<br>')}</div>`:''}
      ${d.cgst?`<div style="font-size:10px;color:#64748B;font-weight:600;margin-top:3px">GSTIN: ${d.cgst}</div>`:''}
    </div>
    <div style="display:flex;gap:0;padding-left:24px">
      <div style="padding-right:24px;border-right:1px solid rgba(255,255,255,0.08)">
        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#475569;margin-bottom:4px">Issue Date</div>
        <div style="font-size:12px;font-weight:600;color:#E2E8F0">${d.date||'—'}</div>
        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#475569;margin-top:10px;margin-bottom:4px">Due Date</div>
        <div style="font-size:12px;font-weight:600;color:#E2E8F0">${d.due||'—'}</div>
      </div>
      <div style="padding-left:24px;text-align:right">
        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#475569;margin-bottom:4px">Amount Due</div>
        <div style="font-size:22px;font-weight:800;color:${accent};font-family:monospace">${fmt_money(d.grand,sym)}</div>
        ${d.svc?`<div style="font-size:10px;color:#64748B;margin-top:6px">${d.svc}</div>`:''}
      </div>
    </div>
  </div>

  <div style="padding:22px 36px 0">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr>
        <th style="${thS};width:28px">#</th>
        <th style="${thS}">Description</th>
        <th style="${thS};text-align:center">Type</th>
        <th style="${thR}">Qty</th>
        <th style="${thR}">Rate</th>
        <th style="${thR}">Line</th>
        ${gstColHeader?`<th style="${thR}">GST%</th>`:''}
        <th style="${thR}">Amount</th>
      </tr></thead>
      <tbody>${itemsHTML.replace(/border-bottom:1px solid #eee/g,'border-bottom:0.5px solid #F1F5F9').replace(/padding:9px 8px/g,'padding:10px 12px')}</tbody>
    </table>
  </div>

  <div style="display:flex;padding:16px 36px 36px;gap:24px">
    <div style="flex:1">
      ${tplBankHTML(d,'#334155','#F8FAFC','border:0.5px solid #E2E8F0;border-radius:8px')}
      ${tplNotesHTML(d,'#475569','#F8FAFC')}
      ${tplTncHTML(d,'#94A3B8')}
      ${paymentReceivedBlock(d,'rgba(56,189,248,0.3)','rgba(56,189,248,0.06)',accent)}
    </div>
    <div style="width:220px">
      ${totalsRows(d,dark,'#E2E8F0')}
      ${previousDueBlock(d,accent,'rgba(56,189,248,0.06)','rgba(56,189,248,0.3)')}
      ${tplSignHTML(d)}
    </div>
  </div>

  <div style="margin-top:24px;background:${dark};padding:14px 36px;display:flex;justify-content:space-between;align-items:center">
    <span style="font-size:10px;color:rgba(255,255,255,0.3)">${d.generatedBy||sc.company||''}</span>
    <span style="font-size:10px;font-weight:700;color:${accent}">${sc.website||sc.email||''}</span>
  </div>
</div>`;
}

// ── Payment Received Block — shown on Paid/Partial invoices ─────────────────
// ── TEMPLATE F: Formal Letterhead ────────────────────────────────────────────
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

function paymentReceivedBlock(d, borderColor='#C8E6C9', bgColor='#F1F8E9', accentColor='#2E7D32') {
  if (d.popt && d.popt.paymentBlock === false) return '';
  const invId = d.invId ? String(d.invId) : '';
  const isPaid    = d.status === 'Paid';
  const isPartial = d.status === 'Partial';
  if (!invId || (!isPaid && !isPartial)) return '';

  const pmts = (typeof STATE !== 'undefined' ? STATE.payments : [])
    .filter(p => p.invoice_id && String(p.invoice_id) === invId)
    .sort((a, b) => new Date(a.date || a.payment_date || 0) - new Date(b.date || b.payment_date || 0));

  if (!pmts.length) return '';

  const totalPaid = pmts.reduce((s, p) => s + parseFloat(p.amount || 0), 0);
  const sym = d.sym || (STATE.settings && STATE.settings.currency) || '₹';

  const rows = pmts.map((p, i) => {
    const dt  = p.date || p.payment_date || '';
    const dtF = dt ? new Date(dt).toLocaleDateString(_moneyLocale(), {day:'2-digit', month:'short', year:'numeric'}) : '—';
    const amt  = parseFloat(p.amount || 0);
    const meth = p.method || '—';
    const txn  = p.txn   || '';
    const settle = parseFloat(p.settlement_discount || 0);

    return `
      <tr style="border-bottom:1px solid ${borderColor}">
        <td style="padding:5px 10px;font-size:11px;font-weight:700;color:#1B5E20;font-family:monospace">${dtF}</td>
        <td style="padding:5px 10px;font-size:11px;color:#2E7D32;font-weight:600">${meth}</td>
        <td style="padding:5px 10px;font-size:10px;color:#558B2F;font-family:monospace">${txn || '—'}</td>
        <td style="padding:5px 10px;font-size:11px;font-weight:800;text-align:right;color:#1B5E20;font-family:monospace">${fmt_money(amt, sym)}</td>
        ${settle > 0 ? `<td style="padding:5px 10px;font-size:10px;text-align:right;color:#EF6C00;font-family:monospace">-${fmt_money(settle,sym)} disc</td>` : '<td></td>'}
      </tr>`;
  }).join('');

  const multiRow = pmts.length > 1 ? `
    <table style="width:100%;border-collapse:collapse;margin-top:6px">
      <thead>
        <tr style="background:${borderColor}">
          <th style="padding:4px 10px;font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#1B5E20;text-align:left">Date</th>
          <th style="padding:4px 10px;font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#1B5E20;text-align:left">Mode</th>
          <th style="padding:4px 10px;font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#1B5E20;text-align:left">Ref / Txn ID</th>
          <th style="padding:4px 10px;font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#1B5E20;text-align:right">Amount</th>
          <th style="padding:4px 10px;font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#1B5E20;text-align:right"></th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>` : (() => {
      const p   = pmts[0];
      const dt  = p.date || p.payment_date || '';
      const dtF = dt ? new Date(dt).toLocaleDateString(_moneyLocale(), {day:'2-digit', month:'short', year:'numeric'}) : '—';
      const meth = p.method || '—';
      const txn  = p.txn || '';
      return `
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:6px">
          <div><span style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#558B2F">Date</span>
            <div style="font-size:12px;font-weight:700;color:#1B5E20;font-family:monospace">${dtF}</div></div>
          <div><span style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#558B2F">Mode</span>
            <div style="font-size:12px;font-weight:700;color:#1B5E20">${meth}</div></div>
          ${txn ? `<div><span style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#558B2F">Ref / Txn ID</span>
            <div style="font-size:12px;font-weight:700;color:#1B5E20;font-family:monospace">${txn}</div></div>` : ''}
          <div style="margin-left:auto"><span style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#558B2F">Amount Paid</span>
            <div style="font-size:14px;font-weight:800;color:#1B5E20;font-family:monospace">${fmt_money(totalPaid, sym)}</div></div>
        </div>`;
    })();

  const partialNote = isPartial ? `
    <div style="margin-top:8px;font-size:10px;font-weight:700;color:#E65100;background:#FFF3E0;padding:4px 10px;border-radius:4px;border-left:3px solid #FF6D00">
      ⚠ Partial payment — Balance of ${fmt_money(Math.max(0,(d.grand||0) - totalPaid), sym)} still due
    </div>` : '';

  return `
  <div style="margin:12px 0 0;padding:12px 14px;background:${bgColor};border-radius:8px;border:1.5px solid ${borderColor};border-left:4px solid ${accentColor}">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
      <span style="font-size:13px">✅</span>
      <span style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:2px;color:${accentColor}">${isPaid ? 'Payment Received' : 'Partial Payment Received'}</span>
      ${pmts.length > 1 ? `<span style="font-size:9px;background:${borderColor};color:${accentColor};padding:1px 7px;border-radius:10px;font-weight:700">${pmts.length} instalments</span>` : ''}
    </div>
    ${multiRow}
    ${partialNote}
  </div>`;
}

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
