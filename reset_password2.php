<?php
// ================================================================
//  OPTMS Invoice Manager — reset_password.php
//  Upload this ONE file to your server root, open in browser,
//  set your new password, then DELETE this file immediately.
//  No other files needed — completely standalone.
// ================================================================

// ── CONFIGURE THESE 4 LINES ────────────────────────────────────


define('DB_HOST', 'localhost');
define('DB_NAME', 'edrppymy_optms_invoice');            // ← your database name
define('DB_USER', 'edrppymy_optms_invoice');          // ← Change to your MySQL username
define('DB_PASS', '1234@Optmsdatabase');              // ← Change to your MySQL password

// ───────────────────────────────────────────────────────────────

$msg = ''; $error = ''; $done = false;

// Connect to DB
try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    $error = 'Cannot connect to database: ' . $e->getMessage();
    $pdo = null;
}

// Load users if connected
$users = [];
if ($pdo) {
    try {
        $users = $pdo->query("SELECT id, name, email, role FROM users ORDER BY role, name")->fetchAll();
    } catch (Exception $e) {
        $error = 'Cannot load users. Make sure the database is set up: ' . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $userId   = (int)($_POST['user_id'] ?? 0);
    $newPass  = $_POST['new_pass']  ?? '';
    $confPass = $_POST['conf_pass'] ?? '';
    $secret   = trim($_POST['secret'] ?? '');

    // Simple anti-bot check
    if ($secret !== 'OPTMS2025') {
        $error = 'Wrong secret key. Edit this file and check the secret.';
    } elseif (!$userId) {
        $error = 'Please select a user.';
    } elseif (strlen($newPass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($newPass !== $confPass) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $userId]);
            if ($stmt->rowCount() > 0) {
                $user = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                $user->execute([$userId]);
                $u = $user->fetch();
                $done = true;
                $msg  = "Password updated for <strong>" . htmlspecialchars($u['name']) . "</strong> (" . htmlspecialchars($u['email']) . ")";
            } else {
                $error = 'User not found.';
            }
        } catch (Exception $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — OPTMS Invoice</title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Public Sans',sans-serif;background:linear-gradient(135deg,#1A2332,#263348 55%,#00897B);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:20px;padding:44px;width:100%;max-width:440px;box-shadow:0 24px 64px rgba(0,0,0,.35)}
.logo{width:54px;height:54px;background:linear-gradient(135deg,#00897B,#4DB6AC);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:900;color:#fff;margin:0 auto 12px}
h1{text-align:center;font-size:20px;font-weight:800;color:#1A2332;margin-bottom:4px}
.sub{text-align:center;color:#9CA3AF;font-size:13px;margin-bottom:28px}
.warn{background:#FEF3C7;border:1px solid #FCD34D;border-radius:10px;padding:12px 16px;font-size:12.5px;color:#92400E;margin-bottom:20px;line-height:1.6}
.warn strong{display:block;margin-bottom:3px}
.field{margin-bottom:16px}
.field label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px}
.field select,.field input{width:100%;padding:12px 14px;border:1.5px solid #E5E7EB;border-radius:9px;font-family:inherit;font-size:14px;color:#111;outline:none;transition:.2s;background:#fff}
.field select:focus,.field input:focus{border-color:#00897B;box-shadow:0 0 0 3px rgba(0,137,123,.12)}
.btn{width:100%;padding:14px;background:linear-gradient(135deg,#00897B,#00695C);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;margin-top:8px;transition:.2s}
.btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,137,123,.3)}
.msg{padding:14px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;line-height:1.6}
.msg.ok{background:#D1FAE5;border:1px solid #6EE7B7;color:#065F46}
.msg.err{background:#FEE2E2;border:1px solid #FECACA;color:#DC2626}
.success-box{text-align:center;padding:20px 0}
.success-box .tick{font-size:56px;margin-bottom:12px}
.success-box h2{color:#065F46;font-size:18px;margin-bottom:8px}
.success-box p{color:#374151;font-size:13px;line-height:1.7}
.delete-note{background:#FEE2E2;border:1px solid #FECACA;border-radius:10px;padding:14px 16px;font-size:13px;color:#DC2626;margin-top:20px;font-weight:600;text-align:center}
.login-btn{display:block;width:100%;padding:13px;background:#1A2332;color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;text-align:center;margin-top:14px}
.login-btn:hover{background:#263348}
hr{border:none;border-top:1px solid #E5E7EB;margin:16px 0}
.hint{font-size:11.5px;color:#9CA3AF;margin-top:5px}
</style>
</head>
<body>
<div class="card">
  <div class="logo">🔑</div>
  <h1>Reset Password</h1>
  <div class="sub">OPTMS Invoice Manager</div>

  <div class="warn">
    <strong>⚠️ Security Notice</strong>
    This file resets any user's password directly in the database.
    <strong>Delete it immediately after use.</strong>
  </div>

  <?php if ($done): ?>

    <div class="success-box">
      <div class="tick">✅</div>
      <h2>Password Updated!</h2>
      <p><?= $msg ?></p>
      <p style="margin-top:8px;color:#9CA3AF;font-size:12px">You can now log in with your new password.</p>
    </div>
    <a href="/" class="login-btn">→ Go to Login</a>
    <div class="delete-note">
      🗑️ DELETE this file from your server now!<br>
      <code style="font-size:11px;font-weight:400">rm reset_password.php</code>
    </div>

  <?php else: ?>

    <?php if ($error): ?>
    <div class="msg err">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($pdo && empty($users)): ?>
    <div class="msg err">No users found in the database. Run setup.php first.</div>
    <?php elseif ($pdo): ?>

    <form method="POST" autocomplete="off">

      <div class="field">
        <label>Select User</label>
        <select name="user_id" required>
          <option value="">-- Choose user --</option>
          <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= isset($_POST['user_id']) && $_POST['user_id'] == $u['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($u['name']) ?> &lt;<?= htmlspecialchars($u['email']) ?>&gt; [<?= $u['role'] ?>]
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <hr>

      <div class="field">
        <label>New Password</label>
        <input type="password" name="new_pass" placeholder="Min 6 characters" required minlength="6" autocomplete="new-password">
      </div>
      <div class="field">
        <label>Confirm Password</label>
        <input type="password" name="conf_pass" placeholder="Repeat password" required autocomplete="new-password">
      </div>

      <hr>

      <div class="field">
        <label>Secret Key <span style="font-weight:400;text-transform:none">(anti-bot)</span></label>
        <input type="text" name="secret" placeholder="Enter: OPTMS2025" required autocomplete="off">
        <div class="hint">Default secret key is <strong>OPTMS2025</strong> — you can change it at the top of this file.</div>
      </div>

      <button type="submit" class="btn">🔒 Reset Password</button>
    </form>

    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
