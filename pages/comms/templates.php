<?php
// ================================================================
//  pages/comms/templates.php
//  PDF invoice template gallery, live preview, and customization
//  (colors, font, logo position, watermark text).
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.templates');

$user = currentUser();

$activePage  = 'templates';
$pageTitle   = 'PDF Templates';
$pageScripts = [
    '/assets/js/shared-data.js',
    '/assets/js/invoice-render-shared.js',
    '/assets/js/templates.js',
];

include __DIR__ . '/../../includes/layout_header.php';
?>
    <div id="page-templates" class="page">
      <div class="templates-intro">Choose a PDF template. <strong>Preview</strong> shows it live below. <strong>Set Active</strong> uses it as default.</div>
      <div class="templates-grid" id="templatesGrid"></div>

      <!-- Inline Preview Panel -->
      <div id="tplPreviewPanel" style="display:none;margin-top:24px">
        <div class="dash-card">
          <div class="card-header">
            <span class="card-title" id="tplPreviewLabel">Template Preview</span>
            <button class="cf-btn" onclick="document.getElementById('tplPreviewPanel').style.display='none'"><i class="fas fa-times"></i> Close</button>
          </div>
          <div style="background:#e8eaed;border-radius:8px;padding:16px;overflow:auto;text-align:center;min-height:200px">
            <div id="tplPreviewInner" style="display:inline-block;text-align:left"></div>
          </div>
        </div>
      </div>

      <!-- Template Customization -->
      <div class="dash-card" style="margin-top:24px">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-paint-brush" style="color:var(--teal)"></i> Customize Active Template</span>
          <span style="font-size:12px;color:var(--muted)">Changes apply to new invoices</span>
        </div>
        <div style="padding:0 4px">
          <!-- Theme selector — shown only when Template 2 is active -->
          <div id="tpl2-theme-picker" style="display:none;margin-bottom:16px;padding:14px;background:var(--bg);border-radius:10px;border:1px solid var(--border)">
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:10px">Color Theme (Template 2 — Colorful Matte)</div>
            <div style="display:flex;flex-wrap:wrap;gap:8px">
              ${[['1','Indigo','#2D3A8C'],['2','Emerald','#065F46'],['3','Rose','#881337'],['4','Amber','#78350F'],['5','Ocean','#0C4A6E'],['6','Violet','#4C1D95'],['7','Slate','#1E293B'],['8','Crimson','#7F1D1D']].map(([id,name,col])=>`
              <button onclick="setMatteTheme(${id})" id="mtheme-btn-${id}" style="display:flex;align-items:center;gap:7px;padding:7px 12px;border:1.5px solid var(--border);border-radius:8px;background:#fff;cursor:pointer;font-size:12px;font-weight:600;color:var(--text2);font-family:var(--font);transition:.15s">
                <span style="width:14px;height:14px;border-radius:3px;background:${col};flex-shrink:0;display:inline-block"></span>${name}
              </button>`).join('')}
            </div>
            <input type="hidden" id="tpl-color-theme" value="1">
          </div>

          <!-- Color pickers — hidden for Template 2 (uses its own themes) -->
          <div id="tpl-color-pickers" class="form-grid g2" style="margin-bottom:16px">
            <div class="field">
              <label>Primary Color <span style="font-size:10px;color:var(--muted)">(header background)</span></label>
              <div style="display:flex;gap:8px;align-items:center">
                <input type="color" id="tpl-color1" value="#1A2332" style="width:44px;height:38px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;padding:2px" oninput="setTplColor('tpl-color1',this.value);_tplMarkUnsaved()">
                <input id="tpl-color1-hex" value="#1A2332" placeholder="#1A2332" style="flex:1;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:var(--mono);font-size:13px" oninput="document.getElementById('tpl-color1').value=this.value;TPL_CUSTOM.color1=this.value;(document.getElementById('invoicePreviewWrap') && livePreview())">
                <div style="display:flex;gap:4px;flex-wrap:wrap">
                  <span onclick="setTplColor('tpl-color1','#1A2332')" style="width:20px;height:20px;background:#1A2332;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color1','#00897B')" style="width:20px;height:20px;background:#00897B;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color1','#1565C0')" style="width:20px;height:20px;background:#1565C0;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color1','#B71C1C')" style="width:20px;height:20px;background:#B71C1C;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color1','#4A148C')" style="width:20px;height:20px;background:#4A148C;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color1','#1B5E20')" style="width:20px;height:20px;background:#1B5E20;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color1','#E64A19')" style="width:20px;height:20px;background:#E64A19;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color1','#0F172A')" style="width:20px;height:20px;background:#0F172A;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                </div>
              </div>
            </div>
            <div class="field">
              <label>Accent Color <span style="font-size:10px;color:var(--muted)">(invoice number, totals)</span></label>
              <div style="display:flex;gap:8px;align-items:center">
                <input type="color" id="tpl-color2" value="#4DB6AC" style="width:44px;height:38px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;padding:2px" oninput="setTplColor('tpl-color2',this.value);_tplMarkUnsaved()">
                <input id="tpl-color2-hex" value="#4DB6AC" placeholder="#4DB6AC" style="flex:1;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:var(--mono);font-size:13px" oninput="document.getElementById('tpl-color2').value=this.value;TPL_CUSTOM.color2=this.value;(document.getElementById('invoicePreviewWrap') && livePreview())">
                <div style="display:flex;gap:4px;flex-wrap:wrap">
                  <span onclick="setTplColor('tpl-color2','#4DB6AC')" style="width:20px;height:20px;background:#4DB6AC;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color2','#FFD54F')" style="width:20px;height:20px;background:#FFD54F;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color2','#42A5F5')" style="width:20px;height:20px;background:#42A5F5;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color2','#EF9A9A')" style="width:20px;height:20px;background:#EF9A9A;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color2','#A5D6A7')" style="width:20px;height:20px;background:#A5D6A7;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color2','#CE93D8')" style="width:20px;height:20px;background:#CE93D8;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color2','#FF8A65')" style="width:20px;height:20px;background:#FF8A65;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                  <span onclick="setTplColor('tpl-color2','#ffffff')" style="width:20px;height:20px;background:#fff;border-radius:4px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ddd"></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Common controls — all templates -->
          <div class="form-grid g2" style="margin-bottom:16px">
            <div class="field">
              <label>Font Family</label>
              <select id="tpl-font" onchange="TPL_CUSTOM.font=this.value;(document.getElementById('invoicePreviewWrap') && livePreview());_tplMarkUnsaved()">
                <option value="'Public Sans',sans-serif">Public Sans (Default)</option>
                <option value="'Roboto',sans-serif">Roboto</option>
                <option value="'Inter',sans-serif">Inter</option>
                <option value="'Poppins',sans-serif">Poppins</option>
                <option value="'Montserrat',sans-serif">Montserrat</option>
                <option value="'Lato',sans-serif">Lato</option>
                <option value="Arial,sans-serif">Arial</option>
                <option value="Georgia,serif">Georgia (Serif)</option>
              </select>
            </div>
            <div class="field">
              <label>Logo Position</label>
              <select id="tpl-logo-pos" onchange="TPL_CUSTOM.logoPosition=this.value;(document.getElementById('invoicePreviewWrap') && livePreview());_tplMarkUnsaved()">
                <option value="left">Left (Default)</option>
                <option value="center">Center</option>
                <option value="right">Right</option>
              </select>
            </div>
            <div class="field">
              <label>Watermark Text <span style="font-size:10px;color:var(--muted)">(shown on paid invoices)</span></label>
              <input id="tpl-watermark-text" value="PAID" placeholder="PAID" oninput="TPL_CUSTOM.watermarkText=this.value;(document.getElementById('invoicePreviewWrap') && livePreview());_tplMarkUnsaved()">
            </div>
          </div>

          <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <button class="btn btn-primary" onclick="applyTplCustomization()"><i class="fas fa-magic"></i> Apply &amp; Preview</button>
            <button class="btn btn-success" onclick="saveTplCustomization()"><i class="fas fa-save"></i> Save</button>
            <button class="btn btn-outline" onclick="resetTplCustomization()"><i class="fas fa-undo"></i> Reset</button>
            <span id="tpl-unsaved-badge" style="display:none;font-size:11px;font-weight:700;color:#E64A19;background:#FFF3E0;padding:3px 10px;border-radius:20px;border:1px solid #FFCCBC"><i class="fas fa-circle" style="font-size:7px;margin-right:4px"></i>Unsaved changes</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ─────────── WHATSAPP SETUP ─────────── -->
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
