<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/layout_header.php';

$pdo = db();

// Recent appointments
$recentAppts = $pdo->query("
    SELECT a.*, d.name AS dept_name, doc.full_name AS doc_name
    FROM appointments a
    LEFT JOIN departments d   ON a.department_id = d.id
    LEFT JOIN doctors doc     ON a.doctor_id     = doc.id
    ORDER BY a.created_at DESC LIMIT 6
")->fetchAll();

// Recent patients
$recentPatients = $pdo->query("
    SELECT * FROM patients ORDER BY registered_at DESC LIMIT 5
")->fetchAll();

// Appointments per department (chart data)
$deptData = $pdo->query("
    SELECT d.name, COUNT(a.id) AS total
    FROM departments d
    LEFT JOIN appointments a ON a.department_id = d.id
    GROUP BY d.id ORDER BY total DESC
")->fetchAll();

// Monthly appointments (last 6 months)
$monthlyData = $pdo->query("
    SELECT DATE_FORMAT(appt_date,'%b %Y') AS month,
           COUNT(*) AS total
    FROM appointments
    WHERE appt_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(appt_date), MONTH(appt_date)
    ORDER BY appt_date ASC
")->fetchAll();
?>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card">
      <div class="stat-ico" style="background:#e8f2fb;color:#1a5fa0"><i class="bi bi-people-fill"></i></div>
      <div>
        <div class="stat-val"><?= number_format($stats['patients']) ?></div>
        <div class="stat-lbl">Total Patients</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card">
      <div class="stat-ico" style="background:#fff8e8;color:#d08000"><i class="bi bi-calendar2-check-fill"></i></div>
      <div>
        <div class="stat-val"><?= $stats['today_appts'] ?></div>
        <div class="stat-lbl">Today's Appts</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card">
      <div class="stat-ico" style="background:#fff0f0;color:#c0162c"><i class="bi bi-hourglass-split"></i></div>
      <div>
        <div class="stat-val"><?= $stats['pending_appts'] ?></div>
        <div class="stat-lbl">Pending</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card">
      <div class="stat-ico" style="background:#edf7f3;color:#3aaa8c"><i class="bi bi-person-badge-fill"></i></div>
      <div>
        <div class="stat-val"><?= $stats['doctors'] ?></div>
        <div class="stat-lbl">Active Doctors</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card">
      <div class="stat-ico" style="background:#f0ebfc;color:#7c4ddc"><i class="bi bi-calendar-fill"></i></div>
      <div>
        <div class="stat-val"><?= number_format($stats['total_appts']) ?></div>
        <div class="stat-lbl">Total Appts</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card">
      <div class="stat-ico" style="background:#fbe8ec;color:#c0162c"><i class="bi bi-envelope-fill"></i></div>
      <div>
        <div class="stat-val"><?= $stats['unread_msgs'] ?></div>
        <div class="stat-lbl">Unread Msgs</div>
      </div>
    </div>
  </div>
</div>

<!-- CHARTS ROW -->
<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="data-card">
      <div class="data-card-head">
        <i class="bi bi-bar-chart-fill text-primary me-2"></i>
        <h5>Monthly Appointments</h5>
        <span style="font-size:.75rem;color:var(--muted)">Last 6 months</span>
      </div>
      <div class="p-4">
        <canvas id="monthlyChart" height="100"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="data-card">
      <div class="data-card-head">
        <i class="bi bi-pie-chart-fill text-success me-2"></i>
        <h5>By Department</h5>
      </div>
      <div class="p-4">
        <canvas id="deptChart" height="170"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- TABLES ROW -->
<div class="row g-3">
  <div class="col-lg-7">
    <div class="data-card">
      <div class="data-card-head">
        <i class="bi bi-calendar2-week text-primary me-2"></i>
        <h5>Recent Appointments</h5>
        <a href="<?= ADMIN_URL ?>/appointments.php" class="btn btn-sm btn-outline-primary ms-auto">View All</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Ref</th><th>Patient</th><th>Department</th><th>Date</th><th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentAppts as $a): ?>
            <tr>
              <td><code style="font-size:.75rem"><?= clean($a['ref_number']) ?></code></td>
              <td><?= clean($a['patient_name']) ?></td>
              <td><span style="font-size:.8rem;color:var(--muted)"><?= clean($a['dept_name']) ?></span></td>
              <td style="white-space:nowrap;font-size:.82rem"><?= date('d M Y', strtotime($a['appt_date'])) ?></td>
              <td><?= statusBadge($a['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$recentAppts): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">No appointments yet</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="data-card">
      <div class="data-card-head">
        <i class="bi bi-people-fill text-success me-2"></i>
        <h5>Recent Patients</h5>
        <a href="<?= ADMIN_URL ?>/patients.php" class="btn btn-sm btn-outline-primary ms-auto">View All</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr><th>ID</th><th>Name</th><th>Gender</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recentPatients as $p): ?>
            <tr>
              <td><code style="font-size:.72rem"><?= clean($p['patient_id']) ?></code></td>
              <td>
                <a href="<?= ADMIN_URL ?>/patients.php?action=view&id=<?= $p['id'] ?>"
                   style="color:var(--blue-mid);text-decoration:none;font-weight:500">
                  <?= clean($p['full_name']) ?>
                </a>
              </td>
              <td style="font-size:.82rem;color:var(--muted)"><?= clean($p['gender']) ?></td>
              <td><?= statusBadge($p['status']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const monthlyLabels = <?= json_encode(array_column($monthlyData, 'month')) ?>;
const monthlyVals   = <?= json_encode(array_map('intval', array_column($monthlyData, 'total'))) ?>;
const deptLabels    = <?= json_encode(array_column($deptData, 'name')) ?>;
const deptVals      = <?= json_encode(array_map('intval', array_column($deptData, 'total'))) ?>;

new Chart(document.getElementById('monthlyChart'), {
  type: 'bar',
  data: {
    labels: monthlyLabels,
    datasets: [{
      label: 'Appointments',
      data: monthlyVals,
      backgroundColor: 'rgba(46,141,212,.75)',
      borderRadius: 8, borderSkipped: false,
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: {
      y: { grid: { color: '#f0f4f9' }, ticks: { font: { size: 11 } } },
      x: { grid: { display: false }, ticks: { font: { size: 11 } } }
    }
  }
});

const palette = ['#2e8dd4','#3aaa8c','#f6b83d','#e8334a','#7c4ddc','#0a2e5c','#c0162c','#1a9e7a'];
new Chart(document.getElementById('deptChart'), {
  type: 'doughnut',
  data: {
    labels: deptLabels,
    datasets: [{ data: deptVals, backgroundColor: palette, borderWidth: 2, borderColor: '#fff' }]
  },
  options: {
    plugins: {
      legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } }
    },
    cutout: '62%'
  }
});
</script>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
