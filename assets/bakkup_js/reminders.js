// ================================================================
//  assets/js/reminders.js
//  Requires: common.js, shared-data.js, wa-shared.js (loaded before
//  this file).
//
//  MPA CHANGES from the SPA version:
//  1. This page's data (reminder log, promises, timing settings)
//     used to be part of one giant loadAllData() Promise.allSettled
//     call. loadRemindersData() below fetches just this page's slice
//     via api/reminders.php (returns {log, promises, settings}).
//  2. openPromiseModal/closePromiseModal/savePromise were defined
//     TWICE identically in the original file (leftover duplicate
//     code) — only one copy is kept here.
//  3. sendReminderNow()/sendBalanceReminder()/sendPromiseReminder()
//     call sendEmailFromInvoice() for the email channel — that
//     belongs to email_setup.php's SMTP subsystem, not built yet.
//     Guarded with typeof; email channel silently no-ops until then,
//     WhatsApp channel works fully now that wa-shared.js exists.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['invoices', 'clients', 'payments', 'settings']);
  await loadRemindersData();
  renderReminders();
  checkDuePromises();
});

async function loadRemindersData() {
  try {
    const r = await api('api/reminders.php');
    if (r?.log) STATE.reminders = r.log.map(row => ({
      id: row.id, ts: row.sent_at, invNum: row.invoice_num, clientName: row.client_name,
      type: row.type, channel: row.channel, status: row.status, message: row.message || '',
    }));
    if (r?.promises) STATE.promises = r.promises.map(p => ({
      id: p.id, invoiceId: p.invoice_id, invNum: p.invoice_num, clientName: p.client_name,
      promiseDate: p.promise_date, amount: parseFloat(p.amount || 0), note: p.note || '',
      channel: p.channel, status: p.status, remindedAt: p.reminded_at,
    }));
    if (r?.settings) STATE._remSettings = r.settings;
  } catch (e) {
    console.warn('[Reminders] loadRemindersData failed:', e.message);
  }
}

function getReminderSettings() {
  const s = STATE._remSettings || {};
  const wa = STATE.settings.wa || {};
  return {
    beforeDays: parseInt(s.before_days ?? s.beforeDays ?? wa.remind_days ?? 3),
    onDue: (s.on_due ?? s.onDue ?? 1) == 1,
    overdueFreq: parseInt(s.overdue_freq ?? s.overdueFreq ?? wa.followup_days ?? 7),
    maxOverdue: parseInt(s.max_overdue ?? s.maxOverdue ?? wa.max_followup ?? 3),
    channel: s.channel || (STATE.settings && STATE.settings.channel) || 'whatsapp',
    sendHour: parseInt(s.send_hour ?? 9),
    sendMinute: parseInt(s.send_minute ?? 0),
  };
}

async function saveReminderSettings() {
  const payload = {
    before_days: parseInt(document.getElementById('rem-before-days')?.value) || 3,
    on_due: document.getElementById('rem-on-due')?.value === '1' ? 1 : 0,
    overdue_freq: parseInt(document.getElementById('rem-overdue-freq')?.value) || 7,
    max_overdue: parseInt(document.getElementById('rem-max-overdue')?.value) || 3,
    channel: document.getElementById('rem-channel')?.value || 'whatsapp',
    send_hour: parseInt(document.getElementById('rem-send-hour')?.value) || 9,
    send_minute: parseInt(document.getElementById('rem-send-minute')?.value) || 0,
  };
  try {
    await api('api/reminders.php', 'POST', payload);
    await api('api/settings.php', 'POST', payload);
    STATE._remSettings = payload;
    const wfl = document.getElementById('wa-followup-days-label');
    if (wfl) wfl.textContent = payload.overdue_freq || 7;
    _updateCronHint(payload.send_hour, payload.send_minute);
    toast('✅ Reminder rules saved', 'success');
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

function renderReminders() {
  const cfg = getReminderSettings();
  const set = (id, v) => { const e = document.getElementById(id); if (e) e.value = v; };
  set('rem-before-days', cfg.beforeDays || 3);
  set('rem-on-due', cfg.onDue === false ? '0' : '1');
  set('rem-overdue-freq', cfg.overdueFreq || 7);
  set('rem-max-overdue', cfg.maxOverdue || 3);
  set('rem-channel', cfg.channel || 'whatsapp');
  set('rem-send-hour', cfg.sendHour || 9);
  set('rem-send-minute', cfg.sendMinute || 0);
  _updateCronHint(cfg.sendHour || 9, cfg.sendMinute || 0);
  const wfl = document.getElementById('wa-followup-days-label');
  if (wfl) wfl.textContent = cfg.overdueFreq || 7;
  _buildReminderQueue();
  _renderPromiseTracker();
  _renderReminderHistory();
  _buildHealthCheck();
}

function _buildReminderQueue() {
  const el = document.getElementById('rem-queue-cards');
  if (!el) return;
  const cfg = getReminderSettings();
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const queue = [];

  const _queueOverdueCount = {};
  const _now = new Date();
  const _todayStr = _now.getFullYear() + '-' + String(_now.getMonth() + 1).padStart(2, '0') + '-' + String(_now.getDate()).padStart(2, '0');
  const _skippedTodayNums = new Set();
  (STATE.reminders || []).forEach(entry => {
    if ((entry.type === 'overdue' || entry.type === 'Overdue Alert') && entry.invNum && entry.status === 'sent') {
      _queueOverdueCount[entry.invNum] = (_queueOverdueCount[entry.invNum] || 0) + 1;
    }
    if (entry.status === 'skipped' && entry.invNum) {
      const entryDate = (entry.ts || '').slice(0, 10);
      if (entryDate === _todayStr) _skippedTodayNums.add(entry.invNum);
    }
  });

  STATE.invoices.forEach(inv => {
    if (['Paid', 'Cancelled', 'Draft'].includes(inv.status)) return;
    const c = STATE.clients.find(x => String(x.id) === String(inv.client)) || {};
    const due = inv.due ? new Date(inv.due) : null;
    if (!due) return;
    due.setHours(0, 0, 0, 0);
    const daysUntilDue = Math.floor((due - today) / 864e5);
    const daysOverdue = -daysUntilDue;
    const invNum = inv.num || inv.invoice_number || '';
    if (_skippedTodayNums.has(invNum)) return;

    if (inv.status === 'Overdue' || daysOverdue > 0) {
      if ((_queueOverdueCount[invNum] || 0) >= (cfg.maxOverdue || 3)) return;
      const _lastOvSent = (STATE.reminders || [])
        .filter(e => e.invNum === invNum && (e.type === 'overdue' || e.type === 'Overdue Alert') && e.status === 'sent')
        .map(e => new Date((e.ts || '').replace(' ', 'T') + 'Z'))
        .filter(d => !isNaN(d)).sort((a, b) => b - a)[0];
      if (_lastOvSent) {
        const _daysSince = Math.floor((today - _lastOvSent) / 864e5);
        if (_daysSince < (cfg.overdueFreq || 7)) return;
      }
      const _activePtp = (STATE.promises || []).find(p => String(p.invoiceId) === String(inv.id) && p.status === 'pending' && new Date(p.promiseDate + 'T00:00:00') >= today);
      if (_activePtp) return;
      queue.push({ inv, client: c, type: 'overdue', urgency: 'high', label: `${daysOverdue}d overdue`, msg: `Overdue reminder for ${invNum}` });
    } else if (daysUntilDue === 0) {
      queue.push({ inv, client: c, type: 'due_today', urgency: 'medium', label: 'Due today', msg: `Payment due today for ${invNum}` });
    } else if (daysUntilDue <= (cfg.beforeDays || 3)) {
      queue.push({ inv, client: c, type: 'due_soon', urgency: 'low', label: `Due in ${daysUntilDue}d`, msg: `Due soon reminder for ${invNum}` });
    }
  });

  const badge = document.getElementById('badge-reminders');
  if (badge) { badge.textContent = queue.length; badge.style.display = queue.length ? '' : 'none'; }
  const waQueuedPill = document.getElementById('waQueuedPill');
  const waQueuedCount = document.getElementById('waQueuedCount');
  if (waQueuedPill && waQueuedCount) {
    waQueuedCount.textContent = queue.length;
    waQueuedPill.style.display = queue.length ? 'inline-flex' : 'none';
  }

  const statsEl = document.getElementById('rem-queue-stats');
  if (statsEl) {
    const overdueCnt = queue.filter(q => q.urgency === 'high').length;
    const todayCnt = queue.filter(q => q.urgency === 'medium').length;
    const upcomingCnt = queue.filter(q => q.urgency === 'low').length;
    const card = (ico, bg, bdr, clr, label, val) => `<div style="background:var(--bg2,var(--bg));border:1px solid ${bdr};border-radius:8px;padding:10px 12px;display:flex;align-items:center;gap:10px">
        <div style="width:32px;height:32px;border-radius:7px;background:${bg};color:${clr};border:1px solid ${bdr};display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0"><i class="fas ${ico}"></i></div>
        <div><div style="font-size:18px;font-weight:700;color:${clr};line-height:1">${val}</div><div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-top:2px">${label}</div></div>
      </div>`;
    statsEl.innerHTML = card('fa-exclamation-triangle', '#FEF0EF', '#F7C1C1', '#C0392B', 'Overdue', overdueCnt) + card('fa-calendar-day', '#FFF4E5', '#FBBF24', '#B45309', 'Due Today', todayCnt) + card('fa-clock', '#EEF5FF', '#B5D4F4', '#185FA5', 'Upcoming', upcomingCnt);
  }

  if (!queue.length) {
    el.innerHTML = `<div style="text-align:center;padding:30px;color:var(--muted)"><i class="fas fa-check-circle" style="font-size:28px;color:#1E7E34;opacity:.4;display:block;margin-bottom:8px"></i><div style="font-weight:600;color:var(--text);margin-bottom:4px">All caught up!</div><div style="font-size:12px">No reminders pending right now.</div></div>`;
    return;
  }

  const overdueBatch = queue.filter(q => q.urgency === 'high');
  const todayBatch = queue.filter(q => q.urgency === 'medium');
  const upcomingBatch = queue.filter(q => q.urgency === 'low');

  const qRow = (q) => {
    const ch = getReminderSettings().channel || 'whatsapp';
    const phone = (q.client.wa || q.client.whatsapp || q.client.phone || q.inv.client_wa || q.inv.client_phone || '').replace(/\D/g, '');
    const email = q.client.email || q.client.mail || q.inv.client_email || '';
    const hasContact = !!(phone || email);
    const chIcon = ch === 'email' ? 'fas fa-envelope' : ch === 'both' ? 'fas fa-paper-plane' : 'fab fa-whatsapp';
    const col = q.urgency === 'high' ? '#C0392B' : q.urgency === 'medium' ? '#B45309' : '#185FA5';
    const bgPill = q.urgency === 'high' ? '#FEF0EF' : q.urgency === 'medium' ? '#FFF4E5' : '#EEF5FF';
    const _CLIENT_COLORS = ['#E8F4FD', '#E8F5E9', '#FFF8E1', '#F3E5F5', '#FCE4EC', '#E0F7FA', '#FFF3E0', '#EDE7F6', '#E1F5FE', '#F9FBE7'];
    const _clientKey = String(q.client.id || q.inv.client || q.client.name || '');
    let _cIdx = 0;
    if (_clientKey) { for (let _ci = 0; _ci < _clientKey.length; _ci++) _cIdx = (_cIdx * 31 + _clientKey.charCodeAt(_ci)) & 0xff; _cIdx = _cIdx % _CLIENT_COLORS.length; }
    const rowBg = _clientKey ? `background:${_CLIENT_COLORS[_cIdx]};` : '';
    const pmts = (STATE.payments || []).filter(pp => String(pp.invoice_id) === String(q.inv.id));
    const paid = pmts.reduce((s, pp) => s + parseFloat(pp.amount || 0), 0);
    const total = parseFloat(q.inv.grand_total || q.inv.amount || 0);
    const remaining = Math.max(0, total - paid);
    const amtStr = paid > 0
      ? `<span style="font-size:13px;font-weight:700;color:#B45309;font-family:var(--mono)">₹${remaining.toLocaleString('en-IN')}</span><div style="font-size:10px;color:var(--muted)">due of ${fmt_money(total)}</div>`
      : `<span style="font-size:14px;font-weight:700;font-family:var(--mono)">${fmt_money(total)}</span>`;
    const _invNumKey = q.inv.num || q.inv.invoice_number || '';
    const _remCount = (STATE.reminders || []).filter(e => e.invNum === _invNumKey && e.status === 'sent').length;
    const _remPill = _remCount > 0 ? `<div style="font-size:9px;color:#6D28D9;font-weight:700;margin-top:2px"><i class="fas fa-bell" style="font-size:8px"></i> ${_remCount} sent</div>` : '';
    let nextRemCell = '—';
    const _sendHour = cfg.sendHour ?? 9;
    const _sendMin = cfg.sendMinute ?? 0;
    const _sendLabel = `${String(_sendHour).padStart(2, '0')}:${String(_sendMin).padStart(2, '0')}`;
    if (q.urgency === 'high') {
      const _lastSent = (STATE.reminders || [])
        .filter(e => e.invNum === (q.inv.num || q.inv.invoice_number) && ['overdue', 'followup', 'Overdue Alert'].includes(e.type) && e.status === 'sent')
        .map(e => new Date((e.ts || '').replace(' ', 'T'))).filter(d => !isNaN(d)).sort((a, b) => b - a)[0];
      if (_lastSent) {
        const _next = new Date(_lastSent);
        _next.setDate(_next.getDate() + (cfg.overdueFreq || 7));
        _next.setHours(_sendHour, _sendMin, 0, 0);
        const _isToday = _next.toDateString() === new Date().toDateString();
        const _isPast = _next < new Date();
        const _d = _next.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
        const _col = _isPast ? '#C0392B' : _isToday ? '#B45309' : '#1565C0';
        const _bg = _isPast ? '#FEF0EF' : _isToday ? '#FFF4E5' : '#E3F2FD';
        const _lbl = _isPast ? 'Overdue now' : _isToday ? `Today ${_sendLabel}` : `${_d} ${_sendLabel}`;
        nextRemCell = `<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:8px;background:${_bg};color:${_col};white-space:nowrap">${_lbl}</span>`;
      } else {
        nextRemCell = `<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:8px;background:#FEF0EF;color:#C0392B;white-space:nowrap">Now</span>`;
      }
    } else if (q.urgency === 'medium') {
      nextRemCell = `<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:8px;background:#FFF4E5;color:#B45309;white-space:nowrap">Today ${_sendLabel}</span>`;
    } else {
      const _dueD = q.inv.due ? new Date(q.inv.due) : null;
      if (_dueD) {
        _dueD.setHours(_sendHour, _sendMin, 0, 0);
        const _d = _dueD.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
        nextRemCell = `<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:8px;background:#E3F2FD;color:#1565C0;white-space:nowrap">${_d} ${_sendLabel}</span>`;
      }
    }
    return `<tr style="${rowBg}border-bottom:1px solid var(--border)">
      <td style="padding:8px 10px;font-family:var(--mono);font-size:12px;font-weight:700;white-space:nowrap">${q.inv.num || q.inv.invoice_number || '—'}${_remPill}</td>
      <td style="padding:8px 6px;font-size:12px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${q.client.name || q.inv.clientName || q.inv.client_name || 'One-Time'}</td>
      <td style="padding:8px 6px;white-space:nowrap">
        <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:8px;background:${bgPill};color:${col}">${q.label}</span>
        <div style="font-size:10px;color:var(--muted);margin-top:1px">${q.inv.due || '—'}</div>
      </td>
      <td style="padding:8px 6px">${amtStr}</td>
      <td style="padding:8px 6px;white-space:nowrap">${nextRemCell}</td>
      <td style="padding:8px 6px;white-space:nowrap">
        <div style="display:flex;gap:4px;align-items:center">
          ${hasContact ? `<button onclick="sendReminderNow('${q.inv.id}','${ch}','${q.type}')" style="display:inline-flex;align-items:center;gap:3px;padding:3px 9px;background:#25D36615;color:#1a7a3c;border:1px solid #25D36635;border-radius:6px;cursor:pointer;font-size:11px;font-weight:600;white-space:nowrap"><i class="${chIcon}" style="font-size:10px"></i> Send</button>` : `<span style="font-size:10px;color:var(--muted);padding:3px 6px">No contact</span>`}
          <button onclick="openPromiseModal('${q.inv.id}')" style="padding:3px 8px;background:#EDE9FE;color:#6D28D9;border:1px solid #C4B5FD;border-radius:6px;cursor:pointer;font-size:11px;font-weight:600" title="Record promise to pay">🤝</button>
          <button onclick="sendReminderNow('${q.inv.id}','skip')" style="padding:3px 8px;background:var(--bg);color:var(--muted);border:1px solid var(--border);border-radius:6px;cursor:pointer;font-size:11px" title="Skip this reminder">Skip</button>
        </div>
      </td>
    </tr>`;
  };

  const qSection = (id, title, items, defaultOpen) => {
    if (!items.length) return '';
    const isOpen = window['_qOpen_' + id] !== undefined ? window['_qOpen_' + id] : defaultOpen;
    const dotCol = id === 'overdue' ? '#C0392B' : id === 'today' ? '#B45309' : '#185FA5';
    const chevron = isOpen ? 'fa-chevron-down' : 'fa-chevron-right';
    return `<div style="margin-bottom:6px">
      <div onclick="window['_qOpen_${id}']=!(window['_qOpen_${id}']!==undefined?window['_qOpen_${id}']:${defaultOpen});_buildReminderQueue()"
           style="display:flex;align-items:center;gap:8px;padding:7px 12px;cursor:pointer;user-select:none;background:var(--bg2,var(--bg));border-radius:8px;border:1px solid var(--border);margin-bottom:2px">
        <i class="fas ${chevron}" style="font-size:10px;color:var(--muted);width:10px"></i>
        <span style="width:8px;height:8px;border-radius:50%;background:${dotCol};display:inline-block;flex-shrink:0"></span>
        <span style="font-size:12px;font-weight:700;color:var(--text)">${title}</span>
        <span style="font-size:11px;padding:1px 7px;border-radius:8px;background:${dotCol}18;color:${dotCol};font-weight:700;margin-left:2px">${items.length}</span>
        <button onclick="event.stopPropagation();items_${id}.forEach(q=>sendReminderNow(q.inv.id,getReminderSettings().channel||'whatsapp',q.type))"
                style="margin-left:auto;padding:2px 10px;background:${dotCol}12;color:${dotCol};border:1px solid ${dotCol}30;border-radius:6px;cursor:pointer;font-size:10px;font-weight:700">Send All</button>
      </div>
      ${isOpen ? `<div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead><tr style="background:var(--bg2,var(--bg));border-bottom:2px solid var(--border)">
            <th style="padding:5px 10px;font-size:10px;font-weight:700;color:var(--muted);text-align:left;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap">Invoice</th>
            <th style="padding:5px 6px;font-size:10px;font-weight:700;color:var(--muted);text-align:left;text-transform:uppercase;letter-spacing:.4px">Client</th>
            <th style="padding:5px 6px;font-size:10px;font-weight:700;color:var(--muted);text-align:left;text-transform:uppercase;letter-spacing:.4px">Status</th>
            <th style="padding:5px 6px;font-size:10px;font-weight:700;color:var(--muted);text-align:left;text-transform:uppercase;letter-spacing:.4px">Amount</th>
            <th style="padding:5px 6px;font-size:10px;font-weight:700;color:var(--muted);text-align:left;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap">Next Reminder</th>
            <th style="padding:5px 6px;font-size:10px;font-weight:700;color:var(--muted);text-align:left;text-transform:uppercase;letter-spacing:.4px">Actions</th>
          </tr></thead>
          <tbody>${items.map(q => qRow(q)).join('')}</tbody>
        </table>
      </div>` : ''}
    </div>`;
  };

  window.items_overdue = overdueBatch;
  window.items_today = todayBatch;
  window.items_upcoming = upcomingBatch;

  el.innerHTML = qSection('overdue', '⚠ Overdue', overdueBatch, true) + qSection('today', '📅 Due Today', todayBatch, false) + qSection('upcoming', '🗓 Upcoming', upcomingBatch, false);
}

function sendReminderNow(invId, channel, qtype) {
  const inv = STATE.invoices.find(i => String(i.id) === String(invId));
  if (!inv) return;
  const c = STATE.clients.find(x => String(x.id) === String(inv.client)) || {};
  const isOverdue = inv.status === 'Overdue' || (inv.due && new Date(inv.due) < new Date(new Date().toDateString()));
  const msgType = isOverdue ? 'payment_overdue' : 'payment_reminder';
  const _logType = isOverdue ? 'overdue' : (qtype === 'due_today' ? 'due_today' : (qtype === 'due_soon' ? 'due_soon' : 'due_reminder'));
  let sentMsg = '';

  const sendViaWA = (ch) => {
    if (ch !== 'whatsapp' && ch !== 'both') return;
    const phone = (c.wa || c.whatsapp || c.phone || inv.client_wa || inv.client_phone || '').replace(/\D/g, '');
    const _waName = c.name || inv.clientName || inv.client_name || 'client';
    const wa = STATE.settings.wa || {};
    if (phone) {
      const tpl = isOverdue ? (wa.tpl_overdue || getDefaultWATpl('overdue')) : (wa.tpl_remind || getDefaultWATpl('remind'));
      const _pmts = (STATE.payments || []).filter(pp => String(pp.invoice_id) === String(inv.id));
      const _paid = _pmts.reduce((s, pp) => s + parseFloat(pp.amount || 0), 0);
      const _total = parseFloat(inv.grand_total || inv.amount || 0);
      const _remaining = Math.max(0, _total - _paid);
      const invForMsg = _paid > 0 ? Object.assign({}, inv, { amount: _remaining, grand_total: _remaining }) : inv;
      const msg = formatWAMsg(tpl, invForMsg, c, STATE.settings);
      sentMsg = msg;
      logWAMessage({ inv, client: c, type: msgType, msg, status: 'sending' });
      sendWA(phone, msg, msgType, invForMsg, c)
        .then(res => logWAMessage({ inv, client: c, type: msgType, msg, status: res ? 'sent_api' : 'sent_web' }))
        .catch(e => logWAMessage({ inv, client: c, type: msgType, msg, status: 'failed', error: e.message }));
    } else {
      toast(`⚠️ No WhatsApp number for ${_waName}`, 'warning');
    }
  };
  const sendViaEmail = (ch) => {
    if (ch !== 'email' && ch !== 'both') return;
    const email = c.email || c.mail || inv.client_email || '';
    const _emailName = c.name || inv.clientName || inv.client_name || 'client';
    if (email) {
      if (typeof sendEmailFromInvoice === 'function') sendEmailFromInvoice(inv.id, isOverdue ? 'overdue' : 'reminder', email, _emailName);
      else console.warn('[Reminders] Email channel not available yet — email_setup.php not built.');
    } else {
      toast(`⚠️ No email address for ${_emailName}`, 'warning');
    }
  };
  sendViaWA(channel);
  sendViaEmail(channel);

  if (channel !== 'skip') toast('✅ Reminder sent', 'success');
  else toast('⏭️ Skipped', 'success');

  const _clientName = c.name || inv.clientName || inv.client_name || '';
  const entry = {
    id: Date.now() + '', ts: new Date().toLocaleString('sv-SE', { timeZone: 'Asia/Kolkata' }).replace('T', ' '),
    invNum: inv.num || inv.invoice_number || '', clientName: _clientName, type: _logType,
    channel: channel === 'skip' ? (getReminderSettings().channel || 'whatsapp') : channel,
    status: channel === 'skip' ? 'skipped' : 'sent', message: sentMsg,
  };
  STATE.reminders.unshift(entry);
  if (STATE.reminders.length > 200) STATE.reminders = STATE.reminders.slice(0, 200);

  api('api/reminders.php?action=log', 'POST', {
    invoice_id: inv.id, invoice_num: inv.num || inv.invoice_number || '', client_name: _clientName,
    type: _logType, channel: entry.channel, status: entry.status, message: sentMsg,
  }).catch(e => console.warn('reminder log write failed:', e.message));

  logActivity('reminder_sent', `Reminder ${channel === 'skip' ? 'skipped' : 'sent'}: ${inv.num || inv.invoice_number || ''}`, _clientName, inv.id);
  renderReminders();
}

function sendAllReminders() {
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const cfg = getReminderSettings();
  const ch = cfg.channel || 'whatsapp';
  const maxOv = cfg.maxOverdue || 3;
  const overdueCountByInv = {};
  (STATE.reminders || []).forEach(entry => {
    if ((entry.type === 'overdue' || entry.type === 'Overdue Alert') && entry.invNum) {
      overdueCountByInv[entry.invNum] = (overdueCountByInv[entry.invNum] || 0) + 1;
    }
  });
  let count = 0;
  STATE.invoices.forEach(inv => {
    if (['Paid', 'Cancelled', 'Draft'].includes(inv.status)) return;
    const due = inv.due ? new Date(inv.due) : null;
    if (!due) return;
    due.setHours(0, 0, 0, 0);
    const daysUntilDue = Math.floor((due - today) / 864e5);
    if (daysUntilDue > (cfg.beforeDays || 3)) return;
    const invNum = inv.num || inv.invoice_number || '';
    if (daysUntilDue < 0 && (overdueCountByInv[invNum] || 0) >= maxOv) return;
    const qtype = daysUntilDue < 0 ? 'overdue' : daysUntilDue === 0 ? 'due_today' : 'due_soon';
    sendReminderNow(inv.id, ch, qtype);
    count++;
  });
  toast(`✅ Sent ${count} reminder${count !== 1 ? 's' : ''} via ${ch}`, 'success');
}

function _renderReminderHistory() {
  if (!window._remHistPage) window._remHistPage = 1;
  const PER_PAGE = 10;
  const tbody = document.getElementById('rem-history-tbody');
  if (!tbody) return;
  const typeF = document.getElementById('rem-hist-type')?.value || '';
  const chanF = document.getElementById('rem-hist-channel')?.value || '';
  const statF = document.getElementById('rem-hist-status')?.value || '';
  const searchF = (document.getElementById('rem-hist-search')?.value || '').toLowerCase().trim();

  let data = (STATE.reminders || []).filter(r => {
    if (typeF && r.type !== typeF) return false;
    if (chanF && (r.channel || '') !== chanF) return false;
    if (statF && (r.status || '') !== statF) return false;
    if (searchF) {
      const inv = (r.invNum || r.invoice_num || '').toLowerCase();
      const client = (r.clientName || r.client_name || '').toLowerCase();
      const msg = (r.message || r.msg || r.note || '').toLowerCase();
      if (!inv.includes(searchF) && !client.includes(searchF) && !msg.includes(searchF)) return false;
    }
    return true;
  });

  if (!data.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="padding:30px;text-align:center;color:var(--muted)"><i class="fas fa-inbox" style="font-size:28px;opacity:.2;display:block;margin-bottom:8px"></i>No reminder history yet</td></tr>`;
    const pgDiv = document.getElementById('rem-hist-pagination');
    if (pgDiv) pgDiv.style.display = 'none';
    return;
  }

  const typeBadge = {
    due_soon: { bg: '#FFF4E5', color: '#B45309', bdr: '#FBBF24', icon: 'fa-clock', label: 'Due Soon', alert: false },
    due_today: { bg: '#FEF0EF', color: '#C0392B', bdr: '#F7C1C1', icon: 'fa-calendar-day', label: 'Due Today', alert: true },
    due_reminder: { bg: '#EEF5FF', color: '#185FA5', bdr: '#B5D4F4', icon: 'fa-bell', label: 'Reminder', alert: false },
    overdue: { bg: '#FEF0EF', color: '#C0392B', bdr: '#F7C1C1', icon: 'fa-exclamation-triangle', label: 'Overdue', alert: true },
    followup: { bg: '#FDF0F7', color: '#9D174D', bdr: '#F0ABCD', icon: 'fa-phone-alt', label: 'Follow-up', alert: true },
    balance_reminder: { bg: '#FFF4E5', color: '#92400E', bdr: '#FCD34D', icon: 'fa-wallet', label: 'Bal. Reminder', alert: false },
    promise_reminder: { bg: '#EDE9FE', color: '#6D28D9', bdr: '#C4B5FD', icon: 'fa-handshake', label: 'Promise', alert: false },
    paid: { bg: '#EDFAF0', color: '#1E7E34', bdr: '#C0DD97', icon: 'fa-check-circle', label: 'Paid', alert: false },
  };
  const chanBadge = ch => {
    if (ch === 'whatsapp') return `<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:#EDFAF0;color:#1E7E34;border:1px solid #C0DD97"><i class="fab fa-whatsapp" style="font-size:10px"></i> WA</span>`;
    if (ch === 'email') return `<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:#EEF5FF;color:#185FA5;border:1px solid #B5D4F4"><i class="fas fa-envelope" style="font-size:10px"></i> Email</span>`;
    if (ch === 'both') return `<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:#F0EFFD;color:#5B52C7;border:1px solid #AFA9EC"><i class="fas fa-paper-plane" style="font-size:10px"></i> Both</span>`;
    return `<span style="font-size:11px;color:var(--muted)">—</span>`;
  };
  const statBadge = st => {
    if (st === 'sent') return `<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:#EDFAF0;color:#1E7E34;border:1px solid #C0DD97"><i class="fas fa-check" style="font-size:9px"></i> Sent</span>`;
    if (st === 'failed') return `<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:#FEF0EF;color:#C0392B;border:1px solid #F7C1C1"><i class="fas fa-times" style="font-size:9px"></i> Failed</span>`;
    if (st === 'skipped') return `<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:#F5F5F5;color:#888;border:1px solid #ddd"><i class="fas fa-forward" style="font-size:9px"></i> Skipped</span>`;
    if (st === 'promise') return `<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:#EDE9FE;color:#6D28D9;border:1px solid #C4B5FD"><i class="fas fa-handshake" style="font-size:9px"></i> Promise</span>`;
    return `<span style="font-size:11px;color:var(--muted)">${st || '—'}</span>`;
  };

  const total = data.length;
  const pages = Math.ceil(total / PER_PAGE);
  const page = window._remHistPage || 1;
  const paged = data.slice((page - 1) * PER_PAGE, page * PER_PAGE);

  tbody.innerHTML = paged.map(r => {
    const raw = r.ts || r.sent_at || '';
    const norm = raw ? (raw.includes('T') ? raw : raw.replace(' ', 'T') + '+05:30') : '';
    const d = norm ? new Date(norm) : null;
    const diff = d && !isNaN(d) ? Math.floor((Date.now() - d) / 1000) : null;
    const rel = diff === null ? '—' : diff < 60 ? 'just now' : diff < 3600 ? Math.floor(diff / 60) + 'm ago' : diff < 86400 ? Math.floor(diff / 3600) + 'h ago' : diff < 604800 ? Math.floor(diff / 86400) + 'd ago' : d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Kolkata' });
    const time = d && !isNaN(d) ? d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Kolkata' }) : '';
    const dateStr = d && !isNaN(d) ? d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Kolkata' }) : '';
    const tb = typeBadge[r.type] || { bg: '#F5F5F5', color: '#888', bdr: '#ddd', icon: 'fa-circle', label: r.type || '—', alert: false };
    const aStyle = tb.alert ? `border-left:3px solid ${tb.color};border-radius:0 6px 6px 0;` : 'border-radius:6px;';
    const typePill = `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;${aStyle}font-size:11px;font-weight:600;background:${tb.bg};color:${tb.color};border:1px solid ${tb.bdr};white-space:nowrap"><i class="fas ${tb.icon}" style="font-size:9px"></i> ${tb.label}</span>`;
    const msg = (r.message || r.msg || r.note || '').trim();
    const msgShort = msg.length > 50 ? msg.slice(0, 50) + '…' : msg;
    return `<tr>
      <td style="white-space:nowrap;min-width:110px">
        <div style="font-size:12px;font-weight:600;color:var(--text)">${rel}</div>
        <div style="font-size:10px;color:var(--muted);font-family:var(--mono)">${time}${dateStr && rel !== dateStr ? ' · ' + dateStr : ''}</div>
      </td>
      <td style="font-family:var(--mono);font-weight:700;font-size:12px">${r.invNum || r.invoice_num || '—'}</td>
      <td style="font-size:13px">${r.clientName || r.client_name || '—'}</td>
      <td>${typePill}</td>
      <td>${chanBadge(r.channel || '')}</td>
      <td>${statBadge(r.status || '')}</td>
      <td style="font-size:11px;color:${msg ? 'var(--text)' : 'var(--muted)'};max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${msg.replace(/"/g, '&quot;')}">${msgShort || '<span style="color:var(--muted)">—</span>'}</td>
    </tr>`;
  }).join('');

  const pgDiv = document.getElementById('rem-hist-pagination');
  const pgInfo = document.getElementById('rem-hist-page-info');
  const pgBtns = document.getElementById('rem-hist-page-btns');
  if (pgDiv) {
    pgDiv.style.display = 'flex';
    if (pgInfo) pgInfo.textContent = `Showing ${((page - 1) * PER_PAGE) + 1}–${Math.min(page * PER_PAGE, total)} of ${total}`;
    if (pgBtns) {
      if (pages > 1) {
        const bS = (act, dis) => `width:28px;height:28px;border-radius:7px;border:1px solid ${act ? 'var(--teal)' : 'var(--border)'};background:${act ? 'var(--teal)' : 'var(--bg)'};color:${act ? '#fff' : 'var(--text)'};cursor:${dis ? 'default' : 'pointer'};font-size:11px;opacity:${dis ? '.4' : '1'}`;
        let h = `<button onclick="if(window._remHistPage>1){window._remHistPage--;_renderReminderHistory();}" style="${bS(false, page === 1)}"><i class="fas fa-chevron-left"></i></button>`;
        for (let i = 1; i <= pages; i++) h += `<button onclick="window._remHistPage=${i};_renderReminderHistory()" style="${bS(i === page, false)}">${i}</button>`;
        h += `<button onclick="if(window._remHistPage<${pages}){window._remHistPage++;_renderReminderHistory();}" style="${bS(false, page === pages)}"><i class="fas fa-chevron-right"></i></button>`;
        pgBtns.innerHTML = h;
      } else pgBtns.innerHTML = '';
    }
  }
}

async function clearReminderHistory() {
  const result = await Swal.fire({ title: 'Clear Reminder History?', text: 'All reminder log entries will be permanently deleted.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Clear All', cancelButtonText: 'Cancel', confirmButtonColor: '#E53935', customClass: { popup: 'swal-compact' } });
  if (!result.isConfirmed) return;
  api('api/reminders.php?log=1', 'DELETE').then(() => {
    STATE.reminders = []; renderReminders(); toast('🗑️ History cleared', 'info');
  }).catch(e => toast('❌ ' + e.message, 'error'));
}

function _updateCronHint(hour, minute) {
  const el = document.getElementById('rem-cron-hint');
  if (!el) return;
  const hh = String(hour).padStart(2, '0'), mm = String(minute).padStart(2, '0');
  el.innerHTML = `<span style="color:var(--muted2);font-size:10px;text-transform:uppercase;letter-spacing:.4px">cPanel Cron (recommended)</span><br>` +
    `<span style="color:var(--text2)">*/30 * * * *&nbsp; php /path/api/wa_cron.php</span><br>` +
    `<span style="color:var(--text2)">*/30 * * * *&nbsp; php /path/api/email_cron.php</span><br>` +
    `<span style="color:var(--teal);font-size:10px">↳ Will actually send at ${hh}:${mm} IST (±20 min window)</span>`;
}

function _buildHealthCheck() {
  const el = document.getElementById('rem-health-body');
  if (!el) return;
  const cfg = getReminderSettings();
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const reminders = STATE.reminders || [];
  const invoices = STATE.invoices || [];
  const clients = STATE.clients || [];
  let noPhone = 0, noEmail = 0, overdueUnsent = 0, capHit = 0;
  const overdueCountByInv = {};
  reminders.forEach(e => { if ((e.type === 'overdue' || e.type === 'Overdue Alert') && e.invNum && e.status === 'sent') overdueCountByInv[e.invNum] = (overdueCountByInv[e.invNum] || 0) + 1; });
  invoices.forEach(inv => {
    if (['Paid', 'Cancelled', 'Draft'].includes(inv.status)) return;
    const due = inv.due ? new Date(inv.due) : null;
    if (!due) return;
    due.setHours(0, 0, 0, 0);
    if (due >= today) return;
    const c = clients.find(x => String(x.id) === String(inv.client)) || {};
    const phone = (c.wa || c.whatsapp || c.phone || inv.client_phone || '').replace(/\D/g, '');
    const email = c.email || c.mail || inv.client_email || '';
    const invNum = inv.num || inv.invoice_number || '';
    const cnt = overdueCountByInv[invNum] || 0;
    if (!phone && !email) { noPhone++; return; }
    if (cfg.channel !== 'email' && !phone) noEmail++;
    if (cfg.channel !== 'whatsapp' && !email) noEmail++;
    if (cnt >= (cfg.maxOverdue || 3)) { capHit++; return; }
    if (cnt === 0) overdueUnsent++;
  });
  const lastSent = reminders.length ? reminders.slice().sort((a, b) => ((b.ts || b.sent_at || '') > (a.ts || a.sent_at || '')) ? 1 : -1)[0] : null;
  const lastSentStr = lastSent ? (() => { const d = new Date((lastSent.ts || lastSent.sent_at || '').replace(' ', 'T') + 'Z'); return isNaN(d) ? '—' : d.toLocaleString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Kolkata' }); })() : 'Never';
  const wa = STATE.settings?.wa || {};
  const waOk = !!(wa.token && wa.pid);
  const smtpOk = !!(STATE.settings?.smtp_host || STATE.settings?.smtp_user);
  const chip = (ok, label) => `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:8px;font-size:11px;font-weight:700;background:${ok ? '#EDFAF0' : '#FEF0EF'};color:${ok ? '#1E7E34' : '#C0392B'};border:1px solid ${ok ? '#C0DD97' : '#F7C1C1'}"><i class="fas ${ok ? 'fa-check' : 'fa-times'}" style="font-size:9px"></i> ${label}</span>`;
  const row = (icon, label, val, warn) => `<div style="display:flex;align-items:center;gap:10px;padding:8px 14px;border-bottom:1px solid var(--border);${warn ? 'background:#FEF9EF;' : ''}">
      <i class="fas ${icon}" style="width:16px;color:${warn ? '#B45309' : 'var(--muted)'}"></i>
      <span style="font-size:12px;flex:1;color:var(--text2)">${label}</span>
      <span style="font-size:12px;font-weight:700;color:${warn ? '#C0392B' : 'var(--text)'}">${val}</span>
    </div>`;
  el.innerHTML = `<div style="display:flex;flex-wrap:wrap;gap:8px;padding:10px 14px;border-bottom:1px solid var(--border)">
      ${chip(waOk, 'WA API')} ${chip(smtpOk, 'SMTP')}
      ${chip(cfg.channel === 'whatsapp' || cfg.channel === 'both', 'WA reminders')}
      ${chip(cfg.channel === 'email' || cfg.channel === 'both', 'Email reminders')}
    </div>` +
    row('fa-clock', 'Last reminder sent', lastSentStr, false) +
    row('fa-exclamation-triangle', 'Overdue — first reminder not sent yet', overdueUnsent, overdueUnsent > 0) +
    row('fa-ban', 'Reached max reminder cap', capHit, capHit > 0) +
    row('fa-phone-slash', 'No contact info on file', noPhone, noPhone > 0) +
    row('fa-bell', 'Send time (IST)', String(cfg.sendHour || 9).padStart(2, '0') + ':' + String(cfg.sendMinute || 0).padStart(2, '0'), false) +
    `<div style="padding:8px 14px;font-size:11px;color:var(--muted)">
      <i class="fas fa-info-circle" style="margin-right:4px"></i>
      Set cPanel cron to <strong>*/30 * * * *</strong> and the timing guard handles the rest.
    </div>`;
}

// ══════════════════════════════════════════
// BALANCE REMINDER (uses the shared modal from layout_footer.php)
// ══════════════════════════════════════════
let _brInvId = null;
let _brChannel = 'whatsapp';

function openBalanceReminderModal(invId) {
  const inv = STATE.invoices.find(i => String(i.id) === String(invId));
  if (!inv) return;
  _brInvId = invId;
  const c = STATE.clients.find(x => String(x.id) === String(inv.client)) || {};
  const sym = inv.currency || '₹';
  const invIdStr = String(inv.id || '');
  const pmts = invIdStr ? (STATE.payments || []).filter(p => String(p.invoice_id) === invIdStr) : [];
  const paid = pmts.reduce((s, p) => s + parseFloat(p.amount || 0), 0);
  const remaining = Math.max(0, parseFloat(inv.amount || 0) - paid);
  document.getElementById('br-total').textContent = fmt_money(parseFloat(inv.amount || 0), sym);
  document.getElementById('br-paid').textContent = fmt_money(paid, sym);
  document.getElementById('br-remaining').textContent = fmt_money(remaining, sym);
  const lbl = (inv.num || inv.invoice_number || '') + ' · ' + (c.name || inv.clientName || inv.client_name || 'Client');
  document.getElementById('br-inv-label').textContent = lbl;
  _brChannel = getReminderSettings().channel || 'whatsapp';
  _highlightBRChannel(_brChannel);
  const wa = STATE.settings.wa || {};
  const tpl = wa.tpl_remind || getDefaultWATpl('remind');
  const invWithAmts = Object.assign({}, inv, { _paidAmt: paid, _remainingAmt: remaining });
  const balanceTpl = tpl.replace(/\*?{currency}\*?{amount}\*?/g, '*' + fmt_money(remaining, sym) + '*').replace(/{amount}/g, fmt_money(remaining, sym));
  const msg = formatWAMsg(balanceTpl, invWithAmts, c, STATE.settings);
  document.getElementById('br-message').value = msg;
  document.getElementById('balance-reminder-modal').style.display = 'flex';
}
function closeBalanceReminderModal() { document.getElementById('balance-reminder-modal').style.display = 'none'; _brInvId = null; }
function setBRChannel(ch) { _brChannel = ch; _highlightBRChannel(ch); }
function _highlightBRChannel(ch) {
  const active = 'border:2px solid #D97706;background:#FFF8E1;color:#92400E;';
  const passive = 'border:2px solid var(--border);background:var(--bg);color:var(--muted);';
  document.getElementById('br-ch-wa').style.cssText += ch === 'whatsapp' ? active : passive;
  document.getElementById('br-ch-email').style.cssText += ch === 'email' ? active : passive;
  document.getElementById('br-ch-both').style.cssText += ch === 'both' ? active : passive;
}

async function sendBalanceReminder() {
  const inv = STATE.invoices.find(i => String(i.id) === String(_brInvId));
  if (!inv) return;
  const cl = STATE.clients.find(x => String(x.id) === String(inv.client)) || {};
  const msg = document.getElementById('br-message').value.trim();
  const ch = _brChannel;
  const phone = (cl.wa || cl.whatsapp || cl.phone || inv.client_wa || inv.client_phone || '').replace(/\D/g, '');
  const email = cl.email || cl.mail || inv.client_email || '';
  let sent = false;
  if ((ch === 'whatsapp' || ch === 'both') && phone) {
    logWAMessage({ inv, client: cl, type: 'balance_reminder', msg, status: 'sending' });
    sendWA(phone, msg, 'balance_reminder', inv, cl)
      .then(res => { logWAMessage({ inv, client: cl, type: 'balance_reminder', msg, status: res ? 'sent_api' : 'sent_web' }); toast(`📱 Balance reminder sent to ${cl.name || phone} via WhatsApp!`, 'success'); })
      .catch(e => { logWAMessage({ inv, client: cl, type: 'balance_reminder', msg, status: 'failed', error: e.message }); toast(`⚠️ WhatsApp not sent — ${e.message}`, 'warning'); });
    sent = true;
  } else if ((ch === 'whatsapp' || ch === 'both') && !phone) {
    toast('⚠️ No WhatsApp number for ' + (cl.name || 'client'), 'warning');
  }
  if ((ch === 'email' || ch === 'both') && email) {
    if (typeof sendEmailFromInvoice === 'function') sendEmailFromInvoice(inv.id, 'reminder', email, cl.name || inv.clientName || '');
    sent = true;
  } else if ((ch === 'email' || ch === 'both') && !email) {
    toast('⚠️ No email address for ' + (cl.name || 'client'), 'warning');
  }
  if (sent) {
    const invIdStr = String(inv.id || '');
    const pmts = (STATE.payments || []).filter(p => String(p.invoice_id) === invIdStr);
    const paid = pmts.reduce((s, p) => s + parseFloat(p.amount || 0), 0);
    const remaining = Math.max(0, parseFloat(inv.amount || 0) - paid);
    api('api/reminders.php?action=log', 'POST', {
      invoice_id: inv.id, invoice_num: inv.num || inv.invoice_number || '', client_name: cl.name || inv.clientName || inv.client_name || '',
      type: 'balance_reminder', channel: ch, status: 'sent', message: 'Balance reminder — paid: ' + fmt_money(paid) + ' · remaining: ' + fmt_money(remaining),
    }).catch(e => console.warn('reminder log failed:', e.message));
    logActivity('reminder_sent', 'Balance reminder sent: ' + (inv.num || inv.invoice_number || ''), cl.name || inv.clientName || '', inv.id);
    toast('✅ Balance reminder sent via ' + ch, 'success');
    closeBalanceReminderModal();
  }
}

// ══════════════════════════════════════════
// PROMISE TO PAY (uses the shared modal from layout_footer.php)
// ══════════════════════════════════════════
let _ptpInvId = null;

function openPromiseModal(invId) {
  _ptpInvId = invId;
  const inv = STATE.invoices.find(i => String(i.id) === String(invId));
  if (!inv) return;
  const c = STATE.clients.find(x => String(x.id) === String(inv.client)) || {};
  const label = document.getElementById('ptp-inv-label');
  if (label) label.textContent = (inv.num || inv.invoice_number || '') + ' · ' + (c.name || inv.clientName || inv.client_name || 'Client');
  const tom = new Date(); tom.setDate(tom.getDate() + 1);
  const dd = document.getElementById('ptp-date');
  if (dd) dd.value = tom.toISOString().slice(0, 10);
  const invPayments = (STATE.payments || []).filter(p => String(p.invoice_id) === String(invId));
  const paid = invPayments.reduce((s, p) => s + (parseFloat(p.amount) || 0), 0);
  const grand = parseFloat(inv.grand_total || inv.amount || 0);
  const remaining = Math.max(0, grand - paid);
  const da = document.getElementById('ptp-amount');
  if (da) da.value = remaining > 0 ? remaining.toFixed(2) : '';
  const dch = document.getElementById('ptp-channel');
  if (dch) dch.value = getReminderSettings().channel || 'whatsapp';
  const dn = document.getElementById('ptp-note');
  if (dn) dn.value = '';
  const modal = document.getElementById('promise-modal');
  if (modal) modal.style.display = 'flex';
}
function closePromiseModal() {
  const modal = document.getElementById('promise-modal');
  if (modal) modal.style.display = 'none';
  _ptpInvId = null;
}
async function savePromise() {
  const invId = _ptpInvId;
  const promiseDate = document.getElementById('ptp-date')?.value;
  const amount = parseFloat(document.getElementById('ptp-amount')?.value || 0);
  const channel = document.getElementById('ptp-channel')?.value || 'whatsapp';
  const note = document.getElementById('ptp-note')?.value?.trim() || '';
  if (!invId || !promiseDate) { toast('❌ Please select a promise date', 'error'); return; }
  if (new Date(promiseDate) < new Date(new Date().toDateString())) { toast('❌ Promise date must be today or in the future', 'error'); return; }
  const inv = STATE.invoices.find(i => String(i.id) === String(invId)) || {};
  const cl = STATE.clients.find(x => String(x.id) === String(inv.client)) || {};
  try {
    const res = await api('api/reminders.php?action=promise', 'POST', {
      invoice_id: invId, invoice_num: inv.num || inv.invoice_number || '', client_name: cl.name || inv.clientName || inv.client_name || '',
      promise_date: promiseDate, amount: amount || 0, channel, note,
    });
    STATE.promises = STATE.promises || [];
    STATE.promises.push({ id: res.id, invoiceId: invId, invNum: inv.num || inv.invoice_number || '', clientName: cl.name || inv.clientName || inv.client_name || '', promiseDate, amount, note, channel, status: 'pending', remindedAt: null });
    closePromiseModal();
    toast('✅ Promise saved — reminder will be sent on ' + promiseDate, 'success');
    renderReminders();
    logActivity('reminder_sent', 'Promise to pay recorded: ' + (inv.num || inv.invoice_number || ''), cl.name || inv.clientName || '', invId);
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

function _renderPromiseTracker() {
  const el = document.getElementById('promise-list');
  const badge = document.getElementById('promise-count-badge');
  if (!el) return;
  const active = (STATE.promises || []).filter(p => p.status === 'pending' || p.status === 'reminded');
  if (badge) { badge.textContent = active.length; badge.style.display = active.length ? 'inline-block' : 'none'; }
  if (!active.length) {
    el.innerHTML = `<div style="text-align:center;padding:24px;color:var(--muted);font-size:13px"><i class="fas fa-handshake" style="font-size:24px;opacity:.2;display:block;margin-bottom:8px"></i>No active promises</div>`;
    return;
  }
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const overdue = active.filter(p => new Date((p.promiseDate || p.promise_date || '') + 'T00:00:00') < today);
  const dueToday = active.filter(p => new Date((p.promiseDate || p.promise_date || '') + 'T00:00:00').getTime() === today.getTime());
  const upcoming = active.filter(p => new Date((p.promiseDate || p.promise_date || '') + 'T00:00:00') > today);

  const row = (p, urgency) => {
    const isOver = urgency === 'overdue', isToday = urgency === 'today';
    const col = isOver ? '#C0392B' : isToday ? '#B45309' : '#6D28D9';
    const bgPill = isOver ? '#FEF0EF' : isToday ? '#FFF4E5' : '#EDE9FE';
    const pDate = p.promiseDate || p.promise_date || '';
    const dateF = pDate ? new Date(pDate + 'T00:00:00').toLocaleDateString('en-IN', { day: '2-digit', month: 'short' }) : '—';
    const daysAgo = isOver ? Math.abs(Math.floor((new Date(pDate + 'T00:00:00') - today) / 864e5)) : 0;
    const daysFwd = !isOver && !isToday ? Math.floor((new Date(pDate + 'T00:00:00') - today) / 864e5) : 0;
    const lbl = isOver ? `${daysAgo}d late` : isToday ? 'Today' : `${daysFwd}d`;
    const _inv = STATE.invoices.find(i => String(i.id) === String(p.invoiceId)) || {};
    const _pmts = (STATE.payments || []).filter(pp => String(pp.invoice_id) === String(p.invoiceId));
    const _paid = _pmts.reduce((s, pp) => s + parseFloat(pp.amount || 0), 0);
    const _total = parseFloat(_inv.grand_total || _inv.amount || p.amount || 0);
    const _remain = Math.max(0, _total - _paid);
    const amt = _paid > 0 ? `<span style="color:#B45309;font-weight:600">${fmt_money(_remain)}</span><div style="font-size:10px;color:var(--muted)">of ${fmt_money(_total)}</div>` : p.amount > 0 ? fmt_money(parseFloat(p.amount)) : '—';
    const chIcon = p.channel === 'email' ? 'fas fa-envelope' : p.channel === 'both' ? 'fas fa-paper-plane' : 'fab fa-whatsapp';
    const chColor = p.channel === 'email' ? '#2563EB' : p.channel === 'both' ? '#5B52C7' : '#1E7E34';
    const statusDot = p.status === 'reminded' ? `<span style="font-size:9px;padding:1px 5px;border-radius:8px;background:#FFF4E5;color:#B45309;border:1px solid #FBBF24">reminded</span>` : '';
    return `<tr style="border-bottom:1px solid var(--border)">
      <td style="padding:8px 10px;font-family:var(--mono);font-size:12px;font-weight:700;white-space:nowrap">${p.invNum || p.invoice_num || '—'}</td>
      <td style="padding:8px 6px;font-size:12px;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.clientName || p.client_name || '—'} ${statusDot}</td>
      <td style="padding:8px 6px;white-space:nowrap">
        <span style="font-size:11px;font-weight:700;padding:2px 7px;border-radius:8px;background:${bgPill};color:${col}">${lbl}</span>
        <div style="font-size:10px;color:var(--muted);margin-top:1px">${dateF}</div>
      </td>
      <td style="padding:8px 6px;font-size:12px;font-weight:600;white-space:nowrap">${amt}</td>
      <td style="padding:8px 6px"><span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:5px;background:${chColor}15;border:1px solid ${chColor}40"><i class="${chIcon}" style="color:${chColor};font-size:11px"></i></span></td>
      <td style="padding:8px 6px;font-size:11px;color:var(--muted);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${(p.note || '').replace(/"/g, '&quot;')}">${p.note || '—'}</td>
      <td style="padding:8px 6px;white-space:nowrap">
        <div style="display:flex;gap:4px;align-items:center">
          <button onclick="sendPromiseReminder('${p.id}')" style="display:inline-flex;align-items:center;gap:3px;padding:3px 8px;background:${bgPill};color:${col};border:1px solid ${col}30;border-radius:6px;cursor:pointer;font-size:11px;font-weight:600;white-space:nowrap"><i class="${chIcon}" style="font-size:10px"></i> Send</button>
          <button onclick="markPromiseFulfilled('${p.id}')" style="padding:3px 9px;background:#EDFAF0;color:#1E7E34;border:1px solid #C0DD97;border-radius:6px;cursor:pointer;font-size:11px;font-weight:600;white-space:nowrap">Paid</button>
          <button onclick="markPromiseCancelled('${p.id}')" style="padding:3px 9px;background:var(--bg);color:var(--muted);border:1px solid var(--border);border-radius:6px;cursor:pointer;font-size:11px;white-space:nowrap">Cancel</button>
        </div>
      </td>
    </tr>`;
  };
  const section = (id, title, items, urgency, defaultOpen) => {
    if (!items.length) return '';
    const isOpen = window['_ptpOpen_' + id] !== false ? defaultOpen : window['_ptpOpen_' + id];
    const chevron = isOpen ? 'fa-chevron-down' : 'fa-chevron-right';
    const dotCol = urgency === 'overdue' ? '#C0392B' : urgency === 'today' ? '#B45309' : '#6D28D9';
    return `<div style="margin-bottom:4px">
      <div onclick="window['_ptpOpen_${id}']=!(window['_ptpOpen_${id}']!==false?${defaultOpen}:window['_ptpOpen_${id}']);_renderPromiseTracker()"
           style="display:flex;align-items:center;gap:8px;padding:7px 12px;cursor:pointer;user-select:none;background:var(--bg2,var(--bg));border-radius:8px;border:1px solid var(--border)">
        <i class="fas ${chevron}" style="font-size:10px;color:var(--muted);width:10px"></i>
        <span style="width:8px;height:8px;border-radius:50%;background:${dotCol};display:inline-block;flex-shrink:0"></span>
        <span style="font-size:12px;font-weight:700;color:var(--text)">${title}</span>
        <span style="font-size:11px;padding:1px 7px;border-radius:8px;background:${dotCol}18;color:${dotCol};font-weight:700;margin-left:2px">${items.length}</span>
      </div>
      ${isOpen ? `<div style="overflow-x:auto;margin-top:4px">
        <table style="width:100%;border-collapse:collapse">
          <thead><tr style="background:var(--bg2,var(--bg));border-bottom:2px solid var(--border)">
            <th style="padding:6px 10px;font-size:10px;font-weight:700;color:var(--muted);text-align:left;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap">Invoice</th>
            <th style="padding:6px 6px;font-size:10px;font-weight:700;color:var(--muted);text-align:left;text-transform:uppercase;letter-spacing:.4px">Client</th>
            <th style="padding:6px 6px;font-size:10px;font-weight:700;color:var(--muted);text-align:left;text-transform:uppercase;letter-spacing:.4px">Due</th>
            <th style="padding:6px 6px;font-size:10px;font-weight:700;color:var(--muted);text-align:left;text-transform:uppercase;letter-spacing:.4px">Amount</th>
            <th style="padding:6px 6px;font-size:10px;font-weight:700;color:var(--muted);text-align:left;text-transform:uppercase;letter-spacing:.4px">Ch.</th>
            <th style="padding:6px 6px;font-size:10px;font-weight:700;color:var(--muted);text-align:left;text-transform:uppercase;letter-spacing:.4px">Note</th>
            <th style="padding:6px 6px;font-size:10px;font-weight:700;color:var(--muted);text-align:left;text-transform:uppercase;letter-spacing:.4px">Actions</th>
          </tr></thead>
          <tbody>${items.map(p => row(p, urgency)).join('')}</tbody>
        </table>
      </div>` : ''}
    </div>`;
  };
  el.innerHTML = section('overdue', '⚠ Overdue Promises', overdue, 'overdue', true) + section('today', '📅 Due Today', dueToday, 'today', true) + section('upcoming', '🗓 Upcoming', upcoming, 'upcoming', true);
}

function sendPromiseReminder(ptpId) {
  const p = (STATE.promises || []).find(x => String(x.id) === String(ptpId));
  if (!p) return;
  const inv = STATE.invoices.find(i => String(i.id) === String(p.invoiceId));
  if (!inv) { toast('⚠️ Invoice not found', 'warning'); return; }
  const cl = STATE.clients.find(x => String(x.id) === String(inv.client)) || {};
  const sym = inv.currency || '₹';
  const pmts = (STATE.payments || []).filter(pp => String(pp.invoice_id) === String(inv.id));
  const paid = pmts.reduce((s, pp) => s + parseFloat(pp.amount || 0), 0);
  const remaining = Math.max(0, parseFloat(inv.grand_total || inv.amount || 0) - paid);
  const wa = STATE.settings.wa || {};
  const tpl = wa.tpl_remind || getDefaultWATpl('remind');
  const invWithAmts = Object.assign({}, inv, { _paidAmt: paid, _remainingAmt: remaining });
  const balanceTpl = tpl.replace(/\*?{currency}\*?{amount}\*?/g, '*' + fmt_money(remaining, sym) + '*').replace(/{amount}/g, fmt_money(remaining, sym));
  const msg = formatWAMsg(balanceTpl, invWithAmts, cl, STATE.settings);
  const phone = (cl.wa || cl.whatsapp || cl.phone || inv.client_wa || inv.client_phone || '').replace(/\D/g, '');
  const ch = p.channel || 'whatsapp';
  if ((ch === 'whatsapp' || ch === 'both') && phone) {
    logWAMessage({ inv, client: cl, type: 'balance_reminder', msg, status: 'sending' });
    sendWA(phone, msg, 'balance_reminder', inv, cl)
      .then(res => logWAMessage({ inv, client: cl, type: 'balance_reminder', msg, status: res ? 'sent_api' : 'sent_web' }))
      .catch(e => logWAMessage({ inv, client: cl, type: 'balance_reminder', msg, status: 'failed', error: e.message }));
  } else if ((ch === 'whatsapp' || ch === 'both') && !phone) {
    toast('⚠️ No WhatsApp number for ' + (cl.name || 'client'), 'warning');
  }
  if ((ch === 'email' || ch === 'both') && (cl.email || inv.client_email)) {
    if (typeof sendEmailFromInvoice === 'function') sendEmailFromInvoice(inv.id, 'reminder', cl.email || inv.client_email, cl.name || inv.clientName || '');
  }
  api('api/reminders.php?action=log', 'POST', {
    invoice_id: inv.id, invoice_num: inv.num || inv.invoice_number || '', client_name: cl.name || inv.clientName || inv.client_name || '',
    type: 'promise_reminder', channel: ch, status: 'sent', message: 'Promise reminder — paid: ' + fmt_money(paid, sym) + ' · remaining: ' + fmt_money(remaining, sym),
  }).catch(e => console.warn('promise reminder log failed:', e.message));
  toast('✅ Promise reminder sent to ' + (cl.name || 'client'), 'success');
  api('api/reminders.php?action=promise_update', 'POST', { id: ptpId, status: 'reminded' }).catch(e => console.warn('promise_update failed:', e.message));
  const idx = (STATE.promises || []).findIndex(x => String(x.id) === String(ptpId));
  if (idx >= 0) STATE.promises[idx].status = 'reminded';
  _renderPromiseTracker();
}

async function markPromiseFulfilled(ptpId) {
  await api('api/reminders.php?action=promise_update', 'POST', { id: ptpId, status: 'fulfilled' }).catch(e => console.warn('promise_update failed:', e.message));
  STATE.promises = (STATE.promises || []).filter(x => String(x.id) !== String(ptpId));
  toast('✅ Marked as fulfilled', 'success');
  _renderPromiseTracker();
}
async function markPromiseCancelled(ptpId) {
  await api('api/reminders.php?action=promise_update', 'POST', { id: ptpId, status: 'cancelled' }).catch(e => console.warn('promise_update failed:', e.message));
  STATE.promises = (STATE.promises || []).filter(x => String(x.id) !== String(ptpId));
  toast('🗑️ Promise cancelled', 'info');
  renderReminders();
}

function checkDuePromises() {
  const today = new Date(); today.setHours(0, 0, 0, 0);
  (STATE.promises || []).forEach(p => {
    if (p.status !== 'pending') return;
    const due = new Date(p.promiseDate + 'T00:00:00');
    if (due > today) return;
    sendPromiseReminder(p.id);
  });
}
