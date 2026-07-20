<?php
// ================================================================
//  OPTMS Invoice Manager — Maintenance Mode
// ================================================================
http_response_code(503);
header('Retry-After: 1800'); // 30 minutes — adjust as needed

$companyName = 'OPTMS Invoice Manager';
if (file_exists(__DIR__ . '/../includes/auth.php')) {
    require_once __DIR__ . '/../includes/auth.php';
    if (function_exists('getSetting')) {
        $companyName = getSetting('company_name', $companyName);
    }
}

// Optional: set an estimated return time to show a live countdown.
// Leave as null to hide the countdown block entirely.
$estimatedReturn = null; // e.g. new DateTime('2026-07-08 22:30:00');
$estimatedReturnTs = $estimatedReturn instanceof DateTime ? $estimatedReturn->getTimestamp() * 1000 : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="120">
<title>Under Maintenance — <?= htmlspecialchars($companyName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<style>
:root{
  --surface:#f8faf6; --surface-container-lowest:#ffffff; --surface-container-low:#f2f4f1;
  --on-surface:#191c1b; --on-surface-variant:#3f4944;
  --outline:#6f7974; --outline-variant:#bfc9c3;
  --secondary:#765b04; --secondary-container:#fed97c; --on-secondary-container:#785d07;
  --primary-container:#0f5a46;
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
.blob-1{width:440px;height:440px;background:#ffdf94;top:-160px;right:-140px}
.blob-2{width:300px;height:300px;background:#acf1d6;bottom:-110px;left:-100px;opacity:.4}

.card{
  position:relative;z-index:1;
  background:var(--surface-container-lowest);
  border:1px solid var(--outline-variant);
  border-radius:18px;
  box-shadow:0 12px 32px rgba(0,0,0,.08);
  width:100%;max-width:460px;
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
.status-badge .dot{
  width:6px;height:6px;border-radius:50%;background:var(--on-secondary-container);
  animation:blink 1.4s ease-in-out infinite;
}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

.icon-circle{
  width:76px;height:76px;border-radius:50%;
  background:var(--secondary-container);
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 24px;
  position:relative;
}
.icon-circle svg{width:32px;height:32px;stroke:var(--on-secondary-container);stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round;
  animation:turn 3.5s linear infinite;transform-origin:center;
}
@keyframes turn{to{transform:rotate(360deg)}}

h1{
  font-family:'Hanken Grotesk',sans-serif;
  font-size:24px;font-weight:600;letter-spacing:-0.01em;
  color:var(--on-surface);margin-bottom:10px;
}
p.desc{
  font-size:14px;line-height:22px;color:var(--on-surface-variant);
  margin-bottom:28px;
}

.progress-track{
  height:6px;width:100%;background:var(--surface-container-low);
  border-radius:999px;overflow:hidden;margin-bottom:26px;
}
.progress-fill{
  height:100%;width:35%;border-radius:999px;
  background:var(--primary-container);
  animation:loading 2.2s ease-in-out infinite;
}
@keyframes loading{
  0%{width:8%;margin-left:0}
  50%{width:55%;margin-left:10%}
  100%{width:8%;margin-left:92%}
}

#countdown{
  display:none;
  font-family:'JetBrains Mono',monospace;
  font-size:13px;color:var(--on-surface-variant);
  background:var(--surface-container-low);
  border-radius:10px;padding:12px 16px;
  margin-bottom:22px;
}
#countdown b{color:var(--on-surface);font-weight:600}

.btn-outline{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  width:100%;height:46px;border-radius:8px;
  background:#fff;color:#1c1c1c;border:1.5px solid #e5e7eb;
  font-family:'Inter',sans-serif;font-size:13.5px;font-weight:600;
  cursor:pointer;text-decoration:none;
  transition:border-color .15s,transform .1s;
}
.btn-outline:hover{border-color:var(--outline)}
.btn-outline:active{transform:scale(.98)}
.btn-outline svg{width:15px;height:15px;stroke:currentColor;stroke-width:2;fill:none}

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
  <div class="status-badge"><span class="dot"></span> UNDER MAINTENANCE</div>

  <div class="icon-circle">
    <svg viewBox="0 0 24 24"><path d="M14.7 6.3a4 4 0 0 1-5.4 5.4L4 17l3 3 5.3-5.3a4 4 0 0 1 5.4-5.4l-2.6 2.6-2-2 2.6-2.6z"/></svg>
  </div>

  <h1>Scheduled maintenance</h1>
  <p class="desc">We're currently performing scheduled maintenance to improve your experience. We'll be back online shortly — thanks for your patience.</p>

  <div class="progress-track"><div class="progress-fill"></div></div>

  <div id="countdown">Estimated return: <b id="countdown-value">—</b></div>

  <a href="javascript:location.reload()" class="btn-outline">
    <svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>
    Check Again
  </a>

  <div class="help-text">
    Urgent issue? <a href="mailto:support@optmstech.in">Contact support</a>
  </div>
</div>

<?php if ($estimatedReturnTs): ?>
<script>
const targetTs = <?= (int)$estimatedReturnTs ?>;
const box = document.getElementById('countdown');
const val = document.getElementById('countdown-value');
box.style.display = 'block';

function tick(){
  const diff = targetTs - Date.now();
  if (diff <= 0){ val.textContent = 'Any moment now'; return; }
  const h = Math.floor(diff / 3600000);
  const m = Math.floor((diff % 3600000) / 60000);
  const s = Math.floor((diff % 60000) / 1000);
  val.textContent = (h > 0 ? h + 'h ' : '') + m + 'm ' + s + 's';
}
tick();
setInterval(tick, 1000);
</script>
<?php endif; ?>

</body>
</html>
