<?php
// ============================================================
//  PANACEA HOSPITAL – Doctors Management with Photo Upload
//  admin/doctors.php — Replace existing doctors.php
// ============================================================
require_once dirname(__FILE__) . '/../includes/helpers.php';
requireLogin();
$admin = currentAdmin();
if (!in_array($admin['role'], ['superadmin','admin'])) {
    flash('main','Access denied.','error');
    header('Location: /panacea/admin/index.php'); exit;
}

$pdo         = db();
$action      = $_GET['action'] ?? 'list';
$id          = (int)($_GET['id'] ?? 0);
$departments = $pdo->query('SELECT * FROM departments ORDER BY name')->fetchAll();

// ── Create uploads directory if not exists ────────────────
$uploadDir = dirname(__DIR__) . '/uploads/doctors/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// ── DELETE ────────────────────────────────────────────────
if ($action === 'delete' && $id) {
    // Delete photo file if exists
    $stmt = $pdo->prepare('SELECT photo FROM doctors WHERE id=?');
    $stmt->execute([$id]); $doc = $stmt->fetch();
    if ($doc && $doc['photo'] && $doc['photo'] !== 'default-doctor.png') {
        $photoPath = $uploadDir . $doc['photo'];
        if (file_exists($photoPath)) unlink($photoPath);
    }
    $pdo->prepare('DELETE FROM doctors WHERE id=?')->execute([$id]);
    logActivity('Deleted doctor', "ID:$id");
    flash('main','Doctor removed.','success');
    header('Location: /panacea/admin/doctors.php'); exit;
}

// ── SAVE ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action,['add','edit'])) {
    verifyCsrf();
    $f = [
        'department_id'  => (int)$_POST['department_id'],
        'full_name'      => trim($_POST['full_name']      ?? ''),
        'specialization' => trim($_POST['specialization'] ?? ''),
        'email'          => trim($_POST['email']          ?? ''),
        'phone'          => trim($_POST['phone']          ?? ''),
        'years_exp'      => (int)($_POST['years_exp']     ?? 0),
        'bio'            => trim($_POST['bio']            ?? ''),
        'is_active'      => isset($_POST['is_active']) ? 1 : 0,
    ];

    // Handle photo upload
    $photoFilename = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file     = $_FILES['photo'];
        $allowed  = ['image/jpeg','image/png','image/gif','image/webp'];
        $maxSize  = 3 * 1024 * 1024; // 3MB

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed)) {
            flash('main','Invalid file type. Please upload JPG, PNG or GIF.','error');
            header('Location: /panacea/admin/doctors.php?action=' . $action . ($id?'&id='.$id:'')); exit;
        }
        if ($file['size'] > $maxSize) {
            flash('main','Photo too large. Maximum size is 3MB.','error');
            header('Location: /panacea/admin/doctors.php?action=' . $action . ($id?'&id='.$id:'')); exit;
        }

        // Generate unique filename
        $ext           = match($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'jpg'
        };
        $photoFilename = 'doctor_' . time() . '_' . rand(1000,9999) . '.' . $ext;

        // Delete old photo if editing
        if ($action === 'edit' && $id) {
            $old = $pdo->prepare('SELECT photo FROM doctors WHERE id=?');
            $old->execute([$id]); $old = $old->fetch();
            if ($old && $old['photo'] && $old['photo'] !== 'default-doctor.png') {
                $oldPath = $uploadDir . $old['photo'];
                if (file_exists($oldPath)) unlink($oldPath);
            }
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $photoFilename)) {
            flash('main','Failed to save photo. Please try again.','error');
            header('Location: /panacea/admin/doctors.php?action=' . $action . ($id?'&id='.$id:'')); exit;
        }
    }

    if ($action === 'add') {
        $pdo->prepare('INSERT INTO doctors
            (department_id,full_name,specialization,email,phone,years_exp,bio,is_active,photo)
            VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([...$f, $photoFilename ?? 'default-doctor.png']);
        logActivity('Added doctor', $f['full_name']);
        flash('main','Doctor added successfully.','success');
    } else {
        if ($photoFilename) {
            $pdo->prepare('UPDATE doctors SET
                department_id=?,full_name=?,specialization=?,email=?,
                phone=?,years_exp=?,bio=?,is_active=?,photo=? WHERE id=?')
                ->execute([...array_values($f), $photoFilename, $id]);
        } else {
            $pdo->prepare('UPDATE doctors SET
                department_id=?,full_name=?,specialization=?,email=?,
                phone=?,years_exp=?,bio=?,is_active=? WHERE id=?')
                ->execute([...array_values($f), $id]);
        }
        logActivity('Updated doctor', "ID:$id");
        flash('main','Doctor updated.','success');
    }
    header('Location: /panacea/admin/doctors.php'); exit;
}

// ── ADD / EDIT FORM ───────────────────────────────────────
if (in_array($action,['add','edit'])) {
    $doc = [];
    if ($action === 'edit' && $id) {
        $s = $pdo->prepare('SELECT * FROM doctors WHERE id=?');
        $s->execute([$id]); $doc = $s->fetch() ?: [];
    }
    $v = fn($k) => htmlspecialchars($doc[$k] ?? '', ENT_QUOTES);

    // Current photo URL
    $currentPhoto = null;
    if (!empty($doc['photo']) && $doc['photo'] !== 'default-doctor.png') {
        $currentPhoto = '/panacea/uploads/doctors/' . $doc['photo'];
    }

    $pageTitle = $action === 'add' ? 'Add Doctor' : 'Edit Doctor';
    require_once dirname(__FILE__) . '/../includes/layout_header.php';
    ?>
    <div class="d-flex align-items-center gap-3 mb-4">
      <a href="/panacea/admin/doctors.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
    </div>

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="form-card">
          <div class="form-card-head"><h4><?= $pageTitle ?></h4></div>
          <div class="form-card-body">
            <form method="POST" enctype="multipart/form-data">
              <input type="hidden" name="csrf_token" value="<?= csrf() ?>"/>
              <div class="row g-3">

                <div class="col-md-6">
                  <label class="form-label">Full Name *</label>
                  <input type="text" name="full_name" class="form-control"
                         value="<?= $v('full_name') ?>" required
                         placeholder="e.g. Tadesse Bekele"/>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Specialization *</label>
                  <input type="text" name="specialization" class="form-control"
                         value="<?= $v('specialization') ?>" required
                         placeholder="e.g. Internal Medicine Specialist"/>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Department *</label>
                  <select name="department_id" class="form-select" required>
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $d): ?>
                      <option value="<?= $d['id'] ?>"
                              <?= ($v('department_id') == $d['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Years Experience</label>
                  <input type="number" name="years_exp" class="form-control"
                         value="<?= $v('years_exp') ?>" min="0" max="60"/>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Status</label>
                  <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="is_active"
                           value="1" <?= ($doc['is_active'] ?? 1) ? 'checked' : '' ?>/>
                    <label class="form-check-label">Active</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email *</label>
                  <input type="email" name="email" class="form-control"
                         value="<?= $v('email') ?>" required/>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input type="text" name="phone" class="form-control"
                         value="<?= $v('phone') ?>" placeholder="+251 9XX XXX XXX"/>
                </div>
                <div class="col-12">
                  <label class="form-label">Bio / Professional Summary</label>
                  <textarea name="bio" class="form-control" rows="3"
                            placeholder="Brief professional background..."><?= $v('bio') ?></textarea>
                </div>

                <!-- PHOTO UPLOAD -->
                <div class="col-12">
                  <label class="form-label">Doctor Photo</label>
                  <div style="background:var(--bg);border-radius:12px;padding:20px;border:2px dashed var(--border)">
                    <div class="d-flex align-items-start gap-4 flex-wrap">

                      <!-- Preview -->
                      <div style="flex-shrink:0">
                        <div id="photoPreview"
                             style="width:120px;height:120px;border-radius:16px;overflow:hidden;
                                    background:linear-gradient(135deg,var(--blue-mid),var(--green-soft,#3aaa8c));
                                    display:flex;align-items:center;justify-content:center;
                                    border:3px solid #fff;box-shadow:var(--shadow-md)">
                          <?php if ($currentPhoto): ?>
                            <img src="<?= $currentPhoto ?>" id="previewImg"
                                 style="width:100%;height:100%;object-fit:cover;display:block"/>
                          <?php else: ?>
                            <div id="previewPlaceholder"
                                 style="color:#fff;font-size:2.5rem;font-weight:700">
                              <?= strtoupper(substr($doc['full_name'] ?? 'D', 0, 1)) ?>
                            </div>
                            <img id="previewImg" style="width:100%;height:100%;object-fit:cover;display:none"/>
                          <?php endif; ?>
                        </div>
                        <?php if ($currentPhoto): ?>
                          <div style="font-size:.7rem;color:var(--muted);text-align:center;margin-top:6px">Current photo</div>
                        <?php endif; ?>
                      </div>

                      <!-- Upload controls -->
                      <div style="flex:1;min-width:200px">
                        <input type="file" name="photo" id="photoInput"
                               accept="image/jpeg,image/png,image/gif,image/webp"
                               style="display:none" onchange="previewPhoto(this)"/>
                        <button type="button" onclick="document.getElementById('photoInput').click()"
                                class="btn btn-outline-primary mb-2"
                                style="font-size:.875rem">
                          <i class="bi bi-camera me-2"></i>
                          <?= $currentPhoto ? 'Change Photo' : 'Upload Photo' ?>
                        </button>
                        <div style="font-size:.78rem;color:var(--muted);line-height:1.6">
                          <i class="bi bi-info-circle me-1"></i>
                          Accepted: JPG, PNG, GIF, WebP<br>
                          Maximum size: 3MB<br>
                          Recommended: Square photo, min 300×300px
                        </div>
                        <div id="fileNameDisplay"
                             style="margin-top:8px;font-size:.8rem;color:var(--blue-mid);display:none">
                          <i class="bi bi-check-circle me-1"></i>
                          <span id="fileName"></span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="col-12 d-flex gap-2 mt-2">
                  <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-lg me-1"></i>
                    <?= $action === 'add' ? 'Add Doctor' : 'Save Changes' ?>
                  </button>
                  <a href="/panacea/admin/doctors.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Tips sidebar -->
      <div class="col-lg-4">
        <div class="data-card p-4">
          <h5 style="font-family:'Playfair Display',serif;color:var(--blue-deep);margin-bottom:16px">
            <i class="bi bi-lightbulb-fill me-2" style="color:#f6b83d"></i>Photo Tips
          </h5>
          <div style="font-size:.85rem;color:var(--muted);line-height:1.8">
            <p style="margin-bottom:12px">For the best results on the website and patient portal:</p>
            <ul style="padding-left:16px;margin:0">
              <li>Use a <strong style="color:var(--blue-deep)">professional headshot</strong></li>
              <li>Plain or blurred background works best</li>
              <li>Doctor should be <strong style="color:var(--blue-deep)">facing the camera</strong></li>
              <li>Good lighting, clear face</li>
              <li>Square format (1:1 ratio) preferred</li>
              <li>Minimum 300×300 pixels</li>
            </ul>
          </div>
          <div style="margin-top:20px;padding:14px;background:var(--bg);border-radius:10px;font-size:.82rem">
            <strong style="color:var(--blue-deep);display:block;margin-bottom:6px">
              <i class="bi bi-globe me-1"></i>Where photos appear:
            </strong>
            <ul style="padding-left:14px;margin:0;color:var(--muted);line-height:1.8">
              <li>Public hospital website</li>
              <li>Patient portal doctor cards</li>
              <li>Appointment booking page</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <script>
    function previewPhoto(input) {
      if (input.files && input.files[0]) {
        const file    = input.files[0];
        const reader  = new FileReader();

        // Show filename
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileNameDisplay').style.display = 'block';

        reader.onload = function(e) {
          const img  = document.getElementById('previewImg');
          const ph   = document.getElementById('previewPlaceholder');
          img.src    = e.target.result;
          img.style.display = 'block';
          if (ph) ph.style.display = 'none';
        };
        reader.readAsDataURL(file);
      }
    }
    </script>
    <?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; exit;
}

// ── LIST ──────────────────────────────────────────────────
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
    <span style="font-size:.78rem;color:var(--muted)"><?= count($doctors) ?> doctors</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>Photo</th><th>Name</th><th>Specialization</th>
          <th>Department</th><th>Experience</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($doctors as $d): ?>
        <tr>
          <td>
            <?php
            $hasPhoto = !empty($d['photo']) && $d['photo'] !== 'default-doctor.png';
            $photoPath = '/panacea/uploads/doctors/' . $d['photo'];
            ?>
            <?php if ($hasPhoto): ?>
              <img src="<?= htmlspecialchars($photoPath) ?>"
                   style="width:46px;height:46px;border-radius:12px;object-fit:cover;
                          border:2px solid var(--border)"
                   alt="<?= htmlspecialchars($d['full_name']) ?>"/>
            <?php else: ?>
              <div style="width:46px;height:46px;border-radius:12px;
                          background:linear-gradient(135deg,var(--blue-mid),var(--blue-bright));
                          display:flex;align-items:center;justify-content:center;
                          color:#fff;font-weight:700;font-size:1.1rem">
                <?= strtoupper(substr($d['full_name'], 0, 1)) ?>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <div style="font-weight:600;color:var(--blue-deep)">
              Dr. <?= htmlspecialchars($d['full_name']) ?>
            </div>
            <div style="font-size:.75rem;color:var(--muted)"><?= htmlspecialchars($d['email']) ?></div>
          </td>
          <td style="font-size:.83rem"><?= htmlspecialchars($d['specialization']) ?></td>
          <td style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($d['dept_name']) ?></td>
          <td style="font-size:.82rem">
            <span style="background:var(--bg);padding:3px 10px;border-radius:20px;font-size:.78rem">
              <?= $d['years_exp'] ?> yrs
            </span>
          </td>
          <td><?= statusBadge($d['is_active'] ? 'Active' : 'Inactive') ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="?action=edit&id=<?= $d['id'] ?>"
                 class="btn btn-sm btn-outline-secondary" title="Edit">
                <i class="bi bi-pencil"></i>
              </a>
              <a href="?action=edit&id=<?= $d['id'] ?>"
                 class="btn btn-sm btn-outline-primary" title="Upload Photo">
                <i class="bi bi-camera"></i>
              </a>
              <a href="?action=delete&id=<?= $d['id'] ?>"
                 class="btn btn-sm btn-outline-danger"
                 data-confirm="Remove Dr. <?= htmlspecialchars($d['full_name']) ?>? This cannot be undone."
                 title="Delete">
                <i class="bi bi-trash"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$doctors): ?>
          <tr>
            <td colspan="7" class="text-center text-muted py-5">
              <i class="bi bi-person-badge" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i>
              No doctors added yet.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; ?>
