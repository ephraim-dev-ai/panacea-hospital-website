<?php
// includes/layout_header.php  — call with $pageTitle set
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();
$admin  = currentAdmin();
$stats  = dashStats();
$flash  = showFlash('main');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= clean($pageTitle ?? 'Dashboard') ?> – Panacea Hospital Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --sidebar-w: 260px;
      --blue-deep: #0a2e5c; --blue-mid: #1a5fa0; --blue-bright: #2e8dd4;
      --green: #3aaa8c; --red: #c0162c;
      --bg: #f0f4f9; --card: #ffffff;
      --text: #1a2b42; --muted: #7a8da8;
      --border: #e2e8f3;
      --shadow: 0 2px 16px rgba(10,46,92,.08);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); }

    /* SIDEBAR */
    .sidebar {
      position: fixed; top: 0; left: 0; bottom: 0; width: var(--sidebar-w);
      background: var(--blue-deep);
      display: flex; flex-direction: column;
      z-index: 1000; overflow-y: auto;
      transition: transform .3s ease;
    }
    .sidebar-brand {
      padding: 22px 20px 18px;
      border-bottom: 1px solid rgba(255,255,255,.08);
      display: flex; align-items: center; gap: 12px;
    }
    .brand-ico {
      width: 40px; height: 40px;
      background: linear-gradient(135deg, var(--blue-mid), var(--green));
      border-radius: 10px; display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 1.2rem; flex-shrink: 0;
    }
    .brand-txt strong { display: block; color: #fff; font-family: 'Playfair Display',serif; font-size: 1rem; }
    .brand-txt span   { color: rgba(255,255,255,.4); font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; }

    .nav-section {
      padding: 20px 12px 0;
      flex: 1;
    }
    .nav-label {
      color: rgba(255,255,255,.3); font-size: .65rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .1em; padding: 0 8px; margin-bottom: 6px;
    }
    .nav-item { margin-bottom: 2px; }
    .nav-link {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px; border-radius: 9px;
      color: rgba(255,255,255,.6); font-size: .875rem; font-weight: 500;
      text-decoration: none; transition: all .2s;
    }
    .nav-link i { font-size: 1rem; width: 20px; text-align: center; }
    .nav-link:hover { background: rgba(255,255,255,.07); color: #fff; }
    .nav-link.active { background: var(--blue-mid); color: #fff; }
    .nav-badge {
      margin-left: auto; background: var(--red);
      color: #fff; font-size: .65rem; font-weight: 700;
      padding: 2px 7px; border-radius: 20px; min-width: 20px; text-align: center;
    }
    .sidebar-footer {
      padding: 16px; border-top: 1px solid rgba(255,255,255,.08);
    }
    .admin-chip {
      display: flex; align-items: center; gap: 10px;
      background: rgba(255,255,255,.06); border-radius: 10px; padding: 10px 12px;
    }
    .admin-avatar {
      width: 34px; height: 34px; border-radius: 9px;
      background: linear-gradient(135deg, var(--blue-bright), var(--green));
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: .9rem; font-weight: 700; flex-shrink: 0;
    }
    .admin-chip .info strong { display: block; color: #fff; font-size: .8rem; }
    .admin-chip .info span { color: rgba(255,255,255,.4); font-size: .7rem; text-transform: capitalize; }

    /* MAIN */
    .main-wrap {
      margin-left: var(--sidebar-w);
      min-height: 100vh;
      display: flex; flex-direction: column;
    }
    .topbar {
      background: var(--card); border-bottom: 1px solid var(--border);
      padding: 14px 28px; display: flex; align-items: center; gap: 16px;
      position: sticky; top: 0; z-index: 100;
      box-shadow: 0 1px 8px rgba(10,46,92,.05);
    }
    .topbar-title { font-family: 'Playfair Display',serif; font-size: 1.2rem; color: var(--blue-deep); flex: 1; }
    .topbar-btn {
      background: none; border: none; width: 36px; height: 36px;
      border-radius: 9px; display: flex; align-items: center; justify-content: center;
      color: var(--muted); cursor: pointer; transition: all .2s; font-size: 1rem;
    }
    .topbar-btn:hover { background: var(--bg); color: var(--blue-mid); }
    .emergency-pill {
      background: rgba(192,22,44,.1); color: var(--red);
      border: 1px solid rgba(192,22,44,.2); border-radius: 20px;
      padding: 5px 14px; font-size: .75rem; font-weight: 600;
      display: flex; align-items: center; gap: 6px;
    }
    .dot-pulse { width: 7px; height: 7px; background: var(--red); border-radius: 50%; animation: dpulse 1.5s infinite; }
    @keyframes dpulse { 0%,100%{opacity:1} 50%{opacity:.3} }

    .content { padding: 28px; flex: 1; }

    /* CARDS */
    .stat-card {
      background: var(--card); border-radius: 14px; padding: 22px 24px;
      box-shadow: var(--shadow); border: 1px solid var(--border);
      display: flex; align-items: center; gap: 18px; transition: transform .2s;
    }
    .stat-card:hover { transform: translateY(-3px); }
    .stat-ico {
      width: 54px; height: 54px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.5rem; flex-shrink: 0;
    }
    .stat-val { font-family: 'Playfair Display',serif; font-size: 2rem; font-weight: 700; color: var(--blue-deep); line-height: 1; }
    .stat-lbl { font-size: .78rem; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin-top: 4px; }

    /* TABLE */
    .data-card { background: var(--card); border-radius: 14px; border: 1px solid var(--border); overflow: hidden; box-shadow: var(--shadow); }
    .data-card-head {
      padding: 18px 24px; border-bottom: 1px solid var(--border);
      display: flex; align-items: center; gap: 12px;
    }
    .data-card-head h5 { font-family: 'Playfair Display',serif; font-size: 1.1rem; color: var(--blue-deep); flex: 1; margin: 0; }
    .table { margin: 0; font-size: .875rem; }
    .table th { background: var(--bg); color: var(--muted); font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; padding: 12px 16px; border: none; white-space: nowrap; }
    .table td { padding: 13px 16px; border-color: var(--border); vertical-align: middle; }
    .table tbody tr:hover { background: #f7f9fc; }

    /* FORM CARD */
    .form-card { background: var(--card); border-radius: 14px; border: 1px solid var(--border); box-shadow: var(--shadow); }
    .form-card-head { padding: 20px 28px; border-bottom: 1px solid var(--border); }
    .form-card-head h4 { font-family: 'Playfair Display',serif; color: var(--blue-deep); margin: 0; font-size: 1.25rem; }
    .form-card-body { padding: 28px; }
    .form-label { font-size: .8rem; font-weight: 600; color: var(--blue-deep); margin-bottom: 5px; }
    .form-control, .form-select {
      border: 1.5px solid var(--border); border-radius: 9px;
      padding: 10px 14px; font-size: .88rem;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--blue-bright);
      box-shadow: 0 0 0 3px rgba(46,141,212,.12);
    }
    .section-divider { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--muted); padding: 20px 0 10px; border-top: 1px solid var(--border); margin-top: 10px; }

    /* BUTTONS */
    .btn-primary { background: linear-gradient(135deg, var(--blue-mid), var(--blue-bright)); border: none; border-radius: 9px; font-weight: 600; font-size: .875rem; }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(26,95,160,.35); }
    .btn-outline-primary { border-color: var(--blue-mid); color: var(--blue-mid); border-radius: 9px; font-size: .875rem; }
    .btn-sm { font-size: .78rem; padding: 5px 12px; border-radius: 7px; }

    /* SEARCH */
    .search-bar { position: relative; }
    .search-bar i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: .9rem; }
    .search-bar input { padding-left: 36px; border-radius: 9px; border: 1.5px solid var(--border); font-size: .875rem; height: 38px; }

    /* RESPONSIVE */
    @media (max-width: 991px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.open { transform: none; }
      .main-wrap { margin-left: 0; }
      .content { padding: 16px; }
    }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-ico"><i class="bi bi-hospital"></i></div>
    <div class="brand-txt">
      <strong>Panacea Hospital</strong>
      <span>Admin Portal</span>
    </div>
  </div>

  <div class="nav-section">
    <div class="nav-label mt-0">Main</div>
    <div class="nav-item">
      <a href="<?= ADMIN_URL ?>/index.php" class="nav-link <?= ($pageTitle === 'Dashboard') ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
    </div>

    <div class="nav-label mt-3">Patients</div>
    <div class="nav-item">
      <a href="<?= ADMIN_URL ?>/patients.php" class="nav-link <?= str_contains($pageTitle ?? '', 'Patient') ? 'active' : '' ?>">
        <i class="bi bi-people-fill"></i> All Patients
      </a>
    </div>
    <div class="nav-item">
      <a href="<?= ADMIN_URL ?>/patients.php?action=add" class="nav-link">
        <i class="bi bi-person-plus"></i> Register Patient
      </a>
    </div>
    <div class="nav-item">
      <a href="<?= ADMIN_URL ?>/records.php" class="nav-link <?= str_contains($pageTitle ?? '', 'Record') ? 'active' : '' ?>">
        <i class="bi bi-journal-medical"></i> Medical Records
      </a>
    </div>

    <div class="nav-label mt-3">Appointments</div>
    <div class="nav-item">
      <a href="<?= ADMIN_URL ?>/appointments.php" class="nav-link <?= str_contains($pageTitle ?? '', 'Appointment') ? 'active' : '' ?>">
        <i class="bi bi-calendar2-week"></i> All Appointments
        <?php if ($stats['pending_appts']): ?>
          <span class="nav-badge"><?= $stats['pending_appts'] ?></span>
        <?php endif; ?>
      </a>
    </div>
    <div class="nav-item">
      <a href="<?= ADMIN_URL ?>/appointments.php?action=add" class="nav-link">
        <i class="bi bi-calendar-plus"></i> New Appointment
      </a>
    </div>

    <div class="nav-label mt-3">Hospital</div>
    <div class="nav-item">
      <a href="<?= ADMIN_URL ?>/doctors.php" class="nav-link <?= str_contains($pageTitle ?? '', 'Doctor') ? 'active' : '' ?>">
        <i class="bi bi-person-badge"></i> Doctors
      </a>
    </div>
    <div class="nav-item">
      <a href="<?= ADMIN_URL ?>/departments.php" class="nav-link <?= str_contains($pageTitle ?? '', 'Department') ? 'active' : '' ?>">
        <i class="bi bi-building"></i> Departments
      </a>
    </div>
    <div class="nav-item">
      <a href="<?= ADMIN_URL ?>/messages.php" class="nav-link <?= str_contains($pageTitle ?? '', 'Message') ? 'active' : '' ?>">
        <i class="bi bi-envelope-fill"></i> Messages
        <?php if ($stats['unread_msgs']): ?>
          <span class="nav-badge"><?= $stats['unread_msgs'] ?></span>
        <?php endif; ?>
      </a>
    </div>

    <?php if ($admin['role'] === 'superadmin'): ?>
    <div class="nav-label mt-3">System</div>
    <div class="nav-item">
      <a href="<?= ADMIN_URL ?>/users.php" class="nav-link <?= str_contains($pageTitle ?? '', 'User') ? 'active' : '' ?>">
        <i class="bi bi-shield-lock"></i> Admin Users
      </a>
    </div>
    <?php endif; ?>
  </div>

  <div class="sidebar-footer">
    <div class="admin-chip">
      <div class="admin-avatar"><?= strtoupper(substr($admin['name'] ?: $admin['username'], 0, 1)) ?></div>
      <div class="info">
        <strong><?= clean($admin['name'] ?: $admin['username']) ?></strong>
        <span><?= clean($admin['role']) ?></span>
      </div>
      <a href="<?= ADMIN_URL ?>/logout.php" class="ms-auto text-danger" title="Logout" style="text-decoration:none">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</aside>

<!-- MAIN WRAPPER -->
<div class="main-wrap">
  <div class="topbar">
    <button class="topbar-btn d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('open')">
      <i class="bi bi-list"></i>
    </button>
    <div class="topbar-title"><?= clean($pageTitle ?? 'Dashboard') ?></div>
    <div class="emergency-pill">
      <div class="dot-pulse"></div>
      Emergency: +251 917 111 111
    </div>
    <a href="<?= SITE_URL ?>/index.php" target="_blank" class="topbar-btn" title="View Website">
      <i class="bi bi-box-arrow-up-right"></i>
    </a>
  </div>

  <div class="content">
    <?= $flash ?>
