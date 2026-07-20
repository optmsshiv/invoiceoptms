// ================================================================
//  assets/js/invoices.js
//  Requires: common.js, shared-data.js (loaded before this file).
//
//  MPA CHANGES from the SPA version:
//  1. "New Invoice" button is now a real <a href> in invoices.php,
//     not a showPage() call.
//  2. refreshInvoices() / confirmDelete() / changeInvoiceStatus()
//     used to also call renderDashRecent()/renderDonutChart()/
//     updateDashStats() — dashboard-page functions that don't exist
//     here. Removed those calls; they're dashboard's job, not this
//     page's, and calling undefined functions would throw and abort
//     the rest of the handler.
//
//  NOT YET BUILT — clicking these will do nothing (or error in
//  console) until a later step:
//   - openPreviewModal(id)  — the "eye" button. Depends on the
//     invoice PDF template engine (buildTpl2/buildTplF), which is
//     its own large subsystem shared with pdf.php and create.php.
//   - openRowMenu(e, id)    — the "⋮" button. Duplicate/convert/etc.
//   - openPaidModal(id)     — recording a payment. ~400 lines on
//     its own; deliberately deferred rather than rushed.
//   - sendWAForInvoice(inv) — WhatsApp sending. Needs a shared
//     wa-shared.js (formatWAMsg, sendWA, logWAMessage, templates)
//     used by invoices, reminders, and whatsapp pages alike.
//  Delete works fully; Quick Status change works fully except the
//  Paid/Partial transitions, which correctly hand off to
//  openPaidModal (also deferred).
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['invoices', 'clients', 'payments', 'settings']);
  renderInvoicesTable();
});

async function refreshInvoices() {
  const btn = document.getElementById('inv-refresh-btn');
  if (btn) { btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing…'; btn.disabled = true; }
  try {
    const [invRes, payRes] = await Promise.all([api('api/invoices.php'), api('api/payments.php')]);
    if (invRes?.data) { STATE.invoices = invRes.data.map(normalizeInvoice); STATE.filteredInvoices = [...STATE.invoices]; }
    if (payRes?.data) STATE.payments = payRes.data;
    renderInvoicesTable();
    toast('🔄 Invoices refreshed', 'info');
  } catch (e) {
    toast('❌ Refresh failed: ' + e.message, 'error');
  } finally {
    if (btn) { btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh'; btn.disabled = false; }
  }
}

function renderInvoicesTable() {
  STATE.filteredInvoices = [...STATE.invoices];
  const badgeInv = document.getElementById('badge-invoices');
  if (badgeInv) badgeInv.textContent = STATE.invoices.length;
  populateClientFilter();
  applyFiltersAndRender();
}

function applyFiltersAndRender() {
  const tbody = document.getElementById('invoicesTbody');
  if (!tbody) return;
  const start = (STATE.currentPage - 1) * STATE.invoicesPerPage;
  const end   = start + STATE.invoicesPerPage;
  const page  = STATE.filteredInvoices.slice(start, end);

  tbody.innerHTML = page.map(inv => {
    const c = STATE.clients.find(x => x.id === inv.client) || { name: inv.client_name || inv.clientName || 'One-Time Client', color: '#607D8B' };
    const isClientInactive = c.id && (parseInt(c.active) === 0 || c.status === 'inactive');
    const avatarColor = isClientInactive ? '#9E9E9E' : c.color;
    const initials = getInitials(c.name);
    const avatar = isValidImg(c.image)
      ? `<div class="cc-avatar" id="cca-${c.id}" style="background:${avatarColor};opacity:${isClientInactive ? '.6' : '1'}"><img src="${c.image}" alt="${c.name}" onerror="this.style.display='none'"></div>`
      : `<div class="cc-avatar" style="background:${avatarColor};opacity:${isClientInactive ? '.6' : '1'}">${initials}</div>`;
    const inactivePill = isClientInactive
      ? `<span style="font-size:9px;font-weight:700;background:#FFF8E1;color:#F9A825;border:1px solid #F9A825;border-radius:8px;padding:1px 5px;margin-left:4px;vertical-align:middle;white-space:nowrap"><i class="fas fa-pause-circle" style="font-size:8px"></i> Inactive</span>`
      : '';

    const invId = String(inv.id);
    const paidPayments = STATE.payments.filter(p => p.invoice_id && String(p.invoice_id) === invId);
    const totalPaid = paidPayments.reduce((s, p) => s + parseFloat(p.amount || 0), 0);
    let paidCell = '';
    if (inv.status === 'Paid') {
      const lastPmt = paidPayments.slice().sort((a, b) => new Date(b.date || b.payment_date || 0) - new Date(a.date || a.payment_date || 0))[0];
      const paidDateRaw = lastPmt ? (lastPmt.date || lastPmt.payment_date || '') : '';
      const paidDateFmt = paidDateRaw ? new Date(paidDateRaw).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '';
      paidCell = paidDateFmt
        ? `<span style="display:inline-flex;align-items:center;gap:4px;background:#E3F2FD;color:#1565C0;font-size:11px;font-weight:700;padding:3px 8px;border-radius:20px;white-space:nowrap"><i class="fas fa-calendar-check" style="font-size:10px"></i> ${paidDateFmt}</span>`
        : `<span style="display:inline-flex;align-items:center;gap:4px;background:#E8F5E9;color:#2E7D32;font-size:11px;font-weight:700;padding:3px 8px;border-radius:20px;white-space:nowrap"><i class="fas fa-check-circle" style="font-size:10px"></i> Full</span>`;
    } else if (inv.status === 'Partial' && totalPaid > 0) {
      const remaining = Math.max(0, inv.amount - totalPaid);
      paidCell = `<div style="display:flex;flex-direction:column;align-items:center;gap:2px">
        <span style="background:#E8F5E9;color:#2E7D32;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;font-family:var(--mono)">${fmt_money(totalPaid)}</span>
        <span style="color:var(--red);font-size:10px;font-weight:600;font-family:var(--mono)">-${fmt_money(remaining)}</span>
      </div>`;
    } else {
      paidCell = `<span style="color:var(--muted2);font-size:12px">—</span>`;
    }

    const today = new Date(); today.setHours(0, 0, 0, 0);
    const dueDate = inv.due ? new Date(inv.due) : null;
    const issuedDate = inv.issued ? new Date(inv.issued) : null;
    const isPaidOrCancelled = inv.status === 'Paid' || inv.status === 'Cancelled';
    let dueCellStyle = '', overdueBadge = '';
    if (dueDate && !isPaidOrCancelled) {
      const diffDays = Math.round((dueDate - today) / 86400000);
      if (diffDays < 0) {
        dueCellStyle = 'color:var(--red);font-weight:700';
        overdueBadge = `<span style="display:inline-block;margin-left:4px;font-size:9px;font-weight:700;background:var(--red);color:#fff;border-radius:10px;padding:1px 5px">+${Math.abs(diffDays)}d</span>`;
      } else if (diffDays <= 7) {
        dueCellStyle = 'color:#F9A825;font-weight:700';
      }
    } else if (isPaidOrCancelled) {
      dueCellStyle = 'color:var(--muted2)';
    }
    const daysSinceIssued = issuedDate ? Math.round((today - issuedDate) / 86400000) : null;
    const issuedTooltip = daysSinceIssued !== null ? `title="Issued ${daysSinceIssued === 0 ? 'today' : daysSinceIssued + ' day' + (daysSinceIssued === 1 ? '' : 's') + ' ago'}"` : '';

    let progressBar = '';
    if (inv.status === 'Partial' && totalPaid > 0 && inv.amount > 0) {
      const pct = Math.min(100, Math.round(totalPaid / inv.amount * 100));
      progressBar = `<div style="margin-top:4px;height:3px;background:var(--border);border-radius:4px;overflow:hidden;width:80px;margin-inline:auto">
        <div style="height:100%;width:${pct}%;background:var(--teal);border-radius:4px;transition:width .4s"></div>
      </div>`;
    }

    const _SVC_PALETTES = [
      { bg: '#E8F4FD', color: '#1565C0' }, { bg: '#E8F5E9', color: '#2E7D32' },
      { bg: '#FFF3E0', color: '#E65100' }, { bg: '#F3E5F5', color: '#6A1B9A' },
      { bg: '#FCE4EC', color: '#880E4F' }, { bg: '#E0F7FA', color: '#00695C' },
      { bg: '#FFF8E1', color: '#F57F17' }, { bg: '#EDE7F6', color: '#4527A0' },
      { bg: '#E1F5FE', color: '#0277BD' }, { bg: '#F9FBE7', color: '#558B2F' },
    ];
    const _svcStr = String(inv.service || '');
    let _svcHash = 0;
    for (let i = 0; i < _svcStr.length; i++) _svcHash = (_svcHash * 31 + _svcStr.charCodeAt(i)) & 0xff;
    const _svcPalette = _SVC_PALETTES[_svcHash % _SVC_PALETTES.length];
    const serviceBadge = _svcStr
      ? `<span style="display:inline-block;font-size:11px;font-weight:500;padding:2px 9px;border-radius:20px;background:${_svcPalette.bg};color:${_svcPalette.color};white-space:nowrap;max-width:160px;overflow:hidden;text-overflow:ellipsis">${_svcStr}</span>`
      : '—';

    const _statusMap = {
      Paid: { icon: 'fa-check-circle', label: 'Paid' },
      Partial: { icon: 'fa-clock', label: 'Partial' },
      Pending: { icon: 'fa-hourglass-half', label: 'Pending' },
      Overdue: { icon: 'fa-exclamation-circle', label: 'Overdue' },
      Cancelled: { icon: 'fa-ban', label: 'Cancelled' },
      Draft: { icon: 'fa-pencil-alt', label: 'Draft' },
    };
    const _sm = _statusMap[inv.status] || { icon: 'fa-circle', label: inv.status };
    const statusBadgeHtml = `<span class="badge badge-${inv.status.toLowerCase()} inv-status-badge" style="cursor:pointer;display:inline-flex;align-items:center;gap:4px;white-space:nowrap"
      title="${inv.status === 'Cancelled' && inv.cancel_reason ? '🚫 Reason: ' + inv.cancel_reason : 'Click to change status'}"
      onclick="openQuickStatus(event,'${inv.id}')"><i class="fas ${_sm.icon}" style="font-size:9px"></i>${_sm.label}</span>${inv.status === 'Cancelled' && inv.cancel_reason ? `<i class="fas fa-info-circle" style="font-size:10px;color:var(--muted);margin-left:4px;cursor:default" title="🚫 ${inv.cancel_reason}"></i>` : ''}`;

    return `<tr data-id="${inv.id}">
      <td><input type="checkbox" class="inv-check" value="${inv.id}" onchange="updateBulkBar()"></td>
      <td><code style="font-family:var(--mono);color:var(--teal);font-weight:600;cursor:default" ${issuedTooltip}>${inv.num}</code></td>
      <td><div class="client-cell">${avatar}<div><div class="cc-name" style="${isClientInactive ? 'color:var(--muted)' : ''}word-break:break-word;white-space:normal">${c.name}${inactivePill}</div><div class="cc-sub">${c.person || ''}</div></div></div></td>
      <td>${serviceBadge}</td>
      <td>${inv.issued}</td>
      <td><span style="${dueCellStyle}">${inv.due}</span>${overdueBadge}</td>
      <td><strong style="font-family:var(--mono)">${fmt_money(inv.amount)}</strong></td>
      <td style="text-align:center">${paidCell}${progressBar}</td>
      <td>${statusBadgeHtml}</td>
      <td>
        <div class="action-cell">
          <button class="act-btn" title="Preview" onclick="openPreviewModal('${inv.id}')"><i class="fas fa-eye"></i></button>
          <button class="act-btn del" title="Delete" onclick="openDeleteModal('${inv.id}')"><i class="fas fa-trash"></i></button>
          <button class="act-btn menu-btn" title="More" onclick="openRowMenu(event,'${inv.id}')"><i class="fas fa-ellipsis-v"></i></button>
        </div>
      </td>
    </tr>`;
  }).join('') || `<tr><td colspan="10" style="text-align:center;padding:40px;color:var(--muted)"><i class="fas fa-file-invoice" style="font-size:32px;margin-bottom:12px;display:block;opacity:.3"></i>No invoices found</td></tr>`;

  renderPagination();
  const info = document.getElementById('tfInfo');
  if (info) info.textContent = `Showing ${page.length ? start + 1 : 0}–${Math.min(end, STATE.filteredInvoices.length)} of ${STATE.filteredInvoices.length}`;
}

function renderPagination() {
  const pg = document.getElementById('pagination');
  if (!pg) return;
  const total = Math.ceil(STATE.filteredInvoices.length / STATE.invoicesPerPage);
  let html = `<button class="pg-btn" onclick="gotoPage(${STATE.currentPage - 1})" ${STATE.currentPage <= 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;
  for (let i = 1; i <= Math.min(total, 10); i++) {
    html += `<button class="pg-btn ${i === STATE.currentPage ? 'active' : ''}" onclick="gotoPage(${i})">${i}</button>`;
  }
  html += `<button class="pg-btn" onclick="gotoPage(${STATE.currentPage + 1})" ${STATE.currentPage >= total ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;
  pg.innerHTML = html;
}
function gotoPage(p) {
  const total = Math.ceil(STATE.filteredInvoices.length / STATE.invoicesPerPage);
  if (p < 1 || p > total) return;
  STATE.currentPage = p;
  applyFiltersAndRender();
}

// ── Filters ────────────────────────────────────────────────────
// invoices.php reads ?filter=draft on load (see dashboard's draft
// pill link) and preselects the status dropdown to match.
(function applyUrlFilterParam() {
  document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const f = params.get('filter');
    if (f === 'draft') {
      const sel = document.getElementById('statusFilter');
      if (sel) { sel.value = 'Draft'; _applyAllFilters(); }
    }
  });
})();

function filterInvoices(val) { _applyAllFilters(); }
function filterByStatus(val) { _applyAllFilters(); }
function filterByService(val) { _applyAllFilters(); }
function filterByDate() { _applyAllFilters(); }
function filterByClient(val) {
  STATE._clientFilter = val;
  const sel = document.getElementById('clientFilter');
  if (sel) sel.value = val || '';
  _applyAllFilters();
}

function _applyAllFilters() {
  let list = [...STATE.invoices];
  const sv = document.getElementById('invSearch')?.value?.toLowerCase() || '';
  const stv = document.getElementById('statusFilter')?.value || '';
  const srv = document.getElementById('serviceFilter')?.value || '';
  const clf = STATE._clientFilter || '';
  const df = document.getElementById('dateFrom')?.value || '';
  const dt = document.getElementById('dateTo')?.value || '';
  if (sv) list = list.filter(i => { const c = STATE.clients.find(x => x.id === i.client); return i.num.toLowerCase().includes(sv) || (c && c.name.toLowerCase().includes(sv)) || i.service.toLowerCase().includes(sv) || i.status.toLowerCase().includes(sv); });
  if (stv) list = list.filter(i => i.status === stv);
  if (srv) list = list.filter(i => i.service === srv);
  if (clf) list = list.filter(i => String(i.client) === String(clf));
  if (df) list = list.filter(i => i.issued >= df);
  if (dt) list = list.filter(i => i.issued <= dt);
  STATE.filteredInvoices = list;
  STATE.currentPage = 1;
  applyFiltersAndRender();
}

function populateClientFilter() {
  const sel = document.getElementById('clientFilter');
  if (!sel) return;
  const cur = sel.value;
  sel.innerHTML = '<option value="">All Clients</option>';
  const sorted = [...STATE.clients].sort((a, b) => (a.name || '').localeCompare(b.name || ''));
  sorted.forEach(c => {
    const o = document.createElement('option');
    o.value = c.id; o.textContent = c.name;
    if (String(c.id) === String(cur)) o.selected = true;
    sel.appendChild(o);
  });
}

// ── Quick inline status change ────────────────────────────────
const QS_ALLOWED = {
  Draft: ['Pending', 'Cancelled'], Estimate: ['Cancelled'],
  Pending: ['Draft', 'Overdue', 'Cancelled'], Partial: ['Pending', 'Overdue', 'Cancelled'],
  Overdue: ['Draft', 'Cancelled'], Paid: [], Cancelled: ['Pending'],
};
const QS_HINTS = { Paid: 'Use Record Payment', Partial: 'Use Record Payment', Estimate: 'Use Convert flow' };

function openQuickStatus(e, id) {
  e.stopPropagation();
  const rm = document.getElementById('rowMenu');
  if (rm) rm.classList.remove('open');
  const inv = STATE.invoices.find(i => String(i.id) === String(id));
  if (!inv) return;

  const hasExistingPayment = STATE.payments.some(p => p.invoice_id && String(p.invoice_id) === String(inv.id));
  let allowed = [...(QS_ALLOWED[inv.status] || [])];
  if (hasExistingPayment) allowed = allowed.filter(s => s !== 'Pending' && s !== 'Draft');
  const allStatuses = ['Draft', 'Estimate', 'Pending', 'Partial', 'Paid', 'Overdue', 'Cancelled'];
  const menu = document.getElementById('quickStatusMenu');

  if (inv.status === 'Paid') {
    menu.innerHTML = `
      <div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;padding:4px 8px 4px">Change Status</div>
      <div style="padding:10px 12px;font-size:12px;color:var(--green);display:flex;align-items:center;gap:8px">
        <i class="fas fa-lock" style="font-size:11px"></i>
        Invoice is <strong>Paid</strong> — no changes allowed
      </div>`;
    menu.style.display = 'block';
    const r = e.target.getBoundingClientRect();
    menu.style.top = (r.bottom + 4) + 'px';
    menu.style.left = Math.min(r.left, window.innerWidth - 210) + 'px';
    menu._invId = id;
    return;
  }

  menu.innerHTML = `<div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;padding:4px 8px 6px">Change Status</div>`
    + allStatuses.map(s => {
      const active = s === inv.status;
      const permitted = allowed.includes(s);
      const hint = hasExistingPayment && (s === 'Pending' || s === 'Draft') ? 'Payment already recorded' : (QS_HINTS[s] || null);
      const disabled = !active && !permitted;
      if (disabled) {
        return `<div style="padding:7px 12px;border-radius:7px;font-size:12.5px;display:flex;align-items:center;gap:8px;opacity:.35;cursor:not-allowed">
          <span class="badge badge-${s.toLowerCase()}" style="font-size:10px;padding:2px 7px">${s}</span>
          ${hint ? `<span style="font-size:10px;color:var(--muted);margin-left:auto;white-space:nowrap">${hint}</span>` : ''}
        </div>`;
      }
      return `<div onclick="applyQuickStatus('${id}','${s}')"
        style="padding:7px 12px;border-radius:7px;cursor:${active ? 'default' : 'pointer'};font-size:12.5px;font-weight:${active ? '700' : '500'};background:${active ? 'var(--teal-bg)' : 'none'};color:${active ? 'var(--teal)' : 'var(--text)'};display:flex;align-items:center;gap:8px;opacity:${active ? .6 : 1}">
        <span class="badge badge-${s.toLowerCase()}" style="font-size:10px;padding:2px 7px">${s}</span>
        ${active ? '<i class="fas fa-check" style="margin-left:auto;font-size:10px;color:var(--teal)"></i>' : ''}
      </div>`;
    }).join('');

  menu.style.display = 'block';
  const r = e.target.getBoundingClientRect();
  const mh = 320;
  const top = (window.innerHeight - r.bottom < mh) ? Math.max(4, r.top - mh) : r.bottom + 4;
  menu.style.top = top + 'px';
  menu.style.left = Math.min(r.left, window.innerWidth - 210) + 'px';
  menu._invId = id;
}

async function applyQuickStatus(id, status) {
  document.getElementById('quickStatusMenu').style.display = 'none';
  const inv = STATE.invoices.find(i => String(i.id) === String(id));
  if (!inv || inv.status === status) return;

  // Paid/Partial must go through the payment modal — NOTE: openPaidModal()
  // is not built yet (see file header), so this hand-off is a no-op for now.
  if (status === 'Paid' || status === 'Partial') { openPaidModal(id); return; }

  if (status === 'Cancelled') {
    const reason = await promptCancelReason(inv);
    if (reason === null) return;
    changeInvoiceStatus(id, 'Cancelled', reason);
    return;
  }
  changeInvoiceStatus(id, status);
}

document.addEventListener('click', e => {
  const qs = document.getElementById('quickStatusMenu');
  if (qs && !qs.contains(e.target) && !e.target.classList.contains('inv-status-badge')) qs.style.display = 'none';
});

// ── Status change / delete / cancel ───────────────────────────
async function changeInvoiceStatus(id, newStatus, cancelReason = '') {
  const inv = STATE.invoices.find(i => String(i.id) === String(id));
  if (!inv) return;
  const oldStatus = inv.status;
  const label = newStatus === 'Pending' ? '📤 Made Pending' : newStatus === 'Cancelled' ? '🚫 Cancelled' : newStatus;
  const payload = { status: newStatus };
  if (newStatus === 'Cancelled' && cancelReason) payload.cancel_reason = cancelReason;
  try {
    await api('api/invoices.php?id=' + parseInt(id), 'PATCH', payload);
    inv.status = newStatus;
    if (newStatus === 'Cancelled' && cancelReason) inv.cancel_reason = cancelReason;
    applyFiltersAndRender();
    logActivity('status_changed', `Status → ${newStatus}: ${inv.num || inv.invoice_number}${cancelReason ? ' — ' + cancelReason : ''}`, inv.client_name || '', id);
    toast(`${label}: ${inv.num || inv.invoice_number}`, 'success');

    // Auto-fire WA when Draft/Cancelled → Pending, matching the SPA's
    // behavior — but sendWA/formatWAMsg/logWAMessage/getDefaultWATpl
    // live in the not-yet-built wa-shared.js, so this will throw
    // quietly in the console (caught below) until that file exists.
    const wa = STATE.settings.wa || {};
    if (newStatus === 'Pending' && ['Draft', 'Cancelled'].includes(oldStatus) && wa.auto_inv === '1') {
      setTimeout(() => {
        try {
          const c = STATE.clients.find(x => String(x.id) === String(inv.client)) || {};
          const phone = (c.wa || c.whatsapp || c.phone || '').replace(/\D/g, '');
          if (!phone) { toast(`⚠️ WA not sent — no phone number for ${inv.client_name || 'client'}`, 'warning'); return; }
          const tpl = wa.tpl_inv || getDefaultWATpl('inv');
          const msg = formatWAMsg(tpl, inv, c, STATE.settings);
          logWAMessage({ inv, client: c, type: 'invoice_created', msg, status: 'sending' });
          sendWA(phone, msg, 'invoice_created', inv, c).then(res => {
            logWAMessage({ inv, client: c, type: 'invoice_created', msg, status: res ? 'sent_api' : 'sent_web' });
            toast(`📱 Invoice sent to ${c.name || phone} via WhatsApp!`, 'success');
          }).catch(e => {
            logWAMessage({ inv, client: c, type: 'invoice_created', msg, status: 'failed', error: e.message });
            toast(`⚠️ WhatsApp not sent — ${e.message}`, 'warning');
          });
        } catch (e) { console.warn('[WA auto-send] not available yet:', e.message); }
      }, 600);
    }
  } catch (e) { toast('❌ Failed: ' + e.message, 'error'); }
}

async function promptCancelReason(inv) {
  const { value: reason, isConfirmed } = await Swal.fire({
    title: `Cancel Invoice ${inv.num || inv.invoice_number}?`,
    html: `
      <div style="text-align:left;margin-bottom:8px;font-size:13px;color:var(--text2)">
        This will mark the invoice as <b>Cancelled</b>.<br>
        <span style="font-size:12px;color:var(--muted)">Reason is saved for your records.</span>
      </div>
      <textarea id="swal-cancel-reason" placeholder="Reason for cancellation (required)…"
        style="width:100%;min-height:80px;padding:8px 10px;border:1.5px solid var(--border2);border-radius:8px;font-family:var(--font);font-size:13px;resize:vertical;margin-top:4px;box-sizing:border-box"
        oninput="document.getElementById('swal-cancel-reason').style.borderColor=this.value.trim()?'var(--border2)':'#E53935'"
      ></textarea>`,
    icon: 'warning', showCancelButton: true,
    confirmButtonText: 'Yes, Cancel It', cancelButtonText: 'Go Back', confirmButtonColor: '#E53935',
    customClass: { popup: 'swal-compact' },
    didOpen: () => document.getElementById('swal-cancel-reason').focus(),
    preConfirm: () => {
      const r = document.getElementById('swal-cancel-reason').value.trim();
      if (!r) { document.getElementById('swal-cancel-reason').style.borderColor = '#E53935'; Swal.showValidationMessage('Please enter a reason for cancellation'); return false; }
      return r;
    },
  });
  return isConfirmed ? reason : null;
}

async function confirmCancelInvoice(id) {
  const inv = STATE.invoices.find(i => String(i.id) === String(id));
  if (!inv) return;
  const reason = await promptCancelReason(inv);
  if (reason === null) return;
  changeInvoiceStatus(id, 'Cancelled', reason);
}

function openDeleteModal(id) {
  STATE.activeMenuInvoiceId = id;
  const inv = STATE.invoices.find(i => String(i.id) === String(id));
  document.getElementById('del-inv-num').textContent = inv ? inv.num : '';
  openModal('modal-delete');
}

function confirmDelete() {
  const mid = String(STATE.activeMenuInvoiceId);
  const inv = STATE.invoices.find(i => String(i.id) === mid);
  if (!inv) { closeModal('modal-delete'); return; }
  closeModal('modal-delete');
  api('api/invoices.php?id=' + (parseInt(mid) || 0), 'DELETE')
    .then(() => {
      STATE.invoices = STATE.invoices.filter(i => String(i.id) !== mid);
      STATE.filteredInvoices = STATE.filteredInvoices.filter(i => String(i.id) !== mid);
      const linkedPayments = STATE.payments.filter(p => p.invoice_id && String(p.invoice_id) === mid);
      linkedPayments.filter(p => p.id).forEach(p => api('api/payments.php?id=' + parseInt(p.id), 'DELETE').catch(() => {}));
      STATE.payments.forEach(p => { if (p.invoice_id && String(p.invoice_id) === mid) p._invoiceDeleted = true; });
      const badge = document.getElementById('badge-invoices');
      if (badge) badge.textContent = STATE.invoices.length;
      const delNum = inv.num || inv.invoice_number || '';
      logActivity(inv.status === 'Estimate' ? 'estimate_deleted' : 'invoice_deleted', `${inv.status === 'Estimate' ? 'Estimate' : 'Invoice'} deleted: ${delNum}`, inv.client_name || '', mid);
      toast('🗑️ Invoice ' + delNum + ' deleted', 'info');
      renderInvoicesTable();
    })
    .catch(e => toast('❌ Delete failed: ' + e.message, 'error'));
}

// ── Bulk action bar ───────────────────────────────────────────
function updateBulkBar() {
  const checked = document.querySelectorAll('.inv-check:checked');
  const bar = document.getElementById('bulkBar');
  const cnt = document.getElementById('bulkCount');
  if (!bar) return;
  if (checked.length > 0) { bar.style.display = 'flex'; cnt.textContent = checked.length + ' selected'; }
  else { bar.style.display = 'none'; }
  const all = document.querySelectorAll('.inv-check');
  const selAll = document.getElementById('selectAll');
  if (selAll) selAll.checked = all.length > 0 && checked.length === all.length;
}

function clearBulkSelection() {
  document.querySelectorAll('.inv-check').forEach(c => c.checked = false);
  const selAll = document.getElementById('selectAll');
  if (selAll) selAll.checked = false;
  updateBulkBar();
}

function getCheckedInvoices() {
  return [...document.querySelectorAll('.inv-check:checked')].map(c => STATE.invoices.find(i => String(i.id) === String(c.value))).filter(Boolean);
}

async function bulkSendWA() {
  // NOTE: sendWAForInvoice() is not built yet — see file header.
  const invs = getCheckedInvoices().filter(i => i.status !== 'Draft' && i.status !== 'Cancelled');
  if (!invs.length) { toast('⚠️ No eligible invoices selected (Draft/Cancelled excluded)', 'warning'); return; }
  const result = await Swal.fire({
    title: `Send WhatsApp to ${invs.length} client${invs.length > 1 ? 's' : ''}?`,
    html: `Messages will be sent for <b>${invs.length}</b> invoice${invs.length > 1 ? 's' : ''} based on each invoice's status template.<br><br><span style="font-size:12px;color:var(--muted)">Draft & Cancelled invoices are excluded.</span>`,
    icon: 'question', showCancelButton: true, confirmButtonText: 'Send All', cancelButtonText: 'Cancel',
    confirmButtonColor: '#25D366', customClass: { popup: 'swal-compact' },
  });
  if (!result.isConfirmed) return;
  let sent = 0;
  for (const inv of invs) {
    try { await sendWAForInvoice(inv); sent++; } catch (e) { /* individual errors already toasted */ }
    await new Promise(r => setTimeout(r, 600));
  }
  toast(`✅ Sent WhatsApp for ${sent} invoice${sent > 1 ? 's' : ''}`, 'success');
  clearBulkSelection();
}

function bulkExportCSV() {
  const invs = getCheckedInvoices();
  if (!invs.length) { toast('⚠️ No invoices selected', 'warning'); return; }
  const rows = [['Invoice #', 'Client', 'Service', 'Issued', 'Due', 'Amount', 'Status']];
  invs.forEach(inv => {
    const c = STATE.clients.find(x => x.id === inv.client) || { name: inv.client_name || 'One-Time' };
    rows.push([inv.num, c.name, inv.service, inv.issued, inv.due, inv.amount, inv.status]);
  });
  _downloadCSV(rows, 'invoices_selected.csv');
  clearBulkSelection();
}

async function bulkDelete() {
  const invs = getCheckedInvoices();
  if (!invs.length) { toast('⚠️ No invoices selected', 'warning'); return; }
  const result = await Swal.fire({
    title: `Delete ${invs.length} invoice${invs.length > 1 ? 's' : ''}?`,
    html: `This will permanently delete <b>${invs.length}</b> invoice${invs.length > 1 ? 's' : ''}. This cannot be undone.`,
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete All', cancelButtonText: 'Cancel',
    confirmButtonColor: '#E53935', customClass: { popup: 'swal-compact' },
  });
  if (!result.isConfirmed) return;
  for (const inv of invs) {
    try {
      await api('api/invoices.php?id=' + inv.id, 'DELETE');
      STATE.invoices = STATE.invoices.filter(i => String(i.id) !== String(inv.id));
    } catch (e) { toast('❌ Failed to delete ' + inv.num + ': ' + e.message, 'error'); }
  }
  STATE.filteredInvoices = [...STATE.invoices];
  renderInvoicesTable();
  toast(`🗑️ Deleted ${invs.length} invoice${invs.length > 1 ? 's' : ''}`, 'info');
  clearBulkSelection();
}

function sortTable(field) {
  if (STATE.sortField === field) STATE.sortDir = STATE.sortDir === 'asc' ? 'desc' : 'asc';
  else { STATE.sortField = field; STATE.sortDir = 'asc'; }
  STATE.filteredInvoices.sort((a, b) => {
    let av = a[field], bv = b[field];
    if (field === 'amount') { av = +av; bv = +bv; }
    if (field === 'client') { av = (STATE.clients.find(c => c.id === a.client) || {}).name || ''; bv = (STATE.clients.find(c => c.id === b.client) || {}).name || ''; }
    if (av < bv) return STATE.sortDir === 'asc' ? -1 : 1;
    if (av > bv) return STATE.sortDir === 'asc' ? 1 : -1;
    return 0;
  });
  applyFiltersAndRender();
}

function selectAllInv(cb) {
  document.querySelectorAll('.inv-check').forEach(c => c.checked = cb.checked);
  updateBulkBar();
}

// ── CSV export ─────────────────────────────────────────────────
function exportCSV() {
  const headers = ['Invoice#', 'Client', 'Service', 'Issue Date', 'Due Date', 'Amount', 'Status'];
  const rows = STATE.invoices.map(inv => {
    const c = STATE.clients.find(x => x.id === inv.client);
    return [inv.num, c?.name || '', inv.service, inv.issued, inv.due, inv.amount, inv.status].map(v => `"${v}"`).join(',');
  });
  downloadFile('optms_invoices.csv', [headers.join(','), ...rows].join('\n'), 'text/csv');
  toast('✅ CSV exported!', 'success');
}

function _downloadCSV(rows, filename) {
  const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a'); a.href = url; a.download = filename;
  document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
}
