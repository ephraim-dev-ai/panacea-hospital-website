<?php
// submit_appointment.php  – handles POST from the public website
require_once __DIR__ . '/config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$name  = trim($_POST['patient_name']  ?? '');
$phone = trim($_POST['patient_phone'] ?? '');
$email = trim($_POST['patient_email'] ?? '');
$dept  = (int)($_POST['department_id'] ?? 0);
$date  = $_POST['appt_date']  ?? '';
$time  = $_POST['appt_time']  ?? 'Morning (7AM–12PM)';
$reason= trim($_POST['reason'] ?? '');

if (!$name || !$phone || !$dept || !$date) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) < strtotime('today')) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid future date.']);
    exit;
}

// Generate ref
$count = (int)db()->query('SELECT COUNT(*) FROM appointments')->fetchColumn() + 1;
$ref   = sprintf('APT-%08d', $count);

db()->prepare('INSERT INTO appointments
    (ref_number, patient_name, patient_phone, patient_email, department_id, appt_date, appt_time, reason, status)
    VALUES (?,?,?,?,?,?,?,?,\'Pending\')')
    ->execute([$ref, $name, $phone, $email, $dept, $date, $time, $reason]);

echo json_encode([
    'success'    => true,
    'message'    => 'Appointment request submitted! Reference: ' . $ref . '. We will confirm within 24 hours.',
    'ref_number' => $ref,
]);
