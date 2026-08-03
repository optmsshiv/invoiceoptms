<?php
// ================================================================
//  OPTMS Invoice Manager — auth/subscription_inactive.php
//
//  Shown to non-owner staff when their tenant's subscription has
//  expired. Deliberately has NO renewal action — billing/renewal
//  isn't this role's call to make. The owner gets license_renew.php
//  instead (see index.php's gate). If the subscription is renewed
//  or this user is later promoted to owner, they're bounced back
//  into the app automatically on next load.
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$user = currentUser();
if (!$user) { doLogout(); header('Location: /auth/login.php'); exit; }

if (!isTenantLicenseExpired($user)) {
    header('Location: /index.php');
    exit;
}
// Owner belongs on the actionable renewal page, not this passive one.
if (($user['role'] ?? '') === 'owner') {
    header('Location: /auth/license_renew.php');
    exit;
}

$companyName  = defined('APP_NAME') ? APP_NAME : 'OPTMS Tech';
$businessName = $user['company_name'] ?? $companyName;

// Edit these two to your real support contact.
$supportEmail = 'info@optms.co.in';
$supportPhone = '7282071620';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Subscription Inactive — <?= htmlspecialchars($companyName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:'Public Sans',sans-serif}
body{min-height:100vh;background:#E8EFEC;display:flex;align-items:center;justify-content:center;padding:24px 20px}
.card{width:100%;max-width:440px;background:#fff;border-radius:20px;padding:42px 38px;box-shadow:0 12px 56px rgba(8,80,65,.12);text-align:center;animation:fadeUp .4s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
.icon{width:56px;height:56px;border-radius:16px;background:#F3F4F6;border:1px solid #E5E7EB;display:flex;align-items:center;justify-content:center;margin:0 auto 20px}
.icon i{font-size:23px;color:#6B7280}
.title{font-size:19px;font-weight:800;color:#111827;margin-bottom:10px}
.desc{font-size:13.5px;color:#6B7280;line-height:1.65;margin-bottom:24px}
.desc b{color:#374151}
.info-box{background:#F9FAFB;border:1px solid #E5E7EB;border-radius:12px;padding:14px 18px;margin-bottom:24px;text-align:left}
.info-row{display:flex;justify-content:space-between;font-size:13px;padding:4px 0}
.info-row .lbl{color:#6B7280}
.info-row .val{color:#111827;font-weight:600}
.btn-outline{width:100%;padding:12px;background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:9px;font-family:inherit;font-size:13.5px;font-weight:700;color:#374151;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none}
.btn-outline:hover{background:#F3F4F6}
.logout-link{display:block;text-align:center;margin-top:16px;font-size:12.5px;color:#9CA3AF;text-decoration:none}
.logout-link:hover{color:#6B7280}
</style>
</head>
<body>

<div class="card">
  <div class="icon"><i class="fas fa-lock"></i></div>
  <div class="title">Subscription Inactive</div>
  <div class="desc">
    <b><?= htmlspecialchars($businessName) ?></b>'s subscription to <?= htmlspecialchars($companyName) ?> has expired.
    Please contact your administrator to renew it — access will be restored automatically once it's done.
  </div>

  <div class="info-box">
    <div class="info-row"><span class="lbl">Organization</span><span class="val"><?= htmlspecialchars($businessName) ?></span></div>
    <div class="info-row"><span class="lbl">Status</span><span class="val" style="color:#B3261E">Expired</span></div>
  </div>

  <a href="mailto:<?= htmlspecialchars($supportEmail) ?>" class="btn-outline"><i class="fas fa-headset"></i> Contact Support</a>
  <a href="/auth/logout.php" class="logout-link"><i class="fas fa-arrow-right-from-bracket"></i> Log Out</a>
</div>

</body>
</html>
