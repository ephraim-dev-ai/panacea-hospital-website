<?php
// admin/ping.php — keeps session alive when user clicks "Stay Logged In"
require_once dirname(__FILE__) . '/../includes/helpers.php';
if (isLoggedIn()) {
    $_SESSION['last_activity'] = time();
    echo json_encode(['status' => 'ok', 'time' => time()]);
} else {
    http_response_code(401);
    echo json_encode(['status' => 'expired']);
}
