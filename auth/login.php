<?php
// ================================================================
//  OPTMS Invoice Manager — Login Page (Redesigned + Geometric BG)
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
  background:#f0f4f3;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:24px 20px;
  position:relative;
  overflow:hidden;
}

/* ── Geometric canvas background ── */
#geo-bg{
  position:fixed;
  inset:0;
  width:100%;
  height:100%;
  z-index:0;
  pointer-events:none;
}

/* ── Card wrapper ── */
.wrap{
  display:flex;
  border-radius:20px;
  overflow:hidden;
  width:100%;
  max-width:720px;
  min-height:520px;
  position:relative;
  z-index:1;
  border:1px solid rgba(29,158,117,.18);
  box-shadow:0 32px 80px rgba(0,0,0,.45);
  animation:fadeUp .45s ease;
}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}

/* ── Left teal panel ── */
.left{
  width:240px;
  flex-shrink:0;
  background:rgba(8,80,65,.88);
  padding:38px 28px;
  display:flex;
  flex-direction:column;
  justify-content:space-between;
  position:relative;
  overflow:hidden;
}
.left-ring{
  position:absolute;
  border-radius:50%;
  border:1px solid rgba(29,158,117,.15);
  pointer-events:none;
}
.left-icon{
  width:46px;height:46px;
  background:rgba(29,158,117,.2);
  border-radius:13px;
  display:flex;align-items:center;justify-content:center;
  margin-bottom:26px;
  border:1px solid rgba(29,158,117,.35);
}
.left-icon i{font-size:20px;color:#9FE1CB}
.left-title{font-size:17px;font-weight:600;color:#E1F5EE;line-height:1.4;margin-bottom:8px}
.left-sub{font-size:12px;color:#5DCAA5;line-height:1.65}
.feature-list{margin-top:26px;display:flex;flex-direction:column;gap:13px}
.feat{display:flex;align-items:flex-start;gap:10px}
.feat-dot{
  width:20px;height:20px;border-radius:50%;
  background:rgba(29,158,117,.15);
  border:1px solid rgba(29,158,117,.3);
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;margin-top:1px;
}
.feat-dot i{font-size:10px;color:#9FE1CB}
.feat-text{font-size:12px;color:#9FE1CB;line-height:1.5}
.left-bottom{font-size:11px;color:rgba(93,202,165,.4);position:relative;z-index:1}

/* ── Right form panel ── */
.right{
  flex:1;
  background:#ffffff;
  padding:44px 40px;
  display:flex;
  flex-direction:column;
  justify-content:center;
}
.welcome-label{font-size:11.5px;color:#6B7280;letter-spacing:.5px;text-transform:uppercase;margin-bottom:5px}
.welcome-title{font-size:21px;font-weight:700;color:#111827;margin-bottom:28px}

/* ── Error box ── */
.err{
  background:#FEF2F2;border:1px solid #FECACA;border-radius:9px;
  padding:9px 13px;font-size:13px;color:#B91C1C;
  margin-bottom:14px;display:flex;align-items:center;gap:7px;
}

/* ── Fields ── */
.field{margin-bottom:14px}
.fl{font-size:11.5px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px;display:block}
.iw{position:relative;display:flex;align-items:center}
.iw i.ic{position:absolute;left:12px;font-size:14px;color:#9CA3AF;pointer-events:none}
.iw input{
  width:100%;padding:11px 12px 11px 36px;
  border:1.5px solid #E5E7EB;border-radius:9px;
  font-family:inherit;font-size:14px;color:#111827;
  background:#F9FAFB;outline:none;
  transition:border-color .18s,box-shadow .18s,background .18s;
}
.iw input:focus{
  border-color:#1D9E75;
  box-shadow:0 0 0 3px rgba(29,158,117,.13);
  background:#fff;
}
.iw input::placeholder{color:#D1D5DB}
.eye-btn{
  position:absolute;right:10px;
  background:none;border:none;cursor:pointer;
  color:#9CA3AF;font-size:13px;padding:4px;
  display:flex;align-items:center;
}
.eye-btn:hover{color:#374151}

/* ── Meta row ── */
.meta{display:flex;align-items:center;justify-content:space-between;margin:14px 0 20px;font-size:13px}
.rem{display:flex;align-items:center;gap:6px;cursor:pointer;color:#6B7280}
.rem input{accent-color:#1D9E75;width:13px;height:13px;cursor:pointer}
.fp{color:#0F6E56;text-decoration:none;font-weight:600;font-size:13px}
.fp:hover{text-decoration:underline}

/* ── Sign-in button ── */
.btn{
  width:100%;padding:12px;
  background:#085041;color:#E1F5EE;
  border:none;border-radius:9px;
  font-family:inherit;font-size:14px;font-weight:600;
  cursor:pointer;letter-spacing:.2px;
  display:flex;align-items:center;justify-content:center;gap:8px;
  transition:background .16s,transform .1s;
}
.btn:hover{background:#0F6E56}
.btn:active{transform:scale(.98)}
.btn:disabled{opacity:.7;cursor:not-allowed}

/* ── SSO divider ── */
.divline{display:flex;align-items:center;gap:10px;margin:18px 0}
.divline span{font-size:11.5px;color:#9CA3AF;white-space:nowrap}
.divline hr{flex:1;border:none;border-top:1px solid #F3F4F6}
.sso-btn{
  width:100%;padding:10px;
  background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:9px;
  font-family:inherit;font-size:13px;color:#374151;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;
  transition:background .15s,border-color .15s;
}
.sso-btn:hover{background:#F3F4F6;border-color:#D1D5DB}

/* ── Shake on error ── */
.shake{animation:shake .35s ease}
@keyframes shake{
  0%,100%{transform:translateX(0)}
  20%{transform:translateX(-6px)}
  40%{transform:translateX(6px)}
  60%{transform:translateX(-4px)}
  80%{transform:translateX(4px)}
}

/* ── Responsive ── */
@media(max-width:580px){
  .wrap{flex-direction:column;min-height:auto}
  .left{width:100%;padding:24px;flex-direction:row;align-items:center;gap:14px}
  .left-title{font-size:14px}
  .left-sub,.feature-list,.left-bottom{display:none}
  .left-icon{margin-bottom:0;flex-shrink:0}
  .right{padding:28px 20px}
}
</style>
</head>
<body>

<!-- Geometric canvas background -->
<canvas id="geo-bg"></canvas>

<div class="wrap" id="card">

  <!-- ── Left panel ── -->
  <div class="left">
    <!-- Decorative rings -->
    <div class="left-ring" style="width:130px;height:130px;top:-45px;right:-45px"></div>
    <div class="left-ring" style="width:90px;height:90px;bottom:-30px;left:-30px"></div>

    <div style="position:relative;z-index:1">
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
      &copy; <?= date('Y') ?> <?= htmlspecialchars($companyName) ?> &nbsp;&middot;&nbsp; v<?= APP_VERSION ?>
    </div>
  </div>

  <!-- ── Right form panel ── -->
  <div class="right">
    <div class="welcome-label">Invoice Manager</div>
    <div class="welcome-title">Welcome back</div>

    <?php if ($error): ?>
    <div class="err">
      <i class="fas fa-exclamation-circle"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="on">
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

      <button type="submit" class="btn" id="signin-btn">
        <i class="fas fa-sign-in-alt"></i> Sign in
      </button>
    </form>

    <div class="divline">
      <hr><span>or continue with</span><hr>
    </div>

    <button type="button" class="sso-btn">
      <i class="fas fa-building"></i> Sign in with SSO
    </button>
  </div>

</div>

<script>
// ── Geometric mesh background ──────────────────────────
(function () {
  var c  = document.getElementById('geo-bg');
  var ctx = c.getContext('2d');

  function resize() {
    c.width  = window.innerWidth;
    c.height = window.innerHeight;
    draw();
  }

  function draw() {
    var W = c.width, H = c.height;
    var teal = 'rgba(29,158,117,';
    ctx.clearRect(0, 0, W, H);

    var size = 52;

    // Dot grid
    for (var x = 0; x <= W; x += size) {
      for (var y = 0; y <= H; y += size) {
        ctx.beginPath();
        ctx.arc(x, y, 1.3, 0, Math.PI * 2);
        ctx.fillStyle = teal + '0.22)';
        ctx.fill();
      }
    }

    // Faint horizontal & vertical grid lines
    ctx.lineWidth = 0.4;
    for (var x = 0; x <= W; x += size) {
      ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H);
      ctx.strokeStyle = teal + '0.06)'; ctx.stroke();
    }
    for (var y = 0; y <= H; y += size) {
      ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y);
      ctx.strokeStyle = teal + '0.06)'; ctx.stroke();
    }

    // Diagonal accent lines
    var diags = [
      [0, H * 0.2,  W * 0.45, 0],
      [0, H * 0.65, W * 0.55, H * 0.05],
      [W * 0.15, H, W * 0.75, 0],
      [W * 0.5,  H, W,        H * 0.3],
      [W * 0.75, H, W,        H * 0.65]
    ];
    ctx.lineWidth = 0.8;
    diags.forEach(function (d) {
      ctx.beginPath(); ctx.moveTo(d[0], d[1]); ctx.lineTo(d[2], d[3]);
      ctx.strokeStyle = teal + '0.055)'; ctx.stroke();
    });

    // Hollow hexagons
    var hexes = [
      [W * 0.12, H * 0.18, 58],
      [W * 0.82, H * 0.7,  46],
      [W * 0.62, H * 0.12, 34],
      [W * 0.28, H * 0.82, 40],
      [W * 0.9,  H * 0.25, 28]
    ];
    ctx.lineWidth = 0.9;
    hexes.forEach(function (h) {
      ctx.beginPath();
      for (var i = 0; i < 6; i++) {
        var angle = Math.PI / 180 * (60 * i - 30);
        var px = h[0] + h[2] * Math.cos(angle);
        var py = h[1] + h[2] * Math.sin(angle);
        i === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
      }
      ctx.closePath();
      ctx.strokeStyle = teal + '0.09)'; ctx.stroke();
    });

    // Small diamond accents
    var diamonds = [
      [W * 0.45, H * 0.08, 10],
      [W * 0.05, H * 0.45, 8],
      [W * 0.95, H * 0.5,  7],
      [W * 0.7,  H * 0.92, 9]
    ];
    ctx.lineWidth = 0.7;
    diamonds.forEach(function (d) {
      ctx.beginPath();
      ctx.moveTo(d[0],        d[1] - d[2]);
      ctx.lineTo(d[0] + d[2], d[1]);
      ctx.lineTo(d[0],        d[1] + d[2]);
      ctx.lineTo(d[0] - d[2], d[1]);
      ctx.closePath();
      ctx.strokeStyle = teal + '0.11)'; ctx.stroke();
    });
  }

  window.addEventListener('resize', resize);
  resize();
})();

// ── Password toggle ────────────────────────────────────
function togglePwd() {
  var p = document.getElementById('password');
  var i = document.getElementById('eyeIco');
  p.type = p.type === 'password' ? 'text' : 'password';
  i.className = p.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}

// ── Shake card on PHP error ────────────────────────────
<?php if ($error): ?>
(function () {
  var c = document.getElementById('card');
  c.classList.add('shake');
  c.addEventListener('animationend', function () {
    c.classList.remove('shake');
  }, { once: true });
})();
<?php endif; ?>
</script>
</body>
</html>