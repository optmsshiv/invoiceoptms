<?php
ini_set('display_errors', 1);
   error_reporting(E_ALL);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/password_reset.php';
startSession();
if (!empty($_SESSION['user_id'])) { header('Location: ' . APP_URL . '/index.php'); exit; }

$msg = ''; $error = '';

// ── AJAX request (used by the fetch-based form submit below) ──
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAjax) {
    header('Content-Type: application/json');
    $email = trim($_POST['email'] ?? '');
    if (!$email) {
        echo json_encode(['success' => false, 'error' => 'Please enter your email address.']);
        exit;
    }
    // Always show success to prevent email enumeration
    $ajaxMsg = 'If that email is registered, a reset link has been sent. Please check your inbox.';
    $user = findUserForPasswordReset($email);
    if ($user) {
        issuePasswordResetAndEmail($user);
    }
    echo json_encode(['success' => true, 'message' => $ajaxMsg]);
    exit;
}

// ── No-JS fallback: plain form POST + full page reload ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!$email) {
        $error = 'Please enter your email address.';
    } else {
        // Always show success to prevent email enumeration
        $msg = 'If that email is registered, a reset link has been sent. Please check your inbox.';
        $user = findUserForPasswordReset($email);
        if ($user) {
            issuePasswordResetAndEmail($user);
        }
    }
}
$companyName = getSetting('company_name', 'OPTMS Tech');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — <?= htmlspecialchars($companyName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:'Public Sans',sans-serif}

body{
  min-height:100vh;
  background:#E8EFEC;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:24px 20px;
  position:relative;
  overflow:hidden;
}

/* ── Top loading bar ── */
#loading-bar{
  position:fixed;
  top:0;left:0;
  height:3px;
  width:0%;
  background:linear-gradient(90deg,#0F6E56,#4DB6AC,#0F6E56);
  background-size:200% 100%;
  border-radius:0 2px 2px 0;
  z-index:9999;
  transition:width .15s ease;
  display:none;
  box-shadow:0 0 8px rgba(15,110,86,.5);
}
#loading-bar.running{
  display:block;
  animation:barShimmer 1s linear infinite;
}
@keyframes barShimmer{
  0%{background-position:100% 0}
  100%{background-position:-100% 0}
}

/* ── Geo background canvas ── */
#geo-bg{
  position:fixed;inset:0;
  width:100%;height:100%;
  pointer-events:none;z-index:0;
}

/* ── Card ── */
.wrap{
  display:flex;
  border-radius:20px;
  overflow:hidden;
  width:100%;max-width:720px;
  min-height:520px;
  position:relative;z-index:1;
  box-shadow:0 12px 56px rgba(8,80,65,.13);
  animation:fadeUp .45s ease;
  transition:opacity .3s ease, transform .3s ease;
}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}

/* ── Card dims slightly while a submission is in flight ── */
.wrap.submitting{opacity:.94;transform:scale(.994)}

/* ── Card pulses green on a real, confirmed success ── */
@keyframes successPulse{
  0%{box-shadow:0 12px 56px rgba(8,80,65,.13)}
  50%{box-shadow:0 12px 66px rgba(29,158,117,.4)}
  100%{box-shadow:0 12px 56px rgba(8,80,65,.13)}
}
.wrap.success-pulse{animation:successPulse .55s ease}

/* ── Staggered entrance for right-panel fields ── */
@keyframes fieldIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.stagger{opacity:0;animation:fieldIn .45s ease forwards}

/* ── Icon micro-bounce on input focus ── */
@keyframes iconBounce{0%{transform:scale(1)}45%{transform:scale(1.28)}100%{transform:scale(1)}}
.iw i.ic.bounce{animation:iconBounce .32s ease}

/* ══ LEFT PANEL ══ */
.left{
  width:260px;flex-shrink:0;
  background:#085041;
  padding:40px 30px;
  display:flex;flex-direction:column;justify-content:space-between;
  position:relative;overflow:hidden;
}
.left-geo{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}
.left-content,.left-bottom{position:relative;z-index:1}

.left-icon{
  width:48px;height:48px;
  background:rgba(29,158,117,.22);
  border-radius:13px;
  display:flex;align-items:center;justify-content:center;
  margin-bottom:28px;
  border:1px solid rgba(29,158,117,.38);
}
.left-icon i{font-size:22px;color:#9FE1CB}
.left-title{font-size:17px;font-weight:700;color:#E1F5EE;line-height:1.4;margin-bottom:9px}
.left-sub{font-size:12px;color:#5DCAA5;line-height:1.7}
.feature-list{margin-top:28px;display:flex;flex-direction:column;gap:14px}
.feat{display:flex;align-items:flex-start;gap:10px;opacity:0;animation:fieldIn .45s ease forwards}
.feat-dot{
  width:22px;height:22px;border-radius:50%;
  background:rgba(29,158,117,.15);
  border:1px solid rgba(29,158,117,.32);
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;margin-top:1px;
}
.feat-dot i{font-size:10px;color:#9FE1CB}
.feat-text{font-size:12.5px;color:#9FE1CB;line-height:1.5}
.left-bottom-text{font-size:11px;color:rgba(93,202,165,.38);line-height:1.8}

/* ══ RIGHT PANEL ══ */
.right{
  flex:1;background:#fff;
  padding:46px 40px;
  display:flex;flex-direction:column;justify-content:center;
}
.welcome-label{font-size:11.5px;color:#6B7280;letter-spacing:.55px;text-transform:uppercase;margin-bottom:5px}
.welcome-title{font-size:22px;font-weight:700;color:#111827;margin-bottom:8px}
.welcome-desc{font-size:13px;color:#9CA3AF;line-height:1.6;margin-bottom:26px}

.err{
  background:#FEF2F2;border:1px solid #FECACA;
  border-radius:9px;padding:10px 14px;
  font-size:13px;color:#B91C1C;
  margin-bottom:16px;
  display:flex;align-items:center;gap:8px;
}
.success-box{
  text-align:center;
  padding:6px 0 4px;
}
.success-icon{
  width:60px;height:60px;border-radius:50%;
  background:#EAFBF4;border:1.5px solid #A7E9D3;
  display:flex;align-items:center;justify-content:center;
  margin:4px auto 20px;
}
.success-icon i{font-size:24px;color:#0F6E56}
.success-title{font-size:16px;font-weight:700;color:#111827;margin-bottom:8px}
.success-text{font-size:13px;color:#6B7280;line-height:1.65;margin-bottom:26px}
.success-text b{color:#111827;font-weight:600}

.field{margin-bottom:15px}
.fl{font-size:11.5px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.45px;margin-bottom:5px;display:block}
.iw{position:relative;display:flex;align-items:center}
.iw i.ic{position:absolute;left:12px;font-size:14px;color:#9CA3AF;pointer-events:none}
.iw input{
  width:100%;padding:11px 12px 11px 37px;
  border:1.5px solid #E5E7EB;border-radius:9px;
  font-family:inherit;font-size:14px;color:#111;
  background:#F9FAFB;outline:none;
  transition:border-color .18s,box-shadow .18s,background .18s;
}
.iw input:focus{border-color:#1D9E75;box-shadow:0 0 0 3px rgba(29,158,117,.13);background:#fff}
.iw input::placeholder{color:#C4C4C4}

/* ── Buttons ── */
.btn-primary{
  width:100%;padding:12px;
  background:#085041;color:#E1F5EE;
  border:none;border-radius:9px;
  font-family:inherit;font-size:14px;font-weight:700;
  cursor:pointer;letter-spacing:.2px;
  display:flex;align-items:center;justify-content:center;gap:9px;
  transition:background .16s,transform .1s,box-shadow .16s;
  margin-top:6px;
}
.btn-primary:hover{background:#0F6E56;box-shadow:0 4px 16px rgba(8,80,65,.22)}
.btn-primary:active{transform:scale(.98)}
.btn-primary:disabled{opacity:.75;cursor:not-allowed;transform:none}

.btn-outline{
  width:100%;padding:11px;
  background:#F9FAFB;border:1.5px solid #E5E7EB;
  border-radius:9px;font-family:inherit;
  font-size:13.5px;font-weight:600;color:#374151;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:8px;
  transition:background .15s,border-color .15s;
  text-decoration:none;
}
.btn-outline:hover{background:#F3F4F6;border-color:#D1D5DB}

/* spinner inside button */
.btn-spinner{
  display:inline-block;
  animation:spinIcon .65s linear infinite;
}
@keyframes spinIcon{to{transform:rotate(360deg)}}

.back-link{
  display:flex;align-items:center;justify-content:center;gap:7px;
  margin-top:22px;font-size:13px;color:#0F6E56;text-decoration:none;font-weight:600;
}
.back-link:hover{text-decoration:underline}

/* ── Shake ── */
.shake{animation:shake .36s ease}
@keyframes shake{
  0%,100%{transform:translateX(0)}
  20%{transform:translateX(-6px)}40%{transform:translateX(6px)}
  60%{transform:translateX(-4px)}80%{transform:translateX(4px)}
}

/* ── Responsive ── */
@media(max-width:580px){
  .wrap{flex-direction:column;min-height:auto}
  .left{width:100%;padding:26px 24px}
  .feature-list{display:none}
  .right{padding:30px 24px}
}
</style>
</head>
<body>

<!-- Top loading bar -->
<div id="loading-bar"></div>

<!-- Geometric background -->
<canvas id="geo-bg"></canvas>

<div class="wrap" id="fp-card">

  <!-- ══ LEFT PANEL ══ -->
  <div class="left">
    <svg class="left-geo" viewBox="0 0 260 520" preserveAspectRatio="xMidYMid slice"
         xmlns="http://www.w3.org/2000/svg" aria-hidden="true">

      <!-- dot grid -->
      <pattern id="dots" x="0" y="0" width="24" height="24" patternUnits="userSpaceOnUse">
        <circle cx="12" cy="12" r="1" fill="rgba(29,158,117,0.18)"/>
      </pattern>
      <rect width="260" height="520" fill="url(#dots)"/>

      <!-- concentric rings top-right -->
      <circle cx="235" cy="55"  r="80" fill="none" stroke="rgba(29,158,117,0.10)" stroke-width="1"/>
      <circle cx="235" cy="55"  r="54" fill="none" stroke="rgba(29,158,117,0.08)" stroke-width="1"/>
      <circle cx="235" cy="55"  r="30" fill="none" stroke="rgba(29,158,117,0.06)" stroke-width="1"/>

      <!-- concentric rings bottom-left -->
      <circle cx="25"  cy="470" r="60" fill="none" stroke="rgba(29,158,117,0.09)" stroke-width="1"/>
      <circle cx="25"  cy="470" r="36" fill="none" stroke="rgba(29,158,117,0.07)" stroke-width="1"/>

      <!-- diagonal lines -->
      <line x1="0"   y1="150" x2="260" y2="340" stroke="rgba(29,158,117,0.055)" stroke-width="1"/>
      <line x1="0"   y1="190" x2="260" y2="380" stroke="rgba(29,158,117,0.035)" stroke-width="1"/>
      <line x1="260" y1="130" x2="0"   y2="320" stroke="rgba(29,158,117,0.045)" stroke-width="1"/>

      <!-- lock/shield motif — bottom right -->
      <g transform="translate(190,418) rotate(-10)" opacity="0.22">
        <rect x="-18" y="-6" width="36" height="30" rx="4"
              fill="rgba(29,158,117,0.08)" stroke="rgba(29,158,117,0.7)" stroke-width="1.2"/>
        <path d="M -11 -6 v -10 a 11 11 0 0 1 22 0 v 10" fill="none" stroke="rgba(29,158,117,0.6)" stroke-width="1.4"/>
        <circle cx="0" cy="9" r="3" fill="rgba(29,158,117,0.6)"/>
      </g>

      <!-- key motif — mid left -->
      <g transform="translate(34,240) rotate(18)" opacity="0.16">
        <circle cx="-10" cy="0" r="9" fill="none" stroke="rgba(29,158,117,0.7)" stroke-width="1.4"/>
        <line x1="-1" y1="0" x2="20" y2="0" stroke="rgba(29,158,117,0.6)" stroke-width="1.4"/>
        <line x1="12" y1="0" x2="12" y2="7" stroke="rgba(29,158,117,0.6)" stroke-width="1.4"/>
        <line x1="18" y1="0" x2="18" y2="6" stroke="rgba(29,158,117,0.6)" stroke-width="1.4"/>
      </g>

      <!-- small shield — top centre -->
      <g transform="translate(120,30) rotate(-4)" opacity="0.14">
        <path d="M0,-16 L14,-11 V4 C14,13 7,18 0,21 C-7,18 -14,13 -14,4 V-11 Z"
              fill="none" stroke="rgba(29,158,117,0.8)" stroke-width="1.2"/>
        <path d="M-5,2 L-1,7 L6,-4" fill="none" stroke="rgba(29,158,117,0.7)" stroke-width="1.2"/>
      </g>

      <!-- dashed stamp rings -->
      <circle cx="200" cy="375" r="24"
              fill="none" stroke="rgba(29,158,117,0.10)" stroke-width="1" stroke-dasharray="4 3"/>
      <circle cx="60"  cy="130" r="16"
              fill="none" stroke="rgba(29,158,117,0.09)" stroke-width="1" stroke-dasharray="3 3"/>

      <!-- triangle accents -->
      <polygon points="52,88 66,114 38,114"
               fill="none" stroke="rgba(29,158,117,0.09)" stroke-width="1"/>
      <polygon points="210,290 222,310 198,310"
               fill="none" stroke="rgba(29,158,117,0.08)" stroke-width="1"/>

      <!-- coin rings -->
      <circle cx="58"  cy="392" r="11" fill="none" stroke="rgba(29,158,117,0.11)" stroke-width="1"/>
      <circle cx="58"  cy="392" r="5"  fill="none" stroke="rgba(29,158,117,0.07)" stroke-width="0.8"/>
      <circle cx="220" cy="170" r="7"  fill="none" stroke="rgba(29,158,117,0.09)" stroke-width="1"/>
      <circle cx="220" cy="170" r="3"  fill="none" stroke="rgba(29,158,117,0.06)" stroke-width="0.7"/>

    </svg>

    <div class="left-content">
      <div class="left-icon"><i class="fas fa-key"></i></div>
      <div class="left-title"><?= htmlspecialchars($companyName) ?><br>Account Recovery</div>
      <div class="left-sub">We'll help you get back into your account securely.</div>
      <div class="feature-list">
        <div class="feat" style="animation-delay:.55s">
          <div class="feat-dot"><i class="fas fa-check"></i></div>
          <div class="feat-text">Secure email verification</div>
        </div>
        <div class="feat" style="animation-delay:.65s">
          <div class="feat-dot"><i class="fas fa-check"></i></div>
          <div class="feat-text">Reset link expires for safety</div>
        </div>
        <div class="feat" style="animation-delay:.75s">
          <div class="feat-dot"><i class="fas fa-check"></i></div>
          <div class="feat-text">Your data stays protected</div>
        </div>
      </div>
    </div>

    <div class="left-bottom">
      <div class="left-bottom-text">
        <?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : $companyName) ?><?= defined('APP_VERSION') ? ' v' . APP_VERSION : '' ?><br>
        &copy; <?= date('Y') ?> <?= htmlspecialchars($companyName) ?>
      </div>
    </div>
  </div>

  <!-- ══ RIGHT PANEL ══ -->
  <div class="right">

    <div id="fp-view-success" class="success-box" style="<?= $msg ? '' : 'display:none' ?>">
      <div class="success-icon"><i class="fas fa-paper-plane"></i></div>
      <div class="success-title">Check your inbox</div>
      <div class="success-text" id="success-text"><?= htmlspecialchars($msg) ?></div>
      <a href="login.php" class="btn-outline"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>

    <div id="fp-view-form" style="<?= $msg ? 'display:none' : '' ?>">
      <div class="welcome-label stagger" style="animation-delay:.05s">Account Recovery</div>
      <div class="welcome-title stagger" style="animation-delay:.1s">Forgot password?</div>
      <div class="welcome-desc stagger" style="animation-delay:.15s">Enter the email linked to your account and we'll send you a reset link.</div>

      <div class="err stagger" id="err-box" style="animation-delay:.15s<?= $error ? '' : ';display:none' ?>">
        <i class="fas fa-exclamation-circle"></i>
        <span class="err-text"><?= htmlspecialchars($error) ?></span>
      </div>

      <form method="POST" autocomplete="on" id="fp-form">
        <div class="field stagger" style="animation-delay:.2s">
          <label class="fl" for="email">Email Address</label>
          <div class="iw">
            <i class="fas fa-envelope ic"></i>
            <input type="email" id="email" name="email"
                   placeholder="admin@optmstech.in"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   required autofocus autocomplete="email">
          </div>
        </div>

        <button type="submit" class="btn-primary stagger" id="send-btn" style="animation-delay:.27s">
          <i class="fas fa-paper-plane" id="btn-icon"></i>
          <span id="btn-label">Send Reset Link</span>
        </button>
      </form>

      <a href="login.php" class="back-link stagger" style="animation-delay:.33s"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>

  </div>

</div><!-- /wrap -->

<script>
/* ════════════════════════
   Loading bar controller
════════════════════════ */
const LoadingBar = {
  el: document.getElementById('loading-bar'),
  timer: null,
  current: 0,

  start(){
    this.el.classList.add('running');
    this.current = 0;
    this._set(0);
    this._animate(30, 200);
    setTimeout(() => this._animate(70, 800), 220);
    setTimeout(() => this._animate(85, 1200), 1050);
  },

  finish(){
    this._set(100);
    setTimeout(() => {
      this.el.style.opacity = '0';
      setTimeout(() => {
        this.el.classList.remove('running');
        this.el.style.opacity = '1';
        this._set(0);
      }, 300);
    }, 200);
  },

  _set(pct){
    this.current = pct;
    this.el.style.width = pct + '%';
  },

  _animate(target, duration){
    const start = this.current;
    const diff  = target - start;
    const began = performance.now();
    const step  = (now) => {
      const elapsed = now - began;
      const progress = Math.min(elapsed / duration, 1);
      this._set(start + diff * progress);
      if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }
};

/* ════════════════════════
   Form submit handler (AJAX)
════════════════════════ */
const fpForm = document.getElementById('fp-form');
if (fpForm) {
  fpForm.addEventListener('submit', function(e){
    e.preventDefault();

    const form   = e.target;
    const btn    = document.getElementById('send-btn');
    const icon   = document.getElementById('btn-icon');
    const label  = document.getElementById('btn-label');
    const card   = document.getElementById('fp-card');
    const errBox = document.getElementById('err-box');
    const errTxt = errBox ? errBox.querySelector('.err-text') : null;
    const viewForm    = document.getElementById('fp-view-form');
    const viewSuccess = document.getElementById('fp-view-success');
    const successTxt  = document.getElementById('success-text');

    function showError(msg){
      btn.disabled       = false;
      icon.className     = 'fas fa-paper-plane';
      label.textContent  = 'Send Reset Link';
      card.classList.remove('submitting');
      if (errBox){
        if (errTxt) errTxt.textContent = msg;
        errBox.style.display = 'flex';
      }
      card.classList.add('shake');
      card.addEventListener('animationend', () => card.classList.remove('shake'), {once:true});
    }

    LoadingBar.start();
    btn.disabled = true;
    icon.className = 'fas fa-circle-notch btn-spinner';
    label.textContent = 'Sending…';
    card.classList.add('submitting');
    card.classList.remove('shake');
    if (errBox) errBox.style.display = 'none';

    fetch(form.getAttribute('action') || window.location.href, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(form)
    })
      .then(res => res.json())
      .then(data => {
        LoadingBar.finish();
        if (data.success) {
          if (successTxt) successTxt.textContent = data.message || '';
          card.classList.remove('submitting');
          viewForm.style.display    = 'none';
          viewSuccess.style.display = '';
          card.classList.add('success-pulse');
        } else {
          showError(data.error || 'Something went wrong. Please try again.');
        }
      })
      .catch(() => {
        LoadingBar.finish();
        showError('Network error. Please check your connection and try again.');
      });
  });
}

/* ════════════════════════
   Icon micro-bounce on focus
════════════════════════ */
document.querySelectorAll('.iw input').forEach(input => {
  const icon = input.parentElement.querySelector('i.ic');
  if (!icon) return;
  input.addEventListener('focus', () => {
    icon.classList.remove('bounce');
    void icon.offsetWidth;
    icon.classList.add('bounce');
  });
  icon.addEventListener('animationend', () => icon.classList.remove('bounce'));
});

/* ════════════════════════
   Shake on PHP error
════════════════════════ */
<?php if ($error): ?>
(function(){
  LoadingBar.finish();
  const c = document.getElementById('fp-card');
  c.classList.add('shake');
  c.addEventListener('animationend', () => c.classList.remove('shake'), {once:true});
})();
<?php endif; ?>

/* ════════════════════════
   Geometric background
════════════════════════ */
(function(){
  const c = document.getElementById('geo-bg');
  const ctx = c.getContext('2d');
  let W = 0, H = 0;

  // Base positions/sizes for the floating locks (unpacked once per resize)
  const lockBase = [
    [.07,.12, 46,-18,.05,  0],
    [.88,.10, 38, 15,.046, 1],
    [.92,.76, 52, -9,.05,  2],
    [.05,.80, 34, 24,.042, 3],
    [.50,.05, 30,  7,.038, 4],
    [.94,.42, 26,-14,.032, 5],
    [.20,.92, 24, 32,.028, 6],
    [.74,.94, 30, -6,.034, 7],
  ];
  const coinBase = [
    [.28,.10,9],[.68,.20,7],[.14,.55,6],
    [.83,.55,8],[.46,.96,6],[.60,.04,5],
  ];
  const stampBase = [[.35,.18,22],[.65,.83,18],[.91,.50,16]];

  function resize(){
    W = c.width  = window.innerWidth;
    H = c.height = window.innerHeight;
  }

  const t = a => `rgba(8,80,65,${a})`;

  function drawLock(cx, cy, s, deg, alpha){
      ctx.save();
      ctx.translate(cx, cy);
      ctx.rotate(deg * Math.PI / 180);
      const bw = s, bh = s * 0.8, r = 2;
      ctx.beginPath();
      ctx.roundRect(-bw/2, -bh/4, bw, bh, r);
      ctx.fillStyle = t(alpha);
      ctx.fill();
      ctx.strokeStyle = t(alpha + 0.04);
      ctx.lineWidth = 1;
      ctx.stroke();
      // shackle
      ctx.beginPath();
      ctx.arc(0, -bh/4, bw * 0.3, Math.PI, 0);
      ctx.strokeStyle = t(alpha + 0.05);
      ctx.lineWidth = s * 0.09;
      ctx.stroke();
      // keyhole
      ctx.beginPath();
      ctx.arc(0, bh/4, s * 0.08, 0, Math.PI * 2);
      ctx.fillStyle = t(alpha + 0.08);
      ctx.fill();
      ctx.restore();
    }

  function render(time){
    ctx.clearRect(0, 0, W, H);
    const sec = time / 1000;

    // Floating locks — each drifts slowly on its own slightly
    // different sine cycle so they never move in unison
    lockBase.forEach(([bx, by, s, deg, alpha, i]) => {
      const speed = 0.15 + (i % 4) * 0.03;
      const driftX = Math.sin(sec * speed + i) * 9;
      const driftY = Math.cos(sec * speed * 0.8 + i) * 7;
      const driftDeg = Math.sin(sec * speed * 0.6 + i) * 2.5;
      try { drawLock(W*bx + driftX, H*by + driftY, s, deg + driftDeg, alpha); } catch(e){}
    });

    // Coin rings — very light drift
    coinBase.forEach(([bx, by, r], i) => {
      const x = W*bx + Math.sin(sec * 0.12 + i) * 5;
      const y = H*by + Math.cos(sec * 0.10 + i) * 5;
      ctx.beginPath(); ctx.arc(x,y,r,0,Math.PI*2);
      ctx.strokeStyle = t(.07); ctx.lineWidth = 1.2; ctx.stroke();
      ctx.beginPath(); ctx.arc(x,y,r*.5,0,Math.PI*2);
      ctx.strokeStyle = t(.05); ctx.lineWidth = .8; ctx.stroke();
    });

    // Dashed stamp rings — fixed position, slow rotation of the dash pattern
    stampBase.forEach(([bx, by, r], i) => {
      ctx.beginPath(); ctx.arc(W*bx, H*by, r, 0, Math.PI*2);
      ctx.strokeStyle = t(.06); ctx.lineWidth = 1;
      ctx.setLineDash([3,3]);
      ctx.lineDashOffset = sec * (4 + i * 2);
      ctx.stroke(); ctx.setLineDash([]);
    });

    // Diagonal lines — static
    [[0,H*.3,W*.45,0],[W*.55,H,W,H*.3],[0,H*.7,W*.3,H]].forEach(([x1,y1,x2,y2]) => {
      ctx.beginPath(); ctx.moveTo(x1,y1); ctx.lineTo(x2,y2);
      ctx.strokeStyle = t(.04); ctx.lineWidth = 1; ctx.stroke();
    });

    requestAnimationFrame(render);
  }

  resize();
  window.addEventListener('resize', resize);
  requestAnimationFrame(render);
})();
</script>
</body>
</html>
