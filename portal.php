<?php
// ============================================================
//  PANACEA HOSPITAL – Patient Portal (Complete Version)
//  portal.php — Replace existing portal.php
// ============================================================
require_once dirname(__FILE__) . '/config/database.php';

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
function portalStatusBadge(string $status): string {
    $map = [
        'Pending'=>'warning','Confirmed'=>'info','Completed'=>'success',
        'Cancelled'=>'danger','No-Show'=>'secondary',
        'Unpaid'=>'danger','Paid'=>'success','Partial'=>'warning',
    ];
    $c = $map[$status] ?? 'secondary';
    return "<span class='badge bg-$c' style='font-size:.72rem'>$status</span>";
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
        $check = $pdo->prepare('SELECT id FROM patients WHERE phone=?');
        $check->execute([$f['phone']]);
        if ($check->fetch()) {
            $regError = 'A patient with this phone number already exists. Please login instead.';
        } else {
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
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password']   ?? '';
    $stmt = $pdo->prepare('SELECT * FROM patients WHERE (phone=? OR patient_id=?) AND status != \'Deceased\' LIMIT 1');
    $stmt->execute([$phone, $phone]);
    $patient = $stmt->fetch();
    if ($patient && !empty($patient['portal_password']) &&
        password_verify($password, $patient['portal_password'])) {
        session_regenerate_id(true);
        $_SESSION['patient_id']   = $patient['id'];
        $_SESSION['patient_name'] = $patient['full_name'];
        $_SESSION['patient_pid']  = $patient['patient_id'];
        $pdo->prepare('UPDATE patients SET last_portal_login=NOW() WHERE id=?')->execute([$patient['id']]);
        header('Location: /panacea/portal.php?page=dashboard'); exit;
    } else {
        $loginError = 'Invalid phone number/Patient ID or password. Please try again.';
    }
}

// ── BOOK APPOINTMENT ──────────────────────────────────────
$bookMsg = ''; $bookErr = '';
if ($page === 'book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePatientLogin();
    $cp = currentPatient();
    $patInfo = $pdo->prepare('SELECT * FROM patients WHERE id=?');
    $patInfo->execute([$cp['id']]); $patInfo = $patInfo->fetch();
    $deptId = (int)($_POST['department_id'] ?? 0);
    $date   = $_POST['appt_date'] ?? '';
    $time   = $_POST['appt_time'] ?? 'Morning (7AM-12PM)';
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
            ->execute([$ref,$cp['id'],$patInfo['full_name'],$patInfo['phone'],
                       $patInfo['email'],$deptId,$date,$time,$reason]);
        $bookMsg = 'Appointment booked! Reference: <strong>' . $ref . '</strong>. We will confirm within 24 hours.';
    }
}

// ── CANCEL APPOINTMENT ────────────────────────────────────
if ($page === 'cancel_appt' && isset($_GET['id'])) {
    requirePatientLogin();
    $cp     = currentPatient();
    $apptId = (int)$_GET['id'];
    // Verify appointment belongs to this patient and is Pending/Confirmed
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id=? AND patient_id=? AND status IN ('Pending','Confirmed')");
    $stmt->execute([$apptId, $cp['id']]);
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE appointments SET status='Cancelled' WHERE id=?")->execute([$apptId]);
        $_SESSION['portal_msg'] = ['type'=>'success','text'=>'Appointment cancelled successfully.'];
    } else {
        $_SESSION['portal_msg'] = ['type'=>'error','text'=>'Cannot cancel this appointment.'];
    }
    header('Location: /panacea/portal.php?page=appointments'); exit;
}

// ── EDIT PROFILE ──────────────────────────────────────────
$profileMsg = '';
if ($page === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePatientLogin();
    $cp = currentPatient();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $f = [
            'full_name'     => trim($_POST['full_name']     ?? ''),
            'email'         => trim($_POST['email']         ?? ''),
            'address'       => trim($_POST['address']       ?? ''),
            'blood_group'   => $_POST['blood_group']        ?? 'Unknown',
            'emergency_contact_name'  => trim($_POST['emergency_contact_name']  ?? ''),
            'emergency_contact_phone' => trim($_POST['emergency_contact_phone'] ?? ''),
        ];
        if (!$f['full_name']) {
            $profileMsg = ['type'=>'error','text'=>'Full name is required.'];
        } else {
            $pdo->prepare('UPDATE patients SET full_name=?,email=?,address=?,blood_group=?,
                           emergency_contact_name=?,emergency_contact_phone=? WHERE id=?')
                ->execute([...$f, $cp['id']]);
            $_SESSION['patient_name'] = $f['full_name'];
            $profileMsg = ['type'=>'success','text'=>'Profile updated successfully!'];
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $stmt    = $pdo->prepare('SELECT portal_password FROM patients WHERE id=?');
        $stmt->execute([$cp['id']]); $pat = $stmt->fetch();

        if (!password_verify($current, $pat['portal_password'] ?? '')) {
            $profileMsg = ['type'=>'error','text'=>'Current password is incorrect.'];
        } elseif ($new !== $confirm) {
            $profileMsg = ['type'=>'error','text'=>'New passwords do not match.'];
        } elseif (strlen($new) < 6) {
            $profileMsg = ['type'=>'error','text'=>'Password must be at least 6 characters.'];
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE patients SET portal_password=? WHERE id=?')->execute([$hash, $cp['id']]);
            $profileMsg = ['type'=>'success','text'=>'Password changed successfully!'];
        }
    }
}

// ── LOAD DATA ─────────────────────────────────────────────
$departments = $pdo->query('SELECT * FROM departments WHERE is_active=1 ORDER BY name')->fetchAll();

// Portal message from session
$portalMsg = $_SESSION['portal_msg'] ?? null;
unset($_SESSION['portal_msg']);
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
      --shadow:0 2px 16px rgba(10,46,92,.08);--radius:14px;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

    /* NAV */
    .portal-nav{background:#fff;border-bottom:1px solid var(--border);padding:14px 0;position:sticky;top:0;z-index:100;box-shadow:0 1px 8px rgba(10,46,92,.05)}
    .nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none}
    .nav-brand-ico{width:40px;height:40px;background:linear-gradient(135deg,var(--blue-mid),var(--green));border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem}
    .nav-brand strong{font-family:'Playfair Display',serif;color:var(--blue-deep);font-size:1.1rem}
    .nav-brand span{font-size:.7rem;color:var(--muted);display:block;text-transform:uppercase;letter-spacing:.05em}
    .nav-link-portal{color:var(--muted);text-decoration:none;font-size:.875rem;font-weight:500;padding:8px 12px;border-radius:8px;transition:all .2s;display:flex;align-items:center;gap:6px}
    .nav-link-portal:hover,.nav-link-portal.active{background:var(--bg);color:var(--blue-mid)}
    .btn-nav-primary{background:linear-gradient(135deg,var(--blue-mid),var(--blue-bright));color:#fff;border:none;padding:9px 20px;border-radius:9px;font-weight:600;font-size:.85rem;text-decoration:none;transition:all .2s;display:flex;align-items:center;gap:6px}
    .btn-nav-primary:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(26,95,160,.35);color:#fff}

    /* LAYOUT */
    .portal-wrap{max-width:1100px;margin:0 auto;padding:32px 20px}

    /* AUTH */
    .auth-wrap{min-height:calc(100vh - 70px);display:flex;align-items:center;justify-content:center;padding:40px 20px}
    .auth-card{background:#fff;border-radius:20px;padding:44px 40px;width:100%;max-width:500px;box-shadow:0 8px 40px rgba(10,46,92,.12)}
    .auth-card h2{font-family:'Playfair Display',serif;color:var(--blue-deep);font-size:1.8rem;margin-bottom:6px}
    .auth-card p.sub{color:var(--muted);font-size:.875rem;margin-bottom:28px}
    .form-label{font-size:.8rem;font-weight:600;color:var(--blue-deep);margin-bottom:5px}
    .form-control,.form-select{border:1.5px solid var(--border);border-radius:9px;padding:11px 14px;font-size:.9rem;transition:all .2s}
    .form-control:focus,.form-select:focus{border-color:var(--blue-bright);box-shadow:0 0 0 3px rgba(46,141,212,.12)}
    .btn-auth{background:linear-gradient(135deg,var(--blue-mid),var(--blue-bright));color:#fff;border:none;border-radius:10px;padding:13px;font-weight:600;font-size:.95rem;width:100%;cursor:pointer;transition:all .2s}
    .btn-auth:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(26,95,160,.4)}
    .auth-switch{text-align:center;margin-top:20px;font-size:.875rem;color:var(--muted)}
    .auth-switch a{color:var(--blue-mid);text-decoration:none;font-weight:600}

    /* DASHBOARD */
    .welcome-banner{background:linear-gradient(135deg,var(--blue-deep),var(--blue-mid));border-radius:var(--radius);padding:28px 32px;color:#fff;margin-bottom:28px;position:relative;overflow:hidden}
    .welcome-banner::after{content:'';position:absolute;right:-40px;top:-40px;width:200px;height:200px;background:rgba(255,255,255,.05);border-radius:50%}
    .welcome-banner h2{font-family:'Playfair Display',serif;font-size:1.6rem;margin-bottom:6px}
    .welcome-banner p{color:rgba(255,255,255,.7);font-size:.9rem;margin:0}
    .pid-badge{display:inline-block;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);padding:4px 14px;border-radius:20px;font-size:.75rem;margin-bottom:12px;backdrop-filter:blur(8px)}
    .stat-card{background:#fff;border-radius:var(--radius);padding:20px 22px;border:1px solid var(--border);box-shadow:var(--shadow);display:flex;align-items:center;gap:16px;transition:transform .2s}
    .stat-card:hover{transform:translateY(-3px)}
    .stat-ico{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0}
    .stat-val{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;color:var(--blue-deep);line-height:1}
    .stat-lbl{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-top:3px}
    .section-card{background:#fff;border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);margin-bottom:24px;overflow:hidden}
    .section-card-head{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
    .section-card-head h5{font-family:'Playfair Display',serif;color:var(--blue-deep);font-size:1.05rem;margin:0;flex:1}
    .table{margin:0;font-size:.875rem}
    .table th{background:var(--bg);color:var(--muted);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:11px 16px;border:none}
    .table td{padding:12px 16px;border-color:var(--border);vertical-align:middle}
    .table tbody tr:hover{background:#f7f9fc}

    /* TABS */
    .portal-tabs{display:flex;gap:4px;background:var(--bg);padding:4px;border-radius:12px;margin-bottom:24px;flex-wrap:wrap}
    .portal-tab{flex:1;text-align:center;padding:10px 16px;border-radius:9px;text-decoration:none;font-size:.85rem;font-weight:600;color:var(--muted);transition:all .2s;white-space:nowrap;display:flex;align-items:center;justify-content:center;gap:6px}
    .portal-tab:hover{color:var(--blue-mid)}
    .portal-tab.active{background:#fff;color:var(--blue-mid);box-shadow:var(--shadow)}

    /* ALERTS */
    .alert-success-custom{background:#edf7f3;border:1px solid rgba(58,170,140,.3);border-radius:10px;padding:14px 18px;color:#1a6b54;font-size:.875rem;margin-bottom:20px}
    .alert-error-custom{background:#fff0f0;border:1px solid rgba(192,22,44,.2);border-radius:10px;padding:14px 18px;color:#c0162c;font-size:.875rem;margin-bottom:20px}

    /* PROFILE */
    .profile-avatar{width:80px;height:80px;border-radius:20px;background:linear-gradient(135deg,var(--blue-mid),var(--green));display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:#fff;flex-shrink:0}
    .info-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border);font-size:.88rem}
    .info-row:last-child{border-bottom:none}
    .info-label{color:var(--muted);font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}

    /* INVOICE */
    .invoice-card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:12px;transition:all .2s}
    .invoice-card:hover{box-shadow:var(--shadow);transform:translateY(-2px)}

    /* EMPTY */
    .empty-state{text-align:center;padding:48px 20px;color:var(--muted)}
    .empty-state i{font-size:3rem;display:block;margin-bottom:12px;opacity:.3}

    /* QUICK ACTION */
    .quick-action{background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:14px 18px;text-decoration:none;color:var(--text);font-weight:600;font-size:.875rem;display:flex;align-items:center;gap:10px;transition:all .2s;margin-bottom:8px}
    .quick-action:hover{background:#fff;box-shadow:var(--shadow);color:var(--blue-mid);transform:translateX(3px)}

    @media(max-width:575px){.auth-card{padding:28px 20px}.portal-wrap{padding:20px 16px}.portal-tab{font-size:.75rem;padding:8px 10px}}
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
      <div class="d-flex align-items-center gap-1 flex-wrap">
        <?php if (patientLoggedIn()):
          $cp = currentPatient(); ?>
          <a href="/panacea/portal.php?page=dashboard"
             class="nav-link-portal <?= $page==='dashboard'?'active':'' ?>">
            <i class="bi bi-speedometer2"></i>
            <span class="d-none d-md-inline">Dashboard</span>
          </a>
          <a href="/panacea/portal.php?page=appointments"
             class="nav-link-portal <?= $page==='appointments'?'active':'' ?>">
            <i class="bi bi-calendar2-week"></i>
            <span class="d-none d-md-inline">Appointments</span>
          </a>
          <a href="/panacea/portal.php?page=records"
             class="nav-link-portal <?= $page==='records'?'active':'' ?>">
            <i class="bi bi-journal-medical"></i>
            <span class="d-none d-md-inline">Records</span>
          </a>
          <a href="/panacea/portal.php?page=billing"
             class="nav-link-portal <?= $page==='billing'?'active':'' ?>">
            <i class="bi bi-receipt"></i>
            <span class="d-none d-md-inline">Billing</span>
          </a>
          <a href="/panacea/portal.php?page=book"
             class="btn-nav-primary ms-1">
            <i class="bi bi-calendar-plus"></i>
            <span class="d-none d-sm-inline">Book</span>
          </a>
          <a href="/panacea/portal.php?page=profile"
             class="nav-link-portal <?= $page==='profile'?'active':'' ?>"
             title="My Profile">
            <i class="bi bi-person-circle"></i>
          </a>
          <a href="/panacea/portal.php?page=logout"
             class="nav-link-portal text-danger" title="Logout">
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
//  HOME
// ════════════════════════════════════════════════════════
if ($page === 'home'): ?>
<div style="background:linear-gradient(135deg,var(--blue-deep),var(--blue-mid));padding:80px 0;text-align:center;color:#fff">
  <div class="container">
    <div style="display:inline-block;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);padding:6px 18px;border-radius:20px;font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-bottom:20px">
      <i class="bi bi-person-heart me-1"></i> Patient Portal
    </div>
    <h1 style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3rem);margin-bottom:16px">Your Health, Your Records</h1>
    <p style="color:rgba(255,255,255,.75);font-size:1rem;max-width:520px;margin:0 auto 36px;line-height:1.7">
      Access your medical records, view appointments, check invoices, and book new visits — all in one place.
    </p>
    <div class="d-flex gap-3 justify-content-center flex-wrap">
      <a href="/panacea/portal.php?page=register"
         style="background:#fff;color:var(--blue-mid);padding:14px 30px;border-radius:10px;font-weight:700;text-decoration:none">
        <i class="bi bi-person-plus me-2"></i>Create Account
      </a>
      <a href="/panacea/portal.php?page=login"
         style="background:rgba(255,255,255,.12);color:#fff;border:1.5px solid rgba(255,255,255,.3);padding:14px 30px;border-radius:10px;font-weight:600;text-decoration:none">
        <i class="bi bi-box-arrow-in-right me-2"></i>Login
      </a>
    </div>
  </div>
</div>
<div class="container py-5">
  <div class="row g-4 text-center">
    <?php
    $features = [
      ['bi-calendar2-check','#e8f2fb','#1a5fa0','Book Appointments','Schedule visits with our specialists online — anytime, anywhere.'],
      ['bi-journal-medical','#edf7f3','#3aaa8c','Medical Records','View your diagnoses, prescriptions, and treatment history securely.'],
      ['bi-receipt','#fff8e8','#d08000','Billing & Invoices','View and track your invoices and payment history.'],
      ['bi-person-badge','#f0ebfc','#7c4ddc','Your Profile','Manage your personal health information and contact details.'],
    ];
    foreach ($features as $f): ?>
    <div class="col-md-3 col-6">
      <div style="background:#fff;border-radius:16px;padding:28px 20px;border:1px solid var(--border);box-shadow:var(--shadow);height:100%">
        <div style="width:60px;height:60px;background:<?= $f[1] ?>;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:<?= $f[2] ?>;margin:0 auto 14px"><i class="bi <?= $f[0] ?>"></i></div>
        <h5 style="font-family:'Playfair Display',serif;color:var(--blue-deep);margin-bottom:8px;font-size:1rem"><?= $f[3] ?></h5>
        <p style="color:var(--muted);font-size:.82rem;line-height:1.6;margin:0"><?= $f[4] ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php

// ════════════════════════════════════════════════════════
//  LOGIN
// ════════════════════════════════════════════════════════
elseif ($page === 'login'): ?>
<div class="auth-wrap">
  <div class="auth-card">
    <div style="text-align:center;margin-bottom:28px">
      <div style="width:56px;height:56px;background:linear-gradient(135deg,var(--blue-mid),var(--green));border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;margin:0 auto 14px"><i class="bi bi-person-circle"></i></div>
      <h2>Patient Login</h2>
      <p class="sub">Enter your phone number or Patient ID to access your records</p>
    </div>
    <?php if ($loginError): ?>
      <div class="alert-error-custom"><i class="bi bi-exclamation-triangle me-2"></i><?= pclean($loginError) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Phone Number or Patient ID *</label>
        <input type="text" name="phone" class="form-control" placeholder="+251 9XX XXX XXX or PH-2026-0001" required value="<?= pclean($_POST['phone'] ?? '') ?>"/>
      </div>
      <div class="mb-4">
        <label class="form-label">Password *</label>
        <input type="password" name="password" class="form-control" placeholder="Your password" required/>
      </div>
      <button type="submit" class="btn-auth"><i class="bi bi-box-arrow-in-right me-2"></i>Login to Portal</button>
    </form>
    <div class="auth-switch">Don't have an account? <a href="/panacea/portal.php?page=register">Register here</a></div>
    <div class="auth-switch mt-2"><a href="/panacea/" style="color:var(--muted)"><i class="bi bi-arrow-left me-1"></i>Back to Hospital Website</a></div>
  </div>
</div>

<?php

// ════════════════════════════════════════════════════════
//  REGISTER
// ════════════════════════════════════════════════════════
elseif ($page === 'register'): ?>
<div class="auth-wrap" style="padding:40px 20px">
  <div class="auth-card" style="max-width:620px">
    <div style="text-align:center;margin-bottom:28px">
      <div style="width:56px;height:56px;background:linear-gradient(135deg,var(--blue-mid),var(--green));border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;margin:0 auto 14px"><i class="bi bi-person-plus"></i></div>
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
        <div class="col-md-6"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required value="<?= pclean($_POST['full_name'] ?? '') ?>"/></div>
        <div class="col-md-3"><label class="form-label">Gender *</label><select name="gender" class="form-select"><option>Male</option><option>Female</option><option>Other</option></select></div>
        <div class="col-md-3"><label class="form-label">Date of Birth *</label><input type="date" name="date_of_birth" class="form-control" required value="<?= pclean($_POST['date_of_birth'] ?? '') ?>"/></div>
        <div class="col-md-6"><label class="form-label">Phone Number *</label><input type="tel" name="phone" class="form-control" placeholder="+251 9XX XXX XXX" required value="<?= pclean($_POST['phone'] ?? '') ?>"/></div>
        <div class="col-md-6"><label class="form-label">Email Address</label><input type="email" name="email" class="form-control" value="<?= pclean($_POST['email'] ?? '') ?>"/></div>
        <div class="col-md-6"><label class="form-label">Blood Group</label><select name="blood_group" class="form-select"><?php foreach (['Unknown','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?><option><?= $bg ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">City / Address</label><input type="text" name="address" class="form-control" placeholder="Hawassa" value="<?= pclean($_POST['address'] ?? '') ?>"/></div>
        <div class="col-md-6"><label class="form-label">Password * <small style="color:var(--muted)">(min 6 chars)</small></label><input type="password" name="password" class="form-control" required/></div>
        <div class="col-md-6"><label class="form-label">Confirm Password *</label><input type="password" name="confirm_password" class="form-control" required/></div>
        <div class="col-12"><button type="submit" class="btn-auth"><i class="bi bi-person-check me-2"></i>Create My Account</button></div>
      </div>
    </form>
    <?php endif; ?>
    <div class="auth-switch">Already have an account? <a href="/panacea/portal.php?page=login">Login here</a></div>
  </div>
</div>

<?php

// ════════════════════════════════════════════════════════
//  DASHBOARD
// ════════════════════════════════════════════════════════
elseif ($page === 'dashboard'):
  requirePatientLogin();
  $cp = currentPatient();
  $patient = $pdo->prepare('SELECT * FROM patients WHERE id=?');
  $patient->execute([$cp['id']]); $patient = $patient->fetch();

  $totalAppts   = (int)$pdo->prepare('SELECT COUNT(*) FROM appointments WHERE patient_id=?')->execute([$cp['id']]) ? $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE patient_id=?') : 0;
  $s1 = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE patient_id=?'); $s1->execute([$cp['id']]); $totalAppts = (int)$s1->fetchColumn();
  $s2 = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id=? AND status='Pending'"); $s2->execute([$cp['id']]); $pendingAppts = (int)$s2->fetchColumn();
  $s3 = $pdo->prepare('SELECT COUNT(*) FROM medical_records WHERE patient_id=?'); $s3->execute([$cp['id']]); $totalRecords = (int)$s3->fetchColumn();
  $s4 = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE patient_id=? AND status='Unpaid'"); $s4->execute([$cp['id']]); $unpaidInvoices = (int)$s4->fetchColumn();

  $recentAppts = $pdo->prepare('SELECT a.*,d.name AS dept_name FROM appointments a LEFT JOIN departments d ON a.department_id=d.id WHERE a.patient_id=? ORDER BY a.appt_date DESC LIMIT 4');
  $recentAppts->execute([$cp['id']]); $recentAppts = $recentAppts->fetchAll();

  $recentRecords = $pdo->prepare('SELECT r.*,doc.full_name AS doc_name FROM medical_records r LEFT JOIN doctors doc ON r.doctor_id=doc.id WHERE r.patient_id=? ORDER BY r.visit_date DESC LIMIT 2');
  $recentRecords->execute([$cp['id']]); $recentRecords = $recentRecords->fetchAll();
?>
<div class="portal-wrap">
  <?php if ($portalMsg): ?>
    <div class="<?= $portalMsg['type']==='success'?'alert-success-custom':'alert-error-custom' ?> mb-4">
      <?= pclean($portalMsg['text']) ?>
    </div>
  <?php endif; ?>

  <div class="welcome-banner">
    <div class="pid-badge"><i class="bi bi-person-badge me-1"></i><?= pclean($patient['patient_id']) ?></div>
    <h2>Welcome back, <?= pclean(explode(' ', $patient['full_name'])[0]) ?>! 👋</h2>
    <p>Here's a summary of your health activity at Panacea Hospital.</p>
    <?php if ($unpaidInvoices > 0): ?>
      <div style="margin-top:12px;background:rgba(192,22,44,.2);border:1px solid rgba(192,22,44,.3);border-radius:8px;padding:10px 16px;font-size:.85rem;display:inline-block">
        <i class="bi bi-exclamation-triangle me-1"></i>
        You have <strong><?= $unpaidInvoices ?> unpaid invoice<?= $unpaidInvoices>1?'s':'' ?></strong>.
        <a href="/panacea/portal.php?page=billing" style="color:#ff9999;font-weight:600;margin-left:8px">View →</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-ico" style="background:#e8f2fb;color:var(--blue-mid)"><i class="bi bi-calendar2-week"></i></div><div><div class="stat-val"><?= $totalAppts ?></div><div class="stat-lbl">Appointments</div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-ico" style="background:#fff8e8;color:#d08000"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-val"><?= $pendingAppts ?></div><div class="stat-lbl">Pending</div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-ico" style="background:#edf7f3;color:var(--green)"><i class="bi bi-journal-medical"></i></div><div><div class="stat-val"><?= $totalRecords ?></div><div class="stat-lbl">Records</div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-ico" style="background:#fbe8ec;color:var(--red)"><i class="bi bi-droplet-fill"></i></div><div><div class="stat-val" style="font-size:1.3rem"><?= pclean($patient['blood_group']) ?></div><div class="stat-lbl">Blood Group</div></div></div></div>
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="section-card">
        <div class="section-card-head">
          <i class="bi bi-calendar2-week text-primary me-2"></i>
          <h5>Recent Appointments</h5>
          <a href="/panacea/portal.php?page=appointments" style="font-size:.8rem;color:var(--blue-mid);text-decoration:none;margin-left:auto">View All</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead><tr><th>Ref</th><th>Department</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($recentAppts as $a): ?>
              <tr>
                <td><code style="font-size:.72rem"><?= pclean($a['ref_number']) ?></code></td>
                <td style="font-size:.82rem"><?= pclean($a['dept_name']) ?></td>
                <td style="font-size:.82rem"><?= date('d M Y', strtotime($a['appt_date'])) ?></td>
                <td><?= portalStatusBadge($a['status']) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$recentAppts): ?><tr><td colspan="4" class="text-center text-muted py-3">No appointments yet</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="section-card mb-3">
        <div class="section-card-head"><i class="bi bi-lightning-charge-fill text-warning me-2"></i><h5>Quick Actions</h5></div>
        <div class="p-3">
          <a href="/panacea/portal.php?page=book" class="quick-action" style="background:linear-gradient(135deg,var(--blue-mid),var(--blue-bright));color:#fff;border-color:transparent"><i class="bi bi-calendar-plus"></i> Book New Appointment</a>
          <a href="/panacea/portal.php?page=records" class="quick-action"><i class="bi bi-journal-medical" style="color:var(--green)"></i> View Medical Records</a>
          <a href="/panacea/portal.php?page=billing" class="quick-action"><i class="bi bi-receipt" style="color:#d08000"></i> My Invoices & Billing</a>
          <a href="/panacea/portal.php?page=profile" class="quick-action"><i class="bi bi-person-gear" style="color:var(--blue-mid)"></i> Edit My Profile</a>
          <a href="tel:+251917111111" class="quick-action" style="background:#fff0f0;border-color:rgba(192,22,44,.2)"><i class="bi bi-telephone-fill" style="color:var(--red)"></i> Emergency: +251 917 111 111</a>
        </div>
      </div>
      <?php if ($recentRecords): ?>
      <div class="section-card">
        <div class="section-card-head"><i class="bi bi-journal-medical text-success me-2"></i><h5>Latest Records</h5></div>
        <div class="p-3">
          <?php foreach ($recentRecords as $r): ?>
          <div style="padding:12px;background:var(--bg);border-radius:10px;margin-bottom:8px">
            <div class="d-flex justify-content-between mb-1">
              <strong style="font-size:.82rem;color:var(--blue-deep)"><?= date('d M Y', strtotime($r['visit_date'])) ?></strong>
              <span style="font-size:.75rem;color:var(--muted)"><?= pclean($r['doc_name'] ?? '—') ?></span>
            </div>
            <?php if ($r['diagnosis']): ?>
              <p style="font-size:.78rem;color:var(--muted);margin:0"><?= pclean(substr($r['diagnosis'],0,60)) ?>...</p>
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

// ════════════════════════════════════════════════════════
//  APPOINTMENTS
// ════════════════════════════════════════════════════════
elseif ($page === 'appointments'):
  requirePatientLogin();
  $cp = currentPatient();
  $appts = $pdo->prepare('SELECT a.*,d.name AS dept_name,doc.full_name AS doc_name FROM appointments a LEFT JOIN departments d ON a.department_id=d.id LEFT JOIN doctors doc ON a.doctor_id=doc.id WHERE a.patient_id=? ORDER BY a.appt_date DESC');
  $appts->execute([$cp['id']]); $appts = $appts->fetchAll();
?>
<div class="portal-wrap">
  <?php if ($portalMsg): ?>
    <div class="<?= $portalMsg['type']==='success'?'alert-success-custom':'alert-error-custom' ?> mb-4">
      <?= pclean($portalMsg['text']) ?>
    </div>
  <?php endif; ?>
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h3 style="font-family:'Playfair Display',serif;color:var(--blue-deep)">My Appointments</h3>
    <a href="/panacea/portal.php?page=book" class="btn-nav-primary"><i class="bi bi-calendar-plus"></i> Book New</a>
  </div>
  <div class="section-card">
    <div class="table-responsive">
      <table class="table table-hover">
        <thead><tr><th>Reference</th><th>Department</th><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($appts as $a): ?>
          <tr>
            <td><code style="font-size:.75rem;background:var(--bg);padding:3px 8px;border-radius:6px"><?= pclean($a['ref_number']) ?></code></td>
            <td style="font-size:.85rem"><?= pclean($a['dept_name']) ?></td>
            <td style="font-size:.82rem;color:var(--muted)"><?= pclean($a['doc_name'] ?? '—') ?></td>
            <td style="font-size:.85rem"><?= date('d M Y', strtotime($a['appt_date'])) ?></td>
            <td style="font-size:.78rem;color:var(--muted)"><?= pclean($a['appt_time']) ?></td>
            <td><?= portalStatusBadge($a['status']) ?></td>
            <td>
              <?php if (in_array($a['status'], ['Pending','Confirmed'])): ?>
                <a href="/panacea/portal.php?page=cancel_appt&id=<?= $a['id'] ?>"
                   onclick="return confirm('Cancel appointment <?= pclean($a['ref_number']) ?>?')"
                   class="btn btn-sm btn-outline-danger" style="font-size:.75rem">
                  <i class="bi bi-x-circle me-1"></i>Cancel
                </a>
              <?php else: ?>
                <span style="font-size:.75rem;color:var(--muted)">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$appts): ?>
            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-calendar-x"></i><p>No appointments yet.<br><a href="/panacea/portal.php?page=book">Book your first appointment →</a></p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php

// ════════════════════════════════════════════════════════
//  MEDICAL RECORDS
// ════════════════════════════════════════════════════════
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
          <?php if ($r['chief_complaint']): ?><div class="col-md-6"><div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:4px">Chief Complaint</div><p style="font-size:.88rem;margin:0"><?= pclean($r['chief_complaint']) ?></p></div><?php endif; ?>
          <?php if ($r['diagnosis']): ?><div class="col-md-6"><div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:4px">Diagnosis</div><p style="font-size:.88rem;margin:0"><?= pclean($r['diagnosis']) ?></p></div><?php endif; ?>
          <?php if ($r['treatment']): ?><div class="col-md-6"><div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:4px">Treatment</div><p style="font-size:.88rem;margin:0"><?= pclean($r['treatment']) ?></p></div><?php endif; ?>
          <?php if ($r['prescription']): ?><div class="col-md-6"><div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:4px">Prescription</div><p style="font-size:.88rem;margin:0"><?= pclean($r['prescription']) ?></p></div><?php endif; ?>
          <?php if ($r['follow_up_date']): ?>
          <div class="col-12"><div style="background:#fff8e8;border-radius:8px;padding:10px 14px;font-size:.82rem;display:flex;align-items:center;gap:8px"><i class="bi bi-calendar-event" style="color:#d08000"></i><strong style="color:#d08000">Follow-up:</strong><?= date('d M Y', strtotime($r['follow_up_date'])) ?></div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="section-card"><div class="empty-state"><i class="bi bi-journal-x"></i><p>No medical records yet.<br>Records will appear here after your visits.</p></div></div>
  <?php endif; ?>
</div>

<?php

// ════════════════════════════════════════════════════════
//  BILLING
// ════════════════════════════════════════════════════════
elseif ($page === 'billing'):
  requirePatientLogin();
  $cp = currentPatient();

  // Check if invoices table exists
  try {
    $invoices = $pdo->prepare('SELECT i.*,COUNT(ii.id) AS item_count FROM invoices i LEFT JOIN invoice_items ii ON ii.invoice_id=i.id WHERE i.patient_id=? GROUP BY i.id ORDER BY i.created_at DESC');
    $invoices->execute([$cp['id']]); $invoices = $invoices->fetchAll();
    $totalPaid   = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM invoices WHERE patient_id=? AND status='Paid'"); $totalPaid->execute([$cp['id']]); $totalPaid = (float)$totalPaid->fetchColumn();
    $totalUnpaid = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM invoices WHERE patient_id=? AND status='Unpaid'"); $totalUnpaid->execute([$cp['id']]); $totalUnpaid = (float)$totalUnpaid->fetchColumn();
    $hasInvoiceTable = true;
  } catch (Exception $e) {
    $invoices = []; $totalPaid = 0; $totalUnpaid = 0; $hasInvoiceTable = false;
  }
?>
<div class="portal-wrap">
  <h3 style="font-family:'Playfair Display',serif;color:var(--blue-deep);margin-bottom:24px">My Billing & Invoices</h3>

  <?php if ($hasInvoiceTable && $invoices): ?>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-ico" style="background:#edf7f3;color:var(--green)"><i class="bi bi-check-circle"></i></div><div><div class="stat-val" style="font-size:1.3rem"><?= number_format($totalPaid) ?></div><div class="stat-lbl">Total Paid (ETB)</div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-ico" style="background:#fff0f0;color:var(--red)"><i class="bi bi-exclamation-circle"></i></div><div><div class="stat-val" style="font-size:1.3rem"><?= number_format($totalUnpaid) ?></div><div class="stat-lbl">Unpaid (ETB)</div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-ico" style="background:#e8f2fb;color:var(--blue-mid)"><i class="bi bi-receipt"></i></div><div><div class="stat-val"><?= count($invoices) ?></div><div class="stat-lbl">Total Invoices</div></div></div></div>
  </div>

  <?php foreach ($invoices as $inv): ?>
  <div class="invoice-card">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div>
        <div class="d-flex align-items-center gap-3 mb-2">
          <strong style="font-size:1rem;color:var(--blue-deep)"><?= pclean($inv['invoice_number']) ?></strong>
          <?= portalStatusBadge($inv['status']) ?>
        </div>
        <div style="font-size:.82rem;color:var(--muted)">
          <i class="bi bi-calendar me-1"></i><?= date('d M Y', strtotime($inv['created_at'])) ?>
          <?php if ($inv['paid_at']): ?> · <i class="bi bi-check-circle me-1" style="color:var(--green)"></i>Paid <?= date('d M Y', strtotime($inv['paid_at'])) ?><?php endif; ?>
          · <i class="bi bi-grid me-1"></i><?= $inv['item_count'] ?> service<?= $inv['item_count']!=1?'s':'' ?>
          <?php if ($inv['payment_method'] && $inv['status']==='Paid'): ?> · <?= pclean($inv['payment_method']) ?><?php endif; ?>
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-size:1.4rem;font-weight:700;font-family:'Playfair Display',serif;color:var(--blue-deep)"><?= number_format($inv['total'], 2) ?> <span style="font-size:.75rem;color:var(--muted)">ETB</span></div>
        <?php if ($inv['balance'] > 0): ?>
          <div style="font-size:.8rem;color:var(--red);font-weight:600">Balance: <?= number_format($inv['balance'],2) ?> ETB</div>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($inv['status'] === 'Unpaid'): ?>
    <div style="margin-top:12px;padding:10px 14px;background:#fff0f0;border-radius:8px;font-size:.82rem;color:var(--red);display:flex;align-items:center;gap:8px">
      <i class="bi bi-exclamation-triangle-fill"></i>
      This invoice is unpaid. Please visit Panacea Hospital or call <strong>+251 917 000 000</strong> to make payment.
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <?php else: ?>
  <div class="section-card">
    <div class="empty-state">
      <i class="bi bi-receipt"></i>
      <p>No invoices yet.<br>Your billing history will appear here after hospital visits.</p>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php

// ════════════════════════════════════════════════════════
//  BOOK APPOINTMENT
// ════════════════════════════════════════════════════════
elseif ($page === 'book'):
  requirePatientLogin();
?>
<div class="portal-wrap" style="max-width:680px">
  <h3 style="font-family:'Playfair Display',serif;color:var(--blue-deep);margin-bottom:24px"><i class="bi bi-calendar-plus me-2" style="color:var(--blue-bright)"></i>Book an Appointment</h3>
  <?php if ($bookMsg): ?><div class="alert-success-custom"><i class="bi bi-check-circle me-2"></i><?= $bookMsg ?></div><?php endif; ?>
  <?php if ($bookErr): ?><div class="alert-error-custom"><i class="bi bi-exclamation-triangle me-2"></i><?= pclean($bookErr) ?></div><?php endif; ?>
  <div class="section-card">
    <div class="p-4">
      <form method="POST">
        <div class="row g-3">
          <div class="col-12"><label class="form-label">Department *</label><select name="department_id" class="form-select" required><option value="">Select Department</option><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= pclean($d['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Preferred Date *</label><input type="date" name="appt_date" class="form-control" min="<?= date('Y-m-d') ?>" required/></div>
          <div class="col-md-6"><label class="form-label">Preferred Time</label><select name="appt_time" class="form-select"><option>Morning (7AM-12PM)</option><option>Afternoon (12PM-5PM)</option><option>Evening (5PM-9PM)</option></select></div>
          <div class="col-12"><label class="form-label">Reason / Symptoms</label><textarea name="reason" class="form-control" rows="3" placeholder="Describe your symptoms..."></textarea></div>
          <div class="col-12"><button type="submit" class="btn-auth"><i class="bi bi-calendar-check me-2"></i>Confirm Appointment</button></div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php

// ════════════════════════════════════════════════════════
//  PROFILE
// ════════════════════════════════════════════════════════
elseif ($page === 'profile'):
  requirePatientLogin();
  $cp = currentPatient();
  $patient = $pdo->prepare('SELECT * FROM patients WHERE id=?');
  $patient->execute([$cp['id']]); $patient = $patient->fetch();
?>
<div class="portal-wrap">
  <h3 style="font-family:'Playfair Display',serif;color:var(--blue-deep);margin-bottom:24px">My Profile</h3>

  <!-- Profile Tabs -->
  <div class="portal-tabs mb-4">
    <a href="#info" class="portal-tab active" onclick="showTab('info',this)"><i class="bi bi-person"></i> Personal Info</a>
    <a href="#edit" class="portal-tab" onclick="showTab('edit',this)"><i class="bi bi-pencil"></i> Edit Profile</a>
    <a href="#password" class="portal-tab" onclick="showTab('password',this)"><i class="bi bi-key"></i> Change Password</a>
  </div>

  <?php if ($profileMsg): ?>
    <div class="<?= $profileMsg['type']==='success'?'alert-success-custom':'alert-error-custom' ?> mb-4">
      <i class="bi bi-<?= $profileMsg['type']==='success'?'check-circle':'exclamation-triangle' ?> me-2"></i>
      <?= pclean($profileMsg['text']) ?>
    </div>
  <?php endif; ?>

  <!-- INFO TAB -->
  <div id="tab-info">
    <div class="section-card">
      <div class="p-4">
        <div class="d-flex align-items-center gap-4 mb-4">
          <div class="profile-avatar"><?= strtoupper(substr($patient['full_name'],0,1)) ?></div>
          <div>
            <h4 style="font-family:'Playfair Display',serif;color:var(--blue-deep);margin-bottom:4px"><?= pclean($patient['full_name']) ?></h4>
            <div style="background:var(--bg);display:inline-block;padding:4px 14px;border-radius:20px;font-size:.78rem;color:var(--muted)"><?= pclean($patient['patient_id']) ?></div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="info-row"><span class="info-label">Gender</span><span><?= pclean($patient['gender']) ?></span></div>
            <div class="info-row"><span class="info-label">Date of Birth</span><span><?= date('d M Y', strtotime($patient['date_of_birth'])) ?></span></div>
            <div class="info-row"><span class="info-label">Age</span><span><?= (int)(new DateTime($patient['date_of_birth']))->diff(new DateTime())->y ?> years</span></div>
            <div class="info-row"><span class="info-label">Blood Group</span><span style="font-weight:700;color:var(--red)"><?= pclean($patient['blood_group']) ?></span></div>
          </div>
          <div class="col-md-6">
            <div class="info-row"><span class="info-label">Phone</span><span><?= pclean($patient['phone']) ?></span></div>
            <div class="info-row"><span class="info-label">Email</span><span><?= pclean($patient['email'] ?: '—') ?></span></div>
            <div class="info-row"><span class="info-label">Address</span><span><?= pclean($patient['address'] ?: '—') ?></span></div>
            <div class="info-row"><span class="info-label">Status</span><span class="badge bg-success"><?= pclean($patient['status']) ?></span></div>
          </div>
        </div>
        <?php if ($patient['allergies']): ?>
        <div style="background:#fff0f0;border-radius:8px;padding:12px 16px;margin-top:16px;font-size:.85rem">
          <strong style="color:var(--red)"><i class="bi bi-exclamation-triangle me-1"></i>Allergies:</strong> <?= pclean($patient['allergies']) ?>
        </div>
        <?php endif; ?>
        <?php if ($patient['emergency_contact_name']): ?>
        <div style="background:#f0f4f9;border-radius:8px;padding:12px 16px;margin-top:8px;font-size:.85rem">
          <strong style="color:var(--blue-deep)"><i class="bi bi-telephone me-1"></i>Emergency Contact:</strong>
          <?= pclean($patient['emergency_contact_name']) ?> · <?= pclean($patient['emergency_contact_phone']) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- EDIT TAB -->
  <div id="tab-edit" style="display:none">
    <div class="section-card">
      <div class="p-4">
        <form method="POST">
          <input type="hidden" name="action" value="update_profile"/>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" value="<?= pclean($patient['full_name']) ?>" required/></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= pclean($patient['email'] ?? '') ?>"/></div>
            <div class="col-md-6"><label class="form-label">Blood Group</label>
              <select name="blood_group" class="form-select">
                <?php foreach (['Unknown','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                  <option <?= $patient['blood_group']===$bg?'selected':'' ?>><?= $bg ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="<?= pclean($patient['address'] ?? '') ?>"/></div>
            <div class="col-md-6"><label class="form-label">Emergency Contact Name</label><input type="text" name="emergency_contact_name" class="form-control" value="<?= pclean($patient['emergency_contact_name'] ?? '') ?>"/></div>
            <div class="col-md-6"><label class="form-label">Emergency Contact Phone</label><input type="text" name="emergency_contact_phone" class="form-control" value="<?= pclean($patient['emergency_contact_phone'] ?? '') ?>"/></div>
            <div class="col-12"><button type="submit" class="btn-auth"><i class="bi bi-check-circle me-2"></i>Save Changes</button></div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- PASSWORD TAB -->
  <div id="tab-password" style="display:none">
    <div class="section-card" style="max-width:480px">
      <div class="p-4">
        <form method="POST">
          <input type="hidden" name="action" value="change_password"/>
          <div class="mb-3"><label class="form-label">Current Password *</label><input type="password" name="current_password" class="form-control" required/></div>
          <div class="mb-3"><label class="form-label">New Password *</label><input type="password" name="new_password" class="form-control" required/><small style="color:var(--muted);font-size:.75rem">Min 6 characters</small></div>
          <div class="mb-4"><label class="form-label">Confirm New Password *</label><input type="password" name="confirm_password" class="form-control" required/></div>
          <button type="submit" class="btn-auth"><i class="bi bi-key me-2"></i>Change Password</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function showTab(name, el) {
  event.preventDefault();
  document.querySelectorAll('[id^="tab-"]').forEach(t => t.style.display='none');
  document.getElementById('tab-'+name).style.display='block';
  document.querySelectorAll('.portal-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
}
// Auto-show edit tab if profile message exists
<?php if ($profileMsg): ?>
document.addEventListener('DOMContentLoaded', () => {
  const action = '<?= $_POST['action'] ?? '' ?>';
  if (action === 'change_password') {
    showTab('password', document.querySelectorAll('.portal-tab')[2]);
  } else if (action === 'update_profile') {
    showTab('edit', document.querySelectorAll('.portal-tab')[1]);
  }
});
<?php endif; ?>
</script>

<?php endif; ?>

<!-- FOOTER -->
<footer style="background:var(--blue-deep);color:rgba(255,255,255,.5);text-align:center;padding:20px;font-size:.8rem;margin-top:60px">
  © <?= date('Y') ?> Panacea Hospital Patient Portal · Hawassa, Ethiopia ·
  <a href="/panacea/" style="color:rgba(255,255,255,.5)">Back to Hospital Website</a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
