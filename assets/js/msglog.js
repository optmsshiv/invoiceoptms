// ================================================================
//  assets/js/msglog.js
//  Requires: common.js, shared-data.js, wa-shared.js (loaded before
//  this file — resendWALog needs sendWA() from wa-shared.js).
//  For pages/comms/msglog.php (WhatsApp message log).
//
//  NOTE: the old SPA had this exact module defined twice — an
//  early draft using a stale localStorage-only exportMsgLog(), and
//  the real one further down using WA_LOG.fetchLog() (the DB-backed
//  source). Since JS function declarations get overwritten by later
//  ones in the same scope, only the second (DB-backed) version ever
//  actually ran — that's the one ported here. It also had two
//  separate DOMContentLoaded listeners (one for initial load, one
//  for auto-refresh); merged into a single init below.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['invoices', 'clients']);
  renderWALog(); // initial load

  // Auto-refresh every 60 seconds — only when tab is visible
  setInterval(() => {
    if (document.visibilityState === 'visible') renderWALog();
  }, 60000);
});

async function renderWALog(resetPage = false) {
    // ── 1. Loading bar ──────────────────────────────────────────────
 // const btn = document.getElementById('wa-log-refresh-btn');
  let bar = document.getElementById('wa-log-loading-bar');
  if (!bar) {
    bar = document.createElement('div');
    bar.id = 'wa-log-loading-bar';
    bar.style.cssText = `
      position:fixed; top:0; left:0; height:3px; width:0%;
      background:linear-gradient(90deg,var(--teal),var(--teal-l));
      z-index:9999; border-radius:0 2px 2px 0;
      transition:width .3s ease, opacity .4s ease;
      box-shadow:0 0 8px var(--teal-l);
    `;
    document.body.appendChild(bar);
  }
  // Animate bar start
  bar.style.opacity = '1';
  bar.style.width = '0%';
  requestAnimationFrame(() => { bar.style.width = '70%'; });
  // ── 2. REFRESH BUTTON — spinner + disabled ────────────────────
  const btn = document.getElementById('wa-log-refresh-btn');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Refreshing…';
  }

    // ── 3. SKELETON ROWS — show immediately while API loads ───────
  const tbodyEarly = document.querySelector('#wa-log-table tbody');
  if (tbodyEarly) {
    const skeletonRow = () => `
      <tr style="animation:wa-skeleton-pulse 1.2s ease-in-out infinite">
        <td><div style="height:12px;border-radius:4px;background:#E8EAED;width:80px;margin-bottom:5px"></div>
            <div style="height:10px;border-radius:4px;background:#F3F4F6;width:60px"></div></td>
        <td><div style="height:22px;border-radius:6px;background:#E8EAED;width:70px"></div></td>
        <td><div style="height:12px;border-radius:4px;background:#E8EAED;width:100px;margin-bottom:5px"></div>
            <div style="height:10px;border-radius:4px;background:#F3F4F6;width:70px"></div></td>
        <td><div style="height:12px;border-radius:4px;background:#E8EAED;width:90px;margin-bottom:5px"></div>
            <div style="height:10px;border-radius:4px;background:#F3F4F6;width:60px"></div></td>
        <td><div style="height:12px;border-radius:4px;background:#E8EAED;width:140px"></div></td>
        <td><div style="height:22px;border-radius:6px;background:#E8EAED;width:65px"></div></td>
        <td><div style="height:28px;border-radius:6px;background:#E8EAED;width:70px"></div></td>
      </tr>`;
    tbodyEarly.innerHTML = Array(6).fill(0).map(skeletonRow).join('');
  }


  try {
    const logs = await WA_LOG.fetchLog();
    const searchTerm   = document.getElementById('msglog-search')?.value?.toLowerCase() || '';
    const filterType   = document.getElementById('msglog-filter-type')?.value || '';
    const filterStatus = document.getElementById('msglog-filter-status')?.value || '';

    let filtered = logs.filter(log => {
      const matchSearch = !searchTerm ||
        (log.client  && log.client.toLowerCase().includes(searchTerm)) ||
        (log.phone   && log.phone.includes(searchTerm)) ||
        (log.inv_num && log.inv_num.toLowerCase().includes(searchTerm));
      const matchType   = !filterType   || log.type   === filterType;
      const matchStatus = !filterStatus || log.status === filterStatus;
      return matchSearch && matchType && matchStatus;
    });

    filtered.sort((a, b) => new Date(b.ts) - new Date(a.ts));

    // Stats
    const stats = {
      total:   logs.length,
      sent:    logs.filter(l => l.status === 'sent_api').length,
      manual:  logs.filter(l => l.status === 'sent_web').length,
      sending: logs.filter(l => l.status === 'sending').length,
      failed:  logs.filter(l => l.status === 'failed').length,
    };
    ['total','sent','manual','sending','failed'].forEach(k => {
      const el = document.getElementById('wa-stat-' + k);
      if (el) el.textContent = stats[k];
    });

    const tbody = document.querySelector('#wa-log-table tbody');
    if (!tbody) return;

    if (filtered.length === 0) {
      tbody.innerHTML = `<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--muted)">
        <i class="fas fa-search" style="font-size:32px;opacity:.2;display:block;margin-bottom:8px"></i>
        No messages match your filters</td></tr>`;
      _renderWALogPagination(0, 0);
      return;
    }

    // Pagination
    if (resetPage) WA_LOG_PAGE.current = 1;
    const totalPages = Math.ceil(filtered.length / WA_LOG_PAGE.perPage);
    WA_LOG_PAGE.current = Math.min(WA_LOG_PAGE.current, totalPages);
    const start = (WA_LOG_PAGE.current - 1) * WA_LOG_PAGE.perPage;
    const page  = filtered.slice(start, start + WA_LOG_PAGE.perPage);

    _renderWALogPagination(filtered.length, totalPages);

    // Badge configs
    const typeBadge = {
      invoice_created:  { bg:'#F0EFFD', color:'#5B52C7', icon:'fa-file',                label:'New Invoice', alert:false },
      estimate_created: { bg:'#EDFAF4', color:'#1A7A5E', icon:'fa-file-alt',            label:'Estimate',    alert:false },
      payment_received: { bg:'#EDFAF0', color:'#1E7E34', icon:'fa-check-circle',        label:'Payment',     alert:false },
      partial_payment:  { bg:'#FFF4E5', color:'#B45309', icon:'fa-bolt',                label:'Partial Pay', alert:false },
      split_payment:    { bg:'#FFF4E5', color:'#B45309', icon:'fa-random',              label:'Split Pay',   alert:false },
      payment_overdue:  { bg:'#FEF0EF', color:'#C0392B', icon:'fa-exclamation-triangle',label:'Overdue',     alert:true  },
      payment_reminder: { bg:'#EEF5FF', color:'#2563EB', icon:'fa-bell',               label:'Reminder',    alert:true  },
      invoice_followup:  { bg:'#FDF0F7', color:'#9D174D', icon:'fa-phone-alt',          label:'Follow-up',      alert:true  },
      balance_reminder:  { bg:'#FFF4E5', color:'#92400E', icon:'fa-wallet',             label:'Bal. Reminder',  alert:false },
      festival:          { bg:'#F0EFFD', color:'#5B52C7', icon:'fa-star',               label:'Festival',       alert:false },
      unknown:           { bg:'#F5F5F5', color:'#888',    icon:'fa-question-circle',    label:'Unknown',        alert:false },
    };
    const statusBadge = {
      sent_api: { bg:'#EDFAF0', color:'#1E7E34', icon:'fa-check',         label:'Sent (API)' },
      sent_web: { bg:'#EEF5FF', color:'#2563EB', icon:'fa-mobile-alt',    label:'Manual'     },
      sending:  { bg:'#FFF9EC', color:'#B45309', icon:'fa-circle-notch',  label:'Sending'    },
      failed:   { bg:'#FEF0EF', color:'#C0392B', icon:'fa-times-circle',  label:'Failed'     },
    };
    const invStatusColor = { Paid:'#1E7E34', Partial:'#B45309', Overdue:'#C0392B', Pending:'#2563EB', Draft:'#888', Cancelled:'#888' };

    tbody.innerHTML = page.map(log => {
      // Time cell
      const tsDate   = log.ts ? new Date(log.ts) : null;
      const diffDays = tsDate ? (Date.now() - tsDate.getTime()) / 86400000 : 999;
      let timeCell;
      if (diffDays <= 5) {
        const relStr  = WA_LOG.formatTimeRelative(log.ts);
        const timeStr = tsDate.toLocaleTimeString('en-IN', { hour:'2-digit', minute:'2-digit', hour12:true, timeZone:'Asia/Kolkata' });
        const dateStr = tsDate.toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric', timeZone:'Asia/Kolkata' });
        timeCell = `<div style="font-size:12px;font-weight:600;color:var(--text);white-space:nowrap">${relStr}</div>
          <div style="font-size:11px;color:var(--muted);font-family:var(--mono);margin-top:1px;white-space:nowrap">${timeStr} · ${dateStr}</div>`;
      } else {
        const fullStr = tsDate.toLocaleString('en-IN', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true, timeZone:'Asia/Kolkata' });
        timeCell = `<div style="font-size:12px;font-weight:600;color:var(--text);white-space:nowrap">${fullStr}</div>`;
      }

      // Type badge
      const tb = typeBadge[log.type] || typeBadge.unknown;
      const alertStyle = tb.alert ? `border-left:3px solid ${tb.color};border-radius:0 6px 6px 0;` : 'border-radius:6px;';
      const typePill = `<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 9px;${alertStyle}font-size:11px;font-weight:600;background:${tb.bg};color:${tb.color};white-space:nowrap"><i class="fas ${tb.icon}" style="font-size:10px"></i>${tb.label}</span>`;

      // Status badge
      const sb = statusBadge[log.status] || { bg:'#F5F5F5', color:'#888', icon:'fa-circle', label:log.status };
      const statusPill = `<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:6px;font-size:11px;font-weight:600;background:${sb.bg};color:${sb.color};white-space:nowrap"><i class="fas ${sb.icon}" style="font-size:10px"></i>${sb.label}</span>`;

      // Message
      const msgText  = (log.status === 'failed' && log.error) ? log.error : (log.msg || '—');
      const msgColor = log.status === 'failed' ? '#C0392B' : 'var(--muted)';
      const msgShort = msgText.length > 55 ? msgText.substring(0,55)+'…' : msgText;

      // Invoice status subline
      const isc = invStatusColor[log.inv_status] || '#888';
      const invSubline = `<span style="color:var(--muted)">${log.inv_amt || '—'}</span>${log.inv_status ? `<span style="color:${isc};margin-left:6px;font-weight:600;font-size:10px">${log.inv_status}</span>` : ''}`;

      // Resend button only
      const logJson = JSON.stringify(log).replace(/"/g,'&quot;');
      const resendBtn = `<button onclick="resendWALog(this)" data-log="${logJson}" title="Resend"
        style="display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:6px;border:1px solid #BBD6FD;background:#EEF5FF;color:#2563EB;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap">
        <i class="fas fa-paper-plane" style="font-size:10px"></i> Resend</button>`;

      return `<tr>
        <td style="white-space:nowrap;min-width:130px">${timeCell}</td>
        <td style="white-space:nowrap">${typePill}</td>
        <td style="min-width:160px">
          <div style="font-size:13px;font-weight:600;color:var(--text)">${log.client || '—'}</div>
          <div style="font-size:11px;color:var(--muted);font-family:var(--mono);margin-top:1px">${log.phone || '—'}</div>
        </td>
        <td style="min-width:140px">
          <div style="font-size:13px;font-weight:600;color:var(--text)">${log.inv_num || '—'}</div>
          <div style="font-size:11px;font-family:var(--mono);margin-top:1px">${invSubline}</div>
        </td>
        <td><div style="font-size:12px;color:${msgColor};overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px" title="${msgText.replace(/"/g,'&quot;')}">${msgShort}</div></td>
        <td style="white-space:nowrap;min-width:110px">${statusPill}</td>
        <td style="white-space:nowrap">${resendBtn}</td>
      </tr>`;
    }).join('');

  } catch(e) {
    console.error('Error rendering WA log:', e);
    toast('❌ Could not load WA logs: ' + e.message, 'error');
  } finally {
    // ── 4. Finish loading bar ──────────────────────────────────────
    const bar = document.getElementById('wa-log-loading-bar');
    if (bar) {
      bar.style.width = '100%';
      setTimeout(() => { bar.style.opacity = '0'; bar.style.width = '0%'; }, 400);
    }
    // ── 5. RESTORE REFRESH BUTTON ─────────────────────────────
    const btn = document.getElementById('wa-log-refresh-btn');
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
    }
    // ── Last refreshed timestamp ──────────────────────────────
    const tsEl = document.getElementById('wa-log-last-refresh');
    if (tsEl) tsEl.textContent = 'Updated ' + new Date().toLocaleTimeString('en-IN', {
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true, timeZone: 'Asia/Kolkata'
    });
  }
}

function _renderWALogPagination(total, totalPages) {
  let el = document.getElementById('wa-log-pagination');
  if (!el) {
    el = document.createElement('div');
    el.id = 'wa-log-pagination';
    const table = document.getElementById('wa-log-table');
    if (table && table.parentNode) table.parentNode.insertBefore(el, table.nextSibling);
  }
  if (totalPages <= 1) { el.innerHTML = ''; return; }
  const cur = WA_LOG_PAGE.current;
  const btnStyle = (active) => `display:inline-flex;align-items:center;justify-content:center;min-width:30px;height:30px;padding:0 8px;border-radius:6px;border:1px solid ${active?'#2563EB':'var(--border,#ddd)'};background:${active?'#2563EB':'var(--bg,#fff)'};color:${active?'#fff':'var(--text)'};font-size:12px;font-weight:${active?'700':'400'};cursor:pointer;margin:0 2px`;
  const start = (cur - 1) * WA_LOG_PAGE.perPage + 1;
  const end   = Math.min(cur * WA_LOG_PAGE.perPage, total);

  let pages = '';
  for (let i = 1; i <= totalPages; i++) {
    if (i === 1 || i === totalPages || (i >= cur-2 && i <= cur+2)) {
      pages += `<button onclick="WA_LOG_PAGE.current=${i};renderWALog()" style="${btnStyle(i===cur)}">${i}</button>`;
    } else if (i === cur-3 || i === cur+3) {
      pages += `<span style="padding:0 4px;color:var(--muted)">…</span>`;
    }
  }

  el.innerHTML = `<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 4px;font-size:12px;color:var(--muted)">
    <span>Showing ${start}–${end} of ${total}</span>
    <div style="display:flex;align-items:center">
      <button onclick="if(WA_LOG_PAGE.current>1){WA_LOG_PAGE.current--;renderWALog();}" style="${btnStyle(false)}" ${cur===1?'disabled':''}>‹</button>
      ${pages}
      <button onclick="if(WA_LOG_PAGE.current<${totalPages}){WA_LOG_PAGE.current++;renderWALog();}" style="${btnStyle(false)}" ${cur===totalPages?'disabled':''}>›</button>
    </div>
  </div>`;
}

// Resend — handles both regular clients and one-time clients
function resendWALog(btn) {
  let log;
  try { log = JSON.parse(btn.dataset.log); } catch(e) { toast('Could not read log entry', 'error'); return; }

  // Try to find phone: from log directly first (covers one-time clients)
  const phoneFromLog = (log.phone || '').replace(/\D/g,'');

  const inv    = STATE.invoices.find(i => String(i.id) === String(log.inv_id));
  const client = inv ? (STATE.clients.find(c => String(c.id) === String(inv.client)) || {}) : {};
  const phoneFromClient = (client.wa || client.whatsapp || client.phone || '').replace(/\D/g,'');

  const phone = phoneFromLog || phoneFromClient;
  if (!phone) { toast('No phone number found for this message', 'warning'); return; }

  // Use inv from STATE if available, else build a minimal object from log
  const invObj = inv || { id: log.inv_id, num: log.inv_num, invoice_number: log.inv_num, amount: 0, status: log.inv_status || 'Pending' };
  const clientObj = Object.keys(client).length ? client : { name: log.client, phone: phoneFromLog };

  sendWA(phone, log.msg || '', log.type, invObj, clientObj)
    .then(() => { toast('✅ Message resent!', 'success'); renderWALog(); })
    .catch(e  => toast('❌ Resend failed: ' + e.message, 'error'));
}
async function exportMsgLog() {
  try {
    const logs = await WA_LOG.fetchLog();
    if (logs.length === 0) {
      toast('No messages to export', 'info');
      return;
    }

    // Prepare CSV
    const headers = ['Time', 'Type', 'Client', 'Phone', 'Invoice', 'Amount', 'Message', 'Status', 'Error'];
    const rows = logs.map(log => [
      log.ts,
      log.type,
      log.client || '',
      log.phone || '',
      log.inv_num || '',
      log.inv_amt || '',
      (log.msg || '').replace(/"/g, '""'),  // Escape quotes
      log.status,
      log.error || ''
    ]);

    // Generate CSV content
    let csv = headers.map(h => `"${h}"`).join(',') + '\n';
    csv += rows.map(row => row.map(val => `"${val}"`).join(',')).join('\n');

    // Download
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.setAttribute('href', URL.createObjectURL(blob));
    link.setAttribute('download', `wa_log_${new Date().toISOString().split('T')[0]}.csv`);
    link.click();

    toast('✓ Log exported as CSV', 'success');
  } catch(e) {
    toast('✗ Export failed: ' + e.message, 'error');
  }
}

