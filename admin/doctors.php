 <?php
require_once dirname(__FILE__) . '/../includes/helpers.php';
requireLogin();
$pdo    = db();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$departments = $pdo->query('SELECT * FROM departments ORDER BY name')->fetchAll();

if ($action === 'delete' && $id) {
    $pdo->prepare('DELETE FROM doctors WHERE id=?')->execute([$id]);
    flash('main','Doctor removed.','success');
    header('Location: /panacea/admin/doctors.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action,['add','edit'])) {
    verifyCsrf();
    $f = [
        'department_id'  => (int)$_POST['department_id'],
        'full_name'      => trim($_POST['full_name'] ?? ''),
        'specialization' => trim($_POST['specialization'] ?? ''),
        'email'          => trim($_POST['email'] ?? ''),
        'phone'          => trim($_POST['phone'] ?? ''),
        'years_exp'      => (int)($_POST['years_exp'] ?? 0),
        'bio'            => trim($_POST['bio'] ?? ''),
        'is_active'      => isset($_POST['is_active']) ? 1 : 0,
    ];
    if ($action === 'add') {
        $pdo->prepare('INSERT INTO doctors
            (department_id,full_name,specialization,email,phone,years_exp,bio,is_active)
            VALUES (?,?,?,?,?,?,?,?)')
            ->execute(array_values($f));
        flash('main','Doctor added successfully.','success');
    } else {
        $pdo->prepare('UPDATE doctors SET
            department_id=?,full_name=?,specialization=?,email=?,
            phone=?,years_exp=?,bio=?,is_active=? WHERE id=?')
            ->execute([...array_values($f), $id]);
        flash('main','Doctor updated.','success');
    }
    header('Location: /panacea/admin/doctors.php'); exit;
}

if (in_array($action,['add','edit'])) {
    $doc = [];
    if ($action === 'edit' && $id) {
        $s = $pdo->prepare('SELECT * FROM doctors WHERE id=?');
        $s->execute([$id]); $doc = $s->fetch() ?: [];
    }
    $v = fn($k) => clean($doc[$k] ?? '');
    $pageTitle = $action === 'add' ? 'Add Doctor' : 'Edit Doctor';
    require_once dirname(__FILE__) . '/../includes/layout_header.php';
    ?>
    <div class="d-flex align-items-center gap-3 mb-4">
      <a href="/panacea/admin/doctors.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
    </div>
    <div class="form-card" style="max-width:680px">
      <div class="form-card-head"><h4><?= $pageTitle ?></h4></div>
      <div class="form-card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf() ?>"/>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full Name *</label>
              <input type="text" name="full_name" class="form-control" value="<?= $v('full_name') ?>" required/>
            </div>
            <div class="col-md-6">
              <label class="form-label">Specialization *</label>
              <input type="text" name="specialization" class="form-control" value="<?= $v('specialization') ?>" required/>
            </div>
            <div class="col-md-6">
              <label class="form-label">Department *</label>
              <select name="department_id" class="form-select" required>
                <option value="">Select Department</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= $d['id'] ?>" <?= ($v('department_id') == $d['id']) ? 'selected' : '' ?>>
                    <?= clean($d['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Years Experience</label>
              <input type="number" name="years_exp" class="form-control" value="<?= $v('years_exp') ?>" min="0" max="60"/>
             </div>
            <div class="col-md-3">
              <label class="form-label">Active</label>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                       <?= ($doc['is_active'] ?? 1) ? 'checked' : '' ?>/>
                <label class="form-check-label">Active</label>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email *</label>
              <input type="email" name="email" class="form-control" value="<?= $v('email') ?>" required/>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= $v('phone') ?>"/>
            </div>
            <div class="col-12">
              <label class="form-label">Bio</label>
              <textarea name="bio" class="form-control" rows="3"><?= $v('bio') ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check-lg me-1"></i>Save
              </button>
              <a href="/panacea/admin/doctors.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; exit;
}

$doctors = $pdo->query("
    SELECT d.*, dep.name AS dept_name
    FROM doctors d
    LEFT JOIN departments dep ON d.department_id = dep.id
    ORDER BY d.full_name
")->fetchAll();

$pageTitle = 'Doctors';
require_once dirname(__FILE__) . '/../includes/layout_header.php';
?>
<div class="d-flex justify-content-end mb-4">
  <a href="?action=add" class="btn btn-primary">
    <i class="bi bi-person-plus me-1"></i>Add Doctor
  </a>
</div>
<div class="data-card">
  <div class="data-card-head">
    <i class="bi bi-person-badge text-primary me-2"></i>
    <h5>Doctors & Specialists</h5>
    <span style="font-size:.78rem;color:var(--muted)"><?= count($doctors) ?> total</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr><th>Name</th><th>Specialization</th><th>Department</th><th>Experience</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($doctors as $d): ?>
        <tr>
          <td style="font-weight:500"><?= clean($d['full_name']) ?></td>
          <td style="font-size:.83rem"><?= clean($d['specialization']) ?></td>
          <td style="font-size:.82rem;color:var(--muted)"><?= clean($d['dept_name']) ?></td>
          <td style="font-size:.82rem"><?= $d['years_exp'] ?> yrs</td>
          <td style="font-size:.8rem"><?= clean($d['email']) ?></td>
          <td style="font-size:.8rem"><?= clean($d['phone'] ?? '—') ?></td>
          <td><?= statusBadge($d['is_active'] ? 'Active' : 'Inactive') ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="?action=edit&id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil"></i>
              </a>
              <a href="?action=delete&id=<?= $d['id'] ?>"
                 class="btn btn-sm btn-outline-danger"
                 data-confirm="Remove Dr. <?= clean($d['full_name']) ?>?">
                <i class="bi bi-trash"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$doctors): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No doctors added yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; ?>