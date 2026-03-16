<?php
require_once dirname(__FILE__) . '/../includes/helpers.php';
requireLogin();
$admin = currentAdmin();
if (!in_array($admin['role'], ['superadmin','admin','receptionist','doctor'])) {
    flash('main','Access denied.','error');
    header('Location: /panacea/admin/index.php'); exit;
}

$pdo    = db();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    verifyCsrf();
    $pdo->prepare('DELETE FROM patients WHERE id=?')->execute([$id]);
    flash('main', 'Patient record deleted.', 'success');
    header('Location: /panacea/admin/patients.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    verifyCsrf();
    $f = [
        'full_name'               => trim($_POST['full_name'] ?? ''),
        'gender'                  => $_POST['gender'] ?? 'Male',
        'date_of_birth'           => $_POST['date_of_birth'] ?? '',
        'phone'                   => trim($_POST['phone'] ?? ''),
        'email'                   => trim($_POST['email'] ?? ''),
        'address'                 => trim($_POST['address'] ?? ''),
        'city'                    => trim($_POST['city'] ?? 'Hawassa'),
        'blood_group'             => $_POST['blood_group'] ?? 'Unknown',
        'emergency_contact_name'  => trim($_POST['emergency_contact_name'] ?? ''),
        'emergency_contact_phone' => trim($_POST['emergency_contact_phone'] ?? ''),
        'allergies'               => trim($_POST['allergies'] ?? ''),
        'notes'                   => trim($_POST['notes'] ?? ''),
        'status'                  => $_POST['status'] ?? 'Active',
    ];

    if ($action === 'add') {
        $f['patient_id'] = generatePatientId();
        $pdo->prepare('INSERT INTO patients
            (patient_id,full_name,gender,date_of_birth,phone,email,address,city,
             blood_group,emergency_contact_name,emergency_contact_phone,allergies,notes,status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute(array_values($f));
        flash('main', 'Patient ' . $f['full_name'] . ' registered (ID: ' . $f['patient_id'] . ').', 'success');
        header('Location: /panacea/admin/patients.php'); exit;
    } else {
        $pdo->prepare('UPDATE patients SET
            full_name=?,gender=?,date_of_birth=?,phone=?,email=?,address=?,city=?,
            blood_group=?,emergency_contact_name=?,emergency_contact_phone=?,
            allergies=?,notes=?,status=? WHERE id=?')
            ->execute([...array_values($f), $id]);
        flash('main', 'Patient updated.', 'success');
        header('Location: /panacea/admin/patients.php?action=view&id=' . $id); exit;
    }
}

if ($action === 'view' && $id) {
    $stmt = $pdo->prepare('SELECT * FROM patients WHERE id=?');
    $stmt->execute([$id]); $patient = $stmt->fetch();
    if (!$patient) { flash('main','Patient not found.','error'); header('Location: /panacea/admin/patients.php'); exit; }

    $appts = $pdo->prepare('SELECT a.*,d.name AS dept_name FROM appointments a LEFT JOIN departments d ON a.department_id=d.id WHERE a.patient_id=? ORDER BY a.appt_date DESC');
    $appts->execute([$id]); $appts = $appts->fetchAll();

    $records = $pdo->prepare('SELECT r.*,doc.full_name AS doc_name FROM medical_records r LEFT JOIN doctors doc ON r.doctor_id=doc.id WHERE r.patient_id=? ORDER BY r.visit_date DESC');
    $records->execute([$id]); $records = $records->fetchAll();

    $pageTitle = 'Patient: ' . $patient['full_name'];
    require_once dirname(__FILE__) . '/../includes/layout_header.php';
    ?>
    <div class="d-flex align-items-center gap-3 mb-4">
      <a href="/panacea/admin/patients.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
      <a href="/panacea/admin/patients.php?action=edit&id=<?= $id ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
      <a href="/panacea/admin/appointments.php?action=add&patient_id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar-plus me-1"></i>Book Appointment</a>
      </div>
    <div class="row g-3">
      <div class="col-lg-4">
        <div class="data-card p-4 text-center mb-3">
          <div style="width:80px;height:80px;border-radius:20px;background:linear-gradient(135deg,#1a5fa0,#3aaa8c);display:flex;align-items:center;justify-content:center;font-size:2rem;color:#fff;margin:0 auto 16px">
            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
          </div>
          <h4 style="font-family:'Playfair Display',serif;color:var(--blue-deep)"><?= clean($patient['full_name']) ?></h4>
          <div style="color:var(--muted);font-size:.82rem;margin-bottom:12px"><?= clean($patient['patient_id']) ?></div>
          <?= statusBadge($patient['status']) ?>
        </div>
        <div class="data-card p-4">
          <table class="table table-sm" style="font-size:.85rem">
            <tr><th style="color:var(--muted);border:none;padding:8px 0">Age</th><td style="border:none;padding:8px 0"><?= calcAge($patient['date_of_birth']) ?> yrs</td></tr>
            <tr><th style="color:var(--muted)">Gender</th><td><?= clean($patient['gender']) ?></td></tr>
            <tr><th style="color:var(--muted)">DOB</th><td><?= date('d M Y', strtotime($patient['date_of_birth'])) ?></td></tr>
            <tr><th style="color:var(--muted)">Blood</th><td><strong style="color:#c0162c"><?= clean($patient['blood_group']) ?></strong></td></tr>
            <tr><th style="color:var(--muted)">Phone</th><td><?= clean($patient['phone']) ?></td></tr>
            <tr><th style="color:var(--muted)">Email</th><td><?= clean($patient['email'] ?: '—') ?></td></tr>
            <tr><th style="color:var(--muted)">City</th><td><?= clean($patient['city']) ?></td></tr>
          </table>
          <?php if ($patient['allergies']): ?>
            <div style="background:#fff0f0;border-radius:8px;padding:10px 12px;font-size:.8rem">
              <strong style="color:#c0162c"><i class="bi bi-exclamation-triangle me-1"></i>Allergies:</strong>
              <?= clean($patient['allergies']) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="data-card mb-3">
          <div class="data-card-head"><i class="bi bi-calendar2-week text-primary me-2"></i><h5>Appointments (<?= count($appts) ?>)</h5></div>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead><tr><th>Ref</th><th>Department</th><th>Date</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($appts as $a): ?>
                <tr>
                  <td><code style="font-size:.75rem"><?= clean($a['ref_number']) ?></code></td>
                  <td style="font-size:.82rem"><?= clean($a['dept_name']) ?></td>
                  <td style="font-size:.82rem"><?= date('d M Y', strtotime($a['appt_date'])) ?></td>
                  <td><?= statusBadge($a['status']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$appts): ?><tr><td colspan="4" class="text-center text-muted py-3">No appointments</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="data-card">
          <div class="data-card-head">
            <i class="bi bi-journal-medical text-success me-2"></i><h5>Medical Records (<?= count($records) ?>)</h5>
            <a href="/panacea/admin/records.php?action=add&patient_id=<?= $id ?>" class="btn btn-sm btn-outline-success ms-auto"><i class="bi bi-plus me-1"></i>Add Record</a>
          </div>
          <div class="p-3">
            <?php if ($records): ?>
              <?php foreach ($records as $r): ?>
              <div style="border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:12px">
                <div class="d-flex justify-content-between mb-2">
                  <strong style="font-size:.9rem;color:var(--blue-deep)"><?= date('d M Y', strtotime($r['visit_date'])) ?></strong>
                  <span style="font-size:.78rem;color:var(--muted)"><?= clean($r['doc_name'] ?? '—') ?></span>
                </div>
                <?php if ($r['diagnosis']): ?><p style="font-size:.83rem;margin:4px 0"><strong>Diagnosis:</strong> <?= clean($r['diagnosis']) ?></p><?php endif; ?>
                <?php if ($r['treatment']): ?><p style="font-size:.83rem;margin:4px 0"><strong>Treatment:</strong> <?= clean($r['treatment']) ?></p><?php endif; ?>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="text-center text-muted py-4">No medical records on file.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; exit;
}

if (in_array($action, ['add','edit'])) {
    $patient = [];
    if ($action === 'edit' && $id) {
        $stmt = $pdo->prepare('SELECT * FROM patients WHERE id=?');
        $stmt->execute([$id]); $patient = $stmt->fetch() ?: [];
    }
   $v = fn($k) => clean($patient[$k] ?? $_POST[$k] ?? '');
// Debug: make sure patient data loaded
if ($action === 'edit' && empty($patient)) {
    flash('main', 'Patient not found.', 'error');
    header('Location: /panacea/admin/patients.php'); exit;
}
    $pageTitle = $action === 'add' ? 'Register Patient' : 'Edit Patient';
    require_once dirname(__FILE__) . '/../includes/layout_header.php';
    ?>
    <div class="d-flex align-items-center gap-3 mb-4">
      <a href="/panacea/admin/patients.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="form-card">
      <div class="form-card-head"><h4><?= $pageTitle ?></h4></div>
      <div class="form-card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf() ?>"/>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Full Name *</label>
            <input type="text" name="full_name" class="form-control" value="<?= $v('full_name') ?>" required/></div>
            <div class="col-md-3"><label class="form-label">Gender *</label>
              <select name="gender" class="form-select" required>
                <?php foreach (['Male','Female','Other'] as $g): ?>
                  <option <?= $v('gender')===$g?'selected':'' ?>><?= $g ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3"><label class="form-label">Date of Birth *</label><input type="date" name="date_of_birth" class="form-control" value="<?= $v('date_of_birth') ?>" required/></div>
            <div class="col-md-4"><label class="form-label">Phone *</label><input type="text" name="phone" class="form-control" value="<?= $v('phone') ?>" required/></div>
            <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= $v('email') ?>"/></div>
            <div class="col-md-4"><label class="form-label">Blood Group</label>
              <select name="blood_group" class="form-select">
                <?php foreach (['Unknown','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                  <option <?= $v('blood_group')===$bg?'selected':'' ?>><?= $bg ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-8"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="<?= $v('address') ?>"/></div>
            <div class="col-md-4"><label class="form-label">City</label><input type="text" name="city" class="form-control" value="<?= $v('city')?:'Hawassa' ?>"/></div>
            <div class="col-md-6"><label class="form-label">Emergency Contact Name</label><input type="text" name="emergency_contact_name" class="form-control" value="<?= $v('emergency_contact_name') ?>"/></div>
            <div class="col-md-6"><label class="form-label">Emergency Contact Phone</label><input type="text" name="emergency_contact_phone" class="form-control" value="<?= $v('emergency_contact_phone') ?>"/></div>
            <div class="col-md-6"><label class="form-label">Allergies</label><input type="text" name="allergies" class="form-control" value="<?= $v('allergies') ?>"/></div>
            <div class="col-md-3"><label class="form-label">Status</label>
              <select name="status" class="form-select">
                <?php foreach (['Active','Inactive','Discharged'] as $s): ?>
                  <option <?= $v('status')===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"><?= $v('notes') ?></textarea></div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i><?= $action==='add'?'Register Patient':'Save Changes' ?></button>
              <a href="/panacea/admin/patients.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; exit;
}

// LIST
$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where  = $search ? 'WHERE full_name LIKE ? OR phone LIKE ? OR patient_id LIKE ? OR email LIKE ?' : '';
$params = $search ? ["%$search%","%$search%","%$search%","%$search%"] : [];

$total = $pdo->prepare("SELECT COUNT(*) FROM patients $where");
$total->execute($params); $total = (int)$total->fetchColumn();

$patients = $pdo->prepare("SELECT * FROM patients $where ORDER BY registered_at DESC LIMIT $perPage OFFSET $offset");
$patients->execute($params); $patients = $patients->fetchAll();

$pg = paginate($total, $perPage, $page);
$pageTitle = 'Patients';
require_once dirname(__FILE__) . '/../includes/layout_header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <form method="GET" class="search-bar flex-grow-1" style="max-width:360px">
    <i class="bi bi-search"></i>
    <input type="text" name="q" class="form-control" placeholder="Search by name, phone, ID…" value="<?= clean($search) ?>"/>
  </form>
  <a href="/panacea/admin/patients.php?action=add" class="btn btn-primary ms-auto">
    <i class="bi bi-person-plus me-1"></i>Register Patient
  </a>
</div>

<div class="data-card">
  <div class="data-card-head">
    <i class="bi bi-people-fill text-primary me-2"></i>
    <h5>All Patients</h5>
    <span style="font-size:.78rem;color:var(--muted)"><?= number_format($total) ?> total</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr><th>Patient ID</th><th>Full Name</th><th>Gender</th><th>Age</th><th>Phone</th><th>Blood</th><th>Status</th><th>Registered</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($patients as $p): ?>
        <tr>
          <td><code style="font-size:.75rem;background:#f0f4f9;padding:3px 7px;border-radius:5px"><?= clean($p['patient_id']) ?></code></td>
          <td><a href="?action=view&id=<?= $p['id'] ?>" style="color:var(--blue-mid);text-decoration:none;font-weight:500"><?= clean($p['full_name']) ?></a></td>
          <td style="font-size:.82rem;color:var(--muted)"><?= clean($p['gender']) ?></td>
          <td style="font-size:.82rem"><?= calcAge($p['date_of_birth']) ?></td>
          <td style="font-size:.82rem"><?= clean($p['phone']) ?></td>
          <td><span style="font-weight:700;color:#c0162c"><?= clean($p['blood_group']) ?></span></td>
          <td><?= statusBadge($p['status']) ?></td>
          <td style="font-size:.78rem;color:var(--muted)"><?= date('d M Y', strtotime($p['registered_at'])) ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="?action=view&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
              <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
              <a href="?action=delete&id=<?= $p['id'] ?>&csrf_token=<?= csrf() ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete this patient?"><i class="bi bi-trash"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$patients): ?>
          <tr><td colspan="9" class="text-center text-muted py-5">No patients found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; ?>