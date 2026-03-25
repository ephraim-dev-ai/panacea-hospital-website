<?php
// admin/change_password.php
require_once dirname(__FILE__) . '/../includes/helpers.php';
require_once dirname(__FILE__) . '/../includes/security.php';
requireLogin();

$admin  = currentAdmin();
$pdo    = db();
$error  = '';
$success= '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Get current hash
    $stmt = $pdo->prepare('SELECT password FROM admin_users WHERE id=?');
    $stmt->execute([$admin['id']]); $user = $stmt->fetch();

    if (!password_verify($current, $user['password'])) {
        $error = 'Current password is incorrect.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $pwErrors = validatePassword($new);
        if ($pwErrors) {
            $error = implode(' ', $pwErrors);
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare('UPDATE admin_users SET password=? WHERE id=?')
                ->execute([$hash, $admin['id']]);
            logActivity('Changed own password');
            securityLog('PASSWORD_CHANGED', "User: {$admin['username']}");
            $success = 'Password changed successfully!';
        }
    }
}

$pageTitle = 'Change Password';
require_once dirname(__FILE__) . '/../includes/layout_header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="form-card">
      <div class="form-card-head">
        <h4><i class="bi bi-key-fill me-2" style="color:var(--blue-bright)"></i>Change Password</h4>
      </div>
      <div class="form-card-body">

        <?php if ($error): ?>
          <div class="alert alert-danger border-0" style="border-radius:9px;font-size:.875rem">
            <i class="bi bi-exclamation-triangle me-1"></i><?= clean($error) ?>
          </div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert-success border-0" style="border-radius:9px;font-size:.875rem">
            <i class="bi bi-check-circle me-1"></i><?= clean($success) ?>
          </div>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf() ?>"/>
          <div class="mb-3">
            <label class="form-label">Current Password *</label>
            <input type="password" name="current_password" class="form-control" required/>
          </div>
          <div class="mb-3">
            <label class="form-label">New Password *</label>
            <input type="password" name="new_password" class="form-control" required/>
            <div style="font-size:.75rem;color:var(--muted);margin-top:4px">
              Min 8 chars · uppercase · lowercase · number
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label">Confirm New Password *</label>
            <input type="password" name="confirm_password" class="form-control" required/>
          </div>

          <!-- Password strength meter -->
          <div class="mb-4">
            <div style="height:4px;background:var(--border);border-radius:2px;overflow:hidden">
              <div id="strengthBar" style="height:100%;width:0;transition:all .3s;border-radius:2px"></div>
            </div>
            <div id="strengthText" style="font-size:.72rem;color:var(--muted);margin-top:4px"></div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">
              <i class="bi bi-key me-1"></i>Change Password
            </button>
            <a href="/panacea/admin/index.php" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>

        <div style="margin-top:24px;padding:16px;background:var(--bg);border-radius:10px;font-size:.8rem;color:var(--muted)">
          <strong style="color:var(--blue-deep);display:block;margin-bottom:8px">
            <i class="bi bi-shield-check me-1"></i>Password Requirements:
          </strong>
          <ul style="margin:0;padding-left:16px;line-height:1.8">
            <li>At least 8 characters long</li>
            <li>At least one uppercase letter (A-Z)</li>
            <li>At least one lowercase letter (a-z)</li>
            <li>At least one number (0-9)</li>
            <li>Avoid using your name or hospital name</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.querySelector('input[name="new_password"]').addEventListener('input', function() {
  const pw  = this.value;
  const bar = document.getElementById('strengthBar');
  const txt = document.getElementById('strengthText');
  let score = 0;
  if (pw.length >= 8)           score++;
  if (/[A-Z]/.test(pw))         score++;
  if (/[a-z]/.test(pw))         score++;
  if (/[0-9]/.test(pw))         score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;

  const levels = [
    { pct:'0%',   color:'transparent', label:'' },
    { pct:'20%',  color:'#c0162c',    label:'Very Weak' },
    { pct:'40%',  color:'#e07b1a',    label:'Weak' },
    { pct:'60%',  color:'#d08000',    label:'Fair' },
    { pct:'80%',  color:'#2e8dd4',    label:'Strong' },
    { pct:'100%', color:'#3aaa8c',    label:'Very Strong' },
  ];
  const lvl = levels[score];
  bar.style.width = lvl.pct;
  bar.style.background = lvl.color;
  txt.textContent = lvl.label;
  txt.style.color = lvl.color;
});
</script>

<?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; ?>
