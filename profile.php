<?php
// profile.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';
require_once __DIR__ . '/helpers/fonnte_helper.php';

require_login();
$user = current_user();
$db = get_db_connection();
$userId = (int)$user['id'];

$pageTitle = 'Profil Pengguna';
$pageSubtitle = 'Pengaturan identitas dan keamanan kata sandi akun Anda';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        flash_set('danger', 'Sesi keamanan tidak sah. Coba lagi.');
        header('Location: ' . BASE_URL . '/profile.php');
        exit;
    }

    if ($action === 'update_profile') {
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $noWa = trim($_POST['no_wa'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($nama) || empty($noWa) || empty($email)) {
            flash_set('danger', 'Nama, Nomor WhatsApp, dan Email wajib diisi.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('danger', 'Format email tidak valid.');
        } else {
            // Check unique email except this user
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetchColumn() > 0) {
                flash_set('danger', 'Email sudah digunakan oleh pengguna lain.');
            } else {
                $stmt = $db->prepare("UPDATE users SET nama_lengkap = ?, no_wa = ?, email = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$nama, $noWa, $email, $userId]);

                // Kirim notifikasi WhatsApp ke nomor baru/lama dan tembusan ke Admin
                $profileMsg = "👤 *PEMBARUAN PROFIL PENGGUNA*\n\n"
                            . "Halo {$nama},\n"
                            . "Profil akun portal absensi Anda telah berhasil diperbarui pada " . date('d/m/Y H:i') . " WIB:\n\n"
                            . "• *Nama:* {$nama}\n"
                            . "• *No. WhatsApp:* {$noWa}\n"
                            . "• *Email:* {$email}\n\n"
                            . "Jika Anda tidak merasa melakukan perubahan data ini, segera hubungi Administrator.";
                send_whatsapp_notification($noWa, 'profile_updated', $profileMsg, $userId);

                flash_set('success', 'Data profil Anda berhasil diperbarui.');
            }
        }
    } elseif ($action === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($oldPass) || empty($newPass) || empty($confirmPass)) {
            flash_set('danger', 'Seluruh kolom kata sandi wajib diisi.');
        } elseif (strlen($newPass) < 6) {
            flash_set('danger', 'Kata sandi baru minimal 6 karakter.');
        } elseif ($newPass !== $confirmPass) {
            flash_set('danger', 'Konfirmasi kata sandi baru tidak cocok.');
        } else {
            // Verify old password
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($oldPass, $hash)) {
                flash_set('danger', 'Kata sandi lama yang Anda masukkan salah.');
            } else {
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newHash, $userId]);

                // Kirim notifikasi WhatsApp peringatan ubah kata sandi
                $passMsg = "🔐 *PERINGATAN KEAMANAN: KATA SANDI DIUBAH*\n\n"
                         . "Halo {$user['nama_lengkap']},\n"
                         . "Kata sandi akun portal absensi Anda telah berhasil diubah pada " . date('d/m/Y H:i') . " WIB.\n\n"
                         . "Gunakan kata sandi baru untuk login selanjutnya. Jika Anda tidak merasa melakukan tindakan ini, segera hubungi Administrator untuk mengamankan akun Anda.";
                send_whatsapp_notification($user['no_wa'], 'password_changed', $passMsg, $userId);

                flash_set('success', 'Kata sandi berhasil diubah! Gunakan kata sandi baru untuk login selanjutnya.');
            }
        }
    }

    header('Location: ' . BASE_URL . '/profile.php');
    exit;
}

// Refresh current user info
$user = current_user();

require_once __DIR__ . '/views/layouts/header.php';
if ($user['role'] === 'admin') {
    require_once __DIR__ . '/views/layouts/sidebar_admin.php';
} else {
    require_once __DIR__ . '/views/layouts/sidebar_employee.php';
}
require_once __DIR__ . '/views/layouts/top_navbar.php';
?>

<div style="max-width: 680px; margin: 0 auto;">

    <!-- ========================================================
         PROFILE HERO BANNER (MATCHES REFERENCE MOCKUP SCREEN 6)
         ======================================================== -->
    <div class="card" style="text-align: center; padding: 32px 20px 24px; margin-bottom: 24px; position: relative; overflow: hidden;">
        <!-- Avatar Circle with Soft Gradient Glow -->
        <div style="width: 86px; height: 86px; border-radius: 50%; background: var(--grad-punch-in); color: #FFFFFF; font-size: 2.2rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 12px 30px rgba(79, 70, 229, 0.35); border: 4px solid #FFFFFF; margin-bottom: 12px;">
            <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
        </div>

        <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.01em;">
            <?= clean_input($user['nama_lengkap']) ?>
        </h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500; margin-top: 2px;">
            <?= clean_input($user['jabatan']) ?> &bull; NIK: <strong><?= clean_input($user['nik']) ?></strong>
        </p>

        <!-- 4 Colorful Quick-Action Shortcut Cards (Screen 6 in Reference) -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 24px;">
            <?php if ($user['role'] === 'karyawan'): ?>
                <a href="<?= BASE_URL ?>/employee_dashboard.php#punch" style="background: #F8FAFD; border-radius: var(--radius-lg); padding: 14px 6px; display: flex; flex-direction: column; align-items: center; gap: 8px; box-shadow: var(--shadow-soft);">
                    <div style="width: 38px; height: 38px; border-radius: 12px; background: var(--grad-accent-blue); display: flex; align-items: center; justify-content: center; color: #FFFFFF;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                    <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-dark);">Presensi</span>
                </a>

                <a href="<?= BASE_URL ?>/employee_history.php" style="background: #F8FAFD; border-radius: var(--radius-lg); padding: 14px 6px; display: flex; flex-direction: column; align-items: center; gap: 8px; box-shadow: var(--shadow-soft);">
                    <div style="width: 38px; height: 38px; border-radius: 12px; background: var(--grad-accent-orange); display: flex; align-items: center; justify-content: center; color: #FFFFFF;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                    <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-dark);">Riwayat</span>
                </a>

                <a href="<?= BASE_URL ?>/employee_leaves.php" style="background: #F8FAFD; border-radius: var(--radius-lg); padding: 14px 6px; display: flex; flex-direction: column; align-items: center; gap: 8px; box-shadow: var(--shadow-soft);">
                    <div style="width: 38px; height: 38px; border-radius: 12px; background: var(--grad-accent-purple); display: flex; align-items: center; justify-content: center; color: #FFFFFF;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    </div>
                    <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-dark);">Izin/Sakit</span>
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/admin_dashboard.php" style="background: #F8FAFD; border-radius: var(--radius-lg); padding: 14px 6px; display: flex; flex-direction: column; align-items: center; gap: 8px; box-shadow: var(--shadow-soft);">
                    <div style="width: 38px; height: 38px; border-radius: 12px; background: var(--grad-accent-blue); display: flex; align-items: center; justify-content: center; color: #FFFFFF;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    </div>
                    <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-dark);">Dashboard</span>
                </a>

                <a href="<?= BASE_URL ?>/admin_attendance.php" style="background: #F8FAFD; border-radius: var(--radius-lg); padding: 14px 6px; display: flex; flex-direction: column; align-items: center; gap: 8px; box-shadow: var(--shadow-soft);">
                    <div style="width: 38px; height: 38px; border-radius: 12px; background: var(--grad-accent-orange); display: flex; align-items: center; justify-content: center; color: #FFFFFF;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                    <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-dark);">Monitoring</span>
                </a>

                <a href="<?= BASE_URL ?>/admin_reports.php" style="background: #F8FAFD; border-radius: var(--radius-lg); padding: 14px 6px; display: flex; flex-direction: column; align-items: center; gap: 8px; box-shadow: var(--shadow-soft);">
                    <div style="width: 38px; height: 38px; border-radius: 12px; background: var(--grad-accent-purple); display: flex; align-items: center; justify-content: center; color: #FFFFFF;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline></svg>
                    </div>
                    <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-dark);">Laporan</span>
                </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/logout.php" style="background: #F8FAFD; border-radius: var(--radius-lg); padding: 14px 6px; display: flex; flex-direction: column; align-items: center; gap: 8px; box-shadow: var(--shadow-soft);">
                <div style="width: 38px; height: 38px; border-radius: 12px; background: var(--grad-accent-teal); display: flex; align-items: center; justify-content: center; color: #FFFFFF;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                </div>
                <span style="font-size: 0.72rem; font-weight: 700; color: #EF4444;">Keluar</span>
            </a>
        </div>
    </div>

    <!-- Edit Profile Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Informasi Pribadi</h2>
            <span class="badge badge-blue"><?= strtoupper($user['role']) ?></span>
        </div>

        <form action="<?= BASE_URL ?>/profile.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label class="form-label">Nomor Induk Karyawan (NIK)</label>
                <input type="text" class="form-control" value="<?= clean_input($user['nik']) ?>" readonly style="background-color: #F8FAFC; cursor: not-allowed;">
                <div class="form-hint">NIK terkunci permanen untuk menjaga integritas data absensi.</div>
            </div>

            <div class="form-group">
                <label class="form-label">Jabatan / Posisi</label>
                <input type="text" class="form-control" value="<?= clean_input($user['jabatan']) ?>" readonly style="background-color: #F8FAFC; cursor: not-allowed;">
            </div>

            <div class="form-group">
                <label for="nama_lengkap" class="form-label required">Nama Lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" value="<?= clean_input($user['nama_lengkap']) ?>" required>
            </div>

            <div class="form-group">
                <label for="no_wa" class="form-label required">Nomor WhatsApp Aktif</label>
                <input type="text" id="no_wa" name="no_wa" class="form-control" value="<?= clean_input($user['no_wa']) ?>" required>
            </div>

            <div class="form-group">
                <label for="email" class="form-label required">Alamat Email</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= clean_input($user['email']) ?>" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;">
                Simpan Perubahan Profil
            </button>
        </form>
    </div>

    <!-- Change Password Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Keamanan & Kata Sandi</h2>
        </div>

        <form action="<?= BASE_URL ?>/profile.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label for="old_password" class="form-label required">Kata Sandi Saat Ini</label>
                <input type="password" id="old_password" name="old_password" class="form-control" placeholder="Masukkan kata sandi lama..." required>
            </div>

            <div class="form-group">
                <label for="new_password" class="form-label required">Kata Sandi Baru</label>
                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Minimal 6 karakter..." required>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label required">Ulangi Kata Sandi Baru</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Konfirmasi sandi baru..." required>
            </div>

            <button type="submit" class="btn btn-secondary" style="width: 100%; margin-top: 8px;">
                Perbarui Kata Sandi
            </button>
        </form>
    </div>

</div>

<?php
require_once __DIR__ . '/views/layouts/footer.php';
?>
