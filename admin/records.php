<?php
require_once dirname(__FILE__) . '/../includes/helpers.php';
requireLogin();
$admin = currentAdmin();
if (!in_array($admin['role'], ['superadmin','admin','doctor'])) {
    flash('main','Access denied.','error');
    header('Location: /panacea/admin/index.php'); exit;
}
$pdo    = db();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    verifyCsrf();
    $f = [
        'patient_id'      => (int)($_POST['patient_id']      ?? 0),
        'doctor_id'       => ($_POST['doctor_id']       ?? '') ?: null,
        'appointment_id'  => ($_POST['appointment_id']  ?? '') ?: null,
        'visit_date'      => $_POST['visit_date']       ?? date('Y-m-d'),
        'visit_type'      => $_POST['visit_type']       ?? 'Outpatient',
        'chief_complaint' => trim($_POST['chief_complaint'] ?? ''),
        'diagnosis'       => trim($_POST['diagnosis']        ?? ''),
        'treatment'       => trim($_POST['treatment']        ?? ''),
        'prescription'    => trim($_POST['prescription']     ?? ''),
        'lab_results'     => trim($_POST['lab_results']      ?? ''),
        'follow_up_date'  => ($_POST['follow_up_date']  ?? '') ?: null,
    ];

    if ($action === 'add') {
        $pdo->prepare('INSERT INTO medical_records
            (patient_id,doctor_id,appointment_id,visit_date,visit_type,
             chief_complaint,diagnosis,treatment,prescription,lab_results,follow_up_date)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute(array_values($f));
        flash('main','Medical record saved successfully.','success');
    } else {
        $pdo->prepare('UPDATE medical_records SET
            patient_id=?,doctor_id=?,appointment_id=?,visit_date=?,visit_type=?,
            chief_complaint=?,diagnosis=?,treatment=?,prescription=?,
            lab_results=?,follow_up_date=? WHERE id=?')
            ->execute([...array_values($f), $id]);
        flash('main','Record updated.','success');
    }
    $pid = $f['patient_id'];
    header("Location: /panacea/admin/patients.php?action=view&id=$pid"); exit;
}

if (in_array($action, ['add','edit'])) {
    $rec = [];
    if ($action === 'edit' && $id) {
        $s = $pdo->prepare('SELECT * FROM medical_records WHERE id=?');
        $s->execute([$id]); $rec = $s->fetch() ?: [];
    }
    $prePatientId = (int)($_GET['patient_id'] ?? $rec['patient_id'] ?? 0);
    $patients = $pdo->query('SELECT id,patient_id,full_name FROM patients ORDER BY full_name')->fetchAll();
    $doctors  = $pdo->query('SELECT id,full_name FROM doctors ORDER BY full_name')->fetchAll();
    $v = fn($k) => clean($rec[$k] ?? '');
    $pageTitle = $action === 'add' ? 'Add Medical Record' : 'Edit Medical Record';
    require_once dirname(__FILE__) . '/../includes/layout_header.php';
    ?>
    <div class="d-flex align-items-center gap-3 mb-4">
      <a href="/panacea/admin/patients.php?action=view&id=<?= $prePatientId ?>"
         class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Patient
      </a>
    </div>
    <div class="form-card">
      <div class="form-card-head"><h4><?= $pageTitle ?></h4></div>
      <div class="form-card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf() ?>"/>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Patient *</label>
              <select name="patient_id" class="form-select" required>
                <option value="">Select Patient</option>
                <?php foreach ($patients as $p): ?>
                  <option value="<?= $p['id'] ?>"
                          <?= ($prePatientId === $p['id']) ? 'selected' : '' ?>>
                    <?= clean($p['patient_id']) ?> – <?= clean($p['full_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Doctor</label>
              <select name="doctor_id" class="form-select">
                <option value="">— Unassigned —</option>
                <?php foreach ($doctors as $d): ?>
                  <option value="<?= $d['id'] ?>"
                          <?= ($v('doctor_id') == $d['id']) ? 'selected' : '' ?>>
                    <?= clean($d['full_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Visit Date *</label>
              <input type="date" name="visit_date" class="form-control"
                     value="<?= $v('visit_date') ?: date('Y-m-d') ?>" required/>
            </div>
            <div class="col-md-4">
              <label class="form-label">Visit Type</label>
              <select name="visit_type" class="form-select">
                <?php foreach (['Outpatient','Inpatient','Emergency','Telemedicine'] as $t): ?>
                  <option <?= $v('visit_type') === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Follow-up Date</label>
              <input type="date" name="follow_up_date" class="form-control"
                     value="<?= $v('follow_up_date') ?>"/>
            </div>
            <div class="col-12">
              <label class="form-label">Chief Complaint</label>
              <input type="text" name="chief_complaint" class="form-control"
                     value="<?= $v('chief_complaint') ?>"
                     placeholder="Main reason for visit"/>
            </div>
            <div class="col-md-6">
              <label class="form-label">Diagnosis</label>
              <textarea name="diagnosis" class="form-control" rows="3"
                        placeholder="Doctor's diagnosis..."><?= $v('diagnosis') ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Treatment Plan</label>
              <textarea name="treatment" class="form-control" rows="3"
                        placeholder="Treatment prescribed..."><?= $v('treatment') ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Prescription</label>
              <textarea name="prescription" class="form-control" rows="3"
                        placeholder="Medications prescribed..."><?= $v('prescription') ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Lab Results</label>
              <textarea name="lab_results" class="form-control" rows="3"
                        placeholder="Laboratory findings..."><?= $v('lab_results') ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check-lg me-1"></i>Save Record
              </button>
              <a href="javascript:history.back()" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; exit;
}

// LIST
$search = trim($_GET['q'] ?? '');
$where  = $search
    ? 'WHERE p.full_name LIKE ? OR r.diagnosis LIKE ? OR r.chief_complaint LIKE ?'
    : '';
$params = $search ? ["%$search%","%$search%","%$search%"] : [];

$records = $pdo->prepare("
    SELECT r.*,
           p.full_name  AS patient_name,
           p.patient_id AS pid,
           doc.full_name AS doc_name
    FROM medical_records r
    LEFT JOIN patients p  ON r.patient_id = p.id
    LEFT JOIN doctors doc ON r.doctor_id  = doc.id
    $where
    ORDER BY r.visit_date DESC
    LIMIT 50
");
$records->execute($params);
$records = $records->fetchAll();

$pageTitle = 'Medical Records';
require_once dirname(__FILE__) . '/../includes/layout_header.php';
?>
 <div class="d-flex align-items-center gap-3 mb-4">
  <form method="GET" class="search-bar flex-grow-1" style="max-width:400px">
    <i class="bi bi-search"></i>
    <input type="text" name="q" class="form-control"
           placeholder="Search patient, diagnosis…"
           value="<?= clean($search) ?>"/>
  </form>
  <a href="/panacea/admin/records.php?action=add" class="btn btn-primary ms-auto">
    <i class="bi bi-plus me-1"></i>Add Record
  </a>
</div>

<div class="data-card">
  <div class="data-card-head">
    <i class="bi bi-journal-medical text-success me-2"></i>
    <h5>Medical Records</h5>
    <span style="font-size:.78rem;color:var(--muted)"><?= count($records) ?> records</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>Patient</th>
          <th>Doctor</th>
          <th>Visit Date</th>
          <th>Type</th>
          <th>Complaint</th>
          <th>Diagnosis</th>
          <th>Follow-up</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $r): ?>
        <tr>
          <td>
            <a href="/panacea/admin/patients.php?action=view&id=<?= $r['patient_id'] ?>"
               style="color:var(--blue-mid);text-decoration:none;font-weight:500">
              <?= clean($r['patient_name'] ?? '—') ?>
            </a>
            <div style="font-size:.72rem;color:var(--muted)"><?= clean($r['pid'] ?? '') ?></div>
          </td>
          <td style="font-size:.82rem"><?= clean($r['doc_name'] ?? '—') ?></td>
          <td style="font-size:.82rem;white-space:nowrap">
            <?= date('d M Y', strtotime($r['visit_date'])) ?>
          </td>
          <td>
            <span class="badge bg-light text-dark" style="font-size:.72rem">
              <?= clean($r['visit_type']) ?>
            </span>
          </td>
          <td style="font-size:.82rem;max-width:160px;overflow:hidden;
                     text-overflow:ellipsis;white-space:nowrap">
            <?= clean($r['chief_complaint'] ?: '—') ?>
          </td>
          <td style="font-size:.82rem;max-width:160px;overflow:hidden;
                     text-overflow:ellipsis;white-space:nowrap">
            <?= clean($r['diagnosis'] ?: '—') ?>
          </td>
          <td style="font-size:.8rem;white-space:nowrap">
            <?= $r['follow_up_date']
                ? date('d M Y', strtotime($r['follow_up_date']))
                : '—' ?>
          </td>
          <td>
            <a href="?action=edit&id=<?= $r['id'] ?>"
               class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-pencil"></i>
            </a>
          </td>
          <a href="/panacea/admin/print_prescription.php?id=<?= $r['id'] ?>"
   target="_blank"
   class="btn btn-sm btn-outline-success" title="Print Prescription">
  <i class="bi bi-printer"></i>
</a>
        </tr>
        <?php endforeach; ?>
        <?php if (!$records): ?>
          <tr>
            <td colspan="8" class="text-center text-muted py-5">
              <i class="bi bi-journal-x" style="font-size:2rem;display:block;margin-bottom:8px"></i>
              No medical records found.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; ?>
