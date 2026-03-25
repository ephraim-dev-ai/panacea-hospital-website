<?php
// ============================================================
//  PANACEA HOSPITAL – Reports & Exports
//  admin/reports.php
// ============================================================
require_once dirname(__FILE__) . '/../includes/helpers.php';
requireLogin();
$admin = currentAdmin();
$pdo   = db();

$type      = $_GET['type']      ?? 'appointments';
$dateFrom  = $_GET['date_from'] ?? date('Y-m-01');        // First day of current month
$dateTo    = $_GET['date_to']   ?? date('Y-m-d');         // Today
$deptId    = (int)($_GET['dept_id']  ?? 0);
$doctorId  = (int)($_GET['doctor_id'] ?? 0);
$status    = $_GET['status']    ?? '';
$export    = $_GET['export']    ?? '';

$departments = $pdo->query('SELECT * FROM departments WHERE is_active=1 ORDER BY name')->fetchAll();
$doctors     = $pdo->query('SELECT * FROM doctors WHERE is_active=1 ORDER BY full_name')->fetchAll();

// ── EXPORT TO CSV (works without PhpSpreadsheet) ──────────
if ($export === 'csv') {
    $filename = $type . '_report_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    // Add BOM for Excel UTF-8
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    if ($type === 'appointments') {
        fputcsv($out, ['#','Reference','Patient Name','Patient ID','Department','Doctor','Date','Time Slot','Status','Reason']);
        $rows = getAppointmentData($pdo, $dateFrom, $dateTo, $deptId, $status);
        foreach ($rows as $i => $r) {
            fputcsv($out, [
                $i+1,
                $r['ref_number'],
                $r['patient_name'],
                $r['pid'] ?? '',
                $r['dept_name'],
                $r['doc_name'] ?? '—',
                date('d M Y', strtotime($r['appt_date'])),
                $r['appt_time'],
                $r['status'],
                $r['reason'] ?? '',
            ]);
        }
    } elseif ($type === 'patients') {
        fputcsv($out, ['#','Patient ID','Full Name','Gender','Age','DOB','Phone','Email','Blood Group','City','Status','Registered']);
        $rows = getPatientData($pdo, $dateFrom, $dateTo);
        foreach ($rows as $i => $r) {
            fputcsv($out, [
                $i+1,
                $r['patient_id'],
                $r['full_name'],
                $r['gender'],
                calcAge($r['date_of_birth']),
                date('d M Y', strtotime($r['date_of_birth'])),
                $r['phone'],
                $r['email'] ?? '',
                $r['blood_group'],
                $r['city'] ?? '',
                $r['status'],
                date('d M Y', strtotime($r['registered_at'])),
            ]);
        }
    } elseif ($type === 'revenue') {
        fputcsv($out, ['#','Invoice #','Patient','Patient ID','Date','Services','Subtotal (ETB)','Tax (ETB)','Discount (ETB)','Total (ETB)','Paid (ETB)','Balance (ETB)','Status','Payment Method']);
        $rows = getRevenueData($pdo, $dateFrom, $dateTo, $status);
        foreach ($rows as $i => $r) {
            fputcsv($out, [
                $i+1,
                $r['invoice_number'],
                $r['patient_name'],
                $r['pid'] ?? '',
                date('d M Y', strtotime($r['created_at'])),
                $r['item_count'],
                number_format($r['subtotal'], 2),
                number_format($r['tax_amount'], 2),
                number_format($r['discount'], 2),
                number_format($r['total'], 2),
                number_format($r['amount_paid'], 2),
                number_format($r['balance'], 2),
                $r['status'],
                $r['payment_method'],
            ]);
        }
        // Summary rows
        fputcsv($out, []);
        fputcsv($out, ['SUMMARY']);
        $totals = getRevenueSummary($pdo, $dateFrom, $dateTo);
        fputcsv($out, ['Total Revenue (Paid)', number_format($totals['total_paid'], 2) . ' ETB']);
        fputcsv($out, ['Total Unpaid',         number_format($totals['total_unpaid'], 2) . ' ETB']);
        fputcsv($out, ['Total Invoices',        $totals['total_invoices']]);
        fputcsv($out, ['Paid Invoices',         $totals['paid_invoices']]);
    } elseif ($type === 'departments') {
        fputcsv($out, ['#','Department','Total Appointments','Completed','Pending','Cancelled','Revenue (ETB)']);
        $rows = getDeptData($pdo, $dateFrom, $dateTo);
        foreach ($rows as $i => $r) {
            fputcsv($out, [
                $i+1,
                $r['dept_name'],
                $r['total'],
                $r['completed'],
                $r['pending'],
                $r['cancelled'],
                number_format($r['revenue'] ?? 0, 2),
            ]);
        }
    } elseif ($type === 'doctors') {
        fputcsv($out, ['#','Doctor Name','Specialization','Department','Patients Seen','Appointments','Completed','Pending']);
        $rows = getDoctorData($pdo, $dateFrom, $dateTo);
        foreach ($rows as $i => $r) {
            fputcsv($out, [
                $i+1,
                $r['full_name'],
                $r['specialization'],
                $r['dept_name'],
                $r['patients_seen'],
                $r['total_appts'],
                $r['completed'],
                $r['pending'],
            ]);
        }
    } elseif ($type === 'medical_records') {
        fputcsv($out, ['#','Patient','Patient ID','Doctor','Visit Date','Type','Complaint','Diagnosis','Treatment','Follow-up']);
        $rows = getMedicalData($pdo, $dateFrom, $dateTo);
        foreach ($rows as $i => $r) {
            fputcsv($out, [
                $i+1,
                $r['patient_name'],
                $r['pid'] ?? '',
                $r['doc_name'] ?? '—',
                date('d M Y', strtotime($r['visit_date'])),
                $r['visit_type'],
                $r['chief_complaint'] ?? '',
                $r['diagnosis'] ?? '',
                $r['treatment'] ?? '',
                $r['follow_up_date'] ? date('d M Y', strtotime($r['follow_up_date'])) : '',
            ]);
        }
    }

    fclose($out); exit;
}

// ── DATA FUNCTIONS ────────────────────────────────────────
function getAppointmentData(PDO $pdo, string $from, string $to, int $dept=0, string $status=''): array {
    $w = ['a.appt_date BETWEEN ? AND ?']; $p = [$from, $to];
    if ($dept)   { $w[] = 'a.department_id=?'; $p[] = $dept; }
    if ($status) { $w[] = 'a.status=?';        $p[] = $status; }
    $where = 'WHERE ' . implode(' AND ', $w);
    $stmt = $pdo->prepare("SELECT a.*,d.name AS dept_name,doc.full_name AS doc_name,
                            pt.patient_id AS pid
                            FROM appointments a
                            LEFT JOIN departments d  ON a.department_id=d.id
                            LEFT JOIN doctors doc    ON a.doctor_id=doc.id
                            LEFT JOIN patients pt    ON a.patient_id=pt.id
                            $where ORDER BY a.appt_date DESC");
    $stmt->execute($p); return $stmt->fetchAll();
}

function getPatientData(PDO $pdo, string $from, string $to): array {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE DATE(registered_at) BETWEEN ? AND ? ORDER BY registered_at DESC");
    $stmt->execute([$from, $to]); return $stmt->fetchAll();
}

function getRevenueData(PDO $pdo, string $from, string $to, string $status=''): array {
    $w = ['DATE(i.created_at) BETWEEN ? AND ?']; $p = [$from, $to];
    if ($status) { $w[] = 'i.status=?'; $p[] = $status; }
    $where = 'WHERE ' . implode(' AND ', $w);
    $stmt = $pdo->prepare("SELECT i.*,p.full_name AS patient_name,p.patient_id AS pid,
                            COUNT(ii.id) AS item_count
                            FROM invoices i
                            LEFT JOIN patients p ON i.patient_id=p.id
                            LEFT JOIN invoice_items ii ON ii.invoice_id=i.id
                            $where GROUP BY i.id ORDER BY i.created_at DESC");
    $stmt->execute($p); return $stmt->fetchAll();
}

function getRevenueSummary(PDO $pdo, string $from, string $to): array {
    $base = "FROM invoices WHERE DATE(created_at) BETWEEN '$from' AND '$to'";
    return [
        'total_paid'     => (float)$pdo->query("SELECT COALESCE(SUM(total),0) $base AND status='Paid'")->fetchColumn(),
        'total_unpaid'   => (float)$pdo->query("SELECT COALESCE(SUM(total),0) $base AND status='Unpaid'")->fetchColumn(),
        'total_invoices' => (int)$pdo->query("SELECT COUNT(*) $base")->fetchColumn(),
        'paid_invoices'  => (int)$pdo->query("SELECT COUNT(*) $base AND status='Paid'")->fetchColumn(),
    ];
}

function getDeptData(PDO $pdo, string $from, string $to): array {
    $stmt = $pdo->query("
        SELECT d.name AS dept_name,
               COUNT(a.id) AS total,
               SUM(a.status='Completed') AS completed,
               SUM(a.status='Pending')   AS pending,
               SUM(a.status='Cancelled') AS cancelled,
               COALESCE((SELECT SUM(i.total) FROM invoices i
                         JOIN invoice_items ii ON ii.invoice_id=i.id
                         JOIN services s ON ii.service_id=s.id
                         WHERE s.category=d.name AND i.status='Paid'
                         AND DATE(i.created_at) BETWEEN '$from' AND '$to'),0) AS revenue
        FROM departments d
        LEFT JOIN appointments a ON a.department_id=d.id
            AND a.appt_date BETWEEN '$from' AND '$to'
        GROUP BY d.id ORDER BY total DESC
    "); return $stmt->fetchAll();
}

function getDoctorData(PDO $pdo, string $from, string $to): array {
    $stmt = $pdo->query("
        SELECT doc.full_name, doc.specialization, dep.name AS dept_name,
               COUNT(DISTINCT a.patient_id) AS patients_seen,
               COUNT(a.id)                  AS total_appts,
               SUM(a.status='Completed')    AS completed,
               SUM(a.status='Pending')      AS pending
        FROM doctors doc
        LEFT JOIN departments dep ON doc.department_id=dep.id
        LEFT JOIN appointments a  ON a.doctor_id=doc.id
            AND a.appt_date BETWEEN '$from' AND '$to'
        GROUP BY doc.id ORDER BY total_appts DESC
    "); return $stmt->fetchAll();
}

function getMedicalData(PDO $pdo, string $from, string $to): array {
    $stmt = $pdo->prepare("
        SELECT r.*,p.full_name AS patient_name,p.patient_id AS pid,
               doc.full_name AS doc_name
        FROM medical_records r
        LEFT JOIN patients p  ON r.patient_id=p.id
        LEFT JOIN doctors doc ON r.doctor_id=doc.id
        WHERE r.visit_date BETWEEN ? AND ?
        ORDER BY r.visit_date DESC
    "); $stmt->execute([$from, $to]); return $stmt->fetchAll();
}

// ── LOAD DATA FOR PREVIEW ─────────────────────────────────
$data    = [];
$summary = [];

if ($type === 'appointments') {
    $data = getAppointmentData($pdo, $dateFrom, $dateTo, $deptId, $status);
    $summary = [
        'Total'     => count($data),
        'Confirmed' => count(array_filter($data, fn($r) => $r['status']==='Confirmed')),
        'Completed' => count(array_filter($data, fn($r) => $r['status']==='Completed')),
        'Pending'   => count(array_filter($data, fn($r) => $r['status']==='Pending')),
        'Cancelled' => count(array_filter($data, fn($r) => $r['status']==='Cancelled')),
    ];
} elseif ($type === 'patients') {
    $data = getPatientData($pdo, $dateFrom, $dateTo);
    $summary = [
        'Total'    => count($data),
        'Male'     => count(array_filter($data, fn($r) => $r['gender']==='Male')),
        'Female'   => count(array_filter($data, fn($r) => $r['gender']==='Female')),
        'Active'   => count(array_filter($data, fn($r) => $r['status']==='Active')),
    ];
} elseif ($type === 'revenue') {
    $data    = getRevenueData($pdo, $dateFrom, $dateTo, $status);
    $summary = getRevenueSummary($pdo, $dateFrom, $dateTo);
} elseif ($type === 'departments') {
    $data    = getDeptData($pdo, $dateFrom, $dateTo);
} elseif ($type === 'doctors') {
    $data    = getDoctorData($pdo, $dateFrom, $dateTo);
} elseif ($type === 'medical_records') {
    $data    = getMedicalData($pdo, $dateFrom, $dateTo);
}

// Build export URL
$exportUrl = '?' . http_build_query(array_merge($_GET, ['export'=>'csv']));

$pageTitle = 'Reports & Exports';
require_once dirname(__FILE__) . '/../includes/layout_header.php';
?>

<style>
.report-type-card {
    background: var(--card);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 18px;
    cursor: pointer;
    transition: all .2s;
    display: flex; align-items: center; gap: 12px;
    text-decoration: none; color: var(--text);
}
.report-type-card:hover { border-color: var(--blue-bright); transform: translateY(-2px); color: var(--text); }
.report-type-card.active { border-color: var(--blue-mid); background: #e8f2fb; }
.report-type-card .ico { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
.report-type-card strong { display: block; font-size: .875rem; color: var(--blue-deep); }
.report-type-card span { font-size: .75rem; color: var(--muted); }
.summary-pill {
    background: #fff; border: 1px solid var(--border);
    border-radius: 10px; padding: 12px 18px;
    display: inline-flex; flex-direction: column; align-items: center;
    min-width: 100px;
}
.summary-pill strong { font-family: 'Playfair Display', serif; font-size: 1.6rem; color: var(--blue-deep); }
.summary-pill span { font-size: .72rem; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
</style>

<!-- Report Type Selector -->
<div class="row g-2 mb-4">
  <?php
  $types = [
    'appointments'   => ['ico'=>'bi-calendar2-week',   'bg'=>'#e8f2fb', 'color'=>'#1a5fa0', 'label'=>'Appointments',    'sub'=>'By date, dept, status'],
    'patients'       => ['ico'=>'bi-people-fill',       'bg'=>'#edf7f3', 'color'=>'#3aaa8c', 'label'=>'Patients',        'sub'=>'Registered patients'],
    'revenue'        => ['ico'=>'bi-cash-coin',         'bg'=>'#fff8e8', 'color'=>'#d08000', 'label'=>'Revenue',         'sub'=>'Invoices & payments'],
    'departments'    => ['ico'=>'bi-building',          'bg'=>'#f0ebfc', 'color'=>'#7c4ddc', 'label'=>'Departments',     'sub'=>'Performance per dept'],
    'doctors'        => ['ico'=>'bi-person-badge',      'bg'=>'#fbe8ec', 'color'=>'#c0162c', 'label'=>'Doctors',         'sub'=>'Activity per doctor'],
    'medical_records'=> ['ico'=>'bi-journal-medical',   'bg'=>'#e8f4fb', 'color'=>'#2e8dd4', 'label'=>'Medical Records', 'sub'=>'Visit history'],
  ];
  foreach ($types as $key => $t): ?>
  <div class="col-6 col-md-4 col-lg-2">
    <a href="?type=<?= $key ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
       class="report-type-card <?= $type===$key?'active':'' ?>">
      <div class="ico" style="background:<?= $t['bg'] ?>;color:<?= $t['color'] ?>">
        <i class="bi <?= $t['ico'] ?>"></i>
      </div>
      <div>
        <strong><?= $t['label'] ?></strong>
        <span><?= $t['sub'] ?></span>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="data-card mb-4">
  <div class="data-card-head">
    <i class="bi bi-funnel-fill text-primary me-2"></i>
    <h5>Filter Options</h5>
  </div>
  <div class="p-4">
    <form method="GET" class="row g-3 align-items-end">
      <input type="hidden" name="type" value="<?= clean($type) ?>"/>
      <div class="col-md-3">
        <label class="form-label">From Date</label>
        <input type="date" name="date_from" class="form-control" value="<?= clean($dateFrom) ?>"/>
      </div>
      <div class="col-md-3">
        <label class="form-label">To Date</label>
        <input type="date" name="date_to" class="form-control" value="<?= clean($dateTo) ?>"/>
      </div>

      <?php if ($type === 'appointments'): ?>
      <div class="col-md-2">
        <label class="form-label">Department</label>
        <select name="dept_id" class="form-select">
          <option value="">All</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $deptId===$d['id']?'selected':'' ?>><?= clean($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">All</option>
          <?php foreach (['Pending','Confirmed','Completed','Cancelled','No-Show'] as $s): ?>
            <option <?= $status===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php elseif ($type === 'revenue'): ?>
      <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">All</option>
          <?php foreach (['Unpaid','Paid','Partial','Cancelled'] as $s): ?>
            <option <?= $status===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-search me-1"></i>Generate
        </button>
      </div>
      <div class="col-md-2">
        <a href="<?= $exportUrl ?>" class="btn btn-success w-100">
          <i class="bi bi-file-earmark-excel me-1"></i>Export CSV
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Summary Pills -->
<?php if ($summary): ?>
<div class="d-flex flex-wrap gap-3 mb-4">
  <?php foreach ($summary as $label => $val): ?>
  <div class="summary-pill">
    <strong><?= is_float($val) ? number_format($val, 2) : number_format($val) ?></strong>
    <span><?= clean($label) ?></span>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Report Period Banner -->
<div style="background:linear-gradient(135deg,var(--blue-deep),var(--blue-mid));border-radius:12px;padding:16px 24px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div>
    <div style="color:rgba(255,255,255,.6);font-size:.72rem;text-transform:uppercase;letter-spacing:.08em">Report Period</div>
    <div style="color:#fff;font-size:1rem;font-weight:600;margin-top:2px">
      <?= date('d M Y', strtotime($dateFrom)) ?> — <?= date('d M Y', strtotime($dateTo)) ?>
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:16px">
    <div style="text-align:right">
      <div style="color:rgba(255,255,255,.6);font-size:.72rem;text-transform:uppercase">Records Found</div>
      <div style="color:#fff;font-size:1.4rem;font-weight:700;font-family:'Playfair Display',serif"><?= count($data) ?></div>
    </div>
    <a href="<?= $exportUrl ?>"
       style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;padding:10px 20px;border-radius:9px;text-decoration:none;font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:8px;transition:all .2s"
       onmouseover="this.style.background='rgba(255,255,255,.25)'"
       onmouseout="this.style.background='rgba(255,255,255,.15)'">
      <i class="bi bi-download"></i> Download CSV
    </a>
  </div>
</div>

<!-- DATA TABLES ──────────────────────────────────────────── -->

<?php if ($type === 'appointments'): ?>
<div class="data-card">
  <div class="data-card-head">
    <i class="bi bi-calendar2-week text-primary me-2"></i>
    <h5>Appointments Report</h5>
    <span style="font-size:.78rem;color:var(--muted)"><?= count($data) ?> records</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover" style="font-size:.82rem">
      <thead><tr><th>#</th><th>Reference</th><th>Patient</th><th>Department</th><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($data as $i => $r): ?>
        <tr>
          <td style="color:var(--muted)"><?= $i+1 ?></td>
          <td><code style="font-size:.72rem"><?= clean($r['ref_number']) ?></code></td>
          <td>
            <div style="font-weight:500"><?= clean($r['patient_name']) ?></div>
            <div style="font-size:.72rem;color:var(--muted)"><?= clean($r['pid'] ?? '') ?></div>
          </td>
          <td><?= clean($r['dept_name']) ?></td>
          <td style="color:var(--muted)"><?= clean($r['doc_name'] ?? '—') ?></td>
          <td><?= date('d M Y', strtotime($r['appt_date'])) ?></td>
          <td style="color:var(--muted);font-size:.75rem"><?= clean($r['appt_time']) ?></td>
          <td><?= statusBadge($r['status']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$data): ?><tr><td colspan="8" class="text-center text-muted py-4">No appointments found for this period.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($type === 'patients'): ?>
<div class="data-card">
  <div class="data-card-head">
    <i class="bi bi-people-fill text-success me-2"></i>
    <h5>Patient Report</h5>
    <span style="font-size:.78rem;color:var(--muted)"><?= count($data) ?> records</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover" style="font-size:.82rem">
      <thead><tr><th>#</th><th>Patient ID</th><th>Full Name</th><th>Gender</th><th>Age</th><th>Phone</th><th>Blood</th><th>City</th><th>Status</th><th>Registered</th></tr></thead>
      <tbody>
        <?php foreach ($data as $i => $r): ?>
        <tr>
          <td style="color:var(--muted)"><?= $i+1 ?></td>
          <td><code style="font-size:.72rem"><?= clean($r['patient_id']) ?></code></td>
          <td style="font-weight:500"><?= clean($r['full_name']) ?></td>
          <td style="color:var(--muted)"><?= clean($r['gender']) ?></td>
          <td><?= calcAge($r['date_of_birth']) ?></td>
          <td><?= clean($r['phone']) ?></td>
          <td style="font-weight:700;color:var(--red)"><?= clean($r['blood_group']) ?></td>
          <td style="color:var(--muted)"><?= clean($r['city'] ?? '—') ?></td>
          <td><?= statusBadge($r['status']) ?></td>
          <td><?= date('d M Y', strtotime($r['registered_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$data): ?><tr><td colspan="10" class="text-center text-muted py-4">No patients registered in this period.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($type === 'revenue'): ?>
<!-- Revenue Summary Cards -->
<?php if ($summary): ?>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-ico" style="background:#edf7f3;color:#3aaa8c"><i class="bi bi-cash-coin"></i></div>
      <div><div class="stat-val" style="font-size:1.3rem"><?= number_format($summary['total_paid']) ?></div><div class="stat-lbl">Paid (ETB)</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-ico" style="background:#fff0f0;color:var(--red)"><i class="bi bi-exclamation-circle"></i></div>
      <div><div class="stat-val" style="font-size:1.3rem"><?= number_format($summary['total_unpaid']) ?></div><div class="stat-lbl">Unpaid (ETB)</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-ico" style="background:#e8f2fb;color:var(--blue-mid)"><i class="bi bi-receipt"></i></div>
      <div><div class="stat-val"><?= $summary['total_invoices'] ?></div><div class="stat-lbl">Total Invoices</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-ico" style="background:#fff8e8;color:#d08000"><i class="bi bi-check-circle"></i></div>
      <div><div class="stat-val"><?= $summary['paid_invoices'] ?></div><div class="stat-lbl">Paid Invoices</div></div>
    </div>
  </div>
</div>
<?php endif; ?>
<div class="data-card">
  <div class="data-card-head">
    <i class="bi bi-cash-coin text-warning me-2"></i>
    <h5>Revenue Report</h5>
  </div>
  <div class="table-responsive">
    <table class="table table-hover" style="font-size:.82rem">
      <thead><tr><th>#</th><th>Invoice #</th><th>Patient</th><th>Date</th><th>Total (ETB)</th><th>Paid (ETB)</th><th>Balance</th><th>Status</th><th>Method</th></tr></thead>
      <tbody>
        <?php foreach ($data as $i => $r): ?>
        <tr>
          <td style="color:var(--muted)"><?= $i+1 ?></td>
          <td><a href="/panacea/admin/invoices.php?action=view&id=<?= $r['id'] ?>" style="color:var(--blue-mid);text-decoration:none;font-weight:600"><?= clean($r['invoice_number']) ?></a></td>
          <td>
            <div style="font-weight:500"><?= clean($r['patient_name']) ?></div>
            <div style="font-size:.72rem;color:var(--muted)"><?= clean($r['pid'] ?? '') ?></div>
          </td>
          <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
          <td style="font-weight:700;color:var(--blue-deep)"><?= number_format($r['total'], 2) ?></td>
          <td style="color:#3aaa8c;font-weight:600"><?= number_format($r['amount_paid'], 2) ?></td>
          <td style="color:<?= $r['balance']>0?'var(--red)':'#3aaa8c' ?>;font-weight:600"><?= number_format($r['balance'], 2) ?></td>
          <td><?= statusBadge($r['status']) ?></td>
          <td style="color:var(--muted)"><?= clean($r['payment_method']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$data): ?><tr><td colspan="9" class="text-center text-muted py-4">No invoices for this period.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($type === 'departments'): ?>
<div class="data-card">
  <div class="data-card-head">
    <i class="bi bi-building" style="color:#7c4ddc" ></i>&nbsp;
    <h5>Department Performance Report</h5>
  </div>
  <div class="table-responsive">
    <table class="table table-hover" style="font-size:.875rem">
      <thead><tr><th>#</th><th>Department</th><th>Total Appts</th><th>Completed</th><th>Pending</th><th>Cancelled</th><th>Revenue (ETB)</th></tr></thead>
      <tbody>
        <?php foreach ($data as $i => $r): ?>
        <tr>
          <td style="color:var(--muted)"><?= $i+1 ?></td>
          <td style="font-weight:600"><?= clean($r['dept_name']) ?></td>
          <td><strong style="color:var(--blue-deep)"><?= $r['total'] ?></strong></td>
          <td><span class="badge bg-success"><?= $r['completed'] ?></span></td>
          <td><span class="badge bg-warning"><?= $r['pending'] ?></span></td>
          <td><span class="badge bg-danger"><?= $r['cancelled'] ?></span></td>
          <td style="font-weight:700;color:var(--blue-deep)"><?= number_format($r['revenue'] ?? 0, 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($type === 'doctors'): ?>
<div class="data-card">
  <div class="data-card-head">
    <i class="bi bi-person-badge text-danger me-2"></i>
    <h5>Doctor Activity Report</h5>
  </div>
  <div class="table-responsive">
    <table class="table table-hover" style="font-size:.875rem">
      <thead><tr><th>#</th><th>Doctor</th><th>Specialization</th><th>Department</th><th>Patients Seen</th><th>Total Appts</th><th>Completed</th><th>Pending</th></tr></thead>
      <tbody>
        <?php foreach ($data as $i => $r): ?>
        <tr>
          <td style="color:var(--muted)"><?= $i+1 ?></td>
          <td style="font-weight:600"><?= clean($r['full_name']) ?></td>
          <td style="color:var(--muted)"><?= clean($r['specialization']) ?></td>
          <td><?= clean($r['dept_name']) ?></td>
          <td><strong style="color:var(--blue-deep)"><?= $r['patients_seen'] ?></strong></td>
          <td><?= $r['total_appts'] ?></td>
          <td><span class="badge bg-success"><?= $r['completed'] ?></span></td>
          <td><span class="badge bg-warning"><?= $r['pending'] ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($type === 'medical_records'): ?>
<div class="data-card">
  <div class="data-card-head">
    <i class="bi bi-journal-medical text-info me-2"></i>
    <h5>Medical Records Report</h5>
    <span style="font-size:.78rem;color:var(--muted)"><?= count($data) ?> records</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover" style="font-size:.82rem">
      <thead><tr><th>#</th><th>Patient</th><th>Doctor</th><th>Visit Date</th><th>Type</th><th>Complaint</th><th>Diagnosis</th><th>Follow-up</th></tr></thead>
      <tbody>
        <?php foreach ($data as $i => $r): ?>
        <tr>
          <td style="color:var(--muted)"><?= $i+1 ?></td>
          <td>
            <div style="font-weight:500"><?= clean($r['patient_name']) ?></div>
            <div style="font-size:.72rem;color:var(--muted)"><?= clean($r['pid'] ?? '') ?></div>
          </td>
          <td style="color:var(--muted)"><?= clean($r['doc_name'] ?? '—') ?></td>
          <td><?= date('d M Y', strtotime($r['visit_date'])) ?></td>
          <td><span class="badge bg-light text-dark" style="font-size:.7rem"><?= clean($r['visit_type']) ?></span></td>
          <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= clean($r['chief_complaint'] ?? '—') ?></td>
          <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= clean($r['diagnosis'] ?? '—') ?></td>
          <td style="color:var(--muted)"><?= $r['follow_up_date'] ? date('d M Y', strtotime($r['follow_up_date'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$data): ?><tr><td colspan="8" class="text-center text-muted py-4">No records for this period.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Print Styles -->
<style>
@media print {
  .sidebar, .topbar, .main-wrap > .topbar,
  .report-type-card, form, .btn { display: none !important; }
  .main-wrap { margin-left: 0 !important; }
  .content { padding: 0 !important; }
}
</style>

<?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; ?>
