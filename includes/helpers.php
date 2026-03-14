<?php
require_once dirname(__FILE__) . '/../config/database.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
        session_start();
    }
}

function isLoggedIn(): bool {
    startSession();
    return isset($_SESSION['admin_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /panacea/admin/login.php');
        exit;
    }
}

function currentAdmin(): array {
    startSession();
    return [
        'id'       => $_SESSION['admin_id']   ?? 0,
        'username' => $_SESSION['admin_user']  ?? '',
        'name'     => $_SESSION['admin_name']  ?? '',
        'role'     => $_SESSION['admin_role']  ?? '',
    ];
}

function logActivity(string $action, string $target = ''): void {
    $admin = currentAdmin();
    if (!$admin['id']) return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    db()->prepare('INSERT INTO activity_log (admin_id, action, target, ip_address) VALUES (?,?,?,?)')
        ->execute([$admin['id'], $action, $target, $ip]);
}

function clean(?string $val): string {
    if ($val === null) return '';
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function csrf(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    startSession();
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

function generatePatientId(): string {
    $year  = date('Y');
    $stmt  = db()->query("SELECT COUNT(*) FROM patients WHERE YEAR(registered_at) = $year");
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf('PH-%s-%04d', $year, $count);
}

function generateApptRef(): string {
    $stmt  = db()->query('SELECT COUNT(*) FROM appointments');
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf('APT-%08d', $count);
}

function flash(string $key, string $msg, string $type = 'success'): void {
    startSession();
    $_SESSION['flash'][$key] = ['msg' => $msg, 'type' => $type];
}

function getFlash(string $key): ?array {
    startSession();
    if (isset($_SESSION['flash'][$key])) {
        $f = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $f;
    }
    return null;
}

function showFlash(string $key): string {
    $f = getFlash($key);
    if (!$f) return '';
    $cls = match($f['type']) {
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    return '<div class="alert ' . $cls . ' alert-dismissible fade show" role="alert">'
         . clean($f['msg'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

function dashStats(): array {
    $pdo = db();
    return [
        'patients'      => (int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn(),
        'today_appts'   => (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE appt_date = CURDATE()")->fetchColumn(),
        'pending_appts' => (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status='Pending'")->fetchColumn(),
        'unread_msgs'   => (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn(),
        'doctors'       => (int)$pdo->query("SELECT COUNT(*) FROM doctors WHERE is_active=1")->fetchColumn(),
        'total_appts'   => (int)$pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn(),
    ];
}

function paginate(int $total, int $perPage, int $current): array {
    $pages = (int)ceil($total / $perPage);
    return ['total' => $total, 'per_page' => $perPage, 'current' => $current, 'pages' => $pages];
}

function calcAge(string $dob): int {
    return (int)(new DateTime($dob))->diff(new DateTime())->y;
}
function statusBadge(string $status): string {
    $map = [
        'Pending'    => 'warning',
        'Confirmed'  => 'info',
        'Completed'  => 'success',
        'Cancelled'  => 'danger',
        'No-Show'    => 'secondary',
        'Active'     => 'success',
        'Inactive'   => 'secondary',
        'Discharged' => 'info',
        'Deceased'   => 'dark',
    ];
    $c = $map[$status] ?? 'secondary';
    return "<span class=\"badge bg-$c\">" . clean($status) . '</span>';
}