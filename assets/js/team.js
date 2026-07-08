// ================================================================
//  assets/js/team.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  No MPA adjustments needed — self-contained in the SPA too.
// ================================================================

document.addEventListener('DOMContentLoaded', () => { renderTeam(); });

STATE.team = [];

async function renderTeam() {
  try {
    const r = await api('api/team.php?action=list');
    STATE.team = Array.isArray(r.data) ? r.data : [];
  } catch (e) {
    toast('❌ ' + e.message, 'error');
    STATE.team = [];
  }
  _renderTeamRows(STATE.team);
}

function filterTeam(q) {
  q = (q || '').toLowerCase();
  const filtered = !q ? STATE.team : STATE.team.filter(u =>
    (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q)
  );
  _renderTeamRows(filtered);
}

const TEAM_AVATAR_PALETTE = ['#00897B', '#1976D2', '#7B1FA2', '#E64A19', '#546E7A', '#00695C', '#5E35B1', '#C2185B'];
function _teamAvatarColor(str) {
  let h = 0;
  for (let i = 0; i < (str || '').length; i++) h = (h * 31 + str.charCodeAt(i)) >>> 0;
  return TEAM_AVATAR_PALETTE[h % TEAM_AVATAR_PALETTE.length];
}
function _teamAvatarCell(u) {
  if (u.avatar) return `<img src="${escHtml(u.avatar)}" class="team-avatar-img" alt="${escHtml(u.name)}">`;
  const initial = (u.name || '?').trim().charAt(0);
  return `<div class="team-avatar-fallback" style="background:${_teamAvatarColor(u.name || u.email || '')}">${escHtml(initial)}</div>`;
}

function _renderTeamRows(rawList) {
  const tbody = document.getElementById('teamTbody');
  const roleLabels = { owner: 'Owner', admin: 'Admin', manager: 'Manager', accountant: 'Accountant', sales: 'Sales', viewer: 'Viewer' };
  const roleOptions = ['admin', 'manager', 'accountant', 'sales', 'viewer'];
  const list = rawList;

  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:24px">No team members yet.</td></tr>`;
  } else {
    tbody.innerHTML = list.map(u => {
      const isOwner = u.role === 'owner';
      const isInactive = u.status !== 'active';
      const roleCell = isOwner
        ? `<span class="badge">Owner</span>`
        : `<select onchange="changeTeamRole(${u.id}, this.value)" class="table-filter" style="font-size:12px;padding:4px 8px">
            ${roleOptions.map(r => `<option value="${r}" ${r === u.role ? 'selected' : ''}>${roleLabels[r]}</option>`).join('')}
          </select>`;
      const statusBadge = u.status === 'active'
        ? `<span class="badge badge-active">Active</span>`
        : `<span class="badge badge-inactive">Inactive</span>`;
      const permsBtn = isOwner ? '' : `<button class="btn btn-outline" style="font-size:11px;padding:5px 10px;color:#7B1FA2" onclick="openTeamPermissionsModal('${u.role}')" title="Permissions"><i class="fas fa-shield-halved"></i></button>`;
      const editBtn = isOwner ? '' : `<button class="btn btn-outline" style="font-size:11px;padding:5px 10px;color:#1976D2" onclick="openEditTeamModal(${u.id})" title="Edit"><i class="fas fa-pen"></i></button>`;
      const actions = isOwner ? '' : `
        <div class="action-cell">
          ${editBtn}
          ${permsBtn}
          ${u.status === 'active'
            ? `<button class="btn btn-outline" style="font-size:11px;padding:5px 10px" onclick="toggleTeamStatus(${u.id},'inactive')" title="Deactivate"><i class="fas fa-user-slash"></i></button>`
            : `<button class="btn btn-outline" style="font-size:11px;padding:5px 10px" onclick="toggleTeamStatus(${u.id},'active')" title="Reactivate"><i class="fas fa-user-check"></i></button>`}
          <button class="btn btn-outline" style="font-size:11px;padding:5px 10px;color:#E53935" onclick="removeTeamMember(${u.id})" title="Remove"><i class="fas fa-trash"></i></button>
        </div>`;
      return `<tr class="${isInactive ? 'team-row-inactive' : ''}">
        <td>${_teamAvatarCell(u)}</td>
        <td>${escHtml(u.name)}</td>
        <td>${escHtml(u.email)}</td>
        <td>${u.phone ? escHtml(u.phone) : '—'}</td>
        <td>${roleCell}</td>
        <td>${statusBadge}</td>
        <td>${u.last_login ? new Date(u.last_login).toLocaleDateString() : '—'}</td>
        <td>${actions}</td>
      </tr>`;
    }).join('');
  }
  document.getElementById('teamCountInfo').textContent = list.length + ' member' + (list.length === 1 ? '' : 's');
}

// ── Add Team Member modal ─────────────────────────────────────
let _tmAvatarBase64 = '';
let _tmCurrentTags = [];

function openAddTeamModal() {
  document.getElementById('tm-name').value = '';
  document.getElementById('tm-email').value = '';
  document.getElementById('tm-mobile').value = '';
  document.getElementById('tm-address').value = '';
  document.getElementById('tm-role').value = 'sales';
  document.getElementById('tm-password').value = '';
  document.getElementById('tm-tag-input').value = '';
  document.getElementById('tm-avatar-file').value = '';
  const _ecw = document.getElementById('tm-extra-contacts'); if (_ecw) _ecw.innerHTML = '';
  _tmAvatarBase64 = '';
  _tmCurrentTags = [];
  _applyTeamAvatarPreview('');
  _renderTeamTagPills();
  openModal('modal-add-team');
}

function handleTeamAvatarUpload(input) {
  const file = input.files[0]; if (!file) return;
  if (file.size > 5 * 1024 * 1024) { toast('⚠️ Image must be under 5MB', 'warning'); return; }
  const icon = document.getElementById('tm-avatar-upload-icon');
  const text = document.getElementById('tm-avatar-upload-text');
  const bar = document.getElementById('tm-avatar-progress-bar');
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
        _tmAvatarBase64 = dataUrl;
        _applyTeamAvatarPreview(dataUrl);
        if (icon) icon.className = 'fas fa-check';
        if (text) text.textContent = 'Uploaded!';
        if (bar) { bar.style.transition = 'width .4s ease'; bar.style.width = '0%'; }
        setTimeout(() => { if (icon) icon.className = 'fas fa-camera'; if (text) text.textContent = 'Upload Photo'; }, 2000);
        toast('✅ Photo ready (' + Math.round(dataUrl.length / 1024) + ' KB)', 'success');
      }, 250);
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

function _applyTeamAvatarPreview(src) {
  const img = document.getElementById('tm-avatar-img');
  const icon = document.getElementById('tm-avatar-icon');
  const preview = document.getElementById('tm-avatar-preview');
  if (src) {
    img.src = src; img.style.display = 'block';
    if (icon) icon.style.display = 'none';
    if (preview) { preview.style.border = '3px solid #00897B'; preview.style.boxShadow = '0 0 0 3px rgba(0,137,123,.25)'; }
  } else {
    img.src = ''; img.style.display = 'none';
    if (icon) icon.style.display = '';
    if (preview) { preview.style.border = '3px solid var(--border)'; preview.style.boxShadow = 'none'; }
  }
}

function _renderTeamTagPills() {
  const wrap = document.getElementById('tm-tags-pills');
  if (!wrap) return;
  wrap.innerHTML = (_tmCurrentTags || []).map(t => {
    const col = _tagColor(t);
    return `<span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:700;padding:3px 9px;border-radius:10px;background:${col.bg};color:${col.text};border:1px solid ${col.border}">${escHtml(t)}<span onclick="removeTeamTag('${escHtml(t)}')" style="cursor:pointer;opacity:.6;font-size:14px;line-height:1;margin-left:2px">&times;</span></span>`;
  }).join('');
}
function removeTeamTag(tag) { _tmCurrentTags = (_tmCurrentTags || []).filter(t => t !== tag); _renderTeamTagPills(); }
function handleTeamTagInput(e) {
  const input = e.target;
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault();
    const val = input.value.trim().replace(/,$/, '');
    if (val && !(_tmCurrentTags || []).includes(val)) { _tmCurrentTags = [...(_tmCurrentTags || []), val]; _renderTeamTagPills(); }
    input.value = '';
  } else if (e.key === 'Backspace' && !input.value && (_tmCurrentTags || []).length) {
    _tmCurrentTags = _tmCurrentTags.slice(0, -1); _renderTeamTagPills();
  }
}

function addTeamContactRow(data) {
  const wrap = document.getElementById('tm-extra-contacts');
  if (!wrap) return;
  const id = 'tmec-' + Date.now() + Math.random().toString(36).slice(2, 6);
  const row = document.createElement('div');
  row.id = id;
  row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:6px;align-items:center;padding:8px 10px;background:var(--bg);border:1px solid var(--border);border-radius:8px';
  row.innerHTML = `
    <input placeholder="Name *" value="${(data && data.name) || ''}" style="padding:6px 9px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:var(--font)" class="tmec-name">
    <input placeholder="Phone" value="${(data && data.phone) || ''}" style="padding:6px 9px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:var(--font)" class="tmec-phone">
    <input placeholder="Relation (e.g. Emergency)" value="${(data && data.relation) || ''}" style="padding:6px 9px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:var(--font)" class="tmec-relation">
    <button type="button" onclick="document.getElementById('${id}').remove()" style="width:28px;height:28px;border:none;background:var(--red-bg,#FFEBEE);color:#C62828;border-radius:6px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center">&times;</button>`;
  wrap.appendChild(row);
}
function _getTeamContacts() {
  const rows = document.querySelectorAll('#tm-extra-contacts > div');
  const result = [];
  rows.forEach(row => {
    const name = row.querySelector('.tmec-name')?.value?.trim();
    const phone = row.querySelector('.tmec-phone')?.value?.trim();
    const relation = row.querySelector('.tmec-relation')?.value?.trim();
    if (name || phone) result.push({ name: name || '', phone: phone || '', relation: relation || '' });
  });
  return result;
}

async function saveNewTeamMember() {
  const name = document.getElementById('tm-name').value.trim();
  const email = document.getElementById('tm-email').value.trim();
  const mobile = document.getElementById('tm-mobile').value.trim();
  const address = document.getElementById('tm-address').value.trim();
  const role = document.getElementById('tm-role').value;
  const password = document.getElementById('tm-password').value.trim();
  if (!name) { toast('⚠️ Name required', 'warning'); return; }
  if (!email) { toast('⚠️ Email required', 'warning'); return; }
  const btn = document.getElementById('tm-save-btn');
  if (btn.disabled) return;
  btn.disabled = true;
  try {
    const payload = {
      name, email, role, mobile, address,
      password: password || undefined,
      tags: _tmCurrentTags || [],
      contacts: _getTeamContacts(),
      avatar: _tmAvatarBase64 || undefined,
    };
    const r = await api('api/team.php?action=add', 'POST', payload);
    closeModal('modal-add-team');
    await renderTeam();
    await Swal.fire({
      title: 'Team member added',
      html: `<strong>${escHtml(email)}</strong> can sign in with this temporary password:<br><br>
             <code style="font-size:15px;background:var(--bg);padding:6px 12px;border-radius:6px;display:inline-block">${escHtml(r.temp_pass)}</code>
             <br><br><span style="font-size:12px;color:var(--muted)">Share this securely — it won't be shown again.</span>`,
      icon: 'success', confirmButtonText: 'Got it', customClass: { popup: 'swal-compact' },
    });
  } catch (e) { toast('❌ ' + e.message, 'error'); }
  finally { btn.disabled = false; }
}

// ── Edit Team Member modal ────────────────────────────────────
let _tmeAvatarBase64 = undefined;
let _tmeCurrentTags = [];
let _tmeEditingId = null;

async function openEditTeamModal(userId) {
  _tmeEditingId = userId;
  _tmeAvatarBase64 = undefined;
  _tmeCurrentTags = [];
  document.getElementById('tme-name').value = '';
  document.getElementById('tme-email').value = '';
  document.getElementById('tme-mobile').value = '';
  document.getElementById('tme-address').value = '';
  document.getElementById('tme-password').value = '';
  document.getElementById('tme-tag-input').value = '';
  document.getElementById('tme-avatar-file').value = '';
  document.getElementById('tme-role-display').value = '';
  const _ecw = document.getElementById('tme-extra-contacts'); if (_ecw) _ecw.innerHTML = '';
  _applyEditTeamAvatarPreview('');
  _renderEditTeamTagPills();
  openModal('modal-edit-team');
  try {
    const r = await api('api/team.php?action=get&user_id=' + userId);
    const u = r.data || {};
    const roleLabels = { owner: 'Owner', admin: 'Admin', manager: 'Manager', accountant: 'Accountant', sales: 'Sales', viewer: 'Viewer' };
    document.getElementById('tme-name').value = u.name || '';
    document.getElementById('tme-email').value = u.email || '';
    document.getElementById('tme-mobile').value = u.phone || '';
    document.getElementById('tme-address').value = u.address || '';
    document.getElementById('tme-role-display').value = roleLabels[u.role] || u.role || '';
    _tmeCurrentTags = Array.isArray(u.tags) ? u.tags : [];
    _renderEditTeamTagPills();
    if (u.avatar) _applyEditTeamAvatarPreview(u.avatar);
    (r.contacts || []).forEach(c => addEditTeamContactRow(c));
  } catch (e) {
    toast('❌ ' + e.message, 'error');
    closeModal('modal-edit-team');
  }
}

function handleEditTeamAvatarUpload(input) {
  const file = input.files[0]; if (!file) return;
  if (file.size > 5 * 1024 * 1024) { toast('⚠️ Image must be under 5MB', 'warning'); return; }
  const icon = document.getElementById('tme-avatar-upload-icon');
  const text = document.getElementById('tme-avatar-upload-text');
  const bar = document.getElementById('tme-avatar-progress-bar');
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
        _tmeAvatarBase64 = dataUrl;
        _applyEditTeamAvatarPreview(dataUrl);
        if (icon) icon.className = 'fas fa-check';
        if (text) text.textContent = 'Updated!';
        if (bar) { bar.style.transition = 'width .4s ease'; bar.style.width = '0%'; }
        setTimeout(() => { if (icon) icon.className = 'fas fa-camera'; if (text) text.textContent = 'Change Photo'; }, 2000);
        toast('✅ Photo ready (' + Math.round(dataUrl.length / 1024) + ' KB)', 'success');
      }, 250);
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

function clearEditTeamAvatar() {
  _tmeAvatarBase64 = '';
  _applyEditTeamAvatarPreview('');
  const fi = document.getElementById('tme-avatar-file'); if (fi) fi.value = '';
}

function _applyEditTeamAvatarPreview(src) {
  const img = document.getElementById('tme-avatar-img');
  const icon = document.getElementById('tme-avatar-icon');
  const preview = document.getElementById('tme-avatar-preview');
  if (src && isValidImg(src)) {
    img.src = src; img.style.display = 'block';
    if (icon) icon.style.display = 'none';
    if (preview) { preview.style.border = '3px solid #00897B'; preview.style.boxShadow = '0 0 0 3px rgba(0,137,123,.25)'; }
  } else {
    img.src = ''; img.style.display = 'none';
    if (icon) icon.style.display = '';
    if (preview) { preview.style.border = '3px solid var(--border)'; preview.style.boxShadow = 'none'; }
  }
}

function _renderEditTeamTagPills() {
  const wrap = document.getElementById('tme-tags-pills');
  if (!wrap) return;
  wrap.innerHTML = (_tmeCurrentTags || []).map(t => {
    const col = _tagColor(t);
    return `<span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:700;padding:3px 9px;border-radius:10px;background:${col.bg};color:${col.text};border:1px solid ${col.border}">${escHtml(t)}<span onclick="removeEditTeamTag('${escHtml(t)}')" style="cursor:pointer;opacity:.6;font-size:14px;line-height:1;margin-left:2px">&times;</span></span>`;
  }).join('');
}
function removeEditTeamTag(tag) { _tmeCurrentTags = (_tmeCurrentTags || []).filter(t => t !== tag); _renderEditTeamTagPills(); }
function handleEditTeamTagInput(e) {
  const input = e.target;
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault();
    const val = input.value.trim().replace(/,$/, '');
    if (val && !(_tmeCurrentTags || []).includes(val)) { _tmeCurrentTags = [...(_tmeCurrentTags || []), val]; _renderEditTeamTagPills(); }
    input.value = '';
  } else if (e.key === 'Backspace' && !input.value && (_tmeCurrentTags || []).length) {
    _tmeCurrentTags = _tmeCurrentTags.slice(0, -1); _renderEditTeamTagPills();
  }
}

function addEditTeamContactRow(data) {
  const wrap = document.getElementById('tme-extra-contacts');
  if (!wrap) return;
  const id = 'tmeec-' + Date.now() + Math.random().toString(36).slice(2, 6);
  const row = document.createElement('div');
  row.id = id;
  row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:6px;align-items:center;padding:8px 10px;background:var(--bg);border:1px solid var(--border);border-radius:8px';
  row.innerHTML = `
    <input placeholder="Name *" value="${(data && data.name) || ''}" style="padding:6px 9px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:var(--font)" class="tmeec-name">
    <input placeholder="Phone" value="${(data && data.phone) || ''}" style="padding:6px 9px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:var(--font)" class="tmeec-phone">
    <input placeholder="Relation (e.g. Emergency)" value="${(data && data.relation) || ''}" style="padding:6px 9px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:var(--font)" class="tmeec-relation">
    <button type="button" onclick="document.getElementById('${id}').remove()" style="width:28px;height:28px;border:none;background:var(--red-bg,#FFEBEE);color:#C62828;border-radius:6px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center">&times;</button>`;
  wrap.appendChild(row);
}
function _getEditTeamContacts() {
  const rows = document.querySelectorAll('#tme-extra-contacts > div');
  const result = [];
  rows.forEach(row => {
    const name = row.querySelector('.tmeec-name')?.value?.trim();
    const phone = row.querySelector('.tmeec-phone')?.value?.trim();
    const relation = row.querySelector('.tmeec-relation')?.value?.trim();
    if (name || phone) result.push({ name: name || '', phone: phone || '', relation: relation || '' });
  });
  return result;
}

async function saveEditTeamMember() {
  if (!_tmeEditingId) return;
  const name = document.getElementById('tme-name').value.trim();
  const email = document.getElementById('tme-email').value.trim();
  const mobile = document.getElementById('tme-mobile').value.trim();
  const address = document.getElementById('tme-address').value.trim();
  const password = document.getElementById('tme-password').value.trim();
  if (!name) { toast('⚠️ Name required', 'warning'); return; }
  if (!email) { toast('⚠️ Email required', 'warning'); return; }
  const btn = document.getElementById('tme-save-btn');
  if (btn.disabled) return;
  btn.disabled = true;
  try {
    const payload = { user_id: _tmeEditingId, name, email, mobile, address, tags: _tmeCurrentTags || [], contacts: _getEditTeamContacts() };
    if (password) payload.password = password;
    if (_tmeAvatarBase64 !== undefined) payload.avatar = _tmeAvatarBase64;
    await api('api/team.php?action=edit', 'PATCH', payload);
    closeModal('modal-edit-team');
    toast('✅ Team member updated', 'success');
    await renderTeam();
  } catch (e) { toast('❌ ' + e.message, 'error'); }
  finally { btn.disabled = false; }
}

// ── Role Permissions modal ─────────────────────────────────────
const TP = { catalog: [], plan: '', activeRole: 'sales' };

async function openTeamPermissionsModal(role) {
  TP.activeRole = role || 'sales';
  document.getElementById('tp-role-select').value = TP.activeRole;
  document.getElementById('tp-subtitle').textContent = 'Loading…';
  document.getElementById('tp-body').innerHTML = `<div style="text-align:center;color:var(--muted);padding:30px"><i class="fas fa-spinner fa-spin"></i> Loading permissions…</div>`;
  openModal('modal-team-permissions');
  try {
    const r = await api('api/role_permissions.php?action=list');
    TP.catalog = Array.isArray(r.data) ? r.data : [];
    TP.plan = r.plan || '';
    document.getElementById('tp-subtitle').textContent = `Plan: ${TP.plan}`;
    _renderTeamPermissionsBody();
  } catch (e) {
    document.getElementById('tp-body').innerHTML = `<div style="text-align:center;color:var(--red);padding:30px">❌ ${escHtml(e.message)}</div>`;
  }
}

function _tpSetActiveRole(role) { TP.activeRole = role; _renderTeamPermissionsBody(); }

function _renderTeamPermissionsBody() {
  const body = document.getElementById('tp-body');
  const role = TP.activeRole;
  if (!TP.catalog.length) { body.innerHTML = `<div style="text-align:center;color:var(--muted);padding:24px">No permissions defined yet.</div>`; return; }
  const groups = {};
  TP.catalog.forEach(p => { (groups[p.category || 'General'] = groups[p.category || 'General'] || []).push(p); });
  body.innerHTML = Object.keys(groups).map(cat => `
    <div style="margin-bottom:18px">
      <div style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">${escHtml(cat)}</div>
      <div style="display:flex;flex-direction:column;gap:1px;border:1px solid var(--border);border-radius:10px;overflow:hidden">
        ${groups[cat].map(p => {
          const enabled = !!p.roles[role];
          const ceiling = !!p.ceiling;
          const disabled = !ceiling && !enabled;
          return `<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 14px;background:${enabled ? 'var(--teal-bg)' : 'var(--card)'}">
            <div style="font-size:13px;color:var(--text);${disabled ? 'opacity:.5' : ''}">
              ${escHtml(p.label)}
              ${!ceiling ? `<span style="font-size:10px;color:var(--amber);margin-left:6px"><i class="fas fa-lock"></i> not on ${escHtml(TP.plan)} plan</span>` : ''}
            </div>
            <label style="position:relative;display:inline-block;width:38px;height:22px;flex-shrink:0">
              <input type="checkbox" ${enabled ? 'checked' : ''} ${disabled ? 'disabled' : ''} onchange="toggleTeamPermission('${role}','${p.key}',this.checked)" style="opacity:0;width:0;height:0">
              <span style="position:absolute;inset:0;background:${enabled ? '#00897B' : '#CBD5E1'};border-radius:22px;transition:.2s;cursor:${disabled ? 'not-allowed' : 'pointer'}"></span>
              <span style="position:absolute;top:2px;left:${enabled ? '18px' : '2px'};width:18px;height:18px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.3)"></span>
            </label>
          </div>`;
        }).join('')}
      </div>
    </div>
  `).join('');
}

async function toggleTeamPermission(role, key, enabled) {
  try {
    await api('api/role_permissions.php?action=set', 'POST', { role, permission_key: key, enabled });
    const item = TP.catalog.find(p => p.key === key);
    if (item) item.roles[role] = enabled;
    toast('✅ Updated', 'success');
    _renderTeamPermissionsBody();
  } catch (e) { toast('❌ ' + e.message, 'error'); _renderTeamPermissionsBody(); }
}

async function changeTeamRole(userId, newRole) {
  try {
    await api('api/team.php?action=update', 'PATCH', { user_id: userId, field: 'role', value: newRole });
    toast('✅ Role updated', 'success');
    await renderTeam();
  } catch (e) { toast('❌ ' + e.message, 'error'); renderTeam(); }
}

async function toggleTeamStatus(userId, newStatus) {
  const verb = newStatus === 'active' ? 'reactivate' : 'deactivate';
  const result = await Swal.fire({
    title: `${verb.charAt(0).toUpperCase() + verb.slice(1)} this team member?`,
    icon: 'warning', showCancelButton: true,
    confirmButtonText: verb.charAt(0).toUpperCase() + verb.slice(1),
    confirmButtonColor: newStatus === 'active' ? '#0F6E56' : '#E53935',
    cancelButtonText: 'Cancel', customClass: { popup: 'swal-compact' },
  });
  if (!result.isConfirmed) return;
  try {
    await api('api/team.php?action=update', 'PATCH', { user_id: userId, field: 'status', value: newStatus });
    toast('✅ Updated', 'success');
    renderTeam();
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

async function removeTeamMember(userId) {
  const result = await Swal.fire({
    title: 'Remove this team member?',
    html: 'They will lose access immediately and disappear from this list. Use "Deactivate" instead if you just want to pause their access and keep them visible.',
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Remove', confirmButtonColor: '#E53935',
    cancelButtonText: 'Cancel', customClass: { popup: 'swal-compact' },
  });
  if (!result.isConfirmed) return;
  try {
    await api('api/team.php?action=remove', 'PATCH', { user_id: userId });
    toast('🗑️ Removed', 'info');
    renderTeam();
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}
