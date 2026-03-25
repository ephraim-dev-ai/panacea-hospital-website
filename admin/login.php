<?php
// ============================================================
//  PANACEA HOSPITAL – Secure Admin Login
//  admin/login.php  (replace existing login.php)
// ============================================================
require_once dirname(__FILE__) . '/../includes/helpers.php';
require_once dirname(__FILE__) . '/../includes/security.php';
secureSession();

if (isLoggedIn()) {
    header('Location: /panacea/admin/index.php'); exit;
}

$ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$error = '';
$lockoutMsg = '';

// Check if IP is locked out
if (isLockedOut($ip)) {
    $mins = getLockoutRemaining($ip);
    $lockoutMsg = "Too many failed login attempts. Please try again in {$mins} minute(s).";
    securityLog('LOGIN_BLOCKED', "IP blocked for $mins more minutes");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$lockoutMsg) {
    verifyCsrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']      ?? '';

    if (!$username || !$password) {
        $error = 'Please fill in all fields.';
    } elseif (isLockedOut($ip)) {
        $mins = getLockoutRemaining($ip);
        $lockoutMsg = "Too many failed attempts. Try again in {$mins} minute(s).";
    } else {
        $stmt = db()->prepare('SELECT * FROM admin_users WHERE (username=? OR email=?) AND is_active=1 LIMIT 1');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // SUCCESS
            clearLoginAttempts($ip);
            secureSession();
            session_regenerate_id(true);

            $_SESSION['admin_id']        = $user['id'];
            $_SESSION['admin_user']      = $user['username'];
            $_SESSION['admin_name']      = $user['full_name'];
            $_SESSION['admin_role']      = $user['role'];
            $_SESSION['last_activity']   = time();
            $_SESSION['last_regenerated']= time();

            db()->prepare('UPDATE admin_users SET last_login=NOW() WHERE id=?')
                ->execute([$user['id']]);

            securityLog('LOGIN_SUCCESS', "User: {$user['username']}");

            header('Location: /panacea/admin/index.php'); exit;
        } else {
            // FAILED
            recordLoginAttempt($ip);
            $attempts = getLoginAttempts($ip);
            $remaining = MAX_LOGIN_ATTEMPTS - $attempts['attempts'];

            securityLog('LOGIN_FAILED', "Username: $username");

            if ($remaining <= 0) {
                $mins = getLockoutRemaining($ip);
                $lockoutMsg = "Account locked for {$mins} minute(s) due to too many failed attempts.";
            } else {
                $error = "Invalid username or password. {$remaining} attempt(s) remaining.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Admin Login – Panacea Hospital</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    body {
      font-family:'DM Sans',sans-serif;
      min-height:100vh;
      background:linear-gradient(135deg,#0a2e5c 0%,#1a5fa0 60%,#3aaa8c 100%);
      display:flex;align-items:center;justify-content:center;padding:20px;
    }
    .login-card {
      background:#fff;border-radius:20px;
      padding:44px 40px;width:100%;max-width:420px;
      box-shadow:0 24px 80px rgba(10,46,92,.3);
    }
    .brand-row{display:flex;align-items:center;gap:12px;margin-bottom:32px}
    .brand-ico{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#1a5fa0,#3aaa8c);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.4rem}
    .brand-txt strong{display:block;font-family:'Playfair Display',serif;font-size:1.15rem;color:#0a2e5c}
    .brand-txt span{font-size:.7rem;color:#7a8da8;text-transform:uppercase;letter-spacing:.06em}
    h2{font-family:'Playfair Display',serif;color:#0a2e5c;font-size:1.6rem;margin-bottom:6px}
    p.sub{color:#7a8da8;font-size:.875rem;margin-bottom:28px}
    .form-label{font-size:.8rem;font-weight:600;color:#0a2e5c}
    .form-control{border:1.5px solid #e2e8f3;border-radius:10px;padding:11px 14px;font-size:.9rem;transition:all .2s}
    .form-control:focus{border-color:#2e8dd4;box-shadow:0 0 0 3px rgba(46,141,212,.12)}
    .btn-login{background:linear-gradient(135deg,#1a5fa0,#2e8dd4);color:#fff;border:none;border-radius:10px;padding:13px;font-weight:600;font-size:.95rem;width:100%;transition:all .3s;cursor:pointer}
    .btn-login:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 6px 24px rgba(26,95,160,.4)}
    .btn-login:disabled{opacity:.7;cursor:not-allowed}

    /* Password toggle */
    .pw-wrap{position:relative}
    .pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#7a8da8;cursor:pointer;padding:0;font-size:1rem}

    /* Attempt indicator */
    .attempt-dots{display:flex;gap:6px;margin-top:8px}
    .attempt-dot{width:10px;height:10px;border-radius:50%;background:#e2e8f3;transition:background .3s}
    .attempt-dot.used{background:#c0162c}

    /* Lockout box */
    .lockout-box{background:#fff0f0;border:1px solid rgba(192,22,44,.3);border-radius:10px;padding:16px;text-align:center}
    .lockout-box i{font-size:2rem;color:#c0162c;display:block;margin-bottom:8px}
    .lockout-box p{color:#c0162c;font-size:.875rem;margin:0;font-weight:600}

    .back-link{text-align:center;margin-top:16px}
    .back-link a{color:#7a8da8;text-decoration:none;font-size:.82rem}
    .back-link a:hover{color:#1a5fa0}
  </style>
</head>
<body>
<div class="login-card">
  <div class="brand-row">
    <div class="brand-ico"><i class="bi bi-hospital"></i></div>
    <div class="brand-txt">
      <strong>Panacea Hospital</strong>
      <span>Staff Portal — Hawassa, Ethiopia</span>
    </div>
  </div>

  <?php if ($lockoutMsg): ?>
    <div class="lockout-box mb-4">
      <i class="bi bi-shield-lock-fill"></i>
      <p><?= htmlspecialchars($lockoutMsg) ?></p>
      <small style="color:#7a8da8;font-size:.78rem">Contact your administrator if you need help.</small>
    </div>
  <?php else: ?>
    <h2>Staff Login</h2>
    <p class="sub">Sign in to manage patients and hospital data.</p>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:.875rem;border-radius:9px">
        <i class="bi bi-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?>
      </div>
      <?php
      $attempts  = getLoginAttempts($ip);
      $usedDots  = min($attempts['attempts'], MAX_LOGIN_ATTEMPTS);
      ?>
      <div class="attempt-dots mb-3">
        <?php for ($i = 0; $i < MAX_LOGIN_ATTEMPTS; $i++): ?>
          <div class="attempt-dot <?= $i < $usedDots ? 'used' : '' ?>"></div>
        <?php endfor; ?>
        <span style="font-size:.72rem;color:#7a8da8;margin-left:4px">
          <?= $usedDots ?>/<?= MAX_LOGIN_ATTEMPTS ?> attempts
        </span>
      </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>"/>
      <div class="mb-3">
        <label class="form-label">Username or Email</label>
        <input type="text" name="username" class="form-control"
               placeholder="admin"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               required autofocus autocomplete="username"/>
      </div>
      <div class="mb-4">
        <label class="form-label">Password</label>
        <div class="pw-wrap">
          <input type="password" name="password" id="pwField"
                 class="form-control" placeholder="••••••••"
                 required autocomplete="current-password"
                 style="padding-right:44px"/>
          <button type="button" class="pw-toggle" onclick="togglePw()" id="pwBtn">
            <i class="bi bi-eye" id="pwIcon"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn-login" <?= $lockoutMsg ? 'disabled' : '' ?>>
        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
      </button>
    </form>
  <?php endif; ?>

  <div class="back-link">
    <a href="/panacea/"><i class="bi bi-arrow-left me-1"></i>Back to Hospital Website</a>
  </div>
</div>

<script>
function togglePw() {
  const f = document.getElementById('pwField');
  const i = document.getElementById('pwIcon');
  if (f.type === 'password') {
    f.type = 'text';
    i.className = 'bi bi-eye-slash';
  } else {
    f.type = 'password';
    i.className = 'bi bi-eye';
  }
}
</script>
</body>
</html>
