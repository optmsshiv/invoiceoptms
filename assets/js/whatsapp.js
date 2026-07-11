// ================================================================
//  assets/js/whatsapp.js
//  Requires: common.js, shared-data.js, wa-shared.js (loaded before
//  this file — needs sendWA, sendWABusinessMsg, formatWAMsg,
//  logWAMessage from wa-shared.js).
//  For pages/comms/whatsapp.php.
//
//  NOTES on things fixed while porting (flagging clearly rather than
//  silently changing behavior):
//  1. sendManualWA() had two definitions in the old SPA — an earlier
//     one that properly used sendWA() (tries the Meta Cloud API,
//     falls back to a wa.me link), and a later one that silently
//     overwrote it with a simpler version that ALWAYS just opened
//     wa.me directly, skipping the API path entirely. Since JS lets
//     a later `window.x = function(){}` assignment silently replace
//     an earlier one, the broken version was what actually ran.
//     Ported the earlier (correct, API-aware) version here instead.
//  2. clearFestivalCampaign() was called by a button in the HTML but
//     was never implemented anywhere — a dead button. Added a
//     straightforward implementation (see below); worth a quick
//     look to confirm it does what you want.
//  3. fillWaManualPhone() had two near-identical definitions with no
//     real behavior difference — kept the later one, no issue there.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['clients', 'settings']);
  waTab('inv', document.querySelector('.wa-tab-btn.active'));
});

// saveWASettings: see // saveWASettings is defined below as // Auto-save a single WA toggle immediately when clicked
async function saveWAToggle(key, el) {
  const val = el.classList.contains('on') ? '1' : '0';
  // Update STATE immediately
  if (!STATE.settings.wa) STATE.settings.wa = {};
  STATE.settings.wa[key.replace('wa_', '')] = val;
  // Save single key to DB
  try {
    await api('api/settings.php', 'POST', { [key]: val });
    // Brief visual feedback on the toggle
    el.style.boxShadow = '0 0 0 3px rgba(0,137,123,.35)';
    setTimeout(() => { el.style.boxShadow = ''; }, 700);
  } catch(e) {
    // Revert on error
    el.classList.toggle('on');
    toast('❌ Failed to save: ' + e.message, 'error');
  }
}
window.saveWASettings = async function() {
  // Save all WA settings (credentials + templates) to DB
  const tog = id => document.getElementById(id)?.classList.contains('on') ? '1' : '0';
  const val = id => document.getElementById(id)?.value || '';
  const payload = {
    wa_token:         val('wa-token'),
    wa_pid:           val('wa-pid'),
    wa_bid:           val('wa-bid'),
    wa_webhook_token: val('wa-webhook-token'),
    wa_test_phone:    val('wa-test-phone'),
    wa_allow_web_fallback: tog('wa-allow-web-fallback'),
    wa_tpl_inv:       val('wa-tpl-inv'),
    wa_tpl_estimate:  val('wa-tpl-estimate'),
    wa_tpl_paid:      val('wa-tpl-paid'),
    wa_tpl_partial:   val('wa-tpl-partial'),
    wa_tpl_remind:    val('wa-tpl-remind'),
    wa_tpl_overdue:   val('wa-tpl-overdue'),
    wa_tpl_followup:  val('wa-tpl-followup'),
    wa_tpl_recurring: val('wa-tpl-recurring'),
    wa_tpl_festival:  val('wa-tpl-festival'),
    wa_auto_inv:      tog('twa1'),
    wa_auto_estimate: tog('twa7'),
    wa_auto_paid:     tog('twa2'),
    wa_auto_partial:  tog('twa6'),
    wa_auto_remind:   tog('twa3'),
    wa_auto_overdue:  tog('twa4'),
    wa_auto_followup: tog('twa5'),
    wa_msg_mode:           document.querySelector('input[name="wa-msg-mode"]:checked')?.value || 'session',
    wa_tpl_name_invoice:   val('tpl-name-invoice'),
    wa_tpl_lang_invoice:   val('tpl-lang-invoice')   || 'en_US',
    wa_tpl_name_estimate:  val('tpl-name-estimate'),
    wa_tpl_lang_estimate:  val('tpl-lang-estimate')  || 'en_US',
    wa_tpl_name_reminder:  val('tpl-name-reminder'),
    wa_tpl_lang_reminder:  val('tpl-lang-reminder')  || 'en_US',
    wa_tpl_name_overdue:   val('tpl-name-overdue'),
    wa_tpl_lang_overdue:   val('tpl-lang-overdue')   || 'en_US',
    wa_tpl_name_paid:      val('tpl-name-paid'),
    wa_tpl_lang_paid:      val('tpl-lang-paid')      || 'en_US',
    wa_tpl_name_followup:  val('tpl-name-followup'),
    wa_tpl_lang_followup:  val('tpl-lang-followup')  || 'en_US',
    wa_tpl_name_recurring: val('tpl-name-recurring'),
    wa_tpl_lang_recurring: val('tpl-lang-recurring')  || 'en_US',
    wa_tpl_name_partial:   val('tpl-name-partial'),
    wa_tpl_lang_partial:   val('tpl-lang-partial')   || 'en_US',
    wa_tpl_name_balance_reminder: val('tpl-name-balance-reminder'),
    wa_tpl_lang_balance_reminder: val('tpl-lang-balance-reminder') || 'en_US',
    wa_tpl_name_festival:  val('tpl-name-festival'),
    wa_tpl_lang_festival:  val('tpl-lang-festival')  || 'en_US',
  };
  // Update STATE immediately with all keys
  if (!STATE.settings.wa) STATE.settings.wa = {};
  Object.assign(STATE.settings.wa, {
    token: payload.wa_token, pid: payload.wa_pid, bid: payload.wa_bid,
    test_phone: payload.wa_test_phone,
    allow_web_fallback: payload.wa_allow_web_fallback === '1',
    tpl_inv: payload.wa_tpl_inv, tpl_estimate: payload.wa_tpl_estimate, tpl_paid: payload.wa_tpl_paid,
    tpl_partial: payload.wa_tpl_partial,
    tpl_remind: payload.wa_tpl_remind, tpl_overdue: payload.wa_tpl_overdue,
    tpl_followup: payload.wa_tpl_followup, tpl_festival: payload.wa_tpl_festival,
    auto_inv: payload.wa_auto_inv, auto_estimate: payload.wa_auto_estimate,
    auto_paid: payload.wa_auto_paid,
    auto_partial: payload.wa_auto_partial,
    auto_remind: payload.wa_auto_remind, auto_overdue: payload.wa_auto_overdue,
    auto_followup: payload.wa_auto_followup,
    msg_mode: payload.wa_msg_mode,
    tpl_name_invoice:  payload.wa_tpl_name_invoice,
    tpl_lang_invoice:  payload.wa_tpl_lang_invoice,
    tpl_name_estimate: payload.wa_tpl_name_estimate,
    tpl_lang_estimate: payload.wa_tpl_lang_estimate,
    tpl_name_reminder: payload.wa_tpl_name_reminder,
    tpl_lang_reminder: payload.wa_tpl_lang_reminder,
    tpl_name_overdue:  payload.wa_tpl_name_overdue,
    tpl_lang_overdue:  payload.wa_tpl_lang_overdue,
    tpl_name_paid:     payload.wa_tpl_name_paid,
    tpl_lang_paid:     payload.wa_tpl_lang_paid,
    tpl_name_followup:  payload.wa_tpl_name_followup,
    tpl_lang_followup:  payload.wa_tpl_lang_followup,
    tpl_name_recurring: payload.wa_tpl_name_recurring,
    tpl_lang_recurring: payload.wa_tpl_lang_recurring,
    tpl_name_partial:   payload.wa_tpl_name_partial,
    tpl_lang_partial:  payload.wa_tpl_lang_partial,
    tpl_name_balance_reminder: payload.wa_tpl_name_balance_reminder,
    tpl_lang_balance_reminder: payload.wa_tpl_lang_balance_reminder,
    tpl_name_festival: payload.wa_tpl_name_festival,
    tpl_lang_festival: payload.wa_tpl_lang_festival,
  });
  try {
    await api('api/settings.php', 'POST', payload);
    toast('✅ WhatsApp settings saved!', 'success');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
};
async function handleLogoUpload(input, targetId, previewId) {
  const file = input.files[0]; if (!file) return;
  if (file.size > 3*1024*1024) { toast('⚠️ Max 3MB', 'warning'); return; }
  const typeMap = {
    'f-company-logo':'logo','sc-logo':'logo',
    'f-signature':'signature','sc-sign':'signature',
    'f-client-logo':'client_logo','f-qr':'qr'
  };
  const fd = new FormData();
  fd.append('file', file);
  fd.append('type', typeMap[targetId] || 'logo');
  try {
    const res  = await fetch('api/upload.php', { method:'POST', body:fd });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch(e) { throw new Error('Upload failed: server returned HTML'); }
    if (!data.success) throw new Error(data.error || 'Upload failed');
    const el = document.getElementById(targetId);
    if (el) { el.value = data.url; el.dispatchEvent(new Event('input')); }
    if (targetId === 'sc-logo' || targetId === 'f-company-logo') {
      STATE.settings.logo = data.url;
    } else if (targetId === 'sc-sign' || targetId === 'f-signature') {
      STATE.settings.signature = data.url;
    }
    // Set the hidden input value so getFormData picks it up
    const _tgtInput = document.getElementById(targetId);
    if (_tgtInput && _tgtInput.tagName === 'INPUT') _tgtInput.value = data.url;
    if (previewId) {
      const prev = document.getElementById(previewId);
      if (prev) {
        const isSign = previewId.includes('sign');
        prev.innerHTML = `<div style="display:inline-flex;align-items:center;gap:8px;padding:6px 10px;background:${isSign?'#1a1a2e':'var(--teal-bg)'};border-radius:8px;border:1px solid var(--border)">
          <img src="${data.url}" style="height:${isSign?'36':'32'}px;max-width:120px;object-fit:contain;border-radius:4px">
          <span style="font-size:11px;color:var(--muted)">${file.name}</span>
          <button onclick="clearLogoField('${targetId}','${previewId}')" style="border:none;background:none;cursor:pointer;color:var(--red);font-size:13px"><i class="fas fa-times"></i></button>
        </div>`;
      }
    }
    toast('✅ Uploaded!', 'success');
  } catch(e) {
    // Fallback: use base64
    const reader = new FileReader();
    reader.onload = ev => {
      const el = document.getElementById(targetId);
      if (el) { el.value = ev.target.result; el.dispatchEvent(new Event('input')); }
      toast('✅ Image loaded', 'success');
    };
    reader.readAsDataURL(file);
    console.warn('Server upload failed, using base64:', e.message);
  }
}

function clearLogoField(targetId, previewId) {
  const el = document.getElementById(targetId); if (el) { el.value = ''; el.dispatchEvent(new Event('input')); }
  const prev = document.getElementById(previewId); if (prev) prev.innerHTML = '';
}
function testWA() {
  const wa    = STATE.settings.wa || {};
  const token = document.getElementById('wa-token')?.value || wa.token || '';
  const pid   = document.getElementById('wa-pid')?.value   || wa.pid   || '';
  const phone = (document.getElementById('wa-test-phone')?.value || '').replace(/\D/g, '');
  if (!phone) { toast('⚠️ Enter test phone number first', 'warning'); return; }

  const sampleInv = {
    num: 'TEST-001', invoice_number: 'TEST-001',
    issued: new Date().toISOString().split('T')[0],
    due:    new Date(Date.now()+15*86400000).toISOString().split('T')[0],
    amount: 15000, currency: '₹', service: 'Web Development',
    service_type: 'Web Development', status: 'Pending',
    items: [{desc:'Website Design', qty:1, rate:10000},{desc:'SEO Setup', qty:1, rate:5000}]
  };
  const tplRaw = document.getElementById('wa-tpl-inv')?.value || wa.tpl_inv || getDefaultWATpl('inv');
  const msg    = formatWAMsg(tplRaw, sampleInv, {name: 'Test Client'}, STATE.settings);

  if (token && pid) {
    sendWABusinessMsg(phone, msg, token, pid)
      .then(()  => toast('✅ Test message sent via WhatsApp Business API!', 'success'))
      .catch(err => {
        console.error('WA API error:', err);
        toast('❌ API Error: ' + err.message, 'error');
      });
  } else {
    const clean = phone.length===10 ? '91'+phone : phone;
    window.open('https://wa.me/' + clean + '?text=' + encodeURIComponent(msg), '_blank');
    toast('📱 Opened WhatsApp (enter API credentials to send directly)', 'info');
  }
}
window.sendManualWA = async function() {
  const phone = (document.getElementById('wa-manual-phone')?.value || '').replace(/\D/g, '');
  const msg   = document.getElementById('wa-manual-msg')?.value || '';
  if (!phone) { toast('⚠️ Enter phone number', 'warning'); return; }
  if (!msg)   { toast('⚠️ Enter message text', 'warning'); return; }
  try {
    const result = await sendWA(phone, msg);
    toast(result ? '✅ Message sent via API!' : '📱 WhatsApp opened', 'success');
    if (result) { document.getElementById('wa-manual-msg').value = ''; }
  } catch(e) { toast('❌ ' + e.message, 'error'); }
};
window.sendFestivalBulk = async function() {
  const sendTo  = document.getElementById('wa-send-to')?.value || 'all';
  const tpl     = document.getElementById('wa-tpl-festival')?.value || getDefaultWATpl('festival');
  const imgUrl  = document.getElementById('wa-festival-img')?.value || '';
  const wa      = STATE.settings.wa || {};

  let targets = [...STATE.clients].filter(c => c.wa || c.whatsapp || c.phone);
  if (sendTo === 'paid') {
    const paidIds = new Set(STATE.invoices.filter(i=>i.status==='Paid').map(i=>String(i.client)));
    targets = targets.filter(c => paidIds.has(String(c.id)));
  }
  if (sendTo === 'active') {
    const cutoff  = new Date(); cutoff.setDate(cutoff.getDate()-90);
    const actIds  = new Set(STATE.invoices.filter(i=>i.issued&&new Date(i.issued)>cutoff).map(i=>String(i.client)));
    targets = targets.filter(c => actIds.has(String(c.id)));
  }

  const log = document.getElementById('wa-bulk-log');
  if (log) { log.style.display = 'block'; log.innerHTML = `<b>Sending to ${targets.length} clients...</b><br>`; }

  let sent = 0, failed = 0;
  for (const client of targets) {
    const phone = (client.wa || client.whatsapp || client.phone || '').replace(/\D/g,'');
    const msg   = formatWAMsg(tpl, {}, client, STATE.settings);
    try {
      const result = await sendWA(phone, msg);
      sent++;
      if (log) log.innerHTML += `<div style="color:var(--green)">✓ ${client.name}${result?' (API)':' (web)'}</div>`;
      if (!result) await new Promise(r => setTimeout(r, 500));
    } catch(e) {
      failed++;
      if (log) log.innerHTML += `<div style="color:var(--red)">✗ ${client.name}: ${e.message}</div>`;
    }
  }
  toast(`📱 Done: ${sent} sent, ${failed} failed`, sent > 0 ? 'success' : 'warning');
};
window.previewFestivalMsg = function() {
  const tpl     = document.getElementById('wa-tpl-festival')?.value || getDefaultWATpl('festival');
  const preview = formatWAMsg(tpl, {}, {name:'[Client Name]'}, STATE.settings);
  toast('📱 ' + preview.substring(0,120) + (preview.length>120?'…':''), 'info');
};
window.fillWaManualPhone = function() {
  const sel = document.getElementById('wa-manual-client');
  const c   = STATE.clients.find(x=>String(x.id)===String(sel?.value));
  const ph  = document.getElementById('wa-manual-phone');
  if (!c) return;
  if (ph) ph.value = c.wa || c.whatsapp || c.phone || '';
  // Also auto-fill a greeting in the message box
  const msgEl = document.getElementById('wa-manual-msg');
  if (msgEl && !msgEl.value) {
    msgEl.value = `Hi ${c.name}! `;
  }
};
function setWAMode(mode) {
  const sec = document.getElementById('tpl-names-section');
  const sLbl = document.getElementById('mode-session-lbl');
  const tLbl = document.getElementById('mode-template-lbl');
  if (sec)  sec.style.display  = mode === 'template' ? 'block' : 'none';
  if (sLbl) sLbl.style.borderColor = mode === 'session'  ? 'var(--teal)' : 'var(--border)';
  if (tLbl) tLbl.style.borderColor = mode === 'template' ? 'var(--teal)' : 'var(--border)';
  if (!STATE.settings.wa) STATE.settings.wa = {};
  STATE.settings.wa.msg_mode = mode;
}
window.saveFestivalCampaign = async function() {
  const payload = {
    wa_festival_tpl:      document.getElementById('wa-tpl-festival')?.value   || '',
    wa_festival_sendto:   document.getElementById('wa-send-to')?.value        || 'all',
    wa_festival_img:      document.getElementById('wa-festival-img')?.value   || '',
    wa_festival_schedule: document.getElementById('wa-festival-schedule')?.value || '',
    wa_festival_repeat:   document.getElementById('wa-festival-repeat')?.value || '',
    wa_festival_name:     document.getElementById('wa-festival')?.value        || 'custom',
  };
  try {
    await api('api/settings.php', 'POST', payload);
    // Store locally
    if (!STATE.settings.wa) STATE.settings.wa = {};
    STATE.settings.wa.festival_schedule = payload.wa_festival_schedule;
    STATE.settings.wa.festival_repeat   = payload.wa_festival_repeat;
    // Show confirmation
    const schedTime = payload.wa_festival_schedule
      ? ' — scheduled for ' + new Date(payload.wa_festival_schedule).toLocaleString(_moneyLocale(),{dateStyle:'medium',timeStyle:'short'})
      : '';
    toast('✅ Campaign saved!' + schedTime, 'success');
    renderFestivalCampaigns();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
};

function renderFestivalCampaigns() {
  const el = document.getElementById('wa-campaigns-list');
  if (!el) return;
  const wa = STATE.settings.wa || {};
  if (!wa.festival_schedule && !wa.festival_repeat) { el.innerHTML = ''; return; }
  const schedTime = wa.festival_schedule
    ? new Date(wa.festival_schedule).toLocaleString(_moneyLocale(),{dateStyle:'medium',timeStyle:'short'})
    : 'Not scheduled';
  el.innerHTML = `<div style="background:var(--teal-bg);border-radius:8px;padding:10px 14px;font-size:12px;border:1px solid var(--teal);margin-top:4px">
    <div style="font-weight:700;color:var(--teal);margin-bottom:4px"><i class="fas fa-calendar-check"></i> Saved Campaign</div>
    <div>📅 Schedule: <strong>${schedTime}</strong></div>
    ${wa.festival_repeat ? `<div>🔁 Repeat: <strong>${wa.festival_repeat}</strong></div>` : ''}
    <div>👥 Send to: <strong>${wa.festival_sendto || 'all clients'}</strong></div>
    <button onclick="clearFestivalCampaign()" style="margin-top:8px;font-size:11px;padding:4px 10px;border:1px solid var(--red);color:var(--red);background:none;border-radius:6px;cursor:pointer">
      <i class="fas fa-times"></i> Clear Campaign
    </button>
  </div>`;
}
// ── Clear festival campaign form ──────────────────────────────
// MPA ADDITION: this button existed in the old SPA HTML but called
// clearFestivalCampaign() which was never actually implemented
// anywhere in the codebase — a dead button. Added a straightforward
// implementation here (resets the campaign scheduling fields, not
// the saved message template itself). Worth a quick look to confirm
// this matches what you want it to do.
function clearFestivalCampaign() {
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
  set('wa-festival', '');
  set('wa-festival-custom', '');
  set('wa-festival-img', '');
  set('wa-festival-schedule', '');
  set('wa-festival-repeat', '');
  const preview = document.getElementById('wa-festival-img-preview');
  if (preview) preview.innerHTML = '';
  toast('Campaign form cleared', 'info');
}

async function sendWACustomMessage() {
  const phone  = (document.getElementById('wa-custom-phone')?.value || '').replace(/\D/g,'');
  const msg    = (document.getElementById('wa-custom-msg')?.value   || '').trim();
  const status = document.getElementById('wa-custom-status');
  if (!phone) { toast('⚠️ Enter recipient phone number','warning'); return; }
  if (!msg)   { toast('⚠️ Enter a message','warning'); return; }
  if (status) status.textContent = 'Sending…';
  try {
    const result = await sendWA(phone, msg, 'unknown', null, null);
    if (result) {
      const wamid = result?.wamid || result?.messages?.[0]?.id || '';
      logWAMessage({ inv:null, client:null, type:'unknown', msg, status:'sent_api', wamid });
      toast('✅ Custom message sent via API!','success');
      document.getElementById('wa-custom-msg').value   = '';
      document.getElementById('wa-custom-phone').value = '';
      document.getElementById('wa-custom-char').textContent = '0 / 4096';
      if (status) status.textContent = '✅ Sent';
    } else {
      toast('📱 WhatsApp opened','info');
      if (status) status.textContent = '📱 Opened WhatsApp';
    }
  } catch(e) {
    logWAMessage({ inv:null, client:null, type:'unknown', msg, status:'failed', error: e.message });
    toast('❌ ' + e.message,'error');
    if (status) status.textContent = '❌ Failed';
  }
}
function waTab(key, btn) {
  document.querySelectorAll('.wa-tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.wa-tab-pane').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  const pane = document.getElementById('watab-' + key);
  if (pane) pane.classList.add('active');
  // Store active tab
  window._waActiveTab = key;
}

// ── Insert variable at cursor ─────────────────────────────────
function waInsertVar(varName) {
  // Find the active tab's textarea
  const key = window._waActiveTab || 'inv';
  const idMap = { inv:'wa-tpl-inv', estimate:'wa-tpl-estimate', paid:'wa-tpl-paid', partial:'wa-tpl-partial',
                  remind:'wa-tpl-remind', overdue:'wa-tpl-overdue', followup:'wa-tpl-followup',
                  recurring:'wa-tpl-recurring' };
  const tId = idMap[key] || 'wa-manual-msg';
  // Also check if manual msg textarea is focused
  const focused = document.activeElement;
  const target = (focused && (focused.id === 'wa-manual-msg' || Object.values(idMap).includes(focused.id)))
    ? focused : document.getElementById(tId);
  if (!target) return;
  const start = target.selectionStart, end = target.selectionEnd;
  target.value = target.value.substring(0,start) + varName + target.value.substring(end);
  target.selectionStart = target.selectionEnd = start + varName.length;
  target.focus();
  target.dispatchEvent(new Event('input'));
}

// ── Character counter ─────────────────────────────────────────
function waUpdateCounter(textareaId, counterId) {
  const ta = document.getElementById(textareaId);
  const ct = document.getElementById(counterId);
  if (!ta || !ct) return;
  const len = ta.value.length;
  const msgs = Math.ceil(len / 160) || 1;
  ct.textContent = len + ' chars' + (msgs > 1 ? ' · ' + msgs + ' SMS segments' : '');
  ct.className = 'wa-char-counter' + (len > 1600 ? ' over' : len > 1000 ? ' warn' : '');
}

// ── Preview toggle ────────────────────────────────────────────
function waTogglePreview(wrapId) {
  const wrap = document.getElementById(wrapId);
  if (!wrap) return;
  const showing = wrap.classList.contains('show');
  wrap.classList.toggle('show', !showing);
  if (!showing) waUpdatePreview(null, wrapId.replace('wa-prev-','wa-tpl-') || null, wrapId);
}
function waUpdatePreview(textareaId, wrapId) {
  // wrapId is like 'wa-prev-inv', bubble id is 'wa-prev-inv-bubble'
  const wrap = document.getElementById(wrapId);
  if (!wrap || !wrap.classList.contains('show')) return;
  const ta = document.getElementById(textareaId);
  if (!ta) return;
  const bubble = document.getElementById(wrapId + '-bubble');
  if (!bubble) return;
  // Render *bold* and links
  let txt = ta.value
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/\*(.*?)\*/g,'<strong>$1</strong>')
    .replace(/(https?:\/\/\S+)/g,'<a href="$1" style="color:#00897B">$1</a>')
    .replace(/\n/g,'<br>');
  bubble.innerHTML = txt;
}

// ── Reset current tab template to default ────────────────────
async function waResetCurrentTab() {
  const key = window._waActiveTab || 'inv';
  const idMap = { inv:'wa-tpl-inv', estimate:'wa-tpl-estimate', paid:'wa-tpl-paid', partial:'wa-tpl-partial',
                  remind:'wa-tpl-remind', overdue:'wa-tpl-overdue', followup:'wa-tpl-followup',
                  recurring:'wa-tpl-recurring' };
  const tplMap = { inv:'inv', estimate:'estimate', paid:'paid', partial:'partial_receipt',
                   remind:'remind', overdue:'overdue', followup:'followup' };
  const tId = idMap[key];
  const tKey = tplMap[key];
  if (!tId || !tKey) return;
  const _waResult = await Swal.fire({ title: 'Reset Template?', text: 'Your changes will be lost and the template will revert to the default.', icon: 'question', showCancelButton: true, confirmButtonText: 'Reset', cancelButtonText: 'Cancel', confirmButtonColor: '#E53935', customClass: { popup: 'swal-compact' } });
  if (!_waResult.isConfirmed) return;
  const ta = document.getElementById(tId);
  if (ta) { ta.value = getDefaultWATpl(tKey); saveWASettings(); toast('↩ Template reset to default', 'info'); }
}

// ── Quick reply templates for manual send ────────────────────
function waQuickReply(type) {
  const wa = STATE.settings.wa || {};
  const templates = {
    payment:  'Hi {client_name}! 👋 This is a reminder that Invoice #{invoice_no} for {amount} is due on {due_date}. Please arrange payment via UPI: ' + (wa.upi||'{upi}') + '. Thank you! — ' + (STATE.settings.company||'{company_name}'),
    followup: 'Hi {client_name}, just following up on the pending invoice. Kindly let us know when we can expect the payment. Thank you! — ' + (STATE.settings.company||'{company_name}'),
    thankyou: 'Hi {client_name}! 🙏 Thank you so much for your payment. We really appreciate your trust in ' + (STATE.settings.company||'{company_name}') + '. Looking forward to serving you again!',
    custom:   ''
  };
  const msg = document.getElementById('wa-manual-msg');
  if (!msg) return;
  if (type === 'custom') { msg.focus(); return; }
  msg.value = templates[type] || '';
  msg.focus();
  waUpdateCounter('wa-manual-msg','wa-manual-counter');
}

// ── Init counters on WA page open ────────────────────────────

// override above (near the recurring page hook). No second override needed.
window._waActiveTab = 'inv';
function getDefaultWATpl(type) {
  const d = {
    estimate: `Hi {client_name}! 👋

📋 *Estimation / Quotation*
From: *{company_name}*

We have prepared a cost estimate for your requirements:

🔢 Quote No: *#{invoice_no}*
📅 Date: *{issue_date}*
💰 Estimated Amount: *{currency}{amount}*
⏳ Valid Until: *{due_date}*
📋 Service: {service}

{item_list}

⚠️ *Please note: This is an ESTIMATE only, not a final invoice. Actual charges may vary based on the final scope of work.*

👁️ View & Review your estimate online:
{invoice_link}

To *accept* this estimate, reply *APPROVED*.
To request changes, reply with your feedback.

Thank you for considering {company_name}! 🙏
📞 {company_phone} | ✉ {company_email}`,

    inv: `Hi {client_name}! 👋

*Invoice #{invoice_no}* from *{company_name}* is ready.

📋 Service: {service}
📅 Issue Date: {issue_date}
⏳ Due Date: *{due_date}*
💰 Amount: *{currency}{amount}*

{item_list}

💳 *Pay via UPI:* {upi}
🏦 {bank_details}

🔗 *View & Download Invoice:*
{invoice_link}

Thank you for choosing {company_name}!
📞 {company_phone} | ✉ {company_email}`,

    paid: `Hi {client_name}! ✅

Payment received for *Invoice #{invoice_no}*{settlement_discount_line}

💰 Amount Received: *{currency}{amount}*
📅 Date: {issue_date}
📋 Service: {service}

🔗 *View Receipt:*
{invoice_link}

Your account is now clear. Thank you! 🙏
We look forward to serving you again.

— *{company_name}*
📞 {company_phone}`,

    remind: `Hi {client_name}! 🔔 *Payment Reminder*

*Invoice #{invoice_no}* for *{currency}{amount}* is due on *{due_date}*

📋 Service: {service}

Please arrange payment at your earliest convenience.

💳 *UPI:* {upi}
🏦 {bank_details}

🔗 *View Invoice:*
{invoice_link}

— {company_name} | 📞 {company_phone}`,

    overdue: `Hi {client_name}! ⚠️ *Overdue Notice*

*Invoice #{invoice_no}* for *{currency}{amount}* was due on *{due_date}*
Overdue by: *{days_overdue} days*

📋 Service: {service}

Please clear this immediately to avoid any inconvenience.

💳 *UPI:* {upi}
🏦 {bank_details}

🔗 *View Invoice:*
{invoice_link}

— {company_name} | 📞 {company_phone}`,

    recurring: `Hi {client_name}! 🔁

*Recurring Invoice #{invoice_no}* from *{company_name}* is ready.

📋 Service: {service}
📅 Issue Date: {issue_date}
⏳ Due Date: *{due_date}*
💰 Amount: *{currency}{amount}*

{item_list}

💳 *Pay via UPI:* {upi}
🏦 {bank_details}

{outstanding_dues}

🔗 *View & Download Invoice:*
{invoice_link}

Thank you for choosing {company_name}!
📞 {company_phone} | ✉ {company_email}`,

    followup: `Hi {client_name},

This is a follow-up for *Invoice #{invoice_no}* (*{currency}{amount}*).
⚠️ Still overdue by *{days_overdue} days*

📋 Service: {service}

Kindly process payment immediately or contact us to discuss.

💳 *UPI:* {upi}

🔗 *View Invoice:*
{invoice_link}

— {company_name} | 📞 {company_phone} | ✉ {company_email}`,

    partial_receipt: `Hi {client_name}! 💚

*Partial Payment Received* for Invoice #{invoice_no}

✅ Paid: *{paid_amount}*
⏳ Remaining: *{remaining_amount}*
📋 Invoice Total: {currency}{amount}
📅 Date: {issue_date}
📋 Service: {service}

Please clear the remaining balance by *{due_date}*.
💳 UPI: {upi}
🏦 {bank_details}

🔗 *View Invoice:*
{invoice_link}

Thank you! — *{company_name}*
📞 {company_phone}`,

    split_receipt: `Hi {client_name}! ⚡

*Split Payment Recorded* for Invoice #{invoice_no}

💰 Amount: *{currency}{amount}*
📋 Payment split across multiple methods
📅 Date: {issue_date}
📋 Service: {service}

🔗 *View Receipt:*
{invoice_link}

Your account is now clear. Thank you! 🙏
— *{company_name}* | 📞 {company_phone}`,

    festival: `Hi {client_name}! 🎉

Warm *festival greetings* from the entire team at *{company_name}*!

May this occasion bring you joy, prosperity, and success. 🌟

Thank you for your continued trust and support. We are grateful for your partnership. 🙏

*{company_name}*
📞 {company_phone} | ✉ {company_email}`
  };
  return d[type] || '';
}
