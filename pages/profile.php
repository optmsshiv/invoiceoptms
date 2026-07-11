<?php
// ================================================================
//  pages/profile.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$user = currentUser();

// Only used on this page, so defined here rather than in auth.php.
function optms_time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) { $d = floor($diff / 86400); return $d . ' day' . ($d > 1 ? 's' : '') . ' ago'; }
    return date('d M Y, h:i A', strtotime($datetime));
}
$loginActivity = getRecentLoginActivity((int)$user['id'], 20);

$activePage  = 'profile';
$pageTitle   = 'My Profile';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/profile.js'];

include __DIR__ . '/../includes/layout_header.php';
?>
      <div class="profile-page-wrap">
       <div class="profile-2col">

        <!-- LEFT COLUMN -->
        <div class="profile-col-left">

        <div class="profile-left-card">
          <div class="profile-banner"></div>
          <div class="profile-left-body">
            <div class="profile-av-wrap profile-av-row">
              <label style="cursor:pointer;display:inline-block" title="Click to change photo">
                <div class="profile-av-lg" id="profile-avatar-preview">
                  <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>" style="width:100%;height:100%;object-fit:cover">
                  <?php else: ?>
                    <?= strtoupper(substr($user['name'], 0, 2)) ?>
                  <?php endif; ?>
                  <div class="pav-overlay"><i class="fas fa-camera"></i></div>
                </div>
                <input type="file" id="profile-photo-input" accept="image/*" style="display:none" onchange="uploadProfilePhoto(this)">
              </label>
              <button type="button" class="profile-upload-btn" onclick="document.getElementById('profile-photo-input').click()">
                <i class="fas fa-upload"></i> Upload Photo
              </button>
            </div>
            <div style="margin:16px 0 18px">
              <div id="profile-display-name" style="font-size:18px;font-weight:800;color:var(--text);line-height:1.2"><?= htmlspecialchars($user['name']) ?></div>
              <div style="font-size:12px;color:var(--muted);margin-top:3px"><?= htmlspecialchars($user['email']) ?></div>
              <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:7px">
                <span style="display:inline-block;font-size:10px;font-weight:700;padding:2px 9px;border-radius:8px;background:var(--teal-bg,#E0F2F1);color:var(--teal);text-transform:uppercase;letter-spacing:.5px"><?= ucfirst($user['role']) ?></span>
                <?php if (!empty($user['is_verified'])): ?>
                  <span class="badge-verified"><i class="fas fa-check-circle"></i> Verified</span>
                <?php endif; ?>
              </div>
            </div>
            <div>
              <div class="profile-stat">
                <span class="profile-stat-label"><i class="fas fa-file-invoice" style="width:14px;color:var(--muted)"></i> Total Invoices</span>
                <span class="profile-stat-val" id="ps-inv-count">—</span>
              </div>
              <div class="profile-stat">
                <span class="profile-stat-label"><i class="fas fa-users" style="width:14px;color:var(--muted)"></i> Total Clients</span>
                <span class="profile-stat-val" id="ps-client-count">—</span>
              </div>
              <div class="profile-stat">
                <span class="profile-stat-label"><i class="fas fa-clock" style="width:14px;color:var(--muted)"></i> Member Since</span>
                <span class="profile-stat-val"><?= isset($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : 'N/A' ?></span>
              </div>
              <?php if (!empty($user['license_no'])): ?>
              <div class="profile-stat">
                <span class="profile-stat-label"><i class="fas fa-id-card" style="width:14px;color:var(--muted)"></i> License</span>
                <span class="profile-stat-val"><?= htmlspecialchars($user['license_no']) ?></span>
              </div>
              <?php endif; ?>
              <?php if (!empty($user['license_expiry'])):
                $licExpired = strtotime($user['license_expiry']) < strtotime('today');
              ?>
              <div class="profile-stat">
                <span class="profile-stat-label"><i class="fas fa-calendar-times" style="width:14px;color:var(--muted)"></i> License Expiry</span>
                <span class="profile-stat-val<?= $licExpired ? ' expired' : '' ?>"><?= date('d M Y', strtotime($user['license_expiry'])) ?><?= $licExpired ? ' (Expired)' : '' ?></span>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="pcard">
          <div class="pcard-body">
            <div class="acct-security-row">
              <div class="acct-security-icon"><i class="fas fa-shield-alt"></i></div>
              <div>
                <div class="acct-security-title">Account Security</div>
                <div class="acct-security-sub">You're signed in as <?= htmlspecialchars(ucfirst($user['role'])) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="pcard profile-logout-card" onclick="confirmLogout()">
          <div class="pcard-body">
            <div class="acct-security-row">
              <div class="acct-security-icon"><i class="fas fa-sign-out-alt"></i></div>
              <div class="acct-security-title">Log Out</div>
            </div>
          </div>
        </div>

        <div class="pcard">
          <div class="pcard-header">
            <i class="fas fa-history"></i>
            <span class="pcard-title">Activity Timeline</span>
          </div>
          <div class="pcard-body">
            <?php if (empty($loginActivity)): ?>
              <div class="activity-empty">No login activity recorded yet.</div>
            <?php else: ?>
              <div class="activity-list" id="activity-list">
                <?php foreach ($loginActivity as $i => $ev):
                  $isLogin = $ev['action'] === 'login';
                ?>
                  <div class="activity-item<?= $i >= 5 ? ' hidden-row' : '' ?>">
                    <div class="activity-icon <?= $isLogin ? 'login' : 'logout' ?>">
                      <i class="fas fa-<?= $isLogin ? 'sign-in-alt' : 'sign-out-alt' ?>"></i>
                    </div>
                    <div>
                      <div class="activity-title"><?= $isLogin ? 'System Login' : 'System Logout' ?></div>
                      <div class="activity-sub">Success from IP <?= htmlspecialchars($ev['ip'] ?: 'unknown') ?></div>
                      <div class="activity-time"><?= htmlspecialchars(optms_time_ago($ev['created_at'])) ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <?php if (count($loginActivity) > 5): ?>
                <button type="button" class="view-history-btn" id="activity-toggle-btn" onclick="toggleActivityHistory()">VIEW FULL HISTORY</button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        </div>
        <!-- /profile-col-left -->

        <!-- RIGHT COLUMN -->
        <div class="profile-col-right">

        <div class="pcard">
          <div class="pcard-header">
            <i class="fas fa-user"></i>
            <span class="pcard-title">Account Information</span>
          </div>
          <div class="pcard-body">
            <div class="pcard-field-row">
              <div class="field"><label>Email Address</label><input type="email" id="profile-email" value="<?= htmlspecialchars($user['email']) ?>" placeholder="your@email.com"></div>
              <div class="field"><label>Full Name</label><input id="profile-name" value="<?= htmlspecialchars($user['name']) ?>" placeholder="Your full name"></div>
            </div>
            <div class="pcard-field-row">
              <div class="field"><label>Mobile Number</label><input type="tel" id="profile-mobile" value="<?= htmlspecialchars($user['mobile'] ?? '') ?>" placeholder="+91 98765 43210"></div>
              <div class="field"><label>Alt Phone <span style="font-weight:400;color:var(--muted)">(optional)</span></label><input type="tel" id="profile-alt-phone" value="<?= htmlspecialchars($user['alt_phone'] ?? '') ?>" placeholder="+91 98765 43210"></div>
            </div>
            <div class="field readonly"><label>User ID</label><input id="profile-user-id" value="<?= htmlspecialchars($user['id']) ?>" readonly disabled title="Your account ID (read-only)"></div>
            <div class="field g-full" style="margin-bottom:0"><label>Address</label><input id="profile-address" value="<?= htmlspecialchars($user['address'] ?? '') ?>" placeholder="Street, city, state, PIN"></div>
          </div>
          <div class="pcard-footer">
            <button class="btn btn-primary" onclick="saveProfileInfo()"><i class="fas fa-save"></i> Save Changes</button>
          </div>
        </div>

        <div class="pcard">
          <div class="pcard-header">
            <i class="fas fa-lock"></i>
            <span class="pcard-title">Change Password</span>
          </div>
          <div class="pcard-body">
            <div class="field"><label>New Password</label><input type="password" id="profile-pass" placeholder="Minimum 6 characters" autocomplete="new-password"></div>
            <div class="field"><label>Confirm New Password</label><input type="password" id="profile-pass2" placeholder="Repeat new password" autocomplete="new-password"></div>
          </div>
          <div class="pcard-footer">
            <span style="font-size:11px;color:var(--muted)">Leave blank to keep current password</span>
            <button class="btn btn-primary" onclick="saveProfilePassword()"><i class="fas fa-key"></i> Update Password</button>
          </div>
        </div>

        </div>
        <!-- /profile-col-right -->

       </div>
       <!-- /profile-2col -->
      </div>
<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
