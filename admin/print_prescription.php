<?php
// ============================================================
//  PANACEA HOSPITAL – Print Prescription
//  admin/print_prescription.php
// ============================================================
require_once dirname(__FILE__) . '/../includes/helpers.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = db();

if (!$id) { header('Location: /panacea/admin/records.php'); exit; }

$record = $pdo->prepare("
    SELECT r.*,
           p.full_name AS patient_name, p.patient_id AS pid,
           p.gender, p.date_of_birth, p.phone, p.blood_group,
           p.allergies,
           doc.full_name AS doc_name, doc.specialization,
           dep.name AS dept_name
    FROM medical_records r
    LEFT JOIN patients p   ON r.patient_id  = p.id
    LEFT JOIN doctors doc  ON r.doctor_id   = doc.id
    LEFT JOIN departments dep ON doc.department_id = dep.id
    WHERE r.id = ?
");
$record->execute([$id]); $record = $record->fetch();

if (!$record) { header('Location: /panacea/admin/records.php'); exit; }

$age = $record['date_of_birth']
    ? (int)(new DateTime($record['date_of_birth']))->diff(new DateTime())->y
    : '—';

// Parse prescription into lines
$prescriptionLines = [];
if ($record['prescription']) {
    $prescriptionLines = array_filter(
        array_map('trim', explode("\n", $record['prescription']))
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Prescription – <?= htmlspecialchars($record['patient_name']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: #f0f4f9;
      color: #1a2b42;
      padding: 20px;
    }

    /* Print controls - hidden when printing */
    .print-controls {
      max-width: 800px; margin: 0 auto 20px;
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

    /* PRESCRIPTION PAPER */
    .rx-paper {
      max-width: 800px; margin: 0 auto;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 32px rgba(10,46,92,.12);
      overflow: hidden;
    }

    /* HEADER */
    .rx-header {
      background: linear-gradient(135deg, #0a2e5c, #1a5fa0);
      padding: 28px 36px;
      display: flex; justify-content: space-between; align-items: flex-start;
    }
    .hospital-brand { display: flex; align-items: center; gap: 14px; }
    .hospital-logo {
      width: 52px; height: 52px; background: rgba(255,255,255,.15);
      border-radius: 12px; display: flex; align-items: center;
      justify-content: center; font-size: 1.6rem; color: #fff; flex-shrink: 0;
    }
    .hospital-info strong {
      display: block; font-family: 'Playfair Display', serif;
      font-size: 1.3rem; color: #fff; margin-bottom: 2px;
    }
    .hospital-info span { color: rgba(255,255,255,.65); font-size: .78rem; line-height: 1.5; }
    .rx-title {
      text-align: right;
    }
    .rx-title .rx-symbol {
      font-family: 'Playfair Display', serif;
      font-size: 3rem; color: rgba(255,255,255,.2);
      line-height: 1;
    }
    .rx-title .rx-label {
      color: rgba(255,255,255,.8); font-size: .75rem;
      text-transform: uppercase; letter-spacing: .1em;
    }
    .rx-title .rx-date {
      color: rgba(255,255,255,.6); font-size: .8rem; margin-top: 4px;
    }

    /* BODY */
    .rx-body { padding: 32px 36px; }

    /* PATIENT INFO */
    .patient-strip {
      background: #f0f4f9; border-radius: 10px;
      padding: 16px 20px; margin-bottom: 28px;
      display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;
    }
    .patient-field label {
      font-size: .68rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .08em; color: #7a8da8; display: block; margin-bottom: 3px;
    }
    .patient-field span {
      font-size: .88rem; font-weight: 600; color: #1a2b42;
    }

    /* DOCTOR INFO */
    .doctor-strip {
      border-left: 3px solid #2e8dd4;
      padding: 8px 16px; margin-bottom: 24px;
      background: #e8f2fb; border-radius: 0 8px 8px 0;
    }
    .doctor-strip .doc-name {
      font-weight: 700; color: #0a2e5c; font-size: .95rem;
    }
    .doctor-strip .doc-spec {
      color: #1a5fa0; font-size: .8rem;
    }

    /* DIAGNOSIS */
    .section-title {
      font-size: .72rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .1em; color: #7a8da8; margin-bottom: 8px;
      padding-bottom: 6px; border-bottom: 1px solid #e2e8f3;
    }
    .diagnosis-box {
      background: #f7f9fc; border-radius: 8px;
      padding: 14px 16px; margin-bottom: 24px;
      font-size: .9rem; line-height: 1.65; color: #1a2b42;
    }

    /* PRESCRIPTION */
    .rx-symbol-large {
      font-family: 'Playfair Display', serif;
      font-size: 2.5rem; color: #1a5fa0; font-weight: 700;
      margin-bottom: 16px; opacity: .7;
    }
    .rx-items { list-style: none; padding: 0; margin-bottom: 24px; }
    .rx-item {
      display: flex; align-items: flex-start; gap: 12px;
      padding: 14px 16px; margin-bottom: 8px;
      background: #fff; border: 1.5px solid #e2e8f3;
      border-radius: 10px; border-left: 3px solid #1a5fa0;
    }
    .rx-item-num {
      width: 26px; height: 26px; background: #1a5fa0;
      border-radius: 50%; display: flex; align-items: center;
      justify-content: center; color: #fff; font-size: .75rem;
      font-weight: 700; flex-shrink: 0; margin-top: 1px;
    }
    .rx-item-text { font-size: .88rem; line-height: 1.6; color: #1a2b42; }

    /* FREE TEXT prescription */
    .rx-freetext {
      background: #f7f9fc; border-radius: 8px;
      padding: 16px; font-size: .9rem; line-height: 1.8;
      white-space: pre-wrap; color: #1a2b42;
      border-left: 3px solid #1a5fa0;
    }

    /* DIVIDER */
    .rx-divider {
      border: none; border-top: 1px dashed #e2e8f3; margin: 24px 0;
    }

    /* NOTES / INSTRUCTIONS */
    .instructions-box {
      background: #fff8e8; border-radius: 8px;
      padding: 14px 16px; margin-bottom: 24px;
      font-size: .87rem; line-height: 1.65;
      border-left: 3px solid #d08000;
    }
    .instructions-box strong { color: #d08000; }

    /* ALLERGY WARNING */
    .allergy-box {
      background: #fff0f0; border-radius: 8px;
      padding: 12px 16px; margin-bottom: 24px;
      font-size: .82rem; border-left: 3px solid #c0162c;
      display: flex; align-items: center; gap: 10px;
    }
    .allergy-box i { color: #c0162c; font-size: 1.1rem; }

    /* FOLLOW UP */
    .followup-box {
      background: #edf7f3; border-radius: 8px;
      padding: 12px 16px; margin-bottom: 24px;
      font-size: .87rem; border-left: 3px solid #3aaa8c;
      display: flex; align-items: center; gap: 10px;
    }
    .followup-box strong { color: #1a6b54; }

    /* SIGNATURE */
    .signature-section {
      display: flex; justify-content: space-between;
      align-items: flex-end; margin-top: 40px; padding-top: 20px;
      border-top: 1px solid #e2e8f3;
    }
    .sig-left { font-size: .8rem; color: #7a8da8; }
    .sig-right { text-align: center; }
    .sig-line {
      width: 200px; border-bottom: 1.5px solid #1a2b42;
      margin-bottom: 6px;
    }
    .sig-label { font-size: .75rem; color: #7a8da8; }
    .sig-name { font-weight: 700; color: #0a2e5c; font-size: .88rem; }

    /* FOOTER */
    .rx-footer {
      background: #f7f9fc; padding: 16px 36px;
      border-top: 1px solid #e2e8f3;
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 8px;
    }
    .rx-footer span { font-size: .75rem; color: #7a8da8; }
    .rx-footer strong { color: #1a2b42; }

    /* WATERMARK for COPY */
    .copy-watermark {
      position: fixed; top: 50%; left: 50%;
      transform: translate(-50%, -50%) rotate(-30deg);
      font-size: 8rem; color: rgba(0,0,0,.04);
      font-weight: 900; pointer-events: none;
      z-index: 0; display: none;
    }

    /* ── PRINT STYLES ── */
    @media print {
      body { background: #fff; padding: 0; }
      .print-controls { display: none !important; }
      .rx-paper { box-shadow: none; border-radius: 0; max-width: 100%; }
      .copy-watermark { display: block; }
      @page { margin: 0.5cm; size: A4; }
    }

    @media (max-width: 600px) {
      .patient-strip { grid-template-columns: repeat(2, 1fr); }
      .rx-header { flex-direction: column; gap: 16px; }
      .rx-title { text-align: left; }
      .rx-body { padding: 20px; }
    }
  </style>
</head>
<body>

<!-- PRINT CONTROLS -->
<div class="print-controls">
  <button class="btn-print" onclick="window.print()">
    🖨️ Print Prescription
  </button>
  <a href="/panacea/admin/patients.php?action=view&id=<?= $record['patient_id'] ?>"
     class="btn-back">← Back to Patient</a>
  <a href="/panacea/admin/records.php" class="btn-back">← All Records</a>
</div>

<div class="copy-watermark">COPY</div>

<!-- PRESCRIPTION PAPER -->
<div class="rx-paper">

  <!-- HEADER -->
  <div class="rx-header">
    <div class="hospital-brand">
      <div class="hospital-logo">🏥</div>
      <div class="hospital-info">
        <strong>Panacea Hospital</strong>
        <span>
          Hawassa, Sidama Region, Ethiopia<br>
          📞 +251 917 000 000 · info@panaceahospital.et
        </span>
      </div>
    </div>
    <div class="rx-title">
      <div class="rx-symbol">℞</div>
      <div class="rx-label">Prescription</div>
      <div class="rx-date"><?= date('d M Y', strtotime($record['visit_date'])) ?></div>
    </div>
  </div>

  <!-- BODY -->
  <div class="rx-body">

    <!-- Patient Info -->
    <div class="patient-strip">
      <div class="patient-field">
        <label>Patient Name</label>
        <span><?= htmlspecialchars($record['patient_name']) ?></span>
      </div>
      <div class="patient-field">
        <label>Patient ID</label>
        <span><?= htmlspecialchars($record['pid']) ?></span>
      </div>
      <div class="patient-field">
        <label>Age / Gender</label>
        <span><?= $age ?> yrs / <?= htmlspecialchars($record['gender']) ?></span>
      </div>
      <div class="patient-field">
        <label>Blood Group</label>
        <span style="color:#c0162c;font-weight:700"><?= htmlspecialchars($record['blood_group']) ?></span>
      </div>
    </div>

    <!-- Allergy Warning -->
    <?php if ($record['allergies']): ?>
    <div class="allergy-box">
      <span style="font-size:1.2rem">⚠️</span>
      <div><strong style="color:#c0162c">ALLERGY ALERT:</strong>
        <?= htmlspecialchars($record['allergies']) ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Doctor Info -->
    <div class="doctor-strip">
      <div class="doc-name">Dr. <?= htmlspecialchars($record['doc_name'] ?? 'Unknown') ?></div>
      <div class="doc-spec">
        <?= htmlspecialchars($record['specialization'] ?? '') ?>
        <?php if ($record['dept_name']): ?> · <?= htmlspecialchars($record['dept_name']) ?><?php endif; ?>
      </div>
    </div>

    <!-- Diagnosis -->
    <?php if ($record['diagnosis']): ?>
    <div class="section-title">Diagnosis</div>
    <div class="diagnosis-box"><?= nl2br(htmlspecialchars($record['diagnosis'])) ?></div>
    <?php endif; ?>

    <!-- Chief Complaint -->
    <?php if ($record['chief_complaint']): ?>
    <div class="section-title">Chief Complaint</div>
    <div class="diagnosis-box" style="background:#f0f4f9"><?= htmlspecialchars($record['chief_complaint']) ?></div>
    <?php endif; ?>

    <!-- Prescription -->
    <?php if ($record['prescription']): ?>
    <hr class="rx-divider"/>
    <div class="section-title">Prescription</div>
    <div class="rx-symbol-large">℞</div>

    <?php if (count($prescriptionLines) > 1): ?>
      <!-- Numbered list if multiple lines -->
      <ul class="rx-items">
        <?php foreach ($prescriptionLines as $i => $line): ?>
        <li class="rx-item">
          <div class="rx-item-num"><?= $i + 1 ?></div>
          <div class="rx-item-text"><?= htmlspecialchars($line) ?></div>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <!-- Free text if single block -->
      <div class="rx-freetext"><?= htmlspecialchars($record['prescription']) ?></div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Treatment -->
    <?php if ($record['treatment']): ?>
    <hr class="rx-divider"/>
    <div class="section-title">Treatment Plan</div>
    <div class="instructions-box">
      <strong>📋 Instructions: </strong><?= nl2br(htmlspecialchars($record['treatment'])) ?>
    </div>
    <?php endif; ?>

    <!-- Lab Results -->
    <?php if ($record['lab_results']): ?>
    <div class="section-title">Lab Results</div>
    <div class="diagnosis-box" style="font-family:monospace;font-size:.85rem"><?= nl2br(htmlspecialchars($record['lab_results'])) ?></div>
    <?php endif; ?>

    <!-- Follow Up -->
    <?php if ($record['follow_up_date']): ?>
    <div class="followup-box">
      <span style="font-size:1.2rem">📅</span>
      <div>
        <strong>Follow-up Date: </strong>
        <?= date('d M Y', strtotime($record['follow_up_date'])) ?>
        <span style="color:#7a8da8;font-size:.82rem;margin-left:8px">
          Please return to the hospital on this date.
        </span>
      </div>
    </div>
    <?php endif; ?>

    <!-- Signature -->
    <div class="signature-section">
      <div class="sig-left">
        <div>Date: <?= date('d M Y', strtotime($record['visit_date'])) ?></div>
        <div style="margin-top:4px">Visit Type: <?= htmlspecialchars($record['visit_type']) ?></div>
        <div style="margin-top:4px">Record ID: #<?= $record['id'] ?></div>
      </div>
      <div class="sig-right">
        <div class="sig-line"></div>
        <div class="sig-name"><?= htmlspecialchars($record['doc_name'] ?? 'Attending Physician') ?></div>
        <div class="sig-label">
          <?= htmlspecialchars($record['specialization'] ?? '') ?><br>
          Panacea Hospital, Hawassa
        </div>
      </div>
    </div>

  </div><!-- /rx-body -->

  <!-- FOOTER -->
  <div class="rx-footer">
    <span>🏥 <strong>Panacea Hospital</strong> · Hawassa, Sidama Region, Ethiopia</span>
    <span>📞 <strong>+251 917 000 000</strong></span>
    <span>Emergency: <strong>+251 917 111 111</strong> (24/7)</span>
  </div>

</div><!-- /rx-paper -->

<script>
  // Auto print option
  const params = new URLSearchParams(window.location.search);
  if (params.get('auto') === '1') window.onload = () => window.print();
</script>
</body>
</html>
