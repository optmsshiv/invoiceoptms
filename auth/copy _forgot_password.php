<?php
require_once __DIR__ . '/../includes/auth.php';
startSession();
if (!empty($_SESSION['user_id'])) { header('Location: ' . APP_URL . '/index.php'); exit; }

$msg = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!$email) {
        $error = 'Please enter your email address.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, name FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        // Always show success to prevent email enumeration
        $msg = 'If that email is registered, a reset link has been sent. Please check your inbox.';
        if ($user) {
            // In production: generate token, store in DB, send email
            // For now we just log the request
            logActivity($user['id'], 'password_reset_request', 'user', $user['id'], 'Password reset requested');
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
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Public Sans',sans-serif;background:linear-gradient(135deg,#1A2332 0%,#263348 60%,#00897B 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:20px;padding:44px;width:100%;max-width:400px;box-shadow:0 24px 64px rgba(0,0,0,.3)}
.brand{text-align:center;margin-bottom:32px}
.brand-logo{width:52px;height:52px;background:linear-gradient(135deg,#00897B,#4DB6AC);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:900;color:#fff;margin:0 auto 12px}
h2{font-size:18px;font-weight:800;color:#1A2332;margin-bottom:6px}
p{font-size:13px;color:#9CA3AF;margin-bottom:24px}
.field label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
.input-wrap{position:relative}
.input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:14px}
.input-wrap input{width:100%;padding:12px 14px 12px 40px;border:1.5px solid #E5E7EB;border-radius:10px;font-family:'Public Sans',sans-serif;font-size:14px;color:#111;outline:none;transition:.2s}
.input-wrap input:focus{border-color:#00897B;box-shadow:0 0 0 3px rgba(0,137,123,.12)}
.msg{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.msg.success{background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7}
.msg.error{background:#FEE2E2;color:#DC2626;border:1px solid #FECACA}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,#00897B,#00695C);color:#fff;border:none;border-radius:10px;font-family:'Public Sans',sans-serif;font-size:14px;font-weight:700;cursor:pointer;margin-top:16px;transition:.2s}
.btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,137,123,.3)}
.back-link{display:block;text-align:center;margin-top:20px;font-size:13px;color:#00897B;text-decoration:none;font-weight:600}
.back-link:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="card">
  <div class="brand">
    <div class="brand-logo">OT</div>
    <h2>Forgot Password?</h2>
    <p>Enter your email and we'll send you a reset link</p>
  </div>

  <?php if ($msg):  ?><div class="msg success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($error):?><div class="msg error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <?php if (!$msg): ?>
  <form method="POST">
    <div class="field">
      <label for="email">Email Address</label>
      <div class="input-wrap">
        <i class="fas fa-envelope"></i>
        <input type="email" id="email" name="email" placeholder="admin@optmstech.in" required>
      </div>
    </div>
    <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> &nbsp;Send Reset Link</button>
  </form>
  <?php endif; ?>

  <a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>
</div>
</body>
</html>
