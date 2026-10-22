<?php
// config/env.php
declare(strict_types=1);

/**
 * Lightweight .env Loader
 * Memuat variabel lingkungan dari berkas .env di root proyek
 */
function load_env(string $filePath): void {
    if (!file_exists($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        // Abaikan komentar
        if (str_starts_with($line, '#') || empty($line)) {
            continue;
        }

        if (str_contains($line, '=')) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Bersihkan kutip jika ada
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            // Simpan ke $_ENV dan putenv
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }
    }
}

/**
 * Mengambil nilai konfigurasi dari .env dengan nilai bawaan (fallback)
 */
function env(string $key, mixed $default = null): mixed {
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    $val = getenv($key);
    if ($val !== false) {
        return $val;
    }
    return $default;
}

// Muat berkas .env otomatis
load_env(dirname(__DIR__) . '/.env');
