<?php
// ============================================================
//  PANACEA HOSPITAL – Security Functions
//  includes/security.php
//  Include this at top of helpers.php
// ============================================================

// ── Configuration ─────────────────────────────────────────
define('MAX_LOGIN_ATTEMPTS',  5);           // Block after 5 wrong tries
define('LOGIN_LOCKOUT_TIME',  15 * 60);     // 15 minutes lockout
define('SESSION_TIMEOUT',     60 * 60);     // 1 hour idle timeout
define('MIN_PASSWORD_LENGTH', 8);           // Minimum password length

// ── Session Security ──────────────────────────────────────
function secureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
        session_start();
    }

    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regenerated'])) {
        session_regenerate_id(true);
        $_SESSION['last_regenerated'] = time();
    } elseif (time() - $_SESSION['last_regenerated'] > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regenerated'] = time();
    }
}

// ── Session Timeout Check ─────────────────────────────────
function checkSessionTimeout(): bool {
    if (!isset($_SESSION['admin_id'])) return false;

    if (isset($_SESSION['last_activity'])) {
        $idle = time() - $_SESSION['last_activity'];
        if ($idle > SESSION_TIMEOUT) {
            // Session expired
            session_destroy();
            return false;
        }
    }
    $_SESSION['last_activity'] = time();
    return true;
}

// ── Brute Force Protection ────────────────────────────────
function getLoginAttempts(string $ip): array {
    $file = sys_get_temp_dir() . '/panacea_login_' . md5($ip) . '.json';
    if (!file_exists($file)) return ['attempts' => 0, 'last_attempt' => 0];
    $data = json_decode(file_get_contents($file), true);
    return $data ?: ['attempts' => 0, 'last_attempt' => 0];
}

function recordLoginAttempt(string $ip): void {
    $file = sys_get_temp_dir() . '/panacea_login_' . md5($ip) . '.json';
    $data = getLoginAttempts($ip);
    $data['attempts']++;
    $data['last_attempt'] = time();
    file_put_contents($file, json_encode($data));
}

function clearLoginAttempts(string $ip): void {
    $file = sys_get_temp_dir() . '/panacea_login_' . md5($ip) . '.json';
    if (file_exists($file)) unlink($file);
}

function isLockedOut(string $ip): bool {
    $data = getLoginAttempts($ip);
    if ($data['attempts'] < MAX_LOGIN_ATTEMPTS) return false;
    // Check if lockout period has passed
    if (time() - $data['last_attempt'] > LOGIN_LOCKOUT_TIME) {
        clearLoginAttempts($ip);
        return false;
    }
    return true;
}

function getLockoutRemaining(string $ip): int {
    $data = getLoginAttempts($ip);
    $remaining = LOGIN_LOCKOUT_TIME - (time() - $data['last_attempt']);
    return max(0, (int)ceil($remaining / 60)); // Return minutes
}

// ── Password Strength Validator ───────────────────────────
function validatePassword(string $password): array {
    $errors = [];
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'At least ' . MIN_PASSWORD_LENGTH . ' characters required.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'At least one uppercase letter required.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'At least one lowercase letter required.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'At least one number required.';
    }
    return $errors;
}

// ── Input Sanitization ────────────────────────────────────
function sanitizeInput(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function sanitizeInt(mixed $val): int {
    return (int)filter_var($val, FILTER_SANITIZE_NUMBER_INT);
}

function sanitizeEmail(string $email): string {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

// ── Rate Limiting ─────────────────────────────────────────
function rateLimit(string $key, int $maxRequests = 30, int $perSeconds = 60): bool {
    $file  = sys_get_temp_dir() . '/panacea_rate_' . md5($key) . '.json';
    $now   = time();
    $data  = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

    // Remove old entries
    $data  = array_filter($data ?? [], fn($t) => $now - $t < $perSeconds);

    if (count($data) >= $maxRequests) return false; // Rate limited

    $data[] = $now;
    file_put_contents($file, json_encode(array_values($data)));
    return true;
}

// ── XSS Prevention ────────────────────────────────────────
function e(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Secure File Upload ────────────────────────────────────
function validateUpload(array $file, array $allowedTypes = ['image/jpeg','image/png','image/gif'], int $maxSize = 2097152): array {
    $errors = [];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload error.';
        return $errors;
    }
    if ($file['size'] > $maxSize) {
        $errors[] = 'File too large. Max ' . ($maxSize / 1024 / 1024) . 'MB allowed.';
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowedTypes)) {
        $errors[] = 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes);
    }
    return $errors;
}

// ── Security Log ──────────────────────────────────────────
function securityLog(string $event, string $detail = ''): void {
    $logFile = dirname(__DIR__) . '/logs/security.log';
    if (!is_dir(dirname($logFile))) mkdir(dirname($logFile), 0755, true);
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $line = sprintf("[%s] [%s] %s %s\n", date('Y-m-d H:i:s'), $ip, $event, $detail);
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
