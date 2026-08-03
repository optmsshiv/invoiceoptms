<?php
// ================================================================
//  OPTMS Invoice Manager — auth/license_renew.php
//
//  Landing page for a staff member whose license_expiry has passed.
//  index.php redirects here instead of loading the dashboard (see
//  the license gate right after currentUser() there).
//
//  Deliberately NOT enforced on every single AJAX call (only on full
//  page loads of index.php) — a mid-session expiry won't yank someone
//  out of a half-finished action, only their next full navigation.
//  If you want stricter enforcement (kick out immediately, even
//  mid-session), that check would need to move into requireLogin()
//  itself in includes/auth.php — a bigger blast radius across every
//  protected page/API, so left as a deliberate, smaller first step.
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$user = currentUser();
if (!$user) { doLogout(); header('Location: /auth/login.php'); exit; }

// License already renewed (or wasn't actually expired) — send them
// back into the app instead of showing this page pointlessly.
if (!isLicenseExpired($user)) {
    header('Location: /index.php');
    exit;
}

// Is there already a pending renewal request for this user?
$pendingRequest = null;
try {
    $stmt = getMasterDB()->prepare(
        "SELECT id, requested_at FROM license_renewal_requests
          WHERE user_id = ? AND status = 'pending'
          ORDER BY requested_at DESC LIMIT 1"
    );
    $stmt->execute([$user['id']]);
    $pendingRequest = $stmt->fetch() ?: null;
} catch (Exception $e) {
    error_log('license_renew.php: pending request lookup failed: ' . $e->getMessage());
}

$companyName = defined('APP_NAME') ? APP_NAME : 'OPTMS Tech';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>License Expired — <?= htmlspecialchars($companyName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:'Public Sans',sans-serif}
body{min-height:100vh;background:#E8EFEC;display:flex;align-items:center;justify-content:center;padding:24px 20px}
.card{width:100%;max-width:460px;background:#fff;border-radius:20px;padding:40px 36px;box-shadow:0 12px 56px rgba(8,80,65,.13);animation:fadeUp .4s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
.icon{width:52px;height:52px;border-radius:14px;background:rgba(196,60,45,.1);border:1px solid rgba(196,60,45,.22);display:flex;align-items:center;justify-content:center;margin-bottom:22px}
.icon i{font-size:22px;color:#B3261E}
.icon.pending{background:rgba(29,158,117,.12);border-color:rgba(29,158,117,.28)}
.icon.pending i{color:#0F6E56}
.title{font-size:20px;font-weight:700;color:#111827;margin-bottom:8px}
.desc{font-size:13px;color:#6B7280;line-height:1.6;margin-bottom:22px}
.info-box{background:#F9FAFB;border:1px solid #E5E7EB;border-radius:12px;padding:16px 18px;margin-bottom:22px}
.info-row{display:flex;justify-content:space-between;font-size:13px;padding:5px 0}
.info-row .lbl{color:#6B7280}
.info-row .val{color:#111827;font-weight:600}
.val.expired{color:#B3261E}
.btn-primary{width:100%;padding:12px;background:#085041;color:#E1F5EE;border:none;border-radius:9px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;transition:background .16s}
.btn-primary:hover{background:#0F6E56}
.btn-primary:disabled{opacity:.65;cursor:not-allowed}
.btn-outline{width:100%;padding:11px;background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:9px;font-family:inherit;font-size:13.5px;font-weight:600;color:#374151;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;margin-top:10px}
.btn-outline:hover{background:#F3F4F6}
.pending-pill{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:10px;background:#EAFBF4;border:1px solid #A7E9D3;color:#0F6E56;font-size:13px;font-weight:600;width:100%;justify-content:center;margin-bottom:8px}
.btn-spinner{display:inline-block;animation:spinIcon .65s linear infinite}
@keyframes spinIcon{to{transform:rotate(360deg)}}
.msg{font-size:12.5px;margin-top:10px;text-align:center}
.msg.err{color:#B3261E}
.msg.ok{color:#0F6E56}
</style>
</head>
<body>

<div class="card">
  <div class="icon <?= $pendingRequest ? 'pending' : '' ?>">
    <i class="fas <?= $pendingRequest ? 'fa-hourglass-half' : 'fa-id-card' ?>"></i>
  </div>
  <div class="title"><?= $pendingRequest ? 'Renewal request sent' : 'Your license has expired' ?></div>
  <div class="desc">
    <?= $pendingRequest
        ? 'Your renewal request is awaiting review. You\'ll get access back as soon as it\'s approved — no need to send another request.'
        : 'Access to ' . htmlspecialchars($companyName) . ' is on hold until your license is renewed. Send a renewal request and our team will review it shortly.' ?>
  </div>

  <div class="info-box">
    <div class="info-row"><span class="lbl">Staff member</span><span class="val"><?= htmlspecialchars($user['name']) ?></span></div>
    <div class="info-row"><span class="lbl">License No.</span><span class="val"><?= htmlspecialchars($user['license_no'] ?: '—') ?></span></div>
    <div class="info-row"><span class="lbl">Expired on</span><span class="val expired"><?= htmlspecialchars(date('d M Y', strtotime($user['license_expiry']))) ?></span></div>
  </div>

  <div id="rn-content">
    <?php if ($pendingRequest): ?>
      <div class="pending-pill"><i class="fas fa-check-circle"></i> Request sent — awaiting approval</div>
    <?php else: ?>
      <button class="btn-primary" id="rn-btn" onclick="requestRenewal()">
        <i class="fas fa-paper-plane" id="rn-icon"></i>
        <span id="rn-label">Request License Renewal</span>
      </button>
      <div class="msg" id="rn-msg"></div>
    <?php endif; ?>
  </div>

  <a href="/auth/logout.php" class="btn-outline"><i class="fas fa-arrow-right-from-bracket"></i> Log Out</a>
</div>

<script>
async function requestRenewal() {
  const btn   = document.getElementById('rn-btn');
  const icon  = document.getElementById('rn-icon');
  const label = document.getElementById('rn-label');
  const msg   = document.getElementById('rn-msg');

  btn.disabled = true;
  icon.className = 'fas fa-circle-notch btn-spinner';
  label.textContent = 'Sending…';
  msg.textContent = '';
  msg.className = 'msg';

  try {
    const res  = await fetch('/api/license.php?action=request_renewal', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();
    if (data.success) {
      document.getElementById('rn-content').innerHTML =
        '<div class="pending-pill"><i class="fas fa-check-circle"></i> Request sent — awaiting approval</div>';
    } else {
      icon.className = 'fas fa-paper-plane';
      label.textContent = 'Request License Renewal';
      btn.disabled = false;
      msg.textContent = data.error || 'Something went wrong. Please try again.';
      msg.className = 'msg err';
    }
  } catch (e) {
    icon.className = 'fas fa-paper-plane';
    label.textContent = 'Request License Renewal';
    btn.disabled = false;
    msg.textContent = 'Network error. Please check your connection and try again.';
    msg.className = 'msg err';
  }
}
</script>
</body>
</html>
