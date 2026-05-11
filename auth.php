<?php
// ── includes/auth.php ─────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode',  '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly',  '1');
    ini_set('session.cookie_samesite',  'Strict');
    ini_set('session.gc_maxlifetime',   '3600');
    session_start();
}

// ── Brute-Force constants ────────────────────────────────────────────────────
define('MAX_ATTEMPTS',   10);
define('LOCKOUT_WINDOW', 900); // 15 minutes

function recordAttempt(mysqli $conn, string $ip): void {
    $ip  = $conn->real_escape_string($ip);
    $now = time();
    $conn->query("INSERT INTO login_attempts (ip_address, attempted_at) VALUES ('$ip', $now)");
}

function isLockedOut(mysqli $conn, string $ip): bool {
    $ip     = $conn->real_escape_string($ip);
    $window = time() - LOCKOUT_WINDOW;
    $res    = $conn->query(
        "SELECT COUNT(*) AS cnt FROM login_attempts
         WHERE ip_address='$ip' AND attempted_at > $window"
    );
    if (!$res) return false;
    return (int)$res->fetch_assoc()['cnt'] >= MAX_ATTEMPTS;
}

function clearAttempts(mysqli $conn, string $ip): void {
    $ip     = $conn->real_escape_string($ip);
    $cutoff = time() - LOCKOUT_WINDOW;
    $conn->query("DELETE FROM login_attempts WHERE ip_address='$ip'");
    $conn->query("DELETE FROM login_attempts WHERE attempted_at <= $cutoff");
}

function getClientIP(): string {
    $candidates = [
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['HTTP_X_REAL_IP']       ?? '',
        $_SERVER['REMOTE_ADDR']          ?? '0.0.0.0',
    ];
    foreach ($candidates as $ip) {
        $ip = trim(explode(',', $ip)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return '0.0.0.0';
}

// ── CSRF ─────────────────────────────────────────────────────────────────────
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    $expected = $_SESSION['csrf_token'] ?? '';
    return $expected !== '' && hash_equals($expected, $token);
}

function requireCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        exit('Security check failed. Please go back and try again.');
    }
}

function csrfField(): string {
    $t = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($t, ENT_QUOTES) . '">';
}

// ── Session ───────────────────────────────────────────────────────────────────
function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['full_name']  = $user['full_name'];
    $_SESSION['email']      = $user['email'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['auth_time']  = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    if (isset($_SESSION['auth_time']) && (time() - $_SESSION['auth_time']) > 3600) {
        session_unset();
        session_destroy();
        header('Location: login.php?session_expired=1');
        exit;
    }
    $_SESSION['auth_time'] = time();
}

function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: dashboard.php');
        exit;
    }
}

function isAdmin():    bool { return isLoggedIn() && $_SESSION['role'] === 'admin'; }
function isStaff():    bool { return isLoggedIn() && $_SESSION['role'] === 'staff'; }
function isResident(): bool { return isLoggedIn() && $_SESSION['role'] === 'resident'; }

// ── Notifications ─────────────────────────────────────────────────────────────
function getUnreadCount(mysqli $conn): int {
    if (!isLoggedIn()) return 0;
    $uid = (int)$_SESSION['user_id'];
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id=$uid AND is_read=0");
    return $res ? (int)$res->fetch_assoc()['cnt'] : 0;
}

function addNotification(mysqli $conn, int $userId, int $complaintId, string $message): void {
    $message = $conn->real_escape_string($message);
    $conn->query(
        "INSERT INTO notifications (user_id, complaint_id, message)
         VALUES ($userId, $complaintId, '$message')"
    );
}

// ── File Upload ───────────────────────────────────────────────────────────────
function uploadPhoto(array $file, string $prefix = 'img'): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size']  > 5 * 1024 * 1024)  return false;
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mimeType, $allowed, true)) return false;
    $ext = match($mimeType) {
        'image/jpeg' => 'jpg', 'image/png' => 'png',
        'image/gif'  => 'gif', 'image/webp' => 'webp', default => 'jpg',
    };
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest     = __DIR__ . '/../uploads/' . $filename;
    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;
    return $filename;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function generateComplaintNo(): string {
    return 'CMP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function statusBadge(string $status): string {
    $map = [
        'pending'     => ['⏳', '#b45309', '#fef3c7'],
        'in_progress' => ['🔄', '#1d4ed8', '#dbeafe'],
        'resolved'    => ['✅', '#15803d', '#dcfce7'],
        'rejected'    => ['❌', '#b91c1c', '#fee2e2'],
    ];
    [$icon, $color, $bg] = $map[$status] ?? ['•', '#6b7280', '#f3f4f6'];
    $label = ucfirst(str_replace('_', ' ', $status));
    return "<span style='background:{$bg};color:{$color};padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;'>{$icon} {$label}</span>";
}

function categoryIcon(string $cat): string {
    $icons = [
        'facilities'  => '🏢', 'maintenance' => '🔧',
        'cleanliness' => '🧹', 'noise'       => '🔊',
        'safety'      => '⚠️', 'utilities'   => '💡',
        'parking'     => '🚗', 'others'      => '📌', 'other' => '📌',
    ];
    return $icons[strtolower($cat)] ?? '📌';
}
