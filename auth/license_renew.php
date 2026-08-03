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

$companyName   = defined('APP_NAME') ? APP_NAME : 'OPTMS Tech';
$businessName  = $user['company_name'] ?? $companyName;

// Edit these two to your real support contact.
$supportEmail = 'info@optms.co.in';
$supportPhone = '7282071620';
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
body{min-height:100vh;background:#E8EFEC;padding:40px 20px;display:flex;justify-content:center}
.wrap{width:100%;max-width:900px}
.card{background:#fff;border-radius:22px;padding:44px 48px;box-shadow:0 12px 56px rgba(8,80,65,.12);animation:fadeUp .4s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}

.hero{text-align:center;margin-bottom:34px}
.hero-icon{width:64px;height:64px;border-radius:50%;background:rgba(211,47,47,.1);display:flex;align-items:center;justify-content:center;margin:0 auto 18px}
.hero-icon i{font-size:26px;color:#D32F2F}
.hero-icon.ok{background:rgba(29,158,117,.12)}
.hero-icon.ok i{color:#0F6E56}
.hero h1{font-size:25px;font-weight:800;color:#111827}
.hero h1 .accent{color:#D32F2F}
.hero p{font-size:13.5px;color:#6B7280;margin-top:10px;line-height:1.6}
.hero p b{color:#D32F2F}

.info-card{display:grid;grid-template-columns:repeat(3,1fr);gap:0;border:1px solid #E5E7EB;border-radius:16px;padding:24px 8px;margin-bottom:20px}
.info-item{display:flex;gap:14px;align-items:flex-start;padding:0 20px;border-right:1px solid #EEF0F2}
.info-item:last-child{border-right:none}
.info-item .ic{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.info-item .ic.red{background:#FDECEC;color:#D32F2F}
.info-item .ic.blue{background:#E8F0FE;color:#1A73E8}
.info-item .ic.amber{background:#FFF3E0;color:#F57C00}
.info-item .lbl{font-size:11.5px;color:#6B7280;margin-bottom:3px}
.info-item .val{font-size:14.5px;font-weight:700;color:#111827}
.info-item .val.danger{color:#D32F2F}
.info-item .sub{font-size:11.5px;color:#9CA3AF;margin-top:2px}
.info-item .val.status-pill{font-size:11.5px;font-weight:700;color:#F57C00;text-transform:uppercase;letter-spacing:.3px}

.notice{display:flex;align-items:flex-start;gap:10px;background:#FDECEC;border:1px solid #F5C2C2;border-radius:14px;padding:16px 20px;margin-bottom:30px;text-align:center;flex-direction:column;align-items:center}
.notice .row{display:flex;align-items:center;gap:9px;font-weight:700;color:#B3261E;font-size:14px}
.notice p{font-size:12.5px;color:#8A5A00;margin-top:4px}
.notice.pending{background:#EAFBF4;border-color:#A7E9D3}
.notice.pending .row{color:#0F6E56}
.notice.pending p{color:#0F6E56;opacity:.85}

.why{text-align:center;margin-bottom:28px}
.why-title{font-size:14px;font-weight:700;color:#111827;position:relative;display:inline-block;padding:0 16px}
.why-title::before,.why-title::after{content:'';position:absolute;top:50%;width:34px;height:1px;background:#D8DEDB}
.why-title::before{right:100%}
.why-title::after{left:100%}
.benefits{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-top:20px}
.benefit{text-align:left}
.benefit .ic{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;font-size:15px}
.benefit .t{font-size:12.5px;font-weight:700;color:#111827;margin-bottom:4px;line-height:1.3}
.benefit .d{font-size:11px;color:#8B95A1;line-height:1.5}
.bi-1{background:#E8F0FE;color:#1A73E8}.bi-2{background:#E4F7EC;color:#1D9E75}
.bi-3{background:#F2E8FE;color:#8B3FE8}.bi-4{background:#FFF3E0;color:#F57C00}
.bi-5{background:#E4F7F7;color:#12A5A5}

.cta-band{border:1px solid #E5E7EB;border-radius:16px;padding:26px;text-align:center;margin-bottom:18px}
.cta-band .lead{font-size:14px;color:#374151;margin-bottom:18px;font-weight:500}
.cta-row{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
.btn-primary{padding:13px 26px;background:#085041;color:#E1F5EE;border:none;border-radius:10px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:9px;transition:background .16s;min-width:220px}
.btn-primary:hover{background:#0F6E56}
.btn-primary:disabled{opacity:.65;cursor:not-allowed}
.btn-outline{padding:12px 26px;background:#fff;border:1.5px solid #D8DEDB;border-radius:10px;font-family:inherit;font-size:14px;font-weight:700;color:#374151;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:9px;text-decoration:none;min-width:220px}
.btn-outline:hover{background:#F9FAFB}
.pending-pill{display:inline-flex;align-items:center;gap:9px;padding:13px 24px;border-radius:10px;background:#EAFBF4;border:1px solid #A7E9D3;color:#0F6E56;font-size:14px;font-weight:700}
.msg{font-size:12.5px;margin-top:12px}
.msg.err{color:#B3261E}
.btn-spinner{display:inline-block;animation:spinIcon .65s linear infinite}
@keyframes spinIcon{to{transform:rotate(360deg)}}

.footer-bar{text-align:center;font-size:12.5px;color:#6B7280;padding-top:18px;border-top:1px solid #EEF0F2}
.footer-bar a{color:#085041;font-weight:700;text-decoration:none}
.footer-bar .sep{margin:0 10px;color:#D8DEDB}
.logout-link{display:block;text-align:center;margin-top:14px;font-size:12.5px;color:#9CA3AF;text-decoration:none}
.logout-link:hover{color:#6B7280}

@media (max-width:760px){
  .card{padding:28px 22px}
  .info-card{grid-template-columns:1fr}
  .info-item{border-right:none;border-bottom:1px solid #EEF0F2;padding:14px 4px}
  .info-item:last-child{border-bottom:none}
  .benefits{grid-template-columns:repeat(2,1fr)}
}
</style>
</head>
<body>
<div class="wrap">
<div class="card">

  <?php if ($pendingRequest): ?>
    <div class="hero">
      <div class="hero-icon ok"><i class="fas fa-hourglass-half"></i></div>
      <h1>Renewal Request <span class="accent" style="color:#0F6E56">Sent</span></h1>
      <p>Your request is awaiting review by our team. You'll get access back as soon as it's approved — no need to send another request.</p>
    </div>
  <?php else: ?>
    <div class="hero">
      <div class="hero-icon"><i class="fas fa-triangle-exclamation"></i></div>
      <h1>Your License Has <span class="accent">Expired</span></h1>
      <p>Your <?= htmlspecialchars($companyName) ?> license expired on
        <b><?= htmlspecialchars(date('d F Y', strtotime($user['license_expiry']))) ?></b>.<br>
        Please renew your license to continue using all features without interruption.</p>
    </div>
  <?php endif; ?>

  <div class="info-card">
    <div class="info-item">
      <div class="ic red"><i class="fas fa-calendar-days"></i></div>
      <div>
        <div class="lbl">License Expired On</div>
        <div class="val danger"><?= htmlspecialchars(date('d F Y', strtotime($user['license_expiry']))) ?></div>
        <div class="sub">11:59 PM</div>
      </div>
    </div>
    <div class="info-item">
      <div class="ic blue"><i class="fas fa-id-badge"></i></div>
      <div>
        <div class="lbl">Staff Member</div>
        <div class="val"><?= htmlspecialchars($user['name']) ?></div>
        <div class="sub"><?= htmlspecialchars($businessName) ?></div>
      </div>
    </div>
    <div class="info-item">
      <div class="ic amber"><i class="fas fa-shield-halved"></i></div>
      <div>
        <div class="lbl">License No.</div>
        <div class="val"><?= htmlspecialchars($user['license_no'] ?: '—') ?></div>
        <div class="val status-pill">Expired</div>
      </div>
    </div>
  </div>

  <div class="notice <?= $pendingRequest ? 'pending' : '' ?>">
    <div class="row">
      <i class="fas <?= $pendingRequest ? 'fa-check-circle' : 'fa-triangle-exclamation' ?>"></i>
      <?= $pendingRequest ? 'Request sent — awaiting approval.' : 'Your account is currently in limited access mode.' ?>
    </div>
    <p><?= $pendingRequest ? "We'll restore your access as soon as it's reviewed." : 'Request renewal now to reactivate your account and avoid disruption.' ?></p>
  </div>

  <div class="why">
    <span class="why-title">Why Renew Your License?</span>
    <div class="benefits">
      <div class="benefit"><div class="ic bi-1"><i class="fas fa-chart-simple"></i></div><div class="t">Uninterrupted Work</div><div class="d">Keep invoicing and trading running smoothly.</div></div>
      <div class="benefit"><div class="ic bi-2"><i class="fas fa-shield-check"></i></div><div class="t">Secure &amp; Compliant</div><div class="d">Stay current with security &amp; regulatory updates.</div></div>
      <div class="benefit"><div class="ic bi-3"><i class="fas fa-headset"></i></div><div class="t">Priority Support</div><div class="d">Get fast help from our support team.</div></div>
      <div class="benefit"><div class="ic bi-4"><i class="fas fa-cloud-arrow-up"></i></div><div class="t">Data Protection</div><div class="d">Keep your records safe and always accessible.</div></div>
      <div class="benefit"><div class="ic bi-5"><i class="fas fa-layer-group"></i></div><div class="t">All Features Access</div><div class="d">Full access to every module you're licensed for.</div></div>
    </div>
  </div>

  <div class="cta-band">
    <?php if ($pendingRequest): ?>
      <div class="lead">We'll notify you the moment your license is renewed.</div>
      <div class="cta-row">
        <span class="pending-pill"><i class="fas fa-check-circle"></i> Request Pending Review</span>
        <a href="mailto:<?= htmlspecialchars($supportEmail) ?>" class="btn-outline"><i class="fas fa-headset"></i> Contact Support</a>
      </div>
    <?php else: ?>
      <div class="lead">Request your license renewal today and get back to business.</div>
      <div class="cta-row" id="rn-content">
        <button class="btn-primary" id="rn-btn" onclick="requestRenewal()">
          <i class="fas fa-rotate-right" id="rn-icon"></i>
          <span id="rn-label">Request Renewal Now</span>
        </button>
        <a href="mailto:<?= htmlspecialchars($supportEmail) ?>" class="btn-outline"><i class="fas fa-headset"></i> Contact Support</a>
      </div>
      <div class="msg" id="rn-msg" style="text-align:center"></div>
    <?php endif; ?>
  </div>

  <div class="footer-bar">
    <i class="fas fa-envelope"></i> Need Help? Contact us at
    <a href="mailto:<?= htmlspecialchars($supportEmail) ?>"><?= htmlspecialchars($supportEmail) ?></a>
    <span class="sep">|</span>
    <a href="tel:<?= htmlspecialchars(preg_replace('/\D/', '', $supportPhone)) ?>"><?= htmlspecialchars($supportPhone) ?></a>
  </div>

  <a href="/auth/logout.php" class="logout-link"><i class="fas fa-arrow-right-from-bracket"></i> Log Out</a>

</div>
</div>

<script>
async function requestRenewal() {
  const btn   = document.getElementById('rn-btn');
  const icon  = document.getElementById('rn-icon');
  const label = document.getElementById('rn-label');
  const msg   = document.getElementById('rn-msg');
  if (!btn) return;

  btn.disabled = true;
  icon.className = 'fas fa-circle-notch btn-spinner';
  label.textContent = 'Sending…';
  msg.textContent = '';

  try {
    const res  = await fetch('/api/license.php?action=request_renewal', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();
    if (data.success) {
      window.location.reload();
    } else {
      icon.className = 'fas fa-rotate-right';
      label.textContent = 'Request Renewal Now';
      btn.disabled = false;
      msg.className = 'msg err';
      msg.textContent = data.error || 'Something went wrong. Please try again.';
    }
  } catch (e) {
    icon.className = 'fas fa-rotate-right';
    label.textContent = 'Request Renewal Now';
    btn.disabled = false;
    msg.className = 'msg err';
    msg.textContent = 'Network error. Please check your connection and try again.';
  }
}
</script>
</body>
</html>
