 <?php
require_once dirname(__FILE__) . '/../includes/helpers.php';
requireLogin();
$admin = currentAdmin();
if (!in_array($admin['role'], ['superadmin','admin','receptionist','doctor'])) {
    flash('main','Access denied.','error');
    header('Location: /panacea/admin/index.php'); exit;
}
$admin = currentAdmin();
if (!in_array($admin['role'], ['superadmin','admin'])) {
    flash('main','Access denied.','error');
    header('Location: /panacea/admin/index.php'); exit;
}
$pdo    = db();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action,['add','edit'])) {
    verifyCsrf();
    $f = [
        'name'        => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'icon'        => trim($_POST['icon'] ?? 'bi-hospital'),
        'is_active'   => isset($_POST['is_active']) ? 1 : 0,
    ];
    if ($action === 'add') {
        $pdo->prepare('INSERT INTO departments (name,description,icon,is_active) VALUES (?,?,?,?)')
            ->execute(array_values($f));
        flash('main','Department added.','success');
    } else {
        $pdo->prepare('UPDATE departments SET name=?,description=?,icon=?,is_active=? WHERE id=?')
            ->execute([...array_values($f), $id]);
        flash('main','Department updated.','success');
    }
    header('Location: /panacea/admin/departments.php'); exit;
}

if (in_array($action,['add','edit'])) {
    $dept = [];
    if ($action === 'edit' && $id) {
        $s = $pdo->prepare('SELECT * FROM departments WHERE id=?');
        $s->execute([$id]); $dept = $s->fetch() ?: [];
    }
    $v = fn($k) => clean($dept[$k] ?? '');
    $pageTitle = $action === 'add' ? 'Add Department' : 'Edit Department';
    require_once dirname(__FILE__) . '/../includes/layout_header.php';
    ?>
    <div class="d-flex align-items-center gap-3 mb-4">
      <a href="/panacea/admin/departments.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
    </div>
    <div class="form-card" style="max-width:560px">
      <div class="form-card-head"><h4><?= $pageTitle ?></h4></div>
      <div class="form-card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf() ?>"/>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Department Name *</label>
              <input type="text" name="name" class="form-control" value="<?= $v('name') ?>" required/>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="2"><?= $v('description') ?></textarea>
            </div>
            <div class="col-md-8">
              <label class="form-label">Bootstrap Icon Class</label>
              <input type="text" name="icon" class="form-control"
                     value="<?= $v('icon') ?: 'bi-hospital' ?>"
                     placeholder="e.g. bi-heart-pulse-fill"/>
              <div style="font-size:.75rem;color:var(--muted);margin-top:4px">
                Preview: <i class="bi <?= $v('icon') ?: 'bi-hospital' ?>" style="font-size:1.2rem;color:var(--blue-mid)"></i>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                       <?= ($dept['is_active'] ?? 1) ? 'checked' : '' ?>/>
                <label class="form-check-label">Active</label>
              </div>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check-lg me-1"></i>Save Department
              </button>
              <a href="/panacea/admin/departments.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; exit;
}

$depts = $pdo->query("
    SELECT d.*, COUNT(doc.id) AS doc_count
    FROM departments d
    LEFT JOIN doctors doc ON doc.department_id = d.id
    GROUP BY d.id
    ORDER BY d.name
")->fetchAll();
$pageTitle = 'Departments';
require_once dirname(__FILE__) . '/../includes/layout_header.php';
?>
<div class="d-flex justify-content-end mb-4">
  <a href="?action=add" class="btn btn-primary">
    <i class="bi bi-plus me-1"></i>Add Department
  </a>
</div>
<div class="data-card">
  <div class="data-card-head">
    <i class="bi bi-building text-primary me-2"></i>
    <h5>Hospital Departments</h5>
    <span style="font-size:.78rem;color:var(--muted)"><?= count($depts) ?> departments</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr><th>Icon</th><th>Name</th><th>Description</th><th>Doctors</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($depts as $d): ?>
        <tr>
          <td><i class="bi <?= clean($d['icon']) ?>" style="font-size:1.4rem;color:var(--blue-mid)"></i></td>
          <td style="font-weight:600"><?= clean($d['name']) ?></td>
          <td style="font-size:.82rem;color:var(--muted);max-width:280px"><?= clean($d['description'] ?? '') ?></td>
          <td>
            <span style="background:var(--bg);padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:600">
              <?= $d['doc_count'] ?>
            </span>
          </td>
          <td><?= statusBadge($d['is_active'] ? 'Active' : 'Inactive') ?></td>
          <td>
            <a href="?action=edit&id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-pencil"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$depts): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No departments found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; ?>