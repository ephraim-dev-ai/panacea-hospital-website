<?php
require_once dirname(__FILE__) . '/../includes/helpers.php';
requireLogin();
$admin = currentAdmin();
if (!in_array($admin['role'], ['superadmin','admin'])) {
    flash('main','Access denied.','error');
    header('Location: /panacea/admin/index.php'); exit;
}

$pdo    = db();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($action === 'toggle' && $id) {
    $pdo->prepare('UPDATE services SET is_active = !is_active WHERE id=?')->execute([$id]);
    flash('main','Service status updated.','success');
    header('Location: /panacea/admin/services.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action,['add','edit'])) {
    verifyCsrf();
    $f = [
        'name'        => trim($_POST['name']        ?? ''),
        'category'    => $_POST['category']          ?? 'Other',
        'price'       => (float)($_POST['price']     ?? 0),
        'description' => trim($_POST['description']  ?? ''),
        'is_active'   => isset($_POST['is_active']) ? 1 : 0,
    ];
    if ($action === 'add') {
        $pdo->prepare('INSERT INTO services (name,category,price,description,is_active) VALUES (?,?,?,?,?)')
            ->execute(array_values($f));
        flash('main','Service added.','success');
    } else {
        $pdo->prepare('UPDATE services SET name=?,category=?,price=?,description=?,is_active=? WHERE id=?')
            ->execute([...array_values($f), $id]);
        flash('main','Service updated.','success');
    }
    header('Location: /panacea/admin/services.php'); exit;
}

if (in_array($action,['add','edit'])) {
    $svc = [];
    if ($action === 'edit' && $id) {
        $s = $pdo->prepare('SELECT * FROM services WHERE id=?');
        $s->execute([$id]); $svc = $s->fetch() ?: [];
    }
    $v = fn($k) => htmlspecialchars($svc[$k] ?? '', ENT_QUOTES);
    $cats = ['Consultation','Laboratory','Radiology','Surgery','Maternity','Pharmacy','Emergency','Inpatient','Other'];
    $pageTitle = $action === 'add' ? 'Add Service' : 'Edit Service';
    require_once dirname(__FILE__) . '/../includes/layout_header.php';
    ?>
    <a href="/panacea/admin/services.php" class="btn btn-sm btn-outline-secondary mb-4">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <div class="form-card" style="max-width:560px">
      <div class="form-card-head"><h4><?= $pageTitle ?></h4></div>
      <div class="form-card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf() ?>"/>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Service Name *</label>
              <input type="text" name="name" class="form-control" value="<?= $v('name') ?>" required/>
            </div>
            <div class="col-md-6">
              <label class="form-label">Category *</label>
              <select name="category" class="form-select">
                <?php foreach ($cats as $c): ?>
                  <option <?= ($v('category')===$c)?'selected':'' ?>><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Price (ETB) *</label>
              <div style="position:relative">
                <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.85rem">ETB</span>
                <input type="number" name="price" class="form-control" style="padding-left:44px"
                       value="<?= $v('price') ?>" min="0" step="0.01" required/>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <input type="text" name="description" class="form-control" value="<?= $v('description') ?>"/>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                       <?= ($svc['is_active'] ?? 1) ? 'checked' : '' ?>/>
                <label class="form-check-label">Active (available for billing)</label>
              </div>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check-lg me-1"></i>Save Service
              </button>
              <a href="/panacea/admin/services.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; exit;
}

// List grouped by category
$services = $pdo->query("SELECT * FROM services ORDER BY category, name")->fetchAll();
$grouped  = [];
foreach ($services as $s) $grouped[$s['category']][] = $s;

$catColors = [
    'Consultation'=>'primary','Laboratory'=>'success','Radiology'=>'info',
    'Surgery'=>'danger','Maternity'=>'pink','Pharmacy'=>'secondary',
    'Emergency'=>'warning','Inpatient'=>'dark','Other'=>'secondary'
];

$pageTitle = 'Services & Pricing';
require_once dirname(__FILE__) . '/../includes/layout_header.php';

// Total revenue potential
$totalServices = count($services);
$activeServices = count(array_filter($services, fn($s) => $s['is_active']));
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <div>
    <span style="font-size:.82rem;color:var(--muted)"><?= $activeServices ?> active services · <?= $totalServices ?> total</span>
  </div>
  <a href="?action=add" class="btn btn-primary ms-auto">
    <i class="bi bi-plus me-1"></i>Add Service
  </a>
</div>

<?php foreach ($grouped as $cat => $svcs): ?>
<div class="data-card mb-4">
  <div class="data-card-head">
    <span class="badge bg-<?= $catColors[$cat] ?? 'secondary' ?> me-2"><?= $cat ?></span>
    <h5><?= $cat ?> Services</h5>
    <span style="font-size:.78rem;color:var(--muted)"><?= count($svcs) ?> services</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr><th>Service Name</th><th>Description</th><th>Price</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($svcs as $s): ?>
        <tr>
          <td style="font-weight:500"><?= htmlspecialchars($s['name']) ?></td>
          <td style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($s['description'] ?? '—') ?></td>
          <td>
            <span style="font-weight:700;color:var(--blue-deep);font-size:.95rem">
              <?= number_format($s['price'], 2) ?>
            </span>
            <span style="font-size:.72rem;color:var(--muted)"> ETB</span>
          </td>
          <td><?= statusBadge($s['is_active'] ? 'Active' : 'Inactive') ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="?action=edit&id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil"></i>
              </a>
              <a href="?action=toggle&id=<?= $s['id'] ?>"
                 class="btn btn-sm <?= $s['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                <i class="bi <?= $s['is_active'] ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; ?>
