<?php
// ================================================================
//  OPTMS Invoice Manager — 403 Permission Denied
// ================================================================
http_response_code(403);
$companyName = 'OPTMS Invoice Manager';
$homeUrl   = '/';
$loginUrl  = 'login.php';
$isLoggedIn = false;
$userLabel  = '';

if (file_exists(__DIR__ . '/../includes/auth.php')) {
    require_once __DIR__ . '/../includes/auth.php';
    if (function_exists('startSession')) { startSession(); }
    if (!empty($_SESSION['user_id'])) {
        $isLoggedIn = true;
        $userLabel  = $_SESSION['user_name'] ?? $_SESSION['user_email'] ?? '';
    }
    if (function_exists('getSetting')) {
        $companyName = getSetting('company_name', $companyName);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Access Denied — <?= htmlspecialchars($companyName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<style>
:root{
  --surface:#f8faf6; --surface-container-lowest:#ffffff; --surface-container-low:#f2f4f1;
  --on-surface:#191c1b; --on-surface-variant:#3f4944;
  --outline:#6f7974; --outline-variant:#bfc9c3;
  --secondary:#765b04; --secondary-container:#fed97c; --on-secondary-container:#785d07;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
  font-family:'Inter',sans-serif;
  background:var(--surface);
  min-height:100vh;
  display:flex;align-items:center;justify-content:center;
  padding:24px;position:relative;overflow:hidden;
}
.blob{position:absolute;border-radius:50%;filter:blur(60px);opacity:.3;pointer-events:none;z-index:0}
.blob-1{width:420px;height:420px;background:#ffdf94;top:-140px;left:-140px}
.blob-2{width:300px;height:300px;background:#e1e3e0;bottom:-110px;right:-90px;opacity:.6}

.card{
  position:relative;z-index:1;
  background:var(--surface-container-lowest);
  border:1px solid var(--outline-variant);
  border-radius:18px;
  box-shadow:0 12px 32px rgba(0,0,0,.08);
  width:100%;max-width:440px;
  padding:44px 40px 36px;
  text-align:center;
  animation:fadeUp .45s ease;
}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}

.status-badge{
  display:inline-flex;align-items:center;gap:6px;
  font-family:'JetBrains Mono',monospace;font-size:12px;font-weight:500;
  letter-spacing:.03em;color:var(--on-secondary-container);
  background:var(--secondary-container);
  padding:5px 12px;border-radius:999px;
  margin-bottom:22px;
}

.icon-circle{
  width:72px;height:72px;border-radius:50%;
  background:var(--secondary-container);
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 24px;
}
.icon-circle svg{width:30px;height:30px;stroke:var(--on-secondary-container);stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}

h1{
  font-family:'Hanken Grotesk',sans-serif;
  font-size:24px;font-weight:600;letter-spacing:-0.01em;
  color:var(--on-surface);margin-bottom:10px;
}
p.desc{
  font-size:14px;line-height:22px;color:var(--on-surface-variant);
  margin-bottom:8px;
}
p.desc + p.desc{margin-bottom:28px}

.user-chip{
  display:inline-flex;align-items:center;gap:6px;
  font-size:12.5px;color:var(--on-surface-variant);
  background:var(--surface-container-low);
  padding:6px 12px;border-radius:999px;
  margin-bottom:26px;
}
.user-chip svg{width:13px;height:13px;stroke:var(--on-surface-variant);stroke-width:2;fill:none}

.actions{display:flex;gap:10px}
.btn-primary,.btn-outline{
  flex:1;display:flex;align-items:center;justify-content:center;gap:8px;
  height:44px;border-radius:8px;
  font-family:'Inter',sans-serif;font-size:13.5px;font-weight:600;
  cursor:pointer;text-decoration:none;
  transition:background .15s,transform .1s,border-color .15s;
}
.btn-primary{background:#0f5a46;color:#fff;border:none}
.btn-primary:hover{background:#0c4a3a}
.btn-outline{background:#fff;color:#1c1c1c;border:1.5px solid #e5e7eb}
.btn-outline:hover{border-color:var(--outline)}
.btn-primary:active,.btn-outline:active{transform:scale(.98)}

.help-text{
  margin-top:22px;padding-top:18px;border-top:1px solid var(--outline-variant);
  font-size:12.5px;color:var(--outline);
}
.help-text a{color:var(--on-surface-variant);font-weight:600;text-decoration:none}
.help-text a:hover{text-decoration:underline}
</style>
</head>
<body>

<div class="blob blob-1"></div>
<div class="blob blob-2"></div>

<div class="card">
  <div class="status-badge">ERROR 403</div>

  <div class="icon-circle">
    <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
  </div>

  <h1>Access denied</h1>
  <?php if (!empty($roleFailReason)): ?>
    <p class="desc"><?= htmlspecialchars($roleFailReason) ?></p>
    <p class="desc">If you believe this is a mistake, contact your administrator.</p>
  <?php else: ?>
    <p class="desc">You don't have permission to view this page.</p>
    <p class="desc">If you believe this is a mistake, contact your administrator.</p>
  <?php endif; ?>

  <?php if ($isLoggedIn && $userLabel): ?>
  <div class="user-chip">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/></svg>
    Signed in as <?= htmlspecialchars($userLabel) ?>
  </div>
  <?php endif; ?>

  <div class="actions">
    <a href="<?= htmlspecialchars($homeUrl) ?>" class="btn-primary">Back to Dashboard</a>
    <?php if (!$isLoggedIn): ?>
      <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn-outline">Sign In</a>
    <?php else: ?>
      <a href="javascript:history.back()" class="btn-outline">Go Back</a>
    <?php endif; ?>
  </div>

  <div class="help-text">
    Need access? <a href="mailto:support@optmstech.in">Contact support</a>
  </div>
</div>

</body>
</html>
