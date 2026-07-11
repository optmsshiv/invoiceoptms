// ================================================================
//  assets/js/clients.js
//  Requires: common.js, shared-data.js (loaded before this file).
//
//  MPA CHANGES from the SPA version:
//  1. The "Outstanding Dues" click on a client card used to call
//     filterByClient('id') then showPage('invoices') — now a real
//     link to /pages/invoices/invoices.php?client=ID. NOTE: invoices.js
//     currently only reads ?filter=draft, not ?client= — add that
//     read when convenient (small addition to invoices.js).
//  2. createInvoiceForClient() used to showPage('create') then
//     fill the form via JS. create.php isn't built yet, so this
//     now links to /pages/invoices/create.php?client=ID — create.php will
//     need to read that param once it exists.
//  3. updateClientDropdown() and populateWAClientDropdown() are
//     called after add/edit/delete/toggle — those populate
//     dropdowns on create.php and whatsapp.php, neither built yet.
//     Guarded with typeof checks so they no-op safely here instead
//     of throwing.
//
//  NOT YET BUILT — deferred, consistent with dashboard/invoices:
//   - sendWAMessage(...)     — the "Msg" button (WhatsApp subsystem)
//   - sendAccountStatement() — the "Statement" button (also WA)
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['invoices', 'clients', 'payments', 'settings']);
  renderClients();
});

// TAG_PALETTE and _tagColor moved to shared-data.js — team.js needs
// them too, and duplicating a const declaration across two files
// loaded on the same page would throw.

let _ncCurrentTags = [];
function _renderTagPills() {
  const wrap = document.getElementById('nc-tags-pills');
  if (!wrap) return;
  wrap.innerHTML = (_ncCurrentTags || []).map(t => {
    const col = _tagColor(t);
    return `<span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:700;padding:3px 9px;border-radius:10px;background:${col.bg};color:${col.text};border:1px solid ${col.border}">${t}<span onclick="removeTag('${t}')" style="cursor:pointer;opacity:.6;font-size:14px;line-height:1;margin-left:2px">&times;</span></span>`;
  }).join('');
}
function removeTag(tag) {
  _ncCurrentTags = (_ncCurrentTags || []).filter(t => t !== tag);
  _renderTagPills();
}
function handleTagInput(e) {
  const input = e.target;
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault();
    const val = input.value.trim().replace(/,$/, '');
    if (val && !(_ncCurrentTags || []).includes(val)) { _ncCurrentTags = [...(_ncCurrentTags || []), val]; _renderTagPills(); }
    input.value = '';
    document.getElementById('nc-tag-suggestions').style.display = 'none';
  } else if (e.key === 'Backspace' && !input.value && (_ncCurrentTags || []).length) {
    _ncCurrentTags = _ncCurrentTags.slice(0, -1);
    _renderTagPills();
  }
}
function showTagSuggestions(val) {
  const box = document.getElementById('nc-tag-suggestions');
  if (!box) return;
  if (!val.trim()) { box.style.display = 'none'; return; }
  const allTags = [...new Set((STATE.clients || []).flatMap(cl => { try { return JSON.parse(cl.tags || '[]'); } catch (e) { return []; } }))];
  const matches = allTags.filter(t => t.toLowerCase().includes(val.toLowerCase()) && !(_ncCurrentTags || []).includes(t));
  if (!matches.length) { box.style.display = 'none'; return; }
  const inputEl = document.getElementById('nc-tag-input');
  if (inputEl) {
    const rect = inputEl.getBoundingClientRect();
    box.style.top = (rect.bottom + window.scrollY + 2) + 'px';
    box.style.left = (rect.left + window.scrollX) + 'px';
    box.style.position = 'fixed';
  }
  box.innerHTML = matches.map(t => `<div onclick="selectTagSuggestion('${t}')" style="padding:8px 14px;cursor:pointer;font-size:13px;font-weight:600" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">${t}</div>`).join('');
  box.style.display = 'block';
}
function selectTagSuggestion(tag) {
  if (!(_ncCurrentTags || []).includes(tag)) { _ncCurrentTags = [...(_ncCurrentTags || []), tag]; _renderTagPills(); }
  const input = document.getElementById('nc-tag-input');
  if (input) input.value = '';
  document.getElementById('nc-tag-suggestions').style.display = 'none';
}
function _refreshTagFilterDropdown() {
  const sel = document.getElementById('client-tag-filter');
  if (!sel) return;
  const cur = sel.value;
  const allTags = [...new Set((STATE.clients || []).flatMap(cl => { try { return JSON.parse(cl.tags || '[]'); } catch (e) { return []; } }))].sort();
  sel.innerHTML = '<option value="">All Tags</option>' + allTags.map(t => `<option value="${t}" ${t === cur ? 'selected' : ''}>${t}</option>`).join('');
}

// ── Extra contacts ─────────────────────────────────────────────
function addExtraContactRow(data) {
  const wrap = document.getElementById('nc-extra-contacts');
  if (!wrap) return;
  const id = 'ec-' + Date.now() + Math.random().toString(36).slice(2, 6);
  const row = document.createElement('div');
  row.id = id;
  row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:6px;align-items:center;padding:8px 10px;background:var(--bg);border:1px solid var(--border);border-radius:8px';
  row.innerHTML = `
    <input placeholder="Name *" value="${(data && data.name) || ''}" style="padding:6px 9px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:var(--font)" class="ec-name">
    <input placeholder="Role (e.g. Accounts)" value="${(data && data.role) || ''}" style="padding:6px 9px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:var(--font)" class="ec-role">
    <input placeholder="WhatsApp" value="${(data && data.wa) || ''}" style="padding:6px 9px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:var(--font)" class="ec-wa">
    <button type="button" onclick="document.getElementById('${id}').remove()" style="width:28px;height:28px;border:none;background:var(--red-bg,#FFEBEE);color:#C62828;border-radius:6px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center">&times;</button>`;
  wrap.appendChild(row);
}
function _getExtraContacts() {
  const rows = document.querySelectorAll('#nc-extra-contacts > div');
  const result = [];
  rows.forEach(row => {
    const name = row.querySelector('.ec-name')?.value?.trim();
    const role = row.querySelector('.ec-role')?.value?.trim();
    const wa = row.querySelector('.ec-wa')?.value?.trim();
    if (name) result.push({ name, role: role || '', wa: wa || '' });
  });
  return result;
}

// ── Payment behaviour score ────────────────────────────────────
function _clientPayBehaviour(clientId) {
  const cid = String(clientId);
  const paidInvs = (STATE.invoices || []).filter(i => String(i.client) === cid && i.status === 'Paid');
  if (!paidInvs.length) return { totalPaid: 0 };

  const delays = [];
  paidInvs.forEach(inv => {
    const invPmts = (STATE.payments || []).filter(p => String(p.invoice_id) === String(inv.id));
    if (!invPmts.length) return;
    const lastPmt = invPmts.map(p => new Date((p.paid_on || p.created_at || '').replace(' ', 'T') + 'Z')).sort((a, b) => b - a)[0];
    const issued = new Date((inv.issued || inv.issued_date || inv.created_at || '').replace(' ', 'T') + 'Z');
    if (lastPmt && issued && !isNaN(lastPmt) && !isNaN(issued)) delays.push(Math.max(0, Math.floor((lastPmt - issued) / 864e5)));
  });

  const avgDays = delays.length ? Math.round(delays.reduce((s, d) => s + d, 0) / delays.length) : 0;
  const allInvs = (STATE.invoices || []).filter(i => String(i.client) === cid);
  const everLate = allInvs.filter(i => {
    const due = new Date((i.due || i.due_date || '').replace(' ', 'T') + 'Z');
    const pmts = (STATE.payments || []).filter(p => String(p.invoice_id) === String(i.id));
    if (!pmts.length) return false;
    const lastPmt = pmts.map(p => new Date((p.paid_on || p.created_at || '').replace(' ', 'T') + 'Z')).sort((a, b) => b - a)[0];
    return lastPmt && due && lastPmt > due;
  }).length;

  let score, label, color, bg, border, icon;
  if (avgDays <= 7 && everLate === 0) { score = 'A'; label = 'Excellent payer'; color = '#166534'; bg = '#DCFCE7'; border = '#BBF7D0'; icon = 'star'; }
  else if (avgDays <= 15 && everLate <= 1) { score = 'B'; label = 'Good payer'; color = '#0369A1'; bg = '#E0F2FE'; border = '#BAE6FD'; icon = 'thumbs-up'; }
  else if (avgDays <= 30 && everLate <= 3) { score = 'C'; label = 'Slow payer'; color = '#92400E'; bg = '#FEF3C7'; border = '#FDE68A'; icon = 'clock'; }
  else { score = 'D'; label = 'Poor payer'; color = '#9F1239'; bg = '#FFE4E6'; border = '#FECDD3'; icon = 'exclamation-triangle'; }

  return { score, label, color, bg, border, icon, avgDays, totalPaid: paidInvs.length, everLate };
}

// ── Main grid render ───────────────────────────────────────────
function renderClients() {
  const grid = document.getElementById('clientsGrid');
  if (!grid) return;
  const showInactive = document.getElementById('show-inactive-toggle')?.checked || false;
  const searchVal = (document.getElementById('client-search')?.value || '').toLowerCase().trim();
  const tagFilter = document.getElementById('client-tag-filter')?.value || '';
  const inactiveCount = STATE.clients.filter(c => parseInt(c.active) === 0 || c.status === 'inactive').length;
  const badge = document.getElementById('inactive-count-badge');
  if (badge) { badge.textContent = inactiveCount; badge.style.display = inactiveCount ? 'inline-block' : 'none'; }
  _refreshTagFilterDropdown();
  let visibleClients = showInactive ? STATE.clients : STATE.clients.filter(c => parseInt(c.active) !== 0 && c.status !== 'inactive');
  if (searchVal) visibleClients = visibleClients.filter(c => (c.name || '').toLowerCase().includes(searchVal) || (c.email || '').toLowerCase().includes(searchVal) || (c.wa || '').includes(searchVal) || (c.person || '').toLowerCase().includes(searchVal));
  if (tagFilter) visibleClients = visibleClients.filter(c => { let tags = []; try { tags = JSON.parse(c.tags || '[]'); } catch (e) {} return tags.includes(tagFilter); });
  if (!visibleClients.length) {
    grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted)">${
      inactiveCount && !showInactive ? `All clients are inactive. <span onclick="document.getElementById('show-inactive-toggle').checked=true;renderClients()" style="color:var(--teal);cursor:pointer;text-decoration:underline">Show inactive</span>` : 'No clients yet'
    }</div>`;
    return;
  }
  grid.innerHTML = visibleClients.map(c => {
    const initials = getInitials(c.name);
    const rev = STATE.invoices.filter(i => i.client === c.id && i.status === 'Paid').reduce((s, i) => s + i.amount, 0);
    const cnt = STATE.invoices.filter(i => i.client === c.id).length;
    const _clientInvNums = STATE.invoices.filter(i => i.client === c.id).map(i => i.num || i.invoice_number || '');
    const _remSentCount = (STATE.reminders || []).filter(e => _clientInvNums.includes(e.invNum) && e.status === 'sent').length;
    const isInactive = parseInt(c.active) === 0 || c.status === 'inactive';

    const overdueInvs = STATE.invoices.filter(i => i.client === c.id && i.status === 'Overdue');
    const pendingInvs = STATE.invoices.filter(i => i.client === c.id && (i.status === 'Pending' || i.status === 'Partial'));
    const outstandingAmt = [...overdueInvs, ...pendingInvs].reduce((s, i) => s + parseFloat(i.amount || 0), 0);
    const hasOverdue = overdueInvs.length > 0;
    const hasPending = pendingInvs.length > 0;

    const cardStyle = isInactive ? `background:#FFF8E1;border:2px solid #F9A825;box-shadow:0 0 0 1px #F9A82555;opacity:.85;` : '';
    const inactiveBadge = isInactive ? `<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:#F9A825;color:#fff;margin-left:6px;vertical-align:middle">INACTIVE</span>` : '';

    let _cTags = []; try { _cTags = JSON.parse(c.tags || '[]'); } catch (e) {}
    const _tagsHTML = _cTags.length
      ? '<div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:6px">' + _cTags.map(t => { const col = _tagColor(t); return '<span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;background:' + col.bg + ';color:' + col.text + ';border:1px solid ' + col.border + '">' + t + '</span>'; }).join('') + '</div>'
      : '';
    let _eCons = []; try { _eCons = JSON.parse(c.extra_contacts || '[]'); } catch (e) {}
    const _extraHTML = _eCons.map(ct => '<div style="font-size:11px;color:var(--muted);display:flex;gap:5px;align-items:center;margin-top:2px"><i class="fas fa-user-tie" style="font-size:9px;color:var(--teal)"></i><b style="color:var(--text)">' + (ct.name || '') + '</b>' + (ct.role ? '<span style="opacity:.6">' + ct.role + '</span>' : '') + (ct.wa ? '<a href="https://wa.me/' + ct.wa.replace(/[^0-9]/g, '') + '" target="_blank" onclick="event.stopPropagation()" style="color:#25D366;margin-left:2px"><i class="fab fa-whatsapp"></i></a>' : '') + '</div>').join('');

    const _beh = _clientPayBehaviour(c.id);
    const _behHTML = _beh.totalPaid > 0 ? '<div style="margin-top:6px;padding:7px 11px;background:' + _beh.bg + ';border-radius:8px;border:1px solid ' + _beh.border + ';display:flex;align-items:center;justify-content:space-between"><div style="display:flex;align-items:center;gap:7px"><i class="fas fa-' + _beh.icon + '" style="color:' + _beh.color + ';font-size:11px"></i><div><div style="font-size:10px;font-weight:700;color:' + _beh.color + ';text-transform:uppercase;letter-spacing:.4px">' + _beh.label + '</div><div style="font-size:10px;color:var(--muted)">Avg ' + _beh.avgDays + 'd to pay · ' + _beh.totalPaid + ' paid</div></div></div><div style="font-size:17px;font-weight:800;color:' + _beh.color + '">' + _beh.score + '</div></div>' : '';

    return `<div class="client-card" style="--c:${c.color};${cardStyle}">
      <div style="position:absolute;top:0;left:0;right:0;height:4px;background:${isInactive ? '#F9A825' : c.color}"></div>
      ${isInactive ? `<div style="position:absolute;top:8px;right:8px;background:#FFF3CD;border:1.5px solid #F9A825;border-radius:8px;padding:3px 8px;font-size:10px;font-weight:700;color:#856404;z-index:2"><i class="fas fa-pause-circle"></i> Inactive</div>` : ''}
      <div class="cc-head">
        <div class="cc-big-avatar ${isValidImg(c.image) ? 'has-logo' : ''}" style="background:${isInactive ? '#9E9E9E' : c.color};${isInactive ? 'opacity:.7' : ''}">
          ${isValidImg(c.image) ? `<img src="${c.image}" alt="${c.name}" onerror="this.style.display='none'">` : initials}
        </div>
        <div style="flex:1;min-width:0">
          <div class="cc-org">${c.name}${inactiveBadge}</div>
          <div class="cc-contact">${c.person || ''}</div>
          <div class="cc-contact">${c.email || ''}</div>
          ${c.landmark ? `<div class="cc-contact" style="font-size:11px;color:var(--muted)"><i class="fas fa-map-marker-alt" style="color:var(--teal);margin-right:3px;font-size:10px"></i>${c.landmark}</div>` : ''}
          ${_tagsHTML}
          ${_extraHTML}
        </div>
      </div>
      <div class="cc-stats" style="${isInactive ? 'opacity:.6' : ''}">
        <div class="cc-stat"><div class="cc-stat-val" style="color:${isInactive ? '#F9A825' : c.color}">${cnt}</div><div class="cc-stat-lbl">Invoices</div></div>
        <div class="cc-stat"><div class="cc-stat-val" style="color:${_remSentCount > 0 ? (isInactive ? '#F9A825' : '#6D28D9') : 'var(--muted)'}${_remSentCount > 0 ? ';font-size:14px' : ''}">${_remSentCount}</div><div class="cc-stat-lbl">Reminders</div></div>
        <div class="cc-stat"><div class="cc-stat-val" style="color:${isInactive ? '#F9A825' : c.color};font-size:12px">${c.wa || '—'}</div><div class="cc-stat-lbl">WhatsApp</div></div>
      </div>
      ${_behHTML}
      <div style="margin-top:6px;display:flex;align-items:center;justify-content:space-between;padding:7px 12px;background:#E3F2FD;border-radius:8px;opacity:${isInactive ? '.6' : '1'}">
        <div style="font-size:10px;font-weight:700;color:#1565C0;text-transform:uppercase;letter-spacing:.5px">Revenue</div>
        <div style="font-size:14px;font-weight:800;color:#1565C0;font-family:var(--mono)">${fmt_money(rev)}</div>
      </div>
      ${outstandingAmt > 0 ? `
      <a href="/pages/invoices/invoices.php?client=${c.id}" style="text-decoration:none;margin-top:8px;display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:${hasOverdue ? '#FFEBEE' : '#FFF8E1'};border-radius:8px;cursor:pointer;border:1px solid ${hasOverdue ? '#FFCDD2' : '#FFE082'}">
        <div style="display:flex;align-items:center;gap:7px">
          <i class="fas fa-exclamation-circle" style="font-size:12px;color:${hasOverdue ? '#C62828' : '#E65100'}"></i>
          <div>
            <div style="font-size:11px;font-weight:700;color:${hasOverdue ? '#B71C1C' : '#BF360C'}">Outstanding Dues</div>
            <div style="font-size:10px;color:${hasOverdue ? '#C62828' : '#E65100'};margin-top:1px">${hasOverdue ? overdueInvs.length + ' overdue' : ''}${hasOverdue && pendingInvs.length ? ', ' : ''}${pendingInvs.length ? pendingInvs.length + ' pending' : ''}</div>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:6px">
          <div style="font-size:14px;font-weight:800;font-family:var(--mono);color:${hasOverdue ? '#C62828' : '#E65100'}">${fmt_money(outstandingAmt)}</div>
          <i class="fas fa-chevron-right" style="font-size:10px;color:${hasOverdue ? '#C62828' : '#E65100'};opacity:.6"></i>
        </div>
      </a>` : `
      <div style="margin-top:8px;display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--bg);border-radius:8px;border:1px solid var(--border)">
        <div style="display:flex;align-items:center;gap:7px">
          <i class="fas fa-check-circle" style="font-size:12px;color:var(--muted)"></i>
          <div style="font-size:11px;font-weight:600;color:var(--muted)">No Dues</div>
        </div>
        <div style="font-size:13px;font-weight:700;color:var(--border2);font-family:var(--mono)">—</div>
      </div>`}
      <div class="cc-footer" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
        ${!isInactive ? `<button class="btn btn-outline" style="flex:1;font-size:12px" onclick="createInvoiceForClient('${c.id}')"><i class="fas fa-plus"></i> Invoice</button>` : ''}
        ${!isInactive ? `<button class="btn btn-whatsapp" style="flex:1;font-size:12px" onclick="sendWAMessage('${c.wa}','${c.name}','','','')"><i class="fab fa-whatsapp"></i> Msg</button>` : ''}
        ${outstandingAmt > 0 ? `<button class="btn btn-outline" style="flex:1;font-size:12px;color:var(--amber);border-color:var(--amber)" onclick="sendAccountStatement('${c.id}')" title="Send Account Statement"><i class="fas fa-file-alt"></i> Statement</button>` : ''}
        <button class="btn btn-outline" style="padding:9px 12px;font-size:12px" onclick="editClient('${c.id}')" title="Edit"><i class="fas fa-edit"></i></button>
        ${isInactive
          ? `<button class="btn" style="flex:1;font-size:12px;background:#E8F5E9;color:#2E7D32;border:1.5px solid #A5D6A7" onclick="toggleClientActive('${c.id}',true)" title="Re-activate client"><i class="fas fa-check-circle"></i> Activate</button>`
          : `<button class="btn btn-outline" style="padding:9px 12px;font-size:12px;color:var(--amber);border-color:var(--amber)" onclick="toggleClientActive('${c.id}',false)" title="Set Inactive"><i class="fas fa-pause-circle"></i></button>`
        }
        <button class="btn btn-danger" style="padding:9px 12px;font-size:12px" onclick="deleteClient('${c.id}')" title="Delete client"><i class="fas fa-trash"></i></button>
      </div>
    </div>`;
  }).join('');
}

function filterClients(val) { renderClients(); }

// NOTE: sendWAMessage() (the "Msg" button) and sendAccountStatement()
// (the "Statement" button) are part of the WhatsApp subsystem —
// formatWAMsg/sendWA/logWAMessage live in a not-yet-built
// wa-shared.js, same as invoices.js's deferred Send WA. Not defined
// here on purpose; clicking those buttons is a no-op for now.

function createInvoiceForClient(id) {
  window.location.href = '/pages/invoices/create.php?client=' + encodeURIComponent(id);
}

// ── Add / Edit client modal ────────────────────────────────────
function openAddClientModal() {
  STATE._editCid = null;
  ['nc-name', 'nc-person', 'nc-wa', 'nc-email', 'nc-gst', 'nc-addr', 'nc-landmark'].forEach(id => { const e = document.getElementById(id); if (e) e.value = ''; });
  const col = document.getElementById('nc-color'); if (col) col.value = '#00897B';
  clearClientLogo();
  updateClientLogoInitials();
  _ncCurrentTags = []; _renderTagPills();
  const _ecwReset = document.getElementById('nc-extra-contacts'); if (_ecwReset) _ecwReset.innerHTML = '';
  const hdr = document.querySelector('#modal-addclient .modal-header span'); if (hdr) hdr.textContent = 'Add New Client';
  const btn = document.querySelector('#modal-addclient .modal-footer .btn-primary'); if (btn) btn.textContent = 'Add Client';
  openModal('modal-addclient');
}

let _ncLogoBase64 = '';

function handleClientLogoUpload(input) {
  const file = input.files[0]; if (!file) return;
  if (file.size > 5 * 1024 * 1024) { toast('⚠️ Image must be under 5MB', 'warning'); return; }

  const btn = document.getElementById('nc-logo-upload-btn');
  const icon = document.getElementById('nc-logo-upload-icon');
  const text = document.getElementById('nc-logo-upload-text');
  const bar = document.getElementById('nc-logo-progress-bar');
  if (btn) btn.style.background = '#00695C';
  if (icon) icon.className = 'fas fa-spinner fa-spin';
  if (text) text.textContent = 'Processing…';
  if (bar) { bar.style.width = '0%'; bar.style.transition = 'none'; }

  let pct = 0;
  const tick = setInterval(() => { pct = pct < 85 ? pct + (85 - pct) * 0.08 : pct; if (bar) bar.style.width = pct + '%'; }, 50);

  const reader = new FileReader();
  reader.onload = e => {
    const img = new Image();
    img.onload = () => {
      const MAX = 200;
      let w = img.width, h = img.height;
      if (w > h) { if (w > MAX) { h = Math.round(h * MAX / w); w = MAX; } }
      else { if (h > MAX) { w = Math.round(w * MAX / h); h = MAX; } }
      const canvas = document.createElement('canvas');
      canvas.width = w; canvas.height = h;
      canvas.getContext('2d').drawImage(img, 0, 0, w, h);
      let quality = 0.85, dataUrl;
      do { dataUrl = canvas.toDataURL('image/jpeg', quality); quality -= 0.1; } while (dataUrl.length > 50 * 1024 * 1.37 && quality > 0.1);

      clearInterval(tick);
      if (bar) { bar.style.transition = 'width .2s ease'; bar.style.width = '100%'; }

      setTimeout(() => {
        _ncLogoBase64 = dataUrl;
        _applyClientLogoPreview(_ncLogoBase64);
        if (btn) btn.style.background = 'var(--teal)';
        if (icon) icon.className = 'fas fa-check';
        if (text) text.textContent = 'Uploaded!';
        if (bar) { bar.style.transition = 'width .4s ease'; bar.style.width = '0%'; }
        setTimeout(() => { if (icon) icon.className = 'fas fa-upload'; if (text) text.textContent = 'Upload'; }, 2000);
        toast('✅ Logo ready (' + Math.round(dataUrl.length / 1024) + ' KB)', 'success');
      }, 250);
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

function previewClientLogoUrl(url) {
  if (!url) { _ncLogoBase64 = ''; _applyClientLogoPreview(''); return; }
  _ncLogoBase64 = url;
  _applyClientLogoPreview(url);
}

function _applyClientLogoPreview(src) {
  const img = document.getElementById('nc-logo-img');
  const initials = document.getElementById('nc-logo-initials');
  const preview = document.getElementById('nc-logo-preview');
  if (src) {
    img.src = src; img.style.display = 'block';
    if (initials) initials.style.display = 'none';
    if (preview) { preview.style.border = '3px solid #00897B'; preview.style.boxShadow = '0 0 0 3px rgba(0,137,123,.25), 0 2px 8px rgba(0,137,123,.35)'; }
  } else {
    img.src = ''; img.style.display = 'none';
    if (initials) initials.style.display = '';
    if (preview) { preview.style.border = '3px solid var(--border)'; preview.style.boxShadow = 'none'; }
  }
}

function updateClientLogoInitials() {
  const name = document.getElementById('nc-name')?.value || '';
  const color = document.getElementById('nc-color')?.value || '#00897B';
  const preview = document.getElementById('nc-logo-preview');
  const initEl = document.getElementById('nc-logo-initials');
  if (preview) preview.style.background = color;
  if (initEl) initEl.textContent = getInitials(name) || '?';
}

function clearClientLogo() {
  _ncLogoBase64 = '';
  _applyClientLogoPreview('');
  const fi = document.getElementById('nc-logo-file'); if (fi) fi.value = '';
  const ui = document.getElementById('nc-logo-url'); if (ui) ui.value = '';
}

async function saveNewClient() {
  const name = (document.getElementById('nc-name')?.value || '').trim();
  if (!name) { toast('⚠️ Enter name', 'warning'); return; }
  const payload = {
    name,
    person: document.getElementById('nc-person')?.value || '',
    email: document.getElementById('nc-email')?.value || '',
    wa: document.getElementById('nc-wa')?.value || '',
    gst: document.getElementById('nc-gst')?.value || '',
    color: document.getElementById('nc-color')?.value || '#00897B',
    addr: document.getElementById('nc-addr')?.value || '',
    landmark: document.getElementById('nc-landmark')?.value || '',
    logo: _ncLogoBase64 || '',
    tags: JSON.stringify(_ncCurrentTags || []),
    extra_contacts: JSON.stringify(_getExtraContacts()),
  };
  const saveBtn = document.querySelector('#modal-addclient .modal-footer .btn-primary');
  const cancelBtn = document.querySelector('#modal-addclient .modal-footer .btn-outline');
  const isEdit = !!STATE._editCid;
  const origLabel = saveBtn ? saveBtn.textContent : '';
  if (saveBtn) { saveBtn.disabled = true; saveBtn.style.opacity = '0.7'; saveBtn.style.cursor = 'not-allowed'; saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…'; }
  if (cancelBtn) cancelBtn.disabled = true;
  try {
    let savedClient = null;
    const logoNormalized = (payload.logo && (payload.logo.indexOf('data:image') === 0 || payload.logo.indexOf('http') === 0)) ? payload.logo : '';
    if (STATE._editCid) {
      const c = STATE.clients.find(x => x.id === STATE._editCid);
      const cId = parseInt(c?.id) || 0;
      await api('api/clients.php?id=' + cId, 'PUT', payload);
      toast('✅ Client updated!', 'success');
      logActivity('client_edited', `Client edited: ${name}`, payload.email || '');
      savedClient = { id: String(cId), name: payload.name, person: payload.person, email: payload.email, phone: c?.phone || '', wa: payload.wa, gst: payload.gst, addr: payload.addr, landmark: payload.landmark, color: payload.color, image: logoNormalized, active: c?.active ?? 1, tags: payload.tags, extra_contacts: payload.extra_contacts };
      const idx = STATE.clients.findIndex(x => String(x.id) === String(cId));
      if (idx !== -1) STATE.clients[idx] = savedClient; else STATE.clients.push(savedClient);
      STATE._editCid = null;
      const hdr = document.querySelector('#modal-addclient .modal-header span'); if (hdr) hdr.textContent = 'Add New Client';
    } else {
      const resp = await api('api/clients.php', 'POST', payload);
      toast('✅ "' + name + '" added!', 'success');
      logActivity('client_added', `Client added: ${name}`, payload.email || '');
      savedClient = { id: String(resp?.id || ('tmp-' + Date.now())), name: payload.name, person: payload.person, email: payload.email, phone: '', wa: payload.wa, gst: payload.gst, addr: payload.addr, landmark: payload.landmark, color: payload.color, image: logoNormalized, active: 1, tags: payload.tags, extra_contacts: payload.extra_contacts };
      STATE.clients.push(savedClient);
    }
    // updateClientDropdown/populateWAClientDropdown belong to create.php
    // and whatsapp.php — neither built yet, so guard rather than throw.
    if (typeof updateClientDropdown === 'function') updateClientDropdown();
    if (typeof populateWAClientDropdown === 'function') populateWAClientDropdown();
    renderClients();
    closeModal('modal-addclient');
    ['nc-name', 'nc-person', 'nc-wa', 'nc-email', 'nc-gst', 'nc-addr', 'nc-landmark'].forEach(id => { const e = document.getElementById(id); if (e) e.value = ''; });
    clearClientLogo();
    const col = document.getElementById('nc-color'); if (col) col.value = '#00897B';
    updateClientLogoInitials();
    _ncCurrentTags = []; _renderTagPills();
    const _ecwReset = document.getElementById('nc-extra-contacts'); if (_ecwReset) _ecwReset.innerHTML = '';
  } catch (e) {
    toast('❌ ' + e.message, 'error');
  } finally {
    if (saveBtn) { saveBtn.disabled = false; saveBtn.style.opacity = ''; saveBtn.style.cursor = ''; saveBtn.textContent = origLabel || (isEdit ? 'Update Client' : 'Add Client'); }
    if (cancelBtn) cancelBtn.disabled = false;
  }
}

function editClient(id) {
  const c = STATE.clients.find(x => x.id === id); if (!c) return;
  STATE._editCid = id;
  ['nc-name', 'nc-person', 'nc-wa', 'nc-email', 'nc-gst', 'nc-addr', 'nc-landmark'].forEach(fid => {
    const f = document.getElementById(fid); if (f) f.value = c[{ 'nc-name': 'name', 'nc-person': 'person', 'nc-wa': 'wa', 'nc-email': 'email', 'nc-gst': 'gst', 'nc-addr': 'addr', 'nc-landmark': 'landmark' }[fid]] || '';
  });
  const col = document.getElementById('nc-color'); if (col) col.value = c.color || '#00897B';
  clearClientLogo();
  if (c.image || c.logo) {
    _ncLogoBase64 = c.image || c.logo;
    _applyClientLogoPreview(_ncLogoBase64);
    const ui = document.getElementById('nc-logo-url');
    if (ui && _ncLogoBase64.startsWith('http')) ui.value = _ncLogoBase64;
  }
  updateClientLogoInitials();
  _ncCurrentTags = [];
  try { _ncCurrentTags = JSON.parse(c.tags || '[]'); } catch (e) { _ncCurrentTags = []; }
  if (!Array.isArray(_ncCurrentTags)) _ncCurrentTags = [];
  _renderTagPills();
  const _ecWrap = document.getElementById('nc-extra-contacts');
  if (_ecWrap) {
    _ecWrap.innerHTML = '';
    let _ecList = [];
    try { _ecList = JSON.parse(c.extra_contacts || '[]'); } catch (e) {}
    if (Array.isArray(_ecList)) _ecList.forEach(ct => addExtraContactRow(ct));
  }
  const hdr = document.querySelector('#modal-addclient .modal-header span'); if (hdr) hdr.textContent = 'Edit Client';
  const btn = document.querySelector('#modal-addclient .modal-footer .btn-primary'); if (btn) btn.textContent = 'Update Client';
  openModal('modal-addclient');
}

async function deleteClient(id) {
  const c = STATE.clients.find(x => String(x.id) === String(id));
  if (!c) return;
  const hasInvoices = STATE.invoices.some(i => String(i.client) === String(id));
  const _delClientHtml = hasInvoices
    ? `<b>"${c.name}"</b> has existing invoices. Deleting the client will <b>not</b> delete their invoices.<br><br>Are you sure?`
    : `Are you sure you want to delete <b>"${c.name}"</b>? This cannot be undone.`;
  const _delClientResult = await Swal.fire({ title: 'Delete Client?', html: _delClientHtml, icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, Delete', cancelButtonText: 'Cancel', confirmButtonColor: '#E53935', customClass: { popup: 'swal-compact' } });
  if (!_delClientResult.isConfirmed) return;
  try {
    const dbId = parseInt(c._dbId || c.id) || 0;
    await api('api/clients.php?id=' + dbId, 'DELETE');
    logActivity('client_deleted', `Client deleted: ${c.name}`, c.email || '');
    toast('🗑 Client "' + c.name + '" deleted', 'info');
    const r = await api('api/clients.php');
    STATE.clients = Array.isArray(r.data) ? r.data : STATE.clients.filter(x => String(x.id) !== String(id));
    if (typeof updateClientDropdown === 'function') updateClientDropdown();
    if (typeof populateWAClientDropdown === 'function') populateWAClientDropdown();
    renderClients();
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

async function toggleClientActive(id, makeActive) {
  const c = STATE.clients.find(x => String(x.id) === String(id));
  if (!c) return;

  const result = await Swal.fire({
    title: makeActive ? 'Activate Client?' : 'Set Client Inactive?',
    html: `<div style="font-size:14px;color:#555">
             ${makeActive
               ? `<i class="fas fa-user-check" style="color:#00897B;font-size:28px;display:block;margin-bottom:10px"></i><strong>${c.name}</strong> will be marked as <span style="color:#00897B;font-weight:700">Active</span> and visible in invoices.`
               : `<i class="fas fa-user-slash" style="color:#F9A825;font-size:28px;display:block;margin-bottom:10px"></i><strong>${c.name}</strong> will be marked as <span style="color:#F9A825;font-weight:700">Inactive</span> and hidden from invoice selection.`
             }
           </div>`,
    icon: makeActive ? 'question' : 'warning', showCancelButton: true,
    confirmButtonText: makeActive ? '✅ Yes, Activate' : '⏸ Yes, Set Inactive', cancelButtonText: 'Cancel',
    confirmButtonColor: makeActive ? '#00897B' : '#F9A825', cancelButtonColor: '#aaa', reverseButtons: true,
    customClass: { popup: 'swal-compact' },
  });
  if (!result.isConfirmed) return;

  try {
    const dbId = parseInt(c._dbId || c.id) || 0;
    const res = await api('api/clients.php?id=' + dbId, 'PUT', {
      name: c.name, person: c.person || '', email: c.email || '', wa: c.wa || '',
      gst: c.gst || '', color: c.color || '#00897B', addr: c.addr || '',
      landmark: c.landmark || '', active: makeActive ? 1 : 0,
      logo: c.image || c.logo || '',
    });
    if (!res || res.success === false) throw new Error(res?.error || 'API returned failure');
    const r = await api('api/clients.php');
    STATE.clients = Array.isArray(r.data) ? r.data : STATE.clients;
    renderClients();
    if (typeof updateClientDropdown === 'function') updateClientDropdown();
    if (typeof populateWAClientDropdown === 'function') populateWAClientDropdown();
    logActivity(makeActive ? 'client_activated' : 'client_deactivated', `Client ${makeActive ? 'activated' : 'deactivated'}: ${c.name}`, c.email || '');
    Swal.fire({ toast: true, position: 'top-end', timer: 2500, timerProgressBar: true, showConfirmButton: false, icon: makeActive ? 'success' : 'info', title: makeActive ? `✅ ${c.name} activated` : `⏸ ${c.name} set to inactive` });
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e.message, confirmButtonColor: '#e53935' });
  }
}
