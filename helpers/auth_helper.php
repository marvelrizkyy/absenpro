<?php
// helpers/auth_helper.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/sanitize_helper.php';

function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    static $currentUser = null;
    if ($currentUser === null) {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT id, nik, nama_lengkap, jabatan, no_wa, email, role, account_status, is_active FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user || $user['is_active'] != 1) {
            // Invalidate session if user deactivated or deleted
            session_unset();
            session_destroy();
            return null;
        }
        $currentUser = $user;
    }
    return $currentUser;
}

function require_login(): void {
    if (!is_logged_in()) {
        flash_set('warning', 'Silakan masuk terlebih dahulu untuk mengakses sistem.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    $user = current_user();
    if (!$user) {
        flash_set('danger', 'Akun Anda tidak aktif atau tidak ditemukan.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    if ($user['account_status'] === 'menunggu') {
        if (basename($_SERVER['PHP_SELF']) !== 'waiting_approval.php') {
            header('Location: ' . BASE_URL . '/waiting_approval.php');
            exit;
        }
    } elseif ($user['account_status'] === 'ditolak') {
        session_unset();
        session_destroy();
        flash_set('danger', 'Pendaftaran akun Anda ditolak oleh Admin.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function require_admin(): void {
    require_login();
    $user = current_user();
    if ($user['role'] !== 'admin') {
        flash_set('danger', 'Akses ditolak: Halaman ini hanya dapat diakses oleh Administrator.');
        header('Location: ' . BASE_URL . '/employee_dashboard.php');
        exit;
    }
}

function require_employee(): void {
    require_login();
    $user = current_user();
    if ($user['role'] !== 'karyawan') {
        // Admin redirected to admin dashboard if accessing purely employee pages
        header('Location: ' . BASE_URL . '/admin_dashboard.php');
        exit;
    }
}

function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
