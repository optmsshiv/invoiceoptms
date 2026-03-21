<?php
// ═══════════════════════════════════════════════════════
//  OPTMS Invoice Manager — Login Page
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/../includes/auth.php';
startSession();

// Already logged in → go to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
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
            header('Location: ' . APP_URL . '/index.php');
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
body{font-family:'Public Sans',sans-serif;background:linear-gradient(135deg,#1A2332 0%,#263348 60%,#00897B 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.login-card{background:#fff;border-radius:20px;padding:48px 44px;width:100%;max-width:420px;box-shadow:0 24px 64px rgba(0,0,0,.3)}
.brand{text-align:center;margin-bottom:36px}
.brand-logo{width:56px;height:56px;background:linear-gradient(135deg,#00897B,#4DB6AC);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:900;color:#fff;margin:0 auto 12px;letter-spacing:-1px}
.brand-name{font-size:20px;font-weight:800;color:#1A2332}
.brand-sub{font-size:13px;color:#9CA3AF;margin-top:2px}
.field{margin-bottom:20px}
.field label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
.input-wrap{position:relative}
.input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:14px}
.input-wrap input{width:100%;padding:12px 14px 12px 40px;border:1.5px solid #E5E7EB;border-radius:10px;font-family:'Public Sans',sans-serif;font-size:14px;color:#111;transition:.2s;outline:none}
.input-wrap input:focus{border-color:#00897B;box-shadow:0 0 0 3px rgba(0,137,123,.12)}
.toggle-pass{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;font-size:14px}
.error-box{background:#FEE2E2;border:1px solid #FECACA;border-radius:10px;padding:12px 16px;font-size:13px;color:#DC2626;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.btn-login{width:100%;padding:14px;background:linear-gradient(135deg,#00897B,#00695C);color:#fff;border:none;border-radius:10px;font-family:'Public Sans',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;letter-spacing:.3px}
.btn-login:hover{background:linear-gradient(135deg,#00695C,#004D40);transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,137,123,.3)}
.btn-login:active{transform:none}
.footer-text{text-align:center;margin-top:24px;font-size:12px;color:#9CA3AF}
.remember-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;font-size:13px}
.remember-row label{display:flex;align-items:center;gap:6px;cursor:pointer;color:#374151}
.remember-row input{accent-color:#00897B;width:14px;height:14px}
.forgot-link{color:#00897B;text-decoration:none;font-weight:600}
.forgot-link:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="login-card">
  <div class="brand">
    <div class="brand-logo">OT</div>
    <div class="brand-name"><?= htmlspecialchars($companyName) ?></div>
    <div class="brand-sub">Invoice Manager — Sign In</div>
  </div>

  <?php if ($error): ?>
  <div class="error-box"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">

    <div class="field">
      <label for="email">Email Address</label>
      <div class="input-wrap">
        <i class="fas fa-envelope"></i>
        <input type="email" id="email" name="email" placeholder="admin@optmstech.in"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </div>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <div class="input-wrap">
        <i class="fas fa-lock"></i>
        <input type="password" id="password" name="password" placeholder="••••••••" required>
        <button type="button" class="toggle-pass" onclick="togglePwd()"><i class="fas fa-eye" id="eyeIcon"></i></button>
      </div>
    </div>

    <div class="remember-row">
      <label><input type="checkbox" name="remember"> Remember me</label>
      <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
    </div>

    <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt"></i> &nbsp;Sign In</button>
  </form>

  <div class="footer-text">
    <?= htmlspecialchars(APP_NAME) ?> v<?= APP_VERSION ?><br>
    &copy; <?= date('Y') ?> <?= htmlspecialchars($companyName) ?>
  </div>
</div>

<script>
function togglePwd() {
  const p = document.getElementById('password');
  const i = document.getElementById('eyeIcon');
  if (p.type === 'password') { p.type = 'text'; i.className = 'fas fa-eye-slash'; }
  else { p.type = 'password'; i.className = 'fas fa-eye'; }
}
</script>
</body>
</html>
