// ================================================================
//  assets/js/profile.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  No MPA adjustments needed — self-contained in the SPA too.
//  (dropped the legacy duplicate window.saveProfile() from the SPA
//  — this page's buttons only ever called saveProfileInfo() and
//  saveProfilePassword() separately, so that combined version was
//  dead code.)
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['invoices', 'clients']);
  renderProfilePage();
});

window.renderProfilePage = function () {
  const invCount = document.getElementById('ps-inv-count');
  const clientCount = document.getElementById('ps-client-count');
  if (invCount) invCount.textContent = (STATE.invoices || []).length || '0';
  if (clientCount) clientCount.textContent = (STATE.clients || []).length || '0';
};

// Sync avatar + name everywhere after profile save (topbar, sidebar,
// dropdown all live in layout_header.php/layout_footer.php, which
// are present on every page including this one).
function _syncProfileUI(name, avatarSrc) {
  if (name) {
    const chipName = document.querySelector('.user-chip-name');
    if (chipName) chipName.textContent = name.split(' ')[0];
    const sidebarName = document.querySelector('.sidebar-footer .user-name');
    if (sidebarName) sidebarName.textContent = name;
    const dispName = document.getElementById('profile-display-name');
    if (dispName) dispName.textContent = name;
    const udName = document.querySelector('.udh-name');
    if (udName) udName.textContent = name;
  }
  if (avatarSrc) {
    const img = `<img src="${avatarSrc}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">`;
    ['#chipAvatar', '#dropdownAvatar', '#profile-avatar-preview', '.user-avatar'].forEach(sel => {
      document.querySelectorAll(sel).forEach(el => el.innerHTML = img);
    });
  }
}

window.toggleActivityHistory = function () {
  const btn = document.getElementById('activity-toggle-btn');
  const list = document.getElementById('activity-list');
  if (!list) return;
  const hidden = list.querySelectorAll('.activity-item.hidden-row');
  const expanded = btn?.dataset.expanded === '1';
  if (!expanded) {
    hidden.forEach(el => el.classList.remove('hidden-row'));
    if (btn) { btn.textContent = 'SHOW LESS'; btn.dataset.expanded = '1'; }
  } else {
    list.querySelectorAll('.activity-item').forEach((el, i) => { if (i >= 5) el.classList.add('hidden-row'); });
    if (btn) { btn.textContent = 'VIEW FULL HISTORY'; btn.dataset.expanded = '0'; }
  }
};

window.saveProfileInfo = async function () {
  const name = document.getElementById('profile-name')?.value.trim();
  const email = document.getElementById('profile-email')?.value.trim();
  const mobile = document.getElementById('profile-mobile')?.value.trim() || '';
  const altPhone = document.getElementById('profile-alt-phone')?.value.trim() || '';
  const address = document.getElementById('profile-address')?.value.trim() || '';
  if (!name || !email) { toast('Name and email are required', 'warning'); return; }
  try {
    await api('api/profile.php', 'POST', { name, email, mobile, alt_phone: altPhone, address });
    _syncProfileUI(name, null);
    toast('✅ Profile updated!', 'success');
  } catch (e) { toast('❌ Failed to save: ' + e.message, 'error'); }
};

window.saveProfilePassword = async function () {
  const pass = document.getElementById('profile-pass')?.value;
  const pass2 = document.getElementById('profile-pass2')?.value;
  if (!pass) { toast('Enter a new password', 'warning'); return; }
  if (pass.length < 6) { toast('Password must be at least 6 characters', 'warning'); return; }
  if (pass !== pass2) { toast('Passwords do not match', 'warning'); return; }
  try {
    await api('api/profile.php', 'POST', { password: pass });
    document.getElementById('profile-pass').value = '';
    document.getElementById('profile-pass2').value = '';
    toast('✅ Password updated!', 'success');
  } catch (e) { toast('❌ Failed: ' + e.message, 'error'); }
};

window.uploadProfilePhoto = async function (input) {
  const file = input.files[0]; if (!file) return;
  if (file.size > 2 * 1024 * 1024) { toast('⚠️ Max 2MB', 'warning'); return; }
  const fd = new FormData(); fd.append('file', file); fd.append('type', 'avatar');
  try {
    const res = await fetch('api/upload.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) throw new Error(data.error);
    // Persist the URL to the DB immediately — without this the photo
    // reverts on refresh since currentUser() re-reads from the DB.
    await api('api/profile.php', 'POST', { avatar: data.url });
    _syncProfileUI(null, data.url);
    SERVER.user = SERVER.user || {};
    SERVER.user._avatarUrl = data.url;
    toast('✅ Photo uploaded!', 'success');
  } catch (e) { toast('❌ Photo upload failed: ' + e.message, 'error'); }
};
