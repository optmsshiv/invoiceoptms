<?php
// ================================================================
//  OPTMS Invoice Manager — auth/locked.php
//  Server-rendered fallback lock screen for when the JS overlay
//  hasn't run yet (e.g. a stale tab reopened after the session was
//  already locked). Confirms the password via a normal POST, then
//  redirects back to wherever the user was.
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();

// Not logged in at all → normal login, nothing to unlock
if (empty($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

// Not actually locked → send them back to the app
if (empty($_SESSION['locked'])) {
    header('Location: /');
    exit;
}

$returnTo = $_GET['return'] ?? $_POST['return'] ?? '/';
// Only allow same-site relative redirects
if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
    $returnTo = '/';
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if ($password === '') {
        $error = 'Please enter your password.';
    } else {
        $result = unlockSession($password);
        if ($result['ok']) {
            header('Location: ' . $returnTo);
            exit;
        }
        $error = match ($result['reason']) {
            'too_many_attempts' => 'Too many failed attempts. Please log in again.',
            default             => 'Incorrect password. Please try again.',
        };
        if ($result['reason'] === 'too_many_attempts') {
            header('Location: /auth/login.php');
            exit;
        }
    }
}

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'there', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Session Locked — <?= htmlspecialchars(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:'Public Sans',sans-serif}
body{
  min-height:100vh;background:#E8EFEC;
  display:flex;align-items:center;justify-content:center;
  padding:24px 20px;
}
.card{
  background:#fff;border-radius:16px;padding:40px 36px;
  width:100%;max-width:380px;box-shadow:0 12px 56px rgba(8,80,65,.13);
  text-align:center;
}
.lock-icon{
  width:56px;height:56px;border-radius:50%;
  background:#085041;display:flex;align-items:center;justify-content:center;
  margin:0 auto 18px;
}
.lock-icon i{font-size:22px;color:#9FE1CB}
.title{font-size:19px;font-weight:700;color:#111827;margin-bottom:6px}
.sub{font-size:13px;color:#6B7280;margin-bottom:24px}
.err{
  background:#FEF2F2;border:1px solid #FECACA;border-radius:9px;
  padding:10px 14px;font-size:13px;color:#B91C1C;margin-bottom:16px;
  display:flex;align-items:center;gap:8px;text-align:left;
}
.field{margin-bottom:16px;text-align:left}
.fl{font-size:11.5px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.45px;margin-bottom:5px;display:block}
.iw{position:relative;display:flex;align-items:center}
.iw i.ic{position:absolute;left:12px;font-size:14px;color:#9CA3AF;pointer-events:none}
.iw input{
  width:100%;padding:11px 12px 11px 37px;border:1.5px solid #E5E7EB;
  border-radius:9px;font-family:inherit;font-size:14px;color:#111;
  background:#F9FAFB;outline:none;
}
.iw input:focus{border-color:#1D9E75;box-shadow:0 0 0 3px rgba(29,158,117,.13);background:#fff}
.btn{
  width:100%;padding:12px;background:#085041;color:#E1F5EE;border:none;
  border-radius:9px;font-family:inherit;font-size:14px;font-weight:700;
  cursor:pointer;margin-bottom:12px;
}
.btn:hover{background:#0F6E56}
.logout-link{font-size:12.5px;color:#6B7280;text-decoration:none}
.logout-link:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="card">
  <div class="lock-icon"><i class="fas fa-lock"></i></div>
  <div class="title">Session locked</div>
  <div class="sub">Welcome back, <?= $userName ?>. Enter your password to continue.</div>

  <?php if ($error): ?>
  <div class="err"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="return" value="<?= htmlspecialchars($returnTo) ?>">
    <div class="field">
      <label class="fl" for="password">Password</label>
      <div class="iw">
        <i class="fas fa-lock ic"></i>
        <input type="password" id="password" name="password" placeholder="••••••••" required autofocus autocomplete="current-password">
      </div>
    </div>
    <button type="submit" class="btn">Unlock</button>
  </form>
  <a href="/auth/logout.php" class="logout-link">Not you? Log out</a>
</div>
</body>
</html>
