// ================================================================
//  assets/js/email-setup.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  For pages/comms/email_setup.php.
//
//  MPA CHANGE: old SPA's populateSettingsForm() was one big function
//  shared by both Settings and Email Setup, keyed off a client-side
//  STATE.settings.email_cfg wrapper that never actually existed on
//  the wire (api/settings.php returns flat keys — see shared-data.js
//  loadCoreData()). settings.js already has its own scoped-down
//  populateSettingsForm() for the sc-* fields; this file has its own
//  populateEmailSMTPForm() below for just the em-* fields, reading
//  STATE.settings flat, matching the real API shape.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['settings']);
  populateEmailSMTPForm();
});

function populateEmailSMTPForm() {
  const s = STATE.settings;
  const set = (id, val) => { const el = document.getElementById(id); if (el && val !== undefined && val !== null) el.value = val; };
  set('em-host', s.smtp_host);
  set('em-port', s.smtp_port);
  set('em-user', s.smtp_user);
  set('em-from', s.smtp_from);
  set('em-name', s.smtp_name);
  set('em-subj', s.email_subject);
  set('em-body', s.email_body);
  if (s.smtp_pass) { const ep = document.getElementById('em-pass'); if (ep) ep.value = s.smtp_pass; }
  document.getElementById('em-tog-cc')?.classList.toggle('on', s.email_cc_self === '1');
}

// ══════════════════════════════════════════════════════════════
// EMAIL SYSTEM — Full feature JS
// ══════════════════════════════════════════════════════════════

// ── Tab switching ────────────────────────────────────────────────
function emTab(name, btn) {
  document.querySelectorAll('.em-tab-pane').forEach(p => p.style.display = 'none');
  document.querySelectorAll('.em-tab-btn').forEach(b => {
    b.style.borderBottom = 'none'; b.style.color = 'var(--muted)'; b.style.fontWeight = '600';
  });
  const pane = document.getElementById('em-tab-' + name);
  if (pane) pane.style.display = '';
  if (btn) { btn.style.borderBottom = '2px solid var(--teal)'; btn.style.color = 'var(--teal)'; btn.style.fontWeight = '700'; }
  if (name === 'logs')     loadEmailLogs();
  if (name === 'profiles') loadSmtpProfiles();
  if (name === 'tpl')      loadEmailTemplates();
  if (name === 'auto')     loadEmailAutoSettings();
}

// ── Provider quick-fill ──────────────────────────────────────────
function emFillProvider(p) {
  const providers = {
    gmail:    { host:'smtp.gmail.com',    port:'587', hint:true  },
    outlook:  { host:'smtp.office365.com',port:'587', hint:false },
    yahoo:    { host:'smtp.mail.yahoo.com',port:'587',hint:false },
    sendgrid: { host:'smtp.sendgrid.net', port:'587', hint:false },
    mailgun:  { host:'smtp.mailgun.org',  port:'587', hint:false },
    custom:   { host:'',                  port:'587', hint:false },
  };
  const cfg = providers[p] || providers.custom;
  const h = document.getElementById('em-host');
  const pt = document.getElementById('em-port');
  if (h && !h.value) h.value = cfg.host;
  if (pt) pt.value = cfg.port;
  const hint = document.getElementById('em-gmail-hint');
  if (hint) hint.style.display = cfg.hint ? '' : 'none';
}

// ── Toggle password visibility ───────────────────────────────────
function emTogglePass() {
  const f = document.getElementById('em-pass');
  if (!f) return;
  f.type = f.type === 'password' ? 'text' : 'password';
}

// ── Template tab switching ───────────────────────────────────────
let STATE_emTemplates = {};
function emTplTab(type, btn) {
  document.getElementById('em-tpl-type').value = type;
  document.querySelectorAll('.em-tpl-btn').forEach(b => {
    b.style.background = 'var(--bg)'; b.style.color = 'var(--text)'; b.style.border = '1.5px solid var(--border)';
  });
  if (btn) { btn.style.background = 'var(--teal)'; btn.style.color = '#fff'; btn.style.border = '1.5px solid var(--teal)'; }
  const tpl = STATE_emTemplates[type] || {};
  document.getElementById('em-tpl-subj').value = tpl.subject || '';
  document.getElementById('em-tpl-body').value = tpl.body    || '';
}

// ── Insert variable at cursor ────────────────────────────────────
function emInsertVar(v) {
  const ta = document.getElementById('em-tpl-body');
  if (!ta) return;
  const s = ta.selectionStart, e = ta.selectionEnd;
  ta.value = ta.value.slice(0, s) + v + ta.value.slice(e);
  ta.selectionStart = ta.selectionEnd = s + v.length;
  ta.focus();
}

// ── Load templates from API ──────────────────────────────────────
async function loadEmailTemplates() {
  try {
    const r = await api('api/email.php?action=templates');
    if (r.success) {
      STATE_emTemplates = {};
      (r.data || []).forEach(t => { STATE_emTemplates[t.type] = t; });
      // Populate current active tab
      const type = document.getElementById('em-tpl-type')?.value || 'invoice';
      const tpl  = STATE_emTemplates[type] || {};
      document.getElementById('em-tpl-subj').value = tpl.subject || '';
      document.getElementById('em-tpl-body').value = tpl.body    || '';
    }
  } catch(e) { console.error('loadEmailTemplates:', e); }
}

// ── Save template ────────────────────────────────────────────────
async function saveEmailTemplate() {
  const type    = document.getElementById('em-tpl-type')?.value;
  const subject = document.getElementById('em-tpl-subj')?.value.trim();
  const body    = document.getElementById('em-tpl-body')?.value.trim();
  if (!subject || !body) { toast('⚠️ Subject and body are required', 'warning'); return; }
  try {
    const r = await api('api/email.php', 'POST', { action:'save_template', type, subject, body });
    if (r.success) {
      STATE_emTemplates[type] = { type, subject, body };
      toast('✅ ' + type.charAt(0).toUpperCase() + type.slice(1) + ' template saved!', 'success');
    } else { toast('❌ ' + (r.error || 'Save failed'), 'error'); }
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

// ── Preview template ─────────────────────────────────────────────
async function emPreviewTemplate(invId) {
  const type = document.getElementById('em-tpl-type')?.value || 'invoice';
  toast('⏳ Building preview…', 'info');
  try {
    const r = await api('api/email.php', 'POST', { action:'preview', type, invoice_id: invId || 0 });
    if (r.success) {
      const modal = document.getElementById('em-preview-modal');
      const frame = document.getElementById('em-preview-frame');
      const subj  = document.getElementById('em-preview-subject');
      if (subj)  subj.textContent = r.subject || '';
      if (frame) { frame.srcdoc = r.html; }
      if (modal) { modal.style.display = 'flex'; }
    } else { toast('❌ ' + (r.error || 'Preview failed'), 'error'); }
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

// ── Load automation settings ─────────────────────────────────────
// ── Load automation settings ─────────────────────────────────────
function loadEmailAutoSettings() {
  const s = STATE.settings;
  const set = (id, val) => { const el = document.getElementById(id); if (el) { if (el.type === 'checkbox' || el.classList.contains('tog')) { if (val === '1' || val === true) el.classList.add('on'); else el.classList.remove('on'); } else { el.value = val || el.value; } } };
  set('em-auto-inv',      s.email_auto_inv);
  set('em-auto-est',      s.email_auto_est);
  set('em-auto-paid',     s.email_auto_paid);
  set('em-auto-partial',  s.email_auto_partial);
  set('em-auto-remind',   s.email_auto_remind);
  set('em-auto-overdue',  s.email_auto_overdue);
  set('em-auto-followup', s.email_auto_followup);
}

async function saveEmailAuto() {
  const togVal = id => document.getElementById(id)?.classList.contains('on') ? '1' : '0';
  const val    = id => document.getElementById(id)?.value || '';
  const payload = {
    email_auto_inv:     togVal('em-auto-inv'),
    email_auto_est:     togVal('em-auto-est'),
    email_auto_paid:    togVal('em-auto-paid'),
    email_auto_partial: togVal('em-auto-partial'),
    email_auto_remind:  togVal('em-auto-remind'),
    email_auto_overdue: togVal('em-auto-overdue'),
    email_auto_followup:togVal('em-auto-followup'),
  };
  Object.assign(STATE.settings, payload);
  try { await api('api/settings.php', 'POST', payload); } catch(e) {}
}

// ── Email log state ───────────────────────────────────────────────
window._emLogPage   = 1;
window._emLogStatus = '';
window._emLogType   = '';

function emLogPill(btn, group) {
  const cls = group === 'status' ? '.em-status-pill' : '.em-type-pill';
  document.querySelectorAll(cls).forEach(b => {
    b.style.background = 'var(--bg)';
    b.style.color      = 'var(--muted)';
    b.classList.remove('active');
  });
  btn.style.background = 'var(--teal)';
  btn.style.color      = '#fff';
  btn.classList.add('active');
  if (group === 'status') window._emLogStatus = btn.dataset.val;
  else                    window._emLogType   = btn.dataset.val;
  window._emLogPage = 1;
  loadEmailLogs();
}

function fmtEmailTime(raw) {
  if (!raw) return '—';
  let normalized = String(raw).trim();
  if (!normalized.includes('T') && !normalized.includes('+') && !normalized.includes('Z')) {
    normalized = normalized.replace(' ', 'T') + '+05:30';
  }
  const d = new Date(normalized);
  if (isNaN(d)) return raw;
  return d.toLocaleString('en-IN', {
    day:    '2-digit',
    month:  'short',
    year:   'numeric',
    hour:   '2-digit',
    minute: '2-digit',
    hour12: true,
    timeZone: 'Asia/Kolkata'
  });
}

async function loadEmailLogs(invId) {
  const container = document.getElementById('em-logs-table');
  if (!container) return;
  container.innerHTML = '<div style="color:var(--muted);text-align:center;padding:24px"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

  let url = 'api/email.php?action=logs&page=' + (window._emLogPage || 1);
  const status  = window._emLogStatus || '';
  const type    = window._emLogType   || '';
  const fromDt  = document.getElementById('em-log-from')?.value || '';
  const toDt    = document.getElementById('em-log-to')?.value   || '';
  if (invId)  url += '&invoice_id=' + invId;
  if (type)   url += '&type='   + encodeURIComponent(type);
  if (fromDt) url += '&from='   + fromDt;
  if (toDt)   url += '&to='     + toDt;
  const filterOpened = (status === 'opened');
  if (status && status !== 'opened') url += '&status=' + status;

  try {
    const r = await api(url);

    // ── Stats cards ───────────────────────────────────────────────
    const statsEl = document.getElementById('em-log-stats');
    if (statsEl && r.stats) {
      const s = r.stats;
      const openRate = s.total > 0 ? Math.round((s.opened / s.total) * 100) : 0;
      const card = (icon, color, bg, label, val, sub='') => `
        <div style="background:var(--card-bg,var(--bg));border:1px solid var(--border);border-radius:10px;padding:12px 14px;display:flex;align-items:center;gap:12px">
          <div style="width:38px;height:38px;border-radius:9px;background:${bg};color:${color};display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">
            <i class="fas ${icon}"></i>
          </div>
          <div>
            <div style="font-size:20px;font-weight:700;color:${color};line-height:1.1">${val}${sub ? '<span style="font-size:12px;color:var(--muted);font-weight:400;margin-left:4px">'+sub+'</span>' : ''}</div>
            <div style="font-size:11px;color:var(--muted);margin-top:2px">${label}</div>
          </div>
        </div>`;
      statsEl.innerHTML =
        card('fa-paper-plane','var(--text)','var(--bg2,#f0f0f0)','Total Sent', s.total||0) +
        card('fa-check-circle','#1E7E34','#EDFAF0','Delivered', s.sent||0) +
        card('fa-times-circle','#C0392B','#FEF0EF','Failed', s.failed||0) +
        card('fa-eye','#1565C0','#EEF5FF','Opened', s.opened||0, openRate+'%');
    }

    let data = r.data || [];
    if (filterOpened) data = data.filter(l => l.open_count > 0);

    if (!data.length) {
      container.innerHTML = '<div style="color:var(--muted);text-align:center;padding:32px;font-size:13px"><i class="fas fa-inbox" style="font-size:28px;opacity:.2;display:block;margin-bottom:8px"></i>No email logs found</div>';
      document.getElementById('em-log-pagination').style.display = 'none';
      return;
    }

    // ── Type badge config ─────────────────────────────────────────
    const typeBadge = {
      invoice:  { bg:'#F0EFFD', color:'#5B52C7', icon:'fa-file',               label:'Invoice'  },
      estimate: { bg:'#EEF5FF', color:'#2563EB', icon:'fa-file-alt',           label:'Estimate' },
      receipt:  { bg:'#EDFAF0', color:'#1E7E34', icon:'fa-check-circle',       label:'Receipt'  },
      reminder: { bg:'#FFF4E5', color:'#B45309', icon:'fa-bell',               label:'Reminder', alert:true },
      overdue:  { bg:'#FEF0EF', color:'#C0392B', icon:'fa-exclamation-triangle',label:'Overdue', alert:true },
      followup: { bg:'#FDF0F7', color:'#9D174D', icon:'fa-phone-alt',          label:'Follow-up',alert:true },
      test:     { bg:'#E0F2F1', color:'#00695C', icon:'fa-flask',              label:'Test'     },
    };

    const showSubject = window._emShowSubject !== false;

    const rows = data.map(log => {
      const tb = typeBadge[log.type] || { bg:'#F5F5F5', color:'#888', icon:'fa-envelope', label:log.type };
      const alertStyle = tb.alert ? `border-left:3px solid ${tb.color};border-radius:0 6px 6px 0;` : 'border-radius:6px;';
      const typePill = `<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 9px;${alertStyle}font-size:11px;font-weight:600;background:${tb.bg};color:${tb.color};white-space:nowrap">
        <i class="fas ${tb.icon}" style="font-size:10px"></i>${tb.label}</span>`;

      const sentTime = fmtEmailTime(log.sent_at || log.created_at);
      const relTime  = _emRelTime(log.sent_at || log.created_at);

      const statusCell = log.status === 'sent'
        ? `<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:6px;font-size:11px;font-weight:600;background:#EDFAF0;color:#1E7E34;white-space:nowrap"><i class="fas fa-check" style="font-size:10px"></i>Sent</span>`
        : `<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:6px;font-size:11px;font-weight:600;background:#FEF0EF;color:#C0392B;white-space:nowrap"><i class="fas fa-times" style="font-size:10px"></i>Failed</span>`;

      const openCell = log.open_count > 0
        ? `<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:6px;font-size:11px;font-weight:600;background:#EEF5FF;color:#1565C0;white-space:nowrap"><i class="fas fa-eye" style="font-size:10px"></i>${log.open_count} open${log.open_count>1?'s':''}</span>`
        : `<span style="font-size:11px;color:var(--muted)">—</span>`;

      const errTip   = log.error_msg ? ` title="${String(log.error_msg).replace(/"/g,'&quot;')}"` : '';
      const retryBtn = log.status === 'failed'
        ? `<button onclick="retryEmail(${log.id},${log.invoice_id||0},'${log.type}','${(log.to_email||'').replace(/'/g,'')}')"
             style="display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:6px;border:1px solid #FACACA;background:#FEF0EF;color:#C0392B;font-size:11px;cursor:pointer;margin-left:6px">
             <i class="fas fa-redo" style="font-size:10px"></i> Retry</button>` : '';

      const subjectCol = showSubject
        ? `<td style="padding:9px 8px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted);font-size:12px" title="${(log.subject||'').replace(/"/g,'&quot;')}">${log.subject||'—'}</td>`
        : '';

      return `<tr style="border-bottom:1px solid var(--border)">
        <td style="padding:9px 8px;white-space:nowrap;min-width:130px">
          <div style="font-size:12px;font-weight:600;color:var(--text)">${relTime}</div>
          <div style="font-size:11px;color:var(--muted);font-family:var(--mono);margin-top:1px">${sentTime}</div>
        </td>
        <td style="padding:9px 8px;white-space:nowrap">${typePill}</td>
        <td style="padding:9px 8px;max-width:180px">
          <div style="font-size:13px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${log.to_name || '—'}</div>
          <div style="font-size:11px;color:var(--muted);font-family:var(--mono);margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${log.to_email||''}</div>
        </td>
        ${subjectCol}
        <td style="padding:9px 8px;white-space:nowrap"${errTip}>${statusCell}${retryBtn}</td>
        <td style="padding:9px 8px">${openCell}</td>
      </tr>`;
    }).join('');

    const subjectHeader = showSubject ? '<th style="padding:8px;text-align:left;font-weight:700">Subject</th>' : '';
    container.innerHTML = `<table style="width:100%;border-collapse:collapse">
      <thead><tr style="background:var(--bg);font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1.5px solid var(--border)">
        <th style="padding:8px;text-align:left;font-weight:700">Sent</th>
        <th style="padding:8px;text-align:left;font-weight:700">Type</th>
        <th style="padding:8px;text-align:left;font-weight:700">Recipient</th>
        ${subjectHeader}
        <th style="padding:8px;text-align:left;font-weight:700">Status</th>
        <th style="padding:8px;text-align:left;font-weight:700">Opened</th>
      </tr></thead><tbody>${rows}</tbody></table>`;

    // ── Pagination ────────────────────────────────────────────────
    const total = r.total || 0;
    const pages = r.pages || 1;
    const page  = r.page  || 1;
    const pgEl  = document.getElementById('em-log-pagination');
    const pgInfo = document.getElementById('em-log-page-info');
    const pgBtns = document.getElementById('em-log-page-btns');
    if (pgEl && total > 25) {
      pgEl.style.display = 'flex';
      pgInfo.textContent = `Showing ${((page-1)*25)+1}–${Math.min(page*25,total)} of ${total}`;
      const btnStyle = (disabled) => `padding:4px 10px;border-radius:6px;border:1px solid var(--border);background:var(--bg);color:${disabled?'var(--muted)':'var(--text)'};cursor:${disabled?'default':'pointer'};font-size:13px;opacity:${disabled?'0.4':'1'}`;
      let btns = `<button onclick="if(window._emLogPage>1){window._emLogPage--;loadEmailLogs();}" style="${btnStyle(page<=1)}" ${page<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
      btns += `<span style="font-size:12px;color:var(--muted);padding:0 8px">Page ${page} of ${pages}</span>`;
      btns += `<button onclick="if(window._emLogPage<${pages}){window._emLogPage++;loadEmailLogs();}" style="${btnStyle(page>=pages)}" ${page>=pages?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
      pgBtns.innerHTML = btns;
    } else if (pgEl) {
      pgEl.style.display = 'none';
    }
  } catch(e) {
    container.innerHTML = '<div style="color:#C62828;padding:24px;text-align:center"><i class="fas fa-exclamation-circle"></i> Error loading logs: ' + e.message + '</div>';
  }
}

// ── Subject toggle ─────────────────────────────────────────────────
function toggleEmailSubject(btn) {
  window._emShowSubject = (window._emShowSubject === false) ? true : false;
  btn.innerHTML = window._emShowSubject === false
    ? '<i class="fas fa-eye"></i> Show Subject'
    : '<i class="fas fa-eye-slash"></i> Hide Subject';
  loadEmailLogs();
}

// ── IST-aware relative time ────────────────────────────────────────
function _emRelTime(raw) {
  if (!raw) return '—';
  let normalized = String(raw).trim();
  if (!normalized.includes('T') && !normalized.includes('+') && !normalized.includes('Z')) {
    normalized = normalized.replace(' ','T') + '+05:30';
  }
  const d = new Date(normalized);
  if (isNaN(d)) return raw;
  const diff = Math.floor((Date.now() - d) / 1000);
  if (diff < 60)     return 'just now';
  if (diff < 3600)   return Math.floor(diff/60) + ' min ago';
  if (diff < 86400)  return Math.floor(diff/3600) + ' hr ago';
  if (diff < 604800) return Math.floor(diff/86400) + ' days ago';
  return d.toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric', timeZone:'Asia/Kolkata' });
}


async function retryEmail(logId, invId, type, toEmail) {
  if (!toEmail) { toast('⚠️ No recipient email on record','warning'); return; }
  try {
    const r = await api('api/email.php','POST',{ action:'send', invoice_id: invId, type, to: toEmail });
    if (r?.success) { toast('📧 Email resent successfully!','success'); loadEmailLogs(); }
    else            toast('❌ Retry failed: ' + (r?.error||'Unknown error'), 'error');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function exportEmailLogsCsv() {
  const rows = document.querySelectorAll('#em-logs-table tbody tr');
  if (!rows.length) { toast('No logs to export','warning'); return; }
  const headers = ['Type','To','Subject','Status','Opened','Sent At'];
  const lines = [headers.join(',')];
  rows.forEach(tr => {
    const cells = tr.querySelectorAll('td');
    const vals = Array.from(cells).map(td => '"' + td.innerText.replace(/"/g,'""').replace(/\n/g,' ').trim() + '"');
    lines.push(vals.join(','));
  });
  const blob = new Blob([lines.join('\n')], {type:'text/csv'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'email_logs_' + new Date().toISOString().slice(0,10) + '.csv';
  a.click();
}

// ── Load SMTP profiles ───────────────────────────────────────────
async function loadSmtpProfiles() {
  const container = document.getElementById('em-profiles-list');
  if (!container) return;
  try {
    const r = await api('api/email.php?action=smtp_profiles');
    if (!r.success || !r.data?.length) {
      container.innerHTML = '<div style="color:var(--muted);text-align:center;padding:32px">No profiles yet. Click Add Profile.</div>';
      window._smtpProfileMap = {};
      return;
    }
    // Store profiles in a map — avoids JSON.stringify in onclick (breaks on double quotes in HTML attrs)
    window._smtpProfileMap = {};
    r.data.forEach(p => { window._smtpProfileMap[p.id] = p; });
    const rows = r.data.map(p => `
      <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border)">
        <div style="flex:1">
          <div style="font-weight:700;font-size:14px">${p.name} ${p.is_default ? '<span style="background:var(--teal);color:#fff;padding:1px 8px;border-radius:20px;font-size:10px;font-weight:700">DEFAULT</span>' : ''}</div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px">${p.host}:${p.port} · ${p.from_email}</div>
          ${p.has_password ? '<div style="font-size:10px;color:var(--green);margin-top:2px">🔐 Password saved</div>' : ''}
        </div>
        <button onclick="emEditProfile(${p.id})" style="padding:5px 12px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);font-size:12px;cursor:pointer"><i class="fas fa-edit"></i></button>
        <button onclick="delSmtpProfile(${p.id})" style="padding:5px 12px;border-radius:8px;border:1.5px solid #FFCDD2;background:#FFEBEE;color:#C62828;font-size:12px;cursor:pointer"><i class="fas fa-trash"></i></button>
      </div>`).join('');
    container.innerHTML = rows;
  } catch(e) { container.innerHTML = '<div style="color:#C62828;padding:24px">Error: ' + e.message + '</div>'; }
}

function emNewProfile() {
  const f = document.getElementById('em-profile-form');
  if (!f) return;
  ['ep-id','ep-name','ep-host','ep-user','ep-pass','ep-from','ep-fname','ep-apikey'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = '';
    el.style.borderColor = '';
    // Restore defaults — don't wipe placeholders
    const defaults = {
      'ep-name':  'e.g. Gmail SMTP',
      'ep-host':  'smtp.gmail.com',
      'ep-user':  'your@gmail.com',
      'ep-pass':  'Enter password or app password',
      'ep-from':  'noreply@yourdomain.com',
      'ep-fname': 'Your Company',
      'ep-apikey':'SG.xxxx or key-xxxx',
    };
    el.placeholder = defaults[id] || '';
  });
  document.getElementById('ep-port').value    = '587';
  document.getElementById('ep-default').checked = false;
  document.getElementById('em-profile-form-title').textContent = 'New SMTP Profile';
  f.style.display = '';
  f.scrollIntoView({ behavior:'smooth' });
}

function emEditProfile(idOrObj) {
  const p = (typeof idOrObj === 'object') ? idOrObj : (window._smtpProfileMap?.[idOrObj] || null);
  if (!p) { toast('Profile data not found — try refreshing', 'error'); return; }
  const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v || ''; };
  set('ep-id',    p.id);
  set('ep-name',  p.name);
  set('ep-host',  p.host);
  set('ep-port',  p.port);
  set('ep-user',  p.username);
  // Password is never returned from API for security.
  // Leave blank — backend only updates it if a new value is entered.
  set('ep-pass',  '');
  const passEl = document.getElementById('ep-pass');
  if (passEl) {
    passEl.placeholder = p.has_password ? '••••••  (saved — leave blank to keep)' : 'Enter password';
    passEl.style.borderColor = p.has_password ? 'var(--green)' : '';
  }
  set('ep-from',  p.from_email);
  set('ep-fname', p.from_name);
  set('ep-apikey',p.api_key || '');
  const prov = document.getElementById('ep-provider'); if (prov) prov.value = p.provider || 'smtp';
  const def  = document.getElementById('ep-default');  if (def)  def.checked = !!p.is_default;
  document.getElementById('em-profile-form-title').textContent = 'Edit Profile: ' + p.name;
  const f = document.getElementById('em-profile-form');
  if (f) { f.style.display = ''; f.scrollIntoView({ behavior:'smooth' }); }
}

async function saveSmtpProfile() {
  const val = id => document.getElementById(id)?.value.trim() || '';
  const payload = {
    id:         val('ep-id') || null,
    name:       val('ep-name'),
    host:       val('ep-host'),
    port:       val('ep-port') || '587',
    username:   val('ep-user'),
    password:   val('ep-pass'),
    from_email: val('ep-from'),
    from_name:  val('ep-fname'),
    api_key:    val('ep-apikey'),
    provider:   document.getElementById('ep-provider')?.value || 'smtp',
    is_default: document.getElementById('ep-default')?.checked ? 1 : 0,
  };
  if (!payload.name || !payload.host || !payload.username) { toast('⚠️ Name, Host and Username are required', 'warning'); return; }
  try {
    const r = await api('api/email.php', 'POST', { action:'save_profile', ...payload });
    if (r.success) {
      toast('✅ Profile saved!', 'success');
      document.getElementById('em-profile-form').style.display = 'none';
      loadSmtpProfiles();
    } else { toast('❌ ' + (r.error || 'Save failed'), 'error'); }
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

async function delSmtpProfile(id) {
  const _smtpResult = await Swal.fire({ title: 'Delete SMTP Profile?', text: 'This cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', cancelButtonText: 'Cancel', confirmButtonColor: '#E53935', customClass: { popup: 'swal-compact' } });
  if (!_smtpResult.isConfirmed) return;
  try {
    await fetch('api/email.php?action=del_profile&id=' + id, { method:'DELETE', headers:{ 'X-Requested-With':'XMLHttpRequest' } });
    loadSmtpProfiles();
    toast('🗑️ Profile deleted', 'success');
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function emProfileProviderChange() {
  const p = document.getElementById('ep-provider')?.value;
  const presets = { gmail:{ host:'smtp.gmail.com', port:'587' }, outlook:{ host:'smtp.office365.com', port:'587' }, sendgrid:{ host:'smtp.sendgrid.net', port:'587' }, mailgun:{ host:'smtp.mailgun.org', port:'587' } };
  if (presets[p]) { document.getElementById('ep-host').value = presets[p].host; document.getElementById('ep-port').value = presets[p].port; }
}

// ── Send email with preview from invoice ────────────────────────
async function sendEmailFromInvoice(invId, type, to, toName) {
  if (!to) { toast('⚠️ No email address on file for this client', 'warning'); return; }
  const ec = STATE.settings;
  // Warn but do NOT redirect — reminder flows should not navigate away unexpectedly
  if (!ec.smtp_host || !ec.smtp_user) {
    toast('⚠️ SMTP not configured — go to Email Setup to enable email sending', 'warning');
    return;
  }
  toast('📧 Sending ' + type + ' email to ' + toName + '…', 'info');
  try {
    const r = await api('api/email.php', 'POST', { action:'send', type, invoice_id: invId, to, to_name: toName });
    if (r.success) {
      toast('✅ Email sent to ' + to + '!', 'success');
    } else {
      toast('❌ Send failed: ' + (r.error || 'Unknown error'), 'error');
    }
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

async function saveEmailSettings() {
  const payload = {
    smtp_host:     document.getElementById('em-host')?.value.trim() || '',
    smtp_port:     document.getElementById('em-port')?.value.trim() || '587',
    smtp_user:     document.getElementById('em-user')?.value.trim() || '',
    smtp_pass:     document.getElementById('em-pass')?.value.trim() || '',
    smtp_from:     document.getElementById('em-from')?.value.trim() || '',
    smtp_name:     document.getElementById('em-name')?.value.trim() || '',
    email_subject: document.getElementById('em-subj')?.value.trim() || '',
    email_body:    document.getElementById('em-body')?.value.trim() || '',
    // MPA FIX: the old SPA read these via `.tog:first-of-type` /
    // `.tog:last-of-type` scoped to #page-email-setup — but since each
    // .tog sits alone under its own .toggle-item wrapper, EVERY .tog on
    // the page independently matches both of those pseudo-classes, so
    // querySelector() always returned the very first .tog in the whole
    // page (#em-tog-cc) for both lookups. That means email_cc_self was
    // reading the right element by accident, but email_attach_pdf never
    // had a real corresponding toggle anywhere in this page's HTML — it
    // was silently mirroring #em-tog-cc's state too. There's genuinely
    // no "Attach PDF" control in this UI; sending '1' (always attach)
    // here as a sane default. Add a real toggle for it if you want this
    // configurable — happy to wire it up.
    email_attach_pdf: '1',
    email_cc_self:    document.getElementById('em-tog-cc')?.classList.contains('on') ? '1' : '0',
  };
  // Validate required fields
  if (!payload.smtp_host) { toast('⚠️ SMTP Host is required', 'warning'); return; }
  if (!payload.smtp_user) { toast('⚠️ Username is required', 'warning'); return; }
  if (!payload.smtp_pass) { toast('⚠️ App Password is required', 'warning'); return; }
  if (!payload.smtp_from) { toast('⚠️ From Email is required', 'warning'); return; }
  Object.assign(STATE.settings, payload);
  try {
    await api('api/settings.php', 'POST', payload);
    toast('✅ Email settings saved!', 'success');
  } catch(e) {
    toast('❌ Failed to save: ' + e.message, 'error');
  }
}

async function testEmail() {
  const host = document.getElementById('em-host')?.value.trim();
  const user = document.getElementById('em-user')?.value.trim();
  const pass = document.getElementById('em-pass')?.value.trim();
  const from = document.getElementById('em-from')?.value.trim();
  const name = document.getElementById('em-name')?.value.trim();
  const port = document.getElementById('em-port')?.value.trim() || '587';
  if (!host || !user || !pass || !from) {
    toast('⚠️ Fill in all SMTP fields before testing', 'warning');
    return;
  }
  toast('📧 Sending test email…', 'info');
  try {
    const r = await api('api/email.php', 'POST', {
      action:   'test',
      smtp_host: host, smtp_port: port,
      smtp_user: user, smtp_pass: pass,
      smtp_from: from, smtp_name: name,
      to: user  // send test to self
    });
    if (r.success) {
      toast('✅ Test email sent to ' + user + '! Check your inbox.', 'success');
    } else {
      toast('❌ Test failed: ' + (r.error || 'Unknown error'), 'error');
    }
  } catch(e) {
    toast('❌ SMTP error: ' + e.message, 'error');
  }
}
