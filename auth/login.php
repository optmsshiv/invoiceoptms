<?php
// ================================================================
//  OPTMS Invoice Manager — Login Page (Redesigned)
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
}

/* ── Outer wrapper ── */
.wrap{
  display:flex;
  border-radius:20px;
  overflow:hidden;
  border:1px solid #d4ddd9;
  width:100%;
  max-width:720px;
  min-height:520px;
  box-shadow:0 8px 40px rgba(8,80,65,.10);
  animation:fadeUp .4s ease;
}
@keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}

/* ── Left teal panel ── */
.left{
  width:240px;
  flex-shrink:0;
  background:#085041;
  padding:38px 28px;
  display:flex;
  flex-direction:column;
  justify-content:space-between;
}
.left-icon{
  width:46px;height:46px;
  background:#0F6E56;
  border-radius:13px;
  display:flex;align-items:center;justify-content:center;
  margin-bottom:26px;
  border:1px solid #1D9E75;
}
.left-icon i{font-size:20px;color:#9FE1CB}
.left-title{font-size:17px;font-weight:600;color:#E1F5EE;line-height:1.4;margin-bottom:8px}
.left-sub{font-size:12px;color:#5DCAA5;line-height:1.65}
.feature-list{margin-top:26px;display:flex;flex-direction:column;gap:13px}
.feat{display:flex;align-items:flex-start;gap:10px}
.feat-dot{
  width:20px;height:20px;border-radius:50%;
  background:#0F6E56;border:1px solid #1D9E75;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;margin-top:1px;
}
.feat-dot i{font-size:10px;color:#9FE1CB}
.feat-text{font-size:12px;color:#9FE1CB;line-height:1.5}
.left-bottom{font-size:11px;color:#0F6E56}

/* ── Right form panel ── */
.right{
  flex:1;
  background:#fff;
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
  font-family:inherit;font-size:14px;color:#111;
  background:#F9FAFB;outline:none;
  transition:border-color .18s,box-shadow .18s,background .18s;
}
.iw input:focus{
  border-color:#1D9E75;
  box-shadow:0 0 0 3px rgba(29,158,117,.13);
  background:#fff;
}
.iw input::placeholder{color:#C4C4C4}
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

/* ── Shake animation ── */
.shake{animation:shake .35s ease}
@keyframes shake{
  0%,100%{transform:translateX(0)}
  20%{transform:translateX(-6px)}
  40%{transform:translateX(6px)}
  60%{transform:translateX(-4px)}
  80%{transform:translateX(4px)}
}

/* ── Responsive: stack on narrow screens ── */
@media(max-width:560px){
  .wrap{flex-direction:column;min-height:auto}
  .left{width:100%;padding:28px 24px;flex-direction:row;flex-wrap:wrap;gap:16px;align-items:center}
  .feature-list{display:none}
  .left-bottom{display:none}
  .right{padding:28px 24px}
}
</style>
</head>
<body>

<div class="wrap" id="card">

  <!-- ── Left panel ── -->
  <div class="left">
    <div>
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
    <div class="left-bottom">&copy; <?= date('Y') ?> <?= htmlspecialchars($companyName) ?> &nbsp;&middot;&nbsp; v<?= APP_VERSION ?></div>
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
function togglePwd(){
  const p=document.getElementById('password'),i=document.getElementById('eyeIco');
  p.type=p.type==='password'?'text':'password';
  i.className=p.type==='password'?'fas fa-eye':'fas fa-eye-slash';
}

<?php if ($error): ?>
(function(){
  const c=document.getElementById('card');
  c.classList.add('shake');
  c.addEventListener('animationend',()=>c.classList.remove('shake'),{once:true});
})();
<?php endif; ?>
</script>
</body>
</html>