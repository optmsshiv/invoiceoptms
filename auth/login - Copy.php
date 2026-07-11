<?php
// ================================================================
//  OPTMS Invoice Manager — Login Page
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();

// Already logged in → dashboard
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
<title>Login — <?= htmlspecialchars($companyName) ?> Invoice Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{font-family:'Public Sans',sans-serif;background:linear-gradient(135deg,#1A2332 0%,#263348 55%,#00897B 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:20px;padding:48px 44px;width:100%;max-width:420px;box-shadow:0 24px 64px rgba(0,0,0,.35);animation:fadeUp .4s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
.brand{text-align:center;margin-bottom:36px}
.logo{width:58px;height:58px;background:linear-gradient(135deg,#00897B,#4DB6AC);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:900;color:#fff;margin:0 auto 12px;letter-spacing:-1px}
.brand-name{font-size:20px;font-weight:800;color:#1A2332}
.brand-sub{font-size:13px;color:#9CA3AF;margin-top:2px}
.field{margin-bottom:18px}
.field label{display:block;font-size:12.5px;font-weight:700;color:#374151;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.inp{position:relative}
.inp i.ic{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:14px;pointer-events:none}
.inp input{width:100%;padding:13px 14px 13px 40px;border:1.5px solid #E5E7EB;border-radius:10px;font-family:inherit;font-size:14px;color:#111;transition:.2s;outline:none;background:#fff}
.inp input:focus{border-color:#00897B;box-shadow:0 0 0 3px rgba(0,137,123,.12)}
.inp .eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;font-size:13px;padding:4px}
.err{background:#FEE2E2;border:1px solid #FECACA;border-radius:9px;padding:11px 14px;font-size:13px;color:#DC2626;margin-bottom:18px;display:flex;align-items:center;gap:8px}
.row{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;font-size:13px}
.row label{display:flex;align-items:center;gap:6px;cursor:pointer;color:#555}
.row input[type=checkbox]{accent-color:#00897B;width:14px;height:14px}
.btn-login{width:100%;padding:14px;background:linear-gradient(135deg,#00897B,#00695C);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;letter-spacing:.3px;transition:.2s}
.btn-login:hover{background:linear-gradient(135deg,#00695C,#004D40);transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,137,123,.3)}
.btn-login:active{transform:none}
.forgot{color:#00897B;text-decoration:none;font-weight:600;font-size:13px}
.forgot:hover{text-decoration:underline}
.footer{text-align:center;margin-top:24px;font-size:11px;color:#C4C4C4;line-height:1.8}
.demo-hint{background:#E0F2F1;border-radius:8px;padding:10px 14px;font-size:12px;color:#00695C;margin-bottom:18px;text-align:center}
.demo-hint code{font-weight:700;font-size:12px}
</style>
</head>
<body>
<div class="card">
  <div class="brand">
    <div class="logo"><?= strtoupper(substr($companyName,0,2)) ?></div>
    <div class="brand-name"><?= htmlspecialchars($companyName) ?></div>
    <div class="brand-sub">Invoice Manager &mdash; Sign In</div>
  </div>

  <div class="demo-hint">
    Default: <code>admin@optmstech.in</code> / <code>Admin@1234</code>
  </div>

  <?php if ($error): ?>
  <div class="err"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="on">
    <div class="field">
      <label for="email">Email Address</label>
      <div class="inp">
        <i class="fas fa-envelope ic"></i>
        <input type="email" id="email" name="email"
               placeholder="admin@optmstech.in"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autofocus autocomplete="email">
      </div>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <div class="inp">
        <i class="fas fa-lock ic"></i>
        <input type="password" id="password" name="password"
               placeholder="••••••••" required autocomplete="current-password">
        <button type="button" class="eye" onclick="togglePwd()" title="Show/hide">
          <i class="fas fa-eye" id="eyeIco"></i>
        </button>
      </div>
    </div>
    <div class="row">
      <label><input type="checkbox" name="remember"> Remember me</label>
      <a href="forgot_password.php" class="forgot">Forgot password?</a>
    </div>
    <button type="submit" class="btn-login">
      <i class="fas fa-sign-in-alt"></i> &nbsp;Sign In
    </button>
  </form>

  <div class="footer">
    <?= htmlspecialchars(APP_NAME) ?> v<?= APP_VERSION ?><br>
    &copy; <?= date('Y') ?> <?= htmlspecialchars($companyName) ?>
  </div>
</div>
<script>
function togglePwd(){
  const p=document.getElementById('password'),i=document.getElementById('eyeIco');
  p.type=p.type==='password'?'text':'password';
  i.className=p.type==='password'?'fas fa-eye':'fas fa-eye-slash';
}
</script>
</body>
</html>
