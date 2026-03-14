 <?php
require_once dirname(__FILE__) . '/../includes/helpers.php';
requireLogin();

$pdo    = db();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

$departments = $pdo->query('SELECT * FROM departments WHERE is_active=1 ORDER BY name')->fetchAll();
$doctors     = $pdo->query('SELECT d.*,dep.name AS dept_name FROM doctors d JOIN departments dep ON d.department_id=dep.id WHERE d.is_active=1 ORDER BY d.full_name')->fetchAll();

if ($action === 'status' && $id && isset($_GET['s'])) {
    $allowed = ['Pending','Confirmed','Completed','Cancelled','No-Show'];
    $s = $_GET['s'];
    if (in_array($s, $allowed)) {
        $pdo->prepare('UPDATE appointments SET status=? WHERE id=?')->execute([$s, $id]);
    }
    header('Location: /panacea/admin/appointments.php'); exit;
}

if ($action === 'delete' && $id) {
    $pdo->prepare('DELETE FROM appointments WHERE id=?')->execute([$id]);
    flash('main', 'Appointment deleted.', 'success');
    header('Location: /panacea/admin/appointments.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    verifyCsrf();
    $f = [
        'patient_name'  => trim($_POST['patient_name']  ?? ''),
        'patient_phone' => trim($_POST['patient_phone'] ?? ''),
        'patient_email' => trim($_POST['patient_email'] ?? ''),
        'patient_id'    => ($_POST['patient_id'] ?? '') ?: null,
        'doctor_id'     => ($_POST['doctor_id']  ?? '') ?: null,
        'department_id' => (int)($_POST['department_id'] ?? 0),
        'appt_date'     => $_POST['appt_date']   ?? '',
        'appt_time'     => $_POST['appt_time']   ?? 'Morning (7AM-12PM)',
        'reason'        => trim($_POST['reason']  ?? ''),
        'status'        => $_POST['status']       ?? 'Pending',
        'notes'         => trim($_POST['notes']   ?? ''),
    ];
    if ($action === 'add') {
        $f['ref_number'] = generateApptRef();
        $pdo->prepare('INSERT INTO appointments
            (ref_number,patient_id,patient_name,patient_phone,patient_email,doctor_id,
             department_id,appt_date,appt_time,reason,status,notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$f['ref_number'],$f['patient_id'],$f['patient_name'],
                       $f['patient_phone'],$f['patient_email'],$f['doctor_id'],
                       $f['department_id'],$f['appt_date'],$f['appt_time'],
                       $f['reason'],$f['status'],$f['notes']]);
        flash('main', 'Appointment ' . $f['ref_number'] . ' created.', 'success');
    } else {
        $pdo->prepare('UPDATE appointments SET
            patient_id=?,patient_name=?,patient_phone=?,patient_email=?,doctor_id=?,
            department_id=?,appt_date=?,appt_time=?,reason=?,status=?,notes=? WHERE id=?')
            ->execute([$f['patient_id'],$f['patient_name'],$f['patient_phone'],
                       $f['patient_email'],$f['doctor_id'],$f['department_id'],
                       $f['appt_date'],$f['appt_time'],$f['reason'],
                       $f['status'],$f['notes'],$id]);
        flash('main', 'Appointment updated.', 'success');
    }
    header('Location: /panacea/admin/appointments.php'); exit;
}

if (in_array($action, ['add','edit'])) {
    $appt = [];
    if ($action === 'edit' && $id) {
        $s = $pdo->prepare('SELECT * FROM appointments WHERE id=?');
        $s->execute([$id]); $appt = $s->fetch() ?: [];
    }
    $prePatientId = (int)($_GET['patient_id'] ?? 0);
    $prePatient   = null;
    if ($prePatientId) {
        $s = $pdo->prepare('SELECT * FROM patients WHERE id=?');
        $s->execute([$prePatientId]); $prePatient = $s->fetch();
    }
    $allPatients = $pdo->query('SELECT id,patient_id,full_name,phone FROM patients ORDER BY full_name')->fetchAll();
    $v = fn($k) => clean($appt[$k] ?? '');
 $pageTitle = $action === 'add' ? 'New Appointment' : 'Edit Appointment';
    require_once dirname(__FILE__) . '/../includes/layout_header.php';
    ?>
    <div class="d-flex align-items-center gap-3 mb-4">
      <a href="/panacea/admin/appointments.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="form-card">
      <div class="form-card-head"><h4><?= $pageTitle ?></h4></div>
      <div class="form-card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf() ?>"/>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Select Existing Patient (optional)</label>
              <select name="patient_id" class="form-select" id="patientSelect" onchange="fillPatient(this)">
                <option value="">— Walk-in / New patient —</option>
                <?php foreach ($allPatients as $p): ?>
                  <option value="<?= $p['id'] ?>"
                          data-name="<?= clean($p['full_name']) ?>"
                          data-phone="<?= clean($p['phone']) ?>"
                          <?= ((int)($appt['patient_id'] ?? 0) === $p['id'] || $prePatientId === $p['id']) ? 'selected' : '' ?>>
                    <?= clean($p['patient_id']) ?> — <?= clean($p['full_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Patient Name *</label>
              <input type="text" name="patient_name" id="pName" class="form-control"
                     value="<?= $v('patient_name') ?: clean($prePatient['full_name'] ?? '') ?>" required/>
            </div>
            <div class="col-md-3"><label class="form-label">Phone *</label>
              <input type="text" name="patient_phone" id="pPhone" class="form-control"
                     value="<?= $v('patient_phone') ?: clean($prePatient['phone'] ?? '') ?>" required/>
            </div>
            <div class="col-md-3"><label class="form-label">Email</label>
              <input type="email" name="patient_email" class="form-control" value="<?= $v('patient_email') ?>"/>
            </div>
            <div class="col-md-4"><label class="form-label">Department *</label>
              <select name="department_id" class="form-select" required id="deptSel" onchange="filterDoctors()">
                <option value="">Select Department</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= $d['id'] ?>" <?= ($v('department_id') == $d['id']) ? 'selected' : '' ?>>
                    <?= clean($d['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4"><label class="form-label">Doctor</label>
              <select name="doctor_id" class="form-select" id="docSel">
                <option value="">— Any available —</option>
                <?php foreach ($doctors as $d): ?>
                  <option value="<?= $d['id'] ?>" data-dept="<?= $d['department_id'] ?>"
                          <?= ($v('doctor_id') == $d['id']) ? 'selected' : '' ?>>
                    <?= clean($d['full_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4"><label class="form-label">Status</label>
              <select name="status" class="form-select">
                <?php foreach (['Pending','Confirmed','Completed','Cancelled','No-Show'] as $s): ?>
                  <option <?= $v('status') === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
</div>
            <div class="col-md-4"><label class="form-label">Date *</label>
              <input type="date" name="appt_date" class="form-control" value="<?= $v('appt_date') ?>" required/>
            </div>
            <div class="col-md-4"><label class="form-label">Time Slot</label>
              <select name="appt_time" class="form-select">
                <?php foreach (['Morning (7AM-12PM)','Afternoon (12PM-5PM)','Evening (5PM-9PM)'] as $t): ?>
                  <option <?= $v('appt_time') === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12"><label class="form-label">Reason</label>
              <textarea name="reason" class="form-control" rows="2"><?= $v('reason') ?></textarea>
            </div>
            <div class="col-12"><label class="form-label">Internal Notes</label>
              <textarea name="notes" class="form-control" rows="2"><?= $v('notes') ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check-lg me-1"></i><?= $action === 'add' ? 'Create Appointment' : 'Save Changes' ?>
              </button>
              <a href="/panacea/admin/appointments.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <script>
      function fillPatient(sel) {
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('pName').value  = opt.dataset.name  || '';
        document.getElementById('pPhone').value = opt.dataset.phone || '';
      }
      function filterDoctors() {
        const dept = document.getElementById('deptSel').value;
        document.querySelectorAll('#docSel option[data-dept]').forEach(o => {
          o.style.display = (!dept || o.dataset.dept === dept) ? '' : 'none';
        });
      }
      filterDoctors();
      const ps = document.getElementById('patientSelect');
      if (ps.value) fillPatient(ps);
    </script>
    <?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; exit;
}

// LIST
$search     = trim($_GET['q'] ?? '');
$filterStat = $_GET['status'] ?? '';
$filterDate = $_GET['date']   ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 15;
$offset     = ($page - 1) * $perPage;

$conditions = []; $params = [];
if ($search) {
    $conditions[] = '(a.patient_name LIKE ? OR a.patient_phone LIKE ? OR a.ref_number LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%");
}
if ($filterStat) { $conditions[] = 'a.status=?'; $params[] = $filterStat; }
if ($filterDate)  { $conditions[] = 'a.appt_date=?'; $params[] = $filterDate; }
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM appointments a $where");
$total->execute($params); $total = (int)$total->fetchColumn();

$appts = $pdo->prepare("
    SELECT a.*, d.name AS dept_name, doc.full_name AS doc_name
    FROM appointments a
    LEFT JOIN departments d   ON a.department_id = d.id
    LEFT JOIN doctors doc     ON a.doctor_id     = doc.id
    $where ORDER BY a.appt_date DESC, a.id DESC
    LIMIT $perPage OFFSET $offset
");
$appts->execute($params); $appts = $appts->fetchAll();

$pg = paginate($total, $perPage, $page);
$pageTitle = 'Appointments';
require_once dirname(__FILE__) . '/../includes/layout_header.php';
?>

<form method="GET" class="row g-2 align-items-end mb-4">
  <div class="col-auto flex-grow-1">
    <div class="search-bar">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="form-control" placeholder="Search name, phone, ref…" value="<?= clean($search) ?>"/>
    </div>
  </div>
  <div class="col-auto">
    <select name="status" class="form-select" style="font-size:.875rem;height:38px">
      <option value="">All Statuses</option>
      <?php foreach (['Pending','Confirmed','Completed','Cancelled','No-Show'] as $s): ?>
        <option <?= $filterStat === $s ? 'selected' : '' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <input type="date" name="date" class="form-control" style="font-size:.875rem;height:38px" value="<?= clean($filterDate) ?>"/>
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-outline-primary" style="height:38px">Filter</button>
    <a href="/panacea/admin/appointments.php" class="btn btn-outline-secondary" style="height:38px">Clear</a>
  </div>
  <div class="col-auto ms-auto">
    <a href="/panacea/admin/appointments.php?action=add" class="btn btn-primary" style="height:38px">
      <i class="bi bi-calendar-plus me-1"></i>New Appointment
    </a>
  </div>
</form>

<div class="data-card">
  <div class="data-card-head">
    <i class="bi bi-calendar2-week text-primary me-2"></i>
    <h5>Appointments</h5>
    <span style="font-size:.78rem;color:var(--muted)"><?= number_format($total) ?> total</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr><th>Ref</th><th>Patient</th><th>Phone</th><th>Department</th><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($appts as $a): ?>
        <tr>
          <td><code style="font-size:.72rem;background:#f0f4f9;padding:3px 7px;border-radius:5px"><?= clean($a['ref_number']) ?></code></td>
          <td style="font-weight:500"><?= clean($a['patient_name']) ?></td>
          <td style="font-size:.82rem"><?= clean($a['patient_phone']) ?></td>
          <td style="font-size:.8rem"><?= clean($a['dept_name']) ?></td>
          <td style="font-size:.8rem;color:var(--muted)"><?= clean($a['doc_name'] ?? '—') ?></td>
          <td style="font-size:.82rem;white-space:nowrap"><?= date('d M Y', strtotime($a['appt_date'])) ?></td>
          <td style="font-size:.75rem;color:var(--muted)"><?= clean($a['appt_time']) ?></td>
          <td>
            <div class="dropdown">
              <button class="btn btn-sm dropdown-toggle border-0 p-0" data-bs-toggle="dropdown">
                <?= statusBadge($a['status']) ?>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach (['Pending','Confirmed','Completed','Cancelled','No-Show'] as $s): ?>
                  <li><a class="dropdown-item" style="font-size:.82rem"
                         href="?action=status&id=<?= $a['id'] ?>&s=<?= urlencode($s) ?>"><?= $s ?></a></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </td>
          <td>
            <div class="d-flex gap-1">
              <a href="?action=edit&id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
              <a href="?action=delete&id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger"
                 data-confirm="Delete this appointment?"><i class="bi bi-trash"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$appts): ?>
          <tr><td colspan="9" class="text-center text-muted py-5">No appointments found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; ?>