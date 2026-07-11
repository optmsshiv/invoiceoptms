// ================================================================
//  assets/js/templates.js
//  Requires: common.js, shared-data.js, invoice-render-shared.js
//  (loaded before this file — buildTpl2/buildTplF for the preview).
//  For pages/comms/templates.php.
//
//  MPA FIX: several functions here (setActiveTemplate, setMatteTheme,
//  setTplColor) called livePreview() directly — create.php's own
//  function, tied to the invoice form (#invoicePreviewWrap). That
//  element doesn't exist on this page, so those calls are now
//  guarded the same way the original code already guarded some (not
//  all) of its own livePreview() calls elsewhere — checking the
//  element exists first, which safely no-ops here and still works
//  correctly if this file is ever loaded alongside create.js.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['settings']);
  renderTemplatesGrid();
  populateTemplateForm();
  syncThemePicker();
});

function renderTemplatesGrid() {
  const grid = document.getElementById('templatesGrid');
  if (!grid) return;
  const active = STATE.settings.activeTemplate || '2';
  const templates = [
    { id:'2', name:'Colorful Matte',     desc:'Navy logo panel · status accent bar · 8 built-in color themes', color:'#6366F1', accent:'#A5B4FC' },
    { id:'F', name:'Formal Letterhead',  desc:'Serif · black & white · ruled, print-ready layout',             color:'#1a1a1a', accent:'#888888' },
  ];
  grid.innerHTML = templates.map(t => {
    const isActive = String(active) === String(t.id);
    return `<div class="tpl-card ${isActive?'active-tpl':''}" id="tpl-card-${t.id}">
      <div class="tpl-thumb" style="background:${t.color}">
        <div style="width:120px;background:rgba(255,255,255,.95);border-radius:4px;padding:10px;box-shadow:0 2px 8px rgba(0,0,0,.3)">
          <div style="height:6px;background:${t.color};border-radius:3px;margin-bottom:4px"></div>
          <div style="height:3px;background:${t.accent};width:60%;border-radius:2px;margin-bottom:6px"></div>
          <div style="display:flex;gap:4px;margin-bottom:4px">${[0,0,0].map(()=>'<div style="flex:1;height:12px;background:#f0f0f0;border-radius:2px"></div>').join('')}</div>
          <div style="height:2px;background:#eee;margin-bottom:3px"></div>
          <div style="height:4px;background:${t.color};width:40%;border-radius:2px;margin-top:6px;margin-left:auto"></div>
        </div>
      </div>
      <div class="tpl-info">
        <div class="tpl-name">${t.name}</div>
        <div style="font-size:11px;color:var(--muted);margin-bottom:8px">${t.desc}</div>
        <div class="tpl-btns">
          <button class="btn btn-outline" style="font-size:11px;padding:5px 10px" onclick="previewTemplate('${t.id}')">Preview</button>
          <button class="btn btn-success" style="font-size:11px;padding:5px 10px" onclick="setActiveTemplate('${t.id}')">${isActive?'✓ Active':'Set Active'}</button>
        </div>
      </div>
    </div>`;
  }).join('');
}

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

async function setActiveTemplate(n) {
  STATE.settings.activeTemplate = n;
  const fTpl = document.getElementById('f-template');
  const sdTpl = document.getElementById('sd-tpl');
  if (fTpl)  fTpl.value  = String(n);
  if (sdTpl) sdTpl.value = String(n);
  syncThemePicker();
  renderTemplatesGrid();
  if (document.getElementById('invoicePreviewWrap')) livePreview();
  try {
    await api('api/settings.php', 'POST', { active_template: String(n) });
    toast(`✅ Template ${n} set as active`, 'success');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}
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

// Show/hide theme picker depending on active template
function syncThemePicker() {
  const tplId   = String(document.getElementById('f-template')?.value || STATE.settings.activeTemplate || '2');
  const picker  = document.getElementById('tpl2-theme-picker');
  const cpicker = document.getElementById('tpl-color-pickers');
  const isTpl2  = tplId === '2';
  const isTplF  = tplId === 'F';
  if (picker)  picker.style.display  = isTpl2 ? 'block' : 'none';
  if (cpicker) cpicker.style.display = (isTpl2 || isTplF) ? 'none' : 'grid';
  // Persist selected template so it survives page refresh
  if (STATE.settings.activeTemplate !== tplId) {
    STATE.settings.activeTemplate = tplId;
    // Sync sd-tpl and other selects
    const sdTpl = document.getElementById('sd-tpl');
    if (sdTpl) sdTpl.value = tplId;
    const prevSel = document.getElementById('prevTplSelect');
    if (prevSel) prevSel.value = tplId;
    // Save to DB silently
    api('api/settings.php', 'POST', { active_template: tplId }).catch(() => {});
  }
}

// Set matte theme for Template 2
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
  if (document.getElementById('invoicePreviewWrap')) livePreview();
}

function setTplColor(inputId, color) {
  const colorInput = document.getElementById(inputId);
  const hexInput   = document.getElementById(inputId + '-hex');
  if (colorInput) colorInput.value = color;
  if (hexInput)   hexInput.value   = color;
  // Immediately update TPL_CUSTOM so preview reflects change
  if (inputId === 'tpl-color1') TPL_CUSTOM.color1 = color;
  if (inputId === 'tpl-color2') TPL_CUSTOM.color2 = color;
  if (document.getElementById('invoicePreviewWrap')) livePreview();
}

// Sync TPL_CUSTOM → template customization form fields on page load
function populateTemplateForm() {
  const C = window.TPL_CUSTOM || {};
  const setV = (id,v) => { const e=document.getElementById(id); if(e&&v!==undefined) e.value=String(v); };
  setV('tpl-color1',        C.color1         || '#1A2332');
  setV('tpl-color1-hex',    C.color1         || '#1A2332');
  setV('tpl-color2',        C.color2         || '#4DB6AC');
  setV('tpl-color2-hex',    C.color2         || '#4DB6AC');
  setV('tpl-font',          C.font           || "'Public Sans',sans-serif");
  setV('tpl-logo-pos',      C.logoPosition   || 'left');
  setV('tpl-watermark-text',C.watermarkText  || 'PAID');
  // Restore matte theme button highlight
  if (C.colorTheme) setMatteTheme(parseInt(C.colorTheme)||1);
  // Show/hide color pickers vs theme picker
  syncThemePicker();
}

window.applyTplCustomization = function() {
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
};

window.saveTplCustomization = async function() {
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
    await api('api/settings.php', 'POST', payload);
    const badge = document.getElementById('tpl-unsaved-badge');
    if (badge) badge.style.display = 'none';
    toast('✅ Template customization saved!', 'success');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
};

function _tplMarkUnsaved() {
  const badge = document.getElementById('tpl-unsaved-badge');
  if (badge) badge.style.display = 'inline-flex';
}

window.resetTplCustomization = function() {
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
};
