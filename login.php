<?php
// login.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';

if (is_logged_in()) {
    $user = current_user();
    if ($user) {
        if ($user['role'] === 'admin') {
            header('Location: ' . BASE_URL . '/admin_dashboard.php');
            exit;
        } else {
            if ($user['account_status'] === 'menunggu') {
                header('Location: ' . BASE_URL . '/waiting_approval.php');
                exit;
            }
            header('Location: ' . BASE_URL . '/employee_dashboard.php');
            exit;
        }
    }
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $error = 'Sesi keamanan telah kedaluwarsa. Silakan muat ulang halaman.';
    } elseif (empty($identifier) || empty($password)) {
        $error = 'Email/NIK dan Password wajib diisi.';
    } else {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT * FROM users WHERE (email = :email OR nik = :nik) LIMIT 1");
        $stmt->execute([':email' => $identifier, ':nik' => $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['is_active'] != 1) {
                $error = 'Akun Anda telah dinonaktifkan oleh Administrator. Hubungi HR.';
            } elseif ($user['account_status'] === 'ditolak') {
                $reason = $user['rejection_reason'] ? " Alasan: " . $user['rejection_reason'] : "";
                $error = 'Pendaftaran akun Anda ditolak oleh Admin.' . $reason;
            } else {
                // Successful login
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['nama'] = $user['nama_lengkap'];

                if ($user['account_status'] === 'menunggu') {
                    header('Location: ' . BASE_URL . '/waiting_approval.php');
                    exit;
                }

                flash_set('success', 'Selamat datang kembali, ' . $user['nama_lengkap'] . '!');
                if ($user['role'] === 'admin') {
                    header('Location: ' . BASE_URL . '/admin_dashboard.php');
                } else {
                    header('Location: ' . BASE_URL . '/employee_dashboard.php');
                }
                exit;
            }
        } else {
            $error = 'Email/NIK atau kata sandi tidak cocok.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Sistem — <?= clean_input(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="auth-page">

<div class="auth-box">
    <div class="auth-header">
        <div class="auth-logo">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.5"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
        </div>
        <h1 class="auth-title">ABSENSI PRO</h1>
        <p class="auth-subtitle">Sistem Manajemen Karyawan & Absensi Presisi</p>
    </div>

    <div class="auth-body">
        <?= render_flash_messages() ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <span class="alert-icon">✕</span>
                <span class="alert-text"><?= clean_input($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/login.php" method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="identifier" class="form-label required">Email atau NIK</label>
                <input type="text" id="identifier" name="identifier" class="form-control" placeholder="admin@absenkaryawan.local atau NIK..." value="<?= clean_input($identifier) ?>" required autofocus>
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label for="password" class="form-label required" style="margin-bottom: 0;">Kata Sandi</label>
                   
                </div>
                <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan kata sandi..." required>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
                Masuk ke Akun
            </button>
        </form>

        <!-- Quick Demo Credentials Box -->
        <div style="margin-top: 24px; padding: 14px; background: #F8FAFC; border: 1px dashed var(--border-light); border-radius: var(--radius-md); font-size: 0.8rem;">
            <div style="font-weight: 700; color: var(--navy-800); margin-bottom: 6px;">💡 Akun Demo Cepat:</div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="fillCreds('admin@absenkaryawan.local', 'admin123')">
                    👑 Login Admin
                </button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="fillCreds('budi@absenkaryawan.local', 'karyawan123')">
                    👤 Login Karyawan
                </button>
            </div>
        </div>

        <div style="text-align: center; margin-top: 22px; font-size: 0.85rem; color: var(--text-muted);">
            Belum memiliki akun karyawan? <br>
            <a href="<?= BASE_URL ?>/register.php" style="font-weight: 700;">Daftar Akun Karyawan Baru &rarr;</a>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const pw = document.getElementById('password');
    pw.type = pw.type === 'password' ? 'text' : 'password';
}
function fillCreds(id, pass) {
    document.getElementById('identifier').value = id;
    document.getElementById('password').value = pass;
}
</script>
</body>
</html>
