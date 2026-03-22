<?php
// ================================================================
//  OPTMS Invoice Manager — reset_password.php
//  Upload as-is. Enter DB credentials IN THE BROWSER FORM.
//  Delete this file after use.
// ================================================================

$error = '';
$done  = false;
$pdo   = null;
$users = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['db_user'])) {
    $DB_HOST = trim($_POST['db_host'] ?? 'localhost');
    $DB_NAME = trim($_POST['db_name'] ?? '');
    $DB_USER = trim($_POST['db_user'] ?? '');
    $DB_PASS = $_POST['db_pass'] ?? '';
    try {
        $pdo = new PDO(
            "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
            $DB_USER, $DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $users = $pdo->query("SELECT id, name, email, role FROM users ORDER BY role, name")->fetchAll();
    } catch (PDOException $e) {
        $error = $e->getMessage();
        $pdo   = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo && !empty($_POST['new_pass'])) {
    $userId  = (int)($_POST['user_id'] ?? 0);
    $newPass = $_POST['new_pass']  ?? '';
    $confPas = $_POST['conf_pass'] ?? '';
    if (!$userId)                    { $error = 'Please select a user.'; }
    elseif (strlen($newPass) < 6)    { $error = 'Password must be at least 6 characters.'; }
    elseif ($newPass !== $confPas)   { $error = 'Passwords do not match.'; }
    else {
        $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
        $u = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
        $u->execute([$userId]); $u = $u->fetch();
        $done = true;
        $msg  = "Password updated for <strong>" . htmlspecialchars($u['name'] ?? '') . "</strong> &lt;" . htmlspecialchars($u['email'] ?? '') . "&gt;";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — OPTMS</title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Public Sans',sans-serif;background:linear-gradient(135deg,#1A2332,#263348 55%,#00897B);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:20px;padding:44px;width:100%;max-width:460px;box-shadow:0 24px 64px rgba(0,0,0,.35)}
.logo{width:54px;height:54px;background:linear-gradient(135deg,#00897B,#4DB6AC);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 12px}
h1{text-align:center;font-size:20px;font-weight:800;color:#1A2332;margin-bottom:4px}
.sub{text-align:center;color:#9CA3AF;font-size:13px;margin-bottom:24px}
.stitle{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#9CA3AF;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid #f0f0f0}
.field{margin-bottom:14px}
.field label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px}
.field input,.field select{width:100%;padding:11px 14px;border:1.5px solid #E5E7EB;border-radius:9px;font-family:inherit;font-size:14px;color:#111;outline:none;transition:.2s;background:#fff}
.field input:focus,.field select:focus{border-color:#00897B;box-shadow:0 0 0 3px rgba(0,137,123,.12)}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,#00897B,#00695C);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;margin-top:6px;transition:.2s}
.btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,137,123,.3)}
.btn2{background:linear-gradient(135deg,#374151,#1A2332)}
.err{background:#FEE2E2;border:1px solid #FECACA;border-radius:9px;padding:12px 14px;font-size:13px;color:#DC2626;margin-bottom:16px;word-break:break-word}
.ok{background:#D1FAE5;border:1px solid #6EE7B7;border-radius:9px;padding:14px;font-size:13px;color:#065F46;margin-bottom:16px;line-height:1.8}
.warn{background:#FEF3C7;border:1px solid #FCD34D;border-radius:9px;padding:11px 14px;font-size:12px;color:#92400E;margin-bottom:18px}
.badge{display:inline-flex;align-items:center;gap:6px;background:#D1FAE5;color:#065F46;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;margin-bottom:16px}
.del{background:#FEE2E2;border:1px solid #FECACA;border-radius:10px;padding:13px;text-align:center;font-size:13px;color:#DC2626;font-weight:600;margin-top:16px}
.login{display:block;text-align:center;padding:12px;background:#1A2332;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;margin-top:12px}
.login:hover{background:#263348}
hr{border:none;border-top:1px solid #f0f0f0;margin:18px 0}
.hint{font-size:11px;color:#9CA3AF;margin-top:4px}
</style>
</head>
<body>
<div class="card">
  <div class="logo">🔑</div>
  <h1>Reset Password</h1>
  <div class="sub">OPTMS Invoice Manager</div>

<?php if ($done): ?>
  <div style="text-align:center;font-size:52px;margin-bottom:12px">✅</div>
  <div class="ok"><?= $msg ?><br><br>You can now log in with your new password.</div>
  <a href="/" class="login">→ Go to Login</a>
  <div class="del">⚠️ DELETE reset_password.php from your server now!<br><small style="font-weight:400">Use cPanel File Manager or FTP</small></div>

<?php else: ?>

  <div class="warn">⚠️ Delete this file immediately after use.</div>

  <?php if ($error): ?><div class="err">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST" autocomplete="off">

    <div class="stitle">Step 1 — Enter Database Credentials</div>

    <?php if ($pdo): ?>
      <div class="badge">✓ Connected to database: <?= htmlspecialchars($_POST['db_name'] ?? '') ?></div>
      <input type="hidden" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>">
      <input type="hidden" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>">
      <input type="hidden" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>">
      <input type="hidden" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>">
    <?php else: ?>
      <div class="g2">
        <div class="field">
          <label>DB Host</label>
          <input type="text" name="db_host" value="localhost">
        </div>
        <div class="field">
          <label>Database Name</label>
          <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" placeholder="optms_invoice" required>
        </div>
      </div>
      <div class="g2">
        <div class="field">
          <label>MySQL Username</label>
          <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" placeholder="root" required>
        </div>
        <div class="field">
          <label>MySQL Password</label>
          <input type="password" name="db_pass" placeholder="(blank if none)">
        </div>
      </div>
      <div class="hint" style="margin-bottom:16px">Find credentials in cPanel → MySQL Databases</div>
    <?php endif; ?>

    <?php if ($pdo && count($users) > 0): ?>
      <hr>
      <div class="stitle">Step 2 — Pick User &amp; Set New Password</div>
      <div class="field">
        <label>User Account</label>
        <select name="user_id" required>
          <option value="">-- Select user --</option>
          <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= (isset($_POST['user_id']) && $_POST['user_id'] == $u['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($u['name']) ?> — <?= htmlspecialchars($u['email']) ?> [<?= $u['role'] ?>]
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="g2">
        <div class="field">
          <label>New Password</label>
          <input type="password" name="new_pass" placeholder="Min 6 chars" required minlength="6" autocomplete="new-password">
        </div>
        <div class="field">
          <label>Confirm Password</label>
          <input type="password" name="conf_pass" placeholder="Repeat" required autocomplete="new-password">
        </div>
      </div>
      <button type="submit" class="btn">🔒 Reset Password</button>

    <?php elseif ($pdo && count($users) === 0): ?>
      <div class="err">No users found. Run setup.php first.</div>

    <?php else: ?>
      <button type="submit" class="btn btn2">Connect to Database →</button>
    <?php endif; ?>

  </form>
<?php endif; ?>
</div>
</body>
</html>
