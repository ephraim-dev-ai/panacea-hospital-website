<?php
// ============================================================
//  PANACEA HOSPITAL – Print Appointment Slip
//  admin/print_appointment.php
// ============================================================
require_once dirname(__FILE__) . '/../includes/helpers.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = db();

if (!$id) { header('Location: /panacea/admin/appointments.php'); exit; }

$appt = $pdo->prepare("
    SELECT a.*,
           d.name AS dept_name,
           doc.full_name AS doc_name, doc.specialization,
           p.patient_id AS pid, p.phone AS patient_phone_db,
           p.date_of_birth, p.gender, p.blood_group
    FROM appointments a
    LEFT JOIN departments d   ON a.department_id = d.id
    LEFT JOIN doctors doc     ON a.doctor_id     = doc.id
    LEFT JOIN patients p      ON a.patient_id    = p.id
    WHERE a.id = ?
");
$appt->execute([$id]); $appt = $appt->fetch();

if (!$appt) { header('Location: /panacea/admin/appointments.php'); exit; }

$statusColor = [
    'Pending'   => '#d08000',
    'Confirmed' => '#1a5fa0',
    'Completed' => '#3aaa8c',
    'Cancelled' => '#c0162c',
    'No-Show'   => '#7a8da8',
];
$sc = $statusColor[$appt['status']] ?? '#7a8da8';

$dayName  = date('l', strtotime($appt['appt_date']));
$dateStr  = date('d F Y', strtotime($appt['appt_date']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Appointment Slip – <?= htmlspecialchars($appt['patient_name']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: #f0f4f9;
      color: #1a2b42;
      padding: 20px;
    }

    .print-controls {
      max-width: 680px; margin: 0 auto 20px;
      display: flex; gap: 12px; flex-wrap: wrap;
    }
    .btn-print {
      background: linear-gradient(135deg, #1a5fa0, #2e8dd4);
      color: #fff; border: none; padding: 12px 28px;
      border-radius: 10px; font-weight: 600; font-size: .95rem;
      cursor: pointer; display: flex; align-items: center; gap: 8px;
      transition: all .2s;
    }
    .btn-print:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,95,160,.4); }
    .btn-back {
      background: #fff; color: #3d4f6b; border: 1.5px solid #e2e8f3;
      padding: 12px 24px; border-radius: 10px; font-weight: 600;
      font-size: .95rem; text-decoration: none; display: flex; align-items: center; gap: 8px;
    }

    /* SLIP */
    .slip-wrap { max-width: 680px; margin: 0 auto; }

    .slip-card {
      background: #fff; border-radius: 16px;
      box-shadow: 0 4px 32px rgba(10,46,92,.12);
      overflow: hidden; margin-bottom: 20px;
    }

    /* HEADER BAND */
    .slip-header {
      background: linear-gradient(135deg, #0a2e5c, #1a5fa0);
      padding: 24px 28px;
      display: flex; justify-content: space-between; align-items: center;
    }
    .slip-header .brand { display: flex; align-items: center; gap: 12px; }
    .slip-header .logo {
      width: 46px; height: 46px; background: rgba(255,255,255,.15);
      border-radius: 10px; display: flex; align-items: center;
      justify-content: center; font-size: 1.4rem; color: #fff;
    }
    .slip-header .hospital-name {
      font-family: 'Playfair Display', serif; font-size: 1.2rem; color: #fff; font-weight: 700;
    }
    .slip-header .hospital-sub { color: rgba(255,255,255,.6); font-size: .72rem; }
    .slip-header .ref-box {
      text-align: right;
    }
    .slip-header .ref-label { color: rgba(255,255,255,.6); font-size: .68rem; text-transform: uppercase; letter-spacing: .08em; }
    .slip-header .ref-number { color: #fff; font-size: 1.1rem; font-weight: 700; font-family: monospace; }

    /* STATUS BAND */
    .status-band {
      padding: 10px 28px;
      display: flex; justify-content: space-between; align-items: center;
    }
    .status-label { font-size: .72rem; color: #7a8da8; text-transform: uppercase; letter-spacing: .06em; }
    .status-value {
      font-weight: 700; font-size: .9rem;
      padding: 4px 16px; border-radius: 20px; color: #fff;
    }

    /* DATE HIGHLIGHT */
    .date-highlight {
      background: #f0f4f9;
      padding: 20px 28px;
      display: flex; align-items: center; gap: 20px;
      border-top: 1px solid #e2e8f3; border-bottom: 1px solid #e2e8f3;
    }
    .date-box {
      background: #0a2e5c; color: #fff; border-radius: 12px;
      padding: 12px 16px; text-align: center; min-width: 70px;
    }
    .date-box .day-num { font-size: 2rem; font-weight: 700; font-family: 'Playfair Display', serif; line-height: 1; }
    .date-box .month { font-size: .7rem; text-transform: uppercase; letter-spacing: .08em; opacity: .7; margin-top: 2px; }
    .date-info .day-name { font-size: 1.1rem; font-weight: 700; color: #0a2e5c; }
    .date-info .full-date { color: #7a8da8; font-size: .85rem; }
    .time-box {
      margin-left: auto; text-align: right;
    }
    .time-box .time-label { font-size: .68rem; color: #7a8da8; text-transform: uppercase; letter-spacing: .06em; }
    .time-box .time-value {
      font-size: 1rem; font-weight: 700; color: #0a2e5c;
      background: #fff; padding: 6px 14px; border-radius: 8px;
      border: 1.5px solid #e2e8f3; display: inline-block; margin-top: 4px;
    }

    /* DETAILS */
    .slip-details { padding: 24px 28px; }
    .detail-grid {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 16px; margin-bottom: 20px;
    }
    .detail-item label {
      font-size: .68rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .08em; color: #7a8da8; display: block; margin-bottom: 4px;
    }
    .detail-item span {
      font-size: .92rem; font-weight: 600; color: #1a2b42;
    }
    .detail-item.full { grid-column: 1 / -1; }

    /* INSTRUCTIONS */
    .instructions {
      background: #fff8e8; border-radius: 10px;
      padding: 14px 16px; margin-bottom: 20px;
      border-left: 3px solid #d08000;
    }
    .instructions h6 {
      font-size: .75rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .08em; color: #d08000; margin-bottom: 8px;
    }
    .instructions ul {
      list-style: none; padding: 0; margin: 0;
    }
    .instructions ul li {
      font-size: .85rem; color: #3d4f6b; padding: 3px 0;
      display: flex; align-items: center; gap: 8px;
    }
    .instructions ul li::before { content: '•'; color: #d08000; font-weight: 700; }

    /* EMERGENCY */
    .emergency-strip {
      background: #fff0f0; border-radius: 10px;
      padding: 12px 16px; border-left: 3px solid #c0162c;
      display: flex; align-items: center; gap: 12px;
    }
    .emergency-strip .ico { font-size: 1.2rem; }
    .emergency-strip span { font-size: .82rem; color: #3d4f6b; }
    .emergency-strip strong { color: #c0162c; }

    /* QR placeholder */
    .qr-section {
      display: flex; align-items: center; gap: 16px;
      padding: 16px 28px; border-top: 1px dashed #e2e8f3;
    }
    .qr-box {
      width: 70px; height: 70px; border: 2px solid #e2e8f3;
      border-radius: 8px; display: flex; align-items: center;
      justify-content: center; font-size: .6rem; color: #7a8da8;
      text-align: center; flex-shrink: 0; background: #f7f9fc;
    }
    .qr-info { font-size: .8rem; color: #7a8da8; line-height: 1.5; }
    .qr-info strong { color: #1a2b42; font-size: .85rem; display: block; margin-bottom: 2px; }

    /* SLIP FOOTER */
    .slip-footer {
      background: #f7f9fc; padding: 14px 28px;
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 8px; border-top: 1px solid #e2e8f3;
    }
    .slip-footer span { font-size: .72rem; color: #7a8da8; }

    /* TEAR LINE */
    .tear-line {
      text-align: center; margin: 16px 0;
      position: relative; color: #7a8da8; font-size: .72rem;
      text-transform: uppercase; letter-spacing: .1em;
    }
    .tear-line::before, .tear-line::after {
      content: ''; position: absolute; top: 50%;
      width: 42%; border-top: 1px dashed #c0c8d8;
    }
    .tear-line::before { left: 0; }
    .tear-line::after  { right: 0; }

    /* PATIENT COPY (smaller) */
    .patient-copy {
      background: #fff; border-radius: 12px;
      box-shadow: 0 2px 16px rgba(10,46,92,.08);
      padding: 20px 24px; overflow: hidden;
    }
    .patient-copy .copy-header {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f3;
    }
    .patient-copy .copy-title {
      font-size: .72rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .08em; color: #7a8da8;
    }
    .patient-copy .copy-ref { font-size: .85rem; font-weight: 700; color: #0a2e5c; font-family: monospace; }
    .copy-row { display: flex; gap: 8px; margin-bottom: 8px; font-size: .85rem; }
    .copy-row .lbl { color: #7a8da8; min-width: 100px; }
    .copy-row .val { font-weight: 600; color: #1a2b42; }

    /* ── PRINT ── */
    @media print {
      body { background: #fff; padding: 0; }
      .print-controls { display: none !important; }
      .slip-card, .patient-copy { box-shadow: none; }
      @page { margin: 0.5cm; size: A4; }
    }

    @media (max-width: 560px) {
      .detail-grid { grid-template-columns: 1fr; }
      .slip-header { flex-direction: column; gap: 12px; }
      .slip-footer { flex-direction: column; }
    }
  </style>
</head>
<body>

<!-- PRINT CONTROLS -->
<div class="print-controls">
  <button class="btn-print" onclick="window.print()">🖨️ Print Slip</button>
  <a href="/panacea/admin/appointments.php" class="btn-back">← Appointments</a>
  <?php if ($appt['patient_id']): ?>
  <a href="/panacea/admin/patients.php?action=view&id=<?= $appt['patient_id'] ?>" class="btn-back">← Patient Profile</a>
  <?php endif; ?>
</div>

<div class="slip-wrap">

  <!-- ══ HOSPITAL COPY ══ -->
  <div class="slip-card">

    <!-- Header -->
    <div class="slip-header">
      <div class="brand">
        <div class="logo">🏥</div>
        <div>
          <div class="hospital-name">Panacea Hospital</div>
          <div class="hospital-sub">Hawassa, Sidama Region, Ethiopia · +251 917 000 000</div>
        </div>
      </div>
      <div class="ref-box">
        <div class="ref-label">Reference No.</div>
        <div class="ref-number"><?= htmlspecialchars($appt['ref_number']) ?></div>
      </div>
    </div>

    <!-- Status Band -->
    <div class="status-band" style="background:<?= $sc ?>18;border-bottom:1px solid <?= $sc ?>30">
      <span class="status-label">Appointment Status</span>
      <span class="status-value" style="background:<?= $sc ?>"><?= htmlspecialchars($appt['status']) ?></span>
    </div>

    <!-- Date Highlight -->
    <div class="date-highlight">
      <div class="date-box">
        <div class="day-num"><?= date('d', strtotime($appt['appt_date'])) ?></div>
        <div class="month"><?= date('M Y', strtotime($appt['appt_date'])) ?></div>
      </div>
      <div class="date-info">
        <div class="day-name"><?= $dayName ?></div>
        <div class="full-date"><?= $dateStr ?></div>
      </div>
      <div class="time-box">
        <div class="time-label">Time Slot</div>
        <div class="time-value">
          <?php
          $timeMap = [
            'Morning (7AM-12PM)'   => '7:00 AM – 12:00 PM',
            'Afternoon (12PM-5PM)' => '12:00 PM – 5:00 PM',
            'Evening (5PM-9PM)'    => '5:00 PM – 9:00 PM',
          ];
          echo htmlspecialchars($timeMap[$appt['appt_time']] ?? $appt['appt_time']);
          ?>
        </div>
      </div>
    </div>

    <!-- Details -->
    <div class="slip-details">
      <div class="detail-grid">
        <div class="detail-item">
          <label>Patient Name</label>
          <span><?= htmlspecialchars($appt['patient_name']) ?></span>
        </div>
        <div class="detail-item">
          <label>Patient ID</label>
          <span style="font-family:monospace"><?= htmlspecialchars($appt['pid'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
          <label>Phone Number</label>
          <span><?= htmlspecialchars($appt['patient_phone'] ?? $appt['patient_phone_db'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
          <label>Department</label>
          <span><?= htmlspecialchars($appt['dept_name']) ?></span>
        </div>
        <div class="detail-item">
          <label>Attending Doctor</label>
          <span><?= $appt['doc_name'] ? 'Dr. ' . htmlspecialchars($appt['doc_name']) : '— To be assigned —' ?></span>
        </div>
        <div class="detail-item">
          <label>Visit Type</label>
          <span>Outpatient</span>
        </div>
        <?php if ($appt['reason']): ?>
        <div class="detail-item full">
          <label>Reason / Chief Complaint</label>
          <span><?= htmlspecialchars($appt['reason']) ?></span>
        </div>
        <?php endif; ?>
      </div>

      <!-- Instructions -->
      <div class="instructions">
        <h6>📋 Patient Instructions</h6>
        <ul>
          <li>Please arrive <strong>15 minutes before</strong> your appointment time</li>
          <li>Bring this slip and a valid ID to the reception desk</li>
          <li>Bring any previous medical records or test results</li>
          <li>To reschedule, call <strong>+251 917 000 000</strong> at least 24 hours in advance</li>
          <?php if ($appt['status'] === 'Pending'): ?>
          <li style="color:#d08000"><strong>⏳ Confirmation pending</strong> — We will call you to confirm</li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Emergency -->
      <div class="emergency-strip">
        <span class="ico">🚨</span>
        <span>
          <strong>Emergency?</strong> Call <strong>+251 917 111 111</strong> available
          <strong>24 hours / 7 days</strong>. Do not wait for your appointment.
        </span>
      </div>
    </div>

    <!-- QR / Barcode area -->
    <div class="qr-section">
      <div class="qr-box">REF<br>CODE<br><?= substr($appt['ref_number'], -4) ?></div>
      <div class="qr-info">
        <strong><?= htmlspecialchars($appt['ref_number']) ?></strong>
        Show this slip at the hospital reception desk.<br>
        Issued: <?= date('d M Y, H:i') ?> · Valid for scheduled date only.
      </div>
    </div>

    <!-- Footer -->
    <div class="slip-footer">
      <span>🏥 <strong>Panacea Hospital</strong> · Hawassa, Ethiopia</span>
      <span>📞 <strong>+251 917 000 000</strong></span>
      <span>✉️ info@panaceahospital.et</span>
    </div>

  </div><!-- /slip-card -->

  <!-- TEAR LINE -->
  <div class="tear-line">✂ Patient Copy</div>

  <!-- ══ PATIENT COPY (compact) ══ -->
  <div class="patient-copy">
    <div class="copy-header">
      <div>
        <div class="copy-title">🏥 Panacea Hospital — Patient Copy</div>
        <div style="font-size:.75rem;color:#7a8da8;margin-top:2px">Hawassa, Sidama Region, Ethiopia</div>
      </div>
      <div class="copy-ref"><?= htmlspecialchars($appt['ref_number']) ?></div>
    </div>
    <div class="copy-row"><span class="lbl">Patient:</span><span class="val"><?= htmlspecialchars($appt['patient_name']) ?></span></div>
    <div class="copy-row"><span class="lbl">Department:</span><span class="val"><?= htmlspecialchars($appt['dept_name']) ?></span></div>
    <div class="copy-row"><span class="lbl">Doctor:</span><span class="val"><?= $appt['doc_name'] ? 'Dr. '.htmlspecialchars($appt['doc_name']) : 'To be assigned' ?></span></div>
    <div class="copy-row"><span class="lbl">Date:</span><span class="val" style="color:#0a2e5c"><?= $dayName ?>, <?= $dateStr ?></span></div>
    <div class="copy-row"><span class="lbl">Time:</span><span class="val"><?= htmlspecialchars($appt['appt_time']) ?></span></div>
    <div class="copy-row"><span class="lbl">Status:</span><span class="val" style="color:<?= $sc ?>"><?= htmlspecialchars($appt['status']) ?></span></div>
    <div style="margin-top:12px;font-size:.75rem;color:#7a8da8;text-align:center">
      Arrive 15 min early · Emergency: +251 917 111 111 (24/7)
    </div>
  </div>

</div><!-- /slip-wrap -->

</body>
</html>
