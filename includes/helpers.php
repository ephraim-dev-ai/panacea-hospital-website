<?php
// ============================================================
//  PANACEA HOSPITAL – Helpers (Security Enhanced)
//  includes/helpers.php  — REPLACE existing file
// ============================================================
require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/security.php';

// ── Auth ──────────────────────────────────────────────────
function startSession(): void {
    secureSession();
}

function isLoggedIn(): bool {
    secureSession();
    if (!isset($_SESSION['admin_id'])) return false;
    // Check session timeout
    if (!checkSessionTimeout()) {
        session_destroy();
        return false;
    }
    return true;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        // Save intended URL for redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: /panacea/admin/login.php');
        exit;
    }
}

function currentAdmin(): array {
    secureSession();
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
    try {
        db()->prepare('INSERT INTO activity_log (admin_id, action, target, ip_address) VALUES (?,?,?,?)')
            ->execute([$admin['id'], $action, $target, $ip]);
    } catch (Exception $e) {
        // Silently fail if activity_log doesn't exist
    }
}

// ── Input / Security ─────────────────────────────────────
function clean(?string $val): string {
    if ($val === null) return '';
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function csrf(): string {
    secureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    secureSession();
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        securityLog('CSRF_VIOLATION', $_SERVER['REQUEST_URI'] ?? '');
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;color:#c0162c"><h2>Security Error</h2><p>Invalid security token. Please go back and try again.</p><a href="/panacea/admin/">Return to Admin</a></div>');
    }
}

// ── Patient ID generator ─────────────────────────────────
function generatePatientId(): string {
    $year  = date('Y');
    $stmt  = db()->query("SELECT COUNT(*) FROM patients WHERE YEAR(registered_at) = $year");
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf('PH-%s-%04d', $year, $count);
}

// ── Appointment ref generator ─────────────────────────────
function generateApptRef(): string {
    $stmt  = db()->query('SELECT COUNT(*) FROM appointments');
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf('APT-%08d', $count);
}

// ── Flash messages ────────────────────────────────────────
function flash(string $key, string $msg, string $type = 'success'): void {
    secureSession();
    $_SESSION['flash'][$key] = ['msg' => $msg, 'type' => $type];
}

function getFlash(string $key): ?array {
    secureSession();
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

// ── Dashboard stats ───────────────────────────────────────
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

// ── Pagination helper ─────────────────────────────────────
function paginate(int $total, int $perPage, int $current): array {
    $pages = (int)ceil($total / $perPage);
    return ['total' => $total, 'per_page' => $perPage, 'current' => $current, 'pages' => $pages];
}

// ── Age from DOB ──────────────────────────────────────────
function calcAge(string $dob): int {
    return (int)(new DateTime($dob))->diff(new DateTime())->y;
}

// ── Status badge ──────────────────────────────────────────
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
        'Unpaid'     => 'danger',
        'Paid'       => 'success',
        'Partial'    => 'warning',
    ];
    $c = $map[$status] ?? 'secondary';
    return "<span class=\"badge bg-$c\">" . clean($status) . '</span>';
}
