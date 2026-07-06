<?php
// ================================================================
//  OPTMS Invoice Manager — Login Page (Final + Loading Bar)
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();

if (!empty($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) {
        $error = 'Please enter both email and password.';
    } else {
        $user = attemptLogin($email, $password);
        if ($user) {
            header('Location: /');
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
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
<title>Sign In — <?= htmlspecialchars($companyName) ?> Invoice Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}

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
.feat{display:flex;align-items:flex-start;gap:10px}
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
.welcome-title{font-size:22px;font-weight:700;color:#111827;margin-bottom:30px}

.err{
  background:#FEF2F2;border:1px solid #FECACA;
  border-radius:9px;padding:10px 14px;
  font-size:13px;color:#B91C1C;
  margin-bottom:16px;
  display:flex;align-items:center;gap:8px;
}

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
.eye-btn{position:absolute;right:11px;background:none;border:none;cursor:pointer;color:#9CA3AF;font-size:13px;padding:4px;display:flex;align-items:center}
.eye-btn:hover{color:#374151}

.meta{display:flex;align-items:center;justify-content:space-between;margin:14px 0 22px;font-size:13px}
.rem{display:flex;align-items:center;gap:7px;cursor:pointer;color:#6B7280}
.rem input{accent-color:#1D9E75;width:14px;height:14px;cursor:pointer}
.fp{color:#0F6E56;text-decoration:none;font-weight:600;font-size:13px}
.fp:hover{text-decoration:underline}

/* ── Sign-in button ── */
.btn-login{
  width:100%;padding:12px;
  background:#085041;color:#E1F5EE;
  border:none;border-radius:9px;
  font-family:inherit;font-size:14px;font-weight:700;
  cursor:pointer;letter-spacing:.2px;
  display:flex;align-items:center;justify-content:center;gap:9px;
  transition:background .16s,transform .1s,box-shadow .16s;
}
.btn-login:hover{background:#0F6E56;box-shadow:0 4px 16px rgba(8,80,65,.22)}
.btn-login:active{transform:scale(.98)}
.btn-login:disabled{opacity:.75;cursor:not-allowed;transform:none}

/* spinner inside button */
.btn-spinner{
  display:inline-block;
  animation:spinIcon .65s linear infinite;
}
@keyframes spinIcon{to{transform:rotate(360deg)}}

.divline{display:flex;align-items:center;gap:10px;margin:18px 0}
.divline span{font-size:11.5px;color:#9CA3AF;white-space:nowrap}
.divline hr{flex:1;border:none;border-top:1px solid #F3F4F6}
.sso-btn{
  width:100%;padding:10px;
  background:#F9FAFB;border:1.5px solid #E5E7EB;
  border-radius:9px;font-family:inherit;
  font-size:13px;color:#374151;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:8px;
  transition:background .15s,border-color .15s;
}
.sso-btn:hover{background:#F3F4F6;border-color:#D1D5DB}

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

<div class="wrap" id="login-card">

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

      <!-- invoice doc — bottom right -->
      <g transform="translate(190,418) rotate(-13)" opacity="0.22">
        <rect x="-22" y="-30" width="40" height="52" rx="4"
              fill="rgba(29,158,117,0.08)" stroke="rgba(29,158,117,0.7)" stroke-width="1.2"/>
        <polyline points="-22,-30 7,-30 18,-19 18,22"
                  fill="none" stroke="rgba(29,158,117,0.5)" stroke-width="1"/>
        <line x1="-13" y1="-6"  x2="9"  y2="-6"  stroke="rgba(29,158,117,0.6)" stroke-width="1"/>
        <line x1="-13" y1="3"   x2="9"  y2="3"   stroke="rgba(29,158,117,0.6)" stroke-width="1"/>
        <line x1="-13" y1="12"  x2="4"  y2="12"  stroke="rgba(29,158,117,0.5)" stroke-width="1"/>
        <text x="4" y="-16" font-size="9" fill="rgba(29,158,117,0.55)" font-family="sans-serif">₹</text>
      </g>

      <!-- invoice doc — mid left -->
      <g transform="translate(30,245) rotate(11)" opacity="0.16">
        <rect x="-16" y="-22" width="30" height="38" rx="3"
              fill="rgba(29,158,117,0.06)" stroke="rgba(29,158,117,0.7)" stroke-width="1"/>
        <polyline points="-16,-22 5,-22 14,-13 14,16"
                  fill="none" stroke="rgba(29,158,117,0.5)" stroke-width="0.9"/>
        <line x1="-9" y1="-3" x2="6"  y2="-3" stroke="rgba(29,158,117,0.55)" stroke-width="0.8"/>
        <line x1="-9" y1="5"  x2="6"  y2="5"  stroke="rgba(29,158,117,0.55)" stroke-width="0.8"/>
        <line x1="-9" y1="12" x2="2"  y2="12" stroke="rgba(29,158,117,0.45)" stroke-width="0.8"/>
      </g>

      <!-- small invoice doc — top centre -->
      <g transform="translate(120,32) rotate(-5)" opacity="0.12">
        <rect x="-12" y="-16" width="22" height="28" rx="3"
              fill="none" stroke="rgba(29,158,117,0.8)" stroke-width="1"/>
        <polyline points="-12,-16 3,-16 10,-9 10,12"
                  fill="none" stroke="rgba(29,158,117,0.6)" stroke-width="0.8"/>
        <line x1="-7" y1="-1" x2="5" y2="-1" stroke="rgba(29,158,117,0.6)" stroke-width="0.7"/>
        <line x1="-7" y1="5"  x2="5" y2="5"  stroke="rgba(29,158,117,0.6)" stroke-width="0.7"/>
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
      <div class="left-icon"><i class="fas fa-file-invoice"></i></div>
      <div class="left-title"><?= htmlspecialchars($companyName) ?><br>Invoice Manager</div>
      <div class="left-sub">Manage invoices, clients &amp; payments — all in one place.</div>
      <div class="feature-list">
        <div class="feat">
          <div class="feat-dot"><i class="fas fa-check"></i></div>
          <div class="feat-text">GST-ready invoices</div>
        </div>
        <div class="feat">
          <div class="feat-dot"><i class="fas fa-check"></i></div>
          <div class="feat-text">Client &amp; payment tracking</div>
        </div>
        <div class="feat">
          <div class="feat-dot"><i class="fas fa-check"></i></div>
          <div class="feat-text">PDF export &amp; QR codes</div>
        </div>
      </div>
    </div>

    <div class="left-bottom">
      <div class="left-bottom-text">
        <?= htmlspecialchars(APP_NAME) ?> v<?= APP_VERSION ?><br>
        &copy; <?= date('Y') ?> <?= htmlspecialchars($companyName) ?>
      </div>
    </div>
  </div>

  <!-- ══ RIGHT PANEL ══ -->
  <div class="right">
    <div class="welcome-label">Invoice Manager</div>
    <div class="welcome-title">Welcome back</div>

    <?php if ($error): ?>
    <div class="err">
      <i class="fas fa-exclamation-circle"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="on" id="login-form">
      <div class="field">
        <label class="fl" for="email">Email</label>
        <div class="iw">
          <i class="fas fa-envelope ic"></i>
          <input type="email" id="email" name="email"
                 placeholder="admin@optmstech.in"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 required autofocus autocomplete="email">
        </div>
      </div>

      <div class="field">
        <label class="fl" for="password">Password</label>
        <div class="iw">
          <i class="fas fa-lock ic"></i>
          <input type="password" id="password" name="password"
                 placeholder="••••••••" required autocomplete="current-password">
          <button type="button" class="eye-btn" onclick="togglePwd()" title="Show / hide password">
            <i class="fas fa-eye" id="eyeIco"></i>
          </button>
        </div>
      </div>

      <div class="meta">
        <label class="rem">
          <input type="checkbox" name="remember"> Remember me
        </label>
        <a href="forgot_password.php" class="fp">Forgot password?</a>
      </div>

      <button type="submit" class="btn-login" id="signin-btn">
        <i class="fas fa-sign-in-alt" id="btn-icon"></i>
        <span id="btn-label">Sign in</span>
      </button>
    </form>

    <div class="divline"><hr><span>or continue with</span><hr></div>
    <button type="button" class="sso-btn">
      <i class="fas fa-building"></i> Sign in with SSO
    </button>
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
    // Rapid initial fill to 30%
    this._animate(30, 200);
    // Slower crawl to 70%
    setTimeout(() => this._animate(70, 800), 220);
    // Stall near 85%
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
   Form submit handler
════════════════════════ */
document.getElementById('login-form').addEventListener('submit', function(){
  const btn   = document.getElementById('signin-btn');
  const icon  = document.getElementById('btn-icon');
  const label = document.getElementById('btn-label');

  // Start top loading bar
  LoadingBar.start();

  // Update button state
  btn.disabled    = true;
  icon.className  = 'fas fa-circle-notch btn-spinner';
  label.textContent = 'Signing in…';

  // If PHP returns an error the page reloads — finish bar on load
  window.addEventListener('load', () => LoadingBar.finish());
});

/* ════════════════════════
   Password toggle
════════════════════════ */
function togglePwd(){
  const p = document.getElementById('password');
  const i = document.getElementById('eyeIco');
  p.type = p.type === 'password' ? 'text' : 'password';
  i.className = p.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}

/* ════════════════════════
   Shake on PHP error
════════════════════════ */
<?php if ($error): ?>
(function(){
  LoadingBar.finish();
  const c = document.getElementById('login-card');
  c.classList.add('shake');
  c.addEventListener('animationend', () => c.classList.remove('shake'), {once:true});
})();
<?php endif; ?>

/* ════════════════════════
   Geometric background
════════════════════════ */
(function(){
  const c = document.getElementById('geo-bg');
  function draw(){
    c.width  = window.innerWidth;
    c.height = window.innerHeight;
    const ctx = c.getContext('2d');
    const W = c.width, H = c.height;
    const t = a => `rgba(8,80,65,${a})`;

    function drawDoc(cx, cy, w, h, deg, alpha){
      ctx.save();
      ctx.translate(cx, cy);
      ctx.rotate(deg * Math.PI / 180);
      const x = -w/2, y = -h/2, fold = w * 0.27, r = 3;
      ctx.beginPath();
      ctx.moveTo(x + r, y);
      ctx.lineTo(x + w - fold, y);
      ctx.lineTo(x + w, y + fold);
      ctx.lineTo(x + w, y + h - r); ctx.arcTo(x+w, y+h, x+w-r, y+h, r);
      ctx.lineTo(x + r, y + h);     ctx.arcTo(x, y+h, x, y+h-r, r);
      ctx.lineTo(x, y + r);         ctx.arcTo(x, y, x+r, y, r);
      ctx.closePath();
      ctx.fillStyle   = t(alpha);
      ctx.fill();
      ctx.strokeStyle = t(alpha + 0.04);
      ctx.lineWidth   = 1;
      ctx.stroke();
      // folded corner
      ctx.beginPath();
      ctx.moveTo(x+w-fold, y);
      ctx.lineTo(x+w-fold, y+fold);
      ctx.lineTo(x+w, y+fold);
      ctx.strokeStyle = t(alpha + 0.05); ctx.stroke();
      // line items
      const lx = x + w * 0.15, lw = w * 0.58;
      [0.38, 0.52, 0.65, 0.77].forEach((fy, i) => {
        ctx.beginPath();
        ctx.rect(lx, y + h * fy, i === 0 ? lw * 0.65 : lw, 1.8);
        ctx.fillStyle = t(alpha + 0.05); ctx.fill();
      });
      // rupee
      ctx.font = `bold ${w * 0.18}px sans-serif`;
      ctx.fillStyle = t(alpha + 0.07);
      ctx.fillText('₹', x + w - fold + 2, y + fold + w * 0.22);
      ctx.restore();
    }

    // Floating invoice docs
    [
      [W*.07, H*.12, 50,64,-18,.052],
      [W*.88, H*.10, 42,54, 15,.048],
      [W*.92, H*.76, 58,74, -9,.052],
      [W*.05, H*.80, 38,50, 24,.044],
      [W*.50, H*.05, 34,44,  7,.040],
      [W*.94, H*.42, 28,36,-14,.034],
      [W*.20, H*.92, 26,34, 32,.030],
      [W*.74, H*.94, 34,44, -6,.036],
    ].forEach(d => { try { drawDoc(...d); } catch(e){} });

    // Coin rings
    [
      [W*.28,H*.10,9],[W*.68,H*.20,7],[W*.14,H*.55,6],
      [W*.83,H*.55,8],[W*.46,H*.96,6],[W*.60,H*.04,5],
    ].forEach(([x,y,r]) => {
      ctx.beginPath(); ctx.arc(x,y,r,0,Math.PI*2);
      ctx.strokeStyle = t(.07); ctx.lineWidth = 1.2; ctx.stroke();
      ctx.beginPath(); ctx.arc(x,y,r*.5,0,Math.PI*2);
      ctx.strokeStyle = t(.05); ctx.lineWidth = .8; ctx.stroke();
    });

    // Dashed stamp rings
    [[W*.35,H*.18,22],[W*.65,H*.83,18],[W*.91,H*.50,16]].forEach(([x,y,r]) => {
      ctx.beginPath(); ctx.arc(x,y,r,0,Math.PI*2);
      ctx.strokeStyle = t(.06); ctx.lineWidth = 1;
      ctx.setLineDash([3,3]); ctx.stroke(); ctx.setLineDash([]);
    });

    // Diagonal lines
    [[0,H*.3,W*.45,0],[W*.55,H,W,H*.3],[0,H*.7,W*.3,H]].forEach(([x1,y1,x2,y2]) => {
      ctx.beginPath(); ctx.moveTo(x1,y1); ctx.lineTo(x2,y2);
      ctx.strokeStyle = t(.04); ctx.lineWidth = 1; ctx.stroke();
    });
  }

  draw();
  window.addEventListener('resize', draw);
})();
</script>
</body>
</html>