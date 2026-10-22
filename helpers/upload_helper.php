<?php
// helpers/upload_helper.php
declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';

function upload_evidence(array $file): array {
    // If no file uploaded
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filename' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Terjadi kesalahan saat mengunggah file (Kode error: ' . $file['error'] . ')'];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'Ukuran file melebihi batas maksimal 2 MB.'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];

    if (!in_array($extension, $allowedExtensions, true)) {
        return ['success' => false, 'error' => 'Format file tidak diizinkan. Hanya menerima JPG, PNG, dan PDF.'];
    }

    // Verify MIME type safely
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'application/pdf',
        'application/x-pdf'
    ];

    if (!in_array($mimeType, $allowedMimes, true)) {
        return ['success' => false, 'error' => 'Tipe konten file tidak valid atau berbahaya.'];
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $newFilename = 'bukti_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $targetPath = UPLOAD_DIR . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Gagal memindahkan file ke folder penyimpanan server.'];
    }

    return ['success' => true, 'filename' => $newFilename];
}
