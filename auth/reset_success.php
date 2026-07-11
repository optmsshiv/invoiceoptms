<?php
// ================================================================
//  OPTMS Invoice Manager — Password Reset Success
// ================================================================
$loginUrl = 'login.php';
$companyName = 'OPTMS Invoice Manager';
if (file_exists(__DIR__ . '/../includes/auth.php')) {
    require_once __DIR__ . '/../includes/auth.php';
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
<title>Password Reset — <?= htmlspecialchars($companyName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<style>
:root{
  --surface:#f8faf6; --surface-container-lowest:#ffffff; --surface-container-low:#f2f4f1;
  --surface-container:#eceeeb; --on-surface:#191c1b; --on-surface-variant:#3f4944;
  --outline:#6f7974; --outline-variant:#bfc9c3;
  --primary:#004131; --on-primary:#ffffff; --primary-container:#0f5a46; --on-primary-container:#8bcfb6;
  --primary-fixed:#acf1d6;
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
.blob{position:absolute;border-radius:50%;filter:blur(60px);opacity:.35;pointer-events:none;z-index:0}
.blob-1{width:420px;height:420px;background:var(--primary-fixed);top:-140px;left:-140px}
.blob-2{width:320px;height:320px;background:#fed97c;bottom:-120px;right:-100px;opacity:.25}

.card{
  position:relative;z-index:1;
  background:var(--surface-container-lowest);
  border:1px solid var(--outline-variant);
  border-radius:18px;
  box-shadow:0 12px 32px rgba(0,0,0,.08);
  width:100%;max-width:420px;
  padding:44px 40px 36px;
  text-align:center;
  animation:fadeUp .45s ease;
}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}

.icon-circle{
  width:72px;height:72px;border-radius:50%;
  background:#e8f6ef;
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 24px;
  animation:pop .5s cubic-bezier(.34,1.56,.64,1) .1s both;
}
@keyframes pop{from{transform:scale(.6);opacity:0}to{transform:scale(1);opacity:1}}
.icon-circle svg{width:32px;height:32px;stroke:var(--primary-container);stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}

h1{
  font-family:'Hanken Grotesk',sans-serif;
  font-size:24px;font-weight:600;letter-spacing:-0.01em;
  color:var(--on-surface);margin-bottom:10px;
}
p.desc{
  font-size:14px;line-height:22px;color:var(--on-surface-variant);
  margin-bottom:30px;
}

.btn-primary{
  display:flex;align-items:center;justify-content:center;gap:8px;
  width:100%;height:48px;
  background:#0f5a46;color:var(--on-primary);
  border:none;border-radius:8px;
  font-family:'Inter',sans-serif;font-size:14px;font-weight:600;
  cursor:pointer;text-decoration:none;
  transition:background .15s,transform .1s;
}
.btn-primary:hover{background:#0c4a3a}
.btn-primary:active{transform:scale(.98)}

.tip{
  margin-top:24px;padding:14px 16px;
  background:var(--surface-container-low);
  border-radius:10px;
  display:flex;align-items:flex-start;gap:10px;
  text-align:left;
}
.tip svg{width:16px;height:16px;stroke:var(--on-surface-variant);stroke-width:2;fill:none;flex-shrink:0;margin-top:2px}
.tip span{font-size:12.5px;line-height:18px;color:var(--on-surface-variant)}

.footer-note{
  margin-top:26px;font-size:12px;color:var(--outline);
}
</style>
</head>
<body>

<div class="blob blob-1"></div>
<div class="blob blob-2"></div>

<div class="card">
  <div class="icon-circle">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9.5"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>
  </div>

  <h1>Password reset successful</h1>
  <p class="desc">Your password has been updated. You can now sign in to your account using your new password.</p>

  <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn-primary">
    Continue to Login
  </a>

  <div class="tip">
    <svg viewBox="0 0 24 24"><path d="M12 9v4M12 16.5h.01"/><circle cx="12" cy="12" r="9.5"/></svg>
    <span>For your security, you've been signed out of all other active sessions.</span>
  </div>

  <div class="footer-note"><?= htmlspecialchars($companyName) ?></div>
</div>

</body>
</html>
