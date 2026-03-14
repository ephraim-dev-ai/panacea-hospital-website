 <?php
require_once dirname(__FILE__) . '/../includes/helpers.php';
startSession();

if (isLoggedIn()) {
    header('Location: /panacea/admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = db()->prepare('SELECT * FROM admin_users WHERE (username=? OR email=?) AND is_active=1 LIMIT 1');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            startSession();
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $user['id'];
            $_SESSION['admin_user'] = $user['username'];
            $_SESSION['admin_name'] = $user['full_name'];
            $_SESSION['admin_role'] = $user['role'];

            db()->prepare('UPDATE admin_users SET last_login=NOW() WHERE id=?')
               ->execute([$user['id']]);

            header('Location: /panacea/admin/index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
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
      font-family: 'DM Sans', sans-serif;
      min-height: 100vh;
      background: linear-gradient(135deg, #0a2e5c 0%, #1a5fa0 60%, #3aaa8c 100%);
      display: flex; align-items: center; justify-content: center;
      padding: 20px;
    }
    .login-card {
      background: #fff; border-radius: 20px;
      padding: 44px 40px; width: 100%; max-width: 420px;
      box-shadow: 0 24px 80px rgba(10,46,92,.3);
    }
    .brand-row { display: flex; align-items: center; gap: 12px; margin-bottom: 32px; }
    .brand-ico {
      width: 48px; height: 48px; border-radius: 12px;
      background: linear-gradient(135deg, #1a5fa0, #3aaa8c);
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 1.4rem;
    }
    .brand-txt strong { display: block; font-family: 'Playfair Display',serif; font-size: 1.15rem; color: #0a2e5c; }
    .brand-txt span { font-size: .7rem; color: #7a8da8; text-transform: uppercase; letter-spacing: .06em; }
    h2 { font-family: 'Playfair Display',serif; color: #0a2e5c; font-size: 1.6rem; margin-bottom: 6px; }
    p.sub { color: #7a8da8; font-size: .875rem; margin-bottom: 28px; }
    .form-label { font-size: .8rem; font-weight: 600; color: #0a2e5c; }
    .form-control {
      border: 1.5px solid #e2e8f3; border-radius: 10px;
      padding: 11px 14px; font-size: .9rem;
    }
    .form-control:focus {
      border-color: #2e8dd4;
      box-shadow: 0 0 0 3px rgba(46,141,212,.12);
    }
    .btn-login {
      background: linear-gradient(135deg, #1a5fa0, #2e8dd4);
      color: #fff; border: none; border-radius: 10px;
      padding: 12px; font-weight: 600; font-size: .95rem;
      width: 100%; transition: all .3s; cursor: pointer;
    }
    .btn-login:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(26,95,160,.4); }
    .demo-box {
      background: #f0f4f9; border-radius: 10px;
      padding: 14px 16px; margin-top: 20px;
      font-size: .8rem; color: #7a8da8;
    }
    .demo-box strong { color: #0a2e5c; }
    .back-link {
      display: block; text-align: center; margin-top: 16px;
      color: #7a8da8; font-size: .82rem; text-decoration: none;
    }
    .back-link:hover { color: #1a5fa0; }
  </style>
</head>
<body>
<div class="login-card">
  <div class="brand-row">
    <div class="brand-ico"><i class="bi bi-hospital"></i></div>
    <div class="brand-txt">
      <strong>Panacea Hospital</strong>
      <span>Hawassa, Ethiopia</span>
    </div>
  </div>
  <h2>Admin Portal</h2>
  <p class="sub">Sign in to manage patients, appointments and hospital data.</p>

  <?php if ($error): ?>
    <div class="alert alert-danger py-2 px-3 mb-3"
         style="font-size:.875rem;border-radius:9px">
      <i class="bi bi-exclamation-triangle me-1"></i>
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf() ?>"/>
    <div class="mb-3">
      <label class="form-label">Username or Email</label>
      <input type="text" name="username" class="form-control"
             placeholder="admin"
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
             required autofocus/>
    </div>
    <div class="mb-4">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control"
             placeholder="••••••••" required/>
    </div>
    <button type="submit" class="btn-login">
      <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
    </button>
  </form>

  <div class="demo-box">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Default credentials:</strong>
    username <code>admin</code> · password <code>Admin@1234</code>
  </div>

  <a href="/panacea/index.php" class="back-link">
    <i class="bi bi-arrow-left me-1"></i>Back to Hospital Website
  </a>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


