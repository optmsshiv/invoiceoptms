const TPL_CUSTOM = {
  color1: '#1A2332', color2: '#4DB6AC',
  font: "'Public Sans',sans-serif",
  headerStyle: 'gradient', tableStyle: 'dark',
  footerText: '', tagline: '', watermarkText: 'PAID',
  companyNameSize: '28', companyNameColor: '#ffffff',
  companyNameWeight: '800', companyNameStyle: 'normal',
  logoPosition: 'left',
  colorTheme: 1
};



// ============================================================
// templates.js — page-specific JS for pages/templates.php
// Depends on: common.js, shared-data.js
// PDF invoice-template color/theme customization page.
// ============================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['settings']);
});

function previewTemplate(n) {
  const panel=document.getElementById('tplPreviewPanel');
  const inner=document.getElementById('tplPreviewInner');
  const label=document.getElementById('tplPreviewLabel');
  if(!panel||!inner){return;}
  const _lblMap={'2':'Colorful Matte','A':'Clean Minimal','B':'Corporate Split','E':'Dark Header','F':'Formal Letterhead'};
  if(label) label.textContent=(_lblMap[String(n)]||'Template '+n)+' Preview';
  const sc=STATE.settings;
  const sd={tpl:n,num:'DEMO-001',date:'2025-04-10',due:'2025-04-25',svc:'Website Development',
    cname:'Sample Client Ltd',cperson:'Contact Person',cemail:'client@example.com',cwa:'+91 9876543210',
    cgst:'',caddr:'Your City, State, India',disc:0,discAmt:0,
    notes: sc.defaultNotes || (sc.company ? 'Thank you for choosing ' + sc.company + '.' : 'Thank you for your business.'),
    bank: sc.defaultBank || sc.bank || '',
    tnc: sc.defaultTnc || 'All prices inclusive of applicable taxes.',
    status:'Paid',sym: STATE.settings.currency || '₹',sub:88500,gstAmt:15930,grand:104430,invId:'',
    companyLogo:sc.logo||'',clientLogo:'',signature:'',qrUrl:'',
    popt:{bank:true,qr:false,sign:true,logo:true,clientLogo:false,notes:true,tnc:true,gstCol:true,footer:true,watermark:true}};
  const iHTML=`<tr><td style="padding:9px 12px;border-bottom:1px solid #eee">Website Development Premium</td><td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee;font-size:11px;color:#666">Service</td><td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee">1</td><td style="padding:9px 12px;text-align:right;border-bottom:1px solid #eee">₹75,000.00</td><td style="padding:9px 12px;text-align:right;border-bottom:1px solid #eee">₹75,000.00</td><td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee">18%</td><td style="padding:9px 12px;text-align:right;font-weight:700;border-bottom:1px solid #eee">₹88,500.00</td></tr><tr><td style="padding:9px 12px;border-bottom:1px solid #eee">Domain & Hosting</td><td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee;font-size:11px;color:#666">Product</td><td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee">1</td><td style="padding:9px 12px;text-align:right;border-bottom:1px solid #eee">₹4,500.00</td><td style="padding:9px 12px;text-align:right;border-bottom:1px solid #eee">₹4,500.00</td><td style="padding:9px 12px;text-align:center;border-bottom:1px solid #eee">18%</td><td style="padding:9px 12px;text-align:right;font-weight:700;border-bottom:1px solid #eee">₹5,310.00</td></tr>`;
  const gH=`<th style="padding:10px 12px;text-align:center">GST%</th>`;
  sd._rawItems=[{desc:'Website Development Premium',qty:1,rate:75000,gst:18},{desc:'Domain & Hosting',qty:1,rate:4500,gst:18}];
  const tpls={'2':buildTpl2,'F':buildTplF};
  const fn=tpls[String(n)]||buildTpl2;
  const scale=Math.min(0.78,(window.innerWidth-280)/794);
  const sh=Math.round(1123*scale);
  inner.innerHTML=`<div style="width:${Math.round(794*scale)}px;height:${sh}px;overflow:hidden;position:relative;border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.2)"><div style="width:794px;transform:scale(${scale});transform-origin:top left;position:absolute;top:0;left:0">${fn(sd,sc,iHTML,gH)}</div></div>`;
  panel.style.display='block';
  panel.scrollIntoView({behavior:'smooth',block:'start'});
  const _tplLabel = {'2':'Colorful Matte','A':'Clean Minimal','B':'Corporate Split','E':'Dark Header','F':'Formal Letterhead'};
  toast(`👁️ ${_tplLabel[String(n)] || 'Template '+n}`, 'info');
}

function setMatteTheme(id) {
  TPL_CUSTOM.colorTheme = id;
  const hidEl = document.getElementById('tpl-color-theme');
  if (hidEl) hidEl.value = id;
  // Highlight active button
  for (let i = 1; i <= 8; i++) {
    const btn = document.getElementById('mtheme-btn-' + i);
    if (!btn) continue;
    btn.style.background   = (i === id) ? '#1A2332' : '#fff';
    btn.style.color        = (i === id) ? '#fff'    : 'var(--text2)';
    btn.style.borderColor  = (i === id) ? '#1A2332' : 'var(--border)';
  }
  livePreview();
}

function setTplColor(inputId, color) {
  const colorInput = document.getElementById(inputId);
  const hexInput   = document.getElementById(inputId + '-hex');
  if (colorInput) colorInput.value = color;
  if (hexInput)   hexInput.value   = color;
  // Immediately update TPL_CUSTOM so preview reflects change
  if (inputId === 'tpl-color1') TPL_CUSTOM.color1 = color;
  if (inputId === 'tpl-color2') TPL_CUSTOM.color2 = color;
  livePreview();
}

function applyTplCustomization() {
  // Read color from hex input first (most reliably updated), fallback to color picker
  const readColor = (hexId, pickerId) => {
    const hex  = document.getElementById(hexId);
    const pick = document.getElementById(pickerId);
    const v = (hex && hex.value && hex.value.match(/^#[0-9a-fA-F]{3,6}$/)) ? hex.value : (pick ? pick.value : '');
    if (hex && v)  hex.value  = v;
    if (pick && v) pick.value = v;
    return v;
  };
  // Only read color pickers for non-Template2 templates
  const isTpl2 = String(STATE.settings.activeTemplate||'2') === '2';
  if (!isTpl2) {
    TPL_CUSTOM.color1 = readColor('tpl-color1-hex', 'tpl-color1') || TPL_CUSTOM.color1;
    TPL_CUSTOM.color2 = readColor('tpl-color2-hex', 'tpl-color2') || TPL_CUSTOM.color2;
  }
  TPL_CUSTOM.colorTheme    = parseInt(document.getElementById('tpl-color-theme')?.value||'1') || 1;
  TPL_CUSTOM.font          = document.getElementById('tpl-font')?.value          || TPL_CUSTOM.font;
  TPL_CUSTOM.logoPosition  = document.getElementById('tpl-logo-pos')?.value      || TPL_CUSTOM.logoPosition;
  TPL_CUSTOM.watermarkText = document.getElementById('tpl-watermark-text')?.value|| 'PAID';
  // Preview
  const n = STATE.settings.activeTemplate || 2;
  previewTemplate(n);
  if (document.getElementById('invoicePreviewWrap')) livePreview();
  toast('✅ Applied! Click Save to persist.', 'success');
}

function resetTplCustomization() {
  TPL_CUSTOM.color1        = '#1A2332';
  TPL_CUSTOM.color2        = '#4DB6AC';
  TPL_CUSTOM.font          = "'Public Sans',sans-serif";
  TPL_CUSTOM.logoPosition  = 'left';
  TPL_CUSTOM.watermarkText = 'PAID';
  TPL_CUSTOM.colorTheme    = 1;
  setTplColor('tpl-color1', '#1A2332');
  setTplColor('tpl-color2', '#4DB6AC');
  const tplFont = document.getElementById('tpl-font');
  if (tplFont) tplFont.value = "'Public Sans',sans-serif";
  const logoPosEl = document.getElementById('tpl-logo-pos');
  if (logoPosEl) logoPosEl.value = 'left';
  const wmEl = document.getElementById('tpl-watermark-text');
  if (wmEl) wmEl.value = 'PAID';
  setMatteTheme(1);
  toast('↩️ Reset to defaults', 'info');
  if (document.getElementById('invoicePreviewWrap')) livePreview();
  previewTemplate(STATE.settings.activeTemplate||'2');
}

async function saveTplCustomization() {
  applyTplCustomization();
  const payload = {
    tpl_color1:        TPL_CUSTOM.color1,
    tpl_color2:        TPL_CUSTOM.color2,
    tpl_font:          TPL_CUSTOM.font,
    tpl_logo_position: TPL_CUSTOM.logoPosition,
    tpl_watermark_text:TPL_CUSTOM.watermarkText,
    tpl_color_theme:   TPL_CUSTOM.colorTheme,
    active_template:   String(STATE.settings.activeTemplate || 2),
  };
  try {
    await api('/api/settings.php', 'POST', payload);
    const badge = document.getElementById('tpl-unsaved-badge');
    if (badge) badge.style.display = 'none';
    toast('✅ Template customization saved!', 'success');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}