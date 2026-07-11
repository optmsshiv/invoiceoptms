<?php
// ================================================================
//  pages/team.php
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.team');

$user = currentUser();

$activePage  = 'team';
$pageTitle   = 'Team';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/team.js'];

include __DIR__ . '/../../includes/layout_header.php';
?>
      <div class="page-toolbar">
        <input type="text" class="table-search" placeholder="Search team…" id="teamSearch" oninput="filterTeam(this.value)">
        <div style="flex:1"></div>
        <span id="teamCountInfo" style="font-size:12px;color:var(--muted);margin-right:8px"></span>
        <button class="btn btn-primary" onclick="openAddTeamModal()"><i class="fas fa-user-plus"></i> Add Team Member</button>
      </div>
      <div class="table-card">
        <table class="data-table">
          <thead><tr>
            <th style="width:44px"></th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th>
          </tr></thead>
          <tbody id="teamTbody"></tbody>
        </table>
        <div class="table-footer">
          <div class="tf-info" id="teamInfo"></div>
        </div>
      </div>

      <!-- Add Team Member Modal — team-page-specific -->
      <div class="modal-overlay" id="modal-add-team">
        <div class="modal modal-md" style="max-width:560px;max-height:92vh;display:flex;flex-direction:column;">
          <div class="modal-header" style="padding:16px 20px;flex-shrink:0">
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:8px;background:var(--teal-bg);display:flex;align-items:center;justify-content:center">
                <i class="fas fa-user-plus" style="color:var(--teal);font-size:14px"></i>
              </div>
              <div style="font-size:14px;font-weight:700;color:var(--text)">Add Team Member</div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-add-team')"><i class="fas fa-times"></i></button>
          </div>
          <div class="modal-body" style="overflow-y:auto;padding:18px 20px;display:flex;flex-direction:column;gap:16px">
            <div style="display:flex;align-items:center;gap:16px;padding:14px;background:var(--bg);border-radius:10px;border:1px solid var(--border)">
              <div id="tm-avatar-preview" style="width:64px;height:64px;border-radius:50%;background:#00897B;display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;overflow:hidden;flex-shrink:0;border:3px solid var(--border);transition:border-color .3s,box-shadow .3s">
                <i class="fas fa-user" id="tm-avatar-icon"></i>
                <img id="tm-avatar-img" src="" style="width:100%;height:100%;object-fit:cover;display:none">
              </div>
              <div style="flex:1;min-width:0">
                <label id="tm-avatar-upload-btn" style="cursor:pointer;display:inline-flex;align-items:center;gap:6px;background:var(--card);color:var(--text);border:1.5px solid var(--border);padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:600;position:relative;overflow:hidden;transition:.2s">
                  <i class="fas fa-camera" id="tm-avatar-upload-icon"></i>
                  <span id="tm-avatar-upload-text">Upload Photo</span>
                  <div id="tm-avatar-progress-bar" style="position:absolute;left:0;bottom:0;height:2px;width:0%;background:var(--teal);transition:width .05s linear"></div>
                  <input type="file" id="tm-avatar-file" accept="image/*" style="display:none" onchange="handleTeamAvatarUpload(this)">
                </label>
                <div style="font-size:11px;color:var(--muted);margin-top:5px">JPG or PNG, optional</div>
              </div>
            </div>
            <div class="form-grid g2">
              <div class="field"><label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:5px">Name *</label>
                <input type="text" id="tm-name" class="table-search" style="width:100%" placeholder="Full name"></div>
              <div class="field"><label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:5px">Email *</label>
                <input type="email" id="tm-email" class="table-search" style="width:100%" placeholder="user@company.com"></div>
              <div class="field"><label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:5px">Mobile Number</label>
                <input type="text" id="tm-mobile" class="table-search" style="width:100%" placeholder="+91 98765 43210"></div>
              <div class="field"><label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:5px">Role *</label>
                <select id="tm-role" class="table-filter" style="width:100%">
                  <option value="admin">Admin</option>
                  <option value="manager">Manager</option>
                  <option value="accountant">Accountant</option>
                  <option value="sales" selected>Sales — create invoices + clients only</option>
                  <option value="viewer">Viewer</option>
                </select></div>
            </div>
            <div>
              <label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:5px">Address</label>
              <textarea id="tm-address" class="table-search" style="width:100%;min-height:56px;resize:vertical" placeholder="Street, city, state, PIN"></textarea>
            </div>
            <div>
              <label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:6px">Tags / Labels <span style="font-size:10px;color:var(--muted);font-weight:400;text-transform:none">(optional — press Enter to add)</span></label>
              <div id="tm-tags-pills" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px"></div>
              <input id="tm-tag-input" class="table-search" style="width:100%" placeholder="e.g. Field Staff, Night Shift…" onkeydown="handleTeamTagInput(event)">
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px">Additional Contacts <span style="font-size:10px;color:var(--muted);font-weight:400;text-transform:none">(optional)</span></label>
                <button type="button" class="btn btn-outline" style="font-size:12px;padding:4px 10px" onclick="addTeamContactRow()"><i class="fas fa-plus"></i> Add Contact</button>
              </div>
              <div id="tm-extra-contacts" style="display:flex;flex-direction:column;gap:6px"></div>
            </div>
            <div>
              <label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:5px">Password <span style="font-size:10px;color:var(--muted);font-weight:400;text-transform:none">(blank = auto-generate)</span></label>
              <input type="text" id="tm-password" class="table-search" style="width:100%" placeholder="Leave blank to auto-generate">
            </div>
            <div style="font-size:11.5px;color:var(--muted);background:var(--bg);border-radius:8px;padding:8px 10px">
              <i class="fas fa-info-circle"></i> If left blank, a temporary password will be generated. Share it with them securely — they should change it after first login.
            </div>
          </div>
          <div class="modal-footer" style="flex-shrink:0;border-top:1px solid var(--border);padding:14px 22px;display:flex;gap:10px;justify-content:flex-end;background:var(--card)">
            <button class="btn btn-outline" onclick="closeModal('modal-add-team')">Cancel</button>
            <button class="btn btn-primary" id="tm-save-btn" onclick="saveNewTeamMember()"><i class="fas fa-user-plus"></i> Add User</button>
          </div>
        </div>
      </div>

      <!-- Edit Team Member Modal — team-page-specific -->
      <div class="modal-overlay" id="modal-edit-team">
        <div class="modal modal-md" style="max-width:560px;max-height:92vh;display:flex;flex-direction:column;">
          <div class="modal-header" style="padding:16px 20px;flex-shrink:0">
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:8px;background:var(--blue-bg);display:flex;align-items:center;justify-content:center">
                <i class="fas fa-user-pen" style="color:var(--blue);font-size:14px"></i>
              </div>
              <div style="font-size:14px;font-weight:700;color:var(--text)">Edit Team Member</div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-edit-team')"><i class="fas fa-times"></i></button>
          </div>
          <div class="modal-body" style="overflow-y:auto;padding:18px 20px;display:flex;flex-direction:column;gap:16px">
            <div style="display:flex;align-items:center;gap:16px;padding:14px;background:var(--bg);border-radius:10px;border:1px solid var(--border)">
              <div id="tme-avatar-preview" style="width:64px;height:64px;border-radius:50%;background:#00897B;display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;overflow:hidden;flex-shrink:0;border:3px solid var(--border);transition:border-color .3s,box-shadow .3s">
                <i class="fas fa-user" id="tme-avatar-icon"></i>
                <img id="tme-avatar-img" src="" style="width:100%;height:100%;object-fit:cover;display:none">
              </div>
              <div style="flex:1;min-width:0">
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                  <label id="tme-avatar-upload-btn" style="cursor:pointer;display:inline-flex;align-items:center;gap:6px;background:var(--card);color:var(--text);border:1.5px solid var(--border);padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:600;position:relative;overflow:hidden;transition:.2s">
                    <i class="fas fa-camera" id="tme-avatar-upload-icon"></i>
                    <span id="tme-avatar-upload-text">Change Photo</span>
                    <div id="tme-avatar-progress-bar" style="position:absolute;left:0;bottom:0;height:2px;width:0%;background:var(--teal);transition:width .05s linear"></div>
                    <input type="file" id="tme-avatar-file" accept="image/*" style="display:none" onchange="handleEditTeamAvatarUpload(this)">
                  </label>
                  <button type="button" class="btn btn-outline" style="font-size:12px;padding:6px 10px;color:var(--red)" onclick="clearEditTeamAvatar()"><i class="fas fa-times"></i> Remove</button>
                </div>
                <div style="font-size:11px;color:var(--muted);margin-top:5px">JPG or PNG, optional</div>
              </div>
            </div>
            <div class="form-grid g2">
              <div class="field"><label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:5px">Name *</label>
                <input type="text" id="tme-name" class="table-search" style="width:100%" placeholder="Full name"></div>
              <div class="field"><label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:5px">Email *</label>
                <input type="email" id="tme-email" class="table-search" style="width:100%" placeholder="user@company.com"></div>
              <div class="field"><label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:5px">Mobile Number</label>
                <input type="text" id="tme-mobile" class="table-search" style="width:100%" placeholder="+91 98765 43210"></div>
              <div class="field"><label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:5px">Role</label>
                <input type="text" id="tme-role-display" class="table-search" style="width:100%;background:var(--bg);color:var(--muted)" disabled>
                <div style="font-size:10px;color:var(--muted);margin-top:4px">Change role from the Team table dropdown</div></div>
            </div>
            <div>
              <label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:5px">Address</label>
              <textarea id="tme-address" class="table-search" style="width:100%;min-height:56px;resize:vertical" placeholder="Street, city, state, PIN"></textarea>
            </div>
            <div>
              <label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:6px">Tags / Labels <span style="font-size:10px;color:var(--muted);font-weight:400;text-transform:none">(optional — press Enter to add)</span></label>
              <div id="tme-tags-pills" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px"></div>
              <input id="tme-tag-input" class="table-search" style="width:100%" placeholder="e.g. Field Staff, Night Shift…" onkeydown="handleEditTeamTagInput(event)">
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px">Additional Contacts <span style="font-size:10px;color:var(--muted);font-weight:400;text-transform:none">(optional)</span></label>
                <button type="button" class="btn btn-outline" style="font-size:12px;padding:4px 10px" onclick="addEditTeamContactRow()"><i class="fas fa-plus"></i> Add Contact</button>
              </div>
              <div id="tme-extra-contacts" style="display:flex;flex-direction:column;gap:6px"></div>
            </div>
            <div>
              <label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:5px">New Password <span style="font-size:10px;color:var(--muted);font-weight:400;text-transform:none">(leave blank to keep current password)</span></label>
              <input type="text" id="tme-password" class="table-search" style="width:100%" placeholder="Leave blank to keep current password">
            </div>
          </div>
          <div class="modal-footer" style="flex-shrink:0;border-top:1px solid var(--border);padding:14px 22px;display:flex;gap:10px;justify-content:flex-end;background:var(--card)">
            <button class="btn btn-outline" onclick="closeModal('modal-edit-team')">Cancel</button>
            <button class="btn btn-primary" id="tme-save-btn" onclick="saveEditTeamMember()"><i class="fas fa-check"></i> Save Changes</button>
          </div>
        </div>
      </div>

      <!-- Role Permissions Modal — team-page-specific -->
      <div class="modal-overlay" id="modal-team-permissions">
        <div class="modal modal-lg" style="max-width:720px;max-height:90vh;display:flex;flex-direction:column;">
          <div class="modal-header" style="padding:16px 20px;flex-shrink:0">
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:8px;background:var(--purple-bg);display:flex;align-items:center;justify-content:center">
                <i class="fas fa-shield-halved" style="color:var(--purple);font-size:14px"></i>
              </div>
              <div>
                <div style="font-size:14px;font-weight:700;color:var(--text)">Role Permissions</div>
                <div id="tp-subtitle" style="font-size:11px;color:var(--muted);font-weight:400;margin-top:1px"></div>
              </div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-team-permissions')"><i class="fas fa-times"></i></button>
          </div>
          <div style="padding:12px 20px 0;flex-shrink:0;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <label style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px">Editing role:</label>
            <select id="tp-role-select" class="table-filter" style="font-size:13px;padding:6px 10px" onchange="_tpSetActiveRole(this.value)">
              <option value="admin">Admin</option>
              <option value="manager">Manager</option>
              <option value="accountant">Accountant</option>
              <option value="sales">Sales</option>
              <option value="viewer">Viewer</option>
            </select>
            <span style="font-size:11px;color:var(--muted)"><i class="fas fa-info-circle"></i> Changes apply to every user with this role.</span>
          </div>
          <div class="modal-body" id="tp-body" style="overflow-y:auto;padding:16px 20px;flex:1;min-height:0">
            <div style="text-align:center;color:var(--muted);padding:30px"><i class="fas fa-spinner fa-spin"></i> Loading permissions…</div>
          </div>
          <div class="modal-footer" style="flex-shrink:0;border-top:1px solid var(--border);padding:14px 22px;display:flex;gap:10px;justify-content:flex-end;background:var(--card)">
            <button class="btn btn-outline" onclick="closeModal('modal-team-permissions')">Close</button>
          </div>
        </div>
      </div>
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
