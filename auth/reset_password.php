<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/password_reset.php';
startSession();
if (!empty($_SESSION['user_id'])) { header('Location: ' . APP_URL . '/index.php'); exit; }

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = '';
$done  = false;

$resetUser = validatePasswordResetToken($token);
$linkValid = (bool) $resetUser;

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password_confirm'] ?? '';

    if (!$linkValid) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        $ok = completePasswordReset((int)$resetUser['token_id'], (int)$resetUser['id'], $password);
        if ($ok) {
            $done = true;
        } else {
            $error = 'Something went wrong updating your password. Please try again.';
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        if ($done) {
            echo json_encode(['success' => true, 'redirect' => APP_URL . '/auth/login.php']);
        } else {
            echo json_encode(['success' => false, 'error' => $error]);
        }
        exit;
    }
}

$companyName = defined('APP_NAME') ? APP_NAME : 'OPTMS Tech';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — <?= htmlspecialchars($companyName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:'Public Sans',sans-serif}
body{min-height:100vh;background:#E8EFEC;display:flex;align-items:center;justify-content:center;padding:24px 20px}
.card{
  width:100%;max-width:420px;background:#fff;border-radius:20px;padding:40px 36px;
  box-shadow:0 12px 56px rgba(8,80,65,.13);
  animation:fadeUp .4s ease;transition:transform .3s ease,opacity .3s ease;
}
.card.shake{animation:shake .36s ease}
@keyframes shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-6px)}40%{transform:translateX(6px)}60%{transform:translateX(-4px)}80%{transform:translateX(4px)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
.icon{width:52px;height:52px;border-radius:14px;background:rgba(29,158,117,.12);border:1px solid rgba(29,158,117,.28);display:flex;align-items:center;justify-content:center;margin-bottom:22px}
.icon i{font-size:22px;color:#0F6E56}
.title{font-size:20px;font-weight:700;color:#111827;margin-bottom:8px}
.desc{font-size:13px;color:#6B7280;line-height:1.6;margin-bottom:24px}
.err{background:#FEF2F2;border:1px solid #FECACA;border-radius:9px;padding:10px 14px;font-size:13px;color:#B91C1C;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.field{margin-bottom:15px}
.fl{font-size:11.5px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.45px;margin-bottom:5px;display:block}
.iw{position:relative;display:flex;align-items:center}
.iw i.ic{position:absolute;left:12px;font-size:14px;color:#9CA3AF}
.iw input{width:100%;padding:11px 12px 11px 37px;border:1.5px solid #E5E7EB;border-radius:9px;font-family:inherit;font-size:14px;color:#111;background:#F9FAFB;outline:none;transition:border-color .18s,box-shadow .18s,background .18s}
.iw input:focus{border-color:#1D9E75;box-shadow:0 0 0 3px rgba(29,158,117,.13);background:#fff}
.btn-primary{width:100%;padding:12px;background:#085041;color:#E1F5EE;border:none;border-radius:9px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;margin-top:6px;transition:background .16s}
.btn-primary:hover{background:#0F6E56}
.btn-primary:disabled{opacity:.75;cursor:not-allowed}
.btn-outline{width:100%;padding:11px;background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:9px;font-family:inherit;font-size:13.5px;font-weight:600;color:#374151;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;margin-top:10px}
.btn-outline:hover{background:#F3F4F6}
.btn-spinner{display:inline-block;animation:spinIcon .65s linear infinite}
@keyframes spinIcon{to{transform:rotate(360deg)}}
.success-icon{width:60px;height:60px;border-radius:50%;background:#EAFBF4;border:1.5px solid #A7E9D3;display:flex;align-items:center;justify-content:center;margin:4px auto 20px}
.success-icon i{font-size:24px;color:#0F6E56}
.center{text-align:center}
.hint{font-size:11.5px;color:#9CA3AF;margin-top:6px}
</style>
</head>
<body>

<div class="card" id="rp-card">

<?php if ($done): ?>
  <div class="center">
    <div class="success-icon"><i class="fas fa-check"></i></div>
    <div class="title">Password updated</div>
    <div class="desc">Your password has been changed successfully. You can now sign in with your new password.</div>
    <a href="login.php" class="btn-primary" style="text-decoration:none"><i class="fas fa-arrow-right"></i> Go to Login</a>
  </div>

<?php elseif (!$linkValid): ?>
  <div class="icon"><i class="fas fa-triangle-exclamation"></i></div>
  <div class="title">Link invalid or expired</div>
  <div class="desc">This password reset link is no longer valid. Reset links expire after <?= (int)PASSWORD_RESET_TOKEN_TTL_MINUTES ?> minutes and can only be used once.</div>
  <a href="forgot_password.php" class="btn-primary" style="text-decoration:none"><i class="fas fa-rotate-right"></i> Request a New Link</a>
  <a href="login.php" class="btn-outline"><i class="fas fa-arrow-left"></i> Back to Login</a>

<?php else: ?>
  <div class="icon"><i class="fas fa-lock"></i></div>
  <div class="title">Set a new password</div>
  <div class="desc">Choose a new password for <b style="color:#111827"><?= htmlspecialchars($resetUser['email']) ?></b>.</div>

  <div class="err" id="err-box" style="<?= $error ? '' : 'display:none' ?>">
    <i class="fas fa-exclamation-circle"></i>
    <span class="err-text"><?= htmlspecialchars($error) ?></span>
  </div>

  <form method="POST" id="rp-form">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <div class="field">
      <label class="fl" for="password">New Password</label>
      <div class="iw">
        <i class="fas fa-lock ic"></i>
        <input type="password" id="password" name="password" placeholder="••••••••" required minlength="8" autofocus autocomplete="new-password">
      </div>
      <div class="hint">At least 8 characters.</div>
    </div>
    <div class="field">
      <label class="fl" for="password_confirm">Confirm Password</label>
      <div class="iw">
        <i class="fas fa-lock ic"></i>
        <input type="password" id="password_confirm" name="password_confirm" placeholder="••••••••" required minlength="8" autocomplete="new-password">
      </div>
    </div>
    <button type="submit" class="btn-primary" id="rp-btn">
      <i class="fas fa-check" id="rp-icon"></i>
      <span id="rp-label">Reset Password</span>
    </button>
  </form>
<?php endif; ?>

</div>

<script>
const rpForm = document.getElementById('rp-form');
if (rpForm) {
  rpForm.addEventListener('submit', function(e){
    e.preventDefault();
    const form   = e.target;
    const btn    = document.getElementById('rp-btn');
    const icon   = document.getElementById('rp-icon');
    const label  = document.getElementById('rp-label');
    const card   = document.getElementById('rp-card');
    const errBox = document.getElementById('err-box');
    const errTxt = errBox ? errBox.querySelector('.err-text') : null;

    function showError(msg){
      btn.disabled = false;
      icon.className = 'fas fa-check';
      label.textContent = 'Reset Password';
      if (errBox){ if (errTxt) errTxt.textContent = msg; errBox.style.display = 'flex'; }
      card.classList.add('shake');
      card.addEventListener('animationend', () => card.classList.remove('shake'), {once:true});
    }

    btn.disabled = true;
    icon.className = 'fas fa-circle-notch btn-spinner';
    label.textContent = 'Saving…';
    if (errBox) errBox.style.display = 'none';

    fetch(window.location.href, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(form)
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          window.location.href = data.redirect || 'login.php';
        } else {
          showError(data.error || 'Something went wrong. Please try again.');
        }
      })
      .catch(() => showError('Network error. Please check your connection and try again.'));
  });
}
</script>
</body>
</html>
