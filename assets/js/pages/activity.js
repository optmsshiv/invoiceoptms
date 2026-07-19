// ================================================================
//  assets/js/activity.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  No MPA adjustments needed — self-contained in the SPA too.
// ================================================================

document.addEventListener('DOMContentLoaded', () => { renderActivityLog(); });

let _actFiltered = [];
let _actPage = 0;
const _ACT_PER = 30;

function renderActivityLog() {
  const el = document.getElementById('activity-timeline');
  if (el) el.innerHTML = `<div style="text-align:center;padding:40px;color:var(--muted)"><i class="fas fa-spinner fa-spin" style="font-size:24px;opacity:.4"></i><div style="margin-top:10px;font-size:13px">Loading activity…</div></div>`;
  api('/api/activity.php?limit=200').then(r => {
    if (r && r.data) STATE.activity = r.data.map(x => ({ id: x.id, type: x.type, label: x.label, detail: x.detail, invoiceId: x.invoice_id, ts: x.created_at }));
    _actFiltered = [...STATE.activity]; _actPage = 0;
    _renderActivityStats(); _renderActivityTimeline(true);
  }).catch(() => {
    _actFiltered = [...STATE.activity]; _actPage = 0;
    _renderActivityStats(); _renderActivityTimeline(true);
  });
}

function refreshActivityLog() {
  const btn = document.getElementById('activity-refresh-btn');
  if (btn) { btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing…'; btn.disabled = true; }
  api('/api/activity.php?limit=200').then(r => {
    if (r && r.data) STATE.activity = r.data.map(x => ({ id: x.id, type: x.type, label: x.label, detail: x.detail, invoiceId: x.invoice_id, ts: x.created_at }));
    filterActivity(document.getElementById('activity-search')?.value || '');
    _renderActivityStats();
    toast('🔄 Activity log refreshed', 'info');
  }).catch(e => toast('❌ Refresh failed: ' + e.message, 'error'))
    .finally(() => { if (btn) { btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh'; btn.disabled = false; } });
}

function filterActivity(val) {
  const s = val.toLowerCase();
  const tf = document.getElementById('activity-type-filter')?.value || '';
  const df = document.getElementById('activity-date-filter')?.value || '';
  _actFiltered = STATE.activity.filter(e => {
    if (tf && e.type !== tf) return false;
    if (df) {
      const now = new Date(), d = new Date(e.ts);
      if (df === 'today' && d.toDateString() !== now.toDateString()) return false;
      if (df === 'week' && (now - d) > 7 * 864e5) return false;
      if (df === 'month' && (d.getMonth() !== now.getMonth() || d.getFullYear() !== now.getFullYear())) return false;
    }
    return !s || (e.label || '').toLowerCase().includes(s) || (e.detail || '').toLowerCase().includes(s);
  });
  _actPage = 0; _renderActivityTimeline(true);
}
function filterActivityType(v) { filterActivity(document.getElementById('activity-search')?.value || ''); }
function filterActivityDate(v) { filterActivity(document.getElementById('activity-search')?.value || ''); }
function loadMoreActivity() { _actPage++; _renderActivityTimeline(false); }

function _renderActivityStats() {
  const el = document.getElementById('activity-stats');
  if (!el) return;
  const types = {};
  STATE.activity.forEach(e => { const key = _actGroupKey(e); types[key] = (types[key] || 0) + 1; });
  const pills = Object.entries(types).slice(0, 6).map(([t, n]) => {
    const info = _actTypeInfo(t);
    return `<div style="display:flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;background:${info.bg};border:1px solid ${info.col}30">
      <span>${info.icon}</span>
      <span style="font-size:12px;font-weight:600;color:${info.col}">${info.label}</span>
      <span style="font-size:11px;font-weight:800;color:${info.col};background:${info.col}20;padding:1px 6px;border-radius:8px">${n}</span>
    </div>`;
  }).join('');
  el.innerHTML = `<div style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px;padding:5px 0">
    <i class="fas fa-history" style="color:var(--teal)"></i> ${STATE.activity.length} total events
  </div>${pills}`;
}

function _actGroupKey(e) {
  const t = (e.type || '').toLowerCase();
  const l = (e.label || '').toLowerCase();
  if (t === 'create' && l === 'payment') return 'payment_recorded';
  if (t === 'create' && l === 'invoice') return 'invoice_created';
  if (t === 'create' && l === 'estimate') return 'estimate_created';
  if (t === 'delete' && l === 'invoice') return 'invoice_deleted';
  if (t === 'delete' && l === 'estimate') return 'estimate_deleted';
  if (t === 'delete' && l === 'payment') return 'invoice_deleted';
  if (t === 'update' && l === 'invoice') return 'invoice_edited';
  if (t === 'update' && l === 'payment') return 'payment_recorded';
  if (t === 'email_sent') return 'email_sent';
  if (t === 'wa_send' || t === 'wa_log') return 'reminder_sent';
  if (t === 'login' || t === 'logout') return t;
  if (t === 'credit_note') return 'credit_note';
  if (t === 'expense_added' || (t === 'create' && l.includes('expense'))) return 'expense_added';
  if (t === 'status_changed' || (t === 'update' && l.includes('status'))) return 'status_changed';
  return t;
}

function _extractInvRef(e) {
  const sources = [
    e.invoiceId ? String(e.invoiceId) : '',
    e.label || '',
    (e.detail || '').replace(/ ?\|SNAPSHOT:[\s\S]*/, ''),
  ];
  for (const s of sources) {
    const m = s.match(/(?:INV|QT|OT)-[\w-]+/);
    if (m) return m[0];
  }
  return '';
}

function _groupActivityEvents(events) {
  const groups = [];
  events.forEach(e => {
    const eTime = e.ts ? new Date(e.ts).getTime() : 0;
    const eKey = _actGroupKey(e);
    const eInv = _extractInvRef(e);
    const match = groups.find(g => {
      if (g.key !== eKey) return false;
      if (Math.abs(g.time - eTime) > 30000) return false;
      if (eInv && g.invoiceRef && eInv !== g.invoiceRef) return false;
      return true;
    });
    if (match) { match.events.push(e); if (!match.invoiceRef && eInv) match.invoiceRef = eInv; }
    else { groups.push({ key: eKey, invoiceRef: eInv, time: eTime, events: [e] }); }
  });
  return groups;
}

function _actTypeInfo(type) {
  const map = {
    invoice_created: { icon: '📄', label: 'Created', col: '#1976D2', bg: '#e3f2fd' },
    invoice_edited: { icon: '✏️', label: 'Edited', col: '#7B1FA2', bg: '#f3e5f5' },
    invoice_deleted: { icon: '🗑️', label: 'Deleted', col: '#C62828', bg: '#ffebee' },
    estimate_created: { icon: '📋', label: 'Estimate', col: '#3949AB', bg: '#e8eaf6' },
    estimate_edited: { icon: '📝', label: 'Est.Edited', col: '#5E35B1', bg: '#ede7f6' },
    estimate_converted: { icon: '🔁', label: 'Converted', col: '#00838F', bg: '#e0f7fa' },
    estimate_deleted: { icon: '🗑️', label: 'Est.Del', col: '#B71C1C', bg: '#ffebee' },
    payment_recorded: { icon: '💰', label: 'Payment', col: '#388E3C', bg: '#e8f5e9' },
    status_changed: { icon: '🔄', label: 'Status', col: '#E65100', bg: '#fbe9e7' },
    client_added: { icon: '👤', label: 'Client', col: '#00897B', bg: '#e0f2f1' },
    client_edited: { icon: '✏️', label: 'Cl.Edited', col: '#0288D1', bg: '#e1f5fe' },
    client_deleted: { icon: '🗑️', label: 'Cl.Deleted', col: '#B71C1C', bg: '#ffebee' },
    client_activated: { icon: '✅', label: 'Activated', col: '#2E7D32', bg: '#E8F5E9' },
    client_deactivated: { icon: '⏸️', label: 'Inactive', col: '#F9A825', bg: '#FFF8E1' },
    reminder_sent: { icon: '🔔', label: 'Reminder', col: '#F9A825', bg: '#fff8e1' },
    expense_added: { icon: '💸', label: 'Expense', col: '#455A64', bg: '#eceff1' },
    email_sent: { icon: '📧', label: 'Email', col: '#0288D1', bg: '#e1f5fe' },
    wa_send: { icon: '💬', label: 'WhatsApp', col: '#2E7D32', bg: '#e8f5e9' },
    wa_log: { icon: '💬', label: 'WhatsApp', col: '#2E7D32', bg: '#e8f5e9' },
    login: { icon: '🔐', label: 'Login', col: '#5C6BC0', bg: '#e8eaf6' },
    logout: { icon: '🚪', label: 'Logout', col: '#78909C', bg: '#eceff1' },
    credit_note: { icon: '📝', label: 'Credit', col: '#6D4C41', bg: '#efebe9' },
    create: { icon: '➕', label: 'Created', col: '#1976D2', bg: '#e3f2fd' },
    delete: { icon: '🗑️', label: 'Deleted', col: '#C62828', bg: '#ffebee' },
    update: { icon: '✏️', label: 'Updated', col: '#7B1FA2', bg: '#f3e5f5' },
  };
  return map[type] || { icon: '•', label: type, col: '#9E9E9E', bg: '#f5f5f5' };
}

function _renderActivityTimeline(reset) {
  const el = document.getElementById('activity-timeline');
  const lm = document.getElementById('activity-load-more');
  if (!el) return;
  if (reset) el.innerHTML = '';

  if (!_actFiltered.length) {
    el.innerHTML = `<div style="text-align:center;padding:60px;color:var(--muted);background:var(--card);border-radius:var(--r);border:1px solid var(--border)">
      <i class="fas fa-history" style="font-size:32px;opacity:.15;display:block;margin-bottom:12px"></i>
      No activity yet. Actions like creating invoices, recording payments, and adding expenses will appear here.
    </div>`;
    if (lm) lm.style.display = 'none';
    return;
  }

  const allGroups = _groupActivityEvents(_actFiltered);
  const start = _actPage * _ACT_PER;
  const chunk = allGroups.slice(0, start + _ACT_PER);

  let lastDate = '';
  const html = chunk.map((g, gi) => {
    const rep = g.events[0];
    const info = _actTypeInfo(g.key);
    const d = rep.ts ? new Date(rep.ts) : new Date();
    const dateStr = d.toLocaleDateString(_moneyLocale(), { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
    const timeStr = d.toLocaleTimeString(_moneyLocale(), { hour: '2-digit', minute: '2-digit' });
    const multi = g.events.length > 1;
    const gid = `actg-${gi}`;

    const invRef = _extractInvRef(rep);
    const client = g.events.map(e => {
      if ((e.label || '').match(/^[A-Z][a-zA-Z ]{2,}/)) return e.label;
      const si = (e.detail || '').indexOf('|SNAPSHOT:');
      if (si > -1) { try { const p = JSON.parse((e.detail || '').substring(si + 10)); if (p?.client_name) return p.client_name; } catch (x) { } }
      return '';
    }).find(c => c) || '';
    const subtitle = [invRef, client].filter(Boolean).join(' · ');

    let dateHeader = '';
    if (dateStr !== lastDate) {
      lastDate = dateStr;
      dateHeader = `<div style="display:flex;align-items:center;gap:10px;margin:12px 0 6px">
        <div style="flex:1;height:1px;background:var(--border)"></div>
        <span style="font-size:11px;font-weight:700;color:var(--muted);white-space:nowrap">${dateStr}</span>
        <div style="flex:1;height:1px;background:var(--border)"></div>
      </div>`;
    }

    const children = multi ? g.events.map(e => {
      const _snapIdx = (e.detail || '').indexOf('|SNAPSHOT:');
      const rawDetail = _snapIdx > -1 ? (e.detail || '').substring(0, _snapIdx).trim() : (e.detail || '').trim();
      const snapRaw = _snapIdx > -1 ? (e.detail || '').substring(_snapIdx + 10) : '';
      const snapJson = snapRaw ? (() => { try { return JSON.stringify(JSON.parse(snapRaw), null, 2); } catch (x) { return snapRaw; } })() : '';
      const snapId = `snap-${e.id}`;
      const src = ['create', 'delete', 'update', 'email_sent', 'wa_send', 'login', 'logout'].includes(e.type) ? 'PHP' : 'JS';
      const childTime = e.ts ? new Date(e.ts).toLocaleTimeString(_moneyLocale(), { hour: '2-digit', minute: '2-digit' }) : '';
      const eInfo = _actTypeInfo(_actGroupKey(e));
      return `<div style="display:flex;align-items:flex-start;gap:10px;padding:8px 14px 8px 14px;border-bottom:1px solid var(--border)">
        <div style="width:26px;height:26px;border-radius:6px;background:${eInfo.bg};display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0">${eInfo.icon}</div>
        <div style="flex:1;min-width:0">
          <div style="font-size:11px;color:var(--muted);margin-bottom:2px">${e.type || ''} · ${e.label || ''} · <span style="background:${src === 'PHP' ? '#e3f2fd' : '#f3e5f5'};color:${src === 'PHP' ? '#1565C0' : '#6A1B9A'};padding:1px 5px;border-radius:4px;font-size:10px;font-weight:600">${src}</span></div>
          <div style="font-size:12px;color:var(--text)">${rawDetail || '—'}</div>
          ${snapJson ? `<span onclick="document.getElementById('${snapId}').style.display=document.getElementById('${snapId}').style.display==='none'?'block':'none';this.textContent=this.textContent.startsWith('+')?'− hide snapshot':'+ show snapshot'" style="font-size:11px;color:var(--primary);cursor:pointer;margin-top:3px;display:inline-block">+ show snapshot</span>
          <pre id="${snapId}" style="display:none;font-size:10px;color:var(--muted);background:var(--hover);padding:6px 8px;border-radius:6px;margin-top:4px;overflow-x:auto;white-space:pre-wrap;word-break:break-all">${snapJson}</pre>` : ''}
        </div>
        <div style="font-size:11px;color:var(--muted);flex-shrink:0;white-space:nowrap">${childTime}</div>
      </div>`;
    }).join('') : '';

    const singleDetail = !multi && rep.detail ? (() => {
      const _sd = (rep.detail || '').indexOf('|SNAPSHOT:'); const d2 = _sd > -1 ? (rep.detail || '').substring(0, _sd).trim() : (rep.detail || '').trim();
      return d2 ? `<div style="font-size:12px;color:var(--muted);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${d2}</div>` : '';
    })() : '';
    const childrenBlock = multi ? `<div id="${gid}-children" style="display:none;border-top:1px solid var(--border)">${children}</div>` : '';
    const chevron = multi ? `<i class="fas fa-chevron-down" id="${gid}-chev" style="font-size:11px;color:var(--muted);transition:transform .2s;flex-shrink:0"></i>` : '';
    const countBadge = multi ? `<span style="font-size:11px;background:var(--hover);color:var(--muted);border-radius:10px;padding:1px 7px;font-weight:600">${g.events.length} events</span>` : '';
    const cursor = multi ? 'cursor:pointer' : '';
    const onclick = multi ? `onclick="(function(){var c=document.getElementById('${gid}-children'),ch=document.getElementById('${gid}-chev');var o=c.style.display==='none';c.style.display=o?'block':'none';ch.style.transform=o?'rotate(180deg)':'rotate(0deg)';})()"` : '';

    return `${dateHeader}<div style="background:var(--card);border:1px solid var(--border);border-radius:8px;margin-bottom:6px;overflow:hidden">
      <div style="display:flex;gap:12px;padding:10px 14px;align-items:flex-start;${cursor}" ${onclick}>
        <div style="width:32px;height:32px;border-radius:8px;background:${info.bg};display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0">${info.icon}</div>
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:2px;flex-wrap:wrap">
            <span style="font-size:13px;font-weight:600;color:var(--text)">${g.key.replace(/_/g, ' ')}</span>
            <span style="font-size:11px;padding:1px 7px;border-radius:10px;background:${info.col}15;color:${info.col};font-weight:700">${info.label}</span>
            ${countBadge}
          </div>
          <div style="font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${subtitle}</div>
          ${singleDetail}
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
          <div style="font-size:11px;color:var(--muted);white-space:nowrap">${timeStr}</div>
          ${chevron}
        </div>
      </div>
      ${childrenBlock}
    </div>`;
  }).join('');

  if (reset) el.innerHTML = html; else el.innerHTML += html;
  if (lm) lm.style.display = chunk.length < allGroups.length ? 'block' : 'none';
}

function exportActivityCSV() {
  const rows = [['Timestamp', 'Type', 'Label', 'Detail']];
  _actFiltered.forEach(e => rows.push([e.ts || '', e.type || '', e.label || '', e.detail || '']));
  _downloadCSV(rows, 'activity_log.csv');
}

async function clearActivityLog() {
  const result = await Swal.fire({ title: 'Clear Activity Log?', text: 'The entire activity log will be permanently deleted.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Clear All', cancelButtonText: 'Cancel', confirmButtonColor: '#E53935', customClass: { popup: 'swal-compact' } });
  if (!result.isConfirmed) return;
  api('/api/activity.php', 'DELETE').then(() => {
    STATE.activity = []; renderActivityLog(); toast('🗑️ Activity log cleared', 'info');
  }).catch(e => toast('❌ ' + e.message, 'error'));
}

// Local fallback so this page doesn't depend on invoices.js being loaded.
if (typeof _downloadCSV !== 'function') {
  window._downloadCSV = function (rows, filename) {
    const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = filename;
    document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
  };
}
