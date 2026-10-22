<?php
// admin_employee_detail.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/date_helper.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';

require_admin();
$db = get_db_connection();

$employeeId = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$employeeId]);
$emp = $stmt->fetch();

if (!$emp) {
    flash_set('danger', 'Data karyawan tidak ditemukan.');
    header('Location: ' . BASE_URL . '/admin_employees.php');
    exit;
}

// Attendance summary stats
$stmtStats = $db->prepare("SELECT 
    COUNT(id) as total_hadir,
    COUNT(CASE WHEN status = 'hadir_tepat_waktu' THEN 1 END) as tepat_waktu,
    COUNT(CASE WHEN status = 'terlambat' THEN 1 END) as terlambat,
    COALESCE(SUM(late_minutes), 0) as total_late_minutes
    FROM attendances WHERE employee_id = ?");
$stmtStats->execute([$employeeId]);
$stats = $stmtStats->fetch();

// Recent attendances
$stmtAtt = $db->prepare("SELECT * FROM attendances WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 15");
$stmtAtt->execute([$employeeId]);
$attendances = $stmtAtt->fetchAll();

// Leave requests
$stmtLeaves = $db->prepare("SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY created_at DESC");
$stmtLeaves->execute([$employeeId]);
$leaves = $stmtLeaves->fetchAll();

// Attendance corrections audit for this employee
$stmtCorr = $db->prepare("SELECT ac.*, a.attendance_date, u.nama_lengkap as admin_name 
    FROM attendance_corrections ac
    JOIN attendances a ON ac.attendance_id = a.id
    JOIN users u ON ac.corrected_by = u.id
    WHERE a.employee_id = ?
    ORDER BY ac.corrected_at DESC");
$stmtCorr->execute([$employeeId]);
$corrections = $stmtCorr->fetchAll();

$pageTitle = 'Detail Profil Karyawan: ' . $emp['nama_lengkap'];
$pageSubtitle = 'NIK: ' . $emp['nik'] . ' — ' . $emp['jabatan'];

require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/sidebar_admin.php';
require_once __DIR__ . '/views/layouts/top_navbar.php';
?>

<div style="margin-bottom: 20px;">
    <a href="<?= BASE_URL ?>/admin_employees.php" class="btn btn-sm btn-secondary">
        &larr; Kembali ke Daftar Karyawan
    </a>
</div>

<!-- Employee Header Card -->
<div class="card" style="margin-bottom: 28px;">
    <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
        <div style="width: 72px; height: 72px; border-radius: var(--radius-lg); background: linear-gradient(135deg, var(--navy-800), var(--navy-900)); color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800;">
            <?= strtoupper(substr($emp['nama_lengkap'], 0, 1)) ?>
        </div>
        <div style="flex: 1; min-width: 240px;">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--navy-900);"><?= clean_input($emp['nama_lengkap']) ?></h2>
                <span class="badge <?= $emp['account_status'] === 'disetujui' ? 'badge-success' : 'badge-warning' ?>">
                    <?= strtoupper($emp['account_status']) ?>
                </span>
                <?php if ($emp['is_active'] != 1): ?>
                    <span class="badge badge-danger">DIARSIPKAN</span>
                <?php endif; ?>
            </div>
            <div style="font-size: 0.88rem; color: var(--text-muted); margin-top: 4px;">
                <strong>NIK:</strong> <?= clean_input($emp['nik']) ?> &bull; 
                <strong>Jabatan:</strong> <?= clean_input($emp['jabatan']) ?> &bull; 
                <strong>WhatsApp:</strong> <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $emp['no_wa']) ?>" target="_blank" style="color: var(--success-600);"><?= clean_input($emp['no_wa']) ?></a> &bull; 
                <strong>Email:</strong> <?= clean_input($emp['email']) ?>
            </div>
        </div>

        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <form action="<?= BASE_URL ?>/actions/do_admin_action.php" method="POST" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin <?= $emp['is_active'] == 1 ? 'mengarsipkan' : 'mengaktifkan kembali' ?> karyawan <?= clean_input($emp['nama_lengkap']) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_archive_user">
                <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                <button type="submit" class="btn btn-sm <?= $emp['is_active'] == 1 ? 'btn-secondary' : 'btn-primary' ?>" title="<?= $emp['is_active'] == 1 ? 'Arsipkan Karyawan' : 'Aktifkan Kembali' ?>">
                    <?= $emp['is_active'] == 1 ? 'Arsipkan' : 'Aktifkan' ?>
                </button>
            </form>

            <form action="<?= BASE_URL ?>/actions/do_admin_action.php" method="POST" style="display: inline;" onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin MENGHAPUS PERMANEN data karyawan <?= addslashes(clean_input($emp['nama_lengkap'])) ?> (NIK: <?= clean_input($emp['nik']) ?>)?\n\nSemua riwayat kehadiran, absensi, dan pengajuan izin karyawan ini akan ikut terhapus selamanya.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger" title="Hapus Data Karyawan Secara Permanen">
                    🗑️ Hapus Karyawan
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Lifetime Statistics -->
<div class="stat-grid">
    <div class="stat-card">
        <div>
            <div class="stat-label">Total Kehadiran</div>
            <div class="stat-value"><?= (int)$stats['total_hadir'] ?></div>
            <div class="stat-subtext">Catatan hadir tercatat</div>
        </div>
        <div class="stat-icon-wrapper">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
    </div>

    <div class="stat-card stat-success">
        <div>
            <div class="stat-label">Hadir Tepat Waktu</div>
            <div class="stat-value"><?= (int)$stats['tepat_waktu'] ?></div>
            <div class="stat-subtext">Sebelum batas jam masuk</div>
        </div>
        <div class="stat-icon-wrapper">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div>
            <div class="stat-label">Terlambat</div>
            <div class="stat-value"><?= (int)$stats['terlambat'] ?></div>
            <div class="stat-subtext">Total <?= (int)$stats['total_late_minutes'] ?> menit terlambat</div>
        </div>
        <div class="stat-icon-wrapper">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
    </div>

    <div class="stat-card stat-purple">
        <div>
            <div class="stat-label">Pengajuan Izin/Sakit</div>
            <div class="stat-value"><?= count($leaves) ?></div>
            <div class="stat-subtext">Total permohonan tercatat</div>
        </div>
        <div class="stat-icon-wrapper">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
        </div>
    </div>
</div>

<!-- Recent Attendances Table -->
<div class="card" style="margin-bottom: 28px;">
    <div class="card-header">
        <h2 class="card-title">15 Catatan Presensi Terakhir</h2>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jam Masuk</th>
                    <th>Jam Pulang</th>
                    <th>Status Presensi</th>
                    <th>Keterlambatan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attendances)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 28px; color: var(--text-muted);">
                            Belum ada catatan presensi untuk karyawan ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($attendances as $att): ?>
                        <tr>
                            <td><strong><?= format_indo_date($att['attendance_date']) ?></strong></td>
                            <td><?= format_time($att['check_in']) ?></td>
                            <td><?= format_time($att['check_out']) ?></td>
                            <td>
                                <?php if ($att['status'] === 'hadir_tepat_waktu'): ?>
                                    <span class="badge badge-success">Tepat Waktu</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Terlambat</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $att['late_minutes'] > 0 ? clean_input((string)$att['late_minutes']) . ' Menit' : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Attendance Corrections Audit Trail -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Histori Audit Koreksi Presensi</h2>
        <span class="badge badge-secondary"><?= count($corrections) ?> Koreksi Tercatat</span>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu Koreksi</th>
                    <th>Tanggal Presensi</th>
                    <th>Nilai Lama</th>
                    <th>Nilai Baru</th>
                    <th>Alasan Koreksi (Wajib)</th>
                    <th>Admin Eksekutor</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($corrections)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 28px; color: var(--text-muted);">
                            Belum ada riwayat koreksi absensi untuk karyawan ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($corrections as $corr): ?>
                        <?php
                        $oldV = json_decode($corr['old_values'], true);
                        $newV = json_decode($corr['new_values'], true);
                        ?>
                        <tr>
                            <td style="font-size: 0.8rem;"><?= format_indo_date(substr($corr['corrected_at'], 0, 10)) ?> <?= substr($corr['corrected_at'], 11, 5) ?> WIB</td>
                            <td><strong><?= format_indo_date($corr['attendance_date']) ?></strong></td>
                            <td style="font-size: 0.78rem; color: var(--danger-600);">
                                Masuk: <?= $oldV['check_in'] ?? '-' ?><br>
                                Pulang: <?= $oldV['check_out'] ?? '-' ?><br>
                                Status: <?= $oldV['status'] ?? '-' ?>
                            </td>
                            <td style="font-size: 0.78rem; color: var(--success-600);">
                                Masuk: <?= $newV['check_in'] ?? '-' ?><br>
                                Pulang: <?= $newV['check_out'] ?? '-' ?><br>
                                Status: <?= $newV['status'] ?? '-' ?>
                            </td>
                            <td style="font-size: 0.82rem; font-weight: 500;">
                                <?= clean_input($corr['reason']) ?>
                            </td>
                            <td>
                                <strong><?= clean_input($corr['admin_name']) ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/views/layouts/footer.php';
?>
