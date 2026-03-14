<?php
// submit_contact.php – handles POST from the public website contact form
require_once __DIR__ . '/config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$name    = trim($_POST['full_name'] ?? '');
$phone   = trim($_POST['phone']     ?? '');
$email   = trim($_POST['email']     ?? '');
$subject = trim($_POST['subject']   ?? '');
$message = trim($_POST['message']   ?? '');

if (!$name || !$phone || !$message) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

db()->prepare('INSERT INTO contact_messages (full_name, phone, email, subject, message) VALUES (?,?,?,?,?)')
    ->execute([$name, $phone, $email ?: null, $subject ?: null, $message]);

echo json_encode(['success' => true, 'message' => 'Message sent successfully! We will respond shortly.']);
