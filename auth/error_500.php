<?php
// ================================================================
//  OPTMS Invoice Manager — 500 Server Error
// ================================================================
http_response_code(500);
$companyName = 'OPTMS Invoice Manager';
$homeUrl = '/';
$refCode = strtoupper(substr(md5(uniqid('', true)), 0, 8));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Something Went Wrong — <?= htmlspecialchars($companyName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<style>
:root{
  --surface:#f8faf6; --surface-container-lowest:#ffffff; --surface-container-low:#f2f4f1;
  --on-surface:#191c1b; --on-surface-variant:#3f4944;
  --outline:#6f7974; --outline-variant:#bfc9c3;
  --error:#ba1a1a; --error-container:#ffdad6; --on-error-container:#93000a;
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
.blob-1{width:420px;height:420px;background:#ffb4a9;top:-140px;right:-140px}
.blob-2{width:300px;height:300px;background:#e1e3e0;bottom:-110px;left:-90px;opacity:.6}

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
  letter-spacing:.03em;color:var(--on-error-container);
  background:var(--error-container);
  padding:5px 12px;border-radius:999px;
  margin-bottom:22px;
}

.icon-circle{
  width:72px;height:72px;border-radius:50%;
  background:var(--error-container);
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 24px;
}
.icon-circle svg{width:32px;height:32px;stroke:var(--error);stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}

h1{
  font-family:'Hanken Grotesk',sans-serif;
  font-size:24px;font-weight:600;letter-spacing:-0.01em;
  color:var(--on-surface);margin-bottom:10px;
}
p.desc{
  font-size:14px;line-height:22px;color:var(--on-surface-variant);
  margin-bottom:28px;
}

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

.ref-row{
  margin-top:22px;padding-top:18px;border-top:1px solid var(--outline-variant);
  font-size:12px;color:var(--outline);
  display:flex;align-items:center;justify-content:center;gap:6px;
}
.ref-row code{
  font-family:'JetBrains Mono',monospace;font-size:11.5px;color:var(--on-surface-variant);
  background:var(--surface-container-low);padding:2px 7px;border-radius:5px;
}
</style>
</head>
<body>

<div class="blob blob-1"></div>
<div class="blob blob-2"></div>

<div class="card">
  <div class="status-badge">ERROR 500</div>

  <div class="icon-circle">
    <svg viewBox="0 0 24 24"><path d="M12 3l10 18H2L12 3z"/><path d="M12 10v4M12 17.5h.01"/></svg>
  </div>

  <h1>Something went wrong</h1>
  <p class="desc">We're experiencing an unexpected issue on our end. Our team has been notified and is already looking into it.</p>

  <div class="actions">
    <a href="javascript:location.reload()" class="btn-primary">Try Again</a>
    <a href="<?= htmlspecialchars($homeUrl) ?>" class="btn-outline">Go Home</a>
  </div>

  <div class="ref-row">
    Reference: <code><?= htmlspecialchars($refCode) ?></code>
  </div>
</div>

</body>
</html>
