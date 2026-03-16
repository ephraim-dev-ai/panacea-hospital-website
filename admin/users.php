<?php
require_once dirname(__FILE__) . '/../includes/helpers.php';
requireLogin();

// Only superadmin and admin can manage users
$admin = currentAdmin();
if (!in_array($admin['role'], ['superadmin', 'admin'])) {
    flash('main', 'You do not have permission to manage staff accounts.', 'error');
    header('Location: /panacea/admin/index.php'); exit;
}

$pdo    = db();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── DELETE ────────────────────────────────────────────────
if ($action === 'delete' && $id) {
    if ($id === (int)$admin['id']) {
        flash('main', 'You cannot delete your own account.', 'error');
        header('Location: /panacea/admin/users.php'); exit;
    }
    $pdo->prepare('DELETE FROM admin_users WHERE id=?')->execute([$id]);
    logActivity('Deleted staff account', "ID:$id");
    flash('main', 'Staff account deleted.', 'success');
    header('Location: /panacea/admin/users.php'); exit;
}

// ── TOGGLE ACTIVE ─────────────────────────────────────────
if ($action === 'toggle' && $id) {
    if ($id === (int)$admin['id']) {
        flash('main', 'You cannot deactivate your own account.', 'error');
        header('Location: /panacea/admin/users.php'); exit;
    }
    $pdo->prepare('UPDATE admin_users SET is_active = !is_active WHERE id=?')->execute([$id]);
    logActivity('Toggled staff account status', "ID:$id");
    flash('main', 'Account status updated.', 'success');
    header('Location: /panacea/admin/users.php'); exit;
}

// ── SAVE ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    verifyCsrf();
    $f = [
        'username'  => trim($_POST['username']  ?? ''),
        'email'     => trim($_POST['email']     ?? ''),
        'full_name' => trim($_POST['full_name'] ?? ''),
        'role'      => $_POST['role']           ?? 'receptionist',
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    $password = $_POST['password'] ?? '';
    $errors = [];

    if (!$f['username']) $errors[] = 'Username is required.';
    if (!$f['email'])    $errors[] = 'Email is required.';
    if ($action === 'add' && !$password) $errors[] = 'Password is required.';
    if ($password && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

    // Check duplicate username/email
    if ($action === 'add') {
        $check = $pdo->prepare('SELECT id FROM admin_users WHERE username=? OR email=?');
        $check->execute([$f['username'], $f['email']]);
        if ($check->fetch()) $errors[] = 'Username or email already exists.';
    }

    if ($errors) {
        flash('main', implode(' ', $errors), 'error');
    } else {
        if ($action === 'add') {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare('INSERT INTO admin_users (username,email,full_name,role,password,is_active) VALUES (?,?,?,?,?,?)')
                ->execute([$f['username'],$f['email'],$f['full_name'],$f['role'],$hash,$f['is_active']]);
            logActivity('Created staff account', $f['username']);
            flash('main', 'Staff account for ' . $f['full_name'] . ' created successfully.', 'success');
        } else {
            $pdo->prepare('UPDATE admin_users SET username=?,email=?,full_name=?,role=?,is_active=? WHERE id=?')
                ->execute([$f['username'],$f['email'],$f['full_name'],$f['role'],$f['is_active'],$id]);
            if ($password) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE admin_users SET password=? WHERE id=?')->execute([$hash, $id]);
            }
            logActivity('Updated staff account', $f['username']);
            flash('main', 'Staff account updated.', 'success');
        }
        header('Location: /panacea/admin/users.php'); exit;
    }
}

// ── ADD / EDIT FORM ───────────────────────────────────────
if (in_array($action, ['add','edit'])) {
    $user = [];
    if ($action === 'edit' && $id) {
        $s = $pdo->prepare('SELECT * FROM admin_users WHERE id=?');
        $s->execute([$id]); $user = $s->fetch() ?: [];
    }
    $v = fn($k) => htmlspecialchars($user[$k] ?? $_POST[$k] ?? '', ENT_QUOTES);
    $pageTitle = $action === 'add' ? 'Add Staff Account' : 'Edit Staff Account';
    require_once dirname(__FILE__) . '/../includes/layout_header.php';

    // Role permissions info
    $roleInfo = [
        'superadmin'   => ['color'=>'#c0162c', 'desc'=>'Full access including staff management'],
        'admin'        => ['color'=>'#1a5fa0', 'desc'=>'Full access to all hospital data'],
        'receptionist' => ['color'=>'#3aaa8c', 'desc'=>'Patients, appointments, messages only'],
        'doctor'       => ['color'=>'#7c4ddc', 'desc'=>'Medical records and patient profiles only'],
    ];
    ?>
    <div class="d-flex align-items-center gap-3 mb-4">
      <a href="/panacea/admin/users.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
    </div>

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="form-card">
          <div class="form-card-head">
            <h4><?= $pageTitle ?></h4>
          </div>
          <div class="form-card-body">
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= csrf() ?>"/>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Full Name *</label>
                  <input type="text" name="full_name" class="form-control"
                         placeholder="e.g. Dr. Tadesse Bekele"
                         value="<?= $v('full_name') ?>" required/>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Username *</label>
                  <div style="position:relative">
                    <input type="text" name="username" class="form-control"
                           placeholder="e.g. dr.tadesse"
                           value="<?= $v('username') ?>" required
                           <?= $action === 'edit' ? 'readonly' : '' ?>/>
                    <?php if ($action === 'edit'): ?>
                      <small style="color:var(--muted);font-size:.72rem">Username cannot be changed</small>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email Address *</label>
                  <input type="email" name="email" class="form-control"
                         placeholder="staff@panaceahospital.et"
                         value="<?= $v('email') ?>" required/>
                </div>
                <div class="col-md-6">
                  <label class="form-label">
                    <?= $action === 'add' ? 'Password *' : 'New Password' ?>
                  </label>
                  <input type="password" name="password" class="form-control"
                         placeholder="<?= $action === 'add' ? 'Min 6 characters' : 'Leave blank to keep current' ?>"/>
                  <?php if ($action === 'edit'): ?>
                    <small style="color:var(--muted);font-size:.72rem">Leave blank to keep existing password</small>
                  <?php endif; ?>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Role *</label>
                  <select name="role" class="form-select" id="roleSelect" onchange="updateRoleInfo()">
                    <?php foreach (['receptionist'=>'Receptionist','doctor'=>'Doctor','admin'=>'Admin','superadmin'=>'Super Admin'] as $val => $label): ?>
                      <option value="<?= $val ?>" <?= ($v('role') === $val) ? 'selected' : '' ?>>
                        <?= $label ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Status</label>
                  <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1"
                           <?= ($user['is_active'] ?? 1) ? 'checked' : '' ?>/>
                    <label class="form-check-label">Active (can log in)</label>
                  </div>
                </div>

                <!-- Role info box -->
                <div class="col-12">
                  <div id="roleInfoBox" style="border-radius:10px;padding:14px 16px;font-size:.85rem;transition:all .3s"></div>
                </div>

                <div class="col-12 d-flex gap-2 mt-2">
                  <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-lg me-1"></i>
                    <?= $action === 'add' ? 'Create Account' : 'Save Changes' ?>
                  </button>
                  <a href="/panacea/admin/users.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Role Guide -->
      <div class="col-lg-4">
        <div class="data-card p-4">
          <h5 style="font-family:'Playfair Display',serif;color:var(--blue-deep);margin-bottom:20px">
            <i class="bi bi-shield-check me-2" style="color:var(--blue-bright)"></i>Role Permissions
          </h5>
          <?php foreach ($roleInfo as $role => $info): ?>
          <div style="margin-bottom:16px;padding:12px;background:var(--bg);border-radius:10px;border-left:3px solid <?= $info['color'] ?>">
            <div style="font-weight:600;font-size:.85rem;color:<?= $info['color'] ?>;margin-bottom:4px;text-transform:capitalize">
              <?= $role ?>
            </div>
            <div style="font-size:.78rem;color:var(--muted)"><?= $info['desc'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <script>
    const roleData = {
      superadmin:   { color:'#c0162c', bg:'#fff0f0', text:'Full system access. Can manage staff accounts, all hospital data, and system settings.' },
      admin:        { color:'#1a5fa0', bg:'#e8f2fb', text:'Full hospital data access. Can manage patients, appointments, records, doctors and departments.' },
      receptionist: { color:'#3aaa8c', bg:'#edf7f3', text:'Can register patients, manage appointments, and read contact messages. Cannot manage doctors or departments.' },
      doctor:       { color:'#7c4ddc', bg:'#f0ebfc',  text:'Can add medical records and view patient profiles. Cannot manage appointments or system settings.' },
    };
    function updateRoleInfo() {
      const role = document.getElementById('roleSelect').value;
      const d    = roleData[role];
      const box  = document.getElementById('roleInfoBox');
      box.style.background   = d.bg;
      box.style.borderLeft   = '3px solid ' + d.color;
      box.style.color        = d.color;
      box.innerHTML = '<i class="bi bi-info-circle me-2"></i><strong style="text-transform:capitalize">' + role + ':</strong> ' + d.text;
    }
    updateRoleInfo();
    </script>
    <?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; exit;
}

// ── LIST ──────────────────────────────────────────────────
$users = $pdo->query("SELECT * FROM admin_users ORDER BY
    FIELD(role,'superadmin','admin','receptionist','doctor'), full_name")->fetchAll();

$roleColors = [
    'superadmin'   => 'danger',
    'admin'        => 'primary',
    'receptionist' => 'success',
    'doctor'       => 'purple',
];

$pageTitle = 'Staff Accounts';
require_once dirname(__FILE__) . '/../includes/layout_header.php';
?>

<style>
.bg-purple { background-color: #7c4ddc !important; }
.role-card {
    background: var(--card);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    padding: 20px;
    text-align: center;
    transition: all .2s;
}
.role-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.role-avatar {
    width: 56px; height: 56px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; font-weight: 700;
    margin: 0 auto 12px;
    color: #fff;
}
</style>

<!-- Role summary cards -->
<div class="row g-3 mb-4">
  <?php
  $roleSummary = [
    'superadmin'   => ['label'=>'Super Admins',   'icon'=>'bi-shield-fill-check', 'color'=>'#c0162c', 'bg'=>'#fff0f0'],
    'admin'        => ['label'=>'Admins',          'icon'=>'bi-person-gear',        'color'=>'#1a5fa0', 'bg'=>'#e8f2fb'],
    'receptionist' => ['label'=>'Receptionists',   'icon'=>'bi-person-badge',       'color'=>'#3aaa8c', 'bg'=>'#edf7f3'],
    'doctor'       => ['label'=>'Doctors',         'icon'=>'bi-heart-pulse',        'color'=>'#7c4ddc', 'bg'=>'#f0ebfc'],
  ];
  foreach ($roleSummary as $role => $info):
    $count = count(array_filter($users, fn($u) => $u['role'] === $role));
  ?>
  <div class="col-6 col-md-3">
    <div class="role-card">
      <div class="role-avatar" style="background:<?= $info['color'] ?>">
        <i class="bi <?= $info['icon'] ?>"></i>
      </div>
      <div style="font-size:1.6rem;font-weight:700;color:var(--blue-deep);font-family:'Playfair Display',serif"><?= $count ?></div>
      <div style="font-size:.78rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em"><?= $info['label'] ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="d-flex justify-content-end mb-4">
  <a href="?action=add" class="btn btn-primary">
    <i class="bi bi-person-plus me-1"></i>Add Staff Account
  </a>
</div>

<div class="data-card">
  <div class="data-card-head">
    <i class="bi bi-people-fill text-primary me-2"></i>
    <h5>All Staff Accounts</h5>
    <span style="font-size:.78rem;color:var(--muted)"><?= count($users) ?> accounts</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>Staff Member</th>
          <th>Username</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Last Login</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:12px">
              <div style="width:38px;height:38px;border-radius:10px;
                          background:linear-gradient(135deg,var(--blue-mid),var(--blue-bright));
                          display:flex;align-items:center;justify-content:center;
                          color:#fff;font-weight:700;font-size:.9rem;flex-shrink:0">
                <?= strtoupper(substr($u['full_name'] ?: $u['username'], 0, 1)) ?>
              </div>
              <div>
                <div style="font-weight:600;font-size:.88rem;color:var(--blue-deep)">
                  <?= htmlspecialchars($u['full_name'] ?: '—') ?>
                </div>
                <?php if ((int)$u['id'] === (int)$admin['id']): ?>
                  <span style="font-size:.68rem;background:#e8f2fb;color:var(--blue-mid);
                               padding:2px 8px;border-radius:20px">You</span>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td>
            <code style="font-size:.8rem;background:var(--bg);padding:3px 8px;border-radius:6px">
              <?= htmlspecialchars($u['username']) ?>
            </code>
          </td>
          <td style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($u['email']) ?></td>
          <td>
            <?php
            $rc = ['superadmin'=>'danger','admin'=>'primary','receptionist'=>'success','doctor'=>'purple'];
            $ri = ['superadmin'=>'bi-shield-fill-check','admin'=>'bi-person-gear','receptionist'=>'bi-person-badge','doctor'=>'bi-heart-pulse'];
            $c  = $rc[$u['role']] ?? 'secondary';
            $ic = $ri[$u['role']] ?? 'bi-person';
            ?>
            <span class="badge bg-<?= $c ?> d-flex align-items-center gap-1" style="width:fit-content;font-size:.72rem;padding:5px 10px">
              <i class="bi <?= $ic ?>"></i>
              <?= ucfirst($u['role']) ?>
            </span>
          </td>
          <td>
            <?php if ($u['is_active']): ?>
              <span class="badge bg-success" style="font-size:.72rem">Active</span>
            <?php else: ?>
              <span class="badge bg-secondary" style="font-size:.72rem">Inactive</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.78rem;color:var(--muted)">
            <?= $u['last_login'] ? date('d M Y, H:i', strtotime($u['last_login'])) : 'Never' ?>
          </td>
          <td>
            <div class="d-flex gap-1">
              <a href="?action=edit&id=<?= $u['id'] ?>"
                 class="btn btn-sm btn-outline-secondary" title="Edit">
                <i class="bi bi-pencil"></i>
              </a>
              <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
                <a href="?action=toggle&id=<?= $u['id'] ?>"
                   class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                   title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                  <i class="bi <?= $u['is_active'] ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                </a>
                <a href="?action=delete&id=<?= $u['id'] ?>"
                   class="btn btn-sm btn-outline-danger"
                   data-confirm="Delete account for <?= htmlspecialchars($u['full_name'] ?: $u['username']) ?>? This cannot be undone."
                   title="Delete">
                  <i class="bi bi-trash"></i>
                </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Default passwords info box -->
<div class="mt-4 p-4" style="background:#fff8e8;border-radius:14px;border:1px solid #f6b83d40">
  <h6 style="color:#d08000;margin-bottom:12px">
    <i class="bi bi-key-fill me-2"></i>Staff Login Instructions
  </h6>
  <p style="font-size:.85rem;color:var(--gray-dark);margin-bottom:8px">
    Share these details with each staff member:
  </p>
  <div class="row g-2" style="font-size:.82rem">
    <div class="col-md-4">
      <div style="background:#fff;border-radius:8px;padding:10px 14px">
        <strong style="color:var(--blue-deep)">Login URL:</strong><br/>
        <code>localhost/panacea/admin/login.php</code>
      </div>
    </div>
    <div class="col-md-4">
      <div style="background:#fff;border-radius:8px;padding:10px 14px">
        <strong style="color:var(--blue-deep)">Username:</strong><br/>
        As set when creating account
      </div>
    </div>
    <div class="col-md-4">
      <div style="background:#fff;border-radius:8px;padding:10px 14px">
        <strong style="color:var(--blue-deep)">Password:</strong><br/>
        As set when creating account
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; ?>
