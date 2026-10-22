<?php
// config/constants.php
declare(strict_types=1);

/**
 * ============================================================================
 * PERHATIAN:
 * File ini HANYALAH LOADER OTOMATIS yang membaca dari file `.env`.
 * Anda TIDAK PERLU mengedit file ini sama sekali.
 * Semua pengaturan nama aplikasi & zona waktu diatur HANYA di: `.env`.
 * ============================================================================
 */

require_once __DIR__ . '/env.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// Timezone murni dari .env
date_default_timezone_set(env('TIMEZONE', 'Asia/Jakarta'));

// Nama Aplikasi murni dari .env
define('APP_NAME', env('APP_NAME', 'Sistem Informasi Absensi & Manajemen Karyawan'));
define('BASE_PATH', dirname(__DIR__));

// Dynamic BASE_URL calculation
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = str_replace('\\', '/', $scriptDir);

// Find the project root relative to script execution
$baseUri = '/absenkaryawan';
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/absenkaryawan') !== false) {
    $baseUri = '/absenkaryawan';
} else {
    $baseUri = rtrim($scriptDir, '/');
}
define('BASE_URL', rtrim($protocol . $host . $baseUri, '/'));

// Uploads
define('UPLOAD_DIR', BASE_PATH . '/uploads/evidence/');
define('UPLOAD_URL', BASE_URL . '/uploads/evidence/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2 MB
