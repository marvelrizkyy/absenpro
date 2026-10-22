<?php
// register.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';
require_once __DIR__ . '/helpers/fonnte_helper.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$errors = [];
$nik = $_POST['nik'] ?? '';
$nama = $_POST['nama_lengkap'] ?? '';
$jabatan = $_POST['jabatan'] ?? '';
$noWa = $_POST['no_wa'] ?? '';
$email = $_POST['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Sesi keamanan tidak sah. Silakan coba lagi.';
    }

    if (empty($nik) || strlen($nik) < 3) {
        $errors[] = 'NIK wajib diisi minimal 3 karakter.';
    }
    if (empty($nama)) {
        $errors[] = 'Nama lengkap wajib diisi.';
    }
    if (empty($jabatan)) {
        $errors[] = 'Jabatan wajib dipilih / diisi.';
    }
    if (empty($noWa)) {
        $errors[] = 'Nomor WhatsApp wajib diisi untuk penerimaan notifikasi presensi.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format alamat email tidak valid.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Kata sandi minimal 6 karakter.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Konfirmasi kata sandi tidak cocok.';
    }

    if (empty($errors)) {
        $db = get_db_connection();

        // Check unique NIK
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE nik = ?");
        $stmt->execute([$nik]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "NIK '{$nik}' sudah terdaftar dalam sistem.";
        }

        // Check unique Email
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Alamat email '{$email}' sudah digunakan.";
        }

        if (empty($errors)) {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            // Strict enforcement: public registration is ALWAYS role 'karyawan' and account_status 'menunggu'
            $stmt = $db->prepare("INSERT INTO users 
                (nik, nama_lengkap, jabatan, no_wa, email, password_hash, role, account_status, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'karyawan', 'menunggu', 1, NOW())");
            
            $stmt->execute([
                $nik,
                $nama,
                $jabatan,
                $noWa,
                $email,
                $passwordHash
            ]);

            $newUserId = (int)$db->lastInsertId();

            // Set session for waiting state
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['role'] = 'karyawan';
            $_SESSION['nama'] = $nama;

            // Trigger Fonnte WhatsApp notification to Admin & Employee
            $stmtSet = $db->query("SELECT admin_phone, company_name FROM work_settings WHERE id = 1");
            $workSettings = $stmtSet->fetch();
            $adminPhone = $workSettings['admin_phone'] ?? '081234567890';
            $companyName = $workSettings['company_name'] ?? 'PT Inovasi Digital Nusantara';

            // Message for Admin
            $adminMsg = "🔔 *PENDAFTARAN KARYAWAN BARU*\n\n"
                      . "Halo Admin, terdapat pendaftaran akun karyawan baru yang memerlukan persetujuan:\n\n"
                      . "• *Nama:* {$nama}\n"
                      . "• *NIK:* {$nik}\n"
                      . "• *Jabatan:* {$jabatan}\n"
                      . "• *No. WhatsApp:* {$noWa}\n"
                      . "• *Email:* {$email}\n"
                      . "• *Waktu Daftar:* " . date('d-m-Y H:i') . " WIB\n\n"
                      . "Silakan login ke portal admin untuk meninjau dan menyetujui akun ini.";
            send_whatsapp_notification($adminPhone, 'new_registration_admin', $adminMsg, $newUserId);

            // Message for Employee
            $empMsg = "👋 *PENDAFTARAN AKUN DITERIMA*\n\n"
                    . "Halo {$nama},\n"
                    . "Pendaftaran akun Anda di *{$companyName}* berhasil kami terima.\n\n"
                    . "• *NIK:* {$nik}\n"
                    . "• *Status:* Menunggu Verifikasi HR / Admin\n\n"
                    . "Anda akan menerima pemberitahuan WhatsApp setelah akun disetujui. Terima kasih.";
            send_whatsapp_notification($noWa, 'new_registration_user', $empMsg, $newUserId);

            flash_set('success', 'Pendaftaran berhasil dikirim! Silakan menunggu persetujuan dari Administrator.');
            header('Location: ' . BASE_URL . '/waiting_approval.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Karyawan Baru — <?= clean_input(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="auth-page">

<div class="auth-box" style="max-width: 580px;">
    <div class="auth-header">
        <div class="auth-logo">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
        </div>
        <h1 class="auth-title">Registrasi Karyawan</h1>
        <p class="auth-subtitle">Lengkapi identitas untuk membuat akun absensi resmi</p>
    </div>

    <div class="auth-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <span class="alert-icon">✕</span>
                <div class="alert-text">
                    <ul style="margin-left: 18px; margin-top: 4px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= clean_input($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/register.php" method="POST">
            <?= csrf_field() ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label for="nik" class="form-label required">Nomor Induk Karyawan (NIK)</label>
                    <input type="text" id="nik" name="nik" class="form-control" placeholder="Contoh: KRY009" value="<?= clean_input($nik) ?>" required>
                </div>
                <div class="form-group">
                    <label for="nama_lengkap" class="form-label required">Nama Lengkap</label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" placeholder="Nama lengkap Anda..." value="<?= clean_input($nama) ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label for="jabatan" class="form-label required">Jabatan / Posisi</label>
                    <input type="text" id="jabatan" name="jabatan" class="form-control" placeholder="Contoh: Staff Keuangan" value="<?= clean_input($jabatan) ?>" required>
                </div>
                <div class="form-group">
                    <label for="no_wa" class="form-label required">Nomor WhatsApp Aktif</label>
                    <input type="text" id="no_wa" name="no_wa" class="form-control" placeholder="081234567890" value="<?= clean_input($noWa) ?>" required>
                    <div class="form-hint">Digunakan untuk notifikasi presensi otomatis.</div>
                </div>
            </div>

            <div class="form-group">
                <label for="email" class="form-label required">Alamat Email</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="nama@perusahaan.com" value="<?= clean_input($email) ?>" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label for="password" class="form-label required">Kata Sandi</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Minimal 6 karakter..." required>
                </div>
                <div class="form-group">
                    <label for="confirm_password" class="form-label required">Ulangi Kata Sandi</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Konfirmasi kata sandi..." required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
                Kirim Pendaftaran Akun
            </button>
        </form>

        <div style="text-align: center; margin-top: 22px; font-size: 0.85rem; color: var(--text-muted);">
            Sudah memiliki akun terdaftar? <br>
            <a href="<?= BASE_URL ?>/login.php" style="font-weight: 700;">Masuk di sini &rarr;</a>
        </div>
    </div>
</div>

</body>
</html>
