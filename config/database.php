<?php
// config/database.php
declare(strict_types=1);

/**
 * ============================================================================
 * PERHATIAN:
 * File ini HANYALAH LOADER OTOMATIS yang membaca dari file `.env`.
 * Anda TIDAK PERLU mengedit file ini sama sekali.
 * Semua pengaturan host, port, nama database, user, dan password 
 * diatur HANYA pada file: `.env` di root proyek.
 * ============================================================================
 */

require_once __DIR__ . '/env.php';

function get_db_connection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        // Ambil konfigurasi murni dari .env
        $host   = env('DB_HOST', '127.0.0.1');
        $port   = env('DB_PORT', '3306');
        $dbname = env('DB_DATABASE', 'db_absensi_karyawan');
        $user   = env('DB_USERNAME', 'root');
        $pass   = env('DB_PASSWORD', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            die("Koneksi database gagal: " . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}
