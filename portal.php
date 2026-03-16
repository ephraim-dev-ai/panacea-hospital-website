<?php
// ============================================================
//  PANACEA HOSPITAL – Patient Portal
//  Single file handles: login, register, dashboard, 
//  appointments, records, profile, logout
// ============================================================
require_once dirname(__FILE__) . '/config/database.php';

// ── Session ──────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

$pdo  = db();
$page = $_GET['page'] ?? 'home';

// ── Helpers ───────────────────────────────────────────────
function pclean(?string $v): string {
    return htmlspecialchars(trim($v ?? ''), ENT_QUOTES, 'UTF-8');
}
function patientLoggedIn(): bool {
    return isset($_SESSION['patient_id']);
}
function requirePatientLogin(): void {
    if (!patientLoggedIn()) {
        header('Location: /panacea/portal.php?page=login'); exit;
    }
}
function currentPatient(): array {
    return [
        'id'         => $_SESSION['patient_id']   ?? 0,
        'name'       => $_SESSION['patient_name'] ?? '',
        'patient_id' => $_SESSION['patient_pid']  ?? '',
    ];
}

// ── LOGOUT ────────────────────────────────────────────────
if ($page === 'logout') {
    session_destroy();
    header('Location: /panacea/portal.php?page=login'); exit;
}

// ── REGISTER ──────────────────────────────────────────────
$regError = ''; $regSuccess = '';
if ($page === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = [
        'full_name'     => trim($_POST['full_name']     ?? ''),
        'gender'        => $_POST['gender']             ?? 'Male',
        'date_of_birth' => $_POST['date_of_birth']      ?? '',
        'phone'         => trim($_POST['phone']         ?? ''),
        'email'         => trim($_POST['email']         ?? ''),
        'password'      => $_POST['password']           ?? '',
        'confirm'       => $_POST['confirm_password']   ?? '',
        'address'       => trim($_POST['address']       ?? ''),
        'blood_group'   => $_POST['blood_group']        ?? 'Unknown',
    ];

    if (!$f['full_name'] || !$f['phone'] || !$f['date_of_birth'] || !$f['password']) {
        $regError = 'Please fill in all required fields.';
    } elseif (strlen($f['password']) < 6) {
        $regError = 'Password must be at least 6 characters.';
    } elseif ($f['password'] !== $f['confirm']) {
        $regError = 'Passwords do not match.';
    } else {
        // Check duplicate phone
        $check = $pdo->prepare('SELECT id FROM patients WHERE phone=?');
        $check->execute([$f['phone']]);
        if ($check->fetch()) {
            $regError = 'A patient with this phone number already exists. Please login instead.';
        } else {
            // Generate patient ID
            $year  = date('Y');
            $count = (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE YEAR(registered_at)=$year")->fetchColumn() + 1;
            $pid   = sprintf('PH-%s-%04d', $year, $count);
            $hash  = password_hash($f['password'], PASSWORD_BCRYPT);

            $pdo->prepare('INSERT INTO patients
                (patient_id,full_name,gender,date_of_birth,phone,email,address,blood_group,portal_password,status)
                VALUES (?,?,?,?,?,?,?,?,?,\'Active\')')
                ->execute([$pid,$f['full_name'],$f['gender'],$f['date_of_birth'],
                           $f['phone'],$f['email'],$f['address'],$f['blood_group'],$hash]);

            $regSuccess = 'Account created! Your Patient ID is <strong>' . $pid . '</strong>. You can now login.';
        }
    }
}

// ── LOGIN ─────────────────────────────────────────────────
$loginError = '';
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone    = trim($_POST['phone']    ?? '');
    $password = $_POST['password']      ?? '';

    $stmt = $pdo->prepare('SELECT * FROM patients WHERE phone=? OR patient_id=? LIMIT 1');
    $stmt->execute([$phone, $phone]);
    $patient = $stmt->fetch();

    if ($patient && !empty($patient['portal_password']) &&
        password_verify($password, $patient['portal_password'])) {
        session_regenerate_id(true);
        $_SESSION['patient_id']   = $patient['id'];
        $_SESSION['patient_name'] = $patient['full_name'];
        $_SESSION['patient_pid']  = $patient['patient_id'];
        $pdo->prepare('UPDATE patients SET last_portal_login=NOW() WHERE id=?')
            ->execute([$patient['id']]);
        header('Location: /panacea/portal.php?page=dashboard'); exit;
    } else {
        $loginError = 'Invalid phone number/Patient ID or password.';
    }
}

// ── BOOK APPOINTMENT ──────────────────────────────────────
$bookMsg = ''; $bookErr = '';
if ($page === 'book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePatientLogin();
    $pat = currentPatient();
    $patInfo = $pdo->prepare('SELECT * FROM patients WHERE id=?');
    $patInfo->execute([$pat['id']]); $patInfo = $patInfo->fetch();

    $deptId = (int)($_POST['department_id'] ?? 0);
    $date   = $_POST['appt_date']   ?? '';
    $time   = $_POST['appt_time']   ?? 'Morning (7AM-12PM)';
    $reason = trim($_POST['reason'] ?? '');

    if (!$deptId || !$date) {
        $bookErr = 'Please select a department and date.';
    } elseif (strtotime($date) < strtotime('today')) {
        $bookErr = 'Please select a future date.';
    } else {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn() + 1;
        $ref   = sprintf('APT-%08d', $count);
        $pdo->prepare('INSERT INTO appointments
            (ref_number,patient_id,patient_name,patient_phone,patient_email,
             department_id,appt_date,appt_time,reason,status)
            VALUES (?,?,?,?,?,?,?,?,?,\'Pending\')')
            ->execute([$ref,$pat['id'],$patInfo['full_name'],$patInfo['phone'],
                       $patInfo['email'],$deptId,$date,$time,$reason]);
        $bookMsg = 'Appointment booked! Reference: <strong>' . $ref . '</strong>. We will confirm within 24 hours.';
    }
}

// ── LAYOUT ────────────────────────────────────────────────
$departments = $pdo->query('SELECT * FROM departments WHERE is_active=1 ORDER BY name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Patient Portal – Panacea Hospital</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --blue-deep:#0a2e5c;--blue-mid:#1a5fa0;--blue-bright:#2e8dd4;
      --green:#3aaa8c;--red:#c0162c;--bg:#f0f4f9;--card:#fff;
      --border:#e2e8f3;--muted:#7a8da8;--text:#1a2b42;
      --shadow:0 2px 16px rgba(10,46,92,.08);
      --radius:14px;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

    /* NAV */
    .portal-nav{
      background:#fff;border-bottom:1px solid var(--border);
      padding:14px 0;position:sticky;top:0;z-index:100;
      box-shadow:0 1px 8px rgba(10,46,92,.05);
    }
    .nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none}
    .nav-brand-ico{width:40px;height:40px;background:linear-gradient(135deg,var(--blue-mid),var(--green));border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem}
    .nav-brand strong{font-family:'Playfair Display',serif;color:var(--blue-deep);font-size:1.1rem}
    .nav-brand span{font-size:.7rem;color:var(--muted);display:block;text-transform:uppercase;letter-spacing:.05em}
    .portal-badge{background:linear-gradient(135deg,var(--blue-mid),var(--blue-bright));color:#fff;padding:4px 14px;border-radius:20px;font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em}
    .nav-link-portal{color:var(--muted);text-decoration:none;font-size:.875rem;font-weight:500;padding:8px 12px;border-radius:8px;transition:all .2s}
    .nav-link-portal:hover{background:var(--bg);color:var(--blue-mid)}
    .nav-link-portal.active{color:var(--blue-mid);background:var(--bg)}
    .btn-nav-primary{background:linear-gradient(135deg,var(--blue-mid),var(--blue-bright));color:#fff;border:none;padding:9px 20px;border-radius:9px;font-weight:600;font-size:.85rem;text-decoration:none;transition:all .2s}
    .btn-nav-primary:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(26,95,160,.35);color:#fff}

    /* AUTH PAGES */
    .auth-wrap{min-height:calc(100vh - 70px);display:flex;align-items:center;justify-content:center;padding:40px 20px}
    .auth-card{background:#fff;border-radius:20px;padding:44px 40px;width:100%;max-width:480px;box-shadow:0 8px 40px rgba(10,46,92,.12)}
    .auth-card h2{font-family:'Playfair Display',serif;color:var(--blue-deep);font-size:1.8rem;margin-bottom:8px}
    .auth-card p.sub{color:var(--muted);font-size:.875rem;margin-bottom:28px}
    .form-label{font-size:.8rem;font-weight:600;color:var(--blue-deep);margin-bottom:5px}
    .form-control,.form-select{border:1.5px solid var(--border);border-radius:9px;padding:11px 14px;font-size:.9rem;transition:all .2s}
    .form-control:focus,.form-select:focus{border-color:var(--blue-bright);box-shadow:0 0 0 3px rgba(46,141,212,.12)}
    .btn-auth{background:linear-gradient(135deg,var(--blue-mid),var(--blue-bright));color:#fff;border:none;border-radius:10px;padding:13px;font-weight:600;font-size:.95rem;width:100%;cursor:pointer;transition:all .2s}
    .btn-auth:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(26,95,160,.4)}
    .auth-switch{text-align:center;margin-top:20px;font-size:.875rem;color:var(--muted)}
    .auth-switch a{color:var(--blue-mid);text-decoration:none;font-weight:600}

    /* DASHBOARD */
    .portal-wrap{max-width:1100px;margin:0 auto;padding:32px 20px}
    .welcome-banner{background:linear-gradient(135deg,var(--blue-deep),var(--blue-mid));border-radius:var(--radius);padding:28px 32px;color:#fff;margin-bottom:28px;position:relative;overflow:hidden}
    .welcome-banner::after{content:'';position:absolute;right:-40px;top:-40px;width:200px;height:200px;background:rgba(255,255,255,.05);border-radius:50%}
    .welcome-banner h2{font-family:'Playfair Display',serif;font-size:1.6rem;margin-bottom:6px}
    .welcome-banner p{color:rgba(255,255,255,.7);font-size:.9rem;margin:0}
    .welcome-banner .pid-badge{display:inline-block;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);padding:4px 14px;border-radius:20px;font-size:.75rem;margin-bottom:12px;backdrop-filter:blur(8px)}

    .stat-card{background:#fff;border-radius:var(--radius);padding:20px 22px;border:1px solid var(--border);box-shadow:var(--shadow);display:flex;align-items:center;gap:16px;transition:transform .2s}
    .stat-card:hover{transform:translateY(-3px)}
    .stat-ico{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0}
    .stat-val{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;color:var(--blue-deep);line-height:1}
    .stat-lbl{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-top:3px}

    .section-card{background:#fff;border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);margin-bottom:24px;overflow:hidden}
    .section-card-head{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
    .section-card-head h5{font-family:'Playfair Display',serif;color:var(--blue-deep);font-size:1.05rem;margin:0;flex:1}
    .section-card-body{padding:0}
    .table{margin:0;font-size:.875rem}
    .table th{background:var(--bg);color:var(--muted);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:11px 16px;border:none}
    .table td{padding:12px 16px;border-color:var(--border);vertical-align:middle}
    .table tbody tr:hover{background:#f7f9fc}

    /* BOOK FORM */
    .book-card{background:#fff;border-radius:var(--radius);padding:32px;border:1px solid var(--border);box-shadow:var(--shadow)}
    .btn-submit{background:linear-gradient(135deg,var(--blue-mid),var(--blue-bright));color:#fff;border:none;padding:13px 32px;border-radius:10px;font-weight:600;font-size:.95rem;cursor:pointer;transition:all .2s}
    .btn-submit:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(26,95,160,.4)}

    /* PROFILE */
    .profile-avatar{width:80px;height:80px;border-radius:20px;background:linear-gradient(135deg,var(--blue-mid),var(--green));display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:#fff;margin-bottom:16px}
    .info-row{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border);font-size:.88rem}
    .info-row:last-child{border-bottom:none}
    .info-label{color:var(--muted);font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}

    /* EMPTY STATE */
    .empty-state{text-align:center;padding:48px 20px;color:var(--muted)}
    .empty-state i{font-size:3rem;display:block;margin-bottom:12px;opacity:.3}
    .empty-state p{font-size:.9rem}

    /* ALERT */
    .alert-success-custom{background:#edf7f3;border:1px solid rgba(58,170,140,.3);border-radius:10px;padding:14px 18px;color:#1a6b54;font-size:.875rem;margin-bottom:20px}
    .alert-error-custom{background:#fff0f0;border:1px solid rgba(192,22,44,.2);border-radius:10px;padding:14px 18px;color:#c0162c;font-size:.875rem;margin-bottom:20px}

    @media(max-width:575px){.auth-card{padding:28px 20px}.portal-wrap{padding:20px 16px}}
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="portal-nav">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between">
      <a href="/panacea/portal.php" class="nav-brand">
        <div class="nav-brand-ico"><i class="bi bi-hospital"></i></div>
        <div>
          <strong>Panacea Hospital</strong>
          <span>Patient Portal</span>
        </div>
      </a>
      <div class="d-flex align-items-center gap-2">
        <?php if (patientLoggedIn()):
          $cp = currentPatient(); ?>
          <a href="/panacea/portal.php?page=dashboard"
             class="nav-link-portal <?= $page==='dashboard'?'active':'' ?>">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
          </a>
          <a href="/panacea/portal.php?page=appointments"
             class="nav-link-portal <?= $page==='appointments'?'active':'' ?>">
            <i class="bi bi-calendar2-week me-1"></i>Appointments
          </a>
          <a href="/panacea/portal.php?page=records"
             class="nav-link-portal <?= $page==='records'?'active':'' ?>">
            <i class="bi bi-journal-medical me-1"></i>Records
          </a>
          <a href="/panacea/portal.php?page=book"
             class="btn-nav-primary ms-2">
            <i class="bi bi-calendar-plus me-1"></i>Book Appointment
          </a>
          <a href="/panacea/portal.php?page=logout"
             class="nav-link-portal text-danger ms-1">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        <?php else: ?>
          <a href="/panacea/portal.php?page=login"
             class="nav-link-portal <?= $page==='login'?'active':'' ?>">Login</a>
          <a href="/panacea/portal.php?page=register"
             class="btn-nav-primary">
            <i class="bi bi-person-plus me-1"></i>Register
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<?php

// ════════════════════════════════════════════════════════
//  PAGES
// ════════════════════════════════════════════════════════

// ── HOME ─────────────────────────────────────────────────
if ($page === 'home' || (!patientLoggedIn() && !in_array($page,['login','register']))): ?>

<div style="background:linear-gradient(135deg,var(--blue-deep),var(--blue-mid));padding:80px 0;text-align:center;color:#fff">
  <div class="container">
    <div style="display:inline-block;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);padding:6px 18px;border-radius:20px;font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-bottom:20px">
      <i class="bi bi-person-heart me-1"></i> Patient Portal
    </div>
    <h1 style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3rem);margin-bottom:16px">
      Your Health, Your Records
    </h1>
    <p style="color:rgba(255,255,255,.75);font-size:1rem;max-width:520px;margin:0 auto 36px;line-height:1.7">
      Access your medical records, view appointments, and book new visits — all in one place.
    </p>
    <div class="d-flex gap-3 justify-content-center flex-wrap">
      <a href="/panacea/portal.php?page=register"
         style="background:#fff;color:var(--blue-mid);padding:14px 30px;border-radius:10px;font-weight:700;text-decoration:none;font-size:.95rem">
        <i class="bi bi-person-plus me-2"></i>Create Account
      </a>
      <a href="/panacea/portal.php?page=login"
         style="background:rgba(255,255,255,.12);color:#fff;border:1.5px solid rgba(255,255,255,.3);padding:14px 30px;border-radius:10px;font-weight:600;text-decoration:none;font-size:.95rem">
        <i class="bi bi-box-arrow-in-right me-2"></i>Login
      </a>
    </div>
  </div>
</div>

<div class="container py-5">
  <div class="row g-4 text-center">
    <div class="col-md-4">
      <div style="background:#fff;border-radius:16px;padding:32px 24px;border:1px solid var(--border);box-shadow:var(--shadow)">
        <div style="width:64px;height:64px;background:#e8f2fb;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:var(--blue-mid);margin:0 auto 16px">
          <i class="bi bi-calendar2-check"></i>
        </div>
        <h5 style="font-family:'Playfair Display',serif;color:var(--blue-deep);margin-bottom:8px">Book Appointments</h5>
        <p style="color:var(--muted);font-size:.875rem;line-height:1.6">Schedule visits with our specialists online — anytime, anywhere.</p>
      </div>
    </div>
    <div class="col-md-4">
      <div style="background:#fff;border-radius:16px;padding:32px 24px;border:1px solid var(--border);box-shadow:var(--shadow)">
        <div style="width:64px;height:64px;background:#edf7f3;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:var(--green);margin:0 auto 16px">
          <i class="bi bi-journal-medical"></i>
        </div>
        <h5 style="font-family:'Playfair Display',serif;color:var(--blue-deep);margin-bottom:8px">View Medical Records</h5>
        <p style="color:var(--muted);font-size:.875rem;line-height:1.6">Access your diagnoses, prescriptions, and treatment history securely.</p>
      </div>
    </div>
    <div class="col-md-4">
      <div style="background:#fff;border-radius:16px;padding:32px 24px;border:1px solid var(--border);box-shadow:var(--shadow)">
        <div style="width:64px;height:64px;background:#f0ebfc;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#7c4ddc;margin:0 auto 16px">
          <i class="bi bi-person-badge"></i>
        </div>
        <h5 style="font-family:'Playfair Display',serif;color:var(--blue-deep);margin-bottom:8px">Your Profile</h5>
        <p style="color:var(--muted);font-size:.875rem;line-height:1.6">View and manage your personal health information and contact details.</p>
      </div>
    </div>
  </div>
</div>

<?php

// ── LOGIN ─────────────────────────────────────────────────
elseif ($page === 'login'): ?>

<div class="auth-wrap">
  <div class="auth-card">
    <div style="text-align:center;margin-bottom:28px">
      <div style="width:56px;height:56px;background:linear-gradient(135deg,var(--blue-mid),var(--green));border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;margin:0 auto 14px">
        <i class="bi bi-person-circle"></i>
      </div>
      <h2>Patient Login</h2>
      <p class="sub">Enter your phone number or Patient ID to access your records</p>
    </div>

    <?php if ($loginError): ?>
      <div class="alert-error-custom"><i class="bi bi-exclamation-triangle me-2"></i><?= pclean($loginError) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Phone Number or Patient ID *</label>
        <input type="text" name="phone" class="form-control"
               placeholder="+251 9XX XXX XXX or PH-2026-0001" required
               value="<?= pclean($_POST['phone'] ?? '') ?>"/>
      </div>
      <div class="mb-4">
        <label class="form-label">Password *</label>
        <input type="password" name="password" class="form-control"
               placeholder="Your password" required/>
      </div>
      <button type="submit" class="btn-auth">
        <i class="bi bi-box-arrow-in-right me-2"></i>Login to Portal
      </button>
    </form>

    <div class="auth-switch">
      Don't have an account?
      <a href="/panacea/portal.php?page=register">Register here</a>
    </div>
    <div class="auth-switch mt-2">
      <a href="/panacea/" style="color:var(--muted)">
        <i class="bi bi-arrow-left me-1"></i>Back to Hospital Website
      </a>
    </div>
  </div>
</div>

<?php

// ── REGISTER ──────────────────────────────────────────────
elseif ($page === 'register'): ?>

<div class="auth-wrap" style="padding:40px 20px">
  <div class="auth-card" style="max-width:600px">
    <div style="text-align:center;margin-bottom:28px">
      <div style="width:56px;height:56px;background:linear-gradient(135deg,var(--blue-mid),var(--green));border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;margin:0 auto 14px">
        <i class="bi bi-person-plus"></i>
      </div>
      <h2>Create Account</h2>
      <p class="sub">Register to access your medical records and book appointments</p>
    </div>

    <?php if ($regError): ?>
      <div class="alert-error-custom"><i class="bi bi-exclamation-triangle me-2"></i><?= pclean($regError) ?></div>
    <?php endif; ?>
    <?php if ($regSuccess): ?>
      <div class="alert-success-custom"><i class="bi bi-check-circle me-2"></i><?= $regSuccess ?>
        <br><a href="/panacea/portal.php?page=login" style="color:var(--blue-mid);font-weight:600">Click here to login →</a>
      </div>
    <?php endif; ?>

    <?php if (!$regSuccess): ?>
    <form method="POST">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Full Name *</label>
          <input type="text" name="full_name" class="form-control"
                 placeholder="Your full name" required
                 value="<?= pclean($_POST['full_name'] ?? '') ?>"/>
        </div>
        <div class="col-md-3">
          <label class="form-label">Gender *</label>
          <select name="gender" class="form-select">
            <option>Male</option><option>Female</option><option>Other</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Date of Birth *</label>
          <input type="date" name="date_of_birth" class="form-control"
                 value="<?= pclean($_POST['date_of_birth'] ?? '') ?>" required/>
        </div>
        <div class="col-md-6">
          <label class="form-label">Phone Number *</label>
          <input type="tel" name="phone" class="form-control"
                 placeholder="+251 9XX XXX XXX" required
                 value="<?= pclean($_POST['phone'] ?? '') ?>"/>
        </div>
        <div class="col-md-6">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control"
                 placeholder="your@email.com"
                 value="<?= pclean($_POST['email'] ?? '') ?>"/>
        </div>
        <div class="col-md-6">
          <label class="form-label">Blood Group</label>
          <select name="blood_group" class="form-select">
            <?php foreach (['Unknown','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
              <option><?= $bg ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">City / Address</label>
          <input type="text" name="address" class="form-control"
                 placeholder="Hawassa"
                 value="<?= pclean($_POST['address'] ?? '') ?>"/>
        </div>
        <div class="col-md-6">
          <label class="form-label">Password * <small style="color:var(--muted)">(min 6 chars)</small></label>
          <input type="password" name="password" class="form-control"
                 placeholder="Choose a password" required/>
        </div>
        <div class="col-md-6">
          <label class="form-label">Confirm Password *</label>
          <input type="password" name="confirm_password" class="form-control"
                 placeholder="Repeat password" required/>
        </div>
        <div class="col-12">
          <button type="submit" class="btn-auth">
            <i class="bi bi-person-check me-2"></i>Create My Account
          </button>
        </div>
      </div>
    </form>
    <?php endif; ?>

    <div class="auth-switch">
      Already have an account?
      <a href="/panacea/portal.php?page=login">Login here</a>
    </div>
  </div>
</div>

<?php

// ── DASHBOARD ─────────────────────────────────────────────
elseif ($page === 'dashboard'):
  requirePatientLogin();
  $cp = currentPatient();
  $patient = $pdo->prepare('SELECT * FROM patients WHERE id=?');
  $patient->execute([$cp['id']]); $patient = $patient->fetch();

  $appts = $pdo->prepare('SELECT a.*,d.name AS dept_name FROM appointments a LEFT JOIN departments d ON a.department_id=d.id WHERE a.patient_id=? ORDER BY a.appt_date DESC LIMIT 5');
  $appts->execute([$cp['id']]); $appts = $appts->fetchAll();

  $records = $pdo->prepare('SELECT r.*,doc.full_name AS doc_name FROM medical_records r LEFT JOIN doctors doc ON r.doctor_id=doc.id WHERE r.patient_id=? ORDER BY r.visit_date DESC LIMIT 3');
  $records->execute([$cp['id']]); $records = $records->fetchAll();

  $totalAppts   = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE patient_id=?');
  $totalAppts->execute([$cp['id']]); $totalAppts = (int)$totalAppts->fetchColumn();
  $pendingAppts = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id=? AND status='Pending'");
  $pendingAppts->execute([$cp['id']]); $pendingAppts = (int)$pendingAppts->fetchColumn();
  $totalRecords = $pdo->prepare('SELECT COUNT(*) FROM medical_records WHERE patient_id=?');
  $totalRecords->execute([$cp['id']]); $totalRecords = (int)$totalRecords->fetchColumn();
?>

<div class="portal-wrap">
  <!-- Welcome Banner -->
  <div class="welcome-banner">
    <div class="pid-badge"><i class="bi bi-person-badge me-1"></i><?= pclean($patient['patient_id']) ?></div>
    <h2>Welcome back, <?= pclean(explode(' ', $patient['full_name'])[0]) ?>! 👋</h2>
    <p>Here's a summary of your health activity at Panacea Hospital.</p>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-ico" style="background:#e8f2fb;color:var(--blue-mid)"><i class="bi bi-calendar2-week"></i></div>
        <div><div class="stat-val"><?= $totalAppts ?></div><div class="stat-lbl">Total Appointments</div></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-ico" style="background:#fff8e8;color:#d08000"><i class="bi bi-hourglass-split"></i></div>
        <div><div class="stat-val"><?= $pendingAppts ?></div><div class="stat-lbl">Pending</div></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-ico" style="background:#edf7f3;color:var(--green)"><i class="bi bi-journal-medical"></i></div>
        <div><div class="stat-val"><?= $totalRecords ?></div><div class="stat-lbl">Medical Records</div></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-ico" style="background:#fbe8ec;color:var(--red)"><i class="bi bi-droplet-fill"></i></div>
        <div><div class="stat-val" style="font-size:1.4rem"><?= pclean($patient['blood_group']) ?></div><div class="stat-lbl">Blood Group</div></div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Recent Appointments -->
    <div class="col-lg-7">
      <div class="section-card">
        <div class="section-card-head">
          <i class="bi bi-calendar2-week text-primary me-2"></i>
          <h5>Recent Appointments</h5>
          <a href="/panacea/portal.php?page=appointments"
             style="font-size:.8rem;color:var(--blue-mid);text-decoration:none;margin-left:auto">View All</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead><tr><th>Ref</th><th>Department</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($appts as $a): ?>
              <tr>
                <td><code style="font-size:.72rem"><?= pclean($a['ref_number']) ?></code></td>
                <td style="font-size:.82rem"><?= pclean($a['dept_name']) ?></td>
                <td style="font-size:.82rem"><?= date('d M Y', strtotime($a['appt_date'])) ?></td>
                <td>
                  <?php
                  $sc = ['Pending'=>'warning','Confirmed'=>'info','Completed'=>'success','Cancelled'=>'danger'];
                  $c  = $sc[$a['status']] ?? 'secondary';
                  echo "<span class='badge bg-$c' style='font-size:.7rem'>{$a['status']}</span>";
                  ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$appts): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No appointments yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Quick Actions + Profile Summary -->
    <div class="col-lg-5">
      <div class="section-card mb-3">
        <div class="section-card-head">
          <i class="bi bi-lightning-charge-fill text-warning me-2"></i>
          <h5>Quick Actions</h5>
        </div>
        <div class="p-3 d-flex flex-column gap-2">
          <a href="/panacea/portal.php?page=book"
             style="background:linear-gradient(135deg,var(--blue-mid),var(--blue-bright));color:#fff;padding:12px 18px;border-radius:10px;text-decoration:none;font-weight:600;font-size:.875rem;display:flex;align-items:center;gap:10px">
            <i class="bi bi-calendar-plus"></i> Book New Appointment
          </a>
          <a href="/panacea/portal.php?page=records"
             style="background:var(--bg);color:var(--blue-deep);padding:12px 18px;border-radius:10px;text-decoration:none;font-weight:600;font-size:.875rem;display:flex;align-items:center;gap:10px;border:1px solid var(--border)">
            <i class="bi bi-journal-medical"></i> View Medical Records
          </a>
          <a href="/panacea/portal.php?page=profile"
             style="background:var(--bg);color:var(--blue-deep);padding:12px 18px;border-radius:10px;text-decoration:none;font-weight:600;font-size:.875rem;display:flex;align-items:center;gap:10px;border:1px solid var(--border)">
            <i class="bi bi-person-circle"></i> My Profile
          </a>
          <a href="tel:+251917111111"
             style="background:#fff0f0;color:var(--red);padding:12px 18px;border-radius:10px;text-decoration:none;font-weight:600;font-size:.875rem;display:flex;align-items:center;gap:10px;border:1px solid rgba(192,22,44,.2)">
            <i class="bi bi-telephone-fill"></i> Emergency: +251 917 111 111
          </a>
        </div>
      </div>

      <!-- Recent Records -->
      <?php if ($records): ?>
      <div class="section-card">
        <div class="section-card-head">
          <i class="bi bi-journal-medical text-success me-2"></i>
          <h5>Latest Records</h5>
        </div>
        <div class="p-3">
          <?php foreach ($records as $r): ?>
          <div style="padding:12px;background:var(--bg);border-radius:10px;margin-bottom:8px">
            <div class="d-flex justify-content-between mb-1">
              <strong style="font-size:.82rem;color:var(--blue-deep)"><?= date('d M Y', strtotime($r['visit_date'])) ?></strong>
              <span style="font-size:.75rem;color:var(--muted)"><?= pclean($r['doc_name'] ?? '—') ?></span>
            </div>
            <?php if ($r['diagnosis']): ?>
              <p style="font-size:.8rem;color:var(--muted);margin:0"><?= pclean(substr($r['diagnosis'],0,60)) ?>...</p>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php

// ── APPOINTMENTS PAGE ─────────────────────────────────────
elseif ($page === 'appointments'):
  requirePatientLogin();
  $cp = currentPatient();
  $appts = $pdo->prepare('SELECT a.*,d.name AS dept_name,doc.full_name AS doc_name FROM appointments a LEFT JOIN departments d ON a.department_id=d.id LEFT JOIN doctors doc ON a.doctor_id=doc.id WHERE a.patient_id=? ORDER BY a.appt_date DESC');
  $appts->execute([$cp['id']]); $appts = $appts->fetchAll();
?>
<div class="portal-wrap">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h3 style="font-family:'Playfair Display',serif;color:var(--blue-deep)">My Appointments</h3>
    <a href="/panacea/portal.php?page=book" class="btn-nav-primary">
      <i class="bi bi-calendar-plus me-1"></i>Book New
    </a>
  </div>
  <div class="section-card">
    <div class="table-responsive">
      <table class="table table-hover">
        <thead><tr><th>Reference</th><th>Department</th><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($appts as $a): ?>
          <tr>
            <td><code style="font-size:.75rem;background:var(--bg);padding:3px 8px;border-radius:6px"><?= pclean($a['ref_number']) ?></code></td>
            <td style="font-size:.85rem"><?= pclean($a['dept_name']) ?></td>
            <td style="font-size:.82rem;color:var(--muted)"><?= pclean($a['doc_name'] ?? '—') ?></td>
            <td style="font-size:.85rem"><?= date('d M Y', strtotime($a['appt_date'])) ?></td>
            <td style="font-size:.78rem;color:var(--muted)"><?= pclean($a['appt_time']) ?></td>
            <td>
              <?php
              $sc = ['Pending'=>'warning','Confirmed'=>'info','Completed'=>'success','Cancelled'=>'danger','No-Show'=>'secondary'];
              $c  = $sc[$a['status']] ?? 'secondary';
              echo "<span class='badge bg-$c'>{$a['status']}</span>";
              ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$appts): ?>
            <tr><td colspan="6"><div class="empty-state"><i class="bi bi-calendar-x"></i><p>No appointments yet.<br><a href="/panacea/portal.php?page=book">Book your first appointment →</a></p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php

// ── MEDICAL RECORDS ───────────────────────────────────────
elseif ($page === 'records'):
  requirePatientLogin();
  $cp = currentPatient();
  $records = $pdo->prepare('SELECT r.*,doc.full_name AS doc_name FROM medical_records r LEFT JOIN doctors doc ON r.doctor_id=doc.id WHERE r.patient_id=? ORDER BY r.visit_date DESC');
  $records->execute([$cp['id']]); $records = $records->fetchAll();
?>
<div class="portal-wrap">
  <h3 style="font-family:'Playfair Display',serif;color:var(--blue-deep);margin-bottom:24px">My Medical Records</h3>
  <?php if ($records): ?>
    <?php foreach ($records as $r): ?>
    <div class="section-card mb-3">
      <div class="section-card-head">
        <i class="bi bi-file-medical text-success me-2"></i>
        <h5><?= date('d M Y', strtotime($r['visit_date'])) ?></h5>
        <span style="background:var(--bg);padding:3px 12px;border-radius:20px;font-size:.72rem;color:var(--muted);margin-left:8px"><?= pclean($r['visit_type']) ?></span>
        <span style="font-size:.8rem;color:var(--muted);margin-left:auto"><?= pclean($r['doc_name'] ?? '—') ?></span>
      </div>
      <div class="p-4">
        <div class="row g-3">
          <?php if ($r['chief_complaint']): ?>
          <div class="col-md-6">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:4px">Chief Complaint</div>
            <p style="font-size:.88rem;color:var(--text);margin:0"><?= pclean($r['chief_complaint']) ?></p>
          </div>
          <?php endif; ?>
          <?php if ($r['diagnosis']): ?>
          <div class="col-md-6">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:4px">Diagnosis</div>
            <p style="font-size:.88rem;color:var(--text);margin:0"><?= pclean($r['diagnosis']) ?></p>
          </div>
          <?php endif; ?>
          <?php if ($r['treatment']): ?>
          <div class="col-md-6">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:4px">Treatment</div>
            <p style="font-size:.88rem;color:var(--text);margin:0"><?= pclean($r['treatment']) ?></p>
          </div>
          <?php endif; ?>
          <?php if ($r['prescription']): ?>
          <div class="col-md-6">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:4px">Prescription</div>
            <p style="font-size:.88rem;color:var(--text);margin:0"><?= pclean($r['prescription']) ?></p>
          </div>
          <?php endif; ?>
          <?php if ($r['follow_up_date']): ?>
          <div class="col-12">
            <div style="background:#fff8e8;border-radius:8px;padding:10px 14px;font-size:.82rem;display:flex;align-items:center;gap:8px">
              <i class="bi bi-calendar-event" style="color:#d08000"></i>
              <strong style="color:#d08000">Follow-up:</strong>
              <?= date('d M Y', strtotime($r['follow_up_date'])) ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="section-card">
      <div class="empty-state">
        <i class="bi bi-journal-x"></i>
        <p>No medical records yet.<br>Records will appear here after your visits.</p>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php

// ── BOOK APPOINTMENT ──────────────────────────────────────
elseif ($page === 'book'):
  requirePatientLogin();
?>
<div class="portal-wrap" style="max-width:680px">
  <h3 style="font-family:'Playfair Display',serif;color:var(--blue-deep);margin-bottom:24px">
    <i class="bi bi-calendar-plus me-2" style="color:var(--blue-bright)"></i>Book an Appointment
  </h3>

  <?php if ($bookMsg): ?>
    <div class="alert-success-custom"><i class="bi bi-check-circle me-2"></i><?= $bookMsg ?></div>
  <?php endif; ?>
  <?php if ($bookErr): ?>
    <div class="alert-error-custom"><i class="bi bi-exclamation-triangle me-2"></i><?= pclean($bookErr) ?></div>
  <?php endif; ?>

  <div class="book-card">
    <form method="POST">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Department *</label>
          <select name="department_id" class="form-select" required>
            <option value="">Select Department</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= $d['id'] ?>"><?= pclean($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Preferred Date *</label>
          <input type="date" name="appt_date" class="form-control"
                 min="<?= date('Y-m-d') ?>" required/>
        </div>
        <div class="col-md-6">
          <label class="form-label">Preferred Time</label>
          <select name="appt_time" class="form-select">
            <option>Morning (7AM-12PM)</option>
            <option>Afternoon (12PM-5PM)</option>
            <option>Evening (5PM-9PM)</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Reason / Symptoms</label>
          <textarea name="reason" class="form-control" rows="3"
                    placeholder="Describe your symptoms or reason for visit..."></textarea>
        </div>
        <div class="col-12">
          <button type="submit" class="btn-submit w-100">
            <i class="bi bi-calendar-check me-2"></i>Confirm Appointment
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php

// ── PROFILE ───────────────────────────────────────────────
elseif ($page === 'profile'):
  requirePatientLogin();
  $cp = currentPatient();
  $patient = $pdo->prepare('SELECT * FROM patients WHERE id=?');
  $patient->execute([$cp['id']]); $patient = $patient->fetch();
?>
<div class="portal-wrap" style="max-width:680px">
  <h3 style="font-family:'Playfair Display',serif;color:var(--blue-deep);margin-bottom:24px">My Profile</h3>

  <div class="section-card">
    <div class="p-4 text-center border-bottom">
      <div class="profile-avatar mx-auto">
        <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
      </div>
      <h4 style="font-family:'Playfair Display',serif;color:var(--blue-deep)"><?= pclean($patient['full_name']) ?></h4>
      <div style="display:inline-block;background:var(--bg);padding:4px 16px;border-radius:20px;font-size:.78rem;color:var(--muted);margin-top:6px">
        <?= pclean($patient['patient_id']) ?>
      </div>
    </div>
    <div class="p-4">
      <div class="info-row">
        <span class="info-label">Gender</span>
        <span><?= pclean($patient['gender']) ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Date of Birth</span>
        <span><?= date('d M Y', strtotime($patient['date_of_birth'])) ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Phone</span>
        <span><?= pclean($patient['phone']) ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Email</span>
        <span><?= pclean($patient['email'] ?: '—') ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Blood Group</span>
        <span style="font-weight:700;color:var(--red)"><?= pclean($patient['blood_group']) ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Address</span>
        <span><?= pclean($patient['address'] ?: '—') ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Status</span>
        <span class="badge bg-success"><?= pclean($patient['status']) ?></span>
      </div>
      <?php if ($patient['allergies']): ?>
      <div class="info-row">
        <span class="info-label">Allergies</span>
        <span style="color:var(--red)"><?= pclean($patient['allergies']) ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php endif; ?>

<!-- FOOTER -->
<footer style="background:var(--blue-deep);color:rgba(255,255,255,.5);text-align:center;padding:20px;font-size:.8rem;margin-top:60px">
  © <?= date('Y') ?> Panacea Hospital Patient Portal · Hawassa, Ethiopia ·
  <a href="/panacea/" style="color:rgba(255,255,255,.5)">Back to Hospital Website</a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
