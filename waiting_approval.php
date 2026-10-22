<?php
// waiting_approval.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';

if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$user = current_user();
if (!$user) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// If already approved, redirect to dashboard immediately
if ($user['account_status'] === 'disetujui') {
    flash_set('success', 'Akun Anda telah disetujui! Selamat datang di sistem absensi.');
    if ($user['role'] === 'admin') {
        header('Location: ' . BASE_URL . '/admin_dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/employee_dashboard.php');
    }
    exit;
} elseif ($user['account_status'] === 'ditolak') {
    $reason = $user['rejection_reason'] ? " Alasan: " . $user['rejection_reason'] : "";
    session_unset();
    session_destroy();
    flash_set('danger', 'Mohon maaf, pendaftaran akun Anda ditolak oleh Admin.' . $reason);
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menunggu Persetujuan Akun — <?= clean_input(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="auth-page">

<div class="auth-box" style="text-align: center; max-width: 520px;">
    <div style="padding: 40px 32px;">
        <div style="width: 80px; height: 80px; background: var(--warning-50); border: 2px solid rgba(245, 158, 11, 0.3); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>

        <h1 style="font-size: 1.4rem; font-weight: 800; color: var(--navy-900); margin-bottom: 8px;">
            Akun Sedang Diverifikasi
        </h1>
        <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 24px;">
            Halo <strong><?= clean_input($user['nama_lengkap']) ?></strong> (NIK: <?= clean_input($user['nik']) ?>), pendaftaran Anda telah berhasil dicatat dan sedang dalam antrean pemeriksaan oleh Administrator / HR.
        </p>

        <div style="background: #F8FAFC; border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 16px; text-align: left; margin-bottom: 24px; font-size: 0.84rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="color: var(--text-muted);">Jabatan:</span>
                <span style="font-weight: 600; color: var(--navy-900);"><?= clean_input($user['jabatan']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="color: var(--text-muted);">No. WhatsApp:</span>
                <span style="font-weight: 600; color: var(--navy-900);"><?= clean_input($user['no_wa']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Status Verifikasi:</span>
                <span class="badge badge-warning">Menunggu Persetujuan</span>
            </div>
        </div>

        <div style="display: flex; gap: 12px; justify-content: center;">
            <a href="<?= BASE_URL ?>/waiting_approval.php" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"></path><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                Periksa Status Terbaru
            </a>
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-secondary">
                Keluar
            </a>
        </div>
    </div>
</div>

</body>
</html>
