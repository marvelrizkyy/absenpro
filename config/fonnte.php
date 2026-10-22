<?php
// config/fonnte.php
declare(strict_types=1);

/**
 * ============================================================================
 * PERHATIAN:
 * File ini HANYALAH LOADER OTOMATIS yang membaca dari file `.env`.
 * Anda TIDAK PERLU mengedit file ini sama sekali.
 * Semua pengaturan token dan endpoint cukup diatur pada file: `.env` di root.
 * ============================================================================
 */

require_once __DIR__ . '/env.php';

// Memuat konfigurasi Fonnte langsung dan murni dari file .env
define('FONNTE_DEFAULT_TOKEN', env('FONNTE_TOKEN', 'YOUR_FONNTE_API_TOKEN_HERE'));
define('FONNTE_API_URL', env('FONNTE_API_URL', 'https://api.fonnte.com/send'));

/**
 * Mengambil token Fonnte aktif langsung dari berkas .env
 */
function get_fonnte_token(): string {
    return trim((string)env('FONNTE_TOKEN', FONNTE_DEFAULT_TOKEN));
}

