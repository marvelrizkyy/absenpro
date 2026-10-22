<?php
// admin_settings.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/date_helper.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';
require_once __DIR__ . '/config/fonnte.php';

require_admin();
$admin = current_user();
$adminId = (int)$admin['id'];
$db = get_db_connection();

// Process POST updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        flash_set('danger', 'Sesi keamanan tidak sah.');
        header('Location: ' . BASE_URL . '/admin_settings.php');
        exit;
    }

    if ($action === 'update_settings') {
        $checkInDeadline = trim($_POST['check_in_deadline'] ?? '08:00:00');
        $checkOutTime = trim($_POST['check_out_time'] ?? '17:00:00');
        $companyName = trim($_POST['company_name'] ?? 'PT Inovasi Digital Nusantara');
        $adminPhone = trim($_POST['admin_phone'] ?? '081234567890');
        $workDaysArray = $_POST['work_days'] ?? ['1','2','3','4','5','6'];
        $workDaysStr = implode(',', $workDaysArray);
        $fonnteToken = trim($_POST['fonnte_token'] ?? '');

        if (strlen($checkInDeadline) === 5) {
            $checkInDeadline .= ':00';
        }
        if (strlen($checkOutTime) === 5) {
            $checkOutTime .= ':00';
        }

        // Update database work_settings
        $stmt = $db->prepare("UPDATE work_settings SET 
            check_in_deadline = ?, 
            check_out_time = ?,
            work_days = ?, 
            company_name = ?, 
            admin_phone = ?, 
            updated_by = ?, 
            updated_at = NOW() 
            WHERE id = 1");
        $stmt->execute([$checkInDeadline, $checkOutTime, $workDaysStr, $companyName, $adminPhone, $adminId]);

        // Save Fonnte token & Admin Phone to .env file securely
        $envPath = BASE_PATH . '/.env';
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            if (!empty($fonnteToken)) {
                if (preg_match('/^FONNTE_TOKEN=.*$/m', $envContent)) {
                    $envContent = preg_replace('/^FONNTE_TOKEN=.*$/m', "FONNTE_TOKEN={$fonnteToken}", $envContent);
                } else {
                    $envContent .= "\nFONNTE_TOKEN={$fonnteToken}\n";
                }
            }
            if (!empty($adminPhone)) {
                if (preg_match('/^ADMIN_PHONE=.*$/m', $envContent)) {
                    $envContent = preg_replace('/^ADMIN_PHONE=.*$/m', "ADMIN_PHONE={$adminPhone}", $envContent);
                } else {
                    $envContent .= "\nADMIN_PHONE={$adminPhone}\n";
                }
            }
            file_put_contents($envPath, $envContent);
        }

        flash_set('success', 'Pengaturan operasional jam kerja dan sistem berhasil diperbarui.');
    } elseif ($action === 'add_holiday') {
        $holidayDate = $_POST['holiday_date'] ?? '';
        $holidayName = trim($_POST['holiday_name'] ?? '');

        if (!empty($holidayDate) && !empty($holidayName)) {
            $stmt = $db->prepare("INSERT INTO holidays (holiday_date, name, created_at) VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE name = VALUES(name)");
            $stmt->execute([$holidayDate, $holidayName]);
            flash_set('success', "Hari libur '{$holidayName}' ({$holidayDate}) berhasil ditambahkan.");
        }
    } elseif ($action === 'delete_holiday') {
        $holidayId = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM holidays WHERE id = ?")->execute([$holidayId]);
        flash_set('info', 'Hari libur berhasil dihapus.');
    }

    header('Location: ' . BASE_URL . '/admin_settings.php');
    exit;
}

// Fetch current settings
$stmtSet = $db->query("SELECT * FROM work_settings WHERE id = 1");
$settings = $stmtSet->fetch() ?: [
    'check_in_deadline' => '08:00:00',
    'work_days' => '1,2,3,4,5,6',
    'company_name' => 'PT Inovasi Digital Nusantara',
    'admin_phone' => '081234567890'
];
$activeDays = explode(',', $settings['work_days']);
$currentFonnteToken = get_fonnte_token();

// Fetch holidays
$stmtHolidays = $db->query("SELECT * FROM holidays ORDER BY holiday_date ASC");
$holidays = $stmtHolidays->fetchAll();

$pageTitle = 'Pengaturan Jam Kerja & Sistem';
$pageSubtitle = 'Konfigurasi batas waktu absensi, hari kerja, dan gateway Fonnte';

require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/sidebar_admin.php';
require_once __DIR__ . '/views/layouts/top_navbar.php';
?>

<div style="display: grid; grid-template-columns: 3fr 2fr; gap: 24px;">
    <!-- Work Settings Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Konfigurasi Operasional Jam Kerja</h2>
        </div>

        <form action="<?= BASE_URL ?>/admin_settings.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_settings">

            <div class="form-group">
                <label for="company_name" class="form-label required">Nama Perusahaan / Organisasi</label>
                <input type="text" id="company_name" name="company_name" class="form-control" value="<?= clean_input($settings['company_name']) ?>" required>
                <div class="form-hint">Ditampilkan pada kop laporan cetak dan header notifikasi WhatsApp.</div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label for="check_in_deadline" class="form-label required">Batas Jam Masuk Tepat Waktu</label>
                    <input type="time" id="check_in_deadline" name="check_in_deadline" step="1" class="form-control" value="<?= substr($settings['check_in_deadline'], 0, 5) ?>" required>
                    <div class="form-hint">Presensi masuk setelah jam ini otomatis berstatus Terlambat.</div>
                </div>

                <div class="form-group">
                    <label for="check_out_time" class="form-label required">Jam Pulang Kerja Standar</label>
                    <input type="time" id="check_out_time" name="check_out_time" step="1" class="form-control" value="<?= substr($settings['check_out_time'] ?? '17:00:00', 0, 5) ?>" required>
                    <div class="form-hint">Absen pulang sebelum jam ini wajib mengisi alasan & bukti.</div>
                </div>
            </div>

            <div class="form-group">
                <label for="admin_phone" class="form-label required">Nomor WhatsApp Admin</label>
                <input type="text" id="admin_phone" name="admin_phone" class="form-control" value="<?= clean_input($settings['admin_phone']) ?>" placeholder="081234567890" required>
                <div class="form-hint">Menerima alert presensi, keterlambatan, pulang awal, izin, dan seluruh tembusan notifikasi.</div>
            </div>

            <div class="form-group">
                <label class="form-label required">Hari Kerja Reguler</label>
                <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-top: 6px;">
                    <?php
                    $daysList = [
                        '1' => 'Senin', '2' => 'Selasa', '3' => 'Rabu',
                        '4' => 'Kamis', '5' => 'Jumat', '6' => 'Sabtu', '7' => 'Minggu'
                    ];
                    foreach ($daysList as $val => $name):
                        $checked = in_array((string)$val, $activeDays, true) ? 'checked' : '';
                    ?>
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 0.88rem; cursor: pointer;">
                            <input type="checkbox" name="work_days[]" value="<?= $val ?>" <?= $checked ?>>
                            <span><?= $name ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="form-hint">Hari yang tidak dicentang otomatis dianggap hari libur mingguan.</div>
            </div>

            <div class="form-group" style="margin-top: 24px; padding-top: 18px; border-top: 1px dashed var(--border-light);">
                <label for="fonnte_token" class="form-label">Token API Fonnte WhatsApp</label>
                <input type="text" id="fonnte_token" name="fonnte_token" class="form-control" value="<?= ($currentFonnteToken !== 'YOUR_FONNTE_API_TOKEN_HERE') ? clean_input($currentFonnteToken) : '' ?>" placeholder="Masukkan token akun Fonnte Anda...">
                <div class="form-hint">Dapatkan token di dashboard <a href="https://fonnte.com" target="_blank">fonnte.com</a>. Jika dikosongkan/default, sistem menggunakan simulasi log.</div>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                Simpan Perubahan Pengaturan
            </button>
        </form>
    </div>

    <!-- National Holidays Management -->
    <div>
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h2 class="card-title">Tambah Hari Libur Nasional</h2>
            </div>

            <form action="<?= BASE_URL ?>/admin_settings.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_holiday">

                <div class="form-group">
                    <label for="holiday_date" class="form-label required">Tanggal Libur</label>
                    <input type="date" id="holiday_date" name="holiday_date" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="holiday_name" class="form-label required">Keterangan Hari Libur</label>
                    <input type="text" id="holiday_name" name="holiday_name" class="form-control" placeholder="Contoh: Hari Kemerdekaan RI" required>
                </div>

                <button type="submit" class="btn btn-secondary" style="width: 100%;">
                    + Tambah ke Kalender Libur
                </button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Daftar Hari Libur Terdaftar</h2>
                <span class="badge badge-secondary"><?= count($holidays) ?> Libur</span>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($holidays)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 20px; color: var(--text-muted); font-size: 0.8rem;">
                                    Belum ada kalender hari libur terdaftar.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($holidays as $h): ?>
                                <tr>
                                    <td style="font-size: 0.82rem; white-space: nowrap;">
                                        <strong><?= format_short_date($h['holiday_date']) ?></strong>
                                    </td>
                                    <td style="font-size: 0.82rem;"><?= clean_input($h['name']) ?></td>
                                    <td style="text-align: right;">
                                        <form action="<?= BASE_URL ?>/admin_settings.php" method="POST" style="display: inline;" onsubmit="return confirm('Hapus hari libur ini?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_holiday">
                                            <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" style="padding: 2px 6px; font-size: 0.72rem;">✕</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/views/layouts/footer.php';
?>
