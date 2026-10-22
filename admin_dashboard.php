<?php
// admin_dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/date_helper.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';

require_admin();
$admin = current_user();
$db = get_db_connection();

$today = date('Y-m-d');

// 1. Total active employees
$stmtActive = $db->query("SELECT COUNT(*) FROM users WHERE role = 'karyawan' AND account_status = 'disetujui' AND is_active = 1");
$totalActiveEmployees = (int)$stmtActive->fetchColumn();

// 2. Pending registrations
$stmtPendingUsers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'karyawan' AND account_status = 'menunggu'");
$totalPendingUsers = (int)$stmtPendingUsers->fetchColumn();

// 3. Pending leave requests
$stmtPendingLeaves = $db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'menunggu'");
$totalPendingLeaves = (int)$stmtPendingLeaves->fetchColumn();

// 4. Today's attendances
$stmtTodayAtt = $db->prepare("SELECT 
    COUNT(CASE WHEN a.status = 'hadir_tepat_waktu' THEN 1 END) as tepat_waktu,
    COUNT(CASE WHEN a.status = 'terlambat' THEN 1 END) as terlambat,
    COUNT(CASE WHEN a.check_out IS NOT NULL THEN 1 END) as sudah_pulang,
    COUNT(a.id) as total_hadir
    FROM attendances a
    JOIN users u ON a.employee_id = u.id
    WHERE a.attendance_date = ? AND u.is_active = 1");
$stmtTodayAtt->execute([$today]);
$todayStats = $stmtTodayAtt->fetch();

$totalHadir = (int)$todayStats['total_hadir'];
$tepatWaktu = (int)$todayStats['tepat_waktu'];
$terlambat = (int)$todayStats['terlambat'];
$sudahPulang = (int)$todayStats['sudah_pulang'];

// 5. Approved leave today
$stmtTodayLeaves = $db->prepare("SELECT COUNT(*) FROM leave_requests 
    WHERE status = 'disetujui' AND ? BETWEEN start_date AND end_date");
$stmtTodayLeaves->execute([$today]);
$totalIzinSakitHariIni = (int)$stmtTodayLeaves->fetchColumn();

// 6. Belum hadir calculation
$belumHadir = max(0, $totalActiveEmployees - $totalHadir - $totalIzinSakitHariIni);

// 7. Today's late employees list
$stmtLateList = $db->prepare("SELECT a.*, u.nik, u.nama_lengkap, u.jabatan, u.no_wa 
    FROM attendances a
    JOIN users u ON a.employee_id = u.id
    WHERE a.attendance_date = ? AND a.status = 'terlambat'
    ORDER BY a.check_in ASC LIMIT 6");
$stmtLateList->execute([$today]);
$lateEmployees = $stmtLateList->fetchAll();

// 8. Recent 5 pending employee registrations for quick action
$stmtRecentPendingUsers = $db->query("SELECT * FROM users WHERE role = 'karyawan' AND account_status = 'menunggu' ORDER BY created_at DESC LIMIT 5");
$pendingUsersList = $stmtRecentPendingUsers->fetchAll();

// 9. Recent 5 pending leaves
$stmtRecentPendingLeaves = $db->query("SELECT lr.*, u.nama_lengkap, u.jabatan, u.nik 
    FROM leave_requests lr
    JOIN users u ON lr.employee_id = u.id
    WHERE lr.status = 'menunggu'
    ORDER BY lr.created_at DESC LIMIT 5");
$pendingLeavesList = $stmtRecentPendingLeaves->fetchAll();

$pageTitle = 'Dashboard Analitik & Pemantauan';
$pageSubtitle = 'Ringkasan operasional presensi hari ini: ' . format_indo_date($today);

require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/sidebar_admin.php';
require_once __DIR__ . '/views/layouts/top_navbar.php';
?>

<!-- Metric Cards Grid -->
<div class="stat-grid">
    <div class="stat-card">
        <div>
            <div class="stat-label">Total Karyawan Aktif</div>
            <div class="stat-value"><?= $totalActiveEmployees ?></div>
            <div class="stat-subtext">Terdaftar & aktif</div>
        </div>
        <div class="stat-icon-wrapper">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        </div>
    </div>

    <div class="stat-card stat-success">
        <div>
            <div class="stat-label">Hadir Hari Ini</div>
            <div class="stat-value"><?= $totalHadir ?></div>
            <div class="stat-subtext"><?= $tepatWaktu ?> Tepat Waktu | <?= $terlambat ?> Terlambat</div>
        </div>
        <div class="stat-icon-wrapper">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div>
            <div class="stat-label">Belum Check-In</div>
            <div class="stat-value"><?= $belumHadir ?></div>
            <div class="stat-subtext"><?= $totalIzinSakitHariIni ?> Karyawan Izin/Sakit</div>
        </div>
        <div class="stat-icon-wrapper">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
    </div>

    <div class="stat-card stat-purple">
        <div>
            <div class="stat-label">Pengajuan Menunggu</div>
            <div class="stat-value"><?= $totalPendingLeaves + $totalPendingUsers ?></div>
            <div class="stat-subtext"><?= $totalPendingLeaves ?> Izin/Sakit, <?= $totalPendingUsers ?> Akun Baru</div>
        </div>
        <div class="stat-icon-wrapper">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line></svg>
        </div>
    </div>
</div>

<!-- Pending Approvals Banner (If Any) -->
<?php if ($totalPendingUsers > 0 || $totalPendingLeaves > 0): ?>
    <div style="background: linear-gradient(90deg, #FEF3C7, #FFFBEB); border: 1px solid #FCD34D; border-radius: var(--radius-lg); padding: 18px 24px; margin-bottom: 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 44px; height: 44px; background: #F59E0B; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #FFFFFF; font-weight: 800; font-size: 1.2rem;">
                !
            </div>
            <div>
                <h3 style="font-size: 1rem; font-weight: 700; color: #92400E;">Perhatian: Terdapat Item Menunggu Tindakan Anda</h3>
                <p style="font-size: 0.82rem; color: #B45309; margin-top: 2px;">
                    <?= $totalPendingUsers ?> pendaftaran akun baru &bull; <?= $totalPendingLeaves ?> permohonan izin/sakit memerlukan keputusan HR.
                </p>
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <?php if ($totalPendingUsers > 0): ?>
                <a href="<?= BASE_URL ?>/admin_employees.php?status=menunggu" class="btn btn-sm btn-primary">
                    Verifikasi Karyawan (<?= $totalPendingUsers ?>)
                </a>
            <?php endif; ?>
            <?php if ($totalPendingLeaves > 0): ?>
                <a href="<?= BASE_URL ?>/admin_leaves.php?status=menunggu" class="btn btn-sm btn-warning">
                    Tinjau Izin/Sakit (<?= $totalPendingLeaves ?>)
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Late Employees Today Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Karyawan Terlambat Hari Ini</h2>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Presensi masuk melewati batas 08:00 WIB</div>
            </div>
            <span class="badge badge-warning"><?= count($lateEmployees) ?> Terlambat</span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th>Jam Masuk</th>
                        <th>Keterlambatan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lateEmployees)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 32px; color: var(--text-muted);">
                                👏 Tidak ada karyawan yang terlambat hari ini. Semua hadir tepat waktu!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lateEmployees as $late): ?>
                            <tr>
                                <td>
                                    <strong><?= clean_input($late['nama_lengkap']) ?></strong>
                                    <div style="font-size: 0.74rem; color: var(--text-muted);"><?= clean_input($late['jabatan']) ?> (<?= clean_input($late['nik']) ?>)</div>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: var(--warning-600);"><?= format_time($late['check_in']) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-warning">+<?= (int)$late['late_minutes'] ?> Menit</span>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/admin_attendance.php?date=<?= $today ?>" class="btn btn-sm btn-secondary" title="Koreksi data">
                                        ✏️ Koreksi
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Registration Verification Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Pendaftaran Akun Menunggu</h2>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Calon karyawan baru yang mendaftar secara publik</div>
            </div>
            <a href="<?= BASE_URL ?>/admin_employees.php?status=menunggu" class="btn btn-sm btn-secondary">
                Lihat Semua &rarr;
            </a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama & NIK</th>
                        <th>Jabatan</th>
                        <th>Waktu Daftar</th>
                        <th>Persetujuan Cepat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pendingUsersList)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 32px; color: var(--text-muted);">
                                Tidak ada antrean pendaftaran karyawan baru saat ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pendingUsersList as $pu): ?>
                            <tr>
                                <td>
                                    <strong><?= clean_input($pu['nama_lengkap']) ?></strong>
                                    <div style="font-size: 0.74rem; color: var(--text-muted);"><?= clean_input($pu['nik']) ?></div>
                                </td>
                                <td><?= clean_input($pu['jabatan']) ?></td>
                                <td style="font-size: 0.75rem; color: var(--text-muted);">
                                    <?= format_short_date($pu['created_at']) ?>
                                </td>
                                <td>
                                    <form action="<?= BASE_URL ?>/actions/do_admin_action.php" method="POST" style="display: inline-block;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="approve_user">
                                        <input type="hidden" name="id" value="<?= $pu['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Setujui akun <?= clean_input($pu['nama_lengkap']) ?>?')">
                                            ✓ Setujui
                                        </button>
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

<?php
require_once __DIR__ . '/views/layouts/footer.php';
?>
